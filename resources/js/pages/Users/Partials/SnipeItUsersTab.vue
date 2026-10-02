<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { onClickOutside } from '@vueuse/core';
import axios from 'axios';
import {
    Download,
    Pencil,
    Printer,
    RefreshCw,
    Search,
    SlidersHorizontal,
    Eye,
    Plus,
    Loader2,
    User,
    Trash2,
    Building2,
    MapPin,
    ExternalLink,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import UserForm from './UserForm.vue';

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
    filterOptions?: {
        companies: Array<{ name: string; count: number }>;
        locations: Array<{ name: string; count: number }>;
        departments: Array<{ name: string; count: number }>;
    };
    options: {
        managers: any[];
        locations: any[];
        departments: any[];
        companies: any[];
    };
}

const props = defineProps<Props>();

const searchQuery = ref('');
const selectedCompany = ref('');
const selectedLocation = ref('');
const selectedDepartment = ref('');

const filteredUsers = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();
    return props.users.filter(
        (user) =>
            (!selectedCompany.value || user.company_name === selectedCompany.value) &&
            (!selectedLocation.value || user.location_name === selectedLocation.value) &&
            (!selectedDepartment.value || user.department_name === selectedDepartment.value) &&
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
                ].some((value) => String(value || '').toLowerCase().includes(query))),
    );
});

const formatDate = (value?: string | null) => {
    if (!value) return '-';
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
onClickOutside(filterPanelRef, () => { showFilters.value = false; });

const activeFilterCount = computed(() =>
    [
        selectedCompany.value,
        selectedLocation.value,
        selectedDepartment.value,
    ].filter(Boolean).length,
);

const resetFilters = () => {
    searchQuery.value = '';
    selectedCompany.value = '';
    selectedLocation.value = '';
    selectedDepartment.value = '';
    currentPage.value = 1;
};

// Pagination
const pageSize = ref(10);
const currentPage = ref(1);

const totalPages = computed(() => Math.max(1, Math.ceil(filteredUsers.value.length / pageSize.value)));
const paginatedUsers = computed(() => {
    const start = (currentPage.value - 1) * pageSize.value;
    return filteredUsers.value.slice(start, start + pageSize.value);
});

const pageStart = computed(() => filteredUsers.value.length === 0 ? 0 : (currentPage.value - 1) * pageSize.value + 1);
const pageEnd = computed(() => Math.min(currentPage.value * pageSize.value, filteredUsers.value.length));

const pageNumbers = computed(() => {
    const total = totalPages.value;
    if (total <= 5) return Array.from({ length: total }, (_, i) => i + 1);
    const start = Math.max(1, currentPage.value - 1);
    const end = Math.min(total, start + 2);
    const adjustedStart = Math.max(1, end - 2);
    return Array.from({ length: end - adjustedStart + 1 }, (_, i) => adjustedStart + i);
});

watch([searchQuery, selectedCompany, selectedLocation, selectedDepartment, pageSize], () => { currentPage.value = 1; });

const goToPreviousPage = () => { currentPage.value = Math.max(1, currentPage.value - 1); };
const goToNextPage = () => { currentPage.value = Math.min(totalPages.value, currentPage.value + 1); };
const setPage = (page: number) => { currentPage.value = Math.min(Math.max(page, 1), totalPages.value); };

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
    const header = ['Name', 'Username', 'Email', 'Phone', 'Job Title', 'Company', 'Department', 'Location', 'Created At'];
    const rows = filteredUsers.value.map((user) => [
        user.name,
        user.username || '-',
        user.email,
        user.phone || '-',
        user.jobtitle || '-',
        user.company_name || '-',
        user.department_name || '-',
        user.location_name || '-',
        formatDate(user.created_at),
    ]);
    const csv = [header, ...rows].map((cols) => cols.map((col) => escapeCsvValue(col)).join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = fileName;
    link.click();
    URL.revokeObjectURL(url);
};

const downloadPdf = () => { window.print(); };

// Modal
const isModalOpen = ref(false);
const modalMode = ref<'create' | 'edit' | 'detail'>('create');
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
        alert('Gagal mengambil data user.');
    } finally {
        loadingUser.value = false;
    }
};

const openDetailModal = async (id: number) => {
    if (loadingUser.value) return;
    loadingUser.value = true;
    modalMode.value = 'detail';
    try {
        const response = await axios.get(`/users/${id}/edit-data`);
        selectedUser.value = response.data;
        isModalOpen.value = true;
    } catch (err) {
        console.error('Failed to fetch user detail', err);
        alert('Gagal mengambil detail user.');
    } finally {
        loadingUser.value = false;
    }
};

const closeModal = () => {
    isModalOpen.value = false;
    selectedUser.value = null;
};

const handleModalSuccess = () => {
    closeModal();
    router.reload({ preserveScroll: true });
};

const deleteUser = (user: UserItem) => {
    if (!user.id || !window.confirm(`Hapus user "${user.name}" dari Snipe-IT dan LLDAP?`)) return;
    router.delete(`/users/${user.id}`, { preserveScroll: true });
};
</script>

<template>
    <div class="flex h-full min-h-0 flex-col">
        <!-- Toolbar Section -->
        <div class="mb-3 flex flex-shrink-0 items-center justify-between gap-3">
            <div class="relative max-w-xs flex-1">
                <Search class="absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-slate-400" />
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Cari data identitas..."
                    class="h-9 w-full rounded-xl border border-slate-200 bg-white pr-3 pl-9 text-xs text-slate-900 shadow-sm transition-all outline-none placeholder:text-slate-400 focus:border-[#003628]/50 focus:ring-2 focus:ring-[#003628]/10"
                />
            </div>

            <div class="flex items-center gap-1.5">
                <button @click="downloadPdf" class="flex size-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 shadow-sm transition-all hover:bg-[#003628]/5 hover:text-[#003628]" title="Ekspor PDF">
                    <Printer class="size-4" />
                </button>
                <button @click="downloadCsv" class="flex size-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 shadow-sm transition-all hover:bg-[#003628]/5 hover:text-[#003628]" title="Ekspor CSV">
                    <Download class="size-4" />
                </button>

                <div class="mx-1 h-5 w-px bg-slate-200" />

                <!-- Filter -->
                <div ref="filterPanelRef" class="relative">
                    <button @click="showFilters = !showFilters" class="relative flex size-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 shadow-sm transition-all hover:bg-[#003628]/5 hover:text-[#003628]">
                        <SlidersHorizontal class="size-4" />
                        <span v-if="activeFilterCount" class="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-[#003628] text-[10px] font-black text-white ring-2 ring-white">
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
                        <div v-if="showFilters" class="absolute top-full right-0 z-50 mt-3 w-80 overflow-hidden rounded-[32px] border border-slate-200 bg-white/95 p-6 shadow-2xl backdrop-blur-xl">
                            <div class="mb-8 flex items-center justify-between">
                                <h3 class="text-[10px] font-black tracking-widest text-slate-400 uppercase">Persempit Pencarian</h3>
                                <button @click="resetFilters(); showFilters = false;" class="flex items-center gap-1.5 text-[10px] font-black tracking-widest text-[#003628] uppercase transition-colors hover:opacity-70">
                                    <RefreshCw class="size-3" /> Reset
                                </button>
                            </div>
                            <div class="space-y-4">
                                <div class="space-y-1.5">
                                    <label class="ml-1 text-[9px] font-black tracking-widest text-slate-500 uppercase">Perusahaan</label>
                                    <select v-model="selectedCompany" class="h-9 w-full appearance-none rounded-xl border border-slate-200 bg-slate-50 px-3 text-[11px] font-medium text-slate-700 outline-none focus:border-[#003628]/50 focus:bg-white">
                                        <option value="">Semua Perusahaan</option>
                                        <option v-for="item in filterOptions?.companies" :key="item.name" :value="item.name">
                                            {{ item.name }} ({{ item.count }})
                                        </option>
                                    </select>
                                </div>
                                <div class="space-y-1.5">
                                    <label class="ml-1 text-[9px] font-black tracking-widest text-slate-500 uppercase">Lokasi</label>
                                    <select v-model="selectedLocation" class="h-9 w-full appearance-none rounded-xl border border-slate-200 bg-slate-50 px-3 text-[11px] font-medium text-slate-700 outline-none focus:border-[#003628]/50 focus:bg-white">
                                        <option value="">Semua Lokasi</option>
                                        <option v-for="item in filterOptions?.locations" :key="item.name" :value="item.name">
                                            {{ item.name }} ({{ item.count }})
                                        </option>
                                    </select>
                                </div>
                                <div class="space-y-1.5">
                                    <label class="ml-1 text-[9px] font-black tracking-widest text-slate-500 uppercase">Departemen</label>
                                    <select v-model="selectedDepartment" class="h-9 w-full appearance-none rounded-xl border border-slate-200 bg-slate-50 px-3 text-[11px] font-medium text-slate-700 outline-none focus:border-[#003628]/50 focus:bg-white">
                                        <option value="">Semua Departemen</option>
                                        <option v-for="item in filterOptions?.departments" :key="item.name" :value="item.name">
                                            {{ item.name }} ({{ item.count }})
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </Transition>
                </div>

                <button @click="openCreateModal" class="ml-1.5 flex h-9 items-center gap-1.5 rounded-lg bg-[#003628] px-3 text-white shadow-md shadow-[#003628]/10 transition-all hover:opacity-90 active:scale-95">
                    <Plus class="size-4" />
                    <span class="text-[11px] font-black tracking-widest uppercase">User Baru</span>
                </button>
            </div>
        </div>

        <!-- Table -->
        <div class="min-h-0 flex-1 overflow-auto rounded-xl border border-slate-200/50">
            <table class="w-full min-w-[900px] border-collapse">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50/50">
                        <th class="w-10 px-4 py-2 text-left text-[9px] font-black tracking-widest text-slate-400 uppercase">#</th>
                        <th class="px-4 py-2 text-left text-[9px] font-black tracking-widest text-slate-400 uppercase">Nama</th>
                        <th class="px-4 py-2 text-left text-[9px] font-black tracking-widest text-slate-400 uppercase">Email</th>
                        <th class="px-4 py-2 text-left text-[9px] font-black tracking-widest text-slate-400 uppercase">Perusahaan</th>
                        <th class="px-4 py-2 text-left text-[9px] font-black tracking-widest text-slate-400 uppercase">Departemen</th>
                        <th class="px-4 py-2 text-left text-[9px] font-black tracking-widest text-slate-400 uppercase">Lokasi</th>
                        <th class="px-4 py-2 text-right text-[9px] font-black tracking-widest text-slate-400 uppercase">Kelola</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr v-for="(item, index) in paginatedUsers" :key="item.id || `snipe-${item.snipeit_user_id}`" class="group transition-colors hover:bg-slate-50/50">
                        <td class="px-4 py-2.5 font-mono text-[9px] font-bold text-slate-300">{{ pageStart + index }}</td>
                        <td class="px-4 py-2.5">
                            <div class="flex items-center gap-2">
                                <div class="flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200 bg-white text-[#003628] shadow-sm">
                                    <User class="size-3.5" />
                                </div>
                                <span class="text-[12px] leading-none font-bold tracking-tight text-slate-900">{{ item.name }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-2.5"><span class="text-[10px] font-medium text-slate-400">{{ item.email }}</span></td>
                        <td class="px-4 py-2.5">
                            <div class="flex items-center gap-1.5">
                                <Building2 class="size-3 text-slate-400" />
                                <span class="text-[11px] font-semibold text-slate-600">{{ item.company_name || 'Generic' }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-2.5"><span class="text-[10px] text-slate-400">{{ item.department_name || 'Tanpa Dept' }}</span></td>
                        <td class="px-4 py-2.5">
                            <div class="flex items-center gap-1.5 text-slate-500">
                                <MapPin class="size-3 text-slate-400" />
                                <span class="text-[11px] font-semibold text-slate-600">{{ item.location_name || '-' }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-2.5 text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <button
                                    type="button"
                                    @click="openDetailModal(item.id)"
                                    class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-100 bg-white text-slate-400 shadow-sm transition-all hover:border-[#003628]/20 hover:text-[#003628] active:scale-90"
                                    title="Detail User"
                                >
                                    <Eye class="size-4" />
                                </button>
                                <button
                                    type="button"
                                    @click="openEditModal(item.id)"
                                    class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-100 bg-white text-slate-400 shadow-sm transition-all hover:border-amber-200 hover:text-amber-600 active:scale-90"
                                    title="Edit user"
                                >
                                    <Pencil class="size-4" />
                                </button>
                                <button
                                    type="button"
                                    @click="deleteUser(item)"
                                    class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-100 bg-white text-slate-400 shadow-sm transition-all hover:border-rose-200 hover:text-rose-600 active:scale-90"
                                    title="Hapus user"
                                >
                                    <Trash2 class="size-4" />
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="mt-3 flex flex-shrink-0 items-center justify-between border-t border-slate-100 pt-3 md:flex-row">
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-2 text-[9px] font-black tracking-widest text-slate-400 uppercase">
                    <span>Tampilkan</span>
                    <select v-model="pageSize" class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-[10px] font-black text-slate-600 outline-none focus:border-[#003628]/50">
                        <option :value="10">10</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                    </select>
                </div>
                <p class="text-[9px] font-black tracking-widest text-slate-400 uppercase">
                    <span class="text-slate-900">{{ pageStart }}–{{ pageEnd }}</span> DARI <span class="text-slate-900">{{ filteredUsers.length }}</span> USER
                </p>
            </div>

            <div class="flex items-center gap-1.5">
                <button type="button" class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white shadow-sm transition-all" :class="currentPage === 1 ? 'cursor-not-allowed text-slate-300 opacity-30' : 'text-slate-600 hover:border-[#003628]/30 hover:text-[#003628] active:scale-95'" @click="goToPreviousPage">
                    <span class="text-lg leading-none">‹</span>
                </button>
                <button v-for="page in pageNumbers" :key="page" type="button" class="flex h-9 min-w-[36px] items-center justify-center rounded-xl border px-2 text-[11px] font-black shadow-sm transition-all" :class="page === currentPage ? 'border-[#003628] bg-[#003628] text-white shadow-lg shadow-[#003628]/20' : 'border-slate-200 bg-white text-slate-500 hover:border-[#003628]/30 hover:text-[#003628] active:scale-95'" @click="setPage(page)">
                    {{ page }}
                </button>
                <button type="button" class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white shadow-sm transition-all" :class="currentPage >= totalPages ? 'cursor-not-allowed text-slate-300 opacity-30' : 'text-slate-600 hover:border-[#003628]/30 hover:text-[#003628] active:scale-95'" @click="goToNextPage">
                    <span class="text-lg leading-none">›</span>
                </button>
            </div>
        </div>

        <!-- Empty State -->
        <div v-if="filteredUsers.length === 0" class="py-24 text-center">
            <div class="flex flex-col items-center gap-4">
                <div class="mb-2 flex h-20 w-20 items-center justify-center rounded-full border border-slate-100 bg-slate-50 text-slate-300">
                    <Search class="size-10" />
                </div>
                <div class="space-y-1">
                    <h3 class="text-xl font-black tracking-widest text-slate-900 uppercase">Tidak Ada Data Identitas</h3>
                    <p class="mx-auto max-w-xs text-sm font-medium text-slate-500">Tidak dapat menemukan pengguna yang sesuai dengan pencarian Anda.</p>
                </div>
                <button @click="resetFilters" class="mt-4 h-11 rounded-xl bg-[#003628]/10 px-6 text-[11px] font-black tracking-widest text-[#003628] uppercase transition-all hover:bg-[#003628]/20 active:scale-95">
                    Reset Pencarian
                </button>
            </div>
        </div>

        <!-- User Form Modal -->
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="isModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm" @click.self="closeModal">
                <Transition
                    enter-active-class="transition duration-300 ease-out"
                    enter-from-class="opacity-0 translate-y-8 scale-95"
                    enter-to-class="opacity-100 translate-y-0 scale-100"
                    leave-active-class="transition duration-200 ease-in"
                    leave-from-class="opacity-100 translate-y-0 scale-100"
                    leave-to-class="opacity-0 translate-y-8 scale-95"
                >
                    <div v-if="isModalOpen" class="relative w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-[32px] border border-slate-200 bg-white shadow-2xl">
                        <!-- DETAIL MODE -->
                        <div v-if="modalMode === 'detail'" class="p-6 md:p-8">
                            <div class="mb-6 flex items-start justify-between border-b border-slate-100 pb-5">
                                <div class="flex items-center gap-3.5">
                                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#003628]/10 text-[#003628]">
                                        <User class="size-6" />
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <h2 class="text-xl font-black tracking-tight text-slate-900">
                                                {{ selectedUser?.name || 'Detail User' }}
                                            </h2>
                                            <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700">
                                                ID #{{ selectedUser?.snipeit_user_id || selectedUser?.id }}
                                            </span>
                                        </div>
                                        <p class="text-xs font-medium text-slate-400">
                                            {{ selectedUser?.email || selectedUser?.username || 'Akun Snipe-IT' }}
                                        </p>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    class="flex size-9 items-center justify-center rounded-xl text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                                    title="Tutup"
                                    @click="closeModal"
                                >
                                    <X class="size-4" />
                                </button>
                            </div>

                            <!-- Detail Content Grid -->
                            <div class="space-y-5">
                                <!-- Section 1: Identitas Pribadi -->
                                <div>
                                    <h3 class="mb-2.5 text-[10px] font-black tracking-widest text-[#003628] uppercase">
                                        Identitas Pribadi
                                    </h3>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 rounded-2xl border border-slate-100 bg-slate-50/50 p-4">
                                        <div>
                                            <span class="text-[10px] font-bold text-slate-400 uppercase">Nama Lengkap</span>
                                            <p class="text-xs font-bold text-slate-800">{{ selectedUser?.name || '-' }}</p>
                                        </div>
                                        <div>
                                            <span class="text-[10px] font-bold text-slate-400 uppercase">Username</span>
                                            <p class="text-xs font-bold text-slate-800">{{ selectedUser?.username || '-' }}</p>
                                        </div>
                                        <div>
                                            <span class="text-[10px] font-bold text-slate-400 uppercase">Alamat Email</span>
                                            <p class="text-xs font-bold text-slate-800">{{ selectedUser?.email || '-' }}</p>
                                        </div>
                                        <div>
                                            <span class="text-[10px] font-bold text-slate-400 uppercase">Telepon / Mobile</span>
                                            <p class="text-xs font-bold text-slate-800">{{ selectedUser?.phone || selectedUser?.mobile || '-' }}</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Section 2: Struktur Organisasi -->
                                <div>
                                    <h3 class="mb-2.5 text-[10px] font-black tracking-widest text-[#003628] uppercase">
                                        Struktur Organisasi
                                    </h3>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 rounded-2xl border border-slate-100 bg-slate-50/50 p-4">
                                        <div>
                                            <span class="text-[10px] font-bold text-slate-400 uppercase">Entitas / Perusahaan</span>
                                            <p class="text-xs font-bold text-slate-800">{{ selectedUser?.company_name || '-' }}</p>
                                        </div>
                                        <div>
                                            <span class="text-[10px] font-bold text-slate-400 uppercase">Departemen</span>
                                            <p class="text-xs font-bold text-slate-800">{{ selectedUser?.department_name || '-' }}</p>
                                        </div>
                                        <div>
                                            <span class="text-[10px] font-bold text-slate-400 uppercase">Lokasi Kerja</span>
                                            <p class="text-xs font-bold text-slate-800">{{ selectedUser?.location_name || '-' }}</p>
                                        </div>
                                        <div>
                                            <span class="text-[10px] font-bold text-slate-400 uppercase">Atasan / Manajer</span>
                                            <p class="text-xs font-bold text-slate-800">{{ selectedUser?.manager_name || '-' }}</p>
                                        </div>
                                        <div>
                                            <span class="text-[10px] font-bold text-slate-400 uppercase">Jabatan</span>
                                            <p class="text-xs font-bold text-slate-800">{{ selectedUser?.jobtitle || '-' }}</p>
                                        </div>
                                        <div>
                                            <span class="text-[10px] font-bold text-slate-400 uppercase">Nomor Karyawan</span>
                                            <p class="text-xs font-bold text-slate-800">{{ selectedUser?.employee_num || '-' }}</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Section 3: Catatan Audit (if any) -->
                                <div v-if="selectedUser?.notes">
                                    <h3 class="mb-2 text-[10px] font-black tracking-widest text-[#003628] uppercase">
                                        Catatan Internal
                                    </h3>
                                    <div class="rounded-xl border border-slate-100 bg-slate-50/40 p-3 text-xs text-slate-600">
                                        {{ selectedUser?.notes }}
                                    </div>
                                </div>
                            </div>

                            <!-- Modal Footer Actions -->
                            <div class="mt-8 flex items-center justify-between border-t border-slate-100 pt-5">
                                <a
                                    :href="`/users/${selectedUser?.id}`"
                                    class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 hover:text-[#003628] transition-colors"
                                >
                                    <span>Halaman Detail Lengkap & Aset</span>
                                    <ExternalLink class="size-3.5" />
                                </a>

                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        @click="closeModal"
                                        class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-600 transition hover:bg-slate-50"
                                    >
                                        Tutup
                                    </button>
                                    <button
                                        type="button"
                                        @click="modalMode = 'edit'"
                                        class="inline-flex items-center gap-1.5 rounded-xl bg-[#003628] px-5 py-2 text-xs font-bold text-white shadow-md shadow-[#003628]/20 transition hover:brightness-110 active:scale-95"
                                    >
                                        <Pencil class="size-3.5" />
                                        <span>Edit User</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- CREATE / EDIT MODE -->
                        <UserForm
                            v-else
                            :is-modal="true"
                            :title="modalMode === 'create' ? 'Tambah User Baru' : 'Perbarui Profil User'"
                            :submit-label="modalMode === 'create' ? 'Selesaikan Registrasi' : 'Simpan Perubahan'"
                            :submit-url="modalMode === 'create' ? '/users' : `/users/${selectedUser?.id}`"
                            :method="modalMode === 'create' ? 'post' : 'put'"
                            :user-id="selectedUser?.id"
                            :options="options"
                            :initial-values="selectedUser"
                            @success="handleModalSuccess"
                            @close="closeModal"
                        />
                    </div>
                </Transition>
            </div>
        </Transition>

        <!-- Loading Overlay -->
        <div v-if="loadingUser" class="fixed inset-0 z-[60] flex items-center justify-center bg-white/50 backdrop-blur-sm">
            <div class="flex flex-col items-center gap-4 rounded-[32px] border border-slate-200 bg-white p-8 shadow-2xl">
                <Loader2 class="size-8 animate-spin text-[#003628]" />
                <span class="text-[10px] font-black tracking-widest text-slate-500 uppercase">Mengambil Data Identitas...</span>
            </div>
        </div>
    </div>
</template>
