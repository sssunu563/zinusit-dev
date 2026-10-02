<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    LucideShieldCheck as ShieldCheck,
    LucideHistory as HistoryIcon,
} from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLogsTable from '@/pages/AuthLogs/Partials/AuthLogsTable.vue';
import AuthLogDetailSheet, {
    type AuthLogItem,
} from '@/pages/AuthLogs/Partials/AuthLogDetailSheet.vue';
import type { BreadcrumbItem } from '@/types';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface StatsSummary {
    total: number;
    success: number;
    failed: number;
    logout: number;
    sync: number;
}

interface Props {
    logs: {
        data: AuthLogItem[];
        links: PaginationLink[];
        total: number;
        from: number | null;
        to: number | null;
    };
    filters: {
        search?: string;
        event?: string;
        status?: string;
        from_date?: string;
        to_date?: string;
    };
    stats?: StatsSummary;
    events: string[];
    statuses: string[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Log Autentikasi', href: '/auth-logs' },
];

const filterForm = reactive({
    search: props.filters.search || '',
    event: props.filters.event || '',
    status: props.filters.status || '',
    from_date: props.filters.from_date || '',
    to_date: props.filters.to_date || '',
});

const selectedLog = ref<AuthLogItem | null>(null);
const sheetOpen = ref(false);

const openDetail = (log: AuthLogItem) => {
    selectedLog.value = log;
    sheetOpen.value = true;
};

let debounceTimer: ReturnType<typeof setTimeout> | null = null;

watch(
    () => [
        filterForm.search,
        filterForm.event,
        filterForm.status,
        filterForm.from_date,
        filterForm.to_date,
    ],
    () => {
        if (debounceTimer) {
            clearTimeout(debounceTimer);
        }

        debounceTimer = setTimeout(() => {
            router.get(
                '/auth-logs',
                {
                    search: filterForm.search || undefined,
                    event: filterForm.event || undefined,
                    status: filterForm.status || undefined,
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
        return 'Belum ada catatan log autentikasi.';
    }

    return `Menampilkan ${props.logs.from ?? 0}-${props.logs.to ?? 0} dari ${props.logs.total} entri log`;
});

const activeFilterCount = computed(
    () =>
        [
            filterForm.search,
            filterForm.event,
            filterForm.status,
            filterForm.from_date,
            filterForm.to_date,
        ].filter(Boolean).length,
);

const exportUrl = computed(() => {
    const params = new URLSearchParams();

    if (filterForm.search) params.set('search', filterForm.search);
    if (filterForm.event) params.set('event', filterForm.event);
    if (filterForm.status) params.set('status', filterForm.status);
    if (filterForm.from_date) params.set('from_date', filterForm.from_date);
    if (filterForm.to_date) params.set('to_date', filterForm.to_date);

    const queryString = params.toString();
    return queryString
        ? `/auth-logs/export?${queryString}`
        : '/auth-logs/export';
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

</script>

<template>
    <Head title="Log Autentikasi" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="app-page-shell">
            <!-- Combined Header + Table Card -->
            <div class="bg-white rounded-[28px] border border-slate-200/70 shadow-xl shadow-slate-200/50">
                <!-- Header Section -->
                <div class="flex items-center justify-between gap-4 px-6 py-4 border-b border-slate-100">
                    <!-- Brand -->
                    <div class="flex items-center gap-2.5">
                        <div class="h-10 w-10 rounded-2xl bg-[#003628] flex items-center justify-center shadow-md shadow-[#003628]/25 shrink-0">
                            <ShieldCheck class="size-5 text-white"/>
                        </div>
                        <div>
                            <h1 class="text-[15px] font-black tracking-tight text-slate-900 leading-none">
                                Log <span class="text-[#003628]">Autentikasi</span>
                            </h1>
                            <p class="text-[9px] text-slate-400 mt-0.5">Akses Identitas & Keamanan</p>
                        </div>
                    </div>

                    <!-- Stats Summary -->
                    <div v-if="stats" class="hidden lg:flex items-center gap-3 text-[10px] font-bold">
                        <div class="flex items-center gap-1.5">
                            <div class="w-1.5 h-1.5 rounded-full bg-slate-400"/>
                            <span class="text-slate-400">Total:</span>
                            <span class="text-slate-700">{{ stats.total.toLocaleString() }}</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <div class="w-1.5 h-1.5 rounded-full bg-green-500"/>
                            <span class="text-slate-400">Success:</span>
                            <span class="text-slate-700">{{ stats.success.toLocaleString() }}</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <div class="w-1.5 h-1.5 rounded-full bg-red-500"/>
                            <span class="text-slate-400">Failed:</span>
                            <span class="text-slate-700">{{ stats.failed.toLocaleString() }}</span>
                        </div>
                    </div>
                </div>

                <!-- Table Content (remove card wrapper from child component) -->
                <div class="p-6 lg:p-8">
                    <AuthLogsTable
                        :logs="logs"
                        :filter-form="filterForm"
                        :events="events"
                        :statuses="statuses"
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
        <AuthLogDetailSheet
            v-if="selectedLog"
            v-model:open="sheetOpen"
            :log="selectedLog"
            @update:open="
                (val) => {
                    if (!val) selectedLog = null;
                }
            "
        />
    </AppLayout>
</template>
