<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Pencil, Printer } from 'lucide-vue-next';
import { computed } from 'vue';

interface TicketDetail {
    id: number;
    company: string;
    location: string;
    category: string;
    ticket_scope: string;
    priority: string;
    requester: string;
    department: string;
    snipeit_asset_id: number | null;
    asset_reference_snapshot: string | null;
    maintenance_type: string;
    issue_description: string;
    action_taken: string;
    note: string | null;
    technician: string;
    vendor_id: number | null;
    vendor: {
        id: number;
        name: string;
    } | null;
    status: string;
    date_closed: string | null;
    snipeit_maintenance_id: number | null;
    snipeit_sync_status: string | null;
    snipeit_sync_message: string | null;
    created_at: string | null;
    creator: {
        id: number;
        name: string;
    } | null;
}

const props = defineProps<{
    ticket: TicketDetail;
    canViewAll?: boolean;
    isModal?: boolean;
    techCompany?: string;
    techLocation?: string;
}>();

const emits = defineEmits<{
    (e: 'edit'): void;
    (e: 'close'): void;
}>();

const formatDate = (date?: string | null) => {
    if (!date) {
        return '-';
    }

    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};

const formatDateTime = (date?: string | null) => {
    if (!date) {
        return '-';
    }

    return new Date(date).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const isAssetTicket = computed(() => props.ticket.ticket_scope === 'asset');
const printedAt = computed(() => formatDateTime(new Date().toISOString()));

const docId = computed(() => {
    const createdAt = props.ticket.created_at
        ? new Date(props.ticket.created_at)
              .toISOString()
              .slice(0, 10)
              .replace(/-/g, '')
        : '00000000';
    return `HDK-${createdAt}-${String(props.ticket.id).padStart(5, '0')}`;
});

const signatureBoxes = computed(() => {
    return [
        { label: 'Peminta', name: props.ticket.requester },
        { label: 'Teknisi', name: props.ticket.technician },
    ];
});

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
    <div class="overflow-hidden bg-background" :class="[isModal ? 'max-w-none rounded-none' : 'rounded-2xl border border-border shadow-sm']">
        <!-- Dashboard Header (Actions) -->
        <div class="border-b border-slate-100 bg-white px-7 py-5 print:hidden">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-bold tracking-tight text-slate-900">
                        Rincian Tiket #{{ ticket.id }}
                    </h2>
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mt-0.5">{{ docId }}</p>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <a
                        :href="`/helpdesk/${ticket.id}/print`"
                        target="_blank"
                        rel="noopener"
                        class="h-9 px-4 rounded-xl border border-slate-200 bg-white flex items-center gap-2 text-slate-600 hover:text-[#003628] hover:bg-[#003628]/5 transition-all text-xs font-bold shadow-sm"
                    >
                        <Printer class="size-3.5" />
                        Cetak PDF
                    </a>
                    <button
                        v-if="ticket.status !== 'Closed' && ticket.status !== 'Selesai'"
                        type="button"
                        @click="$emit('edit')"
                        class="h-9 px-4 rounded-xl bg-[#003628] text-white flex items-center gap-2 hover:bg-[#003628]/90 transition-all text-xs font-bold shadow-sm"
                    >
                        <Pencil class="size-3.5" />
                        Ubah
                    </button>
                    <button
                        v-if="isModal"
                        type="button"
                        @click="$emit('close')"
                        class="h-9 px-4 rounded-xl border border-slate-200 bg-white text-slate-600 hover:text-rose-600 transition-all text-xs font-bold shadow-sm"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>

        <!-- Document Surface (Within Modal) -->
        <div class="p-4 sm:p-7">
            <div class="mx-auto max-w-[900px] p-7 shadow-sm shared-print document-preview">
                <table class="shared-header-table w-full">
                    <tbody>
                        <tr>
                            <td class="shared-logo-cell">
                                <img src="/form-logo.png" class="shared-logo" alt="Zinus" />
                            </td>
                            <td class="shared-title-cell">
                                <div class="shared-title-main">LAPORAN KERJA</div>
                                <div class="shared-title-sub">PT. {{ ticket.tech_company || techCompany || 'ZINUS DREAM INDONESIA' }}</div>
                            </td>
                            <td class="shared-meta-cell">
                                <div class="font-semibold">IT Dept.</div>
                                <div>Dicetak Pada:</div>
                                <div>{{ printedAt }}</div>
                                <div class="mt-1 text-[8px] opacity-70">Mode Pratinjau</div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <table class="shared-info-table mt-4">
                    <tbody>
                        <tr>
                            <td class="shared-label">Tiket</td>
                            <td>#{{ props.ticket.id }} <span class="ml-1 text-[9px] text-muted-foreground">({{ docId }})</span></td>
                            <td class="shared-label">Kategori</td>
                            <td>{{ props.ticket.category || '-' }}</td>
                        </tr>
                        <tr>
                            <td class="shared-label">Tipe Tiket</td>
                            <td>{{ isAssetTicket ? 'Terkait Aset' : 'Dukungan Umum' }}</td>
                            <td class="shared-label">Prioritas</td>
                            <td>
                                <span class="font-bold" :class="ticket.priority === 'Urgent' || ticket.priority === 'High' ? 'text-red-600' : ''">
                                    {{ props.ticket.priority || '-' }}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="shared-label">Tanggal Dibuat</td>
                            <td>{{ formatDateTime(props.ticket.created_at) }}</td>
                            <td class="shared-label">Tanggal Ditutup</td>
                            <td>{{ formatDate(props.ticket.date_closed) }}</td>
                        </tr>
                    </tbody>
                </table>

                <div class="shared-recipient-note mt-4">
                    <span>Pihak yang terlibat dalam laporan ini:</span>
                    <span 
                        class="app-badge border font-black uppercase tracking-widest text-[9px]"
                        :class="getStatusClass(ticket.status)"
                    >
                        Status: {{ getStatusLabel(ticket.status) }}
                    </span>
                </div>

                <table class="shared-info-table">
                    <tbody>
                        <tr>
                            <td class="shared-label">Peminta</td>
                            <td>{{ props.ticket.requester || '-' }}</td>
                            <td class="shared-label">Teknisi</td>
                            <td>{{ props.ticket.technician || '-' }}</td>
                        </tr>
                        <tr>
                            <td class="shared-label">Departemen</td>
                            <td>{{ props.ticket.department || '-' }}</td>
                            <td class="shared-label">Lokasi</td>
                            <td>{{ props.ticket.location || '-' }}</td>
                        </tr>
                        <tr v-if="props.ticket.vendor">
                            <td class="shared-label">Vendor</td>
                            <td colspan="3">
                                <div class="flex flex-wrap gap-2">
                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-blue-50 text-blue-700 font-bold text-[10px] border border-blue-100">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-7h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                                        {{ props.ticket.vendor.name }}
                                    </span>
                                    <span v-if="props.ticket.vendor.category" class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-slate-50 text-slate-600 font-bold text-[10px] border border-slate-100 uppercase tracking-widest">
                                        {{ props.ticket.vendor.category }}
                                    </span>
                                    <span v-if="props.ticket.vendor.phone" class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-700 font-bold text-[10px] border border-emerald-100">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>
                                        {{ props.ticket.vendor.phone }}
                                    </span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <table v-if="isAssetTicket" class="shared-info-table">
                    <tbody>
                        <tr>
                            <td class="shared-label">Ref Aset</td>
                            <td>{{ props.ticket.asset_reference_snapshot || '-' }}</td>
                            <td class="shared-label">Tipe Pemeliharaan</td>
                            <td>{{ props.ticket.maintenance_type || '-' }}</td>
                        </tr>
                    </tbody>
                </table>

                <!-- Narrative Sections -->
                <div class="shared-agreement-box !p-0 mt-4">
                    <div class="border-b border-border bg-muted px-3 py-1.5 font-bold text-[10px] uppercase tracking-wider">
                        Deskripsi Masalah
                    </div>
                    <div class="px-3 py-3 text-[11px] leading-relaxed whitespace-pre-wrap min-h-[50px]">
                        {{ props.ticket.issue_description || '-' }}
                    </div>
                </div>

                <div class="shared-agreement-box !p-0 mt-3">
                    <div class="border-b border-border bg-muted px-3 py-1.5 font-bold text-[10px] uppercase tracking-wider">
                        Tindakan yang Diambil
                    </div>
                    <div class="px-3 py-3 text-[11px] leading-relaxed whitespace-pre-wrap min-h-[50px]">
                        {{ props.ticket.action_taken || '-' }}
                    </div>
                </div>

                <div v-if="props.ticket.note" class="shared-agreement-box !p-0 mt-3">
                    <div class="border-b border-border bg-muted px-3 py-1.5 font-bold text-[10px] uppercase tracking-wider">
                        Catatan
                    </div>
                    <div class="px-3 py-3 text-[11px] leading-relaxed whitespace-pre-wrap">
                        {{ props.ticket.note }}
                    </div>
                </div>

                <!-- Signatures -->
                <div class="shared-signature-grid mt-8" :style="{ gridTemplateColumns: `repeat(${signatureBoxes.length}, 1fr) !important` }">
                    <table class="shared-signature-table" v-for="box in signatureBoxes" :key="box.label">
                        <tbody>
                            <tr>
                                <td class="shared-signature-head py-1.5 font-bold">
                                    {{ box.label }}
                                </td>
                            </tr>
                            <tr>
                                <td class="shared-signature-body">
                                    <div class="shared-signature-stack min-h-[80px]">
                                        <div class="shared-signature-image-box flex-1">
                                            <!-- Empty space -->
                                        </div>
                                        <div class="shared-signature-name mt-auto border-t border-[#d1d8d4] pt-1.5 font-bold">
                                            {{ box.name || '-' }}
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Sync Status Footer info (Optional for Modal) -->
        <div v-if="isAssetTicket" class="border-t border-border bg-card/60 px-7 py-3 text-[10px] text-muted-foreground flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="font-semibold uppercase tracking-wider">Sinkronisasi Snipe-IT:</span>
                <span :class="ticket.snipeit_sync_status === 'synced' ? 'text-emerald-700' : 'text-amber-700'">
                    {{ ticket.snipeit_sync_status === 'synced' ? 'disinkronkan' : (ticket.snipeit_sync_status || 'Belum Disinkronkan') }}
                </span>
                <span v-if="ticket.snipeit_maintenance_id">#{{ ticket.snipeit_maintenance_id }}</span>
            </div>
            <div>{{ ticket.snipeit_sync_message }}</div>
        </div>
    </div>
</template>
