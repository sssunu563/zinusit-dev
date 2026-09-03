<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    FileText,
    User,
    Download,
    Package,
    MapPin,
    Building2,
    Calendar,
    Wallet,
    AlertTriangle,
    Plus,
    Edit,
    History,
    ArrowLeft,
    Info,
    Users,
    RotateCcw,
    BadgeCheck,
    ChevronRight,
    Hash,
    Clock,
    RefreshCw,
    Share2,
} from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import AddStockModal from '@/pages/Asset/Partials/AddStockModal.vue';
import UploadDocumentModal from '@/pages/Asset/Partials/UploadDocumentModal.vue';
import AppPdfViewerModal from '@/components/AppPdfViewerModal.vue';
import AppPagination from '@/components/AppPagination.vue';
import type { BreadcrumbItem } from '@/types';

interface AssetDetail {
    id: number;
    name: string;
    asset_tag?: string;
    serial?: string;
    model?: string;
    model_number?: string;
    category?: string;
    manufacturer?: string;
    location?: string;
    company?: string;
    status?: string;
    qty?: number;
    remaining_qty?: number;
    requestable?: boolean;
    image?: string;
    created_by?: string;
    notes?: string;
    purchase_date?: string;
    purchase_cost?: string;
    order_number?: string;
    supplier?: string;
    model_number?: string;
    item_no?: string;
    min_qty?: number;
    updated_at?: string;
    custom_fields?: Array<{ name: string; value: string; format: string }>;
}

interface CheckoutRecord {
    id: number;
    name: string;
    secondary: string;
    email: string;
    note: string;
    date: string;
    image: string;
}
interface ActivityRecord {
    id: number;
    action_type: string;
    user: string;
    user_image: string;
    target: string;
    note: string;
    date: string;
}
interface AssetFile {
    id: number;
    filename: string;
    download_url: string;
    created_by: string;
    date: string;
    notes: string;
}

interface StockRecord {
    id: number;
    qty: number;
    po_number: string;
    purchase_date: string;
    notes: string | null;
    document_url: string | null;
    created_by: string;
    created_at: string;
}

const props = defineProps<{
    assetType: string;
    assetTypeLabel: string;
    asset: AssetDetail;
    assetFiles: AssetFile[];
    checkoutRecords: CheckoutRecord[];
    activityHistory: ActivityRecord[];
}>();

const activeTab = ref<'info' | 'assigned' | 'files' | 'history' | 'stock'>(
    'info',
);
const showDocModal = ref(false);
const pdfViewerOpen = ref(false);
const pdfViewerUrl = ref<string | null>(null);
const openPdfViewer = (url: string) => {
    pdfViewerUrl.value = url;
    pdfViewerOpen.value = true;
};
const showStockModal = ref(false);

const stockHistory = ref<StockRecord[]>([]);
const stockHistoryLoading = ref(false);
const stockHistoryLoaded = ref(false);

async function loadStockHistory() {
    if (stockHistoryLoaded.value) return;
    stockHistoryLoading.value = true;
    try {
        const res = await fetch(
            `/asset/item/${props.asset.id}/stock-history?type=${encodeURIComponent(props.assetType)}`,
        );
        if (res.ok) stockHistory.value = await res.json();
    } finally {
        stockHistoryLoading.value = false;
        stockHistoryLoaded.value = true;
    }
}

watch(showStockModal, (open) => {
    if (!open && stockHistoryLoaded.value) {
        stockHistoryLoaded.value = false;
        loadStockHistory();
    }
});

watch(activeTab, (tab) => {
    if (tab === 'stock') loadStockHistory();
});

// Pagination state
const checkoutPage = ref(1);
const checkoutPerPage = 10;
const stockPage = ref(1);
const stockPerPage = 15;
const filesPage = ref(1);
const filesPerPage = 10;
const historyPage = ref(1);
const historyPerPage = 15;

// Pagination computed properties
const paginatedCheckouts = computed(() => {
    const start = (checkoutPage.value - 1) * checkoutPerPage;
    return props.checkoutRecords.slice(start, start + checkoutPerPage);
});
const checkoutTotalPages = computed(() =>
    Math.ceil(props.checkoutRecords.length / checkoutPerPage),
);

const paginatedStock = computed(() => {
    const start = (stockPage.value - 1) * stockPerPage;
    return stockHistory.value.slice(start, start + stockPerPage);
});
const stockTotalPages = computed(() =>
    Math.ceil(stockHistory.value.length / stockPerPage),
);

const paginatedFiles = computed(() => {
    const start = (filesPage.value - 1) * filesPerPage;
    return props.assetFiles.slice(start, start + filesPerPage);
});
const filesTotalPages = computed(() =>
    Math.ceil(props.assetFiles.length / filesPerPage),
);

const paginatedHistory = computed(() => {
    const start = (historyPage.value - 1) * historyPerPage;
    return props.activityHistory.slice(start, start + historyPerPage);
});
const historyTotalPages = computed(() =>
    Math.ceil(props.activityHistory.length / historyPerPage),
);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Dashboard', href: '/dashboard' },
    {
        title: 'Assets',
        href: `/asset?type=${encodeURIComponent(props.assetType)}`,
    },
    {
        title: props.assetTypeLabel,
        href: `/asset?type=${encodeURIComponent(props.assetType)}`,
    },
    { title: props.asset.name, href: '#' },
]);

function goBack() {
    window.location.href = `/asset?type=${encodeURIComponent(props.assetType)}`;
}

// STB (Handover) generation
const handleGenerateSTB = () => {
    const movementType = 'out'; // Consumables are always 'out'
    const params = new URLSearchParams({
        documentType: 'handover',
        movementType,
    });

    params.append('selectedAssetIds[]', String(props.asset.id));

    router.visit(`/stb/create?${params.toString()}`);
};

const isOutOfStock = computed(() => (props.asset.remaining_qty ?? 0) <= 0);
const stockPct = computed(() => {
    const t = props.asset.qty ?? 0;
    const r = props.asset.remaining_qty ?? 0;
    return t > 0 ? Math.round((r / t) * 100) : 0;
});

const tabs = computed(() => [
    { id: 'info', label: 'Info', badge: 0 },
    {
        id: 'assigned',
        label: 'Assignments',
        badge: props.checkoutRecords.length,
    },
    { id: 'stock', label: 'Riwayat Stok', badge: 0 },
    { id: 'files', label: 'Dokumen', badge: props.assetFiles.length },
    { id: 'history', label: 'Aktivitas', badge: 0 },
]);

const actionBadgeClass = (action: string) => {
    const a = action.toLowerCase();
    if (a.includes('checkout')) return 'bg-emerald-50 text-emerald-700';
    if (a.includes('checkin')) return 'bg-amber-50 text-amber-700';
    if (a.includes('update')) return 'bg-sky-50 text-sky-700';
    if (a.includes('create') || a.includes('add'))
        return 'bg-violet-50 text-violet-700';
    if (a.includes('delete')) return 'bg-rose-50 text-rose-700';
    return 'bg-slate-100 text-slate-600';
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`${assetTypeLabel} — ${asset.name}`" />

        <div class="app-page-shell space-y-5">
            <!-- HEADER CARD -->
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm">
                <div
                    class="flex flex-col gap-5 px-6 py-5 sm:flex-row sm:items-start sm:justify-between"
                >
                    <div class="flex items-start gap-4">
                        <div
                            class="mt-0.5 flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#003628]"
                        >
                            <Package class="size-5 text-white" />
                        </div>
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h1
                                    class="text-lg font-bold tracking-tight text-slate-900"
                                >
                                    {{ asset.name }}
                                </h1>
                                <span
                                    class="rounded-md bg-[#003628]/10 px-2 py-0.5 text-[10px] font-semibold text-[#003628]"
                                >
                                    {{ assetTypeLabel }}
                                </span>
                                <span
                                    v-if="asset.category"
                                    class="rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-500"
                                >
                                    {{ asset.category }}
                                </span>
                            </div>
                            <div
                                class="mt-1.5 flex flex-wrap items-center gap-3 text-xs text-slate-400"
                            >
                                <span
                                    v-if="asset.manufacturer"
                                    class="flex items-center gap-1"
                                >
                                    <Building2 class="size-3" />
                                    {{ asset.manufacturer }}
                                </span>
                                <span
                                    v-if="asset.location"
                                    class="flex items-center gap-1"
                                >
                                    <MapPin class="size-3" />
                                    {{ asset.location }}
                                </span>
                                <span
                                    v-if="asset.updated_at"
                                    class="flex items-center gap-1"
                                >
                                    <Clock class="size-3" /> Diperbarui
                                    {{ asset.updated_at }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <button
                            type="button"
                            class="flex h-8 items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 text-xs font-medium text-slate-500 transition hover:bg-slate-50"
                            @click="goBack"
                        >
                            <ArrowLeft class="size-3.5" /> Kembali
                        </button>
                        <button
                            type="button"
                            class="flex h-8 items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-3 text-xs font-medium text-emerald-700 transition hover:bg-emerald-100"
                            @click="handleGenerateSTB"
                            title="Generate STB Handover"
                        >
                            <Share2 class="size-3.5" /> Generate STB
                        </button>
                        <button
                            type="button"
                            class="flex h-8 items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 text-xs font-medium text-slate-500 transition hover:bg-slate-50"
                            @click="showStockModal = true"
                        >
                            <Plus class="size-3.5" /> Tambah Stok
                        </button>
                        <a
                            :href="`/asset/${asset.id}/edit?type=${assetType}`"
                            class="flex h-8 items-center gap-1.5 rounded-lg bg-[#003628] px-4 text-xs font-semibold text-white transition hover:bg-[#003628]/90"
                        >
                            <Edit class="size-3.5" /> Edit
                        </a>
                    </div>
                </div>

                <!-- Stock stats bar -->
                <div class="border-t border-slate-100 px-6 py-4">
                    <div
                        class="flex flex-wrap items-center justify-between gap-4"
                    >
                        <div class="flex items-center gap-6">
                            <div class="text-center">
                                <p
                                    class="text-2xl font-bold tabular-nums"
                                    :class="
                                        isOutOfStock
                                            ? 'text-rose-500'
                                            : 'text-[#003628]'
                                    "
                                >
                                    {{ asset.remaining_qty ?? 0 }}
                                </p>
                                <p
                                    class="text-[10px] font-medium tracking-widest text-slate-400 uppercase"
                                >
                                    Tersedia
                                </p>
                            </div>
                            <div class="h-8 w-px bg-slate-100" />
                            <div class="text-center">
                                <p
                                    class="text-2xl font-bold text-slate-700 tabular-nums"
                                >
                                    {{ asset.qty ?? 0 }}
                                </p>
                                <p
                                    class="text-[10px] font-medium tracking-widest text-slate-400 uppercase"
                                >
                                    Total Unit
                                </p>
                            </div>
                            <div class="h-8 w-px bg-slate-100" />
                            <div class="text-center">
                                <p
                                    class="text-2xl font-bold text-slate-700 tabular-nums"
                                >
                                    {{
                                        (asset.qty ?? 0) -
                                        (asset.remaining_qty ?? 0)
                                    }}
                                </p>
                                <p
                                    class="text-[10px] font-medium tracking-widest text-slate-400 uppercase"
                                >
                                    Terpakai
                                </p>
                            </div>
                        </div>
                        <div class="hidden max-w-xs flex-1 sm:block">
                            <div
                                class="mb-1.5 flex items-center justify-between text-[10px] font-medium tracking-widest text-slate-400 uppercase"
                            >
                                <span>Utilisasi Stok</span>
                                <span
                                    :class="
                                        isOutOfStock
                                            ? 'font-semibold text-rose-500'
                                            : ''
                                    "
                                    >{{ stockPct }}%</span
                                >
                            </div>
                            <div
                                class="h-1.5 overflow-hidden rounded-full bg-slate-100"
                            >
                                <div
                                    class="h-full rounded-full transition-all duration-700"
                                    :class="
                                        isOutOfStock
                                            ? 'bg-rose-400'
                                            : stockPct <= 25
                                              ? 'bg-amber-400'
                                              : 'bg-emerald-500'
                                    "
                                    :style="{ width: `${stockPct}%` }"
                                />
                            </div>
                        </div>
                        <div class="shrink-0">
                            <span
                                v-if="isOutOfStock"
                                class="flex items-center gap-1.5 rounded-lg bg-rose-50 px-3 py-1.5 text-[11px] font-semibold text-rose-600"
                            >
                                <AlertTriangle class="size-3.5" /> Habis
                            </span>
                            <span
                                v-else
                                class="flex items-center gap-1.5 rounded-lg bg-emerald-50 px-3 py-1.5 text-[11px] font-semibold text-emerald-600"
                            >
                                <BadgeCheck class="size-3.5" /> Tersedia
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TABS + CONTENT -->
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm">
                <!-- Tab bar -->
                <div
                    class="flex items-center gap-0.5 border-b border-slate-100 px-4 pt-1"
                >
                    <button
                        v-for="tab in tabs"
                        :key="tab.id"
                        type="button"
                        class="relative flex h-10 items-center gap-2 px-4 text-xs font-medium transition-all"
                        :class="
                            activeTab === tab.id
                                ? 'text-[#003628] after:absolute after:right-0 after:bottom-0 after:left-0 after:h-0.5 after:rounded-t after:bg-[#003628]'
                                : 'text-slate-400 hover:text-slate-600'
                        "
                        @click="activeTab = tab.id as any"
                    >
                        <component
                            :is="
                                tab.id === 'info'
                                    ? Info
                                    : tab.id === 'assigned'
                                      ? Users
                                      : tab.id === 'stock'
                                        ? RotateCcw
                                        : tab.id === 'files'
                                          ? FileText
                                          : History
                            "
                            class="size-3.5"
                        />
                        {{ tab.label }}
                        <span
                            v-if="tab.badge > 0"
                            class="rounded-full px-1.5 py-0.5 text-[9px] font-bold tabular-nums"
                            :class="
                                activeTab === tab.id
                                    ? 'bg-[#003628] text-white'
                                    : 'bg-slate-100 text-slate-400'
                            "
                            >{{ tab.badge }}</span
                        >
                    </button>
                </div>

                <!-- Tab content -->
                <div class="p-6">
                    <!-- INFO TAB -->
                    <div v-if="activeTab === 'info'" class="space-y-6">
                        <!-- Identifiers highlight -->
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div
                                class="flex items-center gap-4 rounded-xl border border-dashed border-slate-200 bg-slate-50/60 px-5 py-4"
                            >
                                <div
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white shadow-sm ring-1 ring-slate-200"
                                >
                                    <Hash class="size-4 text-[#003628]" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="text-[10px] font-semibold tracking-widest text-slate-400 uppercase"
                                    >
                                        Model No.
                                    </p>
                                    <p
                                        class="mt-0.5 font-mono text-sm font-bold text-slate-800 select-all"
                                    >
                                        {{ asset.model_number || '—' }}
                                    </p>
                                </div>
                            </div>
                            <div
                                class="flex items-center gap-4 rounded-xl border border-dashed border-slate-200 bg-slate-50/60 px-5 py-4"
                            >
                                <div
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white shadow-sm ring-1 ring-slate-200"
                                >
                                    <Hash class="size-4 text-slate-400" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="text-[10px] font-semibold tracking-widest text-slate-400 uppercase"
                                    >
                                        Item No.
                                    </p>
                                    <p
                                        class="mt-0.5 font-mono text-sm font-bold text-slate-800 select-all"
                                    >
                                        {{ asset.item_no || '—' }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Detail grid -->
                        <div
                            class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3"
                        >
                            <div
                                class="rounded-xl border border-slate-100 bg-slate-50/40 p-4"
                            >
                                <p
                                    class="mb-1 text-[10px] font-semibold tracking-widest text-slate-400 uppercase"
                                >
                                    Category
                                </p>
                                <p class="text-sm font-semibold text-slate-800">
                                    {{ asset.category || '—' }}
                                </p>
                            </div>
                            <div
                                class="rounded-xl border border-slate-100 bg-slate-50/40 p-4"
                            >
                                <p
                                    class="mb-1 text-[10px] font-semibold tracking-widest text-slate-400 uppercase"
                                >
                                    Manufacturer
                                </p>
                                <p class="text-sm font-semibold text-slate-800">
                                    {{ asset.manufacturer || '—' }}
                                </p>
                            </div>
                            <div
                                class="rounded-xl border border-slate-100 bg-slate-50/40 p-4"
                            >
                                <p
                                    class="mb-1 text-[10px] font-semibold tracking-widest text-slate-400 uppercase"
                                >
                                    Supplier
                                </p>
                                <p class="text-sm font-semibold text-slate-800">
                                    {{ asset.supplier || '—' }}
                                </p>
                            </div>
                            <div
                                class="rounded-xl border border-slate-100 bg-slate-50/40 p-4"
                            >
                                <p
                                    class="mb-1 text-[10px] font-semibold tracking-widest text-slate-400 uppercase"
                                >
                                    Location
                                </p>
                                <p class="text-sm font-semibold text-slate-800">
                                    {{ asset.location || '—' }}
                                </p>
                            </div>
                            <div
                                class="rounded-xl border border-slate-100 bg-slate-50/40 p-4"
                            >
                                <p
                                    class="mb-1 text-[10px] font-semibold tracking-widest text-slate-400 uppercase"
                                >
                                    Company
                                </p>
                                <p class="text-sm font-semibold text-slate-800">
                                    {{ asset.company || '—' }}
                                </p>
                            </div>
                            <div
                                class="rounded-xl border border-slate-100 bg-slate-50/40 p-4"
                            >
                                <p
                                    class="mb-1 text-[10px] font-semibold tracking-widest text-slate-400 uppercase"
                                >
                                    Min. QTY
                                </p>
                                <p class="text-sm font-semibold text-slate-800">
                                    {{ asset.min_qty ?? '—' }}
                                </p>
                            </div>
                        </div>

                        <!-- Purchase info -->
                        <div
                            class="rounded-xl border border-slate-100 bg-white p-5"
                        >
                            <p
                                class="mb-3 flex items-center gap-1.5 text-[11px] font-semibold tracking-widest text-slate-500 uppercase"
                            >
                                <Wallet class="size-3.5" /> Pembelian
                            </p>
                            <dl class="grid grid-cols-1 gap-2.5 sm:grid-cols-3">
                                <div
                                    class="flex items-center justify-between sm:flex-col sm:items-start sm:gap-0.5"
                                >
                                    <dt class="text-xs text-slate-400">
                                        No. Order
                                    </dt>
                                    <dd
                                        class="text-xs font-semibold text-slate-700"
                                    >
                                        {{ asset.order_number || '—' }}
                                    </dd>
                                </div>
                                <div
                                    class="flex items-center justify-between sm:flex-col sm:items-start sm:gap-0.5"
                                >
                                    <dt class="text-xs text-slate-400">
                                        Tanggal Beli
                                    </dt>
                                    <dd
                                        class="text-xs font-semibold text-slate-700"
                                    >
                                        {{ asset.purchase_date || '—' }}
                                    </dd>
                                </div>
                                <div
                                    class="flex items-center justify-between sm:flex-col sm:items-start sm:gap-0.5"
                                >
                                    <dt class="text-xs text-slate-400">
                                        Harga Satuan
                                    </dt>
                                    <dd
                                        class="text-xs font-semibold text-slate-900"
                                    >
                                        {{ asset.purchase_cost || '—' }}
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        <!-- Notes -->
                        <div
                            v-if="asset.notes"
                            class="rounded-xl border border-amber-100 bg-amber-50/40 p-4"
                        >
                            <p
                                class="mb-1.5 text-[10px] font-semibold tracking-widest text-amber-600/70 uppercase"
                            >
                                Catatan Administrator
                            </p>
                            <p class="text-sm leading-relaxed text-slate-600">
                                {{ asset.notes }}
                            </p>
                        </div>
                    </div>

                    <!-- ASSIGNED TAB -->
                    <div v-if="activeTab === 'assigned'">
                        <div
                            v-if="checkoutRecords.length > 0"
                            class="space-y-4"
                        >
                            <div
                                class="grid grid-cols-12 gap-4 pb-2 text-[10px] font-semibold tracking-widest text-slate-400 uppercase"
                            >
                                <div class="col-span-5">Penerima / Target</div>
                                <div class="col-span-4 hidden sm:block">
                                    Catatan / Konteks
                                </div>
                                <div class="col-span-3 text-right">
                                    Tanggal Checkout
                                </div>
                            </div>
                            <div
                                v-for="rec in paginatedCheckouts"
                                :key="rec.id"
                                class="-mx-2 grid grid-cols-12 items-center gap-4 rounded-lg px-2 py-3 transition-colors hover:bg-slate-50/60"
                            >
                                <div class="col-span-5 flex items-center gap-3">
                                    <div
                                        class="flex size-8 shrink-0 items-center justify-center overflow-hidden rounded-full bg-slate-100"
                                    >
                                        <img
                                            v-if="rec.image"
                                            :src="rec.image"
                                            class="size-full object-cover"
                                        />
                                        <User
                                            v-else
                                            class="size-4 text-slate-300"
                                        />
                                    </div>
                                    <div class="min-w-0">
                                        <p
                                            class="truncate text-sm font-semibold text-slate-800"
                                        >
                                            {{ rec.name }}
                                        </p>
                                        <p
                                            v-if="rec.secondary"
                                            class="truncate text-[10px] tracking-tighter text-slate-400 uppercase"
                                        >
                                            {{ rec.secondary }}
                                        </p>
                                    </div>
                                </div>
                                <div class="col-span-4 hidden sm:block">
                                    <span class="text-xs text-slate-500 italic"
                                        >"{{ rec.note || '-' }}"</span
                                    >
                                </div>
                                <div class="col-span-3 text-right">
                                    <span
                                        class="text-[11px] font-medium text-slate-400 uppercase tabular-nums"
                                        >{{ rec.date }}</span
                                    >
                                </div>
                            </div>
                            <!-- Checkout Pagination -->
                            <AppPagination
                                v-if="checkoutTotalPages > 1"
                                :current-page="checkoutPage"
                                :total-pages="checkoutTotalPages"
                                :items-per-page="checkoutPerPage"
                                :total-items="checkoutRecords.length"
                                @update:current-page="(page) => (checkoutPage = page)"
                            />
                        </div>
                        <div
                            v-else
                            class="flex flex-col items-center justify-center py-16 text-center"
                        >
                            <div
                                class="mb-3 flex size-12 items-center justify-center rounded-xl bg-slate-50"
                            >
                                <Users class="size-6 text-slate-200" />
                            </div>
                            <p class="text-sm font-medium text-slate-300">
                                Belum ada penugasan aktif (Stock in Inventory)
                            </p>
                        </div>
                    </div>

                    <!-- STOCK HISTORY TAB -->
                    <div v-if="activeTab === 'stock'">
                        <div
                            v-if="stockHistoryLoading"
                            class="flex items-center justify-center py-16"
                        >
                            <RefreshCw
                                class="size-6 animate-spin text-slate-300"
                            />
                        </div>
                        <div
                            v-else-if="stockHistory.length > 0"
                            class="space-y-3"
                        >
                            <div
                                v-for="rec in paginatedStock"
                                :key="rec.id"
                                class="flex flex-col gap-3 rounded-xl border border-slate-100 bg-slate-50/40 p-4 transition hover:border-slate-200 sm:flex-row sm:items-center"
                            >
                                <div
                                    class="flex items-center gap-3 sm:w-28 sm:shrink-0"
                                >
                                    <div
                                        class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600"
                                    >
                                        <Plus class="size-4" />
                                    </div>
                                    <div>
                                        <p
                                            class="text-sm font-bold text-slate-900"
                                        >
                                            +{{ rec.qty }}
                                        </p>
                                        <p
                                            class="text-[9px] font-medium tracking-widest text-slate-400 uppercase"
                                        >
                                            Unit
                                        </p>
                                    </div>
                                </div>
                                <div class="flex-1 space-y-0.5">
                                    <p
                                        class="text-xs font-semibold text-slate-700 uppercase"
                                    >
                                        PO: {{ rec.po_number || '—' }}
                                    </p>
                                    <p
                                        v-if="rec.notes"
                                        class="text-xs text-slate-500 italic"
                                    >
                                        "{{ rec.notes }}"
                                    </p>
                                    <p
                                        class="text-[10px] text-slate-400 uppercase"
                                    >
                                        {{ rec.created_by }} ·
                                        {{ rec.created_at }}
                                    </p>
                                </div>
                                <div
                                    class="flex items-center gap-2 sm:shrink-0"
                                >
                                    <span
                                        class="flex items-center gap-1 text-[10px] font-medium text-slate-400 uppercase"
                                    >
                                        <Calendar class="size-3" />
                                        {{ rec.purchase_date }}
                                    </span>
                                    <button
                                        v-if="
                                            rec.document_url &&
                                            /\.pdf$/i.test(rec.document_url)
                                        "
                                        type="button"
                                        class="flex h-7 items-center gap-1.5 rounded-lg bg-white px-3 text-[10px] font-semibold text-slate-600 shadow-sm ring-1 ring-slate-200 transition hover:bg-slate-50"
                                        @click="openPdfViewer(rec.document_url)"
                                    >
                                        <FileText class="size-3" /> Lihat PDF
                                    </button>
                                </div>
                            </div>
                            <!-- Stock History Pagination -->
                            <AppPagination
                                v-if="stockTotalPages > 1"
                                :current-page="stockPage"
                                :total-pages="stockTotalPages"
                                :items-per-page="stockPerPage"
                                :total-items="stockHistory.length"
                                @update:current-page="(page) => (stockPage = page)"
                            />
                        </div>
                        <div
                            v-else
                            class="flex flex-col items-center justify-center py-16 text-center"
                        >
                            <div
                                class="mb-3 flex size-12 items-center justify-center rounded-xl bg-slate-50"
                            >
                                <RotateCcw class="size-6 text-slate-200" />
                            </div>
                            <p class="text-sm font-medium text-slate-300">
                                Belum ada riwayat penambahan stok
                            </p>
                        </div>
                    </div>

                    <!-- FILES TAB -->
                    <div v-if="activeTab === 'files'">
                        <div class="mb-4 flex justify-end">
                            <button
                                type="button"
                                class="flex h-8 items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 text-xs font-medium text-slate-500 transition hover:bg-slate-50"
                                @click="showDocModal = true"
                            >
                                <Plus class="size-3.5" /> Upload Dokumen
                            </button>
                        </div>
                        <div v-if="assetFiles.length > 0" class="space-y-2">
                            <div
                                v-for="file in paginatedFiles"
                                :key="file.id"
                                class="group flex items-center gap-4 rounded-xl border border-slate-100 bg-slate-50/40 p-3.5 transition hover:border-slate-200 hover:bg-white"
                            >
                                <div
                                    class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-white shadow-sm ring-1 ring-slate-200 transition group-hover:bg-[#003628] group-hover:ring-[#003628]"
                                >
                                    <FileText
                                        class="size-4 text-slate-400 transition group-hover:text-white"
                                    />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="truncate text-sm font-semibold text-slate-800"
                                    >
                                        {{ file.filename }}
                                    </p>
                                    <p
                                        class="text-[10px] tracking-tighter text-slate-400 uppercase"
                                    >
                                        {{ file.date }} · {{ file.created_by }}
                                    </p>
                                </div>
                                <button
                                    v-if="/\.pdf$/i.test(file.filename)"
                                    type="button"
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white text-slate-400 shadow-sm ring-1 ring-slate-200 transition hover:bg-[#003628] hover:text-white hover:ring-[#003628]"
                                    @click="openPdfViewer(file.download_url)"
                                >
                                    <FileText class="size-4" />
                                </button>
                                <a
                                    v-else
                                    :href="file.download_url"
                                    target="_blank"
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white text-slate-400 shadow-sm ring-1 ring-slate-200 transition hover:bg-[#003628] hover:text-white hover:ring-[#003628]"
                                >
                                    <Download class="size-4" />
                                </a>
                            </div>
                            <!-- Files Pagination -->
                            <AppPagination
                                v-if="filesTotalPages > 1"
                                :current-page="filesPage"
                                :total-pages="filesTotalPages"
                                :items-per-page="filesPerPage"
                                :total-items="assetFiles.length"
                                @update:current-page="(page) => (filesPage = page)"
                            />
                        </div>
                        <div
                            v-else
                            class="flex flex-col items-center justify-center py-16 text-center"
                        >
                            <div
                                class="mb-3 flex size-12 items-center justify-center rounded-xl bg-slate-50"
                            >
                                <FileText class="size-6 text-slate-200" />
                            </div>
                            <p
                                class="text-sm font-medium tracking-widest text-slate-300 uppercase"
                            >
                                Belum ada dokumen terunggah
                            </p>
                        </div>
                    </div>

                    <!-- HISTORY TAB -->
                    <div v-if="activeTab === 'history'">
                        <div
                            v-if="activityHistory.length > 0"
                            class="space-y-3"
                        >
                            <div
                                v-for="log in paginatedHistory"
                                :key="log.id"
                                class="flex items-start gap-4 rounded-xl border border-slate-100 bg-slate-50/40 p-4 transition hover:border-slate-200 hover:bg-white"
                            >
                                <div
                                    class="flex size-8 shrink-0 items-center justify-center overflow-hidden rounded-full bg-slate-100 ring-2 ring-white"
                                >
                                    <img
                                        v-if="log.user_image"
                                        :src="log.user_image"
                                        class="size-full object-cover"
                                    />
                                    <User
                                        v-else
                                        class="size-4 text-slate-300"
                                    />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div
                                        class="mb-1 flex flex-wrap items-center gap-2"
                                    >
                                        <span
                                            class="text-sm font-semibold text-slate-800"
                                            >{{ log.user }}</span
                                        >
                                        <span
                                            class="rounded-md border px-2 py-0.5 text-[10px] font-semibold tracking-widest uppercase"
                                            :class="
                                                actionBadgeClass(
                                                    log.action_type,
                                                )
                                            "
                                        >
                                            {{ log.action_type }}
                                        </span>
                                        <span
                                            v-if="log.target"
                                            class="flex items-center gap-1 text-[10px] tracking-tighter text-slate-400 uppercase"
                                        >
                                            <ChevronRight class="size-3" />
                                            {{ log.target }}
                                        </span>
                                    </div>
                                    <p
                                        v-if="log.note && log.note !== '-'"
                                        class="text-xs text-slate-500 italic"
                                    >
                                        "{{ log.note }}"
                                    </p>
                                </div>
                                <span
                                    class="shrink-0 text-[10px] font-medium tracking-tighter text-slate-400 uppercase tabular-nums"
                                    >{{ log.date }}</span
                                >
                            </div>
                            <!-- History Pagination -->
                            <AppPagination
                                v-if="historyTotalPages > 1"
                                :current-page="historyPage"
                                :total-pages="historyTotalPages"
                                :items-per-page="historyPerPage"
                                :total-items="activityHistory.length"
                                @update:current-page="(page) => (historyPage = page)"
                            />
                        </div>
                        <div
                            v-else
                            class="flex flex-col items-center justify-center py-16 text-center"
                        >
                            <div
                                class="mb-3 flex size-12 items-center justify-center rounded-xl bg-slate-50"
                            >
                                <History class="size-6 text-slate-200" />
                            </div>
                            <p
                                class="text-sm font-medium tracking-widest text-slate-300 uppercase"
                            >
                                Belum ada aktivitas tercatat
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modals -->
        <UploadDocumentModal
            :show="showDocModal"
            :asset-id="asset.id"
            :asset-type="assetType"
            @close="showDocModal = false"
        />
        <AppPdfViewerModal
            :open="pdfViewerOpen"
            :url="pdfViewerUrl"
            :title="asset.name"
            @close="pdfViewerOpen = false"
        />
        <AddStockModal
            v-if="showStockModal"
            :show="showStockModal"
            :asset-id="asset.id"
            :asset-type="assetType"
            @close="showStockModal = false"
        />
    </AppLayout>
</template>
