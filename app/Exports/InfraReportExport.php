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
use PhpOffice\PhpSpreadsheet\Cell\Hyperlink;

class InfraReportExport
{
    private string $from;
    private string $to;

    private const SITES = ['F1 Bogor', 'F2 Karawang', 'F3 Tangerang'];

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

        // TODO: Sheet 2 (Raw data sheets) sementara di-disable untuk debugging format
        // $this->addRawDataSheets($spreadsheet);

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Spreadsheet builder
    // ─────────────────────────────────────────────────────────────────────────

    private function getUptimeColor(float $v): string
    {
        if ($v >= 90) return '1B5E20';
        if ($v >= 80) return 'E65100';
        return 'B71C1C';
    }

    private function getBandwidthColor(?float $usage, float $limit): string
    {
        if (!$usage || $limit <= 0) return '000000';
        $pct = ($usage / $limit) * 100;
        if ($pct >= 90) return 'B71C1C'; // Red (接近full)
        if ($pct >= 75) return 'E65100'; // Yellow/Orange
        return '1B5E20'; // Green
    }

    /**
     * Get sheet name from label_g for hyperlink
     */
    private function getReferenceSheetName(string $labelG): ?string
    {
        return match ($labelG) {
            'Network' => 'Network Uptime',
            'NVR' => 'NVR Uptime',
            'CCTV' => 'CCTV Uptime',
            'Server' => 'Server Uptime',
            'Bandwidth' => 'Bandwidth',
            'Helpdesk' => 'Helpdesk',
            default => null,
        };
    }

    /**
     * Safe merge helper — only merges if start != end (single-cell merges corrupt XLSX).
     */
    private function safeMerge(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, string $range): void
    {
        [$start, $end] = explode(':', $range);
        if ($start !== $end) {
            $ws->mergeCells($range);
        }
    }

    /**
     * Format duration seperti di UI (17h52m, 4d22h18m53s)
     */
    private function formatDuration(?string $startedAt, ?string $resolvedAt): string
    {
        if (!$startedAt) {
            return '-';
        }

        $start = Carbon::parse($startedAt);
        $end   = $resolvedAt ? Carbon::parse($resolvedAt) : now();

        $diffInSeconds = $start->diffInSeconds($end, false);

        if ($diffInSeconds <= 0) {
            return '-';
        }

        $days    = floor($diffInSeconds / 86400);
        $hours   = floor(($diffInSeconds % 86400) / 3600);
        $minutes = floor(($diffInSeconds % 3600) / 60);
        $seconds = $diffInSeconds % 60;

        $result = '';
        if ($days > 0) {
            $result .= $days . 'd';
        }
        if ($hours > 0 || $days > 0) {
            $result .= str_pad($hours, 2, '0', STR_PAD_LEFT) . 'h';
        }
        if ($minutes > 0 || $hours > 0 || $days > 0) {
            $result .= str_pad($minutes, 2, '0', STR_PAD_LEFT) . 'm';
        }
        if ($seconds > 0 || $result === '') {
            $result .= str_pad($seconds, 2, '0', STR_PAD_LEFT) . 's';
        }

        return $result ?: '-';
    }

    private function buildSpreadsheet(): Spreadsheet
    {
        $rawNetwork   = $this->getUptimeReport(self::SITES, 'network');
        $rawNvr       = $this->getUptimeReport(self::SITES, 'nvr');
        $rawCctv      = $this->getUptimeReport(self::SITES, 'cctv');
        $rawServer    = $this->getUptimeReport(self::SITES, 'server');
        $rawBandwidth = $this->getBandwidthReport(self::SITES);
        $rawHelpdesk  = $this->getHelpdeskReport(self::SITES);

        $allFailedLogs = [];
        $uptimeLogExtractor = function (array $row, string $category) use (&$allFailedLogs): array {
            $failed = array_filter($row['failed_list'] ?? [], fn ($f) =>
                isset($f['uptime_percent']) && $f['uptime_percent'] < 100
            );
            $failed = array_slice(array_values($failed), 0, 5);

            foreach ($failed as $f) {
                // Calculate duration from uptime_percent (1% downtime = 10.08 minutes in a week)
                $uptimePercent = (float)($f['uptime_percent'] ?? 100);
                $downtimePercent = 100 - $uptimePercent;
                // Convert to seconds (7 days = 604800 seconds, 1% = 6048 seconds)
                $downtimeSeconds = ($downtimePercent / 100) * 604800;
                
                $days = floor($downtimeSeconds / 86400);
                $hours = floor(($downtimeSeconds % 86400) / 3600);
                $minutes = floor(($downtimeSeconds % 3600) / 60);
                $seconds = $downtimeSeconds % 60;
                
                $duration = '';
                if ($days > 0) $duration .= $days . 'd';
                if ($hours > 0 || $days > 0) $duration .= str_pad($hours, 2, '0', STR_PAD_LEFT) . 'h';
                if ($minutes > 0 || $hours > 0 || $days > 0) $duration .= str_pad($minutes, 2, '0', STR_PAD_LEFT) . 'm';
                if ($seconds > 0 || $duration === '') $duration .= str_pad($seconds, 2, '0', STR_PAD_LEFT) . 's';
                
                $allFailedLogs[] = [
                    'category'    => $category,
                    'location'    => $row['location'],
                    'date'        => $f['report_date'] ?? '',
                    'device_name' => $f['device_name'] ?? '',
                    'ip_address'  => $f['ip_address'] ?? '',
                    'duration'    => $duration ?: '-',
                    'remark'      => $f['notes_maintenance_log'] ?? 'System Check',
                ];
            }

            // Return failed logs for display in Excel
            return $failed;
        };

        $categories = [
            $this->buildUptimeCategory('Infra. Operation Report', 'Network H/W Status Check', '90%', 'Failed Device List', 'Network Operation Check', $rawNetwork, fn($r)=>$uptimeLogExtractor($r, 'Network')),
            $this->buildUptimeCategory('Infra. Operation Report', 'NVR Status', '90%', 'Failed Device List', 'NVR Operation Check', $rawNvr, fn($r)=>$uptimeLogExtractor($r, 'NVR')),
            $this->buildUptimeCategory('Infra. Operation Report', 'CCTV Status Check', '90%', 'Failed Device List', 'CCTV Operation Check', $rawCctv, fn($r)=>$uptimeLogExtractor($r, 'CCTV')),
            $this->buildBandwidthCategory($rawBandwidth),
            $this->buildUptimeCategory('Infra. Operation Report', 'Server Check', '90%', 'Failed Device List', 'Server Operation Check', $rawServer, fn($r)=>$uptimeLogExtractor($r, 'Server')),
            $this->buildHelpdeskCategory($rawHelpdesk),
        ];

        $spreadsheet = new Spreadsheet();
        $ws = $spreadsheet->getActiveSheet();
        $ws->setTitle(Carbon::parse($this->to)->format('d M Y'));

        $ws->getPageSetup()
            ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4)
            ->setFitToPage(true)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $ws->getPageMargins()->setTop(0.4)->setBottom(0.4)->setLeft(0.4)->setRight(0.4);

        $borderAll = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ];
        $alignCenter = [
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ];
        $alignLeftCenter = [
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ];
        $alignRightCenter = [
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_RIGHT,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ];

        // Font
        $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri Light')->setSize(10);
        $ws->getDefaultRowDimension()->setRowHeight(25);

        // Header Rows
        $ws->setCellValue('A2', "ZINUS IDN\nWeekly Infra Report");
        $ws->mergeCells('A2:O2');
        $ws->getRowDimension(2)->setRowHeight(75);
        $ws->getStyle('A2:O2')->applyFromArray(array_merge($borderAll, $alignCenter, [
            'font' => ['bold' => true, 'size' => 14],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']],
            'alignment' => [
                'wrapText' => true,
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ]));

        $period = Carbon::parse($this->from)->format('d M Y') . ' - ' . Carbon::parse($this->to)->format('d M Y');
        $generatedAt = now()->format('d M Y H:i');

        // Row 4 - Date/Period info dengan styling yang konsisten
        $ws->setCellValue('A4', 'Date');
        $ws->setCellValue('B4', $generatedAt);
        $ws->setCellValue('C4', '');
        $ws->setCellValue('D4', 'Period');
        $ws->setCellValue('E4', $period);

        // Merge cells untuk period
        $ws->mergeCells('B4:C4');
        $ws->mergeCells('E4:F4');
        $ws->mergeCells('G4:O4');

        // Apply styles ke row 4
        $ws->getStyle('A4')->applyFromArray(array_merge($borderAll, $alignCenter, [
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'C5E0B4']]
        ]));
        $ws->getStyle('B4:C4')->applyFromArray(array_merge($borderAll, $alignCenter, [
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'C5E0B4']]
        ]));
        $ws->getStyle('D4')->applyFromArray(array_merge($borderAll, $alignCenter, [
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2F0D9']]
        ]));
        $ws->getStyle('E4:F4')->applyFromArray(array_merge($borderAll, $alignCenter, [
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2F0D9']]
        ]));
        $ws->getStyle('G4:O4')->applyFromArray(array_merge($borderAll, $alignCenter, [
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']]
        ]));

        // Row 5 - Column Headers (semua kolom A-O)
        $headers = [
            'A' => 'Activity', 'B' => '##', 'C' => 'Item', 'D' => 'Target',
            'E' => 'Location', 'F' => 'Qty / Detail', 'G' => 'Uptime / Value', 'H' => 'Category Label',
            'I' => 'Location', 'J' => 'Date', 'K' => 'IP Address', 'L' => 'Device Name',
            'M' => 'Duration', 'N' => 'Remark', 'O' => 'Sheet Link'
        ];
        $headerColors = [
            'A' => 'FFF2CC', 'B' => 'FFF2CC', 'C' => 'FFF2CC', 'D' => 'FFF2CC',
            'E' => 'FFF2CC', 'F' => 'FFF2CC', 'G' => 'EDEDED', 'H' => 'FFFFCC',
            'I' => 'FFFFCC', 'J' => 'FFFFCC', 'K' => 'FFFFCC', 'L' => 'FFFFCC',
            'M' => 'FFFFCC', 'N' => 'FFFFCC', 'O' => 'FFFFCC'
        ];

        foreach ($headers as $col => $text) {
            $ws->setCellValue($col . '5', $text);
            $ws->getStyle($col . '5')->applyFromArray(array_merge($borderAll, $alignCenter, [
                'font' => ['bold' => true, 'size' => 10],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $headerColors[$col]]]
            ]));
        }
        // Set header row height lebih tinggi
        $ws->getRowDimension(5)->setRowHeight(40);

        $rowNum = 6;
        $categoryIndex = 1;
        $globalStartRow = $rowNum;

        foreach ($categories as $cat) {
            $catRows = $cat['total_category_rows'];
            $gRowspan = $cat['has_average'] ? $catRows - 1 : $catRows;
            
            $startRowCat = $rowNum;
            $endRowCat = $startRowCat + $catRows - 1;
            
            $isBandwidth = ($cat['type'] ?? '') === 'bandwidth';
            $catCellsPrinted = false;
            $itemNo = '1.' . $categoryIndex++;

            if ($isBandwidth) {
                foreach ($cat['locations'] as $locName => $locData) {
                    $startRowLoc = $rowNum;
                    $endRowLoc = $startRowLoc + $locData['rowspan'] - 1;

                    foreach ($locData['providers'] as $providerIndex => $provider) {
                        if (!$catCellsPrinted) {
                            $ws->setCellValue("B{$startRowCat}", $itemNo);
                            $ws->setCellValue("C{$startRowCat}", $cat['item']);
                            $ws->setCellValue("D{$startRowCat}", $cat['target']);
                            
                            // For Bandwidth, column H is used for D/W and U/L labels per row, so we don't merge it vertically.
                            
                            $ws->setCellValue("O{$startRowCat}", $cat['reference_n']);

                            // Sub-headers for Bandwidth detail
                            $ws->setCellValue("I{$startRowCat}", 'Value');
                            $ws->setCellValue("J{$startRowCat}", 'Failed Device List');
                            $ws->setCellValue("K{$startRowCat}", 'IP Address');
                            $ws->setCellValue("L{$startRowCat}", 'IP Address');
                            $ws->setCellValue("M{$startRowCat}", 'Device Name');
                            $ws->setCellValue("N{$startRowCat}", 'Remark');
                            $ws->getStyle("H{$startRowCat}:N{$startRowCat}")->applyFromArray(array_merge($borderAll, $alignCenter, ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFCC']], 'font' => ['bold' => true]]));

                            $catCellsPrinted = true;
                        }

                        if ($providerIndex === 0) {
                            $ws->setCellValue("E{$startRowLoc}", $locName);
                        }

                        $pStart = $rowNum;
                        $pEnd   = $rowNum + 1;

                        // F: provider name (spans 2 rows)
                        $ws->setCellValue("F{$pStart}", $provider['name']);
                        $this->safeMerge($ws, "F{$pStart}:F{$pEnd}");

                        // G: total bandwidth (spans 2 rows)
                        $ws->setCellValue("G{$pStart}", $provider['bandwidth']);
                        $this->safeMerge($ws, "G{$pStart}:G{$pEnd}");

                        // H: D/W label | I: download value  (row pStart)
                        // H: U/L label | I: upload value    (row pEnd)
                        $ws->setCellValue("H{$pStart}", 'D/W');
                        $ws->setCellValue("I{$pStart}", $provider['dl']);
                        $ws->setCellValue("H{$pEnd}",   'U/L');
                        $ws->setCellValue("I{$pEnd}",   $provider['ul']);

                        if (($provider['limit'] ?? 0) > 0) {
                            $dlColor = $this->getBandwidthColor($provider['dl_raw'], $provider['limit']);
                            $ulColor = $this->getBandwidthColor($provider['ul_raw'], $provider['limit']);
                            $ws->getStyle("I{$pStart}")->getFont()->getColor()->setRGB($dlColor);
                            $ws->getStyle("I{$pEnd}")->getFont()->getColor()->setRGB($ulColor);
                        }

                        // J:N — yellow empty cells, written per row (no cross-row merge)
                        // Skip clearing for the first row of the category to preserve sub-headers
                        foreach (['J','K','L','M','N'] as $c) {
                            if ($pStart != $startRowCat) {
                                $ws->setCellValue("{$c}{$pStart}", '');
                            }
                            $ws->setCellValue("{$c}{$pEnd}",   '');
                        }

                        $ws->getStyle("F{$pStart}:G{$pEnd}")->applyFromArray(array_merge($borderAll, $alignCenter, ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F2F2F2']]]));
                        $ws->getStyle("H{$pStart}:I{$pEnd}")->applyFromArray(array_merge($borderAll, $alignCenter, ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']]]));
                        $ws->getStyle("J{$pStart}:N{$pEnd}")->applyFromArray(array_merge($borderAll, $alignCenter, ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFCC']]]));

                        $rowNum += 2;
                    }

                    $this->safeMerge($ws, "E{$startRowLoc}:E{$endRowLoc}");
                    $ws->getStyle("E{$startRowLoc}:E{$endRowLoc}")->applyFromArray(array_merge($borderAll, $alignCenter, ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F2F2F2']]]));
                }
            } elseif (($cat['type'] ?? '') === 'helpdesk') {
                foreach ($cat['locations'] as $locName => $locData) {
                    $startRowLoc = $rowNum;
                    $endRowLoc = $startRowLoc + $locData['rowspan'] - 1;

                    if (!$catCellsPrinted) {
                        $ws->setCellValue("B{$startRowCat}", $itemNo);
                        $ws->setCellValue("C{$startRowCat}", $cat['item']);
                        $ws->setCellValue("D{$startRowCat}", $cat['target']);
                        
                        $ws->setCellValue("G{$startRowCat}", $cat['label_g'] ?: 'Pending Ticket');
                        $this->safeMerge($ws, "G{$startRowCat}:H{$startRowCat}");
                        
                        $ws->setCellValue("O{$startRowCat}", $cat['reference_n']);

                        // Sub-headers for Helpdesk logs (Align with Row 5)
                        $ws->setCellValue("I{$startRowCat}", 'Pending Ticket');
                        $ws->setCellValue("J{$startRowCat}", 'Location');
                        $ws->setCellValue("K{$startRowCat}", 'Date');
                        $ws->setCellValue("L{$startRowCat}", 'Duration');
                        $ws->setCellValue("M{$startRowCat}", 'Case');
                        $ws->setCellValue("N{$startRowCat}", 'Remark');
                        $ws->getStyle("I{$startRowCat}:N{$startRowCat}")->applyFromArray(array_merge($borderAll, $alignCenter, ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFCC']], 'font' => ['bold' => true]]));

                        // Tambahkan hyperlink ke sheet masing-masing
                        $sheetName = $this->getReferenceSheetName($cat['label_g']);
                        if ($sheetName) {
                            $ws->getCell("O{$startRowCat}")->setHyperlink(new Hyperlink("#{$sheetName}!A1", 'Go to ' . $sheetName));
                            $ws->getStyle("O{$startRowCat}")->getFont()->getColor()->setARGB('FF0000FF');
                            $ws->getStyle("O{$startRowCat}")->getFont()->setUnderline(true);
                        }

                        $catCellsPrinted = true;
                    }

                    // E: location name — spans all rows for this location
                    $ws->setCellValue("E{$startRowLoc}", $locName);
                    $this->safeMerge($ws, "E{$startRowLoc}:E{$endRowLoc}");
                    $ws->getStyle("E{$startRowLoc}:E{$endRowLoc}")->applyFromArray(array_merge($borderAll, $alignCenter, ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F2F2F2']]]));

                    // Row 1 of location: "Case" label + total count
                    $row1 = $startRowLoc;
                    $row2 = $startRowLoc + 1;

                    $ws->setCellValue("F{$row1}", 'Case');
                    $ws->setCellValue("G{$row1}", ($locData['summary']['qty'] ?? 0) . ' Ticket');
                    $this->safeMerge($ws, "G{$row1}:H{$row1}");

                    // Row 2 of location: "Closed" label + closed count
                    $ws->setCellValue("F{$row2}", 'Closed');
                    $ws->setCellValue("G{$row2}", ($locData['summary']['uptime'] ?? 0) . ' Ticket');
                    $this->safeMerge($ws, "G{$row2}:H{$row2}");

                    // If rowspan > 2, extend F and G:H downward from row2
                    if ($endRowLoc > $row2) {
                        $this->safeMerge($ws, "F{$row2}:F{$endRowLoc}");
                        $this->safeMerge($ws, "G{$row2}:H{$endRowLoc}");
                    }

                    $ws->getStyle("F{$row1}:F{$endRowLoc}")->applyFromArray(array_merge($borderAll, $alignCenter, ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F2F2F2']]]));
                    $ws->getStyle("G{$row1}:H{$endRowLoc}")->applyFromArray(array_merge($borderAll, $alignCenter, ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']]]));
                    // All log/aux columns I:N should be yellow for the whole location span
                    $ws->getStyle("I{$row1}:N{$endRowLoc}")->applyFromArray(array_merge($borderAll, $alignCenter, ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFCC']]]));

                    // Log rows always start at row2 (alongside the "Closed" row)
                    $logRow = $row2;

                    foreach ($locData['logs'] as $log) {
                        if ($logRow > $endRowLoc) break; // safety guard
                        if ($log !== null) {
                            $ws->setCellValue("I{$logRow}", 'Pending Ticket');
                            $ws->setCellValue("J{$logRow}", $log['location'] ?? '');
                            $ws->setCellValue("K{$logRow}", $log['date'] ?? '');
                            $ws->setCellValue("L{$logRow}", $log['duration'] ?? '-');
                            $ws->setCellValue("M{$logRow}", ($log['ticket_no'] ? '[' . $log['ticket_no'] . '] ' : '') . ($log['case'] ?? ($log['title'] ?? '')));
                            $ws->setCellValue("N{$logRow}", $log['remark'] ?? ($log['status'] ?? '-'));
                        } else {
                            $ws->setCellValue("I{$logRow}", '');
                            $ws->setCellValue("J{$logRow}", '');
                            $ws->setCellValue("K{$logRow}", '');
                            $ws->setCellValue("L{$logRow}", '');
                            $ws->setCellValue("M{$logRow}", '');
                            $ws->setCellValue("N{$logRow}", '');
                        }
                        $ws->getStyle("I{$logRow}:N{$logRow}")->applyFromArray(array_merge($borderAll, $alignCenter, ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFCC']]]));
                        $logRow++;
                    }

                    // Fill any remaining empty rows within this location's span
                    while ($logRow <= $endRowLoc) {
                        $ws->setCellValue("J{$logRow}", '');
                        $ws->setCellValue("K{$logRow}", '');
                        $ws->setCellValue("L{$logRow}", '');
                        $ws->setCellValue("M{$logRow}", '');
                        $ws->setCellValue("N{$logRow}", '');
                        $ws->getStyle("J{$logRow}:N{$logRow}")->applyFromArray(array_merge($borderAll, $alignCenter, ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFCC']]]));
                        $logRow++;
                    }

                    $rowNum = $endRowLoc + 1;
                }
            } else {
                foreach ($cat['locations'] as $locName => $locData) {
                    $startRowLoc = $rowNum;
                    $endRowLoc = $startRowLoc + $locData['rowspan'] - 1;

                    foreach ($locData['logs'] as $logIndex => $log) {
                        if (!$catCellsPrinted) {
                            $ws->setCellValue("B{$startRowCat}", $itemNo);
                            $ws->setCellValue("C{$startRowCat}", $cat['item']);
                            $ws->setCellValue("D{$startRowCat}", $cat['target']);
                            
                            $ws->setCellValue("H{$startRowCat}", $cat['label_g'] ?: 'Failed Device List');
                            
                            $ws->setCellValue("O{$startRowCat}", $cat['reference_n']);

                            // Sub-headers for Uptime logs (Align with Row 5)
                            $ws->setCellValue("I{$startRowCat}", 'Location');
                            $ws->setCellValue("J{$startRowCat}", 'Date');
                            $ws->setCellValue("K{$startRowCat}", 'IP Address');
                            $ws->setCellValue("L{$startRowCat}", 'Device Name');
                            $ws->setCellValue("M{$startRowCat}", 'Duration');
                            $ws->setCellValue("N{$startRowCat}", 'Remark');
                            
                            $ws->getStyle("I{$startRowCat}:N{$startRowCat}")->applyFromArray(array_merge($borderAll, $alignCenter, ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFCC']], 'font' => ['bold' => true]]));

                            $catCellsPrinted = true;
                        }

                        if ($logIndex === 0) {
                            $ws->setCellValue("E{$startRowLoc}", $locName);
                            $ws->setCellValue("F{$startRowLoc}", $locData['summary']['qty']);
                            
                            $uptimeVal = $locData['summary']['uptime'] !== null ? ($locData['summary']['uptime'] / 100) : '-';
                            if (is_numeric($uptimeVal)) {
                                $ws->setCellValue("G{$startRowLoc}", $uptimeVal);
                                $ws->getStyle("G{$startRowLoc}")->getNumberFormat()->setFormatCode('0.00%');
                            } else {
                                $ws->setCellValue("G{$startRowLoc}", $locData['summary']['uptime_fmt']);
                            }
                        }

                        if ($log !== null) {
                            // Back to 6-column style to align with Row 5
                            $ws->setCellValue("I{$rowNum}", $locName);
                            $ws->setCellValue("J{$rowNum}", $log['report_date'] ?? '');
                            $ws->setCellValue("K{$rowNum}", $log['ip_address'] ?? '');
                            $deviceName = $log['device_name'] ?? '';
                            // Remove trailing parentheses like (10.62.1.11) since IP is already in Col K
                            $deviceName = preg_replace('/\s*\([^)]*\)$/', '', $deviceName);
                            $ws->setCellValue("L{$rowNum}", $deviceName);
                            
                            // Use pre-calculated duration from getUptimeReport
                            $ws->setCellValue("M{$rowNum}", $log['duration'] ?? '-');
                            $ws->setCellValue("N{$rowNum}", $log['notes_maintenance_log'] ?? ($log['remark'] ?? '-'));
                        } else {
                            $ws->setCellValue("I{$rowNum}", '');
                            $ws->setCellValue("J{$rowNum}", '');
                            $ws->setCellValue("K{$rowNum}", '');
                            $ws->setCellValue("L{$rowNum}", '');
                            $ws->setCellValue("M{$rowNum}", '');
                            $ws->setCellValue("N{$rowNum}", '');
                        }

                        $ws->getStyle("I{$rowNum}:N{$rowNum}")->applyFromArray(array_merge($borderAll, $alignCenter, ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFCC']]]));

                        $rowNum++;
                    }

                    if ($locData['rowspan'] > 1) {
                        $this->safeMerge($ws, "E{$startRowLoc}:E{$endRowLoc}");
                        $this->safeMerge($ws, "F{$startRowLoc}:F{$endRowLoc}");
                        $this->safeMerge($ws, "G{$startRowLoc}:G{$endRowLoc}");
                    }
                    $ws->getStyle("E{$startRowLoc}:F{$endRowLoc}")->applyFromArray(array_merge($borderAll, $alignCenter, ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F2F2F2']]]));
                    $ws->getStyle("G{$startRowLoc}:G{$endRowLoc}")->applyFromArray(array_merge($borderAll, $alignCenter, ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']]]));
                    
                    if ($locData['summary']['uptime'] !== null) {
                        $color = $this->getUptimeColor((float)$locData['summary']['uptime']);
                        $ws->getStyle("G{$startRowLoc}")->getFont()->getColor()->setRGB($color);
                    }
                }
            }

            if ($cat['has_average']) {
                $ws->setCellValue("E{$rowNum}", 'Average');
                $this->safeMerge($ws, "E{$rowNum}:F{$rowNum}");

                $avgVal = $cat['average_raw'] !== null ? ($cat['average_raw'] / 100) : '-';
                if (is_numeric($avgVal)) {
                    $ws->setCellValue("G{$rowNum}", $avgVal);
                    $ws->getStyle("G{$rowNum}")->getNumberFormat()->setFormatCode('0.00%');
                } else {
                    $ws->setCellValue("G{$rowNum}", $cat['average']);
                }

                if ($cat['average_raw'] !== null) {
                    $color = $this->getUptimeColor((float)$cat['average_raw']);
                    if (!$isBandwidth) {
                        $ws->getStyle("G{$rowNum}")->getFont()->getColor()->setRGB($color);
                    } else {
                        $ws->setCellValue("G{$rowNum}", '');
                        $ws->setCellValue("H{$rowNum}", '');
                        $ws->setCellValue("I{$rowNum}", $avgVal);
                        $ws->getStyle("I{$rowNum}")->getNumberFormat()->setFormatCode('0.00%');
                        $ws->getStyle("I{$rowNum}")->getFont()->getColor()->setRGB($color);
                    }
                }

                $ws->setCellValue("H{$rowNum}", 'Target > 90%');
                $this->safeMerge($ws, "H{$rowNum}:N{$rowNum}");

                $ws->getStyle("E{$rowNum}:N{$rowNum}")->applyFromArray(array_merge($borderAll, $alignCenter, [
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF2CC']],
                    'font' => ['bold' => true]
                ]));
                // Set "Target > 90%" alignment and color
                $ws->getStyle("H{$rowNum}")->applyFromArray([
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_RIGHT,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'font' => [
                        'color' => ['rgb' => 'A6A6A6'],
                        'bold' => true
                    ]
                ]);
                $ws->getStyle("E{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $ws->getStyle("G{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $ws->getStyle("I{$rowNum}:N{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                $rowNum++;
            }

            if ($catRows > 1) {
                $this->safeMerge($ws, "B{$startRowCat}:B{$endRowCat}");
                $this->safeMerge($ws, "C{$startRowCat}:C{$endRowCat}");
                $this->safeMerge($ws, "D{$startRowCat}:D{$endRowCat}");
                $this->safeMerge($ws, "O{$startRowCat}:O{$endRowCat}");
            }
            $ws->getStyle("B{$startRowCat}:D{$endRowCat}")->applyFromArray(array_merge($borderAll, $alignCenter, ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']]]));
            $ws->getStyle("O{$startRowCat}:O{$endRowCat}")->applyFromArray(array_merge($borderAll, $alignCenter, ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']]]));

            $endRowH = $startRowCat + $gRowspan - 1;
            // Bandwidth uses col H for D/W / U/L labels — do NOT merge H for bandwidth
            if ($isBandwidth) {
                if ($gRowspan > 1) {
                    $this->safeMerge($ws, "J{$startRowCat}:K{$endRowH}");
                    $ws->getStyle("J{$startRowCat}:K{$endRowH}")->applyFromArray(array_merge($borderAll, $alignCenter, ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFCC']], 'font' => ['bold' => true]]));
                } else {
                    $this->safeMerge($ws, "J{$startRowCat}:K{$startRowCat}");
                    $ws->getStyle("J{$startRowCat}:K{$startRowCat}")->applyFromArray(array_merge($borderAll, $alignCenter, ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFCC']], 'font' => ['bold' => true]]));
                }
            } else {
                if ($gRowspan > 1) {
                    if (($cat['type'] ?? '') === 'helpdesk') {
                        $this->safeMerge($ws, "I{$startRowCat}:I{$endRowH}");
                        $ws->getStyle("I{$startRowCat}:I{$endRowH}")->applyFromArray(array_merge($borderAll, $alignCenter, ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFCC']], 'font' => ['bold' => true]]));
                    } else {
                        $this->safeMerge($ws, "H{$startRowCat}:H{$endRowH}");
                        $ws->getStyle("H{$startRowCat}:H{$endRowH}")->applyFromArray(array_merge($borderAll, $alignCenter, ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFCC']], 'font' => ['bold' => true]]));
                    }
                } else {
                    if (($cat['type'] ?? '') === 'helpdesk') {
                        $ws->getStyle("I{$startRowCat}")->applyFromArray(array_merge($borderAll, $alignCenter, ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFCC']], 'font' => ['bold' => true]]));
                    } else {
                        $ws->getStyle("H{$startRowCat}")->applyFromArray(array_merge($borderAll, $alignCenter, ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFCC']], 'font' => ['bold' => true]]));
                    }
                }
            }
        }

        // Merge Activity (Col A) for all categories
        $globalEndRow = $rowNum - 1;
        $ws->setCellValue("A{$globalStartRow}", "Infra. Operation Report");
        $this->safeMerge($ws, "A{$globalStartRow}:A{$globalEndRow}");
        $ws->getStyle("A{$globalStartRow}:A{$globalEndRow}")->applyFromArray(array_merge($borderAll, $alignCenter, ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']]]));

        $colWidths = [
            'A' => 15, 'B' => 5, 'C' => 20, 'D' => 15, 'E' => 15,
            'F' => 12, 'G' => 15, 'H' => 12, 'I' => 20, 'J' => 15,
            'K' => 18, 'L' => 30, 'M' => 25, 'N' => 60, 'O' => 20
        ];
        foreach ($colWidths as $col => $w) {
            $ws->getColumnDimension($col)->setWidth($w);
        }

        // Set explicit row heights at the end to prevent overrides
        for ($r = 1; $r <= $rowNum; $r++) {
            if ($r === 2 || $r === 5) continue;
            $ws->getRowDimension($r)->setRowHeight(40);
        }

        return $spreadsheet;
    }

    /**
     * Builds the nested structure for uptime-based categories.
     *
     * KEY GUARANTEE: every location always has at least [null] in 'logs'
     * so the grid never collapses. rowspan is always >= 1.
     *
     * total_category_rows = sum(location rowspans) + 1  (the +1 is the avg row)
     */
    private function buildUptimeCategory(
        string   $activity,
        string   $item,
        string   $target,
        string   $labelG,
        string   $referenceN,
        array    $siteRows,
        callable $logExtractor,
    ): array {
        $locations = [];
        $uptimeSum = 0.0;
        $siteCount = 0;

        foreach ($siteRows as $row) {
            $logs = $logExtractor($row);

            // CRITICAL: guarantee at least one row so the grid never collapses
            if (empty($logs)) {
                $logs = [null];
            }

            $rowspan = count($logs); // always >= 1

            $locations[$row['location']] = [
                'summary' => [
                    'qty'        => $row['qty'],
                    'uptime'     => (float) $row['uptime'],
                    'uptime_fmt' => number_format((float) $row['uptime'], 2) . '%',
                ],
                'rowspan' => $rowspan,
                'logs'    => $logs,
            ];

            $uptimeSum += (float) $row['uptime'];
            $siteCount++;
        }

        // +1 for the average row
        $totalRows = array_sum(array_column($locations, 'rowspan')) + 1;
        $avgUptime = $siteCount > 0 ? round($uptimeSum / $siteCount, 2) : 0.0;

        return [
            'activity'            => $activity,
            'item'                => $item,
            'target'              => $target,
            'label_g'             => $labelG,
            'reference_n'         => $referenceN,
            'has_average'         => true,
            'average'             => number_format($avgUptime, 2) . '%',
            'average_raw'         => $avgUptime,
            'total_category_rows' => $totalRows,
            'locations'           => $locations,
        ];
    }

    /**
     * Builds the bandwidth/internet category.
     *
     * Each location gets a 'providers' sub-array (split by provider).
     * rowspan = number of providers for that location (min 1).
     * Col E = qty of providers, Col F = bandwidth check label.
     * The blade renders this with a dedicated provider loop.
     *
     * No average row. Bandwidth has no uptime metric.
     */
    private function buildBandwidthCategory(array $rawBandwidth): array
    {
        $locations = [];

        foreach ($rawBandwidth as $b) {
            $rawProviders = $b['providers'];
            $providers    = [];

            $providerCapacityMap = [
                'Bogor'     => ['180 Mbps', '100 Mbps'],
                'Karawang'  => ['180 Mbps', '80 Mbps'],
                'Tangerang' => ['240 Mbps', '100 Mbps'],
            ];
            $currentSite = $b['location'];
            $siteKey = str_replace(' Bogor', '', $currentSite);
            $siteKey = str_replace(' Karawang', '', $siteKey);
            $siteKey = str_replace(' Tangerang', '', $siteKey);
            
            foreach ($rawProviders as $idx => $p) {
                $limit = (float)($p['bandwidth_limit'] ?? 0);
                $targetCapacity = ($limit > 0 ? $limit : 100) . ' Mbps';
                $providers[] = [
                    'name'      => $p['provider'],
                    'target'    => $targetCapacity,
                    'limit'     => $limit,
                    'dl_raw'    => $p['avg_download'],
                    'ul_raw'    => $p['avg_upload'],
                    'bandwidth' => ($p['avg_download'] !== null
                        ? number_format($p['avg_download'] + ($p['avg_upload'] ?? 0), 0)
                        : '-') . ' Mbps',
                    'dl'        => $p['avg_download'] !== null
                        ? number_format($p['avg_download'], 2) . ' Mbps'
                        : 'N/A',
                    'ul'        => $p['avg_upload'] !== null
                        ? number_format($p['avg_upload'], 2) . ' Mbps'
                        : 'N/A',
                ];
            }

            // Guarantee at least one row (empty provider placeholder)
            if (empty($providers)) {
                $providers = [[
                    'name'      => '-',
                    'target'    => '100 Mbps',
                    'bandwidth' => '-',
                    'dl'        => 'N/A',
                    'ul'        => 'N/A',
                ]];
            }

            $rowspan = count($providers) * 2;

            $locations[$b['location']] = [
                'item'    => '90%', // Using Target column for bandwidth max
                'summary' => [
                    'qty'        => count($providers),
                    'uptime'     => null,
                    'uptime_fmt' => 'Bandwidth Check',
                ],
                'rowspan'   => $rowspan,
                'providers' => $providers,
                // logs kept empty — the blade uses 'providers' for this type
                'logs'      => [],
            ];
        }

        $totalRows = array_sum(array_column($locations, 'rowspan'));

        return [
            'activity'            => 'Infra. Operation Report',
            'item'                => 'Internet Usage',
            'target'              => 'Bandwidth Usage Check',
            'label_g'             => 'Failed Device List', // Matching the bizarre copy paste in Excel
            'reference_n'         => 'Inet Operation Check',
            'has_average'         => true,
            'average'             => '100.0%',
            'average_raw'         => 100.0,
            'total_category_rows' => $totalRows + 1, // +1 for the average row
            'locations'           => $locations,
            'type'                => 'bandwidth',
        ];
    }

    /**
     * Builds the helpdesk category.
     *
     * Each pending ticket becomes one log row.
     * Col E = total case count, Col F = performance %.
     */
    private function buildHelpdeskCategory(array $rawHelpdesk): array
    {
        $locations = [];
        $perfSum   = 0.0;
        $siteCount = 0;

        foreach ($rawHelpdesk as $h) {
            $tickets = [];

            foreach ($h['pending_list'] as $t) {
                $tickets[] = [
                    'location'    => $h['location'],
                    'date'        => $t['created_at'] ?? '',
                    'device_name' => $t['title'] ?? '',
                    'ip_address'  => '#' . ($t['ticket_no'] ?? ''),
                    'downtime'    => '-',
                    'remark'      => strtoupper($t['status'] ?? ''),
                ];
            }

            // Each location needs at least 2 rows: one for "Case" label, one for "Closed" label.
            // Ticket log rows are written alongside the Closed row and beyond.
            // rowspan = max(2, count(tickets) + 1) so there's always room for both labels.
            $rowspan = max(2, count($tickets) + 1);

            // Guarantee at least one null log entry so the grid never collapses
            if (empty($tickets)) {
                $tickets = [null];
            }

            $locations[$h['location']] = [
                'summary' => [
                    'qty'        => $h['case'],    // Total cases
                    'uptime'     => $h['closed'],  // Closed cases (raw number)
                    'uptime_fmt' => number_format((float) $h['performance'], 2) . '%',
                ],
                'rowspan' => $rowspan,
                'logs'    => $tickets,
            ];

            $perfSum   += (float) $h['performance'];
            $siteCount++;
        }

        $totalRows = array_sum(array_column($locations, 'rowspan')) + 1; // +1 for average row
        $avgPerf   = $siteCount > 0 ? round($perfSum / $siteCount, 2) : 0.0;

        return [
            'activity'            => 'Infra. Operation Report',
            'item'                => 'End User PC Issue handling',
            'target'              => 'Helpdesk Daily',
            'label_g'             => 'Pending Ticket',
            'reference_n'         => 'Helpdesk Operation',
            'has_average'         => true,
            'average'             => number_format($avgPerf, 2) . '%',
            'average_raw'         => $avgPerf,
            'total_category_rows' => $totalRows,
            'locations'           => $locations,
            'type'                => 'helpdesk',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Raw data fetchers
    // ─────────────────────────────────────────────────────────────────────────

    private function getUptimeReport(array $sites, string $type): array
    {
        $results = [];
        foreach ($sites as $site) {
            if ($type === 'network') {
                $devices = NetworkDevice::where('site', $site)->where('is_active', true)->get();
            } elseif ($type === 'server') {
                $devices = ServerDevice::where('site', $site)->where('is_active', true)->get();
            } else {
                $devices = CctvDevice::where('site', $site)
                    ->where('device_type', strtoupper($type))
                    ->where('is_active', true)->get();
            }

            $deviceIds = $devices->pluck('id');
            $qty       = $deviceIds->count();

            if ($qty === 0) {
                $results[] = ['location' => $site, 'qty' => 0, 'uptime' => 100.0, 'failed_list' => []];
                continue;
            }

            if ($type === 'server') {
                $sourceIds  = $devices->pluck('source_id');
                $rows       = DB::table('server_resource_daily')
                    ->whereIn('host_id', $sourceIds)
                    ->whereBetween('report_date', [$this->from, $this->to])->get();
                $totalDays  = (Carbon::parse($this->from)->diffInDays(Carbon::parse($this->to)) + 1);
                $totalSlots = $qty * $totalDays;
                
                // Group by host_id dan count berapa hari setiap host lapor
                $hostDays = $rows->groupBy('host_id')->map(fn($group) => $group->pluck('report_date')->unique()->count());
                $totalReportedDays = $hostDays->sum();
                
                $avgUptime = $totalSlots > 0 ? round(($totalReportedDays / $totalSlots) * 100, 2) : 100.0;
                $presentIds = $rows->pluck('host_id')->unique();
                $failedList = $devices->filter(fn ($d) => !$presentIds->contains($d->source_id))
                    ->map(fn ($d) => [
                        'device_name'    => $d->device_name,
                        'ip_address'     => $d->ip_address,
                        'report_date'    => $this->from . ' ~ ' . $this->to,
                        'uptime_percent' => 0,
                    ])->values()->toArray();

            } elseif ($type === 'network') {
                $avgUptime  = DB::table('network_uptime_daily')
                    ->whereIn('device_id', $deviceIds)
                    ->whereBetween('report_date', [$this->from, $this->to])
                    ->avg('uptime_percent') ?? 100.0;

                $failedList = DB::table('network_uptime_daily')
                    ->join('network_devices', 'network_uptime_daily.device_id', '=', 'network_devices.id')
                    ->leftJoin('network_maintenance_logs', function($join) {
                        $join->on('network_uptime_daily.device_id', '=', 'network_maintenance_logs.device_id')
                             ->on('network_uptime_daily.report_date', '=', DB::raw('DATE(network_maintenance_logs.started_at)'));
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
                    ->orderBy('network_uptime_daily.report_date')
                    ->get()
                    ->map(function ($r) {
                        $arr = (array) $r;
                        $start = $r->started_at ? Carbon::parse($r->started_at) : Carbon::parse($r->report_date);
                        $end   = $r->resolved_at ? Carbon::parse($r->resolved_at) : now();
                        
                        if ($end) {
                            $diff = $start->diff($end);
                            $parts = [];
                            if ($diff->d > 0) $parts[] = "{$diff->d}d";
                            if ($diff->h > 0) $parts[] = "{$diff->h}h";
                            if ($diff->i > 0) $parts[] = "{$diff->i}m";
                            if ($diff->s > 0) $parts[] = "{$diff->s}s";
                            $arr['duration'] = empty($parts) ? '0s' : implode('', $parts);
                        } else {
                            $arr['duration'] = '-';
                        }
                        return $arr;
                    })
                    ->toArray();

            } else {
                // cctv / nvr
                $avgUptime  = DB::table('cctv_uptime_daily')
                    ->whereIn('device_id', $deviceIds)
                    ->whereBetween('report_date', [$this->from, $this->to])
                    ->avg('uptime_percent') ?? 100.0;

                $failedList = DB::table('cctv_uptime_daily')
                    ->join('cctv_devices', 'cctv_uptime_daily.device_id', '=', 'cctv_devices.id')
                    ->leftJoin('cctv_maintenance_logs', function($join) {
                        $join->on('cctv_uptime_daily.device_id', '=', 'cctv_maintenance_logs.device_id')
                             ->on('cctv_uptime_daily.report_date', '=', DB::raw('DATE(cctv_maintenance_logs.started_at)'));
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
                    ->orderBy('cctv_uptime_daily.report_date')
                    ->get()
                    ->map(function ($r) {
                        $arr = (array) $r;
                        $start = $r->started_at ? Carbon::parse($r->started_at) : Carbon::parse($r->report_date);
                        $end   = $r->resolved_at ? Carbon::parse($r->resolved_at) : now();
                        
                        if ($end) {
                            $diff = $start->diff($end);
                            $parts = [];
                            if ($diff->d > 0) $parts[] = "{$diff->d}d";
                            if ($diff->h > 0) $parts[] = "{$diff->h}h";
                            if ($diff->i > 0) $parts[] = "{$diff->i}m";
                            if ($diff->s > 0) $parts[] = "{$diff->s}s";
                            $arr['duration'] = empty($parts) ? '0s' : implode('', $parts);
                        } else {
                            $arr['duration'] = '-';
                        }
                        return $arr;
                    })
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
            $rows = DB::table('bandwidth_daily')
                ->where('location', $site)
                ->whereBetween('report_date', [$this->from, $this->to])
                ->select('provider', 'description', DB::raw('AVG(value_mbps) as avg_mbps'))
                ->groupBy('provider', 'description')
                ->orderBy('provider')
                ->get();

            $providers = [];
            foreach ($rows as $row) {
                $p = $row->provider;
                if (!isset($providers[$p])) {
                    $providers[$p] = ['provider' => $p, 'avg_download' => null, 'avg_upload' => null];
                }
                if (str_contains(strtolower($row->description ?? ''), 'download')) {
                    $providers[$p]['avg_download'] = round($row->avg_mbps, 2);
                } elseif (str_contains(strtolower($row->description ?? ''), 'upload')) {
                    $providers[$p]['avg_upload'] = round($row->avg_mbps, 2);
                }
            }
            $results[] = ['location' => $site, 'providers' => array_values($providers)];
        }
        return $results;
    }

    private function getHelpdeskReport(array $sites): array
    {
        $results = [];
        foreach ($sites as $site) {
            $total  = Ticket::where('location', 'like', "%$site%")
                ->whereBetween('created_at', [$this->from . ' 00:00:00', $this->to . ' 23:59:59'])
                ->count();
            $closed = Ticket::where('location', 'like', "%$site%")
                ->whereBetween('created_at', [$this->from . ' 00:00:00', $this->to . ' 23:59:59'])
                ->whereIn('status', ['closed', 'resolved'])->count();
            $pending = Ticket::where('location', 'like', "%$site%")
                ->whereIn('status', ['open', 'on-progress', 'pending'])
                ->select('id as ticket_no', 'issue_description', 'created_at', 'status', 'date_closed', 'action_taken')
                ->orderByDesc('created_at')->limit(20)->get()
                ->map(function ($t) {
                    $start = Carbon::parse($t->created_at);
                    $end   = ($t->status === 'closed' || $t->status === 'resolved' || $t->date_closed) 
                             ? Carbon::parse($t->date_closed ?? $t->updated_at) 
                             : now();
                    
                    $diff = $start->diff($end);
                    $parts = [];
                    if ($diff->d > 0) $parts[] = "{$diff->d}d";
                    if ($diff->h > 0) $parts[] = "{$diff->h}h";
                    if ($diff->i > 0) $parts[] = "{$diff->i}m";
                    if ($diff->s > 0) $parts[] = "{$diff->s}s";
                    $duration = empty($parts) ? '0s' : implode('', $parts);

                    return [
                        'ticket_no'  => $t->ticket_no,
                        'title'      => $t->issue_description,
                        'status'     => $t->status,
                        'duration'   => $duration,
                        'remark'     => $t->action_taken ?? '-',
                        'date'       => $t->created_at?->toDateString(),
                    ];
                });
            $results[] = [
                'location'     => $site,
                'case'         => $total,
                'closed'       => $closed,
                'performance'  => $total > 0 ? round(($closed / $total) * 100, 2) : 100.0,
                'pending_list' => $pending,
            ];
        }
        return $results;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Extra sheets: raw data per category
    // ─────────────────────────────────────────────────────────────────────────

    private function addRawDataSheets(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet): void
    {
        $borderAll = [
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        ];
        $hdrStyle = [
            'font'      => ['bold' => true],
            'fill'      => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2EFDA']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ];
        $dataStyle = [
            'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ];

        // ── 1. Network Uptime ─────────────────────────────────────────────
        $ws = $spreadsheet->createSheet();
        $ws->setTitle('Network Uptime');
        $ws->fromArray(['Location', 'Qty', 'Uptime %', 'Device Name', 'IP Address', 'Date', 'Downtime %', 'Remark'], null, 'A1');
        $ws->getStyle('A1:H1')->applyFromArray(array_merge($borderAll, $hdrStyle));
        $row = 2;
        foreach ($this->getUptimeReport(self::SITES, 'network') as $site) {
            if (empty($site['failed_list'])) {
                $ws->fromArray([$site['location'], $site['qty'], $site['uptime'], '-', '-', '-', '-', 'No Issues'], null, "A{$row}");
                $ws->getStyle("A{$row}:H{$row}")->applyFromArray(array_merge($borderAll, $dataStyle));
                $row++;
            } else {
                foreach ($site['failed_list'] as $f) {
                    $ws->fromArray([$site['location'], $site['qty'], $site['uptime'], $f['device_name'], $f['ip_address'], $f['report_date'], number_format(100 - $f['uptime_percent'], 2) . '%', 'System Check'], null, "A{$row}");
                    $ws->getStyle("A{$row}:H{$row}")->applyFromArray(array_merge($borderAll, $dataStyle));
                    $row++;
                }
            }
        }
        foreach (range('A', 'H') as $col) { $ws->getColumnDimension($col)->setAutoSize(true); }

        // ── 2. NVR Uptime ─────────────────────────────────────────────────
        $ws = $spreadsheet->createSheet();
        $ws->setTitle('NVR Uptime');
        $ws->fromArray(['Location', 'Qty', 'Uptime %', 'Device Name', 'IP Address', 'Date', 'Downtime %', 'Remark'], null, 'A1');
        $ws->getStyle('A1:H1')->applyFromArray(array_merge($borderAll, $hdrStyle));
        $row = 2;
        foreach ($this->getUptimeReport(self::SITES, 'nvr') as $site) {
            if (empty($site['failed_list'])) {
                $ws->fromArray([$site['location'], $site['qty'], $site['uptime'], '-', '-', '-', '-', 'No Issues'], null, "A{$row}");
                $ws->getStyle("A{$row}:H{$row}")->applyFromArray(array_merge($borderAll, $dataStyle));
                $row++;
            } else {
                foreach ($site['failed_list'] as $f) {
                    $ws->fromArray([$site['location'], $site['qty'], $site['uptime'], $f['device_name'], $f['ip_address'], $f['report_date'], number_format(100 - $f['uptime_percent'], 2) . '%', 'System Check'], null, "A{$row}");
                    $ws->getStyle("A{$row}:H{$row}")->applyFromArray(array_merge($borderAll, $dataStyle));
                    $row++;
                }
            }
        }
        foreach (range('A', 'H') as $col) { $ws->getColumnDimension($col)->setAutoSize(true); }

        // ── 3. CCTV Uptime ────────────────────────────────────────────────
        $ws = $spreadsheet->createSheet();
        $ws->setTitle('CCTV Uptime');
        $ws->fromArray(['Location', 'Qty', 'Uptime %', 'Device Name', 'IP Address', 'Date', 'Downtime %', 'Remark'], null, 'A1');
        $ws->getStyle('A1:H1')->applyFromArray(array_merge($borderAll, $hdrStyle));
        $row = 2;
        foreach ($this->getUptimeReport(self::SITES, 'cctv') as $site) {
            if (empty($site['failed_list'])) {
                $ws->fromArray([$site['location'], $site['qty'], $site['uptime'], '-', '-', '-', '-', 'No Issues'], null, "A{$row}");
                $ws->getStyle("A{$row}:H{$row}")->applyFromArray(array_merge($borderAll, $dataStyle));
                $row++;
            } else {
                foreach ($site['failed_list'] as $f) {
                    $ws->fromArray([$site['location'], $site['qty'], $site['uptime'], $f['device_name'], $f['ip_address'], $f['report_date'], number_format(100 - $f['uptime_percent'], 2) . '%', 'System Check'], null, "A{$row}");
                    $ws->getStyle("A{$row}:H{$row}")->applyFromArray(array_merge($borderAll, $dataStyle));
                    $row++;
                }
            }
        }
        foreach (range('A', 'H') as $col) { $ws->getColumnDimension($col)->setAutoSize(true); }

        // ── 4. Server Uptime ──────────────────────────────────────────────
        $ws = $spreadsheet->createSheet();
        $ws->setTitle('Server Uptime');
        $ws->fromArray(['Location', 'Qty', 'Uptime %', 'Device Name', 'IP Address', 'Date', 'Downtime %', 'Remark'], null, 'A1');
        $ws->getStyle('A1:H1')->applyFromArray(array_merge($borderAll, $hdrStyle));
        $row = 2;
        foreach ($this->getUptimeReport(self::SITES, 'server') as $site) {
            if (empty($site['failed_list'])) {
                $ws->fromArray([$site['location'], $site['qty'], $site['uptime'], '-', '-', '-', '-', 'No Issues'], null, "A{$row}");
                $ws->getStyle("A{$row}:H{$row}")->applyFromArray(array_merge($borderAll, $dataStyle));
                $row++;
            } else {
                foreach ($site['failed_list'] as $f) {
                    $ws->fromArray([$site['location'], $site['qty'], $site['uptime'], $f['device_name'], $f['ip_address'], $f['report_date'], number_format(100 - $f['uptime_percent'], 2) . '%', 'System Check'], null, "A{$row}");
                    $ws->getStyle("A{$row}:H{$row}")->applyFromArray(array_merge($borderAll, $dataStyle));
                    $row++;
                }
            }
        }
        foreach (range('A', 'H') as $col) { $ws->getColumnDimension($col)->setAutoSize(true); }

        // ── 5. Bandwidth ──────────────────────────────────────────────────
        $ws = $spreadsheet->createSheet();
        $ws->setTitle('Bandwidth');
        $ws->fromArray(['Location', 'Provider', 'Avg Download (Mbps)', 'Avg Upload (Mbps)'], null, 'A1');
        $ws->getStyle('A1:D1')->applyFromArray(array_merge($borderAll, $hdrStyle));
        $row = 2;
        foreach ($this->getBandwidthReport(self::SITES) as $b) {
            foreach ($b['providers'] as $p) {
                $ws->fromArray([$b['location'], $p['provider'], $p['avg_download'] ?? 'N/A', $p['avg_upload'] ?? 'N/A'], null, "A{$row}");
                $ws->getStyle("A{$row}:D{$row}")->applyFromArray(array_merge($borderAll, $dataStyle));
                $row++;
            }
            if (empty($b['providers'])) {
                $ws->fromArray([$b['location'], 'No Data', '-', '-'], null, "A{$row}");
                $ws->getStyle("A{$row}:D{$row}")->applyFromArray(array_merge($borderAll, $dataStyle));
                $row++;
            }
        }
        foreach (range('A', 'D') as $col) { $ws->getColumnDimension($col)->setAutoSize(true); }

        // ── 6. Helpdesk ───────────────────────────────────────────────────
        $ws = $spreadsheet->createSheet();
        $ws->setTitle('Helpdesk');
        $ws->fromArray(['Location', 'Total Case', 'Closed', 'Open', 'Resolution %', 'Ticket#', 'Title', 'Status', 'Created At'], null, 'A1');
        $ws->getStyle('A1:I1')->applyFromArray(array_merge($borderAll, $hdrStyle));
        $row = 2;
        foreach ($this->getHelpdeskReport(self::SITES) as $h) {
            $open = $h['case'] - $h['closed'];
            $totalCases = $h['case'] ?? 0 ;
            $closedCases = $h['closed'] ?? 0;
            $openCases = $open > 0 ? $open : 0;
            $resolution = $h['performance'] ?? 0;
            
            if (empty($h['pending_list']) || $h['pending_list']->isEmpty()) {
                $ws->fromArray([$h['location'], $totalCases, $closedCases, $openCases, $resolution . '%', '-', '-', '-', '-'], null, "A{$row}");
                $ws->getStyle("A{$row}:I{$row}")->applyFromArray(array_merge($borderAll, $dataStyle));
                $row++;
            } else {
                $first = true;
                foreach ($h['pending_list'] as $t) {
                    $ws->fromArray([
                        $first ? $h['location'] : '',
                        $first ? $totalCases : '',
                        $first ? $closedCases : '',
                        $first ? $openCases : '',
                        $first ? $h['performance'] . '%' : '',
                        '#' . $t['ticket_no'],
                        $t['title'],
                        strtoupper($t['status']),
                        $t['created_at'],
                    ], null, "A{$row}");
                    $ws->getStyle("A{$row}:I{$row}")->applyFromArray(array_merge($borderAll, $dataStyle));
                    $first = false;
                    $row++;
                }
            }
        }
        foreach (range('A', 'I') as $col) { $ws->getColumnDimension($col)->setAutoSize(true); }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Extra sheet: Maintenance Log (last sheet)
    // Queries network_maintenance_logs, cctv_maintenance_logs, server_maintenance_logs
    // for records where started_at falls within the report period.
    // ─────────────────────────────────────────────────────────────────────────

    private function addMaintenanceLogSheet(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet): void
    {
        $ws = $spreadsheet->createSheet();
        $ws->setTitle('Maintenance Log');

        $borderAll = [
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        ];
        $hdrStyle = [
            'font'      => ['bold' => true],
            'fill'      => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FCE4D6']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ];
        $dataStyle = [
            'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ];

        // Title
        $ws->setCellValue('A1', 'Maintenance Log — ' . Carbon::parse($this->from)->format('d M Y') . ' to ' . Carbon::parse($this->to)->format('d M Y'));
        $ws->mergeCells('A1:J1');
        $ws->getStyle('A1:J1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 12],
            'fill'      => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FCE4D6']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        ]);

        // Headers
        $headers = ['Category', 'Location', 'Device Name', 'IP Address', 'Status', 'Event Type', 'Started At', 'Resolved At', 'Duration', 'Notes'];
        $ws->fromArray($headers, null, 'A2');
        $ws->getStyle('A2:J2')->applyFromArray(array_merge($borderAll, $hdrStyle));

        $row = 3;

        // ── Network maintenance logs ──────────────────────────────────────
        $networkLogs = DB::table('network_maintenance_logs')
            ->join('network_devices', 'network_maintenance_logs.device_id', '=', 'network_devices.id')
            ->whereBetween('network_maintenance_logs.started_at', [$this->from, $this->to])
            ->select(
                'network_devices.device_name',
                'network_devices.ip_address',
                'network_devices.site',
                'network_maintenance_logs.status',
                'network_maintenance_logs.event_type',
                'network_maintenance_logs.started_at',
                'network_maintenance_logs.resolved_at',
                'network_maintenance_logs.notes'
            )
            ->orderBy('network_maintenance_logs.started_at')
            ->get();

        foreach ($networkLogs as $log) {
            $duration = $this->formatDuration($log->started_at, $log->resolved_at);
            $ws->fromArray([
                'Network',
                $log->site ?? '',
                $log->device_name ?? '',
                $log->ip_address ?? '',
                strtoupper($log->status ?? ''),
                $log->event_type ?? '',
                $log->started_at ?? '',
                $log->resolved_at ?? '-',
                $duration,
                $log->notes ?? '',
            ], null, "A{$row}");
            $ws->getStyle("A{$row}:J{$row}")->applyFromArray(array_merge($borderAll, $dataStyle));
            $row++;
        }

        // ── CCTV maintenance logs (covers NVR + CCTV) ────────────────────
        $cctvLogs = DB::table('cctv_maintenance_logs')
            ->join('cctv_devices', 'cctv_maintenance_logs.device_id', '=', 'cctv_devices.id')
            ->whereBetween('cctv_maintenance_logs.started_at', [$this->from, $this->to])
            ->select(
                'cctv_devices.device_name',
                'cctv_devices.ip_address',
                'cctv_devices.site',
                'cctv_devices.device_type',
                'cctv_maintenance_logs.status',
                'cctv_maintenance_logs.event_type',
                'cctv_maintenance_logs.started_at',
                'cctv_maintenance_logs.resolved_at',
                'cctv_maintenance_logs.notes'
            )
            ->orderBy('cctv_maintenance_logs.started_at')
            ->get();

        foreach ($cctvLogs as $log) {
            $duration = $this->formatDuration($log->started_at, $log->resolved_at);
            $category = match (strtoupper($log->device_type ?? '')) {
                'NVR'  => 'NVR',
                'CCTV' => 'CCTV',
                default => 'CCTV/NVR',
            };
            $ws->fromArray([
                $category,
                $log->site ?? '',
                $log->device_name ?? '',
                $log->ip_address ?? '',
                strtoupper($log->status ?? ''),
                $log->event_type ?? '',
                $log->started_at ?? '',
                $log->resolved_at ?? '-',
                $duration,
                $log->notes ?? '',
            ], null, "A{$row}");
            $ws->getStyle("A{$row}:J{$row}")->applyFromArray(array_merge($borderAll, $dataStyle));
            $row++;
        }

        // ── Server maintenance logs ───────────────────────────────────────
        $serverLogs = DB::table('server_maintenance_logs')
            ->join('server_devices', 'server_maintenance_logs.device_id', '=', 'server_devices.id')
            ->whereBetween('server_maintenance_logs.started_at', [$this->from, $this->to])
            ->select(
                'server_devices.device_name',
                'server_devices.ip_address',
                'server_devices.site',
                'server_maintenance_logs.status',
                'server_maintenance_logs.event_type',
                'server_maintenance_logs.started_at',
                'server_maintenance_logs.resolved_at',
                'server_maintenance_logs.notes'
            )
            ->orderBy('server_maintenance_logs.started_at')
            ->get();

        foreach ($serverLogs as $log) {
            $start    = Carbon::parse($log->started_at);
            $end      = $log->resolved_at ? Carbon::parse($log->resolved_at) : null;
            $duration = $end ? number_format($start->diffInDays($end), 2) . ' day(s)' : 'Ongoing';
            $ws->fromArray([
                'Server',
                $log->site ?? '',
                $log->device_name ?? '',
                $log->ip_address ?? '',
                strtoupper($log->status ?? ''),
                $log->event_type ?? '',
                $log->started_at ?? '',
                $log->resolved_at ?? '-',
                $duration,
                $log->notes ?? '',
            ], null, "A{$row}");
            $ws->getStyle("A{$row}:J{$row}")->applyFromArray(array_merge($borderAll, $dataStyle));
            $row++;
        }

        // Empty state
        if ($row === 3) {
            $ws->setCellValue('A3', 'No maintenance logs found for this period.');
            $ws->mergeCells('A3:J3');
            $ws->getStyle('A3:J3')->applyFromArray([
                'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
                'font'      => ['italic' => true, 'color' => ['rgb' => '888888']],
                'borders'   => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
            ]);
        }

        foreach (range('A', 'J') as $col) {
            $ws->getColumnDimension($col)->setAutoSize(true);
        }
        $ws->getDefaultRowDimension()->setRowHeight(25);
    }
}
