<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    LucideActivity as ActivityIcon,
    LucideHistory as HistoryIcon,
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
            <!-- Combined Header + Table Card -->
            <div class="bg-white rounded-[28px] border border-slate-200/70 shadow-xl shadow-slate-200/50">
                <!-- Header Section -->
                <div class="flex items-center justify-between gap-4 px-6 py-4 border-b border-slate-100">
                    <!-- Brand -->
                    <div class="flex items-center gap-2.5">
                        <div class="h-10 w-10 rounded-2xl bg-[#003628] flex items-center justify-center shadow-md shadow-[#003628]/25 shrink-0">
                            <ActivityIcon class="size-5 text-white"/>
                        </div>
                        <div>
                            <h1 class="text-[15px] font-black tracking-tight text-slate-900 leading-none">
                                Log <span class="text-[#003628]">Aktivitas</span>
                            </h1>
                            <p class="text-[9px] text-slate-400 mt-0.5">Timeline Audit & Aktivitas Operasional</p>
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
                            <span class="text-slate-400">Aset:</span>
                            <span class="text-slate-700">{{ stats.assets.toLocaleString() }}</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <div class="w-1.5 h-1.5 rounded-full bg-emerald-500"/>
                            <span class="text-slate-400">Users:</span>
                            <span class="text-slate-700">{{ stats.users.toLocaleString() }}</span>
                        </div>
                    </div>
                </div>

                <!-- Table Content -->
                <div class="p-6 lg:p-8">
                    <LogsTable
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
                        @open-asset="openAssetDetail"
                        @open-detail="openDetail"
                    />
                </div>
            </div>
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
