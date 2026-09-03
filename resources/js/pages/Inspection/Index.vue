<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { onClickOutside } from '@vueuse/core';
import {
    CheckCircle2,
    Download,
    Edit2,
    Eye,
    Plus,
    Printer,
    RefreshCw,
    Search,
    Share2,
    SlidersHorizontal,
    Trash2,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import AppConfirmDialog from '@/components/AppConfirmDialog.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

interface Inspection {
    id: number;
    report_id: string;
    location: string;
    user: string;
    department: string;
    company?: string;
    device_name?: string;
    asset_tag?: string;
    device_category?: string;
    asset_snapshot: string | null;
    issue_description: string;
    date: string;
    created_at: string;
    completed_at?: string | null;
    completed_pdf_path?: string | null;
    share_url?: string | null;
    signed_count?: number;
}

interface Props {
    inspections: {
        data: Inspection[];
        links: any[];
        meta: any;
    };
    filters: {
        search: string;
        location: string;
        department: string;
        from_date: string | null;
        to_date: string | null;
        status?: 'active' | 'completed' | 'cancelled';
    };
    locationOptions: string[];
    departmentOptions: string[];
    activeCount: number;
    completedCount: number;
    cancelledCount: number;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inspection', href: '/inspection' },
];

const search = ref(props.filters?.search || '');
const location = ref(props.filters?.location || '');
const department = ref(props.filters?.department || '');
const fromDate = ref(props.filters?.from_date || '');
const toDate = ref(props.filters?.to_date || '');
const status = ref<'active' | 'completed' | 'cancelled'>(
    props.filters?.status || 'active',
);

const localFilters = ref({
    location: props.filters?.location || '',
    department: props.filters?.department || '',
    from_date: props.filters?.from_date || '',
    to_date: props.filters?.to_date || '',
    status: props.filters?.status || 'active',
});

const showFilters = ref(false);
const filterPanelRef = ref<HTMLElement | null>(null);
onClickOutside(filterPanelRef, () => {
    showFilters.value = false;
});

const activeFilterCount = computed(
    () =>
        [location.value, department.value, fromDate.value, toDate.value].filter(
            Boolean,
        ).length,
);

const applyFilters = () => {
    location.value = localFilters.value.location;
    department.value = localFilters.value.department;
    fromDate.value = localFilters.value.from_date;
    toDate.value = localFilters.value.to_date;
    status.value = localFilters.value.status;
    showFilters.value = false;
    router.get(
        '/inspection',
        {
            search: search.value,
            location: location.value,
            department: department.value,
            from_date: fromDate.value,
            to_date: toDate.value,
            status: status.value,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const selectStatus = (value: 'active' | 'completed' | 'cancelled') => {
    localFilters.value.status = value;
    applyFilters();
};

const resetFilters = () => {
    search.value = '';
    location.value = '';
    department.value = '';
    fromDate.value = '';
    toDate.value = '';
    status.value = 'active';
    localFilters.value = {
        location: '',
        department: '',
        from_date: '',
        to_date: '',
        status: 'active',
    };
    router.get(
        '/inspection',
        {},
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

let debounceTimer: any = null;
watch(search, () => {
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        router.get(
            '/inspection',
            {
                search: search.value,
                location: location.value,
                department: department.value,
                from_date: fromDate.value,
                to_date: toDate.value,
                status: status.value,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 300);
});

const formatDate = (date?: string | null) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const resolveAssetName = (inspection: Inspection) => {
    if (inspection.asset_snapshot) {
        try {
            const snap = JSON.parse(inspection.asset_snapshot);
            return (
                snap.name || snap.asset_name || inspection.device_name || '-'
            );
        } catch {
            /* ignore */
        }
    }
    return inspection.device_name || '-';
};

const truncate = (str: string, len = 55) =>
    str && str.length > len ? str.slice(0, len) + '?' : str || '-';

const deleteConfirmId = ref<number | null>(null);
const activeDeleteItem = computed(
    () =>
        props.inspections.data.find((i) => i.id === deleteConfirmId.value) ??
        null,
);
const deleteItem = () => {
    if (!deleteConfirmId.value) return;
    router.delete(`/inspection/${deleteConfirmId.value}`, {
        preserveScroll: true,
        onFinish: () => {
            deleteConfirmId.value = null;
        },
    });
};

const finalizeConfirmId = ref<number | null>(null);
const finalizeProcessing = ref(false);
const activeFinalizeItem = computed(
    () =>
        props.inspections.data.find((i) => i.id === finalizeConfirmId.value) ??
        null,
);
const finalizeItem = () => {
    if (!finalizeConfirmId.value) return;
    finalizeProcessing.value = true;
    router.post(
        `/inspection/${finalizeConfirmId.value}/complete`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                finalizeProcessing.value = false;
                finalizeConfirmId.value = null;
            },
        },
    );
};

const copyShareLink = async (shareUrl?: string | null) => {
    if (!shareUrl) return;
    try {
        await navigator.clipboard.writeText(shareUrl);
    } catch {
        const el = document.createElement('textarea');
        el.value = shareUrl;
        el.style.position = 'fixed';
        el.style.opacity = '0';
        document.body.appendChild(el);
        el.select();
        document.execCommand('copy');
        document.body.removeChild(el);
    }
};

const escapeCsv = (v: any) => {
    const s = String(v ?? '');
    return s.includes(',') || s.includes('"') || s.includes('\n')
        ? `"${s.replace(/"/g, '""')}"`
        : s;
};
const downloadCsv = () => {
    const header = [
        'Report ID',
        'Location',
        'User',
        'Department',
        'Asset',
        'Issue',
        'Date',
    ];
    const rows = props.inspections.data.map((i) => [
        i.report_id,
        i.location,
        i.user,
        i.department,
        resolveAssetName(i),
        i.issue_description,
        formatDate(i.date),
    ]);
    const csv = [header, ...rows]
        .map((r) => r.map(escapeCsv).join(','))
        .join('\n');
    const url = URL.createObjectURL(
        new Blob([csv], { type: 'text/csv;charset=utf-8;' }),
    );
    const a = document.createElement('a');
    a.href = url;
    a.download = `inspection-${new Date().toISOString().slice(0, 10)}.csv`;
    a.click();
    URL.revokeObjectURL(url);
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="app-page-shell">
            <Head title="Inspection" />

            <div
                class="rounded-[32px] border border-slate-200/60 bg-white p-6 shadow-xl shadow-slate-200/50 lg:p-8"
            >
                <!-- Toolbar -->
                <div
                    class="mb-6 flex flex-col justify-between gap-4 lg:flex-row lg:items-center"
                >
                    <div class="relative w-full lg:max-w-md">
                        <Search
                            class="absolute top-1/2 left-4 size-4 -translate-y-1/2 text-slate-400"
                        />
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Cari report ID, user, atau issue..."
                            class="h-12 w-full rounded-2xl border border-slate-100 bg-white pr-4 pl-12 text-sm text-slate-900 transition-all outline-none placeholder:text-slate-400 focus:border-primary/50 focus:ring-4 focus:ring-primary/10"
                        />
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="flex h-11 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-sm font-bold text-slate-600 shadow-sm transition-all hover:bg-[#003628]/5 hover:text-[#003628] active:scale-95"
                            @click="downloadCsv"
                        >
                            <Download class="size-4" />
                        </button>

                        <!-- Filter flyout -->
                        <div ref="filterPanelRef" class="relative">
                            <button
                                type="button"
                                class="relative flex size-11 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 shadow-sm transition-all hover:bg-[#003628]/5 hover:text-[#003628]"
                                @click="showFilters = !showFilters"
                            >
                                <SlidersHorizontal class="size-5" />
                                <span
                                    v-if="activeFilterCount"
                                    class="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-[#003628] text-[10px] font-black text-white ring-4 ring-white"
                                    >{{ activeFilterCount }}</span
                                >
                            </button>

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
                                    class="absolute top-full right-0 z-50 mt-4 w-80 overflow-hidden rounded-[32px] border border-slate-200 bg-white p-8 shadow-2xl"
                                >
                                    <div
                                        class="mb-8 flex items-center justify-between"
                                    >
                                        <h3
                                            class="text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                        >
                                            Filter
                                        </h3>
                                        <button
                                            @click="resetFilters"
                                            class="flex items-center gap-1.5 text-[10px] font-black tracking-widest text-primary uppercase transition-colors hover:opacity-70"
                                        >
                                            <RefreshCw class="size-3" /> Reset
                                        </button>
                                    </div>
                                    <div class="mb-6 space-y-2">
                                        <p
                                            class="ml-1 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                        >
                                            Views
                                        </p>
                                        <div class="space-y-2">
                                            <button
                                                v-for="view in [
                                                    {
                                                        value: 'active',
                                                        label: 'Active',
                                                        count: activeCount,
                                                    },
                                                    {
                                                        value: 'completed',
                                                        label: 'Completed',
                                                        count: completedCount,
                                                    },
                                                    {
                                                        value: 'cancelled',
                                                        label: 'Cancelled',
                                                        count: cancelledCount,
                                                    },
                                                ]"
                                                :key="view.value"
                                                type="button"
                                                class="flex w-full items-center justify-between rounded-2xl border px-4 py-3 text-left text-[12px] font-bold transition-all"
                                                :class="
                                                    localFilters.status ===
                                                    view.value
                                                        ? 'border-primary/30 bg-primary/5 text-primary'
                                                        : 'border-slate-100 bg-white text-slate-600 hover:border-primary/20'
                                                "
                                                @click="
                                                    selectStatus(
                                                        view.value as typeof status,
                                                    )
                                                "
                                            >
                                                <span>{{ view.label }}</span>
                                                <span
                                                    class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px]"
                                                    >{{ view.count }}</span
                                                >
                                            </button>
                                        </div>
                                    </div>
                                    <div class="space-y-4">
                                        <div class="space-y-1.5">
                                            <label
                                                class="ml-1 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                                >Lokasi</label
                                            >
                                            <div class="relative">
                                                <select
                                                    v-model="
                                                        localFilters.location
                                                    "
                                                    class="h-11 w-full appearance-none rounded-2xl border border-slate-100 bg-slate-50 px-4 pr-10 text-[13px] font-bold text-slate-900 outline-none focus:border-primary/50"
                                                >
                                                    <option value="">
                                                        Semua Lokasi
                                                    </option>
                                                    <option
                                                        v-for="opt in locationOptions"
                                                        :key="opt"
                                                        :value="opt"
                                                    >
                                                        {{ opt }}
                                                    </option>
                                                </select>
                                                <svg
                                                    class="pointer-events-none absolute top-1/2 right-4 size-4 -translate-y-1/2 text-slate-400"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fill-rule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clip-rule="evenodd"
                                                    />
                                                </svg>
                                            </div>
                                        </div>
                                        <div class="space-y-1.5">
                                            <label
                                                class="ml-1 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                                >Department</label
                                            >
                                            <div class="relative">
                                                <select
                                                    v-model="
                                                        localFilters.department
                                                    "
                                                    class="h-11 w-full appearance-none rounded-2xl border border-slate-100 bg-slate-50 px-4 pr-10 text-[13px] font-bold text-slate-900 outline-none focus:border-primary/50"
                                                >
                                                    <option value="">
                                                        Semua Department
                                                    </option>
                                                    <option
                                                        v-for="opt in departmentOptions"
                                                        :key="opt"
                                                        :value="opt"
                                                    >
                                                        {{ opt }}
                                                    </option>
                                                </select>
                                                <svg
                                                    class="pointer-events-none absolute top-1/2 right-4 size-4 -translate-y-1/2 text-slate-400"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fill-rule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clip-rule="evenodd"
                                                    />
                                                </svg>
                                            </div>
                                        </div>
                                        <div class="space-y-1.5">
                                            <label
                                                class="ml-1 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                                >Dari Tanggal</label
                                            >
                                            <input
                                                v-model="localFilters.from_date"
                                                type="date"
                                                class="h-11 w-full rounded-2xl border border-slate-100 bg-slate-50 px-4 text-[13px] font-bold text-slate-900 outline-none focus:border-primary/50"
                                            />
                                        </div>
                                        <div class="space-y-1.5">
                                            <label
                                                class="ml-1 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                                >Sampai Tanggal</label
                                            >
                                            <input
                                                v-model="localFilters.to_date"
                                                type="date"
                                                class="h-11 w-full rounded-2xl border border-slate-100 bg-slate-50 px-4 text-[13px] font-bold text-slate-900 outline-none focus:border-primary/50"
                                            />
                                        </div>
                                        <button
                                            class="mt-2 h-12 w-full rounded-2xl bg-[#003628] text-sm font-black tracking-widest text-white uppercase shadow-lg shadow-primary/10 transition-all hover:opacity-90 active:scale-95"
                                            @click="applyFilters"
                                        >
                                            Terapkan Filter
                                        </button>
                                    </div>
                                </div>
                            </Transition>
                        </div>

                        <Link
                            href="/inspection/create"
                            class="flex h-11 items-center justify-center gap-2 rounded-xl bg-[#003628] px-6 text-[13px] font-bold text-white shadow-lg shadow-emerald-900/20 transition-all hover:bg-[#003628]/90 active:scale-95"
                        >
                            <Plus class="size-4" />
                            <span>Buat Inspection</span>
                        </Link>
                    </div>
                </div>

                <!-- Desktop Table -->
                <div
                    v-if="inspections.data.length"
                    class="hidden overflow-hidden rounded-xl border border-slate-200/50 md:block"
                >
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="border-b border-slate-50 bg-slate-50/50">
                                <th
                                    class="w-12 px-6 py-5 text-left text-[10px] font-black tracking-[0.15em] text-slate-400 uppercase"
                                >
                                    #
                                </th>
                                <th
                                    class="px-6 py-5 text-left text-[10px] font-black tracking-[0.15em] text-slate-400 uppercase"
                                >
                                    REPORT ID
                                </th>
                                <th
                                    class="px-6 py-5 text-left text-[10px] font-black tracking-[0.15em] text-slate-400 uppercase"
                                >
                                    LOCATION
                                </th>
                                <th
                                    class="px-6 py-5 text-left text-[10px] font-black tracking-[0.15em] text-slate-400 uppercase"
                                >
                                    USER
                                </th>
                                <th
                                    class="px-6 py-5 text-left text-[10px] font-black tracking-[0.15em] text-slate-400 uppercase"
                                >
                                    DEPARTMENT
                                </th>
                                <th
                                    class="px-6 py-5 text-left text-[10px] font-black tracking-[0.15em] text-slate-400 uppercase"
                                >
                                    ASSET
                                </th>
                                <th
                                    class="px-6 py-5 text-left text-[10px] font-black tracking-[0.15em] text-slate-400 uppercase"
                                >
                                    ISSUE
                                </th>
                                <th
                                    class="px-6 py-5 text-left text-[10px] font-black tracking-[0.15em] text-slate-400 uppercase"
                                >
                                    DATE
                                </th>
                                <th
                                    class="px-6 py-5 text-center text-[10px] font-black tracking-[0.15em] text-slate-400 uppercase"
                                >
                                    STATUS
                                </th>
                                <th
                                    class="w-40 px-6 py-5 text-center text-[10px] font-black tracking-[0.15em] text-slate-400 uppercase"
                                >
                                    ACTION
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <tr
                                v-for="(item, index) in inspections.data"
                                :key="item.id"
                                class="group transition-colors hover:bg-slate-50/50"
                            >
                                <td
                                    class="px-6 py-4 text-xs font-bold text-slate-300 tabular-nums"
                                >
                                    {{
                                        ((inspections.meta?.current_page ?? 1) -
                                            1) *
                                            (inspections.meta?.per_page ?? 15) +
                                        index +
                                        1
                                    }}
                                </td>
                                <td class="px-6 py-4">
                                    <Link
                                        :href="`/inspection/${item.id}`"
                                        class="text-[13px] font-black tracking-tight text-slate-900 uppercase transition-colors hover:text-primary"
                                    >
                                        {{ item.report_id }}
                                    </Link>
                                </td>
                                <td class="px-6 py-4">
                                    <p
                                        class="max-w-[120px] truncate text-[13px] font-black text-slate-700"
                                    >
                                        {{ item.location || '-' }}
                                    </p>
                                </td>
                                <td class="px-6 py-4">
                                    <p
                                        class="max-w-[120px] truncate text-[13px] font-black text-slate-700"
                                    >
                                        {{ item.user || '-' }}
                                    </p>
                                </td>
                                <td class="px-6 py-4">
                                    <p
                                        class="max-w-[100px] truncate text-[10px] font-black tracking-tight text-slate-400 uppercase"
                                    >
                                        {{ item.department || '-' }}
                                    </p>
                                </td>
                                <td class="px-6 py-4">
                                    <p
                                        class="max-w-[120px] truncate text-[12px] font-bold text-slate-700"
                                    >
                                        {{ resolveAssetName(item) }}
                                    </p>
                                </td>
                                <td class="px-6 py-4">
                                    <p
                                        class="max-w-[160px] truncate text-[11px] text-slate-500"
                                    >
                                        {{ truncate(item.issue_description) }}
                                    </p>
                                </td>
                                <td class="px-6 py-4">
                                    <p
                                        class="text-[12px] font-black whitespace-nowrap text-slate-700"
                                    >
                                        {{ formatDate(item.date) }}
                                    </p>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span
                                        v-if="item.completed_at"
                                        class="inline-flex items-center rounded-full border border-emerald-100 bg-emerald-50 px-2.5 py-1 text-[9px] font-black tracking-widest text-emerald-600 uppercase"
                                        >Completed</span
                                    >
                                    <span
                                        v-else
                                        class="inline-flex items-center rounded-full border border-amber-100 bg-amber-50 px-2.5 py-1 text-[9px] font-black tracking-widest text-amber-600 uppercase"
                                        >Open</span
                                    >
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <div
                                        class="flex items-center justify-center gap-1.5"
                                    >
                                        <!-- Detail ? always -->
                                        <Link
                                            :href="`/inspection/${item.id}`"
                                            class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-400 shadow-sm transition-colors hover:text-primary"
                                            title="Detail"
                                        >
                                            <Eye class="size-4" />
                                        </Link>
                                        <!-- Print / PDF ? always; goes to PDF if completed -->
                                        <a
                                            :href="`/inspection/${item.id}/print`"
                                            target="_blank"
                                            class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-400 shadow-sm transition-colors hover:text-primary"
                                            title="Print / PDF"
                                        >
                                            <Printer class="size-4" />
                                        </a>
                                        <!-- Finalize ? only when all 4 signed and not yet completed -->
                                        <button
                                            v-if="
                                                !item.completed_at &&
                                                (item.signed_count ?? 0) >= 4
                                            "
                                            class="flex h-8 w-8 items-center justify-center rounded-lg border border-[#003628]/20 bg-[#003628]/10 text-[#003628] shadow-sm transition-all hover:bg-[#003628] hover:text-white"
                                            title="Selesaikan & Finalisasi"
                                            @click="finalizeConfirmId = item.id"
                                        >
                                            <CheckCircle2 class="size-4" />
                                        </button>
                                        <!-- Public share link -->
                                        <button
                                            v-if="item.share_url"
                                            class="flex h-8 w-8 items-center justify-center rounded-lg border border-[#003628]/10 bg-[#003628]/5 text-[#003628] shadow-sm transition-all hover:bg-[#003628] hover:text-white"
                                            title="Salin link tanda tangan"
                                            @click="
                                                copyShareLink(item.share_url)
                                            "
                                        >
                                            <Share2 class="size-4" />
                                        </button>
                                        <!-- Edit ? only when zero signatures and not completed -->
                                        <Link
                                            v-if="
                                                !item.completed_at &&
                                                (item.signed_count ?? 0) === 0
                                            "
                                            :href="`/inspection/${item.id}/edit`"
                                            class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-400 shadow-sm transition-colors hover:text-amber-500"
                                            title="Edit"
                                        >
                                            <Edit2 class="size-4" />
                                        </Link>
                                        <!-- Delete ? only when zero signatures and not completed -->
                                        <button
                                            v-if="
                                                !item.completed_at &&
                                                (item.signed_count ?? 0) === 0
                                            "
                                            class="flex h-8 w-8 items-center justify-center rounded-lg border border-rose-100 bg-rose-50 text-rose-400 shadow-sm transition-all hover:bg-rose-500 hover:text-white"
                                            title="Hapus"
                                            @click="deleteConfirmId = item.id"
                                        >
                                            <Trash2 class="size-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile cards -->
                <div v-if="inspections.data.length" class="space-y-4 md:hidden">
                    <article
                        v-for="item in inspections.data"
                        :key="item.id"
                        class="overflow-hidden rounded-3xl border border-slate-100 bg-white p-6 shadow-xl shadow-slate-200/30"
                    >
                        <div class="mb-4 flex items-start justify-between">
                            <div class="space-y-1">
                                <h3
                                    class="text-[11px] font-black tracking-widest text-primary uppercase"
                                >
                                    {{ item.report_id }}
                                </h3>
                                <p
                                    class="text-[13px] font-black text-slate-900"
                                >
                                    {{ item.user }}
                                </p>
                            </div>
                            <span
                                class="rounded-full bg-slate-100 px-2.5 py-1 text-[9px] font-black tracking-widest text-slate-600 uppercase"
                                >{{ formatDate(item.date) }}</span
                            >
                        </div>
                        <div
                            class="grid grid-cols-2 gap-x-2 gap-y-4 border-b border-slate-50 pb-4"
                        >
                            <div>
                                <p
                                    class="text-[9px] font-black tracking-[0.15em] text-slate-400 uppercase"
                                >
                                    Lokasi
                                </p>
                                <p
                                    class="truncate text-[11px] font-black text-slate-600"
                                >
                                    {{ item.location }}
                                </p>
                            </div>
                            <div>
                                <p
                                    class="text-[9px] font-black tracking-[0.15em] text-slate-400 uppercase"
                                >
                                    Asset
                                </p>
                                <p
                                    class="truncate text-[11px] font-black text-slate-600"
                                >
                                    {{ resolveAssetName(item) }}
                                </p>
                            </div>
                        </div>
                        <div class="mt-4 flex items-center justify-between">
                            <p
                                class="max-w-[60%] truncate text-[10px] text-slate-400"
                            >
                                {{ truncate(item.issue_description, 40) }}
                            </p>
                            <div class="flex items-center gap-2">
                                <Link
                                    :href="`/inspection/${item.id}`"
                                    class="text-slate-400 hover:text-primary"
                                    ><Eye class="size-4"
                                /></Link>
                                <Link
                                    :href="`/inspection/${item.id}/edit`"
                                    class="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-50 text-amber-500"
                                    ><Edit2 class="size-4"
                                /></Link>
                            </div>
                        </div>
                    </article>
                </div>

                <!-- Empty State -->
                <div
                    v-if="!inspections.data.length"
                    class="rounded-[40px] border-2 border-dashed border-slate-100 bg-white py-20 text-center"
                >
                    <div class="flex flex-col items-center gap-6">
                        <div
                            class="flex h-20 w-20 items-center justify-center rounded-full bg-slate-50 text-slate-200"
                        >
                            <Search class="size-8" />
                        </div>
                        <div class="space-y-1">
                            <p
                                class="text-sm font-black tracking-widest text-slate-900 uppercase"
                            >
                                Tidak ada data inspection
                            </p>
                            <p class="text-xs text-slate-400">
                                Coba sesuaikan filter atau kata kunci pencarian.
                            </p>
                        </div>
                        <button
                            @click="resetFilters"
                            class="text-[10px] font-black tracking-[0.2em] text-primary uppercase transition-colors hover:text-primary/70"
                        >
                            Hapus semua filter
                        </button>
                    </div>
                </div>

                <!-- Pagination -->
                <div
                    v-if="
                        inspections.data.length && inspections.links?.length > 3
                    "
                    class="mt-8 flex flex-col items-center justify-between gap-6 border-t border-slate-100 pt-8 md:flex-row"
                >
                    <p
                        class="text-[10px] font-black tracking-[0.15em] text-slate-400 uppercase"
                    >
                        <span class="text-slate-900"
                            >{{ inspections.meta?.from ?? 1 }}?{{
                                inspections.meta?.to ?? inspections.data.length
                            }}</span
                        >
                        DARI
                        <span class="text-slate-900">{{
                            inspections.meta?.total ?? inspections.data.length
                        }}</span>
                        REKAMAN
                    </p>
                    <div class="flex items-center gap-1.5">
                        <template
                            v-for="link in inspections.links"
                            :key="link.label"
                        >
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                class="flex h-9 min-w-[36px] items-center justify-center rounded-xl border px-2 text-[10px] font-black tracking-widest uppercase transition-all"
                                :class="
                                    link.active
                                        ? 'border-[#003628] bg-[#003628] text-white shadow-lg shadow-emerald-900/20'
                                        : 'border-slate-100 bg-white text-slate-400 hover:border-[#003628]/30 hover:text-[#003628]'
                                "
                            >
                                <span v-html="link.label" />
                            </Link>
                            <span
                                v-else
                                class="flex h-9 min-w-[36px] items-center justify-center rounded-xl border border-slate-100 bg-slate-50 px-2 text-[10px] font-black text-slate-300"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Confirm -->
        <AppConfirmDialog
            :open="deleteConfirmId !== null"
            kicker="Hapus Inspection"
            title="Hapus laporan ini?"
            description="Data inspection akan dihapus permanen dan tidak bisa dikembalikan."
            confirm-label="Ya, Hapus"
            cancel-label="Batal"
            confirm-variant="danger"
            :subject="activeDeleteItem?.report_id ?? null"
            @close="deleteConfirmId = null"
            @confirm="deleteItem"
        />

        <!-- Finalize Confirm -->
        <AppConfirmDialog
            :open="finalizeConfirmId !== null"
            kicker="Finalisasi Inspection"
            title="Selesaikan inspection ini?"
            description="Proses ini akan: (1) Checkin asset dari user jika masih dipinjam, (2) Mengubah status asset ke Broken di Snipe-IT, (3) Generate PDF final. Tindakan ini tidak bisa dibatalkan."
            confirm-label="Ya, Selesaikan"
            cancel-label="Batal"
            confirm-variant="warning"
            :processing="finalizeProcessing"
            :subject="activeFinalizeItem?.report_id ?? null"
            @close="finalizeConfirmId = null"
            @confirm="finalizeItem"
        />
    </AppLayout>
</template>
