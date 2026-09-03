<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $docId }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm 15mm;
        }

        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #fff;
            color: #111827;
        }

        .document {
            width: 100%;
            font-size: 9px;
            color: #111827;
            background: #fff;
            line-height: 1.35;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            border-spacing: 0;
            table-layout: fixed;
            border: 1px solid #cbd5e1;
        }

        td, th {
            border: 1px solid #cbd5e1;
            padding: 4px 8px;
            vertical-align: top;
        }

        .header-table {
            margin-bottom: 6px;
        }

        .header-table td {
            vertical-align: middle;
            padding: 8px 10px;
        }

        .logo-cell { width: 25%; text-align: center; }
        .title-cell { width: 50%; text-align: center; }
        .meta-cell { width: 25%; text-align: center; font-size: 8.5px; line-height: 1.4; }

        .logo { display: block; width: 60px; height: auto; margin: 0 auto; }
        .title-main { font-size: 14px; font-weight: 700; letter-spacing: 0.02em; color: #003628; }
        .title-sub { margin-top: 1px; font-size: 9.5px; font-weight: 600; color: #475569; }

        .info-table { margin-top: 6px; }
        .info-table td.label { width: 18%; background: #f8fafc; font-weight: 600; color: #475569; }
        .info-table td.value { width: 32%; }

        .items-table { margin-top: 6px; }
        .items-table thead th {
            background: #f8fafc;
            text-align: center;
            font-weight: 700;
            color: #475569;
            padding: 6px 8px;
        }
        .col-no { width: 6%; }
        .col-name { width: 24%; }
        .col-type { width: 20%; }
        .col-qty { width: 6%; }
        .col-sn { width: 24%; }
        .col-asset { width: 20%; }

        .recipient-note {
            margin: 8px 0 4px;
            font-weight: 600;
            font-size: 8.5px;
            display: block;
            width: 100%;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 2px;
            overflow: hidden;
        }
        .recipient-note .date { float: right; color: #64748b; }
        .center { text-align: center; }

        .agreement-box {
            margin-top: 6px;
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            padding: 8px 12px;
        }
        .agreement-title {
            margin: 0 0 4px;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 8px;
            color: #334155;
        }
        .agreement-line {
            display: table;
            width: 100%;
            margin-bottom: 2px;
            font-size: 8.5px;
            line-height: 1.3;
        }
        .point-num {
            display: table-cell;
            width: 22px;
            font-weight: 700;
            color: #003628;
            vertical-align: top;
        }
        .point-text {
            display: table-cell;
            vertical-align: top;
        }

        .signature-grid {
            display: grid;
            grid-template-columns: 1.6fr 1fr;
            gap: 8px;
            margin-top: 8px;
            align-items: start;
        }
        .signature-title {
            background: #f8fafc;
            text-align: center;
            font-weight: 700;
            font-size: 8.5px;
            height: 18px;
            vertical-align: middle;
        }
        .signature-head {
            background: #ffffff;
            text-align: center;
            font-weight: 700;
            font-size: 8px;
            height: 18px;
            vertical-align: middle;
            color: #475569;
        }
        .signature-body {
            height: 88px;
            text-align: center;
            vertical-align: top;
            padding-top: 6px;
        }
        .signature-sign-box {
            display: flex;
            height: 42px;
            align-items: center;
            justify-content: center;
            margin-bottom: 4px;
        }
        .signature-image {
            max-width: 90%;
            max-height: 40px;
            object-fit: contain;
        }
        .signature-name {
            font-weight: 700;
            font-size: 9px;
            margin-top: 2px;
            border-bottom: 1px solid #cbd5e1;
            display: inline-block;
            padding: 0 8px;
        }
        .signature-time {
            font-size: 7px;
            font-weight: 500;
            color: #94a3b8;
            margin-top: 2px;
        }

        .note-table {
            margin-top: 8px;
        }
        .photo-cell {
            width: 32%;
            vertical-align: top;
            padding: 4px;
        }
        .remark-cell {
            width: 68%;
            vertical-align: top;
            padding: 8px 12px;
        }
        .photo-box {
            display: flex;
            min-height: 120px;
            align-items: center;
            justify-content: center;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }
        .photo {
            max-width: 100%;
            max-height: 110px;
            object-fit: contain;
        }
        .remark-title {
            margin-bottom: 4px;
            font-weight: 700;
            color: #475569;
            font-size: 9px;
        }
        .remark-content {
            font-size: 9px;
            line-height: 1.45;
            color: #1e293b;
        }
    </style>
</head>
<body>
<div class="document">
    <table class="header-table"><tbody><tr>
        <td class="logo-cell">@if($logo)<img src="{{ $logo }}" class="logo" alt="Zinus" />@endif</td>
        <td class="title-cell">
            <div class="title-main">{{ ($movementType ?? '') === 'return' ? 'FORM PENGEMBALIAN BARANG' : 'FORM SERAH TERIMA BARANG' }}</div>
            <div class="title-sub">PT. Zinus Global Indonesia</div>
        </td>
        <td class="meta-cell">
            <div style="font-weight: 700; color: #475569;">IT Dept.</div>
            <div style="margin-top: 2px;">Doc. No. IT/STB/XII/24/01</div>
        </td>
    </tr></tbody></table>

    <table class="info-table"><tbody>
        <tr><td class="label">Doc ID</td><td class="value">{{ $docId }}</td><td class="label">Location</td><td class="value">{{ $location }}</td></tr>
        <tr><td class="label">{{ ($movementType ?? '') === 'return' ? 'Return Date' : 'Deliver Date' }}</td><td class="value">{{ $deliverDate }}</td><td class="label">Building</td><td class="value">{{ $building }}</td></tr>
        <tr><td class="label">Use Date</td><td class="value">{{ $useDate }}</td><td class="label">Batch No</td><td class="value">{{ $batchNo }}</td></tr>
        <tr><td class="label">Request Doc No</td><td class="value">{{ $reqDocNo }}</td><td class="label">{{ !empty($expectedReturnDate) ? 'Est. Kembali' : 'PO Doc No' }}</td><td class="value">{{ !empty($expectedReturnDate) ? $expectedReturnDate : $poDocNo }}</td></tr>
    </tbody></table>

    <div class="recipient-note">
        <span>
            @if(($movementType ?? '') === 'return')
                Aset di bawah ini telah dikembalikan oleh:
            @elseif(($movementType ?? '') === 'loan')
                Saya yang bertandatangan di bawah ini (Peminjam):
            @else
                Saya yang bertandatangan di bawah ini:
            @endif
        </span>
        <span class="date">{{ $createdDate }}</span>
    </div>

    <table class="info-table"><tbody>
        <tr><td class="label">{{ ($movementType ?? '') === 'return' ? 'User Return by' : 'Name' }}</td><td class="value">{{ $userName }}</td><td class="label">Company</td><td class="value">{{ $company }}</td></tr>
        <tr><td class="label">Phone Number</td><td class="value">{{ $phoneNumber }}</td><td class="label">Department</td><td class="value">{{ $department }}</td></tr>
        <tr><td class="label">Email</td><td class="value">{{ $email }}</td><td class="label">Position</td><td class="value">{{ $position }}</td></tr>
    </tbody></table>

    <table class="items-table">
        <thead>
            <tr>
                <th class="col-no">No</th>
                <th class="col-name">Nama Barang</th>
                <th class="col-type">Type</th>
                <th class="col-qty">Qty</th>
                <th class="col-sn">Serial No</th>
                <th class="col-asset">Asset</th>
            </tr>
        </thead>
        <tbody>
        @foreach($items as $index => $item)
            <tr><td class="center">{{ $index + 1 }}</td><td>{{ $item['nama'] }}</td><td>{{ $item['type'] }}</td><td class="center">{{ $item['jumlah'] }}</td><td>{{ $item['serial_no'] }}</td><td>{{ $item['asset'] }}</td></tr>
        @endforeach
        @for($i = count($items); $i < 5; $i++)
            <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td></tr>
        @endfor
        </tbody>
    </table>

    <div class="agreement-box">
        <p class="agreement-title">Telah menyetujui ketentuan yang berlaku dalam keadaan sadar dan tanpa ada paksaan dari pihak manapun:</p>
        <div class="agreement-line"><div class="point-num">(A)</div><div class="point-text">Menyimpan dan menjaga semua dokumen, informasi, atau keterangan yang terdapat di dalam barang/ asset yang dianggap sebagai rahasia Perusahaan.</div></div>
        <div class="agreement-line"><div class="point-num">(B)</div><div class="point-text">Menjaga dan berusaha mencegah kemungkinan hal-hal yang dapat membahayakan barang/ asset perusahaan.</div></div>
        <div class="agreement-line"><div class="point-num">(C)</div><div class="point-text">Merawat, menjaga keamanan/ kebersihan dan memelihara barang/ asset milik perusahaan yang dipercayakan kepadanya atau yang digunakan dalam melaksanakan pekerjaannya.</div></div>
        <div class="agreement-line"><div class="point-num">(D)</div><div class="point-text">Bertanggungjawab melakukan penggantian apabila melakukan kesalahan/ kelalaian pribadi yang mengakibatkan rusak/hilangnya barang/aset perusahaan.</div></div>

        <p class="agreement-title" style="margin-top: 8px;">Pelanggaran:</p>
        <div class="agreement-line"><div class="point-num">(A)</div><div class="point-text">Membawa keluar atau menyalahgunakan barang-barang milik perusahaan dan/atau perlengkapan milik perusahaan untuk kepentingan pribadi tanpa izin pimpinan perusahaan.</div></div>
        <div class="agreement-line"><div class="point-num">(B)</div><div class="point-text">Menyalahgunakan barang-barang milik perusahaan yang dipercayakan kepadanya untuk kepentingan dan keuntungan pribadi ataupun pihak ketiga lainnya.</div></div>
    </div>

    @php
        $renderSignature = function($signatureData) {
            if (!$signatureData || !str_starts_with($signatureData, '[')) {
                return null;
            }

            try {
                $strokes = json_decode($signatureData, true);
                if (empty($strokes)) return null;

                $path = '';
                $minX = INF; $minY = INF; $maxX = -INF; $maxY = -INF;

                foreach ($strokes as $p) {
                    $x = $p['x'];
                    $y = $p['y'];
                    $path .= ($p['type'] === 'start' ? "M $x $y " : "L $x $y ");
                    $minX = min($minX, $x);
                    $minY = min($minY, $y);
                    $maxX = max($maxX, $x);
                    $maxY = max($maxY, $y);
                }

                $width = ($maxX - $minX) ?: 1;
                $height = ($maxY - $minY) ?: 1;
                $viewBox = ($minX - 5) . ' ' . ($minY - 5) . ' ' . ($width + 10) . ' ' . ($height + 10);

                return ['path' => $path, 'viewBox' => $viewBox];
            } catch (\Exception $e) {
                return null;
            }
        };
    @endphp

    <div class="signature-grid">
        <table><tbody>
            <tr><td colspan="3" class="signature-title">IT</td></tr>
            <tr><td class="signature-head">Drafter</td><td class="signature-head">Checker</td><td class="signature-head">Approved</td></tr>
            <tr>
                <td class="signature-body">
                    <div class="signature-sign-box">
                        @if($itDrafterSignature)
                            @php $sig = $renderSignature($itDrafterSignature); @endphp
                            @if($sig)
                                <svg viewBox="{{ $sig['viewBox'] }}" preserveAspectRatio="xMidYMid meet" class="signature-image">
                                    <path d="{{ $sig['path'] }}" fill="none" stroke="#003628" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            @else
                                <img src="{{ $itDrafterSignature }}" class="signature-image" alt="" />
                            @endif
                        @endif
                    </div>
                    <div class="signature-name">{{ $itDrafterName }}</div>
                    <div class="signature-time">{{ $itDrafterSignedAt }}</div>
                </td>
                <td class="signature-body">
                    <div class="signature-sign-box">
                        @if($itCheckerSignature)
                            @php $sig = $renderSignature($itCheckerSignature); @endphp
                            @if($sig)
                                <svg viewBox="{{ $sig['viewBox'] }}" preserveAspectRatio="xMidYMid meet" class="signature-image">
                                    <path d="{{ $sig['path'] }}" fill="none" stroke="#003628" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            @else
                                <img src="{{ $itCheckerSignature }}" class="signature-image" alt="" />
                            @endif
                        @endif
                    </div>
                    <div class="signature-name">{{ $itCheckerName }}</div>
                    <div class="signature-time">{{ $itCheckerSignedAt }}</div>
                </td>
                <td class="signature-body">
                    <div class="signature-sign-box">
                        @if($itApprovedSignature)
                            @php $sig = $renderSignature($itApprovedSignature); @endphp
                            @if($sig)
                                <svg viewBox="{{ $sig['viewBox'] }}" preserveAspectRatio="xMidYMid meet" class="signature-image">
                                    <path d="{{ $sig['path'] }}" fill="none" stroke="#003628" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            @else
                                <img src="{{ $itApprovedSignature }}" class="signature-image" alt="" />
                            @endif
                        @endif
                    </div>
                    <div class="signature-name">{{ $itApprovedName }}</div>
                    <div class="signature-time">{{ $itApprovedSignedAt }}</div>
                </td>
            </tr>
        </tbody></table>

        <table style="table-layout:fixed;"><tbody>
            <tr><td colspan="2" class="signature-title">Requester</td></tr>
            <tr>
                <td class="signature-head" style="width:50%;">@if(($movementType ?? '') === 'return') Returned @elseif(($movementType ?? '') === 'loan') Borrower @else Received @endif</td>
                <td class="signature-head" style="width:50%;">Dept Head</td>
            </tr>
            <tr>
                <td class="signature-body">
                    <div class="signature-sign-box">
                        @if($requesterReceivedSignature)
                            @php $sig = $renderSignature($requesterReceivedSignature); @endphp
                            @if($sig)
                                <svg viewBox="{{ $sig['viewBox'] }}" preserveAspectRatio="xMidYMid meet" class="signature-image">
                                    <path d="{{ $sig['path'] }}" fill="none" stroke="#003628" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            @else
                                <img src="{{ $requesterReceivedSignature }}" class="signature-image" alt="" />
                            @endif
                        @endif
                    </div>
                    <div class="signature-name">{{ $userName }}</div>
                    <div class="signature-time">{{ $requesterReceivedSignedAt }}</div>
                </td>
                <td class="signature-body">
                    <div class="signature-sign-box">
                        @if($requesterDeptHeadSignature)
                            @php $sig = $renderSignature($requesterDeptHeadSignature); @endphp
                            @if($sig)
                                <svg viewBox="{{ $sig['viewBox'] }}" preserveAspectRatio="xMidYMid meet" class="signature-image">
                                    <path d="{{ $sig['path'] }}" fill="none" stroke="#003628" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            @else
                                <img src="{{ $requesterDeptHeadSignature }}" class="signature-image" alt="" />
                            @endif
                        @endif
                    </div>
                    <div class="signature-name">{{ $deptHeadName }}</div>
                    <div class="signature-time">{{ $requesterDeptHeadSignedAt }}</div>
                </td>
            </tr>
        </tbody></table>
    </div>

    <table class="note-table"><tbody><tr>
        <td class="photo-cell">
            <div class="photo-box">@if($photo)<img src="{{ $photo }}" class="photo" alt="STB Photo" />@else<span style="font-size:8px;color:#94a3b8;">No Photo</span>@endif</div>
        </td>
        <td class="remark-cell">
            <div class="remark-title">{{ ($movementType ?? '') === 'return' ? 'Catatan Pengembalian' : 'Catatan' }} :</div>
            <div class="remark-content">{{ $remark ?: '-' }}</div>
        </td>
    </tr></tbody></table>
</div>
</body>
</html>


