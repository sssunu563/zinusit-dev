<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
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
import StbItemsModal from '@/pages/Stb/Partials/StbItemsModal.vue';
import StbSignaturesModal from '@/pages/Stb/Partials/StbSignaturesModal.vue';

interface STB {
    id: number;
    user_id: number | null;
    group_id: number | null;
    deliver_date: string | null;
    building: string | null;
    use_date: string | null;
    batch_no: string | null;
    it_drafter_id: number | null;
    it_checker_id: number | null;
    it_approved_id: number | null;
    status: number | null;
    document_type?: string | null;
    movement_type?: string | null;
    returned_at?: string | null;
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
    items?: any[];
    it_drafter_signature_path?: string | null;
    it_checker_signature_path?: string | null;
    it_approved_signature_path?: string | null;
    requester_received_signature_path?: string | null;
    requester_dept_head_signature_path?: string | null;
}

type SortKey =
    | 'docId'
    | 'location'
    | 'userName'
    | 'company'
    | 'department'
    | 'status'
    | 'batchNo'
    | 'deliverDate'
    | 'updatedAt';

type TableColumn = {
    key: SortKey;
    label: string;
    value: (stb: STB) => string;
    cellClass?: string;
    headerClass?: string;
};

const props = defineProps<{
    searchQuery: string;
    downloadCsv: () => void;
    downloadPdf: () => void;
    serverLinks?: Array<{
        url: string | null;
        label: string;
        active?: boolean;
    }>;
    serverTotal?: number;
    tableColumns: TableColumn[];
    paginatedStbs: STB[];
    sortKey: SortKey;
    sortDirection: 'asc' | 'desc';
    statusOptions: string[];
    companyOptions: string[];
    locationOptions: string[];
    selectedStatus: string;
    selectedCompany: string;
    selectedLocation: string;
    pageStart: number;
    pageEnd: number;
    totalRows: number;
    pageSize: number;
    currentPage: number;
    totalPages: number;
    pageNumbers: number[];
    resolveDocId: (stb: STB) => string;
    resolveCompany: (stb: STB) => string;
    resolveLocation: (stb: STB) => string;
    resolveDepartment: (stb: STB) => string;
    resolveStatus: (stb: STB) => string;
    resolveStatusBadgeClass: (stb: STB) => string;
    formatDate: (date: string) => string;
    isCompleted: (stb: STB) => boolean;
    isCancelled: (stb: STB) => boolean;
    isReadyToComplete: (stb: STB) => boolean;
    getLoanReturnHref: (stb: STB) => string | null;
    hasLockedActions: (stb: STB) => boolean;
    copyShareLink: (shareUrl?: string | null) => void;
    openCompleteConfirm: (id: number) => void;
    openDeleteConfirm: (id: number) => void;
    toggleColumnSort: (nextKey: SortKey) => void;
    handleStatusChange: (value: string) => void;
    handleCompanyChange: (value: string) => void;
    handleLocationChange: (value: string) => void;
    resetFilters: () => void;
    goToPreviousPage: () => void;
    goToNextPage: () => void;
    setPage: (page: number) => void;
    activeTab: 'pending' | 'completed' | 'cancelled';
    pendingCount: number;
    completedCount: number;
    cancelledCount: number;
}>();

defineEmits<{
    (e: 'update:searchQuery', value: string): void;
    (e: 'update:page-size', value: number): void;
}>();

const showFilters = ref(false);
const filterPanelRef = ref<HTMLElement | null>(null);

onClickOutside(filterPanelRef, () => {
    showFilters.value = false;
});

const showCreateFlyout = ref(false);
const createFlyoutRef = ref<HTMLElement | null>(null);

onClickOutside(createFlyoutRef, () => {
    showCreateFlyout.value = false;
});

const activeFilterCount = computed(
    () =>
        [
            props.selectedStatus,
            props.selectedCompany,
            props.selectedLocation,
        ].filter((val) => Boolean(val)).length,
);

const itemsModalOpen = ref(false);
const selectedStbForItems = ref<STB | null>(null);

const signaturesModalOpen = ref(false);
const selectedStbForSignatures = ref<STB | null>(null);

const openItemsModal = (stb: STB) => {
    selectedStbForItems.value = stb;
    itemsModalOpen.value = true;
};

const openSignaturesModal = (stb: STB) => {
    selectedStbForSignatures.value = stb;
    signaturesModalOpen.value = true;
};

const localFilters = ref({
    status: props.selectedStatus,
    company: props.selectedCompany,
    location: props.selectedLocation,
});

const commitFilters = () => {
    props.handleStatusChange(localFilters.value.status);
    props.handleCompanyChange(localFilters.value.company);
    props.handleLocationChange(localFilters.value.location);
    showFilters.value = false;
};

const resetFilters = () => {
    localFilters.value = {
        status: '',
        company: '',
        location: '',
    };
    props.resetFilters();
};
</script>

<template>
    <div class="bg-white rounded-[32px] border border-slate-200/60 shadow-xl shadow-slate-200/50 p-6 lg:p-8">
    <section>
        <!-- Toolbar -->
        <div class="mb-6 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div class="relative w-full lg:max-w-md">
                <Search class="absolute left-4 top-1/2 -translate-y-1/2 size-4 text-slate-400" />
                <input
                    :value="searchQuery"
                    type="text"
                    placeholder="Search document, ID, or recipient..."
                    class="w-full h-12 pl-12 pr-4 rounded-2xl border border-slate-100 bg-white text-sm text-slate-900 placeholder:text-slate-400 focus:border-primary/50 focus:ring-4 focus:ring-primary/10 transition-all outline-none"
                    @input="
                        $emit(
                            'update:searchQuery',
                            ($event.target as HTMLInputElement).value,
                        )
                    "
                />
            </div>

            <div class="flex items-center gap-2">
                <button
                    type="button"
                    class="h-11 px-4 rounded-xl border border-slate-200 bg-white flex items-center gap-2 text-slate-600 hover:text-[#003628] hover:bg-[#003628]/5 transition-all text-sm font-bold active:scale-95 shadow-sm"
                    title="Export CSV"
                    @click="downloadCsv"
                >
                    <Download class="size-4" />
                </button>

                <!-- Filter flyout trigger -->
                <div ref="filterPanelRef" class="relative">
                    <button
                        type="button"
                        class="size-11 rounded-xl border border-slate-200 bg-white flex items-center justify-center text-slate-500 hover:text-[#003628] hover:bg-[#003628]/5 transition-all shadow-sm relative"
                        @click="showFilters = !showFilters"
                    >
                        <SlidersHorizontal class="size-5" />
                        <span
                            v-if="activeFilterCount"
                            class="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-[#003628] text-[10px] font-black text-white ring-4 ring-white"
                        >
                            {{ activeFilterCount }}
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
                            class="absolute top-full right-0 z-50 mt-4 w-80 rounded-[32px] border border-slate-200 bg-white p-8 shadow-2xl backdrop-blur-xl overflow-hidden"
                        >
                            <div class="flex items-center justify-between mb-8">
                                <h3 class="text-[10px] font-black uppercase tracking-widest text-slate-400">Search Filters</h3>
                                <button @click="resetFilters" class="text-[10px] font-black uppercase tracking-widest text-primary hover:opacity-70 transition-colors flex items-center gap-1.5">
                                    <RefreshCw class="size-3" /> Reset
                                </button>
                            </div>

                            <div class="space-y-6">
                                <div class="space-y-2">
                                    <label class="text-[10px] font-black uppercase tracking-widest text-slate-400">Views</label>
                                    <div class="grid gap-2">
                                        <Link
                                            href="/stb?tab=pending"
                                            class="flex items-center justify-between p-3.5 rounded-2xl border transition-all"
                                            :class="activeTab === 'pending' || !activeTab ? 'bg-[#003628]/5 border-[#003628]/20 text-[#003628]' : 'border-slate-100 hover:bg-slate-50 text-slate-500'"
                                            @click="showFilters = false"
                                        >
                                            <span class="text-xs font-bold">Draft</span>
                                            <span class="text-[10px] font-black px-2.5 py-0.5 rounded-full bg-slate-100">{{ pendingCount }}</span>
                                        </Link>
                                        <Link
                                            href="/stb?tab=completed"
                                            class="flex items-center justify-between p-3.5 rounded-2xl border transition-all"
                                            :class="activeTab === 'completed' ? 'bg-[#003628]/5 border-[#003628]/20 text-[#003628]' : 'border-slate-100 hover:bg-slate-50 text-slate-500'"
                                            @click="showFilters = false"
                                        >
                                            <span class="text-xs font-bold">Completed</span>
                                            <span class="text-[10px] font-black px-2.5 py-0.5 rounded-full bg-slate-100">{{ completedCount }}</span>
                                        </Link>
                                        <Link
                                            href="/stb?tab=cancelled"
                                            class="flex items-center justify-between p-3.5 rounded-2xl border transition-all"
                                            :class="activeTab === 'cancelled' ? 'bg-[#003628]/5 border-[#003628]/20 text-[#003628]' : 'border-slate-100 hover:bg-slate-50 text-slate-500'"
                                            @click="showFilters = false"
                                        >
                                            <span class="text-xs font-bold">Cancelled</span>
                                            <span class="text-[10px] font-black px-2.5 py-0.5 rounded-full bg-slate-100">{{ cancelledCount }}</span>
                                        </Link>
                                    </div>
                                </div>

                                <div class="space-y-4 pt-6 border-t border-slate-100">
                                    <div class="space-y-1.5">
                                        <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Status</label>
                                        <div class="relative">
                                            <select
                                                v-model="localFilters.status"
                                                class="w-full h-11 px-4 pr-10 rounded-2xl border border-slate-100 bg-slate-50 text-[13px] font-bold text-slate-900 outline-none focus:border-primary/50 appearance-none"
                                            >
                                                <option value="">Semua Status</option>
                                                <option v-for="opt in statusOptions" :key="opt" :value="opt">{{ opt }}</option>
                                            </select>
                                            <svg class="size-4 text-slate-400 absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                                        </div>
                                    </div>

                                    <div class="space-y-1.5">
                                        <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Perusahaan</label>
                                        <div class="relative">
                                            <select
                                                v-model="localFilters.company"
                                                class="w-full h-11 px-4 pr-10 rounded-2xl border border-slate-100 bg-slate-50 text-[13px] font-bold text-slate-900 outline-none focus:border-primary/50 appearance-none"
                                            >
                                                <option value="">Semua Perusahaan</option>
                                                <option v-for="opt in companyOptions" :key="opt" :value="opt">{{ opt }}</option>
                                            </select>
                                            <svg class="size-4 text-slate-400 absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                                        </div>
                                    </div>

                                    <div class="space-y-1.5">
                                        <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Lokasi</label>
                                        <div class="relative">
                                            <select
                                                v-model="localFilters.location"
                                                class="w-full h-11 px-4 pr-10 rounded-2xl border border-slate-100 bg-slate-50 text-[13px] font-bold text-slate-900 outline-none focus:border-primary/50 appearance-none"
                                            >
                                                <option value="">Semua Lokasi</option>
                                                <option v-for="opt in locationOptions" :key="opt" :value="opt">{{ opt }}</option>
                                            </select>
                                            <svg class="size-4 text-slate-400 absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                                        </div>
                                    </div>

                                    <button
                                        class="w-full h-12 mt-4 rounded-2xl bg-[#003628] text-white text-sm font-black uppercase tracking-widest hover:opacity-90 transition-all active:scale-95 shadow-lg shadow-primary/10"
                                        @click="commitFilters"
                                    >
                                        Terapkan Filter
                                    </button>
                                </div>
                            </div>
                        </div>
                    </Transition>
                </div>

                <!-- Create STB Flyout -->
                <div ref="createFlyoutRef" class="relative">
                    <button
                        type="button"
                        class="h-11 px-6 rounded-xl bg-[#003628] flex items-center justify-center text-white text-[13px] font-bold hover:bg-[#003628]/90 transition-all active:scale-95 shadow-lg shadow-emerald-900/20"
                        @click="showCreateFlyout = !showCreateFlyout"
                    >
                        <span>Buat STB</span>
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
                            v-if="showCreateFlyout"
                            class="absolute top-full right-0 z-50 mt-2 w-52 rounded-2xl border border-slate-200 bg-white p-2 shadow-2xl overflow-hidden"
                        >
                            <Link
                                href="/stb/create?documentType=handover&movementType=out"
                                class="flex items-center w-full p-4 rounded-xl hover:bg-slate-50 transition-colors text-left"
                                @click="showCreateFlyout = false"
                            >
                                <span class="text-xs font-black text-slate-900 uppercase tracking-widest">Serah Terima</span>
                            </Link>
                            <Link
                                href="/stb/create?documentType=handover&movementType=return"
                                class="flex items-center w-full p-4 rounded-xl hover:bg-slate-50 transition-colors text-left"
                                @click="showCreateFlyout = false"
                            >
                                <span class="text-xs font-black text-slate-900 uppercase tracking-widest">Pengembalian</span>
                            </Link>
                        </div>
                    </Transition>
                </div>
            </div>
        </div>

        <!-- Desktop Table -->
        <div v-if="paginatedStbs.length" class="hidden md:block overflow-hidden rounded-xl border border-slate-200/50">
            <table class="w-full border-collapse">
                <thead>
                    <tr class="border-b border-slate-50 bg-slate-50/50">
                        <th class="px-6 py-5 text-left text-[10px] font-black uppercase tracking-[0.15em] text-slate-400 w-12">#</th>
                        <th class="px-6 py-5 text-left text-[10px] font-black uppercase tracking-[0.15em] text-slate-400">DOC ID</th>
                        <th class="px-6 py-5 text-left text-[10px] font-black uppercase tracking-[0.15em] text-slate-400">LOCATION</th>
                        <th class="px-6 py-5 text-left text-[10px] font-black uppercase tracking-[0.15em] text-slate-400">DELIVER DATE</th>
                        <th class="px-6 py-5 text-left text-[10px] font-black uppercase tracking-[0.15em] text-slate-400">NAME</th>
                        <th class="px-6 py-5 text-left text-[10px] font-black uppercase tracking-[0.15em] text-slate-400">COMPANY</th>
                        <th class="px-6 py-5 text-left text-[10px] font-black uppercase tracking-[0.15em] text-slate-400">DEPARTMENT</th>
                        <th class="px-6 py-5 text-left text-[10px] font-black uppercase tracking-[0.15em] text-slate-400">STATUS</th>
                        <th class="px-6 py-5 text-center text-[10px] font-black uppercase tracking-[0.15em] text-slate-400">SIGNATURE</th>
                        <th class="px-6 py-5 text-center text-[10px] font-black uppercase tracking-[0.15em] text-slate-400">ITEMS</th>
                        <th class="px-6 py-5 text-center text-[10px] font-black uppercase tracking-[0.15em] text-slate-400 w-48">ACTION</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr
                        v-for="(stb, index) in paginatedStbs"
                        :key="stb.id"
                        class="group hover:bg-slate-50/50 transition-colors"
                    >
                        <td class="px-6 py-4 text-xs font-bold text-slate-300 tabular-nums">
                            {{ pageStart + index }}
                        </td>
                        <td class="px-6 py-4">
                            <Link
                                :href="`/stb/${stb.id}`"
                                class="text-[13px] font-black text-slate-900 hover:text-primary transition-colors uppercase tracking-tight"
                            >
                                {{ resolveDocId(stb) }}
                            </Link>
                        </td>
                        <td class="px-6 py-4">
                            <p class="text-[13px] font-black text-slate-700 truncate max-w-[120px]">{{ resolveLocation(stb) }}</p>
                        </td>
                        <td class="px-6 py-4">
                            <p class="text-[12px] font-black text-slate-700">{{ stb.deliver_date ? formatDate(stb.deliver_date) : formatDate(stb.created_at) }}</p>
                        </td>
                        <td class="px-6 py-4">
                            <p class="text-[13px] font-black text-slate-700 truncate max-w-[120px]">{{ tableColumns[4].value(stb) }}</p>
                        </td>
                        <td class="px-6 py-4">
                            <p class="text-[10px] text-slate-400 uppercase font-black tracking-tight truncate max-w-[120px]">
                                {{ resolveCompany(stb) }}
                            </p>
                        </td>
                        <td class="px-6 py-4">
                            <p class="text-[10px] text-slate-400 uppercase font-black tracking-tight truncate max-w-[120px]">
                                {{ resolveDepartment(stb) }}
                            </p>
                        </td>
                        <td class="px-6 py-4 text-xs font-bold text-slate-600">
                            <span
                                class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-widest"
                                :class="resolveStatusBadgeClass(stb).includes('danger') ? 'bg-rose-50 text-rose-600' : resolveStatusBadgeClass(stb).includes('positive') ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-600'"
                            >
                                {{ resolveStatus(stb) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <button
                                @click="openSignaturesModal(stb)"
                                class="text-[9px] font-black text-slate-400 hover:text-primary transition-colors uppercase tracking-[0.1em] border border-slate-200 bg-white py-1 px-3 rounded-full shadow-sm whitespace-nowrap"
                            >
                                {{ tableColumns[8].value(stb) }} TTD
                            </button>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <button
                                @click="openItemsModal(stb)"
                                class="text-[9px] font-black text-emerald-600 hover:text-emerald-700 transition-colors uppercase tracking-[0.1em] border border-emerald-100 bg-emerald-50 py-1 px-3 rounded-full whitespace-nowrap"
                            >
                                {{ stb.items?.length || 0 }} ITEMS
                            </button>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <div class="flex items-center justify-center gap-1.5">
                                <Link
                                    :href="`/stb/${stb.id}`"
                                    class="h-8 w-8 rounded-lg bg-white border border-slate-200 flex items-center justify-center text-slate-400 hover:text-primary transition-colors shadow-sm"
                                    title="Preview"
                                >
                                    <Eye class="size-4" />
                                </Link>
                                <a
                                    :href="stb.completed_pdf_url || `/stb/${stb.id}/print`"
                                    target="_blank"
                                    class="h-8 w-8 rounded-lg bg-white border border-slate-200 flex items-center justify-center text-slate-400 hover:text-primary transition-colors shadow-sm"
                                    title="Cetak"
                                >
                                    <Printer class="size-4" />
                                </a>
                                
                                <template v-if="!isCompleted(stb) && !isCancelled(stb)">
                                    <button
                                        class="h-8 w-8 rounded-lg bg-primary/5 border border-primary/10 flex items-center justify-center text-primary hover:bg-primary hover:text-white transition-all shadow-sm"
                                        title="Salin link"
                                        @click="copyShareLink(stb.share_url)"
                                    >
                                        <Share2 class="size-4" />
                                    </button>
                                    <button
                                        v-if="isReadyToComplete(stb)"
                                        class="h-8 w-8 rounded-lg bg-amber-50 border border-amber-200 flex items-center justify-center text-amber-500 hover:bg-amber-500 hover:text-white transition-all shadow-sm"
                                        title="Selesaikan"
                                        @click="openCompleteConfirm(stb.id)"
                                    >
                                        <CheckCircle2 class="size-4" />
                                    </button>
                                    <Link
                                        v-if="!hasLockedActions(stb)"
                                        :href="`/stb/${stb.id}/edit`"
                                        class="h-8 w-8 rounded-lg bg-white border border-slate-200 flex items-center justify-center text-slate-400 hover:text-amber-500 transition-colors shadow-sm"
                                        title="Edit"
                                    >
                                        <Edit2 class="size-4" />
                                    </Link>
                                    <button
                                        v-if="!hasLockedActions(stb)"
                                        class="h-8 w-8 rounded-lg bg-rose-50 border border-rose-100 flex items-center justify-center text-rose-400 hover:bg-rose-500 hover:text-white transition-all shadow-sm"
                                        @click="openDeleteConfirm(stb.id)"
                                    >
                                        <Trash2 class="size-4" />
                                    </button>
                                </template>

                                <Link
                                    v-else-if="getLoanReturnHref(stb)"
                                    :href="getLoanReturnHref(stb) || '#'"
                                    class="h-8 px-3 rounded-lg bg-emerald-50 border border-emerald-100 flex items-center justify-center text-emerald-600 text-[9px] font-black uppercase tracking-widest shadow-sm"
                                    title="Pengembalian"
                                >
                                    RETURN
                                </Link>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Mobile cards -->
        <div v-if="paginatedStbs.length" class="space-y-4 md:hidden">
            <article
                v-for="stb in paginatedStbs"
                :key="stb.id"
                class="overflow-hidden rounded-3xl border border-slate-100 bg-white p-6 shadow-xl shadow-slate-200/30 active:scale-[0.98] transition-all"
            >
                <div class="flex items-start justify-between mb-4">
                    <div class="space-y-1">
                        <h3 class="text-[11px] font-black uppercase tracking-widest text-primary">
                            {{ resolveDocId(stb) }}
                        </h3>
                        <p class="text-[13px] font-black text-slate-900">{{ tableColumns[3].value(stb) }}</p>
                    </div>
                    <span
                        class="px-2.5 py-1 rounded-full text-[9px] font-black uppercase tracking-widest"
                        :class="resolveStatusBadgeClass(stb).includes('danger') ? 'bg-rose-50 text-rose-600' : resolveStatusBadgeClass(stb).includes('positive') ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-600'"
                    >
                        {{ resolveStatus(stb) }}
                    </span>
                </div>

                <div class="grid grid-cols-2 gap-y-4 gap-x-2 pb-6 border-b border-slate-50">
                    <div class="space-y-1">
                        <p class="text-[9px] font-black uppercase tracking-[0.15em] text-slate-400">Site</p>
                        <p class="text-[11px] font-black text-slate-600 truncate">{{ resolveLocation(stb) }}</p>
                    </div>
                    <div class="space-y-1">
                        <p class="text-[9px] font-black uppercase tracking-[0.15em] text-slate-400">Actions</p>
                        <div class="flex items-center gap-3">
                             <a :href="stb.completed_pdf_url || `/stb/${stb.id}/print`" target="_blank" class="text-slate-400 hover:text-primary"><Printer class="size-4" /></a>
                             <Link :href="`/stb/${stb.id}`" class="text-slate-400 hover:text-primary"><Eye class="size-4" /></Link>
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex items-center justify-between">
                    <p class="text-[10px] font-black text-slate-300 tabular-nums uppercase">{{ formatDate(stb.created_at) }}</p>
                    <div class="flex items-center gap-2">
                        <template v-if="!isCompleted(stb) && !isCancelled(stb)">
                            <button @click="openSignaturesModal(stb)" class="text-[9px] font-black text-emerald-600 border border-emerald-100 bg-emerald-50 px-3 py-1.5 rounded-xl uppercase tracking-widest shadow-sm">TTD</button>
                            <Link v-if="!hasLockedActions(stb)" :href="`/stb/${stb.id}/edit`" class="h-9 w-9 rounded-xl flex items-center justify-center bg-amber-50 text-amber-500"><Edit2 class="size-4" /></Link>
                        </template>
                        <Link v-else-if="getLoanReturnHref(stb)" :href="getLoanReturnHref(stb) || '#'" class="text-[9px] font-black text-emerald-600 border border-emerald-100 bg-emerald-50 px-3 py-1.5 rounded-xl uppercase tracking-widest">PENGEMBALIAN</Link>
                    </div>
                </div>
            </article>
        </div>

        <!-- Empty State -->
        <div v-if="!paginatedStbs.length" class="py-20 text-center border-2 border-dashed border-slate-100 rounded-[40px] bg-white">
            <div class="flex flex-col items-center gap-6">
                <div class="h-20 w-20 rounded-full bg-slate-50 flex items-center justify-center text-slate-200">
                    <Search class="size-8" />
                </div>
                <div class="space-y-1">
                    <p class="text-sm font-black text-slate-900 uppercase tracking-widest">Dokumen tidak ditemukan</p>
                    <p class="text-xs text-slate-400">Coba sesuaikan filter atau kata kunci pencarian Anda.</p>
                </div>
                <button @click="resetFilters" class="text-[10px] font-black uppercase tracking-[0.2em] text-primary hover:text-primary/70 transition-colors">Hapus semua filter</button>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="totalRows" class="mt-8 flex flex-col md:flex-row items-center justify-between gap-6 border-t border-slate-100 pt-8">
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-2 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                    <span>Tampilkan</span>
                    <select
                        :value="pageSize"
                        class="bg-white border border-slate-100 rounded-lg px-2 py-1 text-slate-900 outline-none focus:border-primary/50"
                        @change="$emit('update:page-size', Number(($event.target as HTMLSelectElement).value))"
                    >
                        <option :value="10">10</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                    </select>
                </div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.15em]">
                    <span class="text-slate-900">{{ pageStart }}–{{ pageEnd }}</span> DARI <span class="text-slate-900">{{ totalRows }}</span> REKAMAN
                </p>
            </div>

            <div class="flex items-center gap-1.5">
                <button
                    type="button"
                    class="h-9 w-9 flex items-center justify-center rounded-xl transition-all border border-slate-100 bg-white"
                    :class="currentPage === 1 ? 'opacity-20 cursor-not-allowed text-slate-300' : 'text-slate-600 hover:border-[#003628]/30 hover:text-[#003628]'"
                    @click="goToPreviousPage"
                >
                    <span class="text-lg leading-none">‹</span>
                </button>

                <button
                    v-for="page in pageNumbers"
                    :key="page"
                    type="button"
                    class="h-9 min-w-[36px] px-2 flex items-center justify-center rounded-xl text-[10px] font-black transition-all border uppercase tracking-widest"
                    :class="page === currentPage 
                        ? 'border-[#003628] bg-[#003628] text-white shadow-lg shadow-emerald-900/20' 
                        : 'border-slate-100 bg-white text-slate-400 hover:border-[#003628]/30 hover:text-[#003628]'"
                    @click="setPage(page)"
                >
                    {{ page }}
                </button>

                <button
                    type="button"
                    class="h-9 w-9 flex items-center justify-center rounded-xl transition-all border border-slate-100 bg-white"
                    :class="currentPage >= totalPages ? 'opacity-20 cursor-not-allowed text-slate-300' : 'text-slate-600 hover:border-[#003628]/30 hover:text-[#003628]'"
                    @click="goToNextPage"
                >
                    <span class="text-lg leading-none">›</span>
                </button>
            </div>
        </div>

        <!-- Modals -->
        <StbItemsModal
            v-if="selectedStbForItems"
            :open="itemsModalOpen"
            :doc-id="resolveDocId(selectedStbForItems)"
            :items="selectedStbForItems.items || []"
            @close="itemsModalOpen = false"
        />
        <StbSignaturesModal
            v-if="selectedStbForSignatures"
            :open="signaturesModalOpen"
            :doc-id="resolveDocId(selectedStbForSignatures)"
            :stb="selectedStbForSignatures"
            @close="signaturesModalOpen = false"
        />
    </section>
    </div>
</template>
