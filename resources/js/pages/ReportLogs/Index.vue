<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    LucideBarChart as ReportIcon,
    LucideHistory as HistoryIcon,
    LucideServer as ServerIcon,
    LucideCamera as CctvIcon,
    LucideWifi as NetworkIcon,
    LucideShield as InfraIcon,
} from 'lucide-vue-next';
import { computed, reactive, watch, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import ReportLogsTable from '@/pages/ReportLogs/Partials/ReportLogsTable.vue';
import ReportLogDetailSheet, { type ReportLogItem } from '@/pages/ReportLogs/Partials/ReportLogDetailSheet.vue';
import type { BreadcrumbItem } from '@/types';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface ReportCategoryOption {
    key: string;
    label: string;
}

interface FilterOptions {
    admins: string[];
    actions: string[];
    reports: ReportCategoryOption[];
}

interface StatsSummary {
    total: number;
    server: number;
    cctv: number;
    bandwidth: number;
    uptime: number;
    all_reports: number;
}

const props = defineProps<{
    logs: {
        data: ReportLogItem[];
        links: PaginationLink[];
        total: number;
        from: number | null;
        to: number | null;
    };
    filters: {
        search?: string;
        filter_report?: string;
        filter_admin?: string;
        filter_action?: string;
        from_date?: string;
        to_date?: string;
    };
    filter_options: FilterOptions;
    stats: StatsSummary;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Report Logs', href: '/report-logs' },
];

const filterForm = reactive({
    search: props.filters.search || '',
    filter_report: props.filters.filter_report || '',
    filter_admin: props.filters.filter_admin || '',
    filter_action: props.filters.filter_action || '',
    from_date: props.filters.from_date || '',
    to_date: props.filters.to_date || '',
});

let debounceTimer: ReturnType<typeof setTimeout> | null = null;

watch(
    () => [
        filterForm.search,
        filterForm.filter_report,
        filterForm.filter_admin,
        filterForm.filter_action,
        filterForm.from_date,
        filterForm.to_date,
    ],
    () => {
        if (debounceTimer) {
            clearTimeout(debounceTimer);
        }

        debounceTimer = setTimeout(() => {
            router.get(
                '/report-logs',
                {
                    search: filterForm.search || undefined,
                    filter_report: filterForm.filter_report || undefined,
                    filter_admin: filterForm.filter_admin || undefined,
                    filter_action: filterForm.filter_action || undefined,
                    from_date: filterForm.from_date || undefined,
                    to_date: filterForm.to_date || undefined,
                },
                {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                },
            );
        }, 300);
    },
);

const summaryText = computed(() => {
    if (!props.logs.total) {
        return 'Belum ada log aktivitas report.';
    }

    return `Menampilkan ${props.logs.from ?? 0}-${props.logs.to ?? 0} dari ${props.logs.total} entri log`;
});

const activeFilterCount = computed(
    () =>
        [
            filterForm.search,
            filterForm.filter_report,
            filterForm.filter_admin,
            filterForm.filter_action,
            filterForm.from_date,
            filterForm.to_date,
        ].filter(Boolean).length,
);

const exportUrl = computed(() => {
    const params = new URLSearchParams();

    if (filterForm.search) params.set('search', filterForm.search);
    if (filterForm.filter_report) params.set('filter_report', filterForm.filter_report);
    if (filterForm.filter_admin) params.set('filter_admin', filterForm.filter_admin);
    if (filterForm.filter_action) params.set('filter_action', filterForm.filter_action);
    if (filterForm.from_date) params.set('from_date', filterForm.from_date);
    if (filterForm.to_date) params.set('to_date', filterForm.to_date);

    const queryString = params.toString();
    return queryString ? `/report-logs/export?${queryString}` : '/report-logs/export';
});

const toInputDate = (date: Date) => date.toISOString().slice(0, 10);

const buildDatePresets = () => {
    const today = new Date();
    const startOfToday = new Date(today);
    const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
    const last7Days = new Date(today);

    last7Days.setDate(today.getDate() - 6);

    return {
        today: {
            from: toInputDate(startOfToday),
            to: toInputDate(startOfToday),
        },
        last7Days: {
            from: toInputDate(last7Days),
            to: toInputDate(today),
        },
        thisMonth: {
            from: toInputDate(startOfMonth),
            to: toInputDate(today),
        },
    };
};

const datePresets = buildDatePresets();

const applyDatePreset = (preset: keyof typeof datePresets) => {
    filterForm.from_date = datePresets[preset].from;
    filterForm.to_date = datePresets[preset].to;
};

const clearDateFilters = () => {
    filterForm.from_date = '';
    filterForm.to_date = '';
};

const isPresetActive = (preset: keyof typeof datePresets) =>
    filterForm.from_date === datePresets[preset].from &&
    filterForm.to_date === datePresets[preset].to;

// Side Sheet State
const sheetOpen = ref(false);
const selectedLog = ref<ReportLogItem | null>(null);

const openDetail = (log: ReportLogItem) => {
    selectedLog.value = log;
    sheetOpen.value = true;
};
</script>

<template>
    <Head title="Report Logs" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="app-page-shell">
            <!-- Header Section -->
            <header class="mb-6 flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div class="space-y-1">
                    <div class="flex items-center gap-2 text-[10px] font-black tracking-widest text-[#003628] uppercase mb-2">
                        <ReportIcon class="size-3" />
                        Audit Modul & Aktivitas Report
                    </div>
                    <h1 class="text-3xl font-black tracking-tight text-slate-900 lg:text-4xl">
                        Log Aktivitas <span class="text-[#003628] italic">Report</span>
                    </h1>
                </div>

                <div class="flex items-center gap-3">
                    <div class="h-12 w-12 rounded-2xl border border-slate-200 bg-white shadow-sm flex items-center justify-center">
                        <HistoryIcon class="size-5 text-[#003628]" />
                    </div>
                </div>
            </header>

            <!-- Stats Overview Cards -->
            <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-5 gap-3.5 mb-6">
                <button
                    type="button"
                    @click="filterForm.filter_report = ''"
                    class="p-4 rounded-2xl border transition-all text-left group"
                    :class="filterForm.filter_report === '' ? 'bg-[#003628] text-white border-[#003628] shadow-lg shadow-emerald-950/20' : 'bg-white text-slate-800 border-slate-200/70 hover:border-slate-300 shadow-sm'"
                >
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[9px] font-black uppercase tracking-widest" :class="filterForm.filter_report === '' ? 'text-emerald-300' : 'text-slate-400'">Semua Report</span>
                        <ReportIcon class="size-4" :class="filterForm.filter_report === '' ? 'text-emerald-200' : 'text-slate-400'" />
                    </div>
                    <p class="text-xl font-black tabular-nums">{{ stats.total }}</p>
                </button>

                <button
                    type="button"
                    @click="filterForm.filter_report = 'server'"
                    class="p-4 rounded-2xl border transition-all text-left group"
                    :class="filterForm.filter_report === 'server' ? 'bg-[#003628] text-white border-[#003628] shadow-lg shadow-emerald-950/20' : 'bg-white text-slate-800 border-slate-200/70 hover:border-slate-300 shadow-sm'"
                >
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[9px] font-black uppercase tracking-widest" :class="filterForm.filter_report === 'server' ? 'text-emerald-300' : 'text-slate-400'">Server Operation</span>
                        <ServerIcon class="size-4" :class="filterForm.filter_report === 'server' ? 'text-emerald-200' : 'text-slate-400'" />
                    </div>
                    <p class="text-xl font-black tabular-nums">{{ stats.server }}</p>
                </button>

                <button
                    type="button"
                    @click="filterForm.filter_report = 'cctv'"
                    class="p-4 rounded-2xl border transition-all text-left group"
                    :class="filterForm.filter_report === 'cctv' ? 'bg-[#003628] text-white border-[#003628] shadow-lg shadow-emerald-950/20' : 'bg-white text-slate-800 border-slate-200/70 hover:border-slate-300 shadow-sm'"
                >
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[9px] font-black uppercase tracking-widest" :class="filterForm.filter_report === 'cctv' ? 'text-emerald-300' : 'text-slate-400'">CCTV Operation</span>
                        <CctvIcon class="size-4" :class="filterForm.filter_report === 'cctv' ? 'text-emerald-200' : 'text-slate-400'" />
                    </div>
                    <p class="text-xl font-black tabular-nums">{{ stats.cctv }}</p>
                </button>

                <button
                    type="button"
                    @click="filterForm.filter_report = 'bandwidth'"
                    class="p-4 rounded-2xl border transition-all text-left group"
                    :class="filterForm.filter_report === 'bandwidth' ? 'bg-[#003628] text-white border-[#003628] shadow-lg shadow-emerald-950/20' : 'bg-white text-slate-800 border-slate-200/70 hover:border-slate-300 shadow-sm'"
                >
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[9px] font-black uppercase tracking-widest" :class="filterForm.filter_report === 'bandwidth' ? 'text-emerald-300' : 'text-slate-400'">Bandwidth & Net</span>
                        <NetworkIcon class="size-4" :class="filterForm.filter_report === 'bandwidth' ? 'text-emerald-200' : 'text-slate-400'" />
                    </div>
                    <p class="text-xl font-black tabular-nums">{{ stats.bandwidth + stats.uptime }}</p>
                </button>

                <button
                    type="button"
                    @click="filterForm.filter_report = 'all'"
                    class="p-4 rounded-2xl border transition-all text-left group col-span-2 sm:col-span-1"
                    :class="filterForm.filter_report === 'all' ? 'bg-[#003628] text-white border-[#003628] shadow-lg shadow-emerald-950/20' : 'bg-white text-slate-800 border-slate-200/70 hover:border-slate-300 shadow-sm'"
                >
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[9px] font-black uppercase tracking-widest" :class="filterForm.filter_report === 'all' ? 'text-emerald-300' : 'text-slate-400'">All Reports Sync</span>
                        <InfraIcon class="size-4" :class="filterForm.filter_report === 'all' ? 'text-emerald-200' : 'text-slate-400'" />
                    </div>
                    <p class="text-xl font-black tabular-nums">{{ stats.all_reports }}</p>
                </button>
            </div>

            <!-- Main Table Component -->
            <ReportLogsTable
                :logs="logs"
                :filter-form="filterForm"
                :filter-options="filter_options"
                :summary-text="summaryText"
                :export-url="exportUrl"
                :apply-date-preset="applyDatePreset"
                :clear-date-filters="clearDateFilters"
                :is-preset-active="isPresetActive"
                :active-filter-count="activeFilterCount"
                @open-detail="openDetail"
            />
        </div>

        <!-- Detail Sheet Modal -->
        <ReportLogDetailSheet
            v-if="selectedLog"
            v-model:open="sheetOpen"
            :log="selectedLog"
            @update:open="(val) => { if (!val) selectedLog = null; }"
        />
    </AppLayout>
</template>
