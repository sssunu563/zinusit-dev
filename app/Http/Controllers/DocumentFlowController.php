<?php

namespace App\Http\Controllers;

use App\Models\ActionLog;
use App\Models\Stb;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\SnipeItService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use App\Traits\DocumentCheckoutTrait;

abstract class DocumentFlowController extends Controller
{
    use DocumentCheckoutTrait;
    protected const SIGNATURE_ROLE_FIELDS = [
        'it_drafter' => [
            'path' => 'it_drafter_signature_path',
            'signed_at' => 'it_drafter_signed_at',
        ],
        'it_checker' => [
            'path' => 'it_checker_signature_path',
            'signed_at' => 'it_checker_signed_at',
        ],
        'it_approved' => [
            'path' => 'it_approved_signature_path',
            'signed_at' => 'it_approved_signed_at',
        ],
        'requester_received' => [
            'path' => 'requester_received_signature_path',
            'signed_at' => 'requester_received_signed_at',
        ],
        'requester_dept_head' => [
            'path' => 'requester_dept_head_signature_path',
            'signed_at' => 'requester_dept_head_signed_at',
        ],
    ];

    protected const PDF_BROWSER_PATHS = [
        'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe',
        'C:\Program Files\Google\Chrome\Application\chrome.exe',
        '/usr/bin/microsoft-edge',
        '/usr/bin/microsoft-edge-stable',
        '/usr/bin/google-chrome',
        '/usr/bin/google-chrome-stable',
        '/usr/bin/chromium',
        '/usr/bin/chromium-browser',
    ];

    /** Per-request memoization caches to avoid repeated DB/API calls */
    private array $schemaCache     = [];
    private array $locationCache   = [];
    private array $userLabelCache  = [];


    protected function isLoanDocument(mixed $stb): bool
    {
        return $this->resolveDocumentType($stb) === 'loan';
    }

    public function __construct(
        protected readonly SnipeItService $snipe,
    ) {
    }

    protected function buildGroupParts(?int $groupId): array
    {
        if (!$groupId) {
            return ['company' => '-', 'location' => '-', 'department' => '-'];
        }

        if (!isset($this->locationCache[$groupId])) {
            $location = $this->snipe->getLocation($groupId);
            $label    = trim((string) data_get($location, 'name', ''));
            $this->locationCache[$groupId] = $label !== '' ? $label : 'Location ' . $groupId;
        }

        return [
            'company'    => '-',
            'location'   => $this->locationCache[$groupId],
            'department' => '-',
        ];
    }

    protected function formatUserLabel(?int $userId): string
    {
        if (!$userId) {
            return '-';
        }

        if (!isset($this->userLabelCache[$userId])) {
            $user      = $this->snipe->getUser($userId);
            $firstName = trim((string) data_get($user, 'first_name', ''));
            $lastName  = trim((string) data_get($user, 'last_name', ''));
            $fullName  = trim($firstName . ' ' . $lastName);
            $this->userLabelCache[$userId] = $fullName !== ''
                ? $fullName
                : ((string) data_get($user, 'name', 'User #' . $userId) ?: 'User #' . $userId);
        }

        return $this->userLabelCache[$userId];
    }

    protected function resolveItemAssetLabel(mixed $item): string
    {
        $snapshotReference = trim((string) data_get($item, 'asset_reference_snapshot', ''));

        if ($snapshotReference !== '') {
            return $snapshotReference;
        }

        $inventoryNumber = trim((string) data_get($item, 'inventory_number', ''));

        if ($inventoryNumber !== '') {
            return $inventoryNumber;
        }

        $serialNumber = trim((string) data_get($item, 'serial_no', ''));

        if ($serialNumber !== '') {
            return $serialNumber;
        }

        $itemName = trim((string) data_get($item, 'nama', ''));

        if ($itemName !== '') {
            return $itemName;
        }

        $assetId = data_get($item, 'snipeit_asset_id') ?: data_get($item, 'computer_id');

        return $assetId ? 'Asset #' . $assetId : '-';
    }

    protected function buildItemSnapshotPayload(array $item): array
    {
        $reference = trim((string) ($item['asset_reference_snapshot'] ?? $item['inventory_number'] ?? ''));
        $category = $item['kategori'] ?? 'assets';
        $computerId = $item['computer_id'] ?? null;

        if (($computerId === null || $computerId === '') && ($category === 'assets' || $category === 'hardware')) {
            $computerId = $item['snipeit_asset_id'] ?? $item['computer_id'] ?? null;
        }

        return [
            'nama' => $item['nama'],
            'kategori' => $category,
            'type' => $item['type'],
            'jumlah' => $item['jumlah'],
            'serial_no' => $item['serialNo'] ?? '',
            'inventory_number' => $item['inventory_number'] ?? null,
            'computer_id' => $computerId,
            'snipeit_asset_id' => $item['snipeit_asset_id'] ?? $computerId ?? null,
            'asset_reference_snapshot' => $reference !== '' ? $reference : null,
            'condition' => $item['condition'] ?? 'Good',
        ];
    }

    protected function hasCompletionColumns(string $table = 'stbs'): bool
    {
        return Schema::hasColumns($table, ['completed_at', 'completed_pdf_path']);
    }


    protected function finalizeDocumentCompletion(mixed $stb, ?string $pdfPath): void
    {
        Log::info('Finalizing document completion...', ['stb_id' => $stb->id]);

        // 1. Log activity to local database for history
        $this->logStbCompletion($stb);

        // 2. Upload completed PDF to Snipe-IT (User and Assets)
        if ($pdfPath) {
            $this->uploadStbPdfToSnipeItems($stb, $pdfPath);
        }

        // 2.1 Upload Evidence Photos to Snipe-IT for problematic assets
        $this->uploadStbEvidenceToSnipeItems($stb);

        // 3. Flush Snipe-IT cache for the recipient user and all involved assets
        $this->flushSnipeCacheForDocument($stb);

        // 4. Auto-trigger Service if damage is detected in Return movement
        if ($this->resolveMovementType($stb) === 'return') {
            if ($this->detectDamage($stb)) {
                $this->triggerServiceDraft($stb);
                $this->notifyTeamsOfHighSeverity($stb);
            }
        }
    }

    protected function flushSnipeCacheForDocument(mixed $stb): void
    {
        if ($stb->user_id) {
            $this->snipe->flushCacheForUser((int) $stb->user_id);
        }
        foreach ($stb->items as $item) {
            $snipeAssetId = (int) ($item->snipeit_asset_id ?? 0);
            $itemType = $item->kategori ?? 'assets';
            if ($snipeAssetId > 0) {
                $this->snipe->flushCacheForAsset($itemType, $snipeAssetId);
            }
        }
    }

    protected function detectDamage(mixed $stb): bool

    {
        // 1. Check explicit condition field in items
        foreach ($stb->items as $item) {
            $condition = strtolower($item->condition ?? '');
            if (in_array($condition, ['broken', 'rusak', 'missing', 'hilang'])) {
                return true;
            }
        }

        // 2. Check legacy remark keywords
        $damageKeywords = ['rusak', 'broken', 'service', 'perbaikan', 'mati', 'error', 'hilang', 'missing'];
        $remarkLower = strtolower($stb->remark ?? '');
        foreach ($damageKeywords as $keyword) {
            if (str_contains($remarkLower, $keyword)) {
                return true;
            }
        }

        return false;
    }

    protected function notifyTeamsOfHighSeverity(mixed $stb): void

    {
        $problematicItems = $stb->items->filter(fn($item) => in_array(strtolower($item->condition ?? ''), ['broken', 'rusak', 'missing', 'hilang']));
        
        if ($problematicItems->isEmpty()) return;

        $docId = $this->formatDocId($stb, $this->buildGroupParts($stb->group_id)['company']);
        $userName = $this->formatUserLabel($stb->user_id);
        $title = "🚨 High Severity Asset Report: " . ($this->resolveDocumentLabel($stb));
        $message = "Problematic items detected during asset return for **{$userName}**.";
        
        $facts = [
            'Document' => $docId,
            'User' => $userName,
            'Items Count' => $problematicItems->count() . ' item(s)',
            'Report Date' => now()->format('d M Y H:i'),
        ];

        foreach ($problematicItems as $index => $item) {
            $facts["Item #" . ($index + 1)] = "{$item->nama} ({$item->condition}) - SN: " . ($item->serial_no ?: '-');
        }

        NotificationService::sendToTeams($title, $message, 'A24432', $facts);

        // Also send email for high severity
        $emailBody = "{$title}\n\n{$message}\n\nDetails:\n";
        foreach ($facts as $label => $value) {
            $emailBody .= "- {$label}: {$value}\n";
        }
        NotificationService::sendEmail($title, $emailBody);
    }

    protected function triggerServiceDraft(mixed $returnDoc): void

    {
        try {
            DB::transaction(function () use ($returnDoc) {
                $serviceDoc = Stb::create([
                    'document_type' => 'service',
                    'movement_type' => 'out',
                    'status' => 4, // Maintenance/Service status in legacy
                    'group_id' => $returnDoc->group_id,
                    'user_id' => $returnDoc->user_id,
                    'deliver_date' => now(),
                    'remark' => "AUTO-GENERATED FROM RETURN #{$returnDoc->id}: " . ($returnDoc->remark ?? ''),
                    'is_completed' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                foreach ($returnDoc->items as $item) {
                    $serviceDoc->items()->create([
                        'nama' => $item->nama,
                        'kategori' => $item->kategori,
                        'type' => $item->type,
                        'jumlah' => $item->jumlah,
                        'condition' => $item->condition,
                        'serial_no' => $item->serial_no,
                        'inventory_number' => $item->inventory_number,
                        'computer_id' => $item->computer_id,
                        'snipeit_asset_id' => $item->snipeit_asset_id,
                        'asset_reference_snapshot' => $item->asset_reference_snapshot,
                    ]);
                }
                
                Log::info("Auto-triggered Service draft #{$serviceDoc->id} from Return #{$returnDoc->id}");
            });
        } catch (\Throwable $e) {
            Log::error('Failed to trigger auto service draft', [
                'stb_id' => $returnDoc->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    protected function logStbCompletion(mixed $stb): void

    {
        try {
            $actorId = auth()->id();
            $groupParts = $stb->location_name ? null : $this->buildGroupParts($stb->group_id);
            $company = $stb->user_company ?: ($groupParts['company'] ?? '-');
            $docNo = $this->formatDocId($stb, $company);
            $documentName = $this->formatStbDocumentName($stb);
            $remark = trim((string) ($stb->remark ?? ''));
            $remarkSuffix = $remark !== '' ? " | Catatan: {$remark}" : '';
            
            // 1. Log for the User involved in STB
            $localUser = null;
            if ($stb->user_id) {
                $localUser = User::where('snipeit_user_id', $stb->user_id)->first();
            }

            if ($localUser) {
                ActionLog::create([
                    'user_id'     => $actorId,
                    'action_type' => 'stb_complete',
                    'item_type'   => get_class($stb),

                    'item_id'     => $stb->id,
                    'target_type' => User::class,
                    'target_id'   => $localUser->id,
                    'note'        => "{$documentName} selesai | Doc ID: {$docNo}{$remarkSuffix}",
                    'log_meta'    => [
                        'doc_no' => $docNo,
                        'document_name' => $documentName,
                        'movement_type' => $this->resolveMovementType($stb),
                        'pdf_path' => $stb->completed_pdf_path,
                    ]
                ]);
            }

            // 2. Log for each Item in the STB to show up in Asset history
            foreach ($stb->items as $item) {
                if ($item->snipeit_asset_id) {
                    $resource = match ($item->kategori ?: 'hardware') {
                        'assets', 'hardware' => 'assets',
                        'license', 'licenses' => 'license',
                        'accessories', 'accessory' => 'accessories',
                        'consumable', 'consumables' => 'consumable',
                        'component', 'components' => 'component',
                        default => 'assets'
                    };

                    ActionLog::create([
                        'user_id'      => $actorId,
                        'action_type'  => 'stb_complete',
                        'item_type'    => get_class($stb),

                        'item_id'      => $stb->id,
                        'snipeit_id'   => $item->snipeit_asset_id,
                        'snipeit_type' => $resource,
                        'note'         => "Aset disertakan dalam {$documentName} | Doc ID: {$docNo} | Serial: " . ($item->serial_no ?: '-') . $remarkSuffix,
                        'log_meta'     => [
                            'doc_no'    => $docNo,
                            'document_name' => $documentName,
                            'movement_type' => $this->resolveMovementType($stb),
                            'item_name' => $item->nama,
                            'pdf_path'  => $stb->completed_pdf_path,
                        ]
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('Failed to log STB completion activity', [
                'stb_id' => $stb->id,
                'error'  => $e->getMessage()
            ]);
        }
    }

    protected function uploadStbPdfToSnipeItems(mixed $stb, string $pdfPath): void

    {
        if (!$stb->items || $stb->items->isEmpty()) {
            return;
        }

        $absolutePath = Storage::disk('public')->path($pdfPath);

        if (!file_exists($absolutePath)) {
            Log::warning('STB PDF upload to Snipe-IT skipped: file not found.', [
                'stb_id' => $stb->id,
                'path'   => $absolutePath,
            ]);
            return;
        }

        // Compress the PDF to reduce size before uploading
        $this->compressPdfWithGhostscript($absolutePath);

        $content  = (string) file_get_contents($absolutePath);
        $groupParts = $stb->location_name ? null : $this->buildGroupParts($stb->group_id);
        $company = $stb->user_company ?: ($groupParts['company'] ?? '-');
        $docId = $this->formatDocId($stb, $company);
        $filename = $this->formatStbDocumentName($stb) . '.pdf';
        $documentName = pathinfo($filename, PATHINFO_FILENAME);
        
        $recipientName = $stb->user_name ?: $this->formatUserLabel($stb->user_id);

        $itemDescriptions = $stb->items->map(function($item) {
            $cat = $item->kategori ?: 'hardware';
            $sn = $item->serial_no ?: '-';
            return "{$item->nama} ({$cat} - {$sn})";
        })->implode(' & ');

        $remark = trim((string) ($stb->remark ?? ''));
        $remarkSuffix = $remark !== '' ? " | Catatan: {$remark}" : '';
        $uploadNotes = "{$documentName} | Doc ID: {$docId} | User: {$recipientName} | Items: {$itemDescriptions}{$remarkSuffix}";

        // Upload PDF to the User
        if ($stb->user_id) {
            try {
                $this->snipe->uploadFile('users', $stb->user_id, $content, $filename, $uploadNotes);
            } catch (\Throwable $e) {
                Log::error('Failed to upload STB PDF to Snipe-IT user', [
                    'stb_id' => $stb->id,
                    'user_id' => $stb->user_id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Upload PDF to each Snipe-IT item
        foreach ($stb->items as $item) {
            $cat = strtolower($item->kategori ?: 'hardware');
            $resource = match ($cat) {
                'hardware', 'assets', 'asset', 'hardware_assets' => 'hardware',
                'component', 'components' => 'components',
                'accessory', 'accessories' => 'accessories',
                'consumable', 'consumables' => 'consumables',
                'license', 'licenses' => 'licenses',
                default => null,
            };

            if ($resource && $item->snipeit_asset_id) {
                try {
                    $this->snipe->uploadFile($resource, $item->snipeit_asset_id, $content, $filename, $uploadNotes);
                } catch (\Throwable $e) {
                    Log::error('Failed to upload STB PDF to Snipe-IT item', [
                        'stb_id' => $stb->id,
                        'resource' => $resource,
                        'asset_id' => $item->snipeit_asset_id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
    }

    /**
     * Upload photos and attachments as evidence for problematic items to Snipe-IT.
     */
    protected function uploadStbEvidenceToSnipeItems(mixed $stb): void

    {
        $problematicItems = $stb->items->filter(fn($item) => in_array(strtolower($item->condition ?? ''), ['broken', 'rusak', 'missing', 'hilang']));
        if ($problematicItems->isEmpty()) return;

        $evidenceFiles = [];

        // Main photo
        if ($stb->photo) {
            $absPath = Storage::disk('public')->path($stb->photo);
            if (file_exists($absPath)) {
                $evidenceFiles[] = [
                    'content' => (string) file_get_contents($absPath),
                    'filename' => 'evidence_main_' . basename($stb->photo),
                    'notes' => 'Foto bukti utama dari STB #' . $stb->id
                ];
            }
        }

        // Additional attachments
        foreach ($stb->attachments as $attachment) {
            $absPath = Storage::disk('public')->path($attachment->file_path);
            if (file_exists($absPath)) {
                $evidenceFiles[] = [
                    'content' => (string) file_get_contents($absPath),
                    'filename' => 'evidence_attach_' . basename($attachment->file_path),
                    'notes' => 'Lampiran bukti tambahan dari STB #' . $stb->id
                ];
            }
        }

        if (empty($evidenceFiles)) return;

        foreach ($problematicItems as $item) {
            if (!$item->snipeit_asset_id) continue;
            
            $resource = match (strtolower($item->kategori ?: 'hardware')) {
                'assets', 'hardware', 'hardware_assets' => 'hardware',
                'license', 'licenses' => 'licenses',
                'accessories', 'accessory' => 'accessories',
                'component', 'components' => 'components',
                default => 'hardware',
            };

            foreach ($evidenceFiles as $file) {
                try {
                    $this->snipe->uploadFile($resource, $item->snipeit_asset_id, $file['content'], $file['filename'], $file['notes']);
                } catch (\Throwable $e) {
                    Log::error('Failed to upload evidence to Snipe-IT', [
                        'item_id' => $item->id,
                        'asset_id' => $item->snipeit_asset_id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
    }

    protected function compressPdfWithGhostscript(string $absolutePath): void
    {
        $gs = env('GHOSTSCRIPT_PATH', 'gs');
        
        // Try to detect gs if default fails on windows
        if ($gs === 'gs' && strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $potentialPaths = [
                'C:\Program Files\gs\gs10.02.1\bin\gswin64c.exe',
                'C:\Program Files\gs\gs10.02.0\bin\gswin64c.exe',
                'C:\Program Files\gs\gs9.54.0\bin\gswin64c.exe',
            ];
            foreach ($potentialPaths as $path) {
                if (file_exists($path)) {
                    $gs = $path;
                    break;
                }
            }
        }

        // Check if gs is executable
        $checkCmd = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? "where $gs" : "which $gs";
        exec($checkCmd, $output, $returnCode);
        if ($returnCode !== 0 && !file_exists($gs)) {
            return;
        }

        $tmpPath = $absolutePath . '.tmp_compressed.pdf';

        $cmd = implode(' ', array_map('escapeshellarg', [
            $gs,
            '-dBATCH',
            '-dNOPAUSE',
            '-sDEVICE=pdfwrite',
            '-dCompatibilityLevel=1.4',
            '-dPDFSETTINGS=/ebook',
            '-sOutputFile=' . $tmpPath,
            $absolutePath,
        ]));

        exec($cmd . ' 2>&1', $output, $returnCode);

        if ($returnCode === 0 && file_exists($tmpPath)) {
            $origSize  = filesize($absolutePath);
            $compSize  = filesize($tmpPath);

            if ($compSize > 0 && $compSize < $origSize) {
                rename($tmpPath, $absolutePath);
                Log::info('PDF compressed with Ghostscript', [
                    'original_bytes'   => $origSize,
                    'compressed_bytes' => $compSize,
                    'reduction_pct'    => round((1 - $compSize / $origSize) * 100, 1),
                ]);
            } else {
                @unlink($tmpPath);
            }
        } else {
            @unlink($tmpPath);
        }
    }

    protected function hasCompletionFlagColumn(string $table = 'stbs'): bool
    {
        $key = "completion_flag:{$table}";
        if (!isset($this->schemaCache[$key])) {
            $this->schemaCache[$key] = Schema::hasColumn($table, 'is_completed');
        }
        return $this->schemaCache[$key];
    }

    protected function hasCancellationColumns(string $table = 'stbs'): bool
    {
        $key = "cancellation:{$table}";
        if (!isset($this->schemaCache[$key])) {
            $this->schemaCache[$key] = Schema::hasColumn($table, 'cancelled_at');
        }
        return $this->schemaCache[$key];
    }

    protected function hasDocumentFlowColumns(string $table = 'stbs'): bool
    {
        $key = "docflow:{$table}";
        if (!isset($this->schemaCache[$key])) {
            $this->schemaCache[$key] = Schema::hasColumns($table, ['document_type', 'movement_type', 'linked_stb_id', 'returned_at']);
        }
        return $this->schemaCache[$key];
    }


    protected function resolveDocumentType(mixed $stb): string
    {
        $documentType = trim((string) data_get($stb, 'document_type', ''));

        if (in_array($documentType, ['handover', 'loan', 'service'], true)) {
            return $documentType;
        }

        return match ((int) data_get($stb, 'status', 0)) {
            3 => 'loan',
            4 => 'service',
            default => 'handover',
        };
    }

    protected function resolveMovementType(mixed $stb): string
    {
        $movementType = trim((string) data_get($stb, 'movement_type', ''));

        if (in_array($movementType, ['out', 'return'], true)) {
            return $movementType;
        }

        return (int) data_get($stb, 'status', 0) === 2 ? 'return' : 'out';
    }

    protected function resolveDocumentLabel(mixed $stb): string
    {
        $documentType = $this->resolveDocumentType($stb);
        $movementType = $this->resolveMovementType($stb);

        return match ([$documentType, $movementType]) {
            ['handover', 'out'] => 'handover',
            ['handover', 'return'] => 'return',
            ['handover', 'handover'] => 'handover',
            ['loan', 'out'] => 'Peminjaman',
            ['loan', 'return'] => 'Pengembalian Pinjaman',
            ['service', 'out'] => 'Perbaikan',
            default => '-',
        };
    }

    protected function formatStbDocumentName(mixed $stb): string
    {
        $company = $stb->user_company;
        if (!$company && $stb->group_id) {
            $company = $this->buildGroupParts($stb->group_id)['company'] ?? null;
        }
        if ($company === '-') {
            $company = null;
        }

        return 'STB-' . $this->formatDocId($stb, $company);
    }

    protected function isLoanOutDocument(mixed $stb): bool
    {
        return $this->resolveDocumentType($stb) === 'loan'
            && $this->resolveMovementType($stb) === 'out';
    }

    protected function isLoanReturnDocument(mixed $stb): bool
    {
        return $this->resolveDocumentType($stb) === 'loan'
            && $this->resolveMovementType($stb) === 'return';
    }

    protected function resolveLegacyStatus(?string $documentType, ?string $movementType): ?int
    {
        return match ([$documentType, $movementType]) {
            ['handover', 'out'] => 1,
            ['handover', 'handover'] => 1,
            ['handover', 'return'] => 2,
            ['loan', 'out'] => 3,
            ['service', 'out'] => 4,
            default => null,
        };
    }

    protected function getLoanModel(): string
    {
        return Stb::class;
    }

    protected function buildOpenLoanReferences(mixed $current = null): array
    {
        if (!$this->hasDocumentFlowColumns()) {
            return [];
        }

        $loanModel = $this->getLoanModel();
        $query = $loanModel::query()
            ->latest('created_at')
            ->where('document_type', 'loan')
            ->where('movement_type', 'out')
            ->whereNull('cancelled_at')
            ->whereNull('returned_at');

        if ($current?->linked_stb_id) {
            $query->orWhere(fn ($builder) => $builder
                ->whereKey($current->linked_stb_id)
                ->where('document_type', 'loan')
                ->where('movement_type', 'out'));
        }

        $loans = $query->get()
            ->filter(fn (mixed $stb) => $current?->linked_stb_id === $stb->id || $this->hasAllSignatures($stb))
            ->unique('id')
            ->values();

        if ($loans->isEmpty()) {
            return [];
        }

        // PERF: Batch-resolve all unique locations and users via a single pool call
        // instead of N sequential snipe->getLocation() + snipe->getUser() calls.
        $uniqueGroupIds = $loans->pluck('group_id')->filter()->unique()->values();
        $uniqueUserIds  = $loans->pluck('user_id')->filter()->unique()->values();

        $poolRequests = [];
        foreach ($uniqueGroupIds as $gid) {
            if (!isset($this->locationCache[$gid])) {
                $poolRequests["loc_{$gid}"] = ["locations/{$gid}", []];
            }
        }
        foreach ($uniqueUserIds as $uid) {
            if (!isset($this->userLabelCache[$uid])) {
                $poolRequests["user_{$uid}"] = ["users/{$uid}", []];
            }
        }

        if (!empty($poolRequests)) {
            $poolResults = $this->snipe->requestPool($poolRequests);

            foreach ($uniqueGroupIds as $gid) {
                if (!isset($this->locationCache[$gid])) {
                    $loc   = $poolResults["loc_{$gid}"] ?? [];
                    $label = trim((string) data_get($loc, 'name', ''));
                    $this->locationCache[$gid] = $label !== '' ? $label : 'Location ' . $gid;
                }
            }
            foreach ($uniqueUserIds as $uid) {
                if (!isset($this->userLabelCache[$uid])) {
                    $u         = $poolResults["user_{$uid}"] ?? [];
                    $firstName = trim((string) data_get($u, 'first_name', ''));
                    $lastName  = trim((string) data_get($u, 'last_name', ''));
                    $fullName  = trim($firstName . ' ' . $lastName);
                    $this->userLabelCache[$uid] = $fullName !== ''
                        ? $fullName
                        : ((string) data_get($u, 'name', 'User #' . $uid) ?: 'User #' . $uid);
                }
            }
        }

        return $loans->map(function (mixed $stb) {
            $groupParts = $this->buildGroupParts($stb->group_id);
            return [
                'id'    => $stb->id,
                'docId' => $this->formatDocId($stb, $groupParts['company'] ?: null),
                'label' => trim($this->formatDocId($stb, $groupParts['company'] ?: null) . ' - ' . $this->formatUserLabel($stb->user_id)),
            ];
        })->values()->all();
    }

    protected function validateLinkedLoanReference(?int $linkedStbId, mixed $currentStb = null): mixed
    {
        if (!$linkedStbId) {
            return null;
        }

        if ($currentStb && $linkedStbId === $currentStb->id) {
            throw new \InvalidArgumentException('Dokumen pengembalian tidak boleh merujuk dirinya sendiri.');
        }

        $loanModel = $this->getLoanModel();
        $linkedLoan = $loanModel::query()->find($linkedStbId);


        if (!$linkedLoan || !$this->isLoanOutDocument($linkedLoan)) {
            throw new \InvalidArgumentException('Dokumen pinjaman asal tidak valid.');
        }

        if ($this->isCancelledState($linkedLoan)) {
            throw new \InvalidArgumentException('Dokumen pinjaman asal sudah dibatalkan.');
        }

        if (!$this->hasAllSignatures($linkedLoan)) {
            throw new \InvalidArgumentException('Dokumen pinjaman asal harus sudah ditandatangani lengkap.');
        }

        if ($linkedLoan->returned_at && (!$currentStb || $currentStb->linked_stb_id !== $linkedLoan->id)) {
            throw new \InvalidArgumentException('Dokumen pinjaman asal sudah pernah ditutup dengan pengembalian.');
        }

        return $linkedLoan;
    }

    protected function getCompanyInitials(?string $company): string
    {
        return collect(explode(' ', (string) $company))
            ->filter()
            ->map(fn (string $word) => Str::upper(Str::substr($word, 0, 1)))
            ->implode('');
    }

    protected function getYearMonthCode(mixed $value): string
    {
        try {
            $date = $value ? Carbon::parse($value) : now();
        } catch (\Throwable) {
            return '';
        }

        return $date->format('ym');
    }

    protected function formatDocId(mixed $stb, ?string $company = null): string
    {
        $companyCode = $this->getCompanyInitials($company);
        $yearMonth = $this->getYearMonthCode($stb->created_at);

        if ($companyCode === '' || $yearMonth === '') {
            return (string) $stb->id;
        }

        return sprintf('%s-%s-%04d', $companyCode, $yearMonth, $stb->id);
    }


    protected function hasAllSignatures(mixed $stb): bool

    {
        foreach (self::SIGNATURE_ROLE_FIELDS as $fields) {
            if (!$stb->{$fields['path']}) {
                return false;
            }
        }

        return true;
    }

    protected function isCompletedState(mixed $stb): bool
    {
        $table = method_exists($stb, 'getTable') ? $stb->getTable() : 'stbs';

        if ($this->hasCompletionFlagColumn($table)) {
            return (bool) $stb->is_completed;
        }

        if (!$this->hasCompletionColumns($table)) {
            return false;
        }


        return !empty($stb->completed_at) && !empty($stb->completed_pdf_path);
    }

    protected function isCancelledState(mixed $stb): bool
    {
        $table = method_exists($stb, 'getTable') ? $stb->getTable() : 'stbs';
        return $this->hasCancellationColumns($table) && !empty($stb->cancelled_at);
    }


    protected function ensureEditable(mixed $stb): void

    {
        if ($this->isCancelledState($stb) || $this->isCompletedState($stb)) {
            abort(404);
        }
    }

    protected function formatDateForPdf(mixed $value): string
    {
        if (!$value) {
            return '-';
        }

        try {
            return Carbon::parse($value)->locale('id')->translatedFormat('j F Y');
        } catch (\Throwable) {
            return '-';
        }
    }

    protected function formatDateTimeForPdf(mixed $value): string
    {
        if (!$value) {
            return '';
        }

        try {
            return Carbon::parse($value)->locale('id')->translatedFormat('j F Y \p\u\k\u\l H.i');
        } catch (\Throwable) {
            return '';
        }
    }

    protected function fileToDataUri(?string $path): ?string
    {
        if (!$path || !is_file($path)) {
            return null;
        }

        $mime = mime_content_type($path) ?: 'application/octet-stream';
        $contents = file_get_contents($path);

        if ($contents === false) {
            return null;
        }

        return 'data:' . $mime . ';base64,' . base64_encode($contents);
    }

    protected function resolvePublicDataUri(string $path): ?string
    {
        return $this->fileToDataUri(public_path($path));
    }

    protected function resolveStorageDataUri(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        $trimmedPath = trim($path);

        if ($trimmedPath === '') {
            return null;
        }

        // Check if the path is actually a Data URI (Base64 stored in DB)
        // or a JSON stroke string (starts with [)
        if (str_starts_with($trimmedPath, 'data:') || str_starts_with($trimmedPath, '[')) {
            return $trimmedPath;
        }

        $normalizedPath = preg_replace('#^/?storage/#', '', $trimmedPath);
        $normalizedPath = preg_replace('#^/?public/#', '', $normalizedPath);
        $normalizedPath = str_replace('\\', '/', $normalizedPath);
        $normalizedPath = ltrim($normalizedPath, '/');

        if ($normalizedPath === '') {
            return null;
        }

        $disk = Storage::disk('public');
        if ($disk->exists($normalizedPath)) {
            $contents = $disk->get($normalizedPath);
            if ($contents === false) {
                return null;
            }

            $mime = $disk->mimeType($normalizedPath);
            if (!$mime) {
                $absolutePath = $disk->path($normalizedPath);
                $mime = mime_content_type($absolutePath) ?: 'application/octet-stream';
            }

            return 'data:' . $mime . ';base64,' . base64_encode($contents);
        }

        $fullPath = storage_path('app/public/' . $normalizedPath);
        if (!is_file($fullPath)) {
            return null;
        }

        return $this->fileToDataUri($fullPath);
    }

    protected function getPdfBrowserPath(): ?string
    {
        $configuredPath = trim((string) config('services.pdf.browser_path', ''));
        $paths = $configuredPath !== ''
            ? array_merge([$configuredPath], self::PDF_BROWSER_PATHS)
            : self::PDF_BROWSER_PATHS;

        foreach ($paths as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    protected function buildPdfViewData(mixed $stb): array

    {
        $user = $stb->user_name ? null : $this->snipe->getUser($stb->user_id);
        $groupParts = $stb->location_name ? null : $this->buildGroupParts($stb->group_id);

        $userName = $stb->user_name ?: $this->formatUserLabel($stb->user_id);
        $company = $stb->user_company ?: (string) data_get($user, 'company.name', $groupParts['company'] ?? '-');
        $location = $stb->location_name ?: ($groupParts['location'] ?? '-');
        $department = $stb->user_dept ?: (string) data_get($user, 'department.name', $groupParts['department'] ?? '-');
        $position = $stb->user_title ?: (string) data_get($user, 'jobtitle', '-');
        $phoneNumber = $stb->user_phone ?: (string) data_get($user, 'phone', '-');
        $email = $stb->user_email ?: (string) data_get($user, 'email', '-');

        $docId = $this->formatDocId($stb, $stb->group_id ? $company : null);

        return [
            'docId' => $docId,
            'createdDate' => $this->formatDateForPdf($stb->created_at),
            'deliverDate' => $this->formatDateForPdf($stb->deliver_date),
            'location' => $location ?: '-',
            'building' => data_get($stb, 'building', '-'),
            'useDate' => $this->formatDateForPdf(data_get($stb, 'use_date')),
            'batchNo' => data_get($stb, 'batch_no', '-'),
            'reqDocNo' => data_get($stb, 'req_doc_no', '-'),
            'poDocNo' => data_get($stb, 'po_doc_no', '-'),
            'userName' => $userName ?: '-',
            'company' => $company ?: '-',
            'phoneNumber' => $phoneNumber ?: '-',
            'department' => $department ?: '-',
            'email' => $email ?: '-',
            'position' => $position ?: '-',
            'statusLabel' => $this->resolveDocumentLabel($stb),

            'items' => $stb->items->take(5)->map(fn ($item) => [
                'nama' => $item->nama,
                'type' => $item->type,
                'jumlah' => $item->jumlah,
                'serial_no' => $item->serial_no,
                'asset' => $this->resolveItemAssetLabel($item),
            ])->values()->all(),
            'itDrafterName' => $this->formatUserLabel($stb->it_drafter_id),
            'itCheckerName' => $this->formatUserLabel($stb->it_checker_id),
            'itApprovedName' => $this->formatUserLabel($stb->it_approved_id),
            'deptHeadName' => (string) data_get($user, 'manager.name', '-') ?: '-',
            'itDrafterSignature' => $this->resolveStorageDataUri($stb->it_drafter_signature_path),
            'itCheckerSignature' => $this->resolveStorageDataUri($stb->it_checker_signature_path),
            'itApprovedSignature' => $this->resolveStorageDataUri($stb->it_approved_signature_path),
            'requesterReceivedSignature' => $this->resolveStorageDataUri($stb->requester_received_signature_path),
            'requesterDeptHeadSignature' => $this->resolveStorageDataUri($stb->requester_dept_head_signature_path),
            'itDrafterSignedAt' => $this->formatDateTimeForPdf($stb->it_drafter_signed_at),
            'itCheckerSignedAt' => $this->formatDateTimeForPdf($stb->it_checker_signed_at),
            'itApprovedSignedAt' => $this->formatDateTimeForPdf($stb->it_approved_signed_at),
            'requesterReceivedSignedAt' => $this->formatDateTimeForPdf($stb->requester_received_signed_at),
            'requesterDeptHeadSignedAt' => $this->formatDateTimeForPdf($stb->requester_dept_head_signed_at),
            'photo' => $this->resolveStorageDataUri($stb->photo),
            'remark' => $stb->remark ?: '-',
            'logo' => $this->resolvePublicDataUri('form-logo.png'),
            'movementType' => $this->resolveMovementType($stb),
        ];
    }

    protected function generateCompletedPdf(mixed $stb): string

    {
        if (app()->environment('testing')) {
            $relativePath = 'stb-pdfs/' . $this->formatStbDocumentName($stb) . '.pdf';
            Storage::disk('public')->put($relativePath, '%PDF-1.4 test');

            return $relativePath;
        }

        $viewData = $this->buildPdfViewData($stb);
        $docId = $viewData['docId'] ?? (string) $stb->id;
        $relativePath = 'stb-pdfs/' . $this->formatStbDocumentName($stb) . '.pdf';

        $browserPath = $this->getPdfBrowserPath();

        if (!$browserPath) {
            throw new \RuntimeException('Headless browser was not found for PDF generation.');
        }

        $html = view('stb.pdf_final', $viewData)->render();

        $tempDirectory = storage_path('app/stb-temp');
        if (!is_dir($tempDirectory)) {
            mkdir($tempDirectory, 0777, true);
        }

        $htmlPath = $tempDirectory . DIRECTORY_SEPARATOR . Str::uuid() . '.html';
        file_put_contents($htmlPath, $html);

        $browserProfilePath = $tempDirectory . DIRECTORY_SEPARATOR . 'browser-profile-' . Str::uuid();
        if (!is_dir($browserProfilePath)) {
            mkdir($browserProfilePath, 0777, true);
        }

        $pdfAbsolutePath = storage_path('app/public/' . $relativePath);
        $pdfDirectory = dirname($pdfAbsolutePath);
        if (!is_dir($pdfDirectory)) {
            mkdir($pdfDirectory, 0777, true);
        }

        $fileUrl = 'file:///' . str_replace('\\', '/', $htmlPath);
        $process = new Process([
            $browserPath,
            '--headless=new',
            '--disable-gpu',
            '--disable-crash-reporter',
            '--disable-breakpad',
            '--no-first-run',
            '--no-default-browser-check',
            '--disable-features=msEdgeCloudManagement,RendererCodeIntegrity',
            '--user-data-dir=' . $browserProfilePath,
            '--allow-file-access-from-files',
            '--no-pdf-header-footer',
            '--run-all-compositor-stages-before-draw',
            '--virtual-time-budget=12000',
            '--print-to-pdf=' . $pdfAbsolutePath,
            $fileUrl,
        ]);

        $process->setTimeout(60);
        $process->run();
        @unlink($htmlPath);
        if (is_dir($browserProfilePath)) {
            $profileFiles = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($browserProfilePath, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST,
            );

            foreach ($profileFiles as $file) {
                $file->isDir() ? @rmdir($file->getRealPath()) : @unlink($file->getRealPath());
            }

            @rmdir($browserProfilePath);
        }

        if (!$process->isSuccessful() || !is_file($pdfAbsolutePath)) {
            throw new \RuntimeException('Failed to generate completed PDF. ' . trim($process->getErrorOutput() . ' ' . $process->getOutput()));
        }

        return $relativePath;
    }

    protected function buildPhotoFilename(?string $docId, string $extension): string
    {
        $sanitizedDocId = Str::of((string) ($docId ?: 'DOC'))
            ->replace(' ', '-')
            ->replace('/', '-')
            ->replace('\\', '-')
            ->replaceMatches('/[^A-Za-z0-9\-_]/', '')
            ->trim('-')
            ->value();

        if ($sanitizedDocId === '') {
            $sanitizedDocId = 'DOC';
        }

        return $sanitizedDocId . '.' . strtolower($extension);
    }

    protected function storePhoto(Request $request, ?string $docId, ?string $existingPhoto = null): ?string
    {
        if (!$request->hasFile('photo')) {
            return $existingPhoto;
        }

        $file = $request->file('photo');
        $extension = $file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg';
        $filename = $this->buildPhotoFilename($docId, $extension);
        $directory = str_contains(static::class, 'Peminjaman') ? 'peminjaman-photos' : 'stb-photos';

        if ($existingPhoto) {
            Storage::disk('public')->delete($existingPhoto);
        }

        foreach (['jpg', 'jpeg', 'png', 'webp'] as $candidateExtension) {
            $candidatePath = $directory . '/' . $this->buildPhotoFilename($docId, $candidateExtension);

            if ($candidatePath !== $existingPhoto) {
                Storage::disk('public')->delete($candidatePath);
            }
        }

        return $file->storeAs($directory, $filename, 'public');
    }

    protected function getSignatureFields(string $role): ?array
    {
        return self::SIGNATURE_ROLE_FIELDS[$role] ?? null;
    }

    protected function buildSignatureFilename(?string $docId, string $role): string
    {
        $sanitizedDocId = Str::of((string) ($docId ?: 'DOC'))
            ->replace(' ', '-')
            ->replace('/', '-')
            ->replace('\\', '-')
            ->replaceMatches('/[^A-Za-z0-9\-_]/', '')
            ->trim('-')
            ->value();

        if ($sanitizedDocId === '') {
            $sanitizedDocId = 'DOC';
        }

        return $sanitizedDocId . '-' . $role . '.png';
    }

    protected function storeSignature(string $signature, ?string $docId, string $role, ?string $existingPath = null): string
    {
        // Requirement: Simpan langsung di database (JSON strokes or Base64)
        
        // Basic Validation: Ensure it's either valid JSON strokes or valid Data URI
        if (str_starts_with($signature, '[')) {
            $decoded = json_decode($signature);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('Invalid JSON signature format.');
            }
        } elseif (!str_starts_with($signature, 'data:')) {
            // If it's not JSON and not Data URI, it might be a legacy path, which we don't allow for NEW signatures
            if (!$existingPath) {
                throw new \RuntimeException('Signature data format is unrecognized.');
            }
        }

        // Hapus file lama jika masih ada (legacy support)
        if ($existingPath && !str_starts_with($existingPath, 'data:') && !str_starts_with($existingPath, '[')) {
            Storage::disk('public')->delete($existingPath);
        }

        return $signature;
    }

    protected function performSign(
        Request $request,
        mixed $stb,
        string $role,

        ?string $redirectRoute = null,
        array $redirectParameters = [],
        bool $redirectBack = false,
    ) {
        $fields = $this->getSignatureFields($role);

        if (!$fields) {
            abort(404);
        }

        $validator = Validator::make($request->all(), [
            'docId' => 'nullable|string',
            'signature' => 'required|string',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Signature data is invalid.',
                    'errors' => $validator->errors()->toArray(),
                ], 422);
            }

            return redirect()->back()
                ->withErrors($validator)
                ->with('error', 'Signature data is invalid.');
        }

        $validated = $validator->validated();

        if ($this->isCancelledState($stb)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Cancelled document cannot be signed again.',
                ], 409);
            }

            return redirect()->back()
                ->with('error', 'Cancelled document cannot be signed again.');
        }

        if ($this->isCompletedState($stb)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Completed document cannot be signed again.',
                ], 409);
            }

            return redirect()->back()
                ->with('error', 'Completed document cannot be signed again.');
        }

        try {
            $path = DB::transaction(function () use ($stb, $fields, $validated, $role) {
                $path = $this->storeSignature(
                    $validated['signature'],
                    $validated['docId'] ?? null,
                    $role,
                    $stb->{$fields['path']},
                );

                $stb->update([
                    $fields['path'] => $path,
                    $fields['signed_at'] => now(),
                ]);
                
                return $path;
            });

            Log::info('Document signed successfully', [
                'document_id' => $stb->id,
                'role' => $role,
            ]);

            // Log to ActionLog
            try {
                $docType  = $stb->document_type ?? 'document';
                $docLabel = strtoupper($docType) . ' #' . $stb->id;
                ActionLog::create([
                    'user_id'     => auth()->id(),
                    'action_type' => 'sign',
                    'item_type'   => get_class($stb),
                    'item_id'     => $stb->id,
                    'note'        => "Tanda tangan role '{$role}' ditambahkan pada {$docLabel}",
                    'log_meta'    => ['role' => $role, 'doc_type' => $docType],
                ]);
            } catch (\Throwable $logEx) {
                Log::warning('Failed to write sign action log', ['error' => $logEx->getMessage()]);
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Tanda tangan berhasil disimpan.',
                    'path' => $path,
                    'signed_at' => $stb->{$fields['signed_at']},
                ]);
            }

            if ($redirectBack) {
                return redirect()->back()
                    ->with('success', 'Tanda tangan berhasil disimpan.');
            }

            return redirect()->route($redirectRoute, $redirectParameters)
                ->with('success', 'Tanda tangan berhasil disimpan.');
        } catch (\Throwable $e) {
            Log::error('Failed to sign document', [
                'document_id' => $stb->id,
                'role' => $role,
                'message' => $e->getMessage(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Failed to save signature: ' . $e->getMessage(),
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Failed to save signature: ' . $e->getMessage());
        }
    }

    protected function performClearSign(Request $request, mixed $stb, string $role)
    {
        $fields = $this->getSignatureFields($role);

        if (!$fields) {
            abort(404);
        }

        if ($this->isCancelledState($stb)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Cancelled document cannot be modified.',
                ], 409);
            }

            return redirect()->back()
                ->with('error', 'Cancelled document cannot be modified.');
        }

        try {
            if ($stb->{$fields['path']}) {
                Storage::disk('public')->delete($stb->{$fields['path']});
            }

            if ($stb->completed_pdf_path) {
                Storage::disk('public')->delete($stb->completed_pdf_path);
            }

            $payload = [
                $fields['path'] => null,
                $fields['signed_at'] => null,
                'completed_pdf_path' => null,
                'completed_at' => null,
            ];

            if ($this->hasCompletionFlagColumn()) {
                $payload['is_completed'] = false;
            }

            $stb->update($payload);

            Log::info('Document signature cleared successfully', [
                'document_id' => $stb->id,
                'role' => $role,
            ]);

            // Log to ActionLog
            try {
                $docType  = $stb->document_type ?? 'document';
                $docLabel = strtoupper($docType) . ' #' . $stb->id;
                ActionLog::create([
                    'user_id'     => auth()->id(),
                    'action_type' => 'sign_cleared',
                    'item_type'   => get_class($stb),
                    'item_id'     => $stb->id,
                    'note'        => "Tanda tangan role '{$role}' dihapus dari {$docLabel}",
                    'log_meta'    => ['role' => $role, 'doc_type' => $docType],
                ]);
            } catch (\Throwable $logEx) {
                Log::warning('Failed to write sign_cleared action log', ['error' => $logEx->getMessage()]);
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Tanda tangan berhasil dihapus.',
                ]);
            }

            return redirect()->back()
                ->with('success', 'Tanda tangan berhasil dihapus.');
        } catch (\Throwable $e) {
            Log::error('Failed to clear document signature', [
                'document_id' => $stb->id,
                'role' => $role,
                'message' => $e->getMessage(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Gagal menghapus tanda tangan: ' . $e->getMessage(),
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Gagal menghapus tanda tangan: ' . $e->getMessage());
        }
    }
}