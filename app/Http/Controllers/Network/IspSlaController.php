<?php

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Models\IspDownHistory;
use App\Models\IspSlaContract;
use App\Models\IspSlaMonthly;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IspSlaController extends Controller
{
    /**
     * GET /isp-sla/data?from=2026-01-01&to=2026-05-31
     * Returns contracts + monthly actuals for the given date range.
     */
    public function data(Request $request): JsonResponse
    {
        $from = Carbon::parse($request->query('from', now()->startOfYear()->toDateString()));
        $to   = Carbon::parse($request->query('to',   now()->toDateString()));

        // Build list of year-month pairs that overlap with the range
        $months = [];
        $cursor = $from->copy()->startOfMonth();
        while ($cursor->lte($to->copy()->endOfMonth())) {
            $months[] = ['year' => $cursor->year, 'month' => $cursor->month];
            $cursor->addMonth();
        }

        // Fetch all active contracts ordered by sort_order
        $contracts = IspSlaContract::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('location')
            ->orderBy('fct')
            ->orderBy('provider')
            ->get();

        // Fetch monthly actuals for the period
        $yearMonths = collect($months);
        $minYear    = $yearMonths->min('year');
        $maxYear    = $yearMonths->max('year');

        $actuals = IspSlaMonthly::whereBetween('year', [$minYear, $maxYear])
            ->whereIn('contract_id', $contracts->pluck('id'))
            ->with('updatedBy:id,name')
            ->get()
            ->groupBy(fn ($r) => $r->contract_id . '_' . $r->year . '_' . $r->month);

        // Build response rows
        $rows = $contracts->map(function ($c) use ($months, $actuals) {
            $monthData = [];
            foreach ($months as $m) {
                $key    = $c->id . '_' . $m['year'] . '_' . $m['month'];
                $actual = $actuals->get($key)?->first();
                $monthData[] = [
                    'year'       => $m['year'],
                    'month'      => $m['month'],
                    'uptime_pct' => $actual?->uptime_pct,
                    'notes'      => $actual?->notes,
                    'on_sla'     => $actual?->uptime_pct !== null
                        ? $actual->uptime_pct >= $c->target_pct
                        : null,
                    'updated_by' => $actual?->updatedBy?->name,
                    'updated_at' => $actual?->updated_at?->format('d M Y H:i'),
                ];
            }

            // Compute average over months that have data
            $withData = collect($monthData)->whereNotNull('uptime_pct');
            $avg      = $withData->count() > 0
                ? round($withData->avg('uptime_pct'), 3)
                : null;

            return [
                'id'         => $c->id,
                'location'   => $c->location,
                'fct'        => $c->fct,
                'provider'   => $c->provider,
                'bandwidth'  => $c->bandwidth,
                'target_pct' => $c->target_pct,
                'avg_pct'    => $avg,
                'on_sla'     => $avg !== null ? $avg >= $c->target_pct : null,
                'months'     => $monthData,
            ];
        });

        // Location summary cards
        $locationSummary = $rows->groupBy('location')->map(function ($group, $loc) {
            $withAvg = $group->whereNotNull('avg_pct');
            $avg     = $withAvg->count() > 0 ? round($withAvg->avg('avg_pct'), 3) : null;
            $target  = $group->min('target_pct');
            return [
                'location' => $loc,
                'avg_pct'  => $avg,
                'on_sla'   => $avg !== null ? $avg >= $target : null,
                'target'   => $target,
            ];
        })->values();

        return response()->json([
            'months'           => $months,
            'rows'             => $rows->values(),
            'location_summary' => $locationSummary,
        ]);
    }

    /**
     * PUT /isp-sla/monthly
     * Upsert a single month's uptime value.
     */
    public function updateMonthly(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contract_id' => 'required|exists:isp_sla_contracts,id',
            'year'        => 'required|integer|min:2020|max:2099',
            'month'       => 'required|integer|min:1|max:12',
            'uptime_pct'  => 'required|numeric|min:0|max:100',
            'notes'       => 'nullable|string|max:500',
        ]);

        $record = IspSlaMonthly::updateOrCreate(
            [
                'contract_id' => $validated['contract_id'],
                'year'        => $validated['year'],
                'month'       => $validated['month'],
            ],
            [
                'uptime_pct' => $validated['uptime_pct'],
                'notes'      => $validated['notes'] ?? null,
                'updated_by' => $request->user()?->id,
            ]
        );

        $contract = IspSlaContract::find($validated['contract_id']);

        return response()->json([
            'uptime_pct' => $record->uptime_pct,
            'on_sla'     => $record->uptime_pct >= $contract->target_pct,
            'updated_by' => $request->user()?->name,
            'updated_at' => $record->updated_at->format('d M Y H:i'),
        ]);
    }

    /**
     * GET /isp-sla/down-history?from=&to=&contract_id=
     */
    public function downHistory(Request $request): JsonResponse
    {
        $from = $request->query('from', now()->subDays(89)->toDateString());
        $to   = $request->query('to',   now()->toDateString());

        $query = IspDownHistory::with(['contract:id,location,fct,provider', 'createdBy:id,name'])
            ->whereBetween('incident_date', [$from, $to])
            ->orderByDesc('incident_date')
            ->orderByDesc('id');

        if ($request->query('contract_id')) {
            $query->where('contract_id', $request->query('contract_id'));
        }

        $rows = $query->get()->map(fn ($r) => [
            'id'               => $r->id,
            'contract_id'      => $r->contract_id,
            'location'         => $r->contract?->location,
            'fct'              => $r->contract?->fct,
            'provider'         => $r->contract?->provider,
            'incident_date'    => $r->incident_date->format('d M Y'),
            'case_description' => $r->case_description,
            'action_taken'     => $r->action_taken,
            'duration_minutes' => $r->duration_minutes,
            'duration_label'   => $this->formatDuration($r->duration_minutes),
            'created_by'       => $r->createdBy?->name ?? 'System',
            'created_at'       => $r->created_at?->format('d M Y H:i'),
        ]);

        return response()->json($rows);
    }

    /**
     * POST /isp-sla/down-history
     */
    public function storeDownHistory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contract_id'      => 'required|exists:isp_sla_contracts,id',
            'incident_date'    => 'required|date',
            'case_description' => 'nullable|string|max:500',
            'action_taken'     => 'nullable|string|max:500',
            'duration_minutes' => 'required|integer|min:1',
        ]);

        $record = IspDownHistory::create([
            ...$validated,
            'created_by' => $request->user()?->id,
        ]);

        $record->load(['contract:id,location,fct,provider', 'createdBy:id,name']);

        return response()->json([
            'id'               => $record->id,
            'contract_id'      => $record->contract_id,
            'location'         => $record->contract?->location,
            'fct'              => $record->contract?->fct,
            'provider'         => $record->contract?->provider,
            'incident_date'    => $record->incident_date->format('d M Y'),
            'case_description' => $record->case_description,
            'action_taken'     => $record->action_taken,
            'duration_minutes' => $record->duration_minutes,
            'duration_label'   => $this->formatDuration($record->duration_minutes),
            'created_by'       => $record->createdBy?->name ?? 'System',
            'created_at'       => $record->created_at?->format('d M Y H:i'),
        ], 201);
    }

    /**
     * DELETE /isp-sla/down-history/{id}
     */
    public function destroyDownHistory(int $id): JsonResponse
    {
        IspDownHistory::findOrFail($id)->delete();
        return response()->json(['message' => 'Deleted']);
    }

    /**
     * GET /isp-sla/contracts
     * Return ALL contracts (active + inactive) for management modal.
     * Use ?active_only=1 for dropdowns.
     */
    public function contracts(Request $request): JsonResponse
    {
        $query = IspSlaContract::orderBy('sort_order')->orderBy('location')->orderBy('fct')->orderBy('provider');

        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        return response()->json(
            $query->get(['id', 'location', 'fct', 'provider', 'bandwidth', 'target_pct', 'is_active', 'sort_order'])
        );
    }

    /**
     * POST /isp-sla/contracts
     * Auto-assign sort_order as max+1 if not provided.
     */
    public function storeContract(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'location'   => 'required|string|max:100',
            'fct'        => 'required|string|max:20',
            'provider'   => 'required|string|max:50',
            'bandwidth'  => 'required|string|max:30',
            'target_pct' => 'required|numeric|min:0|max:100',
            'sort_order' => 'nullable|integer',
        ]);

        // Auto sort_order
        if (!isset($validated['sort_order'])) {
            $validated['sort_order'] = (IspSlaContract::max('sort_order') ?? -1) + 1;
        }

        $contract = IspSlaContract::create($validated);
        return response()->json($contract, 201);
    }

    /**
     * POST /isp-sla/contracts/reorder
     * Accepts: { ids: [3, 1, 2, 4] } — ordered list of contract IDs.
     */
    public function reorderContracts(Request $request): JsonResponse
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);

        foreach ($request->input('ids') as $order => $id) {
            IspSlaContract::where('id', $id)->update(['sort_order' => $order]);
        }

        return response()->json(['message' => 'Reordered']);
    }

    /**
     * PUT /isp-sla/contracts/{id}
     */
    public function updateContract(Request $request, int $id): JsonResponse
    {
        $contract  = IspSlaContract::findOrFail($id);
        $validated = $request->validate([
            'location'   => 'sometimes|filled|string|max:100',
            'fct'        => 'sometimes|filled|string|max:20',
            'provider'   => 'sometimes|filled|string|max:50',
            'bandwidth'  => 'sometimes|filled|string|max:30',
            'target_pct' => 'sometimes|filled|numeric|min:0|max:100',
            'is_active'  => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer',
        ]);

        $contract->fill($validated);
        $contract->save();

        return response()->json($contract->fresh());
    }

    private function formatDuration(int $minutes): string
    {
        if ($minutes < 60) return "{$minutes} menit";
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        return $m > 0 ? "{$h} jam {$m} menit" : "{$h} jam";
    }
}
