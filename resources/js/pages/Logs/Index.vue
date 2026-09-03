<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    LucideActivity as ActivityIcon,
    LucideHistory as HistoryIcon,
    LucideHardDrive as AssetIcon,
    LucideUsers as UserIcon,
    LucideServer as ServerIcon,
    LucideRadio as InfraIcon,
} from 'lucide-vue-next';
import { computed, reactive, watch, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import AssetDetailSheet from '@/pages/Asset/Partials/AssetDetailSheet.vue';
import LogsTable from '@/pages/Logs/Partials/LogsTable.vue';
import LogDetailSheet, { type ActionLogItem } from '@/pages/Logs/Partials/LogDetailSheet.vue';
import type { BreadcrumbItem } from '@/types';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface CategoryOption {
    key: string;
    label: string;
}

interface FilterOptions {
    admins: string[];
    actions: string[];
    items: string[];
    categories: CategoryOption[];
}

interface StatsSummary {
    total: number;
    assets: number;
    users: number;
}

const props = defineProps<{
    logs: {
        data: ActionLogItem[];
        links: PaginationLink[];
        total: number;
        from: number | null;
        to: number | null;
    };
    filters: {
        search?: string;
        filter_category?: string;
        filter_admin?: string;
        filter_action?: string;
        filter_item?: string;
        from_date?: string;
        to_date?: string;
    };
    filter_options: FilterOptions;
    stats: StatsSummary;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Activity Logs', href: '/action-logs' },
];

const filterForm = reactive({
    search: props.filters.search || '',
    filter_category: props.filters.filter_category || '',
    filter_admin: props.filters.filter_admin || '',
    filter_action: props.filters.filter_action || '',
    filter_item: props.filters.filter_item || '',
    from_date: props.filters.from_date || '',
    to_date: props.filters.to_date || '',
});

let debounceTimer: ReturnType<typeof setTimeout> | null = null;

watch(
    () => [
        filterForm.search,
        filterForm.filter_category,
        filterForm.filter_admin,
        filterForm.filter_action,
        filterForm.filter_item,
        filterForm.from_date,
        filterForm.to_date,
    ],
    () => {
        if (debounceTimer) {
            clearTimeout(debounceTimer);
        }

        debounceTimer = setTimeout(() => {
            router.get(
                '/action-logs',
                {
                    search: filterForm.search || undefined,
                    filter_category: filterForm.filter_category || undefined,
                    filter_admin: filterForm.filter_admin || undefined,
                    filter_action: filterForm.filter_action || undefined,
                    filter_item: filterForm.filter_item || undefined,
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
        return 'Belum ada log aktivitas.';
    }

    return `Menampilkan ${props.logs.from ?? 0}-${props.logs.to ?? 0} dari ${props.logs.total} entri log`;
});

const activeFilterCount = computed(
    () =>
        [
            filterForm.search,
            filterForm.filter_category,
            filterForm.filter_admin,
            filterForm.filter_action,
            filterForm.filter_item,
            filterForm.from_date,
            filterForm.to_date,
        ].filter(Boolean).length,
);

const exportUrl = computed(() => {
    const params = new URLSearchParams();

    if (filterForm.search) params.set('search', filterForm.search);
    if (filterForm.filter_category) params.set('filter_category', filterForm.filter_category);
    if (filterForm.filter_admin) params.set('filter_admin', filterForm.filter_admin);
    if (filterForm.filter_action) params.set('filter_action', filterForm.filter_action);
    if (filterForm.filter_item) params.set('filter_item', filterForm.filter_item);
    if (filterForm.from_date) params.set('from_date', filterForm.from_date);
    if (filterForm.to_date) params.set('to_date', filterForm.to_date);

    const queryString = params.toString();
    return queryString ? `/action-logs/export?${queryString}` : '/action-logs/export';
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

// Side Sheet & Asset Drawer State
const detailSheetOpen = ref(false);
const selectedLog = ref<ActionLogItem | null>(null);

const assetSheetOpen = ref(false);
const selectedAssetId = ref<number | null>(null);
const selectedAssetType = ref<string | null>(null);

const openDetail = (log: ActionLogItem) => {
    selectedLog.value = log;
    detailSheetOpen.value = true;
};

const openAssetDetail = (id: number, type: string) => {
    const normalizedType = type.toLowerCase();
    const compatibleTypes = ['assets', 'license', 'accessories', 'consumable', 'component', 'hardware', 'laptop'];
    
    if (compatibleTypes.includes(normalizedType)) {
        selectedAssetId.value = id;
        selectedAssetType.value = (normalizedType === 'hardware' || normalizedType === 'laptop') ? 'assets' : normalizedType;
        assetSheetOpen.value = true;
    } else {
        const url = `/asset/${id}?type=${type}`;
        router.visit(url);
    }
};
</script>

<template>
    <Head title="Activity Logs" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="app-page-shell">
            <!-- Header Section -->
            <header class="mb-6 flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div class="space-y-1">
                    <div class="flex items-center gap-2 text-[10px] font-black tracking-widest text-[#003628] uppercase mb-2">
                        <ActivityIcon class="size-3" />
                        Audit Sistem & Aktivitas Operasional
                    </div>
                    <h1 class="text-3xl font-black tracking-tight text-slate-900 lg:text-4xl">
                        Timeline <span class="text-[#003628] italic">Aktivitas</span>
                    </h1>
                </div>

                <div class="flex items-center gap-3">
                    <div class="h-12 w-12 rounded-2xl border border-slate-200 bg-white shadow-sm flex items-center justify-center">
                        <HistoryIcon class="size-5 text-[#003628]" />
                    </div>
                </div>
            </header>

            <!-- Stats Overview Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5 mb-6">
                <button
                    type="button"
                    @click="filterForm.filter_category = ''"
                    class="p-4 rounded-2xl border transition-all text-left group"
                    :class="filterForm.filter_category === '' ? 'bg-[#003628] text-white border-[#003628] shadow-lg shadow-emerald-950/20' : 'bg-white text-slate-800 border-slate-200/70 hover:border-slate-300 shadow-sm'"
                >
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[9px] font-black uppercase tracking-widest" :class="filterForm.filter_category === '' ? 'text-emerald-300' : 'text-slate-400'">Semua Log (Aset & User)</span>
                        <ActivityIcon class="size-4" :class="filterForm.filter_category === '' ? 'text-emerald-200' : 'text-slate-400'" />
                    </div>
                    <p class="text-xl font-black tabular-nums">{{ stats.total }}</p>
                </button>

                <button
                    type="button"
                    @click="filterForm.filter_category = 'assets'"
                    class="p-4 rounded-2xl border transition-all text-left group"
                    :class="filterForm.filter_category === 'assets' ? 'bg-[#003628] text-white border-[#003628] shadow-lg shadow-emerald-950/20' : 'bg-white text-slate-800 border-slate-200/70 hover:border-slate-300 shadow-sm'"
                >
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[9px] font-black uppercase tracking-widest" :class="filterForm.filter_category === 'assets' ? 'text-emerald-300' : 'text-slate-400'">Aset & Perangkat</span>
                        <AssetIcon class="size-4" :class="filterForm.filter_category === 'assets' ? 'text-emerald-200' : 'text-slate-400'" />
                    </div>
                    <p class="text-xl font-black tabular-nums">{{ stats.assets }}</p>
                </button>

                <button
                    type="button"
                    @click="filterForm.filter_category = 'users'"
                    class="p-4 rounded-2xl border transition-all text-left group"
                    :class="filterForm.filter_category === 'users' ? 'bg-[#003628] text-white border-[#003628] shadow-lg shadow-emerald-950/20' : 'bg-white text-slate-800 border-slate-200/70 hover:border-slate-300 shadow-sm'"
                >
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[9px] font-black uppercase tracking-widest" :class="filterForm.filter_category === 'users' ? 'text-emerald-300' : 'text-slate-400'">Pengguna & Akun</span>
                        <UserIcon class="size-4" :class="filterForm.filter_category === 'users' ? 'text-emerald-200' : 'text-slate-400'" />
                    </div>
                    <p class="text-xl font-black tabular-nums">{{ stats.users }}</p>
                </button>
            </div>

            <!-- Main Table Component -->
            <LogsTable
                :logs="logs"
                :filter-form="filterForm"
                :filter-options="filter_options"
                :summary-text="summaryText"
                :export-url="exportUrl"
                :apply-date-preset="applyDatePreset"
                :clear-date-filters="clearDateFilters"
                :is-preset-active="isPresetActive"
                :active-filter-count="activeFilterCount"
                @open-asset="openAssetDetail"
                @open-detail="openDetail"
            />
        </div>

        <!-- Detail Sheet Modal -->
        <LogDetailSheet
            v-if="selectedLog"
            v-model:open="detailSheetOpen"
            :log="selectedLog"
            @update:open="(val) => { if (!val) selectedLog = null; }"
            @open-asset="openAssetDetail"
        />

        <!-- Asset Drawer Sheet -->
        <AssetDetailSheet
            v-if="selectedAssetId"
            v-model:open="assetSheetOpen"
            :asset-id="selectedAssetId"
            :asset-type="selectedAssetType || 'assets'"
            @update:open="(val) => { if (!val) selectedAssetId = null; }"
        />
    </AppLayout>
</template>
