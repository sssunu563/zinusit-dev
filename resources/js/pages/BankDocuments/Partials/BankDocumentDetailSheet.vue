<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    LucideFolderArchive as FolderArchive,
    LucideFileText as FileText,
    LucideUser as UserIcon,
    LucideCalendar as CalendarIcon,
    LucideCheckCircle2 as CheckCircle,
    LucideClock as ClockIcon,
    LucideX as XIcon,
    LucideDownload as Download,
    LucidePrinter as Printer,
    LucideExternalLink as ExternalLink,
    LucideBuilding as Building,
    LucideLaptop as Laptop,
    LucideFileCheck as FileCheck,
    LucideInfo as InfoIcon,
    LucideFolder as StbIcon,
    LucideClipboardList as LoanIcon,
    LucideSearchCheck as InspectionIcon,
} from 'lucide-vue-next';

export interface BankDocumentItem {
    id: number;
    doc_no: string;
    raw_id: number;
    doc_type: 'stb' | 'peminjaman' | 'inspection' | string;
    doc_type_label: string;
    sub_type?: string;
    user_name: string;
    user_dept?: string;
    user_company?: string;
    device_name?: string;
    status: string;
    status_label: string;
    has_pdf: boolean;
    pdf_url?: string | null;
    print_url: string;
    view_url: string;
    items_count: number;
    remark?: string | null;
    created_at: string;
}

const props = defineProps<{
    open: boolean;
    document: BankDocumentItem | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const close = () => {
    emit('update:open', false);
};

const getDocIcon = (type?: string) => {
    switch (type) {
        case 'stb':
            return StbIcon;
        case 'peminjaman':
            return LoanIcon;
        case 'inspection':
            return InspectionIcon;
        default:
            return FileText;
    }
};
</script>

<template>
    <div v-if="open && document" class="fixed inset-0 z-50 overflow-hidden">
        <!-- Backdrop -->
        <div
            class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity"
            @click="close"
        />

        <div class="fixed inset-y-0 right-0 max-w-full flex pl-10">
            <div class="w-screen max-w-lg bg-white shadow-2xl flex flex-col border-l border-slate-200">
                <!-- Header -->
                <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-xl bg-[#003628]/10 text-[#003628] flex items-center justify-center">
                            <component :is="getDocIcon(document.doc_type)" class="size-5" />
                        </div>
                        <div>
                            <span class="text-[10px] font-black uppercase tracking-widest text-[#003628]">Arsip Bank Dokumen</span>
                            <h2 class="text-base font-black text-slate-900 leading-tight">
                                {{ document.doc_no }}
                            </h2>
                        </div>
                    </div>
                    <button
                        @click="close"
                        class="h-8 w-8 rounded-lg border border-slate-200 bg-white text-slate-400 hover:text-slate-700 hover:bg-slate-50 flex items-center justify-center transition-colors cursor-pointer"
                        title="Tutup"
                    >
                        <XIcon class="size-4" />
                    </button>
                </div>

                <!-- Body -->
                <div class="flex-1 overflow-y-auto p-6 space-y-6">
                    <!-- Status & Type Banner -->
                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-between">
                        <div class="space-y-0.5">
                            <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">Klasifikasi Dokumen</span>
                            <p class="text-xs font-black text-slate-900 flex items-center gap-1.5">
                                <component :is="getDocIcon(document.doc_type)" class="size-3.5 text-[#003628]" />
                                {{ document.doc_type_label }}
                                <span v-if="document.sub_type" class="text-[10px] font-medium text-slate-400">· {{ document.sub_type }}</span>
                            </p>
                        </div>
                        <span
                            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest border"
                            :class="document.status === 'completed'
                                ? 'bg-[#003628]/5 text-[#003628] border-[#003628]/10'
                                : 'bg-amber-50 text-amber-600 border-amber-100'"
                        >
                            <component :is="document.status === 'completed' ? CheckCircle : ClockIcon" class="size-3" />
                            {{ document.status_label }}
                        </span>
                    </div>

                    <!-- Action Shortcut Buttons -->
                    <div class="grid grid-cols-2 gap-3">
                        <a
                            v-if="document.has_pdf && document.pdf_url"
                            :href="document.pdf_url"
                            target="_blank"
                            class="h-10 px-4 rounded-xl bg-[#003628] text-white flex items-center justify-center gap-2 text-xs font-bold shadow-md shadow-emerald-950/20 hover:bg-[#00281e] transition-all cursor-pointer"
                        >
                            <Download class="size-4" />
                            <span>Unduh PDF Digital</span>
                        </a>
                        <a
                            v-else
                            :href="document.print_url"
                            target="_blank"
                            class="h-10 px-4 rounded-xl border border-slate-200 bg-white text-slate-700 flex items-center justify-center gap-2 text-xs font-bold hover:bg-slate-50 transition-all cursor-pointer"
                        >
                            <Printer class="size-4" />
                            <span>Cetak Dokumen</span>
                        </a>

                        <Link
                            :href="document.view_url"
                            class="h-10 px-4 rounded-xl border border-slate-200 bg-white text-slate-700 flex items-center justify-center gap-2 text-xs font-bold hover:bg-slate-50 transition-all cursor-pointer"
                        >
                            <ExternalLink class="size-4" />
                            <span>Buka Form Sumber</span>
                        </Link>
                    </div>

                    <!-- Meta Information Grid -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="p-3.5 rounded-xl border border-slate-100 bg-white shadow-xs space-y-1">
                            <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 flex items-center gap-1.5">
                                <CalendarIcon class="size-3 text-[#003628]" />
                                Tanggal Dokumen
                            </span>
                            <p class="text-xs font-black text-slate-800 tabular-nums">
                                {{ document.created_at }}
                            </p>
                        </div>

                        <div class="p-3.5 rounded-xl border border-slate-100 bg-white shadow-xs space-y-1">
                            <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 flex items-center gap-1.5">
                                <FileCheck class="size-3 text-[#003628]" />
                                Status PDF
                            </span>
                            <p class="text-xs font-black" :class="document.has_pdf ? 'text-[#003628]' : 'text-slate-400'">
                                {{ document.has_pdf ? 'Tersedia (Bertanda Tangan)' : 'Belum Digenerate' }}
                            </p>
                        </div>
                    </div>

                    <!-- Subject / Recipient Identity Card -->
                    <div class="p-4 rounded-2xl border border-slate-100 bg-slate-50/50 space-y-3">
                        <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 flex items-center gap-1.5">
                            <UserIcon class="size-3 text-[#003628]" />
                            Penerima / Pemilik Dokumen
                        </span>

                        <div class="space-y-1 pt-1">
                            <p class="text-xs font-black text-slate-900">{{ document.user_name }}</p>
                            <div class="flex items-center gap-2 pt-1 text-[10px] text-slate-500 font-medium">
                                <span v-if="document.user_dept" class="px-2 py-0.5 rounded-md bg-white border border-slate-200">
                                    {{ document.user_dept }}
                                </span>
                                <span v-if="document.user_company" class="px-2 py-0.5 rounded-md bg-white border border-slate-200">
                                    {{ document.user_company }}
                                </span>
                            </div>
                        </div>

                        <div v-if="document.device_name" class="pt-2 border-t border-slate-200/60 flex items-center gap-2 text-xs">
                            <Laptop class="size-3.5 text-slate-400" />
                            <span class="text-[11px] font-bold text-slate-700">{{ document.device_name }}</span>
                        </div>
                    </div>

                    <!-- Remarks / Description -->
                    <div v-if="document.remark" class="p-4 rounded-2xl border border-slate-100 bg-white shadow-xs space-y-2">
                        <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 flex items-center gap-1.5">
                            <InfoIcon class="size-3 text-[#003628]" />
                            Keterangan / Catatan
                        </span>
                        <p class="text-xs text-slate-600 leading-relaxed">
                            {{ document.remark }}
                        </p>
                    </div>

                    <!-- PDF Preview embed if available -->
                    <div v-if="document.has_pdf && document.pdf_url" class="space-y-2">
                        <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 flex items-center gap-1.5">
                            <FileText class="size-3 text-[#003628]" />
                            Pratinjau Berkas Digital
                        </span>
                        <div class="rounded-2xl border border-slate-200 overflow-hidden bg-slate-100 aspect-3/4 max-h-[380px]">
                            <iframe
                                :src="document.pdf_url + '#toolbar=0'"
                                class="w-full h-full border-0"
                                title="PDF Preview"
                            />
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="p-4 border-t border-slate-100 bg-slate-50/50 flex justify-end">
                    <button
                        @click="close"
                        class="h-9 px-4 rounded-xl border border-slate-200 bg-white text-xs font-bold text-slate-700 hover:bg-slate-50 hover:text-slate-900 transition-colors shadow-xs cursor-pointer"
                    >
                        Tutup Panel
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
