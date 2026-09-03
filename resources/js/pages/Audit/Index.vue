<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { 
    Plus, 
    Search, 
    Calendar, 
    User, 
    ArrowRight, 
    Clock, 
    CheckCircle2, 
    AlertCircle,
    ClipboardList,
    MoreHorizontal,
    FileText
} from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';

interface AuditSession {
    id: number;
    name: string;
    description: string | null;
    status: string;
    created_by: number;
    completed_at: string | null;
    created_at: string;
    items_count: number;
    creator?: {
        id: number;
        name: string;
    };
}

const props = defineProps<{
    sessions: AuditSession[];
}>();

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Stock Opname', href: '/audit' },
];

const showCreateModal = ref(false);
const form = useForm({
    name: '',
    description: '',
});

const submit = () => {
    form.post('/audit', {
        onSuccess: () => {
            showCreateModal.value = false;
            form.reset();
        },
    });
};

const getStatusBadge = (status: string) => {
    switch (status.toLowerCase()) {
        case 'open': return 'bg-emerald-100 text-emerald-700 border-emerald-200';
        case 'completed': return 'bg-blue-100 text-blue-700 border-blue-200';
        case 'cancelled': return 'bg-rose-100 text-rose-700 border-rose-200';
        default: return 'bg-slate-100 text-slate-700 border-slate-200';
    }
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};
</script>

<template>
    <Head title="Stock Opname" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="app-page-shell">
            <!-- Header Section -->
            <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <div class="h-4 w-1 rounded-full bg-[#d99528]" />
                        <span class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">Inventory Management</span>
                    </div>
                    <h1 class="text-3xl font-black text-[#003628] tracking-tight uppercase">Stock Opname</h1>
                    <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mt-1">Audit fisik dan verifikasi aset perusahaan secara berkala.</p>
                </div>

                <Dialog :open="showCreateModal" @update:open="showCreateModal = $event">
                    <DialogTrigger as-child>
                        <Button class="h-12 px-6 rounded-2xl bg-[#003628] hover:bg-[#003628]/90 text-white shadow-xl shadow-emerald-900/10 flex items-center gap-2 group">
                            <Plus class="w-5 h-5 group-hover:rotate-90 transition-transform duration-300" />
                            <span class="text-xs font-black uppercase tracking-widest">Sesi Audit Baru</span>
                        </Button>
                    </DialogTrigger>
                    <DialogContent class="sm:max-w-[500px] rounded-[32px] border-none shadow-2xl p-0 overflow-hidden">
                        <div class="bg-[#003628] p-8 text-white relative">
                            <div class="absolute top-0 right-0 -mr-10 -mt-10 w-40 h-40 bg-white/5 rounded-full blur-2xl"></div>
                            <h2 class="text-2xl font-black uppercase tracking-tight relative z-10">Mulai Sesi Audit</h2>
                            <p class="text-xs font-bold text-emerald-200/60 uppercase tracking-widest mt-1 relative z-10">Tentukan nama dan deskripsi untuk sesi stock opname ini.</p>
                        </div>
                        <form @submit.prevent="submit" class="p-8 space-y-6">
                            <div class="space-y-2">
                                <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Nama Sesi Audit</label>
                                <input v-model="form.name" type="text" class="app-input-shell h-12 w-full px-4 rounded-xl border-slate-100 bg-slate-50 focus:bg-white focus:border-[#003628]/20 transition-all text-sm font-bold" placeholder="Contoh: Audit Q1 2024 - Gudang Utama" required />
                                <p v-if="form.errors.name" class="text-[10px] text-rose-500 font-bold ml-1">{{ form.errors.name }}</p>
                            </div>
                            <div class="space-y-2">
                                <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Deskripsi / Catatan (Opsional)</label>
                                <textarea v-model="form.description" rows="3" class="app-textarea-shell w-full p-4 rounded-xl border-slate-100 bg-slate-50 focus:bg-white focus:border-[#003628]/20 transition-all text-sm font-medium resize-none" placeholder="Target audit, cakupan lokasi, atau instruksi khusus..."></textarea>
                            </div>
                            <div class="flex gap-3 pt-2">
                                <Button type="button" variant="ghost" class="flex-1 h-12 rounded-xl text-xs font-bold uppercase tracking-widest text-slate-400" @click="showCreateModal = false">Batal</Button>
                                <Button type="submit" class="flex-1 h-12 rounded-xl bg-[#003628] text-white text-xs font-black uppercase tracking-widest shadow-lg shadow-emerald-900/10" :disabled="form.processing">
                                    {{ form.processing ? 'Memulai...' : 'Buat Sesi' }}
                                </Button>
                            </div>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>

            <!-- Dashboard Stats -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm flex items-center gap-5 group hover:border-emerald-200 transition-all">
                    <div class="w-14 h-14 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center group-hover:scale-110 transition-transform">
                        <ClipboardList class="w-7 h-7" />
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Total Sesi</p>
                        <h3 class="text-2xl font-black text-slate-800">{{ sessions.length }}</h3>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm flex items-center gap-5 group hover:border-blue-200 transition-all">
                    <div class="w-14 h-14 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center group-hover:scale-110 transition-transform">
                        <CheckCircle2 class="w-7 h-7" />
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Aktif Saat Ini</p>
                        <h3 class="text-2xl font-black text-slate-800">{{ sessions.filter(s => s.status === 'Open').length }}</h3>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm flex items-center gap-5 group hover:border-[#d99528]/20 transition-all">
                    <div class="w-14 h-14 rounded-2xl bg-amber-50 text-[#d99528] flex items-center justify-center group-hover:scale-110 transition-transform">
                        <Clock class="w-7 h-7" />
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Terakhir Diperbarui</p>
                        <h3 class="text-sm font-black text-slate-800 uppercase">{{ sessions.length > 0 ? formatDate(sessions[0].created_at) : '-' }}</h3>
                    </div>
                </div>
            </div>

            <!-- List Sesi Audit -->
            <div class="grid grid-cols-1 gap-4">
                <div v-for="session in sessions" :key="session.id" class="bg-white p-2 rounded-[32px] border border-slate-100 shadow-sm hover:shadow-xl hover:shadow-[#003628]/5 transition-all group overflow-hidden relative">
                    <div class="flex flex-col md:flex-row md:items-center gap-4 p-4">
                        <!-- Left: Status & Info -->
                        <div class="flex items-center gap-4 flex-1">
                            <div class="w-16 h-16 rounded-2xl bg-slate-50 flex flex-col items-center justify-center border border-slate-100 shrink-0">
                                <span class="text-[10px] font-black text-slate-400 uppercase tracking-tighter mb-0.5">Audit</span>
                                <span class="text-xl font-black text-[#003628]">#{{ session.id }}</span>
                            </div>
                            
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2 mb-1.5">
                                    <span class="px-2 py-0.5 rounded-lg border text-[9px] font-black uppercase tracking-widest" :class="getStatusBadge(session.status)">{{ session.status }}</span>
                                    <span class="text-[10px] font-bold text-slate-400 flex items-center gap-1">
                                        <Calendar class="w-3 h-3" /> {{ formatDate(session.created_at) }}
                                    </span>
                                </div>
                                <h3 class="text-lg font-black text-slate-800 uppercase tracking-tight mb-1 truncate">{{ session.name }}</h3>
                                <p class="text-xs text-slate-400 font-medium truncate max-w-xl">{{ session.description || 'Tidak ada deskripsi.' }}</p>
                            </div>
                        </div>

                        <!-- Right: Stats & Action -->
                        <div class="flex items-center justify-between md:justify-end gap-8 px-4 md:px-0">
                            <div class="flex gap-6">
                                <div class="text-center">
                                    <p class="text-[9px] font-black text-slate-300 uppercase tracking-widest mb-1">Aset Terdata</p>
                                    <p class="text-sm font-black text-slate-700">{{ session.items_count }}</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-[9px] font-black text-slate-300 uppercase tracking-widest mb-1">Oleh</p>
                                    <p class="text-sm font-black text-slate-700 truncate max-w-[80px]">{{ session.creator?.name.split(' ')[0] }}</p>
                                </div>
                            </div>

                            <Link :href="route('audit.show', session.id)" class="h-14 w-14 rounded-2xl bg-slate-50 flex items-center justify-center text-[#003628] hover:bg-[#003628] hover:text-white transition-all shadow-sm">
                                <ArrowRight class="w-6 h-6" />
                            </Link>
                        </div>
                    </div>
                    
                    <!-- Progress Strip (Visual Only for now) -->
                    <div class="absolute bottom-0 left-0 h-1 bg-[#003628]/10 w-full">
                        <div class="h-full bg-[#003628]" :style="{ width: session.status === 'Completed' ? '100%' : '15%' }"></div>
                    </div>
                </div>

                <div v-if="sessions.length === 0" class="py-32 flex flex-col items-center justify-center text-center bg-white rounded-[40px] border border-dashed border-slate-200">
                    <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center text-slate-200 mb-6">
                        <ClipboardList class="w-10 h-10" />
                    </div>
                    <h3 class="text-2xl font-black text-slate-800 uppercase tracking-tight mb-3">Belum Ada Sesi Audit</h3>
                    <p class="text-sm text-slate-500 max-w-sm mb-8">Mulai audit fisik aset pertama Anda dengan menekan tombol "Sesi Audit Baru" untuk membuat sesi audit baru.</p>
                    <div class="inline-block px-4 py-2 rounded-full bg-emerald-50 border border-emerald-200 text-[10px] font-black text-emerald-700 uppercase tracking-widest">
                        💡 Klik tombol "Sesi Audit Baru" di atas untuk memulai
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.app-page-shell {
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
}
</style>
