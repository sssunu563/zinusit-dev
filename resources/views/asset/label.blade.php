<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Label — {{ $asset['name'] ?? $asset['asset_tag'] ?? 'Asset' }}</title>
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
        * { margin: 0; padding: 0; box-sizing: border-box; }

        html, body {
            width: {{ $w }}mm;
            height: {{ $h }}mm;
            background: white;
            font-family: 'Arial', sans-serif;
        }

        .label {
            width: {{ $w }}mm;
            height: {{ $h }}mm;
            display: flex;
            align-items: center;
            gap: 2mm;
            padding: 1.5mm;
            background: white;
            overflow: hidden;
        }

        .qr-wrap {
            flex-shrink: 0;
            width: {{ $qrMm }}mm;
            height: {{ $qrMm }}mm;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .qr-wrap canvas { display: block; }

        .info {
            flex: 1;
            min-width: 0;
            overflow: hidden;
        }

        .name {
            font-size: 5.5pt;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: -0.03em;
            line-height: 1.1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .tag {
            font-size: 6pt;
            font-weight: 700;
            font-family: 'Courier New', monospace;
            margin-top: 0.5mm;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .serial, .meta {
            font-size: 4.5pt;
            color: #333;
            margin-top: 0.3mm;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .serial { font-style: italic; font-family: 'Courier New', monospace; }

        @media print {
            @page {
                margin: 0;
                size: {{ $w }}mm {{ $h }}mm;
            }
            html, body {
                width: {{ $w }}mm;
                height: {{ $h }}mm;
            }
        }
    </style>
</head>
<body>
<div class="label">
    <div class="qr-wrap">
        <canvas id="qr-canvas"></canvas>
    </div>
    <div class="info">
        @if(!empty($asset['name']))
            <p class="name">{{ $asset['name'] }}</p>
        @endif
        @if(!empty($asset['asset_tag']))
            <p class="tag">{{ $asset['asset_tag'] }}</p>
        @endif
        @if(!empty($asset['serial']))
            <p class="serial">SN: {{ $asset['serial'] }}</p>
        @endif
        @if(!empty($asset['location'] ?? ''))
            <p class="meta">{{ $asset['location'] ?? '' }}</p>
        @endif
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/qrcode/build/qrcode.min.js"></script>
<script>
    var url  = {{ Js::from($publicUrl) }};
    var qrPx = Math.round({{ $qrMm }} * 3.78);
    QRCode.toCanvas(document.getElementById('qr-canvas'), url, {
        width: qrPx,
        margin: 0,
        color: { dark: '#000000', light: '#ffffff' }
    }, function (err) {
        if (!err) { window.print(); }
    });
</script>
</body>
</html>
