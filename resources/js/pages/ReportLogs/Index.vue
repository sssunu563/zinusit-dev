<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    LucideBarChart as ReportIcon,
    LucideHistory as HistoryIcon,
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
            <!-- Combined Header + Table Card -->
            <div class="bg-white rounded-[28px] border border-slate-200/70 shadow-xl shadow-slate-200/50">
                <!-- Header Section -->
                <div class="flex items-center justify-between gap-4 px-6 py-4 border-b border-slate-100">
                    <!-- Brand -->
                    <div class="flex items-center gap-2.5">
                        <div class="h-10 w-10 rounded-2xl bg-[#003628] flex items-center justify-center shadow-md shadow-[#003628]/25 shrink-0">
                            <ReportIcon class="size-5 text-white"/>
                        </div>
                        <div>
                            <h1 class="text-[15px] font-black tracking-tight text-slate-900 leading-none">
                                Log <span class="text-[#003628]">Report</span>
                            </h1>
                            <p class="text-[9px] text-slate-400 mt-0.5">Audit Modul & Aktivitas Report</p>
                        </div>
                    </div>

                    <!-- Stats Summary -->
                    <div class="hidden lg:flex items-center gap-3 text-[10px] font-bold">
                        <div class="flex items-center gap-1.5">
                            <div class="w-1.5 h-1.5 rounded-full bg-slate-400"/>
                            <span class="text-slate-400">Total:</span>
                            <span class="text-slate-700">{{ stats.total.toLocaleString() }}</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <div class="w-1.5 h-1.5 rounded-full bg-blue-500"/>
                            <span class="text-slate-400">Server:</span>
                            <span class="text-slate-700">{{ stats.server.toLocaleString() }}</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <div class="w-1.5 h-1.5 rounded-full bg-purple-500"/>
                            <span class="text-slate-400">CCTV:</span>
                            <span class="text-slate-700">{{ stats.cctv.toLocaleString() }}</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <div class="w-1.5 h-1.5 rounded-full bg-orange-500"/>
                            <span class="text-slate-400">Bandwidth:</span>
                            <span class="text-slate-700">{{ stats.bandwidth.toLocaleString() }}</span>
                        </div>
                    </div>
                </div>

                <!-- Table Content -->
                <div class="p-6 lg:p-8">
                    <ReportLogsTable
                :logs="logs"
                :filter-form="filterForm"
                :filter-options="filter_options"
                :stats="stats"
                :summary-text="summaryText"
                :export-url="exportUrl"
                :apply-date-preset="applyDatePreset"
                :clear-date-filters="clearDateFilters"
                :is-preset-active="isPresetActive"
                :active-filter-count="activeFilterCount"
                @open-detail="openDetail"
            />
                </div>
            </div>
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
