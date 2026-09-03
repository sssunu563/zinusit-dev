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
import { computed, ref } from 'vue';
import AppConfirmDialog from '@/components/AppConfirmDialog.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import QuickReturnModal from '@/pages/Peminjaman/Partials/QuickReturnModal.vue';
import {
    formatPeminjamanDocId,
    isPeminjamanLoanOut,
    resolvePeminjamanDocumentLabel,
} from '@/pages/Peminjaman/utils/peminjaman';
import type { BreadcrumbItem } from '@/types';

interface PeminjamanRecord {
    id: number;
    user_id: number | null;
    group_id: number | null;
    deliver_date: string | null;
    building: string | null;
    batch_no: string | null;
    status: number | null;
    document_type?: string | null;
    movement_type?: string | null;
    returned_at?: string | null;
    expected_return_date?: string | null;
    document_label?: string | null;
    share_url?: string | null;
    completed_pdf_url?: string | null;
    completed_at?: string | null;
    completed_pdf_path?: string | null;
    is_fully_signed?: boolean;
    is_completed?: boolean;
    cancelled_at?: string | null;
    is_cancelled?: boolean;
    created_at: string;
    updated_at: string;
    items: { jumlah: number }[];
    user_name?: string | null;
    user_company?: string | null;
    user_title?: string | null;
    location_name?: string | null;
    it_drafter_signature_path?: string | null;
    requester_received_signature_path?: string | null;
}

interface Props {
    peminjaman: { data: PeminjamanRecord[]; links: any[]; meta: any };
    activeTab: 'pending' | 'completed' | 'cancelled';
    pendingCount: number;
    completedCount: number;
    cancelledCount: number;
    stats: {
        activeLoans: number;
        overdueLoans: number;
        totalAssetsBorrowed: number;
    };
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Peminjaman', href: '/peminjaman' },
];

const searchQuery = ref('');
const selectedFlow = ref('all');
const selectedCompany = ref('');
const selectedLocation = ref('');
const deleteConfirmId = ref<number | null>(null);
const completeConfirmId = ref<number | null>(null);
const quickReturnId = ref<number | null>(null);
const showFilters = ref(false);
const filterPanelRef = ref<HTMLElement | null>(null);
const pageSize = ref(10);
const currentPage = ref(1);
const shareFeedbackId = ref<number | null>(null);
let shareFeedbackTimer: ReturnType<typeof setTimeout> | null = null;

onClickOutside(filterPanelRef, () => {
    showFilters.value = false;
});

const formatDate = (date?: string | null) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const resolveDocId = (item: PeminjamanRecord) =>
    formatPeminjamanDocId({
        id: item.id,
        locationName: (item as any).location_name,
        date: item.created_at,
    }) || `${item.id}`;

const resolveLocation = (item: PeminjamanRecord) => item.location_name || '-';
const resolveCompany = (item: PeminjamanRecord) => item.user_company || '-';
const resolveFlow = (item: PeminjamanRecord) =>
    isPeminjamanLoanOut(item) ? 'Loan Out' : 'Return';

const copyShareLink = async (shareUrl?: string | null) => {
    if (!shareUrl) return;

    try {
        await navigator.clipboard.writeText(shareUrl);
    } catch {
        const input = document.createElement('textarea');
        input.value = shareUrl;
        input.style.position = 'fixed';
        input.style.opacity = '0';
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        document.body.removeChild(input);
    }

    shareFeedbackId.value = Number(
        new URL(shareUrl, window.location.origin).pathname.split('/').pop(),
    );
    if (shareFeedbackTimer) clearTimeout(shareFeedbackTimer);
    shareFeedbackTimer = setTimeout(() => {
        shareFeedbackId.value = null;
    }, 1800);
};
const resolveStatus = (item: PeminjamanRecord) =>
    isCancelled(item)
        ? 'Cancelled'
        : item.document_label || resolvePeminjamanDocumentLabel(item);

const isCancelled = (item: PeminjamanRecord) =>
    Boolean(item.is_cancelled || item.cancelled_at);
const isCompleted = (item: PeminjamanRecord) => {
    return Boolean(item.is_completed || item.completed_at);
};
const isOverdue = (item: PeminjamanRecord) => {
    if (
        !item.expected_return_date ||
        (item as any).returned_at ||
        !isPeminjamanLoanOut(item)
    )
        return false;
    return new Date(item.expected_return_date) < new Date();
};
const canComplete = (item: PeminjamanRecord) =>
    Boolean(item.is_fully_signed) && !isCompleted(item) && !isCancelled(item);
const canReturn = (item: PeminjamanRecord) =>
    isPeminjamanLoanOut(item) &&
    isCompleted(item) &&
    !isCancelled(item) &&
    !(item as any).returned_at;

const resolveSignatureStats = (item: PeminjamanRecord) => {
    const signed = [
        item.it_drafter_signature_path,
        item.requester_received_signature_path,
    ].filter(Boolean).length;
    return `${signed}/2`;
};

const resolveStatusBadgeClass = (item: PeminjamanRecord) => {
    if (isCancelled(item)) return 'bg-rose-50 text-rose-600';
    if (isCompleted(item)) return 'bg-emerald-50 text-emerald-600';
    if (isOverdue(item)) return 'bg-red-50 text-red-600';
    return 'bg-slate-100 text-slate-600';
};

const filteredRows = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    return props.peminjaman.data.filter((item) => {
        const matchQ =
            !q ||
            [
                resolveDocId(item),
                item.user_name || '',
                resolveCompany(item),
                resolveLocation(item),
                resolveFlow(item),
                resolveStatus(item),
            ].some((v) => v.toLowerCase().includes(q));
        const matchFlow =
            selectedFlow.value === 'all' ||
            (selectedFlow.value === 'out' && isPeminjamanLoanOut(item)) ||
            (selectedFlow.value === 'return' && !isPeminjamanLoanOut(item));
        const matchCompany =
            !selectedCompany.value ||
            resolveCompany(item) === selectedCompany.value;
        const matchLocation =
            !selectedLocation.value ||
            resolveLocation(item) === selectedLocation.value;
        return matchQ && matchFlow && matchCompany && matchLocation;
    });
});

const totalPages = computed(() =>
    Math.max(1, Math.ceil(filteredRows.value.length / pageSize.value)),
);
const paginatedRows = computed(() => {
    const start = (currentPage.value - 1) * pageSize.value;
    return filteredRows.value.slice(start, start + pageSize.value);
});
const pageStart = computed(() =>
    filteredRows.value.length
        ? (currentPage.value - 1) * pageSize.value + 1
        : 0,
);
const pageEnd = computed(() =>
    Math.min(currentPage.value * pageSize.value, filteredRows.value.length),
);
const pageNumbers = computed(() => {
    const total = totalPages.value,
        cur = currentPage.value;
    return [...new Set([1, total, cur, cur - 1, cur + 1])]
        .filter((p) => p >= 1 && p <= total)
        .sort((a, b) => a - b);
});

const companyOptions = computed(() =>
    [...new Set(props.peminjaman.data.map(resolveCompany))]
        .filter((v) => v && v !== '-')
        .sort(),
);
const locationOptions = computed(() =>
    [...new Set(props.peminjaman.data.map(resolveLocation))]
        .filter((v) => v && v !== '-')
        .sort(),
);

const activeFilterCount = computed(
    () =>
        [
            selectedFlow.value !== 'all' ? '1' : '',
            selectedCompany.value,
            selectedLocation.value,
        ].filter(Boolean).length,
);

const localFilters = ref({ flow: 'all', company: '', location: '' });

const commitFilters = () => {
    selectedFlow.value = localFilters.value.flow;
    selectedCompany.value = localFilters.value.company;
    selectedLocation.value = localFilters.value.location;
    currentPage.value = 1;
    showFilters.value = false;
};

const resetFilters = () => {
    localFilters.value = { flow: 'all', company: '', location: '' };
    selectedFlow.value = 'all';
    selectedCompany.value = '';
    selectedLocation.value = '';
    searchQuery.value = '';
    currentPage.value = 1;
};

const activeDeleteItem = computed(
    () =>
        props.peminjaman.data.find((i) => i.id === deleteConfirmId.value) ??
        null,
);
const activeCompleteItem = computed(
    () =>
        props.peminjaman.data.find((i) => i.id === completeConfirmId.value) ??
        null,
);

const deleteItem = () => {
    if (!deleteConfirmId.value) return;
    router.post(
        `/peminjaman/${deleteConfirmId.value}/cancel`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                deleteConfirmId.value = null;
            },
        },
    );
};
const completeItem = () => {
    if (!completeConfirmId.value) return;
    router.post(
        `/peminjaman/${completeConfirmId.value}/complete`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                completeConfirmId.value = null;
            },
        },
    );
};

const escapeCsv = (v: string | number) => {
    const s = String(v ?? '');
    return s.includes(',') || s.includes('"') || s.includes('\n')
        ? `"${s.replace(/"/g, '""')}"`
        : s;
};
const downloadCsv = () => {
    const header = [
        'Doc ID',
        'Location',
        'Date',
        'Name',
        'Company',
        'Flow',
        'Status',
        'Items',
    ];
    const rows = filteredRows.value.map((i) => [
        resolveDocId(i),
        resolveLocation(i),
        formatDate(i.created_at),
        i.user_name || '-',
        resolveCompany(i),
        resolveFlow(i),
        resolveStatus(i),
        i.items.length,
    ]);
    const csv = [header, ...rows]
        .map((r) => r.map(escapeCsv).join(','))
        .join('\n');
    const url = URL.createObjectURL(
        new Blob([csv], { type: 'text/csv;charset=utf-8;' }),
    );
    const a = document.createElement('a');
    a.href = url;
    a.download = `peminjaman-${props.activeTab}-${new Date().toISOString().slice(0, 10)}.csv`;
    a.click();
    URL.revokeObjectURL(url);
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="app-page-shell">
            <Head title="Peminjaman Asset" />

            <!-- Table Shell -->
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
                            v-model="searchQuery"
                            type="text"
                            placeholder="Cari dokumen, ID, atau penerima..."
                            class="h-12 w-full rounded-2xl border border-slate-100 bg-white pr-4 pl-12 text-sm text-slate-900 transition-all outline-none placeholder:text-slate-400 focus:border-primary/50 focus:ring-4 focus:ring-primary/10"
                            @input="currentPage = 1"
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

                                    <div class="space-y-6">
                                        <!-- Views -->
                                        <div class="space-y-2">
                                            <label
                                                class="text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                                >Views</label
                                            >
                                            <div class="grid gap-2">
                                                <Link
                                                    href="/peminjaman?tab=pending"
                                                    class="flex items-center justify-between rounded-2xl border p-3.5 transition-all"
                                                    :class="
                                                        activeTab === 'pending'
                                                            ? 'border-[#003628]/20 bg-[#003628]/5 text-[#003628]'
                                                            : 'border-slate-100 text-slate-500 hover:bg-slate-50'
                                                    "
                                                    @click="showFilters = false"
                                                >
                                                    <span
                                                        class="text-xs font-bold"
                                                        >Active</span
                                                    >
                                                    <span
                                                        class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[10px] font-black"
                                                        >{{
                                                            pendingCount
                                                        }}</span
                                                    >
                                                </Link>
                                                <Link
                                                    href="/peminjaman?tab=completed"
                                                    class="flex items-center justify-between rounded-2xl border p-3.5 transition-all"
                                                    :class="
                                                        activeTab ===
                                                        'completed'
                                                            ? 'border-[#003628]/20 bg-[#003628]/5 text-[#003628]'
                                                            : 'border-slate-100 text-slate-500 hover:bg-slate-50'
                                                    "
                                                    @click="showFilters = false"
                                                >
                                                    <span
                                                        class="text-xs font-bold"
                                                        >Completed</span
                                                    >
                                                    <span
                                                        class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[10px] font-black"
                                                        >{{
                                                            completedCount
                                                        }}</span
                                                    >
                                                </Link>
                                                <Link
                                                    href="/peminjaman?tab=cancelled"
                                                    class="flex items-center justify-between rounded-2xl border p-3.5 transition-all"
                                                    :class="
                                                        activeTab ===
                                                        'cancelled'
                                                            ? 'border-[#003628]/20 bg-[#003628]/5 text-[#003628]'
                                                            : 'border-slate-100 text-slate-500 hover:bg-slate-50'
                                                    "
                                                    @click="showFilters = false"
                                                >
                                                    <span
                                                        class="text-xs font-bold"
                                                        >Cancelled</span
                                                    >
                                                    <span
                                                        class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[10px] font-black"
                                                        >{{
                                                            cancelledCount
                                                        }}</span
                                                    >
                                                </Link>
                                            </div>
                                        </div>

                                        <div
                                            class="space-y-4 border-t border-slate-100 pt-6"
                                        >
                                            <div class="space-y-1.5">
                                                <label
                                                    class="ml-1 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                                    >Flow</label
                                                >
                                                <div class="relative">
                                                    <select
                                                        v-model="
                                                            localFilters.flow
                                                        "
                                                        class="h-11 w-full appearance-none rounded-2xl border border-slate-100 bg-slate-50 px-4 pr-10 text-[13px] font-bold text-slate-900 outline-none focus:border-primary/50"
                                                    >
                                                        <option value="all">
                                                            Semua
                                                        </option>
                                                        <option value="out">
                                                            Loan Out
                                                        </option>
                                                        <option value="return">
                                                            Return
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
                                                    >Perusahaan</label
                                                >
                                                <div class="relative">
                                                    <select
                                                        v-model="
                                                            localFilters.company
                                                        "
                                                        class="h-11 w-full appearance-none rounded-2xl border border-slate-100 bg-slate-50 px-4 pr-10 text-[13px] font-bold text-slate-900 outline-none focus:border-primary/50"
                                                    >
                                                        <option value="">
                                                            Semua
                                                        </option>
                                                        <option
                                                            v-for="opt in companyOptions"
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
                                                            Semua
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
                                            <button
                                                class="mt-4 h-12 w-full rounded-2xl bg-[#003628] text-sm font-black tracking-widest text-white uppercase shadow-lg shadow-primary/10 transition-all hover:opacity-90 active:scale-95"
                                                @click="commitFilters"
                                            >
                                                Terapkan Filter
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </Transition>
                        </div>

                        <!-- Create button -->
                        <Link
                            href="/peminjaman/create?movementType=out"
                            class="flex h-11 items-center justify-center gap-2 rounded-xl bg-[#003628] px-6 text-[13px] font-bold text-white shadow-lg shadow-emerald-900/20 transition-all hover:bg-[#003628]/90 active:scale-95"
                        >
                            <Plus class="size-4" />
                            <span>Buat Peminjaman</span>
                        </Link>
                    </div>
                </div>

                <!-- Desktop Table -->
                <div
                    v-if="paginatedRows.length"
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
                                    DOC ID
                                </th>
                                <th
                                    class="px-6 py-5 text-left text-[10px] font-black tracking-[0.15em] text-slate-400 uppercase"
                                >
                                    LOCATION
                                </th>
                                <th
                                    class="px-6 py-5 text-left text-[10px] font-black tracking-[0.15em] text-slate-400 uppercase"
                                >
                                    DATE
                                </th>
                                <th
                                    class="px-6 py-5 text-left text-[10px] font-black tracking-[0.15em] text-slate-400 uppercase"
                                >
                                    NAME
                                </th>
                                <th
                                    class="px-6 py-5 text-left text-[10px] font-black tracking-[0.15em] text-slate-400 uppercase"
                                >
                                    COMPANY
                                </th>
                                <th
                                    class="px-6 py-5 text-left text-[10px] font-black tracking-[0.15em] text-slate-400 uppercase"
                                >
                                    FLOW
                                </th>
                                <th
                                    class="px-6 py-5 text-left text-[10px] font-black tracking-[0.15em] text-slate-400 uppercase"
                                >
                                    STATUS
                                </th>
                                <th
                                    class="px-6 py-5 text-center text-[10px] font-black tracking-[0.15em] text-slate-400 uppercase"
                                >
                                    ITEMS
                                </th>
                                <th
                                    class="w-48 px-6 py-5 text-center text-[10px] font-black tracking-[0.15em] text-slate-400 uppercase"
                                >
                                    ACTION
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <tr
                                v-for="(item, index) in paginatedRows"
                                :key="item.id"
                                class="group transition-colors hover:bg-slate-50/50"
                            >
                                <td
                                    class="px-6 py-4 text-xs font-bold text-slate-300 tabular-nums"
                                >
                                    {{ pageStart + index }}
                                </td>
                                <td class="px-6 py-4">
                                    <Link
                                        :href="`/peminjaman/${item.id}`"
                                        class="text-[13px] font-black tracking-tight text-slate-900 uppercase transition-colors hover:text-primary"
                                        >{{ resolveDocId(item) }}</Link
                                    >
                                </td>
                                <td class="px-6 py-4">
                                    <p
                                        class="max-w-[120px] truncate text-[13px] font-black text-slate-700"
                                    >
                                        {{ resolveLocation(item) }}
                                    </p>
                                </td>
                                <td class="px-6 py-4">
                                    <p
                                        class="text-[12px] font-black text-slate-700"
                                    >
                                        {{ formatDate(item.created_at) }}
                                    </p>
                                </td>
                                <td class="px-6 py-4">
                                    <p
                                        class="max-w-[120px] truncate text-[13px] font-black text-slate-700"
                                    >
                                        {{ item.user_name || '-' }}
                                    </p>
                                </td>
                                <td class="px-6 py-4">
                                    <p
                                        class="max-w-[120px] truncate text-[10px] font-black tracking-tight text-slate-400 uppercase"
                                    >
                                        {{ resolveCompany(item) }}
                                    </p>
                                </td>
                                <td class="px-6 py-4">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-1 text-[10px] font-black tracking-widest uppercase"
                                        :class="
                                            isPeminjamanLoanOut(item)
                                                ? 'bg-blue-50 text-blue-600'
                                                : 'bg-purple-50 text-purple-600'
                                        "
                                    >
                                        {{ resolveFlow(item) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-1 text-[10px] font-black tracking-widest uppercase"
                                        :class="resolveStatusBadgeClass(item)"
                                    >
                                        {{ resolveStatus(item) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span
                                        class="rounded-full border border-emerald-100 bg-emerald-50 px-3 py-1 text-[9px] font-black whitespace-nowrap text-emerald-600"
                                    >
                                        {{ item.items.length }} ITEMS
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <div
                                        class="flex items-center justify-center gap-1.5"
                                    >
                                        <Link
                                            :href="`/peminjaman/${item.id}`"
                                            class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-400 shadow-sm transition-colors hover:text-primary"
                                            title="Preview"
                                        >
                                            <Eye class="size-4" />
                                        </Link>
                                        <a
                                            :href="
                                                item.completed_pdf_url ||
                                                `/peminjaman/${item.id}/print`
                                            "
                                            target="_blank"
                                            class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-400 shadow-sm transition-colors hover:text-primary"
                                            title="Cetak"
                                        >
                                            <Printer class="size-4" />
                                        </a>
                                        <button
                                            v-if="item.share_url"
                                            type="button"
                                            class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-400 shadow-sm transition-colors hover:text-primary"
                                            :title="
                                                shareFeedbackId === item.id
                                                    ? 'Link berhasil disalin'
                                                    : 'Salin link public'
                                            "
                                            @click="
                                                copyShareLink(item.share_url)
                                            "
                                        >
                                            <CheckCircle2
                                                v-if="
                                                    shareFeedbackId === item.id
                                                "
                                                class="size-4 text-emerald-600"
                                            />
                                            <Share2 v-else class="size-4" />
                                        </button>
                                        <template
                                            v-if="
                                                !isCancelled(item) &&
                                                !isCompleted(item)
                                            "
                                        >
                                            <button
                                                v-if="canComplete(item)"
                                                class="flex h-8 w-8 items-center justify-center rounded-lg border border-amber-200 bg-amber-50 text-amber-500 shadow-sm transition-all hover:bg-amber-500 hover:text-white"
                                                title="Selesaikan"
                                                @click="
                                                    completeConfirmId = item.id
                                                "
                                            >
                                                <CheckCircle2 class="size-4" />
                                            </button>
                                            <Link
                                                v-if="
                                                    resolveSignatureStats(
                                                        item,
                                                    ) === '0/2'
                                                "
                                                :href="`/peminjaman/${item.id}/edit`"
                                                class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-400 shadow-sm transition-colors hover:text-amber-500"
                                                title="Edit"
                                            >
                                                <Edit2 class="size-4" />
                                            </Link>
                                            <button
                                                v-if="
                                                    resolveSignatureStats(
                                                        item,
                                                    ) === '0/2'
                                                "
                                                class="flex h-8 w-8 items-center justify-center rounded-lg border border-rose-100 bg-rose-50 text-rose-400 shadow-sm transition-all hover:bg-rose-500 hover:text-white"
                                                @click="
                                                    deleteConfirmId = item.id
                                                "
                                            >
                                                <Trash2 class="size-4" />
                                            </button>
                                        </template>
                                        <button
                                            v-else-if="canReturn(item)"
                                            class="flex h-8 items-center justify-center rounded-lg border border-emerald-100 bg-emerald-50 px-3 text-[9px] font-black tracking-widest text-emerald-600 uppercase shadow-sm"
                                            @click="quickReturnId = item.id"
                                        >
                                            RETURN
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile cards -->
                <div v-if="paginatedRows.length" class="space-y-4 md:hidden">
                    <article
                        v-for="item in paginatedRows"
                        :key="item.id"
                        class="overflow-hidden rounded-3xl border border-slate-100 bg-white p-6 shadow-xl shadow-slate-200/30"
                    >
                        <div class="mb-4 flex items-start justify-between">
                            <div class="space-y-1">
                                <h3
                                    class="text-[11px] font-black tracking-widest text-primary uppercase"
                                >
                                    {{ resolveDocId(item) }}
                                </h3>
                                <p
                                    class="text-[13px] font-black text-slate-900"
                                >
                                    {{ item.user_name || '-' }}
                                </p>
                            </div>
                            <span
                                class="rounded-full px-2.5 py-1 text-[9px] font-black tracking-widest uppercase"
                                :class="resolveStatusBadgeClass(item)"
                                >{{ resolveStatus(item) }}</span
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
                                    {{ resolveLocation(item) }}
                                </p>
                            </div>
                            <div>
                                <p
                                    class="text-[9px] font-black tracking-[0.15em] text-slate-400 uppercase"
                                >
                                    Flow
                                </p>
                                <p
                                    class="text-[11px] font-black text-slate-600"
                                >
                                    {{ resolveFlow(item) }}
                                </p>
                            </div>
                        </div>
                        <div class="mt-4 flex items-center justify-between">
                            <p
                                class="text-[10px] font-black text-slate-300 uppercase tabular-nums"
                            >
                                {{ formatDate(item.created_at) }}
                            </p>
                            <div class="flex items-center gap-2">
                                <Link
                                    :href="`/peminjaman/${item.id}`"
                                    class="text-slate-400 hover:text-primary"
                                    ><Eye class="size-4"
                                /></Link>
                                <a
                                    :href="
                                        item.completed_pdf_url ||
                                        `/peminjaman/${item.id}/print`
                                    "
                                    target="_blank"
                                    class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-400"
                                    title="Cetak"
                                    ><Printer class="size-4"
                                /></a>
                                <button
                                    v-if="item.share_url"
                                    type="button"
                                    class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-400"
                                    :title="
                                        shareFeedbackId === item.id
                                            ? 'Link berhasil disalin'
                                            : 'Salin link public'
                                    "
                                    @click="copyShareLink(item.share_url)"
                                >
                                    <CheckCircle2
                                        v-if="shareFeedbackId === item.id"
                                        class="size-4 text-emerald-600"
                                    />
                                    <Share2 v-else class="size-4" />
                                </button>
                                <button
                                    v-if="canComplete(item)"
                                    class="flex h-9 w-9 items-center justify-center rounded-xl border border-amber-200 bg-amber-50 text-amber-500"
                                    title="Selesaikan"
                                    @click="completeConfirmId = item.id"
                                >
                                    <CheckCircle2 class="size-4" />
                                </button>
                                <button
                                    v-else-if="canReturn(item)"
                                    class="flex h-9 items-center justify-center rounded-xl border border-emerald-100 bg-emerald-50 px-3 text-[9px] font-black tracking-widest text-emerald-600 uppercase"
                                    title="Return"
                                    @click="quickReturnId = item.id"
                                >
                                    Return
                                </button>
                                <template
                                    v-if="
                                        !isCancelled(item) && !isCompleted(item)
                                    "
                                >
                                    <Link
                                        v-if="
                                            resolveSignatureStats(item) ===
                                            '0/2'
                                        "
                                        :href="`/peminjaman/${item.id}/edit`"
                                        class="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-50 text-amber-500"
                                        ><Edit2 class="size-4"
                                    /></Link>
                                    <button
                                        v-if="
                                            resolveSignatureStats(item) ===
                                            '0/2'
                                        "
                                        class="flex h-9 w-9 items-center justify-center rounded-xl bg-rose-50 text-rose-500"
                                        title="Batalkan"
                                        @click="deleteConfirmId = item.id"
                                    >
                                        <Trash2 class="size-4" />
                                    </button>
                                </template>
                            </div>
                        </div>
                    </article>
                </div>

                <!-- Empty State -->
                <div
                    v-if="!paginatedRows.length"
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
                                Dokumen tidak ditemukan
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
                    v-if="filteredRows.length"
                    class="mt-8 flex flex-col items-center justify-between gap-6 border-t border-slate-100 pt-8 md:flex-row"
                >
                    <div class="flex items-center gap-4">
                        <div
                            class="flex items-center gap-2 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                        >
                            <span>Tampilkan</span>
                            <select
                                :value="pageSize"
                                class="rounded-lg border border-slate-100 bg-white px-2 py-1 text-slate-900 outline-none focus:border-primary/50"
                                @change="
                                    pageSize = Number(
                                        ($event.target as HTMLSelectElement)
                                            .value,
                                    );
                                    currentPage = 1;
                                "
                            >
                                <option :value="10">10</option>
                                <option :value="25">25</option>
                                <option :value="50">50</option>
                            </select>
                        </div>
                        <p
                            class="text-[10px] font-black tracking-[0.15em] text-slate-400 uppercase"
                        >
                            <span class="text-slate-900"
                                >{{ pageStart }}?{{ pageEnd }}</span
                            >
                            DARI
                            <span class="text-slate-900">{{
                                filteredRows.length
                            }}</span>
                            REKAMAN
                        </p>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <button
                            type="button"
                            class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-100 bg-white transition-all"
                            :class="
                                currentPage === 1
                                    ? 'cursor-not-allowed text-slate-300 opacity-20'
                                    : 'text-slate-600 hover:border-[#003628]/30 hover:text-[#003628]'
                            "
                            @click="currentPage = Math.max(1, currentPage - 1)"
                        >
                            <span class="text-lg leading-none">&#8249;</span>
                        </button>
                        <button
                            v-for="page in pageNumbers"
                            :key="page"
                            type="button"
                            class="flex h-9 min-w-[36px] items-center justify-center rounded-xl border px-2 text-[10px] font-black tracking-widest uppercase transition-all"
                            :class="
                                page === currentPage
                                    ? 'border-[#003628] bg-[#003628] text-white shadow-lg shadow-emerald-900/20'
                                    : 'border-slate-100 bg-white text-slate-400 hover:border-[#003628]/30 hover:text-[#003628]'
                            "
                            @click="currentPage = page"
                        >
                            {{ page }}
                        </button>
                        <button
                            type="button"
                            class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-100 bg-white transition-all"
                            :class="
                                currentPage >= totalPages
                                    ? 'cursor-not-allowed text-slate-300 opacity-20'
                                    : 'text-slate-600 hover:border-[#003628]/30 hover:text-[#003628]'
                            "
                            @click="
                                currentPage = Math.min(
                                    totalPages,
                                    currentPage + 1,
                                )
                            "
                        >
                            <span class="text-lg leading-none">&#8250;</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modals -->
        <AppConfirmDialog
            :open="completeConfirmId !== null"
            kicker="Finalisasi Dokumen"
            title="Kunci dokumen ke PDF final?"
            description="Setelah difinalisasi, dokumen masuk tabel Selesai dan tidak bisa diedit lagi."
            confirm-label="Ya, Finalisasi"
            cancel-label="Kembali"
            confirm-variant="warning"
            :subject="
                activeCompleteItem ? resolveDocId(activeCompleteItem) : null
            "
            @close="completeConfirmId = null"
            @confirm="completeItem"
        />
        <AppConfirmDialog
            :open="deleteConfirmId !== null"
            kicker="Batalkan Dokumen"
            title="Batalkan dokumen ini?"
            description="Dokumen akan dipindahkan ke daftar Dibatalkan dan tidak bisa diedit lagi."
            confirm-label="Ya, Batalkan"
            cancel-label="Kembali"
            confirm-variant="danger"
            :subject="activeDeleteItem ? resolveDocId(activeDeleteItem) : null"
            @close="deleteConfirmId = null"
            @confirm="deleteItem"
        />
        <QuickReturnModal
            :show="!!quickReturnId"
            :peminjaman-id="quickReturnId"
            @close="quickReturnId = null"
        />
    </AppLayout>
</template>
