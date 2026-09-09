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
    FileText,
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
        case 'open':
            return 'bg-emerald-100 text-emerald-700 border-emerald-200';
        case 'completed':
            return 'bg-blue-100 text-blue-700 border-blue-200';
        case 'cancelled':
            return 'bg-rose-100 text-rose-700 border-rose-200';
        default:
            return 'bg-slate-100 text-slate-700 border-slate-200';
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
            <div
                class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <div class="mb-1 flex items-center gap-2">
                        <div class="h-4 w-1 rounded-full bg-[#d99528]" />
                        <span
                            class="text-[10px] font-black tracking-[0.2em] text-slate-400 uppercase"
                            >Pengelolaan Inventaris</span
                        >
                    </div>
                    <h1
                        class="text-3xl font-black tracking-tight text-[#003628] uppercase"
                    >
                        Stock Opname
                    </h1>
                    <p
                        class="mt-1 text-[11px] font-bold tracking-widest text-slate-400 uppercase"
                    >
                        Audit fisik dan verifikasi aset perusahaan secara
                        berkala.
                    </p>
                </div>

                <Dialog
                    :open="showCreateModal"
                    @update:open="showCreateModal = $event"
                >
                    <DialogTrigger as-child>
                        <Button
                            class="group flex h-12 items-center gap-2 rounded-2xl bg-[#003628] px-6 text-white shadow-xl shadow-emerald-900/10 hover:bg-[#003628]/90"
                        >
                            <Plus
                                class="h-5 w-5 transition-transform duration-300 group-hover:rotate-90"
                            />
                            <span
                                class="text-xs font-black tracking-widest uppercase"
                                >Sesi Audit Baru</span
                            >
                        </Button>
                    </DialogTrigger>
                    <DialogContent
                        class="overflow-hidden rounded-[32px] border-none p-0 shadow-2xl sm:max-w-[500px]"
                    >
                        <div class="relative bg-[#003628] p-8 text-white">
                            <div
                                class="absolute top-0 right-0 -mt-10 -mr-10 h-40 w-40 rounded-full bg-white/5 blur-2xl"
                            ></div>
                            <h2
                                class="relative z-10 text-2xl font-black tracking-tight uppercase"
                            >
                                Mulai Sesi Audit
                            </h2>
                            <p
                                class="relative z-10 mt-1 text-xs font-bold tracking-widest text-emerald-200/60 uppercase"
                            >
                                Tentukan nama dan deskripsi untuk sesi stock
                                opname ini.
                            </p>
                        </div>
                        <form @submit.prevent="submit" class="space-y-6 p-8">
                            <div class="space-y-2">
                                <label
                                    class="ml-1 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                    >Nama Sesi Audit</label
                                >
                                <input
                                    v-model="form.name"
                                    type="text"
                                    class="app-input-shell h-12 w-full rounded-xl border-slate-100 bg-slate-50 px-4 text-sm font-bold transition-all focus:border-[#003628]/20 focus:bg-white"
                                    placeholder="Contoh: Audit Q1 2024 - Gudang Utama"
                                    required
                                />
                                <p
                                    v-if="form.errors.name"
                                    class="ml-1 text-[10px] font-bold text-rose-500"
                                >
                                    {{ form.errors.name }}
                                </p>
                            </div>
                            <div class="space-y-2">
                                <label
                                    class="ml-1 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                    >Deskripsi / Catatan (Opsional)</label
                                >
                                <textarea
                                    v-model="form.description"
                                    rows="3"
                                    class="app-textarea-shell w-full resize-none rounded-xl border-slate-100 bg-slate-50 p-4 text-sm font-medium transition-all focus:border-[#003628]/20 focus:bg-white"
                                    placeholder="Target audit, cakupan lokasi, atau instruksi khusus..."
                                ></textarea>
                            </div>
                            <div class="flex gap-3 pt-2">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    class="h-12 flex-1 rounded-xl text-xs font-bold tracking-widest text-slate-400 uppercase"
                                    @click="showCreateModal = false"
                                    >Batal</Button
                                >
                                <Button
                                    type="submit"
                                    class="h-12 flex-1 rounded-xl bg-[#003628] text-xs font-black tracking-widest text-white uppercase shadow-lg shadow-emerald-900/10"
                                    :disabled="form.processing"
                                >
                                    {{
                                        form.processing
                                            ? 'Memulai...'
                                            : 'Buat Sesi'
                                    }}
                                </Button>
                            </div>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>

            <!-- Dashboard Stats -->
            <div class="mb-8 grid grid-cols-1 gap-6 md:grid-cols-3">
                <div
                    class="group flex items-center gap-5 rounded-3xl border border-slate-100 bg-white p-6 shadow-sm transition-all hover:border-emerald-200"
                >
                    <div
                        class="flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600 transition-transform group-hover:scale-110"
                    >
                        <ClipboardList class="h-7 w-7" />
                    </div>
                    <div>
                        <p
                            class="mb-1 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                        >
                            Total Sesi
                        </p>
                        <h3 class="text-2xl font-black text-slate-800">
                            {{ sessions.length }}
                        </h3>
                    </div>
                </div>
                <div
                    class="group flex items-center gap-5 rounded-3xl border border-slate-100 bg-white p-6 shadow-sm transition-all hover:border-blue-200"
                >
                    <div
                        class="flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-50 text-blue-600 transition-transform group-hover:scale-110"
                    >
                        <CheckCircle2 class="h-7 w-7" />
                    </div>
                    <div>
                        <p
                            class="mb-1 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                        >
                            Aktif Saat Ini
                        </p>
                        <h3 class="text-2xl font-black text-slate-800">
                            {{
                                sessions.filter((s) => s.status === 'Open')
                                    .length
                            }}
                        </h3>
                    </div>
                </div>
                <div
                    class="group flex items-center gap-5 rounded-3xl border border-slate-100 bg-white p-6 shadow-sm transition-all hover:border-[#d99528]/20"
                >
                    <div
                        class="flex h-14 w-14 items-center justify-center rounded-2xl bg-amber-50 text-[#d99528] transition-transform group-hover:scale-110"
                    >
                        <Clock class="h-7 w-7" />
                    </div>
                    <div>
                        <p
                            class="mb-1 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                        >
                            Terakhir Diperbarui
                        </p>
                        <h3 class="text-sm font-black text-slate-800 uppercase">
                            {{
                                sessions.length > 0
                                    ? formatDate(sessions[0].created_at)
                                    : '-'
                            }}
                        </h3>
                    </div>
                </div>
            </div>

            <!-- List Sesi Audit -->
            <div class="grid grid-cols-1 gap-4">
                <div
                    v-for="session in sessions"
                    :key="session.id"
                    class="group relative overflow-hidden rounded-[32px] border border-slate-100 bg-white p-2 shadow-sm transition-all hover:shadow-xl hover:shadow-[#003628]/5"
                >
                    <div
                        class="flex flex-col gap-4 p-4 md:flex-row md:items-center"
                    >
                        <!-- Left: Status & Info -->
                        <div class="flex flex-1 items-center gap-4">
                            <div
                                class="flex h-16 w-16 shrink-0 flex-col items-center justify-center rounded-2xl border border-slate-100 bg-slate-50"
                            >
                                <span
                                    class="mb-0.5 text-[10px] font-black tracking-tighter text-slate-400 uppercase"
                                    >Audit</span
                                >
                                <span class="text-xl font-black text-[#003628]"
                                    >#{{ session.id }}</span
                                >
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="mb-1.5 flex items-center gap-2">
                                    <span
                                        class="rounded-lg border px-2 py-0.5 text-[9px] font-black tracking-widest uppercase"
                                        :class="getStatusBadge(session.status)"
                                        >{{ session.status }}</span
                                    >
                                    <span
                                        class="flex items-center gap-1 text-[10px] font-bold text-slate-400"
                                    >
                                        <Calendar class="h-3 w-3" />
                                        {{ formatDate(session.created_at) }}
                                    </span>
                                </div>
                                <h3
                                    class="mb-1 truncate text-lg font-black tracking-tight text-slate-800 uppercase"
                                >
                                    {{ session.name }}
                                </h3>
                                <p
                                    class="max-w-xl truncate text-xs font-medium text-slate-400"
                                >
                                    {{
                                        session.description ||
                                        'Tidak ada deskripsi.'
                                    }}
                                </p>
                            </div>
                        </div>

                        <!-- Right: Stats & Action -->
                        <div
                            class="flex items-center justify-between gap-8 px-4 md:justify-end md:px-0"
                        >
                            <div class="flex gap-6">
                                <div class="text-center">
                                    <p
                                        class="mb-1 text-[9px] font-black tracking-widest text-slate-300 uppercase"
                                    >
                                        Aset Terdata
                                    </p>
                                    <p
                                        class="text-sm font-black text-slate-700"
                                    >
                                        {{ session.items_count }}
                                    </p>
                                </div>
                                <div class="text-center">
                                    <p
                                        class="mb-1 text-[9px] font-black tracking-widest text-slate-300 uppercase"
                                    >
                                        Oleh
                                    </p>
                                    <p
                                        class="max-w-[80px] truncate text-sm font-black text-slate-700"
                                    >
                                        {{
                                            session.creator?.name?.split(' ')[0] || 'Admin'
                                        }}
                                    </p>
                                </div>
                            </div>

                            <Link
                                :href="`/audit/${session.id}`"
                                class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-50 text-[#003628] shadow-sm transition-all hover:bg-[#003628] hover:text-white"
                            >
                                <ArrowRight class="h-6 w-6" />
                            </Link>
                        </div>
                    </div>

                    <!-- Progress Strip (Visual Only for now) -->
                    <div
                        class="absolute bottom-0 left-0 h-1 w-full bg-[#003628]/10"
                    >
                        <div
                            class="h-full bg-[#003628]"
                            :style="{
                                width:
                                    session.status === 'Completed'
                                        ? '100%'
                                        : '15%',
                            }"
                        ></div>
                    </div>
                </div>

                <div
                    v-if="sessions.length === 0"
                    class="flex flex-col items-center justify-center rounded-[40px] border border-dashed border-slate-200 bg-white py-32 text-center"
                >
                    <div
                        class="mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-slate-50 text-slate-200"
                    >
                        <ClipboardList class="h-10 w-10" />
                    </div>
                    <h3
                        class="mb-3 text-2xl font-black tracking-tight text-slate-800 uppercase"
                    >
                        Belum Ada Sesi Audit
                    </h3>
                    <p class="mb-8 max-w-sm text-sm text-slate-500">
                        Mulai audit fisik aset pertama Anda dengan menekan
                        tombol "Sesi Audit Baru" untuk membuat sesi audit baru.
                    </p>
                    <div
                        class="inline-block rounded-full border border-emerald-200 bg-emerald-50 px-4 py-2 text-[10px] font-black tracking-widest text-emerald-700 uppercase"
                    >
                        💡 Klik tombol "Sesi Audit Baru" di atas untuk memulai
                    </div>
                </div>
            </div>
        </div>
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
