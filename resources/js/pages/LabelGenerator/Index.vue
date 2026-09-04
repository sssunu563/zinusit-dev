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

// Print / Label configuration options
const labelConfig = ref({
    size: 'standard', // 'standard' (40x25mm), 'medium' (50x30mm), 'large' (70x40mm)
    showName: true,
    showTag: true,
    showSerial: true,
    showLocation: true,
    showStatus: true,
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
            window.open(
                `/label-generator/pdf?ids[]=${ids.join('&ids[]=')}`,
                '_blank',
            );
            return;
        }
    }
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
                    <div
                        class="flex items-center gap-2 font-bold text-slate-500"
                    >
                        <Sliders class="size-3.5 text-[#003628]" />
                        <span>Opsi Ukuran:</span>
                        <select
                            v-model="labelConfig.size"
                            class="h-8 rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-[11px] font-bold text-slate-700 outline-none"
                        >
                            <option value="standard">
                                Standard (40 × 25 mm)
                            </option>
                            <option value="medium">Medium (50 × 30 mm)</option>
                            <option value="large">Besar (70 × 40 mm)</option>
                        </select>
                    </div>

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
        <div class="print-sheet hidden print:block">
            <div
                v-for="item in printItems"
                :key="item.id"
                class="print-label-item"
            >
                <div class="pl-qr">
                    <QrcodeVue
                        :value="item.qr_url"
                        :size="62"
                        level="M"
                        render-as="svg"
                        :margin="0"
                    />
                </div>
                <div class="pl-info">
                    <p class="pl-name">
                        {{ item.name || item.model || item.asset_tag }}
                    </p>
                    <p class="pl-tag">{{ item.asset_tag }}</p>
                    <p v-if="item.serial" class="pl-serial">
                        SN: {{ item.serial }}
                    </p>
                    <p v-if="item.location" class="pl-loc">
                        {{ item.location }}
                    </p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style>
@media print {
    @page {
        size: 40mm 25mm;
        margin: 0;
    }

    body {
        background: white !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    /* Print sheet containing multiple labels */
    .print-sheet {
        display: block !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    .print-label-item {
        display: flex !important;
        align-items: center;
        width: 40mm;
        height: 25mm;
        padding: 1.5mm;
        background: white;
        overflow: hidden;
        gap: 2mm;
        page-break-after: always;
        page-break-inside: avoid;
        box-sizing: border-box;
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
}
</style>
