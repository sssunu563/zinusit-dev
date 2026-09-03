<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    LucideShieldCheck as ShieldCheck,
    LucideHistory as HistoryIcon,
    LucideKeyRound as KeyIcon,
    LucideCheckCircle2 as CheckCircle,
    LucideXCircle as XCircle,
    LucideLogOut as LogOut,
    LucideRefreshCw as RefreshCw,
} from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLogsTable from '@/pages/AuthLogs/Partials/AuthLogsTable.vue';
import AuthLogDetailSheet, { type AuthLogItem } from '@/pages/AuthLogs/Partials/AuthLogDetailSheet.vue';
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
    { title: 'Auth Logs', href: '/auth-logs' },
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
    return queryString ? `/auth-logs/export?${queryString}` : '/auth-logs/export';
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

const setCardFilter = (mode: 'all' | 'success' | 'failed' | 'logout' | 'sync') => {
    switch (mode) {
        case 'all':
            filterForm.event = '';
            filterForm.status = '';
            break;
        case 'success':
            filterForm.event = 'login';
            filterForm.status = 'success';
            break;
        case 'failed':
            filterForm.event = '';
            filterForm.status = 'failed';
            break;
        case 'logout':
            filterForm.event = 'logout';
            filterForm.status = '';
            break;
        case 'sync':
            filterForm.event = 'user_sync';
            filterForm.status = '';
            break;
    }
};

const isCardActive = (mode: 'all' | 'success' | 'failed' | 'logout' | 'sync') => {
    switch (mode) {
        case 'all':
            return filterForm.event === '' && filterForm.status === '';
        case 'success':
            return (filterForm.event === 'login' || filterForm.event === '') && (filterForm.status === 'success' || filterForm.status === 'matched');
        case 'failed':
            return filterForm.status === 'failed';
        case 'logout':
            return filterForm.event === 'logout';
        case 'sync':
            return filterForm.event === 'user_sync';
    }
};
</script>

<template>
    <Head title="Auth Logs" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="app-page-shell">
            <!-- Header Section -->
            <header class="mb-6 flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div class="space-y-1">
                    <div class="flex items-center gap-2 text-[10px] font-black tracking-widest text-[#003628] uppercase mb-2">
                        <ShieldCheck class="size-3" />
                        Akses Identitas &amp; Keamanan
                    </div>
                    <h1 class="text-3xl font-black tracking-tight text-slate-900 lg:text-4xl">
                        Log <span class="text-[#003628] italic">Autentikasi</span>
                    </h1>
                </div>

                <div class="flex items-center gap-3">
                    <div class="h-12 w-12 rounded-2xl border border-slate-200 bg-white shadow-xs flex items-center justify-center">
                        <HistoryIcon class="size-5 text-[#003628]" />
                    </div>
                </div>
            </header>

            <!-- Stats Overview Cards -->
            <div v-if="stats" class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-5 gap-3.5 mb-6">
                <!-- Semua Log -->
                <button
                    type="button"
                    @click="setCardFilter('all')"
                    class="p-4 rounded-2xl border transition-all text-left group cursor-pointer"
                    :class="isCardActive('all')
                        ? 'bg-[#003628] text-white border-[#003628] shadow-lg shadow-emerald-950/20'
                        : 'bg-white text-slate-800 border-slate-200/70 hover:border-slate-300 shadow-xs'"
                >
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[9px] font-black uppercase tracking-widest" :class="isCardActive('all') ? 'text-emerald-300' : 'text-slate-400'">
                            Semua Log
                        </span>
                        <ShieldCheck class="size-4" :class="isCardActive('all') ? 'text-emerald-200' : 'text-slate-400'" />
                    </div>
                    <p class="text-xl font-black tabular-nums">{{ stats.total }}</p>
                </button>

                <!-- Login Berhasil -->
                <button
                    type="button"
                    @click="setCardFilter('success')"
                    class="p-4 rounded-2xl border transition-all text-left group cursor-pointer"
                    :class="isCardActive('success')
                        ? 'bg-[#003628] text-white border-[#003628] shadow-lg shadow-emerald-950/20'
                        : 'bg-white text-slate-800 border-slate-200/70 hover:border-slate-300 shadow-xs'"
                >
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[9px] font-black uppercase tracking-widest" :class="isCardActive('success') ? 'text-emerald-300' : 'text-slate-400'">
                            Login Berhasil
                        </span>
                        <CheckCircle class="size-4" :class="isCardActive('success') ? 'text-emerald-200' : 'text-emerald-500'" />
                    </div>
                    <p class="text-xl font-black tabular-nums">{{ stats.success }}</p>
                </button>

                <!-- Login Gagal -->
                <button
                    type="button"
                    @click="setCardFilter('failed')"
                    class="p-4 rounded-2xl border transition-all text-left group cursor-pointer"
                    :class="isCardActive('failed')
                        ? 'bg-[#003628] text-white border-[#003628] shadow-lg shadow-emerald-950/20'
                        : 'bg-white text-slate-800 border-slate-200/70 hover:border-slate-300 shadow-xs'"
                >
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[9px] font-black uppercase tracking-widest" :class="isCardActive('failed') ? 'text-emerald-300' : 'text-slate-400'">
                            Login Gagal
                        </span>
                        <XCircle class="size-4" :class="isCardActive('failed') ? 'text-emerald-200' : 'text-red-500'" />
                    </div>
                    <p class="text-xl font-black tabular-nums">{{ stats.failed }}</p>
                </button>

                <!-- Logout -->
                <button
                    type="button"
                    @click="setCardFilter('logout')"
                    class="p-4 rounded-2xl border transition-all text-left group cursor-pointer"
                    :class="isCardActive('logout')
                        ? 'bg-[#003628] text-white border-[#003628] shadow-lg shadow-emerald-950/20'
                        : 'bg-white text-slate-800 border-slate-200/70 hover:border-slate-300 shadow-xs'"
                >
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[9px] font-black uppercase tracking-widest" :class="isCardActive('logout') ? 'text-emerald-300' : 'text-slate-400'">
                            Sesi Logout
                        </span>
                        <LogOut class="size-4" :class="isCardActive('logout') ? 'text-emerald-200' : 'text-slate-400'" />
                    </div>
                    <p class="text-xl font-black tabular-nums">{{ stats.logout }}</p>
                </button>

                <!-- User Sync -->
                <button
                    type="button"
                    @click="setCardFilter('sync')"
                    class="p-4 rounded-2xl border transition-all text-left group cursor-pointer col-span-2 sm:col-span-1"
                    :class="isCardActive('sync')
                        ? 'bg-[#003628] text-white border-[#003628] shadow-lg shadow-emerald-950/20'
                        : 'bg-white text-slate-800 border-slate-200/70 hover:border-slate-300 shadow-xs'"
                >
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[9px] font-black uppercase tracking-widest" :class="isCardActive('sync') ? 'text-emerald-300' : 'text-slate-400'">
                            Sinkronisasi
                        </span>
                        <RefreshCw class="size-4" :class="isCardActive('sync') ? 'text-emerald-200' : 'text-slate-400'" />
                    </div>
                    <p class="text-xl font-black tabular-nums">{{ stats.sync }}</p>
                </button>
            </div>

            <!-- Main Table Component -->
            <AuthLogsTable
                :logs="logs"
                :filter-form="filterForm"
                :events="events"
                :statuses="statuses"
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
        <AuthLogDetailSheet
            v-if="selectedLog"
            v-model:open="sheetOpen"
            :log="selectedLog"
            @update:open="(val) => { if (!val) selectedLog = null; }"
        />
    </AppLayout>
</template>
