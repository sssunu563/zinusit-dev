<?php

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Models\NetworkBackupMonthly;
use App\Models\NetworkDevice;
use App\Models\NetworkFetchLog;
use App\Models\NetworkMaintenanceLog;
use App\Models\NetworkUptimeDaily;
use App\Services\NetworkMonitorService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UptimeController extends Controller
{
    public function __construct(private readonly NetworkMonitorService $service) {}

    // GET /uptime/data?month=5&year=2026&site=&group=
    // Returns per-device uptime for a given month, with daily columns
    public function data(Request $request): JsonResponse
    {
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to   = $request->query('to',   now()->toDateString());

        $fromDate = Carbon::parse($from);
        $toDate   = Carbon::parse($to);

        $location = $request->query('location', '');
        $group    = $request->query('group', '');

        // Build list of ALL dates in range (cross-month)
        $dailyDates = [];
        $cursor = $fromDate->copy();
        while ($cursor->lte($toDate)) {
            $dailyDates[] = $cursor->toDateString();
            $cursor->addDay();
        }

        // Query devices with uptime in range
        $devQuery = NetworkDevice::where('is_active', true)
            ->with(['uptimeDaily' => fn ($q) => $q->whereBetween('report_date', [$from, $to])->orderBy('report_date')])
            ->orderBy('location')->orderBy('host_group')->orderBy('device_name');

        if ($location) $devQuery->where('location', $location);
        if ($group)    $devQuery->where('host_group', 'like', "%{$group}%");

        $devices = $devQuery->get();

        // Location summary — exclude is_excluded devices from avg
        $locSummary = [];
        foreach ($devices as $dev) {
            $loc = $dev->location ?? 'Unknown';
            if (!isset($locSummary[$loc])) {
                $locSummary[$loc] = ['location' => $loc, 'site' => $dev->site, 'total' => 0, 'avg_uptime' => null, 'values' => []];
            }
            $locSummary[$loc]['total']++;
            if (!$dev->is_excluded) {
                $vals = $dev->uptimeDaily->pluck('uptime_percent')->filter(fn ($v) => $v !== null)->values();
                if ($vals->count() > 0) {
                    $locSummary[$loc]['values'] = array_merge($locSummary[$loc]['values'], $vals->toArray());
                }
            }
        }
        foreach ($locSummary as &$ls) {
            $ls['avg_uptime'] = count($ls['values']) > 0
                ? round(array_sum($ls['values']) / count($ls['values']), 3)
                : null;
            unset($ls['values']);
        }
        unset($ls);

        // Device rows — one column per date in range
        $rows = $devices->map(function ($dev) use ($dailyDates) {
            // Key by date string for fast lookup
            $dailyMap     = $dev->uptimeDaily->keyBy(fn ($d) => $d->report_date->toDateString());
            $vals         = $dev->uptimeDaily->pluck('uptime_percent')->filter()->values();
            $displayGroup = preg_replace('/^(F\d|R\d)\s+/', '', $dev->host_group ?? '');

            $daily = collect($dailyDates)->map(function ($dateStr) use ($dailyMap) {
                $d = $dailyMap->get($dateStr);
                return [
                    'date'     => $dateStr,
                    'uptime'   => $d?->uptime_percent,
                    'status'   => $d?->status,
                    'in_range' => true,
                ];
            });

            return [
                'id'              => $dev->id,
                'device_name'     => $dev->device_name,
                'ip_address'      => $dev->ip_address,
                'host_group'      => $dev->host_group,
                'display_group'   => $displayGroup,
                'location'        => $dev->location,
                'site'            => $dev->site,
                'source'          => $dev->source,
                'source_instance' => $dev->source_instance,
                'last_status'     => $dev->last_status,
                'avg_uptime'      => $dev->is_excluded ? null : ($vals->count() > 0 ? round($vals->avg(), 3) : null),
                'is_excluded'     => $dev->is_excluded,
                'maintenance_note'  => $dev->maintenance_note,
                'maintenance_until' => $dev->maintenance_until?->toDateString(),
                'in_maintenance'    => $dev->isInMaintenance(),
                'daily'           => $daily,
            ];
        });

        // Distinct locations for filter dropdown
        $allLocations = NetworkDevice::where('is_active', true)
            ->distinct()->orderBy('location')->pluck('location')->filter()->values();

        // Distinct groups for filter dropdown (display version)
        $allGroups = NetworkDevice::where('is_active', true)
            ->when($location, fn ($q) => $q->where('location', $location))
            ->distinct()->pluck('host_group')
            ->map(fn ($g) => preg_replace('/^(F\d|R\d)\s+/', '', $g ?? ''))
            ->unique()->sort()->values();

        return response()->json([
            'from'          => $from,
            'to'            => $to,
            'daily_dates'   => collect($dailyDates)->map(fn ($d) => ['date' => $d])->values(),
            'loc_summary'   => array_values($locSummary),
            'devices'       => $rows,
            'locations'     => $allLocations,
            'groups'        => $allGroups,
        ]);
    }

    // GET /uptime/logs
    public function logs(): JsonResponse
    {
        $logs = NetworkFetchLog::with('triggeredBy:id,name')
            ->orderByDesc('fetch_date')->orderByDesc('id')
            ->limit(50)->get()
            ->map(fn ($l) => [
                'id'             => $l->id,
                'fetch_date'     => $l->fetch_date->toDateString(),
                'source'         => $l->source,
                'source_instance'=> $l->source_instance,
                'group_name'     => $l->group_name,
                'status'         => $l->status,
                'devices_ok'     => $l->devices_ok,
                'devices_fail'   => $l->devices_fail,
                'notes'          => $l->notes,
                'is_manual'      => $l->is_manual,
                'triggered_by'   => $l->triggeredBy?->name ?? 'Cron',
                'created_at'     => $l->created_at?->format('d M Y H:i'),
            ]);

        return response()->json($logs);
    }

    // POST /uptime/fetch  { date }
    // Triggers fetch from ALL sources (PRTG + all Zabbix)
    public function fetch(Request $request): JsonResponse
    {
        $request->validate(['date' => 'required|date|before_or_equal:today']);
        set_time_limit(300);

        $date   = Carbon::parse($request->input('date'));
        $userId = $request->user()?->id;

        try {
            $results   = $this->service->fetchAll($date, $userId, true);
            $totalOk   = array_sum(array_column($results, 'ok'));
            $totalFail = array_sum(array_column($results, 'fail'));
            $sources   = implode(', ', array_keys($results));
            return response()->json([
                'message' => "Fetch selesai ({$sources}): {$totalOk} device OK, {$totalFail} gagal.",
                'results' => $results,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Fetch gagal: ' . $e->getMessage()], 500);
        }
    }

    // GET /uptime/backup?month=5&year=2026
    // Returns backup grid for devices with monitor_backup=true
    public function backupData(Request $request): JsonResponse
    {
        // Follow the same from/to filter as uptime
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to   = $request->query('to',   now()->toDateString());

        $fromDate = Carbon::parse($from)->startOfMonth();
        $toDate   = Carbon::parse($to)->startOfMonth();

        // Build month list from from→to (only distinct months in range)
        $months = [];
        $cursor = $fromDate->copy();
        while ($cursor->lte($toDate)) {
            $months[] = ['year' => $cursor->year, 'month' => $cursor->month, 'label' => $cursor->format('M y')];
            $cursor->addMonth();
        }

        $minYear = collect($months)->min('year');
        $maxYear = collect($months)->max('year');

        // Devices with monitor_backup = true
        $devices = NetworkDevice::where('monitor_backup', true)
            ->where('is_active', true)
            ->orderBy('location')->orderBy('host_group')->orderBy('device_name')
            ->get();

        // Backup records for these devices in range
        $deviceIds = $devices->pluck('id');
        $records   = NetworkBackupMonthly::whereIn('device_id', $deviceIds)
            ->whereBetween('year', [$minYear, $maxYear])
            ->get()
            ->groupBy(fn ($r) => $r->device_id . '|' . $r->year . '|' . $r->month);

        $grid = $devices->map(function ($dev) use ($months, $records) {
            $displayGroup = preg_replace('/^(F\d|R\d)\s+/', '', $dev->host_group ?? '');
            $monthData = collect($months)->map(function ($m) use ($dev, $records) {
                $key    = $dev->id . '|' . $m['year'] . '|' . $m['month'];
                $record = $records->get($key)?->first();
                return [
                    'year'       => $m['year'],
                    'month'      => $m['month'],
                    'label'      => $m['label'],
                    'has_backup' => $record?->has_backup ?? null,
                    'notes'      => $record?->notes,
                ];
            });

            return [
                'device_id'     => $dev->id,
                'device_name'   => $dev->device_name,
                'ip_address'    => $dev->ip_address,
                'host_group'    => $dev->host_group,
                'display_group' => $displayGroup,
                'location'      => $dev->location,   // ZGI BGR F1, ZGI BGR R3, etc
                'site'          => $dev->site,
                'months'        => $monthData,
            ];
        });

        return response()->json([
            'months' => $months,
            'grid'   => $grid,
        ]);
    }

    // PUT /uptime/backup  { device_id, year, month, has_backup, notes? }
    public function updateBackup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id'  => 'required|integer|exists:network_devices,id',
            'year'       => 'required|integer|min:2020|max:2099',
            'month'      => 'required|integer|min:1|max:12',
            'has_backup' => 'required|boolean',
            'notes'      => 'nullable|string|max:500',
        ]);

        $record = NetworkBackupMonthly::updateOrCreate(
            ['device_id' => $validated['device_id'], 'year' => $validated['year'], 'month' => $validated['month']],
            ['has_backup' => $validated['has_backup'], 'notes' => $validated['notes'] ?? null, 'updated_by' => $request->user()?->id]
        );

        return response()->json(['has_backup' => $record->has_backup]);
    }

    // GET /uptime/backup-summary?from=&to=
    // Returns per-location backup stats for the Summary tab KPI card
    public function backupSummary(Request $request): JsonResponse
    {
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to   = $request->query('to',   now()->toDateString());

        $fromDate = Carbon::parse($from)->startOfMonth();
        $toDate   = Carbon::parse($to)->startOfMonth();

        $months = [];
        $cursor = $fromDate->copy();
        while ($cursor->lte($toDate)) {
            $months[] = ['year' => $cursor->year, 'month' => $cursor->month];
            $cursor->addMonth();
        }

        $minYear = collect($months)->min('year');
        $maxYear = collect($months)->max('year');

        $devices = NetworkDevice::where('monitor_backup', true)
            ->where('is_active', true)
            ->orderBy('location')->orderBy('device_name')
            ->get(['id', 'device_name', 'location', 'host_group']);

        $deviceIds = $devices->pluck('id');
        $records   = NetworkBackupMonthly::whereIn('device_id', $deviceIds)
            ->whereBetween('year', [$minYear, $maxYear])
            ->get()
            ->groupBy(fn ($r) => $r->device_id . '|' . $r->year . '|' . $r->month);

        // Per-device: OK if all months in range have has_backup=true
        $totalDevices = $devices->count();
        $okDevices    = 0;

        // Per-location summary
        $locMap = [];
        foreach ($devices as $dev) {
            $loc = $dev->location ?? 'Unknown';
            if (!isset($locMap[$loc])) {
                $locMap[$loc] = ['location' => $loc, 'total' => 0, 'ok' => 0];
            }
            $locMap[$loc]['total']++;

            $allOk  = true;
            $hasAny = false;
            foreach ($months as $m) {
                $key    = $dev->id . '|' . $m['year'] . '|' . $m['month'];
                $record = $records->get($key)?->first();
                if ($record !== null) {
                    $hasAny = true;
                    if (!$record->has_backup) $allOk = false;
                }
            }
            if ($hasAny && $allOk) {
                $okDevices++;
                $locMap[$loc]['ok']++;
            }
        }

        $locSummary = array_values(array_map(function ($l) {
            $pct = $l['total'] > 0 ? round($l['ok'] / $l['total'] * 100, 1) : null;
            return [
                'location' => $l['location'],
                'total'    => $l['total'],
                'ok'       => $l['ok'],
                'pct'      => $pct,
            ];
        }, $locMap));

        $overallPct = $totalDevices > 0 ? round($okDevices / $totalDevices * 100, 1) : null;

        return response()->json([
            'total_devices' => $totalDevices,
            'ok_devices'    => $okDevices,
            'overall_pct'   => $overallPct,
            'loc_summary'   => $locSummary,
        ]);
    }

    // GET /uptime/backup-settings
    // Returns all active devices with their monitor_backup flag
    public function backupSettings(): JsonResponse
    {
        $devices = NetworkDevice::where('is_active', true)
            ->orderBy('location')->orderBy('host_group')->orderBy('device_name')
            ->get(['id', 'device_name', 'ip_address', 'host_group', 'location', 'site', 'monitor_backup'])
            ->map(function ($dev) {
                return [
                    'id'             => $dev->id,
                    'device_name'    => $dev->device_name,
                    'ip_address'     => $dev->ip_address,
                    'host_group'     => $dev->host_group,
                    'display_group'  => preg_replace('/^(F\d|R\d)\s+/', '', $dev->host_group ?? ''),
                    'location'       => $dev->location,
                    'site'           => $dev->site,
                    'monitor_backup' => (bool) $dev->monitor_backup,
                ];
            });

        return response()->json($devices);
    }

    // PUT /uptime/backup-settings  { device_ids: [1,2,3] }
    // Bulk update monitor_backup flags
    public function updateBackupSettings(Request $request): JsonResponse
    {
        $request->validate(['device_ids' => 'required|array', 'device_ids.*' => 'integer']);

        $selectedIds = $request->input('device_ids', []);

        // Set all to false, then set selected to true
        NetworkDevice::where('is_active', true)->update(['monitor_backup' => false]);
        if (!empty($selectedIds)) {
            NetworkDevice::whereIn('id', $selectedIds)->update(['monitor_backup' => true]);
        }

        return response()->json(['updated' => count($selectedIds)]);
    }

    // PUT /uptime/excluded  { device_id, is_excluded }
    // Toggle excluded flag — excluded devices are not counted in avg, not exported, no auto-ticket
    // but fetch data is still stored (value 0 if down)
    public function toggleExcluded(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id'   => 'required|integer|exists:network_devices,id',
            'is_excluded' => 'required|boolean',
        ]);

        $device = NetworkDevice::findOrFail($validated['device_id']);
        $device->update(['is_excluded' => $validated['is_excluded']]);

        return response()->json(['is_excluded' => $device->is_excluded]);
    }

    // PUT /uptime/maintenance  { device_id, note, until? }
    // Set or clear maintenance note on a device
    public function updateMaintenance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => 'required|integer|exists:network_devices,id',
            'note'      => 'nullable|string|max:500',
            'until'     => 'nullable|date',
        ]);

        $device = NetworkDevice::findOrFail($validated['device_id']);
        $device->update([
            'maintenance_note'  => $validated['note'] ?? null,
            'maintenance_until' => $validated['until'] ?? null,
        ]);

        return response()->json(['ok' => true]);
    }

    // GET /uptime/maintenance-logs?from=&to=&device_id=&status=
    // Returns maintenance logs — open tickets always shown, closed filtered by date
    public function maintenanceLogs(Request $request): JsonResponse
    {
        $from     = $request->query('from', now()->subDays(89)->toDateString());
        $to       = $request->query('to',   now()->toDateString());
        $deviceId = $request->query('device_id');
        $status   = $request->query('status'); // open|closed|all

        // Open tickets: always show regardless of date
        $openQuery = NetworkMaintenanceLog::with(['device:id,device_name,ip_address,location,host_group', 'createdBy:id,name', 'closedBy:id,name'])
            ->where('status', 'open')
            ->orderByDesc('started_at');

        // Closed tickets: filter by date range
        $closedQuery = NetworkMaintenanceLog::with(['device:id,device_name,ip_address,location,host_group', 'createdBy:id,name', 'closedBy:id,name'])
            ->where('status', 'closed')
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('started_at', [$from, $to])
                  ->orWhereBetween('resolved_at', [$from, $to]);
            })
            ->orderByDesc('started_at');

        if ($deviceId) {
            $openQuery->where('device_id', $deviceId);
            $closedQuery->where('device_id', $deviceId);
        }

        if ($status === 'open') {
            $logs = $openQuery->get();
        } elseif ($status === 'closed') {
            $logs = $closedQuery->get();
        } else {
            $logs = $openQuery->get()->merge($closedQuery->get())->sortByDesc('started_at')->values();
        }

        return response()->json($logs->map(fn ($l) => $this->formatMaintenanceLog($l)));
    }

    // POST /uptime/maintenance-logs  { device_id, started_at, event_type, notes }
    public function storeMaintenanceLog(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id'  => 'required|integer|exists:network_devices,id',
            'started_at' => 'required|date',
            'event_type' => 'required|in:maintenance,restart,down,auto_detected',
            'notes'      => 'nullable|string|max:1000',
        ]);

        $log = NetworkMaintenanceLog::create([
            ...$validated,
            'status'     => 'open',
            'created_by' => $request->user()?->id,
        ]);

        $log->load(['device:id,device_name,ip_address,location,host_group', 'createdBy:id,name']);
        return response()->json($this->formatMaintenanceLog($log), 201);
    }

    // PUT /uptime/maintenance-logs/{id}  { status, resolved_at, notes }
    // Close or update a maintenance log
    public function updateMaintenanceLog(Request $request, int $id): JsonResponse
    {
        $log       = NetworkMaintenanceLog::findOrFail($id);
        $validated = $request->validate([
            'status'      => 'sometimes|in:open,closed',
            'resolved_at' => 'nullable|date',
            'notes'       => 'nullable|string|max:1000',
            'event_type'  => 'sometimes|in:maintenance,restart,down,auto_detected',
        ]);

        if (isset($validated['status']) && $validated['status'] === 'closed' && !$log->resolved_at) {
            $validated['resolved_at'] = $validated['resolved_at'] ?? now()->toDateString();
            $validated['closed_by']   = $request->user()?->id;
        }

        $log->update($validated);
        $log->load(['device:id,device_name,ip_address,location,host_group', 'createdBy:id,name', 'closedBy:id,name']);
        return response()->json($this->formatMaintenanceLog($log));
    }

    // DELETE /uptime/maintenance-logs/{id}
    public function destroyMaintenanceLog(int $id): JsonResponse
    {
        NetworkMaintenanceLog::findOrFail($id)->delete();
        return response()->json(['ok' => true]);
    }

    private function formatMaintenanceLog(NetworkMaintenanceLog $l): array
    {
        $displayGroup = preg_replace('/^(F\d|R\d)\s+/', '', $l->device?->host_group ?? '');
        return [
            'id'           => $l->id,
            'device_id'    => $l->device_id,
            'device_name'  => $l->device?->device_name,
            'ip_address'   => $l->device?->ip_address,
            'location'     => $l->device?->location,
            'display_group'=> $displayGroup,
            'status'       => $l->status,
            'event_type'   => $l->event_type,
            'started_at'   => $l->started_at?->toDateString(),
            'resolved_at'  => $l->resolved_at?->toDateString(),
            'duration'     => $l->durationLabel(),
            'notes'        => $l->notes,
            'is_auto'      => $l->is_auto,
            'created_by'   => $l->createdBy?->name ?? 'System',
            'closed_by'    => $l->closedBy?->name,
            'created_at'   => $l->created_at?->format('d M Y H:i'),
        ];
    }
}
