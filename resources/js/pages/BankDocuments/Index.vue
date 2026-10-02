<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { onClickOutside } from '@vueuse/core';
import {
    LucideFolderArchive as FolderArchive,
    LucideHistory as HistoryIcon,
    LucideFolder as StbIcon,
    LucideClipboardList as LoanIcon,
    LucideSearchCheck as InspectionIcon,
    LucideFileCheck as FileCheck,
    LucideFiles as FilesIcon,
    LucideSearch,
    LucideDownload,
    LucideSlidersHorizontal,
    LucideRefreshCw,
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

const getLast31DaysRange = () => {
    const today = new Date();
    const last31Days = new Date(today);
    last31Days.setDate(today.getDate() - 31);
    
    return {
        from: last31Days.toISOString().split('T')[0],
        to: today.toISOString().split('T')[0],
    };
};

const defaultDateRange = getLast31DaysRange();

const filterForm = reactive({
    search: props.filters.search || '',
    filter_type: props.filters.filter_type || '',
    filter_status: props.filters.filter_status || '',
    from_date: props.filters.from_date || defaultDateRange.from,
    to_date: props.filters.to_date || defaultDateRange.to,
});

const selectedDoc = ref<BankDocumentItem | null>(null);
const sheetOpen = ref(false);
const showFilters = ref(false);
const filterPanelRef = ref<HTMLElement | null>(null);

onClickOutside(filterPanelRef, () => {
    showFilters.value = false;
});

const openDetail = (doc: BankDocumentItem) => {
    selectedDoc.value = doc;
    sheetOpen.value = true;
};

const getTypeCount = (key: string) => {
    if (!props.stats) return '0';
    const val = (props.stats as unknown as Record<string, number>)[key];
    return val !== undefined ? val : '0';
};

const getStatusCount = (key: string) => {
    if (!props.stats) return '0';
    if (key === 'completed') return props.stats.completed;
    return '0';
};

const resetFilters = () => {
    filterForm.search = '';
    filterForm.filter_type = '';
    filterForm.filter_status = '';
    filterForm.from_date = defaultDateRange.from;
    filterForm.to_date = defaultDateRange.to;
    showFilters.value = false;
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

const activeFilterCount = computed(() => {
    let count = 0;
    
    // Don't count search (auto-applied)
    if (filterForm.filter_type) count++;
    if (filterForm.filter_status) count++;
    
    // Only count date filters if they differ from the default 31-day range
    const isDefaultDateRange = 
        filterForm.from_date === defaultDateRange.from && 
        filterForm.to_date === defaultDateRange.to;
    
    if (!isDefaultDateRange && (filterForm.from_date || filterForm.to_date)) {
        if (filterForm.from_date) count++;
        if (filterForm.to_date) count++;
    }
    
    return count;
});

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

</script>

<template>
    <Head title="Bank Dokumen" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="app-page-shell">
            <!-- Combined Header + Table Card -->
            <div class="bg-white rounded-[32px] border border-slate-200/60 shadow-xl shadow-slate-200/50 p-6 lg:p-8">
                <!-- Compact Single-Row Header -->
                <div class="pb-4 border-b border-slate-100 flex items-center justify-between gap-6 mb-8">
                    <!-- Left: Icon + Title -->
                    <div class="flex items-center gap-3 flex-1">
                        <div class="h-8 w-8 rounded-lg bg-[#003628]/10 flex items-center justify-center shrink-0">
                            <FolderArchive class="size-4 text-[#003628]"/>
                        </div>
                        <div>
                            <h2 class="text-sm font-bold text-slate-900">Bank Dokumen</h2>
                        </div>
                    </div>

                    <!-- Right: Compact Controls -->
                    <div class="flex items-center gap-2 shrink-0">
                        <!-- Small Search Box -->
                        <div class="relative w-40">
                            <LucideSearch class="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-slate-400" />
                            <input
                                v-model="filterForm.search"
                                type="text"
                                placeholder="Cari..."
                                class="w-full h-8 pl-9 pr-3 rounded-lg border border-slate-200 bg-white text-xs text-slate-900 placeholder:text-slate-400 focus:border-[#003628]/50 focus:ring-2 focus:ring-[#003628]/10 transition-all outline-none shadow-sm"
                            />
                        </div>

                        <!-- Export Button -->
                        <a
                            :href="exportUrl"
                            class="h-8 w-8 rounded-lg border border-slate-200 bg-white flex items-center justify-center text-slate-500 hover:text-[#003628] hover:bg-[#003628]/5 transition-all shadow-sm"
                            title="Ekspor CSV"
                        >
                            <LucideDownload class="size-4" />
                        </a>

                        <!-- Filter Panel -->
                        <div ref="filterPanelRef" class="relative">
                            <button
                                type="button"
                                class="h-8 w-8 rounded-lg border border-slate-200 bg-white flex items-center justify-center text-slate-500 hover:text-[#003628] hover:bg-[#003628]/5 transition-all relative shadow-sm"
                                @click="showFilters = !showFilters"
                            >
                                <LucideSlidersHorizontal class="size-4" />
                                <span v-if="activeFilterCount" class="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-[#003628] text-[10px] font-black text-white ring-4 ring-white">
                                    {{ activeFilterCount }}
                                </span>
                            </button>

                            <Transition
                                enter-active-class="transition duration-200 ease-out"
                                enter-from-class="opacity-0 translate-y-2 scale-95"
                                enter-to-class="opacity-100 translate-y-0 scale-100"
                                leave-active-class="transition duration-150 ease-in"
                                leave-from-class="opacity-100 translate-y-0 scale-100"
                                leave-to-class="opacity-0 translate-y-2 scale-95"
                            >
                                <div v-if="showFilters" class="absolute top-full right-0 z-50 mt-4 w-88 rounded-[32px] border border-slate-200 bg-white p-6 shadow-2xl backdrop-blur-xl overflow-hidden">
                                    <div class="flex items-center justify-between mb-6">
                                        <h3 class="text-[10px] font-black uppercase tracking-widest text-slate-400">Filter Bank Dokumen</h3>
                                        <button
                                            @click="resetFilters"
                                            class="text-[10px] font-black uppercase tracking-widest text-[#003628] hover:opacity-70 transition-colors flex items-center gap-1.5 cursor-pointer"
                                        >
                                            <LucideRefreshCw class="size-3" /> Reset
                                        </button>
                                    </div>

                                    <div class="space-y-4">
                                        <!-- Document Type Filter -->
                                        <div class="space-y-1.5">
                                            <label class="text-[9px] font-black uppercase tracking-widest text-slate-500 ml-1">Jenis Dokumen</label>
                                            <select
                                                v-model="filterForm.filter_type"
                                                class="w-full h-9 px-3 rounded-xl border border-slate-200 bg-slate-50 text-[11px] font-medium text-slate-700 outline-none focus:border-[#003628]/50 focus:bg-white"
                                            >
                                                <option value="">Semua Jenis Dokumen</option>
                                                <option v-for="t in document_types" :key="t.key" :value="t.key">
                                                    {{ t.label }} ({{ getTypeCount(t.key) }})
                                                </option>
                                            </select>
                                        </div>

                                        <!-- Status Filter -->
                                        <div class="space-y-1.5">
                                            <label class="text-[9px] font-black uppercase tracking-widest text-slate-500 ml-1">Status Dokumen</label>
                                            <select
                                                v-model="filterForm.filter_status"
                                                class="w-full h-9 px-3 rounded-xl border border-slate-200 bg-slate-50 text-[11px] font-medium text-slate-700 outline-none focus:border-[#003628]/50 focus:bg-white"
                                            >
                                                <option value="">Semua Status</option>
                                                <option v-for="st in statuses" :key="st.key" :value="st.key">
                                                    {{ st.label }} ({{ getStatusCount(st.key) }})
                                                </option>
                                            </select>
                                        </div>

                                        <!-- Date Presets -->
                                        <div class="pt-3 border-t border-slate-100 space-y-2">
                                            <label class="text-[9px] font-black uppercase tracking-widest text-slate-500 ml-1">Preset Rentang Tanggal</label>
                                            <div class="grid grid-cols-3 gap-2">
                                                <button
                                                    type="button"
                                                    @click="applyDatePreset('today')"
                                                    class="h-7 px-2 rounded-lg text-[10px] font-bold border transition-all cursor-pointer"
                                                    :class="isPresetActive('today') ? 'bg-[#003628] text-white border-[#003628]' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100'"
                                                >
                                                    Hari Ini
                                                </button>
                                                <button
                                                    type="button"
                                                    @click="applyDatePreset('last7Days')"
                                                    class="h-7 px-2 rounded-lg text-[10px] font-bold border transition-all cursor-pointer"
                                                    :class="isPresetActive('last7Days') ? 'bg-[#003628] text-white border-[#003628]' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100'"
                                                >
                                                    7 Hari
                                                </button>
                                                <button
                                                    type="button"
                                                    @click="applyDatePreset('thisMonth')"
                                                    class="h-7 px-2 rounded-lg text-[10px] font-bold border transition-all cursor-pointer"
                                                    :class="isPresetActive('thisMonth') ? 'bg-[#003628] text-white border-[#003628]' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100'"
                                                >
                                                    Bulan Ini
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Date Inputs -->
                                        <div class="grid grid-cols-2 gap-3 pt-2 border-t border-slate-100">
                                            <div class="space-y-1">
                                                <label class="text-[9px] font-black uppercase tracking-widest text-slate-400">Dari</label>
                                                <input
                                                    v-model="filterForm.from_date"
                                                    type="date"
                                                    :max="defaultDateRange.to"
                                                    class="w-full h-9 px-2 rounded-xl border border-slate-200 bg-slate-50 text-[10px] font-medium text-slate-700 outline-none focus:border-[#003628]/50 focus:bg-white"
                                                />
                                            </div>
                                            <div class="space-y-1">
                                                <label class="text-[9px] font-black uppercase tracking-widest text-slate-400">Hingga</label>
                                                <input
                                                    v-model="filterForm.to_date"
                                                    type="date"
                                                    :max="defaultDateRange.to"
                                                    class="w-full h-9 px-2 rounded-xl border border-slate-200 bg-slate-50 text-[10px] font-medium text-slate-700 outline-none focus:border-[#003628]/50 focus:bg-white"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </Transition>
                        </div>
                    </div>
                </div>

                <!-- Table Content -->
                <BankDocumentsTable
                    :documents="documents"
                    :filter-form="filterForm"
                    :summary-text="summaryText"
                    @open-detail="openDetail"
                />
            </div>
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
