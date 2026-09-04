<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    @php
        // Size presets (mm) — can be passed via ?size=xs|sm|md|lg|xl
        $sizes = [
            'xs' => ['w' => 40,  'h' => 25],
            'sm' => ['w' => 50,  'h' => 30],
            'md' => ['w' => 62,  'h' => 29],
            'lg' => ['w' => 70,  'h' => 40],
            'xl' => ['w' => 100, 'h' => 50],
        ];
        $sizeKey = request('size', 'xs');
        $sz = $sizes[$sizeKey] ?? $sizes['xs'];
        $w = $sz['w']; $h = $sz['h'];
        $qrMm = round($h * 0.72);
    @endphp
    <style>
        @php
            $pageMargin = 5;
            $columns = max(1, floor((210 - ($pageMargin * 2)) / $w));
            $rows = max(1, floor((297 - ($pageMargin * 2)) / $h));
        @endphp
        @page { size: A4 portrait; margin: 0; }
        * { box-sizing: border-box; }
        html, body { width: 210mm; min-height: 297mm; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; }
        .sheet { display: grid; grid-template-columns: repeat({{ $columns }}, {{ $w }}mm); grid-auto-rows: {{ $h }}mm; align-content: start; width: 210mm; min-height: 297mm; padding: {{ $pageMargin }}mm; }
        .label { display: flex; align-items: center; gap: 2mm; width: {{ $w }}mm; height: {{ $h }}mm; padding: 1.5mm; overflow: hidden; page-break-inside: avoid; }
        .qr { width: {{ $qrMm }}mm; height: {{ $qrMm }}mm; flex: 0 0 {{ $qrMm }}mm; }
        .qr canvas { width: {{ $qrMm }}mm; height: {{ $qrMm }}mm; }
        .info { min-width: 0; overflow: hidden; }
        p { margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .name { font-size: 5.5pt; font-weight: 700; text-transform: uppercase; }
        .tag { margin-top: .5mm; font: 6pt 'Courier New', monospace; }
        .serial, .meta { margin-top: .3mm; font-size: 4.5pt; color: #333; }
    </style>
</head>
<body>
<div class="sheet">
@foreach($assets as $index => $asset)
    <div class="label">
        <div class="qr"><canvas id="qr-{{ $index }}"></canvas></div>
        <div class="info">
            <p class="name">{{ $asset['name'] }}</p>
            <p class="tag">{{ $asset['asset_tag'] }}</p>
            @if($asset['serial'])<p class="serial">SN: {{ $asset['serial'] }}</p>@endif
            @if($asset['location'])<p class="meta">{{ $asset['location'] }}</p>@endif
        </div>
    </div>
@endforeach
</div>
    <script src="https://cdn.jsdelivr.net/npm/qrcode/build/qrcode.min.js"></script>
    <script>
        const assets = @json($assets);
        const qrPx = Math.round({{ $qrMm }} * 3.78);
        assets.forEach((asset, index) => QRCode.toCanvas(document.getElementById(`qr-${index}`), asset.public_url, { width: qrPx, margin: 0 }));
    </script>
</body>
</html>