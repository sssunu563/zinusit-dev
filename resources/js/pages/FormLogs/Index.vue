<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    LucideFileText as FileTextIcon,
    LucideHistory as HistoryIcon,
    LucideFolder as StbIcon,
    LucideClipboardList as LoanIcon,
    LucideSearchCheck as InspectionIcon,
    LucideBriefcase as TicketIcon,
} from 'lucide-vue-next';
import { computed, reactive, watch, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import FormLogsTable, { type FormLogItem } from '@/pages/FormLogs/Partials/FormLogsTable.vue';
import FormLogDetailSheet from '@/pages/FormLogs/Partials/FormLogDetailSheet.vue';
import type { BreadcrumbItem } from '@/types';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface FilterFormCategory {
    key: string;
    label: string;
}

interface FilterOptions {
    admins: string[];
    actions: string[];
    forms: FilterFormCategory[];
}

interface StatsSummary {
    total: number;
    stb: number;
    peminjaman: number;
    inspection: number;
    ticket: number;
}

const props = defineProps<{
    logs: {
        data: FormLogItem[];
        links: PaginationLink[];
        total: number;
        from: number | null;
        to: number | null;
    };
    filters: {
        search?: string;
        filter_form?: string;
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
    { title: 'Log Formulir', href: '/form-logs' },
];

const filterForm = reactive({
    search: props.filters.search || '',
    filter_form: props.filters.filter_form || '',
    filter_admin: props.filters.filter_admin || '',
    filter_action: props.filters.filter_action || '',
    from_date: props.filters.from_date || '',
    to_date: props.filters.to_date || '',
});

let debounceTimer: ReturnType<typeof setTimeout> | null = null;

watch(
    () => [
        filterForm.search,
        filterForm.filter_form,
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
                '/form-logs',
                {
                    search: filterForm.search || undefined,
                    filter_form: filterForm.filter_form || undefined,
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
        return 'Belum ada log formulir.';
    }

    return `Menampilkan ${props.logs.from ?? 0}-${props.logs.to ?? 0} dari ${props.logs.total} entri log`;
});

const activeFilterCount = computed(
    () =>
        [
            filterForm.search,
            filterForm.filter_form,
            filterForm.filter_admin,
            filterForm.filter_action,
            filterForm.from_date,
            filterForm.to_date,
        ].filter(Boolean).length,
);

const exportUrl = computed(() => {
    const params = new URLSearchParams();

    if (filterForm.search) params.set('search', filterForm.search);
    if (filterForm.filter_form) params.set('filter_form', filterForm.filter_form);
    if (filterForm.filter_admin) params.set('filter_admin', filterForm.filter_admin);
    if (filterForm.filter_action) params.set('filter_action', filterForm.filter_action);
    if (filterForm.from_date) params.set('from_date', filterForm.from_date);
    if (filterForm.to_date) params.set('to_date', filterForm.to_date);

    const queryString = params.toString();
    return queryString ? `/form-logs/export?${queryString}` : '/form-logs/export';
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
const selectedLog = ref<FormLogItem | null>(null);

const openDetail = (log: FormLogItem) => {
    selectedLog.value = log;
    sheetOpen.value = true;
};
</script>

<template>
    <Head title="Form Logs" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="app-page-shell">
            <!-- Header Section -->
            <header class="mb-6 flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div class="space-y-1">
                    <div class="flex items-center gap-2 text-[10px] font-black tracking-widest text-[#003628] uppercase mb-2">
                        <FileTextIcon class="size-3" />
                        Audit Formulir & Dokumen
                    </div>
                    <h1 class="text-3xl font-black tracking-tight text-slate-900 lg:text-4xl">
                        Log Formulir <span class="text-[#003628] italic">Operasional</span>
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
                    @click="filterForm.filter_form = ''"
                    class="p-4 rounded-2xl border transition-all text-left group"
                    :class="filterForm.filter_form === '' ? 'bg-[#003628] text-white border-[#003628] shadow-lg shadow-emerald-950/20' : 'bg-white text-slate-800 border-slate-200/70 hover:border-slate-300 shadow-sm'"
                >
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[9px] font-black uppercase tracking-widest" :class="filterForm.filter_form === '' ? 'text-emerald-300' : 'text-slate-400'">Semua Form</span>
                        <FileTextIcon class="size-4" :class="filterForm.filter_form === '' ? 'text-emerald-200' : 'text-slate-400'" />
                    </div>
                    <p class="text-xl font-black tabular-nums">{{ stats.total }}</p>
                </button>

                <button
                    type="button"
                    @click="filterForm.filter_form = 'stb'"
                    class="p-4 rounded-2xl border transition-all text-left group"
                    :class="filterForm.filter_form === 'stb' ? 'bg-[#003628] text-white border-[#003628] shadow-lg shadow-emerald-950/20' : 'bg-white text-slate-800 border-slate-200/70 hover:border-slate-300 shadow-sm'"
                >
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[9px] font-black uppercase tracking-widest" :class="filterForm.filter_form === 'stb' ? 'text-emerald-300' : 'text-slate-400'">Dokumen STB</span>
                        <StbIcon class="size-4" :class="filterForm.filter_form === 'stb' ? 'text-emerald-200' : 'text-slate-400'" />
                    </div>
                    <p class="text-xl font-black tabular-nums">{{ stats.stb }}</p>
                </button>

                <button
                    type="button"
                    @click="filterForm.filter_form = 'peminjaman'"
                    class="p-4 rounded-2xl border transition-all text-left group"
                    :class="filterForm.filter_form === 'peminjaman' ? 'bg-[#003628] text-white border-[#003628] shadow-lg shadow-emerald-950/20' : 'bg-white text-slate-800 border-slate-200/70 hover:border-slate-300 shadow-sm'"
                >
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[9px] font-black uppercase tracking-widest" :class="filterForm.filter_form === 'peminjaman' ? 'text-emerald-300' : 'text-slate-400'">Peminjaman</span>
                        <LoanIcon class="size-4" :class="filterForm.filter_form === 'peminjaman' ? 'text-emerald-200' : 'text-slate-400'" />
                    </div>
                    <p class="text-xl font-black tabular-nums">{{ stats.peminjaman }}</p>
                </button>

                <button
                    type="button"
                    @click="filterForm.filter_form = 'inspection'"
                    class="p-4 rounded-2xl border transition-all text-left group"
                    :class="filterForm.filter_form === 'inspection' ? 'bg-[#003628] text-white border-[#003628] shadow-lg shadow-emerald-950/20' : 'bg-white text-slate-800 border-slate-200/70 hover:border-slate-300 shadow-sm'"
                >
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[9px] font-black uppercase tracking-widest" :class="filterForm.filter_form === 'inspection' ? 'text-emerald-300' : 'text-slate-400'">Inspection</span>
                        <InspectionIcon class="size-4" :class="filterForm.filter_form === 'inspection' ? 'text-emerald-200' : 'text-slate-400'" />
                    </div>
                    <p class="text-xl font-black tabular-nums">{{ stats.inspection }}</p>
                </button>

                <button
                    type="button"
                    @click="filterForm.filter_form = 'ticket'"
                    class="p-4 rounded-2xl border transition-all text-left group col-span-2 sm:col-span-1"
                    :class="filterForm.filter_form === 'ticket' ? 'bg-[#003628] text-white border-[#003628] shadow-lg shadow-emerald-950/20' : 'bg-white text-slate-800 border-slate-200/70 hover:border-slate-300 shadow-sm'"
                >
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[9px] font-black uppercase tracking-widest" :class="filterForm.filter_form === 'ticket' ? 'text-emerald-300' : 'text-slate-400'">Workspace</span>
                        <TicketIcon class="size-4" :class="filterForm.filter_form === 'ticket' ? 'text-emerald-200' : 'text-slate-400'" />
                    </div>
                    <p class="text-xl font-black tabular-nums">{{ stats.ticket }}</p>
                </button>
            </div>

            <!-- Main Table Component -->
            <FormLogsTable
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
        <FormLogDetailSheet
            v-if="selectedLog"
            v-model:open="sheetOpen"
            :log="selectedLog"
            @update:open="(val) => { if (!val) selectedLog = null; }"
        />
    </AppLayout>
</template>
