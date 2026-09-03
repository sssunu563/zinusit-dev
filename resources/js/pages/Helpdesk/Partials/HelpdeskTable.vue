<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    Eye,
    Pencil,
    Printer,
    Trash2,
} from 'lucide-vue-next';

interface Ticket {
    id: number;
    location: string;
    requester: string;
    technician: string;
    category: string;
    issue_description: string;
    status: string;
    created_at: string | null;
}

interface Props {
    tickets: Ticket[];
    formatDate: (date?: string | null) => string;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'view', ticket: Ticket): void;
    (e: 'edit', ticket: Ticket): void;
    (e: 'delete', id: number): void;
}>();

const getStatusClass = (status: string) => {
    switch (status) {
        case 'Closed':
        case 'Selesai': 
            return 'bg-emerald-50 text-emerald-700 border-emerald-100';
        case 'In Progress':
        case 'Diproses':
            return 'bg-amber-50 text-amber-700 border-amber-100';
        default:
            return 'bg-slate-50 text-slate-700 border-slate-100';
    }
};

const getStatusLabel = (status: string) => {
    switch (status) {
        case 'Closed': return 'Selesai';
        case 'In Progress': return 'Diproses';
        case 'Open': return 'Buka';
        default: return status;
    }
};
</script>

<template>
    <div class="app-table-desktop overflow-hidden rounded-xl border border-slate-200/50">
        <table class="app-table min-w-[1040px]">
            <thead class="app-table-head-surface border-b border-slate-50">
                <tr>
                    <th class="app-table-head md:px-6 text-[10px] font-black uppercase tracking-widest text-slate-400">#</th>
                    <th class="app-table-head md:px-6 text-[10px] font-black uppercase tracking-widest text-slate-400">Lokasi</th>
                    <th class="app-table-head md:px-6 text-[10px] font-black uppercase tracking-widest text-slate-400">Peminta</th>
                    <th class="app-table-head md:px-6 text-[10px] font-black uppercase tracking-widest text-slate-400">Teknisi</th>
                    <th class="app-table-head md:px-6 text-[10px] font-black uppercase tracking-widest text-slate-400">Kategori</th>
                    <th class="app-table-head md:px-6 text-[10px] font-black uppercase tracking-widest text-slate-400">Masalah</th>
                    <th class="app-table-head md:px-6 text-[10px] font-black uppercase tracking-widest text-slate-400">Status</th>
                    <th class="app-table-head md:px-6 text-[10px] font-black uppercase tracking-widest text-slate-400">Dibuat Pada</th>
                    <th class="app-table-head text-right md:px-6 text-[10px] font-black uppercase tracking-widest text-slate-400">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <tr
                    v-for="ticket in tickets"
                    :key="ticket.id"
                    class="group hover:bg-slate-50/50 transition-colors"
                >
                    <td class="app-table-cell md:px-6 py-4">
                        <button
                            type="button"
                            @click="emit('view', ticket)"
                            class="flex items-center gap-1.5 font-black text-[#003628] hover:opacity-70 transition-all uppercase tracking-tighter"
                        >
                            #{{ ticket.id }}
                            <ArrowUpRight class="size-3 opacity-0 group-hover:opacity-100 transition-all" />
                        </button>
                    </td>
                    <td class="app-table-cell md:px-6 py-4">
                        <span class="text-[11px] text-slate-500 font-black">{{ ticket.location || '-' }}</span>
                    </td>
                    <td class="app-table-cell md:px-6 py-4">
                        <span class="text-[13px] font-black text-slate-900">{{ ticket.requester || '-' }}</span>
                    </td>
                    <td class="app-table-cell md:px-6 py-4">
                        <span class="text-[11px] text-slate-500 font-black">{{ ticket.technician || '-' }}</span>
                    </td>
                    <td class="app-table-cell md:px-6 py-4">
                        <span class="app-badge app-badge-neutral border-slate-200 bg-slate-100 text-slate-600 font-black">
                            {{ ticket.category || '-' }}
                        </span>
                    </td>
                    <td class="app-table-cell md:px-6 py-4">
                        <p class="w-[200px] truncate text-[11px] text-slate-500 font-bold" :title="ticket.issue_description">
                            {{ ticket.issue_description || '-' }}
                        </p>
                    </td>
                    <td class="app-table-cell md:px-6 py-4">
                        <span :class="getStatusClass(ticket.status)" class="app-badge border font-black uppercase tracking-widest text-[9px]">
                            {{ getStatusLabel(ticket.status) }}
                        </span>
                    </td>
                    <td class="app-table-cell md:px-6 py-4">
                        <span class="text-[13px] text-slate-500 font-black tabular-nums">{{ formatDate(ticket.created_at) }}</span>
                    </td>
                    <td class="app-table-cell md:px-6 py-4 shrink-0">
                        <div class="flex items-center justify-end gap-2">
                                <button
                                    type="button"
                                    @click="emit('view', ticket)"
                                    class="h-8 w-8 rounded-lg border border-slate-100 bg-white flex items-center justify-center text-slate-400 hover:text-[#003628] hover:border-[#003628]/20 transition-all active:scale-90 shadow-sm"
                                    title="Lihat"
                                >
                                    <Eye class="size-4" />
                                </button>
                                <a
                                    :href="`/helpdesk/${ticket.id}/print`"
                                    target="_blank"
                                    rel="noopener"
                                    class="h-8 w-8 rounded-lg border border-slate-100 bg-white flex items-center justify-center text-slate-400 hover:text-emerald-600 hover:border-emerald-200 transition-all active:scale-90 shadow-sm"
                                    title="Cetak"
                                >
                                    <Printer class="size-4" />
                                </a>
                                <button
                                    v-if="ticket.status !== 'Closed'"
                                    type="button"
                                    @click="emit('edit', ticket)"
                                    class="h-8 w-8 rounded-lg border border-slate-100 bg-white flex items-center justify-center text-slate-400 hover:text-amber-600 hover:border-amber-200 transition-all active:scale-90 shadow-sm"
                                    title="Ubah"
                                >
                                    <Pencil class="size-4" />
                                </button>
                                 <button
                                    v-if="ticket.status !== 'Closed'"
                                    type="button"
                                    class="h-8 w-8 rounded-lg border border-slate-100 bg-white flex items-center justify-center text-slate-400 hover:text-rose-600 hover:border-rose-200 transition-all active:scale-90 shadow-sm"
                                    title="Hapus"
                                    @click="emit('delete', ticket.id)"
                                >
                                    <Trash2 class="size-4" />
                                </button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
