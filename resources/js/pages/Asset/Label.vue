<script setup lang="ts">
import QrcodeVue from 'qrcode.vue';
import { computed, onMounted } from 'vue';

interface AssetDetail {
    id: number;
    name?: string;
    asset_tag?: string;
    serial?: string;
    model?: string;
    category?: string;
    location?: string;
    status?: string;
    status_type?: string;
}

const props = defineProps<{
    asset: AssetDetail;
    publicUrl: string;
}>();

const statusColor = computed(() => {
    const t = props.asset.status_type ?? '';
    if (t === 'deployed') return '#059669';
    if (t === 'deployable') return '#0284c7';
    if (t === 'archived') return '#64748b';
    if (t === 'undeployable') return '#dc2626';
    return '#d97706';
});

const displayName = computed(() => props.asset.name || props.asset.asset_tag || 'Asset');

onMounted(() => {
    setTimeout(() => {
        window.print();
    }, 800);
});
</script>

<template>
    <div class="page">
        <div class="labels-grid">
            <!-- Generate 10 labels (2 cols x 5 rows) -->
            <div v-for="i in 10" :key="i" class="label">
                <div class="label-qr">
                    <QrcodeVue
                        :value="publicUrl"
                        :size="38"
                        level="M"
                        render-as="svg"
                        :margin="0"
                    />
                </div>
                <div class="label-text">
                    <div class="label-name">{{ displayName }}</div>
                    <div v-if="asset.asset_tag" class="label-tag">{{ asset.asset_tag }}</div>
                    <div v-if="asset.serial" class="label-serial">SN: {{ asset.serial }}</div>
                    <div v-if="asset.status" class="label-status">
                        <span class="dot" :style="{ background: statusColor }" />
                        <span class="status-text">{{ asset.status }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body, html {
    margin: 0;
    padding: 0;
    width: 100%;
}

.page {
    width: 210mm;
    height: 297mm;
    padding: 0;
    margin: 0;
    background: white;
    font-family: Arial, sans-serif;
    display: flex;
    flex-direction: column;
}

.labels-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    grid-template-rows: repeat(5, 1fr);
    gap: 0;
    width: 100%;
    height: 100%;
    padding: 2mm;
}

.label {
    display: flex;
    align-items: center;
    gap: 1.5mm;
    padding: 1mm;
    margin: 0.5mm;
    border: 0.5px solid #ccc;
    background: white;
    page-break-inside: avoid;
    break-inside: avoid;
    min-height: 0;
    overflow: hidden;
}

.label-qr {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 22mm;
    height: 22mm;
    background: white;
}

.label-qr svg {
    width: 100%;
    height: 100%;
}

.label-text {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 0.3mm;
    overflow: hidden;
}

.label-name {
    font-weight: 700;
    font-size: 7px;
    color: #000;
    text-transform: uppercase;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1;
}

.label-tag {
    font-family: 'Courier New', monospace;
    font-weight: 600;
    font-size: 5px;
    color: #333;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1;
}

.label-serial {
    font-family: 'Courier New', monospace;
    font-size: 4.5px;
    color: #666;
    font-style: italic;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1;
}

.label-status {
    display: flex;
    align-items: center;
    gap: 0.5mm;
    font-size: 4px;
    line-height: 1;
}

.dot {
    width: 1.5px;
    height: 1.5px;
    border-radius: 50%;
    flex-shrink: 0;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}

.status-text {
    font-weight: 700;
    color: #555;
    text-transform: uppercase;
}

@media print {
    @page {
        size: A4;
        margin: 0 !important;
        padding: 0 !important;
    }

    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        color-adjust: exact !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    body, html {
        width: 210mm;
        height: 297mm;
        margin: 0 !important;
        padding: 0 !important;
        background: white;
    }

    .page {
        width: 210mm;
        height: 297mm;
        margin: 0 !important;
        padding: 0 !important;
        page-break-after: always;
    }

    .labels-grid {
        width: 210mm;
        height: 297mm;
        padding: 2mm !important;
        gap: 0 !important;
    }

    .label {
        page-break-inside: avoid;
        break-inside: avoid;
        margin: 0.5mm !important;
        padding: 1mm !important;
    }
}
</style>
