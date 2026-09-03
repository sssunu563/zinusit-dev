<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { onClickOutside } from '@vueuse/core';
import {
    Activity,
    ArrowUpRight,
    Download,
    Eye,
    Pencil,
    Plus,
    Printer,
    RefreshCw,
    Search,
    SlidersHorizontal,
    Trash2,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import AppConfirmDialog from '@/components/AppConfirmDialog.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import HelpdeskDetail from '@/pages/Helpdesk/Partials/HelpdeskDetail.vue';
import HelpdeskForm from '@/pages/Helpdesk/Partials/HelpdeskForm.vue';
import HelpdeskTable from '@/pages/Helpdesk/Partials/HelpdeskTable.vue';
import type { BreadcrumbItem } from '@/types';

interface TicketRow {
    id: number;
    company: string;
    location: string;
    category: string;
    ticket_scope: string;
    priority: string;
    requester: string;
    department: string;
    snipeit_asset_id: number | null;
    asset_reference_snapshot: string | null;
    issue_description: string;
    action_taken: string;
    note: string | null;
    technician: string;
    status: string;
    date_closed: string | null;
    snipeit_maintenance_id: number | null;
    snipeit_sync_status: string | null;
    snipeit_sync_message: string | null;
    created_at: string | null;
    creator: {
        id: number;
        name: string;
    } | null;
}

interface Props {
    tickets: {
        data: TicketRow[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        meta: { total?: number };
    };
    filters: {
        search: string;
        status: string;
        priority: string;
        category: string;
        from_date: string | null;
        to_date: string | null;
        technician: string;
    };
    technicianOptions: string[];
    priorityOptions: string[];
    statusOptions: string[];
    ticketScopeOptions: Array<{ value: string; label: string }>;
    maintenanceTypeOptions: string[];
    categoryOptions: string[];
    initialValues: any;
    canViewAll: boolean;
    techCompany: string;
    techLocation: string;
    vendorOptions: Array<{ id: number; name: string }>;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Workspace', href: '/helpdesk' },
];

const filterForm = useForm({
    search: props.filters.search || '',
    status: props.filters.status || '',
    priority: props.filters.priority || '',
    category: props.filters.category || '',
    from_date: props.filters.from_date || '',
    to_date: props.filters.to_date || '',
    technician: props.filters.technician || '',
});

const deleteConfirmId = ref<number | null>(null);
const showFilters = ref(false);
const filterPanelRef = ref<HTMLElement | null>(null);
const showExport = ref(false);
const exportPanelRef = ref<HTMLElement | null>(null);
const exportFromDate = ref('');
const exportToDate = ref('');
const showPrint = ref(false);
const printPanelRef = ref<HTMLElement | null>(null);
const printFromDate = ref('');
const printToDate = ref('');
const printApprovedBy = ref('');

onClickOutside(filterPanelRef, () => {
    showFilters.value = false;
});

onClickOutside(exportPanelRef, () => {
    showExport.value = false;
});

onClickOutside(printPanelRef, () => {
    showPrint.value = false;
});

let searchDebounceTimer: ReturnType<typeof setTimeout> | null = null;
let skipNextSearchAutoApply = false;

const activeDeleteItem = computed(
    () =>
        props.tickets.data.find((item) => item.id === deleteConfirmId.value) ??
        null,
);

const buildExportUrl = () => {
    const params = new URLSearchParams();
    const technicianName = usePage().props.auth.user.name;

    if (exportFromDate.value) params.set('from_date', exportFromDate.value);
    if (exportToDate.value) params.set('to_date', exportToDate.value);
    if (filterForm.technician) params.set('technician', filterForm.technician);
    else if (technicianName) params.set('technician', technicianName);

    const query = params.toString();

    return query ? `/helpdesk/export?${query}` : '/helpdesk/export';
};

const printTechnician = ref('');

const showCreateModal = ref(false);
const createForm = useForm({ ...props.initialValues });

const submitCreate = () => {
    createForm.post('/helpdesk', {
        preserveScroll: true,
        onSuccess: () => {
            showCreateModal.value = false;
            createForm.reset();
        },
    });
};

const closeCreateModal = () => {
    showCreateModal.value = false;
    createForm.reset();
};

const showViewModal = ref(false);
const viewingTicket = ref<TicketRow | null>(null);

const openViewModal = (ticket: TicketRow) => {
    viewingTicket.value = ticket;
    showViewModal.value = true;
};

const closeViewModal = () => {
    showViewModal.value = false;
    viewingTicket.value = null;
};

const showEditModal = ref(false);
const editForm = useForm({
    company: '',
    location: '',
    category: '',
    ticket_scope: '',
    priority: '',
    requester: '',
    department: '',
    snipeit_asset_id: null as number | null,
    asset_reference_snapshot: '',
    maintenance_type: '',
    issue_description: '',
    action_taken: '',
    note: '',
    technician: '',
    vendor_id: null as number | null,
    status: '',
    date_closed: '',
    snipeit_maintenance_id: null as number | null,
    snipeit_sync_status: null as string | null,
    snipeit_sync_message: null as string | null,
});

const openEditModal = (ticket: TicketRow) => {
    editForm.company = ticket.company || '';
    editForm.location = ticket.location || '';
    editForm.category = ticket.category || '';
    editForm.ticket_scope = ticket.ticket_scope || '';
    editForm.priority = ticket.priority || '';
    editForm.requester = ticket.requester || '';
    editForm.department = ticket.department || '';
    editForm.snipeit_asset_id = ticket.snipeit_asset_id;
    editForm.asset_reference_snapshot = ticket.asset_reference_snapshot || '';
    editForm.maintenance_type = ticket.maintenance_type || '';
    editForm.issue_description = ticket.issue_description || '';
    editForm.action_taken = ticket.action_taken || '';
    editForm.note = ticket.note || '';
    editForm.technician = ticket.technician || '';
    editForm.vendor_id = ticket.vendor_id;
    editForm.status = ticket.status || 'Open';
    editForm.date_closed = ticket.date_closed || '';
    editForm.snipeit_maintenance_id = ticket.snipeit_maintenance_id;
    editForm.snipeit_sync_status = ticket.snipeit_sync_status;
    editForm.snipeit_sync_message = ticket.snipeit_sync_message;

    editForm.clearErrors();
    showEditModal.value = true;
};

const submitEdit = () => {
    if (!viewingTicket.value && !showEditModal.value) return;
    
    // We use viewingTicket.id if editing from view modal, or we need another ref for target id
};

const editTargetId = ref<number | null>(null);

const handleOpenEdit = (ticket: TicketRow) => {
    editTargetId.value = ticket.id;
    openEditModal(ticket);
};

const submitUpdate = () => {
    if (!editTargetId.value) return;
    
    editForm.put(`/helpdesk/${editTargetId.value}`, {
        preserveScroll: true,
        onSuccess: () => {
            showEditModal.value = false;
            editTargetId.value = null;
            editForm.reset();
        },
    });
};

const closeEditModal = () => {
    showEditModal.value = false;
    editTargetId.value = null;
    editForm.reset();
};

const doExport = () => {
    window.location.href = buildExportUrl();
    showExport.value = false;
};

const doPrint = () => {
    const params = new URLSearchParams();

    if (printFromDate.value) params.set('from_date', printFromDate.value);
    if (printToDate.value) params.set('to_date', printToDate.value);
    if (printApprovedBy.value) params.set('approved_by', printApprovedBy.value);
    
    // Use selected print technician, or current filter technician, or empty
    const tech = printTechnician.value || filterForm.technician;
    if (tech) params.set('technician', tech);

    const query = params.toString();
    const url = query
        ? `/helpdesk/print-batch?${query}`
        : '/helpdesk/print-batch';

    window.open(url, '_blank', 'noopener');
    showPrint.value = false;
};

const activeFilterCount = computed(
    () =>
        [
            filterForm.status,
            filterForm.priority,
            filterForm.category,
            filterForm.from_date,
            filterForm.to_date,
            filterForm.technician,
        ].filter((value) => Boolean(value)).length,
);

const applyFilters = (closeFlyout = false) => {
    if (closeFlyout) {
        showFilters.value = false;
    }

    filterForm.get('/helpdesk', {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const resetFilters = () => {
    skipNextSearchAutoApply = true;
    filterForm.search = '';
    filterForm.status = '';
    filterForm.priority = '';
    filterForm.category = '';
    filterForm.from_date = '';
    filterForm.to_date = '';
    filterForm.technician = '';
    applyFilters(true);
};

watch(
    () => filterForm.search,
    () => {
        if (skipNextSearchAutoApply) {
            skipNextSearchAutoApply = false;
            return;
        }

        if (searchDebounceTimer) {
            clearTimeout(searchDebounceTimer);
        }

        searchDebounceTimer = setTimeout(() => {
            applyFilters();
        }, 300);
    },
);

const deleteItem = () => {
    if (deleteConfirmId.value === null) {
        return;
    }

    router.delete(`/helpdesk/${deleteConfirmId.value}`, {
        preserveScroll: true,
        onFinish: () => {
            deleteConfirmId.value = null;
        },
    });
};

const formatDate = (date?: string | null) => {
    if (!date) {
        return '-';
    }

    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="app-page-shell">
            <Head title="Workspace" />

            <div class="bg-white rounded-[32px] border border-slate-200/60 shadow-xl shadow-slate-200/50 p-6 lg:p-8">
                <div class="mb-8 flex items-center justify-between gap-4">
                    <!-- Left: Search -->
                    <div class="relative flex-1 max-w-xl">
                        <Search class="absolute left-4 top-1/2 -translate-y-1/2 size-4 text-slate-400" />
                        <input
                            v-model="filterForm.search"
                            type="text"
                            placeholder="Cari berdasarkan nama, peminta, atau masalah..."
                            class="w-full h-12 pl-12 pr-4 rounded-2xl border border-slate-200 bg-white text-sm text-slate-900 placeholder:text-slate-400 focus:border-[#003628]/50 focus:ring-4 focus:ring-[#003628]/10 transition-all outline-none shadow-sm"
                        />
                    </div>

                    <!-- Right: Action Buttons -->
                    <div class="flex items-center gap-2">
                        <!-- Print Panel -->
                        <div ref="printPanelRef" class="relative">
                            <button
                                type="button"
                                class="size-11 rounded-xl border border-slate-200 bg-white flex items-center justify-center text-slate-500 hover:text-[#003628] hover:bg-[#003628]/5 transition-all shadow-sm"
                                @click="showPrint = !showPrint; showExport = false; showFilters = false;"
                            >
                                <Printer class="size-5" />
                            </button>

                            <Transition
                                enter-active-class="transition duration-200 ease-out"
                                enter-from-class="opacity-0 translate-y-2 scale-95"
                                enter-to-class="opacity-100 translate-y-0 scale-100"
                                leave-active-class="transition duration-150 ease-in"
                                leave-from-class="opacity-100 translate-y-0 scale-100"
                                leave-to-class="opacity-0 translate-y-2 scale-95"
                            >
                                <div v-if="showPrint" class="absolute top-full right-0 z-50 mt-3 w-72 rounded-[32px] border border-slate-200 bg-white p-6 shadow-2xl backdrop-blur-xl">
                                    <h3 class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-6">Cetak Laporan</h3>
                                    <div class="space-y-4">
                                        <div class="space-y-1.5">
                                            <label class="text-[10px] font-black uppercase tracking-widest text-slate-500">Dari Tanggal</label>
                                            <input v-model="printFromDate" type="date" class="app-input-shell app-input-compact" />
                                        </div>
                                        <div class="space-y-1.5">
                                            <label class="text-[10px] font-black uppercase tracking-widest text-slate-500">Sampai Tanggal</label>
                                            <input v-model="printToDate" type="date" class="app-input-shell app-input-compact" />
                                        </div>
                                        <div class="space-y-1.5">
                                            <label class="text-[10px] font-black uppercase tracking-widest text-slate-500">Disetujui Oleh</label>
                                            <input v-model="printApprovedBy" type="text" placeholder="Nama lengkap..." class="app-input-shell app-input-compact placeholder:text-slate-300" />
                                        </div>
                                        <div class="space-y-1.5">
                                            <label class="text-[10px] font-black uppercase tracking-widest text-slate-500">Teknisi (Opsional)</label>
                                            <select v-model="printTechnician" class="app-select-shell app-select-compact w-full">
                                                <option value="">Filter Saat Ini ({{ filterForm.technician || 'Semua' }})</option>
                                                <option v-for="option in props.technicianOptions" :key="option" :value="option">{{ option }}</option>
                                            </select>
                                        </div>
                                        <button @click="doPrint" class="w-full h-11 rounded-xl bg-[#003628] text-white text-xs font-bold hover:bg-[#003628]/90 transition-colors mt-2">Buat PDF</button>
                                    </div>
                                </div>
                            </Transition>
                        </div>

                        <!-- Export Panel -->
                        <div ref="exportPanelRef" class="relative">
                            <button
                                type="button"
                                class="size-11 rounded-xl border border-slate-200 bg-white flex items-center justify-center text-slate-500 hover:text-[#003628] hover:bg-[#003628]/5 transition-all shadow-sm"
                                @click="showExport = !showExport; showFilters = false; showPrint = false;"
                            >
                                <Download class="size-5" />
                            </button>

                            <Transition
                                enter-active-class="transition duration-200 ease-out"
                                enter-from-class="opacity-0 translate-y-2 scale-95"
                                enter-to-class="opacity-100 translate-y-0 scale-100"
                                leave-active-class="transition duration-150 ease-in"
                                leave-from-class="opacity-100 translate-y-0 scale-100"
                                leave-to-class="opacity-0 translate-y-2 scale-95"
                            >
                                <div v-if="showExport" class="absolute top-full right-0 z-50 mt-3 w-72 rounded-[32px] border border-slate-200 bg-white p-6 shadow-2xl backdrop-blur-xl">
                                    <h3 class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-6">Ekspor Excel</h3>
                                    <div class="space-y-4">
                                        <div class="space-y-1.5">
                                            <label class="text-[10px] font-black uppercase tracking-widest text-slate-500">Dari Tanggal</label>
                                            <input v-model="exportFromDate" type="date" class="app-input-shell app-input-compact" />
                                        </div>
                                        <div class="space-y-1.5">
                                            <label class="text-[10px] font-black uppercase tracking-widest text-slate-500">Sampai Tanggal</label>
                                            <input v-model="exportToDate" type="date" class="app-input-shell app-input-compact" />
                                        </div>
                                        <button @click="doExport" class="w-full h-11 rounded-xl bg-[#003628] text-white text-xs font-bold hover:bg-[#003628]/90 transition-colors mt-2">Unduh .xlsx</button>
                                    </div>
                                </div>
                            </Transition>
                        </div>

                        <!-- Filter Panel -->
                        <div ref="filterPanelRef" class="relative">
                            <button
                                type="button"
                                class="size-11 rounded-xl border border-slate-200 bg-white flex items-center justify-center text-slate-500 hover:text-[#003628] hover:bg-[#003628]/5 transition-all relative shadow-sm"
                                @click="showFilters = !showFilters; showExport = false; showPrint = false;"
                            >
                                <SlidersHorizontal class="size-5" />
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
                                <div v-if="showFilters" class="absolute top-full right-0 z-50 mt-3 w-80 rounded-[32px] border border-slate-200 bg-white p-6 shadow-2xl backdrop-blur-xl overflow-hidden">
                                    <div class="flex items-center justify-between mb-8">
                                        <h3 class="text-[10px] font-black uppercase tracking-widest text-slate-400">Filter Pencarian</h3>
                                        <button @click="resetFilters" class="text-[10px] font-black uppercase tracking-widest text-[#003628] hover:opacity-70 transition-colors flex items-center gap-1.5">
                                            <RefreshCw class="size-3" /> Reset
                                        </button>
                                    </div>
                                    <div class="space-y-4">
                                        <div class="space-y-1.5">
                                            <label class="text-[10px] font-black uppercase tracking-widest text-slate-500">Status</label>
                                            <select v-model="filterForm.status" class="app-select-shell app-select-compact w-full">
                                                <option value="">Semua Status</option>
                                                <option v-for="option in props.statusOptions" :key="option" :value="option">{{ option }}</option>
                                            </select>
                                        </div>
                                        <div class="space-y-1.5">
                                            <label class="text-[10px] font-black uppercase tracking-widest text-slate-500">Prioritas</label>
                                            <select v-model="filterForm.priority" class="app-select-shell app-select-compact w-full">
                                                <option value="">Semua Prioritas</option>
                                                <option v-for="option in props.priorityOptions" :key="option" :value="option">{{ option }}</option>
                                            </select>
                                        </div>
                                        <div class="space-y-1.5">
                                            <label class="text-[10px] font-black uppercase tracking-widest text-slate-500">Teknisi</label>
                                            <select v-model="filterForm.technician" class="app-select-shell app-select-compact w-full">
                                                <option value="">Semua Teknisi</option>
                                                <option v-for="option in props.technicianOptions" :key="option" :value="option">{{ option }}</option>
                                            </select>
                                        </div>
                                        <div class="grid grid-cols-2 gap-3">
                                            <div class="space-y-1.5">
                                                <label class="text-[10px] font-black uppercase tracking-widest text-slate-500">Dari Tanggal</label>
                                                <input v-model="filterForm.from_date" type="date" class="app-input-shell app-input-compact" />
                                            </div>
                                            <div class="space-y-1.5">
                                                <label class="text-[10px] font-black uppercase tracking-widest text-slate-500">Sampai Tanggal</label>
                                                <input v-model="filterForm.to_date" type="date" class="app-input-shell app-input-compact" />
                                            </div>
                                        </div>
                                        <div class="grid grid-cols-2 gap-3 pt-4">
                                            <button @click="applyFilters(true)" class="col-span-2 h-11 rounded-xl bg-[#003628] text-white text-xs font-bold hover:bg-[#003628]/90 transition-colors">Terapkan Filter</button>
                                        </div>
                                    </div>
                                </div>
                            </Transition>
                        </div>

                        <!-- New Ticket Button -->
                        <button
                            type="button"
                            class="h-11 px-6 rounded-xl bg-[#003628] text-white flex items-center gap-2 transition-all hover:opacity-90 shadow-lg shadow-[#003628]/10 active:scale-95 ml-2"
                            @click="showCreateModal = true"
                        >
                            <Plus class="size-5" />
                            <span class="text-xs font-black uppercase tracking-widest">Tiket Baru</span>
                        </button>
                    </div>
                </div>

                <div v-if="props.tickets.data.length">
                    <HelpdeskTable
                        :tickets="props.tickets.data"
                        :format-date="formatDate"
                        @view="openViewModal"
                        @edit="handleOpenEdit"
                        @delete="(id) => deleteConfirmId = id"
                    />
                </div>

                <div v-if="props.tickets.data.length" class="space-y-4 md:hidden">
                    <div
                        v-for="ticket in props.tickets.data"
                        :key="ticket.id"
                        class="overflow-hidden rounded-[24px] border border-slate-100 bg-white p-6 shadow-sm active:scale-[0.98] transition-all"
                        @click="openViewModal(ticket)"
                    >
                        <div class="flex items-start justify-between mb-4">
                            <div class="space-y-1">
                                <span class="text-[10px] font-black uppercase tracking-widest text-[#003628]">#{{ ticket.id }}</span>
                                <h3 class="font-bold text-slate-900">{{ ticket.requester }}</h3>
                                <p class="text-[10px] text-slate-400 uppercase font-black tracking-tight">{{ ticket.category }} • {{ ticket.location }}</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div
                    v-if="props.tickets.data.length === 0"
                    class="py-24 flex flex-col items-center justify-center text-center space-y-4"
                >
                    <div class="h-20 w-20 rounded-full border border-slate-100 bg-slate-50 flex items-center justify-center text-slate-300 mb-4">
                        <Search class="size-8" />
                    </div>
                    <div class="space-y-1">
                        <h3 class="text-xl font-bold text-slate-900 tracking-tight">Tidak ada tiket ditemukan</h3>
                        <p class="text-sm text-slate-500 max-w-xs mx-auto">Tidak ada aktivitas yang sesuai dengan filter saat ini. Coba sesuaikan pencarian atau filter Anda.</p>
                    </div>
                    <button
                        type="button"
                        @click="showCreateModal = true"
                        class="text-[#003628] font-black uppercase tracking-widest text-[11px] hover:text-white transition-colors"
                    >
                        Buat Tiket Baru
                    </button>
                </div>

                <!-- Exact Screenshot Replica Pagination -->
                <div v-if="props.tickets.total > 0" class="mt-8 flex items-center justify-between border-t border-slate-100 pt-8 pb-4 px-2">
                    <!-- Left Side: Show Selector & Records Info -->
                    <div class="flex items-center gap-5">
                        <div class="flex items-center gap-3">
                            <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Tampilkan</span>
                            <div class="relative group">
                                <select
                                    :value="props.tickets.per_page"
                                    class="appearance-none flex items-center gap-2 bg-white border border-[#003628]/40 rounded-full px-4 py-1.5 pr-8 text-[11px] font-black text-slate-900 shadow-sm cursor-pointer hover:border-[#003628] transition-all outline-none focus:ring-4 focus:ring-[#003628]/5"
                                    @change="router.get(route('helpdesk.index'), { per_page: $event.target.value }, { preserveState: true, preserveScroll: true })"
                                >
                                    <option :value="10">10</option>
                                    <option :value="25">25</option>
                                    <option :value="50">50</option>
                                    <option :value="100">100</option>
                                </select>
                                <svg class="size-3 text-slate-900 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                            </div>
                        </div>
                        <div class="text-[10px] font-black uppercase tracking-widest flex items-center gap-1.5">
                            <span class="text-slate-900">{{ props.tickets.from || 0 }}-{{ props.tickets.to || 0 }}</span>
                            <span class="text-slate-400">DARI</span>
                            <span class="text-slate-900">{{ props.tickets.total }}</span>
                            <span class="text-slate-400">DATA</span>
                        </div>
                    </div>

                    <!-- Standardized Pagination Controls -->
                    <div class="flex items-center gap-1.5">
                        <!-- Previous Arrow -->
                        <Link
                            v-if="props.tickets.prev_page_url"
                            :href="props.tickets.prev_page_url"
                            class="h-9 w-9 flex items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 hover:border-[#003628]/30 hover:text-[#003628] active:scale-95 shadow-sm transition-all"
                        >
                            <span class="text-lg leading-none">‹</span>
                        </Link>
                        <span v-else class="h-9 w-9 flex items-center justify-center rounded-xl border border-slate-200 bg-white opacity-30 text-slate-300 cursor-not-allowed shadow-sm">
                            <span class="text-lg leading-none">‹</span>
                        </span>

                        <!-- Page Numbers -->
                        <template v-for="(link, i) in props.tickets.links" :key="i">
                            <template v-if="!link.label.includes('Previous') && !link.label.includes('Next')">
                                <Link
                                    v-if="link.url"
                                    :href="link.url"
                                    class="h-9 min-w-[36px] px-2 flex items-center justify-center rounded-xl text-[11px] font-black transition-all border shadow-sm"
                                    :class="link.active 
                                        ? 'border-[#003628] bg-[#003628] text-white shadow-lg shadow-[#003628]/20' 
                                        : 'border-slate-200 bg-white text-slate-500 hover:border-[#003628]/30 hover:text-[#003628] active:scale-95'"
                                    v-html="link.label"
                                />
                                <span v-else-if="link.label === '...'" class="px-2 text-slate-400 font-black">...</span>
                            </template>
                        </template>

                        <!-- Next Arrow -->
                        <Link
                            v-if="props.tickets.next_page_url"
                            :href="props.tickets.next_page_url"
                            class="h-9 w-9 flex items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 hover:border-[#003628]/30 hover:text-[#003628] active:scale-95 shadow-sm transition-all"
                        >
                            <span class="text-lg leading-none">›</span>
                        </Link>
                        <span v-else class="h-9 w-9 flex items-center justify-center rounded-xl border border-slate-200 bg-white opacity-30 text-slate-300 cursor-not-allowed shadow-sm">
                            <span class="text-lg leading-none">›</span>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <AppConfirmDialog
            :open="deleteConfirmId !== null"
            kicker="Hapus Aktivitas"
            title="Hapus data ini?"
            description="Data aktivitas akan dihapus dari daftar dan tidak dapat dipulihkan secara otomatis."
            confirm-label="Ya, Hapus"
            cancel-label="Tidak"
            confirm-variant="danger"
            :subject="
                activeDeleteItem
                    ? `${activeDeleteItem.requester} - ${activeDeleteItem.category}`
                    : null
            "
            @close="deleteConfirmId = null"
            @confirm="deleteItem"
        />

        <!-- Create Ticket Modal -->
        <Dialog :open="showCreateModal" @update:open="(val: boolean) => !val && closeCreateModal()">
            <DialogContent class="sm:max-w-[1000px] border-none bg-transparent p-0">
                <DialogHeader class="sr-only">
                    <DialogTitle>Create New Ticket</DialogTitle>
                    <DialogDescription>
                        Fill in the details to create a new ticket.
                    </DialogDescription>
                </DialogHeader>
                <div
                    class="max-h-[92vh] overflow-y-auto rounded-xl bg-background shadow-2xl"
                >
                        <HelpdeskForm
                            :form="createForm"
                            :priority-options="props.priorityOptions"
                            :status-options="props.statusOptions"
                            :ticket-scope-options="props.ticketScopeOptions"
                            :maintenance-type-options="props.maintenanceTypeOptions"
                            :category-options="props.categoryOptions"
                            :requester-options="[]"
                            :vendor-options="props.vendorOptions"
                            submit-label="Simpan Tiket"
                            :is-modal="true"
                            @submit="submitCreate"
                            @cancel="closeCreateModal"
                        />
                </div>
            </DialogContent>
        </Dialog>

        <!-- View Ticket Modal -->
        <Dialog :open="showViewModal" @update:open="(val: boolean) => !val && closeViewModal()">
            <DialogContent class="sm:max-w-[1000px] border-none bg-transparent p-0">
                <DialogHeader class="sr-only">
                    <DialogTitle>Ticket Details</DialogTitle>
                    <DialogDescription>
                        View all information related to this ticket.
                    </DialogDescription>
                </DialogHeader>
                <div class="max-h-[92vh] overflow-y-auto rounded-xl bg-background shadow-2xl">
                    <HelpdeskDetail
                        v-if="viewingTicket"
                        :ticket="viewingTicket"
                        :can-view-all="props.canViewAll"
                        :is-modal="true"
                        :tech-company="props.techCompany"
                        :tech-location="props.techLocation"
                        @edit="() => { closeViewModal(); handleOpenEdit(viewingTicket!); }"
                        @close="closeViewModal"
                    />
                </div>
            </DialogContent>
        </Dialog>

        <!-- Edit Ticket Modal -->
        <Dialog :open="showEditModal" @update:open="(val: boolean) => !val && closeEditModal()">
            <DialogContent class="sm:max-w-[1000px] border-none bg-transparent p-0">
                <DialogHeader class="sr-only">
                    <DialogTitle>Edit Ticket</DialogTitle>
                    <DialogDescription>
                        Update ticket information.
                    </DialogDescription>
                </DialogHeader>
                <div class="max-h-[92vh] overflow-y-auto rounded-xl bg-background shadow-2xl">
                    <HelpdeskForm
                        :form="editForm"
                        :priority-options="props.priorityOptions"
                        :status-options="props.statusOptions"
                        :ticket-scope-options="props.ticketScopeOptions"
                        :maintenance-type-options="props.maintenanceTypeOptions"
                        :category-options="props.categoryOptions"
                        :requester-options="[]"
                        :vendor-options="props.vendorOptions"
                        submit-label="Perbarui Tiket"
                        :is-modal="true"
                        @submit="submitUpdate"
                        @cancel="closeEditModal"
                    />
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
