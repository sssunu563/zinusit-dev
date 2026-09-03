<?php

namespace App\Http\Controllers;

use App\Models\Stb;
use App\Models\ActionLog;
use App\Models\User;
use App\Services\AssetNoteFormatterService;
use App\Services\ErrorMessageService;
use App\Traits\DocumentCheckoutTrait;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StbController extends DocumentFlowController
{

    protected function buildDocumentRelationSummary(Stb $stb, string $relationLabel): array
    {
        $groupParts = $this->buildGroupParts($stb->group_id);

        return [
            'id' => $stb->id,
            'docId' => $this->formatDocId($stb, $groupParts['company'] ?: null),
            'href' => route('stb.show', $stb),
            'relationLabel' => $relationLabel,
            'documentLabel' => $this->resolveDocumentLabel($stb),
            'userName' => $this->formatUserLabel($stb->user_id),
            'deliverDate' => optional($stb->deliver_date)?->toDateString(),
            'completedAt' => optional($stb->completed_at)?->toIso8601String(),
            'returnedAt' => optional($stb->returned_at)?->toIso8601String(),
        ];
    }

    protected function normalizeStbAssetState(?string $status): ?string
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

                return $this->normalizeStbAssetState($status);
            })
            ->filter(fn (?string $state) => $state !== null)
            ->values()
            ->all();

        if ($states === []) {
            return null;
        }

        $uniqueStates = array_values(array_unique($states));

        if (count($uniqueStates) > 1 || in_array('unsupported', $uniqueStates, true)) {
            return null;
        }

        return $uniqueStates[0] === 'active' ? 'return' : 'out';
    }

    protected function buildCreateInitialData(Request $request): array
    {
        $documentType = $request->query('documentType');
        $movementType = $request->query('movementType');

        if ($documentType === 'handover' && $movementType === null) {
            $movementType = 'out';
        }

        $initialData = [
            'documentType' => $documentType,
            'movementType' => $movementType,
            'linkedStbId' => $request->query('linkedStbId'),
        ];

        $selectedAssetIds = $request->query('selectedAssetIds', []);
        if (!is_array($selectedAssetIds)) {
            $selectedAssetIds = is_string($selectedAssetIds)
                ? preg_split('/[\s,]+/', trim($selectedAssetIds), -1, PREG_SPLIT_NO_EMPTY) ?? []
                : [];
        }

        $selectedAssetIds = array_values(array_filter(array_map('intval', $selectedAssetIds), fn ($id) => $id > 0));

        $linkedStbId = (int) $request->query('linkedStbId', 0);

        if (($request->query('movementType') !== 'return') || $linkedStbId <= 0) {
            $baseData = array_merge(array_filter($initialData, fn ($value) => $value !== null && $value !== ''), [
                'itDrafter_id' => auth()->user()->snipeit_user_id,
            ]);

            if ($selectedAssetIds !== []) {
                $resolvedSelectedUserId = collect($selectedAssetIds)
                    ->map(function (int $assetId): ?int {
                        $record = $this->snipe->getHardware($assetId);
                        $assignedTo = data_get($record, 'assigned_to') ?? data_get($record, 'assignedUser') ?? [];
                        $assignedId = is_array($assignedTo) ? (int) ($assignedTo['id'] ?? 0) : (int) $assignedTo;

                        return $assignedId > 0 ? $assignedId : null;
                    })
                    ->filter(fn (?int $userId) => $userId !== null)
                    ->first();

                if ($resolvedSelectedUserId) {
                    $baseData['user_id'] = $resolvedSelectedUserId;

                    $selectedUser = $this->snipe->getUser($resolvedSelectedUserId);
                    $selectedGroupId = data_get($selectedUser, 'location_id')
                        ?? data_get($selectedUser, 'location.id')
                        ?? data_get($selectedUser, 'group_id')
                        ?? null;

                    if ($selectedGroupId) {
                        $baseData['group_id'] = (int) $selectedGroupId;
                    }
                }

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
                            'is_selected' => true,
                            'asset_reference_snapshot' => (string) ($record['asset_tag'] ?? ''),
                        ];
                    })
                    ->filter()
                    ->values()
                    ->all();
            }

            return $baseData;
        }

        $linkedDoc = Stb::with('items')->find($linkedStbId);

        if (!$linkedDoc || $this->isCancelledState($linkedDoc) || !empty($linkedDoc->returned_at)) {
            return array_filter($initialData, fn ($value) => $value !== null && $value !== '');
        }

        return array_merge($initialData, [
            'user_id' => $linkedDoc->user_id,
            'group_id' => $linkedDoc->group_id,
            'building' => $linkedDoc->building,
            'useDate' => optional($linkedDoc->use_date)?->format('Y-m-d'),
            'batchNo' => $linkedDoc->batch_no,
            'itDrafter_id' => auth()->user()->snipeit_user_id,
            'items' => $linkedDoc->items->map(fn ($item) => [
                'nama' => $item->nama,
                'kategori' => $item->kategori,
                'type' => $item->type,
                'jumlah' => $item->jumlah,
                'serial_no' => $item->serial_no,
                'inventory_number' => $item->inventory_number,
                'computer_id' => $item->computer_id,
                'snipeit_asset_id' => $item->snipeit_asset_id,
                'asset_reference_snapshot' => $item->asset_reference_snapshot,
            ])->values()->all(),
        ]);
    }

    protected function resetCompletion(Stb $stb): void
    {
        if ($stb->completed_pdf_path) {
            Storage::disk('public')->delete($stb->completed_pdf_path);
        }

        if ($stb->completed_at || $stb->completed_pdf_path) {
            $payload = [
                'completed_pdf_path' => null,
                'completed_at' => null,
            ];

            if ($this->hasCompletionFlagColumn()) {
                $payload['is_completed'] = false;
            }

            $stb->forceFill($payload)->save();
        }
    }

    protected function buildShareProps(Stb $stb, bool $sharedMode = false): array
    {
        $stb->loadMissing(['linkedStb', 'linkedReturns']);

        $shareUrl = URL::temporarySignedRoute('stb.share', now()->addDays(7), [
            'stb' => $stb->id,
        ]);

        $shareSignUrls = [];

        foreach (array_keys(self::SIGNATURE_ROLE_FIELDS) as $role) {
            $shareSignUrls[$role] = URL::temporarySignedRoute('stb.share.sign', now()->addDays(7), [
                'stb' => $stb->id,
                'role' => $role,
            ]);
        }

        $stbData = $stb->toArray();
        $stbData['photo'] = $this->resolveStorageDataUri($stb->photo);

        return [
            'stb' => $stbData,
            'sharedMode' => $sharedMode,
            'shareUrl' => $shareUrl,
            'shareSignUrls' => $shareSignUrls,
            'isFullySigned' => $this->hasAllSignatures($stb),
            'isCompleted' => $this->isCompletedState($stb),
            'isCancelled' => $this->isCancelledState($stb),
            'loanReferences' => $this->buildOpenLoanReferences($stb),
            'linkedDocument' => $stb->linkedStb
                ? $this->buildDocumentRelationSummary($stb->linkedStb, 'Dokumen Pinjaman Asal')
                : null,
            'relatedDocuments' => $stb->linkedReturns
                ->sortByDesc('created_at')
                ->map(fn (Stb $linkedReturn) => $this->buildDocumentRelationSummary($linkedReturn, 'Dokumen Pengembalian'))
                ->values()
                ->all(),
            'completedPdfUrl' => $stb->completed_pdf_path ? '/storage/' . ltrim($stb->completed_pdf_path, '/') : null,
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $hasCancellationColumns = $this->hasCancellationColumns();
        $requestedTab = (string) request('tab', 'pending');
        $activeTab = match ($requestedTab) {
            'completed' => 'completed',
            'cancelled' => $hasCancellationColumns ? 'cancelled' : 'pending',
            default => 'pending',
        };
        $hasCompletionColumns = $this->hasCompletionColumns();

        $query = Stb::with('items')
            ->latest('created_at')
            ->when($this->hasDocumentFlowColumns(), fn ($builder) => $builder->where('document_type', '!=', 'loan'))
            ->when($hasCancellationColumns && $activeTab === 'cancelled', fn ($builder) => $builder
                ->whereNotNull('cancelled_at'))
            ->when($hasCancellationColumns && $activeTab !== 'cancelled', fn ($builder) => $builder
                ->whereNull('cancelled_at'))
            ->when($this->hasCompletionFlagColumn() && $activeTab === 'completed', fn ($builder) => $builder
                ->where('is_completed', true))
            ->when($this->hasCompletionFlagColumn() && $activeTab === 'pending', fn ($builder) => $builder
                ->where('is_completed', false))
            ->when(!$this->hasCompletionFlagColumn() && $hasCompletionColumns && $activeTab === 'completed', fn ($builder) => $builder
                ->whereNotNull('completed_at')
                ->whereNotNull('completed_pdf_path'))
            ->when(!$this->hasCompletionFlagColumn() && $hasCompletionColumns && $activeTab === 'pending', fn ($builder) => $builder
                ->where(function ($pendingQuery) {
                    $pendingQuery
                        ->whereNull('completed_at')
                        ->orWhereNull('completed_pdf_path');
                }))
            ->when(!$hasCompletionColumns && $activeTab === 'completed', fn ($builder) => $builder->whereRaw('1 = 0'));

        $stbs = $query
            ->paginate(10)
            ->withQueryString()
            ->through(function (Stb $stb) {
                return array_merge($stb->toArray(), [
                    'share_url' => URL::temporarySignedRoute('stb.share', now()->addDays(7), [
                        'stb' => $stb->id,
                    ]),
                    'document_label' => $this->resolveDocumentLabel($stb),
                    'is_fully_signed' => $this->hasAllSignatures($stb),
                    'is_completed' => $this->isCompletedState($stb),
                    'is_cancelled' => $this->isCancelledState($stb),
                    'completed_pdf_url' => $stb->completed_pdf_path ? '/storage/' . ltrim($stb->completed_pdf_path, '/') : null,
                ]);
            });

        $pendingQuery = Stb::query()
            ->when($this->hasDocumentFlowColumns(), fn ($builder) => $builder->where('document_type', '!=', 'loan'))
            ->when($hasCancellationColumns, fn ($builder) => $builder->whereNull('cancelled_at'));
        $completedQuery = Stb::query()
            ->when($this->hasDocumentFlowColumns(), fn ($builder) => $builder->where('document_type', '!=', 'loan'))
            ->when($hasCancellationColumns, fn ($builder) => $builder->whereNull('cancelled_at'));
        $cancelledCount = $hasCancellationColumns
            ? Stb::query()
                ->when($this->hasDocumentFlowColumns(), fn ($builder) => $builder->where('document_type', '!=', 'loan'))
                ->whereNotNull('cancelled_at')
                ->count()
            : 0;

        return Inertia::render('Stb/Index', [
            'stbs' => $stbs,
            'activeTab' => $activeTab,
            'pendingCount' => $this->hasCompletionFlagColumn()
                ? (clone $pendingQuery)->where('is_completed', false)->count()
                : ($hasCompletionColumns
                ? (clone $pendingQuery)->where(function ($completionQuery) {
                    $completionQuery
                        ->whereNull('completed_at')
                        ->orWhereNull('completed_pdf_path');
                })->count()
                : (clone $pendingQuery)->count()),
            'completedCount' => $this->hasCompletionFlagColumn()
                ? (clone $completedQuery)->where('is_completed', true)->count()
                : ($hasCompletionColumns
                ? (clone $completedQuery)->whereNotNull('completed_at')
                    ->whereNotNull('completed_pdf_path')
                    ->count()
                : 0),
            'cancelledCount' => $cancelledCount,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        if ($request->query('documentType') === 'loan') {
            return redirect()->route('peminjaman.create', $request->query());
        }

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
                return redirect()->route('stb.index')->with('error', 'Hanya aset dengan status Active atau Stock yang dapat dibuat STB. Status lain tidak diperbolehkan.');
            }

            $request->query->set('documentType', 'handover');
            $request->query->set('movementType', $resolvedMovementType);
        }

        return Inertia::render('Stb/Create', [
            'nextStbId' => (Stb::max('id') ?? 0) + 1,
            'initialData' => $this->buildCreateInitialData($request),
            'loanReferences' => $this->buildOpenLoanReferences(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        Log::info('STB store request received', [
            'payload' => $request->except(['photo']),
            'has_photo' => $request->hasFile('photo'),
            'photo_meta' => $request->hasFile('photo')
                ? [
                    'name' => $request->file('photo')?->getClientOriginalName(),
                    'size' => $request->file('photo')?->getSize(),
                    'mime' => $request->file('photo')?->getMimeType(),
                ]
                : null,
        ]);

        $validator = Validator::make($request->all(), [
            'id' => 'nullable|integer',
            'docId' => 'nullable|string',
            'deliverDate' => 'nullable|date',
            'status' => 'nullable|integer|in:1,2,3,4',
            'documentType' => ['required', Rule::in(['handover', 'loan'])],
            'movementType' => ['required', Rule::in(['out', 'return', 'handover'])],
            'linkedStbId' => 'nullable|integer|exists:stbs,id',
            'itDrafter_id' => 'nullable|integer',
            'itChecker_id' => 'nullable|integer',
            'itApproved_id' => 'nullable|integer',
            'reqDocNo' => 'nullable|string',
            'poDocNo' => 'nullable|string',
            'user_id' => 'required|integer',
            'group_id' => 'required|integer',
            'building' => 'nullable|string',
            'useDate' => 'nullable|date',
            'batchNo' => 'nullable|string',
            'photo' => 'nullable|image|max:2048',
            'remark' => 'nullable|string',
            'user_phone' => 'nullable|string',
            'user_email' => 'nullable|string',
            'createDate' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.nama' => 'required|string',
            'items.*.kategori' => 'nullable|string',
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
            Log::warning('STB store validation failed', [
                'errors' => $validator->errors()->toArray(),
                'payload' => $request->except(['photo']),
            ]);

            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        $normalizedMovementType = $validated['movementType'] === 'handover' ? 'out' : ($validated['movementType'] ?? 'out');
        $assetStates = [];

        foreach ($validated['items'] ?? [] as $item) {
            $category = strtolower(trim((string) ($item['kategori'] ?? 'assets')));
            $isHardware = in_array($category, ['assets', 'asset', 'hardware', 'hardware_assets'], true);

            if (!$isHardware) {
                continue;
            }

            $assetId = (int) ($item['snipeit_asset_id'] ?? $item['computer_id'] ?? 0);
            if ($assetId <= 0) {
                continue;
            }

            $record = $this->snipe->getHardware($assetId);
            $status = (string) (data_get($record, 'status_label.name')
                ?? data_get($record, 'status.name')
                ?? data_get($record, 'status_label')
                ?? '');
            $state = $this->normalizeStbAssetState($status);

            if ($state === 'unsupported') {
                return redirect()->back()->withErrors([
                    'items' => ['Hanya aset dengan status Active atau Stock yang dapat dibuat STB. Status lain tidak diperbolehkan.'],
                ])->withInput();
            }

            $assetStates[] = $state;
        }

        if ($assetStates !== []) {
            $uniqueStates = array_values(array_unique($assetStates));
            if (count($uniqueStates) > 1) {
                return redirect()->back()->withErrors([
                    'items' => ['Asset Active dan Stock tidak boleh dicampur dalam satu STB.'],
                ])->withInput();
            }

            $expectedMovement = $uniqueStates[0] === 'active' ? 'return' : 'out';
            if ($normalizedMovementType !== $expectedMovement) {
                return redirect()->back()->withErrors([
                    'items' => [$uniqueStates[0] === 'active'
                        ? 'Asset dengan status Active hanya bisa dibuat STB pengembalian.'
                        : 'Asset dengan status Stock hanya bisa dibuat STB penyerahan.'],
                ])->withInput();
            }
        }

        if (($validated['documentType'] ?? null) === 'loan') {
            return redirect()->route('peminjaman.create', [
                'movementType' => $validated['movementType'] ?? 'out',
                'linkedStbId' => $validated['linkedStbId'] ?? null,
            ])->with('error', 'Dokumen peminjaman sekarang dikelola dari modul Peminjaman.');
        }

        try {
            $stb = DB::transaction(function () use ($request, $validated) {
                $photoPath = null;
                $documentType = $validated['documentType'];
                $movementType = $validated['movementType'];
                $linkedLoan = $documentType === 'loan' && $movementType === 'return'
                    ? $this->validateLinkedLoanReference((int) ($validated['linkedStbId'] ?? 0))
                    : null;

                if ($documentType === 'loan' && $movementType === 'return' && !$linkedLoan) {
                    throw new \InvalidArgumentException('Dokumen pinjaman asal wajib dipilih.');
                }

                // Fetch user and location details for snapshot
                $recipient = $validated['user_id'] ? $this->snipe->getUser((int) $validated['user_id']) : null;
                $location = $validated['group_id'] ? $this->snipe->getLocation((int) $validated['group_id']) : null;

                $stb = Stb::create([
                    'deliver_date' => $validated['deliverDate'] ?? null,
                    'status' => $validated['status'] ?? $this->resolveLegacyStatus($documentType, $movementType),
                    'document_type' => $documentType,
                    'movement_type' => $movementType,
                    'linked_stb_id' => $linkedLoan?->id,
                    'returned_at' => null,
                    'it_drafter_id' => $validated['itDrafter_id'] ?? null,
                    'it_checker_id' => $validated['itChecker_id'] ?? null,
                    'it_approved_id' => $validated['itApproved_id'] ?? null,
                    'req_doc_no' => $validated['reqDocNo'] ?? null,
                    'po_doc_no' => $validated['poDocNo'] ?? null,
                    'user_id' => $validated['user_id'] ?? null,
                    'user_name' => $recipient ? (trim(data_get($recipient, 'first_name', '') . ' ' . data_get($recipient, 'last_name', '')) ?: data_get($recipient, 'name')) : null,
                    'user_company' => data_get($recipient, 'company.name'),
                    'user_dept' => data_get($recipient, 'department.name'),
                    'user_title' => data_get($recipient, 'jobtitle') ?: data_get($recipient, 'title_name'),
                    'user_phone' => data_get($recipient, 'phone'),
                    'user_email' => data_get($recipient, 'email'),
                    'group_id' => $validated['group_id'] ?? null,
                    'location_name' => data_get($location, 'name'),

                    'building' => $validated['building'] ?? null,
                    'use_date' => $validated['useDate'] ?? null,
                    'batch_no' => $validated['batchNo'] ?? null,
                    'photo' => $photoPath,
                    'remark' => $validated['remark'] ?? null,
                    'is_completed' => false,
                    'created_at' => $validated['createDate'] ?? now(),
                    'updated_at' => now(),
                ]);

                $photoPath = $this->storePhoto($request, $validated['docId'] ?? null);

                if ($photoPath) {
                    $stb->update([
                        'photo' => $photoPath,
                    ]);
                }

                foreach ($validated['items'] as $item) {
                    $stb->items()->create($this->buildItemSnapshotPayload($item));
                }

                return $stb;
            });

            Log::info('STB created successfully', [
                'stb_id' => $stb->id,
                'user_id' => $stb->user_id,
                'group_id' => $stb->group_id,
                'items_count' => count($validated['items'] ?? []),
            ]);

            // Log to ActionLog
            try {
                $note = AssetNoteFormatterService::formatSimpleNote(
                    $stb,
                    action: 'Document Created',
                    recipient: $stb->user_name ?? 'System'
                );
                
                ActionLog::create([
                    'user_id'     => auth()->id(),
                    'action_type' => 'created',
                    'item_type'   => Stb::class,
                    'item_id'     => $stb->id,
                    'note'        => $note,
                    'log_meta'    => [
                        'stb_id'         => $stb->id,
                        'document_type'  => $stb->document_type,
                        'movement_type'  => $stb->movement_type,
                        'user_id'        => $stb->user_id,
                        'group_id'       => $stb->group_id,
                        'items_count'    => count($validated['items'] ?? []),
                    ],
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to write STB action log', [
                    'action'  => 'created',
                    'stb_id'  => $stb->id,
                    'error'   => $e->getMessage(),
                ]);
            }

            return redirect()->route('stb.show', $stb->id)
                ->with('success', 'STB berhasil dibuat.');
        } catch (\Exception $e) {
            ErrorMessageService::logError($e, 'stb_create', ['payload_keys' => array_keys($request->except(['photo']))]);

            return redirect()->back()
                ->with('error', ErrorMessageService::getUserFriendlyMessage($e, 'stb_create'));
        }
    }

    /**
     * Display the specified resource.
     */
    protected function ensureEditable(mixed $stb): void
    {
        parent::ensureEditable($stb);

        $signedCount = 0;
        if ($stb->it_drafter_signature_path) $signedCount++;
        if ($stb->it_checker_signature_path) $signedCount++;
        if ($stb->it_approved_signature_path) $signedCount++;
        if ($stb->requester_received_signature_path) $signedCount++;
        if ($stb->requester_dept_head_signature_path) $signedCount++;

        if ($signedCount > 0) {
            abort(403, 'Document cannot be edited once signatures are collected.');
        }
    }

    public function show(Stb $stb)
    {
        if ($this->isLoanDocument($stb)) {
            return redirect()->route('peminjaman.show', $stb);
        }

        $stb->load(['items', 'linkedStb', 'linkedReturns']);

        return Inertia::render('Stb/Show', $this->buildShareProps($stb));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Stb $stb)
    {
        if ($this->isLoanDocument($stb)) {
            return redirect()->route('peminjaman.edit', $stb);
        }

        $this->ensureEditable($stb);
        $stb->load('items');

        $stbData = $stb->toArray();
        $stbData['photo'] = $this->resolveStorageDataUri($stb->photo);

        return Inertia::render('Stb/Edit', [
            'stb' => $stbData,
            'loanReferences' => $this->buildOpenLoanReferences($stb),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Stb $stb)
    {
        $this->ensureEditable($stb);
        Log::info('STB update request received', [
            'stb_id' => $stb->id,
            'payload' => $request->except(['photo']),
            'has_photo' => $request->hasFile('photo'),
            'photo_meta' => $request->hasFile('photo')
                ? [
                    'name' => $request->file('photo')?->getClientOriginalName(),
                    'size' => $request->file('photo')?->getSize(),
                    'mime' => $request->file('photo')?->getMimeType(),
                ]
                : null,
        ]);

        $validator = Validator::make($request->all(), [
            'id' => 'nullable|integer',
            'docId' => 'nullable|string',
            'deliverDate' => 'nullable|date',
            'status' => 'nullable|integer|in:1,2,3,4',
            'documentType' => ['required', Rule::in(['handover', 'loan', 'service'])],
            'movementType' => ['required', Rule::in(['out', 'return', 'handover'])],
            'linkedStbId' => 'nullable|integer|exists:stbs,id',
            'itDrafter_id' => 'nullable|integer',
            'itChecker_id' => 'nullable|integer',
            'itApproved_id' => 'nullable|integer',
            'reqDocNo' => 'nullable|string',
            'poDocNo' => 'nullable|string',
            'user_id' => 'required|integer',
            'group_id' => 'required|integer',
            'building' => 'nullable|string',
            'useDate' => 'nullable|date',
            'batchNo' => 'nullable|string',
            'photo' => 'nullable|image|max:2048',
            'remark' => 'nullable|string',
            'createDate' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.id' => 'nullable|integer',
            'items.*.nama' => 'required|string',
            'items.*.kategori' => 'nullable|string',
            'items.*.type' => 'required|string',
            'items.*.jumlah' => 'required|integer|min:1',
            'items.*.serialNo' => 'nullable|string',
            'items.*.inventory_number' => 'nullable|string',
            'items.*.computer_id' => 'nullable|integer',
            'items.*.snipeit_asset_id' => 'nullable|integer',
            'items.*.asset_reference_snapshot' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            Log::warning('STB update validation failed', [
                'stb_id' => $stb->id,
                'errors' => $validator->errors()->toArray(),
                'payload' => $request->except(['photo']),
            ]);

            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        if (($validated['documentType'] ?? null) === 'loan') {
            return redirect()->route('peminjaman.edit', $stb)
                ->with('error', 'Dokumen peminjaman sekarang dikelola dari modul Peminjaman.');
        }

        try {
            DB::transaction(function () use ($request, $validated, $stb) {
                $photoPath = $stb->photo;
                $documentType = $validated['documentType'];
                $movementType = $validated['movementType'];
                $linkedLoan = $documentType === 'loan' && $movementType === 'return'
                    ? $this->validateLinkedLoanReference((int) ($validated['linkedStbId'] ?? 0), $stb)
                    : null;

                if ($documentType === 'loan' && $movementType === 'return' && !$linkedLoan) {
                    throw new \InvalidArgumentException('Dokumen pinjaman asal wajib dipilih.');
                }

                if ($request->hasFile('photo')) {
                    $photoPath = $this->storePhoto($request, $validated['docId'] ?? null, $stb->photo);
                }

                // Fetch user and location details for snapshot
                $recipient = $validated['user_id'] ? $this->snipe->getUser((int) $validated['user_id']) : null;
                $location = $validated['group_id'] ? $this->snipe->getLocation((int) $validated['group_id']) : null;

                $stb->update([
                    'deliver_date' => $validated['deliverDate'] ?? null,
                    'status' => $validated['status'] ?? $this->resolveLegacyStatus($documentType, $movementType),
                    'document_type' => $documentType,
                    'movement_type' => $movementType,
                    'linked_stb_id' => $linkedLoan?->id,
                    'returned_at' => $documentType === 'loan' && $movementType === 'out' ? $stb->returned_at : null,
                    'it_drafter_id' => $validated['itDrafter_id'] ?? null,
                    'it_checker_id' => $validated['itChecker_id'] ?? null,
                    'it_approved_id' => $validated['itApproved_id'] ?? null,
                    'req_doc_no' => $validated['reqDocNo'] ?? null,
                    'po_doc_no' => $validated['poDocNo'] ?? null,
                    'user_id' => $validated['user_id'] ?? null,
                    'user_name' => $recipient ? (trim(data_get($recipient, 'first_name', '') . ' ' . data_get($recipient, 'last_name', '')) ?: data_get($recipient, 'name')) : null,
                    'user_company' => data_get($recipient, 'company.name'),
                    'user_dept' => data_get($recipient, 'department.name'),
                    'user_title' => data_get($recipient, 'jobtitle') ?: data_get($recipient, 'title_name'),
                    'user_phone' => data_get($recipient, 'phone'),
                    'user_email' => data_get($recipient, 'email'),
                    'group_id' => $validated['group_id'] ?? null,
                    'location_name' => data_get($location, 'name'),

                    'building' => $validated['building'] ?? null,
                    'use_date' => $validated['useDate'] ?? null,
                    'batch_no' => $validated['batchNo'] ?? null,
                    'photo' => $photoPath,
                    'remark' => $validated['remark'] ?? null,
                    'is_completed' => false,
                ]);

                $stb->items()->delete();

                foreach ($validated['items'] as $item) {
                    $stb->items()->create($this->buildItemSnapshotPayload($item));
                }
            });

            Log::info('STB updated successfully', [
                'stb_id' => $stb->id,
                'items_count' => count($validated['items'] ?? []),
            ]);

            return redirect()->route('stb.show', $stb->id)
                ->with('success', 'STB berhasil diperbarui.');
        } catch (\Exception $e) {
            ErrorMessageService::logError($e, 'stb_update', ['stb_id' => $stb->id]);

            return redirect()->back()
                ->with('error', ErrorMessageService::getUserFriendlyMessage($e, 'stb_update'));
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Stb $stb)
    {
        $this->ensureEditable($stb);
        try {
            if ($stb->photo) {
                Storage::disk('public')->delete($stb->photo);
            }

            if ($stb->completed_pdf_path) {
                Storage::disk('public')->delete($stb->completed_pdf_path);
            }

            foreach (self::SIGNATURE_ROLE_FIELDS as $fields) {
                if ($stb->{$fields['path']}) {
                    Storage::disk('public')->delete($stb->{$fields['path']});
                }
            }

            $stb->delete();
            return redirect()->route('stb.index')
                ->with('success', 'STB berhasil dihapus.');
        } catch (\Exception $e) {
            ErrorMessageService::logError($e, 'stb_delete', ['stb_id' => $stb->id]);

            return redirect()->back()
                ->with('error', ErrorMessageService::getUserFriendlyMessage($e, 'stb_delete'));
        }
    }

    public function cancel(Request $request, Stb $stb)
    {
        if ($this->isLoanDocument($stb)) {
            return redirect()->route('peminjaman.show', $stb);
        }

        $this->ensureEditable($stb);

        if (!$this->hasCancellationColumns()) {
            return redirect()->back()
                ->with('error', 'Kolom cancellation belum ada. Jalankan migration terlebih dahulu.');
        }

        try {
            $stb->forceFill([
                'cancelled_at' => now(),
            ])->save();

            Log::info('STB cancelled successfully', [
                'stb_id' => $stb->id,
            ]);

            // Log to ActionLog
            try {
                ActionLog::create([
                    'user_id'     => auth()->id(),
                    'action_type' => 'cancelled',
                    'item_type'   => Stb::class,
                    'item_id'     => $stb->id,
                    'note'        => "STB #{$stb->id} dibatalkan",
                    'log_meta'    => ['document_type' => $stb->document_type, 'movement_type' => $stb->movement_type],
                ]);
            } catch (\Throwable $logEx) {
                Log::warning('Failed to write cancel action log', ['error' => $logEx->getMessage()]);
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'STB berhasil dibatalkan.',
                ]);
            }

            return redirect()->route('stb.index', ['tab' => 'cancelled'])
                ->with('success', 'STB berhasil dibatalkan.');
        } catch (\Throwable $e) {
            ErrorMessageService::logError($e, 'stb_cancel', ['stb_id' => $stb->id]);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => ErrorMessageService::getUserFriendlyMessage($e, 'stb_cancel'),
                ], 500);
            }

            return redirect()->back()
                ->with('error', ErrorMessageService::getUserFriendlyMessage($e, 'stb_cancel'));
        }
    }

    /**
     * Show the print view for the specified resource.
     */
    public function print(Stb $stb)
    {
        if ($this->isLoanDocument($stb)) {
            return redirect()->route('peminjaman.print', $stb);
        }

        $stb->load('items');

        if ($stb->completed_pdf_path && Storage::disk('public')->exists($stb->completed_pdf_path)) {
            return response()->file(
                storage_path('app/public/' . $stb->completed_pdf_path),
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="' . basename($stb->completed_pdf_path) . '"',
                ],
            );
        }

        return Inertia::render('Stb/Print', $this->buildShareProps($stb));
    }

    public function complete(Request $request, Stb $stb)
    {
        Log::info('STB Completion Started', ['stb_id' => $stb->id, 'current_status' => $stb->is_completed]);

        if ($this->isLoanDocument($stb)) {
            Log::info('STB Completion skipped: Is loan document.');
            return redirect()->route('peminjaman.show', $stb);
        }

        $stb->load('items');

        if ($this->isCancelledState($stb)) {
            Log::warning('STB Completion aborted: Document is cancelled.', ['stb_id' => $stb->id]);
            return $request->expectsJson()
                ? response()->json(['message' => 'Cancelled STB cannot be completed.'], 409)
                : redirect()->back()->with('error', 'Cancelled STB cannot be completed.');
        }

        if (!$this->hasCompletionColumns()) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Kolom completion belum ada. Jalankan migration terlebih dahulu.'], 409)
                : redirect()->back()->with('error', 'Kolom completion belum ada. Jalankan migration terlebih dahulu.');
        }

        if (!$this->hasAllSignatures($stb)) {
            Log::warning('STB Completion aborted: Missing signatures.', ['stb_id' => $stb->id]);
            return $request->expectsJson()
                ? response()->json(['message' => 'Semua signature harus lengkap sebelum complete.'], 422)
                : redirect()->back()->with('error', 'Semua signature harus lengkap sebelum complete.');
        }

        if ($this->isLoanReturnDocument($stb)) {
            try {
                $this->validateLinkedLoanReference((int) $stb->linked_stb_id, $stb);
            } catch (\InvalidArgumentException $exception) {
                return $request->expectsJson()
                    ? response()->json(['message' => $exception->getMessage()], 422)
                    : redirect()->back()->with('error', $exception->getMessage());
            }
        }

        try {
            if ($stb->movement_type === 'return') {
                Log::info('Invoking Snipe-IT automated checkin BEFORE PDF generation...', ['stb_id' => $stb->id]);
                $this->processSnipeItCheckin($stb);
                Log::info('Snipe-IT checkin process finished successfully.');
            } else {
                Log::info('Invoking Snipe-IT automated checkout BEFORE PDF generation...', ['stb_id' => $stb->id]);
                $this->processSnipeItCheckout($stb);
                Log::info('Snipe-IT checkout process finished successfully.');
            }

            Log::info('Generating completed PDF...', ['stb_id' => $stb->id]);
            $pdfPath = $this->generateCompletedPdf($stb);
            Log::info('PDF Generated.', ['path' => $pdfPath]);

            Log::info('Starting database transaction for completion...', ['stb_id' => $stb->id]);
            DB::transaction(function () use ($stb, $pdfPath) {
                $stb->update([
                    'completed_pdf_path' => $pdfPath,
                    'completed_at' => now(),
                    'is_completed' => true,
                ]);

                if ($this->isLoanReturnDocument($stb) && $stb->linked_stb_id) {
                    Stb::query()
                        ->whereKey($stb->linked_stb_id)
                        ->update([
                            'returned_at' => now(),
                        ]);
                }
            });
            Log::info('Database record updated successfully.');
            
            // Centralized finalization: Logs history, Uploads PDF to Snipe-IT, Flushes cache, Triggers auto-service
            $this->finalizeDocumentCompletion($stb, $pdfPath);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'STB berhasil diselesaikan.',
                    'completed_pdf_path' => $pdfPath,
                ]);
            }

            return redirect()->route('stb.show', $stb)
                ->with('success', 'STB berhasil diselesaikan.');
        } catch (\Throwable $e) {
            ErrorMessageService::logError($e, 'stb_complete', ['stb_id' => $stb->id]);

            return $request->expectsJson()
                ? response()->json(['message' => ErrorMessageService::getUserFriendlyMessage($e, 'stb_complete')], 500)
                : redirect()->back()->with('error', ErrorMessageService::getUserFriendlyMessage($e, 'stb_complete'));
        }
    }

    public function sign(Request $request, Stb $stb, string $role)
    {
        if ($this->isLoanDocument($stb)) {
            return redirect()->route('peminjaman.show', $stb);
        }

        return $this->performSign($request, $stb, $role, 'stb.show', [$stb]);
    }

    public function sharedShow(Stb $stb)
    {
        if ($this->isLoanDocument($stb)) {
            return redirect()->route('peminjaman.show', $stb);
        }

        $stb->load('items');

        return Inertia::render('Stb/Show', $this->buildShareProps($stb, true));
    }

    public function sharedSign(Request $request, Stb $stb, string $role)
    {
        if ($this->isLoanDocument($stb)) {
            return redirect()->route('peminjaman.show', $stb);
        }

        return $this->performSign($request, $stb, $role, null, [], true);
    }

    public function clearSign(Request $request, Stb $stb, string $role)
    {
        if ($this->isLoanDocument($stb)) {
            return redirect()->route('peminjaman.show', $stb);
        }

        return $this->performClearSign($request, $stb, $role);
    }

    public function lastOutStb(int $userId): \Illuminate\Http\JsonResponse
    {
        $stb = \App\Models\Stb::where('user_id', $userId)
            ->where('movement_type', 'out')
            ->where('is_completed', true)
            ->latest()
            ->first();

        if (!$stb) {
            return response()->json(['stb' => null]);
        }

        return response()->json([
            'stb' => [
                'id' => $stb->id,
                'docId' => $this->formatDocId($stb, null), // Company not strictly needed for link
            ]
        ]);
    }

    public function nextStbId(): \Illuminate\Http\JsonResponse
    {
        $lastId = \App\Models\Stb::query()->max('id') ?? 0;
        return response()->json(['next_id' => $lastId + 1]);
    }
}
