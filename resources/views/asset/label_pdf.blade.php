<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: 40mm 25mm; margin: 0; }
        * { box-sizing: border-box; }
        html, body { width: 40mm; height: 25mm; margin: 0; padding: 0; overflow: hidden; }
        body { font-family: Arial, sans-serif; }
        .label { display: flex; align-items: center; gap: 2mm; width: 40mm; height: 25mm; padding: 1.5mm; overflow: hidden; }
        .qr { width: 17mm; height: 17mm; flex: 0 0 17mm; }
        .qr canvas { width: 17mm; height: 17mm; }
        .info { min-width: 0; overflow: hidden; }
        p { margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .name { font-size: 5.5pt; font-weight: 700; text-transform: uppercase; }
        .tag { margin-top: .5mm; font: 6pt 'Courier New', monospace; }
        .serial, .meta { margin-top: .3mm; font-size: 4.5pt; color: #333; }
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
    <script>QRCode.toCanvas(document.getElementById('qr'), @json($publicUrl), { width: 76, margin: 0 });</script>
</body>
</html>