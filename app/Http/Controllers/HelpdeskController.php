<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTicketRequest;
use App\Models\ActionLog;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Vendor;
use App\Services\SnipeItService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HelpdeskController extends Controller
{
    private const PRIORITY_OPTIONS = ['Low', 'Medium', 'High', 'Urgent'];

    private const STATUS_OPTIONS = ['Open', 'In Progress', 'Closed'];

    private const TICKET_SCOPE_OPTIONS = [
        ['value' => 'general', 'label' => 'Dukungan Umum'],
        ['value' => 'asset', 'label' => 'Terkait Aset'],
    ];

    private const MAINTENANCE_TYPE_OPTIONS = [
        'Pemeliharaan',
        'Perbaikan',
        'Uji PAT',
        'Pembaruan',
        'Dukungan Perangkat Keras',
        'Dukungan Perangkat Lunak',
    ];

    private const SNIPEIT_PROFILE_CACHE_KEY    = 'snipeit-user-profile:';
    private const SNIPEIT_SUPERADMIN_CACHE_KEY = 'snipeit-user-superadmin:';

    /** Per-request memoization cache for canViewAllTickets checks */
    private array $canViewAllCache = [];

    public function __construct(private readonly SnipeItService $snipeItService)
    {
    }

    public function index(Request $request): Response
    {
        $filters    = $this->resolveFilters($request);
        $canViewAll = $this->canViewAllTickets($request->user());

        // PERF: Pre-warm Snipe-IT user cache for all visible ticket creators in one pool
        $this->prewarmCreatorCache(
            $this->buildFilteredQuery($request->user(), $filters, $canViewAll)
                ->latest('created_at')
                ->limit(50)
                ->with('creator:id,snipeit_user_id')
                ->get(['id', 'created_by'])
        );

        $tickets = $this->buildFilteredQuery($request->user(), $filters, $canViewAll)
            ->latest('created_at')
            ->paginate($request->input('per_page', 10))
            ->withQueryString()
            ->through(fn (Ticket $ticket) => $this->transformTicket($ticket));

        $remoteUser   = $this->getRemoteSnipeItUser($request->user());
        $techCompany  = (string) (data_get($remoteUser, 'company.name')  ?? '—');
        $techLocation = (string) (data_get($remoteUser, 'location.name') ?? '—');

        return Inertia::render('Helpdesk/Index', [
            'tickets'               => $tickets,
            'filters'               => $filters,
            'priorityOptions'       => self::PRIORITY_OPTIONS,
            'statusOptions'         => self::STATUS_OPTIONS,
            'ticketScopeOptions'    => self::TICKET_SCOPE_OPTIONS,
            'maintenanceTypeOptions'=> self::MAINTENANCE_TYPE_OPTIONS,
            'categoryOptions'       => $this->resolveCategoryOptions(),
            'initialValues'         => $this->resolveInitialValues($request->user()),
            'canViewAll'            => $canViewAll,
            'techCompany'           => $techCompany,
            'techLocation'          => $techLocation,
            'technicianOptions'     => $this->resolveTechnicianOptions(),
            'vendorOptions'         => Vendor::orderBy('name')->get(),
        ]);
    }



    public function show(Request $request, Ticket $ticket): Response
    {
        $this->authorizeTicketAccess($request->user(), $ticket);

        $ticket->loadMissing('creator:id,name,snipeit_user_id');
        $ticketData = $this->transformTicket($ticket);

        return Inertia::render('Helpdesk/Show', [
            'ticket' => $ticketData,
            'canViewAll' => $this->canViewAllTickets($request->user()),
            'techCompany' => $ticketData['tech_company'] ?? '—',
            'techLocation' => $ticketData['tech_location'] ?? '—',
        ]);
    }

    public function print(Request $request, Ticket $ticket): Response
    {
        $this->authorizeTicketAccess($request->user(), $ticket);

        $ticket->loadMissing('creator:id,name,snipeit_user_id');
        $ticketData = $this->transformTicket($ticket);

        ActionLog::create([
            'user_id' => $request->user()->id,
            'action_type' => 'print',
            'item_type' => Ticket::class,
            'item_id' => $ticket->id,
            'note' => "Printed workspace ticket #{$ticket->id}",
        ]);

        return Inertia::render('Helpdesk/Print', [
            'ticket' => $ticketData,
            'canViewAll' => $this->canViewAllTickets($request->user()),
            'techCompany' => $ticketData['tech_company'] ?? '—',
            'techLocation' => $ticketData['tech_location'] ?? '—',
        ]);
    }

    public function store(StoreTicketRequest $request): RedirectResponse
    {
        $ticket = Ticket::query()->create($this->buildPayload($request, [
            'created_by' => $request->user()->id,
        ]));

        $sync = $this->synchronizeTicketMaintenance($ticket);

        ActionLog::create([
            'user_id' => $request->user()->id,
            'action_type' => 'create',
            'item_type' => Ticket::class,
            'item_id' => $ticket->id,
            'note' => "Created workspace ticket #{$ticket->id} for {$ticket->requester}",
        ]);

        // Notify MS Teams
        \App\Services\NotificationService::sendToTeams(
            "🎫 Tiket Baru: #{$ticket->id}",
            "Ada permintaan dukungan IT baru dari **{$ticket->requester}**.",
            $ticket->priority === 'Urgent' ? 'd9534f' : '003628',
            [
                'Lokasi' => $ticket->location,
                'Kategori' => $ticket->category,
                'Prioritas' => $ticket->priority,
                'Masalah' => $ticket->issue_description
            ]
        );

        return $this->redirectWithSyncMessage(
            'helpdesk.index',
            'Workspace ticket saved successfully.',
            $sync,
        );
    }



    public function update(StoreTicketRequest $request, Ticket $ticket): RedirectResponse
    {
        $this->authorizeTicketAccess($request->user(), $ticket);

        if ($ticket->status === 'Closed') {
            return back()->withErrors(['status' => 'Closed tickets cannot be edited.']);
        }

        $ticket->update($this->buildPayload($request));

        $sync = $this->synchronizeTicketMaintenance($ticket->fresh());

        ActionLog::create([
            'user_id' => $request->user()->id,
            'action_type' => 'update',
            'item_type' => Ticket::class,
            'item_id' => $ticket->id,
            'note' => "Updated workspace ticket #{$ticket->id} (Status: {$ticket->status})",
        ]);

        return $this->redirectWithSyncMessage(
            'helpdesk.index',
            'Workspace ticket updated successfully.',
            $sync,
        );
    }

    public function destroy(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorizeTicketAccess($request->user(), $ticket);

        if ($ticket->status === 'Closed') {
            return redirect('/helpdesk')
                ->with('error', 'Closed tickets cannot be deleted.');
        }

        $ticketId = $ticket->id;
        $requester = $ticket->requester;
        $ticket->delete();

        ActionLog::create([
            'user_id' => $request->user()->id,
            'action_type' => 'delete',
            'item_type' => Ticket::class,
            'item_id' => $ticketId,
            'note' => "Deleted workspace ticket #{$ticketId} from {$requester}",
        ]);

        return redirect('/helpdesk')
            ->with('success', 'Workspace ticket deleted successfully.');
    }

    public function printBatch(Request $request): \Illuminate\Http\Response
    {
        $filters = $this->resolveFilters($request);

        $tickets = $this->buildFilteredQuery($request->user(), $filters)
            ->latest('created_at')
            ->get();

        $fromDate = $filters['from_date'] ? \Carbon\Carbon::parse($filters['from_date'])->format('d M Y') : null;
        $toDate   = $filters['to_date']   ? \Carbon\Carbon::parse($filters['to_date'])->format('d M Y')   : null;

        // Resolve technician profile from SnipeIT
        $remoteUser = $this->getRemoteSnipeItUser($request->user());
        $techCompany  = (string) (data_get($remoteUser, 'company.name')  ?? '—');
        $techLocation = (string) (data_get($remoteUser, 'location.name') ?? '—');

        ActionLog::create([
            'user_id' => $request->user()->id,
            'action_type' => 'print',
            'item_type' => Ticket::class,
            'note' => "Printed workspace batch report (" . $tickets->count() . " tickets)",
        ]);

        return response()->view('helpdesk.print_batch', [
            'tickets'      => $tickets,
            'fromDate'     => $fromDate,
            'toDate'       => $toDate,
            'printedBy'    => $request->user()->name,
            'approvedBy'   => $request->query('approved_by'),
            'technician'   => $filters['technician'],
            'techCompany'  => $techCompany,
            'techLocation' => $techLocation,
            'printedAt'    => now()->format('d M Y, H:i'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->resolveFilters($request);
        $technicianName = $filters['technician'] ?: 'all';
        $dateStamp = now()->format('Y-m-d');
        $filename = $technicianName . '-' . $dateStamp . '.xlsx';

        return response()->streamDownload(function () use ($request, $filters): void {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Helpdesk');

            $headers = [
                'ID', 'Company', 'Location', 'Category', 'Ticket Scope',
                'Priority', 'Requester', 'Department', 'Asset Ref',
                'Maintenance Type', 'Issue Desc', 'Action', 'Note',
                'Technician', 'Status', 'Date Closed', 'Sync Status',
                'Maintenance ID', 'Created At',
            ];

            $sheet->fromArray($headers, null, 'A1');

            // Header row styling
            $lastCol = 'S';
            $headerRange = 'A1:' . $lastCol . '1';
            $sheet->getStyle($headerRange)->applyFromArray([
                'font' => [
                    'bold'  => true,
                    'color' => ['argb' => Color::COLOR_WHITE],
                    'size'  => 10,
                ],
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF1E3A5F'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical'   => Alignment::VERTICAL_CENTER,
                    'wrapText'   => false,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color'       => ['argb' => 'FFCBD5E1'],
                    ],
                ],
            ]);
            $sheet->getRowDimension(1)->setRowHeight(20);
            $sheet->freezePane('A2');

            $rowIndex = 2;

            $this->buildFilteredQuery($request->user(), $filters)
                ->latest('created_at')
                ->chunk(500, function ($tickets) use ($sheet, &$rowIndex): void {
                    foreach ($tickets as $ticket) {
                        $isEven = ($rowIndex % 2 === 0);

                        $sheet->fromArray([
                            $ticket->id,
                            $ticket->company,
                            $ticket->location,
                            $ticket->category,
                            $ticket->ticket_scope,
                            $ticket->priority,
                            $ticket->requester,
                            $ticket->department,
                            $ticket->asset_reference_snapshot,
                            $ticket->maintenance_type,
                            $ticket->issue_description,
                            $ticket->action_taken,
                            $ticket->note,
                            $ticket->technician,
                            $ticket->status,
                            optional($ticket->date_closed)?->toDateString(),
                            $ticket->snipeit_sync_status,
                            $ticket->snipeit_maintenance_id,
                            optional($ticket->created_at)?->toDateTimeString(),
                        ], null, 'A' . $rowIndex);

                        $rowRange = 'A' . $rowIndex . ':S' . $rowIndex;
                        $sheet->getStyle($rowRange)->applyFromArray([
                            'fill' => [
                                'fillType'   => Fill::FILL_SOLID,
                                'startColor' => ['argb' => $isEven ? 'FFF8FAFC' : 'FFFFFFFF'],
                            ],
                            'borders' => [
                                'allBorders' => [
                                    'borderStyle' => Border::BORDER_THIN,
                                    'color'       => ['argb' => 'FFE2E8F0'],
                                ],
                            ],
                            'alignment' => [
                                'vertical' => Alignment::VERTICAL_TOP,
                                'wrapText' => true,
                            ],
                        ]);

                        $rowIndex++;
                    }
                });

            foreach (range('A', 'S') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);

        ActionLog::create([
            'user_id' => $request->user()->id,
            'action_type' => 'export',
            'item_type' => Ticket::class,
            'note' => "Exported workspace tickets to Excel ($filename)",
        ]);
    }

    public function apiIndex(Request $request): JsonResponse
    {
        $filters = $this->resolveFilters($request);

        $tickets = $this->buildFilteredQuery($request->user(), $filters)
            ->latest('created_at')
            ->get()
            ->map(fn (Ticket $ticket) => $this->transformTicket($ticket));

        return response()->json([
            'data' => $tickets,
            'meta' => [
                'total' => $tickets->count(),
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    private function buildFilteredQuery(?User $user, array $filters, ?bool $canViewAll = null): Builder
    {
        // PERF: Accept pre-computed canViewAll to avoid re-calling getRemoteSnipeItUser()
        $canViewAll ??= $this->canViewAllTickets($user);

        return Ticket::query()
            ->with('creator:id,name,snipeit_user_id')
            ->when($user && !$canViewAll, fn (Builder $query) => $query->where('created_by', $user->id))
            ->tap(fn (Builder $query) => $this->applyFiltersToQuery($query, $filters));
    }



    private function applyFiltersToQuery(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $nested) use ($filters): void {
                    $search = '%' . $filters['search'] . '%';

                    $nested
                        ->where('company', 'like', $search)
                        ->orWhere('location', 'like', $search)
                        ->orWhere('category', 'like', $search)
                        ->orWhere('priority', 'like', $search)
                        ->orWhere('requester', 'like', $search)
                        ->orWhere('department', 'like', $search)
                        ->orWhere('asset_reference_snapshot', 'like', $search)
                        ->orWhere('maintenance_type', 'like', $search)
                        ->orWhere('issue_description', 'like', $search)
                        ->orWhere('action_taken', 'like', $search)
                        ->orWhere('note', 'like', $search)
                        ->orWhere('technician', 'like', $search)
                        ->orWhere('status', 'like', $search);
                });
            })
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($filters['priority'] !== '', fn (Builder $query) => $query->where('priority', $filters['priority']))
            ->when($filters['category'] !== '', fn (Builder $query) => $query->where('category', $filters['category']))
            ->when($filters['from_date'], fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['from_date']))
            ->when($filters['to_date'], fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['to_date']))
            ->when($filters['technician'] !== '', fn (Builder $query) => $query->where('technician', 'like', '%' . $filters['technician'] . '%'));
    }

    private function buildPayload(StoreTicketRequest $request, array $extra = []): array
    {
        $validated = $request->validated();

        if (($validated['status'] ?? null) === 'Closed' && empty($validated['date_closed'])) {
            $validated['date_closed'] = now()->toDateString();
        }

        if (($validated['status'] ?? null) !== 'Closed') {
            $validated['date_closed'] = null;
        }

        return array_merge($validated, $extra);
    }

    private function resolveFilters(Request $request): array
    {
        $search = trim((string) $request->string('search'));
        $status = trim((string) $request->string('status'));
        $priority = trim((string) $request->string('priority'));
        $category = trim((string) $request->string('category'));
        $fromDate = $this->normalizeDateFilter($request->string('from_date')->value());
        $toDate = $this->normalizeDateFilter($request->string('to_date')->value());
        $technician = trim((string) $request->string('technician'));

        if ($fromDate && $toDate && $fromDate > $toDate) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        return [
            'search' => $search,
            'status' => in_array($status, self::STATUS_OPTIONS, true) ? $status : '',
            'priority' => in_array($priority, self::PRIORITY_OPTIONS, true) ? $priority : '',
            'category' => $category,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'technician' => $technician,
        ];
    }

    private function transformTicket(Ticket $ticket): array
    {
        $remoteUser = $ticket->creator ? $this->getRemoteSnipeItUser($ticket->creator) : null;
        
        return [
            'id' => $ticket->id,
            'company' => $ticket->company,
            'location' => $ticket->location,
            'category' => $ticket->category,
            'ticket_scope' => $ticket->ticket_scope,
            'priority' => $ticket->priority,
            'requester' => $ticket->requester,
            'department' => $ticket->department,
            'snipeit_asset_id' => $ticket->snipeit_asset_id,
            'asset_reference_snapshot' => $ticket->asset_reference_snapshot,
            'maintenance_type' => $ticket->maintenance_type,
            'issue_description' => $ticket->issue_description,
            'action_taken' => $ticket->action_taken,
            'note' => $ticket->note,
            'technician' => $ticket->technician,
            'vendor_id' => $ticket->vendor_id,
            'vendor' => $ticket->vendor ? [
                'id' => $ticket->vendor->id,
                'name' => $ticket->vendor->name,
            ] : null,
            'status' => $ticket->status,
            'date_closed' => optional($ticket->date_closed)?->toDateString(),
            'snipeit_maintenance_id' => $ticket->snipeit_maintenance_id,
            'snipeit_sync_status' => $ticket->snipeit_sync_status,
            'snipeit_sync_message' => $ticket->snipeit_sync_message,
            'created_at' => optional($ticket->created_at)?->toIso8601String(),
            'creator' => $ticket->creator ? [
                'id' => $ticket->creator->id,
                'name' => $ticket->creator->name,
                'snipeit_user_id' => $ticket->creator->snipeit_user_id,
            ] : null,
            'tech_company' => (string) (data_get($remoteUser, 'company.name') ?? '—'),
            'tech_location' => (string) (data_get($remoteUser, 'location.name') ?? '—'),
        ];
    }

    private function authorizeTicketAccess(User $user, Ticket $ticket): void
    {
        if ($this->canViewAllTickets($user) || $ticket->created_by === $user->id) {
            return;
        }

        abort(403);
    }

    private function canViewAllTickets(?User $user): bool
    {
        if (!$user) {
            return true;
        }

        if ($user->is_admin) {
            return true;
        }

        // PERF: Memoize per-request to avoid calling getRemoteSnipeItUser() multiple times
        $key = $user->id ?? 0;
        if (array_key_exists($key, $this->canViewAllCache)) {
            return $this->canViewAllCache[$key];
        }

        $remoteUser = $this->getRemoteSnipeItUser($user);

        return $this->canViewAllCache[$key] = (
            $this->isTruthy(data_get($remoteUser, 'permissions.superuser'))
            || $this->isTruthy(data_get($remoteUser, 'permissions.superuser.value'))
            || $this->isTruthy(data_get($remoteUser, 'superuser'))
            || $this->isTruthy(data_get($remoteUser, 'is_superuser'))
        );
    }

    /**
     * PERF: Pre-warm the Snipe-IT user cache for a collection of tickets
     * using requestPool() in parallel instead of N sequential getUser() calls.
     */
    private function prewarmCreatorCache(mixed $tickets): void
    {
        // Collect unique snipeit_user_ids that are NOT yet in cache
        $uncachedIds = collect($tickets)
            ->pluck('creator.snipeit_user_id')
            ->filter()
            ->unique()
            ->filter(fn ($id) => !Cache::has(self::SNIPEIT_PROFILE_CACHE_KEY . $id)
                                && !Cache::has(self::SNIPEIT_SUPERADMIN_CACHE_KEY . $id))
            ->values()
            ->all();

        if (empty($uncachedIds)) {
            return;
        }

        // Fetch all in one parallel pool call
        $poolRequests = [];
        foreach ($uncachedIds as $uid) {
            $poolRequests["user_{$uid}"] = ["users/{$uid}", []];
        }

        $poolResults = $this->snipeItService->requestPool($poolRequests);

        foreach ($uncachedIds as $uid) {
            $userData = $poolResults["user_{$uid}"] ?? null;
            if (is_array($userData)) {
                Cache::put(self::SNIPEIT_PROFILE_CACHE_KEY . $uid, $userData, now()->addMinutes(10));
            }
        }
    }

    private function resolveInitialValues(User $user): array
    {
        return [
            'company' => '',
            'location' => '',
            'category' => '',
            'ticket_scope' => 'general',
            'priority' => 'Medium',
            'requester' => '',
            'department' => '',
            'snipeit_asset_id' => null,
            'asset_reference_snapshot' => '',
            'maintenance_type' => 'Pemeliharaan',
            'issue_description' => '',
            'action_taken' => '',
            'note' => '',
            'technician' => (string) $user->name,
            'vendor_id' => null,
            'status' => 'Closed',
            'date_closed' => now()->toDateString(),
            'snipeit_maintenance_id' => null,
            'snipeit_sync_status' => null,
            'snipeit_sync_message' => null,
        ];
    }

    private function resolveCategoryOptions(): array
    {
        // PERF: Cache for 15 minutes — category list is stable and queried on every index page load
        return Cache::remember('helpdesk_category_options', 900, fn () =>
            Ticket::query()
                ->whereNotNull('category')
                ->where('category', '!=', '')
                ->distinct()
                ->orderBy('category')
                ->pluck('category')
                ->map(fn (string $category) => trim($category))
                ->filter(fn (string $category) => $category !== '')
                ->values()
                ->all()
        );
    }

    private function resolveTechnicianOptions(): array
    {
        // PERF: Cache for 15 minutes — technician list is stable and queried on every index page load
        return Cache::remember('helpdesk_technician_options', 900, fn () =>
            Ticket::query()
                ->whereNotNull('technician')
                ->where('technician', '!=', '')
                ->distinct()
                ->orderBy('technician')
                ->pluck('technician')
                ->map(fn (string $tech) => trim($tech))
                ->filter(fn (string $tech) => $tech !== '')
                ->values()
                ->all()
        );
    }

    private function synchronizeTicketMaintenance(Ticket $ticket): array
    {
        if (! $this->requiresSnipeItSync($ticket)) {
            if ($ticket->snipeit_maintenance_id || $ticket->snipeit_sync_status || $ticket->snipeit_sync_message) {
                $ticket->forceFill([
                    'snipeit_asset_id' => null,
                    'asset_reference_snapshot' => null,
                    'snipeit_maintenance_id' => null,
                    'snipeit_sync_status' => null,
                    'snipeit_sync_message' => null,
                ])->saveQuietly();
            }

            return [
                'state' => 'not_requested',
                'message' => null,
            ];
        }

        if (! $ticket->snipeit_asset_id) {
            return $this->markSyncFailed($ticket, 'Asset-related tickets must have a Snipe-IT asset selected.');
        }

        $asset = $this->snipeItService->getHardware((int) $ticket->snipeit_asset_id);

        if (! is_array($asset) || (int) ($asset['id'] ?? 0) <= 0) {
            return $this->markSyncFailed($ticket, 'Snipe-IT asset not found or inaccessible.');
        }

        $assetReference = $this->resolveAssetReferenceSnapshot($asset, $ticket->asset_reference_snapshot);
        $supplierId = (int) data_get($asset, 'supplier.id', 0);

        $ticket->forceFill([
            'asset_reference_snapshot' => $assetReference,
        ])->saveQuietly();

        if ($supplierId <= 0) {
            $supplierId = (int) config('services.snipeit.fallback_supplier_id', 0);
        }

        if ($supplierId <= 0) {
            return $this->markSyncFailed($ticket, 'Snipe-IT asset has no supplier and no fallback supplier found. Maintenance cannot be created.');
        }

        $maintenanceLabelMap = [
            'Pemeliharaan' => 'Maintenance',
            'Perbaikan' => 'Repair',
            'Uji PAT' => 'PAT Test',
            'Pembaruan' => 'Upgrade',
            'Dukungan Perangkat Keras' => 'Hardware Support',
            'Dukungan Perangkat Lunak' => 'Software Support',
        ];

        $maintenanceType = $this->valueToString($ticket->maintenance_type, 'Maintenance');
        $maintenanceType = $maintenanceLabelMap[$maintenanceType] ?? $maintenanceType;

        $payload = [
            'name' => $this->buildMaintenanceName($ticket),
            'asset_id' => (int) $ticket->snipeit_asset_id,
            'supplier_id' => $supplierId,
            'asset_maintenance_type' => $maintenanceType,
            'start_date' => optional($ticket->created_at)->toDateString() ?? now()->toDateString(),
            'completion_date' => $ticket->status === 'Closed'
                ? (optional($ticket->date_closed)->toDateString() ?? now()->toDateString())
                : null,
            'notes' => $this->buildMaintenanceNotes($ticket),
        ];

        $response = $ticket->snipeit_maintenance_id
            ? $this->snipeItService->updateRecord('maintenances', (int) $ticket->snipeit_maintenance_id, $payload)
            : $this->snipeItService->createRecord('maintenances', $payload);

        if (($response['status'] ?? 'error') !== 'success') {
            return $this->markSyncFailed($ticket, $this->extractApiMessage($response));
        }

        $maintenanceId = (int) (data_get($response, 'payload.id') ?? data_get($response, 'id') ?? $ticket->snipeit_maintenance_id ?? 0);

        $ticket->forceFill([
            'snipeit_maintenance_id' => $maintenanceId > 0 ? $maintenanceId : $ticket->snipeit_maintenance_id,
            'snipeit_sync_status' => 'synced',
            'snipeit_sync_message' => 'Snipe-IT maintenance synchronized on ' . now()->format('Y-m-d H:i'),
        ])->saveQuietly();

        return [
            'state' => 'synced',
            'message' => 'Snipe-IT maintenance synchronized successfully.',
        ];
    }

    private function markSyncFailed(Ticket $ticket, string $message): array
    {
        Log::warning('Helpdesk Snipe-IT maintenance sync failed', [
            'ticket_id' => $ticket->id,
            'snipeit_asset_id' => $ticket->snipeit_asset_id,
            'snipeit_maintenance_id' => $ticket->snipeit_maintenance_id,
            'message' => $message,
        ]);

        $ticket->forceFill([
            'snipeit_sync_status' => 'failed',
            'snipeit_sync_message' => $message,
        ])->saveQuietly();

        return [
            'state' => 'failed',
            'message' => $message,
        ];
    }

    private function redirectWithSyncMessage(string $route, string $successMessage, array $sync): RedirectResponse
    {
        $redirect = redirect()->route($route);

        if (($sync['state'] ?? null) === 'failed') {
            return $redirect->with('error', $successMessage . ' However, Snipe-IT maintenance sync failed: ' . $sync['message']);
        }

        if (($sync['state'] ?? null) === 'synced') {
            return $redirect->with('success', $successMessage . ' ' . $sync['message']);
        }

        return $redirect->with('success', $successMessage);
    }

    private function buildMaintenanceName(Ticket $ticket): string
    {
        return Str::limit(
            'Helpdesk #' . $ticket->id . ' - ' . $this->valueToString($ticket->category, 'Maintenance'),
            120,
            '',
        );
    }

    private function buildMaintenanceNotes(Ticket $ticket): string
    {
        return implode("\n", array_filter([
            'Ticket Scope: ' . $this->valueToString($ticket->ticket_scope, '-'),
            'Requester: ' . $this->valueToString($ticket->requester, '-'),
            'Department: ' . $this->valueToString($ticket->department, '-'),
            'Company: ' . $this->valueToString($ticket->company, '-'),
            'Location: ' . $this->valueToString($ticket->location, '-'),
            'Technician: ' . $this->valueToString($ticket->technician, '-'),
            'Priority: ' . $this->valueToString($ticket->priority, '-'),
            'Maintenance Type: ' . $this->valueToString($ticket->maintenance_type, '-'),
            'Status: ' . $this->valueToString($ticket->status, '-'),
            'Asset Ref: ' . $this->valueToString($ticket->asset_reference_snapshot, '-'),
            'Issue: ' . $this->valueToString($ticket->issue_description, '-'),
            'Action: ' . $this->valueToString($ticket->action_taken, '-'),
            $ticket->note ? 'Note: ' . $ticket->note : null,
        ]));
    }

    private function requiresSnipeItSync(Ticket $ticket): bool
    {
        return $ticket->ticket_scope === 'asset';
    }

    private function resolveAssetReferenceSnapshot(array $asset, ?string $fallback = null): string
    {
        return $this->valueToString(
            data_get($asset, 'asset_tag')
                ?? data_get($asset, 'serial')
                ?? data_get($asset, 'name'),
            $this->valueToString($fallback, 'Asset #' . (int) ($asset['id'] ?? 0)),
        );
    }

    private function extractApiMessage(array $response): string
    {
        $messages = $response['messages'] ?? null;

        if (is_string($messages) && trim($messages) !== '') {
            return trim($messages);
        }

        if (is_array($messages)) {
            $flattened = collect($messages)
                ->flatten()
                ->map(fn (mixed $item) => trim((string) $item))
                ->filter()
                ->implode(' ');

            if ($flattened !== '') {
                return $flattened;
            }
        }

        return 'Unknown API error.';
    }

    private function getRemoteSnipeItUser(User $user): ?array
    {
        if (! $user->snipeit_user_id) {
            return null;
        }

        $legacyCacheKey = self::SNIPEIT_SUPERADMIN_CACHE_KEY . $user->snipeit_user_id;

        if (Cache::has($legacyCacheKey)) {
            $cachedUser = Cache::get($legacyCacheKey);

            return is_array($cachedUser) ? $cachedUser : null;
        }

        return Cache::remember(
            self::SNIPEIT_PROFILE_CACHE_KEY . $user->snipeit_user_id,
            now()->addMinutes(10),
            fn () => $this->snipeItService->getUser((int) $user->snipeit_user_id),
        );
    }

    private function isTruthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'y', 'on'], true);
        }

        return false;
    }

    private function normalizeDateFilter(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }

    private function valueToString(mixed $value, string $fallback = ''): string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : $fallback;
    }
}