<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asset Label - {{ $asset['asset_tag'] ?? 'Asset' }}</title>
    @php
        $initW = request('w', request('size') === 'sm' ? 50 : (request('size') === 'xs' ? 40 : (request('size') === 'md' ? 62 : (request('size') === 'lg' ? 70 : (request('size') === 'xl' ? 100 : 76)))));
        $initH = request('h', request('size') === 'sm' ? 30 : (request('size') === 'xs' ? 25 : (request('size') === 'md' ? 29 : (request('size') === 'lg' ? 40 : (request('size') === 'xl' ? 50 : 25)))));
    @endphp
    <style id="dynamic-print-style">
        @page {
            size: {{ $initW }}mm {{ $initH }}mm !important;
            margin: 0mm !important;
        }
    </style>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            min-height: 100%;
            background-color: #f1f5f9;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #0f172a;
            -webkit-font-smoothing: antialiased;
        }

        /* ── Top Header Toolbar ── */
        .screen-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            z-index: 100;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        }

        .header-main {
            height: 56px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
        }

        .header-brand {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .brand-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            object-fit: contain;
            display: block;
            flex-shrink: 0;
        }

        .brand-title {
            font-size: 13.5px;
            font-weight: 700;
            color: #0f172a;
        }

        .brand-sub {
            font-size: 11px;
            color: #64748b;
        }

        .screen-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn {
            padding: 0 14px;
            height: 34px;
            border: none;
            border-radius: 7px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.15s;
        }

        .btn-pdf {
            background: #ffffff;
            color: #0369a1;
            border: 1px solid #bae6fd;
        }

        .btn-pdf:hover {
            background: #f0f9ff;
            border-color: #38bdf8;
        }

        .btn-print {
            background: #003628;
            color: #ffffff;
            box-shadow: 0 1px 3px rgba(0, 54, 40, 0.25);
        }

        .btn-print:hover {
            background: #004d39;
        }

        /* ── Controls Sub-bar (Presets & Custom Size) ── */
        .controls-bar {
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 8px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .presets-row {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .section-tag {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #64748b;
            margin-right: 4px;
        }

        .size-pill {
            padding: 4px 10px;
            font-size: 11px;
            font-weight: 600;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            background: #ffffff;
            color: #475569;
            cursor: pointer;
            transition: all 0.12s;
        }

        .size-pill:hover {
            border-color: #003628;
            color: #003628;
            background: #f0fdf4;
        }

        .size-pill--active {
            background: #003628 !important;
            border-color: #003628 !important;
            color: #ffffff !important;
            box-shadow: 0 1px 2px rgba(0, 54, 40, 0.2);
        }

        /* Custom Dimension Inputs */
        .custom-inputs-box {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #ffffff;
            padding: 3px 8px;
            border-radius: 7px;
            border: 1px solid #cbd5e1;
        }

        .custom-label {
            font-size: 11px;
            font-weight: 600;
            color: #475569;
        }

        .dim-input-wrap {
            display: flex;
            align-items: center;
            gap: 3px;
        }

        .dim-input {
            width: 48px;
            height: 24px;
            padding: 0 4px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 700;
            text-align: center;
            color: #0f172a;
            outline: none;
        }

        .dim-input:focus {
            border-color: #003628;
            box-shadow: 0 0 0 1px #003628;
        }

        .dim-unit {
            font-size: 10.5px;
            color: #64748b;
            font-weight: 600;
        }

        .dim-x {
            color: #94a3b8;
            font-weight: 700;
            font-size: 11px;
        }

        /* ── Stage / Preview Viewport ── */
        .stage {
            padding-top: 130px;
            padding-bottom: 40px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 16px;
        }

        .dimension-badge {
            font-size: 11.5px;
            font-weight: 700;
            color: #003628;
            background: #e6f4ea;
            border: 1px solid #c6e7d2;
            padding: 4px 14px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .dimension-badge svg {
            color: #003628;
        }

        /* Card Outer Shell in Screen Mode */
        .label-outer-wrapper {
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            border: 1px dashed #94a3b8;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: width 0.15s, height 0.15s;
        }

        /* ════════════════════════════════════════════════
           THE STRICT LOCKED LABEL (Clean, Borderless Inside)
        ════════════════════════════════════════════════ */
        .label {
            display: grid;
            align-items: center;
            gap: 2.5mm;
            padding: 2mm 3mm;
            background: #ffffff;
            box-sizing: border-box;
            overflow: hidden;
            border-radius: 4px;
        }

        /* Column 1: QR Code (Completely borderless) */
        .label-qr {
            display: flex;
            align-items: center;
            justify-content: center;
            background: #ffffff;
            border: none !important;
            padding: 0 !important;
            flex-shrink: 0;
            overflow: hidden;
            box-sizing: border-box;
        }

        .label-qr img,
        .label-qr svg {
            width: 100% !important;
            height: 100% !important;
            display: block;
            object-fit: contain;
            image-rendering: -webkit-optimize-contrast;
            image-rendering: crisp-edges;
        }

        /* Column 2: Information (Refined Typography) */
        .label-info {
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-width: 0;
            overflow: hidden;
            gap: 0.8mm;
            height: 100%;
            box-sizing: border-box;
        }

        /* Asset Name: Bold, Clean */
        .label-name {
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: -0.01em;
            line-height: 1.15;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Asset Tag: Sleek pill without harsh borders */
        .label-tag {
            font-family: 'Courier New', Courier, monospace;
            font-weight: 700;
            color: #003628;
            background: #e8f5e9;
            padding: 0.8mm 2.2mm;
            display: inline-block;
            border: none !important;
            border-radius: 3px;
            width: fit-content;
            line-height: 1.1;
            letter-spacing: 0.2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Serial Number: Clean monospace */
        .label-serial {
            font-family: 'Courier New', Courier, monospace;
            color: #475569;
            font-weight: 500;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Status: Modern Clean Dot */
        .label-status {
            display: inline-flex;
            align-items: center;
            gap: 1.2mm;
            margin-top: 0.2mm;
            line-height: 1;
        }

        .status-dot {
            width: 4px;
            height: 4px;
            border-radius: 50%;
            flex-shrink: 0;
            background: #0f172a;
        }

        .status-text {
            font-weight: 700;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        /* Column 3: Zinus Logo */
        .label-logo {
            width: auto;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            flex-shrink: 0;
            padding-left: 1mm;
        }

        .label-logo img {
            max-width: 100%;
            object-fit: contain;
            display: block;
        }

        .stage-note {
            font-size: 11px;
            color: #64748b;
            text-align: center;
            max-width: 480px;
            line-height: 1.5;
        }

        /* ════════════════════════════════════════════════
           PRINT STYLES (Strict 1-Page Output)
        ════════════════════════════════════════════════ */
        @media print {
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            html, body {
                margin: 0 !important;
                padding: 0 !important;
                background: #ffffff !important;
                overflow: hidden !important;
            }

            .screen-header,
            .stage-badge,
            .dimension-badge,
            .stage-note {
                display: none !important;
            }

            .stage {
                padding: 0 !important;
                margin: 0 !important;
                min-height: auto !important;
                display: block !important;
                background: transparent !important;
            }

            .label-outer-wrapper {
                border: none !important;
                border-radius: 0 !important;
                box-shadow: none !important;
                background: transparent !important;
                padding: 0 !important;
                margin: 0 !important;
                page-break-after: avoid !important;
                break-after: avoid !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }

            .label {
                border: none !important;
                border-radius: 0 !important;
                padding: 2mm 3mm !important;
                box-sizing: border-box !important;
                page-break-after: avoid !important;
                break-after: avoid !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
                overflow: hidden !important;
            }

            .label-qr {
                border: none !important;
            }

            .label-tag {
                border: none !important;
            }
        }
    </style>
</head>
<body>

<!-- Fixed Top Navbar -->
<header class="screen-header">
    <div class="header-main">
        <div class="header-brand">
            <img src="{{ asset('apple-touch-icon.png') }}" alt="Zinus IT" class="brand-icon">
            <div>
                <div class="brand-title">Cetak Label Asset</div>
                <div class="brand-sub">{{ $asset['name'] ?? $asset['asset_tag'] }} &bull; {{ $asset['asset_tag'] }}</div>
            </div>
        </div>

        <div class="screen-actions">
            <a id="btn-pdf-link" href="?format=pdf&w={{ $initW }}&h={{ $initH }}" class="btn btn-pdf" download="label-{{ $asset['asset_tag'] ?? 'asset' }}.pdf" title="Unduh file PDF dengan ukuran yang aktif">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                Unduh PDF
            </a>

            <button id="print-btn" onclick="safePrint()" class="btn btn-print">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 6 2 18 2 18 9"></polyline>
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                    <rect x="6" y="14" width="12" height="8"></rect>
                </svg>
                Cetak Label
            </button>
        </div>
    </div>

    <!-- Controls Row: Presets & Custom Size Inputs -->
    <div class="controls-bar">
        <div class="presets-row">
            <span class="section-tag">Pilih Ukuran:</span>
            <button type="button" class="size-pill" onclick="applyPreset(76, 25, this)">3" × 1" (76 × 25 mm)</button>
            <button type="button" class="size-pill" onclick="applyPreset(100, 50, this)">100 × 50 mm</button>
        </div>

        <!-- Custom Size Inputs -->
        <div class="custom-inputs-box">
            <span class="custom-label">📐 Custom Ukuran:</span>
            <div class="dim-input-wrap">
                <input type="number" id="input-w" class="dim-input" value="{{ $initW }}" min="25" max="250" oninput="onCustomDimChange()" />
                <span class="dim-unit">mm</span>
            </div>
            <span class="dim-x">×</span>
            <div class="dim-input-wrap">
                <input type="number" id="input-h" class="dim-input" value="{{ $initH }}" min="15" max="200" oninput="onCustomDimChange()" />
                <span class="dim-unit">mm</span>
            </div>
        </div>
    </div>
</header>

<!-- Center Stage Area -->
<div class="stage">
    <div id="dim-badge" class="dimension-badge">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="3" width="18" height="18" rx="2"></rect>
            <path d="M3 9h18M9 21V9"></path>
        </svg>
        <span id="dim-badge-text">Ukuran Nyata: {{ $initW }} mm × {{ $initH }} mm (100% Terkunci)</span>
    </div>

    <!-- The Clean Borderless Inner Card -->
    <div id="label-wrapper" class="label-outer-wrapper">
        <div id="the-label" class="label">
            <!-- Left: QR Code (Vector SVG / Fallback) -->
            <div id="label-qr" class="label-qr">
                @if (!empty($svgQr))
                    {!! $svgQr !!}
                @else
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=400x400&data={{ urlencode($publicUrl) }}" alt="QR Code">
                @endif
            </div>

            <!-- Middle: Asset Info -->
            <div class="label-info">
                <div id="txt-name" class="label-name">{{ $asset['name'] ?? $asset['asset_tag'] ?? 'Asset' }}</div>
                @if (!empty($asset['asset_tag']))
                    <div id="txt-tag" class="label-tag">{{ $asset['asset_tag'] }}</div>
                @endif
                @if (!empty($asset['serial']))
                    <div id="txt-serial" class="label-serial">Serial Number: {{ $asset['serial'] }}</div>
                @endif
                @if (!empty($asset['status']))
                    <div id="txt-status" class="label-status">
                        <span class="status-dot"></span>
                        <span class="status-text">{{ $asset['status'] }}</span>
                    </div>
                @endif
            </div>

            <!-- Right: Zinus Logo (Base64 Inline / Fallback) -->
            <div id="label-logo" class="label-logo">
                <img id="logo-img" src="{{ !empty($logoBase64) ? $logoBase64 : asset('form-logo.png') }}" alt="Zinus Logo">
            </div>
        </div>
    </div>

    <div class="stage-note">
        💡 <strong>Keterangan:</strong> Ukuran label otomatis menyesuaikan live di layar dan pada hasil cetak printer. Pada jendela print, pastikan <em>Margins</em> diset ke <strong>None</strong>.
    </div>
</div>

<script>
    let currentW = {{ $initW }};
    let currentH = {{ $initH }};

    function updateLabelDimensions(w, h) {
        currentW = w;
        currentH = h;

        // Calculate proportional sizes
        const qrSizeMm = Math.round(h * 0.72);
        const logoHeightMm = Math.max(6, Math.min(13, Math.round(h * 0.32)));
        
        // Font sizes
        const namePt = h <= 25 ? '9pt' : (h <= 30 ? '10pt' : (h <= 40 ? '11.5pt' : '14pt'));
        const tagPt  = h <= 25 ? '7pt' : (h <= 30 ? '7.5pt' : (h <= 40 ? '8.5pt' : '10.5pt'));
        const metaPt = h <= 25 ? '5.5pt' : (h <= 30 ? '6pt' : (h <= 40 ? '7pt' : '8.5pt'));

        // 1. Update DOM Card dimensions
        const wrapper = document.getElementById('label-wrapper');
        const card = document.getElementById('the-label');
        const qr = document.getElementById('label-qr');
        const logoImg = document.getElementById('logo-img');
        const badgeText = document.getElementById('dim-badge-text');

        wrapper.style.width = w + 'mm';
        wrapper.style.height = h + 'mm';
        card.style.width = w + 'mm';
        card.style.height = h + 'mm';
        card.style.gridTemplateColumns = `${qrSizeMm}mm 1fr auto`;

        qr.style.width = qrSizeMm + 'mm';
        qr.style.height = qrSizeMm + 'mm';
        logoImg.style.maxHeight = logoHeightMm + 'mm';

        // Update Fonts
        const nameEl = document.getElementById('txt-name');
        if (nameEl) nameEl.style.fontSize = namePt;
        const tagEl = document.getElementById('txt-tag');
        if (tagEl) tagEl.style.fontSize = tagPt;
        const serialEl = document.getElementById('txt-serial');
        if (serialEl) serialEl.style.fontSize = metaPt;
        const statusEl = document.getElementById('txt-status');
        if (statusEl) statusEl.style.fontSize = metaPt;

        // Badge update
        badgeText.textContent = `Ukuran Nyata: ${w} mm × ${h} mm (100% Terkunci)`;

        // 2. Update Dynamic Print CSS (@page)
        const printStyle = document.getElementById('dynamic-print-style');
        printStyle.textContent = `
            @page {
                size: ${w}mm ${h}mm !important;
                margin: 0mm !important;
            }
            @media print {
                html, body {
                    width: ${w}mm !important;
                    height: ${h}mm !important;
                    max-width: ${w}mm !important;
                    max-height: ${h}mm !important;
                }
                .label-outer-wrapper, .label {
                    width: ${w}mm !important;
                    height: ${h}mm !important;
                    max-width: ${w}mm !important;
                    max-height: ${h}mm !important;
                }
            }
        `;

        // 3. Update PDF download link
        const pdfLink = document.getElementById('btn-pdf-link');
        pdfLink.href = `?format=pdf&w=${w}&h=${h}`;
    }

    function applyPreset(w, h, btnEl) {
        document.querySelectorAll('.size-pill').forEach(el => el.classList.remove('size-pill--active'));
        if (btnEl) btnEl.classList.add('size-pill--active');

        document.getElementById('input-w').value = w;
        document.getElementById('input-h').value = h;

        updateLabelDimensions(w, h);
    }

    function onCustomDimChange() {
        const w = parseInt(document.getElementById('input-w').value, 10);
        const h = parseInt(document.getElementById('input-h').value, 10);

        if (!isNaN(w) && !isNaN(h) && w >= 20 && h >= 15) {
            // Check if matches preset
            document.querySelectorAll('.size-pill').forEach(el => el.classList.remove('size-pill--active'));
            updateLabelDimensions(w, h);
        }
    }

    // Initialize active preset button on page load
    window.addEventListener('DOMContentLoaded', () => {
        let matched = false;
        document.querySelectorAll('.size-pill').forEach(btn => {
            const clickAttr = btn.getAttribute('onclick');
            if (clickAttr && clickAttr.includes(`applyPreset(${currentW}, ${currentH}`)) {
                btn.classList.add('size-pill--active');
                matched = true;
            }
        });
        updateLabelDimensions(currentW, currentH);
    });

    // Safe print function to prevent freeze
    let isPrinting = false;
    function safePrint() {
        if (isPrinting) {
            console.log('Print already in progress, skipping...');
            return;
        }
        
        isPrinting = true;
        const printBtn = document.getElementById('print-btn');
        if (printBtn) {
            printBtn.disabled = true;
            printBtn.style.opacity = '0.5';
        }
        
        try {
            window.print();
        } catch (e) {
            console.error('Print error:', e);
        }
        
        // Reset after print dialog closes (use setTimeout as fallback)
        setTimeout(() => {
            isPrinting = false;
            if (printBtn) {
                printBtn.disabled = false;
                printBtn.style.opacity = '1';
            }
        }, 1000);
        
        // Also listen for afterprint event
        window.addEventListener('afterprint', () => {
            isPrinting = false;
            if (printBtn) {
                printBtn.disabled = false;
                printBtn.style.opacity = '1';
            }
        }, { once: true });
    }
</script>

</body>
</html>
