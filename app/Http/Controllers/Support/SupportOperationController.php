<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SupportOperationController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Report/SupportOperation/Index');
    }

    public function summary(Request $request): JsonResponse
    {
        $from = $request->query('from', now()->subDays(29)->toDateString());
        $to   = $request->query('to', now()->toDateString());

        $totalTickets = Ticket::whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])->count();
        $openTickets  = Ticket::whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->whereNotIn('status', ['closed', 'resolved'])->count();
        $closedTickets = Ticket::whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->whereIn('status', ['closed', 'resolved'])->count();

        // Stats by Location
        $locationStats = Ticket::whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->selectRaw('location, count(*) as count, 
                sum(case when status in ("closed", "resolved") then 1 else 0 end) as resolved_count')
            ->groupBy('location')
            ->get()
            ->map(function ($item) {
                return [
                    'location' => $item->location ?? 'N/A',
                    'total' => $item->count,
                    'resolved' => $item->resolved_count,
                    'pct' => $item->count > 0 ? round(($item->resolved_count / $item->count) * 100, 1) : 0
                ];
            });

        // Stats by Category
        $categoryStats = Ticket::whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->selectRaw('category, count(*) as count')
            ->groupBy('category')
            ->orderByDesc('count')
            ->get();

        return response()->json([
            'total' => $totalTickets,
            'open' => $openTickets,
            'closed' => $closedTickets,
            'location_stats' => $locationStats,
            'category_stats' => $categoryStats,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $from = $request->query('from', now()->subDays(29)->toDateString());
        $to   = $request->query('to', now()->toDateString());
        $search = $request->query('search');

        $query = Ticket::whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->with(['creator:id,name']);

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('issue_description', 'like', "%{$search}%")
                  ->orWhere('requester', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%");
            });
        }

        $tickets = $query->orderByDesc('created_at')->get();

        return response()->json($tickets);
    }

    public function export(Request $request)
    {
        $from = $request->query('from', now()->subDays(29)->toDateString());
        $to   = $request->query('to', now()->toDateString());
        $doSummary = $request->query('summary', '1') === '1';
        $doData    = $request->query('data', '1') === '1';

        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        $hdrStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '003628']],
        ];

        if ($doSummary) {
            $ws = $spreadsheet->createSheet();
            $ws->setTitle('Summary');
            $ws->setCellValue('A1', 'Support Operation Summary');
            $ws->setCellValue('A2', "Periode: {$from} - {$to}");
            $ws->getStyle('A1')->getFont()->setBold(true)->setSize(14);

            $summaryData = $this->summary($request)->getData();

            $ws->setCellValue('A4', 'METRIC');
            $ws->setCellValue('B4', 'VALUE');
            $ws->getStyle('A4:B4')->applyFromArray($hdrStyle);
            
            $ws->setCellValue('A5', 'Total Tickets');
            $ws->setCellValue('B5', $summaryData->total);
            $ws->setCellValue('A6', 'Open Tickets');
            $ws->setCellValue('B6', $summaryData->open);
            $ws->setCellValue('A7', 'Closed/Resolved');
            $ws->setCellValue('B7', $summaryData->closed);

            $row = 9;
            $ws->setCellValue('A' . $row, 'PERFORMANCE PER LOKASI');
            $ws->getStyle('A'.$row.':D'.$row)->applyFromArray($hdrStyle);
            $row++;
            $ws->setCellValue('A'.$row, 'Lokasi');
            $ws->setCellValue('B'.$row, 'Total');
            $ws->setCellValue('C'.$row, 'Resolved');
            $ws->setCellValue('D'.$row, '%');
            $row++;
            foreach ($summaryData->location_stats as $ls) {
                $ws->setCellValue('A'.$row, $ls->location);
                $ws->setCellValue('B'.$row, $ls->total);
                $ws->setCellValue('C'.$row, $ls->resolved);
                $ws->setCellValue('D'.$row, $ls->pct . '%');
                $row++;
            }
        }

        if ($doData) {
            $ws = $spreadsheet->createSheet();
            $ws->setTitle('Support Data');
            
            $cols = ['ID', 'Date', 'Location', 'Category', 'Requester', 'Issue', 'Status', 'Closed Date'];
            foreach ($cols as $i => $col) {
                $cell = chr(65 + $i) . '1';
                $ws->setCellValue($cell, $col);
            }
            $ws->getStyle('A1:H1')->applyFromArray($hdrStyle);

            $tickets = Ticket::whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
                ->orderByDesc('created_at')
                ->get();

            $row = 2;
            foreach ($tickets as $t) {
                $ws->setCellValue('A'.$row, $t->id);
                $ws->setCellValue('B'.$row, $t->created_at->format('Y-m-d H:i'));
                $ws->setCellValue('C'.$row, $t->location);
                $ws->setCellValue('D'.$row, $t->category);
                $ws->setCellValue('E'.$row, $t->requester);
                $ws->setCellValue('F'.$row, $t->issue_description);
                $ws->setCellValue('G'.$row, $t->status);
                $ws->setCellValue('H'.$row, $t->date_closed ? $t->date_closed->format('Y-m-d') : '-');
                $row++;
            }
            foreach (range('A', 'H') as $col) $ws->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $fileName = "Support_Operation_{$from}_{$to}.xlsx";

        return response()->streamDownload(function() use ($writer) {
            $writer->save('php://output');
        }, $fileName);
    }
}
