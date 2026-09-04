<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { onClickOutside } from '@vueuse/core';
import {
    ArrowDownAZ,
    ArrowUpAZ,
    Download,
    Eye,
    PackagePlus,
    Pencil,
    Plus,
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
    categoryOptions: string[];
    selectedCategory: string;
    locationOptions: string[];
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
    canShowGenerateStbIn: boolean;
    canShowGenerateStbOut: boolean;
    canShowGenerateLoan: boolean;
    canShowReturnLoan: boolean;
    getDetailHref: (asset: AssetItem) => string;
    getEditHref: (asset: AssetItem) => string;
    formatCellValue: (value: string | number) => string | number;
    toggleColumnSort: (nextSortKey: SortKey) => void;
    handleStatusChange: (value: string) => void;
    handleCategoryChange: (value: string) => void;
    handleLocationChange: (value: string) => void;
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
        (props.selectedStatus !== 'all' && props.showStatusFilter),
);

const showFilters = ref(false);
const filterPanelRef = ref<HTMLElement | null>(null);

onClickOutside(filterPanelRef, () => {
    showFilters.value = false;
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
</script>

<template>
    <div
        class="flex min-h-[500px] flex-col rounded-[32px] border border-slate-200/60 bg-white p-6 shadow-xl shadow-slate-200/50 lg:p-8"
    >
        <section class="flex flex-1 flex-col">
            <!-- Toolbar -->
            <div
                class="mb-8 flex flex-col justify-between gap-6 lg:flex-row lg:items-center"
            >
                <div class="flex flex-1 items-center gap-4">
                    <div class="group relative max-w-xl flex-1">
                        <div class="relative">
                            <input
                                :value="searchQuery"
                                type="text"
                                placeholder="Cari asset..."
                                class="h-11 w-full rounded-xl border border-slate-100 bg-slate-50 pr-4 pl-10 text-[13px] font-bold text-slate-900 transition-all outline-none placeholder:text-slate-400 focus:border-primary/30 focus:bg-white focus:ring-4 focus:ring-primary/5"
                                @input="
                                    emit(
                                        'update:searchQuery',
                                        ($event.target as HTMLInputElement)
                                            .value,
                                    )
                                "
                            />
                            <Search
                                class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400"
                            />
                        </div>
                    </div>

                    <!-- Create STB and Loan Buttons (Visible only when items selected) -->
                    <Transition
                        enter-active-class="transition duration-300 ease-out"
                        enter-from-class="opacity-0 -translate-x-4"
                        enter-to-class="opacity-100 translate-x-0"
                        leave-active-class="transition duration-200 ease-in"
                        leave-from-class="opacity-100 translate-x-0"
                        leave-to-class="opacity-0 -translate-x-4"
                    >
                        <div
                            v-if="selectedIds.length > 0"
                            class="flex items-center gap-2"
                        >
                            <button
                                v-if="canShowGenerateStbIn"
                                type="button"
                                @click="emit('handover')"
                                class="flex h-12 items-center gap-2 rounded-2xl bg-[#003628] px-6 text-[11px] font-black tracking-widest text-white uppercase shadow-lg shadow-primary/20 transition-all hover:opacity-90 active:scale-95"
                            >
                                <RefreshCw class="size-4.5" />
                                Generate STB IN ({{ selectedIds.length }})
                            </button>
                            <button
                                v-if="canShowGenerateStbOut"
                                type="button"
                                @click="emit('handover')"
                                class="flex h-12 items-center gap-2 rounded-2xl bg-[#003628] px-6 text-[11px] font-black tracking-widest text-white uppercase shadow-lg shadow-primary/20 transition-all hover:opacity-90 active:scale-95"
                            >
                                <RefreshCw class="size-4.5" />
                                {{
                                    isStockType
                                        ? 'Generate STB'
                                        : 'Generate STB OUT'
                                }}
                                ({{ selectedIds.length }})
                            </button>
                            <button
                                v-if="canShowGenerateLoan"
                                type="button"
                                @click="emit('loan')"
                                class="flex h-12 items-center gap-2 rounded-2xl bg-amber-600 px-6 text-[11px] font-black tracking-widest text-white uppercase shadow-lg shadow-amber-600/20 transition-all hover:opacity-90 active:scale-95"
                            >
                                <RefreshCw class="size-4.5" />
                                Generate Loan ({{ selectedIds.length }})
                            </button>
                            <button
                                v-if="canShowReturnLoan"
                                type="button"
                                @click="emit('return-loan', selectedIds)"
                                class="flex h-12 items-center gap-2 rounded-2xl bg-red-600 px-6 text-[11px] font-black tracking-widest text-white uppercase shadow-lg shadow-red-600/20 transition-all hover:opacity-90 active:scale-95"
                            >
                                <RefreshCw class="size-4.5" />
                                Return Loan ({{ selectedIds.length }})
                            </button>
                        </div>
                    </Transition>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        class="flex size-11 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 shadow-sm transition-all hover:bg-primary/5 hover:text-primary"
                        title="Export CSV"
                        @click="downloadCsv"
                    >
                        <Download class="size-5" />
                    </button>

                    <div ref="filterPanelRef" class="relative">
                        <button
                            type="button"
                            class="relative flex size-11 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 shadow-sm transition-all hover:bg-primary/5 hover:text-primary"
                            @click="showFilters = !showFilters"
                        >
                            <SlidersHorizontal class="size-5" />
                            <span
                                v-if="hasActiveFilters"
                                class="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-slate-300 text-[10px] font-black text-white ring-4 ring-white"
                            >
                                {{
                                    Number(selectedCategory !== '') +
                                    Number(selectedLocation !== '') +
                                    Number(
                                        selectedStatus !== 'all' &&
                                            showStatusFilter,
                                    )
                                }}
                            </span>
                        </button>

                        <!-- Flyout panel -->
                        <Transition
                            enter-active-class="transition duration-300 ease-out"
                            enter-from-class="opacity-0 translate-y-4 scale-95"
                            enter-to-class="opacity-100 translate-y-0 scale-100"
                            leave-active-class="transition duration-200 ease-in"
                            leave-from-class="opacity-100 translate-y-0 scale-100"
                            leave-to-class="opacity-0 translate-y-4 scale-95"
                        >
                            <div
                                v-if="showFilters"
                                class="absolute top-full right-0 z-50 mt-4 w-80 overflow-hidden rounded-[32px] border border-slate-200 bg-white p-8 shadow-2xl backdrop-blur-xl"
                            >
                                <div
                                    class="mb-8 flex items-center justify-between"
                                >
                                    <h3
                                        class="text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                    >
                                        Refine Search
                                    </h3>
                                    <button
                                        v-if="hasActiveFilters"
                                        @click="resetFilters"
                                        class="flex items-center gap-1.5 text-[10px] font-black tracking-widest text-primary uppercase transition-colors hover:opacity-70"
                                    >
                                        <RefreshCw class="size-3" /> Reset
                                    </button>
                                </div>

                                <div class="space-y-6">
                                    <div
                                        v-if="
                                            showStatusFilter && statuses.length
                                        "
                                        class="space-y-2"
                                    >
                                        <label
                                            class="ml-1 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                            >Lifecycle Status</label
                                        >
                                        <div class="relative">
                                            <select
                                                :value="selectedStatus"
                                                class="h-11 w-full appearance-none rounded-xl border border-slate-100 bg-slate-50 px-4 text-[13px] font-bold text-slate-900 outline-none focus:border-primary/50"
                                                @change="
                                                    handleStatusChange(
                                                        (
                                                            $event.target as HTMLSelectElement
                                                        ).value,
                                                    )
                                                "
                                            >
                                                <option value="all">
                                                    Every State
                                                </option>
                                                <option
                                                    v-for="s in statuses"
                                                    :key="s.id"
                                                    :value="String(s.id)"
                                                >
                                                    {{ s.name }}
                                                </option>
                                            </select>
                                        </div>
                                    </div>

                                    <div
                                        class="space-y-2 border-t border-slate-100 pt-6"
                                    >
                                        <label
                                            class="ml-1 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                            >Kategori</label
                                        >
                                        <select
                                            :value="selectedCategory"
                                            class="h-11 w-full appearance-none rounded-xl border border-slate-100 bg-slate-50 px-4 text-[13px] font-bold text-slate-900 outline-none focus:border-primary/50"
                                            @change="
                                                handleCategoryChange(
                                                    (
                                                        $event.target as HTMLSelectElement
                                                    ).value,
                                                )
                                            "
                                        >
                                            <option value="">
                                                Semua Kategori
                                            </option>
                                            <option
                                                v-for="cat in categoryOptions"
                                                :key="cat"
                                                :value="cat"
                                            >
                                                {{ cat }}
                                            </option>
                                        </select>
                                    </div>

                                    <div
                                        class="space-y-2 border-t border-slate-100 pt-6"
                                    >
                                        <label
                                            class="ml-1 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                            >Deployment Lokasi</label
                                        >
                                        <select
                                            :value="selectedLocation"
                                            class="h-11 w-full appearance-none rounded-xl border border-slate-100 bg-slate-50 px-4 text-[13px] font-bold text-slate-900 outline-none focus:border-primary/50"
                                            @change="
                                                handleLocationChange(
                                                    (
                                                        $event.target as HTMLSelectElement
                                                    ).value,
                                                )
                                            "
                                        >
                                            <option value="">
                                                Semua Lokasi
                                            </option>
                                            <option
                                                v-for="loc in locationOptions"
                                                :key="loc"
                                                :value="loc"
                                            >
                                                {{ loc }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </Transition>
                    </div>

                    <Link
                        :href="createHref"
                        class="ml-2 flex h-11 items-center gap-2 rounded-xl bg-[#003628] px-6 text-white shadow-lg shadow-primary/10 transition-all hover:opacity-90 active:scale-95"
                    >
                        <Plus class="size-5" />
                        <span
                            class="hidden text-xs font-black tracking-widest uppercase sm:inline"
                            >{{ addButtonLabel }}</span
                        >
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
                                    class="text-[13px] font-black tracking-tight text-slate-900 uppercase transition-colors outline-none group-hover:text-primary"
                                    @click="emit('show-detail', asset)"
                                >
                                    {{ formatCellValue(column.value(asset)) }}
                                </button>
                                <button
                                    v-else-if="column.linkStyle === 'text'"
                                    type="button"
                                    class="text-[13px] font-black tracking-tight text-slate-800 transition-colors outline-none group-hover:text-primary"
                                    @click="emit('show-detail', asset)"
                                >
                                    {{ formatCellValue(column.value(asset)) }}
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
