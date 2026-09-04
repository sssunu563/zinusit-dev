<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    LucideQrCode as QrCode,
    LucideSearch as Search,
    LucidePrinter as Printer,
    LucidePlus as Plus,
    LucideTrash2 as Trash2,
    LucideCheckSquare as CheckSquare,
    LucideSquare as Square,
    LucideSliders as Sliders,
    LucideTag as TagIcon,
    LucideLayers as Layers,
    LucideEye as Eye,
    LucideRotateCcw as RotateCcw,
} from 'lucide-vue-next';
import QrcodeVue from 'qrcode.vue';
import { computed, ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

interface AssetItem {
    id: number;
    name: string;
    asset_tag: string;
    serial: string;
    model: string;
    category: string;
    location: string;
    company: string;
    status: string;
    status_type: string;
    qr_url: string;
}

interface CustomLabel {
    id: string;
    title: string;
    tag: string;
    subtext: string;
    qr_url: string;
}

const props = defineProps<{
    assets: AssetItem[];
    initialSearch?: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Tools', href: '#' },
    { title: 'Label Engine', href: '/label-generator' },
];

const searchQuery = ref(props.initialSearch || '');
const isSearching = ref(false);
const activeTab = ref<'snipe' | 'custom'>('snipe');

// Selected asset tags for batch printing
const selectedAssets = ref<AssetItem[]>([]);

// Custom label list
const customLabels = ref<CustomLabel[]>([
    {
        id: '1',
        title: 'Server Rack A-01',
        tag: 'RACK-A01',
        subtext: 'Server Room Lt. 2',
        qr_url: 'https://zinus.it/infra/rack-a01',
    },
]);

// Paper size presets [label, widthMM, heightMM]
const paperSizeOptions = [
    { key: 'xs', label: '40 × 25 mm', w: 40, h: 25 },
    { key: 'sm', label: '50 × 30 mm', w: 50, h: 30 },
    { key: 'md', label: '62 × 29 mm', w: 62, h: 29 },
    { key: 'lg', label: '70 × 40 mm', w: 70, h: 40 },
    { key: 'xl', label: '100 × 50 mm', w: 100, h: 50 },
] as const;

type PaperSizeKey = (typeof paperSizeOptions)[number]['key'];

const selectedPaperSize = ref<PaperSizeKey>('xs');

const currentPaperSize = computed(
    () =>
        paperSizeOptions.find((s) => s.key === selectedPaperSize.value) ??
        paperSizeOptions[0],
);

// QR pixel size = 72% of label height (1mm ≈ 3.78px)
const qrPxSize = computed(() =>
    Math.round(currentPaperSize.value.h * 3.78 * 0.72),
);

// Inject dynamic @page style (used both for browser print & live preview update)
const applyPageStyle = () => {
    let el = document.getElementById(
        'lg-page-style',
    ) as HTMLStyleElement | null;
    if (!el) {
        el = document.createElement('style');
        el.id = 'lg-page-style';
        document.head.appendChild(el);
    }
    const { w, h } = currentPaperSize.value;
    el.textContent = `@media print { @page { size: ${w}mm ${h}mm; margin: 0; } }`;
};

watch(selectedPaperSize, applyPageStyle, { immediate: true });

// Label display options
const labelConfig = ref({
    showName: true,
    showTag: true,
    showSerial: true,
    showLocation: true,
});

let debounceTimer: ReturnType<typeof setTimeout> | null = null;
const performSearch = () => {
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        if (!searchQuery.value.trim()) return;
        isSearching.value = true;
        router.get(
            '/label-generator',
            { search: searchQuery.value },
            {
                preserveState: true,
                preserveScroll: true,
                onFinish: () => {
                    isSearching.value = false;
                },
            },
        );
    }, 400);
};

watch(searchQuery, performSearch);

const toggleSelectAll = () => {
    if (selectedAssets.value.length === props.assets.length) {
        selectedAssets.value = [];
    } else {
        selectedAssets.value = [...props.assets];
    }
};

const isSelected = (asset: AssetItem) =>
    selectedAssets.value.some((a) => a.id === asset.id);

const toggleSelect = (asset: AssetItem) => {
    const idx = selectedAssets.value.findIndex((a) => a.id === asset.id);
    if (idx >= 0) {
        selectedAssets.value.splice(idx, 1);
    } else {
        selectedAssets.value.push(asset);
    }
};

// Add new custom label
const addCustomLabel = () => {
    customLabels.value.push({
        id: String(Date.now()),
        title: 'Aset Baru',
        tag: 'TAG-' + Math.floor(1000 + Math.random() * 9000),
        subtext: 'Lokasi IT',
        qr_url: 'https://zinus.it/a/' + Math.floor(1000 + Math.random() * 9000),
    });
};

const removeCustomLabel = (index: number) => {
    customLabels.value.splice(index, 1);
};

// Items that will be printed in print view
const printItems = computed(() => {
    if (activeTab.value === 'snipe') {
        return selectedAssets.value.length > 0
            ? selectedAssets.value
            : props.assets;
    }
    return customLabels.value.map((c) => ({
        id: Number(c.id) || 0,
        name: c.title,
        asset_tag: c.tag,
        serial: c.subtext,
        model: '',
        category: '',
        location: c.subtext,
        company: '',
        status: 'Active',
        status_type: 'deployable',
        qr_url: c.qr_url,
    }));
});

const triggerPrint = () => {
    if (activeTab.value === 'snipe') {
        const ids = printItems.value
            .map((item) => item.id)
            .filter((id) => id > 0);

        if (ids.length > 0) {
            const query = new URLSearchParams({
                size: selectedPaperSize.value,
            });
            ids.forEach((id) => query.append('ids[]', String(id)));
            window.open(`/label-generator/pdf?${query.toString()}`, '_blank');
            return;
        }
    }

    applyPageStyle();
    window.print();
};

const printSingleAsset = (asset: AssetItem) => {
    window.open(
        `/asset/label/${encodeURIComponent(asset.asset_tag || asset.serial || String(asset.id))}`,
        '_blank',
    );
};
</script>

<template>
    <Head title="Label Engine" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <!-- SCREEN CONTENT -->
        <div class="app-page-shell space-y-6 print:hidden">
            <!-- Header Section -->
            <header
                class="flex flex-col justify-between gap-4 md:flex-row md:items-center"
            >
                <div class="space-y-1">
                    <div
                        class="mb-1 flex items-center gap-2 text-[10px] font-black tracking-widest text-[#003628] uppercase"
                    >
                        <QrCode class="size-3" />
                        Label &amp; Barcode Management
                    </div>
                    <h1
                        class="text-3xl font-black tracking-tight text-slate-900 lg:text-4xl"
                    >
                        Label <span class="text-[#003628] italic">Engine</span>
                    </h1>
                </div>

                <div class="flex items-center gap-2.5">
                    <button
                        type="button"
                        @click="triggerPrint"
                        :disabled="printItems.length === 0"
                        class="flex h-10 cursor-pointer items-center gap-2 rounded-xl bg-[#003628] px-5 text-xs font-bold text-white shadow-lg shadow-emerald-950/20 transition-all hover:bg-[#00281e] active:scale-95 disabled:opacity-50"
                    >
                        <Printer class="size-4" />
                        <span
                            >Cetak {{ printItems.length }} Label Terpilih</span
                        >
                    </button>
                </div>
            </header>

            <!-- Mode Selector & Settings Bar -->
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <!-- Tabs Mode -->
                <div
                    class="flex items-center gap-1 rounded-2xl border border-slate-200/60 bg-slate-100 p-1.5"
                >
                    <button
                        type="button"
                        @click="activeTab = 'snipe'"
                        class="flex h-9 flex-1 cursor-pointer items-center justify-center gap-2 rounded-xl text-xs font-bold transition-all"
                        :class="
                            activeTab === 'snipe'
                                ? 'bg-white text-[#003628] shadow-xs'
                                : 'text-slate-500 hover:text-slate-900'
                        "
                    >
                        <Layers class="size-3.5" />
                        <span>Aset Hardware (Snipe-IT)</span>
                    </button>
                    <button
                        type="button"
                        @click="activeTab = 'custom'"
                        class="flex h-9 flex-1 cursor-pointer items-center justify-center gap-2 rounded-xl text-xs font-bold transition-all"
                        :class="
                            activeTab === 'custom'
                                ? 'bg-white text-[#003628] shadow-xs'
                                : 'text-slate-500 hover:text-slate-900'
                        "
                    >
                        <TagIcon class="size-3.5" />
                        <span>Label Kustom / Bebas</span>
                    </button>
                </div>

                <!-- Label Configuration Options -->
                <div
                    class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200/60 bg-white p-3 text-xs shadow-xs lg:col-span-2"
                >
                    <!-- Paper size -->
                    <div class="flex flex-wrap items-center gap-2">
                        <div
                            class="flex items-center gap-1.5 font-bold text-slate-500"
                        >
                            <Sliders class="size-3.5 text-[#003628]" />
                            <span>Ukuran Kertas:</span>
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            <button
                                v-for="s in paperSizeOptions"
                                :key="s.key"
                                type="button"
                                @click="selectedPaperSize = s.key"
                                class="h-7 cursor-pointer rounded-lg border px-2.5 text-[10px] font-bold transition-all"
                                :class="
                                    selectedPaperSize === s.key
                                        ? 'border-[#003628] bg-[#003628] text-white'
                                        : 'border-slate-200 bg-slate-50 text-slate-600 hover:border-slate-300'
                                "
                            >
                                {{ s.label }}
                            </button>
                        </div>
                    </div>

                    <!-- Field toggles -->
                    <div
                        class="flex items-center gap-3 text-[11px] font-bold text-slate-600"
                    >
                        <label class="flex cursor-pointer items-center gap-1.5">
                            <input
                                v-model="labelConfig.showTag"
                                type="checkbox"
                                class="rounded text-[#003628]"
                            />
                            <span>Tag</span>
                        </label>
                        <label class="flex cursor-pointer items-center gap-1.5">
                            <input
                                v-model="labelConfig.showSerial"
                                type="checkbox"
                                class="rounded text-[#003628]"
                            />
                            <span>SN</span>
                        </label>
                        <label class="flex cursor-pointer items-center gap-1.5">
                            <input
                                v-model="labelConfig.showLocation"
                                type="checkbox"
                                class="rounded text-[#003628]"
                            />
                            <span>Lokasi</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- TAB 1: SNIPE-IT ASSET SEARCH & SELECT -->
            <div v-if="activeTab === 'snipe'" class="space-y-4">
                <!-- Search Box -->
                <div
                    class="flex flex-col items-center justify-between gap-4 rounded-2xl border border-slate-200/60 bg-white p-4 shadow-xs sm:flex-row"
                >
                    <div class="relative w-full sm:max-w-md">
                        <Search
                            class="absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-slate-400"
                        />
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Cari Asset Tag, Serial Number, atau Nama Laptop/PC..."
                            class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50/50 pr-4 pl-10 text-xs text-slate-900 shadow-xs transition-all outline-none focus:border-[#003628]/50 focus:bg-white"
                        />
                    </div>

                    <div
                        class="flex w-full items-center justify-between gap-2 sm:w-auto sm:justify-end"
                    >
                        <span class="text-[11px] font-bold text-slate-400">
                            {{ selectedAssets.length }} dari
                            {{ assets.length }} aset dipilih
                        </span>
                        <button
                            type="button"
                            @click="toggleSelectAll"
                            v-if="assets.length > 0"
                            class="h-9 cursor-pointer rounded-xl border border-slate-200 px-3 text-xs font-bold text-slate-600 transition-all hover:bg-slate-50"
                        >
                            {{
                                selectedAssets.length === assets.length
                                    ? 'Batal Pilih Semua'
                                    : 'Pilih Semua'
                            }}
                        </button>
                    </div>
                </div>

                <!-- Asset Label Preview Grid -->
                <div
                    v-if="assets.length > 0"
                    class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3"
                >
                    <div
                        v-for="asset in assets"
                        :key="asset.id"
                        class="group relative flex cursor-pointer flex-col justify-between rounded-2xl border bg-white p-4 transition-all"
                        :class="
                            isSelected(asset)
                                ? 'border-[#003628] shadow-md ring-2 ring-[#003628]/10'
                                : 'border-slate-200/70 shadow-xs hover:border-slate-300'
                        "
                        @click="toggleSelect(asset)"
                    >
                        <!-- Top Selector & Controls -->
                        <div
                            class="mb-3 flex items-start justify-between gap-3"
                        >
                            <div class="flex items-center gap-2">
                                <component
                                    :is="
                                        isSelected(asset) ? CheckSquare : Square
                                    "
                                    class="size-4"
                                    :class="
                                        isSelected(asset)
                                            ? 'text-[#003628]'
                                            : 'text-slate-300'
                                    "
                                />
                                <span
                                    class="text-[10px] font-black tracking-widest text-[#003628] uppercase"
                                >
                                    {{ asset.category || 'Hardware' }}
                                </span>
                            </div>

                            <button
                                type="button"
                                @click.stop="printSingleAsset(asset)"
                                class="flex h-7 cursor-pointer items-center gap-1 rounded-lg border border-slate-200 px-2 text-[10px] font-bold text-slate-600 shadow-xs hover:bg-slate-50"
                                title="Cetak Label Tunggal"
                            >
                                <Printer class="size-3 text-[#003628]" />
                                <span>Cetak</span>
                            </button>
                        </div>

                        <!-- Card Preview (Realistic Label simulation) -->
                        <div
                            class="flex items-center gap-3 rounded-xl border border-slate-100 bg-slate-50 p-3"
                        >
                            <div
                                class="flex size-16 shrink-0 items-center justify-center rounded-lg border border-slate-200/60 bg-white p-1 shadow-xs"
                            >
                                <QrcodeVue
                                    :value="asset.qr_url"
                                    :size="56"
                                    level="M"
                                    render-as="svg"
                                    :margin="0"
                                />
                            </div>

                            <div class="min-w-0 flex-1 space-y-0.5">
                                <p
                                    class="truncate text-xs font-black text-slate-900"
                                >
                                    {{
                                        asset.name ||
                                        asset.model ||
                                        asset.asset_tag
                                    }}
                                </p>
                                <p
                                    class="font-mono text-[11px] font-bold tracking-tight text-[#003628]"
                                >
                                    {{ asset.asset_tag || '-' }}
                                </p>
                                <p
                                    v-if="asset.serial"
                                    class="truncate font-mono text-[10px] text-slate-400 italic"
                                >
                                    SN: {{ asset.serial }}
                                </p>
                                <p
                                    v-if="asset.location"
                                    class="truncate text-[9px] font-medium text-slate-500"
                                >
                                    📍 {{ asset.location }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div
                    v-else
                    class="space-y-3 rounded-3xl border border-slate-200/60 bg-white p-16 text-center"
                >
                    <div
                        class="mx-auto flex size-12 items-center justify-center rounded-2xl bg-emerald-50 text-[#003628]"
                    >
                        <Search class="size-6" />
                    </div>
                    <h3 class="text-sm font-bold text-slate-800">
                        Ketik kata kunci pencarian aset di atas
                    </h3>
                    <p class="mx-auto max-w-sm text-xs text-slate-400">
                        Cari aset berdasarkan Asset Tag, Serial Number, nama
                        laptop, atau model untuk membuat dan mencetak label QR.
                    </p>
                </div>
            </div>

            <!-- TAB 2: CUSTOM / AD-HOC LABELS -->
            <div v-else class="space-y-4">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-bold text-slate-500">
                        Buat label QR bebas untuk ruangan, rack server, kabel
                        jaringan, atau perangkat ad-hoc.
                    </p>
                    <button
                        type="button"
                        @click="addCustomLabel"
                        class="flex h-9 cursor-pointer items-center gap-1.5 rounded-xl bg-[#003628] px-4 text-xs font-bold text-white shadow-xs hover:bg-[#00281e]"
                    >
                        <Plus class="size-3.5" />
                        <span>Tambah Label</span>
                    </button>
                </div>

                <div
                    class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3"
                >
                    <div
                        v-for="(custom, idx) in customLabels"
                        :key="custom.id"
                        class="space-y-3 rounded-2xl border border-slate-200/70 bg-white p-4 shadow-xs"
                    >
                        <div class="flex items-center justify-between">
                            <span
                                class="text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                >Label #{{ idx + 1 }}</span
                            >
                            <button
                                type="button"
                                @click="removeCustomLabel(idx)"
                                class="cursor-pointer text-slate-400 transition-colors hover:text-rose-600"
                                title="Hapus Label"
                            >
                                <Trash2 class="size-3.5" />
                            </button>
                        </div>

                        <!-- Editor inputs -->
                        <div class="space-y-2">
                            <input
                                v-model="custom.title"
                                type="text"
                                placeholder="Judul / Nama Barang"
                                class="h-8 w-full rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-xs font-bold text-slate-800 outline-none"
                            />
                            <div class="grid grid-cols-2 gap-2">
                                <input
                                    v-model="custom.tag"
                                    type="text"
                                    placeholder="Kode / Tag"
                                    class="h-8 rounded-lg border border-slate-200 bg-slate-50 px-2.5 font-mono text-xs text-slate-800 outline-none"
                                />
                                <input
                                    v-model="custom.subtext"
                                    type="text"
                                    placeholder="Lokasi / Keterangan"
                                    class="h-8 rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-xs text-slate-800 outline-none"
                                />
                            </div>
                            <input
                                v-model="custom.qr_url"
                                type="text"
                                placeholder="URL atau Teks QR Code"
                                class="h-8 w-full rounded-lg border border-slate-200 bg-slate-50 px-2.5 font-mono text-[11px] text-slate-600 outline-none"
                            />
                        </div>

                        <!-- Live Mini Preview -->
                        <div
                            class="flex items-center gap-3 rounded-xl border border-slate-100 bg-slate-50 p-2.5"
                        >
                            <div
                                class="flex size-12 shrink-0 items-center justify-center rounded-md border border-slate-200 bg-white p-1"
                            >
                                <QrcodeVue
                                    :value="custom.qr_url || 'https://zinus.it'"
                                    :size="40"
                                    level="M"
                                    render-as="svg"
                                    :margin="0"
                                />
                            </div>
                            <div class="min-w-0">
                                <p
                                    class="truncate text-xs font-black text-slate-800"
                                >
                                    {{ custom.title || 'Judul' }}
                                </p>
                                <p
                                    class="font-mono text-[10px] font-bold text-[#003628]"
                                >
                                    {{ custom.tag || 'TAG' }}
                                </p>
                                <p class="truncate text-[9px] text-slate-400">
                                    {{ custom.subtext || 'Keterangan' }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- PRINT VIEW ONLY (Triggered when window.print() is called) -->
        <div class="print-sheet">
            <div
                v-for="item in printItems"
                :key="item.id"
                class="print-label-item"
                :style="{
                    width: currentPaperSize.w + 'mm',
                    height: currentPaperSize.h + 'mm',
                }"
            >
                <div
                    class="pl-qr"
                    :style="{
                        width: Math.round(currentPaperSize.h * 0.72) + 'mm',
                        height: Math.round(currentPaperSize.h * 0.72) + 'mm',
                        flexBasis: Math.round(currentPaperSize.h * 0.72) + 'mm',
                    }"
                >
                    <QrcodeVue
                        :value="item.qr_url"
                        :size="qrPxSize"
                        level="M"
                        render-as="svg"
                        :margin="0"
                    />
                </div>
                <div class="pl-info">
                    <p class="pl-name">
                        {{ item.name || item.model || item.asset_tag }}
                    </p>
                    <p v-if="labelConfig.showTag" class="pl-tag">
                        {{ item.asset_tag }}
                    </p>
                    <p
                        v-if="labelConfig.showSerial && item.serial"
                        class="pl-serial"
                    >
                        SN: {{ item.serial }}
                    </p>
                    <p
                        v-if="labelConfig.showLocation && item.location"
                        class="pl-loc"
                    >
                        {{ item.location }}
                    </p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style>
/* ── Screen: ensure print-sheet is invisible ── */
.print-sheet {
    display: none;
}

@media print {
    /* @page size is injected dynamically via applyPageStyle() */

    /* Hide non-print elements */
    aside,
    [data-sidebar='sidebar'],
    header,
    [data-sidebar='header'],
    .app-page-shell {
        display: none !important;
    }

    /* Reset layout ancestors so print-sheet is displayed cleanly */
    html,
    body,
    #app,
    [data-sidebar='provider'],
    [data-sidebar='inset'],
    main {
        margin: 0 !important;
        padding: 0 !important;
        background: white !important;
        border: none !important;
        overflow: visible !important;
        width: 100% !important;
        height: auto !important;
        min-height: auto !important;
        box-shadow: none !important;
    }

    /* Show only the print sheet */
    .print-sheet {
        display: block !important;
        margin: 0 !important;
        padding: 0 !important;
        background: white !important;
    }

    .print-label-item {
        display: flex !important;
        align-items: center !important;
        padding: 1.5mm !important;
        background: white !important;
        overflow: hidden !important;
        gap: 2mm !important;
        page-break-after: always !important;
        break-after: page !important;
        page-break-inside: avoid !important;
        break-inside: avoid !important;
        box-sizing: border-box !important;
        margin: 0 !important;
    }

    .print-label-item:last-child {
        page-break-after: avoid !important;
        break-after: avoid !important;
    }

    .pl-qr {
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .pl-qr svg {
        width: 100% !important;
        height: 100% !important;
    }

    .pl-info {
        flex: 1;
        min-width: 0;
        overflow: hidden;
        font-family: Arial, sans-serif;
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

    .pl-loc {
        font-size: 4.5pt;
        color: #555;
        margin-top: 0.3mm;
        white-space: nowrap;
        overflow: hidden;
    }

    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
}
</style>
