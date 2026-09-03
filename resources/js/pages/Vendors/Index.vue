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
    User
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
        router.get('/vendors', { search: val }, { preserveState: true, replace: true });
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
            onSuccess: () => deleteConfirmId.value = null
        });
    }
};
</script>

<template>
    <Head title="Master Vendors" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="app-page-shell">
            <!-- Header Section -->
            <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <div class="h-4 w-1 rounded-full bg-[#d99528]" />
                        <span class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">Relationship Management</span>
                    </div>
                    <h1 class="text-3xl font-black text-[#003628] tracking-tight uppercase">Master Vendors</h1>
                    <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mt-1">Kelola data penyedia layanan dan pemasok perangkat IT.</p>
                </div>

                <Button @click="openCreate" class="h-12 px-6 rounded-2xl bg-[#003628] hover:bg-[#003628]/90 text-white shadow-xl shadow-emerald-900/10 flex items-center gap-2 group">
                    <Plus class="w-5 h-5 group-hover:rotate-90 transition-transform duration-300" />
                    <span class="text-xs font-black uppercase tracking-widest">Tambah Vendor</span>
                </Button>
            </div>

            <!-- Search & Filter Bar -->
            <div class="bg-white rounded-[32px] p-2 border border-slate-100 shadow-sm mb-8 flex items-center gap-2">
                <div class="relative flex-1">
                    <Search class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                    <input 
                        v-model="searchInput"
                        type="text" 
                        placeholder="Cari berdasarkan nama, kategori, atau kontak..." 
                        class="w-full h-12 pl-12 pr-4 rounded-2xl border-none bg-transparent text-sm font-bold text-slate-800 placeholder:text-slate-400 focus:ring-0"
                    />
                </div>
                <div class="w-px h-8 bg-slate-100 mx-2" />
                <Button variant="ghost" class="h-12 w-12 rounded-2xl text-slate-400 hover:text-[#003628] transition-all">
                    <Filter class="w-5 h-5" />
                </Button>
            </div>

            <!-- Vendor Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div v-for="vendor in vendors.data" :key="vendor.id" class="bg-white rounded-[40px] border border-slate-100 shadow-xl shadow-[#003628]/5 p-8 relative group transition-all hover:shadow-2xl hover:shadow-[#003628]/10 hover:-translate-y-1">
                    <div class="flex items-start justify-between mb-6">
                        <div class="w-16 h-16 rounded-[24px] bg-slate-50 flex items-center justify-center text-[#003628] group-hover:bg-[#003628]/5 transition-colors border border-slate-100">
                            <Truck class="w-8 h-8" />
                        </div>
                        <div class="flex items-center gap-1">
                            <Button @click="openEdit(vendor)" variant="ghost" class="h-10 w-10 rounded-xl text-slate-400 hover:text-blue-600 hover:bg-blue-50">
                                <Pencil class="w-4 h-4" />
                            </Button>
                            <Button @click="deleteConfirmId = vendor.id" variant="ghost" class="h-10 w-10 rounded-xl text-slate-400 hover:text-rose-600 hover:bg-rose-50">
                                <Trash2 class="w-4 h-4" />
                            </Button>
                        </div>
                    </div>

                    <div class="mb-6">
                        <span class="text-[9px] font-black uppercase tracking-widest px-2 py-0.5 rounded-lg bg-emerald-50 text-emerald-600 border border-emerald-100 mb-2 inline-block">
                            {{ vendor.category || 'Uncategorized' }}
                        </span>
                        <h3 class="text-xl font-black text-slate-800 uppercase tracking-tight truncate">{{ vendor.name }}</h3>
                    </div>

                    <div class="space-y-3 pt-6 border-t border-slate-50">
                        <div class="flex items-center gap-3 text-xs font-bold text-slate-500">
                            <User class="w-3.5 h-3.5 text-slate-300" /> {{ vendor.contact_person || '-' }}
                        </div>
                        <div class="flex items-center gap-3 text-xs font-bold text-slate-500">
                            <Mail class="w-3.5 h-3.5 text-slate-300" /> {{ vendor.email || '-' }}
                        </div>
                        <div class="flex items-center gap-3 text-xs font-bold text-slate-500">
                            <Phone class="w-3.5 h-3.5 text-slate-300" /> {{ vendor.phone || '-' }}
                        </div>
                        <div class="flex items-start gap-3 text-xs font-bold text-slate-400 mt-2 italic leading-relaxed">
                            <MapPin class="w-3.5 h-3.5 text-slate-200 shrink-0" /> {{ vendor.address || 'Alamat belum diatur.' }}
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="vendors.data.length === 0" class="col-span-full py-32 flex flex-col items-center justify-center text-center bg-white rounded-[40px] border border-dashed border-slate-200">
                    <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center text-slate-200 mb-6">
                        <Truck class="w-10 h-10" />
                    </div>
                    <h3 class="text-xl font-black text-slate-800 uppercase tracking-tight mb-2">Vendor Tidak Ditemukan</h3>
                    <p class="text-sm text-slate-400 max-w-sm">Mulai kelola database vendor Anda dengan menekan tombol "Tambah Vendor" di atas.</p>
                </div>
            </div>

            <!-- Pagination (Simplified for now) -->
             <div v-if="vendors.meta && vendors.meta.total > 10" class="mt-8 flex justify-center">
                <!-- Pagination buttons here if needed -->
             </div>
        </div>

        <!-- Create/Edit Modal -->
        <Dialog :open="showModal" @update:open="showModal = $event">
            <DialogContent class="sm:max-w-[600px] rounded-[40px] border-none shadow-2xl p-0 overflow-hidden bg-white">
                <div class="bg-[#003628] p-10 text-white relative">
                    <div class="absolute top-0 right-0 -mr-12 -mt-12 w-48 h-48 bg-white/5 rounded-full blur-3xl"></div>
                    <h2 class="text-3xl font-black uppercase tracking-tight relative z-10">
                        {{ editingVendor ? 'Perbarui Vendor' : 'Tambah Vendor Baru' }}
                    </h2>
                    <p class="text-[10px] font-black text-emerald-200/60 uppercase tracking-[0.2em] mt-2 relative z-10">
                        Data vendor akan tersinkronisasi dengan modul helpdesk dan pengadaan.
                    </p>
                </div>

                <form @submit.prevent="submit" class="p-10 space-y-6">
                    <div class="grid grid-cols-2 gap-6">
                        <div class="col-span-2 space-y-2">
                            <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Nama Vendor / Perusahaan</label>
                            <input v-model="form.name" type="text" class="app-input-shell h-12 w-full px-4 rounded-2xl border-slate-100 bg-slate-50 focus:bg-white focus:border-[#003628]/20 transition-all text-sm font-bold" placeholder="Contoh: PT. Technology Solusindo" required />
                            <p v-if="form.errors.name" class="text-[10px] text-rose-500 font-bold">{{ form.errors.name }}</p>
                        </div>

                        <div class="space-y-2">
                            <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Kategori</label>
                            <select v-model="form.category" class="app-select-shell h-12 w-full px-4 rounded-2xl border-slate-100 bg-slate-50 focus:bg-white focus:border-[#003628]/20 transition-all text-sm font-bold">
                                <option value="">-- Pilih Kategori --</option>
                                <option value="Hardware">Hardware</option>
                                <option value="Software">Software</option>
                                <option value="Network">Network</option>
                                <option value="General Support">General Support</option>
                                <option value="Maintenance">Maintenance</option>
                            </select>
                        </div>

                        <div class="space-y-2">
                            <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Contact Person</label>
                            <input v-model="form.contact_person" type="text" class="app-input-shell h-12 w-full px-4 rounded-2xl border-slate-100 bg-slate-50 focus:bg-white focus:border-[#003628]/20 transition-all text-sm font-bold" placeholder="Nama PIC..." />
                        </div>

                        <div class="space-y-2">
                            <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Email Kantor</label>
                            <input v-model="form.email" type="email" class="app-input-shell h-12 w-full px-4 rounded-2xl border-slate-100 bg-slate-50 focus:bg-white focus:border-[#003628]/20 transition-all text-sm font-bold" placeholder="vendor@example.com" />
                        </div>

                        <div class="space-y-2">
                            <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Nomor Telepon</label>
                            <input v-model="form.phone" type="text" class="app-input-shell h-12 w-full px-4 rounded-2xl border-slate-100 bg-slate-50 focus:bg-white focus:border-[#003628]/20 transition-all text-sm font-bold" placeholder="+62..." />
                        </div>

                        <div class="col-span-2 space-y-2">
                            <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Alamat Kantor</label>
                            <textarea v-model="form.address" rows="3" class="app-textarea-shell w-full p-4 rounded-2xl border-slate-100 bg-slate-50 focus:bg-white focus:border-[#003628]/20 transition-all text-sm font-medium resize-none" placeholder="Alamat lengkap perusahaan..."></textarea>
                        </div>
                    </div>

                    <div class="flex gap-4 pt-4">
                        <Button type="button" variant="ghost" class="flex-1 h-14 rounded-2xl text-xs font-black uppercase tracking-widest text-slate-400" @click="showModal = false">Batal</Button>
                        <Button type="submit" class="flex-1 h-14 rounded-2xl bg-[#003628] text-white text-xs font-black uppercase tracking-[0.2em] shadow-xl shadow-emerald-900/10" :disabled="form.processing">
                            {{ form.processing ? 'Menyimpan...' : (editingVendor ? 'Perbarui' : 'Simpan Vendor') }}
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
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
}
</style>
