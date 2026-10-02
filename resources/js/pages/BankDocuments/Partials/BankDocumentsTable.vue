<script setup lang="ts">
/* eslint-disable vue/no-mutating-props */
import { Link } from '@inertiajs/vue3';
import {
    LucideDownload as Download,
    LucideFolderArchive as FolderArchive,
    LucideFolder as StbIcon,
    LucideClipboardList as LoanIcon,
    LucideSearchCheck as InspectionIcon,
    LucideFileText as FileText,
    LucideEye as EyeIcon,
    LucideCheckCircle2 as CheckCircle,
    LucideClock as ClockIcon,
    LucideExternalLink as ExternalLink,
    LucidePrinter as Printer,
} from 'lucide-vue-next';
import type { BankDocumentItem } from '@/pages/BankDocuments/Partials/BankDocumentDetailSheet.vue';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

const props = defineProps<{
    documents: {
        data: BankDocumentItem[];
        links: PaginationLink[];
    };
    filterForm: {
        search: string;
        filter_type: string;
        filter_status: string;
        from_date: string;
        to_date: string;
    };
    summaryText: string;
}>();

const emit = defineEmits<{
    (e: 'open-detail', doc: BankDocumentItem): void;
}>();

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
    <div>
        <!-- Desktop Table -->
        <div class="hidden md:block overflow-hidden rounded-2xl border border-slate-100">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/50">
                            <th class="px-6 py-3.5 text-[10px] font-black uppercase tracking-widest text-slate-400">Nomor &amp; Jenis Dokumen</th>
                            <th class="px-6 py-3.5 text-[10px] font-black uppercase tracking-widest text-slate-400">Penerima / Pemilik</th>
                            <th class="px-6 py-3.5 text-[10px] font-black uppercase tracking-widest text-slate-400">Tanggal Terbit</th>
                            <th class="px-6 py-3.5 text-[10px] font-black uppercase tracking-widest text-slate-400">Status</th>
                            <th class="px-6 py-3.5 text-[10px] font-black uppercase tracking-widest text-slate-400 text-center">Berkas PDF</th>
                            <th class="px-6 py-3.5 text-[10px] font-black uppercase tracking-widest text-slate-400 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr
                            v-for="doc in documents.data"
                            :key="doc.doc_no"
                            class="group hover:bg-slate-50/50 transition-colors"
                        >
                            <!-- Nomor & Jenis Dokumen -->
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="size-8 rounded-lg bg-[#003628]/10 text-[#003628] flex items-center justify-center shrink-0">
                                        <component :is="getDocIcon(doc.doc_type)" class="size-4" />
                                    </div>
                                    <div class="flex flex-col min-w-0">
                                        <span class="text-xs font-black text-slate-900 font-mono tracking-tight">{{ doc.doc_no }}</span>
                                        <span class="text-[10px] text-slate-400 font-medium flex items-center gap-1">
                                            {{ doc.doc_type_label }}
                                            <span v-if="doc.sub_type" class="text-slate-300">· {{ doc.sub_type }}</span>
                                        </span>
                                    </div>
                                </div>
                            </td>

                            <!-- Penerima / Pemilik -->
                            <td class="px-6 py-4">
                                <div class="flex flex-col min-w-0">
                                    <span class="text-xs font-bold text-slate-900 truncate max-w-[220px]">{{ doc.user_name }}</span>
                                    <div class="flex items-center gap-1.5 text-[10px] text-slate-400 font-medium truncate max-w-[220px]">
                                        <span v-if="doc.user_dept">{{ doc.user_dept }}</span>
                                        <span v-if="doc.user_company && doc.user_dept">·</span>
                                        <span v-if="doc.user_company">{{ doc.user_company }}</span>
                                    </div>
                                </div>
                            </td>

                            <!-- Tanggal Terbit -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex flex-col">
                                    <span class="text-[12px] font-bold text-slate-800 tabular-nums">
                                        {{ doc.created_at.split(' ')[0] }}
                                    </span>
                                    <span class="text-[10px] text-slate-400 font-mono font-medium mt-0.5">
                                        {{ doc.created_at.split(' ')[1] || '' }}
                                    </span>
                                </div>
                            </td>

                            <!-- Status -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-widest border"
                                    :class="doc.status === 'completed'
                                        ? 'bg-[#003628]/5 text-[#003628] border-[#003628]/10'
                                        : 'bg-amber-50 text-amber-600 border-amber-100'"
                                >
                                    <component :is="doc.status === 'completed' ? CheckCircle : ClockIcon" class="size-3" />
                                    {{ doc.status_label }}
                                </span>
                            </td>

                            <!-- Berkas PDF -->
                            <td class="px-6 py-4 text-center whitespace-nowrap">
                                <a
                                    v-if="doc.has_pdf && doc.pdf_url"
                                    :href="doc.pdf_url"
                                    target="_blank"
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-mono text-[11px] font-bold border border-emerald-200 transition-colors cursor-pointer"
                                    title="Unduh / Pratinjau PDF"
                                >
                                    <Download class="size-3" />
                                    <span>PDF Digital</span>
                                </a>
                                <a
                                    v-else
                                    :href="doc.print_url"
                                    target="_blank"
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-50 hover:bg-slate-100 text-slate-500 font-mono text-[10px] font-medium border border-slate-200 transition-colors cursor-pointer"
                                    title="Cetak via Browser"
                                >
                                    <Printer class="size-3" />
                                    <span>Cetak</span>
                                </a>
                            </td>

                            <!-- Aksi -->
                            <td class="px-6 py-4 text-center whitespace-nowrap">
                                <div class="flex items-center justify-center gap-1.5">
                                    <button
                                        type="button"
                                        @click="emit('open-detail', doc)"
                                        class="h-8 px-2.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 hover:text-slate-900 text-xs font-bold transition-all active:scale-95 shadow-xs inline-flex items-center gap-1 cursor-pointer"
                                        title="Lihat Pratinjau & Detail"
                                    >
                                        <EyeIcon class="size-3.5" />
                                        <span>Detail</span>
                                    </button>

                                    <Link
                                        :href="doc.view_url"
                                        class="h-8 w-8 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-500 hover:text-slate-900 transition-all flex items-center justify-center shadow-xs cursor-pointer"
                                        title="Buka Form Sumber"
                                    >
                                        <ExternalLink class="size-3.5" />
                                    </Link>
                                </div>
                            </td>
                        </tr>

                        <!-- Empty State -->
                        <tr v-if="documents.data.length === 0">
                            <td colspan="6" class="px-6 py-20 text-center text-slate-400 italic text-sm">
                                Belum ada dokumen yang sesuai dengan filter pencarian Anda.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Mobile View List -->
            <div class="md:hidden space-y-3">
                <div
                    v-for="doc in documents.data"
                    :key="doc.doc_no"
                    class="p-4 rounded-2xl border border-slate-200/80 bg-slate-50/50 space-y-3"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <component :is="getDocIcon(doc.doc_type)" class="size-4 text-[#003628]" />
                            <span class="text-xs font-black text-slate-900 font-mono">{{ doc.doc_no }}</span>
                        </div>
                        <span
                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-widest border"
                            :class="doc.status === 'completed'
                                ? 'bg-[#003628]/5 text-[#003628] border-[#003628]/10'
                                : 'bg-amber-50 text-amber-600 border-amber-100'"
                        >
                            {{ doc.status_label }}
                        </span>
                    </div>

                    <div class="space-y-0.5">
                        <p class="text-xs font-bold text-slate-800">{{ doc.user_name }}</p>
                        <p class="text-[10px] text-slate-500">
                            {{ doc.doc_type_label }} · {{ doc.user_dept || '-' }}
                        </p>
                    </div>

                    <div class="flex items-center justify-between text-[10px] text-slate-400 font-mono pt-2 border-t border-slate-200/60">
                        <span>{{ doc.created_at.split(' ')[0] }}</span>
                        <div class="flex items-center gap-3">
                            <a
                                v-if="doc.has_pdf && doc.pdf_url"
                                :href="doc.pdf_url"
                                target="_blank"
                                class="font-sans font-bold text-emerald-700 flex items-center gap-1"
                            >
                                <Download class="size-3" /> Unduh
                            </a>
                            <button
                                type="button"
                                @click="emit('open-detail', doc)"
                                class="font-sans font-bold text-[#003628] hover:underline cursor-pointer"
                            >
                                Detail →
                            </button>
                        </div>
                    </div>
                </div>

                <div v-if="documents.data.length === 0" class="p-8 text-center text-slate-400 italic text-sm bg-slate-50/50 rounded-2xl border border-slate-200">
                    Belum ada dokumen yang sesuai dengan filter pencarian Anda.
                </div>
            </div>

            <!-- Table Footer -->
            <div class="px-2 py-4 flex flex-col sm:flex-row items-center justify-between gap-4 border-t border-slate-100 mt-4">
                <span class="text-[11px] font-black uppercase tracking-widest text-slate-400">{{ summaryText }}</span>
                <nav v-if="documents.links.length > 3" class="flex items-center gap-1.5">
                    <Link
                        v-for="(link, j) in documents.links"
                        :key="j"
                        :href="link.url || '#'"
                        class="h-8 min-w-[32px] px-2 rounded-lg flex items-center justify-center text-[11px] font-bold transition-all border border-slate-100"
                        :class="link.active ? 'bg-[#003628] text-white shadow-lg shadow-emerald-950/20 border-[#003628]' : 'bg-white text-slate-500 hover:bg-slate-50'"
                        v-html="link.label"
                    />
                </nav>
            </div>
        </div>
</template>
