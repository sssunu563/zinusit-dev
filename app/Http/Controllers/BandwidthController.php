<?php

namespace App\Http\Controllers;

use App\Models\BandwidthDaily;
use App\Models\BandwidthFetchLog;
use App\Services\PrtgService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BandwidthController extends Controller
{
    public function __construct(private readonly PrtgService $prtg) {}

    public function index(): Response
    {
        return Inertia::render('Report/Bandwidth', [
            'locations' => PrtgService::locations(),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $from     = $request->query('from', now()->subDays(6)->toDateString());
        $to       = $request->query('to',   now()->toDateString());
        $location = $request->query('location', '');

        $query = BandwidthDaily::query()
            ->whereBetween('report_date', [$from, $to])
            ->orderBy('report_date', 'desc')
            ->orderBy('location')
            ->orderBy('provider');

        if ($location) $query->where('location', $location);

        $rows    = $query->get();
        $grouped = [];

        foreach ($rows as $row) {
            $key = $row->report_date->toDateString() . '|' . $row->location . '|' . $row->provider;
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'date'     => $row->report_date->toDateString(),
                    'location' => $row->location,
                    'provider' => $row->provider,
                    'download' => null,
                    'upload'   => null,
                ];
            }
            if ($row->description === 'Download (Mbps)') {
                $grouped[$key]['download'] = $row->value_mbps;
            } else {
                $grouped[$key]['upload'] = $row->value_mbps;
            }
        }

        return response()->json(array_values($grouped));
    }

    public function summary(Request $request): JsonResponse
    {
        $from     = $request->query('from', now()->subDays(29)->toDateString());
        $to       = $request->query('to',   now()->toDateString());
        $location = $request->query('location', '');

        $query = BandwidthDaily::query()
            ->selectRaw('report_date, location, provider, description, MAX(value_mbps) as value_mbps')
            ->whereBetween('report_date', [$from, $to])
            ->groupBy('report_date', 'location', 'provider', 'description')
            ->orderBy('report_date');

        if ($location) $query->where('location', $location);

        $rows      = $query->get();
        $series    = [];
        $allValues = [];
        $dates     = [];

        foreach ($rows as $row) {
            $date = $row->report_date->toDateString();
            if (!in_array($date, $dates)) $dates[] = $date;

            $key = $row->location . ' ' . $row->provider . ' ' . $row->description;
            if (!isset($series[$key])) {
                $series[$key] = [
                    'name'        => $key,
                    'location'    => $row->location,
                    'provider'    => $row->provider,
                    'description' => $row->description,
                    'data'        => [],
                ];
                $allValues[$key] = [];
            }
            $series[$key]['data'][$date]  = (float) $row->value_mbps;
            $allValues[$key][]            = (float) $row->value_mbps;
        }

        sort($dates);

        foreach ($series as &$s) {
            $normalized = [];
            foreach ($dates as $d) {
                $normalized[] = $s['data'][$d] ?? null;
            }
            $s['data'] = $normalized;
        }
        unset($s);

        $cards = [];
        foreach ($series as $key => $s) {
            $loc  = $s['location'];
            $vals = array_filter($allValues[$key], fn ($v) => $v !== null);
            if (!isset($cards[$loc])) $cards[$loc] = ['location' => $loc, 'series' => []];
            $cards[$loc]['series'][] = [
                'name'     => $s['provider'] . ' ' . $s['description'],
                'avg_mbps' => count($vals) > 0 ? round(array_sum($vals) / count($vals), 2) : null,
                'max_mbps' => count($vals) > 0 ? round(max($vals), 2) : null,
            ];
        }

        return response()->json([
            'dates'  => $dates,
            'series' => array_values($series),
            'cards'  => array_values($cards),
        ]);
    }

    public function logs(Request $request): JsonResponse
    {
        $logs = BandwidthFetchLog::with('triggeredBy:id,name')
            ->orderByDesc('fetch_date')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn ($l) => [
                'id'           => $l->id,
                'fetch_date'   => $l->fetch_date->toDateString(),
                'status'       => $l->status,
                'sensors_ok'   => $l->sensors_ok,
                'sensors_fail' => $l->sensors_fail,
                'notes'        => $l->notes,
                'is_manual'    => $l->is_manual,
                'triggered_by' => $l->triggeredBy?->name ?? 'Cron',
                'created_at'   => $l->created_at?->format('d M Y H:i'),
            ]);

        return response()->json($logs);
    }

    public function fetch(Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'required|date|before_or_equal:today',
        ]);

        set_time_limit(120);

        $date   = Carbon::parse($request->input('date'));
        $userId = $request->user()?->id;

        \Illuminate\Support\Facades\Log::info('BandwidthController::fetch called', [
            'date'       => $date->toDateString(),
            'prtg_url'   => config('services.prtg.url'),
            'prtg_token' => substr((string) config('services.prtg.api_token'), 0, 10),
            'env_url'    => env('PRTG_URL'),
        ]);

        try {
            $result = $this->prtg->fetchForDate($date, $userId, true);

            $status = $result['fail'] === 0 ? 200 : ($result['ok'] > 0 ? 206 : 500);

            return response()->json([
                'message' => "Fetch selesai: {$result['ok']} sensor berhasil, {$result['fail']} gagal.",
                'result'  => $result,
                'errors'  => $result['notes'],
            ], $status);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('BandwidthController::fetch error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Fetch gagal: ' . $e->getMessage(),
                'errors'  => [$e->getMessage()],
            ], 500);
        }
    }

    public function export(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $from = $request->query('from', now()->subDays(29)->toDateString());
        $to   = $request->query('to',   now()->toDateString());

        $rows = BandwidthDaily::query()
            ->selectRaw('report_date, location, provider, description, MAX(value_mbps) as value_mbps')
            ->whereBetween('report_date', [$from, $to])
            ->groupBy('report_date', 'location', 'provider', 'description')
            ->orderBy('location')->orderBy('provider')->orderBy('description')->orderBy('report_date')
            ->get();

        $seriesMap = [];
        $allDates  = [];
        $allValues = [];

        foreach ($rows as $row) {
            $date = $row->report_date->toDateString();
            if (!in_array($date, $allDates)) $allDates[] = $date;
            $key = $row->location . ' - ' . $row->provider . ' | ' . $row->description;
            $seriesMap[$key][$date] = (float) $row->value_mbps;
            $allValues[$key][]      = (float) $row->value_mbps;
        }
        sort($allDates);
        $nDates = count($allDates);

        // Group by location for summary cards
        $byLocation = [];
        foreach ($seriesMap as $seriesName => $dateValues) {
            $parts = explode(' - ', $seriesName, 2);
            $loc   = $parts[0] ?? $seriesName;
            $vals  = array_values($dateValues);
            if (!isset($byLocation[$loc])) $byLocation[$loc] = [];
            $byLocation[$loc][] = [
                'name' => $parts[1] ?? $seriesName,
                'avg'  => count($vals) > 0 ? round(array_sum($vals) / count($vals), 2) : null,
                'max'  => count($vals) > 0 ? round(max($vals), 2) : null,
            ];
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

        $hdrStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '003628']],
        ];
        $locStyle = ['font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => '003628']]];
        $subStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => '64748B'], 'size' => 9],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']],
        ];

        // SHEET 1: Summary  (location cards + line chart)
        $s1 = $spreadsheet->getActiveSheet();
        $s1->setTitle('Summary');

        // Title
        $s1->setCellValue('A1', 'Bandwidth Usage - Summary');
        $s1->getStyle('A1')->applyFromArray(['font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '003628']]]);
        $s1->setCellValue('A2', "Periode: {$from} s/d {$to}");
        $s1->getStyle('A2')->applyFromArray(['font' => ['color' => ['rgb' => '64748B'], 'size' => 10]]);

        // 3 location cards side-by-side (A-C, E-G, I-K)
        $cardCols = [1, 5, 9];
        $cardRow  = 4;
        $locs     = array_keys($byLocation);

        foreach ($locs as $li => $loc) {
            $sc = $cardCols[$li] ?? 1;
            $colA = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($sc);
            $colB = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($sc + 1);
            $colC = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($sc + 2);

            // Location header
            $s1->setCellValue($colA . $cardRow, $loc);
            $s1->getStyle($colA . $cardRow)->applyFromArray($locStyle);
            $s1->mergeCells("{$colA}{$cardRow}:{$colC}{$cardRow}");

            // Sub-header
            $r = $cardRow + 1;
            $s1->setCellValue($colA . $r, 'PROVIDER / DESC');
            $s1->setCellValue($colB . $r, 'AVG');
            $s1->setCellValue($colC . $r, 'MAX');
            $s1->getStyle("{$colA}{$r}:{$colC}{$r}")->applyFromArray($subStyle);

            // Series rows
            $r++;
            foreach ($byLocation[$loc] as $s) {
                $s1->setCellValue($colA . $r, $s['name']);
                $s1->setCellValue($colB . $r, $s['avg']);
                $s1->setCellValue($colC . $r, $s['max']);
                $s1->getStyle($colC . $r)->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => 'DC2626']]]);
                $r++;
            }
        }

        // Column widths for summary
        foreach ([1, 5, 9] as $sc) {
            $s1->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($sc))->setWidth(28);
            $s1->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($sc + 1))->setWidth(10);
            $s1->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($sc + 2))->setWidth(10);
        }
        foreach ([4, 8] as $gc) {
            $s1->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($gc))->setWidth(2);
        }

        $sChart = $spreadsheet->createSheet();
        $sChart->setTitle('_ChartData');
        $sChart->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);

        // Row 1: date labels
        $sChart->setCellValue('A1', 'Series');
        foreach ($allDates as $di => $date) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($di + 2);
            $sChart->setCellValue($col . '1', Carbon::parse($date)->format('d M'));
        }

        // Rows 2+: one row per series
        $chartRows = [];
        $sr = 2;
        foreach ($seriesMap as $seriesName => $dateValues) {
            $sChart->setCellValue('A' . $sr, $seriesName);
            foreach ($allDates as $di => $date) {
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($di + 2);
                if (isset($dateValues[$date])) {
                    $sChart->setCellValue($col . $sr, $dateValues[$date]);
                }
            }
            $chartRows[] = $sr;
            $sr++;
        }

        $lastDateCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($nDates + 1);
        $nSeries     = count($chartRows);

        // Build chart series
        $seriesNames  = [];
        $xLabels      = [];
        $seriesValues = [];

        foreach ($chartRows as $row) {
            $seriesNames[]  = new \PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues(
                'String', '_ChartData!$A$' . $row, null, 1
            );
            $xLabels[]      = new \PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues(
                'String', '_ChartData!$B$1:$' . $lastDateCol . '$1', null, $nDates
            );
            $seriesValues[] = new \PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues(
                'Number', '_ChartData!$B$' . $row . ':$' . $lastDateCol . '$' . $row, null, $nDates
            );
        }

        $dataSeries = new \PhpOffice\PhpSpreadsheet\Chart\DataSeries(
            \PhpOffice\PhpSpreadsheet\Chart\DataSeries::TYPE_LINECHART,
            \PhpOffice\PhpSpreadsheet\Chart\DataSeries::GROUPING_STANDARD,
            range(0, $nSeries - 1),
            $seriesNames,
            $xLabels,
            $seriesValues
        );

        $chart = new \PhpOffice\PhpSpreadsheet\Chart\Chart(
            'bw_chart',
            new \PhpOffice\PhpSpreadsheet\Chart\Title('Bandwidth Usage (Mbps)'),
            new \PhpOffice\PhpSpreadsheet\Chart\Legend(\PhpOffice\PhpSpreadsheet\Chart\Legend::POSITION_BOTTOM, null, false),
            new \PhpOffice\PhpSpreadsheet\Chart\PlotArea(null, [$dataSeries]),
            true,
            \PhpOffice\PhpSpreadsheet\Chart\DataSeries::EMPTY_AS_GAP
        );

        $chart->setTopLeftPosition('A14');
        $chart->setBottomRightPosition('K29');
        $s1->addChart($chart);

        // SHEET 2: Data  (pivot: series x dates)
        $s2 = $spreadsheet->createSheet();
        $s2->setTitle('Data');

        $s2->setCellValue('A1', 'Location - Provider | Description');
        $s2->setCellValue('B1', 'AVG (Mbps)');
        $s2->setCellValue('C1', 'Max (Mbps)');

        $ci = 4;
        foreach ($allDates as $date) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci);
            $s2->setCellValue($col . '1', Carbon::parse($date)->format('d M y'));
            $ci++;
        }

        $lastDataCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci - 1);
        $s2->getStyle("A1:{$lastDataCol}1")->applyFromArray($hdrStyle);

        $rowNum = 2;
        foreach ($seriesMap as $seriesName => $dateValues) {
            $vals = array_values($dateValues);
            $avg  = count($vals) > 0 ? round(array_sum($vals) / count($vals), 2) : null;
            $max  = count($vals) > 0 ? round(max($vals), 2) : null;

            $s2->setCellValue('A' . $rowNum, $seriesName);
            $s2->setCellValue('B' . $rowNum, $avg);
            $s2->setCellValue('C' . $rowNum, $max);

            $c = 4;
            foreach ($allDates as $date) {
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
                $s2->setCellValue($col . $rowNum, $dateValues[$date] ?? null);
                $c++;
            }

            if ($rowNum % 2 === 0) {
                $s2->getStyle("A{$rowNum}:{$lastDataCol}{$rowNum}")->applyFromArray([
                    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8FAFB']],
                ]);
            }
            $rowNum++;
        }

        $s2->getColumnDimension('A')->setWidth(40);
        $s2->getColumnDimension('B')->setWidth(14);
        $s2->getColumnDimension('C')->setWidth(14);
        for ($c = 4; $c < $ci; $c++) {
            $s2->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c))->setWidth(12);
        }
        $s2->freezePane('D2');
        $s2->getStyle("B2:C{$rowNum}")->getNumberFormat()->setFormatCode('0.00');

        $filename    = 'bandwidth_' . $from . '_' . $to . '.xlsx';
        $spreadsheet->setActiveSheetIndex(0);

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->setIncludeCharts(true);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }
}
