<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    LucideFolderArchive as FolderArchive,
    LucideHistory as HistoryIcon,
    LucideFolder as StbIcon,
    LucideClipboardList as LoanIcon,
    LucideSearchCheck as InspectionIcon,
    LucideFileCheck as FileCheck,
    LucideFiles as FilesIcon,
} from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import BankDocumentsTable from '@/pages/BankDocuments/Partials/BankDocumentsTable.vue';
import BankDocumentDetailSheet, { type BankDocumentItem } from '@/pages/BankDocuments/Partials/BankDocumentDetailSheet.vue';
import type { BreadcrumbItem } from '@/types';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface StatsSummary {
    total: number;
    stb: number;
    peminjaman: number;
    inspection: number;
    completed: number;
}

interface DocTypeOption {
    key: string;
    label: string;
}

interface StatusOption {
    key: string;
    label: string;
}

interface Props {
    documents: {
        data: BankDocumentItem[];
        links: PaginationLink[];
        total: number;
        from: number | null;
        to: number | null;
    };
    filters: {
        search?: string;
        filter_type?: string;
        filter_status?: string;
        from_date?: string;
        to_date?: string;
    };
    stats: StatsSummary;
    document_types: DocTypeOption[];
    statuses: StatusOption[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Form', href: '/stb' },
    { title: 'Bank Dokumen', href: '/bank-documents' },
];

const filterForm = reactive({
    search: props.filters.search || '',
    filter_type: props.filters.filter_type || '',
    filter_status: props.filters.filter_status || '',
    from_date: props.filters.from_date || '',
    to_date: props.filters.to_date || '',
});

const selectedDoc = ref<BankDocumentItem | null>(null);
const sheetOpen = ref(false);

const openDetail = (doc: BankDocumentItem) => {
    selectedDoc.value = doc;
    sheetOpen.value = true;
};

let debounceTimer: ReturnType<typeof setTimeout> | null = null;

watch(
    () => [
        filterForm.search,
        filterForm.filter_type,
        filterForm.filter_status,
        filterForm.from_date,
        filterForm.to_date,
    ],
    () => {
        if (debounceTimer) {
            clearTimeout(debounceTimer);
        }

        debounceTimer = setTimeout(() => {
            router.get(
                '/bank-documents',
                {
                    search: filterForm.search || undefined,
                    filter_type: filterForm.filter_type || undefined,
                    filter_status: filterForm.filter_status || undefined,
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
    if (!props.documents.total) {
        return 'Belum ada arsip dokumen.';
    }

    return `Menampilkan ${props.documents.from ?? 0}-${props.documents.to ?? 0} dari ${props.documents.total} dokumen`;
});

const activeFilterCount = computed(
    () =>
        [
            filterForm.search,
            filterForm.filter_type,
            filterForm.filter_status,
            filterForm.from_date,
            filterForm.to_date,
        ].filter(Boolean).length,
);

const exportUrl = computed(() => {
    const params = new URLSearchParams();

    if (filterForm.search) params.set('search', filterForm.search);
    if (filterForm.filter_type) params.set('filter_type', filterForm.filter_type);
    if (filterForm.filter_status) params.set('filter_status', filterForm.filter_status);
    if (filterForm.from_date) params.set('from_date', filterForm.from_date);
    if (filterForm.to_date) params.set('to_date', filterForm.to_date);

    const queryString = params.toString();
    return queryString ? `/bank-documents/export?${queryString}` : '/bank-documents/export';
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

const setCardFilter = (mode: 'all' | 'stb' | 'peminjaman' | 'inspection' | 'completed') => {
    switch (mode) {
        case 'all':
            filterForm.filter_type = '';
            filterForm.filter_status = '';
            break;
        case 'stb':
            filterForm.filter_type = 'stb';
            filterForm.filter_status = '';
            break;
        case 'peminjaman':
            filterForm.filter_type = 'peminjaman';
            filterForm.filter_status = '';
            break;
        case 'inspection':
            filterForm.filter_type = 'inspection';
            filterForm.filter_status = '';
            break;
        case 'completed':
            filterForm.filter_type = '';
            filterForm.filter_status = 'completed';
            break;
    }
};

const isCardActive = (mode: 'all' | 'stb' | 'peminjaman' | 'inspection' | 'completed') => {
    switch (mode) {
        case 'all':
            return filterForm.filter_type === '' && filterForm.filter_status === '';
        case 'stb':
            return filterForm.filter_type === 'stb';
        case 'peminjaman':
            return filterForm.filter_type === 'peminjaman';
        case 'inspection':
            return filterForm.filter_type === 'inspection';
        case 'completed':
            return filterForm.filter_status === 'completed';
    }
};
</script>

<template>
    <Head title="Bank Dokumen" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="app-page-shell">
            <!-- Header Section -->
            <header class="mb-6 flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div class="space-y-1">
                    <div class="flex items-center gap-2 text-[10px] font-black tracking-widest text-[#003628] uppercase mb-2">
                        <FolderArchive class="size-3" />
                        Repositori &amp; Arsip Digital Operasional
                    </div>
                    <h1 class="text-3xl font-black tracking-tight text-slate-900 lg:text-4xl">
                        Bank <span class="text-[#003628] italic">Dokumen</span>
                    </h1>
                </div>

                <div class="flex items-center gap-3">
                    <div class="h-12 w-12 rounded-2xl border border-slate-200 bg-white shadow-xs flex items-center justify-center">
                        <FilesIcon class="size-5 text-[#003628]" />
                    </div>
                </div>
            </header>

            <!-- Stats Overview Cards -->
            <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-5 gap-3.5 mb-6">
                <!-- Semua Dokumen -->
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
                            Semua Dokumen
                        </span>
                        <FilesIcon class="size-4" :class="isCardActive('all') ? 'text-emerald-200' : 'text-slate-400'" />
                    </div>
                    <p class="text-xl font-black tabular-nums">{{ stats.total }}</p>
                </button>

                <!-- Dokumen STB -->
                <button
                    type="button"
                    @click="setCardFilter('stb')"
                    class="p-4 rounded-2xl border transition-all text-left group cursor-pointer"
                    :class="isCardActive('stb')
                        ? 'bg-[#003628] text-white border-[#003628] shadow-lg shadow-emerald-950/20'
                        : 'bg-white text-slate-800 border-slate-200/70 hover:border-slate-300 shadow-xs'"
                >
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[9px] font-black uppercase tracking-widest" :class="isCardActive('stb') ? 'text-emerald-300' : 'text-slate-400'">
                            Dokumen STB
                        </span>
                        <StbIcon class="size-4" :class="isCardActive('stb') ? 'text-emerald-200' : 'text-slate-400'" />
                    </div>
                    <p class="text-xl font-black tabular-nums">{{ stats.stb }}</p>
                </button>

                <!-- Peminjaman -->
                <button
                    type="button"
                    @click="setCardFilter('peminjaman')"
                    class="p-4 rounded-2xl border transition-all text-left group cursor-pointer"
                    :class="isCardActive('peminjaman')
                        ? 'bg-[#003628] text-white border-[#003628] shadow-lg shadow-emerald-950/20'
                        : 'bg-white text-slate-800 border-slate-200/70 hover:border-slate-300 shadow-xs'"
                >
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[9px] font-black uppercase tracking-widest" :class="isCardActive('peminjaman') ? 'text-emerald-300' : 'text-slate-400'">
                            Peminjaman
                        </span>
                        <LoanIcon class="size-4" :class="isCardActive('peminjaman') ? 'text-emerald-200' : 'text-slate-400'" />
                    </div>
                    <p class="text-xl font-black tabular-nums">{{ stats.peminjaman }}</p>
                </button>

                <!-- Inspection -->
                <button
                    type="button"
                    @click="setCardFilter('inspection')"
                    class="p-4 rounded-2xl border transition-all text-left group cursor-pointer"
                    :class="isCardActive('inspection')
                        ? 'bg-[#003628] text-white border-[#003628] shadow-lg shadow-emerald-950/20'
                        : 'bg-white text-slate-800 border-slate-200/70 hover:border-slate-300 shadow-xs'"
                >
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[9px] font-black uppercase tracking-widest" :class="isCardActive('inspection') ? 'text-emerald-300' : 'text-slate-400'">
                            Inspection
                        </span>
                        <InspectionIcon class="size-4" :class="isCardActive('inspection') ? 'text-emerald-200' : 'text-slate-400'" />
                    </div>
                    <p class="text-xl font-black tabular-nums">{{ stats.inspection }}</p>
                </button>

                <!-- PDF Lengkap -->
                <button
                    type="button"
                    @click="setCardFilter('completed')"
                    class="p-4 rounded-2xl border transition-all text-left group cursor-pointer col-span-2 sm:col-span-1"
                    :class="isCardActive('completed')
                        ? 'bg-[#003628] text-white border-[#003628] shadow-lg shadow-emerald-950/20'
                        : 'bg-white text-slate-800 border-slate-200/70 hover:border-slate-300 shadow-xs'"
                >
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[9px] font-black uppercase tracking-widest" :class="isCardActive('completed') ? 'text-emerald-300' : 'text-slate-400'">
                            Arsip PDF Selesai
                        </span>
                        <FileCheck class="size-4" :class="isCardActive('completed') ? 'text-emerald-200' : 'text-emerald-600'" />
                    </div>
                    <p class="text-xl font-black tabular-nums">{{ stats.completed }}</p>
                </button>
            </div>

            <!-- Main Table Component -->
            <BankDocumentsTable
                :documents="documents"
                :filter-form="filterForm"
                :document-types="document_types"
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
        <BankDocumentDetailSheet
            v-if="selectedDoc"
            v-model:open="sheetOpen"
            :document="selectedDoc"
            @update:open="(val) => { if (!val) selectedDoc = null; }"
        />
    </AppLayout>
</template>
