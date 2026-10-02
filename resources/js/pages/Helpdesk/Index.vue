<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { onClickOutside } from '@vueuse/core';
import {
    Activity,
    ArrowUpRight,
    Check,
    Download,
    Eye,
    Pencil,
    Plus,
    Printer,
    RefreshCw,
    Search,
    SlidersHorizontal,
    Trash2,
    LayoutDashboard,
    List,
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
import SummaryTab from '@/pages/Helpdesk/Partials/SummaryTab.vue';
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
    technicianOptions: Array<{ name: string; count: number }>;
    priorityOptions: Array<{ name: string; count: number }>;
    statusOptions: Array<{ name: string; count: number }>;
    ticketScopeOptions: Array<{ value: string; label: string }>;
    maintenanceTypeOptions: string[];
    categoryOptions: Array<{ name: string; count: number }>;
    initialValues: any;
    canViewAll: boolean;
    techCompany: string;
    techLocation: string;
    vendorOptions: Array<{ id: number; name: string }>;
    analytics: {
        total: number;
        open: number;
        inProgress: number;
        closed: number;
        completionRate: number;
        avgResolutionTime: number;
        topCategories: Array<{ name: string; count: number; percentage: number }>;
        topRequesters: Array<{ name: string; department: string; location: string; count: number; percentage: number }>;
        statusDistribution: Array<{ status: string; count: number; percentage: number }>;
        priorityDistribution: Array<{ priority: string; count: number; percentage: number }>;
        recentTickets: Array<any>;
        technicianStats: Array<{ name: string; count: number }>;
    };
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Workspace', href: '/helpdesk' },
];

const toLocalDateString = (d: Date) => {
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const getDefaultDateRange = () => {
    const today = new Date();
    const thirtyOneDaysAgo = new Date(today);
    thirtyOneDaysAgo.setDate(today.getDate() - 30);
    return {
        from: toLocalDateString(thirtyOneDaysAgo),
        to: toLocalDateString(today),
    };
};

const defaultRange = getDefaultDateRange();

const filterForm = useForm({
    search: props.filters.search || '',
    status: props.filters.status || '',
    priority: props.filters.priority || '',
    category: props.filters.category || '',
    from_date: props.filters.from_date || defaultRange.from,
    to_date: props.filters.to_date || defaultRange.to,
    technician: props.filters.technician || '',
});

const deleteConfirmId = ref<number | null>(null);
const showFilters = ref(false);
const filterPanelRef = ref<HTMLElement | null>(null);
const showExport = ref(false);
const exportPanelRef = ref<HTMLElement | null>(null);
const showPrint = ref(false);
const printPanelRef = ref<HTMLElement | null>(null);
const printApprovedBy = ref('');

// Tab state
type TabKey = 'summary' | 'list';
const activeTab = ref<TabKey>('summary');
const setActiveTab = (key: TabKey) => { activeTab.value = key; };

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

    // Pakai filter yang sudah aktif dari sidebar
    if (filterForm.from_date) params.set('from_date', filterForm.from_date);
    if (filterForm.to_date) params.set('to_date', filterForm.to_date);
    if (filterForm.status) params.set('status', filterForm.status);
    if (filterForm.priority) params.set('priority', filterForm.priority);
    if (filterForm.category) params.set('category', filterForm.category);
    if (filterForm.technician) params.set('technician', filterForm.technician);
    if (filterForm.search) params.set('search', filterForm.search);

    const query = params.toString();

    return query ? `/helpdesk/export?${query}` : '/helpdesk/export';
};

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

    // Pakai filter yang sudah aktif dari sidebar
    if (filterForm.from_date) params.set('from_date', filterForm.from_date);
    if (filterForm.to_date) params.set('to_date', filterForm.to_date);
    if (filterForm.status) params.set('status', filterForm.status);
    if (filterForm.priority) params.set('priority', filterForm.priority);
    if (filterForm.category) params.set('category', filterForm.category);
    if (filterForm.technician) params.set('technician', filterForm.technician);
    if (printApprovedBy.value) params.set('approved_by', printApprovedBy.value);

    const query = params.toString();
    const url = query
        ? `/helpdesk/print-batch?${query}`
        : '/helpdesk/print-batch';

    window.open(url, '_blank', 'noopener');
    showPrint.value = false;
};

const activeFilterCount = computed(() => {
    const defaultRange = getDefaultDateRange();
    let count = 0;
    if (filterForm.status) count++;
    if (filterForm.priority) count++;
    if (filterForm.category) count++;
    if (filterForm.technician) count++;
    // Only count date filters if they are NOT the default 31-day range
    if (filterForm.from_date && filterForm.from_date !== defaultRange.from) count++;
    if (filterForm.to_date && filterForm.to_date !== defaultRange.to) count++;
    return count;
});

const filterOptionsLoading = ref(false);

const dynamicCategoryOptions = computed(() => {
    const fromData = props.tickets.data
        .map(t => t.category)
        .filter(Boolean);
    return [...new Set(fromData)].sort();
});

const dynamicTechnicianOptions = computed(() => {
    const fromData = props.tickets.data
        .map(t => t.technician)
        .filter(Boolean);
    return [...new Set(fromData)].sort();
});

async function fetchFilterOptions() {
    filterOptionsLoading.value = true;
    try {
        const params = new URLSearchParams();
        if (filterForm.from_date) params.set('from_date', filterForm.from_date);
        if (filterForm.to_date) params.set('to_date', filterForm.to_date);
        if (filterForm.status) params.set('status', filterForm.status);
        if (filterForm.priority) params.set('priority', filterForm.priority);
        if (filterForm.category) params.set('category', filterForm.category);
        if (filterForm.technician) params.set('technician', filterForm.technician);
        if (filterForm.search) params.set('search', filterForm.search);

        const res = await fetch(`/helpdesk/filter-options?${params}`);
        if (res.ok) {
            const data = await res.json();
            // The dynamic computed will merge with this data on next render
            // We trigger a re-render by updating a dummy state if needed
        }
    } catch (e) {
        console.error('Failed to fetch filter options', e);
    } finally {
        filterOptionsLoading.value = false;
    }
}

const categoryOptions = computed(() => {
    // Props already has count from backend
    return props.categoryOptions || [];
});

const technicianOptions = computed(() => {
    // Props already has count from backend
    return props.technicianOptions || [];
});

const applyFilters = (closeFlyout = false) => {
    if (closeFlyout) {
        showFilters.value = false;
    }
    fetchFilterOptions();
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
    const range = getDefaultDateRange();
    filterForm.from_date = range.from;
    filterForm.to_date = range.to;
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

['status', 'priority', 'category', 'technician', 'from_date', 'to_date'].forEach(field => {
    watch(
        () => filterForm[field as keyof typeof filterForm],
        () => {
            if (skipNextSearchAutoApply) {
                skipNextSearchAutoApply = false;
                return;
            }
            applyFilters();
        },
    );
});

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

const statusColors: Record<string, { dot: string; bg: string }> = {
    'Open': { dot: 'bg-amber-500', bg: 'bg-amber-50' },
    'In Progress': { dot: 'bg-blue-500', bg: 'bg-blue-50' },
    'Closed': { dot: 'bg-emerald-500', bg: 'bg-emerald-50' },
};

const getStatusColor = (status: string) => {
    return statusColors[status] || { dot: 'bg-slate-400', bg: 'bg-slate-50' };
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
        <Head title="Workspace" />

        <!-- Full-height, no outer scroll — unified pure white main card -->
        <div class="flex flex-col overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-xs" style="height: calc(100vh - 96px)">

            <!-- ╔═══════════════════════════════════════╗
                 HEADER — clean integrated white header
                 ╚═══════════════════════════════════════╝ -->
            <header class="flex flex-shrink-0 items-center justify-between gap-4 border-b border-slate-100 bg-white px-6 py-3.5">
                <!-- Left: identity -->
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-[#003628] border border-slate-200/60 shadow-2xs">
                        <Activity class="size-4.5" />
                    </div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-[15px] font-black tracking-tight text-slate-900">
                            Helpdesk Workspace
                        </h1>
                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700">
                            <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500" />
                            {{ props.analytics.total }} tiket
                        </span>
                    </div>
                </div>

                <!-- Right: tab switcher + action controls -->
                <div class="flex items-center gap-2">
                    <!-- Tab switcher -->
                    <div class="inline-flex items-center gap-1 rounded-xl border border-slate-200/60 bg-slate-100/90 p-1 shadow-2xs">
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-[11px] font-bold transition-all duration-150"
                            :class="activeTab === 'summary'
                                ? 'bg-white text-slate-900 shadow-xs'
                                : 'text-slate-500 hover:text-slate-800'"
                            @click="setActiveTab('summary')"
                        >
                            <LayoutDashboard class="size-3.5" :class="activeTab === 'summary' ? 'text-[#003628]' : 'text-slate-400'" />
                            <span>Ringkasan</span>
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-[11px] font-bold transition-all duration-150"
                            :class="activeTab === 'list'
                                ? 'bg-white text-slate-900 shadow-xs'
                                : 'text-slate-500 hover:text-slate-800'"
                            @click="setActiveTab('list')"
                        >
                            <List class="size-3.5" :class="activeTab === 'list' ? 'text-[#003628]' : 'text-slate-400'" />
                            <span>Daftar Tiket</span>
                            <span
                                class="rounded-md px-1.5 py-0.5 text-[9px] font-black"
                                :class="activeTab === 'list'
                                    ? 'bg-[#003628]/10 text-[#003628]'
                                    : 'bg-slate-200/80 text-slate-500'"
                            >
                                {{ props.tickets.data.length }}
                            </span>
                        </button>
                    </div>

                    <!-- Action controls (only visible in list tab) -->
                    <template v-if="activeTab === 'list'">
                        <!-- Small Search Box -->
                        <div class="relative w-40">
                            <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-slate-400" />
                            <input
                                v-model="filterForm.search"
                                type="text"
                                placeholder="Cari..."
                                class="w-full h-8 pl-9 pr-3 rounded-lg border border-slate-200 bg-white text-xs text-slate-900 placeholder:text-slate-400 focus:border-[#003628]/50 focus:ring-2 focus:ring-[#003628]/10 transition-all outline-none shadow-sm"
                            />
                        </div>

                        <!-- Print Panel -->
                        <div ref="printPanelRef" class="relative">
                            <button
                                type="button"
                                class="h-8 w-8 rounded-lg border border-slate-200 bg-white flex items-center justify-center text-slate-500 hover:text-[#003628] hover:bg-[#003628]/5 transition-all shadow-sm"
                                @click="showPrint = !showPrint; showExport = false; showFilters = false;"
                            >
                                <Printer class="size-4" />
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
                                        <!-- Info Filter Aktif -->
                                        <div class="rounded-xl bg-slate-50 border border-slate-200 p-3">
                                            <p class="text-[9px] font-black uppercase tracking-widest text-slate-500 mb-2">Filter Saat Ini</p>
                                            <div class="space-y-1 text-[11px] text-slate-600">
                                                <p><span class="font-semibold">Periode:</span> {{ filterForm.from_date || '-' }} s/d {{ filterForm.to_date || '-' }}</p>
                                                <p v-if="filterForm.status"><span class="font-semibold">Status:</span> {{ filterForm.status }}</p>
                                                <p v-if="filterForm.priority"><span class="font-semibold">Prioritas:</span> {{ filterForm.priority }}</p>
                                                <p v-if="filterForm.category"><span class="font-semibold">Kategori:</span> {{ filterForm.category }}</p>
                                                <p v-if="filterForm.technician"><span class="font-semibold">Teknisi:</span> {{ filterForm.technician }}</p>
                                            </div>
                                        </div>

                                        <!-- Approved By -->
                                        <div class="space-y-1.5">
                                            <label class="text-[9px] font-black uppercase tracking-widest text-slate-500 ml-1">Disetujui Oleh</label>
                                            <input v-model="printApprovedBy" type="text" placeholder="Nama lengkap..." class="w-full h-9 px-3 rounded-xl border border-slate-200 bg-slate-50 text-[11px] font-medium text-slate-700 placeholder:text-slate-400 outline-none focus:border-[#003628]/50 focus:bg-white" />
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
                                class="h-8 w-8 rounded-lg border border-slate-200 bg-white flex items-center justify-center text-slate-500 hover:text-[#003628] hover:bg-[#003628]/5 transition-all shadow-sm"
                                @click="showExport = !showExport; showFilters = false; showPrint = false;"
                            >
                                <Download class="size-4" />
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
                                        <!-- Info Filter Aktif -->
                                        <div class="rounded-xl bg-slate-50 border border-slate-200 p-3">
                                            <p class="text-[9px] font-black uppercase tracking-widest text-slate-500 mb-2">Filter Saat Ini</p>
                                            <div class="space-y-1 text-[11px] text-slate-600">
                                                <p><span class="font-semibold">Periode:</span> {{ filterForm.from_date || '-' }} s/d {{ filterForm.to_date || '-' }}</p>
                                                <p v-if="filterForm.status"><span class="font-semibold">Status:</span> {{ filterForm.status }}</p>
                                                <p v-if="filterForm.priority"><span class="font-semibold">Prioritas:</span> {{ filterForm.priority }}</p>
                                                <p v-if="filterForm.category"><span class="font-semibold">Kategori:</span> {{ filterForm.category }}</p>
                                                <p v-if="filterForm.technician"><span class="font-semibold">Teknisi:</span> {{ filterForm.technician }}</p>
                                                <p v-if="filterForm.search"><span class="font-semibold">Pencarian:</span> {{ filterForm.search }}</p>
                                            </div>
                                        </div>
                                        <button @click="doExport" class="w-full h-11 rounded-xl bg-[#003628] text-white text-xs font-bold hover:bg-[#003628]/90 transition-colors mt-2">Unduh Excel (.xlsx)</button>
                                    </div>
                                </div>
                            </Transition>
                        </div>

                        <!-- Filter Panel -->
                        <div ref="filterPanelRef" class="relative">
                            <button
                                type="button"
                                class="h-8 w-8 rounded-lg border border-slate-200 bg-white flex items-center justify-center text-slate-500 hover:text-[#003628] hover:bg-[#003628]/5 transition-all relative shadow-sm"
                                @click="showFilters = !showFilters; showExport = false; showPrint = false;"
                            >
                                <SlidersHorizontal class="size-4" />
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
                                        <h3 class="text-[10px] font-black uppercase tracking-widest text-slate-400">Filter Helpdesk Ticket</h3>
                                        <button @click="resetFilters" class="text-[10px] font-black uppercase tracking-widest text-[#003628] hover:opacity-70 transition-colors flex items-center gap-1.5 cursor-pointer">
                                            <RefreshCw class="size-3" /> Reset
                                        </button>
                                    </div>

                                    <div class="space-y-3.5">
                                        <!-- Status Filter -->
                                        <div class="space-y-1">
                                            <label class="text-[9px] font-black uppercase tracking-widest text-slate-500 ml-1">Status</label>
                                            <select v-model="filterForm.status" class="w-full h-9 px-3 rounded-xl border border-slate-200 bg-slate-50 text-[11px] font-medium text-slate-700 outline-none focus:border-[#003628]/50 focus:bg-white cursor-pointer">
                                                <option value="">Semua Status</option>
                                                <option v-for="status in props.statusOptions" :key="status.name" :value="status.name">
                                                    {{ status.name }} ({{ status.count }})
                                                </option>
                                            </select>
                                        </div>

                                        <!-- Priority Filter -->
                                        <div class="space-y-1">
                                            <label class="text-[9px] font-black uppercase tracking-widest text-slate-500 ml-1">Prioritas</label>
                                            <select v-model="filterForm.priority" class="w-full h-9 px-3 rounded-xl border border-slate-200 bg-slate-50 text-[11px] font-medium text-slate-700 outline-none focus:border-[#003628]/50 focus:bg-white cursor-pointer">
                                                <option value="">Semua Prioritas</option>
                                                <option v-for="option in props.priorityOptions" :key="option.name" :value="option.name">
                                                    {{ option.name }} ({{ option.count }})
                                                </option>
                                            </select>
                                        </div>

                                        <!-- Category Filter -->
                                        <div class="space-y-1">
                                            <label class="text-[9px] font-black uppercase tracking-widest text-slate-500 ml-1">Kategori</label>
                                            <select v-model="filterForm.category" class="w-full h-9 px-3 rounded-xl border border-slate-200 bg-slate-50 text-[11px] font-medium text-slate-700 outline-none focus:border-[#003628]/50 focus:bg-white cursor-pointer">
                                                <option value="">Semua Kategori</option>
                                                <option v-for="cat in categoryOptions" :key="cat.name" :value="cat.name">
                                                    {{ cat.name }} ({{ cat.count }})
                                                </option>
                                            </select>
                                        </div>

                                        <!-- Technician Filter -->
                                        <div class="space-y-1">
                                            <label class="text-[9px] font-black uppercase tracking-widest text-slate-500 ml-1">Teknisi</label>
                                            <select v-model="filterForm.technician" class="w-full h-9 px-3 rounded-xl border border-slate-200 bg-slate-50 text-[11px] font-medium text-slate-700 outline-none focus:border-[#003628]/50 focus:bg-white cursor-pointer">
                                                <option value="">Semua Teknisi</option>
                                                <option v-for="option in technicianOptions" :key="option.name" :value="option.name">
                                                    {{ option.name }} ({{ option.count }})
                                                </option>
                                            </select>
                                        </div>

                                        <!-- Date Inputs -->
                                        <div class="grid grid-cols-2 gap-3 pt-2 border-t border-slate-100">
                                            <div class="space-y-1">
                                                <label class="text-[9px] font-black uppercase tracking-widest text-slate-400">Dari</label>
                                                <input v-model="filterForm.from_date" type="date" class="w-full h-9 px-2 rounded-xl border border-slate-200 bg-slate-50 text-[10px] font-medium text-slate-700 outline-none focus:border-[#003628]/50 focus:bg-white" />
                                            </div>
                                            <div class="space-y-1">
                                                <label class="text-[9px] font-black uppercase tracking-widest text-slate-400">Hingga</label>
                                                <input v-model="filterForm.to_date" type="date" class="w-full h-9 px-2 rounded-xl border border-slate-200 bg-slate-50 text-[10px] font-medium text-slate-700 outline-none focus:border-[#003628]/50 focus:bg-white" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </Transition>
                        </div>

                        <!-- New Ticket Button -->
                        <button
                            type="button"
                            class="h-8 px-3 rounded-lg bg-[#003628] text-white flex items-center gap-1.5 transition-all hover:opacity-90 shadow-sm active:scale-95"
                            @click="showCreateModal = true"
                        >
                            <Plus class="size-4" />
                            <span class="text-xs font-bold">Tiket</span>
                        </button>
                    </template>
                </div>
            </header>

            <!-- ╔═══════════════════════════════════════╗
                 CONTENT — pure white background, fills height
                 ╚═══════════════════════════════════════╝ -->
            <div class="flex min-h-0 flex-1 flex-col overflow-hidden bg-white">
                <Transition
                    enter-active-class="transition duration-150 ease-out"
                    enter-from-class="opacity-0 translate-y-1"
                    enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="transition duration-100 ease-in"
                    leave-from-class="opacity-100"
                    leave-to-class="opacity-0"
                    mode="out-in"
                >
                    <!-- Summary Tab -->
                    <SummaryTab
                        v-if="activeTab === 'summary'"
                        key="summary"
                        :analytics="props.analytics"
                    />

                    <!-- List Tab (existing content) -->
                    <div v-else key="list" class="flex flex-col min-h-0 h-full overflow-y-auto p-4">
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
                                class="text-[#003628] font-black uppercase tracking-widest text-[11px] hover:opacity-70 transition-colors"
                            >
                                Buat Tiket Baru
                            </button>
                        </div>

                        <!-- Pagination -->
                        <div v-if="props.tickets.total > 0" class="mt-6 flex items-center justify-between border-t border-slate-100 pt-4 px-2">
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
                </Transition>
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
            <DialogContent class="sm:max-w-[1000px] border-none bg-transparent p-0 overflow-hidden">
                <DialogHeader class="sr-only">
                    <DialogTitle>Create New Ticket</DialogTitle>
                    <DialogDescription>
                        Fill in the details to create a new ticket.
                    </DialogDescription>
                </DialogHeader>
                <div class="max-h-[92vh] flex flex-col rounded-2xl bg-white shadow-2xl overflow-hidden">
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
            <DialogContent class="sm:max-w-[1000px] border-none bg-transparent p-0 overflow-hidden">
                <DialogHeader class="sr-only">
                    <DialogTitle>Edit Ticket</DialogTitle>
                    <DialogDescription>
                        Update ticket information.
                    </DialogDescription>
                </DialogHeader>
                <div class="max-h-[92vh] flex flex-col rounded-2xl bg-white shadow-2xl overflow-hidden">
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
