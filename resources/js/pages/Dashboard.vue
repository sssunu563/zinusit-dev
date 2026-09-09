<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { useIntervalFn } from '@vueuse/core';
import {
    AlertCircle,
    AlertTriangle,
    ArrowUpRight,
    Boxes,
    Calendar,
    CheckCircle2,
    Clock,
    ExternalLink,
    FileCheck,
    FileText,
    Filter,
    History,
    KeyRound,
    LayoutDashboard,
    Monitor,
    Package,
    Plus,
    RefreshCw,
    RotateCcw,
    ShieldAlert,
    ShieldCheck,
    TicketIcon,
    TrendingUp,
    Wrench,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import type { ChartDataPoint } from '@/components/AppDashboardAreaChart.vue';
import AppDashboardAreaChart from '@/components/AppDashboardAreaChart.vue';
import AppDashboardDoughnut from '@/components/AppDashboardDoughnut.vue';
import DashboardHardwareStatus from '@/components/dashboard/DashboardHardwareStatus.vue';
import DashboardRestockWidget from '@/components/dashboard/DashboardRestockWidget.vue';
import DashboardWorkflowPipeline from '@/components/dashboard/DashboardWorkflowPipeline.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

interface Summary {
    totalAssets: number;
    totalStb: number;
    totalPeminjaman: number;
    totalInspections: number;
    totalTickets: number;
}

interface Stats {
    activeTickets: number;
    pendingApprovals: number;
    lowStockItems: number;
    resolvedToday: number;
    activeUsersToday: number;
    hardwareReady: number;
}

interface TicketItem {
    id: number;
    requester: string;
    category: string;
    priority: string;
    status: string;
    createdAt: string;
    href: string;
}

interface ActivityItem {
    type: string;
    label: string;
    title: string;
    time: string;
    tone: string;
    href: string;
}

interface AssetBreakdownItem {
    label: string;
    count: number;
    href: string;
    tone: string;
}

interface ConsumableItem {
    id: number;
    name: string;
    remaining: number;
    minimum?: number;
    location?: string;
    forecast: string;
    status: string;
    statusLabel: string;
    href: string;
}

interface WarrantyItem {
    id: number;
    name: string;
    tag: string;
    expiry: string;
    daysLeft: number;
    href: string;
}

interface PipelineStage {
    label: string;
    sublabel: string;
    count: number;
    tone: 'amber' | 'sky' | 'emerald' | 'purple';
}

interface ModuleApproval {
    key?: string;
    label: string;
    title?: string;
    description?: string;
    total: number;
    href: string;
    stages?: PipelineStage[];
    pending?: number;
    approved?: number;
    finalized?: number;
}

interface Queues {
    pendingStb: number;
    pendingPeminjaman: number;
    pendingInspection?: number;
    approvedNotFinal: number;
}

interface HardwareStatus {
    label: string;
    count: number;
    share: number;
    href: string;
}

interface StockHistoryItem {
    id: number;
    assetId: number;
    assetName: string;
    qty: number;
    poNumber: string | null;
    purchaseDate: string | null;
    createdAt: string;
    notes: string | null;
    href: string;
}

interface RestockLeader {
    assetId: number;
    assetName: string;
    totalQty: number;
    transactions: number;
    latestPurchaseDate: string | null;
    href: string;
}

interface AssetHighlight {
    label: string;
    value: number;
    detail: string;
    tone: string;
}

interface DateFilter {
    from: string;
    to: string;
    fromLabel: string;
    toLabel: string;
    isActive: boolean;
    defaultFrom: string;
    defaultTo: string;
}

const props = defineProps<{
    summary: Summary;
    stats: Stats;
    recentTickets: TicketItem[];
    recentActivities: ActivityItem[];
    assetBreakdown: AssetBreakdownItem[];
    consumables: {
        totalItems?: number;
        availableItems?: number;
        lowStockItems?: number;
        outOfStockItems?: number;
        totalUnitsRemaining?: number;
        focusItems: ConsumableItem[];
    };
    expiringWarranties: WarrantyItem[];
    expiringLicenses: WarrantyItem[];
    trend: ChartDataPoint[];
    generatedAt: string;
    moduleApprovals?: ModuleApproval[];
    queues?: Queues;
    assetHighlights?: AssetHighlight[];
    hardwareStatuses?: HardwareStatus[];
    stockHistory?: StockHistoryItem[];
    restockLeaders?: RestockLeader[];
    stockTrend?: any[];
    dateFilter?: DateFilter;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
];

const activeTab = ref<'all' | 'operations' | 'inventory'>('all');
const isRefreshing = ref(false);

const toneColor: Record<string, string> = {
    emerald: '#10b981',
    sky: '#0ea5e9',
    amber: '#f59e0b',
    rose: '#f43f5e',
    slate: '#64748b',
    purple: '#a855f7',
};

const selectedFrom = ref(props.dateFilter?.from || '');
const selectedTo = ref(props.dateFilter?.to || '');
const filterError = ref('');
const isFiltering = ref(false);

const isFilterActive = computed(() => Boolean(props.dateFilter?.isActive));

const formatYMD = (d: Date): string => {
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const applyDateFilter = (fromVal?: string, toVal?: string) => {
    const f = fromVal !== undefined ? fromVal : selectedFrom.value;
    const t = toVal !== undefined ? toVal : selectedTo.value;

    if (f && t && f > t) {
        filterError.value = 'Tanggal awal tidak boleh melebihi tanggal akhir';
        return;
    }

    filterError.value = '';
    isFiltering.value = true;

    router.get(
        '/dashboard',
        {
            date_from: f || undefined,
            date_to: t || undefined,
        },
        {
            preserveScroll: true,
            preserveState: false,
            onFinish: () => {
                isFiltering.value = false;
            },
        },
    );
};

const resetDateFilter = () => {
    selectedFrom.value = props.dateFilter?.defaultFrom || '';
    selectedTo.value = props.dateFilter?.defaultTo || '';
    filterError.value = '';
    isFiltering.value = true;

    router.get(
        '/dashboard',
        {},
        {
            preserveScroll: true,
            preserveState: false,
            onFinish: () => {
                isFiltering.value = false;
            },
        },
    );
};

const applyPreset = (
    preset: '7d' | '30d' | 'this_month' | '3m' | '6m' | 'this_year',
) => {
    const now = new Date();
    const to = formatYMD(now);
    let from = '';

    if (preset === '7d') {
        const d = new Date();
        d.setDate(d.getDate() - 7);
        from = formatYMD(d);
    } else if (preset === '30d') {
        const d = new Date();
        d.setDate(d.getDate() - 30);
        from = formatYMD(d);
    } else if (preset === 'this_month') {
        const d = new Date(now.getFullYear(), now.getMonth(), 1);
        from = formatYMD(d);
    } else if (preset === '3m') {
        const d = new Date();
        d.setMonth(d.getMonth() - 3);
        from = formatYMD(d);
    } else if (preset === '6m') {
        const d = new Date();
        d.setMonth(d.getMonth() - 6);
        from = formatYMD(d);
    } else if (preset === 'this_year') {
        const d = new Date(now.getFullYear(), 0, 1);
        from = formatYMD(d);
    }

    selectedFrom.value = from;
    selectedTo.value = to;
    applyDateFilter(from, to);
};

const getStatusBadge = (s?: string) => {
    switch (s?.toLowerCase()) {
        case 'open':
            return 'bg-sky-50 text-sky-700 border-sky-200/80';
        case 'in progress':
            return 'bg-amber-50 text-amber-700 border-amber-200/80';
        case 'closed':
        case 'resolved':
            return 'bg-emerald-50 text-emerald-700 border-emerald-200/80';
        default:
            return 'bg-slate-50 text-slate-600 border-slate-200/80';
    }
};

const getPriorityBadge = (p: string) => {
    switch (p) {
        case 'Urgent':
            return 'bg-rose-50 text-rose-700 border-rose-200/80';
        case 'High':
            return 'bg-amber-50 text-amber-700 border-amber-200/80';
        case 'Medium':
            return 'bg-sky-50 text-sky-700 border-sky-200/80';
        default:
            return 'bg-slate-50 text-slate-600 border-slate-200/80';
    }
};

const urgentTicketsCount = computed(() => {
    return props.recentTickets.filter(
        (t) => t.priority === 'Urgent' || t.priority === 'High',
    ).length;
});

// Primary Executive KPI Cards
const primaryMetrics = computed(() => [
    {
        label: 'Total Aset Terdaftar',
        value: props.summary.totalAssets,
        subtext: `${props.stats.hardwareReady} perangkat siap digunakan`,
        icon: Package,
        href: '/asset',
        accentColor: '#003628',
        cardBg: 'bg-white',
        iconBg: 'bg-[#003628]/10 text-[#003628]',
    },
    {
        label: 'Tiket Bantuan Aktif',
        value: props.stats.activeTickets,
        subtext: `${props.stats.resolvedToday} tiket diselesaikan hari ini`,
        icon: TicketIcon,
        href: '/helpdesk',
        accentColor: '#f59e0b',
        cardBg: 'bg-white',
        iconBg: 'bg-amber-50 text-amber-600',
    },
    {
        label: 'Menunggu Tanda Tangan',
        value: props.stats.pendingApprovals,
        subtext: `${props.queues?.approvedNotFinal ?? 0} aset sedang dipinjam pakai`,
        icon: Clock,
        href: '/stb',
        accentColor: '#0ea5e9',
        cardBg: 'bg-white',
        iconBg: 'bg-sky-50 text-sky-600',
    },
    {
        label: 'Stok Barang Habis Pakai Menipis',
        value: props.stats.lowStockItems,
        subtext: 'Barang yang hampir atau sudah habis',
        icon: AlertTriangle,
        href: '/asset/consumable',
        accentColor: '#f43f5e',
        cardBg: 'bg-white',
        iconBg:
            props.stats.lowStockItems > 0
                ? 'bg-rose-50 text-rose-600'
                : 'bg-emerald-50 text-emerald-600',
    },
]);

// Quick Module Counters
const moduleItems = computed(() => [
    {
        label: 'Serah Terima (STB)',
        count: props.summary.totalStb,
        pending: props.queues?.pendingStb ?? 0,
        href: '/stb',
        icon: FileText,
        color: '#003628',
    },
    {
        label: 'Peminjaman Aset',
        count: props.summary.totalPeminjaman,
        pending: props.queues?.pendingPeminjaman ?? 0,
        href: '/peminjaman',
        icon: FileCheck,
        color: '#0ea5e9',
    },
    {
        label: 'Inspeksi',
        count: props.summary.totalInspections,
        pending: props.queues?.pendingInspection ?? 0,
        href: '/inspection',
        icon: Wrench,
        color: '#a855f7',
    },
    {
        label: 'Tiket Bantuan',
        count: props.summary.totalTickets,
        pending: props.stats.activeTickets,
        href: '/helpdesk',
        icon: TicketIcon,
        color: '#f59e0b',
    },
]);

const refreshData = () => {
    isRefreshing.value = true;
    router.reload({
        only: [
            'summary',
            'stats',
            'recentTickets',
            'recentActivities',
            'assetBreakdown',
            'consumables',
            'expiringWarranties',
            'expiringLicenses',
            'trend',
            'moduleApprovals',
            'queues',
            'assetHighlights',
            'hardwareStatuses',
            'stockHistory',
            'restockLeaders',
            'stockTrend',
            'generatedAt',
            'dateFilter',
        ],
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            isRefreshing.value = false;
        },
    });
};

const { pause, resume } = useIntervalFn(() => {
    refreshData();
}, 30000);

onMounted(() => resume());
onUnmounted(() => pause());
</script>

<template>
    <Head title="Dashboard" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-[1800px] space-y-5 p-3 sm:p-4 md:p-6">
            <!-- TOP COMMAND HEADER -->
            <header
                class="flex flex-col justify-between gap-4 rounded-2xl border border-slate-100 bg-white p-5 shadow-xs sm:flex-row sm:items-center"
            >
                <div>
                    <div class="flex items-center gap-2">
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-bold text-emerald-700"
                        >
                            <span
                                class="h-2 w-2 animate-pulse rounded-full bg-emerald-500"
                            />
                            Sistem Aktif
                        </span>
                        <span class="text-xs text-slate-300">·</span>
                        <span class="text-xs font-semibold text-slate-500">
                            Sinkronisasi terakhir: {{ generatedAt }}
                        </span>
                    </div>
                    <h1
                        class="mt-1 text-xl font-black tracking-tight text-slate-900 sm:text-2xl"
                    >
                        Dashboard IT
                    </h1>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        @click="refreshData"
                        :disabled="isRefreshing"
                        class="inline-flex h-9 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 text-xs font-bold text-slate-700 shadow-2xs transition-all hover:bg-slate-50 hover:text-slate-900 active:scale-95 disabled:opacity-50"
                        title="Perbarui data"
                    >
                        <RefreshCw
                            class="size-3.5"
                            :class="{ 'animate-spin': isRefreshing }"
                        />
                        <span class="hidden sm:inline">Refresh</span>
                    </button>

                    <a
                        href="/check-assets"
                        target="_blank"
                        class="inline-flex h-9 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 text-xs font-bold text-slate-700 shadow-2xs transition-all hover:border-[#003628]/40 hover:text-[#003628]"
                    >
                        <ExternalLink class="size-3.5" />
                        <span>Portal Cek Aset</span>
                    </a>

                    <Link
                        href="/helpdesk"
                        class="inline-flex h-9 items-center gap-2 rounded-xl bg-[#003628] px-4 text-xs font-bold text-white shadow-sm shadow-[#003628]/20 transition-all hover:bg-[#004d39] active:scale-95"
                    >
                        <Plus class="size-4" />
                        <span>Buat Tiket</span>
                    </Link>
                </div>
            </header>

            <!-- DATE RANGE FILTER BAR -->
            <section
                class="rounded-2xl border border-slate-200/90 bg-white p-4 shadow-xs"
            >
                <div
                    class="flex flex-col gap-3.5 xl:flex-row xl:items-center xl:justify-between"
                >
                    <!-- Left: Title & Quick Presets -->
                    <div
                        class="flex flex-col gap-2.5 sm:flex-row sm:items-center"
                    >
                        <div class="flex items-center gap-2">
                            <div
                                class="flex h-8 w-8 items-center justify-center rounded-xl bg-[#003628]/10 text-[#003628]"
                            >
                                <Calendar class="size-4" />
                            </div>
                            <div>
                                <span class="text-xs font-bold text-slate-800"
                                    >Rentang Waktu:</span
                                >
                                <span
                                    v-if="dateFilter"
                                    class="ml-1.5 inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-700"
                                >
                                    {{ dateFilter.fromLabel }} –
                                    {{ dateFilter.toLabel }}
                                </span>
                            </div>
                        </div>

                        <!-- Presets buttons -->
                        <div class="flex flex-wrap items-center gap-1.5">
                            <button
                                type="button"
                                @click="applyPreset('7d')"
                                class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-600 transition-colors hover:border-[#003628] hover:text-[#003628] active:scale-95"
                            >
                                7 Hari
                            </button>
                            <button
                                type="button"
                                @click="applyPreset('30d')"
                                class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-600 transition-colors hover:border-[#003628] hover:text-[#003628] active:scale-95"
                            >
                                30 Hari
                            </button>
                            <button
                                type="button"
                                @click="applyPreset('this_month')"
                                class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-600 transition-colors hover:border-[#003628] hover:text-[#003628] active:scale-95"
                            >
                                Bulan Ini
                            </button>
                            <button
                                type="button"
                                @click="applyPreset('3m')"
                                class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-600 transition-colors hover:border-[#003628] hover:text-[#003628] active:scale-95"
                            >
                                3 Bulan
                            </button>
                            <button
                                type="button"
                                @click="applyPreset('6m')"
                                class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-600 transition-colors hover:border-[#003628] hover:text-[#003628] active:scale-95"
                            >
                                6 Bulan (Default)
                            </button>
                            <button
                                type="button"
                                @click="applyPreset('this_year')"
                                class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-600 transition-colors hover:border-[#003628] hover:text-[#003628] active:scale-95"
                            >
                                Tahun Ini
                            </button>
                        </div>
                    </div>

                    <!-- Right: Custom Date Pickers & Actions -->
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="flex items-center gap-1.5">
                            <input
                                type="date"
                                v-model="selectedFrom"
                                class="h-9 rounded-xl border border-slate-200 bg-slate-50/60 px-3 text-xs font-semibold text-slate-700 outline-hidden transition-all focus:border-[#003628] focus:bg-white focus:ring-1 focus:ring-[#003628]"
                                aria-label="Tanggal Awal"
                            />
                            <span class="text-xs font-bold text-slate-400"
                                >s/d</span
                            >
                            <input
                                type="date"
                                v-model="selectedTo"
                                class="h-9 rounded-xl border border-slate-200 bg-slate-50/60 px-3 text-xs font-semibold text-slate-700 outline-hidden transition-all focus:border-[#003628] focus:bg-white focus:ring-1 focus:ring-[#003628]"
                                aria-label="Tanggal Akhir"
                            />
                        </div>

                        <button
                            type="button"
                            @click="applyDateFilter()"
                            :disabled="isFiltering"
                            class="inline-flex h-9 items-center gap-1.5 rounded-xl bg-[#003628] px-3.5 text-xs font-bold text-white shadow-xs transition-all hover:bg-[#004d39] active:scale-95 disabled:opacity-50"
                        >
                            <Filter class="size-3.5" />
                            <span>Terapkan</span>
                        </button>

                        <button
                            v-if="
                                isFilterActive ||
                                (dateFilter &&
                                    (selectedFrom !== dateFilter.defaultFrom ||
                                        selectedTo !== dateFilter.defaultTo))
                            "
                            type="button"
                            @click="resetDateFilter"
                            :disabled="isFiltering"
                            class="inline-flex h-9 items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-600 transition-all hover:bg-slate-50 hover:text-slate-900 active:scale-95 disabled:opacity-50"
                            title="Kembali ke 6 bulan default"
                        >
                            <RotateCcw class="size-3.5" />
                            <span>Reset</span>
                        </button>
                    </div>
                </div>

                <!-- Feedback / Warning if Date Error -->
                <div
                    v-if="filterError"
                    class="mt-2.5 flex items-center gap-1.5 text-xs font-bold text-rose-600"
                >
                    <AlertCircle class="size-3.5 shrink-0" />
                    <span>{{ filterError }}</span>
                </div>

                <!-- Active Filter Notice -->
                <div
                    v-if="isFilterActive"
                    class="mt-3 flex items-center justify-between rounded-xl border border-emerald-200/80 bg-emerald-50/80 px-3 py-2 text-xs font-medium text-emerald-900"
                >
                    <div class="flex items-center gap-2">
                        <span
                            class="h-2 w-2 animate-ping rounded-full bg-emerald-500"
                        />
                        <span>
                            Filter tanggal kustom aktif (<strong>{{
                                dateFilter?.fromLabel
                            }}</strong>
                            sampai <strong>{{ dateFilter?.toLabel }}</strong
                            >). Menampilkan data tren, tiket, aktivitas, dan
                            riwayat stok dalam periode ini.
                        </span>
                    </div>
                    <button
                        type="button"
                        @click="resetDateFilter"
                        class="ml-2 inline-flex items-center gap-1 text-xs font-bold text-emerald-800 underline hover:text-emerald-950"
                    >
                        <X class="size-3" /> Hapus Filter
                    </button>
                </div>
            </section>

            <!-- SMART ACTION-NEEDED BANNER -->
            <div
                v-if="stats.pendingApprovals > 0 || urgentTicketsCount > 0"
                class="flex flex-col items-start justify-between gap-3 rounded-2xl border border-amber-200/80 bg-linear-to-r from-amber-50/80 via-white to-amber-50/40 p-4 shadow-xs sm:flex-row sm:items-center"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-amber-500 text-white shadow-xs"
                    >
                        <AlertCircle class="size-5" />
                    </div>
                    <div>
                        <h2
                            class="text-xs font-bold tracking-wider text-amber-900 uppercase"
                        >
                            Perhatian Diperlukan Segera
                        </h2>
                        <p class="text-xs text-amber-700">
                            Terdapat
                            <strong
                                v-if="stats.pendingApprovals > 0"
                                class="underline"
                            >
                                {{ stats.pendingApprovals }} dokumen menunggu
                                tanda tangan
                            </strong>
                            <span
                                v-if="
                                    stats.pendingApprovals > 0 &&
                                    urgentTicketsCount > 0
                                "
                            >
                                dan
                            </span>
                            <strong
                                v-if="urgentTicketsCount > 0"
                                class="underline"
                            >
                                {{ urgentTicketsCount }} tiket prioritas tinggi
                            </strong>
                            dalam antrean.
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Link
                        v-if="urgentTicketsCount > 0"
                        href="/helpdesk"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-amber-300 bg-white px-3 py-1.5 text-xs font-bold text-amber-800 transition-all hover:bg-amber-50"
                    >
                        Buka Tiket Bantuan <ArrowUpRight class="size-3.5" />
                    </Link>
                </div>
            </div>

            <!-- PRIMARY EXECUTIVE KPI CARDS (4 COLUMNS) -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <Link
                    v-for="metric in primaryMetrics"
                    :key="metric.label"
                    :href="metric.href"
                    class="group relative flex flex-col justify-between overflow-hidden rounded-2xl border border-t-4 border-slate-100 bg-white p-5 shadow-xs transition-all duration-200 hover:-translate-y-0.5 hover:border-slate-200 hover:shadow-md"
                    :style="{ borderTopColor: metric.accentColor }"
                >
                    <div class="flex items-center justify-between">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-xl"
                            :class="metric.iconBg"
                        >
                            <component :is="metric.icon" class="size-5" />
                        </div>
                        <span
                            class="flex h-7 w-7 items-center justify-center rounded-full bg-slate-50 text-slate-400 transition-colors group-hover:bg-[#003628]/10 group-hover:text-[#003628]"
                        >
                            <ArrowUpRight class="size-4" />
                        </span>
                    </div>

                    <div class="mt-4">
                        <div
                            class="text-3xl font-black tracking-tight text-slate-900"
                        >
                            {{ metric.value }}
                        </div>
                        <div
                            class="mt-1 text-xs font-bold tracking-wider text-slate-500 uppercase"
                        >
                            {{ metric.label }}
                        </div>
                        <div
                            class="mt-1.5 text-[11px] font-medium text-slate-400"
                        >
                            {{ metric.subtext }}
                        </div>
                    </div>
                </Link>
            </div>

            <!-- QUICK MODULE SHORTCUTS -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <Link
                    v-for="mod in moduleItems"
                    :key="mod.label"
                    :href="mod.href"
                    class="group flex items-center justify-between rounded-xl border border-slate-100 bg-white px-4 py-3 shadow-2xs transition-all hover:border-slate-200 hover:shadow-sm"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-lg"
                            :style="{
                                backgroundColor: mod.color + '15',
                                color: mod.color,
                            }"
                        >
                            <component :is="mod.icon" class="size-4" />
                        </div>
                        <div>
                            <div
                                class="text-[11px] font-bold text-slate-700 group-hover:text-slate-900"
                            >
                                {{ mod.label }}
                            </div>
                            <div class="text-xs font-semibold text-slate-400">
                                {{ mod.count }} total
                            </div>
                        </div>
                    </div>

                    <span
                        v-if="mod.pending > 0"
                        class="flex h-5 min-w-[22px] items-center justify-center rounded-full px-1.5 text-[10px] font-black text-white"
                        :style="{ backgroundColor: mod.color }"
                        title="Butuh perhatian"
                    >
                        {{ mod.pending }}
                    </span>
                    <CheckCircle2 v-else class="size-4 text-emerald-500" />
                </Link>
            </div>

            <!-- VIEW SELECTOR TABS -->
            <div
                class="flex items-center justify-between border-b border-slate-200/80 pb-3"
            >
                <div
                    class="inline-flex flex-wrap items-center gap-1 rounded-2xl border border-slate-200/60 bg-slate-100/90 p-1 shadow-2xs"
                >
                    <button
                        type="button"
                        @click="activeTab = 'all'"
                        class="flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-bold transition-all"
                        :class="
                            activeTab === 'all'
                                ? 'bg-[#003628] text-white shadow-xs'
                                : 'text-slate-600 hover:bg-white/60 hover:text-slate-900'
                        "
                    >
                        <LayoutDashboard class="size-3.5" />
                        <span>Ringkasan (Semua)</span>
                    </button>
                    <button
                        type="button"
                        @click="activeTab = 'operations'"
                        class="flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-bold transition-all"
                        :class="
                            activeTab === 'operations'
                                ? 'bg-[#003628] text-white shadow-xs'
                                : 'text-slate-600 hover:bg-white/60 hover:text-slate-900'
                        "
                    >
                        <FileCheck class="size-3.5" />
                        <span>Operasional</span>
                    </button>
                    <button
                        type="button"
                        @click="activeTab = 'inventory'"
                        class="flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-bold transition-all"
                        :class="
                            activeTab === 'inventory'
                                ? 'bg-[#003628] text-white shadow-xs'
                                : 'text-slate-600 hover:bg-white/60 hover:text-slate-900'
                        "
                    >
                        <Boxes class="size-3.5" />
                        <span>Inventaris & Perangkat</span>
                    </button>
                </div>
            </div>

            <!-- MAIN GRID CONTENT AREA -->
            <div class="grid grid-cols-1 gap-5 xl:grid-cols-[1fr_380px]">
                <!-- LEFT CONTENT (OPERATIONAL, CHARTS, WORKFLOW) -->
                <div class="space-y-5">
                    <!-- SECTION 1: TREND OPERASIONAL CHART (Visible in 'all' and 'operations') -->
                    <section
                        v-if="activeTab === 'all' || activeTab === 'operations'"
                        class="rounded-2xl border border-slate-100 bg-white p-5 shadow-xs"
                    >
                        <div
                            class="mb-4 flex flex-col justify-between gap-2 sm:flex-row sm:items-center"
                        >
                            <div>
                                <h2
                                    class="flex items-center gap-2 text-sm font-bold tracking-tight text-slate-900"
                                >
                                    <TrendingUp class="size-4 text-[#003628]" />
                                    Tren Aktivitas & Operasional IT
                                </h2>
                                <p class="text-xs text-slate-400">
                                    Analisis volume tiket, STB, dan peminjaman
                                    selama 6 bulan terakhir
                                </p>
                            </div>
                        </div>

                        <AppDashboardAreaChart :data="trend" :height="260" />
                    </section>

                    <!-- SECTION 2: WORKFLOW & APPROVAL PIPELINE (STB & PEMINJAMAN) -->
                    <DashboardWorkflowPipeline
                        v-if="
                            (activeTab === 'all' ||
                                activeTab === 'operations') &&
                            moduleApprovals
                        "
                        :module-approvals="moduleApprovals"
                    />

                    <!-- SECTION 3: RECENT TICKETS & RESOLUTION STATS -->
                    <div
                        v-if="activeTab === 'all' || activeTab === 'operations'"
                        class="grid grid-cols-1 gap-4 md:grid-cols-[1fr_260px]"
                    >
                        <!-- Recent Tickets List -->
                        <section
                            class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-xs"
                        >
                            <div
                                class="flex items-center justify-between border-b border-slate-100 px-5 py-3.5"
                            >
                                <div class="flex items-center gap-2">
                                    <div
                                        class="flex h-6 w-6 items-center justify-center rounded-lg bg-amber-50 text-amber-700"
                                    >
                                        <TicketIcon class="size-3.5" />
                                    </div>
                                    <h2
                                        class="text-xs font-bold tracking-wider text-slate-900 uppercase"
                                    >
                                        Tiket Bantuan Terkini
                                    </h2>
                                </div>
                                <Link
                                    href="/helpdesk"
                                    class="inline-flex items-center gap-1 text-xs font-semibold text-[#003628] hover:underline"
                                >
                                    Semua Tiket <ArrowUpRight class="size-3" />
                                </Link>
                            </div>

                            <div class="divide-y divide-slate-50">
                                <Link
                                    v-for="ticket in recentTickets"
                                    :key="ticket.id"
                                    :href="ticket.href"
                                    class="group flex items-center justify-between px-5 py-3 transition-colors hover:bg-slate-50/70"
                                >
                                    <div
                                        class="flex min-w-0 items-center gap-3"
                                    >
                                        <div
                                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#003628]/10 text-xs font-black text-[#003628]"
                                        >
                                            {{
                                                ticket.requester
                                                    .charAt(0)
                                                    .toUpperCase()
                                            }}
                                        </div>
                                        <div class="min-w-0">
                                            <p
                                                class="truncate text-xs font-bold text-slate-800 group-hover:text-[#003628]"
                                            >
                                                {{ ticket.requester }}
                                            </p>
                                            <p
                                                class="mt-0.5 text-[11px] text-slate-400"
                                            >
                                                {{ ticket.category }} ·
                                                {{ ticket.createdAt }}
                                            </p>
                                        </div>
                                    </div>
                                    <div
                                        class="ml-3 flex shrink-0 items-center gap-1.5"
                                    >
                                        <span
                                            v-if="ticket.status"
                                            class="rounded-md border px-2 py-0.5 text-xs font-semibold"
                                            :class="
                                                getStatusBadge(ticket.status)
                                            "
                                        >
                                            {{ ticket.status }}
                                        </span>
                                        <span
                                            class="rounded-md border px-2 py-0.5 text-xs font-bold tracking-wider uppercase"
                                            :class="
                                                getPriorityBadge(
                                                    ticket.priority,
                                                )
                                            "
                                        >
                                            {{ ticket.priority }}
                                        </span>
                                    </div>
                                </Link>

                                <div
                                    v-if="!recentTickets.length"
                                    class="py-10 text-center text-xs font-medium text-slate-400"
                                >
                                    Tidak ada tiket aktif saat ini
                                </div>
                            </div>
                        </section>

                        <!-- Resolution Quick Metrics -->
                        <div class="space-y-3">
                            <div
                                class="rounded-2xl border border-slate-100 bg-white p-4 shadow-xs"
                            >
                                <div class="flex items-center justify-between">
                                    <span
                                        class="text-xs font-bold tracking-wider text-slate-400 uppercase"
                                    >
                                        Selesai Hari Ini
                                    </span>
                                    <CheckCircle2
                                        class="size-4 text-emerald-500"
                                    />
                                </div>
                                <div
                                    class="mt-2 text-3xl font-black text-slate-900"
                                >
                                    {{ stats.resolvedToday }}
                                </div>
                                <div
                                    class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-slate-100"
                                >
                                    <div
                                        class="h-full rounded-full bg-[#003628]"
                                        :style="{
                                            width: `${Math.min(stats.resolvedToday * 20, 100)}%`,
                                        }"
                                    />
                                </div>
                                <p class="mt-1.5 text-[11px] text-slate-400">
                                    Tiket ditutup oleh tim IT
                                </p>
                            </div>

                            <div
                                class="rounded-2xl border border-slate-100 bg-white p-4 shadow-xs"
                            >
                                <div class="flex items-center justify-between">
                                    <span
                                        class="text-xs font-bold tracking-wider text-slate-400 uppercase"
                                    >
                                        Hardware Siap
                                    </span>
                                    <Monitor class="size-4 text-emerald-500" />
                                </div>
                                <div
                                    class="mt-2 text-3xl font-black text-emerald-600"
                                >
                                    {{ stats.hardwareReady }}
                                </div>
                                <p class="mt-1 text-[11px] text-slate-400">
                                    Unit siap deploy ke user
                                </p>
                            </div>

                            <div
                                class="rounded-2xl border border-slate-100 bg-white p-4 shadow-xs"
                            >
                                <div class="flex items-center justify-between">
                                    <span
                                        class="text-xs font-bold tracking-wider text-slate-400 uppercase"
                                    >
                                        Pengguna Aktif
                                    </span>
                                    <span
                                        class="h-2 w-2 rounded-full bg-sky-500"
                                    />
                                </div>
                                <div
                                    class="mt-2 text-3xl font-black text-sky-600"
                                >
                                    {{ stats.activeUsersToday }}
                                </div>
                                <p class="mt-1 text-[11px] text-slate-400">
                                    User terautentikasi hari ini
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 4: HARDWARE STATUS DISTRIBUTION (Visible in 'all' and 'inventory') -->
                    <DashboardHardwareStatus
                        v-if="
                            (activeTab === 'all' ||
                                activeTab === 'inventory') &&
                            hardwareStatuses
                        "
                        :hardware-statuses="hardwareStatuses"
                        :highlights="assetHighlights"
                    />

                    <!-- SECTION 5: CONSUMABLE FORECAST & PO RESTOCK LOG (Visible in 'all' and 'inventory') -->
                    <DashboardRestockWidget
                        v-if="
                            (activeTab === 'all' ||
                                activeTab === 'inventory') &&
                            consumables.focusItems
                        "
                        :focus-items="consumables.focusItems"
                        :stock-history="stockHistory || []"
                        :restock-leaders="restockLeaders"
                    />
                </div>

                <!-- RIGHT SIDEBAR (ASSET COMPOSITION, EXPIRING WARRANTIES, TIMELINE) -->
                <aside class="space-y-5">
                    <!-- Asset Doughnut Breakdown -->
                    <section
                        class="rounded-2xl border border-slate-100 bg-white p-5 shadow-xs"
                    >
                        <div class="mb-3 flex items-start justify-between">
                            <div>
                                <h2
                                    class="text-xs font-bold tracking-wider text-slate-900 uppercase"
                                >
                                    Komposisi Tipe Aset
                                </h2>
                                <p class="text-xs text-slate-400">
                                    Total {{ summary.totalAssets }} aset di
                                    database
                                </p>
                            </div>
                            <Link
                                href="/asset"
                                class="inline-flex items-center gap-1 text-xs font-semibold text-[#003628] hover:underline"
                            >
                                Detail <ArrowUpRight class="size-3" />
                            </Link>
                        </div>

                        <div class="my-4 flex justify-center">
                            <AppDashboardDoughnut
                                :data="assetBreakdown"
                                :total="summary.totalAssets"
                                :size="180"
                                :strokeWidth="24"
                            />
                        </div>

                        <div class="space-y-1 divide-y divide-slate-50">
                            <Link
                                v-for="item in assetBreakdown"
                                :key="item.label"
                                :href="item.href"
                                class="group flex items-center justify-between rounded-lg px-2 py-2 transition-colors hover:bg-slate-50/70"
                            >
                                <div class="flex items-center gap-2.5">
                                    <span
                                        class="h-2.5 w-2.5 shrink-0 rounded-full"
                                        :style="{
                                            backgroundColor:
                                                toneColor[item.tone] ??
                                                '#94a3b8',
                                        }"
                                    />
                                    <span
                                        class="text-xs font-semibold text-slate-600 group-hover:text-slate-900"
                                    >
                                        {{ item.label }}
                                    </span>
                                </div>
                                <span class="text-xs font-black text-slate-900">
                                    {{ item.count }}
                                </span>
                            </Link>
                        </div>
                    </section>

                    <!-- Expiring Warranties Card -->
                    <section
                        class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-xs"
                    >
                        <div
                            class="flex items-center justify-between border-b border-slate-100 px-5 py-3.5"
                        >
                            <div class="flex items-center gap-2">
                                <div
                                    class="flex h-6 w-6 items-center justify-center rounded-lg bg-rose-50 text-rose-600"
                                >
                                    <ShieldAlert class="size-3.5" />
                                </div>
                                <h2
                                    class="text-xs font-bold tracking-wider text-slate-900 uppercase"
                                >
                                    Garansi Akan Berakhir (&le; 30 Hari)
                                </h2>
                            </div>
                            <span
                                v-if="expiringWarranties.length"
                                class="flex h-5 min-w-[20px] items-center justify-center rounded-full bg-rose-500 px-1.5 text-[10px] font-bold text-white"
                            >
                                {{ expiringWarranties.length }}
                            </span>
                        </div>

                        <div class="divide-y divide-slate-50">
                            <Link
                                v-for="asset in expiringWarranties"
                                :key="asset.id"
                                :href="asset.href"
                                class="group flex items-center gap-3 px-5 py-3 transition-colors hover:bg-slate-50/70"
                            >
                                <div
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-rose-50 text-rose-500 transition-all group-hover:bg-rose-500 group-hover:text-white"
                                >
                                    <Monitor class="size-3.5" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="truncate text-xs font-bold text-slate-800"
                                    >
                                        {{ asset.name }}
                                    </p>
                                    <p
                                        class="mt-0.5 text-[10px] font-semibold text-rose-500"
                                    >
                                        Exp: {{ asset.expiry }} · Sisa
                                        {{ asset.daysLeft }} hari
                                    </p>
                                </div>
                            </Link>

                            <div
                                v-if="!expiringWarranties.length"
                                class="px-5 py-8 text-center"
                            >
                                <div
                                    class="inline-flex items-center gap-1.5 text-xs font-bold text-emerald-600"
                                >
                                    <ShieldCheck class="size-4" /> Semua garansi
                                    hardware aman
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Expiring Licenses Card -->
                    <section
                        class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-xs"
                    >
                        <div
                            class="flex items-center justify-between border-b border-slate-100 px-5 py-3.5"
                        >
                            <div class="flex items-center gap-2">
                                <div
                                    class="flex h-6 w-6 items-center justify-center rounded-lg bg-amber-50 text-amber-600"
                                >
                                    <KeyRound class="size-3.5" />
                                </div>
                                <h2
                                    class="text-xs font-bold tracking-wider text-slate-900 uppercase"
                                >
                                    Lisensi Berakhir (&le; 60 Hari)
                                </h2>
                            </div>
                            <span
                                v-if="expiringLicenses.length"
                                class="flex h-5 min-w-[20px] items-center justify-center rounded-full bg-amber-500 px-1.5 text-[10px] font-bold text-white"
                            >
                                {{ expiringLicenses.length }}
                            </span>
                        </div>

                        <div class="divide-y divide-slate-50">
                            <Link
                                v-for="license in expiringLicenses"
                                :key="license.id"
                                :href="license.href"
                                class="group flex items-center gap-3 px-5 py-3 transition-colors hover:bg-slate-50/70"
                            >
                                <div
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-500 transition-all group-hover:bg-amber-500 group-hover:text-white"
                                >
                                    <KeyRound class="size-3.5" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="truncate text-xs font-bold text-slate-800"
                                    >
                                        {{ license.name }}
                                    </p>
                                    <p
                                        class="mt-0.5 text-[10px] font-semibold text-amber-600"
                                    >
                                        Berakhir: {{ license.expiry }} · Sisa
                                        {{ license.daysLeft }} hari
                                    </p>
                                </div>
                            </Link>

                            <div
                                v-if="!expiringLicenses.length"
                                class="px-5 py-8 text-center"
                            >
                                <div
                                    class="inline-flex items-center gap-1.5 text-xs font-bold text-emerald-600"
                                >
                                    <ShieldCheck class="size-4" /> Semua lisensi
                                    masih berlaku
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Real-time Activity Timeline -->
                    <section
                        class="rounded-2xl border border-slate-100 bg-white p-5 shadow-xs"
                    >
                        <div class="mb-4 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div
                                    class="flex h-6 w-6 items-center justify-center rounded-lg bg-[#003628]/10 text-[#003628]"
                                >
                                    <History class="size-3.5" />
                                </div>
                                <h2
                                    class="text-xs font-bold tracking-wider text-slate-900 uppercase"
                                >
                                    Aktivitas Terbaru
                                </h2>
                            </div>
                        </div>

                        <div class="relative space-y-4">
                            <div
                                class="absolute top-2 bottom-2 left-[7px] w-px bg-slate-100"
                            />
                            <div
                                v-for="(activity, i) in recentActivities"
                                :key="i"
                                class="group relative pl-6"
                            >
                                <div
                                    class="absolute top-1 left-0 z-10 h-3.5 w-3.5 rounded-full border-2 border-white bg-slate-200 shadow-2xs transition-colors group-hover:bg-[#003628]"
                                    :style="{
                                        backgroundColor: toneColor[
                                            activity.tone
                                        ]
                                            ? toneColor[activity.tone] + '40'
                                            : undefined,
                                    }"
                                />
                                <div class="space-y-0.5">
                                    <div
                                        class="flex items-center justify-between gap-2"
                                    >
                                        <span
                                            class="text-[10px] font-bold tracking-wider uppercase"
                                            :style="{
                                                color:
                                                    toneColor[activity.tone] ??
                                                    '#64748b',
                                            }"
                                        >
                                            {{ activity.label }}
                                        </span>
                                        <span
                                            class="shrink-0 text-[10px] text-slate-400"
                                        >
                                            {{ activity.time }}
                                        </span>
                                    </div>
                                    <p
                                        class="line-clamp-1 text-xs font-semibold text-slate-800"
                                    >
                                        {{ activity.title }}
                                    </p>
                                    <Link
                                        :href="activity.href"
                                        class="inline-flex items-center gap-1 text-[10px] font-bold text-slate-400 uppercase hover:text-[#003628]"
                                    >
                                        Buka Detail
                                        <ArrowUpRight class="size-2.5" />
                                    </Link>
                                </div>
                            </div>

                            <div
                                v-if="!recentActivities.length"
                                class="py-6 text-center text-xs font-medium text-slate-400"
                            >
                                Belum ada aktivitas transaksi
                            </div>
                        </div>
                    </section>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>
