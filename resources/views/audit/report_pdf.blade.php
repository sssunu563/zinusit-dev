<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Stock Opname - {{ $session->name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 8.5px;
            color: #1e293b;
            background: #ffffff;
            line-height: 1.45;
        }

        /* ── HEADER ─────────────────────────────────────────────── */
        .header {
            background-color: #003628;
            color: #ffffff;
            padding: 14px 20px 12px;
            margin-bottom: 0;
        }

        .header-inner {
            width: 100%;
        }

        .header-left {
            width: 60%;
        }

        .header-right {
            width: 40%;
            text-align: right;
            vertical-align: top;
        }

        .co-name {
            font-size: 15px;
            font-weight: 700;
            color: #ffffff;
            letter-spacing: 0.5px;
        }

        .co-sub {
            font-size: 8px;
            color: #6ee7b7;
            margin-top: 2px;
        }

        .rpt-title {
            font-size: 13px;
            font-weight: 700;
            color: #ffffff;
        }

        .rpt-sub {
            font-size: 8px;
            color: #6ee7b7;
            margin-top: 2px;
        }

        .header-divider {
            border: none;
            border-top: 1px solid rgba(255,255,255,0.18);
            margin: 10px 0 8px;
        }

        .meta-label {
            font-size: 6.5px;
            color: #6ee7b7;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .meta-value {
            font-size: 8.5px;
            font-weight: 600;
            color: #ffffff;
            margin-top: 1px;
        }

        /* ── STATUS BADGE ───────────────────────────────────────── */
        .badge-status {
            display: inline-block;
            padding: 1px 7px;
            border-radius: 8px;
            font-size: 7.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .badge-completed { background: #d1fae5; color: #065f46; }
        .badge-open      { background: #fef3c7; color: #92400e; }

        /* ── SECTION WRAPPER ────────────────────────────────────── */
        .section {
            padding: 10px 20px;
        }

        .section-title {
            font-size: 8px;
            font-weight: 700;
            color: #003628;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1.5px solid #003628;
            padding-bottom: 3px;
            margin-bottom: 8px;
        }

        /* ── STAT CARDS (table-cell based) ─────────────────────── */
        .stats-wrap {
            width: 100%;
            border-spacing: 5px 0;
            border-collapse: separate;
        }

        .stat-card {
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            padding: 8px 10px;
            text-align: center;
            vertical-align: middle;
        }

        .stat-num {
            font-size: 20px;
            font-weight: 700;
            line-height: 1;
        }

        .stat-lbl {
            font-size: 6.5px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-top: 2px;
        }

        .stat-pct {
            font-size: 7.5px;
            color: #64748b;
            font-weight: 600;
            margin-top: 1px;
        }

        .c-total      { background: #f0fdf4; border-color: #86efac; }
        .c-match      { background: #f0fdf4; border-color: #22c55e; }
        .c-mismatch   { background: #fffbeb; border-color: #f59e0b; }
        .c-missing    { background: #fff1f2; border-color: #f43f5e; }
        .c-completion { background: #eff6ff; border-color: #60a5fa; }

        .n-total      { color: #003628; }
        .n-match      { color: #15803d; }
        .n-mismatch   { color: #b45309; }
        .n-missing    { color: #be123c; }
        .n-completion { color: #1d4ed8; }

        /* ── DEPT TABLE ─────────────────────────────────────────── */
        .dept-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8px;
        }

        .dept-table th {
            background-color: #003628;
            color: #ffffff;
            padding: 5px 8px;
            font-size: 7.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            text-align: left;
        }

        .dept-table td {
            padding: 4px 8px;
            border-bottom: 1px solid #f1f5f9;
            color: #374151;
        }

        .dept-table tr.even td { background: #f8fafc; }

        /* ── DETAIL TABLE ───────────────────────────────────────── */
        .detail-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7.5px;
        }

        .detail-table th {
            background-color: #003628;
            color: #ffffff;
            padding: 5px 5px;
            font-size: 7px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            text-align: left;
            white-space: nowrap;
        }

        .detail-table td {
            padding: 3.5px 5px;
            border-bottom: 1px solid #e8ecf0;
            vertical-align: top;
            color: #374151;
        }

        .detail-table td.ctr { text-align: center; }

        .row-match    td { background: #f0fdf4; }
        .row-mismatch td { background: #fffbeb; }
        .row-missing  td { background: #fff1f2; }

        .badge {
            display: inline-block;
            padding: 1.5px 5px;
            border-radius: 8px;
            font-size: 6.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            white-space: nowrap;
        }

        .b-match    { background: #dcfce7; color: #166534; }
        .b-mismatch { background: #fef9c3; color: #854d0e; }
        .b-missing  { background: #ffe4e6; color: #9f1239; }

        .txt-muted { color: #94a3b8; }
        .txt-diff  { color: #b45309; font-size: 6.5px; }
        .txt-bold  { font-weight: 700; }

        /* ── FOOTER ─────────────────────────────────────────────── */
        .footer {
            position: fixed;
            bottom: 8px;
            left: 0;
            right: 0;
            padding: 5px 20px;
            border-top: 1px solid #e2e8f0;
        }

        .footer-inner {
            width: 100%;
        }

        .footer-left  { font-size: 6.5px; color: #94a3b8; font-style: italic; }
        .footer-right { font-size: 6.5px; color: #94a3b8; text-align: right; }

        /* ── PAGE BREAK ─────────────────────────────────────────── */
        .page-break { page-break-after: always; }
    </style>
</head>
<body>

{{-- ════════════════════ HEADER ════════════════════ --}}
<div class="header">
    <table class="header-inner" cellpadding="0" cellspacing="0">
        <tr>
            <td class="header-left">
                <div class="co-name">ZINUSIT</div>
                <div class="co-sub">IT Asset Management System</div>
            </td>
            <td class="header-right">
                <div class="rpt-title">Laporan Stock Opname</div>
                <div class="rpt-sub">Audit Report</div>
            </td>
        </tr>
    </table>

    <hr class="header-divider">

    <table style="width:100%;" cellpadding="0" cellspacing="0">
        <tr>
            <td style="width:28%; padding-right:10px;">
                <div class="meta-label">Nama Sesi</div>
                <div class="meta-value">{{ $session->name }}</div>
            </td>
            <td style="width:16%; padding-right:10px;">
                <div class="meta-label">Status</div>
                <div style="margin-top:2px;">
                    <span class="badge-status {{ $session->status === 'Completed' ? 'badge-completed' : 'badge-open' }}">
                        {{ $session->status }}
                    </span>
                </div>
            </td>
            <td style="width:20%; padding-right:10px;">
                <div class="meta-label">Dibuat Oleh</div>
                <div class="meta-value">{{ $session->creator?->name ?? 'System' }}</div>
            </td>
            <td style="width:18%; padding-right:10px;">
                <div class="meta-label">Tanggal Mulai</div>
                <div class="meta-value">{{ $session->created_at->format('d M Y') }}</div>
            </td>
            @if($session->completed_at)
            <td style="width:18%;">
                <div class="meta-label">Tanggal Selesai</div>
                <div class="meta-value">{{ $session->completed_at->format('d M Y') }}</div>
            </td>
            @endif
        </tr>
    </table>
</div>

@php
    $items         = $session->items;
    $total         = $items->count();
    $matchCount    = $items->where('status', 'Match')->count();
    $mismatchCount = $items->where('status', 'Mismatch')->count();
    $missingCount  = $items->where('status', 'Missing')->count();
    $verifiedCount = $items->whereNotNull('verified_at')->count();
    $completionPct = $total > 0 ? round(($verifiedCount / $total) * 100, 1) : 0;
    $byDept        = $items->groupBy(fn($i) => $i->expected_department ?: 'Tidak Ada Departemen')->sortKeys();
@endphp

{{-- ════════════════════ STATISTICS ════════════════════ --}}
<div class="section">
    <div class="section-title">Ringkasan Statistik</div>
    <table class="stats-wrap" cellpadding="0" cellspacing="5">
        <tr>
            <td class="stat-card c-total">
                <div class="stat-num n-total">{{ $total }}</div>
                <div class="stat-lbl">Total Aset</div>
            </td>
            <td class="stat-card c-match">
                <div class="stat-num n-match">{{ $matchCount }}</div>
                <div class="stat-lbl">Match</div>
                <div class="stat-pct">{{ $total > 0 ? round($matchCount / $total * 100, 1) : 0 }}%</div>
            </td>
            <td class="stat-card c-mismatch">
                <div class="stat-num n-mismatch">{{ $mismatchCount }}</div>
                <div class="stat-lbl">Mismatch</div>
                <div class="stat-pct">{{ $total > 0 ? round($mismatchCount / $total * 100, 1) : 0 }}%</div>
            </td>
            <td class="stat-card c-missing">
                <div class="stat-num n-missing">{{ $missingCount }}</div>
                <div class="stat-lbl">Missing</div>
                <div class="stat-pct">{{ $total > 0 ? round($missingCount / $total * 100, 1) : 0 }}%</div>
            </td>
            <td class="stat-card c-completion">
                <div class="stat-num n-completion">{{ $completionPct }}%</div>
                <div class="stat-lbl">Completion</div>
                <div class="stat-pct">{{ $verifiedCount }} / {{ $total }} aset</div>
            </td>
        </tr>
    </table>
</div>

{{-- ════════════════════ PER-DEPARTMENT ════════════════════ --}}
@if($byDept->isNotEmpty())
<div class="section" style="padding-top:4px;">
    <div class="section-title">Ringkasan Per Departemen</div>
    <table class="dept-table" cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                <th>Departemen</th>
                <th style="text-align:center; width:80px;">Total Aset</th>
                <th style="text-align:center; width:65px;">Match</th>
                <th style="text-align:center; width:65px;">Mismatch</th>
                <th style="text-align:center; width:65px;">Missing</th>
                <th style="text-align:center; width:75px;">Completion</th>
            </tr>
        </thead>
        <tbody>
            @foreach($byDept as $dept => $deptItems)
            @php
                $dTotal    = $deptItems->count();
                $dMatch    = $deptItems->where('status', 'Match')->count();
                $dMismatch = $deptItems->where('status', 'Mismatch')->count();
                $dMissing  = $deptItems->where('status', 'Missing')->count();
                $dVerified = $deptItems->whereNotNull('verified_at')->count();
                $dPct      = $dTotal > 0 ? round($dVerified / $dTotal * 100, 1) : 0;
                $isEven    = $loop->even;
            @endphp
            <tr class="{{ $isEven ? 'even' : '' }}">
                <td class="txt-bold">{{ $dept }}</td>
                <td style="text-align:center;">{{ $dTotal }}</td>
                <td style="text-align:center; color:#15803d; font-weight:700;">{{ $dMatch }}</td>
                <td style="text-align:center; color:#b45309; font-weight:700;">{{ $dMismatch }}</td>
                <td style="text-align:center; color:#be123c; font-weight:700;">{{ $dMissing }}</td>
                <td style="text-align:center;">{{ $dPct }}%</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- ════════════════════ DETAIL TABLE ════════════════════ --}}
<div class="section" style="padding-top:4px;">
    <div class="section-title">
        Detail Hasil Audit
        <span style="font-weight:400; font-size:7.5px; text-transform:none; letter-spacing:0; color:#475569;">
            — {{ $total }} aset total &nbsp;|&nbsp;
            {{ $verifiedCount }} terverifikasi &nbsp;|&nbsp;
            {{ $missingCount }} missing
        </span>
    </div>
    <table class="detail-table" cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                <th style="text-align:center; width:18px;">#</th>
                <th style="width:52px;">Asset Tag</th>
                <th style="width:90px;">Nama Aset</th>
                <th style="width:55px;">Dept.</th>
                <th style="width:62px;">Lok. Expected</th>
                <th style="width:62px;">Lok. Fisik</th>
                <th style="width:62px;">User Expected</th>
                <th style="width:62px;">User Fisik</th>
                <th style="text-align:center; width:42px;">Status</th>
                <th style="width:52px;">Verifikator</th>
                <th style="width:52px;">Tgl Verif.</th>
            </tr>
        </thead>
        <tbody>
            @php $rowNo = 0; @endphp
            @foreach($items->sortBy('expected_location') as $item)
            @php
                $rowNo++;
                $rowClass  = match($item->status) { 'Match' => 'row-match', 'Mismatch' => 'row-mismatch', default => 'row-missing' };
                $badgeClass = match($item->status) { 'Match' => 'b-match', 'Mismatch' => 'b-mismatch', default => 'b-missing' };
                $locDiff  = $item->physical_location && $item->physical_location !== $item->expected_location;
                $userDiff = $item->physical_user && $item->physical_user !== $item->expected_user;
            @endphp
            <tr class="{{ $rowClass }}">
                <td class="ctr txt-muted">{{ $rowNo }}</td>
                <td class="txt-bold" style="white-space:nowrap;">{{ $item->asset_tag ?: '-' }}</td>
                <td>{{ $item->asset_name ?: '-' }}</td>
                <td>{{ $item->expected_department ?: '-' }}</td>
                <td>{{ $item->expected_location ?: '-' }}</td>
                <td>
                    {{ $item->physical_location ?: '-' }}
                    @if($locDiff)<div class="txt-diff">≠ Berbeda</div>@endif
                </td>
                <td>{{ $item->expected_user ?: '-' }}</td>
                <td>
                    {{ $item->physical_user ?: '-' }}
                    @if($userDiff)<div class="txt-diff">≠ Berbeda</div>@endif
                </td>
                <td class="ctr">
                    <span class="badge {{ $badgeClass }}">{{ $item->status }}</span>
                </td>
                <td>{{ $item->verifier?->name ?: ($item->verified_at ? 'System' : '-') }}</td>
                <td style="white-space:nowrap;">{{ $item->verified_at ? $item->verified_at->format('d/m/y H:i') : '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

{{-- ════════════════════ FOOTER ════════════════════ --}}
<div class="footer">
    <table class="footer-inner" cellpadding="0" cellspacing="0">
        <tr>
            <td class="footer-left">
                Dokumen ini digenerate otomatis oleh ZINUSIT Asset Management System. Bersifat konfidensial.
            </td>
            <td class="footer-right">
                Dicetak: {{ now()->format('d M Y, H:i') }} WIB
            </td>
        </tr>
    </table>
</div>

</body>
</html>
