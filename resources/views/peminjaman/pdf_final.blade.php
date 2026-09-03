<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $docId }}</title>
    <style>
        @page { size: A4 portrait; margin: 10mm 15mm; }
        * { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body { margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; background: #fff; color: #111827; }
        .document { width: 100%; font-size: 9px; color: #111827; background: #fff; line-height: 1.35; }

        table { width: 100%; border-collapse: collapse; border-spacing: 0; table-layout: fixed; border: 1px solid #cbd5e1; }
        td, th { border: 1px solid #cbd5e1; padding: 4px 8px; vertical-align: top; }

        .header-table { margin-bottom: 6px; }
        .header-table td { vertical-align: middle; padding: 8px 10px; }
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
        .items-table thead th { background: #f8fafc; text-align: center; font-weight: 700; color: #475569; padding: 6px 8px; }
        .col-no { width: 6%; }
        .col-name { width: 26%; }
        .col-type { width: 22%; }
        .col-qty { width: 6%; }
        .col-sn { width: 22%; }
        .col-asset { width: 18%; }

        .recipient-note { margin: 8px 0 4px; font-weight: 600; font-size: 8.5px; display: block; width: 100%; border-bottom: 1px solid #e2e8f0; padding-bottom: 2px; }
        .center { text-align: center; }

        .agreement-box { margin-top: 6px; border: 1px solid #cbd5e1; background: #f8fafc; padding: 8px 12px; }
        .agreement-title { margin: 0 0 4px; font-weight: 700; text-transform: uppercase; font-size: 8px; color: #334155; }
        .agreement-line { display: table; width: 100%; margin-bottom: 2px; font-size: 8.5px; line-height: 1.3; }
        .point-num { display: table-cell; width: 22px; font-weight: 700; color: #003628; vertical-align: top; }
        .point-text { display: table-cell; vertical-align: top; }

        /* Signature — 2 columns only */
        .signature-table { margin-top: 8px; }
        .signature-title { background: #f8fafc; text-align: center; font-weight: 700; font-size: 8.5px; height: 18px; vertical-align: middle; }
        .signature-head { background: #ffffff; text-align: center; font-weight: 700; font-size: 8px; height: 18px; vertical-align: middle; color: #475569; }
        .signature-body { height: 88px; text-align: center; vertical-align: top; padding-top: 6px; }
        .signature-sign-box { display: flex; height: 42px; align-items: center; justify-content: center; margin-bottom: 4px; }
        .signature-image { max-width: 90%; max-height: 40px; object-fit: contain; }
        .signature-name { font-weight: 700; font-size: 9px; margin-top: 2px; border-bottom: 1px solid #cbd5e1; display: inline-block; padding: 0 8px; }
        .signature-time { font-size: 7px; font-weight: 500; color: #94a3b8; margin-top: 2px; }

        /* Photos row */
        .photo-row { margin-top: 6px; }
        .photo-cell { width: 50%; }
        .photo-label { font-size: 8px; font-weight: 700; color: #475569; margin-bottom: 3px; }
        .photo-box { display: flex; height: 110px; align-items: center; justify-content: center; background: #f8fafc; border: 1px solid #e2e8f0; }
        .photo { max-width: 100%; max-height: 100px; object-fit: contain; }
        .photo-meta { font-size: 7.5px; color: #94a3b8; margin-top: 2px; }

        .remark-table { margin-top: 6px; }
        .remark-title { font-weight: 700; color: #475569; font-size: 9px; margin-bottom: 4px; }
        .remark-content { font-size: 9px; line-height: 1.35; color: #1e293b; }
    </style>
</head>
<body>
<div class="document">

    {{-- Header --}}
    <table class="header-table"><tbody><tr>
        <td class="logo-cell">@if($logo)<img src="{{ $logo }}" class="logo" alt="Zinus" />@endif</td>
        <td class="title-cell">
            <div class="title-main">FORM PEMINJAMAN ASSET</div>
            <div class="title-sub">PT. Zinus Global Indonesia</div>
        </td>
        <td class="meta-cell">
            <div style="font-weight:700;color:#475569;">IT Dept.</div>
            <div style="margin-top:2px;">{{ $docId }}</div>
        </td>
    </tr></tbody></table>

    {{-- Document Info --}}
    <table class="info-table"><tbody>
        <tr>
            <td class="label">Doc ID</td><td class="value">{{ $docId }}</td>
            <td class="label">Location</td><td class="value">{{ $location }}</td>
        </tr>
        <tr>
            <td class="label">Tanggal Pinjam</td><td class="value">{{ $loanDate }}</td>
            <td class="label">Est. Kembali</td>
            <td class="value" style="{{ $isOverdue ? 'color:#dc2626;font-weight:700;' : '' }}">{{ $expectedReturnDate }}</td>
        </tr>
        <tr>
            <td class="label">Status</td>
            <td class="value" colspan="3">{{ $statusLabel }}</td>
        </tr>
    </tbody></table>

    {{-- Recipient Note --}}
    <div class="recipient-note">
        <span>
            @if($movementType === 'return')
                Aset di bawah ini telah dikembalikan oleh:
            @else
                Saya yang bertandatangan di bawah ini (Peminjam):
            @endif
        </span>
        <span style="float:right;color:#64748b;">{{ $createdDate }}</span>
    </div>

    {{-- Recipient Info --}}
    <table class="info-table"><tbody>
        <tr>
            <td class="label">Name</td><td class="value">{{ $userName }}</td>
            <td class="label">Company</td><td class="value">{{ $company }}</td>
        </tr>
        <tr>
            <td class="label">Phone Number</td><td class="value">{{ $phoneNumber }}</td>
            <td class="label">Department</td><td class="value">{{ $department }}</td>
        </tr>
        <tr>
            <td class="label">Email</td><td class="value">{{ $email }}</td>
            <td class="label">Position</td><td class="value">{{ $position }}</td>
        </tr>
    </tbody></table>

    {{-- Items --}}
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
            <tr>
                <td class="center">{{ $index + 1 }}</td>
                <td>{{ $item['nama'] }}</td>
                <td>{{ $item['type'] }}</td>
                <td class="center">{{ $item['jumlah'] }}</td>
                <td>{{ $item['serial_no'] }}</td>
                <td>{{ $item['asset'] }}</td>
            </tr>
        @endforeach
        @for($i = count($items); $i < 5; $i++)
            <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td></tr>
        @endfor
        </tbody>
    </table>

@php
    $renderSignature = function($signatureData) {
        if (!$signatureData || !str_starts_with($signatureData, '[')) return null;
        try {
            $strokes = json_decode($signatureData, true);
            if (empty($strokes)) return null;
            $path = ''; $minX = INF; $minY = INF; $maxX = -INF; $maxY = -INF;
            foreach ($strokes as $p) {
                $x = $p['x']; $y = $p['y'];
                $path .= ($p['type'] === 'start' ? "M $x $y " : "L $x $y ");
                $minX = min($minX, $x); $minY = min($minY, $y);
                $maxX = max($maxX, $x); $maxY = max($maxY, $y);
            }
            $w = ($maxX - $minX) ?: 1; $h = ($maxY - $minY) ?: 1;
            return ['path' => $path, 'viewBox' => ($minX-5).' '.($minY-5).' '.($w+10).' '.($h+10)];
        } catch (\Exception $e) { return null; }
    };
@endphp

    {{-- Signatures: 3 kolom sejajar — IT Drafter | Borrower | Catatan --}}
    <table class="signature-table" style="margin-top:8px; table-layout:fixed;">
        <tbody>
            <tr>
                <td class="signature-title" style="width:34%;">IT Drafter</td>
                <td class="signature-title" style="width:34%;">{{ $movementType === 'return' ? 'Returned By' : 'Borrower' }}</td>
                <td class="signature-title" style="width:32%; text-align:left; padding-left:8px;">Catatan :</td>
            </tr>
            <tr>
                {{-- IT Drafter signature --}}
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
                {{-- Borrower/Returned signature --}}
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
                {{-- Remark --}}
                <td style="vertical-align:top; padding:8px; font-size:9px; color:#334155;">
                    {{ $remark }}
                </td>
            </tr>
        </tbody>
    </table>

    {{-- Photos --}}
    <table class="photo-row" style="margin-top:6px;"><tbody><tr>
        <td class="photo-cell" style="padding:6px 8px;">
            <div class="photo-label">Foto Penyerahan :</div>
            <div class="photo-box">
                @if($photoLoan)
                    <img src="{{ $photoLoan }}" class="photo" alt="Foto Penyerahan" />
                @else
                    <span style="font-size:8px;color:#94a3b8;">Tidak ada foto</span>
                @endif
            </div>
            @if($photoLoanDate)<div class="photo-meta">{{ $photoLoanDate }}</div>@endif
        </td>
        <td class="photo-cell" style="padding:6px 8px;">
            <div class="photo-label">Foto Pengembalian :</div>
            <div class="photo-box">
                @if($photoReturn)
                    <img src="{{ $photoReturn }}" class="photo" alt="Foto Pengembalian" />
                @else
                    <span style="font-size:8px;color:#94a3b8;">Tidak ada foto</span>
                @endif
            </div>
            @if($photoReturnDate)<div class="photo-meta">{{ $photoReturnDate }}</div>@endif
        </td>
    </tr></tbody></table>

</div>
</body>
</html>
