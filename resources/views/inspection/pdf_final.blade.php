<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $reportId }}</title>
    <style>
        @page { size: A4 portrait; margin: 10mm 12mm; }
        * { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body { margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; background: #fff; color: #111827; font-size: 9px; line-height: 1.4; }

        table { width: 100%; border-collapse: collapse; border-spacing: 0; }
        td, th { border: 1px solid #94a3b8; padding: 4px 7px; vertical-align: top; }

        /* ── HEADER ── */
        .header-table td { vertical-align: middle; padding: 8px 10px; border: 1px solid #94a3b8; }
        .logo-cell  { width: 22%; text-align: center; }
        .title-cell { width: 56%; text-align: center; }
        .title-main { font-size: 13px; font-weight: 800; color: #003628; letter-spacing: 0.04em; }
        .title-sub  { font-size: 9px; font-weight: 600; color: #475569; margin-top: 2px; }
        .meta-cell  { width: 22%; text-align: center; font-size: 8.5px; line-height: 1.5; color: #475569; }
        .meta-cell strong { font-weight: 700; color: #334155; }
        .logo { display: block; width: 55px; height: auto; margin: 0 auto 3px; }

        /* ── DEVICE TABLE (3 cols) ── */
        .device-table { margin-top: 5px; }
        .device-table th { background: #f1f5f9; text-align: left; font-weight: 700; font-size: 9px; padding: 5px 8px; width: 33.33%; }
        .device-table td { font-size: 9.5px; font-weight: 600; padding: 5px 8px; width: 33.33%; }

        /* ── INFO GRID (4 cols, label row + value row) ── */
        .info-table { margin-top: 5px; }
        .info-table .lbl { background: #f1f5f9; font-weight: 700; font-size: 9px; color: #334155; width: 25%; }
        .info-table .val { font-size: 9px; width: 25%; }

        /* ── ISSUE + PHOTO ── */
        .issue-photo-table { margin-top: 5px; }
        .issue-header { background: #f1f5f9; color: #334155; font-weight: 700; font-size: 9px; padding: 5px 8px; }
        .issue-cell  { width: 40%; vertical-align: top; padding: 8px 8px; font-size: 9px; white-space: pre-wrap; line-height: 1.6; }
        .photo-cell  { width: 60%; vertical-align: top; padding: 4px; text-align: center; }
        .photo-img   { max-width: 100%; max-height: 180px; object-fit: contain; display: block; margin: 0 auto; }

        /* ── SOLUTION / NOTE ── */
        .section-table { margin-top: 4px; }
        .section-label   { background: #f1f5f9; font-weight: 700; font-size: 9px; color: #334155; padding: 5px 8px; }
        .section-content { padding: 6px 8px; font-size: 9px; white-space: pre-wrap; min-height: 20px; line-height: 1.6; }

        /* ── CONFIRMATION ── */
        .confirm-table { margin-top: 6px; }
        .confirm-header   { background: #f1f5f9; color: #334155; font-weight: 700; font-size: 9.5px; padding: 5px 8px; }
        .confirm-col-head { background: #f8f8f8; font-weight: 700; text-align: center; font-size: 9px; padding: 4px 6px; width: 25%; }
        .confirm-sig-cell { height: 60px; text-align: center; vertical-align: bottom; padding: 4px 6px; }
        .confirm-name-row td { text-align: center; font-weight: 700; font-size: 9px; padding: 4px 6px; }
        .sig-img { max-width: 90%; max-height: 50px; object-fit: contain; display: block; margin: 0 auto; }
    </style>
</head>
<body>

{{-- ── HEADER ── --}}
<table class="header-table">
    <tbody><tr>
        <td class="logo-cell">
            @if($logo)<img src="{{ $logo }}" class="logo" alt="Zinus" />@endif
        </td>
        <td class="title-cell">
            <div class="title-main">Inspection Report</div>
            <div class="title-sub">PT. ZINUS GLOBAL INDONESIA</div>
        </td>
        <td class="meta-cell">
            <strong>IT Dept.</strong><br>
            Doc. No. IT/INSP/II/25/01
        </td>
    </tr></tbody>
</table>

{{-- ── DEVICE INFO (3 cols: Asset Tag | Kategori | Serial Number) ── --}}
@php
    $catLabels = ['pc'=>'PC','laptop'=>'Laptop','printer'=>'Printer','monitor'=>'Monitor','other'=>'Other','network'=>'Network Device'];
    $catLabel  = $catLabels[$deviceCategory] ?? $deviceCategory;
@endphp
<table class="device-table">
    <thead>
        <tr>
            <th>Asset Tag</th>
            <th>Kategori</th>
            <th>Serial Number</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>{{ $assetTag }}</td>
            <td>{{ $catLabel }}</td>
            <td>{{ $serialNumber }}</td>
        </tr>
    </tbody>
</table>

{{-- ── INFO GRID (4 cols, label row + value row) ── --}}
<table class="info-table">
    <tbody>
        <tr>
            <td class="lbl">Case ID</td>
            <td class="lbl">Location</td>
            <td class="lbl">Checked By</td>
            <td class="lbl">Checked Date</td>
        </tr>
        <tr>
            <td class="val">{{ $reportId }}</td>
            <td class="val">{{ $location }}</td>
            <td class="val">{{ $checkedBy }}</td>
            <td class="val">{{ $checkedDate }}</td>
        </tr>
        <tr>
            <td class="lbl">Departement</td>
            <td class="lbl">User</td>
            <td class="lbl">Email</td>
            <td class="lbl">Date</td>
        </tr>
        <tr>
            <td class="val">{{ $department }}</td>
            <td class="val">{{ $userName }}</td>
            <td class="val" style="word-break:break-all;">{{ $email }}</td>
            <td class="val">{{ $date }}</td>
        </tr>
    </tbody>
</table>

{{-- ── ISSUE + PHOTO ── --}}
<table class="issue-photo-table">
    <thead>
        <tr>
            <td class="issue-header" style="width:40%;">Issue</td>
            <td class="issue-header" style="width:60%;">Photo</td>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="issue-cell">{{ $issueDescription }}</td>
            <td class="photo-cell">
                @if($photo)
                    <img src="{{ $photo }}" class="photo-img" alt="Inspection Photo" />
                @else
                    <div style="height:100px;display:flex;align-items:center;justify-content:center;color:#94a3b8;font-size:8px;">Tidak ada foto</div>
                @endif
            </td>
        </tr>
    </tbody>
</table>

{{-- ── SOLUTION ── --}}
<table class="section-table">
    <tbody>
        <tr><td class="section-label">Solution</td></tr>
        <tr><td class="section-content">{{ $solution }}</td></tr>
    </tbody>
</table>

{{-- ── NOTE / REMARKS ── --}}
<table class="section-table">
    <tbody>
        <tr><td class="section-label">Note</td></tr>
        <tr><td class="section-content">{{ $remarks ?: '-' }}</td></tr>
    </tbody>
</table>

{{-- ── CONFIRMATION ── --}}
@php
    $renderSig = function($data) {
        if (!$data || !str_starts_with(trim($data), '[')) return null;
        try {
            $strokes = json_decode($data, true);
            if (empty($strokes)) return null;
            $path = ''; $minX = INF; $minY = INF; $maxX = -INF; $maxY = -INF;
            foreach ($strokes as $p) {
                $x = $p['x']; $y = $p['y'];
                $path .= ($p['type'] === 'start' ? "M $x $y " : "L $x $y ");
                $minX = min($minX,$x); $minY = min($minY,$y);
                $maxX = max($maxX,$x); $maxY = max($maxY,$y);
            }
            $w = ($maxX-$minX) ?: 1; $h = ($maxY-$minY) ?: 1;
            return ['path'=>$path,'viewBox'=>($minX-5).' '.($minY-5).' '.($w+10).' '.($h+10)];
        } catch (\Exception $e) { return null; }
    };
    $sigs = [
        ['data'=>$itSignature,      'name'=>$itStaff ?: $checkedBy],
        ['data'=>$checkedSignature,  'name'=>$checkedBy],
        ['data'=>$userSignature,     'name'=>$userName],
        ['data'=>$leaderSignature,   'name'=>$deptHead],
    ];
@endphp

<table class="confirm-table">
    <tbody>
        <tr>
            <td class="confirm-header" colspan="4">Confirmation</td>
        </tr>
        <tr>
            <td class="confirm-col-head">IT</td>
            <td class="confirm-col-head">Checked</td>
            <td class="confirm-col-head">User</td>
            <td class="confirm-col-head">Leader / Head Dept.</td>
        </tr>
        <tr>
            @foreach($sigs as $sig)
            <td class="confirm-sig-cell">
                @if($sig['data'])
                    @php $s = $renderSig($sig['data']); @endphp
                    @if($s)
                        <svg viewBox="{{ $s['viewBox'] }}" preserveAspectRatio="xMidYMid meet" class="sig-img">
                            <path d="{{ $s['path'] }}" fill="none" stroke="#003628" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    @else
                        <img src="{{ $sig['data'] }}" class="sig-img" alt=""/>
                    @endif
                @endif
            </td>
            @endforeach
        </tr>
        <tr class="confirm-name-row">
            @foreach($sigs as $sig)
            <td>{{ $sig['name'] }}</td>
            @endforeach
        </tr>
    </tbody>
</table>

</body>
</html>
