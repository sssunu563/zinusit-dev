<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    @php
        $w = $w ?? 40;
        $h = $h ?? 25;
        $qrMm = round($h * 0.72);
    @endphp
    <style>
        @page { size: {{ $w }}mm {{ $h }}mm; margin: 0; }
        * { box-sizing: border-box; }
        html, body { width: {{ $w }}mm; height: {{ $h }}mm; margin: 0; padding: 0; overflow: hidden; background: #fff; }
        body { font-family: Arial, sans-serif; }
        .label {
            display: grid;
            grid-template-columns: {{ $qrMm }}mm 1fr;
            align-items: center;
            gap: 2mm;
            width: {{ $w }}mm;
            height: {{ $h }}mm;
            padding: 1.5mm;
            overflow: hidden;
            box-sizing: border-box;
        }
        .qr {
            width: {{ $qrMm }}mm;
            height: {{ $qrMm }}mm;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .qr canvas { width: 100% !important; height: 100% !important; }
        .info {
            min-width: 0;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: center;
            height: 100%;
        }
        p { margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; line-height: 1.15; }
        .name { font-size: {{ $h <= 25 ? '5.5pt' : ($h <= 30 ? '6.5pt' : '8pt') }}; font-weight: 700; text-transform: uppercase; }
        .tag { margin-top: .4mm; font: {{ $h <= 25 ? '6pt' : ($h <= 30 ? '7pt' : '8.5pt') }} 'Courier New', monospace; font-weight: 700; }
        .serial, .meta { margin-top: .3mm; font-size: {{ $h <= 25 ? '4.5pt' : ($h <= 30 ? '5pt' : '6.5pt') }}; color: #333; }
    </style>
</head>
<body>
    <div class="label">
        <div class="qr"><canvas id="qr"></canvas></div>
        <div class="info">
            <p class="name">{{ $asset['name'] }}</p>
            <p class="tag">{{ $asset['asset_tag'] }}</p>
            @if($asset['serial'])<p class="serial">SN: {{ $asset['serial'] }}</p>@endif
            @if($asset['location'])<p class="meta">{{ $asset['location'] }}</p>@endif
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/qrcode/build/qrcode.min.js"></script>
    <script>QRCode.toCanvas(document.getElementById('qr'), @json($publicUrl), { width: 80, margin: 0 });</script>
</body>
</html>