<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
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
    Minus,
    Edit,
    History,
    ArrowLeft,
    Info,
    Users,
    Check,
    RotateCcw,
    FileCode,
    KeyRound,
    RefreshCw,
    Clock,
    BadgeCheck,
    Ban,
    ChevronRight,
    Share2,
    Eye,
    ExternalLink,
    Pencil,
    Folder,
    SearchCheck,
    Briefcase,
    ClipboardList,
    Activity,
} from 'lucide-vue-next';
import { ref, computed, watch, onMounted } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import AddStockModal from '@/pages/Asset/Partials/AddStockModal.vue';
import UploadDocumentModal from '@/pages/Asset/Partials/UploadDocumentModal.vue';
import AppPdfViewerModal from '@/components/AppPdfViewerModal.vue';
import AppPagination from '@/components/AppPagination.vue';
import AssetStockHistoryTable from '@/pages/Asset/Partials/AssetStockHistoryTable.vue';
import AssetAssignmentsTable from '@/pages/Asset/Partials/AssetAssignmentsTable.vue';
import AssetInfoSection from '@/pages/Asset/Partials/AssetInfoSection.vue';
import AssetDocumentsTable from '@/pages/Asset/Partials/AssetDocumentsTable.vue';
import AssetActivityHistoryTable from '@/pages/Asset/Partials/AssetActivityHistoryTable.vue';
import AssetActivityDetailSheet, {
    type ActivityDetailItem,
} from '@/pages/Asset/Partials/AssetActivityDetailSheet.vue';
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
    seats?: number;
    free_seats?: number;
    qty?: number;
    remaining_qty?: number;
    requestable?: boolean;
    image?: string;
    created_by?: string;
    notes?: string;
    purchase_date?: string;
    purchase_cost?: string;
    order_number?: string;
    license_name?: string;
    license_email?: string;
    expiration_date?: string;
    termination_date?: string;
    reassignable?: boolean;
    supplier?: string;
    min_qty?: number;
    po_number?: string;
    company?: string;
    maintained?: boolean;
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
    id: string | number;
    action_type: string;
    action_label?: string | null;
    user: string;
    user_id?: number | null;
    user_image?: string;
    target: string;
    target_type?: string;
    note: string;
    date: string;
    file_status?: 'success' | 'failed' | null;
    file_name?: string;
    file_url?: string | null;
    doc_no?: string | null;
    doc_url?: string | null;
    pdf_url?: string | null;
    form_type?: string | null;
    form_name?: string | null;
    role?: string | null;
    quantity?: number | string | null;
    changed?: string | null;
    log_meta?: Record<string, any> | null;
}

interface AssetFile {
    id: number;
    filename: string;
    download_url: string;
    created_by: string;
    date: string;
    notes: string;
    doc_no?: string | null;
    doc_url?: string | null;
    form_type?: string | null;
    form_name?: string | null;
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

// Activity Log & File Detail Sheet State
const activitySheetOpen = ref(false);
const selectedActivityItem = ref<ActivityDetailItem | null>(null);
const openActivityDetail = (item: ActivityDetailItem) => {
    selectedActivityItem.value = item;
    activitySheetOpen.value = true;
};

const getFormIcon = (formType?: string | null) => {
    switch ((formType || '').toLowerCase()) {
        case 'stb':
            return Folder;
        case 'peminjaman':
            return ClipboardList;
        case 'inspection':
            return SearchCheck;
        case 'ticket':
        case 'helpdesk':
            return Briefcase;
        default:
            return FileText;
    }
};

const getActionIcon = (actionType?: string | null) => {
    const a = (actionType || '').toLowerCase();
    if (a.includes('update') || a.includes('edit')) return Pencil;
    if (a.includes('upload') || a.includes('download')) return Download;
    if (a.includes('check')) return RotateCcw;
    if (a.includes('create') || a.includes('add')) return Plus;
    if (a.includes('delete') || a.includes('destroy')) return AlertTriangle;
    if (a.includes('stb') || a.includes('mutasi')) return Folder;
    if (a.includes('inspection')) return SearchCheck;
    return Activity;
};

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

onMounted(() => {
    loadStockHistory();
});



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
        label: 'Seats Assigned',
        badge: props.checkoutRecords.length,
    },
    { id: 'stock', label: 'Riwayat Kursi', badge: stockHistory.value.length },
    { id: 'files', label: 'Dokumen', badge: props.assetFiles.length },
    { id: 'history', label: 'Aktivitas', badge: props.activityHistory.length },
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

// STB (Handover) generation
const handleGenerateSTB = () => {
    const movementType = 'out'; // License/Accessories/Consumables are always 'out'
    const params = new URLSearchParams({
        documentType: 'handover',
        movementType,
        assetType: props.assetType, // Pass asset type to resolver
    });

    params.append('selectedAssetIds[]', String(props.asset.id));

    router.visit(`/stb/create?${params.toString()}`);
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
                            <FileCode class="size-5 text-white" />
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
                            <Plus class="size-3.5" /> Tambah Kursi
                        </button>
                        <a
                            :href="`/asset/${asset.id}/edit?type=${assetType}`"
                            class="flex h-8 items-center gap-1.5 rounded-lg bg-[#003628] px-4 text-xs font-semibold text-white transition hover:bg-[#003628]/90"
                        >
                            <Edit class="size-3.5" /> Edit
                        </a>
                    </div>
                </div>

                <!-- Seat stats bar -->
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
                                    class="text-[10px] font-medium text-slate-400"
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
                                    class="text-[10px] font-medium text-slate-400"
                                >
                                    Total Kursi
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
                                    class="text-[10px] font-medium text-slate-400"
                                >
                                    Terpakai
                                </p>
                            </div>
                        </div>
                        <div class="hidden max-w-xs flex-1 sm:block">
                            <div
                                class="mb-1.5 flex items-center justify-between text-[10px] font-medium text-slate-400"
                            >
                                <span>Utilisasi</span>
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
                    <div v-if="activeTab === 'info'">
                        <AssetInfoSection
                            :asset="asset"
                            :asset-type="assetType"
                            :asset-type-label="assetTypeLabel"
                        />
                    </div>

                    <!-- ASSIGNED TAB -->
                    <div v-if="activeTab === 'assigned'">
                        <AssetAssignmentsTable
                            :records="checkoutRecords"
                            :asset-type="assetType"
                            unit-label="Kursi"
                            title="Alokasi Kursi Lisensi (Seats)"
                        />
                    </div>

                    <!-- STOCK HISTORY TAB -->
                    <div v-if="activeTab === 'stock'">
                        <AssetStockHistoryTable
                            :stock-history="stockHistory"
                            :loading="stockHistoryLoading"
                            unit-label="Kursi"
                            title="Riwayat Kursi / Lisensi"
                            @add-stock="showStockModal = true"
                            @open-pdf="openPdfViewer"
                        />
                    </div>

                    <!-- FILES TAB -->
                    <div v-if="activeTab === 'files'">
                        <AssetDocumentsTable
                            :files="assetFiles"
                            @upload-document="showDocModal = true"
                            @open-pdf="openPdfViewer"
                            @open-detail="openActivityDetail"
                        />
                    </div>

                    <!-- HISTORY TAB -->
                    <div v-if="activeTab === 'history'">
                        <AssetActivityHistoryTable
                            :history="activityHistory"
                            @open-pdf="openPdfViewer"
                            @open-detail="openActivityDetail"
                        />
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

        <!-- Asset Activity & Document Detail Sheet -->
        <AssetActivityDetailSheet
            v-if="selectedActivityItem"
            v-model:open="activitySheetOpen"
            :item="selectedActivityItem"
            @open-pdf="openPdfViewer"
            @update:open="(val) => { if (!val) selectedActivityItem = null; }"
        />
    </AppLayout>
</template>
