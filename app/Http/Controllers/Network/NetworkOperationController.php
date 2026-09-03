<?php

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Models\BandwidthDaily;
use App\Models\NetworkDevice;
use App\Models\NetworkUptimeDaily;
use App\Models\IspSlaContract;
use App\Models\IspSlaMonthly;
use App\Models\IspDownHistory;
use App\Services\PrtgService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class NetworkOperationController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Report/NetworkOperation/Index', [
            'locations' => PrtgService::locations(),
        ]);
    }

    /**
     * GET /network-operation/export?from=&to=&bandwidth=1&uptime=1&isp=1
     * Export selected tabs as separate sheets in one Excel file.
     */
    public function export(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $from      = $request->query('from', now()->subDays(29)->toDateString());
        $to        = $request->query('to',   now()->toDateString());
        $doBw      = $request->query('bandwidth', '1') === '1';
        $doUptime  = $request->query('uptime',    '0') === '1';
        $doIsp     = $request->query('isp',       '0') === '1';
        $doSummary = $request->query('summary',   '0') === '1';

        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0); // remove default sheet

        $hdrStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '003628']],
        ];

        if ($doSummary) {
            $ws = $spreadsheet->createSheet();
            $ws->setTitle('Network Operation Summary');
            $ws->getTabColor()->setRGB('1D4ED8');

            $ws->setCellValue('A1', 'Network Operation Summary');
            $ws->getStyle('A1')->applyFromArray(['font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '003628']]]);
            $ws->setCellValue('A2', "Periode: {$from} s/d {$to}");
            $ws->getStyle('A2')->applyFromArray(['font' => ['color' => ['rgb' => '64748B'], 'size' => 10]]);

            // ── SECTION 1: UPTIME PER LOKASI ─────────────────────────────────
            $row = 4;
            $ws->setCellValue('A' . $row, 'UPTIME PER LOKASI');
            $ws->getStyle('A' . $row)->applyFromArray($hdrStyle);
            $ws->mergeCells("A{$row}:D{$row}");
            $row++;

            $ws->setCellValue('A' . $row, 'Lokasi');
            $ws->setCellValue('B' . $row, 'Avg Uptime %');
            $ws->setCellValue('C' . $row, 'Target');
            $ws->setCellValue('D' . $row, 'Total Device');
            $ws->getStyle("A{$row}:D{$row}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']],
            ]);
            $row++;

            $devices = \App\Models\NetworkDevice::where('is_active', true)
                ->with(['uptimeDaily' => fn ($q) => $q->whereBetween('report_date', [$from, $to])])
                ->get();
            $locMap = [];
            foreach ($devices as $dev) {
                $loc = $dev->location ?? 'Unknown';
                if (!isset($locMap[$loc])) $locMap[$loc] = ['total' => 0, 'values' => []];
                $locMap[$loc]['total']++;
                foreach ($dev->uptimeDaily as $d) {
                    if ($d->uptime_percent !== null) $locMap[$loc]['values'][] = $d->uptime_percent;
                }
            }
            foreach ($locMap as $loc => $data) {
                $avg   = count($data['values']) > 0 ? round(array_sum($data['values']) / count($data['values']), 2) : null;
                $color = ($avg !== null && $avg >= 90) ? '059669' : 'DC2626';
                $ws->setCellValue('A' . $row, $loc);
                $ws->setCellValue('B' . $row, $avg !== null ? $avg . '%' : '-');
                $ws->setCellValue('C' . $row, '90%');
                $ws->setCellValue('D' . $row, $data['total']);
                $ws->getStyle('B' . $row)->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => $color]]]);
                $row++;
            }

            $row += 2;

            // ── SECTION 2: ISP SLA PER LOKASI ────────────────────────────────
            $ws->setCellValue('A' . $row, 'ISP SLA PER LOKASI');
            $ws->getStyle('A' . $row)->applyFromArray($hdrStyle);
            $ws->mergeCells("A{$row}:D{$row}");
            $row++;

            $ws->setCellValue('A' . $row, 'Lokasi');
            $ws->setCellValue('B' . $row, 'Avg SLA %');
            $ws->setCellValue('C' . $row, 'Target');
            $ws->setCellValue('D' . $row, 'Status');
            $ws->getStyle("A{$row}:D{$row}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']],
            ]);
            $row++;

            $contracts = \App\Models\IspSlaContract::where('is_active', true)->get();
            $ispMonths = [];
            $cursor = \Carbon\Carbon::parse($from)->startOfMonth();
            while ($cursor->lte(\Carbon\Carbon::parse($to))) {
                $ispMonths[] = ['year' => $cursor->year, 'month' => $cursor->month];
                $cursor->addMonth();
            }
            $actuals = \App\Models\IspSlaMonthly::whereIn('contract_id', $contracts->pluck('id'))->get()
                ->groupBy(fn ($r) => $r->contract_id . '_' . $r->year . '_' . $r->month);
            $ispLocMap = [];
            foreach ($contracts as $c) {
                $loc = $c->location;
                if (!isset($ispLocMap[$loc])) $ispLocMap[$loc] = ['values' => [], 'target' => $c->target_pct];
                foreach ($ispMonths as $m) {
                    $key    = $c->id . '_' . $m['year'] . '_' . $m['month'];
                    $actual = $actuals->get($key)?->first();
                    if ($actual?->uptime_pct !== null) $ispLocMap[$loc]['values'][] = $actual->uptime_pct;
                }
            }
            foreach ($ispLocMap as $loc => $data) {
                $avg   = count($data['values']) > 0 ? round(array_sum($data['values']) / count($data['values']), 3) : null;
                $onSla = $avg !== null ? ($avg >= $data['target'] ? 'On SLA' : 'Breach') : 'No Data';
                $color = $avg !== null ? ($avg >= $data['target'] ? '059669' : 'DC2626') : '94A3B8';
                $ws->setCellValue('A' . $row, $loc);
                $ws->setCellValue('B' . $row, $avg !== null ? $avg . '%' : '-');
                $ws->setCellValue('C' . $row, $data['target'] . '%');
                $ws->setCellValue('D' . $row, $onSla);
                $ws->getStyle('B' . $row)->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => $color]]]);
                $ws->getStyle('D' . $row)->applyFromArray(['font' => ['color' => ['rgb' => $color]]]);
                $row++;
            }

            $row += 2;

            // ── SECTION 3: BANDWIDTH PER LOKASI ──────────────────────────────
            $ws->setCellValue('A' . $row, 'BANDWIDTH PER LOKASI');
            $ws->getStyle('A' . $row)->applyFromArray($hdrStyle);
            $ws->mergeCells("A{$row}:D{$row}");
            $row++;

            $ws->setCellValue('A' . $row, 'Lokasi');
            $ws->setCellValue('B' . $row, 'Provider');
            $ws->setCellValue('C' . $row, 'Avg DL (Mbps)');
            $ws->setCellValue('D' . $row, 'Avg UL (Mbps)');
            $ws->getStyle("A{$row}:D{$row}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']],
            ]);
            $row++;

            $bwRows = \App\Models\BandwidthDaily::query()
                ->selectRaw('location, provider, description, AVG(value_mbps) as avg_mbps')
                ->whereBetween('report_date', [$from, $to])
                ->groupBy('location', 'provider', 'description')
                ->orderBy('location')->orderBy('provider')
                ->get();
            $bwMap = [];
            foreach ($bwRows as $bw) {
                $key = $bw->location . '|' . $bw->provider;
                if (!isset($bwMap[$key])) $bwMap[$key] = ['location' => $bw->location, 'provider' => $bw->provider, 'dl' => null, 'ul' => null];
                if (str_contains($bw->description, 'Download')) $bwMap[$key]['dl'] = round($bw->avg_mbps, 2);
                if (str_contains($bw->description, 'Upload'))   $bwMap[$key]['ul'] = round($bw->avg_mbps, 2);
            }
            foreach ($bwMap as $entry) {
                $ws->setCellValue('A' . $row, $entry['location']);
                $ws->setCellValue('B' . $row, $entry['provider']);
                $ws->setCellValue('C' . $row, $entry['dl'] !== null ? $entry['dl'] . ' Mbps' : '-');
                $ws->setCellValue('D' . $row, $entry['ul'] !== null ? $entry['ul'] . ' Mbps' : '-');
                $row++;
            }

            $row += 2;

            // ── SECTION 4: BACKUP DEVICE ──────────────────────────────────────
            $ws->setCellValue('A' . $row, 'BACKUP DEVICE');
            $ws->getStyle('A' . $row)->applyFromArray($hdrStyle);
            $ws->mergeCells("A{$row}:D{$row}");
            $row++;

            $ws->setCellValue('A' . $row, 'Device');
            $ws->setCellValue('B' . $row, 'Lokasi');
            $ws->setCellValue('C' . $row, 'Group');
            $ws->setCellValue('D' . $row, 'Status Backup');
            $ws->getStyle("A{$row}:D{$row}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']],
            ]);
            $row++;

            // Build month list for backup lookup
            $sumBkpMonths = [];
            $bkpCursor = \Carbon\Carbon::parse($from)->startOfMonth();
            while ($bkpCursor->lte(\Carbon\Carbon::parse($to))) {
                $sumBkpMonths[] = ['year' => $bkpCursor->year, 'month' => $bkpCursor->month];
                $bkpCursor->addMonth();
            }

            $backupDevicesSum = \App\Models\NetworkDevice::where('is_active', true)
                ->where('monitor_backup', true)
                ->with(['backupMonthly' => fn ($q) => $q->whereIn('year', array_unique(array_column($sumBkpMonths, 'year')))])
                ->orderBy('location')->orderBy('device_name')
                ->get();

            $totalBackup  = $backupDevicesSum->count();
            $okCount      = 0;
            $missingCount = 0;

            foreach ($backupDevicesSum as $dev) {
                $group     = preg_replace('/^(F\d|R\d)\s+/', '', $dev->host_group ?? '');
                $backupMap = $dev->backupMonthly->keyBy(fn ($b) => $b->year . '_' . $b->month);

                $allOk  = true;
                $hasAny = false;
                foreach ($sumBkpMonths as $m) {
                    $record = $backupMap->get($m['year'] . '_' . $m['month']);
                    if ($record) {
                        $hasAny = true;
                        if (!$record->has_backup) $allOk = false;
                    }
                }
                $statusLabel = !$hasAny ? 'No Data' : ($allOk ? 'OK' : 'MISSING');
                $statusColor = !$hasAny ? '94A3B8' : ($allOk ? '059669' : 'DC2626');
                if ($hasAny && $allOk) $okCount++;
                elseif ($hasAny && !$allOk) $missingCount++;

                $ws->setCellValue('A' . $row, $dev->device_name);
                $ws->setCellValue('B' . $row, $dev->location);
                $ws->setCellValue('C' . $row, $group);
                $ws->setCellValue('D' . $row, $statusLabel);
                $ws->getStyle('D' . $row)->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => $statusColor]]]);
                if ($row % 2 === 0) {
                    $ws->getStyle("A{$row}:D{$row}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8FAFB']],
                    ]);
                }
                $row++;
            }

            // Totals row
            $ws->setCellValue('A' . $row, 'Total: ' . $totalBackup . ' device');
            $ws->setCellValue('C' . $row, 'OK: ' . $okCount);
            $ws->setCellValue('D' . $row, 'Missing: ' . $missingCount);
            $ws->getStyle('A' . $row)->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => '003628']]]);
            $ws->getStyle('C' . $row)->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => '059669']]]);
            $ws->getStyle('D' . $row)->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => 'DC2626']]]);

            // Column widths
            $ws->getColumnDimension('A')->setWidth(34);
            $ws->getColumnDimension('B')->setWidth(18);
            $ws->getColumnDimension('C')->setWidth(16);
            $ws->getColumnDimension('D')->setWidth(16);
        }

        if ($doBw) {
            $bwRows = BandwidthDaily::query()
                ->selectRaw('report_date, location, provider, description, MAX(value_mbps) as value_mbps')
                ->whereBetween('report_date', [$from, $to])
                ->groupBy('report_date', 'location', 'provider', 'description')
                ->orderBy('location')->orderBy('provider')->orderBy('report_date')
                ->get();

            // All dates in range
            $allDates = [];
            $cur = Carbon::parse($from);
            while ($cur->lte(Carbon::parse($to))) {
                $allDates[] = $cur->toDateString();
                $cur->addDay();
            }
            $nDates = count($allDates);

            // pivot[location|provider][date][desc] = value
            $pivot     = [];
            $seriesMap = []; // "location provider description" => [date => value]
            $locSummary = []; // location => [{provider, avg_dl, max_dl, avg_ul, max_ul}]

            foreach ($bwRows as $bwRow) {
                $date = $bwRow->report_date->toDateString();
                $pKey = $bwRow->location . '|' . $bwRow->provider;
                if (!isset($pivot[$pKey])) {
                    $pivot[$pKey] = ['location' => $bwRow->location, 'provider' => $bwRow->provider, 'data' => []];
                }
                $pivot[$pKey]['data'][$date][$bwRow->description] = (float) $bwRow->value_mbps;

                $sKey = $bwRow->location . ' ' . $bwRow->provider . ' ' . $bwRow->description;
                if (!isset($seriesMap[$sKey])) $seriesMap[$sKey] = [];
                $seriesMap[$sKey][$date] = (float) $bwRow->value_mbps;
            }

            // Build location summary cards
            foreach ($pivot as $p) {
                $loc   = $p['location'];
                $dlVals = array_filter(array_map(fn ($d) => $d['Download (Mbps)'] ?? null, $p['data']));
                $ulVals = array_filter(array_map(fn ($d) => $d['Upload (Mbps)'] ?? null, $p['data']));
                if (!isset($locSummary[$loc])) $locSummary[$loc] = [];
                $locSummary[$loc][] = [
                    'provider' => $p['provider'],
                    'avg_dl'   => count($dlVals) > 0 ? round(array_sum($dlVals) / count($dlVals), 2) : null,
                    'max_dl'   => count($dlVals) > 0 ? round(max($dlVals), 2) : null,
                    'avg_ul'   => count($ulVals) > 0 ? round(array_sum($ulVals) / count($ulVals), 2) : null,
                    'max_ul'   => count($ulVals) > 0 ? round(max($ulVals), 2) : null,
                ];
            }

            $ws1 = $spreadsheet->createSheet();
            $ws1->setTitle('Bandwidth Summary');
            $ws1->getTabColor()->setRGB('0369A1');
            $ws1->setCellValue('A1', 'Bandwidth Usage - Summary');
            $ws1->getStyle('A1')->applyFromArray(['font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '003628']]]);
            $ws1->setCellValue('A2', "Periode: {$from} s/d {$to}");
            $ws1->getStyle('A2')->applyFromArray(['font' => ['color' => ['rgb' => '64748B'], 'size' => 10]]);

            // Location cards side by side: A-D, F-I, K-N (gap col E, J)
            $cardStartRow = 4;
            $cardColStarts = [1, 6, 11]; // col index A=1, F=6, K=11
            $locKeys = array_keys($locSummary);
            $maxCardDataRows = 0;

            foreach ($locKeys as $li => $loc) {
                $sc   = $cardColStarts[$li] ?? ($li * 5 + 1);
                $colA = Coordinate::stringFromColumnIndex($sc);
                $colB = Coordinate::stringFromColumnIndex($sc + 1);
                $colC = Coordinate::stringFromColumnIndex($sc + 2);
                $colD = Coordinate::stringFromColumnIndex($sc + 3);

                // Location header
                $ws1->setCellValue($colA . $cardStartRow, $loc);
                $ws1->getStyle($colA . $cardStartRow)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '003628']],
                ]);
                $ws1->mergeCells("{$colA}{$cardStartRow}:{$colD}{$cardStartRow}");

                // Sub-header
                $r = $cardStartRow + 1;
                $ws1->setCellValue($colA . $r, 'Provider / Desc');
                $ws1->setCellValue($colB . $r, 'AVG');
                $ws1->setCellValue($colC . $r, 'MAX');
                $ws1->getStyle("{$colA}{$r}:{$colC}{$r}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '64748B']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']],
                ]);

                // Data rows: DL then UL per provider
                $r++;
                $dataRows = 0;
                foreach ($locSummary[$loc] as $s) {
                    $ws1->setCellValue($colA . $r, $s['provider'] . ' Download');
                    $ws1->setCellValue($colB . $r, $s['avg_dl'] !== null ? $s['avg_dl'] . ' Mbps' : '-');
                    $ws1->setCellValue($colC . $r, $s['max_dl'] !== null ? $s['max_dl'] . ' Mbps' : '-');
                    $ws1->getStyle($colC . $r)->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => 'DC2626']]]);
                    $r++; $dataRows++;

                    $ws1->setCellValue($colA . $r, $s['provider'] . ' Upload');
                    $ws1->setCellValue($colB . $r, $s['avg_ul'] !== null ? $s['avg_ul'] . ' Mbps' : '-');
                    $ws1->setCellValue($colC . $r, $s['max_ul'] !== null ? $s['max_ul'] . ' Mbps' : '-');
                    $ws1->getStyle($colC . $r)->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => '0369A1']]]);
                    $r++; $dataRows++;
                }
                $maxCardDataRows = max($maxCardDataRows, $dataRows);

                $ws1->getColumnDimension($colA)->setWidth(24);
                $ws1->getColumnDimension($colB)->setWidth(10);
                $ws1->getColumnDimension($colC)->setWidth(10);
            }

            // Gap col between cards
            foreach ([5, 10] as $gapCol) {
                $ws1->getColumnDimension(Coordinate::stringFromColumnIndex($gapCol))->setWidth(2);
            }

            // Chart starts 3 rows after cards
            $chartTopRow    = $cardStartRow + $maxCardDataRows + 4; // +2 header rows + 3 gap
            $chartBottomRow = $chartTopRow + 22;

            $sChart = $spreadsheet->createSheet();
            $sChart->setTitle('_BwChart');
            $sChart->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);

            // Row 1: date labels
            $sChart->setCellValue('A1', 'Series');
            foreach ($allDates as $di => $date) {
                $col = Coordinate::stringFromColumnIndex($di + 2);
                $sChart->setCellValue($col . '1', Carbon::parse($date)->format('d M'));
            }

            // Rows 2+: one per series
            $chartDataRows = [];
            $sr = 2;
            foreach ($seriesMap as $seriesName => $dateValues) {
                $sChart->setCellValue('A' . $sr, $seriesName);
                foreach ($allDates as $di => $date) {
                    $col = Coordinate::stringFromColumnIndex($di + 2);
                    if (isset($dateValues[$date])) {
                        $sChart->setCellValue($col . $sr, $dateValues[$date]);
                    }
                }
                $chartDataRows[] = $sr;
                $sr++;
            }

            $lastDateCol = Coordinate::stringFromColumnIndex($nDates + 1);
            $nSeries     = count($chartDataRows);

            if ($nSeries > 0) {
                $seriesNames  = [];
                $xLabels      = [];
                $seriesValues = [];

                foreach ($chartDataRows as $chartRow) {
                    $seriesNames[]  = new \PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues(
                        'String', '_BwChart!$A$' . $chartRow, null, 1
                    );
                    $xLabels[]      = new \PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues(
                        'String', '_BwChart!$B$1:$' . $lastDateCol . '$1', null, $nDates
                    );
                    $seriesValues[] = new \PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues(
                        'Number', '_BwChart!$B$' . $chartRow . ':$' . $lastDateCol . '$' . $chartRow, null, $nDates
                    );
                }

                $dataSeries = new \PhpOffice\PhpSpreadsheet\Chart\DataSeries(
                    \PhpOffice\PhpSpreadsheet\Chart\DataSeries::TYPE_LINECHART,
                    \PhpOffice\PhpSpreadsheet\Chart\DataSeries::GROUPING_STANDARD,
                    range(0, $nSeries - 1),
                    $seriesNames, $xLabels, $seriesValues
                );

                $chart = new \PhpOffice\PhpSpreadsheet\Chart\Chart(
                    'bw_chart',
                    new \PhpOffice\PhpSpreadsheet\Chart\Title('Bandwidth Usage (Mbps)'),
                    new \PhpOffice\PhpSpreadsheet\Chart\Legend(
                        \PhpOffice\PhpSpreadsheet\Chart\Legend::POSITION_BOTTOM, null, false
                    ),
                    new \PhpOffice\PhpSpreadsheet\Chart\PlotArea(null, [$dataSeries]),
                    true,
                    \PhpOffice\PhpSpreadsheet\Chart\DataSeries::EMPTY_AS_GAP
                );

                $chart->setTopLeftPosition('A' . $chartTopRow);
                $chart->setBottomRightPosition('N' . $chartBottomRow);
                $ws1->addChart($chart);
            }

            $ws2 = $spreadsheet->createSheet();
            $ws2->setTitle('Bandwidth Data');
            $ws2->getTabColor()->setRGB('0369A1');

            // Title
            $ws2->setCellValue('A1', 'Bandwidth Data - Detail');
            $ws2->getStyle('A1')->applyFromArray(['font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '003628']]]);
            $ws2->setCellValue('A2', "Periode: {$from} s/d {$to}");
            $ws2->getStyle('A2')->applyFromArray(['font' => ['color' => ['rgb' => '64748B'], 'size' => 10]]);

            // Header: Lokasi | Provider | Tipe | AVG | date1 | date2 | ...
            $ws2->setCellValue('A4', 'Lokasi');
            $ws2->setCellValue('B4', 'Provider');
            $ws2->setCellValue('C4', 'Tipe');
            $ws2->setCellValue('D4', 'AVG');
            $ci = 5;
            foreach ($allDates as $date) {
                $col = Coordinate::stringFromColumnIndex($ci);
                $ws2->setCellValue($col . '4', Carbon::parse($date)->format('d M'));
                $ci++;
            }
            $lastDataCol = Coordinate::stringFromColumnIndex($ci - 1);
            $ws2->getStyle("A4:{$lastDataCol}4")->applyFromArray($hdrStyle);

            // Data rows
            $rowNum = 5;
            foreach ($pivot as $p) {
                foreach (['Download (Mbps)' => 'Download', 'Upload (Mbps)' => 'Upload'] as $desc => $label) {
                    $vals = array_filter(array_map(fn ($d) => $d[$desc] ?? null, $p['data']));
                    $avg  = count($vals) > 0 ? round(array_sum($vals) / count($vals), 2) : null;

                    $ws2->setCellValue('A' . $rowNum, $p['location']);
                    $ws2->setCellValue('B' . $rowNum, $p['provider']);
                    $ws2->setCellValue('C' . $rowNum, $label);
                    $ws2->setCellValue('D' . $rowNum, $avg !== null ? $avg . ' Mbps' : '-');

                    $c = 5;
                    foreach ($allDates as $date) {
                        $col = Coordinate::stringFromColumnIndex($c);
                        $val = $p['data'][$date][$desc] ?? null;
                        $ws2->setCellValue($col . $rowNum, $val !== null ? $val . ' Mbps' : '-');
                        $c++;
                    }

                    // Alternate row shading
                    if ($rowNum % 2 === 0) {
                        $ws2->getStyle("A{$rowNum}:{$lastDataCol}{$rowNum}")->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8FAFB']],
                        ]);
                    }
                    $rowNum++;
                }
            }

            // Column widths — wider to accommodate "X Mbps" values
            $ws2->getColumnDimension('A')->setWidth(20);
            $ws2->getColumnDimension('B')->setWidth(14);
            $ws2->getColumnDimension('C')->setWidth(12);
            $ws2->getColumnDimension('D')->setWidth(16);
            for ($c = 5; $c < $ci; $c++) {
                $ws2->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setWidth(14);
            }
            $ws2->freezePane('E5');
        }
        if ($doUptime) {
            $fromDate = Carbon::parse($from);
            $toDate   = Carbon::parse($to);
            $dates    = [];
            $cur      = $fromDate->copy();
            while ($cur->lte($toDate)) { $dates[] = $cur->toDateString(); $cur->addDay(); }
            $nDates = count($dates);

            // Build month list for backup sheet
            $months = [];
            $cur = $fromDate->copy()->startOfMonth();
            while ($cur->lte($toDate)) {
                $months[] = ['year' => $cur->year, 'month' => $cur->month, 'label' => $cur->format('M y')];
                $cur->addMonth();
            }

            // Load all active devices with uptime data — exclude is_excluded from avg/export
            $devices = NetworkDevice::where('is_active', true)
                ->where('is_excluded', false)
                ->with([
                    'uptimeDaily' => fn ($q) => $q->whereBetween('report_date', [$from, $to])->orderBy('report_date'),
                    'backupMonthly' => fn ($q) => $q->whereIn('year', array_unique(array_column($months, 'year'))),
                ])
                ->orderBy('location')->orderBy('host_group')->orderBy('device_name')
                ->get();

            // ════════════════════════════════════════════════════════════════
            // SHEET 1 — Network Uptime Summary
            // Layout: Uptime Summary (top) → gap → Uptime Performance (below)
            // ════════════════════════════════════════════════════════════════
            $wsU = $spreadsheet->createSheet();
            $wsU->setTitle('Network Uptime Summary');
            $wsU->getTabColor()->setRGB('059669');

            $wsU->setCellValue('A1', 'Network Uptime Report');
            $wsU->getStyle('A1')->applyFromArray(['font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '003628']]]);
            $wsU->setCellValue('A2', "Periode: {$from} s/d {$to}");
            $wsU->getStyle('A2')->applyFromArray(['font' => ['color' => ['rgb' => '64748B'], 'size' => 10]]);

            // ── TOP: Uptime Summary per location ─────────────────────────────
            $wsU->setCellValue('A4', 'Uptime Summary');
            $wsU->getStyle('A4')->applyFromArray(['font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '003628']]]);

            $wsU->setCellValue('A5', 'Lokasi');
            $wsU->setCellValue('B5', 'AVG');
            $wsU->setCellValue('C5', 'Target');
            $wsU->getStyle('A5:C5')->applyFromArray($hdrStyle);

            // Build per-location avg
            $locUptime = [];
            foreach ($devices as $dev) {
                $loc = $dev->location ?? 'Unknown';
                if (!isset($locUptime[$loc])) $locUptime[$loc] = [];
                foreach ($dev->uptimeDaily as $d) {
                    if ($d->uptime_percent !== null) $locUptime[$loc][] = $d->uptime_percent;
                }
            }
            $summaryRow = 6;
            foreach ($locUptime as $loc => $vals) {
                $avg   = count($vals) > 0 ? round(array_sum($vals) / count($vals), 2) : null;
                $color = ($avg !== null && $avg >= 90) ? '059669' : 'DC2626';
                $wsU->setCellValue('A' . $summaryRow, strtoupper($loc));
                $wsU->setCellValue('B' . $summaryRow, $avg !== null ? $avg . '%' : '-');
                $wsU->setCellValue('C' . $summaryRow, '90%');
                $wsU->getStyle('B' . $summaryRow)->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => $color]]]);
                $summaryRow++;
            }

            // ── BELOW: Uptime Performance (all devices + daily columns) ──────
            $perfLabelRow = $summaryRow + 2; // 2-row gap after summary
            $wsU->setCellValue('A' . $perfLabelRow, 'Uptime Performance');
            $wsU->getStyle('A' . $perfLabelRow)->applyFromArray(['font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '003628']]]);

            $perfHdrRow = $perfLabelRow + 1;
            $wsU->setCellValue('A' . $perfHdrRow, 'Device');
            $wsU->setCellValue('B' . $perfHdrRow, 'IP');
            $wsU->setCellValue('C' . $perfHdrRow, 'Lokasi');
            $wsU->setCellValue('D' . $perfHdrRow, 'Group');
            $wsU->setCellValue('E' . $perfHdrRow, 'Avg %');
            $ci = 6; // F onwards for dates
            foreach ($dates as $date) {
                $col = Coordinate::stringFromColumnIndex($ci);
                $wsU->setCellValue($col . $perfHdrRow, Carbon::parse($date)->format('d M'));
                $ci++;
            }
            $lastPerfCol = Coordinate::stringFromColumnIndex($ci - 1);
            $wsU->getStyle("A{$perfHdrRow}:{$lastPerfCol}{$perfHdrRow}")->applyFromArray($hdrStyle);

            $perfDataRow = $perfHdrRow + 1;
            foreach ($devices as $dev) {
                $dailyMap = $dev->uptimeDaily->keyBy(fn ($d) => $d->report_date->toDateString());
                $vals     = $dev->uptimeDaily->pluck('uptime_percent')->filter()->values();
                $avg      = $vals->count() > 0 ? round($vals->avg(), 2) : null;
                $group    = preg_replace('/^(F\d|R\d)\s+/', '', $dev->host_group ?? '');
                $color    = ($avg !== null && $avg >= 90) ? '059669' : ($avg !== null ? 'DC2626' : '94A3B8');

                $wsU->setCellValue('A' . $perfDataRow, $dev->device_name);
                $wsU->setCellValue('B' . $perfDataRow, $dev->ip_address);
                $wsU->setCellValue('C' . $perfDataRow, $dev->location);
                $wsU->setCellValue('D' . $perfDataRow, $group);
                $wsU->setCellValue('E' . $perfDataRow, $avg !== null ? $avg . '%' : '-');
                $wsU->getStyle('E' . $perfDataRow)->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => $color]]]);

                $c = 6;
                foreach ($dates as $date) {
                    $col = Coordinate::stringFromColumnIndex($c);
                    $d   = $dailyMap->get($date);
                    $val = $d?->uptime_percent;
                    $wsU->setCellValue($col . $perfDataRow, $val !== null ? $val . '%' : '-');
                    if ($val !== null) {
                        $dColor = $val >= 90 ? '059669' : 'DC2626';
                        $wsU->getStyle($col . $perfDataRow)->applyFromArray([
                            'font' => ['color' => ['rgb' => $dColor]],
                        ]);
                    }
                    $c++;
                }

                if ($perfDataRow % 2 === 0) {
                    $wsU->getStyle("A{$perfDataRow}:E{$perfDataRow}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8FAFB']],
                    ]);
                }
                $perfDataRow++;
            }

            // Column widths (shared for both summary and performance — same columns A-E)
            $wsU->getColumnDimension('A')->setWidth(34);
            $wsU->getColumnDimension('B')->setWidth(16);
            $wsU->getColumnDimension('C')->setWidth(16);
            $wsU->getColumnDimension('D')->setWidth(16);
            $wsU->getColumnDimension('E')->setWidth(8);
            for ($c = 6; $c < $ci; $c++) {
                $wsU->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setWidth(9);
            }

            // Freeze: below performance header, after Avg % col (F)
            $wsU->freezePane('F' . ($perfHdrRow + 1));

            // ════════════════════════════════════════════════════════════════
            // SHEET 2 — Network Backup
            // Devices with monitor_backup = true, monthly backup status
            // ════════════════════════════════════════════════════════════════
            $wsB = $spreadsheet->createSheet();
            $wsB->setTitle('Network Backup');
            $wsB->getTabColor()->setRGB('7C3AED');

            $wsB->setCellValue('A1', 'Network Backup Report');
            $wsB->getStyle('A1')->applyFromArray(['font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '003628']]]);
            $wsB->setCellValue('A2', "Periode: {$from} s/d {$to}");
            $wsB->getStyle('A2')->applyFromArray(['font' => ['color' => ['rgb' => '64748B'], 'size' => 10]]);

            // Header: Device | IP | Lokasi | Group | month1 | month2 ...
            $wsB->setCellValue('A4', 'Device');
            $wsB->setCellValue('B4', 'IP');
            $wsB->setCellValue('C4', 'Lokasi');
            $wsB->setCellValue('D4', 'Group');
            $bci = 5;
            foreach ($months as $m) {
                $col = Coordinate::stringFromColumnIndex($bci);
                $wsB->setCellValue($col . '4', $m['label']);
                $bci++;
            }
            $lastBCol = Coordinate::stringFromColumnIndex($bci - 1);
            $wsB->getStyle("A4:{$lastBCol}4")->applyFromArray($hdrStyle);

            $bRow = 5;
            $backupDevices = $devices->where('monitor_backup', true);
            if ($backupDevices->isEmpty()) {
                $wsB->setCellValue('A5', 'Tidak ada device backup dalam periode ini');
                $wsB->getStyle('A5')->applyFromArray(['font' => ['color' => ['rgb' => '94A3B8']]]);
                $wsB->mergeCells("A5:{$lastBCol}5");
            } else {
                foreach ($backupDevices as $dev) {
                    $group = preg_replace('/^(F\d|R\d)\s+/', '', $dev->host_group ?? '');
                    $backupMap = $dev->backupMonthly->keyBy(fn ($b) => $b->year . '_' . $b->month);

                    $wsB->setCellValue('A' . $bRow, $dev->device_name);
                    $wsB->setCellValue('B' . $bRow, $dev->ip_address);
                    $wsB->setCellValue('C' . $bRow, $dev->location);
                    $wsB->setCellValue('D' . $bRow, $group);

                    $bc = 5;
                    foreach ($months as $m) {
                        $col    = Coordinate::stringFromColumnIndex($bc);
                        $bKey   = $m['year'] . '_' . $m['month'];
                        $record = $backupMap->get($bKey);
                        $label  = $record ? ($record->has_backup ? 'OK' : 'MISSING') : '-';
                        $bColor = $record ? ($record->has_backup ? '059669' : 'DC2626') : '94A3B8';
                        $wsB->setCellValue($col . $bRow, $label);
                        $wsB->getStyle($col . $bRow)->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => $bColor]]]);
                        $bc++;
                    }

                    if ($bRow % 2 === 0) {
                        $wsB->getStyle("A{$bRow}:D{$bRow}")->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8FAFB']],
                        ]);
                    }
                    $bRow++;
                }
            }

            $wsB->getColumnDimension('A')->setWidth(34);
            $wsB->getColumnDimension('B')->setWidth(16);
            $wsB->getColumnDimension('C')->setWidth(16);
            $wsB->getColumnDimension('D')->setWidth(16);
            for ($c = 5; $c < $bci; $c++) {
                $wsB->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setWidth(10);
            }

            // ════════════════════════════════════════════════════════════════
            // SHEET 3 — Network Maintenance
            // Maintenance logs from network_maintenance_logs table
            // ════════════════════════════════════════════════════════════════
            $wsM = $spreadsheet->createSheet();
            $wsM->setTitle('Network Maintenance');
            $wsM->getTabColor()->setRGB('DC2626');

            $wsM->setCellValue('A1', 'Network Maintenance Report');
            $wsM->getStyle('A1')->applyFromArray(['font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '003628']]]);
            $wsM->setCellValue('A2', "Periode: {$from} s/d {$to}");
            $wsM->getStyle('A2')->applyFromArray(['font' => ['color' => ['rgb' => '64748B'], 'size' => 10]]);

            // Header — matches maintenance log fields
            $wsM->setCellValue('A4', 'Device');
            $wsM->setCellValue('B4', 'IP');
            $wsM->setCellValue('C4', 'Lokasi');
            $wsM->setCellValue('D4', 'Group');
            $wsM->setCellValue('E4', 'Tipe');
            $wsM->setCellValue('F4', 'Mulai');
            $wsM->setCellValue('G4', 'Selesai');
            $wsM->setCellValue('H4', 'Durasi');
            $wsM->setCellValue('I4', 'Status');
            $wsM->setCellValue('J4', 'Catatan');
            $wsM->getStyle('A4:J4')->applyFromArray($hdrStyle);

            // Load maintenance logs: open tickets always + closed within date range
            $maintLogs = \App\Models\NetworkMaintenanceLog::with(['device:id,device_name,ip_address,location,host_group'])
                ->where(function ($q) use ($from, $to) {
                    $q->where('status', 'open')
                      ->orWhere(function ($q2) use ($from, $to) {
                          $q2->where('status', 'closed')
                             ->where(function ($q3) use ($from, $to) {
                                 $q3->whereBetween('started_at', [$from, $to])
                                    ->orWhereBetween('resolved_at', [$from, $to]);
                             });
                      });
                })
                ->orderByDesc('started_at')
                ->get();

            $mRow = 5;
            if ($maintLogs->isEmpty()) {
                $wsM->setCellValue('A5', 'Tidak ada data maintenance dalam periode ini');
                $wsM->getStyle('A5')->applyFromArray(['font' => ['color' => ['rgb' => '94A3B8']]]);
                $wsM->mergeCells('A5:J5');
            } else {
                $eventLabels = [
                    'maintenance'   => 'Maintenance',
                    'restart'       => 'Restart',
                    'down'          => 'Down',
                    'auto_detected' => 'Auto Detected',
                ];
                foreach ($maintLogs as $log) {
                    $group  = preg_replace('/^(F\d|R\d)\s+/', '', $log->device?->host_group ?? '');
                    $isOpen = $log->status === 'open';
                    $sColor = $isOpen ? 'DC2626' : '059669';

                    $wsM->setCellValue('A' . $mRow, $log->device?->device_name ?? '-');
                    $wsM->setCellValue('B' . $mRow, $log->device?->ip_address ?? '-');
                    $wsM->setCellValue('C' . $mRow, $log->device?->location ?? '-');
                    $wsM->setCellValue('D' . $mRow, $group);
                    $wsM->setCellValue('E' . $mRow, $eventLabels[$log->event_type] ?? $log->event_type);
                    $wsM->setCellValue('F' . $mRow, $log->started_at?->format('d M Y') ?? '-');
                    $wsM->setCellValue('G' . $mRow, $log->resolved_at?->format('d M Y') ?? '-');
                    $wsM->setCellValue('H' . $mRow, $log->durationLabel());
                    $wsM->setCellValue('I' . $mRow, $isOpen ? 'Open' : 'Closed');
                    $wsM->setCellValue('J' . $mRow, $log->notes ?? '');
                    $wsM->getStyle('I' . $mRow)->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => $sColor]]]);

                    if ($mRow % 2 === 0) {
                        $wsM->getStyle("A{$mRow}:J{$mRow}")->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8FAFB']],
                        ]);
                    }
                    $mRow++;
                }
            }

            $wsM->getColumnDimension('A')->setWidth(34);
            $wsM->getColumnDimension('B')->setWidth(16);
            $wsM->getColumnDimension('C')->setWidth(16);
            $wsM->getColumnDimension('D')->setWidth(16);
            $wsM->getColumnDimension('E')->setWidth(14);
            $wsM->getColumnDimension('F')->setWidth(14);
            $wsM->getColumnDimension('G')->setWidth(14);
            $wsM->getColumnDimension('H')->setWidth(12);
            $wsM->getColumnDimension('I')->setWidth(10);
            $wsM->getColumnDimension('J')->setWidth(45);
            if ($mRow > 5) {
                $wsM->getStyle('J5:J' . ($mRow - 1))->getAlignment()->setWrapText(true);
            }
            // No freeze on maintenance sheet
        }

        if ($doIsp) {
            $fromDate = Carbon::parse($from);
            $toDate   = Carbon::parse($to);

            // Build month list from filter range
            $months = [];
            $cur    = $fromDate->copy()->startOfMonth();
            while ($cur->lte($toDate)) {
                $months[] = ['year' => $cur->year, 'month' => $cur->month, 'label' => $cur->format('M y')];
                $cur->addMonth();
            }

            $contracts = IspSlaContract::where('is_active', true)
                ->orderBy('sort_order')->orderBy('location')->orderBy('fct')->orderBy('provider')
                ->get();

            $records = IspSlaMonthly::whereIn('contract_id', $contracts->pluck('id'))
                ->get()->groupBy(fn ($r) => $r->contract_id . '|' . $r->year . '|' . $r->month);

            // Down history in range
            $downHistory = \App\Models\IspDownHistory::with(['contract:id,location,fct,provider'])
                ->whereBetween('incident_date', [$from, $to])
                ->orderByDesc('incident_date')
                ->get();

            // Build per-location summary (avg across all contracts in that location)
            $locSummary = [];
            foreach ($contracts as $contract) {
                $loc = $contract->location;
                if (!isset($locSummary[$loc])) {
                    $locSummary[$loc] = ['values' => [], 'target' => $contract->target_pct];
                }
                foreach ($months as $m) {
                    $key    = $contract->id . '|' . $m['year'] . '|' . $m['month'];
                    $record = $records->get($key)?->first();
                    if ($record?->uptime_pct !== null) {
                        $locSummary[$loc]['values'][] = $record->uptime_pct;
                    }
                }
            }

            // ════════════════════════════════════════════════════════════════
            // SHEET 1 — ISP SLA Summary
            // Layout: SLA Summary table (cols A-C) | gap (col D) | SLA Performance table (col E onwards)
            // ════════════════════════════════════════════════════════════════
            $ws1 = $spreadsheet->createSheet();
            $ws1->setTitle('ISP SLA Summary');
            $ws1->getTabColor()->setRGB('D97706');

            // Title & periode
            $ws1->setCellValue('A1', 'ISP SLA Report');
            $ws1->getStyle('A1')->applyFromArray(['font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '003628']]]);
            $ws1->setCellValue('A2', "Periode: {$from} s/d {$to}");
            $ws1->getStyle('A2')->applyFromArray(['font' => ['color' => ['rgb' => '64748B'], 'size' => 10]]);

            $dataRow = 4; // both tables start at same row

            // ── LEFT: SLA Summary ────────────────────────────────────────────
            $ws1->setCellValue('A' . $dataRow, 'SLA Summary');
            $ws1->getStyle('A' . $dataRow)->applyFromArray(['font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '003628']]]);
            $dataRow++;

            // Summary header
            $ws1->setCellValue('A' . $dataRow, 'Location');
            $ws1->setCellValue('B' . $dataRow, 'AVG');
            $ws1->setCellValue('C' . $dataRow, 'Target');
            $ws1->getStyle("A{$dataRow}:C{$dataRow}")->applyFromArray($hdrStyle);
            $dataRow++;

            $summaryDataStartRow = $dataRow;
            foreach ($locSummary as $loc => $data) {
                $vals   = $data['values'];
                $avg    = count($vals) > 0 ? round(array_sum($vals) / count($vals), 3) : null;
                $target = $data['target'];
                $color  = $avg !== null ? ($avg >= $target ? '059669' : 'DC2626') : '94A3B8';

                $ws1->setCellValue('A' . $dataRow, strtoupper($loc));
                $ws1->setCellValue('B' . $dataRow, $avg !== null ? $avg . '%' : '-');
                $ws1->setCellValue('C' . $dataRow, $target . '%');
                $ws1->getStyle('B' . $dataRow)->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => $color]]]);
                $dataRow++;
            }
            $summaryLastRow = $dataRow - 1;

            // Summary column widths
            $ws1->getColumnDimension('A')->setWidth(16);
            $ws1->getColumnDimension('B')->setWidth(10);
            $ws1->getColumnDimension('C')->setWidth(10);
            $ws1->getColumnDimension('D')->setWidth(4); // gap

            // ── RIGHT: SLA Performance ───────────────────────────────────────
            // Performance table starts at col E (index 5), same row 4
            $perfLabelRow = 4;
            $ws1->setCellValue('E' . $perfLabelRow, 'SLA Performance');
            $ws1->getStyle('E' . $perfLabelRow)->applyFromArray(['font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '003628']]]);
            $perfLabelRow++;

            // Performance header: Location | Factory | Provider | B/W | Target | AVG | Status | month...
            $perfHdrRow = $perfLabelRow;
            $ws1->setCellValue('E' . $perfHdrRow, 'Location');
            $ws1->setCellValue('F' . $perfHdrRow, 'Factory');
            $ws1->setCellValue('G' . $perfHdrRow, 'Provider');
            $ws1->setCellValue('H' . $perfHdrRow, 'B/W');
            $ws1->setCellValue('I' . $perfHdrRow, 'Target');
            $ws1->setCellValue('J' . $perfHdrRow, 'AVG');
            $ws1->setCellValue('K' . $perfHdrRow, 'Status');
            $ci = 12; // L onwards for months
            foreach ($months as $m) {
                $col = Coordinate::stringFromColumnIndex($ci);
                $ws1->setCellValue($col . $perfHdrRow, $m['label']);
                $ci++;
            }
            $lastPerfCol = Coordinate::stringFromColumnIndex($ci - 1);
            $ws1->getStyle("E{$perfHdrRow}:{$lastPerfCol}{$perfHdrRow}")->applyFromArray($hdrStyle);

            // Performance data rows — NO merge
            $perfDataRow = $perfHdrRow + 1;
            foreach ($contracts as $contract) {
                $contractVals = [];
                foreach ($months as $m) {
                    $key    = $contract->id . '|' . $m['year'] . '|' . $m['month'];
                    $record = $records->get($key)?->first();
                    if ($record?->uptime_pct !== null) $contractVals[] = $record->uptime_pct;
                }
                $avg   = count($contractVals) > 0 ? round(array_sum($contractVals) / count($contractVals), 3) : null;
                $onSla = $avg !== null ? ($avg >= $contract->target_pct ? 'On SLA' : 'Breach') : 'No Data';
                $color = $avg !== null ? ($avg >= $contract->target_pct ? '059669' : 'DC2626') : '94A3B8';

                $ws1->setCellValue('E' . $perfDataRow, $contract->location);
                $ws1->setCellValue('F' . $perfDataRow, $contract->fct ?? '');
                $ws1->setCellValue('G' . $perfDataRow, $contract->provider);
                $ws1->setCellValue('H' . $perfDataRow, $contract->bandwidth ?? '');
                $ws1->setCellValue('I' . $perfDataRow, $contract->target_pct . '%');
                $ws1->setCellValue('J' . $perfDataRow, $avg !== null ? $avg . '%' : '-');
                $ws1->setCellValue('K' . $perfDataRow, $onSla);
                $ws1->getStyle('J' . $perfDataRow)->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => $color]]]);
                $ws1->getStyle('K' . $perfDataRow)->applyFromArray(['font' => ['color' => ['rgb' => $color]]]);

                // Monthly columns — individual cells, no merge
                $c = 12;
                foreach ($months as $m) {
                    $key    = $contract->id . '|' . $m['year'] . '|' . $m['month'];
                    $record = $records->get($key)?->first();
                    $col    = Coordinate::stringFromColumnIndex($c);
                    $val    = $record?->uptime_pct;
                    $ws1->setCellValue($col . $perfDataRow, $val !== null ? $val . '%' : '-');
                    if ($val !== null) {
                        $mColor = $val >= $contract->target_pct ? '059669' : 'DC2626';
                        $ws1->getStyle($col . $perfDataRow)->applyFromArray([
                            'font' => ['bold' => true, 'color' => ['rgb' => $mColor]],
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $val >= $contract->target_pct ? 'ECFDF5' : 'FEF2F2']],
                        ]);
                    }
                    $c++;
                }

                if ($perfDataRow % 2 === 0) {
                    $ws1->getStyle("E{$perfDataRow}:K{$perfDataRow}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8FAFB']],
                    ]);
                }
                $perfDataRow++;
            }

            // Performance column widths
            $ws1->getColumnDimension('E')->setWidth(16);
            $ws1->getColumnDimension('F')->setWidth(10);
            $ws1->getColumnDimension('G')->setWidth(12);
            $ws1->getColumnDimension('H')->setWidth(8);
            $ws1->getColumnDimension('I')->setWidth(10);
            $ws1->getColumnDimension('J')->setWidth(10);
            $ws1->getColumnDimension('K')->setWidth(10);
            for ($c = 12; $c < $ci; $c++) {
                $ws1->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setWidth(10);
            }

            // ════════════════════════════════════════════════════════════════
            // SHEET 2 — ISP Downtime History
            // ════════════════════════════════════════════════════════════════
            $ws2 = $spreadsheet->createSheet();
            $ws2->setTitle('ISP Downtime History');
            $ws2->getTabColor()->setRGB('D97706');

            $ws2->setCellValue('A1', 'ISP Downtime History');
            $ws2->getStyle('A1')->applyFromArray(['font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '003628']]]);
            $ws2->setCellValue('A2', "Periode: {$from} s/d {$to}");
            $ws2->getStyle('A2')->applyFromArray(['font' => ['color' => ['rgb' => '64748B'], 'size' => 10]]);

            // Header
            $dhHeaders = ['No', 'Tanggal', 'Location', 'FCT', 'Provider', 'Case', 'Action', 'Durasi (menit)'];
            $dhRow = 4;
            foreach ($dhHeaders as $hi => $hdr) {
                $col = Coordinate::stringFromColumnIndex($hi + 1);
                $ws2->setCellValue($col . $dhRow, $hdr);
            }
            $ws2->getStyle("A{$dhRow}:H{$dhRow}")->applyFromArray($hdrStyle);
            $dhRow++;

            if ($downHistory->isEmpty()) {
                $ws2->setCellValue('A' . $dhRow, 'Tidak ada insiden dalam periode ini');
                $ws2->getStyle('A' . $dhRow)->applyFromArray(['font' => ['color' => ['rgb' => '94A3B8']]]);
                $ws2->mergeCells("A{$dhRow}:H{$dhRow}");
            } else {
                $no = 1;
                foreach ($downHistory as $inc) {
                    $ws2->setCellValue('A' . $dhRow, $no++);
                    $ws2->setCellValue('B' . $dhRow, $inc->incident_date->format('d M Y'));
                    $ws2->setCellValue('C' . $dhRow, $inc->contract?->location ?? '');
                    $ws2->setCellValue('D' . $dhRow, $inc->contract?->fct ?? '');
                    $ws2->setCellValue('E' . $dhRow, $inc->contract?->provider ?? '');
                    $ws2->setCellValue('F' . $dhRow, $inc->case_description ?? '');
                    $ws2->setCellValue('G' . $dhRow, $inc->action_taken ?? '');
                    $ws2->setCellValue('H' . $dhRow, $inc->duration_minutes);
                    if ($dhRow % 2 === 0) {
                        $ws2->getStyle("A{$dhRow}:H{$dhRow}")->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8FAFB']],
                        ]);
                    }
                    $dhRow++;
                }
            }

            // Column widths
            $ws2->getColumnDimension('A')->setWidth(6);
            $ws2->getColumnDimension('B')->setWidth(16);
            $ws2->getColumnDimension('C')->setWidth(16);
            $ws2->getColumnDimension('D')->setWidth(8);
            $ws2->getColumnDimension('E')->setWidth(12);
            $ws2->getColumnDimension('F')->setWidth(45);
            $ws2->getColumnDimension('G')->setWidth(45);
            $ws2->getColumnDimension('H')->setWidth(16);
            $ws2->getStyle('F5:F' . ($dhRow - 1))->getAlignment()->setWrapText(true);
            $ws2->getStyle('G5:G' . ($dhRow - 1))->getAlignment()->setWrapText(true);
        }

        if ($spreadsheet->getSheetCount() === 0) {
            $ws = $spreadsheet->createSheet();
            $ws->setTitle('Empty');
            $ws->setCellValue('A1', 'Tidak ada data yang dipilih');
        }

        $spreadsheet->setActiveSheetIndex(0);
        $filename = 'network_report_' . $from . '_' . $to . '.xlsx';
        $writer   = new Xlsx($spreadsheet);
        $writer->setIncludeCharts(true);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }
}
