<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    Plus,
    Calendar,
    ArrowRight,
    Clock,
    CheckCircle2,
    ClipboardList,
    FileText,
} from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
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
    creator?: { id: number; name: string };
}

const props = defineProps<{ sessions: AuditSession[] }>();

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Stock Opname', href: '/audit' },
];

const showCreateModal = ref(false);
const form = useForm({ name: '', description: '' });

const submit = () => {
    form.post('/audit', {
        onSuccess: () => { showCreateModal.value = false; form.reset(); },
    });
};

const getStatusConfig = (status: string) => {
    switch (status.toLowerCase()) {
        case 'open': return { dot: 'bg-emerald-500', text: 'text-emerald-700', bg: 'bg-emerald-50', label: 'Open' };
        case 'completed': return { dot: 'bg-blue-500', text: 'text-blue-600', bg: 'bg-blue-50', label: 'Selesai' };
        case 'cancelled': return { dot: 'bg-red-400', text: 'text-red-500', bg: 'bg-red-50', label: 'Dibatalkan' };
        default: return { dot: 'bg-gray-300', text: 'text-gray-500', bg: 'bg-gray-50', label: status };
    }
};

const formatDate = (date: string) => new Date(date).toLocaleDateString('id-ID', { year: 'numeric', month: 'short', day: 'numeric' });
</script>

<template>
    <Head title="Stock Opname" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs text-gray-400 mb-0.5">Pengelolaan Inventaris</p>
                    <h1 class="text-xl font-semibold text-gray-800">Stock Opname</h1>
                    <p class="text-sm text-gray-400 mt-0.5">Audit fisik dan verifikasi aset perusahaan secara berkala.</p>
                </div>

                <Dialog :open="showCreateModal" @update:open="showCreateModal = $event">
                    <DialogTrigger as-child>
                        <Button class="flex items-center gap-2 rounded-xl bg-gray-800 px-4 h-9 text-white hover:bg-gray-700 text-sm">
                            <Plus class="h-4 w-4" />
                            Sesi Audit Baru
                        </Button>
                    </DialogTrigger>
                    <DialogContent class="rounded-2xl border-gray-100 sm:max-w-[460px]">
                        <DialogHeader>
                            <DialogTitle class="text-base font-semibold text-gray-800">Mulai Sesi Audit</DialogTitle>
                        </DialogHeader>
                        <form @submit.prevent="submit" class="space-y-4 pt-2">
                            <div class="space-y-1.5">
                                <label class="text-xs text-gray-500">Nama Sesi Audit</label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    class="w-full h-10 px-3 rounded-lg border border-gray-200 bg-white text-sm text-gray-800 placeholder:text-gray-300 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-100 transition-all"
                                    placeholder="Contoh: Audit Q1 2025 - Gedung Utama"
                                    required
                                />
                                <p v-if="form.errors.name" class="text-xs text-red-500">{{ form.errors.name }}</p>
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-xs text-gray-500">Deskripsi / Catatan (Opsional)</label>
                                <textarea
                                    v-model="form.description"
                                    rows="3"
                                    class="w-full resize-none rounded-lg border border-gray-200 bg-white p-3 text-sm text-gray-800 placeholder:text-gray-300 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-100 transition-all"
                                    placeholder="Target audit, cakupan lokasi, atau instruksi khusus..."
                                ></textarea>
                            </div>
                            <div class="flex gap-2 pt-1">
                                <Button type="button" variant="ghost" class="flex-1 h-9 rounded-lg text-gray-500 text-sm" @click="showCreateModal = false">
                                    Batal
                                </Button>
                                <Button type="submit" class="flex-1 h-9 rounded-lg bg-gray-800 text-white hover:bg-gray-700 text-sm" :disabled="form.processing">
                                    {{ form.processing ? 'Memulai...' : 'Buat Sesi' }}
                                </Button>
                            </div>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-3 gap-4">
                <div class="bg-white rounded-xl border border-gray-100 p-4">
                    <p class="text-xs text-gray-400 mb-1">Total Sesi</p>
                    <p class="text-2xl font-semibold text-gray-800">{{ sessions.length }}</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-100 p-4">
                    <p class="text-xs text-gray-400 mb-1">Aktif</p>
                    <p class="text-2xl font-semibold text-emerald-600">{{ sessions.filter(s => s.status === 'Open').length }}</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-100 p-4">
                    <p class="text-xs text-gray-400 mb-1">Terakhir Dibuat</p>
                    <p class="text-sm font-medium text-gray-700">{{ sessions.length > 0 ? formatDate(sessions[0].created_at) : '-' }}</p>
                </div>
            </div>

            <!-- Session List -->
            <div class="space-y-2">
                <div
                    v-for="session in sessions"
                    :key="session.id"
                    class="bg-white rounded-xl border border-gray-100 hover:border-gray-200 transition-colors overflow-hidden"
                >
                    <div class="flex flex-col gap-3 p-4 md:flex-row md:items-center">
                        <!-- Left -->
                        <div class="flex flex-1 items-center gap-4 min-w-0">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-50 border border-gray-100">
                                <span class="text-sm font-semibold text-gray-500">#{{ session.id }}</span>
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2 mb-0.5">
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-md px-2 py-0.5 text-xs"
                                        :class="[getStatusConfig(session.status).bg, getStatusConfig(session.status).text]"
                                    >
                                        <span class="size-1.5 rounded-full" :class="getStatusConfig(session.status).dot"></span>
                                        {{ getStatusConfig(session.status).label }}
                                    </span>
                                    <span class="flex items-center gap-1 text-xs text-gray-400">
                                        <Calendar class="h-3 w-3" />
                                        {{ formatDate(session.created_at) }}
                                    </span>
                                </div>
                                <h3 class="text-sm font-medium text-gray-800 truncate">{{ session.name }}</h3>
                                <p class="text-xs text-gray-400 truncate mt-0.5">{{ session.description || 'Tidak ada deskripsi.' }}</p>
                            </div>
                        </div>

                        <!-- Right -->
                        <div class="flex items-center justify-between gap-6 pl-14 md:pl-0">
                            <div class="flex gap-5">
                                <div>
                                    <p class="text-xs text-gray-400">Aset Terdata</p>
                                    <p class="text-sm font-semibold text-gray-700">{{ session.items_count }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-400">Dibuat oleh</p>
                                    <p class="text-sm font-semibold text-gray-700">{{ session.creator?.name?.split(' ')[0] || 'Admin' }}</p>
                                </div>
                            </div>

                            <Link
                                :href="`/audit/${session.id}`"
                                class="flex h-9 w-9 items-center justify-center rounded-lg bg-gray-50 border border-gray-200 text-gray-500 hover:bg-gray-800 hover:text-white hover:border-gray-800 transition-all shrink-0"
                            >
                                <ArrowRight class="h-4 w-4" />
                            </Link>
                        </div>
                    </div>

                    <!-- Progress strip -->
                    <div class="h-0.5 w-full bg-gray-50">
                        <div
                            class="h-full bg-emerald-400 transition-all duration-1000"
                            :style="{ width: session.status === 'Completed' ? '100%' : '15%' }"
                        ></div>
                    </div>
                </div>

                <!-- Empty State -->
                <div
                    v-if="sessions.length === 0"
                    class="flex flex-col items-center justify-center rounded-xl border border-dashed border-gray-200 bg-white py-20 text-center"
                >
                    <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-gray-50">
                        <ClipboardList class="h-7 w-7 text-gray-300" />
                    </div>
                    <h3 class="text-sm font-medium text-gray-700 mb-1">Belum Ada Sesi Audit</h3>
                    <p class="text-sm text-gray-400 max-w-xs">Mulai audit fisik aset pertama Anda dengan membuat sesi baru.</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
