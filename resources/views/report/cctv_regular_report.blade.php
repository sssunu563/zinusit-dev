<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCTV Reguler Check Form - {{ $report->doc_no }}</title>
    <link rel="icon" type="image/png" href="{{ asset('apple-touch-icon.png') }}">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        body {
            background-color: #f1f5f9;
            color: #000;
            font-size: 11px;
            line-height: 1.3;
        }

        .no-print {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            padding: 12px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            border: 1px solid transparent;
            transition: all 0.15s;
        }

        .btn-primary {
            background: #003628;
            color: #ffffff;
        }

        .btn-primary:hover {
            background: #004d39;
        }

        .btn-secondary {
            background: #ffffff;
            color: #475569;
            border-color: #cbd5e1;
        }

        .btn-secondary:hover {
            background: #f8fafc;
        }

        /* A4 Page Container */
        .page {
            width: 210mm;
            min-height: 297mm;
            padding: 14mm 14mm 12mm 14mm;
            margin: 15px auto;
            background: #ffffff;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            position: relative;
        }

        @media print {
            body {
                background: #ffffff;
            }
            .no-print {
                display: none !important;
            }
            .page {
                margin: 0;
                padding: 12mm 14mm;
                box-shadow: none;
                width: 100%;
                min-height: auto;
                page-break-after: always;
            }
            .page:last-child {
                page-break-after: avoid;
            }
        }

        /* Header block */
        .doc-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }

        .doc-title-block h1 {
            font-size: 16px;
            font-weight: bold;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
            color: #000;
        }

        .doc-title-block p {
            font-size: 10.5px;
            font-weight: bold;
            color: #1e293b;
        }

        .brand-logo-block {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .brand-logo-block img {
            width: 36px;
            height: 36px;
            object-fit: contain;
        }

        .brand-logo-block span {
            font-size: 11px;
            font-weight: 900;
            letter-spacing: 1.5px;
            margin-top: 2px;
        }

        /* Standard Table Formatting */
        table.form-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            font-size: 10px;
        }

        table.form-table th, 
        table.form-table td {
            border: 1px solid #000000;
            padding: 4px 6px;
            text-align: center;
        }

        .bg-yellow-header {
            background-color: #FFF2CC !important;
            font-weight: bold;
        }

        .bg-sub-header {
            background-color: #F8F9FA !important;
            font-weight: bold;
        }

        .text-left { text-align: left !important; }
        .text-right { text-align: right !important; }
        .text-center { text-align: center !important; }

        .section-bar {
            background-color: #FFF2CC;
            font-weight: bold;
            text-align: left;
            padding: 4px 8px;
            border: 1px solid #000;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 11px;
            margin-top: 6px;
        }

        .live-view-capture-box {
            border: 1px solid #000;
            border-top: none;
            padding: 6px;
            background: #fafafa;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .live-view-capture-inner {
            width: 100%;
            height: 440px;
            background: #000;
            border: 1px solid #94a3b8;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .live-view-capture-inner img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .before-after-card {
            border: 1px solid #000;
            padding: 8px;
            margin-bottom: 10px;
            background: #fff;
        }

        .before-after-header {
            font-weight: bold;
            font-size: 11px;
            margin-bottom: 6px;
            display: flex;
            justify-content: space-between;
            background: #f8fafc;
            padding: 4px 8px;
            border: 1px solid #e2e8f0;
        }

        .before-after-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .ba-box {
            border: 1px solid #000;
            padding: 4px;
        }

        .ba-title {
            background: #FFF2CC;
            font-weight: bold;
            padding: 3px 6px;
            border: 1px solid #000;
            margin-bottom: 4px;
            font-size: 10px;
            text-align: center;
        }

        .ba-image-wrapper {
            height: 160px;
            border: 1px dashed #cbd5e1;
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .ba-image-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .signature-box {
            height: 75px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .signature-box img {
            max-height: 65px;
            max-width: 140px;
            object-fit: contain;
        }

        .page-num {
            position: absolute;
            bottom: 8mm;
            right: 15mm;
            font-size: 9px;
            color: #666;
        }
    </style>
</head>
<body>

    <!-- Print Control Bar -->
    <div class="no-print">
        <div style="display: flex; align-items: center; gap: 10px;">
            <span style="font-weight: bold; font-size: 14px; color: #0f172a;">CCTV Reguler Check Form</span>
            <span style="background: #e2e8f0; font-size: 11px; padding: 2px 8px; border-radius: 4px; font-weight: bold;">
                {{ $report->doc_no }}
            </span>
        </div>
        <div style="display: flex; gap: 8px;">
            <button onclick="window.print()" class="btn btn-primary">
                🖨️ Cetak / Simpan PDF
            </button>
            <button onclick="window.close()" class="btn btn-secondary">
                Tutup
            </button>
        </div>
    </div>

    @php
        $areas = $report->resolved_areas ?? [];
        $maintenance = $report->maintenance_data ?? [];
        $maintenanceItems = $maintenance['items'] ?? [];
        
        // Resolve dynamic company name based on location
        $companyName = (isset($report->company_name) && $report->company_name)
            ? $report->company_name
            : (str_starts_with(strtoupper(trim($report->location ?? '')), 'ZDI') ? 'PT. ZINUS DREAM INDONESIA' : 'PT. ZINUS GLOBAL INDONESIA');

        // Total pages = 1 page per area + 1 maintenance & signature page (2 areas = 3 pages!)
        $totalPages = count($areas) + 1;
        if ($totalPages < 2) $totalPages = 2;
    @endphp

    <!-- ================= DYNAMIC AREA PAGES (1 PAGE PER AREA WITH LARGE LIVE VIEW) ================= -->
    @foreach ($areas as $areaIndex => $area)
        @php
            $areaName = $area['name'] ?? ('Area ' . ($areaIndex + 1));
            $camQty = $area['camera_qty'] ?? ($area['total_cam'] ?? 16);
            $okQty = $area['ok_qty'] ?? ($area['normal_cam'] ?? $camQty);
            $notOkQty = $area['not_ok_qty'] ?? ($area['problem_cam'] ?? 0);
            $remarks = $area['remarks'] ?? '-';
            $lastRecordDate = $area['last_record_date'] ?? null;
            $retentionDays = $area['record_days'] ?? ($area['retention_days'] ?? '-');
            $nvrId = $area['nvr_id'] ?? ($area['nvr'] ?? '-');
            $nvrRemarks = $area['nvr_remarks'] ?? '-';
            $screenshots = $area['screenshots'] ?? [];
            $actions = $area['action_needed'] ?? [];
            $mainScreenshot = $screenshots[0] ?? ($area['screenshot'] ?? null);
        @endphp

        <div class="page">
            <!-- Header -->
            <div class="doc-header">
                <div class="doc-title-block">
                    <h1>CCTV REGULER CHECK FORM</h1>
                    <p>{{ $companyName }}</p>
                </div>
                <div class="brand-logo-block">
                    <img src="{{ asset('apple-touch-icon.png') }}" alt="Logo Zinus">
                    <span>ZINUS</span>
                </div>
            </div>

            <!-- Meta Table -->
            <table class="form-table">
                <thead>
                    <tr class="bg-yellow-header">
                        <th style="width: 25%;">Location</th>
                        <th style="width: 25%;">Checked By</th>
                        <th style="width: 25%;">Checked Date</th>
                        <th style="width: 25%;">Document ID</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>{{ $report->location }}</td>
                        <td style="font-weight: bold;">{{ strtoupper($report->checked_by) }}</td>
                        <td>{{ \Carbon\Carbon::parse($report->checked_date)->format('d-M-y') }}</td>
                        <td style="font-weight: bold;">{{ $report->doc_no }}</td>
                    </tr>
                    <tr>
                        <td colspan="4" class="text-right" style="font-weight: bold; background: #fff; padding-right: 12px;">
                            Week: {{ $report->week_number }}
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Section Bar for Dynamic Area -->
            <div class="section-bar">
                <span>{{ strtoupper($areaName) }}</span>
                <span>○</span>
            </div>

            <!-- Camera Status & Retention Specs -->
            <table class="form-table" style="margin-bottom: 0;">
                <tr class="bg-sub-header">
                    <th style="width: 25%;">Camera Qty</th>
                    <th style="width: 25%;">OK</th>
                    <th style="width: 25%;">Not OK</th>
                    <th style="width: 25%;">Remarks</th>
                </tr>
                <tr>
                    <td>{{ $camQty }}</td>
                    <td>{{ $okQty }}</td>
                    <td>{{ $notOkQty }}</td>
                    <td>{{ $remarks ?: '-' }}</td>
                </tr>
                <tr class="bg-sub-header">
                    <th>Last Record date</th>
                    <th>Retention Days</th>
                    <th>NVR ID</th>
                    <th>Remarks</th>
                </tr>
                <tr>
                    <td>{{ !empty($lastRecordDate) ? \Carbon\Carbon::parse($lastRecordDate)->format('d-M-y') : '-' }}</td>
                    <td style="font-weight: bold; color: #0f172a;">{{ $retentionDays }} Hari</td>
                    <td style="font-weight: bold;">{{ $nvrId ?: '-' }}</td>
                    <td>{{ $nvrRemarks ?: '-' }}</td>
                </tr>
            </table>

            <!-- Captured Screen (Single Live View 16 Channels - Full Size) -->
            <div style="border: 1px solid #000; border-top: none; background: #FFF2CC; font-weight: bold; padding: 4px 8px; font-size: 10px;">
                Captured Screen (max 16 cameras) - {{ $areaName }}
            </div>
            <div class="live-view-capture-box">
                @if (!empty($mainScreenshot))
                    <div class="live-view-capture-inner">
                        <img src="{{ $mainScreenshot }}" alt="Live View {{ $areaName }}">
                    </div>
                @else
                    <div style="color: #64748b; font-size: 10px; font-style: italic; padding: 80px; text-align: center;">
                        (Belum ada tangkapan layar live view 16 channel diunggah)
                    </div>
                @endif
            </div>

            <!-- Action Needed Table -->
            <table class="form-table">
                <tr class="bg-yellow-header">
                    <th colspan="4" class="text-left">Action Needed ({{ $areaName }}):</th>
                </tr>
                <tr class="bg-sub-header">
                    <th style="width: 20%;">Camera ID</th>
                    <th style="width: 40%;">Action</th>
                    <th style="width: 20%;">Due Date (plan)</th>
                    <th style="width: 20%;">Remarks</th>
                </tr>
                @if (!empty($actions))
                    @foreach ($actions as $act)
                        <tr>
                            <td>{{ $act['camera_id'] ?? '-' }}</td>
                            <td class="text-left">{{ $act['action'] ?? '-' }}</td>
                            <td>{{ $act['due_date'] ?? '-' }}</td>
                            <td>{{ $act['remarks'] ?? '-' }}</td>
                        </tr>
                    @endforeach
                @else
                    <tr>
                        <td>-</td>
                        <td class="text-left">-</td>
                        <td>-</td>
                        <td>-</td>
                    </tr>
                @endif
            </table>

            <div class="page-num">Page {{ $areaIndex + 1 }} of {{ $totalPages }}</div>
        </div>
    @endforeach

    <!-- ================= PAGE 3: MAINTENANCE & SIGNATURE PAGE ================= -->
    <div class="page">
        <!-- Header -->
        <div class="doc-header">
            <div class="doc-title-block">
                <h1>CCTV REGULER CHECK FORM</h1>
                <p>{{ $companyName }}</p>
            </div>
            <div class="brand-logo-block">
                <img src="{{ asset('apple-touch-icon.png') }}" alt="Logo Zinus">
                <span>ZINUS</span>
            </div>
        </div>

        <!-- Meta Table -->
        <table class="form-table">
            <thead>
                <tr class="bg-yellow-header">
                    <th style="width: 25%;">Location</th>
                    <th style="width: 25%;">Checked By</th>
                    <th style="width: 25%;">Checked Date</th>
                    <th style="width: 25%;">Document ID</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $report->location }}</td>
                    <td style="font-weight: bold;">{{ strtoupper($report->checked_by) }}</td>
                    <td>{{ \Carbon\Carbon::parse($report->checked_date)->format('d-M-y') }}</td>
                    <td style="font-weight: bold;">{{ $report->doc_no }}</td>
                </tr>
                <tr>
                    <td colspan="4" class="text-right" style="font-weight: bold; background: #fff; padding-right: 12px;">
                        Week: {{ $report->week_number }}
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Section: Maintenance Log -->
        <div class="section-bar">
            <span>OTHER CCTV (WEEKLY MAINTENANCE)</span>
        </div>
        <table class="form-table" style="margin-bottom: 8px;">
            <tr class="bg-sub-header">
                <th style="width: 20%;">Camera ID</th>
                <th style="width: 45%;">Action / Catatan Pemeliharaan</th>
                <th style="width: 15%;">Act Date</th>
                <th style="width: 20%;">Remarks</th>
            </tr>
            @if (!empty($maintenanceItems))
                @foreach ($maintenanceItems as $item)
                    <tr>
                        <td>{{ $item['camera_id'] ?? '-' }}</td>
                        <td class="text-left">{{ $item['action'] ?? '-' }}</td>
                        <td>{{ $item['act_date'] ?? '-' }}</td>
                        <td>{{ $item['remarks'] ?? '-' }}</td>
                    </tr>
                @endforeach
            @else
                <tr>
                    <td>-</td>
                    <td class="text-left">-</td>
                    <td>-</td>
                    <td>-</td>
                </tr>
            @endif
            @if (!empty($maintenance['last_record_date']) || !empty($maintenance['nvr_id']))
                <tr class="bg-sub-header">
                    <th colspan="2">Last Record date</th>
                    <th colspan="2">NVR ID</th>
                </tr>
                <tr>
                    <td colspan="2">{{ $maintenance['last_record_date'] ?? '-' }}</td>
                    <td colspan="2" style="font-weight: bold;">{{ $maintenance['nvr_id'] ?? '-' }}</td>
                </tr>
            @endif
        </table>

        <!-- Before & After Comparison Photos -->
        <div style="font-weight: bold; font-size: 11px; margin-bottom: 6px; color: #1e293b;">
            Dokumentasi Pemeliharaan (Before & After):
        </div>

        @php
            $hasItemPhotos = false;
            foreach ($maintenanceItems as $mItem) {
                if (!empty($mItem['before_photo']) || !empty($mItem['after_photo'])) {
                    $hasItemPhotos = true;
                    break;
                }
            }
        @endphp

        @if ($hasItemPhotos)
            @foreach ($maintenanceItems as $mIdx => $mItem)
                @if (!empty($mItem['before_photo']) || !empty($mItem['after_photo']))
                    <div class="before-after-card">
                        <div class="before-after-header">
                            <span>Item #{{ $mIdx + 1 }}: {{ $mItem['camera_id'] ?? 'Kamera' }} - {{ $mItem['action'] ?? 'Maintenance' }}</span>
                            <span>{{ $mItem['act_date'] ?? '' }}</span>
                        </div>
                        <div class="before-after-container">
                            <div class="ba-box">
                                <div class="ba-title">Before (Sebelum)</div>
                                <div class="ba-image-wrapper">
                                    @if (!empty($mItem['before_photo']))
                                        <img src="{{ $mItem['before_photo'] }}" alt="Before Item {{ $mIdx + 1 }}">
                                    @else
                                        <span style="color: #94a3b8; font-size: 10px;">(Tidak ada foto Before)</span>
                                    @endif
                                </div>
                            </div>
                            <div class="ba-box">
                                <div class="ba-title">After (Sesudah)</div>
                                <div class="ba-image-wrapper">
                                    @if (!empty($mItem['after_photo']))
                                        <img src="{{ $mItem['after_photo'] }}" alt="After Item {{ $mIdx + 1 }}">
                                    @else
                                        <span style="color: #94a3b8; font-size: 10px;">(Tidak ada foto After)</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            @endforeach
        @else
            <!-- Standard Before & After Photo Boxes (Original Layout) -->
            <div class="before-after-container" style="margin-bottom: 12px;">
                <div class="ba-box">
                    <div class="ba-title">Before</div>
                    <div class="ba-image-wrapper">
                        @if (!empty($maintenance['before_photo']))
                            <img src="{{ $maintenance['before_photo'] }}" alt="Foto Before">
                        @else
                            <span style="color: #94a3b8; font-size: 10px;">(Tidak ada foto Before)</span>
                        @endif
                    </div>
                </div>
                <div class="ba-box">
                    <div class="ba-title">After</div>
                    <div class="ba-image-wrapper">
                        @if (!empty($maintenance['after_photo']))
                            <img src="{{ $maintenance['after_photo'] }}" alt="Foto After">
                        @else
                            <span style="color: #94a3b8; font-size: 10px;">(Tidak ada foto After)</span>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <!-- Signatures Section -->
        <table class="form-table" style="margin-top: 15px;">
            <thead>
                <tr class="bg-yellow-header">
                    <th style="width: 50%;">Dept #1: {{ $report->signature_dept1_name ?? 'IT' }}</th>
                    <th style="width: 50%;">Dept #2: {{ $report->signature_dept2_name ?? 'Exim' }}</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="vertical-align: middle; height: 80px;">
                        <div class="signature-box">
                            @if (!empty($report->signature_dept1_image))
                                @if (str_starts_with($report->signature_dept1_image, 'data:image') || str_starts_with($report->signature_dept1_image, 'http') || str_starts_with($report->signature_dept1_image, '/'))
                                    <img src="{{ $report->signature_dept1_image }}" alt="Tanda Tangan Dept 1">
                                @else
                                    <span style="font-size: 10px; color: #64748b;">(Digital Signed)</span>
                                @endif
                            @else
                                <span style="font-size: 10px; color: #94a3b8;">Belum Ditandatangani</span>
                            @endif
                        </div>
                    </td>
                    <td style="vertical-align: middle; height: 80px;">
                        <div class="signature-box">
                            @if (!empty($report->signature_dept2_image))
                                @if (str_starts_with($report->signature_dept2_image, 'data:image') || str_starts_with($report->signature_dept2_image, 'http') || str_starts_with($report->signature_dept2_image, '/'))
                                    <img src="{{ $report->signature_dept2_image }}" alt="Tanda Tangan Dept 2">
                                @else
                                    <span style="font-size: 10px; color: #64748b;">(Digital Signed)</span>
                                @endif
                            @else
                                <span style="font-size: 10px; color: #94a3b8;">Belum Ditandatangani</span>
                            @endif
                        </div>
                    </td>
                </tr>
                <tr class="bg-sub-header">
                    <td style="font-weight: bold;">
                        Name: {{ strtoupper($report->signature_dept1_signer ?? $report->checked_by) }}
                    </td>
                    <td style="font-weight: bold;">
                        Name: {{ strtoupper($report->signature_dept2_signer ?? '-') }}
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="page-num">Page {{ $totalPages }} of {{ $totalPages }}</div>
    </div>

</body>
</html>
