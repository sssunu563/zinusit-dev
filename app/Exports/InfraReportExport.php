<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\NetworkDevice;
use App\Models\CctvDevice;
use App\Models\ServerDevice;
use App\Models\Ticket;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class InfraReportExport
{
    private string $from;
    private string $to;

    private const SITES = ['F1 Bogor', 'F2 Karawang', 'F3 Tangerang'];

    public static function weeklyUptimeHeaderLabels(string $from, string $to): array
    {
        $cursor = Carbon::parse($from);
        $end = Carbon::parse($to);

        $labels = [];
        while ($cursor->lte($end) && count($labels) < 7) {
            $labels[] = $cursor->format('d M Y');
            $cursor->addDay();
        }

        $labels[] = 'Avg';

        return $labels;
    }

    public static function weeklyBandwidthDateLabels(string $from, string $to): array
    {
        $cursor = Carbon::parse($from);
        $end = Carbon::parse($to);

        $labels = [];
        while ($cursor->lte($end) && count($labels) < 7) {
            $labels[] = $cursor->format('j/n/y');
            $cursor->addDay();
        }

        return $labels;
    }

    public function __construct(string $from, string $to)
    {
        $this->from = $from;
        $this->to   = $to;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Public download entry point
    // ─────────────────────────────────────────────────────────────────────────

    public function download(string $fileName): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $spreadsheet = $this->buildSpreadsheet();

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Main Spreadsheet Builder
    // ─────────────────────────────────────────────────────────────────────────

    private function buildSpreadsheet(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getDefaultStyle()->getFont()->setName('Segoe UI')->setSize(9.5);

        // Fetch summarized data
        $rawNetwork   = $this->getUptimeReport(self::SITES, 'network');
        $rawNvr       = $this->getUptimeReport(self::SITES, 'nvr');
        $rawCctv      = $this->getUptimeReport(self::SITES, 'cctv');
        $rawServer    = $this->getUptimeReport(self::SITES, 'server');
        $rawBandwidth = $this->getBandwidthReport(self::SITES);
        $rawHelpdesk  = $this->getHelpdeskReport(self::SITES);

        // Sheet 1: Dashboard (Weekly Infra Report - Exact replica of reference layout)
        $wsDashboard = $spreadsheet->getActiveSheet();
        $wsDashboard->setTitle('Weekly Dashboard');
        $this->buildDashboardSheet(
            $wsDashboard,
            $rawNetwork,
            $rawNvr,
            $rawCctv,
            $rawServer,
            $rawBandwidth,
            $rawHelpdesk
        );

        // Sheet 2: Network Devices (Full inventory & weekly uptime)
        $this->buildNetworkSheet($spreadsheet);

        // Sheet 3: NVR & CCTV Devices (Full inventory & weekly uptime)
        $this->buildCctvSheet($spreadsheet);

        // Sheet 4: Server Devices (Full inventory & CPU/RAM/Disk stats)
        $this->buildServerSheet($spreadsheet);

        // Sheet 5: Bandwidth Traffic (Daily readings & SLA limits)
        $this->buildBandwidthSheet($spreadsheet);

        // Sheet 6: Helpdesk Tickets (All tickets handled in the period)
        $this->buildHelpdeskSheet($spreadsheet);

        // Sheet 7: Maintenance Logs (All incident/maintenance logs)
        $this->buildMaintenanceSheet($spreadsheet);

        // Set active sheet back to Sheet 1 (Dashboard)
        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SHEET 1: Weekly Dashboard
    // ─────────────────────────────────────────────────────────────────────────

    private function buildDashboardSheet(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws,
        array $rawNetwork,
        array $rawNvr,
        array $rawCctv,
        array $rawServer,
        array $rawBandwidth,
        array $rawHelpdesk
    ): void {
        // Page setup: Landscape A4
        $ws->getPageSetup()
            ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4)
            ->setFitToPage(true)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $ws->getPageMargins()->setTop(0.4)->setBottom(0.4)->setLeft(0.4)->setRight(0.4);
        $ws->setShowGridLines(true);

        // Set precise column widths matching reference layout
        $widths = [
            'A' => 3.5,  // Left gutter
            'B' => 14,   // Branch / Location
            'C' => 11,   // Network / Date
            'D' => 11,   // NVR / Category
            'E' => 11,   // CCTV / Device Name Part 1
            'F' => 11,   // Server / Device Name Part 2
            'G' => 4,    // GAP COLUMN between Branch Health & Bandwidth Snapshot
            'H' => 14,   // Branch / IP Address Part 1
            'I' => 12,   // ISP / IP Address Part 2
            'J' => 11,   // Capacity / Downtime
            'K' => 14,   // Down / Remark Part 1
            'L' => 16,   // Up / Remark Part 2
            'M' => 3.5,  // Right gutter
        ];
        foreach ($widths as $col => $w) {
            $ws->getColumnDimension($col)->setWidth($w);
        }

        // Shared border style
        $borderThin = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D0D5DD'],
                ],
            ],
        ];

        // ── 1. TITLE HEADER ─────────────────────────────────────────────────
        $ws->getRowDimension(2)->setRowHeight(26);
        $ws->setCellValue('B2', 'ZINUS IDN | WEEKLY INFRA REPORT');
        $ws->getStyle('B2')->getFont()->setSize(16)->setBold(true)->getColor()->setRGB('0B1E33');

        $fromFormatted = Carbon::parse($this->from)->format('d M y');
        $toFormatted   = Carbon::parse($this->to)->format('d M y');
        $ws->getRowDimension(4)->setRowHeight(16);
        $ws->setCellValue('B4', "Infrastructure health & incident overview • {$fromFormatted} – {$toFormatted}");
        $ws->getStyle('B4')->getFont()->setSize(9)->setItalic(true)->getColor()->setRGB('64748B');

        // ── 2. CALCULATE KPI METRICS ─────────────────────────────────────────
        $sites = ['Bogor', 'Karawang', 'Tangerang'];
        $matrix = [];
        foreach ($sites as $site) {
            $matrix[$site] = [
                'network' => $this->findUptimeForSite($rawNetwork, $site),
                'nvr'     => $this->findUptimeForSite($rawNvr, $site),
                'cctv'    => $this->findUptimeForSite($rawCctv, $site),
                'server'  => $this->findUptimeForSite($rawServer, $site),
            ];
        }

        $avgNetwork = $this->calcAverage(array_column($matrix, 'network'));
        $avgNvr     = $this->calcAverage(array_column($matrix, 'nvr'));
        $avgCctv    = $this->calcAverage(array_column($matrix, 'cctv'));
        $avgServer  = $this->calcAverage(array_column($matrix, 'server'));
        $overallUptime = round(($avgNetwork + $avgNvr + $avgCctv + $avgServer) / 4, 1);

        // Bandwidth Snapshot Rows with default SLA capacity fallback
        $bwRows = [];
        $dlValues = [];
        $ulValues = [];
        foreach ($rawBandwidth as $b) {
            $cleanLoc = $this->cleanLocation($b['location']);
            foreach ($b['providers'] as $p) {
                $limit = $p['bandwidth_limit'] ?? null;
                if (!$limit || $limit <= 0) {
                    $limit = $this->getDefaultCapacity($cleanLoc, $p['provider']);
                }

                $dl = isset($p['avg_download']) && is_numeric($p['avg_download']) ? (float) $p['avg_download'] : null;
                $ul = isset($p['avg_upload']) && is_numeric($p['avg_upload']) ? (float) $p['avg_upload'] : null;

                if ($dl !== null && $dl > 0) $dlValues[] = $dl;
                if ($ul !== null && $ul > 0) $ulValues[] = $ul;

                $bwRows[] = [
                    'branch'   => $cleanLoc,
                    'isp'      => $p['provider'],
                    'capacity' => $limit ? number_format($limit, 1) : '-',
                    'down'     => $dl !== null ? number_format($dl, 1) . ' Mbps' : '-',
                    'up'       => $ul !== null ? number_format($ul, 1) . ' Mbps' : '-',
                ];
            }
        }

        $avgDl = count($dlValues) ? round(array_sum($dlValues) / count($dlValues), 1) : 0;
        $avgUl = count($ulValues) ? round(array_sum($ulValues) / count($ulValues), 1) : 0;

        // PC Issues Resolved
        $totalIssues  = array_sum(array_map('intval', array_column($rawHelpdesk, 'case')));
        $closedIssues = array_sum(array_map('intval', array_column($rawHelpdesk, 'closed')));

        // Failed Devices: Group by device to avoid duplicate daily rows
        $failedDevicesMap = [];
        $gatherFailed = function (array $report, string $category) use (&$failedDevicesMap) {
            foreach ($report as $siteItem) {
                $cleanSite = $this->cleanLocation($siteItem['location']);
                foreach ($siteItem['failed_list'] ?? [] as $f) {
                    if (($f['uptime_percent'] ?? 100) < 100) {
                        $key = $category . '_' . ($f['ip_address'] ?? '') . '_' . ($f['device_name'] ?? '');
                        if (!isset($failedDevicesMap[$key])) {
                            $failedDevicesMap[$key] = [
                                'location'    => $cleanSite,
                                'date'        => isset($f['report_date']) ? Carbon::parse($f['report_date'])->format('d-M-y') : '-',
                                'category'    => $category,
                                'device_name' => $f['device_name'] ?? '-',
                                'ip_address'  => $f['ip_address'] ?? '-',
                                'duration'    => $f['duration'] ?? '-',
                                'remark'      => $f['notes_maintenance_log'] ?? $f['remark'] ?? '-',
                            ];
                        } else {
                            if (isset($f['report_date'])) {
                                $failedDevicesMap[$key]['date'] = Carbon::parse($f['report_date'])->format('d-M-y');
                            }
                            if (($failedDevicesMap[$key]['remark'] === '-' || empty($failedDevicesMap[$key]['remark'])) && !empty($f['notes_maintenance_log'])) {
                                $failedDevicesMap[$key]['remark'] = $f['notes_maintenance_log'];
                            }
                        }
                    }
                }
            }
        };
        $gatherFailed($rawNetwork, 'Network');
        $gatherFailed($rawNvr, 'NVR');
        $gatherFailed($rawCctv, 'CCTV');
        $gatherFailed($rawServer, 'Server');

        $failedDevices = array_values($failedDevicesMap);
        $failedCount = count($failedDevices);

        // ── 3. TOP KPI CARDS (Rows 6-7) ─────────────────────────────────────
        // Card labels (Row 6)
        $ws->mergeCells('B6:D6');
        $ws->setCellValue('B6', 'SYSTEM UPTIME');
        $ws->mergeCells('E6:G6');
        $ws->setCellValue('E6', 'AVG BANDWIDTH (Mbps)');
        $ws->mergeCells('H6:J6');
        $ws->setCellValue('H6', 'PC ISSUES RESOLVED');
        $ws->mergeCells('K6:L6');
        $ws->setCellValue('K6', 'FAILED DEVICES');

        // Card values (Row 7)
        $ws->mergeCells('B7:D7');
        $ws->setCellValue('B7', number_format($overallUptime, 1) . '%');
        $ws->mergeCells('E7:G7');
        $ws->setCellValue('E7', "{$avgDl} ↓ / {$avgUl} ↑");
        $ws->mergeCells('H7:J7');
        $ws->setCellValue('H7', "{$closedIssues} / {$totalIssues}");
        $ws->mergeCells('K7:L7');
        $ws->setCellValue('K7', $failedCount);

        // Styling KPI Box
        $ws->getRowDimension(6)->setRowHeight(18);
        $ws->getRowDimension(7)->setRowHeight(34);

        $ws->getStyle('B6:L7')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8FAFC']],
        ]);

        $ws->getStyle('B6:L7')->applyFromArray([
            'borders' => [
                'outline' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CBD5E1'],
                ],
            ],
        ]);

        $ws->getStyle('B6:L6')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 8.5, 'color' => ['rgb' => '64748B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $ws->getStyle('B7:L7')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 17],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        // Colors for KPI values
        $ws->getStyle('B7')->getFont()->getColor()->setRGB('00875A');
        $ws->getStyle('E7')->getFont()->getColor()->setRGB('00875A');
        $ws->getStyle('H7')->getFont()->getColor()->setRGB('00875A');
        $ws->getStyle('K7')->getFont()->getColor()->setRGB($failedCount > 0 ? 'D92D20' : '00875A');

        // ── 4. MIDDLE SECTION: 2 SIDE-BY-SIDE TABLES (Rows 10+) ──────────────
        // Banner Headers (Row 10)
        $ws->getRowDimension(10)->setRowHeight(24);
        $ws->mergeCells('B10:F10');
        $ws->setCellValue('B10', 'BRANCH HEALTH');

        $ws->mergeCells('H10:L10');
        $ws->setCellValue('H10', 'BANDWIDTH SNAPSHOT');

        $darkGreenBanner = [
            'font'      => ['bold' => true, 'size' => 9.5, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '003628']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
        ];
        $ws->getStyle('B10:F10')->applyFromArray($darkGreenBanner);
        $ws->getStyle('H10:L10')->applyFromArray($darkGreenBanner);

        // Subheaders (Row 11) - Warm Golden Amber Background
        $ws->getRowDimension(11)->setRowHeight(20);
        $amberHeader = [
            'font'      => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'C88528']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ];

        // Left Table Headers (B to F)
        $ws->setCellValue('B11', 'Branch');
        $ws->setCellValue('C11', 'Network');
        $ws->setCellValue('D11', 'NVR');
        $ws->setCellValue('E11', 'CCTV');
        $ws->setCellValue('F11', 'Server');
        $ws->getStyle('B11:F11')->applyFromArray(array_merge($borderThin, $amberHeader));
        $ws->getStyle('B11')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getStyle('C11:F11')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Right Table Headers (H to L)
        $ws->setCellValue('H11', 'Branch');
        $ws->setCellValue('I11', 'ISP');
        $ws->setCellValue('J11', 'Capacity');
        $ws->setCellValue('K11', 'Down');
        $ws->setCellValue('L11', 'Up');
        $ws->getStyle('H11:L11')->applyFromArray(array_merge($borderThin, $amberHeader));
        $ws->getStyle('H11:I11')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getStyle('J11')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getStyle('K11:L11')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // Left Table Rows (Bogor, Karawang, Tangerang)
        $rowL = 12;
        foreach ($sites as $site) {
            $ws->getRowDimension($rowL)->setRowHeight(20);
            $ws->setCellValue("B{$rowL}", $site);
            $ws->setCellValue("C{$rowL}", number_format($matrix[$site]['network'], 1) . '%');
            $ws->setCellValue("D{$rowL}", number_format($matrix[$site]['nvr'], 1) . '%');
            $ws->setCellValue("E{$rowL}", number_format($matrix[$site]['cctv'], 1) . '%');
            $ws->setCellValue("F{$rowL}", number_format($matrix[$site]['server'], 1) . '%');

            $ws->getStyle("B{$rowL}")->getFont()->setBold(true);
            $ws->getStyle("B{$rowL}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
            $ws->getStyle("C{$rowL}:F{$rowL}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $ws->getStyle("B{$rowL}:F{$rowL}")->applyFromArray($borderThin);
            $rowL++;
        }

        // Left Table: Average Row
        $ws->getRowDimension($rowL)->setRowHeight(20);
        $ws->setCellValue("B{$rowL}", 'Average');
        $ws->setCellValue("C{$rowL}", number_format($avgNetwork, 1) . '%');
        $ws->setCellValue("D{$rowL}", number_format($avgNvr, 1) . '%');
        $ws->setCellValue("E{$rowL}", number_format($avgCctv, 1) . '%');
        $ws->setCellValue("F{$rowL}", number_format($avgServer, 1) . '%');

        $ws->getStyle("B{$rowL}:F{$rowL}")->applyFromArray(array_merge($borderThin, [
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8FAFC']],
        ]));
        $ws->getStyle("B{$rowL}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
        $ws->getStyle("C{$rowL}:F{$rowL}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $rowL++;

        // Right Table Rows (Bandwidth items H to L)
        $rowR = 12;
        foreach ($bwRows as $bw) {
            $ws->getRowDimension($rowR)->setRowHeight(20);
            $ws->setCellValue("H{$rowR}", $bw['branch']);
            $ws->setCellValue("I{$rowR}", $bw['isp']);
            $ws->setCellValue("J{$rowR}", $bw['capacity']);
            $ws->setCellValue("K{$rowR}", $bw['down']);
            $ws->setCellValue("L{$rowR}", $bw['up']);

            $ws->getStyle("H{$rowR}")->getFont()->setBold(true);
            $ws->getStyle("H{$rowR}:I{$rowR}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
            $ws->getStyle("J{$rowR}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $ws->getStyle("K{$rowR}:L{$rowR}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT)->setVertical(Alignment::VERTICAL_CENTER);
            $ws->getStyle("H{$rowR}:L{$rowR}")->applyFromArray($borderThin);
            $rowR++;
        }

        if (empty($bwRows)) {
            $ws->mergeCells("H{$rowR}:L{$rowR}");
            $ws->setCellValue("H{$rowR}", 'Belum ada data bandwidth');
            $ws->getStyle("H{$rowR}:L{$rowR}")->applyFromArray(array_merge($borderThin, [
                'font' => ['italic' => true, 'color' => ['rgb' => '888888']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]));
            $rowR++;
        }

        // Blank spacer row between sections
        $startActionRow = max($rowL, $rowR) + 1;
        $ws->getRowDimension($startActionRow - 1)->setRowHeight(12);

        // ── 5. BOTTOM SECTION: ACTION REQUIRED • FAILED DEVICES ─────────────
        $ws->getRowDimension($startActionRow)->setRowHeight(24);
        $ws->mergeCells("B{$startActionRow}:L{$startActionRow}");
        $ws->setCellValue("B{$startActionRow}", 'ACTION REQUIRED • FAILED DEVICES');
        $ws->getStyle("B{$startActionRow}:L{$startActionRow}")->applyFromArray($darkGreenBanner);

        // Subheaders (Crimson Red Banner)
        $subHRow = $startActionRow + 1;
        $ws->getRowDimension($subHRow)->setRowHeight(20);
        $redHeader = [
            'font'      => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '991B1B']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ];

        $ws->setCellValue("B{$subHRow}", 'Location');
        $ws->setCellValue("C{$subHRow}", 'Date');
        $ws->setCellValue("D{$subHRow}", 'Category');
        $ws->mergeCells("E{$subHRow}:F{$subHRow}");
        $ws->setCellValue("E{$subHRow}", 'Device Name');
        $ws->mergeCells("G{$subHRow}:H{$subHRow}");
        $ws->setCellValue("G{$subHRow}", 'IP Address');
        $ws->setCellValue("I{$subHRow}", 'Downtime');
        $ws->mergeCells("J{$subHRow}:L{$subHRow}");
        $ws->setCellValue("J{$subHRow}", 'Remark');

        $ws->getStyle("B{$subHRow}:L{$subHRow}")->applyFromArray(array_merge($borderThin, $redHeader));
        $ws->getStyle("B{$subHRow}:F{$subHRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getStyle("G{$subHRow}:I{$subHRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getStyle("J{$subHRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        // Data Rows for Failed Devices
        $curRow = $subHRow + 1;
        if (!empty($failedDevices)) {
            foreach ($failedDevices as $dev) {
                $ws->getRowDimension($curRow)->setRowHeight(20);
                $ws->setCellValue("B{$curRow}", $dev['location']);
                $ws->setCellValue("C{$curRow}", $dev['date']);
                $ws->setCellValue("D{$curRow}", $dev['category']);
                $ws->mergeCells("E{$curRow}:F{$curRow}");
                $ws->setCellValue("E{$curRow}", $dev['device_name']);
                $ws->mergeCells("G{$curRow}:H{$curRow}");
                $ws->setCellValue("G{$curRow}", $dev['ip_address']);
                $ws->setCellValue("I{$curRow}", $dev['duration']);
                $ws->mergeCells("J{$curRow}:L{$curRow}");
                $ws->setCellValue("J{$curRow}", $dev['remark']);

                $ws->getStyle("B{$curRow}")->getFont()->setBold(true);
                $ws->getStyle("B{$curRow}:F{$curRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
                $ws->getStyle("G{$curRow}:I{$curRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                $ws->getStyle("J{$curRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
                $ws->getStyle("B{$curRow}:L{$curRow}")->applyFromArray($borderThin);
                $curRow++;
            }
        } else {
            // No failed devices state
            $ws->getRowDimension($curRow)->setRowHeight(26);
            $ws->mergeCells("B{$curRow}:L{$curRow}");
            $ws->setCellValue("B{$curRow}", 'Semua Perangkat Normal (100% Uptime)');
            $ws->getStyle("B{$curRow}:L{$curRow}")->applyFromArray(array_merge($borderThin, [
                'font' => ['bold' => true, 'color' => ['rgb' => '00875A']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]));
            $curRow++;
        }

        // ── 6. FOOTER CAPTION ────────────────────────────────────────────────
        $footerRow = $curRow + 1;
        $ws->getRowDimension($footerRow)->setRowHeight(16);
        $ws->mergeCells("B{$footerRow}:L{$footerRow}");
        $ws->setCellValue("B{$footerRow}", 'Source: Weekly Infra Report data • Dashboard is linked to the Data sheet.');
        $ws->getStyle("B{$footerRow}")->getFont()->setSize(8.5)->setItalic(true)->getColor()->setRGB('888888');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SHEET 2: Network Devices (Full inventory & status)
    // ─────────────────────────────────────────────────────────────────────────

    private function buildNetworkSheet(Spreadsheet $spreadsheet): void
    {
        $ws = $spreadsheet->createSheet();
        $ws->setTitle('Network Devices');
        $ws->setShowGridLines(true);

        $headers = array_merge(
            ['No', 'Site', 'Location', 'Host Group', 'Device Name', 'IP Address'],
            self::weeklyUptimeHeaderLabels($this->from, $this->to),
            ['Status', 'Downtime', 'Remark']
        );
        $this->writeHeaderRow($ws, $headers);

        $devices = NetworkDevice::where('is_active', true)
            ->where('is_excluded', false)
            ->orderBy('site')
            ->orderBy('device_name')
            ->get();

        $row = 2;
        $no = 1;
        foreach ($devices as $dev) {
            $rows = DB::table('network_uptime_daily')
                ->where('device_id', $dev->id)
                ->whereBetween('report_date', [$this->from, $this->to])
                ->orderBy('report_date')
                ->get();

            $dailyMap = $rows->keyBy('report_date');
            $uptimes = [];
            $cursor = Carbon::parse($this->from);
            $end = Carbon::parse($this->to);
            while ($cursor->lte($end) && count($uptimes) < 7) {
                $dateKey = $cursor->toDateString();
                $uptime = $dailyMap->get($dateKey)?->uptime_percent;
                $uptimes[] = $uptime !== null ? number_format((float) $uptime, 1) . '%' : '-';
                $cursor->addDay();
            }

            $values = $rows->pluck('uptime_percent')
                ->filter(fn ($v) => $v !== null && is_numeric($v))
                ->map(fn ($v) => (float) $v)
                ->all();
            $avgUptime = count($values) > 0 ? round(array_sum($values) / count($values), 2) : 100.0;

            $log = DB::table('network_maintenance_logs')
                ->where('device_id', $dev->id)
                ->whereBetween(DB::raw('DATE(started_at)'), [$this->from, $this->to])
                ->latest('started_at')
                ->first();

            $duration = $this->formatDuration($log?->started_at, $log?->resolved_at);
            $statusText = $avgUptime >= 99 ? 'Normal' : ($avgUptime >= 90 ? 'Warning' : 'Critical');

            $rowValues = [
                $no++,
                $this->cleanLocation($dev->site),
                $dev->location ?? '-',
                $dev->host_group ?? '-',
                $dev->device_name,
                $dev->ip_address,
            ];
            $rowValues = array_merge($rowValues, $uptimes, [
                number_format($avgUptime, 1) . '%',
                $statusText,
                $duration,
                $log?->notes ?? '-',
            ]);

            $ws->fromArray($rowValues, null, "A{$row}");

            $this->applyDataRowStyle($ws, $row, count($headers), $avgUptime < 100);
            $row++;
        }

        $this->autoSizeColumns($ws, count($headers));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SHEET 3: NVR & CCTV Devices
    // ─────────────────────────────────────────────────────────────────────────

    private function buildCctvSheet(Spreadsheet $spreadsheet): void
    {
        $ws = $spreadsheet->createSheet();
        $ws->setTitle('NVR & CCTV');
        $ws->setShowGridLines(true);

        $headers = array_merge(
            ['No', 'Site', 'Type', 'Location', 'Device Name', 'IP Address'],
            self::weeklyUptimeHeaderLabels($this->from, $this->to),
            ['Status', 'Downtime', 'Remark']
        );
        $this->writeHeaderRow($ws, $headers);

        $devices = CctvDevice::where('is_active', true)
            ->where('is_excluded', false)
            ->orderBy('site')
            ->orderBy('device_type')
            ->orderBy('device_name')
            ->get();

        $row = 2;
        $no = 1;
        foreach ($devices as $dev) {
            $rows = DB::table('cctv_uptime_daily')
                ->where('device_id', $dev->id)
                ->whereBetween('report_date', [$this->from, $this->to])
                ->orderBy('report_date')
                ->get();

            $dailyMap = $rows->keyBy('report_date');
            $uptimes = [];
            $cursor = Carbon::parse($this->from);
            $end = Carbon::parse($this->to);
            while ($cursor->lte($end) && count($uptimes) < 7) {
                $dateKey = $cursor->toDateString();
                $uptime = $dailyMap->get($dateKey)?->uptime_percent;
                $uptimes[] = $uptime !== null ? number_format((float) $uptime, 1) . '%' : '-';
                $cursor->addDay();
            }

            $values = $rows->pluck('uptime_percent')
                ->filter(fn ($v) => $v !== null && is_numeric($v))
                ->map(fn ($v) => (float) $v)
                ->all();
            $avgUptime = count($values) > 0 ? round(array_sum($values) / count($values), 2) : 100.0;

            $log = DB::table('cctv_maintenance_logs')
                ->where('device_id', $dev->id)
                ->whereBetween(DB::raw('DATE(started_at)'), [$this->from, $this->to])
                ->latest('started_at')
                ->first();

            $duration = $this->formatDuration($log?->started_at, $log?->resolved_at);
            $statusText = $avgUptime >= 99 ? 'Normal' : ($avgUptime >= 90 ? 'Warning' : 'Critical');

            $rowValues = [
                $no++,
                $this->cleanLocation($dev->site),
                $dev->device_type,
                $dev->location ?? '-',
                $dev->device_name,
                $dev->ip_address,
            ];
            $rowValues = array_merge($rowValues, $uptimes, [
                number_format($avgUptime, 1) . '%',
                $statusText,
                $duration,
                $log?->notes ?? '-',
            ]);

            $ws->fromArray($rowValues, null, "A{$row}");

            $this->applyDataRowStyle($ws, $row, count($headers), $avgUptime < 100);
            $row++;
        }

        $this->autoSizeColumns($ws, count($headers));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SHEET 4: Server Devices
    // ─────────────────────────────────────────────────────────────────────────

    private function buildServerSheet(Spreadsheet $spreadsheet): void
    {
        $ws = $spreadsheet->createSheet();
        $ws->setTitle('Servers');
        $ws->setShowGridLines(true);

        $headers = ['No', 'Site', 'Location', 'Host / Device Name', 'IP Address', 'Avg CPU %', 'Avg RAM %', 'Disk Free', 'Uptime %', 'Status'];
        $this->writeHeaderRow($ws, $headers);

        $devices = ServerDevice::where('is_active', true)
            ->where('is_excluded', false)
            ->orderBy('site')
            ->orderBy('device_name')
            ->get();

        $daysCount = Carbon::parse($this->from)->diffInDays(Carbon::parse($this->to)) + 1;

        $row = 2;
        $no = 1;
        foreach ($devices as $dev) {
            $resRows = DB::table('server_resource_daily')
                ->where('host_id', $dev->source_id)
                ->whereBetween('report_date', [$this->from, $this->to])
                ->get();

            $avgCpu = null;
            $avgRam = null;
            if ($resRows->isNotEmpty()) {
                $cpuVals = $resRows->pluck('cpu_usage_percent')
                    ->filter(fn($v) => $v !== null && is_numeric($v))
                    ->map(fn($v) => (float) $v);
                $ramVals = $resRows->pluck('memory_usage_percent')
                    ->filter(fn($v) => $v !== null && is_numeric($v))
                    ->map(fn($v) => (float) $v);

                if ($cpuVals->isNotEmpty()) {
                    $avgCpu = round((float) $cpuVals->avg(), 1);
                }
                if ($ramVals->isNotEmpty()) {
                    $avgRam = round((float) $ramVals->avg(), 1);
                }
            }

            $latestHdd = $resRows->last()?->hdd_free_percent ?? '-';

            $uptime = min(100.0, round(($resRows->count() / max(1, $daysCount)) * 100, 1));
            $statusText = $uptime >= 99 ? 'Normal' : ($uptime >= 90 ? 'Warning' : 'Critical');

            $ws->fromArray([
                $no++,
                $this->cleanLocation($dev->site),
                $dev->location ?? '-',
                $dev->device_name,
                $dev->ip_address,
                $avgCpu !== null ? number_format($avgCpu, 1) . '%' : '-',
                $avgRam !== null ? number_format($avgRam, 1) . '%' : '-',
                $latestHdd ?: '-',
                number_format($uptime, 1) . '%',
                $statusText,
            ], null, "A{$row}");

            $this->applyDataRowStyle($ws, $row, 10, $uptime < 100);
            $row++;
        }

        $this->autoSizeColumns($ws, 10);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SHEET 5: Bandwidth Traffic Detail
    // ─────────────────────────────────────────────────────────────────────────

    private function buildBandwidthSheet(Spreadsheet $spreadsheet): void
    {
        $ws = $spreadsheet->createSheet();
        $ws->setTitle('Bandwidth Traffic');
        $ws->setShowGridLines(true);

        $dateLabels = self::weeklyBandwidthDateLabels($this->from, $this->to);
        $headers = array_merge(
            ['No', 'Site', 'Provider', 'Description', 'SLA Capacity (Mbps)'],
            $dateLabels,
            ['Avg (Mbps)', 'Remark']
        );
        $this->writeHeaderRow($ws, $headers);

        $records = DB::table('bandwidth_daily')
            ->whereBetween('report_date', [$this->from, $this->to])
            ->orderBy('location')
            ->orderBy('provider')
            ->orderBy('description')
            ->orderBy('report_date')
            ->get();

        $contracts = DB::table('isp_sla_contracts')->get();

        $groups = $records->groupBy(fn ($record) => implode('|', [
            $record->location,
            $record->provider,
            $record->description ?? '-',
        ]));

        $row = 2;
        $no = 1;
        foreach ($groups as $group) {
            $first = $group->first();
            $cleanLoc = $this->cleanLocation($first->location);
            $matchedContract = $contracts->first(function ($c) use ($first, $cleanLoc) {
                return stripos($c->provider, $first->provider) !== false
                    && stripos($c->location, $cleanLoc) !== false;
            });

            $limit = $matchedContract ? (float) $matchedContract->bandwidth : $this->getDefaultCapacity($cleanLoc, $first->provider);
            $dailyMap = $group->keyBy('report_date');
            $dailyValues = [];
            $dailyDates = [];
            $cursor = Carbon::parse($this->from);
            $end = Carbon::parse($this->to);

            while ($cursor->lte($end) && count($dailyDates) < 7) {
                $dateKey = $cursor->toDateString();
                $value = $dailyMap->get($dateKey)?->value_mbps;
                $dailyDates[] = $value !== null ? number_format((float) $value, 2) : '-';
                if ($value !== null && is_numeric($value)) {
                    $dailyValues[] = (float) $value;
                }
                $cursor->addDay();
            }

            $avg = count($dailyValues) > 0 ? array_sum($dailyValues) / count($dailyValues) : null;

            $rowValues = [
                $no++,
                $cleanLoc,
                $first->provider,
                $first->description ?? '-',
                $limit ? number_format($limit, 1) : '-',
            ];
            $rowValues = array_merge($rowValues, $dailyDates, [
                $avg !== null ? number_format($avg, 2) : '-',
                $first->remark ?? '-',
            ]);

            $ws->fromArray($rowValues, null, "A{$row}");

            $this->applyDataRowStyle($ws, $row, count($headers), false);
            $row++;
        }

        if ($records->isEmpty()) {
            $ws->mergeCells('A2:' . $this->columnLetter(count($headers)) . '2');
            $ws->setCellValue('A2', 'Tidak ada data bandwidth harian untuk periode ini');
            $ws->getStyle('A2')->getFont()->setItalic(true);
        }

        $this->autoSizeColumns($ws, count($headers));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SHEET 6: Helpdesk Tickets
    // ─────────────────────────────────────────────────────────────────────────

    private function buildHelpdeskSheet(Spreadsheet $spreadsheet): void
    {
        $ws = $spreadsheet->createSheet();
        $ws->setTitle('Helpdesk Tickets');
        $ws->setShowGridLines(true);

        $headers = ['No', 'Ticket ID', 'Location', 'Requester', 'Department', 'Issue Description', 'Action Taken', 'Created At', 'Date Closed', 'Status'];
        $this->writeHeaderRow($ws, $headers);

        $tickets = Ticket::whereBetween('created_at', [$this->from . ' 00:00:00', $this->to . ' 23:59:59'])
            ->orderBy('created_at', 'desc')
            ->get();

        $row = 2;
        $no = 1;
        foreach ($tickets as $t) {
            $ws->fromArray([
                $no++,
                '#' . $t->id,
                $t->location ?? '-',
                $t->requester ?? '-',
                $t->department ?? '-',
                $t->issue_description ?? '-',
                $t->action_taken ?? '-',
                $t->created_at ? $t->created_at->format('Y-m-d H:i') : '-',
                $t->date_closed ? Carbon::parse($t->date_closed)->format('Y-m-d H:i') : '-',
                strtoupper($t->status ?? 'OPEN'),
            ], null, "A{$row}");

            $this->applyDataRowStyle($ws, $row, 10, false);
            $row++;
        }

        if ($tickets->isEmpty()) {
            $ws->mergeCells('A2:J2');
            $ws->setCellValue('A2', 'Tidak ada tiket helpdesk pada periode ini');
            $ws->getStyle('A2')->getFont()->setItalic(true);
        }

        $this->autoSizeColumns($ws, 10);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SHEET 7: Maintenance Logs (All categories merged)
    // ─────────────────────────────────────────────────────────────────────────

    private function buildMaintenanceSheet(Spreadsheet $spreadsheet): void
    {
        $ws = $spreadsheet->createSheet();
        $ws->setTitle('Maintenance Logs');
        $ws->setShowGridLines(true);

        $headers = ['No', 'Category', 'Site', 'Device Name', 'IP Address', 'Event Type', 'Status', 'Started At', 'Resolved At', 'Duration', 'Notes / Remark'];
        $this->writeHeaderRow($ws, $headers);

        $allLogs = [];

        // Network logs
        $netLogs = DB::table('network_maintenance_logs')
            ->join('network_devices', 'network_maintenance_logs.device_id', '=', 'network_devices.id')
            ->whereBetween(DB::raw('DATE(network_maintenance_logs.started_at)'), [$this->from, $this->to])
            ->select('network_devices.site', 'network_devices.device_name', 'network_devices.ip_address', 'network_maintenance_logs.*')
            ->get();
        foreach ($netLogs as $l) {
            $allLogs[] = array_merge((array) $l, ['category' => 'Network']);
        }

        // CCTV logs
        $cctvLogs = DB::table('cctv_maintenance_logs')
            ->join('cctv_devices', 'cctv_maintenance_logs.device_id', '=', 'cctv_devices.id')
            ->whereBetween(DB::raw('DATE(cctv_maintenance_logs.started_at)'), [$this->from, $this->to])
            ->select('cctv_devices.site', 'cctv_devices.device_name', 'cctv_devices.ip_address', 'cctv_devices.device_type', 'cctv_maintenance_logs.*')
            ->get();
        foreach ($cctvLogs as $l) {
            $cat = !empty($l->device_type) ? strtoupper($l->device_type) : 'CCTV';
            $allLogs[] = array_merge((array) $l, ['category' => $cat]);
        }

        // Server logs
        $serverLogs = DB::table('server_maintenance_logs')
            ->join('server_devices', 'server_maintenance_logs.device_id', '=', 'server_devices.id')
            ->whereBetween(DB::raw('DATE(server_maintenance_logs.started_at)'), [$this->from, $this->to])
            ->select('server_devices.site', 'server_devices.device_name', 'server_devices.ip_address', 'server_maintenance_logs.*')
            ->get();
        foreach ($serverLogs as $l) {
            $allLogs[] = array_merge((array) $l, ['category' => 'Server']);
        }

        usort($allLogs, fn($a, $b) => strcmp($b['started_at'] ?? '', $a['started_at'] ?? ''));

        $row = 2;
        $no = 1;
        foreach ($allLogs as $log) {
            $duration = $this->formatDuration($log['started_at'] ?? null, $log['resolved_at'] ?? null);

            $ws->fromArray([
                $no++,
                $log['category'],
                $this->cleanLocation($log['site'] ?? '-'),
                $log['device_name'] ?? '-',
                $log['ip_address'] ?? '-',
                strtoupper($log['event_type'] ?? 'MAINTENANCE'),
                strtoupper($log['status'] ?? 'CLOSED'),
                $log['started_at'] ?? '-',
                $log['resolved_at'] ?? '-',
                $duration,
                $log['notes'] ?? '-',
            ], null, "A{$row}");

            $this->applyDataRowStyle($ws, $row, 11, false);
            $row++;
        }

        if (empty($allLogs)) {
            $ws->mergeCells('A2:K2');
            $ws->setCellValue('A2', 'Tidak ada log maintenance / downtime pada periode ini');
            $ws->getStyle('A2')->getFont()->setItalic(true);
        }

        $this->autoSizeColumns($ws, 11);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Formatting & Helper Methods
    // ─────────────────────────────────────────────────────────────────────────

    private function writeHeaderRow(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, array $headers): void
    {
        $ws->fromArray($headers, null, 'A1');
        $ws->getRowDimension(1)->setRowHeight(24);

        $endCol = chr(64 + count($headers));
        $ws->getStyle("A1:{$endCol}1")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 9],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '003628']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D0D5DD']]],
        ]);
    }

    private function applyDataRowStyle(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws,
        int $row,
        int $colCount,
        bool $isWarning
    ): void {
        $endCol = chr(64 + $colCount);
        $ws->getRowDimension($row)->setRowHeight(20);

        $style = [
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ];

        if ($isWarning) {
            $style['fill'] = ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF1F2']];
        } elseif ($row % 2 === 0) {
            $style['fill'] = ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8FAFC']];
        }

        $ws->getStyle("A{$row}:{$endCol}{$row}")->applyFromArray($style);
    }

    private function autoSizeColumns(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, int $colCount): void
    {
        for ($i = 1; $i <= $colCount; $i++) {
            $col = chr(64 + $i);
            $ws->getColumnDimension($col)->setAutoSize(true);
        }
    }

    private function columnLetter(int $column): string
    {
        $letter = '';
        while ($column > 0) {
            $column--;
            $letter = chr(65 + ($column % 26)) . $letter;
            $column = intdiv($column, 26);
        }

        return $letter;
    }

    private function cleanLocation(?string $loc): string
    {
        if (!$loc) return '-';
        return trim(preg_replace('/^F\d+\s+/i', '', $loc));
    }

    private function getDefaultCapacity(string $location, string $provider): ?float
    {
        $loc = strtoupper($location);
        $p   = strtoupper($provider);

        if (str_contains($loc, 'BOGOR')) {
            if (str_contains($p, 'ISAT') || str_contains($p, 'INDOSAT')) return 180.0;
            if (str_contains($p, 'TGG')) return 100.0;
        } elseif (str_contains($loc, 'KARAWANG')) {
            if (str_contains($p, 'ISAT') || str_contains($p, 'INDOSAT')) return 180.0;
            if (str_contains($p, 'TGG')) return 80.0;
        } elseif (str_contains($loc, 'TANGERANG')) {
            if (str_contains($p, 'BIZNET')) return 240.0;
            if (str_contains($p, 'TGG')) return 100.0;
        }
        return null;
    }

    private function findUptimeForSite(array $report, string $site): float
    {
        foreach ($report as $r) {
            if (stripos($r['location'], $site) !== false) {
                return (float) ($r['uptime'] ?? 100.0);
            }
        }
        return 100.0;
    }

    private function calcAverage(array $numbers): float
    {
        if (empty($numbers)) return 100.0;
        return round(array_sum($numbers) / count($numbers), 1);
    }

    private function formatDuration(?string $startedAt, ?string $resolvedAt): string
    {
        if (!$startedAt) return '-';
        $start = Carbon::parse($startedAt);
        $end   = $resolvedAt ? Carbon::parse($resolvedAt) : now();

        $diff = $start->diff($end);
        $parts = [];
        if ($diff->d > 0) $parts[] = "{$diff->d}d";
        if ($diff->h > 0) $parts[] = str_pad($diff->h, 2, '0', STR_PAD_LEFT) . "h";
        if ($diff->i > 0) $parts[] = str_pad($diff->i, 2, '0', STR_PAD_LEFT) . "m";

        return empty($parts) ? '0s' : implode(' ', $parts);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Legacy / Compatible Data Fetchers (kept for test compatibility)
    // ─────────────────────────────────────────────────────────────────────────

    private function getUptimeReport(array $sites, string $type): array
    {
        $results = [];

        foreach ($sites as $site) {
            if ($type === 'network') {
                $devices = NetworkDevice::where('site', $site)->where('is_active', true)->where('is_excluded', false)->get();
            } elseif ($type === 'server') {
                $devices = ServerDevice::where('site', $site)->where('is_active', true)->where('is_excluded', false)->get();
            } else {
                $devices = CctvDevice::where('site', $site)
                    ->where('device_type', strtoupper($type))
                    ->where('is_active', true)
                    ->where('is_excluded', false)
                    ->get();
            }

            $deviceIds = $devices->pluck('id');
            $qty       = $deviceIds->count();

            if ($qty === 0) {
                $results[] = [
                    'location'    => $site,
                    'qty'         => 0,
                    'uptime'      => 100.0,
                    'failed_list' => [],
                ];
                continue;
            }

            if ($type === 'server') {
                $sourceIds = $devices->pluck('source_id');
                $daysCount = Carbon::parse($this->from)->diffInDays(Carbon::parse($this->to)) + 1;
                $rows = DB::table('server_resource_daily')
                    ->whereIn('host_id', $sourceIds)
                    ->whereBetween('report_date', [$this->from, $this->to])
                    ->get();

                $totalExpectedSlots = $qty * $daysCount;
                $actualSlots        = $rows->count();
                $avgUptime          = $totalExpectedSlots > 0 ? ($actualSlots / $totalExpectedSlots) * 100 : 100;
                $avgUptime          = min(100.0, round($avgUptime, 2));

                $failedList = [];
            } elseif ($type === 'network') {
                $avgUptime = DB::table('network_uptime_daily')
                    ->whereIn('device_id', $deviceIds)
                    ->whereBetween('report_date', [$this->from, $this->to])
                    ->avg('uptime_percent') ?? 100.0;

                $failedList = DB::table('network_uptime_daily')
                    ->join('network_devices', 'network_uptime_daily.device_id', '=', 'network_devices.id')
                    ->leftJoin('network_maintenance_logs', function ($join) {
                        $join->on('network_uptime_daily.device_id', '=', 'network_maintenance_logs.device_id')
                             ->where(function ($q) {
                                 $q->whereRaw('network_uptime_daily.report_date >= DATE(network_maintenance_logs.started_at)')
                                   ->whereRaw('(network_maintenance_logs.resolved_at IS NULL OR network_uptime_daily.report_date <= DATE(network_maintenance_logs.resolved_at))');
                             });
                    })
                    ->whereIn('network_uptime_daily.device_id', $deviceIds)
                    ->whereBetween('network_uptime_daily.report_date', [$this->from, $this->to])
                    ->where('network_uptime_daily.uptime_percent', '<', 100)
                    ->select(
                        'network_devices.device_name',
                        'network_devices.ip_address',
                        'network_uptime_daily.report_date',
                        'network_uptime_daily.uptime_percent',
                        'network_maintenance_logs.started_at',
                        'network_maintenance_logs.resolved_at',
                        'network_maintenance_logs.notes as notes_maintenance_log'
                    )
                    ->orderBy('network_uptime_daily.report_date', 'desc')
                    ->get()
                    ->map(fn($r) => (array) $r)
                    ->toArray();
            } else {
                $avgUptime = DB::table('cctv_uptime_daily')
                    ->whereIn('device_id', $deviceIds)
                    ->whereBetween('report_date', [$this->from, $this->to])
                    ->avg('uptime_percent') ?? 100.0;

                $failedList = DB::table('cctv_uptime_daily')
                    ->join('cctv_devices', 'cctv_uptime_daily.device_id', '=', 'cctv_devices.id')
                    ->leftJoin('cctv_maintenance_logs', function ($join) {
                        $join->on('cctv_uptime_daily.device_id', '=', 'cctv_maintenance_logs.device_id')
                             ->where(function ($q) {
                                 $q->whereRaw('cctv_uptime_daily.report_date >= DATE(cctv_maintenance_logs.started_at)')
                                   ->whereRaw('(cctv_maintenance_logs.resolved_at IS NULL OR cctv_uptime_daily.report_date <= DATE(cctv_maintenance_logs.resolved_at))');
                             });
                    })
                    ->whereIn('cctv_uptime_daily.device_id', $deviceIds)
                    ->whereBetween('cctv_uptime_daily.report_date', [$this->from, $this->to])
                    ->where('cctv_uptime_daily.uptime_percent', '<', 100)
                    ->select(
                        'cctv_devices.device_name',
                        'cctv_devices.ip_address',
                        'cctv_uptime_daily.report_date',
                        'cctv_uptime_daily.uptime_percent',
                        'cctv_maintenance_logs.started_at',
                        'cctv_maintenance_logs.resolved_at',
                        'cctv_maintenance_logs.notes as notes_maintenance_log'
                    )
                    ->orderBy('cctv_uptime_daily.report_date', 'desc')
                    ->get()
                    ->map(fn($r) => (array) $r)
                    ->toArray();
            }

            $results[] = [
                'location'    => $site,
                'qty'         => $qty,
                'uptime'      => round((float) $avgUptime, 2),
                'failed_list' => $failedList,
            ];
        }

        return $results;
    }

    private function getBandwidthReport(array $sites): array
    {
        $results = [];
        foreach ($sites as $site) {
            $cleanSite = str_ireplace(['F1 ', 'F2 ', 'F3 '], '', $site);
            $fct = '';
            if (str_starts_with($site, 'F1')) $fct = 'F1';
            elseif (str_starts_with($site, 'F2')) $fct = 'F2';
            elseif (str_starts_with($site, 'F3')) $fct = 'F3';

            $rows = DB::table('bandwidth_daily')
                ->where('location', 'like', "%$cleanSite%")
                ->whereBetween('report_date', [$this->from, $this->to])
                ->select('provider', 'description', 'remark', DB::raw('AVG(value_mbps) as avg_mbps'))
                ->groupBy('provider', 'description', 'remark')
                ->orderBy('provider')
                ->get();

            $contractsQuery = DB::table('isp_sla_contracts')
                ->where('location', 'like', "%$cleanSite%");
            if ($fct) {
                $contractsQuery->where('fct', $fct);
            }
            $contracts = $contractsQuery->get()->keyBy(fn($c) => strtoupper($c->provider));

            $providers = [];
            foreach ($rows as $row) {
                $p = $row->provider;
                $pKey = strtoupper($p);
                if (!isset($providers[$p])) {
                    $limit = isset($contracts[$pKey]) ? (float) $contracts[$pKey]->bandwidth : $this->getDefaultCapacity($cleanSite, $p);
                    $providers[$p] = [
                        'provider'        => $p,
                        'remark'          => $row->remark ?? '-',
                        'avg_download'    => null,
                        'avg_upload'      => null,
                        'bandwidth_limit' => $limit,
                    ];
                }
                if (str_contains(strtolower($row->description ?? ''), 'download')) {
                    $providers[$p]['avg_download'] = round($row->avg_mbps, 2);
                } elseif (str_contains(strtolower($row->description ?? ''), 'upload')) {
                    $providers[$p]['avg_upload'] = round($row->avg_mbps, 2);
                }
            }

            $results[] = [
                'location'  => $site,
                'providers' => array_values($providers),
            ];
        }
        return $results;
    }

    private function getHelpdeskReport(array $sites): array
    {
        $results = [];
        foreach ($sites as $site) {
            $siteKey = str_replace([' Bogor', ' Karawang', ' Tangerang'], '', $site);

            $query = Ticket::where(function ($q) use ($site, $siteKey) {
                $q->where('location', 'like', "%$site%")
                  ->orWhere('location', 'like', "%$siteKey%");
            })->whereBetween('created_at', [$this->from . ' 00:00:00', $this->to . ' 23:59:59']);

            $total  = (clone $query)->count();
            $closed = (clone $query)->whereIn('status', ['closed', 'resolved'])->count();

            $results[] = [
                'location'    => $site,
                'case'        => $total,
                'closed'      => $closed,
                'performance' => $total > 0 ? round(($closed / $total) * 100, 2) : 100.0,
            ];
        }
        return $results;
    }
}
