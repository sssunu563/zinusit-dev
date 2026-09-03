<?php

namespace App\Http\Controllers\Server;

use App\Http\Controllers\Controller;
use App\Models\ActionLog;
use App\Models\ServerDevice;
use App\Models\ServerFetchLog;
use App\Models\ServerMaintenanceLog;
use App\Models\ServerResourceDaily;
use App\Models\ServerTemperatureDaily;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ServerOperationController extends Controller
{
    public function __construct(private readonly \App\Services\ServerMonitorService $service) {}

    public function index(): Response
    {
        return Inertia::render('Report/ServerOperation/Index');
    }

    /**
     * GET /server-operation/data?from=&to=
     * Returns per-device server resource data (CPU, RAM, Disk)
     */
    public function data(Request $request): JsonResponse
    {
        $from = $request->query('from', now()->subDays(29)->toDateString());
        $to   = $request->query('to', now()->toDateString());

        $fromDate = Carbon::parse($from);
        $toDate   = Carbon::parse($to);

        $location = $request->query('location', '');
        $group    = $request->query('group', '');

        // Build list of ALL dates in range
        $dailyDates = [];
        $cursor = $fromDate->copy();
        while ($cursor->lte($toDate)) {
            $dailyDates[] = $cursor->toDateString();
            $cursor->addDay();
        }

        // Query devices with resource data in range
        $devQuery = ServerDevice::where('is_active', true)
            ->with(['resourceDaily' => fn ($q) => $q->whereBetween('report_date', [$from, $to])->orderBy('report_date')])
            ->orderBy('location')->orderBy('host_group')->orderBy('device_name');

        if ($location) $devQuery->where('location', $location);
        if ($group)    $devQuery->where('host_group', 'like', "%{$group}%");

        $devices = $devQuery->get();

        // Location summary
        $locSummary = [];
        foreach ($devices as $dev) {
            $loc = $dev->location ?? 'Unknown';
            if (!isset($locSummary[$loc])) {
                $locSummary[$loc] = [
                    'location' => $loc,
                    'site' => $dev->site,
                    'total' => 0,
                    'avg_cpu' => null,
                    'avg_memory' => null,
                    'cpu_values' => [],
                    'memory_values' => [],
                ];
            }
            $locSummary[$loc]['total']++;
            if (!$dev->is_excluded) {
                $cpuVals = $dev->resourceDaily->pluck('cpu_usage_percent')->filter(fn ($v) => $v !== null)->values();
                $memVals = $dev->resourceDaily->pluck('memory_usage_percent')->filter(fn ($v) => $v !== null)->values();
                if ($cpuVals->count() > 0) {
                    $locSummary[$loc]['cpu_values'] = array_merge($locSummary[$loc]['cpu_values'], $cpuVals->toArray());
                }
                if ($memVals->count() > 0) {
                    $locSummary[$loc]['memory_values'] = array_merge($locSummary[$loc]['memory_values'], $memVals->toArray());
                }
            }
        }

        foreach ($locSummary as &$ls) {
            $ls['avg_cpu'] = count($ls['cpu_values']) > 0
                ? round(array_sum($ls['cpu_values']) / count($ls['cpu_values']), 2)
                : null;
            $ls['avg_memory'] = count($ls['memory_values']) > 0
                ? round(array_sum($ls['memory_values']) / count($ls['memory_values']), 2)
                : null;
            unset($ls['cpu_values']);
            unset($ls['memory_values']);
        }
        unset($ls);

        // Device rows
        $rows = $devices->map(function ($dev) use ($dailyDates) {
            $dailyMap = $dev->resourceDaily->keyBy(fn ($d) => $d->report_date->toDateString());
            $cpuVals = $dev->resourceDaily->pluck('cpu_usage_percent')->filter()->values();
            $memVals = $dev->resourceDaily->pluck('memory_usage_percent')->filter()->values();
            $displayGroup = preg_replace('/^(F\d|R\d)\s+/', '', $dev->host_group ?? '');

            $daily = collect($dailyDates)->map(function ($dateStr) use ($dailyMap) {
                $d = $dailyMap->get($dateStr);

                return [
                    'date'     => $dateStr,
                    'cpu'      => $d?->cpu_usage_percent,
                    'memory'   => $d?->memory_usage_percent,
                    'disk'     => self::normalizeHddFreeLabel($d?->hdd_free_percent),
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
                'avg_cpu'         => $dev->is_excluded ? null : ($cpuVals->count() > 0 ? round($cpuVals->avg(), 2) : null),
                'avg_memory'      => $dev->is_excluded ? null : ($memVals->count() > 0 ? round($memVals->avg(), 2) : null),
                'is_excluded'     => $dev->is_excluded,
                'maintenance_note'  => $dev->maintenance_note,
                'maintenance_until' => $dev->maintenance_until?->toDateString(),
                'in_maintenance'    => $dev->isInMaintenance(),
                'latest_disk'       => self::normalizeHddFreeLabel($dev->resourceDaily->last()?->hdd_free_percent),
                'daily'           => $daily,
            ];
        });

        $allLocations = ServerDevice::where('is_active', true)
            ->distinct()->orderBy('location')->pluck('location')->filter()->values();

        $allGroups = ServerDevice::where('is_active', true)
            ->when($location, fn ($q) => $q->where('location', $location))
            ->distinct()->pluck('host_group')
            ->map(fn ($g) => preg_replace('/^(F\d|R\d)\s+/', '', $g ?? ''))
            ->unique()->sort()->values();

        // Open maintenance tickets
        $openTickets = ServerMaintenanceLog::with(['device:id,device_name,ip_address,location,host_group'])
            ->where('status', 'open')
            ->orderByDesc('started_at')
            ->get()
            ->map(fn ($l) => $this->formatMaintenanceLog($l));

        return response()->json([
            'from'          => $from,
            'to'            => $to,
            'daily_dates'   => collect($dailyDates)->map(fn ($d) => ['date' => $d])->values(),
            'loc_summary'   => array_values($locSummary),
            'devices'       => $rows,
            'locations'     => $allLocations,
            'groups'        => $allGroups,
            'open_tickets'  => $openTickets,
        ]);
    }

    /**
     * GET /server-operation/temperature?from=&to=
     * Returns server temperature data
     */
    public function temperature(Request $request): JsonResponse
    {
        $from = $request->query('from', now()->subDays(29)->toDateString());
        $to   = $request->query('to', now()->toDateString());

        $fromDate = Carbon::parse($from);
        $toDate   = Carbon::parse($to);

        // Build list of dates
        $dailyDates = [];
        $cursor = $fromDate->copy();
        while ($cursor->lte($toDate)) {
            $dailyDates[] = $cursor->toDateString();
            $cursor->addDay();
        }

        // Get temperature data
        $tempData = ServerTemperatureDaily::whereBetween('report_date', [$from, $to])
            ->orderBy('report_date')
            ->orderBy('location')
            ->get();

        // Group by location
        $locSummary = [];
        foreach ($tempData as $temp) {
            $loc = $temp->location ?? 'Unknown';
            if (!isset($locSummary[$loc])) {
                $locSummary[$loc] = [
                    'location' => $loc,
                    'description' => $temp->description,
                    'avg_temp' => null,
                    'max_temp' => null,
                    'min_temp' => null,
                    'values' => [],
                ];
            }
            $locSummary[$loc]['values'][] = $temp->value_celsius;
        }

        foreach ($locSummary as &$ls) {
            if (count($ls['values']) > 0) {
                $ls['avg_temp'] = round(array_sum($ls['values']) / count($ls['values']), 2);
                $ls['max_temp'] = round(max($ls['values']), 2);
                $ls['min_temp'] = round(min($ls['values']), 2);
            }
            unset($ls['values']);
        }
        unset($ls);

        // Daily data by sensor/location
        $dailyRows = $tempData->groupBy(function ($temp) {
            return $temp->sensor_id . '|' . $temp->location;
        })->map(function ($group, $key) use ($dailyDates) {
            $first = $group->first();
            $dailyMap = $group->keyBy(fn ($d) => $d->report_date->toDateString());

            $daily = collect($dailyDates)->map(function ($dateStr) use ($dailyMap) {
                $d = $dailyMap->get($dateStr);
                return [
                    'date' => $dateStr,
                    'temp' => $d?->value_celsius,
                ];
            });

            return [
                'sensor_id' => $first->sensor_id,
                'location' => $first->location,
                'description' => $first->description,
                'avg_temp' => count($group) > 0 ? round($group->avg('value_celsius'), 2) : null,
                'daily' => $daily,
            ];
        })->values();

        $allLocations = ServerTemperatureDaily::whereBetween('report_date', [$from, $to])
            ->distinct()->orderBy('location')->pluck('location')->filter()->values();

        return response()->json([
            'from'          => $from,
            'to'            => $to,
            'daily_dates'   => collect($dailyDates)->map(fn ($d) => ['date' => $d])->values(),
            'loc_summary'   => array_values($locSummary),
            'daily_rows'    => $dailyRows,
            'locations'     => $allLocations,
        ]);
    }

    /**
     * GET /server-operation/logs
     * Returns fetch logs with user information
     */
    public function logs(): JsonResponse
    {
        $logs = ServerFetchLog::with('triggeredBy:id,name')
            ->orderByDesc('fetch_date')->orderByDesc('id')
            ->limit(100)->get()
            ->map(fn ($l) => [
                'id'             => $l->id,
                'fetch_date'     => $l->fetch_date->toDateString(),
                'group_name'     => $l->group_name,
                'status'         => $l->status,
                'devices_ok'     => $l->devices_ok,
                'devices_fail'   => $l->devices_fail,
                'notes'          => $l->notes,
                'is_manual'      => $l->is_manual,
                'triggered_by'   => $l->triggeredBy?->name ?? 'Cron',
                'created_at'     => $l->created_at->toDateTimeString(),
            ]);

        return response()->json($logs);
    }

    /**
     * POST /server-operation/fetch
     * Trigger manual fetch and log activity
     */
    public function fetch(Request $request): JsonResponse
    {
        $user = auth()->user();
        $dateStr = $request->input('fetch_date') ?? now()->subDay()->toDateString();
        $date = Carbon::parse($dateStr);

        try {
            // Log the action
            ActionLog::create([
                'user_id' => $user->id,
                'action_type' => 'fetch_server_operation',
                'item_type' => 'ServerOperation',
                'note' => "Manually triggered server operation fetch for {$dateStr}",
            ]);

            $results = $this->service->fetchAll($date, $user->id, true);
            
            return response()->json([
                'success' => true,
                'message' => "Fetch selesai: " . 
                             ($results['resources']['ok'] + $results['temperature']['ok']) . " data OK, " .
                             ($results['resources']['fail'] + $results['temperature']['fail']) . " gagal.",
                'results' => $results,
            ]);
        } catch (\Exception $e) {
            // Log error
            ActionLog::create([
                'user_id' => $user->id,
                'action_type' => 'fetch_server_operation_failed',
                'item_type' => 'ServerOperation',
                'note' => "Error: " . $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to trigger fetch: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /server-operation/summary?from=&to=
     * Returns summary statistics
     */
    public function summary(Request $request): JsonResponse
    {
        $from = $request->query('from', now()->subDays(29)->toDateString());
        $to   = $request->query('to', now()->toDateString());

        $activeDevices = ServerDevice::where('is_active', true)->count();
        $avgCpu = ServerResourceDaily::whereBetween('report_date', [$from, $to])->whereNotNull('cpu_usage_percent')->avg('cpu_usage_percent');
        $avgMemory = ServerResourceDaily::whereBetween('report_date', [$from, $to])->whereNotNull('memory_usage_percent')->avg('memory_usage_percent');
        $avgTemp = ServerTemperatureDaily::whereBetween('report_date', [$from, $to])->avg('value_celsius');

        // Performance per Lokasi
        $locations = ServerDevice::where('is_active', true)->distinct()->pluck('location')->filter()->values();
        $locationStats = [];

        foreach ($locations as $loc) {
            $sourceIds = ServerDevice::where('location', $loc)->where('is_active', true)->pluck('source_id');
            
            $lAvgCpu = ServerResourceDaily::whereIn('host_id', $sourceIds)
                ->whereBetween('report_date', [$from, $to])
                ->whereNotNull('cpu_usage_percent')
                ->avg('cpu_usage_percent');

            $lAvgMem = ServerResourceDaily::whereIn('host_id', $sourceIds)
                ->whereBetween('report_date', [$from, $to])
                ->whereNotNull('memory_usage_percent')
                ->avg('memory_usage_percent');

            // Temperature sensors might not be linked to devices directly by source_id, 
            // so we filter by location name instead for the location summary.
            $lAvgTemp = ServerTemperatureDaily::where('location', $loc)
                ->whereBetween('report_date', [$from, $to])
                ->avg('value_celsius');

            $locationStats[] = [
                'location'   => $loc,
                'count'      => count($sourceIds),
                'avg_cpu'    => $lAvgCpu ? round($lAvgCpu, 2) : 0,
                'avg_memory' => $lAvgMem ? round($lAvgMem, 2) : 0,
                'avg_temp'   => $lAvgTemp ? round($lAvgTemp, 2) : 0,
            ];
        }

        return response()->json([
            'from' => $from,
            'to' => $to,
            'active_devices' => $activeDevices,
            'avg_cpu_usage' => $avgCpu ? round($avgCpu, 2) : null,
            'avg_memory_usage' => $avgMemory ? round($avgMemory, 2) : null,
            'avg_temperature' => $avgTemp ? round($avgTemp, 2) : null,
            'location_stats' => $locationStats,
        ]);
    }

    /**
     * GET /server-operation/export?from=&to=&resources=1&temperature=1&summary=1
     * Export server operation report to Excel
     */
    public function export(Request $request)
    {
        $from = $request->query('from', now()->subDays(29)->toDateString());
        $to   = $request->query('to', now()->toDateString());
        $doResources = $request->query('resources', '1') === '1';
        $doTemp = $request->query('temperature', '0') === '1';
        $doSummary = $request->query('summary', '0') === '1';

        $user = auth()->user();

        // Log the export action
        ActionLog::create([
            'user_id' => $user->id,
            'action_type' => 'export_server_operation',
            'item_type' => 'ServerOperation',
            'note' => "Exported server operation report from {$from} to {$to}",
        ]);

        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        $hdrStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '003628']],
        ];

        if ($doSummary) {
            $ws = $spreadsheet->createSheet();
            $ws->setTitle('Server Operation Summary');
            $ws->getTabColor()->setRGB('003628');

            $ws->setCellValue('A1', 'Server Operation Summary');
            $ws->getStyle('A1')->applyFromArray(['font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '003628']]]);
            $ws->setCellValue('A2', "Periode: {$from} s/d {$to}");
            $ws->getStyle('A2')->applyFromArray(['font' => ['color' => ['rgb' => '64748B'], 'size' => 10]]);

            $row = 4;

            // Summary statistics
            $ws->setCellValue('A' . $row, 'SUMMARY');
            $ws->getStyle('A' . $row)->applyFromArray($hdrStyle);
            $ws->mergeCells("A{$row}:B{$row}");
            $row++;

            $ws->setCellValue('A' . $row, 'Metric');
            $ws->setCellValue('B' . $row, 'Value');
            $ws->getStyle("A{$row}:B{$row}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']],
            ]);
            $row++;

            $activeDevices = ServerDevice::where('is_active', true)->count();
            $avgCpu = ServerResourceDaily::whereBetween('report_date', [$from, $to])
                ->whereNotNull('cpu_usage_percent')->avg('cpu_usage_percent');
            $avgMem = ServerResourceDaily::whereBetween('report_date', [$from, $to])
                ->whereNotNull('memory_usage_percent')->avg('memory_usage_percent');
            $avgTemp = ServerTemperatureDaily::whereBetween('report_date', [$from, $to])
                ->avg('value_celsius');

            $metrics = [
                'Active Devices' => $activeDevices,
                'Avg CPU Usage' => $avgCpu ? round($avgCpu, 2) . '%' : '-',
                'Avg Memory Usage' => $avgMem ? round($avgMem, 2) . '%' : '-',
                'Avg Temperature' => $avgTemp ? round($avgTemp, 2) . '°C' : '-',
            ];

            foreach ($metrics as $label => $value) {
                $ws->setCellValue('A' . $row, $label);
                $ws->setCellValue('B' . $row, $value);
                $row++;
            }

            $ws->getColumnDimension('A')->setWidth(25);
            $ws->getColumnDimension('B')->setWidth(20);
        }

        if ($doResources) {
            $dates = [];
            $cur = Carbon::parse($from);
            while ($cur->lte(Carbon::parse($to))) {
                $dates[] = $cur->toDateString();
                $cur->addDay();
            }

            // CPU Sheet
            $wsCpu = $spreadsheet->createSheet();
            $wsCpu->setTitle('CPU Usage');
            $wsCpu->getTabColor()->setRGB('EA580C');
            $wsCpu->setCellValue('A1', 'CPU Usage Report');
            $wsCpu->getStyle('A1')->applyFromArray(['font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '003628']]]);

            $wsCpu->setCellValue('A4', 'Device');
            $wsCpu->setCellValue('B4', 'IP');
            $wsCpu->setCellValue('C4', 'Location');
            $wsCpu->setCellValue('D4', 'Avg');
            $ci = 5;
            foreach ($dates as $date) {
                $col = Coordinate::stringFromColumnIndex($ci);
                $wsCpu->setCellValue($col . '4', Carbon::parse($date)->format('d M'));
                $ci++;
            }
            $wsCpu->getStyle('A4:' . Coordinate::stringFromColumnIndex($ci - 1) . '4')->applyFromArray($hdrStyle);

            $row = 5;
            foreach ($devices as $dev) {
                $resourceMap = $dev->resourceDaily->keyBy(fn ($d) => $d->report_date->toDateString());
                $cpuVals = $dev->resourceDaily->pluck('cpu_usage_percent')->filter()->values();
                $avgCpu = $cpuVals->count() > 0 ? round($cpuVals->avg(), 2) : null;

                $wsCpu->setCellValue('A' . $row, $dev->device_name);
                $wsCpu->setCellValue('B' . $row, $dev->ip_address);
                $wsCpu->setCellValue('C' . $row, $dev->location);
                $wsCpu->setCellValue('D' . $row, $avgCpu !== null ? $avgCpu . '%' : '-');

                $c = 5;
                foreach ($dates as $date) {
                    $col = Coordinate::stringFromColumnIndex($c);
                    $val = $resourceMap->get($date)?->cpu_usage_percent;
                    $wsCpu->setCellValue($col . $row, $val !== null ? round($val, 2) . '%' : '-');
                    $c++;
                }
                $row++;
            }
            foreach (range('A','D') as $col) $wsCpu->getColumnDimension($col)->setAutoSize(true);

            // Memory Sheet
            $wsMem = $spreadsheet->createSheet();
            $wsMem->setTitle('Memory Usage');
            $wsMem->getTabColor()->setRGB('059669');
            $wsMem->setCellValue('A1', 'Memory Usage Report');
            $wsMem->getStyle('A1')->applyFromArray(['font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '003628']]]);

            $wsMem->setCellValue('A4', 'Device');
            $wsMem->setCellValue('B4', 'IP');
            $wsMem->setCellValue('C4', 'Location');
            $wsMem->setCellValue('D4', 'Avg');
            $ci = 5;
            foreach ($dates as $date) {
                $col = Coordinate::stringFromColumnIndex($ci);
                $wsMem->setCellValue($col . '4', Carbon::parse($date)->format('d M'));
                $ci++;
            }
            $wsMem->getStyle('A4:' . Coordinate::stringFromColumnIndex($ci - 1) . '4')->applyFromArray($hdrStyle);

            $row = 5;
            foreach ($devices as $dev) {
                $resourceMap = $dev->resourceDaily->keyBy(fn ($d) => $d->report_date->toDateString());
                $memVals = $dev->resourceDaily->pluck('memory_usage_percent')->filter()->values();
                $avgMem = $memVals->count() > 0 ? round($memVals->avg(), 2) : null;

                $wsMem->setCellValue('A' . $row, $dev->device_name);
                $wsMem->setCellValue('B' . $row, $dev->ip_address);
                $wsMem->setCellValue('C' . $row, $dev->location);
                $wsMem->setCellValue('D' . $row, $avgMem !== null ? $avgMem . '%' : '-');

                $c = 5;
                foreach ($dates as $date) {
                    $col = Coordinate::stringFromColumnIndex($c);
                    $val = $resourceMap->get($date)?->memory_usage_percent;
                    $wsMem->setCellValue($col . $row, $val !== null ? round($val, 2) . '%' : '-');
                    $c++;
                }
                $row++;
            }
            foreach (range('A','D') as $col) $wsMem->getColumnDimension($col)->setAutoSize(true);

            // Disk Usage Sheet
            $wsDisk = $spreadsheet->createSheet();
            $wsDisk->setTitle('Disk Usage');
            $wsDisk->getTabColor()->setRGB('2563EB');
            $wsDisk->setCellValue('A1', 'Disk Usage Report');
            $wsDisk->getStyle('A1')->applyFromArray(['font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '003628']]]);

            $wsDisk->setCellValue('A4', 'Device');
            $wsDisk->setCellValue('B4', 'IP');
            $wsDisk->setCellValue('C4', 'Location');
            $ci = 4;
            foreach ($dates as $date) {
                $col = Coordinate::stringFromColumnIndex($ci);
                $wsDisk->setCellValue($col . '4', Carbon::parse($date)->format('d M'));
                $ci++;
            }
            $wsDisk->getStyle('A4:' . Coordinate::stringFromColumnIndex($ci - 1) . '4')->applyFromArray($hdrStyle);

            $row = 5;
            foreach ($devices as $dev) {
                $resourceMap = $dev->resourceDaily->keyBy(fn ($d) => $d->report_date->toDateString());
                $wsDisk->setCellValue('A' . $row, $dev->device_name);
                $wsDisk->setCellValue('B' . $row, $dev->ip_address);
                $wsDisk->setCellValue('C' . $row, $dev->location);

                $c = 4;
                foreach ($dates as $date) {
                    $col = Coordinate::stringFromColumnIndex($c);
                    $val = $resourceMap->get($date)?->hdd_free_percent;
                    $wsDisk->setCellValue($col . $row, $val ?? '-');
                    $c++;
                }
                $row++;
            }
            foreach (range('A','C') as $col) $wsDisk->getColumnDimension($col)->setAutoSize(true);
        }

        if ($doTemp) {
            $wsTemp = $spreadsheet->createSheet();
            $wsTemp->setTitle('Temperature Data');
            $wsTemp->getTabColor()->setRGB('E11D48');
            // ... (Temperature sheet logic) ...
            $wsTemp->setCellValue('A1', 'Server Temperature Report');
            $wsTemp->getStyle('A1')->applyFromArray(['font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '003628']]]);

            $wsTemp->setCellValue('A4', 'Location');
            $wsTemp->setCellValue('B4', 'Description');
            $wsTemp->setCellValue('C4', 'Avg');
            $ci = 4;
            foreach ($dates as $date) {
                $col = Coordinate::stringFromColumnIndex($ci);
                $wsTemp->setCellValue($col . '4', Carbon::parse($date)->format('d M'));
                $ci++;
            }
            $wsTemp->getStyle('A4:' . Coordinate::stringFromColumnIndex($ci - 1) . '4')->applyFromArray($hdrStyle);

            $tempData = ServerTemperatureDaily::whereBetween('report_date', [$from, $to])
                ->orderBy('location')->orderBy('sensor_id')->orderBy('report_date')->get();
            $grouped = $tempData->groupBy(fn ($t) => $t->location . '|' . $t->sensor_id);

            $row = 5;
            foreach ($grouped as $group) {
                $first = $group->first();
                $tempMap = $group->keyBy(fn ($d) => $d->report_date->toDateString());
                $wsTemp->setCellValue('A' . $row, $first->location);
                $wsTemp->setCellValue('B' . $row, $first->description);
                $wsTemp->setCellValue('C' . $row, round($group->avg('value_celsius'), 1) . '°C');

                $c = 4;
                foreach ($dates as $date) {
                    $col = Coordinate::stringFromColumnIndex($c);
                    $val = $tempMap->get($date)?->value_celsius;
                    $wsTemp->setCellValue($col . $row, $val !== null ? round($val, 1) . '°C' : '-');
                    $c++;
                }
                $row++;
            }
            foreach (range('A','C') as $col) $wsTemp->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $filename = 'ServerOperation_' . now()->format('Ymd_His') . '.xlsx';

        return response()->streamDownload(
            fn () => $writer->save('php://output'),
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }

    /**
     * PUT /server-operation/maintenance  { device_id, note, until? }
     */
    public function updateMaintenance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => 'required|integer|exists:server_devices,id',
            'note'      => 'nullable|string|max:500',
            'until'     => 'nullable|date',
        ]);

        $device = ServerDevice::findOrFail($validated['device_id']);
        $device->update([
            'maintenance_note'  => $validated['note'] ?? null,
            'maintenance_until' => $validated['until'] ?? null,
        ]);

        ActionLog::create([
            'user_id'     => $request->user()?->id,
            'action_type' => $validated['note'] ? 'set_maintenance' : 'clear_maintenance',
            'item_type'   => 'ServerDevice',
            'item_id'     => $device->id,
            'note'        => $validated['note']
                ? "Set maintenance: {$device->device_name} — {$validated['note']}"
                : "Clear maintenance: {$device->device_name}",
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * PUT /server-operation/excluded  { device_id, is_excluded }
     */
    public function toggleExcluded(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id'   => 'required|integer|exists:server_devices,id',
            'is_excluded' => 'required|boolean',
        ]);

        $device = ServerDevice::findOrFail($validated['device_id']);
        $device->update(['is_excluded' => $validated['is_excluded']]);

        ActionLog::create([
            'user_id'     => $request->user()?->id,
            'action_type' => $validated['is_excluded'] ? 'exclude_device' : 'include_device',
            'item_type'   => 'ServerDevice',
            'item_id'     => $device->id,
            'note'        => ($validated['is_excluded'] ? 'Exclude' : 'Include') . " device dari hitungan: {$device->device_name}",
        ]);

        return response()->json(['is_excluded' => $device->is_excluded]);
    }

    /**
     * GET /server-operation/maintenance-logs?from=&to=&status=
     */
    public function maintenanceLogs(Request $request): JsonResponse
    {
        $from   = $request->query('from', now()->subDays(89)->toDateString());
        $to     = $request->query('to',   now()->toDateString());
        $status = $request->query('status');

        $openQuery = ServerMaintenanceLog::with(['device:id,device_name,ip_address,location,host_group', 'createdBy:id,name', 'closedBy:id,name'])
            ->where('status', 'open')
            ->orderByDesc('started_at');

        $closedQuery = ServerMaintenanceLog::with(['device:id,device_name,ip_address,location,host_group', 'createdBy:id,name', 'closedBy:id,name'])
            ->where('status', 'closed')
            ->where(fn ($q) => $q->whereBetween('started_at', [$from, $to])->orWhereBetween('resolved_at', [$from, $to]))
            ->orderByDesc('started_at');

        $logs = match ($status) {
            'open'   => $openQuery->get(),
            'closed' => $closedQuery->get(),
            default  => $openQuery->get()->merge($closedQuery->get())->sortByDesc('started_at')->values(),
        };

        return response()->json($logs->map(fn ($l) => $this->formatMaintenanceLog($l)));
    }

    /**
     * POST /server-operation/maintenance-logs
     */
    public function storeMaintenanceLog(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id'  => 'required|integer|exists:server_devices,id',
            'started_at' => 'required|date',
            'event_type' => 'required|in:maintenance,restart,down,auto_detected',
            'notes'      => 'nullable|string|max:1000',
        ]);

        $log = ServerMaintenanceLog::create([
            ...$validated,
            'status'     => 'open',
            'created_by' => $request->user()?->id,
        ]);

        $log->load(['device:id,device_name,ip_address,location,host_group', 'createdBy:id,name']);
        return response()->json($this->formatMaintenanceLog($log), 201);
    }

    /**
     * PUT /server-operation/maintenance-logs/{id}
     */
    public function updateMaintenanceLog(Request $request, int $id): JsonResponse
    {
        $log       = ServerMaintenanceLog::findOrFail($id);
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

    /**
     * DELETE /server-operation/maintenance-logs/{id}
     */
    public function destroyMaintenanceLog(int $id): JsonResponse
    {
        ServerMaintenanceLog::findOrFail($id)->delete();
        return response()->json(['ok' => true]);
    }

    /**
     * HDD column stores pipe-separated free-space labels ("D1: 12GB | D2: 5GB"), not a float.
     */
    private static function normalizeHddFreeLabel(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_string($raw)) {
            $s = trim($raw);

            return $s !== '' ? $s : null;
        }
        /* Legacy rows incorrectly cast/stored */
        if (is_numeric($raw)) {
            return (string) $raw;
        }

        return null;
    }

    private function formatMaintenanceLog($l): array
    {
        return [
            'id'            => $l->id,
            'device_id'     => $l->device_id,
            'device_name'   => $l->device?->device_name ?? 'Unknown',
            'ip_address'    => $l->device?->ip_address ?? '-',
            'location'      => $l->device?->location ?? '-',
            'host_group'    => $l->device?->host_group ?? '-',
            'status'        => $l->status,
            'event_type'    => $l->event_type,
            'started_at'    => $l->started_at?->toDateString(),
            'resolved_at'   => $l->resolved_at?->toDateString(),
            'notes'         => $l->notes,
            'is_auto'       => $l->is_auto,
            'created_by'    => $l->createdBy?->name ?? 'System',
            'closed_by'     => $l->closedBy?->name ?? '-',
            'duration'      => $l->durationLabel(),
            'created_at'    => $l->created_at?->toDateTimeString(),
        ];
    }
}

