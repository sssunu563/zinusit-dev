<script setup lang="ts">
import QrcodeVue from 'qrcode.vue';
import { onMounted, computed } from 'vue';

interface AssetDetail {
    id: number;
    name?: string;
    asset_tag?: string;
    serial?: string;
    model?: string;
    model_number?: string;
    category?: string;
    manufacturer?: string;
    location?: string;
    company?: string;
    status?: string;
    status_type?: string;
    image?: string;
    assigned_to?: string;
}

const props = defineProps<{
    asset: AssetDetail;
    publicUrl: string;
}>();

const pdfUrl = `/asset/label/${encodeURIComponent(props.asset.asset_tag || props.asset.serial || String(props.asset.id))}/pdf`;

const displayName = computed(
    () =>
        props.asset.name ||
        props.asset.model ||
        props.asset.asset_tag ||
        'Asset',
);

const shortCategory = computed(() => {
    const cat = props.asset.category || '';
    return cat.length > 18 ? cat.substring(0, 16) + '…' : cat;
});

const shortLocation = computed(() => {
    const loc = props.asset.location || '';
    return loc.length > 22 ? loc.substring(0, 20) + '…' : loc;
});

const statusColor = computed(() => {
    const t = props.asset.status_type ?? '';
    if (t === 'deployed') return '#059669';
    if (t === 'deployable') return '#0284c7';
    if (t === 'archived') return '#64748b';
    if (t === 'undeployable') return '#dc2626';
    return '#d97706';
});

// Auto-print after QR renders (small delay to ensure SVG is painted)
onMounted(() => {
    setTimeout(() => {
        window.print();
    }, 600);
});
</script>

<template>
    <!-- Screen preview: centered label with print button -->
    <div class="screen-only">
        <div class="preview-wrapper">
            <h2>Pratinjau Label</h2>
            <p class="sub">Label 40×25mm · Siap Cetak</p>

            <div class="label-card">
                <!-- Left: QR Code -->
                <div class="qr-col">
                    <QrcodeVue
                        :value="publicUrl"
                        :size="72"
                        level="M"
                        render-as="svg"
                        :margin="0"
                    />
                </div>

                <!-- Right: Info -->
                <div class="info-col">
                    <p class="asset-name">{{ displayName }}</p>
                    <p v-if="asset.asset_tag" class="tag">
                        {{ asset.asset_tag }}
                    </p>
                    <p v-if="asset.serial" class="serial">
                        SN: {{ asset.serial }}
                    </p>
                    <div
                        v-if="asset.category || asset.location"
                        class="meta-row"
                    >
                        <span v-if="shortCategory" class="badge cat">{{
                            shortCategory
                        }}</span>
                        <span v-if="shortLocation" class="badge loc">{{
                            shortLocation
                        }}</span>
                    </div>
                    <div v-if="asset.status" class="status-row">
                        <span
                            class="status-dot"
                            :style="{ background: statusColor }"
                        />
                        <span class="status-label">{{ asset.status }}</span>
                    </div>
                </div>
            </div>

            <div class="actions">
                <button
                    class="btn-print"
                    @click="() => window.open(pdfUrl, '_blank')"
                >
                    🖨 Cetak Label
                </button>
                <button class="btn-close" @click="() => window.close()">
                    ✕ Tutup
                </button>
            </div>

            <p class="hint">Cetak dengan skala 100%, tanpa margin.</p>
        </div>
    </div>

    <!-- Print-only label (rendered as 40×25mm physical size) -->
    <div class="print-label">
        <div class="pl-qr">
            <QrcodeVue
                :value="publicUrl"
                :size="62"
                level="M"
                render-as="svg"
                :margin="0"
            />
        </div>
        <div class="pl-info">
            <p class="pl-name">{{ displayName }}</p>
            <p v-if="asset.asset_tag" class="pl-tag">{{ asset.asset_tag }}</p>
            <p v-if="asset.serial" class="pl-serial">SN: {{ asset.serial }}</p>
            <div v-if="asset.status" class="pl-status">
                <span class="pl-dot" :style="{ background: statusColor }" />
                <span>{{ asset.status }}</span>
            </div>
        </div>
    </div>
</template>

<style>
/* ── Reset ── */
*,
*::before,
*::after {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

/* ── SCREEN view ── */
body {
    font-family: 'Segoe UI', Arial, sans-serif;
    background: #f1f5f9;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Hide print-label on screen */
.print-label {
    display: none;
}

.screen-only {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    width: 100%;
    padding: 24px;
}

.preview-wrapper {
    background: white;
    border-radius: 20px;
    padding: 32px 36px;
    box-shadow: 0 8px 40px rgba(0, 0, 0, 0.12);
    max-width: 480px;
    width: 100%;
    text-align: center;
}

.preview-wrapper h2 {
    font-size: 18px;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.02em;
}

.sub {
    font-size: 12px;
    color: #94a3b8;
    font-weight: 600;
    margin-top: 4px;
    margin-bottom: 24px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

/* Preview card — mimics physical label proportions */
.label-card {
    display: flex;
    align-items: center;
    gap: 14px;
    background: white;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    padding: 14px 16px;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
    text-align: left;
    margin-bottom: 24px;
}

.qr-col {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}

.info-col {
    flex: 1;
    min-width: 0;
    overflow: hidden;
}

.asset-name {
    font-size: 13px;
    font-weight: 800;
    color: #0f172a;
    text-transform: uppercase;
    letter-spacing: -0.02em;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.tag {
    font-size: 11px;
    font-weight: 700;
    color: #334155;
    margin-top: 2px;
    font-family: monospace;
}

.serial {
    font-size: 10px;
    color: #94a3b8;
    font-weight: 600;
    font-style: italic;
    margin-top: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.meta-row {
    display: flex;
    gap: 5px;
    margin-top: 5px;
    flex-wrap: wrap;
}

.badge {
    font-size: 9px;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 4px;
    white-space: nowrap;
}

.badge.cat {
    background: #eff6ff;
    color: #1d4ed8;
    border: 1px solid #bfdbfe;
}

.badge.loc {
    background: #f0fdf4;
    color: #15803d;
    border: 1px solid #bbf7d0;
}

.status-row {
    display: flex;
    align-items: center;
    gap: 4px;
    margin-top: 5px;
}

.status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    flex-shrink: 0;
}

.status-label {
    font-size: 9px;
    font-weight: 700;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

/* Action buttons */
.actions {
    display: flex;
    gap: 10px;
    justify-content: center;
    margin-bottom: 14px;
}

.btn-print {
    background: #003628;
    color: white;
    border: none;
    border-radius: 10px;
    padding: 10px 22px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    transition: opacity 0.15s;
}

.btn-print:hover {
    opacity: 0.85;
}

.btn-close {
    background: white;
    color: #475569;
    border: 1.5px solid #e2e8f0;
    border-radius: 10px;
    padding: 10px 22px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.15s;
}

.btn-close:hover {
    background: #f8fafc;
}

.hint {
    font-size: 10px;
    color: #cbd5e1;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

/* ── PRINT view ── */
@media print {
    @page {
        size: 40mm 25mm;
        margin: 0;
    }

    html,
    body {
        width: 40mm !important;
        height: 25mm !important;
        min-width: 40mm !important;
        min-height: 25mm !important;
        max-width: 40mm !important;
        max-height: 25mm !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: hidden !important;
    }

    /* Hide everything on screen during print */
    body {
        background: white !important;
        min-height: unset !important;
        display: block !important;
    }

    .screen-only {
        display: none !important;
    }

    /* Show print label */
    .print-label {
        display: flex !important;
        align-items: center;
        width: 40mm;
        height: 25mm;
        padding: 1.5mm;
        background: white;
        overflow: hidden;
        gap: 2mm;
        page-break-inside: avoid;
    }

    .pl-qr {
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 17mm;
        height: 17mm;
    }

    .pl-qr svg {
        width: 17mm !important;
        height: 17mm !important;
    }

    .pl-info {
        flex: 1;
        min-width: 0;
        overflow: hidden;
    }

    .pl-name {
        font-size: 5.5pt;
        font-weight: 900;
        color: black;
        text-transform: uppercase;
        letter-spacing: -0.03em;
        line-height: 1.1;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        font-family: Arial, sans-serif;
    }

    .pl-tag {
        font-size: 6pt;
        font-weight: 700;
        color: black;
        margin-top: 0.5mm;
        white-space: nowrap;
        overflow: hidden;
        font-family: 'Courier New', monospace;
    }

    .pl-serial {
        font-size: 4.5pt;
        color: #333;
        font-style: italic;
        margin-top: 0.3mm;
        white-space: nowrap;
        overflow: hidden;
        font-family: 'Courier New', monospace;
    }

    .pl-status {
        display: flex;
        align-items: center;
        gap: 1mm;
        margin-top: 0.8mm;
    }

    .pl-dot {
        width: 2mm;
        height: 2mm;
        border-radius: 50%;
        flex-shrink: 0;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .pl-status span:last-child {
        font-size: 4.5pt;
        font-weight: 700;
        color: #444;
        text-transform: uppercase;
        font-family: Arial, sans-serif;
    }
}
</style>
