<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Plus,
    Search,
    Truck,
    Mail,
    Phone,
    MapPin,
    MoreHorizontal,
    Pencil,
    Trash2,
    X,
    Filter,
    User,
} from 'lucide-vue-next';
import { ref, watch } from 'vue';
import AppConfirmDialog from '@/components/AppConfirmDialog.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';

interface Vendor {
    id: number;
    name: string;
    contact_person: string | null;
    phone: string | null;
    email: string | null;
    address: string | null;
    category: string | null;
    created_at: string;
}

const props = defineProps<{
    vendors: {
        data: Vendor[];
        links: any[];
        meta: any;
    };
    filters: {
        search: string;
    };
}>();

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Master Vendors', href: '/vendors' },
];

// Search Logic
const searchInput = ref(props.filters.search || '');
let searchTimeout: any;
watch(searchInput, (val) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(
            '/vendors',
            { search: val },
            { preserveState: true, replace: true },
        );
    }, 300);
});

// Modal Logic
const showModal = ref(false);
const editingVendor = ref<Vendor | null>(null);
const form = useForm({
    name: '',
    contact_person: '',
    phone: '',
    email: '',
    address: '',
    category: '',
});

const openCreate = () => {
    editingVendor.value = null;
    form.reset();
    showModal.value = true;
};

const openEdit = (vendor: Vendor) => {
    editingVendor.value = vendor;
    form.name = vendor.name;
    form.contact_person = vendor.contact_person || '';
    form.phone = vendor.phone || '';
    form.email = vendor.email || '';
    form.address = vendor.address || '';
    form.category = vendor.category || '';
    showModal.value = true;
};

const submit = () => {
    if (editingVendor.value) {
        form.put(`/vendors/${editingVendor.value.id}`, {
            onSuccess: () => {
                showModal.value = false;
                form.reset();
            },
        });
    } else {
        form.post('/vendors', {
            onSuccess: () => {
                showModal.value = false;
                form.reset();
            },
        });
    }
};

// Delete Logic
const deleteConfirmId = ref<number | null>(null);
const handleDelete = () => {
    if (deleteConfirmId.value) {
        router.delete(`/vendors/${deleteConfirmId.value}`, {
            onSuccess: () => (deleteConfirmId.value = null),
        });
    }
};
</script>

<template>
    <Head title="Data Vendor" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="app-page-shell">
            <!-- Header Section -->
            <div
                class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <div class="mb-1 flex items-center gap-2">
                        <div class="h-4 w-1 rounded-full bg-[#d99528]" />
                        <span
                            class="text-[10px] font-black tracking-[0.2em] text-slate-400 uppercase"
                            >Pengelolaan Relasi</span
                        >
                    </div>
                    <h1
                        class="text-3xl font-black tracking-tight text-[#003628] uppercase"
                    >
                        Data Vendor
                    </h1>
                    <p
                        class="mt-1 text-[11px] font-bold tracking-widest text-slate-400 uppercase"
                    >
                        Kelola data penyedia layanan dan pemasok perangkat IT.
                    </p>
                </div>

                <Button
                    @click="openCreate"
                    class="group flex h-12 items-center gap-2 rounded-2xl bg-[#003628] px-6 text-white shadow-xl shadow-emerald-900/10 hover:bg-[#003628]/90"
                >
                    <Plus
                        class="h-5 w-5 transition-transform duration-300 group-hover:rotate-90"
                    />
                    <span class="text-xs font-black tracking-widest uppercase"
                        >Tambah Vendor</span
                    >
                </Button>
            </div>

            <!-- Search & Filter Bar -->
            <div
                class="mb-8 flex items-center gap-2 rounded-[32px] border border-slate-100 bg-white p-2 shadow-sm"
            >
                <div class="relative flex-1">
                    <Search
                        class="absolute top-1/2 left-4 h-4 w-4 -translate-y-1/2 text-slate-400"
                    />
                    <input
                        v-model="searchInput"
                        type="text"
                        placeholder="Cari berdasarkan nama, kategori, atau kontak..."
                        class="h-12 w-full rounded-2xl border-none bg-transparent pr-4 pl-12 text-sm font-bold text-slate-800 placeholder:text-slate-400 focus:ring-0"
                    />
                </div>
                <div class="mx-2 h-8 w-px bg-slate-100" />
                <Button
                    variant="ghost"
                    class="h-12 w-12 rounded-2xl text-slate-400 transition-all hover:text-[#003628]"
                >
                    <Filter class="h-5 w-5" />
                </Button>
            </div>

            <!-- Vendor Grid -->
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="vendor in vendors.data"
                    :key="vendor.id"
                    class="group relative rounded-[40px] border border-slate-100 bg-white p-8 shadow-xl shadow-[#003628]/5 transition-all hover:-translate-y-1 hover:shadow-2xl hover:shadow-[#003628]/10"
                >
                    <div class="mb-6 flex items-start justify-between">
                        <div
                            class="flex h-16 w-16 items-center justify-center rounded-[24px] border border-slate-100 bg-slate-50 text-[#003628] transition-colors group-hover:bg-[#003628]/5"
                        >
                            <Truck class="h-8 w-8" />
                        </div>
                        <div class="flex items-center gap-1">
                            <Button
                                @click="openEdit(vendor)"
                                variant="ghost"
                                class="h-10 w-10 rounded-xl text-slate-400 hover:bg-blue-50 hover:text-blue-600"
                            >
                                <Pencil class="h-4 w-4" />
                            </Button>
                            <Button
                                @click="deleteConfirmId = vendor.id"
                                variant="ghost"
                                class="h-10 w-10 rounded-xl text-slate-400 hover:bg-rose-50 hover:text-rose-600"
                            >
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>

                    <div class="mb-6">
                        <span
                            class="mb-2 inline-block rounded-lg border border-emerald-100 bg-emerald-50 px-2 py-0.5 text-[9px] font-black tracking-widest text-emerald-600 uppercase"
                        >
                            {{ vendor.category || 'Belum Dikategorikan' }}
                        </span>
                        <h3
                            class="truncate text-xl font-black tracking-tight text-slate-800 uppercase"
                        >
                            {{ vendor.name }}
                        </h3>
                    </div>

                    <div class="space-y-3 border-t border-slate-50 pt-6">
                        <div
                            class="flex items-center gap-3 text-xs font-bold text-slate-500"
                        >
                            <User class="h-3.5 w-3.5 text-slate-300" />
                            {{ vendor.contact_person || '-' }}
                        </div>
                        <div
                            class="flex items-center gap-3 text-xs font-bold text-slate-500"
                        >
                            <Mail class="h-3.5 w-3.5 text-slate-300" />
                            {{ vendor.email || '-' }}
                        </div>
                        <div
                            class="flex items-center gap-3 text-xs font-bold text-slate-500"
                        >
                            <Phone class="h-3.5 w-3.5 text-slate-300" />
                            {{ vendor.phone || '-' }}
                        </div>
                        <div
                            class="mt-2 flex items-start gap-3 text-xs leading-relaxed font-bold text-slate-400 italic"
                        >
                            <MapPin
                                class="h-3.5 w-3.5 shrink-0 text-slate-200"
                            />
                            {{ vendor.address || 'Alamat belum diatur.' }}
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div
                    v-if="vendors.data.length === 0"
                    class="col-span-full flex flex-col items-center justify-center rounded-[40px] border border-dashed border-slate-200 bg-white py-32 text-center"
                >
                    <div
                        class="mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-slate-50 text-slate-200"
                    >
                        <Truck class="h-10 w-10" />
                    </div>
                    <h3
                        class="mb-2 text-xl font-black tracking-tight text-slate-800 uppercase"
                    >
                        Vendor Tidak Ditemukan
                    </h3>
                    <p class="max-w-sm text-sm text-slate-400">
                        Mulai kelola database vendor Anda dengan menekan tombol
                        "Tambah Vendor" di atas.
                    </p>
                </div>
            </div>

            <!-- Pagination (Simplified for now) -->
            <div
                v-if="vendors.meta && vendors.meta.total > 10"
                class="mt-8 flex justify-center"
            >
                <!-- Pagination buttons here if needed -->
            </div>
        </div>

        <!-- Create/Edit Modal -->
        <Dialog :open="showModal" @update:open="showModal = $event">
            <DialogContent
                class="overflow-hidden rounded-[40px] border-none bg-white p-0 shadow-2xl sm:max-w-[600px]"
            >
                <div class="relative bg-[#003628] p-10 text-white">
                    <div
                        class="absolute top-0 right-0 -mt-12 -mr-12 h-48 w-48 rounded-full bg-white/5 blur-3xl"
                    ></div>
                    <h2
                        class="relative z-10 text-3xl font-black tracking-tight uppercase"
                    >
                        {{
                            editingVendor
                                ? 'Perbarui Vendor'
                                : 'Tambah Vendor Baru'
                        }}
                    </h2>
                    <p
                        class="relative z-10 mt-2 text-[10px] font-black tracking-[0.2em] text-emerald-200/60 uppercase"
                    >
                        Data vendor akan tersinkronisasi dengan modul helpdesk
                        dan pengadaan.
                    </p>
                </div>

                <form @submit.prevent="submit" class="space-y-6 p-10">
                    <div class="grid grid-cols-2 gap-6">
                        <div class="col-span-2 space-y-2">
                            <label
                                class="ml-1 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                >Nama Vendor / Perusahaan</label
                            >
                            <input
                                v-model="form.name"
                                type="text"
                                class="app-input-shell h-12 w-full rounded-2xl border-slate-100 bg-slate-50 px-4 text-sm font-bold transition-all focus:border-[#003628]/20 focus:bg-white"
                                placeholder="Contoh: PT. Technology Solusindo"
                                required
                            />
                            <p
                                v-if="form.errors.name"
                                class="text-[10px] font-bold text-rose-500"
                            >
                                {{ form.errors.name }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <label
                                class="ml-1 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                >Kategori</label
                            >
                            <select
                                v-model="form.category"
                                class="app-select-shell h-12 w-full rounded-2xl border-slate-100 bg-slate-50 px-4 text-sm font-bold transition-all focus:border-[#003628]/20 focus:bg-white"
                            >
                                <option value="">-- Pilih Kategori --</option>
                                <option value="Hardware">Hardware</option>
                                <option value="Software">Software</option>
                                <option value="Network">Network</option>
                                <option value="General Support">
                                    General Support
                                </option>
                                <option value="Maintenance">Maintenance</option>
                            </select>
                        </div>

                        <div class="space-y-2">
                            <label
                                class="ml-1 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                >Nama Narahubung</label
                            >
                            <input
                                v-model="form.contact_person"
                                type="text"
                                class="app-input-shell h-12 w-full rounded-2xl border-slate-100 bg-slate-50 px-4 text-sm font-bold transition-all focus:border-[#003628]/20 focus:bg-white"
                                placeholder="Nama PIC..."
                            />
                        </div>

                        <div class="space-y-2">
                            <label
                                class="ml-1 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                >Email Kantor</label
                            >
                            <input
                                v-model="form.email"
                                type="email"
                                class="app-input-shell h-12 w-full rounded-2xl border-slate-100 bg-slate-50 px-4 text-sm font-bold transition-all focus:border-[#003628]/20 focus:bg-white"
                                placeholder="vendor@example.com"
                            />
                        </div>

                        <div class="space-y-2">
                            <label
                                class="ml-1 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                >Nomor Telepon</label
                            >
                            <input
                                v-model="form.phone"
                                type="text"
                                class="app-input-shell h-12 w-full rounded-2xl border-slate-100 bg-slate-50 px-4 text-sm font-bold transition-all focus:border-[#003628]/20 focus:bg-white"
                                placeholder="+62..."
                            />
                        </div>

                        <div class="col-span-2 space-y-2">
                            <label
                                class="ml-1 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                >Alamat Kantor</label
                            >
                            <textarea
                                v-model="form.address"
                                rows="3"
                                class="app-textarea-shell w-full resize-none rounded-2xl border-slate-100 bg-slate-50 p-4 text-sm font-medium transition-all focus:border-[#003628]/20 focus:bg-white"
                                placeholder="Alamat lengkap perusahaan..."
                            ></textarea>
                        </div>
                    </div>

                    <div class="flex gap-4 pt-4">
                        <Button
                            type="button"
                            variant="ghost"
                            class="h-14 flex-1 rounded-2xl text-xs font-black tracking-widest text-slate-400 uppercase"
                            @click="showModal = false"
                            >Batal</Button
                        >
                        <Button
                            type="submit"
                            class="h-14 flex-1 rounded-2xl bg-[#003628] text-xs font-black tracking-[0.2em] text-white uppercase shadow-xl shadow-emerald-900/10"
                            :disabled="form.processing"
                        >
                            {{
                                form.processing
                                    ? 'Menyimpan...'
                                    : editingVendor
                                      ? 'Perbarui'
                                      : 'Simpan Vendor'
                            }}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <AppConfirmDialog
            :open="deleteConfirmId !== null"
            title="Hapus Vendor?"
            description="Data vendor akan dihapus permanen. Hal ini tidak akan menghapus riwayat tiket yang sudah dikaitkan dengan vendor ini."
            confirm-label="Ya, Hapus"
            cancel-label="Batal"
            confirm-variant="danger"
            @close="deleteConfirmId = null"
            @confirm="handleDelete"
        />
    </AppLayout>
</template>

<style scoped>
.app-page-shell {
    font-family:
        'Inter',
        system-ui,
        -apple-system,
        sans-serif;
}
</style>
