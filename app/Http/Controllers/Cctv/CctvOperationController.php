<?php

namespace App\Http\Controllers\Cctv;

use App\Http\Controllers\Controller;
use App\Models\ActionLog;
use App\Models\CctvDevice;
use App\Models\CctvFetchLog;
use App\Models\CctvMaintenanceLog;
use App\Models\CctvNvrRecord;
use App\Models\CctvUptimeDaily;
use App\Services\CctvMonitorService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class CctvOperationController extends Controller
{
    public function __construct(private readonly CctvMonitorService $service) {}

    public function index(): Response
    {
        return Inertia::render('Report/CctvOperation/Index', [
            'sites' => CctvMonitorService::sites(),
        ]);
    }

    // ── DATA ─────────────────────────────────────────────────────────────────

    /**
     * GET /cctv-operation/data?from=&to=&type=cctv|finger|nvr&location=
     */
    public function data(Request $request): JsonResponse
    {
        $from     = $request->query('from', now()->subDays(29)->toDateString());
        $to       = $request->query('to',   now()->toDateString());
        $type     = $request->query('type', '');   // cctv | finger | nvr | '' = all
        $location = $request->query('location', '');

        $fromDate = Carbon::parse($from);
        $toDate   = Carbon::parse($to);

        $dailyDates = [];
        $cur = $fromDate->copy();
        while ($cur->lte($toDate)) { $dailyDates[] = $cur->toDateString(); $cur->addDay(); }

        $devQuery = CctvDevice::where('is_active', true)
            ->with(['uptimeDaily' => fn ($q) => $q->whereBetween('report_date', [$from, $to])->orderBy('report_date')])
            ->orderBy('location')->orderBy('device_type')->orderBy('device_name');

        if ($type)     $devQuery->where('device_type', $type);
        if ($location) $devQuery->where('location', $location);

        $devices = $devQuery->get();

        // Location summary — include 0% values, exclude is_excluded from avg
        $locSummary = [];
        foreach ($devices as $dev) {
            $loc = $dev->location ?? 'Unknown';
            if (!isset($locSummary[$loc])) {
                $locSummary[$loc] = ['location' => $loc, 'site' => $dev->site, 'total' => 0, 'avg_uptime' => null, 'values' => []];
            }
            $locSummary[$loc]['total']++;
            if (!$dev->is_excluded) {
                // Include 0% values — filter only null (no data), not 0
                $vals = $dev->uptimeDaily->pluck('uptime_percent')
                    ->filter(fn ($v) => $v !== null)->values();
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

        $rows = $devices->map(function ($dev) use ($dailyDates) {
            $dailyMap     = $dev->uptimeDaily->keyBy(fn ($d) => $d->report_date->toDateString());
            // Include 0% — filter only null, not zero
            $vals         = $dev->uptimeDaily->pluck('uptime_percent')->filter(fn ($v) => $v !== null)->values();
            $displayGroup = preg_replace('/^(F\d|R\d)\s+/', '', $dev->host_group ?? '');

            $daily = collect($dailyDates)->map(fn ($dateStr) => [
                'date'     => $dateStr,
                'uptime'   => $dailyMap->get($dateStr)?->uptime_percent,
                'status'   => $dailyMap->get($dateStr)?->status,
                'in_range' => true,
            ]);

            return [
                'id'               => $dev->id,
                'device_name'      => $dev->device_name,
                'ip_address'       => $dev->ip_address,
                'host_group'       => $dev->host_group,
                'display_group'    => $displayGroup,
                'device_type'      => $dev->device_type,
                'location'         => $dev->location,
                'site'             => $dev->site,
                'last_status'      => $dev->last_status,
                'avg_uptime'       => $dev->is_excluded ? null : ($vals->count() > 0 ? round($vals->avg(), 3) : null),
                'is_excluded'      => $dev->is_excluded,
                'maintenance_note' => $dev->maintenance_note,
                'maintenance_until'=> $dev->maintenance_until?->toDateString(),
                'in_maintenance'   => $dev->isInMaintenance(),
                'daily'            => $daily,
            ];
        });

        // Return distinct locations — use full location string which already includes R3/F1/F2/F3
        $allLocations = CctvDevice::where('is_active', true)
            ->when($type, fn ($q) => $q->where('device_type', $type))
            ->distinct()->orderBy('location')->pluck('location')->filter()->values();

        return response()->json([
            'from'        => $from,
            'to'          => $to,
            'daily_dates' => collect($dailyDates)->map(fn ($d) => ['date' => $d])->values(),
            'loc_summary' => array_values($locSummary),
            'devices'     => $rows,
            'locations'   => $allLocations,
        ]);
    }

    /**
     * GET /cctv-operation/export?from=&to=&summary=1&nvr=1&cctv=1&finger=1
     * Export selected tabs as separate sheets in one Excel file.
     */
    public function export(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $from      = $request->query('from', now()->subDays(29)->toDateString());
        $to        = $request->query('to',   now()->toDateString());
        $doSummary = $request->query('summary', '1') === '1';
        $doNvr     = $request->query('nvr',     '0') === '1';
        $doCctv    = $request->query('cctv',    '0') === '1';
        $doFinger  = $request->query('finger',  '0') === '1';

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        $hdrStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '003628']],
        ];

        if ($doSummary) {
            $ws = $spreadsheet->createSheet();
            $ws->setTitle('CCTV Operation Summary');
            $ws->getTabColor()->setRGB('003628');

            $ws->setCellValue('A1', 'CCTV Operation Summary');
            $ws->getStyle('A1')->applyFromArray(['font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '003628']]]);
            $ws->setCellValue('A2', "Periode: {$from} s/d {$to}");
            $ws->getStyle('A2')->applyFromArray(['font' => ['color' => ['rgb' => '64748B'], 'size' => 10]]);

            $row = 4;
            foreach (['nvr' => 'NVR', 'cctv' => 'CCTV', 'finger' => 'Fingerprint'] as $type => $label) {
                $ws->setCellValue('A' . $row, strtoupper($label) . ' UPTIME PER LOKASI');
                $ws->getStyle('A' . $row)->applyFromArray($hdrStyle);
                $ws->mergeCells("A{$row}:D{$row}");
                $row++;

                $ws->setCellValue('A' . $row, 'Lokasi');
                $ws->setCellValue('B' . $row, 'Avg Uptime %');
                $ws->setCellValue('C' . $row, 'Target');
                $ws->setCellValue('D' . $row, 'Total Device');
                $ws->getStyle("A{$row}:D{$row}")->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']],
                ]);
                $row++;

                $devices = CctvDevice::where('is_active', true)
                    ->where('device_type', $type)
                    ->with(['uptimeDaily' => fn ($q) => $q->whereBetween('report_date', [$from, $to])])
                    ->get();

                $locMap = [];
                foreach ($devices as $dev) {
                    $loc = $dev->location ?? 'Unknown';
                    if (!isset($locMap[$loc])) $locMap[$loc] = ['total' => 0, 'values' => []];
                    $locMap[$loc]['total']++;
                    if (!$dev->is_excluded) {
                        foreach ($dev->uptimeDaily as $d) {
                            if ($d->uptime_percent !== null) $locMap[$loc]['values'][] = $d->uptime_percent;
                        }
                    }
                }

                foreach ($locMap as $loc => $data) {
                    $avg   = count($data['values']) > 0 ? round(array_sum($data['values']) / count($data['values']), 2) : null;
                    $color = ($avg !== null && $avg >= 99.5) ? '059669' : (($avg !== null && $avg >= 95) ? 'D97706' : 'DC2626');
                    $ws->setCellValue('A' . $row, $loc);
                    $ws->setCellValue('B' . $row, $avg !== null ? $avg . '%' : '-');
                    $ws->setCellValue('C' . $row, '99.5%');
                    $ws->setCellValue('D' . $row, $data['total']);
                    $ws->getStyle('B' . $row)->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => $color]]]);
                    $row++;
                }
                $row += 2;
            }

            $ws->getColumnDimension('A')->setWidth(25);
            $ws->getColumnDimension('B')->setWidth(15);
            $ws->getColumnDimension('C')->setWidth(15);
            $ws->getColumnDimension('D')->setWidth(15);
        }

        // Export device data for each type
        foreach (['nvr' => 'NVR', 'cctv' => 'CCTV', 'finger' => 'Fingerprint'] as $type => $label) {
            $doExport = match ($type) {
                'nvr'    => $doNvr,
                'cctv'   => $doCctv,
                'finger' => $doFinger,
            };

            if (!$doExport) continue;

            $ws = $spreadsheet->createSheet();
            $ws->setTitle($label);
            $ws->getTabColor()->setRGB('003628');

            $ws->setCellValue('A1', strtoupper($label) . ' Uptime Report');
            $ws->getStyle('A1')->applyFromArray(['font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '003628']]]);
            $ws->setCellValue('A2', "Periode: {$from} s/d {$to}");
            $ws->getStyle('A2')->applyFromArray(['font' => ['color' => ['rgb' => '64748B'], 'size' => 10]]);

            $fromDate = Carbon::parse($from);
            $toDate   = Carbon::parse($to);
            $dailyDates = [];
            $cur = $fromDate->copy();
            while ($cur->lte($toDate)) { $dailyDates[] = $cur->toDateString(); $cur->addDay(); }

            $devices = CctvDevice::where('is_active', true)
                ->where('device_type', $type)
                ->with(['uptimeDaily' => fn ($q) => $q->whereBetween('report_date', [$from, $to])->orderBy('report_date')])
                ->orderBy('location')->orderBy('device_name')
                ->get();

            $row = 4;
            $ws->setCellValue('A' . $row, 'Lokasi');
            $ws->setCellValue('B' . $row, 'Device');
            $ws->setCellValue('C' . $row, 'IP Address');
            $ws->setCellValue('D' . $row, 'Group');
            $ws->setCellValue('E' . $row, 'Avg %');

            $col = 6;
            foreach ($dailyDates as $date) {
                $d = Carbon::parse($date);
                $ws->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . $row, $d->format('d M'));
                $col++;
            }

            $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col - 1);
            $ws->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']],
            ]);

            $row++;
            foreach ($devices as $dev) {
                $dailyMap = $dev->uptimeDaily->keyBy(fn ($d) => $d->report_date->toDateString());
                $vals = $dev->uptimeDaily->pluck('uptime_percent')->filter(fn ($v) => $v !== null)->values();
                $avg = $vals->count() > 0 ? round($vals->avg(), 2) : null;

                $ws->setCellValue('A' . $row, $dev->location);
                $ws->setCellValue('B' . $row, $dev->device_name);
                $ws->setCellValue('C' . $row, $dev->ip_address);
                $ws->setCellValue('D' . $row, preg_replace('/^(F\d|R\d)\s+/', '', $dev->host_group ?? ''));
                $ws->setCellValue('E' . $row, $avg !== null ? $avg . '%' : '-');

                $col = 6;
                foreach ($dailyDates as $date) {
                    $uptime = $dailyMap->get($date)?->uptime_percent;
                    $ws->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . $row, $uptime !== null ? $uptime . '%' : '-');
                    $col++;
                }

                $row++;
            }

            $ws->getColumnDimension('A')->setWidth(20);
            $ws->getColumnDimension('B')->setWidth(25);
            $ws->getColumnDimension('C')->setWidth(15);
            $ws->getColumnDimension('D')->setWidth(20);
            $ws->getColumnDimension('E')->setWidth(12);

            // Freeze columns A-E (Location, Device, IP, Group, Avg)
            $ws->freezePane('F5');
        }

        $filename = 'CCTV-Operation-' . now()->format('Y-m-d-His') . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * GET /cctv-operation/summary — overall KPI for Summary tab
     */
    public function summary(Request $request): JsonResponse
    {
        $from = $request->query('from', now()->subDays(29)->toDateString());
        $to   = $request->query('to',   now()->toDateString());

        $types = ['cctv', 'finger', 'nvr'];
        $result = [];

        foreach ($types as $type) {
            $devices = CctvDevice::where('is_active', true)
                ->where('device_type', $type)
                ->with(['uptimeDaily' => fn ($q) => $q->whereBetween('report_date', [$from, $to])])
                ->get();

            $locMap = [];
            foreach ($devices as $dev) {
                $loc = $dev->location ?? 'Unknown';
                if (!isset($locMap[$loc])) $locMap[$loc] = ['total' => 0, 'values' => []];
                $locMap[$loc]['total']++;
                if (!$dev->is_excluded) {
                    foreach ($dev->uptimeDaily as $d) {
                        if ($d->uptime_percent !== null) $locMap[$loc]['values'][] = $d->uptime_percent;
                    }
                }
            }

            $locSummary = [];
            foreach ($locMap as $loc => $data) {
                $avg = count($data['values']) > 0 ? round(array_sum($data['values']) / count($data['values']), 2) : null;
                $locSummary[] = ['location' => $loc, 'total' => $data['total'], 'avg_uptime' => $avg];
            }

            $allVals = array_merge(...array_column($locMap, 'values'));
            $overallAvg = count($allVals) > 0 ? round(array_sum($allVals) / count($allVals), 2) : null;

            $result[$type] = [
                'total_devices' => $devices->count(),
                'overall_avg'   => $overallAvg,
                'loc_summary'   => $locSummary,
            ];
        }

        // Open maintenance tickets
        $openTickets = CctvMaintenanceLog::with(['device:id,device_name,ip_address,location,host_group,device_type'])
            ->where('status', 'open')
            ->orderByDesc('started_at')
            ->get()
            ->map(fn ($l) => $this->formatMaintenanceLog($l));

        return response()->json([
            'cctv'         => $result['cctv'],
            'finger'       => $result['finger'],
            'nvr'          => $result['nvr'],
            'open_tickets' => $openTickets,
        ]);
    }

    // ── FETCH ─────────────────────────────────────────────────────────────────

    /**
     * POST /cctv-operation/fetch  { date }
     */
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

            ActionLog::create([
                'user_id'     => $userId,
                'action_type' => 'fetch',
                'item_type'   => 'CctvOperation',
                'item_id'     => null,
                'note'        => "Manual fetch CCTV: {$totalOk} OK, {$totalFail} gagal. Tanggal: {$date->toDateString()}",
                'log_meta'    => ['date' => $date->toDateString(), 'results' => $results],
            ]);

            return response()->json([
                'message' => "Fetch selesai: {$totalOk} device OK, {$totalFail} gagal.",
                'results' => $results,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Fetch gagal: ' . $e->getMessage()], 500);
        }
    }

    // ── FETCH LOGS ────────────────────────────────────────────────────────────

    /**
     * GET /cctv-operation/logs?type=
     */
    public function logs(Request $request): JsonResponse
    {
        $type = $request->query('type', '');

        $query = CctvFetchLog::with('triggeredBy:id,name')
            ->orderByDesc('fetch_date')->orderByDesc('id')
            ->limit(100);

        if ($type) $query->where('device_type', $type);

        return response()->json($query->get()->map(fn ($l) => [
            'id'           => $l->id,
            'fetch_date'   => $l->fetch_date->toDateString(),
            'source'       => $l->source,
            'source_instance' => $l->source_instance,
            'device_type'  => $l->device_type,
            'group_name'   => $l->group_name,
            'status'       => $l->status,
            'devices_ok'   => $l->devices_ok,
            'devices_fail' => $l->devices_fail,
            'notes'        => $l->notes,
            'is_manual'    => $l->is_manual,
            'triggered_by' => $l->triggeredBy?->name ?? 'Cron',
            'created_at'   => $l->created_at?->format('d M Y H:i'),
        ]));
    }

    // ── MAINTENANCE ───────────────────────────────────────────────────────────

    /**
     * PUT /cctv-operation/maintenance  { device_id, note, until? }
     */
    public function updateMaintenance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => 'required|integer|exists:cctv_devices,id',
            'note'      => 'nullable|string|max:500',
            'until'     => 'nullable|date',
        ]);

        $device = CctvDevice::findOrFail($validated['device_id']);
        $device->update([
            'maintenance_note'  => $validated['note'] ?? null,
            'maintenance_until' => $validated['until'] ?? null,
        ]);

        ActionLog::create([
            'user_id'     => $request->user()?->id,
            'action_type' => $validated['note'] ? 'set_maintenance' : 'clear_maintenance',
            'item_type'   => 'CctvDevice',
            'item_id'     => $device->id,
            'note'        => $validated['note']
                ? "Set maintenance: {$device->device_name} — {$validated['note']}"
                : "Clear maintenance: {$device->device_name}",
            'log_meta'    => ['device' => $device->device_name, 'note' => $validated['note'], 'until' => $validated['until'] ?? null],
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * PUT /cctv-operation/excluded  { device_id, is_excluded }
     */
    public function toggleExcluded(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id'   => 'required|integer|exists:cctv_devices,id',
            'is_excluded' => 'required|boolean',
        ]);

        $device = CctvDevice::findOrFail($validated['device_id']);
        $device->update(['is_excluded' => $validated['is_excluded']]);

        ActionLog::create([
            'user_id'     => $request->user()?->id,
            'action_type' => $validated['is_excluded'] ? 'exclude_device' : 'include_device',
            'item_type'   => 'CctvDevice',
            'item_id'     => $device->id,
            'note'        => ($validated['is_excluded'] ? 'Exclude' : 'Include') . " device dari hitungan: {$device->device_name}",
            'log_meta'    => ['device' => $device->device_name, 'is_excluded' => $validated['is_excluded']],
        ]);

        return response()->json(['is_excluded' => $device->is_excluded]);
    }

    // ── MAINTENANCE LOGS ──────────────────────────────────────────────────────

    /**
     * GET /cctv-operation/maintenance-logs?from=&to=&type=&status=
     */
    public function maintenanceLogs(Request $request): JsonResponse
    {
        $from   = $request->query('from', now()->subDays(89)->toDateString());
        $to     = $request->query('to',   now()->toDateString());
        $type   = $request->query('type', '');
        $status = $request->query('status');

        $openQuery = CctvMaintenanceLog::with(['device:id,device_name,ip_address,location,host_group,device_type', 'createdBy:id,name', 'closedBy:id,name'])
            ->where('status', 'open')
            ->when($type, fn ($q) => $q->whereHas('device', fn ($dq) => $dq->where('device_type', $type)))
            ->orderByDesc('started_at');

        $closedQuery = CctvMaintenanceLog::with(['device:id,device_name,ip_address,location,host_group,device_type', 'createdBy:id,name', 'closedBy:id,name'])
            ->where('status', 'closed')
            ->when($type, fn ($q) => $q->whereHas('device', fn ($dq) => $dq->where('device_type', $type)))
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
     * POST /cctv-operation/maintenance-logs
     */
    public function storeMaintenanceLog(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id'  => 'required|integer|exists:cctv_devices,id',
            'started_at' => 'required|date',
            'event_type' => 'required|in:maintenance,restart,down,auto_detected',
            'notes'      => 'nullable|string|max:1000',
        ]);

        $log = CctvMaintenanceLog::create([
            ...$validated,
            'status'     => 'open',
            'created_by' => $request->user()?->id,
        ]);

        $log->load(['device:id,device_name,ip_address,location,host_group,device_type', 'createdBy:id,name']);
        return response()->json($this->formatMaintenanceLog($log), 201);
    }

    /**
     * PUT /cctv-operation/maintenance-logs/{id}
     */
    public function updateMaintenanceLog(Request $request, int $id): JsonResponse
    {
        $log       = CctvMaintenanceLog::findOrFail($id);
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
        $log->load(['device:id,device_name,ip_address,location,host_group,device_type', 'createdBy:id,name', 'closedBy:id,name']);
        return response()->json($this->formatMaintenanceLog($log));
    }

    /**
     * DELETE /cctv-operation/maintenance-logs/{id}
     */
    public function destroyMaintenanceLog(int $id): JsonResponse
    {
        CctvMaintenanceLog::findOrFail($id)->delete();
        return response()->json(['ok' => true]);
    }

    // ── NVR DURATION RECORD ───────────────────────────────────────────────────

    /**
     * GET /cctv-operation/nvr-records?from=&to=
     * Returns monthly duration record grid for NVR devices
     */
    public function nvrRecords(Request $request): JsonResponse
    {
        $from = $request->query('from', now()->subDays(29)->toDateString());
        $to   = $request->query('to',   now()->toDateString());

        $fromDate = Carbon::parse($from)->startOfMonth();
        $toDate   = Carbon::parse($to)->startOfMonth();

        $months = [];
        $cur = $fromDate->copy();
        while ($cur->lte($toDate)) {
            $months[] = ['year' => $cur->year, 'month' => $cur->month, 'label' => $cur->format('M y')];
            $cur->addMonth();
        }

        $devices = CctvDevice::where('is_active', true)
            ->where('device_type', 'nvr')
            ->orderBy('location')->orderBy('device_name')
            ->get();

        $deviceIds = $devices->pluck('id');
        $records   = CctvNvrRecord::whereIn('device_id', $deviceIds)
            ->whereIn('year', array_unique(array_column($months, 'year')))
            ->get()
            ->groupBy(fn ($r) => $r->device_id . '|' . $r->year . '|' . $r->month);

        $grid = $devices->map(function ($dev) use ($months, $records) {
            $monthData = collect($months)->map(function ($m) use ($dev, $records) {
                $key    = $dev->id . '|' . $m['year'] . '|' . $m['month'];
                $record = $records->get($key)?->first();
                return [
                    'year'             => $m['year'],
                    'month'            => $m['month'],
                    'label'            => $m['label'],
                    'check_date'       => $record?->check_date?->toDateString(),
                    'last_record_date' => $record?->last_record_date?->toDateString(),
                    'duration_days'    => $record?->duration_days,
                    'notes'            => $record?->notes,
                ];
            });

            return [
                'device_id'    => $dev->id,
                'device_name'  => $dev->device_name,
                'ip_address'   => $dev->ip_address,
                'location'     => $dev->location,
                'display_group'=> preg_replace('/^(F\d|R\d)\s+/', '', $dev->host_group ?? ''),
            ] + ['months' => $monthData];
        });

        return response()->json(['months' => $months, 'grid' => $grid]);
    }

    /**
     * PUT /cctv-operation/nvr-records
     * Upsert a monthly NVR duration record
     */
    public function updateNvrRecord(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id'        => 'required|integer|exists:cctv_devices,id',
            'year'             => 'required|integer|min:2020|max:2099',
            'month'            => 'required|integer|min:1|max:12',
            'check_date'       => 'required|date',
            'last_record_date' => 'required|date',
            'notes'            => 'nullable|string|max:500',
        ]);

        $checkDate      = Carbon::parse($validated['check_date']);
        $lastRecordDate = Carbon::parse($validated['last_record_date']);
        $durationDays   = (int) $lastRecordDate->diffInDays($checkDate);

        $record = CctvNvrRecord::updateOrCreate(
            [
                'device_id' => $validated['device_id'],
                'year'      => $validated['year'],
                'month'     => $validated['month'],
            ],
            [
                'check_date'       => $validated['check_date'],
                'last_record_date' => $validated['last_record_date'],
                'duration_days'    => $durationDays,
                'notes'            => $validated['notes'] ?? null,
                'updated_by'       => $request->user()?->id,
            ]
        );

        $device = CctvDevice::find($validated['device_id']);
        ActionLog::create([
            'user_id'     => $request->user()?->id,
            'action_type' => 'update_nvr_record',
            'item_type'   => 'CctvNvrRecord',
            'item_id'     => $record->id,
            'note'        => "Update NVR record: {$device?->device_name} {$validated['year']}/{$validated['month']} — {$durationDays} hari",
            'log_meta'    => $validated + ['duration_days' => $durationDays],
        ]);

        return response()->json([
            'check_date'       => $record->check_date->toDateString(),
            'last_record_date' => $record->last_record_date->toDateString(),
            'duration_days'    => $record->duration_days,
        ]);
    }

    private function formatMaintenanceLog(CctvMaintenanceLog $l): array
    {
        $displayGroup = preg_replace('/^(F\d|R\d)\s+/', '', $l->device?->host_group ?? '');
        return [
            'id'           => $l->id,
            'device_id'    => $l->device_id,
            'device_name'  => $l->device?->device_name,
            'ip_address'   => $l->device?->ip_address,
            'location'     => $l->device?->location,
            'device_type'  => $l->device?->device_type,
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
