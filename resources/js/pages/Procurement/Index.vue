<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { 
    Plus, 
    Search, 
    ShoppingCart, 
    Calendar, 
    DollarSign, 
    Tag,
    Clock,
    CheckCircle2,
    XCircle,
    MoreHorizontal,
    FileText,
    TrendingUp
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps<{
    procurements: any;
    vendors: any[];
    filters: any;
}>();

const showAddModal = ref(false);
const editingProcurement = ref<any>(null);

const form = useForm({
    title: '',
    request_number: '',
    requester_name: '',
    department: '',
    estimated_cost: 0,
    actual_cost: 0,
    status: 'Pending',
    request_date: new Date().toISOString().split('T')[0],
    purchase_date: '',
    po_number: '',
    description: '',
    vendor_id: '',
});

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Rekap Pengadaan', href: '/procurement' },
];

const openAddModal = () => {
    editingProcurement.value = null;
    form.reset();
    form.request_number = 'PR-' + Math.floor(Math.random() * 100000);
    showAddModal.value = true;
};

const openEditModal = (proc: any) => {
    editingProcurement.value = proc;
    form.title = proc.title;
    form.request_number = proc.request_number;
    form.requester_name = proc.requester_name;
    form.department = proc.department;
    form.estimated_cost = proc.estimated_cost;
    form.actual_cost = proc.actual_cost;
    form.status = proc.status;
    form.request_date = proc.request_date;
    form.purchase_date = proc.purchase_date;
    form.po_number = proc.po_number;
    form.description = proc.description;
    form.vendor_id = proc.vendor_id || '';
    showAddModal.value = true;
};

const submit = () => {
    if (editingProcurement.value) {
        form.put(`/procurement/${editingProcurement.value.id}`, {
            onSuccess: () => {
                showAddModal.value = false;
                form.reset();
            }
        });
    } else {
        form.post('/procurement', {
            onSuccess: () => {
                showAddModal.value = false;
                form.reset();
            }
        });
    }
};

const getStatusBadge = (status: string) => {
    switch (status) {
        case 'Approved': return 'bg-sky-50 text-sky-700 border-sky-100';
        case 'Purchased': return 'bg-emerald-50 text-emerald-700 border-emerald-100';
        case 'Cancelled': return 'bg-rose-50 text-rose-700 border-rose-100';
        default: return 'bg-slate-50 text-slate-700 border-slate-100';
    }
};

const formatCurrency = (val: number) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(val);
};

const totalInvestment = computed(() => {
    return props.procurements.data.reduce((acc: number, p: any) => acc + Number(p.estimated_cost), 0);
});
</script>

<template>
    <Head title="Rekap Pengadaan" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-8 space-y-8 max-w-[1600px] mx-auto">
            <!-- Header Section -->
            <header class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                <div>
                    <h1 class="text-3xl font-black text-slate-900 tracking-tight flex items-center gap-3">
                        <ShoppingCart class="size-8 text-[#003628]" />
                        Rekap <span class="text-primary italic">Pengadaan IT</span>
                    </h1>
                    <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mt-1">History and tracking of IT purchase requests</p>
                </div>
                
                <Button @click="openAddModal" class="h-12 px-8 rounded-2xl bg-[#003628] text-white font-black uppercase tracking-widest shadow-xl shadow-emerald-950/20">
                    <Plus class="size-4 mr-2" /> Tambah Rekap
                </Button>
            </header>

            <!-- Stats Row -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="bg-white p-6 rounded-[32px] border border-slate-100 shadow-sm flex items-center gap-5">
                    <div class="size-14 rounded-2xl bg-slate-50 flex items-center justify-center text-slate-400">
                        <FileText class="size-6" />
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Total Requests</p>
                        <h3 class="text-2xl font-black text-slate-800">{{ procurements.total }}</h3>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-[32px] border border-slate-100 shadow-sm flex items-center gap-5">
                    <div class="size-14 rounded-2xl bg-emerald-50 flex items-center justify-center text-emerald-500">
                        <CheckCircle2 class="size-6" />
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Purchased</p>
                        <h3 class="text-2xl font-black text-slate-800">{{ procurements.data.filter((p: any) => p.status === 'Purchased').length }}</h3>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-[32px] border border-slate-100 shadow-sm flex items-center gap-5">
                    <div class="size-14 rounded-2xl bg-amber-50 flex items-center justify-center text-amber-500">
                        <Clock class="size-6" />
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Pending</p>
                        <h3 class="text-2xl font-black text-slate-800">{{ procurements.data.filter((p: any) => p.status === 'Pending').length }}</h3>
                    </div>
                </div>
                <div class="bg-[#003628] p-6 rounded-[32px] shadow-xl shadow-emerald-950/20 flex items-center gap-5">
                    <div class="size-14 rounded-2xl bg-white/10 flex items-center justify-center text-white/40">
                        <TrendingUp class="size-6" />
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-emerald-100/50 uppercase tracking-widest">Est. Investment</p>
                        <h3 class="text-xl font-black text-white">{{ formatCurrency(totalInvestment) }}</h3>
                    </div>
                </div>
            </div>

            <!-- Table Section -->
            <div class="bg-white rounded-[40px] border border-slate-100 shadow-xl shadow-[#003628]/5 overflow-hidden">
                <div class="p-8 border-b border-slate-50 flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <h3 class="text-lg font-black text-slate-800 uppercase tracking-tight">Purchase History</h3>
                    <div class="relative">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-slate-300" />
                        <Input placeholder="Search requests..." class="pl-10 h-10 w-64 rounded-xl border-slate-100 bg-slate-50 text-xs" />
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="bg-slate-50/50 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">
                                <th class="px-8 py-5">Request Info</th>
                                <th class="px-8 py-5">Status</th>
                                <th class="px-8 py-5 text-right">Estimated Cost</th>
                                <th class="px-8 py-5 text-right">Actual Cost</th>
                                <th class="px-8 py-5">Dates</th>
                                <th class="px-8 py-5"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <tr v-for="proc in procurements.data" :key="proc.id" class="group hover:bg-slate-50/50 transition-all">
                                <td class="px-8 py-6">
                                    <p class="text-sm font-black text-slate-800 uppercase tracking-tight">{{ proc.title }}</p>
                                    <p class="text-[10px] font-bold text-slate-400 mt-1 uppercase tracking-widest">
                                        {{ proc.request_number }} • {{ proc.requester_name }} ({{ proc.department }})
                                    </p>
                                </td>
                                <td class="px-8 py-6">
                                    <span class="px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest border" :class="getStatusBadge(proc.status)">
                                        {{ proc.status }}
                                    </span>
                                </td>
                                <td class="px-8 py-6 text-right">
                                    <p class="text-sm font-black text-slate-600">{{ formatCurrency(proc.estimated_cost) }}</p>
                                </td>
                                <td class="px-8 py-6 text-right">
                                    <p class="text-sm font-black text-[#003628]">{{ formatCurrency(proc.actual_cost || 0) }}</p>
                                </td>
                                <td class="px-8 py-6">
                                    <div class="flex flex-col gap-1 text-[10px] font-bold text-slate-400">
                                        <div class="flex items-center gap-2"><Calendar class="size-3" /> Req: {{ proc.request_date }}</div>
                                        <div v-if="proc.purchase_date" class="flex items-center gap-2 text-emerald-600"><Calendar class="size-3" /> Pur: {{ proc.purchase_date }}</div>
                                    </div>
                                </td>
                                <td class="px-8 py-6 text-right">
                                    <button @click="openEditModal(proc)" class="p-2 rounded-xl hover:bg-slate-100 text-slate-400 hover:text-[#003628] transition-all">
                                        <MoreHorizontal class="size-5" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Add/Edit Modal (Simple) -->
        <div v-if="showAddModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-6">
            <div class="bg-white rounded-[40px] w-full max-w-2xl shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-300">
                <div class="p-8 border-b border-slate-50 bg-[#003628] text-white">
                    <h3 class="text-2xl font-black tracking-tight uppercase">{{ editingProcurement ? 'Edit Rekap' : 'Tambah Rekap Baru' }}</h3>
                    <p class="text-emerald-100/50 text-xs font-bold uppercase tracking-widest mt-1">Isi detail pengajuan pengadaan barang IT</p>
                </div>
                
                <form @submit.prevent="submit" class="p-10 space-y-8">
                    <div class="grid grid-cols-2 gap-8">
                        <div class="space-y-2">
                            <Label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Judul Pengajuan</Label>
                            <Input v-model="form.title" class="h-12 rounded-2xl bg-slate-50 border-none focus:ring-4 focus:ring-emerald-500/10" required />
                        </div>
                        <div class="space-y-2">
                            <Label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Nomor Pengajuan</Label>
                            <Input v-model="form.request_number" class="h-12 rounded-2xl bg-slate-50 border-none" required />
                        </div>
                        <div class="space-y-2">
                            <Label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Nama Pemohon</Label>
                            <Input v-model="form.requester_name" class="h-12 rounded-2xl bg-slate-50 border-none" required />
                        </div>
                        <div class="space-y-2">
                            <Label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Departemen</Label>
                            <Input v-model="form.department" class="h-12 rounded-2xl bg-slate-50 border-none" required />
                        </div>
                        <div class="space-y-2">
                            <Label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Estimasi Biaya</Label>
                            <Input v-model="form.estimated_cost" type="number" class="h-12 rounded-2xl bg-slate-50 border-none" />
                        </div>
                        <div class="space-y-2">
                            <Label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Vendor</Label>
                            <select v-model="form.vendor_id" class="w-full h-12 rounded-2xl bg-slate-50 border-none px-4 text-sm outline-none">
                                <option value="">Pilih Vendor</option>
                                <option v-for="vendor in vendors" :key="vendor.id" :value="vendor.id">{{ vendor.name }}</option>
                            </select>
                        </div>
                        <div class="space-y-2">
                            <Label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Status</Label>
                            <select v-model="form.status" class="w-full h-12 rounded-2xl bg-slate-50 border-none px-4 text-sm outline-none">
                                <option value="Pending">Pending</option>
                                <option value="Approved">Approved</option>
                                <option value="Purchased">Purchased</option>
                                <option value="Cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="space-y-2">
                            <Label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Tanggal Request</Label>
                            <Input v-model="form.request_date" type="date" class="h-12 rounded-2xl bg-slate-50 border-none" />
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-4 pt-6 border-t border-slate-50">
                        <button type="button" @click="showAddModal = false" class="px-8 h-12 rounded-2xl text-[11px] font-black uppercase tracking-widest text-slate-400 hover:text-slate-600 transition-all">Batal</button>
                        <Button type="submit" class="px-10 h-12 rounded-2xl bg-[#003628] text-white font-black uppercase tracking-widest shadow-xl shadow-emerald-950/20">
                            Simpan Rekap
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
