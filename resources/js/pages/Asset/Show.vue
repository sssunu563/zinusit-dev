<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    FileText,
    Eye,
    Plus,
    LucidePlus,
    Mail,
    Package,
    ArrowLeft,
    Edit,
    Upload,
    FileDown,
    SlidersHorizontal,
    LayoutDashboard,
    Activity,
    Info,
    History,
    Settings,
    Wrench,
    ShieldCheck,
    Shield,
    ClipboardCheck,
    User,
    MapPin,
    Calendar,
    Monitor,
    Building,
    MapPinned,
    Hash,
    Tag,
    ClipboardList,
    Wallet,
    Download,
    ExternalLink,
    Pencil,
    RotateCcw,
    PlusCircle,
    Trash2,
    Folder,
    SearchCheck,
    Briefcase,
    Share2,
    ChevronDown,
} from 'lucide-vue-next';
import { computed, ref, onMounted } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import AddStockModal from '@/pages/Asset/Partials/AddStockModal.vue';
import AssetDetailSummary from '@/pages/Asset/Partials/AssetDetailSummary.vue';
import UploadDocumentModal from '@/pages/Asset/Partials/UploadDocumentModal.vue';
import AppPdfViewerModal from '@/components/AppPdfViewerModal.vue';
import AppPagination from '@/components/AppPagination.vue';
import AssetActivityDetailSheet, {
    type ActivityDetailItem,
} from '@/pages/Asset/Partials/AssetActivityDetailSheet.vue';
import AssetInfoSection from '@/pages/Asset/Partials/AssetInfoSection.vue';
import AssetActivityHistoryTable from '@/pages/Asset/Partials/AssetActivityHistoryTable.vue';
import AssetDocumentsTable from '@/pages/Asset/Partials/AssetDocumentsTable.vue';
import AssetMaintenancesTable from '@/pages/Asset/Partials/AssetMaintenancesTable.vue';
import type { BreadcrumbItem } from '@/types';

interface CustomField {
    name: string;
    value: string;
    format: string;
}

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
    rtd_location?: string;
    company?: string;
    supplier?: string;
    status?: string;
    status_name?: string;
    state_name?: string;
    status_type?: string;
    qty?: number;
    remaining_qty?: number;
    checked_out?: number;
    requestable?: boolean;
    image?: string;
    created_by?: string;
    assigned_to?: string;
    assigned_to_type?: string;
    assigned_to_username?: string;
    assigned_to_email?: string;
    assigned_to_jobtitle?: string;
    notes?: string;
    purchase_date?: string;
    purchase_cost?: string;
    order_number?: string;
    warranty_months?: string;
    warranty_expires?: string;
    asset_eol_date?: string;
    book_value?: string;
    last_audit_date?: string;
    next_audit_date?: string;
    last_checkout?: string;
    last_checkin?: string;
    expected_checkin?: string;
    checkin_counter?: number;
    checkout_counter?: number;
    byod?: boolean;
    custom_fields?: CustomField[];
    created_at?: string;
    updated_at?: string;
}

interface CheckoutRecord {
    id: number;
    name: string;
    secondary: string;
    email: string;
    company: string;
    location: string;
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

interface Props {
    assetType: string;
    assetTypeLabel: string;
    asset: AssetDetail;
    assetFiles: AssetFile[];
    checkoutRecords: CheckoutRecord[];
    activityHistory: ActivityRecord[];
}

const props = defineProps<Props>();

const isStockType = computed(
    () =>
        props.assetType === 'accessories' ||
        props.assetType === 'consumable' ||
        props.assetType === 'component',
);

const hasCheckoutTab = computed(() =>
    ['accessories', 'component', 'license', 'consumable'].includes(
        props.assetType,
    ),
);

const activeTab = ref<string>(
    props.assetType === 'assets' ? 'info' : 'assigned',
);

const showStockModal = ref(false);
const showDocModal = ref(false);
const pdfViewerOpen = ref(false);
const pdfViewerUrl = ref<string | null>(null);
const openPdfViewer = (url: string) => {
    const separator = url.includes('?') ? '&' : '?';
    pdfViewerUrl.value = `${url}${separator}preview=1`;
    pdfViewerOpen.value = true;
};

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
    if (a.includes('create') || a.includes('add')) return PlusCircle;
    if (a.includes('delete') || a.includes('destroy')) return Trash2;
    if (a.includes('stb') || a.includes('mutasi')) return Folder;
    if (a.includes('inspection')) return SearchCheck;
    return Activity;
};

// History pagination (client-side)
const activityPage = ref(1);
const activityPerPage = 15;
const paginatedActivityHistory = computed(() => {
    const start = (activityPage.value - 1) * activityPerPage;
    return props.activityHistory.slice(start, start + activityPerPage);
});
const activityTotalPages = computed(() =>
    Math.ceil(props.activityHistory.length / activityPerPage),
);

const formatChangedParts = (changed: string | null | undefined) => {
    if (!changed) return [];

    return changed.split(/\s+\|\|\s+/).map((part) => {
        const arrowIndex = part.indexOf(' -> ');
        if (arrowIndex === -1) {
            return { text: part, oldValue: null, newValue: null };
        }

        const beforeArrow = part.slice(0, arrowIndex);
        const labelSeparator = beforeArrow.indexOf(': ');

        return {
            label:
                labelSeparator === -1
                    ? ''
                    : beforeArrow.slice(0, labelSeparator),
            oldValue:
                labelSeparator === -1
                    ? beforeArrow
                    : beforeArrow.slice(labelSeparator + 2),
            newValue: part.slice(arrowIndex + 4),
            text: null,
        };
    });
};

// Checkout pagination (client-side)
const checkoutPage = ref(1);
const checkoutPerPage = 10;
const paginatedCheckouts = computed(() => {
    const start = (checkoutPage.value - 1) * checkoutPerPage;
    return props.checkoutRecords.slice(start, start + checkoutPerPage);
});
const checkoutTotalPages = computed(() =>
    Math.ceil(props.checkoutRecords.length / checkoutPerPage),
);

// Files pagination (client-side)
const filesPage = ref(1);
const filesPerPage = 10;
const paginatedFiles = computed(() => {
    const start = (filesPage.value - 1) * filesPerPage;
    return props.assetFiles.slice(start, start + filesPerPage);
});
const filesTotalPages = computed(() =>
    Math.ceil(props.assetFiles.length / filesPerPage),
);

// Lazy-loaded tab data (hardware only)
const tabData = ref<Record<string, any[]>>({});
const tabLoading = ref<Record<string, boolean>>({});
const tabDataPages = ref<Record<string, number>>({});
const tabDataPerPage = 15;

async function loadTab(tab: string) {
    activeTab.value = tab;
    const lazyTabs = ['licenses', 'components', 'sub_assets', 'maintenances'];
    if (!lazyTabs.includes(tab) || tab in tabData.value) return;
    tabLoading.value[tab] = true;
    tabDataPages.value[tab] = 1; // Reset to page 1 when loading new tab
    try {
        const res = await fetch(
            `/asset/item/${props.asset.id}/tab-data?type=${encodeURIComponent(props.assetType)}&tab=${tab}`,
        );
        tabData.value[tab] = await res.json();
    } catch {
        tabData.value[tab] = [];
    } finally {
        tabLoading.value[tab] = false;
    }
}

onMounted(() => {
    const urlTab = new URLSearchParams(window.location.search).get('tab');
    if (urlTab) {
        loadTab(urlTab);
    } else if (activeTab.value === 'maintenances') {
        loadTab('maintenances');
    }
});

// Helper function to get paginated tab data
const getPaginatedTabData = (tab: string) => {
    const data = tabData.value[tab] ?? [];
    const page = tabDataPages.value[tab] ?? 1;
    const start = (page - 1) * tabDataPerPage;
    return data.slice(start, start + tabDataPerPage);
};

// Helper function to get total pages for tab data
const getTabDataTotalPages = (tab: string) => {
    const data = tabData.value[tab] ?? [];
    return Math.ceil(data.length / tabDataPerPage);
};

const checkoutLabel = computed(() => {
    if (props.assetType === 'component') return 'Assets';
    if (props.assetType === 'license') return 'Assigned Seats';
    if (props.assetType === 'consumable') return 'Consumed By';
    return 'Checked Out';
});

const secondaryHeader = computed(() => {
    if (props.assetType === 'component') return 'Asset Tag';
    if (props.assetType === 'license') return 'Username';
    if (props.assetType === 'consumable') return 'Created By';
    return 'Username';
});

const noteHeader = computed(() => {
    if (props.assetType === 'component') return 'Location';
    return 'Note';
});

const actionBadgeClass = (action: string): string => {
    const a = action.toLowerCase();
    if (a.includes('checkout')) return 'bg-emerald-100 text-emerald-800';
    if (a.includes('checkin')) return 'bg-amber-100 text-amber-800';
    if (a.includes('update')) return 'bg-blue-100 text-blue-800';
    if (a.includes('create') || a.includes('add'))
        return 'bg-purple-100 text-purple-800';
    if (a.includes('delete') || a.includes('destroy'))
        return 'bg-red-100 text-red-800';
    if (a.includes('stb_complete')) return 'bg-indigo-100 text-indigo-800';
    return 'bg-muted text-muted-foreground';
};

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
    {
        title: props.asset.name || 'Detail',
        href: `/asset/item/${props.asset.id}?type=${encodeURIComponent(props.assetType)}`,
    },
]);

// Actions
const showActions = ref(false);

const normalizeAssetStatusForAction = (value?: string | null) => {
    const normalized = String(value ?? '')
        .trim()
        .toLowerCase();

    if (normalized.includes('active')) return 'active';
    if (
        normalized.includes('stock') ||
        normalized.includes('ready to deploy') ||
        normalized.includes('available') ||
        normalized.includes('deployable')
    ) {
        return 'stock';
    }
    if (
        normalized.includes('borrow') ||
        normalized.includes('on loan') ||
        normalized.includes('checked out')
    ) {
        return 'borrowed';
    }

    return 'unsupported';
};

const hardwareActionStatus = computed(() =>
    normalizeAssetStatusForAction(
        props.asset.status_name ||
            props.asset.state_name ||
            props.asset.status_type ||
            props.asset.status,
    ),
);
const canGenerateStb = computed(
    () =>
        ['assets', 'laptop'].includes(props.assetType) &&
        ['active', 'stock', 'borrowed'].includes(hardwareActionStatus.value),
);
const canGenerateLoan = computed(
    () =>
        ['assets', 'laptop'].includes(props.assetType) &&
        hardwareActionStatus.value === 'stock',
);
const canGenerateInspection = computed(
    () =>
        ['assets', 'laptop'].includes(props.assetType) &&
        hardwareActionStatus.value === 'active',
);
const generateStbLabel = computed(() =>
    ['active', 'borrowed'].includes(hardwareActionStatus.value)
        ? 'Generate STB IN'
        : 'Generate STB OUT',
);

const handleGenerateStb = () => {
    if (!canGenerateStb.value) return;
    showActions.value = false;

    const params = new URLSearchParams({
        documentType: 'handover',
        movementType: ['active', 'borrowed'].includes(
            hardwareActionStatus.value,
        )
            ? 'return'
            : 'out',
    });
    params.append('selectedAssetIds[]', String(props.asset.id));
    router.visit(`/stb/create?${params.toString()}`);
};

const handleGenerateLoan = () => {
    if (!canGenerateLoan.value) return;
    showActions.value = false;

    const params = new URLSearchParams({ movementType: 'out' });
    params.append('selectedAssetIds[]', String(props.asset.id));
    router.visit(`/peminjaman/create?${params.toString()}`);
};

const handleGenerateInspection = () => {
    if (!canGenerateInspection.value) return;
    showActions.value = false;

    router.visit(
        `/inspection/create?from_asset=${encodeURIComponent(String(props.asset.id))}`,
    );
};

function goBack() {
    window.location.href = `/asset?type=${encodeURIComponent(props.assetType)}`;
}

function editAsset() {
    window.location.href = `/asset/${props.asset.id}/edit?type=${encodeURIComponent(props.assetType)}`;
}
</script>

<template>
    <Head :title="`Detail ${assetTypeLabel}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="app-page-shell">
            <div
                class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_300px] xl:grid-cols-[minmax(0,1fr)_320px]"
            >
                <!-- Left: Main panel -->
                <div
                    class="overflow-hidden rounded-[28px] border border-slate-200/70 bg-white shadow-xl shadow-slate-200/50"
                >
                    <!-- HEADER (Premium Look) -->
                    <div
                        class="flex flex-col gap-6 border-b border-slate-100 bg-white px-8 py-6 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <!-- Brand -->
                        <div class="flex items-center gap-4">
                            <div
                                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-[#003628] shadow-lg shadow-[#003628]/25"
                            >
                                <Package class="size-6 text-white" />
                            </div>
                            <div class="min-w-0">
                                <h1
                                    class="max-w-[300px] truncate text-[17px] leading-none font-black tracking-tight text-[#003628] sm:max-w-[400px]"
                                >
                                    {{ asset.name || 'Asset Detail' }}
                                </h1>
                                <div class="mt-1.5 flex items-center gap-2">
                                    <span
                                        class="rounded-full bg-[#FFF2CC] px-2 py-0.5 text-[9px] font-black tracking-widest text-[#003628] uppercase"
                                    >
                                        {{ assetTypeLabel }}
                                    </span>
                                    <span
                                        class="text-[10px] font-bold text-slate-400"
                                    >
                                        ·
                                        {{
                                            asset.asset_tag ||
                                            asset.serial ||
                                            'No Tag'
                                        }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="flex h-9 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-[11px] font-black tracking-widest text-slate-500 uppercase transition-all hover:bg-slate-50 hover:text-slate-700"
                                @click="goBack"
                            >
                                <ArrowLeft class="size-3.5" /> Kembali
                            </button>
                            <button
                                type="button"
                                class="flex h-9 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-[11px] font-black tracking-widest text-slate-500 uppercase shadow-sm transition-all hover:bg-slate-50 hover:text-slate-700"
                                @click="editAsset"
                            >
                                <Edit class="size-3.5" /> Edit Detail
                            </button>
                            <div
                                v-if="
                                    canGenerateStb ||
                                    canGenerateLoan ||
                                    canGenerateInspection
                                "
                                class="relative"
                            >
                                <button
                                    type="button"
                                    class="flex h-9 items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 text-[11px] font-black tracking-widest text-emerald-700 uppercase transition-all hover:bg-emerald-100"
                                    aria-haspopup="menu"
                                    :aria-expanded="showActions"
                                    @click="showActions = !showActions"
                                >
                                    <Share2 class="size-3.5" /> Generate
                                    <ChevronDown class="size-3.5" />
                                </button>
                                <div
                                    v-if="showActions"
                                    class="absolute top-11 right-0 z-30 min-w-48 rounded-xl border border-slate-200 bg-white p-1.5 shadow-xl"
                                    role="menu"
                                >
                                    <button
                                        v-if="canGenerateStb"
                                        type="button"
                                        class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-[11px] font-bold text-slate-700 hover:bg-emerald-50 hover:text-emerald-700"
                                        role="menuitem"
                                        @click="handleGenerateStb"
                                    >
                                        <Share2 class="size-3.5" />
                                        {{ generateStbLabel }}
                                    </button>
                                    <button
                                        v-if="canGenerateLoan"
                                        type="button"
                                        class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-[11px] font-bold text-slate-700 hover:bg-amber-50 hover:text-amber-700"
                                        role="menuitem"
                                        @click="handleGenerateLoan"
                                    >
                                        <ClipboardList class="size-3.5" />
                                        Buat Peminjaman
                                    </button>
                                    <button
                                        v-if="canGenerateInspection"
                                        type="button"
                                        class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-[11px] font-bold text-slate-700 hover:bg-sky-50 hover:text-sky-700"
                                        role="menuitem"
                                        @click="handleGenerateInspection"
                                    >
                                        <SearchCheck class="size-3.5" />
                                        Pemeriksaan (Inspection)
                                    </button>
                                </div>
                            </div>
                            <button
                                type="button"
                                class="flex h-9 items-center gap-2 rounded-xl bg-[#003628] px-5 text-[11px] font-black tracking-widest text-white uppercase shadow-lg shadow-[#003628]/20 transition-all hover:opacity-90 active:scale-95"
                                @click="showDocModal = true"
                            >
                                <Upload class="size-3.5" /> Upload Dokumen
                            </button>
                        </div>
                    </div>

                    <!-- QUICK STATS BAR -->
                    <div
                        v-if="assetType === 'assets'"
                        class="grid grid-cols-2 gap-px border-b border-slate-100 bg-slate-100 md:grid-cols-3 lg:grid-cols-6"
                    >
                        <!-- Status -->
                        <div
                            class="flex items-center gap-3 bg-white px-6 py-4 transition-colors hover:bg-slate-50/50"
                        >
                            <div
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600"
                            >
                                <ShieldCheck class="size-4.5" />
                            </div>
                            <div class="min-w-0">
                                <p
                                    class="text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                >
                                    Status
                                </p>
                                <p
                                    class="truncate text-[11px] font-black text-slate-900"
                                >
                                    {{ asset.status || '-' }}
                                </p>
                            </div>
                        </div>
                        <!-- Assigned To -->
                        <div
                            class="flex items-center gap-3 bg-white px-6 py-4 transition-colors hover:bg-slate-50/50"
                        >
                            <div
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-sky-50 text-sky-600"
                            >
                                <User class="size-4.5" />
                            </div>
                            <div class="min-w-0">
                                <p
                                    class="text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                >
                                    Ditugaskan Kepada
                                </p>
                                <p
                                    class="truncate text-[11px] font-black text-slate-900"
                                >
                                    {{
                                        asset.assigned_to || 'Belum Ditugaskan'
                                    }}
                                </p>
                            </div>
                        </div>
                        <!-- Location -->
                        <div
                            class="flex items-center gap-3 bg-white px-6 py-4 transition-colors hover:bg-slate-50/50"
                        >
                            <div
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600"
                            >
                                <MapPin class="size-4.5" />
                            </div>
                            <div class="min-w-0">
                                <p
                                    class="text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                >
                                    Lokasi
                                </p>
                                <p
                                    class="truncate text-[11px] font-black text-slate-900"
                                >
                                    {{ asset.location || '-' }}
                                </p>
                            </div>
                        </div>
                        <!-- Purchase Date -->
                        <div
                            class="flex items-center gap-3 bg-white px-6 py-4 transition-colors hover:bg-slate-50/50"
                        >
                            <div
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-50 text-slate-400"
                            >
                                <Calendar class="size-4.5" />
                            </div>
                            <div class="min-w-0">
                                <p
                                    class="text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                >
                                    Tanggal Pembelian
                                </p>
                                <p
                                    class="truncate text-[11px] font-black text-slate-900"
                                >
                                    {{ asset.purchase_date || '-' }}
                                </p>
                            </div>
                        </div>
                        <!-- Warranty -->
                        <div
                            class="flex items-center gap-3 bg-white px-6 py-4 transition-colors hover:bg-slate-50/50"
                        >
                            <div
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl"
                                :class="
                                    asset.warranty_expires &&
                                    new Date(asset.warranty_expires) <
                                        new Date()
                                        ? 'bg-rose-50 text-rose-500'
                                        : 'bg-emerald-50 text-emerald-600'
                                "
                            >
                                <Shield class="size-4.5" />
                            </div>
                            <div class="min-w-0">
                                <p
                                    class="text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                >
                                    Garansi
                                </p>
                                <p
                                    class="truncate text-[11px] font-black"
                                    :class="
                                        asset.warranty_expires &&
                                        new Date(asset.warranty_expires) <
                                            new Date()
                                            ? 'text-rose-600'
                                            : 'text-emerald-600'
                                    "
                                >
                                    {{
                                        asset.warranty_expires
                                            ? new Date(asset.warranty_expires) <
                                              new Date()
                                                ? 'Kedaluwarsa'
                                                : 'Berlaku'
                                            : '-'
                                    }}
                                </p>
                            </div>
                        </div>
                        <!-- Last Audit -->
                        <div
                            class="flex items-center gap-3 bg-white px-6 py-4 transition-colors hover:bg-slate-50/50"
                        >
                            <div
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-50 text-slate-400"
                            >
                                <ClipboardCheck class="size-4.5" />
                            </div>
                            <div class="min-w-0">
                                <p
                                    class="text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                >
                                    Audit Terakhir
                                </p>
                                <p
                                    class="truncate text-[11px] font-black text-slate-900"
                                >
                                    {{ asset.last_audit_date || '-' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- TAB BAR -->
                    <div
                        class="flex min-h-[50px] items-center gap-1 border-b border-slate-100 bg-white px-8"
                    >
                        <!-- Main tabs left -->
                        <div class="flex items-center gap-1 self-stretch">
                            <!-- HARDWARE TABS: Info | History | Maintenances | Files -->
                            <template v-if="assetType === 'assets'">
                                <button
                                    v-for="t in [
                                        {
                                            key: 'info',
                                            label: 'Informasi',
                                            icon: Info,
                                        },
                                        {
                                            key: 'history',
                                            label: 'Riwayat Aktivitas',
                                            icon: History,
                                        },
                                        {
                                            key: 'maintenances',
                                            label: 'Pemeliharaan',
                                            icon: Wrench,
                                        },
                                        {
                                            key: 'files',
                                            label: 'Dokumen',
                                            icon: FileText,
                                        },
                                    ]"
                                    :key="t.key"
                                    type="button"
                                    class="group relative flex h-full items-center gap-2 border-b-2 px-5 text-[11px] font-black tracking-widest uppercase transition-all"
                                    :class="
                                        activeTab === t.key
                                            ? 'border-[#003628] text-[#003628]'
                                            : 'border-transparent text-slate-400 hover:text-slate-600'
                                    "
                                    @click="loadTab(t.key)"
                                >
                                    <component
                                        :is="t.icon"
                                        class="size-4"
                                        :class="
                                            activeTab === t.key
                                                ? 'text-[#003628]'
                                                : 'text-slate-300 group-hover:text-slate-500'
                                        "
                                    />
                                    {{ t.label }}
                                    <span
                                        v-if="t.key === 'history'"
                                        class="ml-2 rounded-full px-2 py-0.5 text-[10px] font-black tabular-nums"
                                        :class="
                                            activeTab === t.key
                                                ? 'bg-[#003628] text-white shadow-md shadow-[#003628]/20'
                                                : 'bg-slate-100 text-slate-500'
                                        "
                                        >{{ activityHistory.length }}</span
                                    >
                                </button>
                            </template>

                            <!-- NON-HARDWARE: simple info + checkout list, no tabs -->
                            <template v-else>
                                <button
                                    type="button"
                                    class="group relative flex h-full items-center gap-2 border-b-2 px-5 text-[11px] font-black tracking-widest uppercase transition-all"
                                    :class="
                                        activeTab === 'assigned'
                                            ? 'border-[#003628] text-[#003628]'
                                            : 'border-transparent text-slate-400 hover:text-slate-600'
                                    "
                                    @click="activeTab = 'assigned'"
                                >
                                    <LayoutDashboard
                                        class="size-4"
                                        :class="
                                            activeTab === 'assigned'
                                                ? 'text-[#003628]'
                                                : 'text-slate-300 group-hover:text-slate-500'
                                        "
                                    />
                                    {{ checkoutLabel }}
                                    <span
                                        class="ml-2 rounded-full px-2 py-0.5 text-[10px] font-black tabular-nums"
                                        :class="
                                            activeTab === 'assigned'
                                                ? 'bg-[#003628] text-white shadow-md shadow-[#003628]/20'
                                                : 'bg-slate-100 text-slate-500'
                                        "
                                    >
                                        {{ checkoutRecords.length }}
                                    </span>
                                </button>
                                <button
                                    type="button"
                                    class="group relative flex h-full items-center gap-2 border-b-2 px-5 text-[11px] font-black tracking-widest uppercase transition-all"
                                    :class="
                                        activeTab === 'documents'
                                            ? 'border-[#003628] text-[#003628]'
                                            : 'border-transparent text-slate-400 hover:text-slate-600'
                                    "
                                    @click="activeTab = 'documents'"
                                >
                                    <FileText
                                        class="size-4"
                                        :class="
                                            activeTab === 'documents'
                                                ? 'text-[#003628]'
                                                : 'text-slate-300 group-hover:text-slate-500'
                                        "
                                    />
                                    Dokumen
                                    <span
                                        class="ml-2 rounded-full px-2 py-0.5 text-[10px] font-black tabular-nums"
                                        :class="
                                            activeTab === 'documents'
                                                ? 'bg-[#003628] text-white shadow-md shadow-[#003628]/20'
                                                : 'bg-slate-100 text-slate-500'
                                        "
                                    >
                                        {{ assetFiles.length }}
                                    </span>
                                </button>
                                <button
                                    type="button"
                                    class="group relative flex h-full items-center gap-2 border-b-2 px-5 text-[11px] font-black tracking-widest uppercase transition-all"
                                    :class="
                                        activeTab === 'history'
                                            ? 'border-[#003628] text-[#003628]'
                                            : 'border-transparent text-slate-400 hover:text-slate-600'
                                    "
                                    @click="activeTab = 'history'"
                                >
                                    <History
                                        class="size-4"
                                        :class="
                                            activeTab === 'history'
                                                ? 'text-[#003628]'
                                                : 'text-slate-300 group-hover:text-slate-500'
                                        "
                                    />
                                    Riwayat
                                    <span
                                        class="ml-2 rounded-full px-2 py-0.5 text-[10px] font-black tabular-nums"
                                        :class="
                                            activeTab === 'history'
                                                ? 'bg-[#003628] text-white shadow-md shadow-[#003628]/20'
                                                : 'bg-slate-100 text-slate-500'
                                        "
                                    >
                                        {{ activityHistory.length }}
                                    </span>
                                </button>
                            </template>
                        </div>
                    </div>

                    <!-- TAB CONTENT -->
                    <div class="p-8">
                        <!-- NON-HARDWARE: stock summary + checkout list in one view -->
                        <div v-if="assetType !== 'assets'" class="space-y-8">
                            <!-- Stock summary row -->
                            <div class="grid grid-cols-1 gap-5 md:grid-cols-3">
                                <div
                                    class="rounded-[20px] border border-slate-200 bg-white p-5 shadow-sm transition-all hover:border-slate-300"
                                >
                                    <div class="mb-2 flex items-center gap-2">
                                        <div
                                            class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-50 text-slate-400"
                                        >
                                            <Package class="size-4" />
                                        </div>
                                        <span
                                            class="text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                            >Total Stok</span
                                        >
                                    </div>
                                    <p
                                        class="text-2xl font-black text-slate-900"
                                    >
                                        {{ asset.qty ?? 0 }}
                                    </p>
                                </div>
                                <div
                                    class="rounded-[20px] border border-slate-200 bg-white p-5 shadow-sm transition-all hover:border-slate-300"
                                >
                                    <div class="mb-2 flex items-center gap-2">
                                        <div
                                            class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50 text-amber-500"
                                        >
                                            <LayoutDashboard class="size-4" />
                                        </div>
                                        <span
                                            class="text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                            >Sedang Digunakan</span
                                        >
                                    </div>
                                    <p
                                        class="text-2xl font-black text-amber-600"
                                    >
                                        {{ asset.checked_out ?? 0 }}
                                    </p>
                                </div>
                                <div
                                    class="rounded-[20px] border border-slate-200 bg-white p-5 shadow-sm transition-all hover:border-slate-300"
                                >
                                    <div class="mb-2 flex items-center gap-2">
                                        <div
                                            class="flex h-8 w-8 items-center justify-center rounded-lg"
                                            :class="
                                                (asset.remaining_qty ?? 0) <= 0
                                                    ? 'bg-rose-50 text-rose-500'
                                                    : 'bg-emerald-50 text-emerald-500'
                                            "
                                        >
                                            <ShieldCheck class="size-4" />
                                        </div>
                                        <span
                                            class="text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                            >Tersedia</span
                                        >
                                    </div>
                                    <p
                                        class="text-2xl font-black"
                                        :class="
                                            (asset.remaining_qty ?? 0) <= 0
                                                ? 'text-rose-600'
                                                : 'text-emerald-600'
                                        "
                                    >
                                        {{ asset.remaining_qty ?? 0 }}
                                    </p>
                                </div>
                            </div>

                            <!-- Info fields -->
                            <div
                                class="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm"
                            >
                                <div
                                    class="grid grid-cols-1 divide-y divide-slate-100 md:grid-cols-2 md:divide-x md:divide-y-0"
                                >
                                    <div class="space-y-4 p-6">
                                        <div
                                            v-if="asset.category"
                                            class="flex items-center justify-between"
                                        >
                                            <span
                                                class="text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                                >Kategori</span
                                            >
                                            <span
                                                class="text-[12px] font-black text-slate-700"
                                                >{{ asset.category }}</span
                                            >
                                        </div>
                                        <div
                                            v-if="asset.manufacturer"
                                            class="flex items-center justify-between"
                                        >
                                            <span
                                                class="text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                                >Produsen / Merek</span
                                            >
                                            <span
                                                class="text-[12px] font-black text-slate-700"
                                                >{{ asset.manufacturer }}</span
                                            >
                                        </div>
                                        <div
                                            v-if="asset.location"
                                            class="flex items-center justify-between"
                                        >
                                            <span
                                                class="text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                                >Lokasi</span
                                            >
                                            <span
                                                class="text-[12px] font-black text-slate-700"
                                                >{{ asset.location }}</span
                                            >
                                        </div>
                                        <div
                                            v-if="asset.company"
                                            class="flex items-center justify-between"
                                        >
                                            <span
                                                class="text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                                >Perusahaan</span
                                            >
                                            <span
                                                class="text-[12px] font-black text-slate-700"
                                                >{{ asset.company }}</span
                                            >
                                        </div>
                                    </div>
                                    <div class="space-y-4 p-6">
                                        <div
                                            v-if="asset.purchase_cost"
                                            class="flex items-center justify-between"
                                        >
                                            <span
                                                class="text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                                >Harga Pembelian</span
                                            >
                                            <span
                                                class="text-[12px] font-black text-[#003628]"
                                                >{{ asset.purchase_cost }}</span
                                            >
                                        </div>
                                        <div
                                            v-if="asset.purchase_date"
                                            class="flex items-center justify-between"
                                        >
                                            <span
                                                class="text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                                >Tanggal Pembelian</span
                                            >
                                            <span
                                                class="text-[12px] font-black text-slate-700"
                                                >{{ asset.purchase_date }}</span
                                            >
                                        </div>
                                        <div
                                            v-if="asset.order_number"
                                            class="flex items-center justify-between"
                                        >
                                            <span
                                                class="text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                                >Nomor Pesanan</span
                                            >
                                            <span
                                                class="text-[12px] font-black text-slate-700"
                                                >{{ asset.order_number }}</span
                                            >
                                        </div>
                                        <div
                                            v-if="asset.model_number"
                                            class="flex items-center justify-between"
                                        >
                                            <span
                                                class="text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                                >Model No.</span
                                            >
                                            <span
                                                class="text-[12px] font-black text-slate-700"
                                                >{{ asset.model_number }}</span
                                            >
                                        </div>
                                    </div>
                                </div>
                                <div
                                    v-if="asset.notes"
                                    class="border-t border-slate-100 bg-slate-50/30 p-6"
                                >
                                    <span
                                        class="mb-2 block text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                        >Administrator Notes</span
                                    >
                                    <p
                                        class="text-[12px] leading-relaxed text-slate-600 italic"
                                    >
                                        "{{ asset.notes }}"
                                    </p>
                                </div>
                            </div>

                            <!-- Checkout list header -->
                            <div class="space-y-4">
                                <h3
                                    class="flex items-center gap-2 text-[11px] font-black tracking-widest text-[#003628] uppercase"
                                >
                                    <LayoutDashboard class="size-4" />
                                    {{ checkoutLabel }}
                                    <span
                                        class="ml-1 rounded-full bg-[#003628]/10 px-2 py-0.5 text-[10px] text-[#003628] tabular-nums"
                                        >{{ checkoutRecords.length }}</span
                                    >
                                </h3>

                                <div
                                    v-if="checkoutRecords.length"
                                    class="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm"
                                >
                                    <div class="divide-y divide-slate-100">
                                        <div
                                            v-for="rec in paginatedCheckouts"
                                            :key="rec.id"
                                            class="flex items-center gap-4 px-6 py-4 transition-colors hover:bg-slate-50/50"
                                        >
                                            <div
                                                class="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-slate-100 shadow-inner"
                                            >
                                                <img
                                                    v-if="rec.image"
                                                    :src="rec.image"
                                                    class="size-full object-cover"
                                                />
                                                <User
                                                    v-else
                                                    class="size-5 text-slate-300"
                                                />
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <p
                                                    class="text-[13px] font-black text-slate-900"
                                                >
                                                    {{ rec.name || '-' }}
                                                </p>
                                                <p
                                                    v-if="rec.secondary"
                                                    class="text-[10px] font-bold tracking-tighter text-slate-400 uppercase"
                                                >
                                                    {{ rec.secondary }}
                                                </p>
                                            </div>
                                            <div
                                                v-if="rec.email"
                                                class="hidden shrink-0 items-center gap-1.5 text-[11px] font-black tracking-tighter text-slate-400 uppercase md:flex"
                                            >
                                                <Mail class="size-3.5" />
                                                {{ rec.email }}
                                            </div>
                                            <div
                                                v-if="rec.location"
                                                class="hidden shrink-0 items-center gap-1.5 text-[11px] font-black tracking-tighter text-slate-400 uppercase lg:flex"
                                            >
                                                <MapPin class="size-3.5" />
                                                {{ rec.location }}
                                            </div>
                                            <div class="text-right">
                                                <span
                                                    class="text-[11px] font-black text-slate-400 uppercase tabular-nums"
                                                    >{{ rec.date }}</span
                                                >
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Pagination -->
                                    <AppPagination
                                        v-if="checkoutTotalPages > 1"
                                        :current-page="checkoutPage"
                                        :total-pages="checkoutTotalPages"
                                        :items-per-page="checkoutPerPage"
                                        :total-items="checkoutRecords.length"
                                        @update:current-page="
                                            (page) => (checkoutPage = page)
                                        "
                                    />
                                </div>
                                <div
                                    v-else
                                    class="rounded-[32px] border-2 border-dashed border-slate-100 py-24 text-center"
                                >
                                    <LayoutDashboard
                                        class="mx-auto mb-4 size-16 text-slate-100"
                                    />
                                    <p
                                        class="text-sm font-black tracking-widest text-slate-300 uppercase"
                                    >
                                        No active assignments recorded
                                    </p>
                                </div>
                            </div>
                        </div>
                        <!-- HARDWARE TABS: INFO | ACTIVITY LOG | MAINTENANCES | FILES -->
                        <template v-if="assetType === 'assets'">
                            <!-- 1. Info Tab -->
                            <div v-if="activeTab === 'info'">
                                <AssetInfoSection
                                    :asset="asset"
                                    :asset-type="assetType"
                                    :asset-type-label="assetTypeLabel"
                                />
                            </div>

                            <!-- 2. Activity Log Tab -->
                            <div v-else-if="activeTab === 'history'">
                                <AssetActivityHistoryTable
                                    :history="activityHistory"
                                    @open-pdf="openPdfViewer"
                                    @open-detail="openActivityDetail"
                                />
                            </div>

                            <!-- 3. Maintenances Tab -->
                            <div v-else-if="activeTab === 'maintenances'">
                                <AssetMaintenancesTable
                                    :maintenances="
                                        tabData['maintenances'] ?? []
                                    "
                                    :loading="
                                        Boolean(tabLoading['maintenances'])
                                    "
                                />
                            </div>

                            <!-- 4. Files Tab -->
                            <div
                                v-else-if="
                                    activeTab === 'files' ||
                                    activeTab === 'documents' ||
                                    activeTab === 'additional_files'
                                "
                            >
                                <AssetDocumentsTable
                                    :files="assetFiles"
                                    @upload-document="showDocModal = true"
                                    @open-pdf="openPdfViewer"
                                    @open-detail="openActivityDetail"
                                />
                            </div>
                        </template>

                        <!-- NON-HARDWARE FALLBACK TABS (if accessed via Show.vue) -->
                        <template v-else>
                            <!-- Files Tab -->
                            <div
                                v-if="
                                    activeTab === 'files' ||
                                    activeTab === 'documents' ||
                                    activeTab === 'additional_files'
                                "
                            >
                                <AssetDocumentsTable
                                    :files="assetFiles"
                                    @upload-document="showDocModal = true"
                                    @open-pdf="openPdfViewer"
                                    @open-detail="openActivityDetail"
                                />
                            </div>

                            <!-- History Tab -->
                            <div v-else-if="activeTab === 'history'">
                                <AssetActivityHistoryTable
                                    :history="activityHistory"
                                    @open-pdf="openPdfViewer"
                                    @open-detail="openActivityDetail"
                                />
                            </div>
                        </template>
                    </div>

                    <!-- Lazy-loaded tab panels (hardware only) -->
                    <!-- Shared loading/empty helper -->
                    <template v-if="assetType === 'assets'">
                        <!-- LICENSES tab -->
                        <!-- LICENSES tab (Hardware Only) - Modernized -->
                        <div v-if="activeTab === 'licenses'" class="space-y-6">
                            <h3
                                class="flex items-center gap-2 text-[11px] font-black tracking-widest text-[#003628] uppercase"
                            >
                                <Key class="size-4" /> Allocated Licenses
                                <span
                                    class="ml-1 rounded-full bg-[#003628]/10 px-2 py-0.5 text-[10px] text-[#003628] tabular-nums"
                                >
                                    {{ (tabData['licenses'] ?? []).length }}
                                </span>
                            </h3>

                            <div
                                class="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm"
                            >
                                <div class="overflow-x-auto">
                                    <table
                                        class="w-full border-collapse text-left"
                                    >
                                        <thead>
                                            <tr
                                                class="border-b border-slate-100 bg-slate-50/50"
                                            >
                                                <th
                                                    class="px-6 py-4 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                                >
                                                    License Name
                                                </th>
                                                <th
                                                    class="px-6 py-4 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                                >
                                                    Product Key
                                                </th>
                                                <th
                                                    class="px-6 py-4 text-right text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                                >
                                                    Expiration
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-50">
                                            <tr
                                                v-for="row in tabData[
                                                    'licenses'
                                                ] ?? []"
                                                :key="row.id"
                                                class="group transition-colors hover:bg-slate-50/50"
                                            >
                                                <td class="px-6 py-4">
                                                    <div
                                                        class="flex items-center gap-3"
                                                    >
                                                        <div
                                                            class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-violet-50 text-violet-600 shadow-sm transition-all group-hover:bg-violet-600 group-hover:text-white"
                                                        >
                                                            <Key
                                                                class="size-5"
                                                            />
                                                        </div>
                                                        <p
                                                            class="text-[13px] font-black text-slate-900"
                                                        >
                                                            {{
                                                                row.name || '-'
                                                            }}
                                                        </p>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <span
                                                        class="rounded border border-slate-100 bg-slate-50 px-2 py-1 font-mono text-[11px] font-bold text-slate-600 uppercase tabular-nums"
                                                        >{{
                                                            row.product_key ||
                                                            '-'
                                                        }}</span
                                                    >
                                                </td>
                                                <td
                                                    class="px-6 py-4 text-right"
                                                >
                                                    <span
                                                        class="text-[11px] font-black text-slate-700 uppercase tabular-nums"
                                                        >{{
                                                            row.expiration_date ||
                                                            'No Expiry'
                                                        }}</span
                                                    >
                                                </td>
                                            </tr>
                                            <tr
                                                v-if="
                                                    !(tabData['licenses'] ?? [])
                                                        .length
                                                "
                                            >
                                                <td
                                                    colspan="3"
                                                    class="px-6 py-20 text-center"
                                                >
                                                    <div
                                                        class="flex flex-col items-center gap-3"
                                                    >
                                                        <div
                                                            class="flex size-12 items-center justify-center rounded-2xl bg-slate-50 text-slate-300"
                                                        >
                                                            <Key
                                                                class="size-6"
                                                            />
                                                        </div>
                                                        <p
                                                            class="text-[11px] font-black tracking-widest text-slate-400 uppercase"
                                                        >
                                                            No licenses
                                                            allocated
                                                        </p>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- COMPONENTS tab -->
                        <!-- COMPONENTS tab (Hardware Only) - Modernized -->
                        <div
                            v-if="activeTab === 'components'"
                            class="space-y-6"
                        >
                            <h3
                                class="flex items-center gap-2 text-[11px] font-black tracking-widest text-[#003628] uppercase"
                            >
                                <Cpu class="size-4" /> Installed Components
                                <span
                                    class="ml-1 rounded-full bg-[#003628]/10 px-2 py-0.5 text-[10px] text-[#003628] tabular-nums"
                                >
                                    {{ (tabData['components'] ?? []).length }}
                                </span>
                            </h3>

                            <div
                                class="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm"
                            >
                                <div class="overflow-x-auto">
                                    <table
                                        class="w-full border-collapse text-left"
                                    >
                                        <thead>
                                            <tr
                                                class="border-b border-slate-100 bg-slate-50/50"
                                            >
                                                <th
                                                    class="px-6 py-4 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                                >
                                                    Component Detail
                                                </th>
                                                <th
                                                    class="px-6 py-4 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                                >
                                                    Serial Number
                                                </th>
                                                <th
                                                    class="px-6 py-4 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                                >
                                                    Qty
                                                </th>
                                                <th
                                                    class="px-6 py-4 text-right text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                                >
                                                    Cost
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-50">
                                            <tr
                                                v-for="row in tabData[
                                                    'components'
                                                ] ?? []"
                                                :key="row.id"
                                                class="group transition-colors hover:bg-slate-50/50"
                                            >
                                                <td class="px-6 py-4">
                                                    <div
                                                        class="flex items-center gap-3"
                                                    >
                                                        <div
                                                            class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600 shadow-sm transition-all group-hover:bg-amber-600 group-hover:text-white"
                                                        >
                                                            <Cpu
                                                                class="size-5"
                                                            />
                                                        </div>
                                                        <p
                                                            class="text-[13px] font-black text-slate-900"
                                                        >
                                                            {{
                                                                row.name || '-'
                                                            }}
                                                        </p>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <span
                                                        class="font-mono text-[11px] font-bold tracking-tighter text-slate-400 uppercase tabular-nums"
                                                        >{{
                                                            row.serial || '-'
                                                        }}</span
                                                    >
                                                </td>
                                                <td class="px-6 py-4">
                                                    <span
                                                        class="text-[12px] font-black text-slate-700 tabular-nums"
                                                        >{{
                                                            row.qty ?? 0
                                                        }}</span
                                                    >
                                                </td>
                                                <td
                                                    class="px-6 py-4 text-right"
                                                >
                                                    <span
                                                        class="text-[12px] font-black text-[#003628]"
                                                        >{{
                                                            row.purchase_cost ||
                                                            '-'
                                                        }}</span
                                                    >
                                                </td>
                                            </tr>
                                            <tr
                                                v-if="
                                                    !(
                                                        tabData['components'] ??
                                                        []
                                                    ).length
                                                "
                                            >
                                                <td
                                                    colspan="4"
                                                    class="px-6 py-20 text-center"
                                                >
                                                    <div
                                                        class="flex flex-col items-center gap-3"
                                                    >
                                                        <div
                                                            class="flex size-12 items-center justify-center rounded-2xl bg-slate-50 text-slate-300"
                                                        >
                                                            <Cpu
                                                                class="size-6"
                                                            />
                                                        </div>
                                                        <p
                                                            class="text-[11px] font-black tracking-widest text-slate-400 uppercase"
                                                        >
                                                            No components
                                                            installed
                                                        </p>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- ASSETS (sub-assets checked out TO this asset) tab -->
                        <!-- ASSETS (Sub-Assets) - Modernized -->
                        <div
                            v-if="activeTab === 'sub_assets'"
                            class="space-y-6"
                        >
                            <h3
                                class="flex items-center gap-2 text-[11px] font-black tracking-widest text-[#003628] uppercase"
                            >
                                <Box class="size-4" /> Dependent Sub-Assets
                                <span
                                    class="ml-1 rounded-full bg-[#003628]/10 px-2 py-0.5 text-[10px] text-[#003628] tabular-nums"
                                >
                                    {{ (tabData['sub_assets'] ?? []).length }}
                                </span>
                            </h3>

                            <div
                                class="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm"
                            >
                                <div class="overflow-x-auto">
                                    <table
                                        class="w-full border-collapse text-left"
                                    >
                                        <thead>
                                            <tr
                                                class="border-b border-slate-100 bg-slate-50/50"
                                            >
                                                <th
                                                    class="px-6 py-4 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                                >
                                                    Asset Info
                                                </th>
                                                <th
                                                    class="px-6 py-4 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                                >
                                                    Identifier
                                                </th>
                                                <th
                                                    class="px-6 py-4 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                                >
                                                    Status
                                                </th>
                                                <th
                                                    class="px-6 py-4 text-right text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                                >
                                                    Value
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-50">
                                            <tr
                                                v-for="row in tabData[
                                                    'sub_assets'
                                                ] ?? []"
                                                :key="row.id"
                                                class="group transition-colors hover:bg-slate-50/50"
                                            >
                                                <td class="px-6 py-4">
                                                    <div
                                                        class="flex items-center gap-3"
                                                    >
                                                        <div
                                                            class="size-10 shrink-0 overflow-hidden rounded-xl border border-slate-100 bg-slate-50 shadow-sm ring-2 ring-white"
                                                        >
                                                            <img
                                                                v-if="row.image"
                                                                :src="row.image"
                                                                class="size-full object-cover"
                                                            />
                                                            <div
                                                                v-else
                                                                class="flex size-full items-center justify-center text-slate-300"
                                                            >
                                                                <Box
                                                                    class="size-5"
                                                                />
                                                            </div>
                                                        </div>
                                                        <div class="min-w-0">
                                                            <p
                                                                class="truncate text-[13px] font-black text-slate-900"
                                                            >
                                                                {{
                                                                    row.name ||
                                                                    '-'
                                                                }}
                                                            </p>
                                                            <p
                                                                class="text-[10px] font-bold tracking-tighter text-slate-400 uppercase"
                                                            >
                                                                {{
                                                                    row.model ||
                                                                    'Unknown Model'
                                                                }}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="flex flex-col">
                                                        <span
                                                            class="text-[11px] font-black text-slate-700 uppercase tabular-nums"
                                                            >TAG:
                                                            {{
                                                                row.asset_tag ||
                                                                '-'
                                                            }}</span
                                                        >
                                                        <span
                                                            class="text-[9px] font-bold tracking-tighter text-slate-400 uppercase tabular-nums"
                                                            >SN:
                                                            {{
                                                                row.serial ||
                                                                '-'
                                                            }}</span
                                                        >
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <span
                                                        v-if="row.status"
                                                        class="rounded-lg border px-2 py-0.5 text-[9px] font-black tracking-widest uppercase"
                                                        :class="
                                                            row.status_type ===
                                                            'deployed'
                                                                ? 'border-emerald-100 bg-emerald-50 text-emerald-600'
                                                                : 'border-slate-100 bg-slate-50 text-slate-400'
                                                        "
                                                    >
                                                        {{ row.status }}
                                                    </span>
                                                </td>
                                                <td
                                                    class="px-6 py-4 text-right"
                                                >
                                                    <p
                                                        class="text-[12px] font-black text-slate-900"
                                                    >
                                                        {{
                                                            row.book_value ||
                                                            '-'
                                                        }}
                                                    </p>
                                                    <p
                                                        class="text-[9px] font-bold tracking-tighter text-slate-400 uppercase"
                                                    >
                                                        Cost:
                                                        {{
                                                            row.purchase_cost ||
                                                            '-'
                                                        }}
                                                    </p>
                                                </td>
                                            </tr>
                                            <tr
                                                v-if="
                                                    !(
                                                        tabData['sub_assets'] ??
                                                        []
                                                    ).length
                                                "
                                            >
                                                <td
                                                    colspan="4"
                                                    class="px-6 py-20 text-center"
                                                >
                                                    <div
                                                        class="flex flex-col items-center gap-3"
                                                    >
                                                        <div
                                                            class="flex size-12 items-center justify-center rounded-2xl bg-slate-50 text-slate-300"
                                                        >
                                                            <Box
                                                                class="size-6"
                                                            />
                                                        </div>
                                                        <p
                                                            class="text-[11px] font-black tracking-widest text-slate-400 uppercase"
                                                        >
                                                            No sub-assets
                                                            assigned
                                                        </p>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
                <!-- Right: Summary panel -->
                <div
                    class="overflow-hidden rounded-[28px] border border-slate-200/70 bg-white shadow-xl shadow-slate-200/50 lg:sticky lg:top-4 lg:self-start"
                >
                    <div class="p-5">
                        <AssetDetailSummary
                            :asset="asset"
                            :asset-type="assetType"
                            :asset-type-label="assetTypeLabel"
                            @add-stock="showStockModal = true"
                            @upload-document="showDocModal = true"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>

    <AddStockModal
        :show="showStockModal"
        :asset-id="asset.id"
        :asset-type="assetType"
        @close="showStockModal = false"
    />

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

    <!-- Asset Activity & Document Detail Sheet -->
    <AssetActivityDetailSheet
        v-if="selectedActivityItem"
        v-model:open="activitySheetOpen"
        :item="selectedActivityItem"
        @open-pdf="openPdfViewer"
        @update:open="
            (val) => {
                if (!val) selectedActivityItem = null;
            }
        "
    />
</template>
