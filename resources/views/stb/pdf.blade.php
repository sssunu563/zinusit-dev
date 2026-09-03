<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $docId }}</title>
    <style>
        * { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; background: #fff; color: #111827; }
        .document { width: 194mm; margin: 0 auto; font-size: 10px; color: #111827; background: #fff; }
        table { width: 100%; border-collapse: collapse; border-spacing: 0; table-layout: fixed; border: 1px solid #cbd5e1; }
        td, th { border: 1px solid #cbd5e1; padding: 4px 6px; vertical-align: top; }
        .header-table { margin-bottom: 4px; }
        .logo-cell { width: 20%; text-align: center; vertical-align: middle; }
        .title-cell { width: 50%; text-align: center; vertical-align: middle; }
        .meta-cell { width: 30%; font-size: 10px; text-align: center; vertical-align: middle; }
        .logo { display: block; width: 64px; height: auto; margin: 0 auto; }
        .title-main { font-size: 16px; font-weight: 600; letter-spacing: 0.04em; }
        .title-sub { margin-top: 2px; font-size: 11px; font-weight: 500; }
        .meta-title { margin-bottom: 2px; font-weight: 600; }
        .info-table, .items-table, .note-table { margin-top: 4px; }
        .label { width: 20%; background: #f1f5f9; font-weight: 600; }
        .recipient-note { margin-top: 6px; font-weight: 500; display: flex; align-items: center; justify-content: space-between; gap: 12px; }
        .items-table thead th { background: #f1f5f9; text-align: left; font-weight: 600; }
        .narrow { width: 8%; }
        .center { text-align: center; }
        .agreement-box { margin-top: 6px; border: 1px solid #cbd5e1; background: #f8fafc; padding: 8px 10px; }
        .agreement-title { margin: 0 0 4px; font-weight: 700; }
        .agreement-text { margin: 0 0 6px; line-height: 1.35; }
        .signature-grid { display: grid; grid-template-columns: 1.6fr 1fr; gap: 6px; margin-top: 6px; align-items: start; }
        .signature-title { background: #f1f5f9; text-align: center; font-weight: 600; }
        .signature-head { background: #f8fafc; text-align: center; font-weight: 600; }
        .signature-body { height: 92px; text-align: center; vertical-align: top; font-weight: 500; }
        .signature-sign-box { display: flex; height: 44px; align-items: center; justify-content: center; margin-bottom: 6px; }
        .signature-image { max-width: 100%; max-height: 42px; object-fit: contain; }
        .signature-time { margin-top: 3px; font-size: 8px; font-weight: 500; color: #6b7280; }
        .photo-cell { width: 34%; }
        .photo-box { display: flex; min-height: 126px; align-items: center; justify-content: center; overflow: hidden; background: #f8fafc; }
        .photo { max-width: 100%; max-height: 116px; object-fit: contain; }
        .photo-empty { color: #9ca3af; }
        .remark-cell { width: 66%; }
        .remark-status { margin-bottom: 6px; font-weight: 700; }
        .remark-title { margin-bottom: 6px; font-weight: 700; }
        .remark-content { min-height: 104px; white-space: pre-wrap; line-height: 1.35; }
    </style>
</head>
<body>
<div class="document">
    <table class="header-table"><tbody><tr>
        <td class="logo-cell">@if($logo)<img src="{{ $logo }}" class="logo" alt="Zinus" />@endif</td>
        <td class="title-cell"><div class="title-main">SURAT TANDA BUKTI</div><div class="title-sub">ZINUS DREAM INDONESIA</div></td>
        <td class="meta-cell"><div class="meta-title">IT Dept.</div><div>Doc. No. IT/STB/VII/24/01</div></td>
    </tr></tbody></table>

    <table class="info-table"><tbody>
        <tr><td class="label">Doc ID</td><td>{{ $docId }}</td><td class="label">Location</td><td>{{ $location }}</td></tr>
        <tr><td class="label">Deliver Date</td><td>{{ $deliverDate }}</td><td class="label">Building</td><td>{{ $building }}</td></tr>
        <tr><td class="label">Use Date</td><td>{{ $useDate }}</td><td class="label">Batch No</td><td>{{ $batchNo }}</td></tr>
        <tr><td class="label">Request Doc No</td><td>{{ $reqDocNo }}</td><td class="label">PO Doc No</td><td>{{ $poDocNo }}</td></tr>
    </tbody></table>

    <div class="recipient-note"><span>Saya yang bertandatangan di bawah ini:</span><span>{{ $createdDate }}</span></div>

    <table class="info-table"><tbody>
        <tr><td class="label">Name</td><td>{{ $userName }}</td><td class="label">Company</td><td>{{ $company }}</td></tr>
        <tr><td class="label">Phone Number</td><td>{{ $phoneNumber }}</td><td class="label">Department</td><td>{{ $department }}</td></tr>
        <tr><td class="label">Email</td><td>{{ $email }}</td><td class="label">Position</td><td>{{ $position }}</td></tr>
    </tbody></table>

    <table class="items-table">
        <thead><tr><th class="narrow">No</th><th>Nama Barang</th><th>Type</th><th class="narrow">Qty</th><th>Serial No</th><th>Asset</th></tr></thead>
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
        <p class="agreement-text"><strong>(A)</strong> Menyimpan dan menjaga semua dokumen, informasi, atau keterangan yang terdapat di dalam barang/ asset yang dianggap sebagai rahasia Perusahaan. <br><strong>(B)</strong> Menjaga dan berusaha mencegah kemungkinan hal-hal yang dapat membahayakan barang/ asset perusahaan. <br><strong>(C)</strong> Merawat, menjaga keamanan/ kebersihan dan memelihara barang/ asset milik perusahaan yang dipercayakan kepadanya atau yang digunakan dalam melaksanakan pekerjaannya. <br><strong>(D)</strong> Bertanggungjawab melakukan penggantian apabila melakukan kesalahan/ kelalaian pribadi yang mengakibatkan rusak/hilangnya barang/aset perusahaan.</p>
        <p class="agreement-title">Pelanggaran:</p>
        <p class="agreement-text"><strong>(A)</strong> Membawa keluar atau menyalahgunakan barang barang milik perusahaan dan/ atau perlengkapan milik perusahaan untuk kepentingan pribadi tanpa izin pimpinan perusahaan. <br><strong>(B)</strong> Menyalahgunakan barang-barang milik perusahaan yang dipercayakan kepadanya untuk kepentingan dan keuntungan pribadi ataupun pihak ketiga lainnya</p>
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
                if ($p['type'] === 'start') {
                    $path .= "M $x $y ";
                } else {
                    $path .= "L $x $y ";
                }
                $minX = min($minX, $x);
                $minY = min($minY, $y);
                $maxX = max($maxX, $x);
                $maxY = max($maxY, $y);
            }

            $width = ($maxX - $minX) ?: 1;
            $height = ($maxY - $minY) ?: 1;
            $viewBox = ($minX - 5) . " " . ($minY - 5) . " " . ($width + 10) . " " . ($height + 10);

            return [
                'path' => $path,
                'viewBox' => $viewBox
            ];
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
                    <div>{{ $itDrafterName }}</div>
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
                    <div>{{ $itCheckerName }}</div>
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
                    <div>{{ $itApprovedName }}</div>
                    <div class="signature-time">{{ $itApprovedSignedAt }}</div>
                </td>
            </tr>
        </tbody></table>

        <table><tbody>
            <tr><td colspan="2" class="signature-title">Requester</td></tr>
            <tr><td class="signature-head">Received</td><td class="signature-head">Dept Head</td></tr>
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
                    <div>{{ $userName }}</div>
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
                    <div>{{ $deptHeadName }}</div>
                    <div class="signature-time">{{ $requesterDeptHeadSignedAt }}</div>
                </td>
            </tr>
        </tbody></table>
    </div>

    <table class="note-table"><tbody><tr>
        <td class="photo-cell"><div class="photo-box">@if($photo)<img src="{{ $photo }}" class="photo" alt="STB Photo" />@else<span class="photo-empty">No Photo</span>@endif</div></td>
        <td class="remark-cell"><div class="remark-title">Remark :</div><div class="remark-content">{{ $remark }}</div></td>
    </tr></tbody></table>

</div>
</body>
</html>


