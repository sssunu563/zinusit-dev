<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    LucideFileText as FileText,
    LucideUser as UserIcon,
    LucideCalendar as CalendarIcon,
    LucideCheckCircle2 as CheckCircle,
    LucideExternalLink as ExternalLink,
    LucideDownload as Download,
    LucideX as XIcon,
    LucideLayers as Layers,
    LucideShieldCheck as ShieldCheck,
    LucidePenTool as PenTool,
    LucideAlertTriangle as AlertTriangle,
    LucideInfo as InfoIcon,
    LucideFolder as StbIcon,
    LucideClipboardList as LoanIcon,
    LucideSearchCheck as InspectionIcon,
    LucideBriefcase as TicketIcon,
    LucideRotateCcw as RotateCcw,
    LucidePlusCircle as PlusCircle,
    LucidePencil as Pencil,
    LucideTrash2 as Trash2,
    LucideEye as EyeIcon,
} from 'lucide-vue-next';
import { computed } from 'vue';

export interface ActivityDetailItem {
    id: string | number;
    action_type: string;
    action_label?: string | null;
    note?: string | null;
    log_meta?: Record<string, any> | null;
    created_at?: string;
    date?: string;
    user?: string | null;
    user_id?: number | null;
    target?: string | null;
    target_type?: string | null;
    form_type?: string | null;
    form_name?: string | null;
    doc_no?: string | null;
    doc_url?: string | null;
    pdf_url?: string | null;
    file_name?: string | null;
    file_url?: string | null;
    download_url?: string | null;
    role?: string | null;
    quantity?: number | string | null;
    changed?: string | null;
    source?: string | null;
}

const props = defineProps<{
    open: boolean;
    item: ActivityDetailItem | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'open-pdf', url: string): void;
}>();

const close = () => {
    emit('update:open', false);
};

const formatRoleName = (role: string | null | undefined) => {
    if (!role) return '-';
    return role
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (c) => c.toUpperCase());
};

const formatChangedParts = (changed: string | null | undefined) => {
    if (!changed) return [];

    return changed.split(/\s+\|\|\s+/).map((part) => {
        const arrowIndex = part.indexOf(' -> ');
        if (arrowIndex === -1) {
            return { text: part, oldValue: null, newValue: null, label: '' };
        }

        const beforeArrow = part.slice(0, arrowIndex);
        const labelSeparator = beforeArrow.indexOf(': ');

        return {
            label:
                labelSeparator === -1
                    ? ''
                    : beforeArrow.slice(0, labelSeparator),
            oldValue:
                labelSeparator === -1
                    ? beforeArrow
                    : beforeArrow.slice(labelSeparator + 2),
            newValue: part.slice(arrowIndex + 4),
            text: null,
        };
    });
};

const getFormIcon = (formType?: string | null) => {
    switch ((formType || '').toLowerCase()) {
        case 'stb':
            return StbIcon;
        case 'peminjaman':
            return LoanIcon;
        case 'inspection':
            return InspectionIcon;
        case 'ticket':
        case 'helpdesk':
            return TicketIcon;
        default:
            return FileText;
    }
};

const displayTitle = computed(() => {
    if (!props.item) return '';
    return (
        props.item.doc_no ||
        props.item.file_name ||
        props.item.form_name ||
        props.item.action_label ||
        props.item.action_type ||
        'Detail Log'
    );
});

const displayActionLabel = computed(() => {
    if (!props.item) return '';
    return (
        props.item.action_label ||
        props.item.action_type?.replace(/_/g, ' ') ||
        'Aktivitas'
    );
});

const resolvedPdfUrl = computed(() => {
    return props.item?.pdf_url || props.item?.download_url || (props.item?.file_url && /\.pdf$/i.test(props.item.file_url) ? props.item.file_url : null);
});

const handleViewPdf = () => {
    if (resolvedPdfUrl.value) {
        emit('open-pdf', resolvedPdfUrl.value);
    }
};
</script>

<template>
    <div v-if="open && item" class="fixed inset-0 z-50 overflow-hidden">
        <!-- Backdrop -->
        <div
            class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity"
            @click="close"
        />

        <div class="fixed inset-y-0 right-0 max-w-full flex pl-10">
            <div
                class="w-screen max-w-lg bg-white shadow-2xl flex flex-col border-l border-slate-200"
            >
                <!-- Header -->
                <div
                    class="px-6 py-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50"
                >
                    <div class="flex items-center gap-3 min-w-0">
                        <div
                            class="h-10 w-10 shrink-0 rounded-xl bg-[#003628]/10 text-[#003628] flex items-center justify-center"
                        >
                            <component
                                :is="getFormIcon(item.form_type)"
                                class="size-5"
                            />
                        </div>
                        <div class="min-w-0">
                            <span
                                class="text-[10px] font-black uppercase tracking-widest text-[#003628] block truncate"
                            >
                                {{ item.form_name || 'Detail Log / Dokumen' }}
                            </span>
                            <h2
                                class="text-base font-black text-slate-900 leading-tight truncate"
                                :title="displayTitle"
                            >
                                {{ displayTitle }}
                            </h2>
                        </div>
                    </div>
                    <button
                        @click="close"
                        class="h-8 w-8 shrink-0 rounded-lg border border-slate-200 bg-white text-slate-400 hover:text-slate-700 hover:bg-slate-50 flex items-center justify-center transition-colors"
                    >
                        <XIcon class="size-4" />
                    </button>
                </div>

                <!-- Body -->
                <div class="flex-1 overflow-y-auto p-6 space-y-6">
                    <!-- Status & Type Banner -->
                    <div
                        class="p-4 rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-between gap-3"
                    >
                        <div class="space-y-0.5 min-w-0">
                            <span
                                class="text-[9px] font-black uppercase tracking-widest text-slate-400"
                            >
                                Jenis Operasi / Dokumen
                            </span>
                            <p class="text-xs font-black text-slate-900 truncate">
                                {{ item.form_name || 'Aktivitas Asset' }}
                            </p>
                        </div>
                        <span
                            class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest border shrink-0"
                            :class="{
                                'bg-[#003628]/5 text-[#003628] border-[#003628]/15':
                                    ['created', 'create', 'stb_complete', 'completed', 'diselesaikan', 'dibuat'].some((k) =>
                                        (item.action_type || '').toLowerCase().includes(k)
                                    ),
                                'bg-amber-50 text-amber-600 border-amber-200':
                                    ['updated', 'update', 'sign', 'tanda tangan', 'diperbarui', 'checkout'].some((k) =>
                                        (item.action_type || '').toLowerCase().includes(k)
                                    ),
                                'bg-blue-50 text-blue-700 border-blue-200':
                                    ['checkin', 'mutasi', 'kembali'].some((k) =>
                                        (item.action_type || '').toLowerCase().includes(k)
                                    ),
                                'bg-rose-50 text-rose-600 border-rose-200':
                                    ['deleted', 'delete', 'cancelled', 'batal', 'gagal'].some((k) =>
                                        (item.action_type || '').toLowerCase().includes(k)
                                    ),
                                'bg-slate-100 text-slate-600 border-slate-200':
                                    !['created', 'create', 'stb_complete', 'completed', 'updated', 'update', 'sign', 'checkin', 'checkout', 'deleted', 'delete'].some((k) =>
                                        (item.action_type || '').toLowerCase().includes(k)
                                    )
                            }"
                        >
                            {{ displayActionLabel }}
                        </span>
                    </div>

                    <!-- Meta Information Grid -->
                    <div class="grid grid-cols-2 gap-4">
                        <div
                            class="p-3.5 rounded-xl border border-slate-100 bg-white shadow-sm space-y-1"
                        >
                            <span
                                class="text-[9px] font-black uppercase tracking-widest text-slate-400 flex items-center gap-1.5"
                            >
                                <CalendarIcon class="size-3 text-slate-400" />
                                Waktu Kejadian
                            </span>
                            <p
                                class="text-[11px] font-mono font-bold text-slate-800"
                            >
                                {{ item.date || item.created_at || '-' }}
                            </p>
                        </div>

                        <div
                            class="p-3.5 rounded-xl border border-slate-100 bg-white shadow-sm space-y-1"
                        >
                            <span
                                class="text-[9px] font-black uppercase tracking-widest text-slate-400 flex items-center gap-1.5"
                            >
                                <UserIcon class="size-3 text-slate-400" />
                                Otorisasi Oleh
                            </span>
                            <Link
                                v-if="item.user_id"
                                :href="`/users/${item.user_id}`"
                                class="text-[11px] font-black text-[#003628] hover:underline block truncate"
                            >
                                {{ item.user || 'System' }}
                            </Link>
                            <span
                                v-else
                                class="text-[11px] font-bold text-slate-800 block truncate"
                            >
                                {{ item.user || 'System' }}
                            </span>
                        </div>
                    </div>

                    <!-- Target / Penerima / Role Grid -->
                    <div
                        v-if="item.target || item.role || item.quantity"
                        class="p-4 rounded-xl border border-slate-100 bg-slate-50 space-y-2.5"
                    >
                        <div
                            v-if="item.target"
                            class="flex items-center justify-between gap-2"
                        >
                            <span
                                class="text-[10px] font-black uppercase tracking-wider text-slate-400"
                            >
                                Target / Pihak Terkait
                            </span>
                            <span
                                class="text-xs font-bold text-slate-800 text-right truncate"
                            >
                                {{ item.target }}
                            </span>
                        </div>

                        <div
                            v-if="item.role"
                            class="flex items-center justify-between gap-2 pt-2 border-t border-slate-200/50"
                        >
                            <span
                                class="text-[10px] font-black uppercase tracking-wider text-slate-400"
                            >
                                Role Penandatangan
                            </span>
                            <span class="text-xs font-bold text-slate-800">
                                {{ formatRoleName(item.role) }}
                            </span>
                        </div>

                        <div
                            v-if="item.quantity"
                            class="flex items-center justify-between gap-2 pt-2 border-t border-slate-200/50"
                        >
                            <span
                                class="text-[10px] font-black uppercase tracking-wider text-slate-400"
                            >
                                Kuantitas
                            </span>
                            <span class="text-xs font-mono font-bold text-slate-800">
                                {{ item.quantity }}
                            </span>
                        </div>
                    </div>

                    <!-- Dokumen Terkait Box -->
                    <div
                        v-if="item.doc_no || item.file_name"
                        class="p-4 rounded-2xl border border-emerald-100 bg-emerald-50/40 space-y-2"
                    >
                        <span
                            class="text-[9px] font-black uppercase tracking-widest text-emerald-800 flex items-center gap-1.5"
                        >
                            <FileText class="size-3.5 text-emerald-700" />
                            Dokumen / Berkas Terkait
                        </span>
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p
                                    class="text-xs font-mono font-black text-slate-900 truncate"
                                >
                                    {{ item.doc_no || item.file_name }}
                                </p>
                                <p
                                    v-if="item.file_name && item.doc_no && item.file_name !== item.doc_no"
                                    class="text-[11px] text-slate-500 truncate"
                                >
                                    {{ item.file_name }}
                                </p>
                            </div>
                            <Link
                                v-if="item.doc_url"
                                :href="item.doc_url"
                                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-[#003628] text-white text-[10px] font-black uppercase tracking-wider shadow-sm hover:bg-[#00281e] shrink-0"
                            >
                                <span>Buka Form</span>
                                <ExternalLink class="size-3" />
                            </Link>
                        </div>
                    </div>

                    <!-- Note -->
                    <div v-if="item.note && item.note !== '-'" class="space-y-1.5">
                        <span
                            class="text-[9px] font-black uppercase tracking-widest text-slate-400 ml-1"
                        >
                            Catatan Log
                        </span>
                        <div
                            class="p-4 rounded-xl border border-slate-100 bg-slate-50 text-xs font-medium text-slate-700 leading-relaxed break-words whitespace-pre-line"
                        >
                            {{ item.note }}
                        </div>
                    </div>

                    <!-- Changed Details (Perubahan Field) -->
                    <div v-if="item.changed" class="space-y-1.5">
                        <span
                            class="text-[9px] font-black uppercase tracking-widest text-slate-400 ml-1"
                        >
                            Rincian Perubahan Field
                        </span>
                        <div
                            class="divide-y divide-slate-100 rounded-xl border border-slate-100 bg-slate-50/80 overflow-hidden text-xs"
                        >
                            <div
                                v-for="(change, idx) in formatChangedParts(item.changed)"
                                :key="idx"
                                class="px-3.5 py-2.5 flex flex-col sm:flex-row sm:items-center justify-between gap-2"
                            >
                                <span
                                    v-if="change.label"
                                    class="font-black text-[10px] uppercase tracking-wider text-slate-500"
                                >
                                    {{ change.label }}
                                </span>
                                <div
                                    v-if="change.newValue !== null"
                                    class="flex items-center gap-2 text-[11px] font-mono"
                                >
                                    <s class="text-rose-400">{{ change.oldValue || 'kosong' }}</s>
                                    <span class="text-slate-400">→</span>
                                    <span class="font-bold text-emerald-700">{{ change.newValue }}</span>
                                </div>
                                <span
                                    v-else
                                    class="font-medium text-slate-700 text-[11px]"
                                >
                                    {{ change.text }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Detailed Meta Payload -->
                    <div
                        v-if="item.log_meta && Object.keys(item.log_meta).length > 0"
                        class="space-y-2"
                    >
                        <span
                            class="text-[9px] font-black uppercase tracking-widest text-slate-400 ml-1 flex items-center gap-1.5"
                        >
                            <Layers class="size-3 text-slate-400" />
                            Rincian Metadata
                        </span>

                        <div
                            class="divide-y divide-slate-100 rounded-xl border border-slate-100 bg-slate-50/60 overflow-hidden text-xs"
                        >
                            <div
                                v-for="(val, key) in item.log_meta"
                                :key="key"
                                class="px-3.5 py-2.5 flex flex-col sm:flex-row sm:items-start justify-between gap-2"
                            >
                                <span
                                    class="font-black text-[10px] uppercase tracking-wider text-slate-400 shrink-0"
                                >
                                    {{ key }}
                                </span>
                                <span
                                    class="font-medium text-slate-800 break-all text-right font-mono text-[11px]"
                                >
                                    {{ typeof val === 'object' ? JSON.stringify(val) : val }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer Actions -->
                <div
                    class="p-4 border-t border-slate-100 bg-slate-50/50 flex items-center gap-3"
                >
                    <button
                        v-if="resolvedPdfUrl"
                        type="button"
                        @click="handleViewPdf"
                        class="flex-1 h-10 px-4 rounded-xl border border-slate-200 bg-white text-slate-700 hover:text-slate-900 hover:bg-slate-50 flex items-center justify-center gap-2 text-xs font-bold transition-all shadow-sm active:scale-95"
                    >
                        <EyeIcon class="size-3.5 text-slate-500" />
                        <span>Lihat Dokumen PDF</span>
                    </button>

                    <Link
                        v-if="item.doc_url"
                        :href="item.doc_url"
                        class="flex-1 h-10 px-4 rounded-xl bg-[#003628] text-white hover:bg-[#00271d] flex items-center justify-center gap-2 text-xs font-bold transition-all shadow-md shadow-emerald-900/10 active:scale-95"
                    >
                        <ExternalLink class="size-3.5" />
                        <span>Buka Halaman Form</span>
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
