<?php

namespace App\Http\Controllers;

use App\Models\AssetStockHistory;
use App\Models\Stb;
use App\Models\User;
use Illuminate\Support\Collection;
use App\Services\AssetNoteFormatterService;
use App\Services\ErrorMessageService;
use App\Services\SnipeItService;
use App\Traits\DocumentCheckoutTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

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
    ) {
    }

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

        if (!$record) {
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

        return Inertia::render('Asset/Create', [
            'mode' => 'edit',
            'assetId' => $assetId,
            'initialType' => $type,
            'types' => $this->buildTypes(),
            'metadata' => $metadata,
            'initialData' => $this->mapAssetRecordToFormData($type, $record, $metadata),
            'initialModelDetail' => $initialModelDetail,
        ]);
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
            'record'   => ["{$endpoint}/{$assetId}", []],
            'files'    => ["{$endpoint}/{$assetId}/files", []],
            'checkout' => $this->checkoutEndpointForPool($type, $assetId),
            // TRULY PARALLEL DEEP SYNC: Request 3 pages of 500 simultaneously to bypass Snipe-IT server-side cap
            'hist_p1'  => ['reports/activity', ['item_type' => $this->reportsTargetType($type), 'item_id' => $assetId, 'limit' => 500, 'offset' => 0]],
            'hist_p2'  => ['reports/activity', ['item_type' => $this->reportsTargetType($type), 'item_id' => $assetId, 'limit' => 500, 'offset' => 500]],
            'hist_p3'  => ['reports/activity', ['item_type' => $this->reportsTargetType($type), 'item_id' => $assetId, 'limit' => 500, 'offset' => 1000]],
        ], true);

        $record = $poolResults['record'] ?? [];
        if (empty($record['id'] ?? null)) {
            return redirect()
                ->route('asset.index', ['type' => $type])
                ->with('error', 'Detail data not found in Snipe-IT.');
        }

        // Build files from pool result
        $rawFiles = is_array($poolResults['files']['rows'] ?? null) ? $poolResults['files']['rows'] : [];
        $assetFiles = collect($rawFiles)->map(fn (array $f) => [
            'id'           => $f['id'] ?? null,
            'filename'     => $f['name'] ?? $f['filename'] ?? '-',
            'download_url' => $f['url'] ?? null,
            'created_by'   => data_get($f, 'created_by.name', '-'),
            'date'         => data_get($f, 'created_at.formatted', '-'),
            'notes'        => $f['note'] ?? '-',
        ])->sortByDesc('date')->values()->all();

        $view = match ($type) {
            'license'     => 'Asset/ShowLicense',
            'accessories' => 'Asset/ShowAccessory',
            'consumable'  => 'Asset/ShowConsumable',
            'component'   => 'Asset/ShowComponent',
            default       => 'Asset/Show',
        };

        return Inertia::render($view, [
            'assetType'       => $type,
            'assetTypeLabel'  => self::ASSET_TYPES[$type] ?? 'Asset',
            'asset'           => $this->mapAssetDetail($type, $record),
            'assetFiles'      => $assetFiles,
            'checkoutRecords' => $this->buildCheckoutFromPool($type, $assetId, $poolResults['checkout'] ?? []),
            'activityHistory' => $this->fetchActivityHistory($type, $assetId, array_merge(
                $poolResults['hist_p1']['rows'] ?? [],
                $poolResults['hist_p2']['rows'] ?? [],
                $poolResults['hist_p3']['rows'] ?? []
            )),
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
            'record'   => ["{$endpoint}/{$assetId}", []],
            'files'    => ["{$endpoint}/{$assetId}/files", []],
            'checkout' => $this->checkoutEndpointForPool($type, $assetId),
            'hist_p1'  => ['reports/activity', ['item_type' => $this->reportsTargetType($type), 'item_id' => $assetId, 'limit' => 500, 'offset' => 0]],
            'hist_p2'  => ['reports/activity', ['item_type' => $this->reportsTargetType($type), 'item_id' => $assetId, 'limit' => 500, 'offset' => 500]],
            'hist_p3'  => ['reports/activity', ['item_type' => $this->reportsTargetType($type), 'item_id' => $assetId, 'limit' => 500, 'offset' => 1000]],
        ], true);

        $record = $poolResults['record'] ?? [];
        if (empty($record['id'] ?? null)) {
            return response()->json(['error' => 'Data not found'], 404);
        }

        $rawFiles = is_array($poolResults['files']['rows'] ?? null) ? $poolResults['files']['rows'] : [];
        $assetFiles = collect($rawFiles)->map(fn (array $f) => [
            'id'           => $f['id'] ?? null,
            'filename'     => $f['name'] ?? $f['filename'] ?? '-',
            'download_url' => $f['url'] ?? null,
            'created_by'   => data_get($f, 'created_by.name', '-'),
            'date'         => data_get($f, 'created_at.formatted', '-'),
            'notes'        => $f['note'] ?? '-',
        ])->values()->all();

        return response()->json([
            'assetType'       => $type,
            'assetTypeLabel'  => self::ASSET_TYPES[$type] ?? 'Asset',
            'asset'           => $this->mapAssetDetail($type, $record),
            'assetFiles'      => $assetFiles,
            'checkoutRecords' => $this->buildCheckoutFromPool($type, $assetId, $poolResults['checkout'] ?? []),
            'activityHistory' => $this->fetchActivityHistory($type, $assetId, array_merge(
                $poolResults['hist_p1']['rows'] ?? [],
                $poolResults['hist_p2']['rows'] ?? [],
                $poolResults['hist_p3']['rows'] ?? []
            )),
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

        if (!$assetId) {
            return response()->json(['error' => 'Asset not found'], 404);
        }

        return response()->json([
            'assetType'      => 'assets',
            'assetTypeLabel' => 'Assets',
            'asset'          => $this->mapAssetDetail('assets', $record),
        ]);
    }

    public function printLabel(Request $request, string $tag): \Inertia\Response|\Illuminate\Http\RedirectResponse
    {
        $result = $this->snipe->getHardwareByAssetTag($tag);
        $rows   = $result['rows'] ?? [];

        if (empty($rows)) {
            return redirect()->route('asset.index')->with('error', 'Asset tidak ditemukan.');
        }

        $record    = $rows[0];
        $asset     = $this->mapAssetDetail('assets', $record);
        $ref       = $asset['serial'] ?: $asset['asset_tag'];
        $publicUrl = $ref ? url("a/{$ref}") : url("a/{$tag}");

        return \Inertia\Inertia::render('Asset/Label', [
            'asset'     => $asset,
            'publicUrl' => $publicUrl,
        ]);
    }

    public function tabData(Request $request, int $assetId): JsonResponse
    {
        $type = $this->normalizeType((string) $request->query('type', 'assets'));
        $tab  = (string) $request->query('tab', '');

        if ($type !== 'assets' && $type !== 'laptop') {
            return response()->json([]);
        }

        $data = match ($tab) {
            'maintenances' => $this->fetchHardwareMaintenances($assetId, true),
            'licenses'     => $this->fetchHardwareLicenses($assetId, true),
            'components'   => $this->fetchHardwareComponents($assetId, true),
            'sub_assets'   => $this->fetchHardwareSubAssets($assetId, true),
            default        => [],
        };

        return response()->json($data);
    }

    public function addStock(Request $request, int $assetId): RedirectResponse
    {
        $type = $this->normalizeType((string) $request->input('type', 'assets'));

        if (!in_array($type, ['accessories', 'consumable', 'component', 'license'], true)) {
            abort(404);
        }

        $validated = $request->validate([
            'qty'           => 'required|integer|min:1',
            'po_number'     => 'nullable|string|max:100',
            'purchase_date' => 'nullable|date|before_or_equal:today',
            'notes'         => 'nullable|string|max:1000',
            'document'      => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx',
        ]);

        $addedQty = (int) $validated['qty'];
        $endpoint = $this->endpointForType($type);

        $current = $this->snipe->request("{$endpoint}/{$assetId}");
        
        // Licenses use 'seats', others use 'qty'
        $qtyField = ($type === 'license') ? 'seats' : 'qty';
        $currentQty = (int) ($current[$qtyField] ?? $current['qty'] ?? $current['total_qty'] ?? 0);
        $newQty     = $currentQty + $addedQty;

        $syncPayload = [$qtyField => $newQty];
        if (!empty($validated['notes'])) {
            $syncPayload['notes'] = $validated['notes'];
        }
        $syncResult = $this->snipe->updateRecord($endpoint, $assetId, $syncPayload);
        if (($syncResult['status'] ?? 'error') === 'error') {
            Log::warning('addStock: Snipe-IT qty sync failed', [
                'asset_id' => $assetId, 'type' => $type,
                'error'    => $syncResult['messages'] ?? $syncResult,
            ]);
        }

        $documentPath = null;
        if ($request->hasFile('document')) {
            $documentPath = $request->file('document')->store('stock-documents', 'public');
        }

        AssetStockHistory::query()->create([
            'asset_type'    => $type,
            'asset_id'      => $assetId,
            'qty'           => $addedQty,
            'po_number'     => (string) ($validated['po_number'] ?? ''),
            'purchase_date' => (string) ($validated['purchase_date'] ?? now()->toDateString()),
            'document_path' => $documentPath,
            'notes'         => !empty($validated['notes']) ? (string) $validated['notes'] : null,
            'created_by'    => $request->user()?->id,
        ]);

        if ($request->hasFile('document')) {
            $file = $request->file('document');
            $noteParts = array_filter([
                !empty($validated['po_number'])     ? 'PO: ' . $validated['po_number']     : null,
                !empty($validated['purchase_date']) ? 'Tgl: ' . $validated['purchase_date'] : null,
                !empty($validated['notes'])         ? $validated['notes']                   : null,
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
            . (!empty($validated['po_number']) ? " | PO: {$validated['po_number']}" : '')
            . (!empty($validated['notes'])     ? " | {$validated['notes']}"         : '');
        $this->logAction('add_stock', $assetId, $this->normalizeType($type), $note, [
            'added_qty'   => $addedQty,
            'new_qty'     => $newQty,
            'po_number'   => $validated['po_number'] ?? null,
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
                'id'            => $h->id,
                'qty'           => $h->qty,
                'po_number'     => $h->po_number ?: '-',
                'purchase_date' => $h->purchase_date?->format('d M Y') ?? '-',
                'notes'         => $h->notes,
                'document_url'  => $h->document_path
                    ? \Illuminate\Support\Facades\Storage::url($h->document_path)
                    : null,
                'created_by'    => $h->createdBy?->name ?? 'System',
                'created_at'    => $h->created_at?->format('d M Y H:i') ?? '-',
            ]);

        return response()->json($rows);
    }

    public function uploadDocument(Request $request, int $assetId): RedirectResponse
    {
        $type = $this->normalizeType((string) $request->input('type', 'assets'));

        $validated = $request->validate([
            'document' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx',
            'notes'    => 'nullable|string|max:1000',
        ]);

        $file     = $request->file('document');
        $endpoint = $this->endpointForType($type);

        $this->snipe->uploadFile(
            $endpoint,
            $assetId,
            (string) file_get_contents($file->getRealPath()),
            $file->getClientOriginalName(),
            trim((string) ($validated['notes'] ?? '')),
        );
        $this->logAction('upload', $assetId, $this->normalizeType($type), "Uploaded document: " . $file->getClientOriginalName());
        $this->snipe->flushCacheForAsset($type, $assetId);

        return redirect()
            ->route('asset.show', ['assetId' => $assetId, 'type' => $type])
            ->with('success', 'Dokumen berhasil di-upload.');
    }

    public function store(Request $request): RedirectResponse
    {
        $type = $this->normalizeType((string) $request->input('type', 'assets'));

        $validated = match ($type) {
            'assets' => $request->validate([
                'type'             => 'required|string',
                'name'             => 'nullable|string|max:255',
                'asset_tag'        => 'required|string|max:255',
                'serial'           => 'nullable|string|max:255',
                'model_id'         => 'required|integer',
                'status_id'        => 'nullable|integer',
                'company_id'       => 'nullable|integer',
                'location_id'      => 'nullable|integer',
                'notes'            => 'nullable|string',
                'requestable'      => 'nullable|boolean',
                'custom_fields'    => 'nullable|array',
                // Optional Information
                'warranty_months'  => 'nullable|integer|min:0',
                'expected_checkin' => 'nullable|date',
                'next_audit_date'  => 'nullable|date',
                'byod'             => 'nullable|boolean',
                // Order Related Information
                'order_number'     => 'nullable|string|max:255',
                'purchase_date'    => 'nullable|date',
                'asset_eol_date'   => 'nullable|date',
                'supplier_id'      => 'nullable|integer',
                'purchase_cost'    => 'nullable|numeric|min:0',
                // Image
                'image'            => 'nullable|file|max:5120|mimes:jpg,jpeg,png,gif,webp',
                'create_stb'       => 'nullable|boolean',
                'stb_user_id'      => 'nullable|required_if:create_stb,true|integer',
                'stb_location_id'  => 'nullable|integer',
                'stb_building'     => 'nullable|string|max:255',
                'stb_batch_no'      => 'nullable|string|max:255',
                'stb_req_doc_no'    => 'nullable|string|max:255',
                'stb_po_doc_no'     => 'nullable|string|max:255',
                'stb_it_drafter_id' => 'nullable|required_if:create_stb,true|integer',
                'stb_it_checker_id' => 'nullable|required_if:create_stb,true|integer',
                'stb_it_approved_id'=> 'nullable|required_if:create_stb,true|integer',
                'stb_remark'        => 'nullable|string|max:1000',
            ]),
            'license' => $request->validate([
                'type'             => 'required|string',
                'name'             => 'required|string|max:255',
                'seats'            => 'required|integer|min:1',
                'category_id'      => 'required|integer',
                'company_id'       => 'nullable|integer',
                'manufacturer_id'  => 'nullable|integer',
                'supplier_id'      => 'nullable|integer',
                'serial'           => 'nullable|string|max:255',
                'license_name'     => 'nullable|string|max:255',
                'license_email'    => 'nullable|email|max:255',
                'reassignable'     => 'nullable|boolean',
                'order_number'     => 'nullable|string|max:255',
                'purchase_cost'    => 'nullable|numeric|min:0',
                'purchase_date'    => 'nullable|date',
                'expiration_date'  => 'nullable|date',
                'termination_date' => 'nullable|date',
                'min_qty'          => 'nullable|integer|min:0',
                'notes'            => 'nullable|string',
                'depreciation_id'  => 'nullable|integer',
                'maintained'       => 'nullable|boolean',
                'create_stb'       => 'nullable|boolean',
                'stb_user_id'       => 'nullable|required_if:create_stb,true|integer',
                'stb_location_id'   => 'nullable|integer',
                'stb_building'      => 'nullable|string|max:255',
                'stb_use_date'      => 'nullable|date',
                'stb_batch_no'      => 'nullable|string|max:255',
                'stb_req_doc_no'    => 'nullable|string|max:255',
                'stb_po_doc_no'     => 'nullable|string|max:255',
                'stb_it_drafter_id' => 'nullable|required_if:create_stb,true|integer',
                'stb_it_checker_id' => 'nullable|required_if:create_stb,true|integer',
                'stb_it_approved_id'=> 'nullable|required_if:create_stb,true|integer',
                'stb_remark'        => 'nullable|string|max:1000',
            ]),
            'accessories', 'consumable', 'component' => $request->validate([
                'type'           => 'required|string',
                'name'           => 'required|string|max:255',
                'qty'            => 'required|integer|min:1',
                'category_id'    => 'required|integer',
                'company_id'     => 'nullable|integer',
                'location_id'    => 'nullable|integer',
                'manufacturer_id'=> 'nullable|integer',
                'supplier_id'    => 'nullable|integer',
                'model_number'   => 'nullable|string|max:255',
                'item_no'        => 'nullable|string|max:255',
                'serial'         => 'nullable|string|max:255',
                'order_number'   => 'nullable|string|max:255',
                'purchase_cost'  => 'nullable|numeric|min:0',
                'purchase_date'  => 'nullable|date',
                'min_qty'        => 'nullable|integer|min:0',
                'notes'          => 'nullable|string',
                'image'          => 'nullable|file|max:5120|mimes:jpg,jpeg,png,gif,webp',
                'po_number'      => 'nullable|string|max:100',
                'stock_document' => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx',
                'create_stb'     => 'nullable|boolean',
                'stb_user_id'       => 'nullable|required_if:create_stb,true|integer',
                'stb_location_id'   => 'nullable|integer',
                'stb_building'      => 'nullable|string|max:255',
                'stb_use_date'      => 'nullable|date',
                'stb_batch_no'      => 'nullable|string|max:255',
                'stb_req_doc_no'    => 'nullable|string|max:255',
                'stb_po_doc_no'     => 'nullable|string|max:255',
                'stb_it_drafter_id' => 'nullable|required_if:create_stb,true|integer',
                'stb_it_checker_id' => 'nullable|required_if:create_stb,true|integer',
                'stb_it_approved_id'=> 'nullable|required_if:create_stb,true|integer',
                'stb_remark'        => 'nullable|string|max:1000',
            ]),
            default => abort(404),
        };

        $endpoint = $this->endpointForType($type);

        // Auto-set "stock" (RTD) status for hardware assets on create
        if ($type === 'assets' && empty($validated['status_id'])) {
            $validated['status_id'] = $this->resolveStockStatusId();
        }

        $payload = match ($type) {
            'assets'  => $this->buildHardwareCreatePayload($validated, $request),
            'license' => $this->buildLicenseCreatePayload($validated),
            default   => $this->buildStockTypeCreatePayload($validated, $type, $request),
        };

        $response = $this->snipe->createRecord($endpoint, $payload);

        if (($response['status'] ?? 'error') !== 'success') {
            return back()
                ->withInput()
                ->with('error', 'Failed to create ' . self::ASSET_TYPES[$type] . ': ' . $this->extractApiMessage($response));
        }

        $createdId = (int) (data_get($response, 'payload.id') ?? data_get($response, 'id') ?? 0);

        if (in_array($type, ['accessories', 'consumable', 'component', 'license'], true) && $createdId > 0) {
            $this->storeStockHistory($request, $type, $createdId, $validated);
        }

        // Automated STB Creation
        if (!empty($validated['create_stb']) && !empty($validated['stb_user_id']) && $createdId > 0) {
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
                    'user_name' => trim(data_get($recipient, 'first_name', '') . ' ' . data_get($recipient, 'last_name', '')) ?: data_get($recipient, 'name'),
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

                if (!empty($validated['stb_send_notification'])) {
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
                    ->with('success', self::ASSET_TYPES[$type] . ' created and STB generated successfully.');
            } catch (\Exception $e) {
                // Log error but the asset is already created, so we might want to still redirect to asset index with warning
                ErrorMessageService::logError($e, 'stb_create', ['asset_type' => $type, 'auto_generated' => true]);
                return redirect()
                    ->route('asset.index', ['type' => $type])
                    ->with('success', self::ASSET_TYPES[$type] . ' created successfully, but STB generation failed.');
            }
        }

        // Log locally - ensure normalized type
        $assetName = $validated['name'] ?? ($validated['asset_tag'] ?? 'Asset Baru');
        $this->logAction('create', $createdId, $this->normalizeType($type), $validated['notes'] ?? "Asset baru dibuat: {$assetName}", array_merge($payload, [
            'item_name' => $assetName,
        ]));

        return redirect()
            ->route('asset.index', ['type' => $type])
            ->with('success', self::ASSET_TYPES[$type] . ' created successfully.');
    }

    public function update(Request $request, int $assetId): RedirectResponse
    {
        $type = $this->normalizeType((string) $request->input('type', 'assets'));

        $validated = match ($type) {
            'assets' => $request->validate([
                'type'             => 'required|string',
                'name'             => 'nullable|string|max:255',
                'asset_tag'        => 'required|string|max:255',
                'serial'           => 'nullable|string|max:255',
                'model_id'         => 'required|integer',
                'status_id'        => 'nullable|integer',
                'company_id'       => 'nullable|integer',
                'location_id'      => 'nullable|integer',
                'notes'            => 'nullable|string',
                'requestable'      => 'nullable|boolean',
                'custom_fields'    => 'nullable|array',
                // Optional Information
                'warranty_months'  => 'nullable|integer|min:0',
                'expected_checkin' => 'nullable|date',
                'next_audit_date'  => 'nullable|date',
                'byod'             => 'nullable|boolean',
                // Order Related Information
                'order_number'     => 'nullable|string|max:255',
                'purchase_date'    => 'nullable|date',
                'asset_eol_date'   => 'nullable|date',
                'supplier_id'      => 'nullable|integer',
                'purchase_cost'    => 'nullable|numeric|min:0',
                // Image
                'image'            => 'nullable|file|max:5120|mimes:jpg,jpeg,png,gif,webp',
            ]),
            'license' => $request->validate([
                'type'             => 'required|string',
                'name'             => 'required|string|max:255',
                'seats'            => 'required|integer|min:1',
                'category_id'      => 'required|integer',
                'company_id'       => 'nullable|integer',
                'manufacturer_id'  => 'nullable|integer',
                'supplier_id'      => 'nullable|integer',
                'serial'           => 'nullable|string|max:255',
                'license_name'     => 'nullable|string|max:255',
                'license_email'    => 'nullable|email|max:255',
                'reassignable'     => 'nullable|boolean',
                'order_number'     => 'nullable|string|max:255',
                'purchase_cost'    => 'nullable|numeric|min:0',
                'purchase_date'    => 'nullable|date',
                'expiration_date'  => 'nullable|date',
                'termination_date' => 'nullable|date',
                'min_qty'          => 'nullable|integer|min:0',
                'notes'            => 'nullable|string',
                'depreciation_id'  => 'nullable|integer',
                'maintained'       => 'nullable|boolean',
            ]),
            'accessories', 'consumable', 'component' => $request->validate([
                'type'           => 'required|string',
                'name'           => 'required|string|max:255',
                'qty'            => 'required|integer|min:1',
                'category_id'    => 'required|integer',
                'company_id'     => 'nullable|integer',
                'location_id'    => 'nullable|integer',
                'manufacturer_id'=> 'nullable|integer',
                'supplier_id'    => 'nullable|integer',
                'model_number'   => 'nullable|string|max:255',
                'item_no'        => 'nullable|string|max:255',
                'serial'         => 'nullable|string|max:255',
                'order_number'   => 'nullable|string|max:255',
                'purchase_cost'  => 'nullable|numeric|min:0',
                'purchase_date'  => 'nullable|date',
                'min_qty'        => 'nullable|integer|min:0',
                'notes'          => 'nullable|string',
                'image'          => 'nullable|file|max:5120|mimes:jpg,jpeg,png,gif,webp',
                'po_number'      => 'nullable|string|max:100',
                'stock_document' => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx',
            ]),
            default => abort(404),
        };

        $endpoint = $this->endpointForType($type);

        $payload = match ($type) {
            'assets'  => $this->buildHardwareCreatePayload($validated, $request),
            'license' => $this->buildLicenseCreatePayload($validated),
            default   => $this->buildStockTypeCreatePayload($validated, $type, $request),
        };

        $response = $this->snipe->updateRecord($endpoint, $assetId, $payload);

        if (($response['status'] ?? 'error') !== 'success') {
            return back()
                ->withInput()
                ->with('error', 'Failed to update ' . self::ASSET_TYPES[$type] . ': ' . $this->extractApiMessage($response));
        }

        if (in_array($type, ['accessories', 'consumable', 'component', 'license'], true)) {
            $this->storeStockHistory($request, $type, $assetId, $validated);
        }

        // Log locally - ensure normalized type with informative note & item_name
        $assetName = $validated['name'] ?? ($validated['asset_tag'] ?? null);
        $note = $validated['notes'] ?? ($assetName ? "Pembaruan data asset: {$assetName}" : "Pembaruan data asset");
        $this->logAction('update', $assetId, $this->normalizeType($type), $note, array_merge($payload, array_filter([
            'item_name' => $assetName,
        ])));
        $this->snipe->flushCacheForAsset($type, $assetId);

        return redirect()
            ->route('asset.index', ['type' => $type])
            ->with('success', self::ASSET_TYPES[$type] . ' updated successfully.');
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

        $assets = match ($activeType) {
            'consumable' => $this->buildConsumables($forceRefresh),
            'license' => $this->buildLicenses($forceRefresh),
            'accessories' => $this->buildAccessories($forceRefresh),
            'component' => $this->buildComponents($forceRefresh),
            default => $this->buildAssets($forceRefresh, $laptopOnly ? 'laptop' : null),
        };

        $showStatusFilter = in_array($activeType, ['assets', 'laptop'], true);
        $activeState = $showStatusFilter && is_numeric($status) ? (int) $status : null;

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

                if (!$record) {
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
                'remark' => $validated['remark'] ?? ('Bulk checkout of ' . count($validated['ids']) . ' items.'),
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
                        $docId = 'PEM-' . str_pad((string) $pem->id, 5, '0', STR_PAD_LEFT);
                    }
                    return [
                        'id' => $pem->id,
                        'docId' => $docId,
                        'label' => trim($docId . ' - ' . ($pem->user_name ?? '')),
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
            'name'            => (string) ($record['name'] ?? ''),
            'asset_tag'       => (string) ($record['asset_tag'] ?? ''),
            'serial'          => (string) ($record['serial'] ?? ''),
            'model_id'        => data_get($record, 'model.id') ? (string) data_get($record, 'model.id') : '',
            'status_id'       => data_get($record, 'status_label.id') ? (string) data_get($record, 'status_label.id') : '',
            'category_id'     => data_get($record, 'category.id') ? (string) data_get($record, 'category.id') : '',
            'company_id'      => data_get($record, 'company.id') ? (string) data_get($record, 'company.id') : '',
            'location_id'     => data_get($record, 'rtd_location.id')
                ? (string) data_get($record, 'rtd_location.id')
                : (data_get($record, 'location.id') ? (string) data_get($record, 'location.id') : ''),
            'qty'             => (int) ($record['qty'] ?? 1),
            'seats'           => (int) ($record['seats'] ?? 1),
            'notes'           => (string) ($record['notes'] ?? ''),
            'po_number'       => '',
            'purchase_date'   => '',
            'custom_fields'   => [],
        ];

        if ($type !== 'assets') {
            $base['supplier_id']    = data_get($record, 'supplier.id')      ? (string) data_get($record, 'supplier.id')      : '';
            $base['order_number']   = (string) ($record['order_number'] ?? '');
            $base['purchase_cost']  = $this->extractCost($record['purchase_cost'] ?? null);
            $base['purchase_date']  = $this->extractRawDate($record['purchase_date'] ?? '');
            $base['min_qty']        = $this->extractMinQty($record);

            if ($type === 'license') {
                $base['serial']           = (string) ($record['product_key'] ?? '');
                $base['license_name']     = (string) ($record['license_name'] ?? '');
                $base['license_email']    = (string) ($record['license_email'] ?? '');
                $base['reassignable']     = (bool) ($record['reassignable'] ?? false);
                $base['expiration_date']  = $this->extractRawDate($record['expiration_date'] ?? '');
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

        $base['requestable']      = (bool) ($record['requestable'] ?? false);
        $base['warranty_months']  = $record['warranty_months'] !== null ? (string) ($record['warranty_months'] ?? '') : '';
        $base['expected_checkin'] = $this->extractRawDate($record['expected_checkin'] ?? '');
        $base['next_audit_date']  = $this->extractRawDate($record['next_audit_date'] ?? '');
        $base['byod']             = (bool) ($record['byod'] ?? false);
        $base['order_number']     = (string) ($record['order_number'] ?? '');
        $base['purchase_date']    = $this->extractRawDate($record['purchase_date'] ?? '');
        $base['asset_eol_date']   = $this->extractRawDate($record['asset_eol_date'] ?? '');
        $base['supplier_id']      = data_get($record, 'supplier.id') ? (string) data_get($record, 'supplier.id') : '';
        $base['purchase_cost']    = $this->extractCost($record['purchase_cost'] ?? null);
        $base['custom_fields']    = $this->mapCustomFieldsForForm($record['custom_fields'] ?? null, $record);

        return $base;
    }

    private function mapAssetDetail(string $type, array $record): array
    {
        $qty = (int) ($record['qty'] ?? $record['seats'] ?? 0);
        $remainingQty = (int) ($record['remaining_qty'] ?? $record['remaining'] ?? $record['free_seats_count'] ?? $record['free_seats'] ?? $qty);

        $base = [
            'id'           => (int) ($record['id'] ?? 0),
            'name'         => $this->valueToString($record['name'] ?? $record['asset_tag'] ?? '-', '-'),
            'asset_tag'    => $this->valueToString($record['asset_tag'] ?? ''),
            'serial'       => $this->valueToString($record['serial'] ?? $record['product_key'] ?? ''),
            'model'        => $this->valueToString(data_get($record, 'model.name', '')),
            'category'     => $this->valueToString(data_get($record, 'category.name', '')),
            'manufacturer' => $this->valueToString(data_get($record, 'manufacturer.name', '')),
            'location'     => $this->valueToString(
                data_get($record, 'location.name', data_get($record, 'rtd_location.name', '')),
            ),
            'company'      => $this->valueToString(data_get($record, 'company.name', '')),
            'status'       => $this->valueToString(data_get($record, 'status_label.name', 'Available'), 'Available'),
            'qty'          => $qty,
            'remaining_qty' => $remainingQty,
            'checked_out'  => max(0, $qty - $remainingQty),
            'requestable'  => (bool) ($record['requestable'] ?? false),
            'image'        => $this->valueToString($record['image'] ?? ''),
            'created_by'   => $this->valueToString(data_get($record, 'created_by.name', '')),
            'assigned_to'  => $this->valueToString(data_get($record, 'assigned_to.name', '')),
            'notes'        => $this->valueToString($record['notes'] ?? ''),
            'created_at'   => $this->extractFormattedDate($record['created_at'] ?? ''),
            'updated_at'   => $this->extractFormattedDate($record['updated_at'] ?? ''),
            'custom_fields' => $this->mapCustomFields($record['custom_fields'] ?? null, $record),
            'model_number' => $this->valueToString($record['model_number'] ?? ''),
        ];

        if ($type === 'assets' || $type === 'laptop') {
            return array_merge($base, [
                'status_type'         => $this->valueToString(data_get($record, 'status_label.status_type', '')),
                'rtd_location'        => $this->valueToString(data_get($record, 'rtd_location.name', '')),
                'supplier'            => $this->valueToString(data_get($record, 'supplier.name', '')),
                'purchase_date'       => $this->extractFormattedDate($record['purchase_date'] ?? ''),
                'purchase_cost'       => $this->valueToString($record['purchase_cost'] ?? ''),
                'order_number'        => $this->valueToString($record['order_number'] ?? ''),
                'warranty_months'     => $this->valueToString($record['warranty_months'] ?? ''),
                'warranty_expires'    => $this->extractFormattedDate($record['warranty_expires'] ?? ''),
                'asset_eol_date'      => $this->extractFormattedDate($record['asset_eol_date'] ?? ''),
                'book_value'          => $this->valueToString($record['book_value'] ?? ''),
                'last_audit_date'     => $this->extractFormattedDate($record['last_audit_date'] ?? ''),
                'next_audit_date'     => $this->extractFormattedDate($record['next_audit_date'] ?? ''),
                'last_checkout'       => $this->extractFormattedDate($record['last_checkout'] ?? ''),
                'last_checkin'        => $this->extractFormattedDate($record['last_checkin'] ?? ''),
                'expected_checkin'    => $this->extractFormattedDate($record['expected_checkin'] ?? ''),
                'checkin_counter'     => (int) ($record['checkin_counter'] ?? 0),
                'checkout_counter'    => (int) ($record['checkout_counter'] ?? 0),
                'assigned_to_type'    => $this->valueToString(data_get($record, 'assigned_to.type', '')),
                'assigned_to_username'=> $this->valueToString(data_get($record, 'assigned_to.username', '')),
                'assigned_to_email'   => $this->valueToString(data_get($record, 'assigned_to.email', '')),
                'assigned_to_jobtitle'=> $this->valueToString(data_get($record, 'assigned_to.jobtitle', '')),
                'byod'                => (bool) ($record['byod'] ?? false),
            ]);
        }

        if ($type === 'license') {
            return array_merge($base, [
                'seats'            => $qty,
                'free_seats'       => $remainingQty,
                'license_name'     => $this->valueToString($record['license_name'] ?? ''),
                'license_email'    => $this->valueToString($record['license_email'] ?? ''),
                'reassignable'     => (bool) ($record['reassignable'] ?? false),
                'expiration_date'  => $this->extractFormattedDate($record['expiration_date'] ?? ''),
                'termination_date' => $this->extractFormattedDate($record['termination_date'] ?? ''),
                'purchase_date'    => $this->extractFormattedDate($record['purchase_date'] ?? ''),
                'purchase_cost'    => $this->valueToString($record['purchase_cost'] ?? ''),
                'order_number'     => $this->valueToString($record['order_number'] ?? ''),
                'maintained'       => (bool) ($record['maintained'] ?? false),
            ]);
        }

        if ($type === 'consumable') {
            return array_merge($base, [
                'item_no'          => $this->valueToString($record['item_no'] ?? ''),
                'min_qty'          => $this->valueToString($record['min_amt'] ?? ''),
                'order_number'     => $this->valueToString($record['order_number'] ?? ''),
                'purchase_date'    => $this->extractFormattedDate($record['purchase_date'] ?? ''),
                'purchase_cost'    => $this->valueToString($record['purchase_cost'] ?? ''),
            ]);
        }

        // Accessories and Components
        return array_merge($base, [
            'min_qty'          => $this->valueToString($record['min_qty'] ?? $record['min_amt'] ?? ''),
            'order_number'     => $this->valueToString($record['order_number'] ?? ''),
            'purchase_date'    => $this->extractFormattedDate($record['purchase_date'] ?? ''),
            'purchase_cost'    => $this->valueToString($record['purchase_cost'] ?? ''),
        ]);
    }

    private function mapCustomFields(mixed $fields, array $fullRecord = []): array
    {
        $mapped = [];
        
        // 1. Try to map from the standard custom_fields key
        if (is_array($fields) && !empty($fields)) {
            foreach ($fields as $name => $f) {
                if (is_array($f) && isset($f['value'])) {
                    $mapped[] = [
                        'name'   => (string) ($f['name'] ?? $f['label'] ?? $name),
                        'value'  => $this->valueToString($f['value'] ?? ''),
                        'format' => $this->valueToString($f['field_format'] ?? ''),
                    ];
                }
            }
        }

        // 2. If empty, try to find root-level _snipeit_ fields (some API responses differ)
        if (empty($mapped) && !empty($fullRecord)) {
            foreach ($fullRecord as $key => $value) {
                if (str_starts_with($key, '_snipeit_') && $value !== null && $value !== '') {
                    $mapped[] = [
                        'name'   => ucwords(str_replace(['_snipeit_', '_'], ['', ' '], $key)),
                        'value'  => $this->valueToString($value),
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
        if (is_array($fields) && !empty($fields)) {
            foreach ($fields as $name => $f) {
                if (is_array($f) && isset($f['value'])) {
                    $mapped[$name] = $f['value'];
                }
            }
        }

        // 2. Fallback to root _snipeit_ fields (often how they are sent back/expected)
        if (empty($mapped) && !empty($fullRecord)) {
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
            'component'   => ["components/{$assetId}/assets", []],
            'license'     => ["licenses/{$assetId}/seats", []],
            'consumable'  => ["consumables/{$assetId}/users", ['limit' => 1500]],
            default       => ['hardware/0', []], // hardware has no checkout list tab
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
                            'id'        => (int) ($r['assigned_pivot_id'] ?? $r['id'] ?? 0),
                            'name'      => $this->valueToString(data_get($user, 'name', '')),
                            'secondary' => $this->valueToString(data_get($user, 'username', '')),
                            'email'     => $this->valueToString(data_get($user, 'email', '')),
                            'company'   => $this->valueToString(data_get($user, 'company.name', '')),
                            'location'  => $this->valueToString(data_get($user, 'location.name', data_get($r, 'location.name', ''))),
                            'note'      => $this->valueToString($r['note'] ?? ''),
                            'date'      => $this->extractFormattedDate($r['created_at'] ?? ''),
                            'image'     => $this->valueToString(data_get($user, 'image', data_get($user, 'avatar', ''))),
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
                            'id'        => (int) ($r['id'] ?? 0),
                            'name'      => $this->valueToString(data_get($r, 'name', '')),
                            'secondary' => $this->valueToString(data_get($r, 'asset_tag', '')),
                            'email'     => '',
                            'company'   => $this->valueToString(data_get($r, 'company.name', '')),
                            'location'  => $this->valueToString(data_get($r, 'location.name', '')),
                            'note'      => $note,
                            'date'      => $this->extractFormattedDate($stbItem?->stb?->deliver_date ?? $r['created_at'] ?? ''),
                            'image'     => $this->valueToString(data_get($r, 'image', '')),
                        ];
                    })->sortByDesc('date')->values()->all();

                case 'license':
                    $rows = is_array($rawResponse['rows'] ?? null) ? $rawResponse['rows'] : $rawResponse;
                    if ($rows === []) {
                        $rows = $this->snipe->getLicenseSeats($assetId, true);
                    }
                    return collect($rows)
                        ->filter(fn (array $r) => !empty($r['assigned_to']) || !empty($r['assigned_user']))
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
                                    $note .= ' | Catatan: ' . trim((string) $stb->remark);
                                }
                            }

                            return [
                                'id'        => (int) ($r['id'] ?? 0),
                                'name'      => $this->valueToString(data_get($user, 'name', '')),
                                'secondary' => $this->valueToString(data_get($user, 'username', '')),
                                'email'     => $this->valueToString(data_get($user, 'email', '')),
                                'company'   => $this->valueToString(data_get($user, 'company.name', data_get($r, 'company.name', ''))),
                                'location'  => $this->valueToString(data_get($user, 'location.name', data_get($r, 'location.name', ''))),
                                'note'      => $note,
                                'date'      => $this->extractFormattedDate($stbItem?->stb?->deliver_date ?? $r['updated_at'] ?? ''),
                                'image'     => $this->valueToString(data_get($user, 'avatar', data_get($user, 'image', ''))),
                            ];
                        })->sortByDesc('date')->values()->all();

                case 'consumable':
                    $rows = is_array($rawResponse['rows'] ?? null) ? $rawResponse['rows'] : $rawResponse;
                    $uniqueUsers = [];
                    foreach ($rows as $r) {
                        $uid = (int) data_get($r, 'user.id', data_get($r, 'assigned_to.id', 0));
                        if ($uid <= 0) continue;
                        
                        $qty = (int) ($r['qty'] ?? 1);
                        $date = $this->extractFormattedDate($r['created_at'] ?? '');
                        
                        if (!isset($uniqueUsers[$uid])) {
                            $user = $r['user'] ?? $r['assigned_to'] ?? $r;
                            $uniqueUsers[$uid] = [
                                'id'        => $uid,
                                'name'      => $this->valueToString(data_get($user, 'name', '')),
                                'secondary' => $this->valueToString(data_get($user, 'username', '')),
                                'email'     => $this->valueToString(data_get($user, 'email', '')),
                                'company'   => $this->valueToString(data_get($user, 'company.name', '')),
                                'location'  => $this->valueToString(data_get($user, 'location.name', '')),
                                'note'      => $this->valueToString($r['note'] ?? ''),
                                'date'      => $date,
                                'image'     => $this->valueToString(data_get($user, 'avatar', data_get($user, 'image', ''))),
                                'qty'       => $qty,
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
                    usort($uniqueUsers, fn($a, $b) => strcmp($b['date'], $a['date']));
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
                        'id'         => (int) ($r['assigned_pivot_id'] ?? $r['id'] ?? 0),
                        'name'       => $this->valueToString($r['name'] ?? ''),
                        'secondary'  => $this->valueToString($r['username'] ?? ''),
                        'note'       => $this->valueToString($r['note'] ?? ''),
                        'date'       => $this->extractFormattedDate($r['created_at'] ?? ''),
                        'image'      => $this->valueToString($r['avatar'] ?? ''),
                    ])->values()->all();

                case 'component':
                    $rows = $this->snipe->fetchRows("components/{$assetId}/assets");
                    return collect($rows)->map(fn (array $r) => [
                        'id'         => (int) ($r['id'] ?? 0),
                        'name'       => $this->valueToString($r['name'] ?? ''),
                        'secondary'  => $this->valueToString($r['asset_tag'] ?? ''),
                        'note'       => $this->valueToString(data_get($r, 'location.name', '')),
                        'date'       => '',
                        'image'      => $this->valueToString($r['image'] ?? ''),
                    ])->values()->all();

                case 'license':
                    $rows = $this->snipe->fetchRows("licenses/{$assetId}/seats");
                    return collect($rows)
                        ->filter(fn (array $r) => !empty($r['assigned_to']) || !empty($r['assigned_user']))
                        ->map(fn (array $r) => [
                            'id'         => (int) ($r['id'] ?? 0),
                            'name'       => $this->valueToString(data_get($r, 'assigned_user.name', data_get($r, 'assigned_to.name', ''))),
                            'secondary'  => $this->valueToString(data_get($r, 'assigned_user.username', data_get($r, 'assigned_to.username', ''))),
                            'note'       => $this->valueToString($r['notes'] ?? $r['note'] ?? ''),
                            'date'       => $this->extractFormattedDate($r['updated_at'] ?? ''),
                            'image'       => $this->valueToString(data_get($r, 'assigned_user.avatar', data_get($r, 'assigned_to.avatar', ''))),
                        ])->values()->all();

                case 'consumable':
                    $rows = $this->snipe->fetchRows("consumables/{$assetId}/users", [], 200);
                    return collect($rows)->map(fn (array $r) => [
                        'id'        => (int) data_get($r, 'user.id', 0),
                        'name'      => $this->valueToString(data_get($r, 'user.name', '')),
                        'secondary' => $this->valueToString(data_get($r, 'created_by.name', '')),
                        'note'      => $this->valueToString($r['note'] ?? ''),
                        'date'      => $this->extractFormattedDate($r['created_at'] ?? ''),
                        'image'     => $this->valueToString($r['avatar'] ?? ''),
                    ])->values()->all();

                default:
                    return [];
            }
        } catch (\Throwable) {
            return [];
        }
    }

    private function fetchActivityHistory(string $type, int $assetId, array $snipeHistory = []): array
    {
        try {
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
                }

                return (string) ($history['admin']['name'] ?? ($history['created_by']['name'] ?? '-'));
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

            $history = collect($snipeHistory)->map(fn($h) => [
                'id'          => 'snipe_' . $h['id'],
                'action_type' => strtoupper($h['action_type'] ?? '-'),
                'user'        => $resolveSnipeActor($h),
                'user_image'  => null,
                'target'      => $h['target']['name'] ?? '-',
                'target_type' => $h['target_type'] ?? null,
                'note'        => $resolveSnipeNote($h),
                'date'        => !empty($h['created_at']['datetime']) ? \Carbon\Carbon::parse($h['created_at']['datetime'])->format('Y-m-d H:i:s') : '-',
                'source'      => 'snipeit',
            ])->concat($localLogs->map(fn($log) => [
                'id'          => 'local_' . $log->id,
                'action_type' => strtoupper($log->action_type),
                'user'        => $log->user?->name ?? 'System',
                'user_image'  => null,
                'target'      => $this->resolveItemName($log, 'item'),
                'target_type' => $log->item_type,
                'note'        => $log->note,
                'date'        => $log->created_at->format('Y-m-d H:i:s'),
                'source'      => 'app',
            ]))->concat($stbItems->map(fn($item) => [
                'id'          => 'stb_' . $item->id,
                'action_type' => strtoupper($item->stb->document_type === 'handover' ? 'MUTASI (SERAH TERIMA)' : 'MUTASI (PENGEMBALIAN)'),
                'user'        => $item->stb->user_name ?? 'System',
                'user_image'  => null,
                'target'      => $item->stb->department . ' (' . $item->stb->location_name . ')',
                'target_type' => 'stb',
                'note'        => $item->stb->remark,
                'date'        => \Carbon\Carbon::parse($item->stb->deliver_date)->format('Y-m-d H:i:s'),
                'source'      => 'stb',
                'href'        => route($item->stb->document_type === 'handover' ? 'stb.show' : 'peminjaman.show', $item->stb->id),
            ]))->concat($tickets->map(fn($ticket) => [
                'id'          => 'ticket_' . $ticket->id,
                'action_type' => strtoupper('HELPDESK: ' . $ticket->maintenance_type),
                'user'        => $ticket->technician ?? 'IT Support',
                'user_image'  => null,
                'target'      => $ticket->requester . ' (' . $ticket->status . ')',
                'target_type' => 'ticket',
                'note'        => $ticket->issue_description,
                'date'        => $ticket->created_at->format('Y-m-d H:i:s'),
                'source'      => 'helpdesk',
                'href'        => route('helpdesk.show', $ticket->id),
            ]))->sortByDesc('date')->values()->all();

            return $history;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('History fetch error: ' . $e->getMessage());
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
                $record = $this->fetchAssetRecordByType($itemType, (int)$itemId);
                if ($record) {
                    $meta['item_name'] = (string)($record['name'] ?? $record['asset_tag'] ?? '');
                }
            }

            \App\Models\ActionLog::create([
                'user_id'     => auth()->id(),
                'action_type' => $action,
                'item_type'   => 'snipeit_' . $itemType,
                'item_id'     => $itemId,
                'snipeit_id'  => $itemId,
                'snipeit_type'=> $itemType,
                'note'        => $note,
                'log_meta'    => $meta,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to log action locally: ' . $e->getMessage());
        }
    }

    private function fetchFullModelOption(int $modelId): ?array
    {
        $model = $this->snipe->request('models/' . $modelId);
        if (!is_array($model) || empty($model['id'])) return null;

        $fieldsetId = (int) data_get($model, 'fieldset.id', 0);
        if ($fieldsetId > 0) {
            $fsData     = $this->snipe->request('fieldsets/' . $fieldsetId);
            $fsRows     = $fsData['fields']['rows'] ?? $fsData['rows'] ?? [];
            if (!empty($fsRows)) {
                $model['default_fieldset_values'] = $fsRows;
            }
        }

        // Map definitions to be consistent with SnipeItController
        $rawFields = $model['default_fieldset_values'] ?? [];
        $mappedFields = collect($rawFields)
            ->filter(fn ($field) => is_array($field) && (!empty($field['db_column_name']) || !empty($field['db_column'])))
            ->map(fn (array $field) => [
                'name'           => (string) ($field['name'] ?? '-'),
                'db_column_name' => (string) ($field['db_column_name'] ?? $field['db_column'] ?? ''),
                'default_value'  => $field['default_value'] ?? $field['value'] ?? null,
                'format'         => (string) ($field['format'] ?? $field['field_format'] ?? 'ANY'),
                'type'           => (string) ($field['type'] ?? $field['element'] ?? 'text'),
                'field_values'   => (string) ($field['field_values'] ?? ''),
                'required'       => (bool) ($field['required'] ?? false),
            ])
            ->values()
            ->all();

        return [
            'id'             => (int) $model['id'],
            'name'           => (string) $model['name'],
            'image'          => (string) ($model['image'] ?? ''),
            'fieldset_name'  => (string) data_get($model, 'fieldset.name', ''),
            'has_details'    => true,
            'default_fields' => $mappedFields,
        ];
    }

    private function buildCreateMetadata(): array
    {
        $pool = $this->snipe->requestPool([
            'users_p1'       => ['users',        ['limit' => 500, 'offset' => 0]],
            'users_p2'       => ['users',        ['limit' => 500, 'offset' => 500]],
            'models_p1'      => ['models',       ['limit' => 500, 'offset' => 0]],
            'models_p2'      => ['models',       ['limit' => 500, 'offset' => 500]],
            'models_p3'      => ['models',       ['limit' => 500, 'offset' => 1000]],
            'locations'      => ['locations',    ['limit' => 500]],
            'companies'      => ['companies',    ['limit' => 500]],
            'manufacturers'  => ['manufacturers', ['limit' => 500]],
            'suppliers'      => ['suppliers',    ['limit' => 500]],
            'categories_all' => ['categories',   ['limit' => 500]],
            'statuslabels'   => ['statuslabels', ['limit' => 500]],
            'fieldsets'      => ['fieldsets',    ['limit' => 500]],
        ]);

        $users = array_merge($pool['users_p1']['rows'] ?? [], $pool['users_p2']['rows'] ?? []);
        $models = array_merge(
            $pool['models_p1']['rows'] ?? [],
            $pool['models_p2']['rows'] ?? [],
            $pool['models_p3']['rows'] ?? []
        );

        $locations     = $pool['locations']['rows'] ?? [];
        $companies     = $pool['companies']['rows'] ?? [];
        $manufacturers = $pool['manufacturers']['rows'] ?? [];
        $suppliers     = $pool['suppliers']['rows'] ?? [];
        $categories    = collect($pool['categories_all']['rows'] ?? []);

        $assetMetadata = [
            'categories'    => $categories->filter(fn($c) => ($c['category_type'] ?? '') === 'asset')->values()->all(),
            'companies'     => $companies,
            'locations'     => $locations,
            'statuses'      => $pool['statuslabels']['rows'] ?? [],
            'manufacturers' => $manufacturers,
            'suppliers'     => $suppliers,
            'models'        => $models,
            'fieldsets'     => $pool['fieldsets']['rows'] ?? [],
        ];

        return [
            'users'       => $users,
            'assets'      => $assetMetadata,
            'license'     => [
                'categories'    => $categories->filter(fn($c) => ($c['category_type'] ?? '') === 'license')->values()->all(),
                'manufacturers' => $manufacturers,
                'companies'     => $companies,
                'suppliers'     => $suppliers,
            ],
            'accessories' => [
                'categories'    => $categories->filter(fn($c) => ($c['category_type'] ?? '') === 'accessory')->values()->all(),
                'manufacturers' => $manufacturers,
                'companies'     => $companies,
                'suppliers'     => $suppliers,
            ],
            'consumable' => [
                'categories'    => $categories->filter(fn($c) => ($c['category_type'] ?? '') === 'consumable')->values()->all(),
                'manufacturers' => $manufacturers,
                'companies'     => $companies,
                'suppliers'     => $suppliers,
            ],
            'component' => [
                'categories'    => $categories->filter(fn($c) => ($c['category_type'] ?? '') === 'component')->values()->all(),
                'manufacturers' => $manufacturers,
                'companies'     => $companies,
                'suppliers'     => $suppliers,
            ],
        ];
    }

    /**
     * Maps internal type to Snipe-IT reports target_type.
     */
    private function reportsTargetType(string $type): string
    {
        return match ($type) {
            'assets'      => 'asset',
            'license'     => 'license',
            'accessories' => 'accessory',
            'consumable'  => 'consumable',
            'component'   => 'component',
            default       => 'asset',
        };
    }

    private function buildHardwareCreatePayload(array $validated, Request $request): array
    {
        $payload = [
            'name'             => trim((string) ($validated['name'] ?? '')),
            'asset_tag'        => trim((string) $validated['asset_tag']),
            'serial'           => trim((string) ($validated['serial'] ?? '')),
            'model_id'         => (int) $validated['model_id'],
            'status_id'        => !empty($validated['status_id']) ? (int) $validated['status_id'] : null,
            'company_id'       => !empty($validated['company_id']) ? (int) $validated['company_id'] : null,
            'location_id'      => !empty($validated['location_id']) ? (int) $validated['location_id'] : null,
            'notes'            => trim((string) ($validated['notes'] ?? '')),
            'requestable'      => !empty($validated['requestable']) ? 1 : 0,
            'warranty_months'  => isset($validated['warranty_months']) ? (int) $validated['warranty_months'] : null,
            'expected_checkin' => !empty($validated['expected_checkin']) ? (string) $validated['expected_checkin'] : null,
            'next_audit_date'  => !empty($validated['next_audit_date']) ? (string) $validated['next_audit_date'] : null,
            'byod'             => !empty($validated['byod']) ? 1 : 0,
            'order_number'     => trim((string) ($validated['order_number'] ?? '')),
            'purchase_date'    => !empty($validated['purchase_date']) ? (string) $validated['purchase_date'] : null,
            'asset_eol_date'   => !empty($validated['asset_eol_date']) ? (string) $validated['asset_eol_date'] : null,
            'supplier_id'      => !empty($validated['supplier_id']) ? (int) $validated['supplier_id'] : null,
            'purchase_cost'    => !empty($validated['purchase_cost']) ? (float) $validated['purchase_cost'] : null,
        ];

        if (!empty($validated['custom_fields']) && is_array($validated['custom_fields'])) {
            foreach ($validated['custom_fields'] as $key => $value) {
                if ($value !== null && $value !== '') {
                    $payload[$key] = $value;
                }
            }
        }

        if ($request->hasFile('image')) {
            $imageFile = $request->file('image');
            $payload['image'] = 'data:' . $imageFile->getMimeType() . ';base64,' . base64_encode((string) file_get_contents($imageFile->getRealPath()));
        }

        return array_filter($payload, fn ($v) => $v !== null && $v !== '');
    }

    private function buildLicenseCreatePayload(array $validated): array
    {
        return array_filter([
            'name'             => trim((string) $validated['name']),
            'seats'            => (int) $validated['seats'],
            'category_id'      => (int) $validated['category_id'],
            'company_id'       => !empty($validated['company_id']) ? (int) $validated['company_id'] : null,
            'manufacturer_id'  => !empty($validated['manufacturer_id']) ? (int) $validated['manufacturer_id'] : null,
            'supplier_id'      => !empty($validated['supplier_id']) ? (int) $validated['supplier_id'] : null,
            'product_key'      => trim((string) ($validated['serial'] ?? '')),
            'license_name'     => trim((string) ($validated['license_name'] ?? '')),
            'license_email'    => trim((string) ($validated['license_email'] ?? '')),
            'reassignable'     => !empty($validated['reassignable']) ? 1 : 0,
            'maintained'       => !empty($validated['maintained']) ? 1 : 0,
            'order_number'     => trim((string) ($validated['order_number'] ?? '')),
            'purchase_cost'    => !empty($validated['purchase_cost']) ? (float) $validated['purchase_cost'] : null,
            'purchase_date'    => !empty($validated['purchase_date']) ? (string) $validated['purchase_date'] : null,
            'expiration_date'  => !empty($validated['expiration_date']) ? (string) $validated['expiration_date'] : null,
            'termination_date' => !empty($validated['termination_date']) ? (string) $validated['termination_date'] : null,
            'depreciation_id'  => !empty($validated['depreciation_id']) ? (int) $validated['depreciation_id'] : null,
            'min_qty'          => isset($validated['min_qty']) ? (int) $validated['min_qty'] : null,
            'notes'            => trim((string) ($validated['notes'] ?? '')),
        ], fn ($v) => $v !== null && $v !== '');
    }

    private function buildStockTypeCreatePayload(array $validated, string $type, Request $request): array
    {
        $imageBase64 = null;
        if ($request->hasFile('image')) {
            $imageFile   = $request->file('image');
            $imageBase64 = 'data:' . $imageFile->getMimeType() . ';base64,' . base64_encode((string) file_get_contents($imageFile->getRealPath()));
        }

        $payload = array_filter([
            'name'            => trim((string) ($validated['name'] ?? '')),
            'qty'             => (int) ($validated['qty'] ?? 0),
            'category_id'     => (int) $validated['category_id'],
            'company_id'      => !empty($validated['company_id'])      ? (int) $validated['company_id']      : null,
            'location_id'     => !empty($validated['location_id'])     ? (int) $validated['location_id']     : null,
            'manufacturer_id' => !empty($validated['manufacturer_id']) ? (int) $validated['manufacturer_id'] : null,
            'supplier_id'     => !empty($validated['supplier_id'])     ? (int) $validated['supplier_id']     : null,
            'model_number'    => trim((string) ($validated['model_number'] ?? '')) ?: null,
            'order_number'    => trim((string) ($validated['order_number'] ?? '')) ?: null,
            'purchase_cost'   => !empty($validated['purchase_cost']) ? (float) $validated['purchase_cost'] : null,
            'purchase_date'   => !empty($validated['purchase_date']) ? (string) $validated['purchase_date'] : null,
            'min_qty'         => isset($validated['min_qty']) ? (int) $validated['min_qty'] : null,
            'notes'           => trim((string) ($validated['notes'] ?? '')),
            'image'           => $imageBase64,
        ], fn ($v) => $v !== null && $v !== '');

        if ($type === 'component') {
            $serial = trim((string) ($validated['serial'] ?? ''));
            if ($serial !== '') $payload['serial'] = $serial;
        }
        if ($type === 'consumable') {
            $itemNo = trim((string) ($validated['item_no'] ?? ''));
            if ($itemNo !== '') $payload['item_no'] = $itemNo;
        }

        return $payload;
    }

    private function extractApiMessage(array $response): string
    {
        $messages = $response['messages'] ?? null;
        if (is_string($messages) && $messages !== '') return $messages;
        if (is_array($messages)) {
            foreach ($messages as $m) {
                if (is_string($m) && $m !== '') return $m;
                if (is_array($m) && !empty($m[0]) && is_string($m[0])) return $m[0];
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

    private function buildAssets(bool $forceRefresh = false, ?string $type = null)
    {
        $records = $this->sortSnipeRowsByNewest(
            collect($this->snipe->fetchRows('hardware', [], 500, $forceRefresh)),
        )
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
                'id'             => (int) ($a['id'] ?? 0),
                'name'           => (string) ($a['name'] ?? ''),
                'serial'         => (string) ($a['model_number'] ?? ''),
                'holder_name'    => '',
                'group_name'     => (string) data_get($a, 'location.name', ''),
                'department_name'=> '',
                'company_name'   => $this->valueToString(data_get($a, 'company.name'), ''),
                'type_name'      => (string) data_get($a, 'category.name', ''),
                'stock'          => (int) ($a['qty'] ?? 0),
                'remaining'      => (int) ($a['remaining'] ?? $a['remaining_qty'] ?? 0),
                'used'           => max(0, (int) ($a['qty'] ?? 0) - (int) ($a['remaining'] ?? $a['remaining_qty'] ?? 0)),
                'state_name'     => '',
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
                $freeSeats  = (int) ($a['free_seats_count'] ?? $a['free_seats'] ?? 0);
                return [
                    'id'              => (int) ($a['id'] ?? 0),
                    'name'            => (string) ($a['name'] ?? ''),
                    'serial'          => (string) ($a['serial'] ?? ''),
                    'otherserial'     => (string) ($a['product_key'] ?? ''),
                    'holder_name'     => '',
                    'group_name'      => (string) data_get($a, 'location.name', ''),
                    'department_name' => $this->valueToString(data_get($a, 'department.name'), ''),
                    'company_name'    => $this->valueToString(data_get($a, 'company.name'), ''),
                    'type_name'       => (string) data_get($a, 'manufacturer.name', ''),
                    'stock'           => $totalSeats,
                    'remaining'       => $freeSeats,
                    'used'            => max(0, $totalSeats - $freeSeats),
                    'state_name'      => '',
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
                $qty       = (int) ($a['qty'] ?? 0);
                $remaining = (int) ($a['remaining_qty'] ?? $a['remaining'] ?? 0);
                return [
                    'id'              => (int) ($a['id'] ?? 0),
                    'name'            => (string) ($a['name'] ?? ''),
                    'serial'          => (string) ($a['model_number'] ?? ''),
                    'holder_name'     => '',
                    'group_name'      => (string) data_get($a, 'location.name', ''),
                    'department_name' => '',
                    'company_name'    => $this->valueToString(data_get($a, 'company.name'), ''),
                    'type_name'       => (string) data_get($a, 'category.name', ''),
                    'stock'           => $qty,
                    'remaining'       => $remaining,
                    'used'            => max(0, $qty - $remaining),
                    'state_name'      => '',
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
                $qty       = (int) ($a['qty'] ?? 0);
                $remaining = (int) ($a['remaining_qty'] ?? $a['remaining'] ?? 0);
                return [
                    'id'              => (int) ($a['id'] ?? 0),
                    'name'            => (string) ($a['name'] ?? ''),
                    'serial'          => (string) ($a['serial'] ?? ''),
                    'holder_name'     => '',
                    'group_name'      => (string) data_get($a, 'location.name', ''),
                    'department_name' => '',
                    'company_name'    => $this->valueToString(data_get($a, 'company.name'), ''),
                    'type_name'       => (string) data_get($a, 'category.name', ''),
                    'stock'           => $qty,
                    'remaining'       => $remaining,
                    'used'            => max(0, $qty - $remaining),
                    'state_name'      => '',
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

            $results = collect($assets)->map(fn($a) => [
                'id' => $a['id'],
                'name' => $a['name'] ?? $a['model']['name'] ?? 'Unknown Item',
                'asset_tag' => $a['asset_tag'] ?? null,
                'serial' => $a['serial'] ?? null,
                'otherserial' => $a['otherserial'] ?? null,
                'category' => $a['category']['name'] ?? null,
                'type_name' => $a['model']['name'] ?? null,
                'remaining' => (int)($a['remaining_qty'] ?? $a['remaining'] ?? $a['free_seats_count'] ?? 0),
            ])->all();

            return response()->json($results);
        } catch (\Throwable $e) {
            return response()->json([], 500);
        }
    }

    private function copyAssetImageToStb(Request $request): ?string
    {
        if (!$request->hasFile('image')) return null;

        try {
            $file = $request->file('image');
            $filename = 'stb_' . time() . '_' . Str::random(5) . '.' . $file->getClientOriginalExtension();
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
            'asset_type'    => $type,
            'asset_id'      => $assetId,
            'qty'           => (int) ($validated['qty'] ?? 1),
            'po_number'     => (string) ($validated['po_number'] ?? ''),
            'purchase_date' => (string) ($validated['purchase_date'] ?? now()->toDateString()),
            'document_path' => $docPath,
            'notes'         => !empty($validated['notes']) ? (string) $validated['notes'] : null,
            'created_by'    => $request->user()?->id,
        ]);
    }

    private function valueToString(mixed $v, string $default = ''): string
    {
        if ($v === null) return $default;
        return (string) $v;
    }

    private function extractCost(mixed $v): string
    {
        if ($v === null || $v === '') return '';
        
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
        if (is_array($v)) return (string) ($v['date'] ?? $v['datetime'] ?? '');
        return is_string($v) ? $v : '';
    }

    private function extractMinQty(array $record): string
    {
        return (string) ($record['min_qty'] ?? $record['min_amt'] ?? '');
    }

    private function extractAssignedUserName(array $a): string
    {
        $name = data_get($a, 'assigned_to.name');
        if ($name) return (string) $name;
        $firstName = data_get($a, 'assigned_to.first_name');
        $lastName = data_get($a, 'assigned_to.last_name');
        if ($firstName || $lastName) return trim($firstName . ' ' . $lastName);
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
                'id'          => $f['id'] ?? null,
                'filename'    => $f['name'] ?? $f['filename'] ?? '-',
                'download_url'=> $f['url'] ?? null,
                'created_by'  => data_get($f, 'created_by.name', '-'),
                'date'        => data_get($f, 'created_at.formatted', '-'),
                'notes'       => $f['note'] ?? '-',
            ])->values()->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function fetchHardwareMaintenances(int $assetId, bool $forceRefresh = false): array
    {
        return $this->snipe->fetchRows("hardware/{$assetId}/maintenances", [], 200, $forceRefresh);
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
        if (!empty($meta['item_name']) && $relation === 'item') {
            return $meta['item_name'];
        }
        if (!empty($meta['target_name']) && $relation === 'target') {
            return $meta['target_name'];
        }

        // Handle Snipe-IT items which don't have local models
        if (str_starts_with((string)$type, 'snipeit_')) {
            $label = str_replace('snipeit_', '', (string)$type);
            $prefix = match(strtolower($label)) {
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
