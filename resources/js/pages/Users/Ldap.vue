<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import {
    Building2,
    Eye,
    MapPin,
    Pencil,
    Plus,
    Search,
    Trash2,
    User,
    X,
} from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

interface LdapUser {
    username: string;
    name: string;
    first_name: string;
    last_name: string;
    email: string;
    phone: string;
    company_name: string;
    department_name: string;
    location_name: string;
    manager: string;
    title: string;
    uuid: string;
    created_at: string;
    modified_at: string;
    password_modified_at: string;
    groups: string[];
}

interface LdapOptions {
    companies: string[];
    locations: string[];
    managers: Array<{ value: string; label: string }>;
}

const props = defineProps<{
    users: LdapUser[];
    options: LdapOptions;
    status?: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Pengguna', href: '/users' },
    { title: 'User LDAP', href: '/users/ldap' },
];

const search = ref('');
const pageSize = ref(10);
const currentPage = ref(1);
const showForm = ref(false);
const showDetail = ref(false);
const detailUser = ref<LdapUser | null>(null);
const editingUsername = ref<string | null>(null);

const form = useForm({
    username: '',
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    company_name: '',
    department_name: '',
    location_name: '',
    manager: '',
    title: '',
    group: '',
    company_other: '',
    location_other: '',
    password: '',
});

const filteredUsers = computed(() => {
    const query = search.value.trim().toLowerCase();
    if (!query) return props.users;

    return props.users.filter((user) =>
        [user.username, user.name, user.email, user.phone].some((value) =>
            String(value || '')
                .toLowerCase()
                .includes(query),
        ),
    );
});

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

const pageNumbers = computed(() =>
    Array.from({ length: totalPages.value }, (_, index) => index + 1),
);

const goToPreviousPage = () => {
    currentPage.value = Math.max(1, currentPage.value - 1);
};

const goToNextPage = () => {
    currentPage.value = Math.min(totalPages.value, currentPage.value + 1);
};

const setPage = (page: number) => {
    currentPage.value = Math.min(Math.max(page, 1), totalPages.value);
};

const resetPage = () => {
    currentPage.value = 1;
};

const formatName = (user: LdapUser) => user.name || user.username;

const ldapCompanyOptions = computed(() =>
    [
        ...new Set(
            props.users.map((user) => user.company_name.trim()).filter(Boolean),
        ),
    ].sort((left, right) => left.localeCompare(right)),
);

const ldapLocationOptions = computed(() =>
    [
        ...new Set(
            props.users
                .map((user) => user.location_name.trim())
                .filter(Boolean),
        ),
    ].sort((left, right) => left.localeCompare(right)),
);

const ldapManagerOptions = computed(() =>
    props.users
        .filter((user) => user.username)
        .map((user) => ({
            value: user.name || user.username,
            label: `${user.name || user.username} (${user.username})`,
        }))
        .sort((left, right) => left.label.localeCompare(right.label)),
);

const managerOptions = computed(() => {
    const current = form.manager.trim();
    if (
        !current ||
        ldapManagerOptions.value.some((manager) => manager.value === current)
    ) {
        return ldapManagerOptions.value;
    }

    return [{ value: current, label: current }, ...ldapManagerOptions.value];
});

const openCreate = () => {
    editingUsername.value = null;
    form.reset();
    form.group = 'lldap_admin';
    form.clearErrors();
    showForm.value = true;
};

const openEdit = (user: LdapUser) => {
    editingUsername.value = user.username;
    form.username = user.username;
    form.first_name = user.first_name;
    form.last_name = user.last_name;
    form.email = user.email;
    form.phone = user.phone;
    form.company_name = user.company_name;
    form.department_name = user.department_name;
    form.location_name = user.location_name;
    form.manager = user.manager;
    form.title = user.title;
    form.group = 'lldap_admin';
    form.company_other = '';
    form.location_other = '';
    form.password = '';
    form.clearErrors();
    showForm.value = true;
};

const openDetail = (user: LdapUser) => {
    detailUser.value = user;
    showDetail.value = true;
};

const closeDetail = () => {
    showDetail.value = false;
    detailUser.value = null;
};

const closeForm = () => {
    showForm.value = false;
    form.reset();
    form.clearErrors();
};

const submit = () => {
    if (!editingUsername.value) {
        form.company_name =
            form.company_name === '__other__'
                ? form.company_other.trim()
                : form.company_name;
        form.location_name =
            form.location_name === '__other__'
                ? form.location_other.trim()
                : form.location_name;
        form.group = 'lldap_admin';
    }

    if (editingUsername.value) {
        form.put(`/users/ldap/${encodeURIComponent(editingUsername.value)}`, {
            preserveScroll: true,
            onSuccess: closeForm,
        });
        return;
    }

    form.post('/users/ldap', {
        preserveScroll: true,
        onSuccess: closeForm,
    });
};

const removeUser = (user: LdapUser) => {
    if (!window.confirm(`Hapus akun LDAP "${user.name || user.username}"?`))
        return;

    form.delete(`/users/ldap/${encodeURIComponent(user.username)}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="User LDAP" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="app-page-shell">
            <div
                v-if="status"
                class="mb-4 rounded-2xl border border-[#003628]/20 bg-[#003628]/5 p-3 text-center text-[10px] font-bold tracking-widest text-[#003628] uppercase"
            >
                {{ status }}
            </div>

            <div
                class="rounded-[32px] border border-slate-200/60 bg-white p-6 shadow-xl shadow-slate-200/50 lg:p-8"
            >
                <div
                    class="mb-8 flex flex-col justify-between gap-6 lg:flex-row lg:items-center"
                >
                    <div class="relative max-w-xl flex-1">
                        <Search
                            class="absolute top-1/2 left-4 size-4 -translate-y-1/2 text-slate-400"
                        />
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Cari data identitas..."
                            class="h-12 w-full rounded-2xl border border-slate-200 bg-white pr-4 pl-12 text-sm text-slate-900 shadow-sm transition-all outline-none placeholder:text-slate-400 focus:border-[#003628]/50 focus:ring-4 focus:ring-[#003628]/10"
                            @input="resetPage"
                        />
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="ml-1 flex h-11 items-center gap-2 rounded-xl bg-[#003628] px-6 text-white shadow-lg shadow-[#003628]/10 transition-all hover:opacity-90 active:scale-95"
                            @click="openCreate"
                        >
                            <Plus class="size-5" />
                            <span
                                class="text-xs font-black tracking-widest uppercase"
                                >User Baru</span
                            >
                        </button>
                    </div>
                </div>

                <div
                    class="hidden overflow-hidden rounded-xl border border-slate-200/50 md:block"
                >
                    <div class="overflow-x-auto">
                        <table
                            class="w-full min-w-[1050px] border-collapse text-left"
                        >
                            <thead
                                class="border-b border-slate-100 bg-slate-50/50"
                            >
                                <tr>
                                    <th
                                        class="w-12 px-6 py-3 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                    >
                                        #
                                    </th>
                                    <th
                                        class="px-6 py-3 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                    >
                                        Nama
                                    </th>
                                    <th
                                        class="px-6 py-3 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                    >
                                        Email
                                    </th>
                                    <th
                                        class="px-6 py-3 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                    >
                                        Perusahaan
                                    </th>
                                    <th
                                        class="px-6 py-3 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                    >
                                        Lokasi
                                    </th>
                                    <th
                                        class="px-6 py-3 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                    >
                                        Login LDAP
                                    </th>
                                    <th
                                        class="px-6 py-3 text-right text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                    >
                                        Kelola
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <tr
                                    v-for="(user, index) in paginatedUsers"
                                    :key="user.username.toLowerCase()"
                                    class="group border-b border-slate-50 transition-colors hover:bg-slate-50/50"
                                >
                                    <td
                                        class="px-6 py-4 font-mono text-[10px] font-bold text-slate-300"
                                    >
                                        {{ pageStart + index }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-[#003628] shadow-sm"
                                            >
                                                <User class="size-4" />
                                            </div>
                                            <div>
                                                <div
                                                    class="text-[13px] font-black tracking-tight text-slate-900"
                                                >
                                                    {{ formatName(user) }}
                                                </div>
                                                <div
                                                    class="font-mono text-[10px] font-bold text-slate-400"
                                                >
                                                    {{ user.username }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td
                                        class="px-6 py-4 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                    >
                                        {{ user.email || '-' }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <div
                                            class="flex items-center gap-2 text-[11px] font-black text-slate-600"
                                        >
                                            <Building2
                                                class="size-3 text-slate-400"
                                            />
                                            {{ user.company_name || 'Generic' }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div
                                            class="flex items-center gap-2 text-[11px] font-black text-slate-600"
                                        >
                                            <MapPin
                                                class="size-3 text-slate-400"
                                            />
                                            {{ user.location_name || '-' }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span
                                            class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-[9px] font-black tracking-wide text-emerald-700 uppercase ring-1 ring-emerald-200"
                                        >
                                            Bisa login LDAP
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex justify-end gap-2">
                                            <button
                                                type="button"
                                                class="flex size-8 items-center justify-center rounded-lg border border-slate-100 bg-white text-slate-400 shadow-sm hover:border-[#003628]/20 hover:text-[#003628]"
                                                title="Detail User LDAP"
                                                @click="openDetail(user)"
                                            >
                                                <Eye class="size-4" />
                                            </button>
                                            <button
                                                type="button"
                                                class="flex size-8 items-center justify-center rounded-lg border border-slate-100 bg-white text-slate-400 shadow-sm hover:border-amber-200 hover:text-amber-600"
                                                title="Edit User LDAP"
                                                @click="openEdit(user)"
                                            >
                                                <Pencil class="size-3.5" />
                                            </button>
                                            <button
                                                type="button"
                                                class="flex size-8 items-center justify-center rounded-lg border border-slate-100 bg-white text-slate-400 shadow-sm hover:border-rose-200 hover:text-rose-600"
                                                title="Hapus User LDAP"
                                                @click="removeUser(user)"
                                            >
                                                <Trash2 class="size-3.5" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!filteredUsers.length">
                                    <td
                                        colspan="7"
                                        class="px-6 py-16 text-center text-xs font-semibold text-slate-400"
                                    >
                                        Belum ada user LDAP yang cocok.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div
                        class="mt-8 flex flex-col items-center justify-between gap-6 border-t border-slate-100 pt-8 md:flex-row"
                    >
                        <div class="flex items-center gap-4">
                            <div
                                class="flex items-center gap-2 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                            >
                                <span>Tampilkan</span>
                                <select
                                    v-model="pageSize"
                                    class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-[10px] font-black text-slate-600 outline-none"
                                    @change="resetPage"
                                >
                                    <option :value="10">10</option>
                                    <option :value="25">25</option>
                                    <option :value="50">50</option>
                                </select>
                            </div>
                            <p
                                class="text-[9px] font-black tracking-widest text-slate-400 uppercase"
                            >
                                <span class="text-slate-900"
                                    >{{ pageStart }}–{{ pageEnd }}</span
                                >
                                DARI
                                <span class="text-slate-900">{{
                                    filteredUsers.length
                                }}</span>
                                USER
                            </p>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <button
                                type="button"
                                class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-lg text-slate-500 shadow-sm disabled:cursor-not-allowed disabled:opacity-30"
                                :disabled="currentPage === 1"
                                @click="goToPreviousPage"
                            >
                                ‹
                            </button>
                            <button
                                v-for="page in pageNumbers"
                                :key="page"
                                type="button"
                                class="flex h-9 min-w-[36px] items-center justify-center rounded-xl border px-2 text-[11px] font-black shadow-sm"
                                :class="
                                    page === currentPage
                                        ? 'border-[#003628] bg-[#003628] text-white'
                                        : 'border-slate-200 bg-white text-slate-500'
                                "
                                @click="setPage(page)"
                            >
                                {{ page }}
                            </button>
                            <button
                                type="button"
                                class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-lg text-slate-500 shadow-sm disabled:cursor-not-allowed disabled:opacity-30"
                                :disabled="currentPage >= totalPages"
                                @click="goToNextPage"
                            >
                                ›
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="showDetail && detailUser"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4"
            @click.self="closeDetail"
        >
            <div
                class="w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-2xl"
            >
                <div
                    class="flex items-start justify-between border-b border-slate-100 px-6 py-5"
                >
                    <div>
                        <p
                            class="text-[10px] font-black tracking-[0.18em] text-[#003628] uppercase"
                        >
                            User LDAP
                        </p>
                        <h2 class="mt-1 text-xl font-black text-slate-900">
                            {{ detailUser.name || detailUser.username }}
                        </h2>
                        <p class="mt-1 font-mono text-xs text-slate-400">
                            {{ detailUser.username }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="flex size-8 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100"
                        title="Tutup"
                        @click="closeDetail"
                    >
                        <X class="size-4" />
                    </button>
                </div>
                <div
                    class="grid max-h-[70vh] grid-cols-1 gap-4 overflow-y-auto p-6 sm:grid-cols-2"
                >
                    <div class="space-y-1">
                        <p class="detail-label">Nama depan</p>
                        <p class="detail-value">
                            {{ detailUser.first_name || '-' }}
                        </p>
                    </div>
                    <div class="space-y-1">
                        <p class="detail-label">Nama belakang</p>
                        <p class="detail-value">
                            {{ detailUser.last_name || '-' }}
                        </p>
                    </div>
                    <div class="space-y-1">
                        <p class="detail-label">Email</p>
                        <p class="detail-value">
                            {{ detailUser.email || '-' }}
                        </p>
                    </div>
                    <div class="space-y-1">
                        <p class="detail-label">Nomor telepon</p>
                        <p class="detail-value">
                            {{ detailUser.phone || '-' }}
                        </p>
                    </div>
                    <div class="space-y-1">
                        <p class="detail-label">Company</p>
                        <p class="detail-value">
                            {{ detailUser.company_name || '-' }}
                        </p>
                    </div>
                    <div class="space-y-1">
                        <p class="detail-label">Department</p>
                        <p class="detail-value">
                            {{ detailUser.department_name || '-' }}
                        </p>
                    </div>
                    <div class="space-y-1">
                        <p class="detail-label">Location</p>
                        <p class="detail-value">
                            {{ detailUser.location_name || '-' }}
                        </p>
                    </div>
                    <div class="space-y-1">
                        <p class="detail-label">Manager</p>
                        <p class="detail-value">
                            {{ detailUser.manager || '-' }}
                        </p>
                    </div>
                    <div class="space-y-1 sm:col-span-2">
                        <p class="detail-label">Title</p>
                        <p class="detail-value">
                            {{ detailUser.title || '-' }}
                        </p>
                    </div>
                    <div class="border-t border-slate-100 pt-4 sm:col-span-2">
                        <p class="detail-label mb-2">Group memberships</p>
                        <div class="flex flex-wrap gap-2">
                            <span
                                v-for="group in detailUser.groups"
                                :key="group"
                                class="rounded-lg bg-slate-100 px-3 py-1.5 font-mono text-xs font-bold text-slate-600"
                                >{{ group }}</span
                            >
                            <span
                                v-if="!detailUser.groups.length"
                                class="text-xs text-slate-400"
                                >Belum ada group membership.</span
                            >
                        </div>
                    </div>
                </div>
                <div
                    class="flex justify-end border-t border-slate-100 px-6 py-4"
                >
                    <button
                        type="button"
                        class="rounded-lg bg-[#003628] px-5 py-2 text-xs font-bold text-white"
                        @click="closeDetail"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>

        <div
            v-if="showForm"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4"
            @click.self="closeForm"
        >
            <div
                class="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl"
            >
                <div
                    class="flex items-center justify-between border-b border-slate-100 px-6 py-4"
                >
                    <div>
                        <h2 class="text-lg font-black text-slate-900">
                            {{
                                editingUsername
                                    ? 'Edit User LDAP'
                                    : 'Tambah User LDAP'
                            }}
                        </h2>
                        <p class="mt-1 text-xs text-slate-400">
                            Data ini hanya mengatur akun login LDAP.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="flex size-8 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100"
                        @click="closeForm"
                    >
                        <X class="size-4" />
                    </button>
                </div>
                <form
                    class="flex min-h-0 flex-1 flex-col overflow-hidden"
                    @submit.prevent="submit"
                >
                    <div
                        class="ldap-form-scroll relative min-h-0 flex-1 overflow-y-auto px-6 py-5"
                    >
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div
                                class="col-span-full border-b border-slate-100 pb-3 text-sm font-black text-slate-900"
                            >
                                User details
                            </div>
                            <label
                                class="order-1 space-y-1.5 text-xs font-bold text-slate-600"
                                >User ID<input
                                    v-model="form.username"
                                    :disabled="Boolean(editingUsername)"
                                    required
                                    class="h-10 w-full rounded-lg border border-slate-200 px-3 font-mono outline-none focus:border-[#003628] disabled:bg-slate-100"
                            /></label>
                            <label
                                v-if="editingUsername"
                                class="order-2 space-y-1.5 text-xs font-bold text-slate-600"
                                >UUID<input
                                    :value="
                                        editingUsername
                                            ? props.users.find(
                                                  (user) =>
                                                      user.username ===
                                                      editingUsername,
                                              )?.uuid || '-'
                                            : '-'
                                    "
                                    readonly
                                    class="h-10 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 font-mono text-xs text-slate-500"
                            /></label>
                            <label
                                class="order-3 space-y-1.5 text-xs font-bold text-slate-600"
                                :class="editingUsername ? 'sm:col-span-2' : ''"
                                >Display name<input
                                    :value="
                                        `${form.first_name} ${form.last_name}`.trim()
                                    "
                                    readonly
                                    class="h-10 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs text-slate-500"
                            /></label>
                            <label
                                class="order-4 space-y-1.5 text-xs font-bold text-slate-600"
                                >Nama depan<input
                                    v-model="form.first_name"
                                    required
                                    class="h-10 w-full rounded-lg border border-slate-200 px-3 outline-none focus:border-[#003628]"
                            /></label>
                            <label
                                class="order-4 space-y-1.5 text-xs font-bold text-slate-600"
                                >Nama belakang<input
                                    v-model="form.last_name"
                                    class="h-10 w-full rounded-lg border border-slate-200 px-3 outline-none focus:border-[#003628]"
                            /></label>
                            <label
                                class="order-5 space-y-1.5 text-xs font-bold text-slate-600"
                                >Email<input
                                    v-model="form.email"
                                    type="email"
                                    class="h-10 w-full rounded-lg border border-slate-200 px-3 outline-none focus:border-[#003628]"
                            /></label>
                            <label
                                class="order-5 space-y-1.5 text-xs font-bold text-slate-600"
                                >Nomor telepon<input
                                    v-model="form.phone"
                                    class="h-10 w-full rounded-lg border border-slate-200 px-3 outline-none focus:border-[#003628]"
                            /></label>
                            <label
                                class="order-9 space-y-1.5 text-xs font-bold text-slate-600"
                                >Company<select
                                    v-model="form.company_name"
                                    class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 outline-none focus:border-[#003628]"
                                >
                                    <option value="">Pilih company</option>
                                    <option
                                        v-for="company in ldapCompanyOptions"
                                        :key="company"
                                        :value="company"
                                    >
                                        {{ company }}
                                    </option>
                                    <option value="__other__">Other</option>
                                </select>
                                <input
                                    v-if="form.company_name === '__other__'"
                                    v-model="form.company_other"
                                    placeholder="Tulis company baru"
                                    class="h-10 w-full rounded-lg border border-slate-200 px-3 outline-none focus:border-[#003628]"
                                />
                            </label>
                            <label
                                class="order-9 space-y-1.5 text-xs font-bold text-slate-600"
                                >Department<input
                                    v-model="form.department_name"
                                    class="h-10 w-full rounded-lg border border-slate-200 px-3 outline-none focus:border-[#003628]"
                            /></label>
                            <label
                                class="order-10 space-y-1.5 text-xs font-bold text-slate-600"
                                >Location<select
                                    v-model="form.location_name"
                                    class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 outline-none focus:border-[#003628]"
                                >
                                    <option value="">Pilih location</option>
                                    <option
                                        v-for="location in ldapLocationOptions"
                                        :key="location"
                                        :value="location"
                                    >
                                        {{ location }}
                                    </option>
                                    <option value="__other__">Other</option>
                                </select>
                                <input
                                    v-if="form.location_name === '__other__'"
                                    v-model="form.location_other"
                                    placeholder="Tulis location baru"
                                    class="h-10 w-full rounded-lg border border-slate-200 px-3 outline-none focus:border-[#003628]"
                                />
                            </label>
                            <label
                                class="order-10 space-y-1.5 text-xs font-bold text-slate-600"
                                >Manager<select
                                    v-model="form.manager"
                                    class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 outline-none focus:border-[#003628]"
                                >
                                    <option value="">Pilih manager</option>
                                    <option
                                        v-for="manager in managerOptions"
                                        :key="manager.value"
                                        :value="manager.value"
                                    >
                                        {{ manager.label }}
                                    </option>
                                </select>
                            </label>
                            <label
                                class="order-[11] space-y-1.5 text-xs font-bold text-slate-600 sm:col-span-2"
                                >Title<input
                                    v-model="form.title"
                                    class="h-10 w-full rounded-lg border border-slate-200 px-3 outline-none focus:border-[#003628]"
                            /></label>
                            <div
                                v-if="editingUsername"
                                class="order-[12] col-span-full border-t border-slate-100 pt-4"
                            >
                                <h3
                                    class="mb-3 text-sm font-black text-slate-900"
                                >
                                    Group memberships
                                </h3>
                                <div class="flex flex-wrap gap-2">
                                    <span
                                        v-for="group in props.users.find(
                                            (user) =>
                                                user.username ===
                                                editingUsername,
                                        )?.groups || []"
                                        :key="group"
                                        class="rounded-lg bg-slate-100 px-3 py-1.5 font-mono text-xs font-bold text-slate-600"
                                        >{{ group }}</span
                                    >
                                    <span
                                        v-if="
                                            !props.users.find(
                                                (user) =>
                                                    user.username ===
                                                    editingUsername,
                                            )?.groups?.length
                                        "
                                        class="text-xs text-slate-400"
                                    >
                                        Belum ada group membership.
                                    </span>
                                </div>
                            </div>
                            <label
                                v-else
                                class="order-[12] space-y-1.5 text-xs font-bold text-slate-600"
                            >
                                Group membership
                                <select
                                    v-model="form.group"
                                    disabled
                                    class="h-10 w-full rounded-lg border border-slate-200 bg-slate-100 px-3 text-sm text-slate-600 outline-none"
                                >
                                    <option value="lldap_admin">
                                        lldap_admin
                                    </option>
                                </select>
                            </label>
                            <label
                                class="order-[13] space-y-1.5 text-xs font-bold text-slate-600"
                                >{{
                                    editingUsername
                                        ? 'Password baru (opsional)'
                                        : 'Password'
                                }}<input
                                    v-model="form.password"
                                    type="password"
                                    :required="!editingUsername"
                                    class="h-10 w-full rounded-lg border border-slate-200 px-3 outline-none focus:border-[#003628]"
                            /></label>
                            <p
                                v-if="form.errors.api"
                                class="col-span-full text-xs font-bold text-rose-600"
                            >
                                {{ form.errors.api }}
                            </p>
                        </div>
                    </div>
                    <div
                        class="flex shrink-0 justify-end gap-3 border-t border-slate-100 bg-white px-6 py-4"
                    >
                        <button
                            type="button"
                            class="rounded-lg px-4 py-2 text-xs font-bold text-slate-500 hover:bg-slate-100"
                            @click="closeForm"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-lg bg-[#003628] px-5 py-2 text-xs font-bold text-white disabled:opacity-50"
                        >
                            {{ form.processing ? 'Menyimpan...' : 'Simpan' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.detail-label {
    color: #94a3b8;
    font-size: 10px;
    font-weight: 900;
    letter-spacing: 0.1em;
    text-transform: uppercase;
}

.detail-value {
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    background: #f8fafc;
    padding: 0.625rem 0.75rem;
    color: #334155;
    font-size: 0.875rem;
    font-weight: 600;
}

.ldap-form-scroll {
    scrollbar-width: auto;
    scrollbar-color: #94a3b8 #f1f5f9;
}

.ldap-form-scroll::-webkit-scrollbar {
    width: 10px;
}

.ldap-form-scroll::-webkit-scrollbar-track {
    border-radius: 999px;
    background: #f1f5f9;
}

.ldap-form-scroll::-webkit-scrollbar-thumb {
    border: 2px solid #f1f5f9;
    border-radius: 999px;
    background: #94a3b8;
}

.ldap-form-scroll::-webkit-scrollbar-thumb:hover {
    background: #64748b;
}
</style>
