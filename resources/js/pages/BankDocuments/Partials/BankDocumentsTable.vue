<script setup lang="ts">
/* eslint-disable vue/no-mutating-props */
import { Link } from '@inertiajs/vue3';
import { onClickOutside } from '@vueuse/core';
import {
    LucideDownload as Download,
    LucideSearch as Search,
    LucideSlidersHorizontal as SlidersHorizontal,
    LucideFolderArchive as FolderArchive,
    LucideFolder as StbIcon,
    LucideClipboardList as LoanIcon,
    LucideSearchCheck as InspectionIcon,
    LucideFileText as FileText,
    LucideUser as UserIcon,
    LucideRefreshCw as RefreshCw,
    LucideEye as EyeIcon,
    LucideCheckCircle2 as CheckCircle,
    LucideClock as ClockIcon,
    LucideExternalLink as ExternalLink,
    LucidePrinter as Printer,
} from 'lucide-vue-next';
import { ref } from 'vue';
import type { BankDocumentItem } from '@/pages/BankDocuments/Partials/BankDocumentDetailSheet.vue';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface DocTypeOption {
    key: string;
    label: string;
}

interface StatusOption {
    key: string;
    label: string;
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
    documentTypes: DocTypeOption[];
    statuses: StatusOption[];
    summaryText: string;
    exportUrl: string;
    applyDatePreset: (preset: 'today' | 'last7Days' | 'thisMonth') => void;
    clearDateFilters: () => void;
    isPresetActive: (preset: 'today' | 'last7Days' | 'thisMonth') => boolean;
    activeFilterCount: number;
}>();

const emit = defineEmits<{
    (e: 'open-detail', doc: BankDocumentItem): void;
}>();

const showFilters = ref(false);
const filterPanelRef = ref<HTMLElement | null>(null);

onClickOutside(filterPanelRef, () => {
    showFilters.value = false;
});

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
    <div class="space-y-4">
        <!-- TABLE CARD CONTAINER -->
        <div class="bg-white rounded-[32px] border border-slate-200/60 shadow-xl shadow-slate-200/50 p-6 lg:p-8">
            <!-- Toolbar Section -->
            <div class="mb-8 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <div class="relative w-full lg:max-w-md">
                    <Search class="absolute left-4 top-1/2 -translate-y-1/2 size-4 text-slate-400" />
                    <input
                        v-model="filterForm.search"
                        type="text"
                        placeholder="Cari nomor dokumen, penerima, departemen..."
                        class="w-full h-10 pl-11 pr-4 rounded-xl border border-slate-200 bg-slate-50/30 text-xs text-slate-900 placeholder:text-slate-400 focus:border-[#003628]/50 focus:bg-white transition-all outline-none shadow-xs"
                    />
                </div>

                <div class="flex items-center gap-2">
                    <a
                        :href="exportUrl"
                        class="h-10 px-3 rounded-xl border border-slate-200 bg-white flex items-center justify-center text-slate-500 hover:text-slate-900 hover:bg-slate-50 transition-all active:scale-95 shadow-xs"
                        title="Ekspor CSV"
                    >
                        <Download class="size-4" />
                    </a>

                    <!-- Filter Dropdown Button & Popover -->
                    <div ref="filterPanelRef" class="relative">
                        <button
                            type="button"
                            class="h-10 px-4 rounded-xl border border-slate-200 bg-white flex items-center gap-2.5 text-xs font-bold text-slate-600 hover:text-slate-900 hover:bg-slate-50 transition-all active:scale-95 shadow-xs cursor-pointer"
                            @click="showFilters = !showFilters"
                        >
                            <SlidersHorizontal class="size-4 text-slate-400" />
                            <span>Filter</span>
                            <span
                                v-if="activeFilterCount"
                                class="flex h-4 w-4 items-center justify-center rounded-full bg-[#003628] text-[9px] font-black text-white"
                            >
                                {{ activeFilterCount }}
                            </span>
                        </button>

                        <Transition
                            enter-active-class="transition duration-300 ease-out"
                            enter-from-class="opacity-0 translate-y-4 scale-95"
                            enter-to-class="opacity-100 translate-y-0 scale-100"
                            leave-active-class="transition duration-200 ease-in"
                            leave-from-class="opacity-100 translate-y-0 scale-100"
                            leave-to-class="opacity-0 translate-y-4 scale-95"
                        >
                            <div
                                v-if="showFilters"
                                class="absolute top-full right-0 z-50 mt-4 w-88 rounded-[32px] border border-slate-200 bg-white p-6 shadow-2xl backdrop-blur-xl overflow-hidden"
                            >
                                <div class="flex items-center justify-between mb-6">
                                    <h3 class="text-[10px] font-black uppercase tracking-widest text-slate-400">Filter Bank Dokumen</h3>
                                    <button
                                        @click="
                                            filterForm.search = '';
                                            filterForm.filter_type = '';
                                            filterForm.filter_status = '';
                                            clearDateFilters();
                                            showFilters = false;
                                        "
                                        class="text-[10px] font-black uppercase tracking-widest text-[#003628] hover:opacity-70 transition-colors flex items-center gap-1.5 cursor-pointer"
                                    >
                                        <RefreshCw class="size-3" /> Reset
                                    </button>
                                </div>

                                <div class="space-y-4">
                                    <!-- Document Type Filter -->
                                    <div class="space-y-1.5">
                                        <label class="text-[9px] font-black uppercase tracking-widest text-slate-500 ml-1">Jenis Dokumen</label>
                                        <select
                                            v-model="filterForm.filter_type"
                                            class="w-full h-9 px-3 rounded-xl border border-slate-200 bg-slate-50 text-[11px] font-medium text-slate-700 outline-none focus:border-[#003628]/50 focus:bg-white"
                                        >
                                            <option value="">Semua Jenis Dokumen</option>
                                            <option v-for="t in documentTypes" :key="t.key" :value="t.key">{{ t.label }}</option>
                                        </select>
                                    </div>

                                    <!-- Status Filter -->
                                    <div class="space-y-1.5">
                                        <label class="text-[9px] font-black uppercase tracking-widest text-slate-500 ml-1">Status Dokumen</label>
                                        <select
                                            v-model="filterForm.filter_status"
                                            class="w-full h-9 px-3 rounded-xl border border-slate-200 bg-slate-50 text-[11px] font-medium text-slate-700 outline-none focus:border-[#003628]/50 focus:bg-white"
                                        >
                                            <option value="">Semua Status</option>
                                            <option v-for="st in statuses" :key="st.key" :value="st.key">{{ st.label }}</option>
                                        </select>
                                    </div>

                                    <!-- Date Presets -->
                                    <div class="pt-3 border-t border-slate-100 space-y-2">
                                        <label class="text-[9px] font-black uppercase tracking-widest text-slate-500 ml-1">Preset Rentang Tanggal</label>
                                        <div class="grid grid-cols-3 gap-2">
                                            <button
                                                type="button"
                                                @click="applyDatePreset('today')"
                                                class="h-7 px-2 rounded-lg text-[10px] font-bold border transition-all cursor-pointer"
                                                :class="isPresetActive('today') ? 'bg-[#003628] text-white border-[#003628]' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100'"
                                            >
                                                Hari Ini
                                            </button>
                                            <button
                                                type="button"
                                                @click="applyDatePreset('last7Days')"
                                                class="h-7 px-2 rounded-lg text-[10px] font-bold border transition-all cursor-pointer"
                                                :class="isPresetActive('last7Days') ? 'bg-[#003628] text-white border-[#003628]' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100'"
                                            >
                                                7 Hari
                                            </button>
                                            <button
                                                type="button"
                                                @click="applyDatePreset('thisMonth')"
                                                class="h-7 px-2 rounded-lg text-[10px] font-bold border transition-all cursor-pointer"
                                                :class="isPresetActive('thisMonth') ? 'bg-[#003628] text-white border-[#003628]' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100'"
                                            >
                                                Bulan Ini
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Date Inputs -->
                                    <div class="grid grid-cols-2 gap-3 pt-2 border-t border-slate-100">
                                        <div class="space-y-1">
                                            <label class="text-[9px] font-black uppercase tracking-widest text-slate-400">Dari</label>
                                            <input
                                                v-model="filterForm.from_date"
                                                type="date"
                                                class="w-full h-9 px-2 rounded-xl border border-slate-200 bg-slate-50 text-[10px] font-medium text-slate-700 outline-none focus:border-[#003628]/50 focus:bg-white"
                                            />
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-[9px] font-black uppercase tracking-widest text-slate-400">Hingga</label>
                                            <input
                                                v-model="filterForm.to_date"
                                                type="date"
                                                class="w-full h-9 px-2 rounded-xl border border-slate-200 bg-slate-50 text-[10px] font-medium text-slate-700 outline-none focus:border-[#003628]/50 focus:bg-white"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </Transition>
                    </div>
                </div>
            </div>

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
    </div>
</template>
