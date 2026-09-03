<?php

namespace App\Http\Controllers;

use App\Models\AuthLog;
use App\Models\AssetStockHistory;
use App\Models\Inspection;
use App\Models\Stb;
use App\Models\Ticket;
use App\Services\SnipeItService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
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

    public function __invoke(): Response
    {
        $now = CarbonImmutable::now();
        $windowStart = $now->subMonths(5)->startOfMonth();

        // Implement Caching for Snipe-IT Data (15 minutes)
        $cacheTtl = 900;

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

        $stockHistoryRows = AssetStockHistory::query()
            ->where('asset_type', 'consumable')
            ->latest('purchase_date')
            ->latest('id')
            ->limit(6)
            ->get();

        $stockTrendRows = AssetStockHistory::query()
            ->where('asset_type', 'consumable')
            ->where('purchase_date', '>=', $windowStart->toDateString())
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
            ->groupBy('asset_id')
            ->orderByDesc('total_qty')
            ->orderByDesc('total_transactions')
            ->limit(5)
            ->get();

        // PERF: Aggregate all Stb counts in a single DB query instead of 8+ separate count() calls
        $stbStats = DB::table('stbs')
            ->selectRaw("
                COUNT(CASE WHEN document_type = 'handover' AND cancelled_at IS NULL AND it_approved_signed_at IS NOT NULL THEN 1 END) as approved_documents,
                COUNT(CASE WHEN document_type = 'handover' AND cancelled_at IS NULL AND it_approved_signed_at IS NULL THEN 1 END) as pending_approval_documents,
                COUNT(CASE WHEN document_type = 'handover' AND cancelled_at IS NULL AND (is_completed = 1 OR completed_at IS NOT NULL) THEN 1 END) as completed_stb,
                COUNT(CASE WHEN document_type = 'loan'     AND cancelled_at IS NULL AND (is_completed = 1 OR completed_at IS NOT NULL) THEN 1 END) as completed_loan,
                COUNT(cancelled_at) as cancelled_documents,
                COUNT(CASE WHEN document_type = 'handover' THEN 1 END) as total_stb,
                COUNT(CASE WHEN document_type = 'loan'     THEN 1 END) as total_peminjaman,
                COUNT(CASE WHEN document_type = 'handover' AND cancelled_at IS NULL AND is_completed = 0 AND (completed_at IS NULL OR is_completed IS NULL) THEN 1 END) as pending_stb,
                COUNT(CASE WHEN document_type = 'loan'     AND cancelled_at IS NULL AND is_completed = 0 AND (completed_at IS NULL OR is_completed IS NULL) THEN 1 END) as pending_peminjaman,
                COUNT(CASE WHEN document_type = 'handover' AND cancelled_at IS NULL AND it_approved_signed_at IS NULL THEN 1 END) as module_stb_pending,
                COUNT(CASE WHEN document_type = 'handover' AND cancelled_at IS NULL AND it_approved_signed_at IS NOT NULL THEN 1 END) as module_stb_approved,
                COUNT(CASE WHEN document_type = 'loan'     AND cancelled_at IS NULL AND it_approved_signed_at IS NULL THEN 1 END) as module_loan_pending,
                COUNT(CASE WHEN document_type = 'loan'     AND cancelled_at IS NULL AND it_approved_signed_at IS NOT NULL THEN 1 END) as module_loan_approved
            ")->first();

        $completedDocuments        = ($stbStats->completed_stb ?? 0) + ($stbStats->completed_loan ?? 0);
        $cancelledDocuments        = $stbStats->cancelled_documents ?? 0;
        $approvedDocuments         = $stbStats->approved_documents ?? 0;
        $pendingApprovalDocuments  = $stbStats->pending_approval_documents ?? 0;
        $totalStb                  = $stbStats->total_stb ?? 0;
        $totalPeminjaman           = $stbStats->total_peminjaman ?? 0;
        $pendingStb                = $stbStats->pending_stb ?? 0;
        $pendingPeminjaman         = $stbStats->pending_peminjaman ?? 0;

        $totalInspections = Inspection::count();
        $totalTickets     = Ticket::count();
        $pendingTickets   = Ticket::whereIn('status', ['Open', 'In Progress'])->count();

        $stbTrend = $this->buildMonthlyTrend(
            Stb::query()
                ->where('document_type', 'handover')
                ->where('created_at', '>=', $windowStart)
                ->pluck('created_at'),
            $windowStart,
            $now,
        );

        $peminjamanTrend = $this->buildMonthlyTrend(
            Stb::query()
                ->where('document_type', 'loan')
                ->where('created_at', '>=', $windowStart)
                ->pluck('created_at'),
            $windowStart,
            $now,
        );

        $inspectionTrend = $this->buildMonthlyTrend(
            Inspection::query()
                ->where('created_at', '>=', $windowStart)
                ->pluck('created_at'),
            $windowStart,
            $now,
        );

        $ticketTrend = $this->buildMonthlyTrend(
            Ticket::query()
                ->where('created_at', '>=', $windowStart)
                ->pluck('created_at'),
            $windowStart,
            $now,
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

        $stockTrend = $this->buildMonthlyQuantityTrend($stockTrendRows, $windowStart, $now)->values();

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
                'approved' => $approvedDocuments,
                'finalized' => $completedDocuments,
                'cancelled' => $cancelledDocuments,
            ],
            'moduleApprovals' => [
                [
                    'label'     => 'STB',
                    'total'     => $totalStb,
                    'pending'   => $stbStats->module_stb_pending   ?? 0,
                    'approved'  => $stbStats->module_stb_approved  ?? 0,
                    'finalized' => $stbStats->completed_stb        ?? 0,
                    'href'      => route('stb.index', ['tab' => 'pending']),
                ],
                [
                    'label'     => 'Peminjaman',
                    'total'     => $totalPeminjaman,
                    'pending'   => $stbStats->module_loan_pending  ?? 0,
                    'approved'  => $stbStats->module_loan_approved ?? 0,
                    'finalized' => $stbStats->completed_loan       ?? 0,
                    'href'      => route('peminjaman.index', ['tab' => 'pending']),
                ],
            ],
            'queues' => [
                'pendingStb' => $pendingStb,
                'pendingPeminjaman' => $pendingPeminjaman,
                'approvedNotFinal' => max($approvedDocuments - $completedDocuments, 0),
            ],
            'assetBreakdown' => [
                [
                    'label' => 'Hardware',
                    'count' => $hardware->count(),
                    'href' => route('asset.index', ['type' => 'assets']),
                    'tone' => 'emerald',
                ],
                [
                    'label' => 'Consumable',
                    'count' => $consumableSnapshots->count(),
                    'href' => route('asset.index', ['type' => 'consumable']),
                    'tone' => 'amber',
                ],
                [
                    'label' => 'Accessories',
                    'count' => $accessories->count(),
                    'href' => route('asset.index', ['type' => 'accessories']),
                    'tone' => 'sky',
                ],
                [
                    'label' => 'Components',
                    'count' => $components->count(),
                    'href' => route('asset.index', ['type' => 'component']),
                    'tone' => 'slate',
                ],
                [
                    'label' => 'License',
                    'count' => $licenses->count(),
                    'href' => route('asset.index', ['type' => 'license']),
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
            'recentTickets' => Ticket::query()
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn (Ticket $ticket) => [
                    'id' => $ticket->id,
                    'requester' => $ticket->requester,
                    'category' => $ticket->category,
                    'priority' => $ticket->priority,
                    'status' => $ticket->status,
                    'createdAt' => $ticket->created_at->diffForHumans(),
                    'href' => route('helpdesk.show', $ticket->id),
                ]),
            'recentActivities' => collect()
                ->merge(
                    Stb::query()->latest()->limit(5)->get()->map(fn (Stb $stb) => [
                        'type' => 'document',
                        'label' => $stb->document_type === 'handover' ? 'Serah Terima' : 'Peminjaman',
                        'title' => $stb->department . ' - ' . $stb->receiver_name,
                        'time' => $stb->created_at->diffForHumans(),
                        'tone' => $stb->document_type === 'handover' ? 'emerald' : 'sky',
                        'href' => route($stb->document_type === 'handover' ? 'stb.show' : 'peminjaman.show', $stb->id),
                    ])
                )
                ->merge(
                    Inspection::query()->latest()->limit(5)->get()->map(fn (Inspection $insp) => [
                        'type' => 'inspection',
                        'label' => 'Inspection',
                        'title' => $insp->location . ' - ' . $insp->inspector_name,
                        'time' => $insp->created_at->diffForHumans(),
                        'tone' => 'purple',
                        'href' => route('inspection.show', $insp->id),
                    ])
                )
                ->sortByDesc('time')
                ->values()
                ->take(6),
            'expiringWarranties' => $hardware
                ->filter(function ($asset) {
                    if (empty($asset['warranty_expires'])) return false;
                    $expiry = CarbonImmutable::parse($asset['warranty_expires']);
                    return $expiry->isFuture() && $expiry->diffInDays(now()) <= 30;
                })
                ->map(fn ($asset) => [
                    'id' => $asset['id'],
                    'name' => $asset['name'] ?? $asset['model']['name'],
                    'tag' => $asset['asset_tag'],
                    'expiry' => CarbonImmutable::parse($asset['warranty_expires'])->format('d M Y'),
                    'daysLeft' => CarbonImmutable::parse($asset['warranty_expires'])->diffInDays(now()),
                    'href' => route('asset.show', ['assetId' => $asset['id'], 'type' => 'assets']),
                ])
                ->values()
                ->all(),
            'generatedAt' => $now->format('d M Y H:i'),
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

    private function buildMonthlyTrend(Collection $timestamps, CarbonImmutable $windowStart, CarbonImmutable $now): Collection
    {
        $months = collect(range(5, 0))->mapWithKeys(function (int $offset) use ($now): array {
            $date = $now->subMonths($offset)->startOfMonth();

            return [
                $date->format('Y-m') => [
                    'label' => $date->translatedFormat('M'),
                    'count' => 0,
                ],
            ];
        });

        foreach ($timestamps as $timestamp) {
            if ($timestamp === null) {
                continue;
            }

            $date = CarbonImmutable::parse($timestamp);

            if ($date->lt($windowStart)) {
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

    private function buildMonthlyQuantityTrend(Collection $rows, CarbonImmutable $windowStart, CarbonImmutable $now): Collection
    {
        $months = collect(range(5, 0))->mapWithKeys(function (int $offset) use ($now): array {
            $date = $now->subMonths($offset)->startOfMonth();

            return [
                $date->format('Y-m') => [
                    'label' => $date->translatedFormat('M'),
                    'qty' => 0,
                    'transactions' => 0,
                ],
            ];
        });

        foreach ($rows as $row) {
            $purchaseDate = data_get($row, 'purchase_date');

            if ($purchaseDate === null) {
                continue;
            }

            $date = CarbonImmutable::parse($purchaseDate);

            if ($date->lt($windowStart)) {
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
