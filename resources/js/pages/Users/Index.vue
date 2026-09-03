<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { onClickOutside } from '@vueuse/core';
import axios from 'axios';
import {
    LucideDownload as Download,
    LucidePencil as Pencil,
    LucidePrinter as Printer,
    LucideRefreshCw as RefreshCw,
    LucideSearch as Search,
    LucideSlidersHorizontal as SlidersHorizontal,
    LucideDatabaseZap as DatabaseZap,
    LucideEye as Eye,
    LucideX as X,
    LucidePlus as Plus,
    LucideLoader2 as Loader2,
    LucideUsers as UsersIcon,
    LucideBuilding2 as Building2,
    LucideMapPin as MapPin,
    LucideUser as User
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import UserForm from '@/pages/Users/Partials/UserForm.vue';
import type { BreadcrumbItem } from '@/types';

interface UserItem {
    id: number;
    name: string;
    first_name?: string | null;
    last_name?: string | null;
    username?: string | null;
    email: string;
    phone?: string | null;
    jobtitle?: string | null;
    manager_id?: number | null;
    manager_name?: string | null;
    location_id?: number | null;
    location_name?: string | null;
    department_id?: number | null;
    department_name?: string | null;
    company_id?: number | null;
    company_name?: string | null;
    email_verified_at?: string | null;
    snipeit_user_id?: number | null;
    snipeit_username?: string | null;
    snipeit_synced_at?: string | null;
    created_at?: string | null;
}

interface Props {
    users: UserItem[];
    status?: string;
    options: {
        managers: any[];
        locations: any[];
        departments: any[];
        companies: any[];
    };
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Identitas', href: '/users' },
];

const searchQuery = ref('');
const selectedSource = ref<'all' | 'linked' | 'local'>('all');
const selectedCompany = ref('');
const selectedLocation = ref('');

const filteredUsers = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();
    return props.users.filter(
        (user) =>
            (selectedSource.value === 'all' ||
                (selectedSource.value === 'linked' &&
                    Boolean(user.snipeit_user_id)) ||
                (selectedSource.value === 'local' && !user.snipeit_user_id)) &&
            (!selectedCompany.value || user.company_name === selectedCompany.value) &&
            (!selectedLocation.value || user.location_name === selectedLocation.value) &&
            (!query ||
                [
                    user.name,
                    user.username,
                    user.email,
                    user.phone,
                    user.jobtitle,
                    user.manager_name,
                    user.location_name,
                    user.department_name,
                    user.company_name,
                    user.snipeit_username,
                ].some((value) =>
                    String(value || '')
                        .toLowerCase()
                        .includes(query),
                )),
    );
});

const formatDate = (value?: string | null) => {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const showFilters = ref(false);
const filterPanelRef = ref<HTMLElement | null>(null);

onClickOutside(filterPanelRef, () => {
    showFilters.value = false;
});

const activeFilterCount = computed(
    () =>
        [
            selectedSource.value !== 'all' ? selectedSource.value : '',
            selectedCompany.value,
            selectedLocation.value
        ].filter(Boolean).length,
);

const resetFilters = () => {
    searchQuery.value = '';
    selectedSource.value = 'all';
    selectedCompany.value = '';
    selectedLocation.value = '';
    currentPage.value = 1;
};

// --- Pagination Logic ---
const pageSize = ref(10);
const currentPage = ref(1);

const totalPages = computed(() =>
    Math.max(1, Math.ceil(filteredUsers.value.length / pageSize.value)),
);

const paginatedUsers = computed(() => {
    const start = (currentPage.value - 1) * pageSize.value;
    return filteredUsers.value.slice(start, start + pageSize.value);
});

const pageStart = computed(() =>
    filteredUsers.value.length === 0
        ? 0
        : (currentPage.value - 1) * pageSize.value + 1,
);

const pageEnd = computed(() =>
    Math.min(currentPage.value * pageSize.value, filteredUsers.value.length),
);

const pageNumbers = computed(() => {
    const total = totalPages.value;
    if (total <= 5) {
        return Array.from({ length: total }, (_, index) => index + 1);
    }
    const start = Math.max(1, currentPage.value - 1);
    const end = Math.min(total, start + 2);
    const adjustedStart = Math.max(1, end - 2);
    return Array.from(
        { length: end - adjustedStart + 1 },
        (_, index) => adjustedStart + index,
    );
});

watch([searchQuery, selectedSource, selectedCompany, selectedLocation, pageSize], () => {
    currentPage.value = 1;
});

const goToPreviousPage = () => {
    currentPage.value = Math.max(1, currentPage.value - 1);
};

const goToNextPage = () => {
    currentPage.value = Math.min(totalPages.value, currentPage.value + 1);
};

const setPage = (page: number) => {
    currentPage.value = Math.min(Math.max(page, 1), totalPages.value);
};

const downloadCsv = () => {
    const stamp = new Date().toISOString().slice(0, 10);
    const fileName = `users-${stamp}.csv`;
    
    const escapeCsvValue = (val: string | number) => {
        const normalized = String(val ?? '');
        if (normalized.includes(',') || normalized.includes('"') || normalized.includes('\n')) {
            return `"${normalized.replace(/"/g, '""')}"`;
        }
        return normalized;
    };

    const header = ['Name', 'Username', 'Email', 'Phone', 'Job Title', 'Company', 'Department', 'Location', 'Source', 'Created At'];
    const rows = filteredUsers.value.map((user) => [
        user.name,
        user.username || '-',
        user.email,
        user.phone || '-',
        user.jobtitle || '-',
        user.company_name || '-',
        user.department_name || '-',
        user.location_name || '-',
        user.snipeit_user_id ? 'Terhubung' : 'Local user',
        formatDate(user.created_at),
    ]);
    
    const csv = [header, ...rows]
        .map((columns) => columns.map((column) => escapeCsvValue(column)).join(','))
        .join('\n');
    
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = fileName;
    link.click();
    URL.revokeObjectURL(url);
};

const downloadPdf = () => {
    window.print();
};

const syncingLdap = ref(false);

const syncLdap = () => {
    if (syncingLdap.value) return;

    syncingLdap.value = true;
    import('@inertiajs/vue3').then(({ router }) => {
        router.post(
            '/users/sync-ldap',
            {},
            {
                onFinish: () => {
                    syncingLdap.value = false;
                },
            },
        );
    });
};

const isModalOpen = ref(false);
const modalMode = ref<'create' | 'edit'>('create');
const selectedUser = ref<any>(null);
const loadingUser = ref(false);

const openCreateModal = () => {
    modalMode.value = 'create';
    selectedUser.value = {
        first_name: '',
        last_name: '',
        username: '',
        email: '',
        employee_num: '',
        phone: '',
        mobile: '',
        jobtitle: '',
        website: '',
        notes: '',
        manager_id: '',
        location_id: '',
        department_id: '',
        company_id: '',
        password: '',
        password_confirmation: '',
        vip: false,
        remote: false,
        auto_assign_licenses: false,
    };
    isModalOpen.value = true;
};

const openEditModal = async (id: number) => {
    if (loadingUser.value) return;
    
    loadingUser.value = true;
    modalMode.value = 'edit';
    
    try {
        const response = await axios.get(`/users/${id}/edit-data`);
        selectedUser.value = response.data;
        isModalOpen.value = true;
    } catch (err) {
        console.error('Failed to fetch user data', err);
    } finally {
        loadingUser.value = false;
    }
};

const closeModal = () => {
    isModalOpen.value = false;
    selectedUser.value = null;
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Direktori Identitas" />

        <div class="app-page-shell">
            <div v-if="status" class="p-3 rounded-2xl border border-[#003628]/20 bg-[#003628]/5 text-[#003628] text-[10px] font-bold uppercase tracking-widest text-center mb-4">
                {{ status }}
            </div>

            <!-- TABLE CARD -->
            <div class="bg-white rounded-[32px] border border-slate-200/60 shadow-xl shadow-slate-200/50 p-6 lg:p-8">
                    
                    <!-- Toolbar Section -->
                    <div class="mb-8 flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                        <div class="relative flex-1 max-w-xl">
                            <Search class="absolute left-4 top-1/2 -translate-y-1/2 size-4 text-slate-400" />
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Cari data identitas..."
                                class="w-full h-12 pl-12 pr-4 rounded-2xl border border-slate-200 bg-white text-sm text-slate-900 placeholder:text-slate-400 focus:border-[#003628]/50 focus:ring-4 focus:ring-[#003628]/10 transition-all outline-none shadow-sm"
                            />
                        </div>

                        <div class="flex items-center gap-2">
                             <!-- Export Actions -->
                              <button
                                @click="downloadPdf"
                                class="size-11 rounded-xl border border-slate-200 bg-white flex items-center justify-center text-slate-500 hover:text-[#003628] hover:bg-[#003628]/5 transition-all shadow-sm"
                                title="Ekspor PDF"
                            >
                                <Printer class="size-5" />
                            </button>
                            <button
                                @click="downloadCsv"
                                class="size-11 rounded-xl border border-slate-200 bg-white flex items-center justify-center text-slate-500 hover:text-[#003628] hover:bg-[#003628]/5 transition-all shadow-sm"
                                title="Ekspor CSV"
                            >
                                <Download class="size-5" />
                            </button>

                            <div class="w-px h-6 bg-slate-200 mx-1" />

                            <!-- Filter -->
                            <div ref="filterPanelRef" class="relative">
                                <button
                                    @click="showFilters = !showFilters"
                                    class="size-11 rounded-xl border border-slate-200 bg-white flex items-center justify-center text-slate-500 hover:text-[#003628] hover:bg-[#003628]/5 transition-all relative shadow-sm"
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
                                    <div v-if="showFilters" class="absolute top-full right-0 z-50 mt-3 w-80 rounded-[32px] border border-slate-200 bg-white/95 p-6 shadow-2xl backdrop-blur-xl overflow-hidden">
                                        <div class="flex items-center justify-between mb-8">
                                            <h3 class="text-[10px] font-black uppercase tracking-widest text-slate-400">Persempit Pencarian</h3>
                                            <button 
                                                @click="resetFilters(); showFilters = false;"
                                                class="text-[10px] font-black uppercase tracking-widest text-[#003628] hover:opacity-70 transition-colors flex items-center gap-1.5"
                                            >
                                                <RefreshCw class="size-3" /> Reset
                                            </button>
                                        </div>
                                        <div class="space-y-4">
                                            <div class="space-y-1.5">
                                                <label class="text-[9px] font-black uppercase tracking-widest text-slate-500 ml-1">Perusahaan</label>
                                                <select v-model="selectedCompany" class="w-full h-9 px-3 rounded-xl border border-slate-200 bg-slate-50 text-[11px] font-medium text-slate-700 outline-none focus:border-[#003628]/50 focus:bg-white appearance-none">
                                                    <option value="">Semua Perusahaan</option>
                                                    <option v-for="c in options.companies" :key="c.id" :value="c.name">{{ c.name }}</option>
                                                </select>
                                            </div>

                                            <div class="space-y-1.5">
                                                <label class="text-[9px] font-black uppercase tracking-widest text-slate-500 ml-1">Lokasi</label>
                                                <select v-model="selectedLocation" class="w-full h-9 px-3 rounded-xl border border-slate-200 bg-slate-50 text-[11px] font-medium text-slate-700 outline-none focus:border-[#003628]/50 focus:bg-white appearance-none">
                                                    <option value="">Semua Lokasi</option>
                                                    <option v-for="l in options.locations" :key="l.id" :value="l.name">{{ l.name }}</option>
                                                </select>
                                            </div>

                                            <div class="space-y-1.5">
                                                <label class="text-[9px] font-black uppercase tracking-widest text-slate-500 ml-1">Sumber Profil</label>
                                                <select v-model="selectedSource" class="w-full h-9 px-3 rounded-xl border border-slate-200 bg-slate-50 text-[11px] font-medium text-slate-700 outline-none focus:border-[#003628]/50 focus:bg-white appearance-none">
                                                    <option value="all">Semua Sumber</option>
                                                    <option value="linked">Terhubung (Sync)</option>
                                                    <option value="local">User Lokal Saja</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </Transition>
                            </div>

                            <button 
                                @click="syncLdap"
                                :disabled="syncingLdap"
                                class="h-11 px-4 rounded-xl bg-slate-50 border border-slate-200 flex items-center gap-2.5 text-slate-700 text-xs font-bold hover:bg-slate-100 transition-all active:scale-95 disabled:opacity-50"
                            >
                                <DatabaseZap class="size-5 text-[#003628]" :class="{ 'animate-pulse': syncingLdap }" />
                                 <span class="uppercase tracking-widest text-[10px]">Sinkronisasi Master</span>
                            </button>

                            <button 
                                @click="openCreateModal"
                                class="h-11 px-6 rounded-xl bg-[#003628] text-white flex items-center gap-2 transition-all hover:opacity-90 shadow-lg shadow-[#003628]/10 active:scale-95 ml-2"
                            >
                                <Plus class="size-5" />
                                <span class="text-xs font-black uppercase tracking-widest">User Baru</span>
                            </button>
                        </div>
                    </div>

                    <!-- Desktop Table -->
                    <div class="hidden md:block overflow-hidden rounded-xl border border-slate-200/50">
                        <table class="w-full border-collapse min-w-[1100px]">
                            <thead>
                                <tr class="border-b border-slate-100 bg-slate-50/50">
                                    <th class="px-6 py-3 text-left text-[10px] font-black uppercase tracking-widest text-slate-400 w-12">#</th>
                                    <th class="px-6 py-3 text-left text-[10px] font-black uppercase tracking-widest text-slate-400">Nama</th>
                                    <th class="px-6 py-3 text-left text-[10px] font-black uppercase tracking-widest text-slate-400">Email</th>
                                    <th class="px-6 py-3 text-left text-[10px] font-black uppercase tracking-widest text-slate-400">Perusahaan</th>
                                    <th class="px-6 py-3 text-left text-[10px] font-black uppercase tracking-widest text-slate-400">Departemen</th>
                                    <th class="px-6 py-3 text-left text-[10px] font-black uppercase tracking-widest text-slate-400">Lokasi</th>
                                    <th class="px-6 py-3 text-right text-[10px] font-black uppercase tracking-widest text-slate-400">Kelola</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <tr v-for="(item, index) in paginatedUsers" :key="item.id || `snipe-${item.snipeit_user_id}`" class="group hover:bg-slate-50/50 transition-colors">
                                    <td class="px-6 py-4 text-[10px] font-bold font-mono text-slate-300">{{ pageStart + index }}</td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="h-9 w-9 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-[#003628] shadow-sm group-hover:scale-105 transition-transform duration-300">
                                                <User class="size-4.5" />
                                            </div>
                                            <span class="text-[13px] font-black text-slate-900 tracking-tight leading-none">{{ item.name }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="text-[10px] uppercase font-black tracking-widest text-slate-400">{{ item.email }}</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-2">
                                            <Building2 class="size-3 text-slate-400" />
                                            <span class="text-[11px] font-black text-slate-600">{{ item.company_name || 'Generic' }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">{{ item.department_name || 'Tanpa Dept' }}</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-2 text-slate-500">
                                            <MapPin class="size-3 text-slate-400" />
                                            <span class="text-[11px] font-black text-slate-600">{{ item.location_name || '-' }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <Link
                                                v-if="item.id"
                                                :href="`/users/${item.id}`"
                                                class="h-8 w-8 rounded-lg border border-slate-100 bg-white flex items-center justify-center text-slate-400 hover:text-[#003628] hover:border-[#003628]/20 transition-all active:scale-90 shadow-sm"
                                                title="Detail User"
                                            >
                                                <Eye class="size-4" />
                                            </Link>
                                            <button
                                                v-if="item.id"
                                                @click="openEditModal(item.id)"
                                                class="h-8 w-8 rounded-lg border border-slate-100 bg-white flex items-center justify-center text-slate-400 hover:text-amber-600 hover:border-amber-200 transition-all active:scale-90 shadow-sm"
                                                title="Edit user"
                                            >
                                                <Pencil class="size-4" />
                                            </button>
                                            <div v-if="!item.id" class="px-2 py-1 rounded bg-slate-100 text-[9px] font-black uppercase text-slate-400">
                                                Belum Sinkron
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="mt-8 flex flex-col md:flex-row items-center justify-between gap-6 border-t border-slate-100 pt-8">
                        <div class="flex items-center gap-4">
                            <div class="flex items-center gap-2 text-[9px] font-black text-slate-400 uppercase tracking-widest">
                                <span>Tampilkan</span>
                                <select
                                    v-model="pageSize"
                                    class="bg-white border border-slate-200 rounded-lg px-2 py-1 text-[10px] font-black text-slate-600 outline-none focus:border-[#003628]/50"
                                >
                                    <option :value="10">10</option>
                                    <option :value="25">25</option>
                                    <option :value="50">50</option>
                                </select>
                            </div>
                            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">
                                <span class="text-slate-900">{{ pageStart }}–{{ pageEnd }}</span> DARI <span class="text-slate-900">{{ filteredUsers.length }}</span> USER
                            </p>
                        </div>

                        <div class="flex items-center gap-1.5">
                            <button
                                type="button"
                                class="h-9 w-9 flex items-center justify-center rounded-xl transition-all border border-slate-200 bg-white shadow-sm"
                                :class="currentPage === 1 ? 'opacity-30 cursor-not-allowed text-slate-300' : 'text-slate-600 hover:border-[#003628]/30 hover:text-[#003628] active:scale-95'"
                                @click="goToPreviousPage"
                            >
                                <span class="text-lg leading-none">‹</span>
                            </button>

                            <button
                                v-for="page in pageNumbers"
                                :key="page"
                                type="button"
                                class="h-9 min-w-[36px] px-2 flex items-center justify-center rounded-xl text-[11px] font-black transition-all border shadow-sm"
                                :class="page === currentPage 
                                    ? 'border-[#003628] bg-[#003628] text-white shadow-lg shadow-[#003628]/20' 
                                    : 'border-slate-200 bg-white text-slate-500 hover:border-[#003628]/30 hover:text-[#003628] active:scale-95'"
                                @click="setPage(page)"
                            >
                                {{ page }}
                            </button>

                            <button
                                type="button"
                                class="h-9 w-9 flex items-center justify-center rounded-xl transition-all border border-slate-200 bg-white shadow-sm"
                                :class="currentPage >= totalPages ? 'opacity-30 cursor-not-allowed text-slate-300' : 'text-slate-600 hover:border-[#003628]/30 hover:text-[#003628] active:scale-95'"
                                @click="goToNextPage"
                            >
                                <span class="text-lg leading-none">›</span>
                            </button>
                        </div>
                    </div>

                    <!-- Mobile cards -->
                    <div class="space-y-3 p-4 md:hidden bg-slate-50/30 rounded-2xl mt-8">
                        <article
                            v-for="item in paginatedUsers"
                            :key="item.id || `mob-snipe-${item.snipeit_user_id}`"
                            class="overflow-hidden rounded-2xl border border-slate-200 bg-white p-4 shadow-sm active:scale-[0.98] transition-all"
                        >
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex items-center gap-3">
                                    <div class="h-9 w-9 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-center text-[#003628] shrink-0">
                                        <User class="size-4.5" />
                                    </div>
                                    <div class="space-y-0.5 min-w-0">
                                        <h3 class="text-[13px] font-bold text-slate-900 tracking-tight truncate">{{ item.name }}</h3>
                                        <p class="text-[10px] font-medium text-slate-500 truncate">{{ item.email }}</p>
                                    </div>
                                </div>
                                <span
                                    class="px-2 py-0.5 rounded-lg text-[9px] font-black uppercase tracking-widest shrink-0 border"
                                    :class="item.snipeit_user_id ? 'bg-[#003628]/5 text-[#003628] border-[#003628]/20' : 'bg-slate-100 text-slate-500 border-slate-200'"
                                >
                                    {{ item.snipeit_user_id ? 'Sync' : 'Lokal' }}
                                </span>
                            </div>

                            <div class="flex items-center justify-between pt-3 border-t border-slate-50">
                                <div class="space-y-0.5">
                                    <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Organisasi</p>
                                    <p class="text-[11px] font-bold text-slate-600 truncate max-w-[150px]">{{ item.company_name || '-' }}</p>
                                </div>
                                <div class="flex items-center gap-2">
                                     <button v-if="item.id" @click="openEditModal(item.id)" class="p-2 text-slate-400 hover:text-amber-600"><Pencil class="size-4" /></button>
                                     <Link v-if="item.id" :href="`/users/${item.id}`" class="p-2 text-slate-400 hover:text-[#003628]"><Eye class="size-4" /></Link>
                                </div>
                            </div>
                        </article>
                    </div>

                    <!-- Empty State -->
                    <div v-if="filteredUsers.length === 0" class="py-24 text-center">
                        <div class="flex flex-col items-center gap-4">
                            <div class="h-20 w-20 rounded-full bg-slate-50 flex items-center justify-center text-slate-300 border border-slate-100 mb-2">
                                <Search class="size-10" />
                            </div>
                            <div class="space-y-1">
                                <h3 class="text-xl font-black text-slate-900 uppercase tracking-widest">Tidak Ada Data Identitas</h3>
                                <p class="text-sm font-medium text-slate-500 max-w-xs mx-auto">Tidak dapat menemukan pengguna yang sesuai dengan pencarian Anda.</p>
                            </div>
                            <button @click="resetFilters" class="mt-4 h-11 px-6 rounded-xl bg-[#003628]/10 text-[#003628] text-[11px] font-black uppercase tracking-widest hover:bg-[#003628]/20 transition-all active:scale-95">Reset Pencarian</button>
                        </div>
                    </div>
                </div>
        </div>

        <!-- User Form Modal Overlay -->
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="isModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 backdrop-blur-sm bg-slate-900/60" @click.self="closeModal">
                <Transition
                    enter-active-class="transition duration-300 ease-out"
                    enter-from-class="opacity-0 translate-y-8 scale-95"
                    enter-to-class="opacity-100 translate-y-0 scale-100"
                    leave-active-class="transition duration-200 ease-in"
                    leave-from-class="opacity-100 translate-y-0 scale-100"
                    leave-to-class="opacity-0 translate-y-8 scale-95"
                >
                    <div v-if="isModalOpen" class="relative w-full max-w-[940px] max-h-[90vh] overflow-y-auto rounded-[32px] border border-slate-200 bg-white shadow-2xl no-scrollbar">
                         <UserForm
                            :is-modal="true"
                            :title="modalMode === 'create' ? 'Daftarkan User Baru' : `Tata Kelola: ${selectedUser?.name}`"
                            :submit-label="modalMode === 'create' ? 'Selesaikan Registrasi' : 'Perbarui Profil'"
                            :submit-url="modalMode === 'create' ? '/users' : `/users/${selectedUser?.id}`"
                            :method="modalMode === 'create' ? 'post' : 'put'"
                            :user-id="selectedUser?.id"
                            :options="options"
                            :initial-values="selectedUser"
                            @success="closeModal"
                         />
                    </div>
                </Transition>
            </div>
        </Transition>

        <!-- Loading Overlay -->
        <div v-if="loadingUser" class="fixed inset-0 z-[60] flex items-center justify-center bg-white/50 backdrop-blur-sm">
            <div class="flex flex-col items-center gap-4 p-8 rounded-[32px] bg-white border border-slate-200 shadow-2xl">
                <Loader2 class="size-8 animate-spin text-[#003628]" />
                <span class="text-[10px] font-black uppercase tracking-widest text-slate-500">Mengambil Data Identitas...</span>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
  display: none;
}
.no-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>
