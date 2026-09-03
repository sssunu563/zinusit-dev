<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { usePrintPreview } from '@/composables/usePrintPreview';

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
    status: string;
    date_closed: string | null;
    snipeit_maintenance_id: number | null;
    snipeit_sync_status: string | null;
    snipeit_sync_message: string | null;
    created_at: string | null;
    creator: { id: number; name: string } | null;
    tech_company?: string;
    tech_location?: string;
}

interface Props {
    ticket: TicketDetail;
    canViewAll: boolean;
    techCompany: string;
    techLocation: string;
}

const props = defineProps<Props>();
const printRoot = ref<HTMLElement | null>(null);

const formatDate = (date?: string | null) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};

const formatDateTime = (date?: string | null) => {
    if (!date) return '-';
    return new Date(date).toLocaleString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const printedAt = computed(() => formatDateTime(new Date().toISOString()));
const isAssetTicket = computed(() => props.ticket.ticket_scope === 'asset');

const signatureBoxes = computed(() => {
    return [
        { label: 'Peminta', name: props.ticket.requester },
        { label: 'Teknisi', name: props.ticket.technician },
    ];
});

usePrintPreview(printRoot, async () => {
    await Promise.resolve();
});
</script>

<template>
    <Head :title="`Print Tiket #${props.ticket.id}`" />

    <div ref="printRoot" class="print-stage bg-[#f0f2f5] min-h-screen px-2 py-4 md:py-6 print:bg-transparent print:p-0">
        <!-- ToolBar / Header Controls outside canvas -->
        <div class="mb-5 mx-auto max-w-[210mm] flex flex-col md:flex-row md:items-start md:justify-between gap-4 border-b border-slate-200 pb-5 print:hidden">
            <div class="flex-1">
                <div class="flex items-center gap-3">
                    <h2 class="text-xl font-bold text-[#003628] tracking-tight uppercase">Pratinjau Cetak Tiket</h2>
                    <span 
                        class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-black tracking-wider uppercase shadow-sm"
                        :class="
                            props.ticket.status === 'Closed' || props.ticket.status === 'Selesai'
                                ? 'bg-emerald-600 text-white'
                                : props.ticket.status === 'In Progress' || props.ticket.status === 'Diproses'
                                    ? 'bg-[#d99528] text-white'
                                    : 'bg-slate-500 text-white'
                        "
                    >
                        {{ props.ticket.status === 'Closed' ? 'Selesai' : (props.ticket.status === 'In Progress' ? 'Diproses' : 'Buka') }}
                    </span>
                </div>
                <p class="mt-1 flex items-center gap-2 text-sm font-medium text-slate-500">
                    <span class="font-bold text-[10px] tracking-widest text-[#003628]">#{{ props.ticket.id }}</span>
                    <span>•</span>
                    <span class="text-[10px] font-black uppercase tracking-tight">{{ formatDateTime(props.ticket.created_at) }}</span>
                </p>
            </div>
            
            <div class="flex items-center gap-2 shrink-0">
                <button
                    class="h-10 px-6 rounded-xl bg-[#003628] text-white text-xs font-bold uppercase tracking-widest shadow-lg hover:brightness-110 transition-all flex items-center gap-2"
                    onclick="window.print()"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-4a2 2 0 012-2H5a2 2 0 012 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                    Cetak Dokumen
                </button>
                <button
                    @click="window.close()"
                    class="h-10 px-6 rounded-xl border border-slate-200 bg-white text-slate-900 text-xs font-bold uppercase tracking-widest shadow-sm hover:bg-slate-50 transition-all"
                >
                    Tutup
                </button>
            </div>
        </div>

        <div class="mx-auto w-[210mm] max-w-full min-h-[297mm] flex flex-col bg-white p-[5mm_8mm] shadow-2xl print:p-[4mm_6mm] print:shadow-none shared-print batch-print-canvas">
            
            <!-- Print Document Surface -->
            <div class="flex-grow">
                <table class="shared-header-table w-full">
                    <tbody>
                        <tr>
                            <td class="shared-logo-cell">
                                <img src="/form-logo.png" class="shared-logo" alt="Zinus" />
                            </td>
                            <td class="shared-title-cell">
                                <div class="shared-title-main">LAPORAN KERJA</div>
                                <div class="shared-title-sub">PT. {{ props.ticket.tech_company || techCompany || 'ZINUS DREAM INDONESIA' }}</div>
                            </td>
                            <td class="shared-meta-cell">
                                <div class="font-semibold">IT Dept.</div>
                                <div>Dicetak Pada:</div>
                                <div>{{ printedAt }}</div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <table class="shared-info-table">
                    <tbody>
                        <tr>
                            <td class="shared-label">Tiket</td>
                            <td class="font-bold">#{{ props.ticket.id }}</td>
                            <td class="shared-label">Kategori</td>
                            <td>{{ props.ticket.category || '-' }}</td>
                        </tr>
                        <tr>
                            <td class="shared-label">Tipe Tiket</td>
                            <td>{{ isAssetTicket ? 'Terkait Aset' : 'Dukungan Umum' }}</td>
                            <td class="shared-label">Prioritas</td>
                            <td>{{ {Urgent:'Darurat', High:'Tinggi', Medium:'Sedang', Low:'Rendah'}[props.ticket.priority] || props.ticket.priority }}</td>
                        </tr>
                        <tr>
                            <td class="shared-label">Tanggal Dibuat</td>
                            <td>{{ formatDateTime(props.ticket.created_at) }}</td>
                            <td class="shared-label">Tanggal Ditutup</td>
                            <td>{{ formatDate(props.ticket.date_closed) }}</td>
                        </tr>
                    </tbody>
                </table>

                <div class="shared-recipient-note mt-3">
                    <div class="flex items-center justify-between w-full">
                        <div class="text-left">
                            <strong>Peminta:</strong> {{ props.ticket.requester || '-' }}
                        </div>
                        <div class="text-center">
                            <strong>Teknisi:</strong> {{ props.ticket.technician || '-' }}
                        </div>
                        <div class="text-right">
                            <strong>Status:</strong> {{ props.ticket.status }}
                        </div>
                    </div>
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
                <div class="shared-agreement-box !p-0">
                    <div class="border-b border-[#d1d8d4] bg-[#f2f5f3] px-3 py-1.5 font-bold">
                        Deskripsi Masalah
                    </div>
                    <div class="px-3 py-2.5 whitespace-pre-wrap leading-relaxed min-h-[60px]">
                        {{ props.ticket.issue_description || '-' }}
                    </div>
                </div>

                <div class="shared-agreement-box !p-0 mt-2">
                    <div class="border-b border-[#d1d8d4] bg-[#f2f5f3] px-3 py-1.5 font-bold">
                        Tindakan yang Diambil
                    </div>
                    <div class="px-3 py-2.5 whitespace-pre-wrap leading-relaxed min-h-[60px]">
                        {{ props.ticket.action_taken || '-' }}
                    </div>
                </div>

                <div v-if="props.ticket.note" class="shared-agreement-box !p-0 mt-2">
                    <div class="border-b border-[#d1d8d4] bg-[#f2f5f3] px-3 py-1.5 font-bold">
                        Catatan
                    </div>
                    <div class="px-3 py-2.5 whitespace-pre-wrap leading-relaxed">
                        {{ props.ticket.note }}
                    </div>
                </div>

                <!-- Signatures -->
                <div class="shared-signature-grid mt-auto pt-6 shrink-0" :style="{ gridTemplateColumns: `repeat(${signatureBoxes.length}, 1fr) !important` }">
                    <table class="shared-signature-table" v-for="box in signatureBoxes" :key="box.label">
                        <tbody>
                            <tr>
                                <td class="shared-signature-head">
                                    {{ box.label }}
                                </td>
                            </tr>
                            <tr>
                                <td class="shared-signature-body">
                                    <div class="shared-signature-stack">
                                        <div class="shared-signature-image-box">
                                            <!-- Empty space for physical signature -->
                                        </div>
                                        <div class="shared-signature-name mt-auto border-t border-[#d1d8d4] pt-1">
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
    </div>
</template>

<style>
/* Global print overrides integrated into app.css */
</style>

<style scoped>
* {
    box-sizing: border-box;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}
</style>
