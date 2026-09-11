<?php

namespace App\Http\Controllers;

use App\Models\AuditItem;
use App\Models\AssetStockHistory;
use App\Models\Stb;
use App\Models\User;
use App\Services\AssetNoteFormatterService;
use App\Services\ErrorMessageService;
use App\Services\SnipeItService;
use App\Traits\DocumentCheckoutTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AssetController extends Controller
{
    use DocumentCheckoutTrait;

    private const ASSET_TYPES = [
        'assets' => 'Assets',
        'laptop' => 'Laptop',
        'license' => 'License',
        'accessories' => 'Accessories',
        'consumable' => 'Consumable',
        'component' => 'Component',
    ];

    public function __construct(
        private readonly SnipeItService $snipe,
    ) {}

    public function create(Request $request)
    {
        $activeType = $this->normalizeType((string) $request->query('type', 'assets'));

        return Inertia::render('Asset/Create', [
            'initialType' => $activeType,
            'types' => $this->buildTypes(),
            'metadata' => $this->buildCreateMetadata(),
        ]);
    }

    public function edit(Request $request, int $assetId)
    {
        $type = $this->normalizeType((string) $request->query('type', 'assets'));

        // Direct laptop asset edit to hardware (assets) edit
        if ($type === 'laptop') {
            return redirect()->route('asset.edit', [
                'assetId' => $assetId,
                'type' => 'assets',
            ]);
        }

        $metadata = $this->buildCreateMetadata();
        $record = $this->fetchAssetRecordByType($type, $assetId);

        if (! $record) {
            return redirect()
                ->route('asset.index', ['type' => $type])
                ->with('error', 'Asset data not found in Snipe-IT.');
        }

        // Pass full model+fieldset detail for edit so Vue doesn't need an async fetch
        $initialModelDetail = null;
        if ($type === 'assets') {
            $modelId = (int) data_get($record, 'model.id', 0);
            if ($modelId > 0) {
                $initialModelDetail = $this->fetchFullModelOption($modelId);
            }
        }

        $auditContext = null;
        $auditSessionId = (int) $request->query('audit_session', 0);
        $auditItemId = (int) $request->query('audit_item', 0);
        if ($auditSessionId > 0 && $auditItemId > 0) {
            $auditItem = AuditItem::where('id', $auditItemId)
                ->where('audit_session_id', $auditSessionId)
                ->where('snipeit_asset_id', $assetId)
                ->first();

            if ($auditItem) {
                $auditContext = [
                    'session_id' => $auditSessionId,
                    'item_id' => $auditItem->id,
                    'expected_location' => $auditItem->expected_location,
                    'expected_user' => $auditItem->expected_user,
                ];
            }
        }

        return Inertia::render($request->boolean('audit_page') ? 'Audit/AssetEdit' : 'Asset/Create', [
            'mode' => 'edit',
            'assetId' => $assetId,
            'initialType' => $type,
            'types' => $this->buildTypes(),
            'metadata' => $metadata,
            'initialData' => $this->mapAssetRecordToFormData($type, $record, $metadata),
            'initialModelDetail' => $initialModelDetail,
            'auditContext' => $auditContext,
            'auditPage' => $request->boolean('audit_page'),
        ]);
    }

    public function auditEdit(int $sessionId, int $itemId)
    {
        $auditItem = AuditItem::where('id', $itemId)
            ->where('audit_session_id', $sessionId)
            ->firstOrFail();

        $request = request()->merge([
            'type' => 'assets',
            'audit_session' => $sessionId,
            'audit_item' => $itemId,
            'audit_page' => true,
        ]);

        return $this->edit($request, (int) $auditItem->snipeit_asset_id);
    }

    public function show(Request $request, int $assetId)
    {
        $type = $this->normalizeType((string) $request->query('type', 'assets'));

        // Direct laptop asset detail to hardware (assets) detail
        if ($type === 'laptop') {
            return redirect()->route('asset.show', [
                'assetId' => $assetId,
                'type' => 'assets',
            ]);
        }

        $endpoint = $this->endpointForType($type);

        // Fire all Snipe-IT reads concurrently via HTTP Pool
        $poolResults = $this->snipe->requestPool([
            'record' => ["{$endpoint}/{$assetId}", []],
            'files' => ["{$endpoint}/{$assetId}/files", []],
            'checkout' => $this->checkoutEndpointForPool($type, $assetId),
            // TRULY PARALLEL DEEP SYNC: Request 3 pages of 500 simultaneously to bypass Snipe-IT server-side cap
            'hist_p1' => ['reports/activity', ['item_type' => $this->reportsTargetType($type), 'item_id' => $assetId, 'limit' => 500, 'offset' => 0]],
            'hist_p2' => ['reports/activity', ['item_type' => $this->reportsTargetType($type), 'item_id' => $assetId, 'limit' => 500, 'offset' => 500]],
            'hist_p3' => ['reports/activity', ['item_type' => $this->reportsTargetType($type), 'item_id' => $assetId, 'limit' => 500, 'offset' => 1000]],
        ], true);

        $record = $poolResults['record'] ?? [];
        if (empty($record['id'] ?? null)) {
            return redirect()
                ->route('asset.index', ['type' => $type])
                ->with('error', 'Detail data not found in Snipe-IT.');
        }

        // Build files from pool result with document resolution
        $rawFiles = is_array($poolResults['files']['rows'] ?? null) ? $poolResults['files']['rows'] : [];
        $assetFiles = collect($rawFiles)->map(function (array $f) {
            $docInfo = $this->resolveFileDocInfo($f);

            return [
                'id' => $f['id'] ?? null,
                'filename' => $f['name'] ?? $f['filename'] ?? '-',
                'download_url' => $this->fileProxyUrl($f['url'] ?? null),
                'created_by' => data_get($f, 'created_by.name', '-'),
                'date' => data_get($f, 'created_at.formatted', '-'),
                'notes' => $f['note'] ?? '-',
                'doc_no' => $docInfo['doc_no'],
                'doc_url' => $docInfo['doc_url'],
                'form_type' => $docInfo['form_type'],
                'form_name' => $docInfo['form_name'],
            ];
        })->sortByDesc('date')->values()->all();

        $view = match ($type) {
            'license' => 'Asset/ShowLicense',
            'accessories' => 'Asset/ShowAccessory',
            'consumable' => 'Asset/ShowConsumable',
            'component' => 'Asset/ShowComponent',
            default => 'Asset/Show',
        };

        return Inertia::render($view, [
            'assetType' => $type,
            'assetTypeLabel' => self::ASSET_TYPES[$type] ?? 'Asset',
            'asset' => $this->mapAssetDetail($type, $record),
            'assetFiles' => $assetFiles,
            'checkoutRecords' => $this->buildCheckoutFromPool($type, $assetId, $poolResults['checkout'] ?? []),
            'activityHistory' => $this->fetchActivityHistory($type, $assetId, array_merge(
                $poolResults['hist_p1']['rows'] ?? [],
                $poolResults['hist_p2']['rows'] ?? [],
                $poolResults['hist_p3']['rows'] ?? []
            ), $rawFiles),
        ]);
    }

    public function apiShow(Request $request, int $assetId): JsonResponse
    {
        $type = $this->normalizeType((string) $request->query('type', 'assets'));
        if ($type === 'laptop') {
            $type = 'assets';
        }
        $endpoint = $this->endpointForType($type);

        $poolResults = $this->snipe->requestPool([
            'record' => ["{$endpoint}/{$assetId}", []],
            'files' => ["{$endpoint}/{$assetId}/files", []],
            'checkout' => $this->checkoutEndpointForPool($type, $assetId),
            'hist_p1' => ['reports/activity', ['item_type' => $this->reportsTargetType($type), 'item_id' => $assetId, 'limit' => 500, 'offset' => 0]],
            'hist_p2' => ['reports/activity', ['item_type' => $this->reportsTargetType($type), 'item_id' => $assetId, 'limit' => 500, 'offset' => 500]],
            'hist_p3' => ['reports/activity', ['item_type' => $this->reportsTargetType($type), 'item_id' => $assetId, 'limit' => 500, 'offset' => 1000]],
        ], true);

        $record = $poolResults['record'] ?? [];
        if (empty($record['id'] ?? null)) {
            return response()->json(['error' => 'Data not found'], 404);
        }

        $rawFiles = is_array($poolResults['files']['rows'] ?? null) ? $poolResults['files']['rows'] : [];
        $assetFiles = collect($rawFiles)->map(function (array $f) {
            $docInfo = $this->resolveFileDocInfo($f);

            return [
                'id' => $f['id'] ?? null,
                'filename' => $f['name'] ?? $f['filename'] ?? '-',
                'download_url' => $this->fileProxyUrl($f['url'] ?? null),
                'created_by' => data_get($f, 'created_by.name', '-'),
                'date' => data_get($f, 'created_at.formatted', '-'),
                'notes' => $f['note'] ?? '-',
                'doc_no' => $docInfo['doc_no'],
                'doc_url' => $docInfo['doc_url'],
                'form_type' => $docInfo['form_type'],
                'form_name' => $docInfo['form_name'],
            ];
        })->values()->all();

        return response()->json([
            'assetType' => $type,
            'assetTypeLabel' => self::ASSET_TYPES[$type] ?? 'Asset',
            'asset' => $this->mapAssetDetail($type, $record),
            'assetFiles' => $assetFiles,
            'checkoutRecords' => $this->buildCheckoutFromPool($type, $assetId, $poolResults['checkout'] ?? []),
            'activityHistory' => $this->fetchActivityHistory($type, $assetId, array_merge(
                $poolResults['hist_p1']['rows'] ?? [],
                $poolResults['hist_p2']['rows'] ?? [],
                $poolResults['hist_p3']['rows'] ?? []
            ), $rawFiles),
        ]);
    }

    public function apiShowByTag(Request $request, string $tag): JsonResponse
    {
        // Search hardware by asset_tag in Snipe-IT
        $result = $this->snipe->getHardwareByAssetTag($tag);
        $rows = $result['rows'] ?? [];

        if (empty($rows)) {
            return response()->json(['error' => 'Asset not found'], 404);
        }

        $record = $rows[0];
        $assetId = (int) ($record['id'] ?? 0);

        if (! $assetId) {
            return response()->json(['error' => 'Asset not found'], 404);
        }

        return response()->json([
            'assetType' => 'assets',
            'assetTypeLabel' => 'Assets',
            'asset' => $this->mapAssetDetail('assets', $record),
        ]);
    }

    public function printLabel(Request $request, string $tag)
    {
        $result = $this->snipe->getHardwareByAssetTag($tag);
        $rows = $result['rows'] ?? [];

        if (empty($rows)) {
            return redirect()->route('asset.index')->with('error', 'Asset tidak ditemukan.');
        }

        $record = $rows[0];
        $asset = $this->mapAssetDetail('assets', $record);
        $ref = $asset['serial'] ?: $asset['asset_tag'];
        $publicUrl = $ref ? url("a/{$ref}") : url("a/{$tag}");

        // Return Blade view instead of Vue for better print control
        $statusColor = '#d97706';
        if ($asset['status_type'] === 'deployed') {
            $statusColor = '#059669';
        } elseif ($asset['status_type'] === 'deployable') {
            $statusColor = '#0284c7';
        } elseif ($asset['status_type'] === 'archived') {
            $statusColor = '#64748b';
        } elseif ($asset['status_type'] === 'undeployable') {
            $statusColor = '#dc2626';
        }

        // Generate inline vector QR SVG and base64 logo for 100% reliable offline/PDF rendering
        $svgQr = '';
        try {
            $svg = (new \BaconQrCode\Writer(
                new \BaconQrCode\Renderer\ImageRenderer(
                    new \BaconQrCode\Renderer\RendererStyle\RendererStyle(256, 0, null, null, \BaconQrCode\Renderer\RendererStyle\Fill::uniformColor(new \BaconQrCode\Renderer\Color\Rgb(255, 255, 255), new \BaconQrCode\Renderer\Color\Rgb(0, 0, 0))),
                    new \BaconQrCode\Renderer\Image\SvgImageBackEnd
                )
            ))->writeString($publicUrl);
            $svgQr = trim(substr($svg, strpos($svg, "\n") + 1));
        } catch (\Throwable $e) {
            $svgQr = '';
        }

        $logoBase64 = '';
        $logoPath = public_path('form-logo.png');
        if (file_exists($logoPath)) {
            $logoBase64 = 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath));
        }

        // Check if user wants direct PDF
        if ($request->query('format') === 'pdf') {
            return $this->generateLabelPdf($asset, $publicUrl, $statusColor, $tag, $svgQr, $logoBase64);
        }

        return view('asset.label_grid', [
            'asset' => $asset,
            'publicUrl' => $publicUrl,
            'statusColor' => $statusColor,
            'svgQr' => $svgQr,
            'logoBase64' => $logoBase64,
        ]);
    }

    private function generateLabelPdf(array $asset, string $publicUrl, string $statusColor, string $tag, string $svgQr = '', string $logoBase64 = '')
    {
        $browserPath = $this->pdfBrowserPath();
        if (! $browserPath) {
            return redirect()->route('asset.label.print', ['tag' => $tag])
                ->with('error', 'Browser untuk generate PDF tidak tersedia.');
        }

        $tempDirectory = storage_path('app/label-temp');
        if (! is_dir($tempDirectory)) {
            mkdir($tempDirectory, 0777, true);
        }

        $htmlPath = $tempDirectory.DIRECTORY_SEPARATOR.Str::uuid().'.html';
        $pdfPath = storage_path('app/public/asset-labels/label-'.Str::slug($asset['asset_tag'] ?? $tag).'.pdf');

        if (! is_dir(dirname($pdfPath))) {
            mkdir(dirname($pdfPath), 0777, true);
        }

        $profilePath = storage_path('app/browser-profile-'.Str::uuid());
        if (! is_dir($profilePath)) {
            mkdir($profilePath, 0777, true);
        }

        // Render HTML with embedded vector assets
        file_put_contents($htmlPath, view('asset.label_grid', [
            'asset' => $asset,
            'publicUrl' => $publicUrl,
            'statusColor' => $statusColor,
            'svgQr' => $svgQr,
            'logoBase64' => $logoBase64,
        ])->render());

        // Generate PDF with Chrome/Edge
        $process = new \Symfony\Component\Process\Process([
            $browserPath,
            '--headless=new',
            '--no-sandbox',
            '--disable-dev-shm-usage',
            '--disable-gpu',
            '--disable-crash-reporter',
            '--disable-breakpad',
            '--no-first-run',
            '--no-default-browser-check',
            '--disable-features=msEdgeCloudManagement,RendererCodeIntegrity',
            '--user-data-dir='.$profilePath,
            '--allow-file-access-from-files',
            '--no-pdf-header-footer',
            '--run-all-compositor-stages-before-draw',
            '--virtual-time-budget=12000',
            '--print-to-pdf='.$pdfPath,
            'file:///'.str_replace('\\', '/', $htmlPath),
        ]);
        $process->setTimeout(60);
        $process->run();

        @unlink($htmlPath);

        if (is_dir($profilePath)) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($profilePath, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($files as $f) {
                $f->isDir() ? @rmdir($f->getRealPath()) : @unlink($f->getRealPath());
            }
            @rmdir($profilePath);
        }

        if (! $process->isSuccessful() || ! is_file($pdfPath)) {
            Log::error('Label PDF generation failed', [
                'tag' => $tag,
                'error' => $process->getErrorOutput(),
            ]);

            return redirect()->route('asset.label.print', ['tag' => $tag])
                ->with('error', 'Gagal generate PDF label.');
        }

        return response()->download($pdfPath, 'label-'.Str::slug($asset['asset_tag'] ?? $tag).'.pdf');
    }

    public function printLabelPdf(Request $request, string $tag): \Symfony\Component\HttpFoundation\BinaryFileResponse|RedirectResponse
    {
        $rows = $this->snipe->getHardwareByAssetTag($tag)['rows'] ?? [];
        if ($rows === []) {
            return redirect()->route('asset.index')->with('error', 'Asset tidak ditemukan.');
        }

        $record = $rows[0];
        $assetTag = (string) ($record['asset_tag'] ?? $tag);
        $sizeKey = (string) $request->query('size', 'xs');
        $sizePresets = [
            'xs' => ['w' => 40,  'h' => 25],
            'sm' => ['w' => 50,  'h' => 30],
            'md' => ['w' => 62,  'h' => 29],
            'lg' => ['w' => 70,  'h' => 40],
            'xl' => ['w' => 100, 'h' => 50],
        ];
        $selectedSize = $sizePresets[$sizeKey] ?? $sizePresets['xs'];

        $viewData = [
            'asset' => [
                'name' => $record['name'] ?? $assetTag,
                'asset_tag' => $assetTag,
                'serial' => $record['serial'] ?? '',
                'location' => data_get($record, 'location.name', ''),
            ],
            'publicUrl' => url('a/'.($record['serial'] ?? $assetTag)),
            'w' => $selectedSize['w'],
            'h' => $selectedSize['h'],
        ];

        $browserPath = $this->pdfBrowserPath();
        if (! $browserPath) {
            return redirect()->route('asset.label.print', ['tag' => $tag])
                ->with('error', 'Browser PDF belum tersedia di server.');
        }

        $tempDirectory = storage_path('app/label-temp');
        if (! is_dir($tempDirectory)) {
            mkdir($tempDirectory, 0777, true);
        }
        $htmlPath = $tempDirectory.DIRECTORY_SEPARATOR.Str::uuid().'.html';
        $pdfPath = storage_path('app/public/asset-labels/'.Str::slug($assetTag).'.pdf');
        if (! is_dir(dirname($pdfPath))) {
            mkdir(dirname($pdfPath), 0777, true);
        }

        $profilePath = storage_path('app/browser-profile-'.Str::uuid());
        if (! is_dir($profilePath)) {
            mkdir($profilePath, 0777, true);
        }

        file_put_contents($htmlPath, view('asset.label_pdf', $viewData)->render());
        $process = new \Symfony\Component\Process\Process([
            $browserPath,
            '--headless=new',
            '--no-sandbox',
            '--disable-dev-shm-usage',
            '--disable-gpu',
            '--disable-crash-reporter',
            '--disable-breakpad',
            '--no-first-run',
            '--no-default-browser-check',
            '--disable-features=msEdgeCloudManagement,RendererCodeIntegrity',
            '--user-data-dir='.$profilePath,
            '--allow-file-access-from-files',
            '--no-pdf-header-footer',
            '--run-all-compositor-stages-before-draw',
            '--virtual-time-budget=12000',
            '--print-to-pdf='.$pdfPath,
            'file:///'.str_replace('\\', '/', $htmlPath),
        ]);
        $process->setTimeout(60);
        $process->run();
        @unlink($htmlPath);

        if (is_dir($profilePath)) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($profilePath, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($files as $f) {
                $f->isDir() ? @rmdir($f->getRealPath()) : @unlink($f->getRealPath());
            }
            @rmdir($profilePath);
        }

        if (! $process->isSuccessful() || ! is_file($pdfPath)) {
            Log::error('Asset label PDF generation failed', ['tag' => $tag, 'error' => $process->getErrorOutput()]);

            return redirect()->route('asset.label.print', ['tag' => $tag])->with('error', 'PDF label gagal dibuat.');
        }

        return response()->download($pdfPath, 'label-'.Str::slug($assetTag).'.pdf');
    }

    private function pdfBrowserPath(): ?string
    {
        $configured = trim((string) config('services.pdf.browser_path', ''));
        foreach (array_filter(array_merge([$configured], [
            'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
            'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
            '/usr/bin/chromium', '/usr/bin/chromium-browser',
            '/usr/bin/google-chrome', '/usr/bin/google-chrome-stable',
        ])) as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    public function tabData(Request $request, int $assetId): JsonResponse
    {
        $type = $this->normalizeType((string) $request->query('type', 'assets'));
        $tab = (string) $request->query('tab', '');

        if ($type !== 'assets' && $type !== 'laptop') {
            return response()->json([]);
        }

        $data = match ($tab) {
            'maintenances' => $this->fetchHardwareMaintenances($assetId, true),
            'licenses' => $this->fetchHardwareLicenses($assetId, true),
            'components' => $this->fetchHardwareComponents($assetId, true),
            'sub_assets' => $this->fetchHardwareSubAssets($assetId, true),
            default => [],
        };

        return response()->json($data);
    }

    public function addStock(Request $request, int $assetId): RedirectResponse
    {
        $type = $this->normalizeType((string) $request->input('type', 'assets'));

        if (! in_array($type, ['accessories', 'consumable', 'component', 'license'], true)) {
            abort(404);
        }

        $validated = $request->validate([
            'qty' => 'required|integer|min:1',
            'po_number' => 'nullable|string|max:100',
            'purchase_date' => 'nullable|date|before_or_equal:today',
            'notes' => 'nullable|string|max:1000',
            'document' => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx',
        ]);

        $addedQty = (int) $validated['qty'];
        $endpoint = $this->endpointForType($type);

        $current = $this->snipe->request("{$endpoint}/{$assetId}");

        // Licenses use 'seats', others use 'qty'
        $qtyField = ($type === 'license') ? 'seats' : 'qty';
        $currentQty = (int) ($current[$qtyField] ?? $current['qty'] ?? $current['total_qty'] ?? 0);
        $newQty = $currentQty + $addedQty;

        $syncPayload = [$qtyField => $newQty];
        if (! empty($validated['notes'])) {
            $syncPayload['notes'] = $validated['notes'];
        }
        $syncResult = $this->snipe->updateRecord($endpoint, $assetId, $syncPayload);
        if (($syncResult['status'] ?? 'error') === 'error') {
            Log::warning('addStock: Snipe-IT qty sync failed', [
                'asset_id' => $assetId, 'type' => $type,
                'error' => $syncResult['messages'] ?? $syncResult,
            ]);
        }

        $documentPath = null;
        if ($request->hasFile('document')) {
            $documentPath = $request->file('document')->store('stock-documents', 'public');
        }

        AssetStockHistory::query()->create([
            'asset_type' => $type,
            'asset_id' => $assetId,
            'qty' => $addedQty,
            'po_number' => (string) ($validated['po_number'] ?? ''),
            'purchase_date' => (string) ($validated['purchase_date'] ?? now()->toDateString()),
            'document_path' => $documentPath,
            'notes' => ! empty($validated['notes']) ? (string) $validated['notes'] : null,
            'created_by' => $request->user()?->id,
        ]);

        if ($request->hasFile('document')) {
            $file = $request->file('document');
            $noteParts = array_filter([
                ! empty($validated['po_number']) ? 'PO: '.$validated['po_number'] : null,
                ! empty($validated['purchase_date']) ? 'Tgl: '.$validated['purchase_date'] : null,
                ! empty($validated['notes']) ? $validated['notes'] : null,
            ]);
            $this->snipe->uploadFile(
                $endpoint,
                $assetId,
                (string) file_get_contents($file->getRealPath()),
                $file->getClientOriginalName(),
                $noteParts ? implode(' | ', $noteParts) : '',
            );
        }

        $note = "Tambah stok +{$addedQty} (total: {$newQty})"
            .(! empty($validated['po_number']) ? " | PO: {$validated['po_number']}" : '')
            .(! empty($validated['notes']) ? " | {$validated['notes']}" : '');
        $this->logAction('add_stock', $assetId, $this->normalizeType($type), $note, [
            'added_qty' => $addedQty,
            'new_qty' => $newQty,
            'po_number' => $validated['po_number'] ?? null,
            'purchase_date' => $validated['purchase_date'] ?? null,
        ]);

        $this->snipe->flushCacheForAsset($type, $assetId);

        return redirect()
            ->route('asset.show', ['assetId' => $assetId, 'type' => $type])
            ->with('success', "Stock berhasil ditambahkan +{$addedQty}. Total sekarang: {$newQty}.");
    }

    public function stockHistory(Request $request, int $assetId): JsonResponse
    {
        $type = $this->normalizeType((string) $request->query('type', 'accessories'));

        $rows = AssetStockHistory::query()
            ->where('asset_type', $type)
            ->where('asset_id', $assetId)
            ->with('createdBy:id,name')
            ->orderByDesc('purchase_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($h) => [
                'id' => $h->id,
                'qty' => $h->qty,
                'po_number' => $h->po_number ?: '-',
                'purchase_date' => $h->purchase_date?->format('d M Y') ?? '-',
                'notes' => $h->notes,
                'document_url' => $h->document_path
                    ? \Illuminate\Support\Facades\Storage::url($h->document_path)
                    : null,
                'created_by' => $h->createdBy?->name ?? 'System',
                'created_at' => $h->created_at?->timezone(config('app.timezone'))->format('d M Y H:i') ?? '-',
            ]);

        return response()->json($rows);
    }

    public function uploadDocument(Request $request, int $assetId): RedirectResponse
    {
        $type = $this->normalizeType((string) $request->input('type', 'assets'));

        $validated = $request->validate([
            'document' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx',
            'notes' => 'nullable|string|max:1000',
        ]);

        $file = $request->file('document');
        $endpoint = $this->endpointForType($type);

        $this->snipe->uploadFile(
            $endpoint,
            $assetId,
            (string) file_get_contents($file->getRealPath()),
            $file->getClientOriginalName(),
            trim((string) ($validated['notes'] ?? '')),
        );
        $this->logAction('upload', $assetId, $this->normalizeType($type), 'Uploaded document: '.$file->getClientOriginalName());
        $this->snipe->flushCacheForAsset($type, $assetId);

        return redirect()
            ->route('asset.show', ['assetId' => $assetId, 'type' => $type])
            ->with('success', 'Dokumen berhasil di-upload.');
    }

    public function proxyFile(Request $request)
    {
        $url = (string) $request->query('url', '');
        $configuredHost = parse_url((string) config('services.snipeit.url'), PHP_URL_HOST);
        $requestedHost = parse_url($url, PHP_URL_HOST);

        abort_if($url === '' || ! $configuredHost || $requestedHost !== $configuredHost, 404);

        $parts = parse_url($url);
        $path = (string) ($parts['path'] ?? '');
        if (preg_match('#^/(assets|hardware|licenses|accessories|consumables|components)/(\d+)/files/(\d+)$#', $path, $matches)) {
            $resource = $matches[1] === 'assets' ? 'hardware' : $matches[1];
            $host = $parts['host'];
            if (! empty($parts['port'])) {
                $host .= ':'.$parts['port'];
            }
            $url = sprintf(
                '%s://%s/api/v1/%s/%d/files/%d',
                $parts['scheme'] ?? 'http',
                $host,
                $resource,
                (int) $matches[2],
                (int) $matches[3],
            );
        }

        $response = \Illuminate\Support\Facades\Http::withToken((string) config('services.snipeit.token'))
            ->withHeaders([
                'Accept' => '*/*',
                'x-impersonate-user' => (string) (auth()->user()?->snipeit_user_id ?? ''),
            ])
            ->timeout((int) config('services.snipeit.timeout', 30))
            ->get($url);

        abort_unless($response->successful(), $response->status() === 404 ? 404 : 502);

        $body = $response->body();
        $contentType = $response->header('Content-Type', 'application/octet-stream');

        if ($request->boolean('preview') && str_starts_with($body, '%PDF-')) {
            $contentType = 'application/pdf';
        }

        return response($body, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    public function destroy(Request $request, int $assetId): RedirectResponse
    {
        $type = $this->normalizeType((string) $request->query('type', 'assets'));
        $endpoint = $type === 'laptop' ? 'hardware' : $this->endpointForType($type);
        $record = $this->snipe->request("{$endpoint}/{$assetId}", [], true);

        if (empty($record['id'])) {
            return back()->with('error', 'Asset tidak ditemukan di Snipe-IT.');
        }

        $assigned = data_get($record, 'assigned_to.id')
            ?? data_get($record, 'assigned_to');
        if ($assigned !== null && $assigned !== '' && $assigned !== '-') {
            return back()->with('error', 'Asset masih di-assign. Check-in asset terlebih dahulu.');
        }

        $referenced =
            \App\Models\StbItem::where('snipeit_asset_id', $assetId)->exists()
            || \App\Models\PeminjamanItem::where('snipeit_asset_id', $assetId)->exists();
        if ($referenced) {
            return back()->with('error', 'Asset masih digunakan dalam dokumen STB atau Peminjaman.');
        }

        $response = $this->snipe->deleteRecord($endpoint, $assetId);
        if (($response['status'] ?? 'error') !== 'success') {
            return back()->with('error', 'Asset tidak dapat dihapus dari Snipe-IT: '.$this->extractApiMessage($response));
        }

        $this->logAction('deleted', $assetId, $type, 'Asset dihapus dari Snipe-IT', [
            'asset_type' => $type,
            'asset_id' => $assetId,
        ]);

        return to_route('asset.index', ['type' => $type])->with('success', 'Asset berhasil dihapus.');
    }

    public function store(Request $request): RedirectResponse
    {
        $type = $this->normalizeType((string) $request->input('type', 'assets'));

        $validated = match ($type) {
            'assets' => $request->validate([
                'type' => 'required|string',
                'name' => 'nullable|string|max:255',
                'asset_tag' => 'required|string|max:255',
                'serial' => 'nullable|string|max:255',
                'model_id' => 'required|integer',
                'status_id' => 'nullable|integer',
                'company_id' => 'nullable|integer',
                'location_id' => 'nullable|integer',
                'notes' => 'nullable|string',
                'requestable' => 'nullable|boolean',
                'custom_fields' => 'nullable|array',
                // Optional Information
                'warranty_months' => 'nullable|integer|min:0',
                'expected_checkin' => 'nullable|date',
                'next_audit_date' => 'nullable|date',
                'byod' => 'nullable|boolean',
                // Order Related Information
                'order_number' => 'nullable|string|max:255',
                'purchase_date' => 'nullable|date',
                'asset_eol_date' => 'nullable|date',
                'supplier_id' => 'nullable|integer',
                'purchase_cost' => 'nullable|numeric|min:0',
                // Image
                'image' => 'nullable|file|max:5120|mimes:jpg,jpeg,png,gif,webp',
                'create_stb' => 'nullable|boolean',
                'stb_user_id' => 'nullable|required_if:create_stb,true|integer',
                'stb_location_id' => 'nullable|integer',
                'stb_building' => 'nullable|string|max:255',
                'stb_batch_no' => 'nullable|string|max:255',
                'stb_req_doc_no' => 'nullable|string|max:255',
                'stb_po_doc_no' => 'nullable|string|max:255',
                'stb_it_drafter_id' => 'nullable|required_if:create_stb,true|integer',
                'stb_it_checker_id' => 'nullable|required_if:create_stb,true|integer',
                'stb_it_approved_id' => 'nullable|required_if:create_stb,true|integer',
                'stb_remark' => 'nullable|string|max:1000',
            ]),
            'license' => $request->validate([
                'type' => 'required|string',
                'name' => 'required|string|max:255',
                'seats' => 'required|integer|min:1',
                'category_id' => 'required|integer',
                'company_id' => 'nullable|integer',
                'manufacturer_id' => 'nullable|integer',
                'supplier_id' => 'nullable|integer',
                'serial' => 'nullable|string|max:255',
                'license_name' => 'nullable|string|max:255',
                'license_email' => 'nullable|email|max:255',
                'reassignable' => 'nullable|boolean',
                'order_number' => 'nullable|string|max:255',
                'purchase_cost' => 'nullable|numeric|min:0',
                'purchase_date' => 'nullable|date',
                'expiration_date' => 'nullable|date',
                'termination_date' => 'nullable|date',
                'min_qty' => 'nullable|integer|min:0',
                'po_number' => 'nullable|string|max:100',
                'notes' => 'nullable|string',
                'depreciation_id' => 'nullable|integer',
                'maintained' => 'nullable|boolean',
                'create_stb' => 'nullable|boolean',
                'stb_user_id' => 'nullable|required_if:create_stb,true|integer',
                'stb_location_id' => 'nullable|integer',
                'stb_building' => 'nullable|string|max:255',
                'stb_use_date' => 'nullable|date',
                'stb_batch_no' => 'nullable|string|max:255',
                'stb_req_doc_no' => 'nullable|string|max:255',
                'stb_po_doc_no' => 'nullable|string|max:255',
                'stb_it_drafter_id' => 'nullable|required_if:create_stb,true|integer',
                'stb_it_checker_id' => 'nullable|required_if:create_stb,true|integer',
                'stb_it_approved_id' => 'nullable|required_if:create_stb,true|integer',
                'stb_remark' => 'nullable|string|max:1000',
            ]),
            'accessories', 'consumable', 'component' => $request->validate([
                'type' => 'required|string',
                'name' => 'required|string|max:255',
                'qty' => 'required|integer|min:1',
                'category_id' => 'required|integer',
                'company_id' => 'nullable|integer',
                'location_id' => 'nullable|integer',
                'manufacturer_id' => 'nullable|integer',
                'supplier_id' => 'nullable|integer',
                'model_number' => 'nullable|string|max:255',
                'item_no' => 'nullable|string|max:255',
                'serial' => 'nullable|string|max:255',
                'order_number' => 'nullable|string|max:255',
                'purchase_cost' => 'nullable|numeric|min:0',
                'purchase_date' => 'nullable|date',
                'min_qty' => 'nullable|integer|min:0',
                'notes' => 'nullable|string',
                'image' => 'nullable|file|max:5120|mimes:jpg,jpeg,png,gif,webp',
                'po_number' => 'nullable|string|max:100',
                'stock_document' => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx',
                'create_stb' => 'nullable|boolean',
                'stb_user_id' => 'nullable|required_if:create_stb,true|integer',
                'stb_location_id' => 'nullable|integer',
                'stb_building' => 'nullable|string|max:255',
                'stb_use_date' => 'nullable|date',
                'stb_batch_no' => 'nullable|string|max:255',
                'stb_req_doc_no' => 'nullable|string|max:255',
                'stb_po_doc_no' => 'nullable|string|max:255',
                'stb_it_drafter_id' => 'nullable|required_if:create_stb,true|integer',
                'stb_it_checker_id' => 'nullable|required_if:create_stb,true|integer',
                'stb_it_approved_id' => 'nullable|required_if:create_stb,true|integer',
                'stb_remark' => 'nullable|string|max:1000',
            ]),
            default => abort(404),
        };

        $endpoint = $this->endpointForType($type);

        // Auto-set "stock" (RTD) status for hardware assets on create
        if ($type === 'assets' && empty($validated['status_id'])) {
            $validated['status_id'] = $this->resolveStockStatusId();
        }

        $payload = match ($type) {
            'assets' => $this->buildHardwareCreatePayload($validated, $request),
            'license' => $this->buildLicenseCreatePayload($validated),
            default => $this->buildStockTypeCreatePayload($validated, $type, $request),
        };

        $response = $this->snipe->createRecord($endpoint, $payload);

        if (($response['status'] ?? 'error') !== 'success') {
            return back()
                ->withInput()
                ->with('error', 'Failed to create '.self::ASSET_TYPES[$type].': '.$this->extractApiMessage($response));
        }

        $createdId = (int) (data_get($response, 'payload.id') ?? data_get($response, 'id') ?? 0);

        if (in_array($type, ['accessories', 'consumable', 'component', 'license'], true) && $createdId > 0) {
            $this->storeStockHistory($request, $type, $createdId, $validated);
        }

        // Automated STB Creation
        if (! empty($validated['create_stb']) && ! empty($validated['stb_user_id']) && $createdId > 0) {
            try {
                // Fetch user and location details for snapshot
                $recipient = $this->snipe->getUser((int) $validated['stb_user_id']);
                $targetLocationId = $validated['stb_location_id'] ?? $validated['location_id'] ?? null;
                $location = $targetLocationId ? $this->snipe->getLocation((int) $targetLocationId) : null;

                $stb = Stb::create([
                    'deliver_date' => now(),
                    'status' => 1, // Default status for new STB
                    'document_type' => 'handover',
                    'movement_type' => 'out',
                    'user_id' => $validated['stb_user_id'],
                    'user_name' => trim(data_get($recipient, 'first_name', '').' '.data_get($recipient, 'last_name', '')) ?: data_get($recipient, 'name'),
                    'user_company' => data_get($recipient, 'company.name'),
                    'user_dept' => data_get($recipient, 'department.name'),
                    'user_title' => data_get($recipient, 'jobtitle') ?: data_get($recipient, 'title_name'),
                    'user_phone' => data_get($recipient, 'phone'),
                    'user_email' => data_get($recipient, 'email'),
                    'group_id' => $targetLocationId,
                    'location_name' => data_get($location, 'name'),
                    'building' => $validated['stb_building'] ?? null,
                    'use_date' => $validated['stb_use_date'] ?? now(),
                    'batch_no' => $validated['stb_batch_no'] ?? null,
                    'req_doc_no' => $validated['stb_req_doc_no'] ?? null,
                    'po_doc_no' => $validated['stb_po_doc_no'] ?? ($validated['order_number'] ?? null),
                    'it_drafter_id' => $validated['stb_it_drafter_id'] ?? null,
                    'it_checker_id' => $validated['stb_it_checker_id'] ?? null,
                    'it_approved_id' => $validated['stb_it_approved_id'] ?? null,
                    'photo' => $this->copyAssetImageToStb($request),
                    'remark' => $validated['stb_remark'] ?? 'Auto-generated STB from asset creation.',
                ]);

                if (! empty($validated['stb_send_notification'])) {
                    Log::info('STB Notification requested', [
                        'stb_id' => $stb->id,
                        'recipient' => $stb->user_email,
                    ]);

                }

                $stb->items()->create([
                    'nama' => $validated['name'] ?? data_get($response, 'payload.name') ?? 'New Asset',
                    'kategori' => $type,
                    'type' => $type === 'assets' ? (data_get($response, 'payload.model.name') ?? 'Hardware') : $type,
                    'jumlah' => $validated['qty'] ?? 1,
                    'serial_no' => $validated['serial'] ?? null,
                    'inventory_number' => $validated['asset_tag'] ?? null,
                    'snipeit_asset_id' => $createdId,
                ]);

                // Automated checkout will be handled when STB is marked as complete
                // $this->processSnipeItCheckout($stb);

                $assetName = $validated['name'] ?? ($validated['asset_tag'] ?? 'Asset Baru');
                $this->logAction('create', $createdId, $this->normalizeType($type), $validated['notes'] ?? "Asset {$assetName} dibuat & STB #{$stb->id} dibuat otomatis", array_merge($payload, [
                    'item_name' => $assetName,
                    'stb_id' => $stb->id,
                ]));

                return redirect()
                    ->route('stb.show', $stb->id)
                    ->with('success', self::ASSET_TYPES[$type].' created and STB generated successfully.');
            } catch (\Exception $e) {
                // Log error but the asset is already created
                ErrorMessageService::logError($e, 'stb_create', ['asset_type' => $type, 'auto_generated' => true]);
                // Fall through to log below
            }
        }

        // Log asset creation ONCE (for both regular creation and STB failure fallback)
        $assetName = $validated['name'] ?? ($validated['asset_tag'] ?? 'Asset Baru');
        $this->logAction('create', $createdId, $this->normalizeType($type), $validated['notes'] ?? "Asset baru dibuat: {$assetName}", array_merge($payload, [
            'item_name' => $assetName,
        ]));

        return redirect()
            ->route('asset.index', ['type' => $type])
            ->with('success', self::ASSET_TYPES[$type].' created successfully.');
    }

    public function update(Request $request, int $assetId): RedirectResponse
    {
        $type = $this->normalizeType((string) $request->input('type', 'assets'));

        $validated = match ($type) {
            'assets' => $request->validate([
                'type' => 'required|string',
                'name' => 'nullable|string|max:255',
                'asset_tag' => 'required|string|max:255',
                'serial' => 'nullable|string|max:255',
                'model_id' => 'required|integer',
                'status_id' => 'nullable|integer',
                'company_id' => 'nullable|integer',
                'location_id' => 'nullable|integer',
                'notes' => 'nullable|string',
                'requestable' => 'nullable|boolean',
                'custom_fields' => 'nullable|array',
                // Optional Information
                'warranty_months' => 'nullable|integer|min:0',
                'expected_checkin' => 'nullable|date',
                'next_audit_date' => 'nullable|date',
                'byod' => 'nullable|boolean',
                // Order Related Information
                'order_number' => 'nullable|string|max:255',
                'purchase_date' => 'nullable|date',
                'asset_eol_date' => 'nullable|date',
                'supplier_id' => 'nullable|integer',
                'purchase_cost' => 'nullable|numeric|min:0',
                // Image
                'image' => 'nullable|file|max:5120|mimes:jpg,jpeg,png,gif,webp',
            ]),
            'license' => $request->validate([
                'type' => 'required|string',
                'name' => 'required|string|max:255',
                'seats' => 'required|integer|min:1',
                'category_id' => 'required|integer',
                'company_id' => 'nullable|integer',
                'manufacturer_id' => 'nullable|integer',
                'supplier_id' => 'nullable|integer',
                'serial' => 'nullable|string|max:255',
                'license_name' => 'nullable|string|max:255',
                'license_email' => 'nullable|email|max:255',
                'reassignable' => 'nullable|boolean',
                'order_number' => 'nullable|string|max:255',
                'purchase_cost' => 'nullable|numeric|min:0',
                'purchase_date' => 'nullable|date',
                'expiration_date' => 'nullable|date',
                'termination_date' => 'nullable|date',
                'min_qty' => 'nullable|integer|min:0',
                'po_number' => 'nullable|string|max:100',
                'notes' => 'nullable|string',
                'depreciation_id' => 'nullable|integer',
                'maintained' => 'nullable|boolean',
            ]),
            'accessories', 'consumable', 'component' => $request->validate([
                'type' => 'required|string',
                'name' => 'required|string|max:255',
                'qty' => 'required|integer|min:1',
                'category_id' => 'required|integer',
                'company_id' => 'nullable|integer',
                'location_id' => 'nullable|integer',
                'manufacturer_id' => 'nullable|integer',
                'supplier_id' => 'nullable|integer',
                'model_number' => 'nullable|string|max:255',
                'item_no' => 'nullable|string|max:255',
                'serial' => 'nullable|string|max:255',
                'order_number' => 'nullable|string|max:255',
                'purchase_cost' => 'nullable|numeric|min:0',
                'purchase_date' => 'nullable|date',
                'min_qty' => 'nullable|integer|min:0',
                'notes' => 'nullable|string',
                'image' => 'nullable|file|max:5120|mimes:jpg,jpeg,png,gif,webp',
                'po_number' => 'nullable|string|max:100',
                'stock_document' => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx',
            ]),
            default => abort(404),
        };

        $endpoint = $this->endpointForType($type);
        $currentRecord = $this->fetchAssetRecordByType($type, $assetId) ?? [];

        $payload = match ($type) {
            'assets' => $this->buildHardwareCreatePayload($validated, $request),
            'license' => $this->buildLicenseCreatePayload($validated),
            default => $this->buildStockTypeCreatePayload($validated, $type, $request),
        };

        $response = $this->snipe->updateRecord($endpoint, $assetId, $payload);

        if (($response['status'] ?? 'error') !== 'success') {
            return back()
                ->withInput()
                ->with('error', 'Failed to update '.self::ASSET_TYPES[$type].': '.$this->extractApiMessage($response));
        }

        $assetName = $validated['name'] ?? ($validated['asset_tag'] ?? data_get($currentRecord, 'name'));
        $changedFields = $this->detectChangedAssetFields($type, $currentRecord, $validated, $payload, $request);
        $this->logAction('update', $assetId, $this->normalizeType($type), null, array_merge($payload, [
            'item_name' => $assetName,
            'changed_fields' => $changedFields,
        ]));
        $this->snipe->flushCacheForAsset($type, $assetId);

        return redirect()
            ->route('asset.show', ['assetId' => $assetId, 'type' => $type])
            ->with('success', self::ASSET_TYPES[$type].' updated successfully.');
    }

    public function index(Request $request, ?string $status = null)
    {
        $requestedType = $this->normalizeType((string) $request->query('type', 'assets'));
        $laptopOnly = $requestedType === 'laptop'
            || strtolower((string) $request->query('category', '')) === 'laptop';
        $activeType = $laptopOnly && $requestedType === 'laptop'
            ? 'laptop'
            : ($laptopOnly ? 'assets' : $requestedType);
        $forceRefresh = $request->boolean('refresh') || $request->boolean('force_refresh');

        $statuses = collect($this->snipe->fetchRows('statuslabels', [], 500, $forceRefresh))
            ->map(fn (array $status) => [
                'id' => (int) ($status['id'] ?? 0),
                'name' => (string) ($status['name'] ?? '-'),
            ])
            ->filter(fn (array $status) => $status['id'] > 0)
            ->values();

        $showStatusFilter = in_array($activeType, ['assets', 'laptop'], true);
        $activeState = $showStatusFilter && is_numeric($status) ? (int) $status : null;

        $assets = match ($activeType) {
            'consumable' => $this->buildConsumables($forceRefresh),
            'license' => $this->buildLicenses($forceRefresh),
            'accessories' => $this->buildAccessories($forceRefresh),
            'component' => $this->buildComponents($forceRefresh),
            default => $this->buildAssets(
                $forceRefresh,
                $laptopOnly ? 'laptop' : null,
                $activeState,
                $statuses->pluck('id')->all(),
            ),
        };

        $filteredAssets = $activeState
            ? $assets->filter(fn (array $asset) => (int) ($asset['state'] ?? 0) === $activeState)->values()
            : $assets;

        $stateCounts = $showStatusFilter
            ? $assets->groupBy(fn (array $asset) => (int) ($asset['state'] ?? 0))->map(fn ($group) => $group->count())
            : collect();

        return Inertia::render('Asset/List', [
            'types' => $this->buildTypes(),
            'statuses' => $showStatusFilter
                ? $statuses->map(fn (array $status) => array_merge($status, [
                    'count' => $stateCounts[$status['id']] ?? 0,
                ]))->all()
                : [],
            'assets' => $filteredAssets->all(),
            'activeStatus' => $activeState,
            'activeType' => $activeType,
            'activeTypeLabel' => self::ASSET_TYPES[$activeType] ?? 'Asset',
            'showStatusFilter' => $showStatusFilter,
            'totalAssets' => $assets->count(),
            'metadata' => $this->buildCreateMetadata(),
            'loanReferences' => $this->buildOpenLoanReferences(),
        ]);
    }

    public function bulkCheckout(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|integer',
            'type' => ['required', 'string', Rule::in(array_keys(self::ASSET_TYPES))],
            'recipient_id' => 'required',
            'recipient_name' => 'required|string',
            'stb_no' => 'required|string',
            'deliver_date' => 'required|date',
            'use_date' => 'nullable|date',
            'building' => 'nullable|string',
            'floor' => 'nullable|string',
            'room' => 'nullable|string',
            'checker_id' => 'nullable|integer',
            'approved_id' => 'nullable|integer',
            'items' => 'required|array|min:1',
            'items.*' => 'required|array',
            'items.*.id' => 'required|integer',
            'items.*.name' => 'required|string',
            'items.*.qty' => 'nullable|integer|min:1',
            'send_notification' => 'nullable|boolean',
            'remark' => 'nullable|string|max:1000',
        ]);

        try {
            $stbUserId = (int) $validated['recipient_id'];
            $recipient = $this->snipe->getUser((int) $validated['recipient_id']);

            $type = $this->normalizeType($validated['type']);

            $itemIds = collect($validated['items'])->pluck('id')->map(fn ($id) => (int) $id);
            $submittedIds = collect($validated['ids'])->map(fn ($id) => (int) $id);
            if ($itemIds->count() !== count($validated['ids'])
                || $itemIds->unique()->count() !== $itemIds->count()
                || $itemIds->sort()->values()->all() !== $submittedIds->sort()->values()->all()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'items' => 'Daftar item tidak valid.',
                ]);
            }

            foreach ($validated['items'] as $item) {
                $record = $this->fetchAssetRecordByType($type, (int) $item['id']);

                if (! $record) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'items' => "Item {$item['name']} tidak ditemukan atau tidak sesuai tipe.",
                    ]);
                }

                if (in_array($type, ['assets', 'laptop'], true)) {
                    continue;
                }

                // Snipe-IT returns remaining qty in different fields based on type
                $remaining = match ($type) {
                    'license' => (int) ($record['free_seats_count'] ?? $record['free_seats'] ?? 0),
                    default => (int) ($record['remaining_qty'] ?? $record['remaining'] ?? 0),
                };

                $requestedQty = (int) ($item['qty'] ?? 1);

                if ($remaining < $requestedQty) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'stock' => "Tolong cek kembali ketersediaan {$item['name']}, saat ini stocknya {$remaining}.",
                    ]);
                }
            }

            $stb = Stb::create([
                'deliver_date' => $validated['deliver_date'],
                'status' => 1,
                'document_type' => 'handover',
                'movement_type' => 'out',
                'user_id' => $stbUserId,
                'user_name' => $validated['recipient_name'],
                'user_company' => data_get($recipient, 'company.name'),
                'user_dept' => data_get($recipient, 'department.name'),
                'user_title' => data_get($recipient, 'jobtitle') ?: data_get($recipient, 'title_name'),
                'user_phone' => data_get($recipient, 'phone'),
                'user_email' => data_get($recipient, 'email'),
                'location_name' => data_get($recipient, 'location.name'),
                'building' => $validated['building'],
                'floor' => $validated['floor'],
                'room' => $validated['room'],
                'use_date' => $validated['use_date'] ?? now(),
                'it_drafter_id' => $request->user()?->id,
                'it_checker_id' => $validated['checker_id'],
                'it_approved_id' => $validated['approved_id'],
                'remark' => $validated['remark'] ?? ('Bulk checkout of '.count($validated['ids']).' items.'),
            ]);

            foreach ($validated['items'] as $item) {
                $stb->items()->create([
                    'nama' => $item['name'],
                    'kategori' => $validated['type'],
                    'type' => $item['model'] ?? $validated['type'],
                    'jumlah' => $item['qty'] ?? 1,
                    'serial_no' => $item['serial'] ?? null,
                    'inventory_number' => $item['asset_tag'] ?? null,
                    'snipeit_asset_id' => $item['id'],
                ]);
            }

            $this->processSnipeItCheckout($stb);

            foreach ($validated['items'] as $item) {
                $this->logAction('checkout', $item['id'], $this->normalizeType($validated['type']), "Checkout ke {$validated['recipient_name']} via STB #{$stb->id}", [
                    'stb_id' => $stb->id,
                    'recipient' => $validated['recipient_name'],
                    'item_name' => $item['name'],
                ]);
            }

            return redirect()
                ->route('asset.index', ['type' => $type])
                ->with('success', 'STB berhasil dibuat dan item diserahkan.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            ErrorMessageService::logError($e, 'batch_process');

            return redirect()
                ->back()
                ->with('error', ErrorMessageService::getUserFriendlyMessage($e, 'batch_process'));
        }
    }

    private function buildOpenLoanReferences(): array
    {
        try {
            $peminjamans = \App\Models\Peminjaman::query()
                ->latest('created_at')
                ->where('document_type', 'loan')
                ->where('movement_type', 'out')
                ->whereNull('cancelled_at')
                ->whereNull('returned_at')
                ->get()
                ->map(function ($pem) {
                    $docId = (string) $pem->id;
                    if ($pem->id) {
                        $docId = 'PEM-'.str_pad((string) $pem->id, 5, '0', STR_PAD_LEFT);
                    }

                    return [
                        'id' => $pem->id,
                        'docId' => $docId,
                        'label' => trim($docId.' - '.($pem->user_name ?? '')),
                    ];
                })
                ->values()
                ->all();

            return $peminjamans;
        } catch (\Exception $e) {
            \Log::warning('Failed to fetch open loan references', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function buildTypes(): array
    {
        return collect(self::ASSET_TYPES)
            ->map(fn (string $label, string $key) => [
                'key' => $key,
                'endpoint' => $key,
                'label' => $label,
            ])
            ->values()
            ->all();
    }

    private function endpointForType(string $type): string
    {
        return match ($type) {
            'assets', 'laptop' => 'hardware',
            'license' => 'licenses',
            'accessories' => 'accessories',
            'consumable' => 'consumables',
            'component' => 'components',
            default => 'hardware',
        };
    }

    private function fetchAssetRecordByType(string $type, int $assetId): ?array
    {
        return match ($type) {
            'assets', 'laptop' => $this->snipe->getHardware($assetId),
            'license' => $this->snipe->getLicense($assetId),
            'accessories' => $this->snipe->getAccessory($assetId),
            'consumable' => $this->snipe->getConsumable($assetId),
            'component' => $this->snipe->getComponent($assetId),
            default => null,
        };
    }

    private function mapAssetRecordToFormData(string $type, array $record, array $metadata): array
    {
        $base = [
            'name' => (string) ($record['name'] ?? ''),
            'asset_tag' => (string) ($record['asset_tag'] ?? ''),
            'serial' => (string) ($record['serial'] ?? ''),
            'model_id' => data_get($record, 'model.id') ? (string) data_get($record, 'model.id') : '',
            'status_id' => data_get($record, 'status_label.id') ? (string) data_get($record, 'status_label.id') : '',
            'category_id' => data_get($record, 'category.id') ? (string) data_get($record, 'category.id') : '',
            'company_id' => data_get($record, 'company.id') ? (string) data_get($record, 'company.id') : '',
            'location_id' => data_get($record, 'rtd_location.id')
                ? (string) data_get($record, 'rtd_location.id')
                : (data_get($record, 'location.id') ? (string) data_get($record, 'location.id') : ''),
            'qty' => (int) ($record['qty'] ?? 1),
            'seats' => (int) ($record['seats'] ?? 1),
            'notes' => (string) ($record['notes'] ?? ''),
            'po_number' => '',
            'purchase_date' => '',
            'custom_fields' => [],
        ];

        if ($type !== 'assets') {
            $base['manufacturer_id'] = data_get($record, 'manufacturer.id') ? (string) data_get($record, 'manufacturer.id') : '';
            $base['supplier_id'] = data_get($record, 'supplier.id') ? (string) data_get($record, 'supplier.id') : '';
            $base['order_number'] = (string) ($record['order_number'] ?? '');
            $base['purchase_cost'] = $this->extractCost($record['purchase_cost'] ?? null);
            $base['purchase_date'] = $this->extractRawDate($record['purchase_date'] ?? '');
            $base['min_qty'] = $this->extractMinQty($record);

            if ($type === 'license') {
                $base['serial'] = (string) ($record['product_key'] ?? '');
                $base['license_name'] = (string) ($record['license_name'] ?? '');
                $base['license_email'] = (string) ($record['license_email'] ?? '');
                $base['reassignable'] = (bool) ($record['reassignable'] ?? false);
                $base['maintained'] = (bool) ($record['maintained'] ?? false);
                $base['expiration_date'] = $this->extractRawDate($record['expiration_date'] ?? '');
                $base['termination_date'] = $this->extractRawDate($record['termination_date'] ?? '');
                if ($base['purchase_cost'] === '' && isset($record['purchase_cost_numeric'])) {
                    $base['purchase_cost'] = (string) $record['purchase_cost_numeric'];
                }
            }

            if (in_array($type, ['accessories', 'consumable', 'component'], true)) {
                $base['model_number'] = $this->valueToString($record['model_number'] ?? '');
            }
            if ($type === 'consumable') {
                $base['item_no'] = $this->valueToString($record['item_no'] ?? '');
            }

            return $base;
        }

        $base['requestable'] = (bool) ($record['requestable'] ?? false);
        $base['warranty_months'] = $record['warranty_months'] !== null ? (string) ($record['warranty_months'] ?? '') : '';
        $base['expected_checkin'] = $this->extractRawDate($record['expected_checkin'] ?? '');
        $base['next_audit_date'] = $this->extractRawDate($record['next_audit_date'] ?? '');
        $base['byod'] = (bool) ($record['byod'] ?? false);
        $base['order_number'] = (string) ($record['order_number'] ?? '');
        $base['purchase_date'] = $this->extractRawDate($record['purchase_date'] ?? '');
        $base['asset_eol_date'] = $this->extractRawDate($record['asset_eol_date'] ?? '');
        $base['supplier_id'] = data_get($record, 'supplier.id') ? (string) data_get($record, 'supplier.id') : '';
        $base['purchase_cost'] = $this->extractCost($record['purchase_cost'] ?? null);
        $base['custom_fields'] = $this->mapCustomFieldsForForm($record['custom_fields'] ?? null, $record);

        return $base;
    }

    private function mapAssetDetail(string $type, array $record): array
    {
        $qty = (int) ($record['qty'] ?? $record['seats'] ?? 0);
        $remainingQty = (int) ($record['remaining_qty'] ?? $record['remaining'] ?? $record['free_seats_count'] ?? $record['free_seats'] ?? $qty);

        $base = [
            'id' => (int) ($record['id'] ?? 0),
            'name' => $this->valueToString($record['name'] ?? $record['asset_tag'] ?? '-', '-'),
            'asset_tag' => $this->valueToString($record['asset_tag'] ?? ''),
            'serial' => $this->valueToString($record['serial'] ?? $record['product_key'] ?? ''),
            'model' => $this->valueToString(data_get($record, 'model.name', '')),
            'category' => $this->valueToString(data_get($record, 'category.name', '')),
            'manufacturer' => $this->valueToString(data_get($record, 'manufacturer.name', '')),
            'location' => $this->valueToString(
                data_get($record, 'location.name', data_get($record, 'rtd_location.name', '')),
            ),
            'company' => $this->valueToString(data_get($record, 'company.name', '')),
            'status' => $this->valueToString(data_get($record, 'status_label.name', 'Available'), 'Available'),
            'status_name' => $this->valueToString(
                data_get($record, 'status_label.name')
                    ?? $record['status_name']
                    ?? $record['state_name']
                    ?? $record['status']
                    ?? 'Available',
                'Available',
            ),
            'qty' => $qty,
            'remaining_qty' => $remainingQty,
            'checked_out' => max(0, $qty - $remainingQty),
            'requestable' => (bool) ($record['requestable'] ?? false),
            'image' => $this->valueToString($record['image'] ?? ''),
            'created_by' => $this->valueToString(data_get($record, 'created_by.name', '')),
            'assigned_to' => $this->valueToString(data_get($record, 'assigned_to.name', '')),
            'notes' => $this->valueToString($record['notes'] ?? ''),
            'created_at' => $this->extractFormattedDate($record['created_at'] ?? ''),
            'updated_at' => $this->extractFormattedDate($record['updated_at'] ?? ''),
            'custom_fields' => $this->mapCustomFields($record['custom_fields'] ?? null, $record),
            'model_number' => $this->valueToString($record['model_number'] ?? ''),
        ];

        if ($type === 'assets' || $type === 'laptop') {
            return array_merge($base, [
                'status_type' => $this->valueToString(
                    data_get($record, 'status_label.status_type')
                        ?? data_get($record, 'status_label.type')
                        ?? ($record['status_type'] ?? null)
                        ?? ($record['status_type_name'] ?? null)
                        ?? data_get($record, 'status_label.name', ''),
                ),
                'rtd_location' => $this->valueToString(data_get($record, 'rtd_location.name', '')),
                'supplier' => $this->valueToString(data_get($record, 'supplier.name', '')),
                'purchase_date' => $this->extractFormattedDate($record['purchase_date'] ?? ''),
                'purchase_cost' => $this->valueToString($record['purchase_cost'] ?? ''),
                'order_number' => $this->valueToString($record['order_number'] ?? ''),
                'warranty_months' => $this->valueToString($record['warranty_months'] ?? ''),
                'warranty_expires' => $this->extractFormattedDate($record['warranty_expires'] ?? ''),
                'asset_eol_date' => $this->extractFormattedDate($record['asset_eol_date'] ?? ''),
                'book_value' => $this->valueToString($record['book_value'] ?? ''),
                'last_audit_date' => $this->extractFormattedDate($record['last_audit_date'] ?? ''),
                'next_audit_date' => $this->extractFormattedDate($record['next_audit_date'] ?? ''),
                'last_checkout' => $this->extractFormattedDate($record['last_checkout'] ?? ''),
                'last_checkin' => $this->extractFormattedDate($record['last_checkin'] ?? ''),
                'expected_checkin' => $this->extractFormattedDate($record['expected_checkin'] ?? ''),
                'checkin_counter' => (int) ($record['checkin_counter'] ?? 0),
                'checkout_counter' => (int) ($record['checkout_counter'] ?? 0),
                'assigned_to_type' => $this->valueToString(data_get($record, 'assigned_to.type', '')),
                'assigned_to_username' => $this->valueToString(data_get($record, 'assigned_to.username', '')),
                'assigned_to_email' => $this->valueToString(data_get($record, 'assigned_to.email', '')),
                'assigned_to_jobtitle' => $this->valueToString(data_get($record, 'assigned_to.jobtitle', '')),
                'byod' => (bool) ($record['byod'] ?? false),
            ]);
        }

        if ($type === 'license') {
            return array_merge($base, [
                'seats' => $qty,
                'free_seats' => $remainingQty,
                'license_name' => $this->valueToString($record['license_name'] ?? ''),
                'license_email' => $this->valueToString($record['license_email'] ?? ''),
                'reassignable' => (bool) ($record['reassignable'] ?? false),
                'expiration_date' => $this->extractFormattedDate($record['expiration_date'] ?? ''),
                'termination_date' => $this->extractFormattedDate($record['termination_date'] ?? ''),
                'purchase_date' => $this->extractFormattedDate($record['purchase_date'] ?? ''),
                'purchase_cost' => $this->valueToString($record['purchase_cost'] ?? ''),
                'order_number' => $this->valueToString($record['order_number'] ?? ''),
                'maintained' => (bool) ($record['maintained'] ?? false),
            ]);
        }

        if ($type === 'consumable') {
            return array_merge($base, [
                'item_no' => $this->valueToString($record['item_no'] ?? ''),
                'min_qty' => $this->valueToString($record['min_amt'] ?? ''),
                'order_number' => $this->valueToString($record['order_number'] ?? ''),
                'purchase_date' => $this->extractFormattedDate($record['purchase_date'] ?? ''),
                'purchase_cost' => $this->valueToString($record['purchase_cost'] ?? ''),
            ]);
        }

        // Accessories and Components
        return array_merge($base, [
            'min_qty' => $this->valueToString($record['min_qty'] ?? $record['min_amt'] ?? ''),
            'order_number' => $this->valueToString($record['order_number'] ?? ''),
            'purchase_date' => $this->extractFormattedDate($record['purchase_date'] ?? ''),
            'purchase_cost' => $this->valueToString($record['purchase_cost'] ?? ''),
        ]);
    }

    private function mapCustomFields(mixed $fields, array $fullRecord = []): array
    {
        $mapped = [];

        // 1. Try to map from the standard custom_fields key
        if (is_array($fields) && ! empty($fields)) {
            foreach ($fields as $name => $f) {
                if (is_array($f) && isset($f['value'])) {
                    $mapped[] = [
                        'name' => (string) ($f['name'] ?? $f['label'] ?? $name),
                        'value' => $this->valueToString($f['value'] ?? ''),
                        'format' => $this->valueToString($f['field_format'] ?? ''),
                    ];
                }
            }
        }

        // 2. If empty, try to find root-level _snipeit_ fields (some API responses differ)
        if (empty($mapped) && ! empty($fullRecord)) {
            foreach ($fullRecord as $key => $value) {
                if (str_starts_with($key, '_snipeit_') && $value !== null && $value !== '') {
                    $mapped[] = [
                        'name' => ucwords(str_replace(['_snipeit_', '_'], ['', ' '], $key)),
                        'value' => $this->valueToString($value),
                        'format' => '',
                    ];
                }
            }
        }

        return $mapped;
    }

    private function mapCustomFieldsForForm(mixed $fields, array $fullRecord = []): array
    {
        $mapped = [];

        // 1. Try from standard custom_fields key
        if (is_array($fields) && ! empty($fields)) {
            foreach ($fields as $key => $f) {
                if (is_array($f) && isset($f['value'])) {
                    $fieldKey = (string) ($f['db_column_name'] ?? $f['db_column'] ?? $key);
                    $mapped[$fieldKey] = $f['value'];
                }
            }
        }

        // 2. Fallback to root _snipeit_ fields (often how they are sent back/expected)
        if (empty($mapped) && ! empty($fullRecord)) {
            foreach ($fullRecord as $key => $value) {
                if (str_starts_with($key, '_snipeit_')) {
                    $mapped[$key] = $value;
                }
            }
        }

        return $mapped;
    }

    private function extractFormattedDate(mixed $value): string
    {
        if (is_array($value)) {
            return (string) ($value['formatted'] ?? $value['datetime'] ?? '');
        }

        return is_string($value) ? $value : '';
    }

    /**
     * Returns the [endpoint, query] pair suitable for requestPool() for the checkout tab of a given asset type.
     */
    private function checkoutEndpointForPool(string $type, int $assetId): array
    {
        return match ($type) {
            'accessories' => ["accessories/{$assetId}/checkedout", []],
            'component' => ["components/{$assetId}/assets", []],
            'license' => ["licenses/{$assetId}/seats", []],
            'consumable' => ["consumables/{$assetId}/users", ['limit' => 1500]],
            default => ['hardware/0', []], // hardware has no checkout list tab
        };
    }

    /**
     * Maps a raw pool response (for checkout data) into the normalised format expected by the Vue component.
     * Uses the same logic as fetchCheckoutRecords but works on already-fetched data.
     */
    private function buildCheckoutFromPool(string $type, int $assetId, array $rawResponse): array
    {
        try {
            switch ($type) {
                case 'accessories':
                    $rows = is_array($rawResponse['rows'] ?? null) ? $rawResponse['rows'] : $rawResponse;

                    return collect($rows)->map(function (array $r) {
                        $user = $r['assigned_to'] ?? $r;

                        return [
                            'id' => (int) ($r['assigned_pivot_id'] ?? $r['id'] ?? 0),
                            'name' => $this->valueToString(data_get($user, 'name', '')),
                            'secondary' => $this->valueToString(data_get($user, 'username', '')),
                            'email' => $this->valueToString(data_get($user, 'email', '')),
                            'company' => $this->valueToString(data_get($user, 'company.name', '')),
                            'location' => $this->valueToString(data_get($user, 'location.name', data_get($r, 'location.name', ''))),
                            'note' => $this->valueToString($r['note'] ?? ''),
                            'date' => $this->extractFormattedDate($r['created_at'] ?? ''),
                            'image' => $this->valueToString(data_get($user, 'image', data_get($user, 'avatar', ''))),
                        ];
                    })->sortByDesc('date')->values()->all();

                case 'component':
                    $rows = is_array($rawResponse['rows'] ?? null) ? $rawResponse['rows'] : $rawResponse;

                    return collect($rows)->map(function (array $r) use ($assetId) {
                        $targetAssetId = (int) ($r['id'] ?? 0);
                        $stbItem = \App\Models\StbItem::query()
                            ->where('snipeit_asset_id', $assetId)
                            ->where('computer_id', $targetAssetId)
                            ->with('stb')
                            ->latest('id')
                            ->first();
                        $note = $this->valueToString($r['note'] ?? $r['notes'] ?? '');
                        if ($note === '' && $stbItem?->stb) {
                            $stb = $stbItem->stb;
                            // Use standardized formatter
                            $note = AssetNoteFormatterService::formatAssignmentNote(
                                $stb,
                                itemName: $this->valueToString(data_get($r, 'name', '')),
                                serialNo: $this->valueToString(data_get($r, 'asset_tag', '')),
                                assignedTo: $stb->user_name ?? null,
                                catatan: trim((string) $stb->remark) !== '' ? trim((string) $stb->remark) : null,
                                reference: $stb->user_company ?? null
                            );
                        }

                        return [
                            'id' => (int) ($r['id'] ?? 0),
                            'name' => $this->valueToString(data_get($r, 'name', '')),
                            'secondary' => $this->valueToString(data_get($r, 'asset_tag', '')),
                            'email' => '',
                            'company' => $this->valueToString(data_get($r, 'company.name', '')),
                            'location' => $this->valueToString(data_get($r, 'location.name', '')),
                            'note' => $note,
                            'date' => $this->extractFormattedDate($stbItem?->stb?->deliver_date ?? $r['created_at'] ?? ''),
                            'image' => $this->valueToString(data_get($r, 'image', '')),
                        ];
                    })->sortByDesc('date')->values()->all();

                case 'license':
                    $rows = is_array($rawResponse['rows'] ?? null) ? $rawResponse['rows'] : $rawResponse;
                    if ($rows === []) {
                        $rows = $this->snipe->getLicenseSeats($assetId, true);
                    }

                    return collect($rows)
                        ->filter(fn (array $r) => ! empty($r['assigned_to']) || ! empty($r['assigned_user']))
                        ->map(function (array $r) use ($assetId) {
                            $user = $r['assigned_user'] ?? $r['assigned_to'] ?? [];
                            $stbItem = \App\Models\StbItem::query()
                                ->where('snipeit_asset_id', $assetId)
                                ->where('kategori', 'license')
                                ->with('stb')
                                ->latest('id')
                                ->first();
                            $note = $this->valueToString($r['notes'] ?? $r['note'] ?? '');
                            if ($note === '' && $stbItem?->stb) {
                                $stb = $stbItem->stb;
                                $docId = (string) $stb->id;
                                $note = "STB-{$docId}";
                                if (trim((string) $stb->remark) !== '') {
                                    $note .= ' | Catatan: '.trim((string) $stb->remark);
                                }
                            }

                            return [
                                'id' => (int) ($r['id'] ?? 0),
                                'name' => $this->valueToString(data_get($user, 'name', '')),
                                'secondary' => $this->valueToString(data_get($user, 'username', '')),
                                'email' => $this->valueToString(data_get($user, 'email', '')),
                                'company' => $this->valueToString(data_get($user, 'company.name', data_get($r, 'company.name', ''))),
                                'location' => $this->valueToString(data_get($user, 'location.name', data_get($r, 'location.name', ''))),
                                'note' => $note,
                                'date' => $this->extractFormattedDate($stbItem?->stb?->deliver_date ?? $r['updated_at'] ?? ''),
                                'image' => $this->valueToString(data_get($user, 'avatar', data_get($user, 'image', ''))),
                            ];
                        })->sortByDesc('date')->values()->all();

                case 'consumable':
                    $rows = is_array($rawResponse['rows'] ?? null) ? $rawResponse['rows'] : $rawResponse;
                    $uniqueUsers = [];
                    foreach ($rows as $r) {
                        $uid = (int) data_get($r, 'user.id', data_get($r, 'assigned_to.id', 0));
                        if ($uid <= 0) {
                            continue;
                        }

                        $qty = (int) ($r['qty'] ?? 1);
                        $date = $this->extractFormattedDate($r['created_at'] ?? '');

                        if (! isset($uniqueUsers[$uid])) {
                            $user = $r['user'] ?? $r['assigned_to'] ?? $r;
                            $uniqueUsers[$uid] = [
                                'id' => $uid,
                                'name' => $this->valueToString(data_get($user, 'name', '')),
                                'secondary' => $this->valueToString(data_get($user, 'username', '')),
                                'email' => $this->valueToString(data_get($user, 'email', '')),
                                'company' => $this->valueToString(data_get($user, 'company.name', '')),
                                'location' => $this->valueToString(data_get($user, 'location.name', '')),
                                'note' => $this->valueToString($r['note'] ?? ''),
                                'date' => $date,
                                'image' => $this->valueToString(data_get($user, 'avatar', data_get($user, 'image', ''))),
                                'qty' => $qty,
                            ];
                        } else {
                            $uniqueUsers[$uid]['qty'] += $qty;
                            // Update date to the latest one
                            if ($date > $uniqueUsers[$uid]['date']) {
                                $uniqueUsers[$uid]['date'] = $date;
                            }
                        }
                    }
                    // Sort by date descending (latest first)
                    usort($uniqueUsers, fn ($a, $b) => strcmp($b['date'], $a['date']));

                    return array_values($uniqueUsers);

                default:
                    return [];
            }
        } catch (\Throwable) {
            return [];
        }
    }

    private function fetchCheckoutRecords(string $type, int $assetId): array
    {
        try {
            switch ($type) {
                case 'accessories':
                    $rows = $this->snipe->fetchRows("accessories/{$assetId}/checkedout");

                    return collect($rows)->map(fn (array $r) => [
                        'id' => (int) ($r['assigned_pivot_id'] ?? $r['id'] ?? 0),
                        'name' => $this->valueToString($r['name'] ?? ''),
                        'secondary' => $this->valueToString($r['username'] ?? ''),
                        'note' => $this->valueToString($r['note'] ?? ''),
                        'date' => $this->extractFormattedDate($r['created_at'] ?? ''),
                        'image' => $this->valueToString($r['avatar'] ?? ''),
                    ])->values()->all();

                case 'component':
                    $rows = $this->snipe->fetchRows("components/{$assetId}/assets");

                    return collect($rows)->map(fn (array $r) => [
                        'id' => (int) ($r['id'] ?? 0),
                        'name' => $this->valueToString($r['name'] ?? ''),
                        'secondary' => $this->valueToString($r['asset_tag'] ?? ''),
                        'note' => $this->valueToString(data_get($r, 'location.name', '')),
                        'date' => '',
                        'image' => $this->valueToString($r['image'] ?? ''),
                    ])->values()->all();

                case 'license':
                    $rows = $this->snipe->fetchRows("licenses/{$assetId}/seats");

                    return collect($rows)
                        ->filter(fn (array $r) => ! empty($r['assigned_to']) || ! empty($r['assigned_user']))
                        ->map(fn (array $r) => [
                            'id' => (int) ($r['id'] ?? 0),
                            'name' => $this->valueToString(data_get($r, 'assigned_user.name', data_get($r, 'assigned_to.name', ''))),
                            'secondary' => $this->valueToString(data_get($r, 'assigned_user.username', data_get($r, 'assigned_to.username', ''))),
                            'note' => $this->valueToString($r['notes'] ?? $r['note'] ?? ''),
                            'date' => $this->extractFormattedDate($r['updated_at'] ?? ''),
                            'image' => $this->valueToString(data_get($r, 'assigned_user.avatar', data_get($r, 'assigned_to.avatar', ''))),
                        ])->values()->all();

                case 'consumable':
                    $rows = $this->snipe->fetchRows("consumables/{$assetId}/users", [], 200);

                    return collect($rows)->map(fn (array $r) => [
                        'id' => (int) data_get($r, 'user.id', 0),
                        'name' => $this->valueToString(data_get($r, 'user.name', '')),
                        'secondary' => $this->valueToString(data_get($r, 'created_by.name', '')),
                        'note' => $this->valueToString($r['note'] ?? ''),
                        'date' => $this->extractFormattedDate($r['created_at'] ?? ''),
                        'image' => $this->valueToString($r['avatar'] ?? ''),
                    ])->values()->all();

                default:
                    return [];
            }
        } catch (\Throwable) {
            return [];
        }
    }

    private function fileProxyUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $configuredHost = parse_url((string) config('services.snipeit.url'), PHP_URL_HOST);
        if (parse_url($url, PHP_URL_HOST) !== $configuredHost) {
            return $url;
        }

        return route('asset.file.proxy', ['url' => $url]);
    }

    private function resolveFileDocInfo(array $f): array
    {
        $text = ($f['name'] ?? $f['filename'] ?? '').' '.($f['note'] ?? '');

        // Check IR (Inspection Report)
        if (preg_match('/IR-[A-Z0-9-]+/i', $text, $matches)) {
            $reportId = strtoupper($matches[0]);
            $inspection = \App\Models\Inspection::where('report_id', $reportId)->first();

            return [
                'doc_no' => $reportId,
                'doc_url' => $inspection ? route('inspection.show', $inspection->id) : null,
                'form_type' => 'inspection',
                'form_name' => 'Inspection',
            ];
        }

        // Check STB
        if (preg_match('/STB-[A-Z0-9-]+/i', $text, $matches) || preg_match('/Doc ID:\s*([A-Z0-9-]+)/i', $text, $matches)) {
            $docId = strtoupper(trim(preg_replace('/^Doc ID:\s*/i', '', $matches[0])));
            $stb = \App\Models\Stb::where('batch_no', $docId)->orWhere('id', preg_replace('/\D/', '', $docId))->first();
            if (! $stb) {
                $stb = \App\Models\Stb::where('batch_no', 'like', "%{$docId}%")->first();
            }

            return [
                'doc_no' => $docId,
                'doc_url' => $stb ? route('stb.show', $stb->id) : null,
                'form_type' => 'stb',
                'form_name' => 'Dokumen STB',
            ];
        }

        // Check PEM (Peminjaman)
        if (preg_match('/PEM-[A-Z0-9-]+/i', $text, $matches)) {
            $pemId = strtoupper($matches[0]);
            $pem = \App\Models\Peminjaman::where('doc_no', $pemId)->orWhere('batch_no', $pemId)->first();

            return [
                'doc_no' => $pemId,
                'doc_url' => $pem ? route('peminjaman.show', $pem->id) : null,
                'form_type' => 'peminjaman',
                'form_name' => 'Peminjaman',
            ];
        }

        return [
            'doc_no' => null,
            'doc_url' => null,
            'form_type' => 'file',
            'form_name' => 'Dokumen Terlampir',
        ];
    }

    private function resolveFormTypeKey(string $itemType): string
    {
        $base = class_basename($itemType);

        return match ($base) {
            'Stb' => 'stb',
            'Peminjaman' => 'peminjaman',
            'Inspection' => 'inspection',
            'Ticket' => 'ticket',
            'AuditSession', 'AuditItem' => 'audit',
            default => 'asset',
        };
    }

    private function resolveFormName($log): string
    {
        $type = $this->resolveFormTypeKey((string) $log->item_type);

        return match ($type) {
            'stb' => 'Dokumen STB',
            'peminjaman' => 'Peminjaman',
            'inspection' => 'Inspection',
            'ticket' => 'Workspace Ticket',
            'audit' => 'Stock Opname',
            default => 'Aktivitas Asset',
        };
    }

    private function resolveDocNumber($log): ?string
    {
        $meta = $log->log_meta ?? [];

        if (! empty($meta['doc_no'])) {
            return $meta['doc_no'];
        }

        if (! empty($meta['report_id'])) {
            return $meta['report_id'];
        }

        if ($log->item) {
            $item = $log->item;
            if ($item instanceof \App\Models\Inspection && ! empty($item->report_id)) {
                return $item->report_id;
            }
            if ($item instanceof \App\Models\Stb && ! empty($item->batch_no)) {
                return $item->batch_no;
            }
            if ($item instanceof \App\Models\Ticket) {
                return "Tiket #{$item->id}";
            }
        }

        return null;
    }

    private function resolveDocUrl($log): ?string
    {
        if (! $log->item_id) {
            return null;
        }

        $type = $this->resolveFormTypeKey((string) $log->item_type);

        return match ($type) {
            'stb' => "/stb/{$log->item_id}",
            'peminjaman' => "/peminjaman/{$log->item_id}",
            'inspection' => "/inspection/{$log->item_id}",
            'ticket' => "/helpdesk/{$log->item_id}",
            'audit' => "/audit/{$log->item_id}",
            default => null,
        };
    }

    private function resolvePdfUrl($log): ?string
    {
        $meta = $log->log_meta ?? [];

        if (! empty($meta['pdf_path'])) {
            return '/storage/'.ltrim($meta['pdf_path'], '/');
        }

        if ($log->item) {
            $item = $log->item;
            if (! empty($item->completed_pdf_path)) {
                return '/storage/'.ltrim($item->completed_pdf_path, '/');
            }
        }

        return null;
    }

    private function resolveActionLabel(string $action): string
    {
        return match (strtolower($action)) {
            'created', 'create' => 'Dibuat',
            'updated', 'update' => 'Diperbarui',
            'sign' => 'Tanda Tangan',
            'sign_cleared' => 'Hapus TTD',
            'completed', 'stb_complete' => 'Diselesaikan',
            'cancelled' => 'Dibatalkan',
            'checkout' => 'Checkout',
            'checkin' => 'Checkin',
            'upload' => 'Upload File',
            'add_stock' => 'Tambah Stok',
            'print' => 'Cetak',
            'sync_failed' => 'Gagal Sinkronisasi',
            default => ucfirst(str_replace('_', ' ', $action)),
        };
    }

    private function fetchActivityHistory(string $type, int $assetId, array $snipeHistory = [], array $assetFiles = []): array
    {
        try {
            $resolveActivityFile = function (array $history) use ($assetFiles): ?array {
                $historyText = implode(' ', array_filter([
                    $history['filename'] ?? null,
                    $history['file_name'] ?? null,
                    $history['note'] ?? null,
                ]));
                $reference = preg_match('/(?:IR-[A-Z0-9-]+|STB-[A-Z0-9-]+|PEM-[A-Z0-9-]+|Doc ID:\s*[A-Z0-9-]+)/i', $historyText, $matches)
                    ? strtoupper(trim(preg_replace('/^Doc ID:\s*/i', '', $matches[0])))
                    : null;

                foreach ($assetFiles as $file) {
                    $name = (string) ($file['name'] ?? $file['filename'] ?? '');
                    $note = (string) ($file['note'] ?? '');
                    if ($reference !== null && str_contains(strtoupper($name.' '.$note), $reference)) {
                        return [
                            'name' => $name,
                            'url' => $this->fileProxyUrl($file['url'] ?? null),
                        ];
                    }
                }

                return null;
            };
            $localLogs = \App\Models\ActionLog::where('snipeit_id', $assetId)
                ->where('snipeit_type', $type)
                ->with('user')
                ->latest()
                ->get();

            // Fetch STB Items related to this asset
            $stbItems = \App\Models\StbItem::where('snipeit_asset_id', $assetId)
                ->where('kategori', $type)
                ->with('stb')
                ->get();

            // Fetch Tickets related to this asset
            $tickets = \App\Models\Ticket::where('snipeit_asset_id', $assetId)
                ->get();

            $resolveSnipeActor = function (array $history) use ($localLogs): string {
                $note = (string) ($history['note'] ?? '');
                if (preg_match('/Doc ID:\s*([A-Z0-9-]+)/i', $note, $matches)) {
                    $docId = $matches[1];
                    $localLog = $localLogs->first(
                        fn ($log) => $log->action_type === 'stb_complete'
                            && str_contains((string) $log->note, $docId),
                    );

                    if ($localLog?->user?->name) {
                        return $localLog->user->name;
                    }
                    if ($localLog) {
                        return (string) data_get($localLog->log_meta, 'user', 'System / Snipe-IT');
                    }
                }

                if (preg_match('/IR-[A-Z0-9-]+/i', $note, $matches)) {
                    $reportId = strtoupper($matches[0]);
                    $localLog = $localLogs->first(
                        fn ($log) => strtoupper((string) data_get($log->log_meta, 'report_id')) === $reportId,
                    );

                    if ($localLog?->user?->name) {
                        return $localLog->user->name;
                    }
                    if ($localLog) {
                        return (string) data_get($localLog->log_meta, 'user', 'System / Snipe-IT');
                    }
                }

                $actor = (string) ($history['admin']['name'] ?? ($history['created_by']['name'] ?? '-'));

                // Snipe-IT records API mutations under its technical admin account.
                // Avoid exposing that implementation detail as the business actor.
                return strtolower(trim($actor)) === 'admin' ? 'System / Snipe-IT' : $actor;
            };

            $resolveSnipeNote = function (array $history) use ($localLogs): string {
                $note = (string) ($history['note'] ?? '');
                if ($note !== '') {
                    return $note;
                }

                if (strtolower((string) ($history['action_type'] ?? '')) === 'checkout') {
                    return (string) ($localLogs
                        ->first(fn ($log) => $log->action_type === 'stb_complete')
                        ?->note ?? '-');
                }

                return '-';
            };

            $resolveLocalFormUrl = function ($log): ?string {
                $type = strtolower(class_basename((string) $log->item_type));

                return match (true) {
                    str_contains($type, 'inspection') => route('inspection.show', $log->item_id),
                    str_contains($type, 'peminjaman') => route('peminjaman.show', $log->item_id),
                    str_contains($type, 'stb') => route('stb.show', $log->item_id),
                    str_contains($type, 'ticket') => route('helpdesk.show', $log->item_id),
                    default => null,
                };
            };
            $resolveActivityFormUrl = function (array $history) use ($localLogs, $resolveLocalFormUrl): ?string {
                $text = implode(' ', array_filter([
                    $history['filename'] ?? null,
                    $history['file_name'] ?? null,
                    $history['note'] ?? null,
                ]));
                if (! preg_match('/(?:IR-[A-Z0-9-]+|STB-[A-Z0-9-]+|PEM-[A-Z0-9-]+|Doc ID:\s*[A-Z0-9-]+)/i', $text, $matches)) {
                    return null;
                }

                $reference = strtoupper(trim(preg_replace('/^Doc ID:\s*/i', '', $matches[0])));
                $log = $localLogs->first(fn ($candidate) => strtoupper((string) data_get($candidate->log_meta, 'report_id')) === $reference
                    || str_contains(strtoupper((string) $candidate->note), $reference)
                );

                return $log ? $resolveLocalFormUrl($log) : null;
            };

            $snipeEvents = collect($snipeHistory)->map(function ($h) use ($resolveSnipeActor, $resolveSnipeNote, $resolveActivityFile, $resolveActivityFormUrl) {
                $matchedFile = $resolveActivityFile($h);
                $fileName = $h['filename'] ?? $h['file_name'] ?? $matchedFile['name'] ?? null;
                $historyText = implode(' ', array_filter([$fileName, $h['note'] ?? null]));
                $docNo = null;
                $formType = 'asset';
                $formName = 'Snipe-IT Audit';
                if (preg_match('/(?:IR-[A-Z0-9-]+|STB-[A-Z0-9-]+|PEM-[A-Z0-9-]+|Doc ID:\s*[A-Z0-9-]+)/i', $historyText, $matches)) {
                    $docNo = strtoupper(trim(preg_replace('/^Doc ID:\s*/i', '', $matches[0])));
                    if (str_starts_with($docNo, 'IR-')) {
                        $formType = 'inspection';
                        $formName = 'Inspection';
                    } elseif (str_starts_with($docNo, 'STB-') || str_starts_with($docNo, 'ZGI-')) {
                        $formType = 'stb';
                        $formName = 'Dokumen STB';
                    } elseif (str_starts_with($docNo, 'PEM-')) {
                        $formType = 'peminjaman';
                        $formName = 'Peminjaman';
                    }
                }

                $docUrl = $resolveActivityFormUrl($h);

                return [
                    'id' => 'snipe_'.$h['id'],
                    'action_type' => strtoupper($h['action_type'] ?? '-'),
                    'action_label' => $this->resolveActionLabel($h['action_type'] ?? ''),
                    'user' => $resolveSnipeActor($h),
                    'user_id' => null,
                    'user_image' => null,
                    'target' => $h['target']['name'] ?? '-',
                    'target_type' => $h['target_type'] ?? null,
                    'note' => $resolveSnipeNote($h),
                    'date' => ! empty($h['created_at']['datetime'])
                        ? \Carbon\Carbon::parse($h['created_at']['datetime'])->format('Y-m-d H:i:s')
                        : '-',
                    'source' => 'snipeit',
                    'file_status' => null,
                    'file_name' => $fileName,
                    'file_url' => $docUrl ?? ($matchedFile['url'] ?? null),
                    'doc_no' => $docNo,
                    'doc_url' => $docUrl,
                    'pdf_url' => $matchedFile['url'] ?? null,
                    'form_type' => $formType,
                    'form_name' => $formName,
                    'quantity' => $h['quantity'] ?? $h['qty'] ?? null,
                    'changed' => $this->formatActivityChanges($h['changes'] ?? $h['changed'] ?? null),
                    'log_meta' => null,
                ];
            });

            $localEvents = $localLogs->map(function ($log) use ($resolveLocalFormUrl) {
                $syncLog = (string) data_get($log->log_meta, 'sync_log', '');
                $note = (string) $log->note;
                $changed = implode(' || ', (array) data_get($log->log_meta, 'changed_fields', []));
                if ($log->action_type === 'update' && preg_match('/^Field diubah:\s*(.+)$/i', $note, $matches)) {
                    $changed = $changed !== '' ? $changed : $matches[1];
                    $note = '';
                }

                $formTypeKey = $this->resolveFormTypeKey((string) $log->item_type);
                $formName = $this->resolveFormName($log);
                $docNo = $this->resolveDocNumber($log);
                $docUrl = $resolveLocalFormUrl($log) ?? $this->resolveDocUrl($log);
                $pdfUrl = $this->resolvePdfUrl($log);

                return [
                    'id' => 'local_'.$log->id,
                    'action_type' => $log->action_type === 'completed'
                        ? 'INSPECTION COMPLETED'
                        : strtoupper($log->action_type),
                    'action_label' => $this->resolveActionLabel($log->action_type),
                    'user' => $log->user?->name ?? 'System',
                    'user_id' => $log->user?->id,
                    'user_image' => null,
                    'target' => $this->resolveItemName($log, 'item') ?: ($log->target?->name ?? '-'),
                    'target_type' => $log->item_type,
                    'note' => $note,
                    'date' => \Carbon\Carbon::parse($log->created_at, 'UTC')
                        ->timezone(config('app.timezone'))
                        ->format('Y-m-d H:i:s'),
                    'source' => 'app',
                    'file_status' => $log->action_type === 'upload'
                        ? 'success'
                        : (str_contains(strtolower($syncLog), 'pdf upload failed') ? 'failed' : null),
                    'file_name' => $log->action_type === 'upload'
                        ? preg_replace('/^Uploaded document:\s*/i', '', (string) $log->note)
                        : data_get($log->log_meta, 'file_name'),
                    'file_url' => $docUrl ?? data_get($log->log_meta, 'file_url'),
                    'doc_no' => $docNo,
                    'doc_url' => $docUrl,
                    'pdf_url' => $pdfUrl,
                    'form_type' => $formTypeKey,
                    'form_name' => $formName,
                    'role' => data_get($log->log_meta, 'role'),
                    'quantity' => data_get($log->log_meta, 'quantity', data_get($log->log_meta, 'qty')),
                    'changed' => $changed !== '' ? $changed : null,
                    'report_id' => data_get($log->log_meta, 'report_id'),
                    'log_meta' => $log->log_meta,
                ];
            });

            // Fold Snipe-IT upload events into the related inspection event.
            $uploadEvents = $snipeEvents->filter(fn (array $event) => str_contains(strtolower($event['action_type']), 'upload')
            );
            $mergedUploadIds = collect();
            foreach ($uploadEvents as $uploadKey => $upload) {
                $uploadNote = (string) $upload['note'];
                if (! preg_match('/(?:IR-[A-Z0-9-]+|STB-[A-Z0-9-]+|PEM-[A-Z0-9-]+|Doc ID:\s*[A-Z0-9-]+)/i', $uploadNote, $matches)) {
                    $snipeEvents->put($uploadKey, array_merge($upload, [
                        'file_status' => 'success',
                    ]));

                    continue;
                }

                $documentId = strtoupper(trim(preg_replace('/^Doc ID:\s*/i', '', $matches[0])));
                $localIndex = $localEvents->search(fn (array $event) => $event['report_id'] === $documentId
                );
                if ($localIndex !== false) {
                    $localEvents->put($localIndex, array_merge($localEvents->get($localIndex), [
                        'file_status' => 'success',
                        'file_name' => $upload['note'],
                        'file_url' => $upload['file_url'],
                    ]));
                    $mergedUploadIds->push($upload['id']);

                    continue;
                }

                $snipeIndex = $snipeEvents->search(fn (array $event) => str_contains(strtoupper((string) $event['note']), $documentId)
                );
                if ($snipeIndex !== false) {
                    $snipeEvents->put($snipeIndex, array_merge($snipeEvents->get($snipeIndex), [
                        'file_status' => 'success',
                        'file_name' => $upload['note'],
                        'file_url' => $upload['file_url'],
                    ]));
                    $mergedUploadIds->push($upload['id']);
                }
            }

            $snipeEvents = $snipeEvents->reject(fn (array $event) => $mergedUploadIds->contains($event['id'])
            );

            // Hide technical Snipe-IT audit noise. Business events are already
            // represented by the local inspection, STB, loan, or ticket logs.
            $snipeEvents = $snipeEvents->reject(fn (array $event) => $event['user'] === 'System / Snipe-IT'
            );

            // Snipe-IT and local audit logging both record create/update events.
            // Keep the local event, which contains the application actor and note.
            $duplicateSnipeIds = $snipeEvents->filter(function (array $snipe) use ($localEvents) {
                $snipeAction = strtolower($snipe['action_type']);
                if (! str_contains($snipeAction, 'create') && ! str_contains($snipeAction, 'update')) {
                    return false;
                }

                $snipeDate = \Carbon\Carbon::parse($snipe['date']);

                return $localEvents->contains(function (array $local) use ($snipe, $snipeDate) {
                    $localAction = strtolower($local['action_type']);
                    if (! in_array($localAction, ['create', 'created', 'update', 'updated'], true)) {
                        return false;
                    }

                    $localDate = \Carbon\Carbon::parse($local['date']);
                    $sameTarget = $local['target'] === '-' || $snipe['target'] === '-'
                        || str_contains(strtolower((string) $local['target']), strtolower((string) $snipe['target']))
                        || str_contains(strtolower((string) $local['note']), strtolower((string) $snipe['target']));

                    return $sameTarget && abs($localDate->diffInSeconds($snipeDate, false)) <= 120;
                });
            })->pluck('id');

            $history = $localEvents
                ->concat($snipeEvents->reject(fn (array $snipe) => $duplicateSnipeIds->contains($snipe['id'])))
                ->concat($stbItems->map(fn ($item) => [
                    'id' => 'stb_'.$item->id,
                    'action_type' => strtoupper($item->stb->document_type === 'handover' ? 'MUTASI (SERAH TERIMA)' : 'MUTASI (PENGEMBALIAN)'),
                    'action_label' => $item->stb->document_type === 'handover' ? 'Serah Terima' : 'Pengembalian',
                    'user' => $item->stb->user_name ?? 'System',
                    'user_id' => $item->stb->user_id,
                    'user_image' => null,
                    'target' => $item->stb->department.' ('.$item->stb->location_name.')',
                    'target_type' => 'stb',
                    'note' => $item->stb->remark,
                    'date' => \Carbon\Carbon::parse($item->updated_at)->format('Y-m-d H:i:s'),
                    'source' => 'stb',
                    'file_status' => null,
                    'doc_no' => $item->stb->batch_no ?? "STB #{$item->stb->id}",
                    'doc_url' => route($item->stb->document_type === 'handover' ? 'stb.show' : 'peminjaman.show', $item->stb->id),
                    'pdf_url' => $item->stb->completed_pdf_path ? '/storage/'.ltrim($item->stb->completed_pdf_path, '/') : null,
                    'form_type' => $item->stb->document_type === 'handover' ? 'stb' : 'peminjaman',
                    'form_name' => $item->stb->document_type === 'handover' ? 'Dokumen STB' : 'Peminjaman',
                    'href' => route($item->stb->document_type === 'handover' ? 'stb.show' : 'peminjaman.show', $item->stb->id),
                    'log_meta' => null,
                ]))->concat($tickets->map(fn ($ticket) => [
                    'id' => 'ticket_'.$ticket->id,
                    'action_type' => strtoupper('HELPDESK: '.$ticket->maintenance_type),
                    'action_label' => 'Tiket Helpdesk',
                    'user' => $ticket->technician ?? 'IT Support',
                    'user_id' => null,
                    'user_image' => null,
                    'target' => $ticket->requester.' ('.$ticket->status.')',
                    'target_type' => 'ticket',
                    'note' => $ticket->issue_description,
                    'date' => $ticket->created_at->format('Y-m-d H:i:s'),
                    'source' => 'helpdesk',
                    'file_status' => null,
                    'doc_no' => "Tiket #{$ticket->id}",
                    'doc_url' => route('helpdesk.show', $ticket->id),
                    'form_type' => 'ticket',
                    'form_name' => 'Workspace Ticket',
                    'href' => route('helpdesk.show', $ticket->id),
                    'log_meta' => null,
                ]))->sortByDesc('date')->values()->all();

            return $history;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('History fetch error: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Log an action to local database for history tracking.
     */
    private function logAction(string $action, $itemId, string $itemType, ?string $note = null, array $meta = []): void
    {
        try {
            // Try to include the item name in meta for better display in logs
            if (empty($meta['item_name'])) {
                $record = $this->fetchAssetRecordByType($itemType, (int) $itemId);
                if ($record) {
                    $meta['item_name'] = (string) ($record['name'] ?? $record['asset_tag'] ?? '');
                }
            }

            \App\Models\ActionLog::create([
                'user_id' => auth()->id(),
                'action_type' => $action,
                'item_type' => 'snipeit_'.$itemType,
                'item_id' => $itemId,
                'snipeit_id' => $itemId,
                'snipeit_type' => $itemType,
                'note' => $note,
                'log_meta' => $meta,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to log action locally: '.$e->getMessage());
        }
    }

    private function formatActivityChanges(mixed $changes): ?string
    {
        if (is_string($changes)) {
            return $changes !== '' ? $changes : null;
        }

        if (! is_array($changes)) {
            return null;
        }

        $formatted = [];
        foreach ($changes as $field => $change) {
            if (is_array($change)) {
                $old = $change['old'] ?? $change['before'] ?? null;
                $new = $change['new'] ?? $change['after'] ?? null;
                $formatted[] = $old !== null || $new !== null
                    ? sprintf('%s: %s -> %s', $field, $old ?? '-', $new ?? '-')
                    : (string) $field;
            } else {
                $formatted[] = is_string($field) && ! is_int($field)
                    ? $field.': '.$change
                    : (string) $change;
            }
        }

        return $formatted !== [] ? implode(' | ', $formatted) : null;
    }

    private function detectChangedAssetFields(string $type, array $currentRecord, array $validated, array $payload, Request $request): array
    {
        $current = $this->mapAssetRecordToFormData($type, $currentRecord, []);
        $labels = [
            'name' => 'Name',
            'asset_tag' => 'Asset Tag',
            'serial' => 'Serial',
            'product_key' => 'Serial',
            'model_id' => 'Model',
            'status_id' => 'Status',
            'category_id' => 'Category',
            'company_id' => 'Company',
            'location_id' => 'Location',
            'notes' => 'Notes',
            'requestable' => 'Requestable',
            'warranty_months' => 'Warranty',
            'expected_checkin' => 'Expected Check-in',
            'next_audit_date' => 'Next Audit Date',
            'byod' => 'BYOD',
            'order_number' => 'Order Number',
            'purchase_date' => 'Purchase Date',
            'asset_eol_date' => 'Asset EOL Date',
            'supplier_id' => 'Supplier',
            'purchase_cost' => 'Purchase Cost',
            'seats' => 'Seats',
            'qty' => 'Quantity',
            'manufacturer_id' => 'Manufacturer',
            'model_number' => 'Model Number',
            'item_no' => 'Item Number',
            'min_qty' => 'Minimum Quantity',
            'expiration_date' => 'Expiration Date',
            'termination_date' => 'Termination Date',
            'license_name' => 'License Name',
            'license_email' => 'License Email',
            'reassignable' => 'Reassignable',
            'maintained' => 'Maintained',
        ];
        $changed = [];
        $fieldSources = [
            'product_key' => 'serial',
        ];

        foreach ($labels as $field => $label) {
            if (! array_key_exists($field, $payload)) {
                continue;
            }

            $sourceField = $fieldSources[$field] ?? $field;
            $before = $this->normalizeComparableValue($current[$sourceField] ?? null);
            $after = $this->normalizeComparableValue($validated[$sourceField] ?? $payload[$field] ?? null);
            if ($before !== $after) {
                $changed[] = sprintf('%s: %s -> %s', $label, $before !== '' ? $before : '-', $after !== '' ? $after : '-');
            }
        }

        if ($request->hasFile('image') || $request->hasFile('stock_document')) {
            $changed[] = 'File: - -> uploaded';
        }
        $customFieldLabels = [];
        $modelId = (int) data_get($currentRecord, 'model.id', 0);
        if ($modelId > 0) {
            $modelDetail = $this->fetchFullModelOption($modelId);
            foreach ((array) data_get($modelDetail, 'default_fields', []) as $field) {
                $fieldKey = strtolower((string) ($field['db_column_name'] ?? ''));
                $fieldName = trim((string) ($field['name'] ?? ''));
                if ($fieldKey !== '' && $fieldName !== '') {
                    $customFieldLabels[$fieldKey] = $fieldName;
                    $customFieldLabels[strtolower($fieldName)] = $fieldName;
                }
            }
        }
        foreach ((array) ($currentRecord['custom_fields'] ?? []) as $key => $field) {
            if (! is_array($field)) {
                continue;
            }

            $fieldKey = (string) ($field['db_column_name'] ?? $field['db_column'] ?? $key);
            $fieldName = (string) ($field['name'] ?? $field['label'] ?? $key);
            $customFieldLabels[strtolower($fieldKey)] = $fieldName;
            $customFieldLabels[strtolower($fieldName)] = $fieldName;
        }

        $canonicalizeCustomFields = function (mixed $fields) use ($customFieldLabels): array {
            $canonical = [];
            foreach ((array) $fields as $key => $value) {
                $normalizedKey = strtolower((string) $key);
                $label = $customFieldLabels[$normalizedKey] ?? (string) $key;
                $canonical[strtolower($label)] = $value;
            }

            return $canonical;
        };
        $currentCustomFields = $canonicalizeCustomFields($current['custom_fields'] ?? []);
        $updatedCustomFields = $canonicalizeCustomFields($validated['custom_fields'] ?? []);

        foreach (array_unique(array_merge(array_keys($currentCustomFields), array_keys($updatedCustomFields))) as $key) {
            $before = $this->normalizeComparableValue($currentCustomFields[$key] ?? null);
            $after = $this->normalizeComparableValue($updatedCustomFields[$key] ?? null);
            if ($before !== $after) {
                $label = $customFieldLabels[$key]
                    ?? ucwords(str_replace(['_snipeit_', '_'], ['', ' '], (string) $key));
                $changed[] = sprintf('%s: %s -> %s', $label, $before !== '' ? $before : '-', $after !== '' ? $after : '-');
            }
        }

        return array_values(array_unique($changed));
    }

    private function normalizeComparableValue(mixed $value): string
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return trim((string) ($value ?? ''));
    }

    private function fetchFullModelOption(int $modelId): ?array
    {
        $model = $this->snipe->request('models/'.$modelId);
        if (! is_array($model) || empty($model['id'])) {
            return null;
        }

        $fieldsetId = (int) data_get($model, 'fieldset.id', 0);
        if ($fieldsetId > 0) {
            $fsData = $this->snipe->request('fieldsets/'.$fieldsetId);
            $fsRows = $fsData['fields']['rows'] ?? $fsData['rows'] ?? [];
            if (! empty($fsRows)) {
                $model['default_fieldset_values'] = $fsRows;
            }
        }

        // Map definitions to be consistent with SnipeItController
        $rawFields = $model['default_fieldset_values'] ?? [];
        $mappedFields = collect($rawFields)
            ->filter(fn ($field) => is_array($field) && (! empty($field['db_column_name']) || ! empty($field['db_column'])))
            ->map(fn (array $field) => [
                'name' => (string) ($field['name'] ?? '-'),
                'db_column_name' => (string) ($field['db_column_name'] ?? $field['db_column'] ?? ''),
                'default_value' => $field['default_value'] ?? $field['value'] ?? null,
                'format' => (string) ($field['format'] ?? $field['field_format'] ?? 'ANY'),
                'type' => (string) ($field['type'] ?? $field['element'] ?? 'text'),
                'field_values' => (string) ($field['field_values'] ?? ''),
                'required' => (bool) ($field['required'] ?? false),
            ])
            ->values()
            ->all();

        return [
            'id' => (int) $model['id'],
            'name' => (string) $model['name'],
            'image' => (string) ($model['image'] ?? ''),
            'fieldset_name' => (string) data_get($model, 'fieldset.name', ''),
            'has_details' => true,
            'default_fields' => $mappedFields,
        ];
    }

    private function buildCreateMetadata(): array
    {
        $pool = $this->snipe->requestPool([
            'users_p1' => ['users',        ['limit' => 500, 'offset' => 0]],
            'users_p2' => ['users',        ['limit' => 500, 'offset' => 500]],
            'models_p1' => ['models',       ['limit' => 500, 'offset' => 0]],
            'models_p2' => ['models',       ['limit' => 500, 'offset' => 500]],
            'models_p3' => ['models',       ['limit' => 500, 'offset' => 1000]],
            'locations' => ['locations',    ['limit' => 500]],
            'companies' => ['companies',    ['limit' => 500]],
            'manufacturers' => ['manufacturers', ['limit' => 500]],
            'suppliers' => ['suppliers',    ['limit' => 500]],
            'categories_all' => ['categories',   ['limit' => 500]],
            'statuslabels' => ['statuslabels', ['limit' => 500]],
            'fieldsets' => ['fieldsets',    ['limit' => 500]],
        ]);

        $users = array_merge($pool['users_p1']['rows'] ?? [], $pool['users_p2']['rows'] ?? []);
        $models = array_merge(
            $pool['models_p1']['rows'] ?? [],
            $pool['models_p2']['rows'] ?? [],
            $pool['models_p3']['rows'] ?? []
        );

        $locations = $pool['locations']['rows'] ?? [];
        $companies = $pool['companies']['rows'] ?? [];
        $manufacturers = $pool['manufacturers']['rows'] ?? [];
        $suppliers = $pool['suppliers']['rows'] ?? [];
        $categories = collect($pool['categories_all']['rows'] ?? []);

        $assetMetadata = [
            'categories' => $categories->filter(fn ($c) => ($c['category_type'] ?? '') === 'asset')->values()->all(),
            'companies' => $companies,
            'locations' => $locations,
            'statuses' => $pool['statuslabels']['rows'] ?? [],
            'manufacturers' => $manufacturers,
            'suppliers' => $suppliers,
            'models' => $models,
            'fieldsets' => $pool['fieldsets']['rows'] ?? [],
        ];

        return [
            'users' => $users,
            'assets' => $assetMetadata,
            'license' => [
                'categories' => $categories->filter(fn ($c) => ($c['category_type'] ?? '') === 'license')->values()->all(),
                'manufacturers' => $manufacturers,
                'companies' => $companies,
                'locations' => $locations,
                'suppliers' => $suppliers,
            ],
            'accessories' => [
                'categories' => $categories->filter(fn ($c) => ($c['category_type'] ?? '') === 'accessory')->values()->all(),
                'manufacturers' => $manufacturers,
                'companies' => $companies,
                'locations' => $locations,
                'suppliers' => $suppliers,
            ],
            'consumable' => [
                'categories' => $categories->filter(fn ($c) => ($c['category_type'] ?? '') === 'consumable')->values()->all(),
                'manufacturers' => $manufacturers,
                'companies' => $companies,
                'locations' => $locations,
                'suppliers' => $suppliers,
            ],
            'component' => [
                'categories' => $categories->filter(fn ($c) => ($c['category_type'] ?? '') === 'component')->values()->all(),
                'manufacturers' => $manufacturers,
                'companies' => $companies,
                'locations' => $locations,
                'suppliers' => $suppliers,
            ],
        ];
    }

    /**
     * Maps internal type to Snipe-IT reports target_type.
     */
    private function reportsTargetType(string $type): string
    {
        return match ($type) {
            'assets' => 'asset',
            'license' => 'license',
            'accessories' => 'accessory',
            'consumable' => 'consumable',
            'component' => 'component',
            default => 'asset',
        };
    }

    private function buildHardwareCreatePayload(array $validated, Request $request): array
    {
        $payload = [
            'name' => trim((string) ($validated['name'] ?? '')),
            'asset_tag' => trim((string) $validated['asset_tag']),
            'serial' => trim((string) ($validated['serial'] ?? '')),
            'model_id' => (int) $validated['model_id'],
            'status_id' => ! empty($validated['status_id']) ? (int) $validated['status_id'] : null,
            'company_id' => ! empty($validated['company_id']) ? (int) $validated['company_id'] : null,
            'location_id' => ! empty($validated['location_id']) ? (int) $validated['location_id'] : null,
            'notes' => trim((string) ($validated['notes'] ?? '')),
            'requestable' => ! empty($validated['requestable']) ? 1 : 0,
            'warranty_months' => isset($validated['warranty_months']) ? (int) $validated['warranty_months'] : null,
            'expected_checkin' => ! empty($validated['expected_checkin']) ? (string) $validated['expected_checkin'] : null,
            'next_audit_date' => ! empty($validated['next_audit_date']) ? (string) $validated['next_audit_date'] : null,
            'byod' => ! empty($validated['byod']) ? 1 : 0,
            'order_number' => trim((string) ($validated['order_number'] ?? '')),
            'purchase_date' => ! empty($validated['purchase_date']) ? (string) $validated['purchase_date'] : null,
            'asset_eol_date' => ! empty($validated['asset_eol_date']) ? (string) $validated['asset_eol_date'] : null,
            'supplier_id' => ! empty($validated['supplier_id']) ? (int) $validated['supplier_id'] : null,
            'purchase_cost' => ! empty($validated['purchase_cost']) ? (float) $validated['purchase_cost'] : null,
        ];

        if (! empty($validated['custom_fields']) && is_array($validated['custom_fields'])) {
            foreach ($validated['custom_fields'] as $key => $value) {
                if ($value !== null && $value !== '') {
                    $payload[$key] = $value;
                }
            }
        }

        if ($request->hasFile('image')) {
            $imageFile = $request->file('image');
            $payload['image'] = 'data:'.$imageFile->getMimeType().';base64,'.base64_encode((string) file_get_contents($imageFile->getRealPath()));
        }

        return array_filter($payload, fn ($v) => $v !== null && $v !== '');
    }

    private function buildLicenseCreatePayload(array $validated): array
    {
        return array_filter([
            'name' => trim((string) $validated['name']),
            'seats' => (int) $validated['seats'],
            'category_id' => (int) $validated['category_id'],
            'company_id' => ! empty($validated['company_id']) ? (int) $validated['company_id'] : null,
            'manufacturer_id' => ! empty($validated['manufacturer_id']) ? (int) $validated['manufacturer_id'] : null,
            'supplier_id' => ! empty($validated['supplier_id']) ? (int) $validated['supplier_id'] : null,
            'product_key' => trim((string) ($validated['serial'] ?? '')),
            'license_name' => trim((string) ($validated['license_name'] ?? '')),
            'license_email' => trim((string) ($validated['license_email'] ?? '')),
            'reassignable' => ! empty($validated['reassignable']) ? 1 : 0,
            'maintained' => ! empty($validated['maintained']) ? 1 : 0,
            'order_number' => trim((string) ($validated['order_number'] ?? '')),
            'purchase_cost' => ! empty($validated['purchase_cost']) ? (float) $validated['purchase_cost'] : null,
            'purchase_date' => ! empty($validated['purchase_date']) ? (string) $validated['purchase_date'] : null,
            'expiration_date' => ! empty($validated['expiration_date']) ? (string) $validated['expiration_date'] : null,
            'termination_date' => ! empty($validated['termination_date']) ? (string) $validated['termination_date'] : null,
            'depreciation_id' => ! empty($validated['depreciation_id']) ? (int) $validated['depreciation_id'] : null,
            'min_qty' => isset($validated['min_qty']) ? (int) $validated['min_qty'] : null,
            'notes' => trim((string) ($validated['notes'] ?? '')),
        ], fn ($v) => $v !== null && $v !== '');
    }

    private function buildStockTypeCreatePayload(array $validated, string $type, Request $request): array
    {
        $imageBase64 = null;
        if ($request->hasFile('image')) {
            $imageFile = $request->file('image');
            $imageBase64 = 'data:'.$imageFile->getMimeType().';base64,'.base64_encode((string) file_get_contents($imageFile->getRealPath()));
        }

        $payload = array_filter([
            'name' => trim((string) ($validated['name'] ?? '')),
            'qty' => (int) ($validated['qty'] ?? 0),
            'category_id' => (int) $validated['category_id'],
            'company_id' => ! empty($validated['company_id']) ? (int) $validated['company_id'] : null,
            'location_id' => ! empty($validated['location_id']) ? (int) $validated['location_id'] : null,
            'manufacturer_id' => ! empty($validated['manufacturer_id']) ? (int) $validated['manufacturer_id'] : null,
            'supplier_id' => ! empty($validated['supplier_id']) ? (int) $validated['supplier_id'] : null,
            'model_number' => trim((string) ($validated['model_number'] ?? '')) ?: null,
            'order_number' => trim((string) ($validated['order_number'] ?? '')) ?: null,
            'purchase_cost' => ! empty($validated['purchase_cost']) ? (float) $validated['purchase_cost'] : null,
            'purchase_date' => ! empty($validated['purchase_date']) ? (string) $validated['purchase_date'] : null,
            $type === 'consumable' ? 'min_amt' : 'min_qty' => isset($validated['min_qty']) ? (int) $validated['min_qty'] : null,
            'notes' => trim((string) ($validated['notes'] ?? '')),
            'image' => $imageBase64,
        ], fn ($v) => $v !== null && $v !== '');

        if ($type === 'component') {
            $serial = trim((string) ($validated['serial'] ?? ''));
            if ($serial !== '') {
                $payload['serial'] = $serial;
            }
        }
        if ($type === 'consumable') {
            $itemNo = trim((string) ($validated['item_no'] ?? ''));
            if ($itemNo !== '') {
                $payload['item_no'] = $itemNo;
            }
        }

        return $payload;
    }

    private function extractApiMessage(array $response): string
    {
        $messages = $response['messages'] ?? null;
        if (is_string($messages) && $messages !== '') {
            return $messages;
        }
        if (is_array($messages)) {
            foreach ($messages as $m) {
                if (is_string($m) && $m !== '') {
                    return $m;
                }
                if (is_array($m) && ! empty($m[0]) && is_string($m[0])) {
                    return $m[0];
                }
            }
        }

        return 'Unknown Snipe-IT API error.';
    }

    private function normalizeType(string $type): string
    {
        $normalized = strtolower($type);
        $normalized = match ($normalized) {
            'asset', 'hardware' => 'assets',
            'laptop', 'laptops' => 'laptop',
            'licenses' => 'license',
            'accessory' => 'accessories',
            'components' => 'component',
            'consumables' => 'consumable',
            default => $normalized,
        };

        return array_key_exists($normalized, self::ASSET_TYPES) ? $normalized : 'assets';
    }

    private function buildAssets(bool $forceRefresh = false, ?string $type = null, ?int $statusId = null, array $statusIds = [])
    {
        $query = $statusId ? ['status_id' => $statusId] : [];
        $hardwareRows = $this->snipe->fetchRows('hardware', $query, 500, $forceRefresh);

        // The unfiltered Snipe-IT endpoint omits archived statuses. Include each
        // known status when showing Every State so Broken assets remain discoverable.
        if (! $statusId) {
            foreach ($statusIds as $knownStatusId) {
                $hardwareRows = array_merge(
                    $hardwareRows,
                    $this->snipe->fetchRows('hardware', ['status_id' => (int) $knownStatusId], 500, $forceRefresh),
                );
            }
        }

        $records = $this->sortSnipeRowsByNewest(collect($hardwareRows))
            ->unique(fn (array $asset) => (int) ($asset['id'] ?? 0))
            ->values()
            ->filter(function (array $a) use ($type) {
                if ($type !== 'laptop') {
                    return true;
                }

                $categoryName = strtolower((string) data_get($a, 'category.name', ''));
                $modelName = strtolower((string) data_get($a, 'model.name', ''));
                $name = strtolower((string) ($a['name'] ?? ''));

                return str_contains($categoryName, 'laptop')
                    || str_contains($modelName, 'laptop')
                    || str_contains($name, 'laptop');
            })
            ->map(fn (array $a) => [
                'id' => (int) ($a['id'] ?? 0),
                'name' => (string) ($a['name'] ?? $a['asset_tag'] ?? '-'),
                'serial' => (string) ($a['serial'] ?? ''),
                'otherserial' => (string) ($a['asset_tag'] ?? ''),
                'holder_name' => $this->extractAssignedUserName($a),
                'state' => (int) data_get($a, 'status_label.id', 0),
                'state_name' => (string) data_get($a, 'status_label.name', '-'),
                'group_name' => (string) data_get($a, 'location.name', '-'),
                'department_name' => $this->extractAssignedDepartmentName($a),
                'company_name' => $this->extractAssignedCompanyName($a),
                'type_name' => (string) data_get($a, 'category.name', '-'),
                'stock' => '-',
                'used' => '-',
                'notes' => (string) ($a['notes'] ?? ''),
            ])
            ->filter(fn ($a) => $a['id'] > 0)
            ->values();

        return $records;
    }

    private function buildConsumables(bool $forceRefresh = false)
    {
        return $this->sortSnipeRowsByNewest(
            collect($this->snipe->fetchRows('consumables', [], 500, $forceRefresh)),
        )
            ->map(fn (array $a) => [
                'id' => (int) ($a['id'] ?? 0),
                'name' => (string) ($a['name'] ?? ''),
                'serial' => (string) ($a['model_number'] ?? ''),
                'holder_name' => '',
                'group_name' => (string) data_get($a, 'location.name', ''),
                'department_name' => '',
                'company_name' => $this->valueToString(data_get($a, 'company.name'), ''),
                'type_name' => (string) data_get($a, 'category.name', ''),
                'stock' => (int) ($a['qty'] ?? 0),
                'remaining' => (int) ($a['remaining'] ?? $a['remaining_qty'] ?? 0),
                'used' => max(0, (int) ($a['qty'] ?? 0) - (int) ($a['remaining'] ?? $a['remaining_qty'] ?? 0)),
                'state_name' => '',
                'notes' => (string) ($a['notes'] ?? ''),
            ])
            ->filter(fn ($a) => $a['id'] > 0)
            ->values();
    }

    private function buildLicenses(bool $forceRefresh = false)
    {
        return $this->sortSnipeRowsByNewest(
            collect($this->snipe->fetchRows('licenses', [], 500, $forceRefresh)),
        )
            ->map(function (array $a) {
                $totalSeats = (int) ($a['seats'] ?? 0);
                $freeSeats = (int) ($a['free_seats_count'] ?? $a['free_seats'] ?? 0);

                return [
                    'id' => (int) ($a['id'] ?? 0),
                    'name' => (string) ($a['name'] ?? ''),
                    'serial' => (string) ($a['serial'] ?? ''),
                    'otherserial' => (string) ($a['product_key'] ?? ''),
                    'holder_name' => '',
                    'group_name' => (string) data_get($a, 'location.name', ''),
                    'department_name' => $this->valueToString(data_get($a, 'department.name'), ''),
                    'company_name' => $this->valueToString(data_get($a, 'company.name'), ''),
                    'type_name' => (string) data_get($a, 'manufacturer.name', ''),
                    'stock' => $totalSeats,
                    'remaining' => $freeSeats,
                    'used' => max(0, $totalSeats - $freeSeats),
                    'state_name' => '',
                    'notes' => (string) ($a['notes'] ?? ''),
                ];
            })
            ->filter(fn ($a) => $a['id'] > 0)
            ->values();
    }

    private function buildAccessories(bool $forceRefresh = false)
    {
        return $this->sortSnipeRowsByNewest(
            collect($this->snipe->fetchRows('accessories', [], 500, $forceRefresh)),
        )
            ->map(function (array $a) {
                $qty = (int) ($a['qty'] ?? 0);
                $remaining = (int) ($a['remaining_qty'] ?? $a['remaining'] ?? 0);

                return [
                    'id' => (int) ($a['id'] ?? 0),
                    'name' => (string) ($a['name'] ?? ''),
                    'serial' => (string) ($a['model_number'] ?? ''),
                    'holder_name' => '',
                    'group_name' => (string) data_get($a, 'location.name', ''),
                    'department_name' => '',
                    'company_name' => $this->valueToString(data_get($a, 'company.name'), ''),
                    'type_name' => (string) data_get($a, 'category.name', ''),
                    'stock' => $qty,
                    'remaining' => $remaining,
                    'used' => max(0, $qty - $remaining),
                    'state_name' => '',
                    'notes' => (string) ($a['notes'] ?? ''),
                ];
            })
            ->filter(fn ($a) => $a['id'] > 0)
            ->values();
    }

    private function buildComponents(bool $forceRefresh = false)
    {
        return $this->sortSnipeRowsByNewest(
            collect($this->snipe->fetchRows('components', [], 500, $forceRefresh)),
        )
            ->map(function (array $a) {
                $qty = (int) ($a['qty'] ?? 0);
                $remaining = (int) ($a['remaining_qty'] ?? $a['remaining'] ?? 0);

                return [
                    'id' => (int) ($a['id'] ?? 0),
                    'name' => (string) ($a['name'] ?? ''),
                    'serial' => (string) ($a['serial'] ?? ''),
                    'holder_name' => '',
                    'group_name' => (string) data_get($a, 'location.name', ''),
                    'department_name' => '',
                    'company_name' => $this->valueToString(data_get($a, 'company.name'), ''),
                    'type_name' => (string) data_get($a, 'category.name', ''),
                    'stock' => $qty,
                    'remaining' => $remaining,
                    'used' => max(0, $qty - $remaining),
                    'state_name' => '',
                    'notes' => (string) ($a['notes'] ?? ''),
                ];
            })
            ->filter(fn ($a) => $a['id'] > 0)
            ->values();
    }

    private function sortSnipeRowsByNewest(Collection $rows): Collection
    {
        return $rows->sort(function (array $left, array $right): int {
            $leftDate = data_get($left, 'created_at.datetime')
                ?? data_get($left, 'created_at')
                ?? data_get($left, 'updated_at.datetime')
                ?? data_get($left, 'updated_at')
                ?? '';
            $rightDate = data_get($right, 'created_at.datetime')
                ?? data_get($right, 'created_at')
                ?? data_get($right, 'updated_at.datetime')
                ?? data_get($right, 'updated_at')
                ?? '';

            $dateResult = strcmp((string) $rightDate, (string) $leftDate);

            return $dateResult !== 0
                ? $dateResult
                : ((int) ($right['id'] ?? 0) <=> (int) ($left['id'] ?? 0));
        })->values();
    }

    public function checkSerial(Request $request): JsonResponse
    {
        $serial = (string) $request->query('serial', '');
        $type = (string) $request->query('type', 'assets');

        try {
            $endpoint = $this->endpointForType($type);
            $assets = $this->snipe->fetchRows($endpoint, ['search' => $serial]);

            $results = collect($assets)->map(fn ($a) => [
                'id' => $a['id'],
                'name' => $a['name'] ?? $a['model']['name'] ?? 'Unknown Item',
                'asset_tag' => $a['asset_tag'] ?? null,
                'serial' => $a['serial'] ?? null,
                'otherserial' => $a['otherserial'] ?? null,
                'category' => $a['category']['name'] ?? null,
                'type_name' => $a['model']['name'] ?? null,
                'remaining' => (int) ($a['remaining_qty'] ?? $a['remaining'] ?? $a['free_seats_count'] ?? 0),
            ])->all();

            return response()->json($results);
        } catch (\Throwable $e) {
            return response()->json([], 500);
        }
    }

    private function copyAssetImageToStb(Request $request): ?string
    {
        if (! $request->hasFile('image')) {
            return null;
        }

        try {
            $file = $request->file('image');
            $filename = 'stb_'.time().'_'.Str::random(5).'.'.$file->getClientOriginalExtension();

            return $file->storeAs('stb', $filename, 'public');
        } catch (\Exception $e) {
            Log::error('Failed to copy asset image to STB', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function resolveStockStatusId(): ?int
    {
        $statuses = $this->snipe->fetchRows('statuslabels');
        foreach ($statuses as $s) {
            if (strtolower($s['name']) === 'stock' || strtolower($s['status_type']) === 'deployable') {
                return (int) $s['id'];
            }
        }

        return null;
    }

    private function storeStockHistory(Request $request, string $type, int $assetId, array $validated): void
    {
        $docPath = null;
        if ($request->hasFile('stock_document')) {
            $docPath = $request->file('stock_document')->store('stock-documents', 'public');
        }

        AssetStockHistory::create([
            'asset_type' => $type,
            'asset_id' => $assetId,
            'qty' => (int) ($validated['qty'] ?? 1),
            'po_number' => (string) ($validated['po_number'] ?? ''),
            'purchase_date' => (string) ($validated['purchase_date'] ?? now()->toDateString()),
            'document_path' => $docPath,
            'notes' => ! empty($validated['notes']) ? (string) $validated['notes'] : null,
            'created_by' => $request->user()?->id,
        ]);
    }

    private function valueToString(mixed $v, string $default = ''): string
    {
        if ($v === null) {
            return $default;
        }

        return (string) $v;
    }

    private function extractCost(mixed $v): string
    {
        if ($v === null || $v === '') {
            return '';
        }

        $val = '';
        if (is_array($v)) {
            $val = (string) ($v['numeric'] ?? $v['value'] ?? '');
        } else {
            $val = (string) $v;
        }

        // Strip currency symbols, commas, and other formatting characters, keeping only digits and decimal point
        return preg_replace('/[^-0-9.]/', '', $val);
    }

    private function extractRawDate(mixed $v): string
    {
        if (is_array($v)) {
            return (string) ($v['date'] ?? $v['datetime'] ?? '');
        }

        return is_string($v) ? $v : '';
    }

    private function extractMinQty(array $record): string
    {
        return (string) ($record['min_qty'] ?? $record['min_amt'] ?? '');
    }

    private function extractAssignedUserName(array $a): string
    {
        $name = data_get($a, 'assigned_to.name');
        if ($name) {
            return (string) $name;
        }
        $firstName = data_get($a, 'assigned_to.first_name');
        $lastName = data_get($a, 'assigned_to.last_name');
        if ($firstName || $lastName) {
            return trim($firstName.' '.$lastName);
        }

        return '-';
    }

    private function extractAssignedDepartmentName(array $a): string
    {
        return (string) data_get($a, 'assigned_to.department.name', '-');
    }

    private function extractAssignedCompanyName(array $a): string
    {
        return (string) data_get($a, 'assigned_to.company.name', '-');
    }

    private function fetchSnipeFiles(string $type, int $assetId): array
    {
        try {
            $endpoint = $this->endpointForType($type);
            $response = $this->snipe->request("{$endpoint}/{$assetId}/files");

            $files = is_array($response['rows'] ?? null) ? $response['rows'] : [];

            return collect($files)->map(fn (array $f) => [
                'id' => $f['id'] ?? null,
                'filename' => $f['name'] ?? $f['filename'] ?? '-',
                'download_url' => $f['url'] ?? null,
                'created_by' => data_get($f, 'created_by.name', '-'),
                'date' => data_get($f, 'created_at.formatted', '-'),
                'notes' => $f['note'] ?? '-',
            ])->values()->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function fetchHardwareMaintenances(int $assetId, bool $forceRefresh = false): array
    {
        $rows = $this->snipe->fetchRows("hardware/{$assetId}/maintenances", [], 200, $forceRefresh);

        // Some Snipe-IT versions do not expose records through the nested endpoint.
        // Fall back to the global maintenance list filtered by the parent asset.
        if (! $rows) {
            $globalRows = $this->snipe->fetchRows('maintenances', ['asset_id' => $assetId], 200, $forceRefresh);
            $rows = array_values(array_filter($globalRows, fn (array $row) => (int) data_get($row, 'asset.id', 0) === $assetId
            ));
        }

        return array_map(fn (array $row) => [
            ...$row,
            'type' => $row['type'] ?? $row['asset_maintenance_type'] ?? '-',
            'start_date' => is_array($row['start_date'] ?? null)
                ? ($row['start_date']['formatted'] ?? $row['start_date']['date'] ?? '-')
                : ($row['start_date'] ?? '-'),
            'completion_date' => is_array($row['completion_date'] ?? null)
                ? ($row['completion_date']['formatted'] ?? $row['completion_date']['date'] ?? null)
                : ($row['completion_date'] ?? null),
            'supplier' => is_array($row['supplier'] ?? null)
                ? ($row['supplier']['name'] ?? null)
                : ($row['supplier'] ?? null),
        ], $rows);
    }

    private function fetchHardwareLicenses(int $assetId, bool $forceRefresh = false): array
    {
        return $this->snipe->fetchRows("hardware/{$assetId}/licenses", [], 200, $forceRefresh);
    }

    private function fetchHardwareComponents(int $assetId, bool $forceRefresh = false): array
    {
        return $this->snipe->fetchRows("hardware/{$assetId}/components", [], 200, $forceRefresh);
    }

    private function fetchHardwareSubAssets(int $assetId, bool $forceRefresh = false): array
    {
        return $this->snipe->fetchRows("hardware/{$assetId}/assets", [], 200, $forceRefresh);
    }

    private function resolveItemName($log, string $relation = 'item'): ?string
    {
        $type = $relation === 'item' ? $log->item_type : $log->target_type;
        $id = $relation === 'item' ? $log->item_id : $log->target_id;
        $meta = $log->log_meta ?? [];

        // Use name from meta if available (for Snipe-IT items)
        if (! empty($meta['item_name']) && $relation === 'item') {
            return $meta['item_name'];
        }
        if (! empty($meta['target_name']) && $relation === 'target') {
            return $meta['target_name'];
        }

        // Handle Snipe-IT items which don't have local models
        if (str_starts_with((string) $type, 'snipeit_')) {
            $label = str_replace('snipeit_', '', (string) $type);
            $prefix = match (strtolower($label)) {
                'assets', 'hardware' => 'Asset',
                'license' => 'License',
                'accessories' => 'Accessory',
                'consumable' => 'Consumable',
                'component' => 'Component',
                default => ucfirst($label)
            };

            return "{$prefix} #{$id}";
        }

        return $id ? "ID: {$id}" : null;
    }
}
