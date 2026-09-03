<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Label — {{ $asset['name'] ?? $asset['asset_tag'] ?? 'Asset' }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        html, body {
            width: 40mm;
            height: 30mm;
            background: white;
            font-family: 'Arial', sans-serif;
        }

        .label {
            width: 40mm;
            height: 30mm;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1mm;
            background: white;
            color: black;
            overflow: hidden;
        }

        .qr-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .qr-wrap canvas,
        .qr-wrap img {
            display: block;
        }

        .info {
            width: 100%;
            text-align: center;
            margin-top: 0.8mm;
        }

        .name {
            font-size: 7pt;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: -0.03em;
            line-height: 1.1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            width: 100%;
        }

        .meta {
            display: flex;
            justify-content: center;
            gap: 1.5mm;
            margin-top: 0.4mm;
            flex-wrap: nowrap;
            overflow: hidden;
        }

        .tag {
            font-size: 6pt;
            font-weight: 700;
            white-space: nowrap;
        }

        .serial {
            font-size: 5.5pt;
            font-weight: 600;
            font-style: italic;
            opacity: 0.55;
            white-space: nowrap;
        }

        @media print {
            @page {
                margin: 0;
                size: 40mm 30mm;
            }
            html, body {
                width: 40mm;
                height: 30mm;
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
        <div class="meta">
            @if(!empty($asset['asset_tag']))
                <span class="tag">{{ $asset['asset_tag'] }}</span>
            @endif
            @if(!empty($asset['serial']))
                <span class="serial">{{ $asset['serial'] }}</span>
            @endif
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/qrcode/build/qrcode.min.js"></script>
<script>
    var url = {{ Js::from($publicUrl) }};
    QRCode.toCanvas(document.getElementById('qr-canvas'), url, {
        width: 76,
        margin: 0,
        color: { dark: '#000000', light: '#ffffff' }
    }, function (err) {
        if (!err) {
            window.print();
        }
    });
</script>
</body>
</html>
