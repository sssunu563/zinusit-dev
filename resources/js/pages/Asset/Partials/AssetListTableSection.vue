<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { onClickOutside } from '@vueuse/core';
import {
    ArrowDownAZ,
    ArrowUpAZ,
    ChevronDown,
    ChevronUp,
    CircuitBoard,
    Download,
    Eye,
    HardDrive,
    Key,
    Laptop,
    Package,
    PackagePlus,
    Pencil,
    Plug,
    Plus,
    Printer,
    RefreshCw,
    Search,
    SlidersHorizontal,
    Trash2,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useRenderProfiler } from '@/composables/useRenderProfiler';
import type { AssetItem, SortKey, TableColumn } from '@/pages/Asset/types';

const props = defineProps<{
    pageTitle: string;
    showStatusFilter: boolean;
    statuses: Array<{ id: number | string; name: string; count?: number }>;
    selectedStatus: string;
    statusOptions: Array<{ value: string; count: number }> | null;
    selectedStateName: string;
    categoryOptions: Array<{ value: string; count: number }>;
    selectedCategory: string;
    locationOptions: Array<{ value: string; count: number }>;
    selectedLocation: string;
    searchQuery: string;
    addButtonLabel: string;
    createHref: string;
    downloadCsv: () => void;
    tableColumns: TableColumn[];
    paginatedAssets: AssetItem[];
    emptyColspan: number;
    sortKey: SortKey;
    sortDirection: 'asc' | 'desc';
    columnFilters: Partial<Record<SortKey, string>>;
    pageStart: number;
    pageEnd: number;
    totalRows: number;
    pageSize: number;
    currentPage: number;
    totalPages: number;
    pageNumbers: number[];
    isStockType: boolean;
    isHardwareType: boolean;
    canShowGenerateStbIn: boolean;
    canShowGenerateStbOut: boolean;
    canShowGenerateLoan: boolean;
    canShowGenerateInspection: boolean;
    canShowReturnLoan: boolean;
    getDetailHref: (asset: AssetItem) => string;
    getEditHref: (asset: AssetItem) => string;
    formatCellValue: (value: string | number) => string | number;
    toggleColumnSort: (nextSortKey: SortKey) => void;
    handleStatusChange: (value: string) => void;
    handleCategoryChange: (value: string) => void;
    handleLocationChange: (value: string) => void;
    handleStateNameChange: (value: string) => void;
    resetFilters: () => void;
    goToPreviousPage: () => void;
    goToNextPage: () => void;
    setPage: (page: number) => void;
    selectedIds: (number | string)[];
}>();

const emit = defineEmits<{
    'add-stock': [asset: AssetItem];
    'show-detail': [asset: AssetItem];
    'update:selectedIds': [ids: (number | string)[]];
    'update:searchQuery': [value: string];
    handover: [];
    loan: [];
    inspection: [];
    label: [];
    'return-loan': [items: AssetItem[]];
    delete: [asset: AssetItem];
}>();

useRenderProfiler('AssetListTableSection');

// Status badge coloring based on state name
const statusBadge = (stateName: string | null | undefined) => {
    const s = String(stateName || '').toLowerCase();
    if (['in use', 'digunakan', 'deployed', 'used'].some((k) => s.includes(k)))
        return {
            bg: 'bg-emerald-50 border-emerald-100 text-emerald-600',
            dot: 'bg-emerald-500',
        };
    if (
        ['maintenance', 'repair', 'perbaikan', 'service'].some((k) =>
            s.includes(k),
        )
    )
        return {
            bg: 'bg-amber-50 border-amber-100 text-amber-600',
            dot: 'bg-amber-500',
        };
    if (['available', 'ready', 'tersedia', 'stock'].some((k) => s.includes(k)))
        return {
            bg: 'bg-sky-50 border-sky-100 text-sky-600',
            dot: 'bg-sky-500',
        };
    if (['broken', 'rusak', 'damage'].some((k) => s.includes(k)))
        return {
            bg: 'bg-rose-50 border-rose-100 text-rose-600',
            dot: 'bg-rose-500',
        };
    return {
        bg: 'bg-slate-50 border-slate-100 text-slate-500',
        dot: 'bg-slate-300',
    };
};

const hasActiveFilters = computed(
    () =>
        props.selectedCategory !== '' ||
        props.selectedLocation !== '' ||
        props.selectedStateName !== '' ||
        (props.selectedStatus !== 'all' && props.showStatusFilter),
);

const activeFilterCount = computed(() => {
    return (
        Number(props.selectedCategory !== '') +
        Number(props.selectedLocation !== '') +
        Number(props.selectedStateName !== '') +
        Number(props.selectedStatus !== 'all' && props.showStatusFilter)
    );
});

const assetIcon = computed(() => {
    const t = (props.pageTitle || '').toLowerCase();
    if (t.includes('laptop')) return Laptop;
    if (t.includes('license') || t.includes('lisensi')) return Key;
    if (t.includes('accessor') || t.includes('aksesori')) return Plug;
    if (t.includes('consumable') || t.includes('habis pakai')) return Package;
    if (t.includes('component') || t.includes('komponen')) return CircuitBoard;
    return HardDrive;
});

const showGenerateDropdown = ref(false);
const showFilters = ref(false);
const filterPanelRef = ref<HTMLElement | null>(null);
const generateDropdownRef = ref<HTMLElement | null>(null);

onClickOutside(filterPanelRef, () => {
    showFilters.value = false;
});

onClickOutside(generateDropdownRef, () => {
    showGenerateDropdown.value = false;
});

const isAllSelected = computed(() => {
    return (
        props.paginatedAssets.length > 0 &&
        props.paginatedAssets.every((a) => props.selectedIds.includes(a.id))
    );
});

const toggleSelectAll = () => {
    if (isAllSelected.value) {
        const paginatedIds = props.paginatedAssets.map((a) => a.id);
        emit(
            'update:selectedIds',
            props.selectedIds.filter((id) => !paginatedIds.includes(id)),
        );
    } else {
        const newIds = [...props.selectedIds];
        props.paginatedAssets.forEach((a) => {
            if (!newIds.includes(a.id)) newIds.push(a.id);
        });
        emit('update:selectedIds', newIds);
    }
};

const toggleSelect = (id: number | string) => {
    const newIds = [...props.selectedIds];
    const idx = newIds.indexOf(id);
    if (idx > -1) {
        newIds.splice(idx, 1);
    } else {
        newIds.push(id);
    }
    emit('update:selectedIds', newIds);
};

// Extract inspection code from notes if it exists
const getInspectionCode = (asset: AssetItem): string | null => {
    const notes = String(asset.notes || '').toLowerCase();
    if (!notes) return null;

    // Look for patterns like IR-ZGI-2609-00005
    const match = notes.match(/\bir-[a-z]{3}-\d{4}-\d{5}\b/i);
    return match ? match[0].toUpperCase() : null;
};


</script>

<template>
    <div
        class="flex min-h-[500px] flex-col rounded-[32px] border border-slate-200/60 bg-white p-6 shadow-xl shadow-slate-200/50 lg:p-8"
    >
        <section class="flex flex-1 flex-col">
            <!-- Compact Single-Row Header -->
            <div class="pb-4 border-b border-slate-100 flex items-center justify-between gap-6 mb-8">
                <!-- Left: Icon + Title -->
                <div class="flex items-center gap-3 flex-1 min-w-0">
                    <div class="h-8 w-8 rounded-lg bg-[#003628]/10 flex items-center justify-center shrink-0">
                        <component :is="assetIcon" class="size-4 text-[#003628]" />
                    </div>
                    <div>
                        <h2 class="text-sm font-bold text-slate-900 truncate">{{ pageTitle }}</h2>
                    </div>
                </div>

                <!-- Right: Compact Controls -->
                <div class="flex items-center gap-2 shrink-0">
                    <!-- Generate & Return Loan dropdown actions -->
                    <Transition
                        enter-active-class="transition duration-200 ease-out"
                        enter-from-class="opacity-0 -translate-x-2"
                        enter-to-class="opacity-100 translate-x-0"
                        leave-active-class="transition duration-150 ease-in"
                        leave-from-class="opacity-100 translate-x-0"
                        leave-to-class="opacity-0 -translate-x-2"
                    >
                        <div
                            v-if="selectedIds.length > 0"
                            class="flex items-center gap-2"
                        >
                            <div class="relative" ref="generateDropdownRef">
                                <button
                                    type="button"
                                    class="flex h-8 items-center gap-1.5 rounded-lg bg-[#003628] px-3 text-xs font-bold text-white shadow-sm transition-all hover:opacity-90 active:scale-95 cursor-pointer"
                                    @click="
                                        showGenerateDropdown =
                                            !showGenerateDropdown
                                    "
                                >
                                    <RefreshCw class="size-3.5" />
                                    <span>Generate</span>
                                    <span class="text-xs"
                                        >({{ selectedIds.length }})</span
                                    >
                                    <ChevronDown
                                        v-if="!showGenerateDropdown"
                                        class="size-3.5"
                                    />
                                    <ChevronUp v-else class="size-3.5" />
                                </button>
                                <div
                                    v-if="showGenerateDropdown"
                                    class="absolute top-full right-0 z-50 mt-2 w-60 rounded-2xl border border-slate-200 bg-white p-2 shadow-2xl"
                                >
                                    <button
                                        v-if="canShowGenerateStbIn"
                                        type="button"
                                        @click="
                                            emit('handover');
                                            showGenerateDropdown = false;
                                        "
                                        class="flex w-full items-center gap-2 rounded-xl px-3 py-2 text-left text-[11px] font-black tracking-widest text-slate-700 uppercase hover:bg-emerald-50 hover:text-emerald-700 cursor-pointer"
                                    >
                                        <RefreshCw class="size-4" />
                                        Generate STB IN
                                    </button>
                                    <button
                                        v-if="canShowGenerateStbOut"
                                        type="button"
                                        @click="
                                            emit('handover');
                                            showGenerateDropdown = false;
                                        "
                                        class="flex w-full items-center gap-2 rounded-xl px-3 py-2 text-left text-[11px] font-black tracking-widest text-slate-700 uppercase hover:bg-emerald-50 hover:text-emerald-700 cursor-pointer"
                                    >
                                        <RefreshCw class="size-4" />
                                        {{
                                            isStockType
                                                ? 'Generate STB'
                                                : 'Generate STB OUT'
                                        }}
                                    </button>
                                    <button
                                        v-if="canShowGenerateLoan"
                                        type="button"
                                        @click="
                                            emit('loan');
                                            showGenerateDropdown = false;
                                        "
                                        class="flex w-full items-center gap-2 rounded-xl px-3 py-2 text-left text-[11px] font-black tracking-widest text-amber-700 uppercase hover:bg-amber-50 cursor-pointer"
                                    >
                                        <RefreshCw class="size-4" />
                                        Generate Loan
                                    </button>
                                    <button
                                        v-if="canShowGenerateInspection"
                                        type="button"
                                        @click="
                                            emit('inspection');
                                            showGenerateDropdown = false;
                                        "
                                        class="flex w-full items-center gap-2 rounded-xl px-3 py-2 text-left text-[11px] font-black tracking-widest text-sky-700 uppercase hover:bg-sky-50 cursor-pointer"
                                    >
                                        <Search class="size-4" />
                                        Inspection
                                    </button>
                                    <button
                                        type="button"
                                        @click="
                                            emit('label');
                                            showGenerateDropdown = false;
                                        "
                                        class="flex w-full items-center gap-2 rounded-xl px-3 py-2 text-left text-[11px] font-black tracking-widest text-slate-700 uppercase hover:bg-slate-50 hover:text-slate-900 cursor-pointer"
                                    >
                                        <Printer class="size-4" />
                                        Generate Label
                                    </button>
                                </div>
                            </div>
                            <button
                                v-if="canShowReturnLoan"
                                type="button"
                                @click="emit('return-loan', selectedIds)"
                                class="flex h-8 items-center gap-1.5 rounded-lg bg-red-600 px-3 text-xs font-bold text-white shadow-sm transition-all hover:opacity-90 active:scale-95 cursor-pointer"
                            >
                                <RefreshCw class="size-3.5" />
                                Return Loan ({{ selectedIds.length }})
                            </button>
                        </div>
                    </Transition>

                    <!-- Small Search Box -->
                    <div class="relative w-40 sm:w-48">
                        <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-slate-400" />
                        <input
                            :value="searchQuery"
                            type="text"
                            placeholder="Cari..."
                            class="w-full h-8 pl-9 pr-3 rounded-lg border border-slate-200 bg-white text-xs text-slate-900 placeholder:text-slate-400 focus:border-[#003628]/50 focus:ring-2 focus:ring-[#003628]/10 transition-all outline-none shadow-sm"
                            @input="emit('update:searchQuery', ($event.target as HTMLInputElement).value)"
                        />
                    </div>

                    <!-- Export Button -->
                    <button
                        type="button"
                        class="h-8 w-8 rounded-lg border border-slate-200 bg-white flex items-center justify-center text-slate-500 hover:text-[#003628] hover:bg-[#003628]/5 transition-all shadow-sm cursor-pointer"
                        title="Export CSV"
                        @click="downloadCsv"
                    >
                        <Download class="size-4" />
                    </button>

                    <!-- Filter Panel -->
                    <div ref="filterPanelRef" class="relative">
                        <button
                            type="button"
                            class="h-8 w-8 rounded-lg border border-slate-200 bg-white flex items-center justify-center text-slate-500 hover:text-[#003628] hover:bg-[#003628]/5 transition-all relative shadow-sm cursor-pointer"
                            @click="showFilters = !showFilters"
                        >
                            <SlidersHorizontal class="size-4" />
                            <span
                                v-if="activeFilterCount > 0"
                                class="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-[#003628] text-[10px] font-black text-white ring-4 ring-white"
                            >
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
                            <div
                                v-if="showFilters"
                                class="absolute top-full right-0 z-50 mt-4 w-72 overflow-hidden rounded-[32px] border border-slate-200 bg-white/95 p-6 shadow-2xl backdrop-blur-xl"
                            >
                                <div class="mb-6 flex items-center justify-between">
                                    <h3 class="text-[10px] font-black tracking-widest text-slate-400 uppercase">
                                        Persempit Pencarian
                                    </h3>
                                    <button
                                        v-if="hasActiveFilters"
                                        @click="resetFilters"
                                        class="flex items-center gap-1.5 text-[10px] font-black tracking-widest text-[#003628] uppercase transition-colors hover:opacity-70 cursor-pointer"
                                    >
                                        <RefreshCw class="size-3" /> Reset
                                    </button>
                                </div>

                                <div class="space-y-4">
                                    <!-- Lifecycle Status (server-side) -->
                                    <div v-if="showStatusFilter && statuses.length" class="space-y-1.5">
                                        <label class="ml-1 text-[9px] font-black tracking-widest text-slate-500 uppercase">Lifecycle Status</label>
                                        <select
                                            :value="selectedStatus"
                                            class="h-9 w-full appearance-none rounded-xl border border-slate-200 bg-slate-50 px-3 text-[11px] font-medium text-slate-700 outline-none focus:border-[#003628]/50 focus:bg-white"
                                            @change="handleStatusChange(($event.target as HTMLSelectElement).value)"
                                        >
                                            <option value="all">Semua Status</option>
                                            <option v-for="s in statuses" :key="s.id" :value="String(s.id)">
                                                {{ s.name }}{{ s.count !== undefined ? ` (${s.count})` : '' }}
                                            </option>
                                        </select>
                                    </div>

                                    <!-- Status (client-side, hardware/laptop) -->
                                    <div v-if="isHardwareType && !showStatusFilter && statusOptions && statusOptions.length" class="space-y-1.5">
                                        <label class="ml-1 text-[9px] font-black tracking-widest text-slate-500 uppercase">Status</label>
                                        <select
                                            :value="selectedStateName"
                                            class="h-9 w-full appearance-none rounded-xl border border-slate-200 bg-slate-50 px-3 text-[11px] font-medium text-slate-700 outline-none focus:border-[#003628]/50 focus:bg-white"
                                            @change="handleStateNameChange(($event.target as HTMLSelectElement).value)"
                                        >
                                            <option value="">Semua Status</option>
                                            <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
                                                {{ opt.value }} ({{ opt.count }})
                                            </option>
                                        </select>
                                    </div>

                                    <!-- Kategori -->
                                    <div class="space-y-1.5">
                                        <label class="ml-1 text-[9px] font-black tracking-widest text-slate-500 uppercase">Kategori</label>
                                        <select
                                            :value="selectedCategory"
                                            class="h-9 w-full appearance-none rounded-xl border border-slate-200 bg-slate-50 px-3 text-[11px] font-medium text-slate-700 outline-none focus:border-[#003628]/50 focus:bg-white"
                                            @change="handleCategoryChange(($event.target as HTMLSelectElement).value)"
                                        >
                                            <option value="">Semua Kategori</option>
                                            <option v-for="opt in categoryOptions" :key="opt.value" :value="opt.value">
                                                {{ opt.value }} ({{ opt.count }})
                                            </option>
                                        </select>
                                    </div>

                                    <!-- Deployment Lokasi -->
                                    <div class="space-y-1.5">
                                        <label class="ml-1 text-[9px] font-black tracking-widest text-slate-500 uppercase">Deployment Lokasi</label>
                                        <select
                                            :value="selectedLocation"
                                            class="h-9 w-full appearance-none rounded-xl border border-slate-200 bg-slate-50 px-3 text-[11px] font-medium text-slate-700 outline-none focus:border-[#003628]/50 focus:bg-white"
                                            @change="handleLocationChange(($event.target as HTMLSelectElement).value)"
                                        >
                                            <option value="">Semua Lokasi</option>
                                            <option v-for="opt in locationOptions" :key="opt.value" :value="opt.value">
                                                {{ opt.value }} ({{ opt.count }})
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </Transition>
                    </div>

                    <!-- Create Button -->
                    <Link
                        :href="createHref"
                        class="h-8 px-3 rounded-lg bg-[#003628] text-white flex items-center gap-1.5 transition-all hover:opacity-90 shadow-sm active:scale-95 shrink-0"
                    >
                        <Plus class="size-4" />
                        <span class="text-xs font-bold">{{ addButtonLabel }}</span>
                    </Link>
                </div>
            </div>

            <!-- Desktop Table -->
            <div
                class="hidden overflow-hidden rounded-xl border border-slate-200/50 md:block"
            >
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="bg-slate-50">
                            <th
                                class="w-10 border-b border-slate-200 px-5 py-3 text-left"
                            >
                                <input
                                    type="checkbox"
                                    :checked="isAllSelected"
                                    @change="toggleSelectAll"
                                    class="size-4 cursor-pointer rounded border-slate-300 text-primary focus:ring-primary/20"
                                />
                            </th>
                            <th
                                class="w-12 border-b border-slate-200 px-5 py-3 text-left text-[10px] font-black tracking-widest text-slate-600 uppercase"
                            >
                                #
                            </th>
                            <th
                                v-for="column in tableColumns"
                                :key="column.key"
                                class="border-b border-slate-200 px-5 py-3 text-left"
                            >
                                <button
                                    v-if="column.sortKey"
                                    type="button"
                                    class="inline-flex items-center gap-1.5 text-[10px] font-black tracking-widest uppercase transition-colors"
                                    :class="
                                        sortKey === column.sortKey
                                            ? 'text-primary'
                                            : 'text-slate-500 hover:text-primary'
                                    "
                                    @click="toggleColumnSort(column.sortKey)"
                                >
                                    {{ column.label }}
                                    <ArrowDownAZ
                                        v-if="
                                            sortKey === column.sortKey &&
                                            sortDirection === 'asc'
                                        "
                                        class="size-3 text-primary"
                                    />
                                    <ArrowUpAZ
                                        v-else-if="
                                            sortKey === column.sortKey &&
                                            sortDirection === 'desc'
                                        "
                                        class="size-3 text-primary"
                                    />
                                    <ArrowDownAZ
                                        v-else
                                        class="size-3 opacity-20"
                                    />
                                </button>
                                <span
                                    v-else
                                    class="text-[10px] font-black tracking-widest text-slate-500 uppercase"
                                    >{{ column.label }}</span
                                >
                            </th>
                            <th
                                class="border-b border-slate-200 px-5 py-3 text-right text-[10px] font-black tracking-widest text-slate-600 uppercase"
                            >
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/30">
                        <tr
                            v-for="(asset, idx) in paginatedAssets"
                            :key="asset.id"
                            class="group transition-colors hover:bg-primary/[0.015]"
                            :class="{
                                'bg-primary/[0.01]': selectedIds.includes(
                                    asset.id,
                                ),
                            }"
                        >
                            <td class="px-5 py-2.5">
                                <input
                                    type="checkbox"
                                    :checked="selectedIds.includes(asset.id)"
                                    @change="toggleSelect(asset.id)"
                                    class="size-4 cursor-pointer rounded border-slate-300 text-primary focus:ring-primary/20"
                                />
                            </td>
                            <td
                                class="px-5 py-4 font-mono text-[11px] font-bold text-slate-300 tabular-nums"
                            >
                                {{ pageStart + idx }}
                            </td>
                            <td
                                v-for="column in tableColumns"
                                :key="column.key"
                                class="px-5 py-4"
                            >
                                <button
                                    v-if="column.linkStyle === 'asset-tag'"
                                    type="button"
                                    class="flex items-center gap-2 text-[13px] font-black tracking-tight text-slate-900 uppercase transition-colors outline-none group-hover:text-primary"
                                    @click="emit('show-detail', asset)"
                                >
                                    <span>{{
                                        formatCellValue(column.value(asset))
                                    }}</span>
                                    <span
                                        v-if="getInspectionCode(asset)"
                                        class="inline-flex items-center gap-1 rounded-full bg-sky-50 px-2 py-0.5 text-[9px] font-black tracking-widest text-sky-600 uppercase"
                                    >
                                        🔍 {{ getInspectionCode(asset) }}
                                    </span>
                                </button>
                                <button
                                    v-else-if="column.linkStyle === 'text'"
                                    type="button"
                                    class="flex items-center gap-2 text-[13px] font-black tracking-tight text-slate-800 transition-colors outline-none group-hover:text-primary"
                                    @click="emit('show-detail', asset)"
                                >
                                    <span>{{
                                        formatCellValue(column.value(asset))
                                    }}</span>
                                    <span
                                        v-if="getInspectionCode(asset)"
                                        class="ml-auto inline-flex items-center gap-1 rounded-full bg-sky-50 px-2 py-0.5 text-[9px] font-black tracking-widest text-sky-600 uppercase"
                                    >
                                        🔍 {{ getInspectionCode(asset) }}
                                    </span>
                                </button>
                                <template
                                    v-else-if="column.key === 'state_name'"
                                >
                                    <div class="flex items-center gap-1.5">
                                        <span
                                            class="h-1.5 w-1.5 rounded-full"
                                            :class="
                                                statusBadge(
                                                    String(column.value(asset)),
                                                ).dot
                                            "
                                        />
                                        <span
                                            class="text-[10px] font-black tracking-widest text-slate-600 uppercase"
                                        >
                                            {{
                                                formatCellValue(
                                                    column.value(asset),
                                                )
                                            }}
                                        </span>
                                    </div>
                                </template>
                                <template v-else>
                                    <span
                                        class="text-[11px] font-black text-slate-500"
                                    >
                                        {{
                                            formatCellValue(column.value(asset))
                                        }}
                                    </span>
                                </template>
                            </td>
                            <td class="px-5 py-4">
                                <div
                                    class="flex items-center justify-end gap-1.5"
                                >
                                    <button
                                        type="button"
                                        class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-100 bg-white text-slate-400 shadow-sm transition-all hover:border-primary/20 hover:text-primary active:scale-90"
                                        title="Detail"
                                        @click="emit('show-detail', asset)"
                                    >
                                        <Eye class="size-4" />
                                    </button>
                                    <button
                                        v-if="isStockType"
                                        type="button"
                                        class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-100 bg-white text-slate-400 shadow-sm transition-all hover:border-emerald-200 hover:text-emerald-600 active:scale-90"
                                        title="Tambah Stock"
                                        @click="emit('add-stock', asset)"
                                    >
                                        <PackagePlus class="size-4" />
                                    </button>
                                    <Link
                                        :href="getEditHref(asset)"
                                        class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-100 bg-white text-slate-400 shadow-sm transition-all hover:border-amber-200 hover:text-amber-600 active:scale-90"
                                        title="Edit"
                                    >
                                        <Pencil class="size-4" />
                                    </Link>
                                    <button
                                        type="button"
                                        class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-100 bg-white text-slate-400 shadow-sm transition-all hover:border-rose-200 hover:text-rose-600 active:scale-90"
                                        title="Hapus"
                                        @click="emit('delete', asset)"
                                    >
                                        <Trash2 class="size-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!paginatedAssets.length">
                            <td
                                :colspan="tableColumns.length + 3"
                                class="py-24 text-center"
                            >
                                <div class="flex flex-col items-center gap-4">
                                    <div
                                        class="mb-2 flex h-20 w-20 items-center justify-center rounded-full border border-slate-100 bg-slate-50 text-slate-300"
                                    >
                                        <Search class="size-10" />
                                    </div>
                                    <div class="space-y-1">
                                        <h3
                                            class="text-xl font-black tracking-widest text-slate-900 uppercase"
                                        >
                                            No Asset Records
                                        </h3>
                                        <p
                                            class="mx-auto max-w-xs text-sm font-medium text-slate-500"
                                        >
                                            Could not find any items matching
                                            your filters. / Tidak ada data
                                            ditemukan dalam kategori ini.
                                        </p>
                                    </div>
                                    <button
                                        @click="resetFilters"
                                        class="mt-4 h-11 rounded-xl bg-primary/10 px-6 text-[11px] font-black tracking-widest text-primary uppercase transition-all hover:bg-primary/20 active:scale-95"
                                    >
                                        Reset All Filters
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Mobile cards -->
            <div class="space-y-4 md:hidden">
                <div
                    v-for="asset in paginatedAssets"
                    :key="asset.id"
                    class="overflow-hidden rounded-2xl border border-border bg-card p-5 shadow-lg transition-all active:scale-[0.98]"
                >
                    <div class="mb-4 flex items-start justify-between">
                        <div class="space-y-1">
                            <p
                                class="text-[10px] font-black tracking-widest text-primary uppercase"
                            >
                                {{
                                    formatCellValue(
                                        tableColumns[0]?.value(asset),
                                    )
                                }}
                            </p>
                            <h3 class="leading-tight font-bold text-slate-900">
                                {{
                                    asset.name ||
                                    formatCellValue(
                                        tableColumns[1]?.value(asset),
                                    )
                                }}
                            </h3>
                        </div>
                        <div
                            v-if="asset.state_name"
                            class="flex items-center gap-1.5"
                        >
                            <span
                                class="h-1.5 w-1.5 rounded-full"
                                :class="
                                    statusBadge(String(asset.state_name)).dot
                                "
                            />
                            <span
                                class="text-[9px] font-black tracking-widest text-slate-500 uppercase"
                                >{{ asset.state_name }}</span
                            >
                        </div>
                    </div>

                    <div
                        class="mb-6 grid grid-cols-2 gap-x-2 gap-y-4 border-t border-border/40 pt-4"
                    >
                        <div
                            v-for="column in tableColumns.slice(2, 6)"
                            :key="column.key"
                            class="space-y-1"
                        >
                            <p
                                class="text-[9px] font-black tracking-widest text-slate-400 uppercase"
                            >
                                {{ column.label }}
                            </p>
                            <p
                                class="truncate text-[11px] font-bold text-slate-600"
                            >
                                {{ formatCellValue(column.value(asset)) }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            @click="emit('show-detail', asset)"
                            class="flex h-10 flex-1 items-center justify-center gap-2 rounded-xl bg-primary/5 text-xs font-bold text-primary"
                        >
                            <Eye class="size-4" /> Detail
                        </button>
                        <Link
                            :href="getEditHref(asset)"
                            class="flex h-10 items-center justify-center rounded-xl bg-amber-500/10 px-4 text-amber-600"
                        >
                            <Pencil class="size-4" />
                        </Link>
                        <button
                            type="button"
                            class="flex h-10 items-center justify-center rounded-xl bg-rose-500/10 px-4 text-rose-600"
                            title="Hapus"
                            @click="emit('delete', asset)"
                        >
                            <Trash2 class="size-4" />
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <!-- Pagination -->
        <div
            class="mt-8 flex flex-col items-center justify-between gap-6 border-t border-border/20 pt-4 md:flex-row"
        >
            <div class="flex items-center gap-4">
                <div
                    class="flex items-center gap-2 text-[9px] font-bold tracking-widest text-slate-500 uppercase"
                >
                    <span>Show</span>
                    <select
                        :value="pageSize"
                        class="rounded-md border border-border bg-card px-1.5 py-0.5 text-[10px] text-foreground outline-none focus:border-primary/50"
                        @change="
                            $emit(
                                'update:pageSize',
                                Number(
                                    ($event.target as HTMLSelectElement).value,
                                ),
                            )
                        "
                    >
                        <option :value="10">10</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                    </select>
                </div>
                <p
                    class="text-[9px] font-bold tracking-widest text-slate-500 uppercase"
                >
                    <span class="text-slate-900"
                        >{{ pageStart }}–{{ pageEnd }}</span
                    >
                    OF
                    <span class="text-slate-900">{{ totalRows }}</span> ASSETS
                </p>
            </div>

            <div class="flex items-center gap-1">
                <button
                    type="button"
                    class="flex h-8 w-8 items-center justify-center rounded-lg border border-border bg-card transition-all"
                    :class="
                        currentPage === 1
                            ? 'cursor-not-allowed text-slate-300 opacity-20'
                            : 'text-slate-600 hover:border-primary/30 hover:text-primary'
                    "
                    @click="goToPreviousPage"
                >
                    <span class="text-base leading-none">‹</span>
                </button>

                <button
                    v-for="page in pageNumbers"
                    :key="page"
                    type="button"
                    class="flex h-8 min-w-[32px] items-center justify-center rounded-lg border px-2 text-[11px] font-bold transition-all"
                    :class="
                        page === currentPage
                            ? 'border-primary bg-primary text-white shadow-md shadow-primary/10'
                            : 'border-border bg-card text-slate-500 hover:border-primary/30 hover:text-primary'
                    "
                    @click="setPage(page)"
                >
                    {{ page }}
                </button>

                <button
                    type="button"
                    class="flex h-8 w-8 items-center justify-center rounded-lg border border-border bg-card transition-all"
                    :class="
                        currentPage >= totalPages
                            ? 'cursor-not-allowed text-slate-300 opacity-20'
                            : 'text-slate-600 hover:border-primary/30 hover:text-primary'
                    "
                    @click="goToNextPage"
                >
                    <span class="text-base leading-none">›</span>
                </button>
            </div>
        </div>
    </div>
</template>
