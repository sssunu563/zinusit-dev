<?php

namespace App\Http\Controllers;

use App\Models\ActionLog;
use App\Models\Peminjaman;
use App\Services\AssetNoteFormatterService;
use App\Services\ErrorMessageService;

use App\Http\Controllers\DocumentFlowController;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Traits\DocumentCheckoutTrait;
use Inertia\Inertia;

class PeminjamanController extends DocumentFlowController
{
    use DocumentCheckoutTrait;

    /**
     * Fetch user + location data from Snipe-IT and return as array for saving.
     */
    private function fetchSnipeUserAndLocation(int $userId, int $groupId): array
    {
        try {
            // PERF: Fetch user + location in parallel via requestPool instead of 2 serial requests
            $snipeService = app(\App\Services\SnipeItService::class);
            $poolResults  = $snipeService->requestPool([
                'user'     => ["users/{$userId}",     []],
                'location' => ["locations/{$groupId}", []],
            ]);

            $userData     = $poolResults['user']     ?? [];
            $locationData = $poolResults['location'] ?? [];

            $firstName = trim((string) ($userData['first_name'] ?? ''));
            $lastName  = trim((string) ($userData['last_name'] ?? ''));
            $fullName  = trim($firstName . ' ' . $lastName);

            return [
                'user_name'     => !empty($userData['id']) ? ($fullName !== '' ? $fullName : ($userData['name'] ?? null)) : null,
                'user_title'    => $userData['jobtitle']                    ?? null,
                'user_phone'    => $userData['phone']                       ?? null,
                'user_email'    => $userData['email']                       ?? null,
                'user_dept'     => data_get($userData,    'department.name') ?? null,
                'user_company'  => data_get($userData,    'company.name')    ?? data_get($locationData, 'parent.name') ?? null,
                'location_name' => data_get($locationData, 'name')          ?? null,
            ];
        } catch (\Exception $e) {
            \Log::warning('Failed to fetch user/location from Snipe-IT', [
                'user_id'     => $userId,
                'location_id' => $groupId,
                'error'       => $e->getMessage(),
            ]);
            return [
                'user_name' => null, 'user_title' => null, 'user_phone' => null,
                'user_email' => null, 'user_dept' => null, 'user_company' => null,
                'location_name' => null,
            ];
        }
    }
    
    /**
     * Override formatDocId to use simple format without company prefix
     */
    protected function formatDocId(mixed $stb, ?string $company = null): string
    {
        $yearMonth = $this->getYearMonthCode($stb->created_at);

        if ($yearMonth === '') {
            return (string) $stb->id;
        }

        // Extract 3-letter location code from stored location_name (e.g. "ZGI BGR F1" → "ZGI")
        $locationCode = '';
        $locationName = $stb->location_name ?? '';
        if ($locationName && $locationName !== '-') {
            $firstWord = explode(' ', trim($locationName))[0];
            $locationCode = strtoupper(substr($firstWord, 0, 3));
        }

        $paddedId = sprintf('%04d', $stb->id);

        if ($locationCode) {
            return sprintf('%s-%s-%s', $locationCode, $yearMonth, $paddedId);
        }

        return sprintf('%s-%s', $yearMonth, $paddedId);
    }

    protected function getLoanModel(): string
    {
        return Peminjaman::class;
    }

    /**
     * Override buildPdfViewData for Peminjaman — uses stored user/location data,
     * 2 signature roles only, and includes both loan + return photos.
     */
    protected function buildPdfViewData(mixed $stb): array
    {
        $userName   = $stb->user_name   ?: $this->formatUserLabel($stb->user_id);
        $company    = $stb->user_company ?: '-';
        $location   = $stb->location_name ?: '-';
        $department = $stb->user_dept   ?: '-';
        $position   = $stb->user_title  ?: '-';
        $phone      = $stb->user_phone  ?: '-';
        $email      = $stb->user_email  ?: '-';

        $docId = $this->formatDocId($stb, $location);

        $expectedReturn = $stb->expected_return_date
            ? $this->formatDateForPdf($stb->expected_return_date)
            : '-';

        $isOverdue = $stb->expected_return_date
            && !$stb->returned_at
            && now()->gt(\Carbon\Carbon::parse($stb->expected_return_date));

        return [
            'docId'                      => $docId,
            'createdDate'                => $this->formatDateForPdf($stb->created_at),
            'loanDate'                   => $this->formatDateForPdf($stb->use_date ?? $stb->created_at),
            'expectedReturnDate'         => $expectedReturn,
            'isOverdue'                  => $isOverdue,
            'location'                   => $location,
            'userName'                   => $userName,
            'company'                    => $company,
            'phoneNumber'                => $phone,
            'department'                 => $department,
            'email'                      => $email,
            'position'                   => $position,
            'statusLabel'                => $this->resolveDocumentLabel($stb),
            'movementType'               => $this->resolveMovementType($stb),
            'remark'                     => $stb->remark ?: '-',

            'items' => $stb->items->map(fn ($item) => [
                'nama'      => $item->nama,
                'type'      => $item->type,
                'jumlah'    => $item->jumlah,
                'serial_no' => $item->serial_no ?? '-',
                'asset'     => $this->resolveItemAssetLabel($item),
            ])->values()->all(),

            // Signatures — 2 roles
            'itDrafterName'              => $stb->user_name ? $this->formatUserLabel($stb->it_drafter_id) : $this->formatUserLabel($stb->it_drafter_id),
            'itDrafterSignature'         => $this->resolveStorageDataUri($stb->it_drafter_signature_path),
            'itDrafterSignedAt'          => $this->formatDateTimeForPdf($stb->it_drafter_signed_at),
            'requesterReceivedSignature' => $this->resolveStorageDataUri($stb->requester_received_signature_path),
            'requesterReceivedSignedAt'  => $this->formatDateTimeForPdf($stb->requester_received_signed_at),

            // Photos
            'photoLoan'       => $this->resolveStorageDataUri($stb->photo),
            'photoLoanDate'   => $stb->photo ? $this->formatDateTimeForPdf($stb->created_at) : null,
            'photoReturn'     => $this->resolveStorageDataUri($stb->return_photo_path ?? null),
            'photoReturnDate' => ($stb->return_photo_path ?? null) ? $this->formatDateTimeForPdf($stb->returned_at) : null,

            'logo' => $this->resolvePublicDataUri('form-logo.png'),
        ];
    }

    /**
     * Override generateCompletedPdf to use peminjaman blade template.
     */
    protected function generateCompletedPdf(mixed $stb): string
    {
        if (app()->environment('testing')) {
            $relativePath = 'peminjaman-pdfs/peminjaman-' . str_pad((string) $stb->id, 5, '0', STR_PAD_LEFT) . '.pdf';
            \Illuminate\Support\Facades\Storage::disk('public')->put($relativePath, '%PDF-1.4 test');
            return $relativePath;
        }

        $viewData    = $this->buildPdfViewData($stb);
        $docId       = $viewData['docId'] ?? (string) $stb->id;
        $relativePath = 'peminjaman-pdfs/' . \Illuminate\Support\Str::slug($docId, '-') . '.pdf';

        $browserPath = $this->getPdfBrowserPath();
        if (!$browserPath) {
            throw new \RuntimeException('Headless browser was not found for PDF generation.');
        }

        $html = view('peminjaman.pdf_final', $viewData)->render();

        $tempDir = storage_path('app/peminjaman-temp');
        if (!is_dir($tempDir)) mkdir($tempDir, 0777, true);

        $htmlPath = $tempDir . DIRECTORY_SEPARATOR . \Illuminate\Support\Str::uuid() . '.html';
        file_put_contents($htmlPath, $html);

        $profilePath = $tempDir . DIRECTORY_SEPARATOR . 'browser-profile-' . \Illuminate\Support\Str::uuid();
        if (!is_dir($profilePath)) mkdir($profilePath, 0777, true);

        $pdfAbsPath = storage_path('app/public/' . $relativePath);
        $pdfDir = dirname($pdfAbsPath);
        if (!is_dir($pdfDir)) mkdir($pdfDir, 0777, true);

        $process = new \Symfony\Component\Process\Process([
            $browserPath,
            '--headless=new', '--no-sandbox', '--disable-dev-shm-usage',
            '--disable-gpu', '--disable-crash-reporter',
            '--disable-breakpad', '--no-first-run', '--no-default-browser-check',
            '--disable-features=msEdgeCloudManagement,RendererCodeIntegrity',
            '--user-data-dir=' . $profilePath,
            '--allow-file-access-from-files', '--no-pdf-header-footer',
            '--run-all-compositor-stages-before-draw', '--virtual-time-budget=12000',
            '--print-to-pdf=' . $pdfAbsPath,
            'file:///' . str_replace('\\', '/', $htmlPath),
        ]);
        $process->setTimeout(60);
        $process->run();
        @unlink($htmlPath);

        // Cleanup browser profile
        if (is_dir($profilePath)) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($profilePath, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($files as $f) { $f->isDir() ? @rmdir($f->getRealPath()) : @unlink($f->getRealPath()); }
            @rmdir($profilePath);
        }

        if (!$process->isSuccessful() || !is_file($pdfAbsPath)) {
            throw new \RuntimeException('Failed to generate PDF. ' . trim($process->getErrorOutput() . ' ' . $process->getOutput()));
        }

        return $relativePath;
    }

    protected function resolveLinkedLoanId(Request $request, array $validated = []): ?int

    {
        $linkedLoanId = $validated['linkedLoanId']
            ?? $request->input('linkedLoanId')
            ?? $request->query('linkedLoanId');

        if ($linkedLoanId === null || $linkedLoanId === '') {
            return null;
        }

        return (int) $linkedLoanId;
    }

    protected function ensureLoanRecord(Peminjaman $peminjaman): void

    {
        if (!$this->isLoanDocument($peminjaman)) {
            abort(404);
        }
    }

    protected function buildLoanRelationSummary(Peminjaman $stb, string $relationLabel): array

    {
        // Use stored company name instead of fetching from API
        $companyName = $stb->user_company ?? null;

        return [
            'id' => $stb->id,
            'docId' => $this->formatDocId($stb, $companyName),
            'href' => route('peminjaman.show', $stb),
            'relationLabel' => $relationLabel,
            'documentLabel' => $this->resolveDocumentLabel($stb),
            'userName' => $stb->user_name ?? $this->formatUserLabel($stb->user_id),
            'completedAt' => optional($stb->completed_at)?->toIso8601String(),
            'returnedAt' => optional($stb->returned_at)?->toIso8601String(),
        ];
    }

    protected function buildOpenLoanReferences(mixed $current = null): array
    {
        if (!$this->hasDocumentFlowColumns('peminjamans')) {
            return [];
        }

        $query = Peminjaman::query()
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

        return $query
            ->get()
            ->filter(fn (Peminjaman $stb) => $current?->linked_stb_id === $stb->id || $this->hasAllSignatures($stb))
            ->unique('id')
            ->map(function (Peminjaman $stb) {
                // Use stored data instead of fetching from API
                $companyName = $stb->user_company ?? null;
                $userName = $stb->user_name ?? $this->formatUserLabel($stb->user_id);
                $docId = $this->formatDocId($stb, $companyName);

                return [
                    'id' => $stb->id,
                    'docId' => $docId,
                    'label' => trim($docId . ' - ' . $userName),
                ];
            })
            ->values()
            ->all();
    }

    protected function normalizeAssetState(?string $status): ?string
    {
        $normalized = strtolower(trim((string) ($status ?? '')));

        if ($normalized === '' || $normalized === '-') {
            return null;
        }

        if (str_contains($normalized, 'active')) {
            return 'active';
        }

        if (
            str_contains($normalized, 'stock') ||
            str_contains($normalized, 'ready to deploy') ||
            str_contains($normalized, 'available') ||
            str_contains($normalized, 'deployable')
        ) {
            return 'stock';
        }

        if (
            str_contains($normalized, 'borrow') ||
            str_contains($normalized, 'borrowed') ||
            str_contains($normalized, 'on loan') ||
            str_contains($normalized, 'dipinjam') ||
            str_contains($normalized, 'peminjaman') ||
            str_contains($normalized, 'loaner')
        ) {
            return 'borrow';
        }

        return 'unsupported';
    }

    protected function resolveSelectedAssetMovementType(array $selectedAssetIds): ?string
    {
        if ($selectedAssetIds === []) {
            return null;
        }

        $states = collect($selectedAssetIds)
            ->map(function (int $assetId): ?string {
                $record = $this->snipe->getHardware($assetId);
                $status = (string) (data_get($record, 'status_label.name')
                    ?? data_get($record, 'status.name')
                    ?? data_get($record, 'status_label')
                    ?? '');

                return $this->normalizeAssetState($status);
            })
            ->filter(fn (?string $state) => $state !== null)
            ->values()
            ->all();

        if ($states === []) {
            return null;
        }

        $uniqueStates = array_values(array_unique($states));

        // For Peminjaman (new loans), only Stock is allowed
        if (count($uniqueStates) > 1 || $uniqueStates[0] !== 'stock') {
            return null;
        }

        return 'out';
    }

    protected function buildLoanCreateInitialData(Request $request): array
    {
        $movementType = in_array($request->query('movementType'), ['out', 'return'], true)
            ? (string) $request->query('movementType')
            : 'out';
        $linkedLoanId = $this->resolveLinkedLoanId($request);

        $initialData = [
            'documentType' => 'loan',
            'movementType' => $movementType,
            'linkedLoanId' => $linkedLoanId,
        ];

        // Extract selectedAssetIds from query parameters
        $selectedAssetIds = $request->query('selectedAssetIds', []);
        if (!is_array($selectedAssetIds)) {
            $selectedAssetIds = is_string($selectedAssetIds)
                ? preg_split('/[\s,]+/', trim($selectedAssetIds), -1, PREG_SPLIT_NO_EMPTY) ?? []
                : [];
        }

        $selectedAssetIds = array_values(array_filter(array_map('intval', $selectedAssetIds), fn ($id) => $id > 0));

        if ($movementType !== 'return' || $linkedLoanId === null || $linkedLoanId <= 0) {
            $baseData = array_merge(array_filter($initialData, fn ($value) => $value !== null && $value !== ''), [
                'itDrafter_id' => auth()->user()->snipeit_user_id,
            ]);

            // Prefill items from selectedAssetIds
            if ($selectedAssetIds !== []) {
                $baseData['items'] = collect($selectedAssetIds)
                    ->map(function (int $assetId): array {
                        $record = $this->snipe->getHardware($assetId);

                        if (empty($record['id'])) {
                            return [];
                        }

                        return [
                            'nama' => (string) ($record['name'] ?? 'Asset'),
                            'kategori' => 'assets',
                            'type' => (string) data_get($record, 'category.name', 'Hardware'),
                            'jumlah' => 1,
                            'serialNo' => (string) ($record['serial'] ?? ''),
                            'inventory_number' => (string) ($record['asset_tag'] ?? ''),
                            'computer_id' => null,
                            'snipeit_asset_id' => $assetId,
                            'condition' => 'Good',
                            'asset_reference_snapshot' => (string) ($record['asset_tag'] ?? ''),
                        ];
                    })
                    ->filter()
                    ->values()
                    ->all();
            }

            return $baseData;
        }

        $linkedLoan = Peminjaman::with('items')->find($linkedLoanId);


        if (!$linkedLoan || !$this->isLoanOutDocument($linkedLoan) || $this->isCancelledState($linkedLoan) || !empty($linkedLoan->returned_at)) {
            return array_filter($initialData, fn ($value) => $value !== null && $value !== '');
        }

        return array_merge($initialData, [
            'user_id' => $linkedLoan->user_id,
            'user_phone' => $linkedLoan->user_phone,
            'user_email' => $linkedLoan->user_email,
            'group_id' => $linkedLoan->group_id,
            'useDate' => optional($linkedLoan->use_date)?->format('Y-m-d'),
            'expectedReturnDate' => now()->format('Y-m-d'), // Default to today for return date
            'itDrafter_id' => auth()->user()->snipeit_user_id,
            'items' => $this->filterBorrowedItems($linkedLoan->items),
            'skippedItems' => $this->getSkippedItemsSummary($linkedLoan->items),
        ]);
    }

    /**
     * Filter items from a linked loan: only include items currently in 'borrow' state.
     * Items without a snipeit_asset_id (manual entries) are always included.
     */
    protected function filterBorrowedItems(\Illuminate\Support\Collection $items): array
    {
        return $items->filter(function ($item) {
            $assetId = (int) ($item->snipeit_asset_id ?? $item->computer_id ?? 0);

            // Manual items (no Snipe-IT ID) — always include
            if ($assetId <= 0) {
                return true;
            }

            try {
                $record = $this->snipe->getHardware($assetId);
                $status = (string) (data_get($record, 'status_label.name')
                    ?? data_get($record, 'status.name')
                    ?? data_get($record, 'status_label')
                    ?? '');

                return $this->normalizeAssetState($status) === 'borrow';
            } catch (\Throwable $e) {
                Log::warning("filterBorrowedItems: could not fetch asset #{$assetId} — including anyway", [
                    'error' => $e->getMessage(),
                ]);
                // On API error, include the item so user isn't silently losing items
                return true;
            }
        })->map(fn ($item) => [
            'nama'                     => $item->nama,
            'kategori'                 => $item->kategori,
            'type'                     => $item->type,
            'jumlah'                   => $item->jumlah,
            'serial_no'                => $item->serial_no,
            'inventory_number'         => $item->inventory_number,
            'computer_id'              => $item->computer_id,
            'snipeit_asset_id'         => $item->snipeit_asset_id,
            'asset_reference_snapshot' => $item->asset_reference_snapshot,
            'condition'                => $item->condition ?? 'Good',
        ])->values()->all();
    }

    /**
     * Return a summary of items that were skipped (not in borrow state)
     * so the frontend can show a notice to the user.
     */
    protected function getSkippedItemsSummary(\Illuminate\Support\Collection $items): array
    {
        return $items->filter(function ($item) {
            $assetId = (int) ($item->snipeit_asset_id ?? $item->computer_id ?? 0);
            if ($assetId <= 0) {
                return false; // Manual items never skipped
            }

            try {
                $record = $this->snipe->getHardware($assetId);
                $status = (string) (data_get($record, 'status_label.name')
                    ?? data_get($record, 'status.name')
                    ?? data_get($record, 'status_label')
                    ?? '');

                return $this->normalizeAssetState($status) !== 'borrow';
            } catch (\Throwable $e) {
                return false; // On error, don't report as skipped
            }
        })->map(fn ($item) => [
            'nama'             => $item->nama,
            'inventory_number' => $item->inventory_number,
            'serial_no'        => $item->serial_no,
        ])->values()->all();
    }

    protected function buildLoanShareProps(Peminjaman $peminjaman, bool $sharedMode = false): array

    {
        $peminjaman->loadMissing(['linkedStb', 'linkedReturns', 'attachments']);

        $isShareable = !$sharedMode && !$this->isCompletedState($peminjaman) && !$this->isCancelledState($peminjaman);
        $shareUrl = $isShareable
            ? URL::temporarySignedRoute('peminjaman.share', now()->addDays(7), ['peminjaman' => $peminjaman->id])
            : null;

        $shareSignUrls = [];

        foreach ($isShareable ? ['it_drafter', 'requester_received'] : [] as $role) {
            $shareSignUrls[$role] = URL::temporarySignedRoute('peminjaman.share.sign', now()->addDays(7), [
                'peminjaman' => $peminjaman->id,
                'role' => $role,
            ]);
        }

        return [
            'peminjaman' => array_merge($peminjaman->toArray(), [
                'photo' => $this->resolveStorageDataUri($peminjaman->photo),
                'linkedLoanId' => $peminjaman->linked_stb_id,
                'handoverPhoto' => $this->resolveStorageDataUri(
                    $peminjaman->movement_type === 'out'
                        ? $peminjaman->photo
                        : ($peminjaman->linkedStb?->photo)
                ),
                'returnPhoto' => $this->resolveStorageDataUri(
                    $peminjaman->movement_type === 'return'
                        ? $peminjaman->photo
                        : ($peminjaman->linkedReturns->first()?->photo)
                ),
                'created_at_formatted' => $peminjaman->created_at?->format('Y-m-d H:i'),
                'returned_at_formatted' => $peminjaman->returned_at?->format('Y-m-d H:i'),
            ]),
            'sharedMode' => $sharedMode,
            'shareUrl' => $shareUrl,
            'shareSignUrls' => $shareSignUrls,
            'isFullySigned' => $this->hasAllSignatures($peminjaman),
            'isCompleted' => $this->isCompletedState($peminjaman),
            'isCancelled' => $this->isCancelledState($peminjaman),
            'loanReferences' => $this->buildOpenLoanReferences($peminjaman),
            'linkedDocument' => $peminjaman->linkedStb
                ? $this->buildLoanRelationSummary($peminjaman->linkedStb, 'Dokumen Pinjaman Asal')
                : null,
            'relatedDocuments' => $peminjaman->linkedReturns
                ->sortByDesc('created_at')
                ->map(fn (Peminjaman $linkedReturn) => $this->buildLoanRelationSummary($linkedReturn, 'Dokumen Pengembalian'))

                ->values()
                ->all(),
            'completedPdfUrl' => $peminjaman->completed_pdf_path ? '/storage/' . ltrim($peminjaman->completed_pdf_path, '/') : null,
            'assetHistory' => $this->getAssetHistory($peminjaman),
        ];
    }

    protected function getAssetHistory(Peminjaman $peminjaman): array
    {
        $serials = $peminjaman->items->pluck('serial_no')->filter()->unique();

        if ($serials->isEmpty()) return [];

        $rows = \DB::table('peminjaman_items')
            ->join('peminjamans', 'peminjaman_items.peminjaman_id', '=', 'peminjamans.id')
            ->whereIn('peminjaman_items.serial_no', $serials)
            ->where('peminjamans.id', '!=', $peminjaman->id)
            ->where('peminjamans.document_type', 'loan')
            ->orderBy('peminjamans.created_at', 'desc')
            ->take(20)
            ->get();

        if ($rows->isEmpty()) return [];

        // PERF: Batch-resolve all unique user labels via pool instead of N+1 API calls
        $uniqueUserIds = $rows->pluck('user_id')->filter()->unique()->values();
        $poolRequests  = [];
        foreach ($uniqueUserIds as $uid) {
            if (!isset($this->userLabelCache[$uid])) {
                $poolRequests["user_{$uid}"] = ["users/{$uid}", []];
            }
        }
        if (!empty($poolRequests)) {
            $poolResults = $this->snipe->requestPool($poolRequests);
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

        return $rows->map(fn($row) => [
            'id'            => $row->peminjaman_id,
            'serial_no'     => $row->serial_no,
            'user_label'    => $this->formatUserLabel($row->user_id),
            'movement_type' => $row->movement_type,
            'completed_at'  => $row->completed_at,
            'remark'        => $row->remark,
        ])->groupBy('serial_no')->toArray();
    }

    public function index()
    {
        $hasCancellationColumns = $this->hasCancellationColumns('peminjamans');

        $requestedTab = (string) request('tab', 'pending');
        $activeTab = match ($requestedTab) {
            'completed' => 'completed',
            'cancelled' => $hasCancellationColumns ? 'cancelled' : 'pending',
            default => 'pending',
        };
        $hasCompletionColumns = $this->hasCompletionColumns('peminjamans');


        $query = Peminjaman::with('items')

            ->latest('created_at')
            ->where('document_type', 'loan')
            ->when($hasCancellationColumns && $activeTab === 'cancelled', fn ($builder) => $builder->whereNotNull('cancelled_at'))
            ->when($hasCancellationColumns && $activeTab !== 'cancelled', fn ($builder) => $builder->whereNull('cancelled_at'))
            ->when($this->hasCompletionFlagColumn('peminjamans') && $activeTab === 'completed', fn ($builder) => $builder
                ->where('is_completed', true)
                ->where(function ($completedQuery) {
                    $completedQuery->where('movement_type', 'return')
                        ->orWhereNotNull('returned_at');
                }))
            ->when($this->hasCompletionFlagColumn('peminjamans') && $activeTab === 'pending', fn ($builder) => $builder
                ->where(function ($pendingQuery) {
                    $pendingQuery->where('is_completed', false)
                        ->orWhere(function ($notReturnedQuery) {
                            $notReturnedQuery->where('is_completed', true)
                                ->where('movement_type', 'out')
                                ->whereNull('returned_at');
                        });
                }))
            ->when(!$this->hasCompletionFlagColumn('peminjamans') && $hasCompletionColumns && $activeTab === 'completed', fn ($builder) => $builder
                ->whereNotNull('completed_at')
                ->whereNotNull('completed_pdf_path')
                ->where(function ($completedQuery) {
                    $completedQuery->where('movement_type', 'return')
                        ->orWhereNotNull('returned_at');
                }))
            ->when(!$this->hasCompletionFlagColumn('peminjamans') && $hasCompletionColumns && $activeTab === 'pending', fn ($builder) => $builder
                ->where(function ($pendingQuery) {
                    $pendingQuery->whereNull('completed_at')->orWhereNull('completed_pdf_path');
                })
                ->orWhere(function ($pendingQuery) {
                    $pendingQuery->whereNotNull('completed_at')
                        ->whereNotNull('completed_pdf_path')
                        ->where('movement_type', 'out')
                        ->whereNull('returned_at');
                }))
            ->when(!$hasCompletionColumns && $activeTab === 'completed', fn ($builder) => $builder->whereRaw('1 = 0'));

        $peminjaman = $query
            ->paginate(10)
            ->withQueryString()
            ->through(function (Peminjaman $stb) {
                $shareable = !$this->isCompletedState($stb) && !$this->isCancelledState($stb);

                return array_merge($stb->toArray(), [
                    'share_url' => $shareable
                        ? URL::temporarySignedRoute('peminjaman.share', now()->addDays(7), ['peminjaman' => $stb->id])
                        : null,
                    'document_label' => $this->resolveDocumentLabel($stb),
                    'is_fully_signed' => $this->hasAllSignatures($stb),
                    'is_completed' => $this->isCompletedState($stb),
                    'is_cancelled' => $this->isCancelledState($stb),
                    'completed_pdf_url' => $stb->completed_pdf_path ? '/storage/' . ltrim($stb->completed_pdf_path, '/') : null,
                ]);
            });

        $pendingQuery = Peminjaman::query()
            ->where('document_type', 'loan')
            ->when($hasCancellationColumns, fn ($builder) => $builder->whereNull('cancelled_at'))
            ->where(function ($queryBuilder) {
                $queryBuilder->where('is_completed', false)
                    ->orWhere(function ($notReturnedQuery) {
                        $notReturnedQuery->where('is_completed', true)
                            ->where('movement_type', 'out')
                            ->whereNull('returned_at');
                    });
            });
        $completedQuery = Peminjaman::query()
            ->where('document_type', 'loan')
            ->when($hasCancellationColumns, fn ($builder) => $builder->whereNull('cancelled_at'))
            ->where('is_completed', true)
            ->where(function ($completedQuery) {
                $completedQuery->where('movement_type', 'return')
                    ->orWhereNotNull('returned_at');
            });

        $cancelledCount = $hasCancellationColumns
            ? Peminjaman::query()->where('document_type', 'loan')->whereNotNull('cancelled_at')->count()
            : 0;


        return Inertia::render('Peminjaman/Index', [
            'peminjaman' => $peminjaman,
            'activeTab' => $activeTab,
            'pendingCount' => $this->hasCompletionFlagColumn('peminjamans')

                ? (clone $pendingQuery)->where('is_completed', false)->count()
                : ($hasCompletionColumns
                    ? (clone $pendingQuery)->where(function ($completionQuery) {
                        $completionQuery->whereNull('completed_at')->orWhereNull('completed_pdf_path');
                    })->count()
                    : (clone $pendingQuery)->count()),
            'completedCount' => $this->hasCompletionFlagColumn('peminjamans')

                ? (clone $completedQuery)->where('is_completed', true)->count()
                : ($hasCompletionColumns
                    ? (clone $completedQuery)->whereNotNull('completed_at')->whereNotNull('completed_pdf_path')->count()
                    : 0),
            'cancelledCount' => $cancelledCount,
            'stats' => [
                'activeLoans' => Peminjaman::query()
                    ->where('document_type', 'loan')
                    ->where('movement_type', 'out')
                    ->where('is_completed', true)
                    ->whereNull('returned_at')
                    ->whereNull('cancelled_at')
                    ->count(),
                'overdueLoans' => Peminjaman::query()
                    ->where('document_type', 'loan')
                    ->where('movement_type', 'out')
                    ->where('is_completed', true)
                    ->whereNull('returned_at')
                    ->whereNull('cancelled_at')
                    ->whereNotNull('expected_return_date')
                    ->where('expected_return_date', '<', now())
                    ->count(),
                'totalAssetsBorrowed' => (int) DB::table('peminjaman_items')
                    ->join('peminjamans', 'peminjaman_items.peminjaman_id', '=', 'peminjamans.id')
                    ->where('peminjamans.document_type', 'loan')
                    ->where('peminjamans.movement_type', 'out')
                    ->where('peminjamans.is_completed', true)
                    ->whereNull('peminjamans.returned_at')
                    ->whereNull('peminjamans.cancelled_at')
                    ->sum('peminjaman_items.jumlah'),

            ],
        ]);
    }

    public function create(Request $request)
    {
        $selectedAssetIds = $request->query('selectedAssetIds', []);
        if (!is_array($selectedAssetIds)) {
            $selectedAssetIds = is_string($selectedAssetIds)
                ? preg_split('/[\s,]+/', trim($selectedAssetIds), -1, PREG_SPLIT_NO_EMPTY) ?? []
                : [];
        }

        $selectedAssetIds = array_values(array_filter(array_map('intval', $selectedAssetIds), fn ($id) => $id > 0));

        if ($selectedAssetIds !== []) {
            $resolvedMovementType = $this->resolveSelectedAssetMovementType($selectedAssetIds);

            if ($resolvedMovementType === null) {
                return redirect()->route('peminjaman.index')->with('error', 'Hanya aset dengan status Stock yang dapat dibuat Peminjaman (pinjaman baru). Status lain tidak diperbolehkan.');
            }

            $request->query->set('movementType', $resolvedMovementType);
        }

        $movementType = in_array($request->query('movementType'), ['out', 'return'], true)
            ? (string) $request->query('movementType')
            : 'out';
        $linkedLoanId = $this->resolveLinkedLoanId($request);

        if ($movementType === 'return' && $linkedLoanId) {
            $linkedLoan = Peminjaman::query()->find($linkedLoanId);

            if ($linkedLoan && $this->isLoanOutDocument($linkedLoan) && !$this->isCancelledState($linkedLoan)) {
                return redirect()->route('peminjaman.show', $linkedLoan)
                    ->with('info', 'Pengembalian dilanjutkan dari dokumen pinjaman yang sudah ada.');
            }
        }

        return Inertia::render('Peminjaman/Create', [
            'nextPeminjamanId' => (Peminjaman::max('id') ?? 0) + 1,

            'initialData' => $this->buildLoanCreateInitialData($request),
            'loanReferences' => $this->buildOpenLoanReferences(),
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'nullable|integer',
            'docId' => 'nullable|string',
            'movementType' => ['required', Rule::in(['out', 'return'])],
            'linkedLoanId' => 'nullable|integer|exists:peminjamans,id',
            'itDrafter_id' => 'nullable|integer',

            'user_id' => 'required|integer',
            'group_id' => 'required|integer',
            'useDate' => 'nullable|date',
            'expectedReturnDate' => 'nullable|date',
            'photo' => 'nullable|image|max:2048',
            'attachments' => 'nullable|array',
            'attachments.*' => 'image|max:5120',
            'remark' => 'nullable|string',
            'createDate' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.nama' => 'required|string',
            'items.*.kategori' => ['nullable', 'string', Rule::notIn(['component', 'components'])],
            'items.*.type' => 'required|string',
            'items.*.jumlah' => 'required|integer|min:1',
            'items.*.serialNo' => 'nullable|string',
            'items.*.inventory_number' => 'nullable|string',
            'items.*.computer_id' => 'nullable|integer',
            'items.*.snipeit_asset_id' => 'nullable|integer',
            'items.*.condition' => 'nullable|string|in:Good,Broken,Missing',
            'items.*.asset_reference_snapshot' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();

        // Validate asset statuses match movement type
        // Note: Validation is more lenient to allow flexibility in asset management
        $statusErrors = [];
        $statusWarnings = [];
        
        foreach ($validated['items'] ?? [] as $index => $item) {
            $assetId = (int) ($item['snipeit_asset_id'] ?? $item['computer_id'] ?? 0);
            if ($assetId <= 0) {
                continue; // Skip items without Snipe-IT ID (manual items)
            }

            try {
                $record = $this->snipe->getHardware($assetId);
                $status = (string) (data_get($record, 'status_label.name')
                    ?? data_get($record, 'status.name')
                    ?? data_get($record, 'status_label')
                    ?? '');
                $state = $this->normalizeAssetState($status);
                $itemName = $item['nama'] ?? 'Asset';
                $assignedTo = data_get($record, 'assigned_to.name', '-');

                // Check if asset exists in Snipe-IT
                if (empty($record['id'])) {
                    $statusErrors[] = "Asset '{$itemName}' (ID: {$assetId}) tidak ditemukan di Snipe-IT.";
                    continue;
                }

                // For NEW loans (out): Warn if not Stock, but allow if deployable
                if ($validated['movementType'] === 'out') {
                    if ($state === 'unsupported') {
                        $statusErrors[] = "Asset '{$itemName}' (ID: {$assetId}) memiliki status '{$status}' yang tidak dapat dipinjamkan.";
                    } elseif ($state !== 'stock' && $state !== 'borrow') {
                        // Allow borrow status in case asset is already borrowed but being re-borrowed
                        $statusWarnings[] = "Asset '{$itemName}' (ID: {$assetId}) berstatus '{$status}'. Biasanya untuk peminjaman baru asset harus berstatus Stock/Ready to Deploy.";
                    }
                }

                // For RETURNS: Check if asset is actually assigned
                if ($validated['movementType'] === 'return') {
                    $linkedLoanId = (int) ($validated['linkedLoanId'] ?? 0);
                    
                    // More lenient check: allow return if asset is assigned OR has borrow status
                    $canReturn = ($state === 'borrow') || 
                                 ($assignedTo !== '-' && $assignedTo !== '') ||
                                 ($linkedLoanId > 0); // Trust linkedLoan reference
                    
                    if (!$canReturn && $state === 'stock') {
                        $statusErrors[] = "Asset '{$itemName}' (ID: {$assetId}) berstatus '{$status}' dan tidak ter-assign ke siapapun. Asset ini sepertinya belum dipinjam atau sudah dikembalikan. Pastikan Anda memilih dokumen peminjaman yang benar.";
                    } elseif ($state === 'unsupported') {
                        $statusErrors[] = "Asset '{$itemName}' (ID: {$assetId}) memiliki status '{$status}' yang tidak dapat diproses.";
                    } elseif ($state === 'stock') {
                        // Asset is stock but we have linkedLoanId, might be data inconsistency
                        $statusWarnings[] = "Asset '{$itemName}' (ID: {$assetId}) berstatus '{$status}' di Snipe-IT. Ini mungkin menandakan asset sudah dikembalikan sebelumnya atau terjadi inkonsistensi data.";
                    }
                }
            } catch (\Throwable $e) {
                Log::error('Asset validation error during peminjaman store', [
                    'asset_id' => $assetId,
                    'error' => $e->getMessage()
                ]);
                // Don't block on API errors, just warn
                $statusWarnings[] = "Asset ID {$assetId}: Tidak dapat memverifikasi status di Snipe-IT - {$e->getMessage()}";
            }
        }

        // Only block on critical errors, not warnings
        if (!empty($statusErrors)) {
            $allMessages = array_merge(
                ['Terdapat masalah dengan asset yang dipilih:'],
                $statusErrors
            );
            
            if (!empty($statusWarnings)) {
                $allMessages[] = '';
                $allMessages[] = 'Peringatan tambahan:';
                $allMessages = array_merge($allMessages, $statusWarnings);
            }
            
            return redirect()->back()->withErrors([
                'items' => $allMessages,
            ])->withInput();
        }

        // Log warnings but allow to proceed
        if (!empty($statusWarnings)) {
            Log::warning('Peminjaman created with status warnings', [
                'warnings' => $statusWarnings,
                'movement_type' => $validated['movementType'],
            ]);
        }

        if ($validated['movementType'] === 'return') {
            $linkedLoanId = $this->resolveLinkedLoanId($request, $validated);
            $linkedLoan = $this->validateLinkedLoanReference((int) ($linkedLoanId ?? 0));

            if ($linkedLoan) {
                return redirect()->route('peminjaman.show', $linkedLoan)
                    ->with('info', 'Pengembalian tidak membuat dokumen baru; lanjutkan dari dokumen pinjaman asal.');
            }

            throw new \InvalidArgumentException('Dokumen pinjaman asal wajib dipilih.');
        }

        try {
            $peminjaman = DB::transaction(function () use ($request, $validated) {
                $documentType = 'loan';
                $movementType = $validated['movementType'];
                $linkedLoanId = $this->resolveLinkedLoanId($request, $validated);
                $linkedLoan = null;

                // Fetch user + location data from Snipe-IT
                $snipeData = $this->fetchSnipeUserAndLocation(
                    (int) $validated['user_id'],
                    (int) $validated['group_id']
                );

                $peminjaman = Peminjaman::create([
                    'status' => $this->resolveLegacyStatus($documentType, $movementType),
                    'document_type' => $documentType,
                    'movement_type' => $movementType,
                    'linked_stb_id' => $linkedLoan?->id,
                    'returned_at' => null,
                    'it_drafter_id' => $validated['itDrafter_id'] ?? null,

                    'user_id'      => $validated['user_id'] ?? null,
                    'user_name'    => $snipeData['user_name'],
                    'user_company' => $snipeData['user_company'],
                    'user_dept'    => $snipeData['user_dept'],
                    'user_title'   => $snipeData['user_title'],
                    'user_phone'   => $snipeData['user_phone'],
                    'user_email'   => $snipeData['user_email'],

                    'group_id'     => $validated['group_id'] ?? null,
                    'location_name' => $snipeData['location_name'],
                    'use_date' => $validated['useDate'] ?? null,
                    'expected_return_date' => $validated['expectedReturnDate'] ?? null,
                    'photo' => null,
                    'remark' => $validated['remark'] ?? null,
                    'is_completed' => false,
                    'created_at' => $validated['createDate'] ?? now(),
                    'updated_at' => now(),
                ]);

                // Generate docId after peminjaman is created
                $docId = $this->formatDocId($peminjaman, null);
                
                $photoPath = $this->storePhoto($request, $docId);

                if ($photoPath) {
                    $peminjaman->update(['photo' => $photoPath]);
                }

                // Handle Multiple Attachments
                if ($request->hasFile('attachments')) {
                    foreach ($request->file('attachments') as $file) {
                        $peminjaman->attachments()->create([
                            'file_path' => $file->store('peminjaman-attachments', 'public'),
                            'file_type' => $file->getClientMimeType(),
                        ]);

                    }
                }

                foreach ($validated['items'] as $item) {
                    $peminjaman->items()->create($this->buildItemSnapshotPayload($item));
                }

                \Log::info('Peminjaman created successfully', [
                    'id' => $peminjaman->id,
                    'user_id' => $peminjaman->user_id,
                    'user_name' => $peminjaman->user_name,
                    'group_id' => $peminjaman->group_id,
                    'location_name' => $peminjaman->location_name,
                    'photo' => $peminjaman->photo,
                    'items_count' => $peminjaman->items()->count(),
                ]);

                // Log to ActionLog
                try {
                    $note = AssetNoteFormatterService::formatSimpleNote(
                        $peminjaman,
                        action: 'Document Created',
                        recipient: $peminjaman->user_name
                    );
                    
                    ActionLog::create([
                        'user_id'     => auth()->id(),
                        'action_type' => 'created',
                        'item_type'   => Peminjaman::class,
                        'item_id'     => $peminjaman->id,
                        'note'        => $note,
                        'log_meta'    => [
                            'peminjaman_id'  => $peminjaman->id,
                            'document_type'  => $peminjaman->document_type,
                            'movement_type'  => $peminjaman->movement_type,
                            'user_id'        => $peminjaman->user_id,
                            'user_name'      => $peminjaman->user_name,
                            'group_id'       => $peminjaman->group_id,
                            'items_count'    => $peminjaman->items()->count(),
                        ],
                    ]);
                } catch (\Exception $e) {
                    \Log::warning('Failed to write peminjaman action log', [
                        'action'        => 'created',
                        'peminjaman_id' => $peminjaman->id,
                        'error'         => $e->getMessage(),
                    ]);
                }

                return $peminjaman;
            });

            return redirect()->route('peminjaman.show', $peminjaman)->with('success', 'Dokumen peminjaman berhasil dibuat.');
        } catch (\Throwable $e) {
            ErrorMessageService::logError($e, 'peminjaman_create', ['payload_keys' => array_keys($request->except(['photo']))]);

            return redirect()->back()->with('error', ErrorMessageService::getUserFriendlyMessage($e, 'peminjaman_create'));
        }
    }

    public function show(Peminjaman $peminjaman)

    {
        $this->ensureLoanRecord($peminjaman);
        $peminjaman->load(['items', 'linkedStb', 'linkedReturns']);

        return Inertia::render('Peminjaman/Show', $this->buildLoanShareProps($peminjaman));
    }

    public function edit(Peminjaman $peminjaman)

    {
        $this->ensureLoanRecord($peminjaman);
        $this->ensureEditable($peminjaman);
        $peminjaman->load('items');

        return Inertia::render('Peminjaman/Edit', [
            'peminjaman' => array_merge($peminjaman->toArray(), [
                'linkedLoanId' => $peminjaman->linked_stb_id,
            ]),
            'loanReferences' => $this->buildOpenLoanReferences($peminjaman),
        ]);
    }

    public function update(Request $request, Peminjaman $peminjaman)

    {
        $this->ensureLoanRecord($peminjaman);
        $this->ensureEditable($peminjaman);

        $validator = Validator::make($request->all(), [
            'id' => 'nullable|integer',
            'docId' => 'nullable|string',
            'movementType' => ['required', Rule::in(['out', 'return'])],
            'linkedLoanId' => 'nullable|integer|exists:peminjamans,id',
            'itDrafter_id' => 'nullable|integer',

            'user_id' => 'required|integer',
            'group_id' => 'required|integer',
            'useDate' => 'nullable|date',
            'expectedReturnDate' => 'nullable|date',
            'photo' => 'nullable|image|max:2048',
            'attachments' => 'nullable|array',
            'attachments.*' => 'image|max:5120',
            'remark' => 'nullable|string',
            'createDate' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.id' => 'nullable|integer',
            'items.*.nama' => 'required|string',
            'items.*.kategori' => ['nullable', 'string', Rule::notIn(['component', 'components'])],
            'items.*.type' => 'required|string',
            'items.*.jumlah' => 'required|integer|min:1',
            'items.*.serialNo' => 'nullable|string',
            'items.*.inventory_number' => 'nullable|string',
            'items.*.computer_id' => 'nullable|integer',
            'items.*.snipeit_asset_id' => 'nullable|integer',
            'items.*.condition' => 'nullable|string|in:Good,Broken,Missing',
            'items.*.asset_reference_snapshot' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();

        try {
            DB::transaction(function () use ($request, $validated, $peminjaman) {
                $movementType = $validated['movementType'];
                $linkedLoanId = $this->resolveLinkedLoanId($request, $validated);
                $linkedLoan = $movementType === 'return'
                    ? $this->validateLinkedLoanReference((int) ($linkedLoanId ?? 0), $peminjaman)
                    : null;

                if ($movementType === 'return' && !$linkedLoan) {
                    throw new \InvalidArgumentException('Dokumen pinjaman asal wajib dipilih.');
                }

                // Fetch user + location data from Snipe-IT
                $snipeData = $this->fetchSnipeUserAndLocation(
                    (int) $validated['user_id'],
                    (int) $validated['group_id']
                );

                // Generate docId for photo storage
                $docId = $this->formatDocId($peminjaman, null);
                
                $photoPath = $peminjaman->photo;

                if ($request->hasFile('photo')) {
                    $photoPath = $this->storePhoto($request, $docId, $peminjaman->photo);
                }

                $peminjaman->update([
                    'status' => $this->resolveLegacyStatus('loan', $movementType),
                    'document_type' => 'loan',
                    'movement_type' => $movementType,
                    'linked_stb_id' => $linkedLoan?->id,
                    'returned_at' => $movementType === 'out' ? $peminjaman->returned_at : null,
                    'it_drafter_id' => $validated['itDrafter_id'] ?? null,

                    'user_id'      => $validated['user_id'] ?? null,
                    'user_name'    => $snipeData['user_name'],
                    'user_company' => $snipeData['user_company'],
                    'user_dept'    => $snipeData['user_dept'],
                    'user_title'   => $snipeData['user_title'],
                    'user_phone'   => $snipeData['user_phone'],
                    'user_email'   => $snipeData['user_email'],

                    'group_id'      => $validated['group_id'] ?? null,
                    'location_name' => $snipeData['location_name'],
                    'use_date' => $validated['useDate'] ?? null,
                    'expected_return_date' => $validated['expectedReturnDate'] ?? null,
                    'photo' => $photoPath,
                    'remark' => $validated['remark'] ?? null,
                    'is_completed' => false,
                ]);

                // Handle Multiple Attachments
                if ($request->hasFile('attachments')) {
                    foreach ($request->file('attachments') as $file) {
                        $peminjaman->attachments()->create([
                            'file_path' => $file->store('peminjaman-attachments', 'public'),
                            'file_type' => $file->getClientMimeType(),
                        ]);

                    }
                }

                $peminjaman->items()->delete();

                foreach ($validated['items'] as $item) {
                    $peminjaman->items()->create($this->buildItemSnapshotPayload($item));
                }
                
                \Log::info('Peminjaman updated successfully', [
                    'id' => $peminjaman->id,
                    'user_id' => $peminjaman->user_id,
                    'user_name' => $peminjaman->user_name,
                    'group_id' => $peminjaman->group_id,
                    'location_name' => $peminjaman->location_name,
                    'photo' => $peminjaman->photo,
                    'items_count' => $peminjaman->items()->count(),
                ]);
            });

            return redirect()->route('peminjaman.show', $peminjaman)->with('success', 'Dokumen peminjaman berhasil diperbarui.');
        } catch (\Throwable $e) {
            ErrorMessageService::logError($e, 'peminjaman_update', ['peminjaman_id' => $peminjaman->id]);

            return redirect()->back()->with('error', ErrorMessageService::getUserFriendlyMessage($e, 'peminjaman_update'));
        }
    }

    public function destroy(Peminjaman $peminjaman)

    {
        $this->ensureLoanRecord($peminjaman);
        $this->ensureEditable($peminjaman);

        try {
            if ($peminjaman->photo) {
                Storage::disk('public')->delete($peminjaman->photo);
            }

            if ($peminjaman->completed_pdf_path) {
                Storage::disk('public')->delete($peminjaman->completed_pdf_path);
            }

            $peminjaman->delete();

            return redirect()->route('peminjaman.index')->with('success', 'Dokumen peminjaman berhasil dihapus.');
        } catch (\Throwable $e) {
            ErrorMessageService::logError($e, 'peminjaman_delete', ['peminjaman_id' => $peminjaman->id]);

            return redirect()->back()->with('error', ErrorMessageService::getUserFriendlyMessage($e, 'peminjaman_delete'));
        }
    }

    public function quickReturn(Request $request, Peminjaman $peminjaman)
    {
        $this->ensureLoanRecord($peminjaman);

        if (!$this->isCompletedState($peminjaman)) {
            return response()->json(['message' => 'Dokumen harus difinalisasi terlebih dahulu sebelum pengembalian.'], 422);
        }

        if ($peminjaman->returned_at) {
            return response()->json(['message' => 'Dokumen ini sudah pernah dikembalikan.'], 422);
        }

        $request->validate([
            'photo' => 'required|image|max:5120',
        ]);

        try {
            $peminjaman->load('items');

            $pdfPath = null;

            DB::transaction(function () use ($request, $peminjaman, &$pdfPath) {
                // 1. Store return photo
                $photoPath = $request->file('photo')->store('peminjaman-photos', 'public');

                // 2. Mark as returned
                $peminjaman->update([
                    'returned_at'       => now(),
                    'return_photo_path' => $photoPath,
                ]);

                // 3. Snipe-IT checkin
                $this->processSnipeItCheckin($peminjaman);

                // 4. Generate PDF (now we have both loan photo + return photo)
                $pdfPath = $this->generateCompletedPdf($peminjaman);

                // 5. Save PDF path
                $peminjaman->update(['completed_pdf_path' => $pdfPath]);
            });

            // 6. Finalize: log history, upload PDF to Snipe-IT, flush cache
            $this->finalizeDocumentCompletion($peminjaman, $pdfPath);

            $pdfUrl = $pdfPath ? '/storage/' . ltrim($pdfPath, '/') : null;

            return response()->json([
                'message'           => 'Barang berhasil dikembalikan.',
                'returned_at'       => $peminjaman->returned_at,
                'completed_pdf_url' => $pdfUrl,
            ]);
        } catch (\Throwable $e) {
            ErrorMessageService::logError($e, 'peminjaman_complete', ['peminjaman_id' => $peminjaman->id]);

            return response()->json(['message' => ErrorMessageService::getUserFriendlyMessage($e, 'peminjaman_complete')], 500);
        }
    }

    public function cancel(Request $request, Peminjaman $peminjaman)

    {
        $this->ensureLoanRecord($peminjaman);
        $this->ensureEditable($peminjaman);

        if (!$this->hasCancellationColumns('peminjamans')) {

            return redirect()->back()->with('error', 'Kolom cancellation belum ada. Jalankan migration terlebih dahulu.');
        }

        try {
            $peminjaman->forceFill(['cancelled_at' => now()])->save();

            // Log cancellation
            try {
                \App\Models\ActionLog::create([
                    'user_id'     => auth()->id(),
                    'action_type' => 'cancelled',
                    'item_type'   => Peminjaman::class,
                    'item_id'     => $peminjaman->id,
                    'note'        => "Peminjaman #{$peminjaman->id} dibatalkan",
                    'log_meta'    => ['document_type' => 'loan', 'movement_type' => $peminjaman->movement_type ?? null],
                ]);
            } catch (\Throwable $logEx) {
                \Log::warning('Failed to write peminjaman cancel log', ['error' => $logEx->getMessage()]);
            }

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Dokumen peminjaman dibatalkan.']);
            }

            return redirect()->route('peminjaman.index', ['tab' => 'cancelled'])->with('success', 'Dokumen peminjaman dibatalkan.');
        } catch (\Throwable $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => ErrorMessageService::getUserFriendlyMessage($e, 'peminjaman_delete')], 500);
            }

            return redirect()->back()->with('error', ErrorMessageService::getUserFriendlyMessage($e, 'peminjaman_delete'));
        }
    }

    public function print(Peminjaman $peminjaman)
    {
        $this->ensureLoanRecord($peminjaman);
        $peminjaman->load(['items', 'attachments']);

        if ($peminjaman->completed_pdf_path && Storage::disk('public')->exists($peminjaman->completed_pdf_path)) {
            return response()->file(
                storage_path('app/public/' . $peminjaman->completed_pdf_path),
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="' . basename($peminjaman->completed_pdf_path) . '"',
                ],
            );
        }

        return Inertia::render('Peminjaman/Print', [
            'peminjaman' => $peminjaman,
            'shareUrl' => URL::temporarySignedRoute('peminjaman.share', now()->addDays(7), [
                'peminjaman' => $peminjaman->id,
            ]),
        ]);
    }

    public function complete(Request $request, Peminjaman $peminjaman)

    {
        $this->ensureLoanRecord($peminjaman);
        $peminjaman->load('items');

        if ($this->isCancelledState($peminjaman)) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Dokumen yang dibatalkan tidak bisa difinalisasi.'], 409)
                : redirect()->back()->with('error', 'Dokumen yang dibatalkan tidak bisa difinalisasi.');
        }

        if (!$this->hasCompletionColumns('peminjamans')) {

            return $request->expectsJson()
                ? response()->json(['message' => 'Kolom completion belum ada. Jalankan migration terlebih dahulu.'], 409)
                : redirect()->back()->with('error', 'Kolom completion belum ada. Jalankan migration terlebih dahulu.');
        }

        if (!$this->hasAllSignatures($peminjaman)) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Semua signature harus lengkap sebelum complete.'], 422)
                : redirect()->back()->with('error', 'Semua signature harus lengkap sebelum complete.');
        }

        if ($this->isLoanReturnDocument($peminjaman)) {
            try {
                $this->validateLinkedLoanReference((int) $peminjaman->linked_stb_id, $peminjaman);
            } catch (\InvalidArgumentException $exception) {
                return $request->expectsJson()
                    ? response()->json(['message' => $exception->getMessage()], 422)
                    : redirect()->back()->with('error', $exception->getMessage());
            }
        }

        try {
            // For return operations, Snipe-IT checkin is critical and must succeed
            if ($peminjaman->movement_type === 'return') {
                try {
                    $this->processSnipeItCheckin($peminjaman);
                } catch (\Throwable $e) {
                    Log::error('Snipe-IT Checkin failed during Peminjaman complete', [
                        'peminjaman_id' => $peminjaman->id,
                        'error' => $e->getMessage()
                    ]);
                    // Re-throw for return operations
                    throw $e;
                }
            } else {
                // For loan-out operations, Snipe-IT checkout is non-blocking
                // Log any errors but don't fail the complete operation
                try {
                    $this->processSnipeItCheckout($peminjaman);
                } catch (\Throwable $e) {
                    if ($peminjaman->items->contains(fn ($item) => strtolower((string) $item->kategori) === 'component')) {
                        throw $e;
                    }

                    Log::warning('Snipe-IT Checkout warning during Peminjaman complete (non-blocking)', [
                        'peminjaman_id' => $peminjaman->id,
                        'error' => $e->getMessage()
                    ]);
                    // Don't re-throw for loan-out operations
                }
            }

            // Loan-out: no PDF yet — PDF is generated after the physical return (quickReturn)
            $pdfPath = null;

            DB::transaction(function () use ($peminjaman, $pdfPath) {
                $peminjaman->update([
                    'completed_pdf_path' => $pdfPath,
                    'completed_at' => now(),
                    'is_completed' => true,
                ]);

                if ($this->isLoanReturnDocument($peminjaman) && $peminjaman->linked_stb_id) {
                    Peminjaman::query()
                        ->whereKey($peminjaman->linked_stb_id)
                        ->update([
                            'returned_at' => now(),
                        ]);
                }
            });

            // Centralized finalization: Logs history, Uploads PDF to Snipe-IT, Flushes cache, Triggers auto-service
            $this->finalizeDocumentCompletion($peminjaman, $pdfPath);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Dokumen peminjaman selesai difinalisasi.',
                    'completed_pdf_path' => $pdfPath,
                ]);
            }

            return redirect()->route('peminjaman.show', $peminjaman)->with('success', 'Dokumen peminjaman selesai difinalisasi.');
        } catch (\Throwable $e) {
            ErrorMessageService::logError($e, 'peminjaman_complete', ['peminjaman_id' => $peminjaman->id]);

            return $request->expectsJson()
                ? response()->json(['message' => ErrorMessageService::getUserFriendlyMessage($e, 'peminjaman_complete')], 500)
                : redirect()->back()->with('error', ErrorMessageService::getUserFriendlyMessage($e, 'peminjaman_complete'));
        }
    }

    public function sign(Request $request, Peminjaman $peminjaman, string $role)

    {
        $this->ensureLoanRecord($peminjaman);

        return $this->performSign($request, $peminjaman, $role, 'peminjaman.show', [$peminjaman]);
    }

    public function sharedShow(Peminjaman $peminjaman)

    {
        $this->ensureLoanRecord($peminjaman);

        if ($this->isCompletedState($peminjaman) || $this->isCancelledState($peminjaman)) {
            abort(410, 'Public link sudah tidak aktif.');
        }
        $peminjaman->load('items');

        return Inertia::render('Peminjaman/Show', $this->buildLoanShareProps($peminjaman, true));
    }

    public function sharedSign(Request $request, Peminjaman $peminjaman, string $role)

    {
        $this->ensureLoanRecord($peminjaman);

        return $this->performSign($request, $peminjaman, $role, null, [], true);
    }

    public function clearSign(Request $request, Peminjaman $peminjaman, string $role)

    {
        $this->ensureLoanRecord($peminjaman);

        return $this->performClearSign($request, $peminjaman, $role);
    }

    protected function hasAllSignatures(mixed $stb): bool
    {
        // Only require IT Drafter and User Borrower signatures for Loan
        return !empty($stb->it_drafter_signature_path) && !empty($stb->requester_received_signature_path);
    }

    public function lastOutPeminjaman(int $userId): \Illuminate\Http\JsonResponse
    {
        $loan = Peminjaman::query()
            ->where('user_id', $userId)
            ->where('movement_type', 'out')
            ->where('is_completed', true)
            ->latest()
            ->first();

        if (!$loan) {
            return response()->json(['peminjaman' => null]);
        }

        return response()->json([
            'peminjaman' => [
                'id' => $loan->id,
                'docId' => $this->formatDocId($loan, null),
            ]
        ]);
    }
}
