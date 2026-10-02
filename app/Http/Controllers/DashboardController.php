<?php

namespace App\Http\Controllers;

use App\Models\AuthLog;
use App\Models\AssetStockHistory;
use App\Models\Inspection;
use App\Models\Peminjaman;
use App\Models\Stb;
use App\Models\Ticket;
use App\Services\SnipeItService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly SnipeItService $snipe)
    {
    }

    public function __invoke(Request $request): Response
    {
        $now = CarbonImmutable::now();

        // Resolve date range from request or fall back to default 6-month window
        $defaultFrom = $now->subMonths(5)->startOfMonth();
        $defaultTo   = $now->endOfDay();

        $rawFrom = $request->input('date_from');
        $rawTo   = $request->input('date_to');

        $windowStart = $rawFrom ? CarbonImmutable::parse($rawFrom)->startOfDay() : $defaultFrom;
        $windowEnd   = $rawTo   ? CarbonImmutable::parse($rawTo)->endOfDay()     : $defaultTo;

        // Safeguard: ensure from <= to
        if ($windowStart->gt($windowEnd)) {
            [$windowStart, $windowEnd] = [$windowEnd, $windowStart];
        }

        $isFiltered = $rawFrom !== null || $rawTo !== null;

        // Implement Caching for Snipe-IT Data (15 minutes)
        // OPTIMIZED: Only load count for summary, not full data
        $cacheTtl = 900; // 15 menit

        $hardwareCount = Cache::remember('dashboard_hardware_count', $cacheTtl, function() {
            return count($this->snipe->fetchRows('hardware'));
        });
        
        $licensesCount = Cache::remember('dashboard_licenses_count', $cacheTtl, function() {
            return count($this->snipe->fetchRows('licenses'));
        });
        
        $accessoriesCount = Cache::remember('dashboard_accessories_count', $cacheTtl, function() {
            return count($this->snipe->fetchRows('accessories'));
        });
        
        $componentsCount = Cache::remember('dashboard_components_count', $cacheTtl, function() {
            return count($this->snipe->fetchRows('components'));
        });

        // Only load full data when needed for specific calculations
        $hardware = Cache::remember('dashboard_hardware', $cacheTtl, fn() => collect($this->snipe->fetchRows('hardware')));
        $licenses = Cache::remember('dashboard_licenses', $cacheTtl, fn() => collect($this->snipe->fetchRows('licenses')));
        $accessories = Cache::remember('dashboard_accessories', $cacheTtl, fn() => collect($this->snipe->fetchRows('accessories')));
        $components = Cache::remember('dashboard_components', $cacheTtl, fn() => collect($this->snipe->fetchRows('components')));
        $consumables = Cache::remember('dashboard_consumables', $cacheTtl, fn() => collect($this->snipe->fetchRows('consumables')));

        $consumableSnapshots = $consumables
            ->map(fn (array $item) => $this->mapConsumableSnapshot($item))
            ->filter(fn (array $item) => $item['id'] > 0)
            ->values();
        $consumableLookup = $consumableSnapshots->keyBy('id');
        $hardwareAssigned = $hardware
            ->filter(fn (array $item) => (int) data_get($item, 'assigned_to.id', 0) > 0)
            ->count();
        $hardwareReady = $hardware
            ->filter(function (array $item): bool {
                $status = strtolower(trim((string) data_get($item, 'status_label.name', '')));

                return in_array($status, ['ready to deploy', 'available', 'deployable'], true);
            })
            ->count();
        $licenseSeatsUsed = $licenses
            ->sum(function (array $item): int {
                $totalSeats = (int) ($item['seats'] ?? 0);
                $freeSeats = (int) ($item['free_seats_count'] ?? $item['free_seats'] ?? 0);

                return max($totalSeats - $freeSeats, 0);
            });
        $licenseSeatsTotal = $licenses->sum(fn (array $item): int => (int) ($item['seats'] ?? 0));
        $criticalConsumables = $consumableSnapshots
            ->whereIn('status', ['low', 'empty'])
            ->count();
        $hardwareStatusBreakdown = $hardware
            ->map(function (array $item): string {
                $status = trim((string) data_get($item, 'status_label.name', ''));

                return $status !== '' ? $status : 'Tanpa Status';
            })
            ->countBy()
            ->sortDesc()
            ->take(6)
            ->map(fn (int $count, string $label): array => [
                'label' => $label,
                'count' => $count,
                'share' => $hardware->count() > 0 ? (int) round(($count / $hardware->count()) * 100) : 0,
                'href' => route('asset.index', ['type' => 'assets']),
            ])
            ->values()
            ->all();

        $stockHistoryRows = Cache::remember('dashboard_stock_history', 600, function() use ($windowStart, $windowEnd) {
            return AssetStockHistory::query()
                ->where('asset_type', 'consumable')
                ->whereBetween('purchase_date', [$windowStart->toDateString(), $windowEnd->toDateString()])
                ->latest('purchase_date')
                ->latest('id')
                ->limit(6)
                ->get();
        });

        $stockTrendRows = AssetStockHistory::query()
            ->where('asset_type', 'consumable')
            ->whereBetween('purchase_date', [$windowStart->toDateString(), $windowEnd->toDateString()])
            ->orderBy('purchase_date')
            ->get(['purchase_date', 'qty']);

        $restockLeaders = AssetStockHistory::query()
            ->select([
                'asset_id',
                DB::raw('SUM(qty) as total_qty'),
                DB::raw('COUNT(*) as total_transactions'),
                DB::raw('MAX(purchase_date) as latest_purchase_date'),
            ])
            ->where('asset_type', 'consumable')
            ->whereBetween('purchase_date', [$windowStart->toDateString(), $windowEnd->toDateString()])
            ->groupBy('asset_id')
            ->orderByDesc('total_qty')
            ->orderByDesc('total_transactions')
            ->limit(5)
            ->get();

        $stbStats = DB::table('stbs')
            ->selectRaw("
                COUNT(id) as total,
                COUNT(CASE WHEN cancelled_at IS NULL AND (is_completed = 0 OR is_completed IS NULL) THEN 1 END) as waiting_approval,
                COUNT(CASE WHEN cancelled_at IS NULL AND is_completed = 1 AND movement_type = 'out' THEN 1 END) as stb_out_complete,
                COUNT(CASE WHEN cancelled_at IS NULL AND is_completed = 1 AND movement_type = 'return' THEN 1 END) as stb_in_complete
            ")->first();

        $peminjamanStats = DB::table('peminjamans')
            ->selectRaw("
                COUNT(id) as total,
                COUNT(CASE WHEN cancelled_at IS NULL AND (is_completed = 0 OR is_completed IS NULL) THEN 1 END) as waiting_approval,
                COUNT(CASE WHEN cancelled_at IS NULL AND is_completed = 1 AND movement_type = 'out' AND returned_at IS NULL THEN 1 END) as active_loan,
                COUNT(CASE WHEN cancelled_at IS NULL AND ((movement_type = 'return' AND is_completed = 1) OR returned_at IS NOT NULL) THEN 1 END) as return_complete
            ")->first();

        $inspectionStats = DB::table('inspections')
            ->selectRaw("
                COUNT(id) as total,
                COUNT(CASE WHEN signature_date IS NULL THEN 1 END) as waiting_approval,
                COUNT(CASE WHEN signature_date IS NOT NULL AND (device_category NOT IN ('internal_component', 'external_component') OR device_category IS NULL) THEN 1 END) as inspection_asset,
                COUNT(CASE WHEN signature_date IS NOT NULL AND device_category IN ('internal_component', 'external_component') THEN 1 END) as inspection_component
            ")->first();

        $totalStb          = (int) ($stbStats->total ?? 0);
        $totalPeminjaman   = (int) ($peminjamanStats->total ?? 0);
        $totalInspections  = (int) ($inspectionStats->total ?? 0);
        $totalTickets      = Ticket::count();
        $pendingTickets    = Ticket::whereIn('status', ['Open', 'In Progress'])->count();

        $pendingApprovalDocuments = (int) ($stbStats->waiting_approval ?? 0)
            + (int) ($peminjamanStats->waiting_approval ?? 0)
            + (int) ($inspectionStats->waiting_approval ?? 0);

        $stbTrend = $this->buildMonthlyTrend(
            Stb::query()
                ->whereBetween('created_at', [$windowStart, $windowEnd])
                ->pluck('created_at'),
            $windowStart,
            $windowEnd,
        );

        $peminjamanTrend = $this->buildMonthlyTrend(
            Peminjaman::query()
                ->whereBetween('created_at', [$windowStart, $windowEnd])
                ->pluck('created_at'),
            $windowStart,
            $windowEnd,
        );

        $inspectionTrend = $this->buildMonthlyTrend(
            Inspection::query()
                ->whereBetween('created_at', [$windowStart, $windowEnd])
                ->pluck('created_at'),
            $windowStart,
            $windowEnd,
        );

        $ticketTrend = $this->buildMonthlyTrend(
            Ticket::query()
                ->whereBetween('created_at', [$windowStart, $windowEnd])
                ->pluck('created_at'),
            $windowStart,
            $windowEnd,
        );

        $trend = $stbTrend->map(function (array $item, string $monthKey) use ($inspectionTrend, $peminjamanTrend, $ticketTrend): array {
            return [
                'label' => $item['label'],
                'stb' => $item['count'],
                'peminjaman' => $peminjamanTrend[$monthKey]['count'] ?? 0,
                'inspections' => $inspectionTrend[$monthKey]['count'] ?? 0,
                'tickets' => $ticketTrend[$monthKey]['count'] ?? 0,
            ];
        })->values();

        $stockTrend = $this->buildMonthlyQuantityTrend($stockTrendRows, $windowStart, $windowEnd)->values();

        return Inertia::render('Dashboard', [
            'summary' => [
                'totalAssets' => $hardware->count() + $licenses->count() + $accessories->count() + $components->count() + $consumableSnapshots->count(),
                'totalStb' => $totalStb,
                'totalPeminjaman' => $totalPeminjaman,
                'totalInspections' => $totalInspections,
                'totalTickets' => $totalTickets,
            ],
            'stats' => [
                'activeTickets' => $pendingTickets,
                'pendingApprovals' => $pendingApprovalDocuments,
                'lowStockItems' => $criticalConsumables,
                'resolvedToday' => Ticket::where('status', 'Closed')->whereDate('date_closed', today())->count(),
                'activeUsersToday' => AuthLog::whereDate('created_at', today())->distinct('user_id')->count(),
                'hardwareReady' => $hardwareReady,
            ],
            'assetHighlights' => [
                [
                    'label' => 'Hardware Terpakai',
                    'value' => $hardwareAssigned,
                    'detail' => 'asset hardware sedang ter-assign ke user',
                    'tone' => 'emerald',
                ],
                [
                    'label' => 'Hardware Siap',
                    'value' => $hardwareReady,
                    'detail' => 'hardware dengan status siap deploy atau available',
                    'tone' => 'sky',
                ],
                [
                    'label' => 'Seats License Terpakai',
                    'value' => $licenseSeatsUsed,
                    'detail' => sprintf('%d dari %d seat sudah digunakan', $licenseSeatsUsed, $licenseSeatsTotal),
                    'tone' => 'rose',
                ],
                [
                    'label' => 'Consumable Kritis',
                    'value' => $criticalConsumables,
                    'detail' => 'item consumable yang mulai habis atau sudah kosong',
                    'tone' => 'amber',
                ],
            ],
            'approvals' => [
                'pending' => $pendingApprovalDocuments,
                'approved' => (int) ($stbStats->stb_out_complete ?? 0) + (int) ($peminjamanStats->active_loan ?? 0),
                'finalized' => (int) ($stbStats->stb_in_complete ?? 0) + (int) ($peminjamanStats->return_complete ?? 0),
                'cancelled' => 0,
            ],
            'moduleApprovals' => [
                [
                    'key'         => 'stb',
                    'label'       => 'STB',
                    'title'       => 'DOKUMEN STB',
                    'total'       => $totalStb,
                    'href'        => route('stb.index', ['tab' => 'pending']),
                    'stages'      => [
                        [
                            'label'    => 'Menunggu',
                            'sublabel' => 'Perlu Ditindaklanjuti',
                            'count'    => (int) ($stbStats->waiting_approval ?? 0),
                            'tone'     => 'amber',
                        ],
                        [
                            'label'    => 'Penyerahan Selesai',
                            'sublabel' => 'Penyerahan Selesai',
                            'count'    => (int) ($stbStats->stb_out_complete ?? 0),
                            'tone'     => 'sky',
                        ],
                        [
                            'label'    => 'Pengembalian Selesai',
                            'sublabel' => 'Pengembalian Selesai',
                            'count'    => (int) ($stbStats->stb_in_complete ?? 0),
                            'tone'     => 'emerald',
                        ],
                    ],
                ],
                [
                    'key'         => 'peminjaman',
                    'label'       => 'Peminjaman',
                    'title'       => 'DOKUMEN PEMINJAMAN',
                    'total'       => $totalPeminjaman,
                    'href'        => route('peminjaman.index', ['tab' => 'pending']),
                    'stages'      => [
                        [
                            'label'    => 'Menunggu',
                            'sublabel' => 'Perlu Ditindaklanjuti',
                            'count'    => (int) ($peminjamanStats->waiting_approval ?? 0),
                            'tone'     => 'amber',
                        ],
                        [
                            'label'    => 'Sedang Dipinjam',
                            'sublabel' => 'Belum Dikembalikan',
                            'count'    => (int) ($peminjamanStats->active_loan ?? 0),
                            'tone'     => 'sky',
                        ],
                        [
                            'label'    => 'Sudah Dikembalikan',
                            'sublabel' => 'Sudah Dikembalikan',
                            'count'    => (int) ($peminjamanStats->return_complete ?? 0),
                            'tone'     => 'emerald',
                        ],
                    ],
                ],
                [
                    'key'         => 'inspection',
                    'label'       => 'Inspeksi',
                    'title'       => 'DOKUMEN INSPEKSI',
                    'total'       => $totalInspections,
                    'href'        => route('inspection.index'),
                    'stages'      => [
                        [
                            'label'    => 'Menunggu',
                            'sublabel' => 'Menunggu Review',
                            'count'    => (int) ($inspectionStats->waiting_approval ?? 0),
                            'tone'     => 'amber',
                        ],
                        [
                            'label'    => 'Inspeksi Aset',
                            'sublabel' => 'Perangkat',
                            'count'    => (int) ($inspectionStats->inspection_asset ?? 0),
                            'tone'     => 'sky',
                        ],
                        [
                            'label'    => 'Inspeksi Komponen',
                            'sublabel' => 'Komponen Internal',
                            'count'    => (int) ($inspectionStats->inspection_component ?? 0),
                            'tone'     => 'purple',
                        ],
                    ],
                ],
            ],
            'queues' => [
                'pendingStb' => (int) ($stbStats->waiting_approval ?? 0),
                'pendingPeminjaman' => (int) ($peminjamanStats->waiting_approval ?? 0),
                'pendingInspection' => (int) ($inspectionStats->waiting_approval ?? 0),
                'approvedNotFinal' => (int) ($peminjamanStats->active_loan ?? 0),
            ],
            'assetBreakdown' => [
                [
                    'label' => 'Hardware',
                    'count' => $hardware->count(),
                    'href' => '/asset?type=assets',
                    'tone' => 'emerald',
                ],
                [
                    'label' => 'Consumable',
                    'count' => $consumableSnapshots->count(),
                    'href' => '/asset?type=consumable',
                    'tone' => 'amber',
                ],
                [
                    'label' => 'Accessories',
                    'count' => $accessories->count(),
                    'href' => '/asset?type=accessories',
                    'tone' => 'sky',
                ],
                [
                    'label' => 'Components',
                    'count' => $components->count(),
                    'href' => '/asset?type=component',
                    'tone' => 'slate',
                ],
                [
                    'label' => 'License',
                    'count' => $licenses->count(),
                    'href' => '/asset?type=license',
                    'tone' => 'rose',
                ],
            ],
            'hardwareStatuses' => $hardwareStatusBreakdown,
            'consumables' => [
                'totalItems' => $consumableSnapshots->count(),
                'availableItems' => $consumableSnapshots->where('status', 'available')->count(),
                'lowStockItems' => $consumableSnapshots->where('status', 'low')->count(),
                'outOfStockItems' => $consumableSnapshots->where('status', 'empty')->count(),
                'totalUnitsRemaining' => $consumableSnapshots->sum('remaining'),
                'focusItems' => $consumableSnapshots
                    ->filter(fn (array $item) => in_array($item['status'], ['low', 'empty'], true))
                    ->sortBy([
                        ['remaining', 'asc'],
                        ['name', 'asc'],
                    ])
                    ->take(5)
                    ->pipe(function ($focusItems) {
                        // PERF: Pre-fetch all weekly usage in one query instead of N separate queries in a loop
                        $focusIds = $focusItems->pluck('id')->all();
                        $usageMap = \App\Models\StbItem::query()
                            ->join('stbs', 'stb_items.stb_id', '=', 'stbs.id')
                            ->whereIn('stb_items.snipeit_asset_id', $focusIds)
                            ->where('stb_items.kategori', 'consumable')
                            ->where('stbs.deliver_date', '>=', now()->subDays(90))
                            ->groupBy('stb_items.snipeit_asset_id')
                            ->selectRaw('stb_items.snipeit_asset_id, SUM(jumlah) as total_qty')
                            ->pluck('total_qty', 'snipeit_asset_id')
                            ->toArray();

                        return $focusItems->map(function (array $item) use ($usageMap) {
                            $totalQty     = (int) ($usageMap[$item['id']] ?? 0);
                            $weeklyUsage  = $totalQty / 12.8;
                            $daysRemaining = $weeklyUsage > 0
                                ? (int) round(($item['remaining'] / $weeklyUsage) * 7)
                                : null;

                            return [
                                'id'          => $item['id'],
                                'name'        => $item['name'],
                                'remaining'   => $item['remaining'],
                                'minimum'     => $item['minimum'],
                                'location'    => $item['location'],
                                'status'      => $item['status'],
                                'statusLabel' => $item['statusLabel'],
                                'forecast'    => $daysRemaining !== null ? "Habis dlm ±{$daysRemaining} hari" : 'Pemakaian rendah',
                                'href'        => route('asset.show', ['assetId' => $item['id'], 'type' => 'consumable']),
                            ];
                        })->values()->all();
                    }),
            ],
            'stockHistory' => $stockHistoryRows
                ->map(function (AssetStockHistory $item) use ($consumableLookup): array {
                    $consumable = $consumableLookup->get($item->asset_id);

                    return [
                        'id' => $item->id,
                        'assetId' => $item->asset_id,
                        'assetName' => $consumable['name'] ?? ('Consumable #' . $item->asset_id),
                        'qty' => $item->qty,
                        'poNumber' => $item->po_number,
                        'purchaseDate' => optional($item->purchase_date)->format('d M Y'),
                        'createdAt' => optional($item->created_at)->diffForHumans(),
                        'notes' => $item->notes,
                        'href' => route('asset.show', ['assetId' => $item->asset_id, 'type' => 'consumable']),
                    ];
                })
                ->values()
                ->all(),
            'restockLeaders' => $restockLeaders
                ->map(function (object $row) use ($consumableLookup): array {
                    $consumable = $consumableLookup->get((int) $row->asset_id);

                    return [
                        'assetId' => (int) $row->asset_id,
                        'assetName' => $consumable['name'] ?? ('Consumable #' . $row->asset_id),
                        'totalQty' => (int) $row->total_qty,
                        'transactions' => (int) $row->total_transactions,
                        'latestPurchaseDate' => $row->latest_purchase_date
                            ? CarbonImmutable::parse($row->latest_purchase_date)->format('d M Y')
                            : null,
                        'href' => route('asset.show', ['assetId' => (int) $row->asset_id, 'type' => 'consumable']),
                    ];
                })
                ->values()
                ->all(),
            'stockTrend' => $stockTrend,
            'trend' => $trend,
            'recentTickets' => Cache::remember('dashboard_recent_tickets', 300, function() use ($windowStart, $windowEnd) {
                return Ticket::query()
                    ->whereBetween('created_at', [$windowStart, $windowEnd])
                    ->latest()
                    ->limit(5)
                    ->get(['id', 'requester', 'category', 'priority', 'status', 'created_at'])
                    ->map(fn (Ticket $ticket) => [
                        'id' => $ticket->id,
                        'requester' => $ticket->requester,
                        'category' => $ticket->category,
                        'priority' => $ticket->priority,
                        'status' => $ticket->status,
                        'createdAt' => $ticket->created_at->diffForHumans(),
                        'href' => route('helpdesk.show', $ticket->id),
                    ]);
            }),
            'recentActivities' => Cache::remember('dashboard_recent_activities', 300, function() use ($windowStart, $windowEnd) {
                return collect()
                    ->merge(
                        Stb::query()
                            ->select(['id', 'movement_type', 'user_name', 'user_dept', 'location_name', 'created_at'])
                            ->whereBetween('created_at', [$windowStart, $windowEnd])
                            ->latest()
                            ->limit(5)
                            ->get()
                            ->map(fn (Stb $stb) => [
                                'type' => 'document',
                                'label' => $stb->movement_type === 'return' ? 'STB In (Kembali)' : 'STB Out (Penyerahan)',
                                'title' => ($stb->user_name ?? $stb->user_dept ?? 'User') . ' - ' . ($stb->location_name ?? 'STB'),
                                'time' => $stb->created_at->diffForHumans(),
                                'timestamp' => $stb->created_at->timestamp,
                                'tone' => $stb->movement_type === 'return' ? 'sky' : 'emerald',
                                'href' => route('stb.show', $stb->id),
                            ])
                    )
                    ->merge(
                        Peminjaman::query()
                            ->select(['id', 'movement_type', 'user_name', 'user_dept', 'location_name', 'returned_at', 'created_at'])
                            ->whereBetween('created_at', [$windowStart, $windowEnd])
                            ->latest()
                            ->limit(5)
                            ->get()
                            ->map(fn (Peminjaman $p) => [
                                'type' => 'peminjaman',
                                'label' => $p->movement_type === 'return' || $p->returned_at ? 'Pengembalian Pinjaman' : 'Peminjaman Aset',
                                'title' => ($p->user_name ?? $p->user_dept ?? 'User') . ' - ' . ($p->location_name ?? 'Peminjaman'),
                                'time' => $p->created_at->diffForHumans(),
                                'timestamp' => $p->created_at->timestamp,
                                'tone' => 'sky',
                                'href' => route('peminjaman.show', $p->id),
                            ])
                    )
                    ->merge(
                        Inspection::query()
                            ->select(['id', 'device_category', 'device_name', 'location', 'user', 'created_at'])
                            ->whereBetween('created_at', [$windowStart, $windowEnd])
                            ->latest()
                            ->limit(5)
                            ->get()
                            ->map(fn (Inspection $insp) => [
                                'type' => 'inspection',
                                'label' => in_array($insp->device_category, ['internal_component', 'external_component']) ? 'Inspeksi Komponen' : 'Inspeksi Aset',
                                'title' => ($insp->device_name ?? $insp->location ?? 'Aset') . ' - ' . ($insp->user ?? 'Inspeksi'),
                                'time' => $insp->created_at->diffForHumans(),
                                'timestamp' => $insp->created_at->timestamp,
                                'tone' => 'purple',
                                'href' => route('inspection.show', $insp->id),
                            ])
                    )
                    ->sortByDesc('timestamp')
                    ->values()
                    ->take(6);
            }),
            'expiringWarranties' => $hardware
                ->filter(function ($asset) {
                    if (empty($asset['warranty_expires'])) return false;
                    try {
                        $expiry = CarbonImmutable::parse($asset['warranty_expires']);
                        return $expiry->isFuture() && $expiry->diffInDays(now()) <= 30;
                    } catch (\Throwable) {
                        return false;
                    }
                })
                ->take(10)  // LIMIT: hanya ambil 10 teratas
                ->map(fn ($asset) => [
                    'id' => $asset['id'],
                    'name' => $asset['name'] ?? $asset['model']['name'] ?? 'Hardware',
                    'tag' => $asset['asset_tag'] ?? '-',
                    'expiry' => CarbonImmutable::parse($asset['warranty_expires'])->format('d M Y'),
                    'daysLeft' => CarbonImmutable::parse($asset['warranty_expires'])->diffInDays(now()),
                    'href' => route('asset.show', ['assetId' => $asset['id'], 'type' => 'assets']),
                ])
                ->values()
                ->all(),
            'expiringLicenses' => $licenses
                ->filter(function (array $license) use ($now): bool {
                    $expiresAt = $license['expiration_date']
                        ?? data_get($license, 'expiration_date.date')
                        ?? data_get($license, 'expiration_date.formatted');

                    if (empty($expiresAt)) {
                        return false;
                    }

                    try {
                        $expiry = CarbonImmutable::parse($expiresAt);
                    } catch (\Throwable) {
                        return false;
                    }

                    return $expiry->isFuture() && $expiry->diffInDays($now) <= 60;
                })
                ->sortBy(function (array $license) use ($now): int {
                    $expiresAt = $license['expiration_date']
                        ?? data_get($license, 'expiration_date.date')
                        ?? data_get($license, 'expiration_date.formatted');

                    return CarbonImmutable::parse($expiresAt)->diffInDays($now);
                })
                ->take(10)  // LIMIT: hanya ambil 10 teratas
                ->map(function (array $license) use ($now): array {
                    $expiresAt = $license['expiration_date']
                        ?? data_get($license, 'expiration_date.date')
                        ?? data_get($license, 'expiration_date.formatted');
                    $expiry = CarbonImmutable::parse($expiresAt);

                    return [
                        'id' => (int) ($license['id'] ?? 0),
                        'name' => (string) ($license['name'] ?? $license['product_name'] ?? 'Lisensi'),
                        'tag' => (string) ($license['product_key'] ?? $license['serial'] ?? ''),
                        'expiry' => $expiry->format('d M Y'),
                        'daysLeft' => $expiry->diffInDays($now),
                        'href' => route('asset.show', ['assetId' => $license['id'], 'type' => 'license']),
                    ];
                })
                ->values()
                ->all(),
            'generatedAt' => $now->format('d M Y H:i'),
            'dateFilter' => [
                'from'        => $windowStart->format('Y-m-d'),
                'to'          => $windowEnd->format('Y-m-d'),
                'fromLabel'   => $windowStart->translatedFormat('d M Y'),
                'toLabel'     => $windowEnd->translatedFormat('d M Y'),
                'isActive'    => $isFiltered,
                'defaultFrom' => $defaultFrom->format('Y-m-d'),
                'defaultTo'   => $defaultTo->format('Y-m-d'),
            ],
        ]);
    }

    private function mapConsumableSnapshot(array $item): array
    {
        $totalQty = (int) ($item['qty'] ?? 0);
        $remaining = (int) ($item['remaining_qty'] ?? $item['num_remaining'] ?? $item['remaining'] ?? $totalQty);
        $minimum = max((int) ($item['min_amt'] ?? 0), 0);
        $threshold = $minimum > 0 ? $minimum : min(max($totalQty, 1), 5);

        $status = match (true) {
            $remaining <= 0 => 'empty',
            $remaining <= $threshold => 'low',
            default => 'available',
        };

        return [
            'id' => (int) ($item['id'] ?? 0),
            'name' => (string) ($item['name'] ?? 'Consumable'),
            'remaining' => max($remaining, 0),
            'minimum' => $minimum,
            'location' => (string) data_get($item, 'location.name', '-'),
            'status' => $status,
            'statusLabel' => match ($status) {
                'empty' => 'Habis',
                'low' => 'Mulai habis',
                default => 'Tersedia',
            },
        ];
    }

    private function buildMonthlyTrend(Collection $timestamps, CarbonImmutable $windowStart, CarbonImmutable $windowEnd): Collection
    {
        $months = collect();
        $cursor = $windowStart->startOfMonth();
        $endMonth = $windowEnd->startOfMonth();
        $maxMonths = 24;
        $count = 0;

        while ($cursor->lte($endMonth) && $count < $maxMonths) {
            $months->put($cursor->format('Y-m'), [
                'label' => $cursor->translatedFormat('M \'y'),
                'count' => 0,
            ]);
            $cursor = $cursor->addMonth();
            $count++;
        }

        foreach ($timestamps as $timestamp) {
            if ($timestamp === null) {
                continue;
            }

            $date = CarbonImmutable::parse($timestamp);

            if ($date->lt($windowStart) || $date->gt($windowEnd)) {
                continue;
            }

            $monthKey = $date->format('Y-m');

            if (! $months->has($monthKey)) {
                continue;
            }

            $month = $months->get($monthKey);
            $month['count']++;
            $months->put($monthKey, $month);
        }

        return $months;
    }

    private function buildMonthlyQuantityTrend(Collection $rows, CarbonImmutable $windowStart, CarbonImmutable $windowEnd): Collection
    {
        $months = collect();
        $cursor = $windowStart->startOfMonth();
        $endMonth = $windowEnd->startOfMonth();
        $maxMonths = 24;
        $count = 0;

        while ($cursor->lte($endMonth) && $count < $maxMonths) {
            $months->put($cursor->format('Y-m'), [
                'label' => $cursor->translatedFormat('M \'y'),
                'qty' => 0,
                'transactions' => 0,
            ]);
            $cursor = $cursor->addMonth();
            $count++;
        }

        foreach ($rows as $row) {
            $purchaseDate = data_get($row, 'purchase_date');

            if ($purchaseDate === null) {
                continue;
            }

            $date = CarbonImmutable::parse($purchaseDate);

            if ($date->lt($windowStart) || $date->gt($windowEnd)) {
                continue;
            }

            $monthKey = $date->format('Y-m');

            if (! $months->has($monthKey)) {
                continue;
            }

            $month = $months->get($monthKey);
            $month['qty'] += (int) data_get($row, 'qty', 0);
            $month['transactions']++;
            $months->put($monthKey, $month);
        }

        return $months;
    }

    private function completedDocumentsQuery(): Builder
    {
        return Stb::query()->where(function (Builder $query): void {
            $query->whereNotNull('completed_at')
                ->orWhere('is_completed', true);
        });
    }

    private function pendingDocumentsQuery(string $documentType): Builder
    {
        return Stb::query()
            ->where('document_type', $documentType)
            ->whereNull('cancelled_at')
            ->where(function (Builder $query): void {
                $query->whereNull('completed_at')
                    ->orWhere('is_completed', false)
                    ->orWhereNull('is_completed');
            });
    }

    private function moduleDocumentsQuery(string $documentType): Builder
    {
        return Stb::query()
            ->where('document_type', $documentType)
            ->whereNull('cancelled_at');
    }
}
