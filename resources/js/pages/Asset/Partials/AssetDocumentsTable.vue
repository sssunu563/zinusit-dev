<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import {
    FileText,
    Download,
    Eye,
    Plus,
    Search,
    X,
    Folder,
    ClipboardList,
    SearchCheck,
    Briefcase,
    Calendar,
    Layers,
    User,
    ExternalLink,
    FileSpreadsheet,
    FileImage,
    File,
} from 'lucide-vue-next';
import AppPagination from '@/components/AppPagination.vue';
import type { ActivityDetailItem } from '@/pages/Asset/Partials/AssetActivityDetailSheet.vue';

export interface AssetFile {
    id: number;
    filename: string;
    download_url: string;
    created_by: string;
    date: string;
    notes: string;
    doc_no?: string | null;
    doc_url?: string | null;
    form_type?: string | null;
    form_name?: string | null;
}

interface Props {
    files: AssetFile[];
    title?: string;
    subtitle?: string;
}

const props = withDefaults(defineProps<Props>(), {
    title: 'DOKUMEN TERLAMPIR',
    subtitle:
        'Daftar berkas lampiran, formulir PDF, dan berkas legalitas aset.',
});

const emit = defineEmits<{
    (e: 'upload-document'): void;
    (e: 'open-pdf', url: string): void;
    (e: 'open-detail', item: ActivityDetailItem): void;
}>();

const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = 10;

// Filtered Files
const filteredFiles = computed(() => {
    if (!searchQuery.value.trim()) return props.files;
    const q = searchQuery.value.toLowerCase().trim();
    return props.files.filter((f) => {
        return (
            (f.filename && f.filename.toLowerCase().includes(q)) ||
            (f.doc_no && f.doc_no.toLowerCase().includes(q)) ||
            (f.form_name && f.form_name.toLowerCase().includes(q)) ||
            (f.created_by && f.created_by.toLowerCase().includes(q)) ||
            (f.notes && f.notes.toLowerCase().includes(q)) ||
            (f.date && f.date.toLowerCase().includes(q))
        );
    });
});

// Pagination
const totalPages = computed(
    () => Math.ceil(filteredFiles.value.length / itemsPerPage) || 1,
);

const paginatedFiles = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    return filteredFiles.value.slice(start, start + itemsPerPage);
});

// Summary Stats
const totalDocumentsCount = computed(() => props.files.length);

const systemFormsCount = computed(
    () =>
        props.files.filter((f) => Boolean(f.doc_no || f.form_name || f.doc_url))
            .length,
);

const latestUploadDate = computed(() => {
    if (!props.files.length) return '—';
    return props.files[0]?.date || '—';
});

// Helper for file/form icon
function getFileIcon(filename: string, formType?: string | null) {
    const ft = (formType || '').toLowerCase();
    if (ft === 'stb') return Folder;
    if (ft === 'peminjaman') return ClipboardList;
    if (ft === 'inspection') return SearchCheck;
    if (ft === 'ticket' || ft === 'helpdesk') return Briefcase;

    const ext = filename.split('.').pop()?.toLowerCase();
    if (ext === 'pdf') return FileText;
    if (['xlsx', 'xls', 'csv'].includes(ext || '')) return FileSpreadsheet;
    if (['png', 'jpg', 'jpeg', 'webp', 'svg'].includes(ext || ''))
        return FileImage;
    return File;
}

function isPdfFile(filename: string) {
    return /\.pdf$/i.test(filename);
}

function handleOpenDetail(file: AssetFile) {
    const detailItem: ActivityDetailItem = {
        id: file.id,
        action_type: 'Dokumen Terlampir',
        action_label: 'Dokumen Terlampir',
        file_name: file.filename,
        download_url: file.download_url,
        file_url: file.download_url,
        pdf_url: isPdfFile(file.filename) ? file.download_url : null,
        doc_no: file.doc_no,
        doc_url: file.doc_url,
        form_type: file.form_type,
        form_name: file.form_name,
        date: file.date,
        user: file.created_by,
        note: file.notes,
    };
    emit('open-detail', detailItem);
}
</script>

<template>
    <div class="space-y-4">
        <!-- Header & Action Toolbar -->
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="space-y-0.5">
                <div class="flex items-center gap-2">
                    <div
                        class="flex size-6 items-center justify-center rounded-lg bg-[#003628]/10 text-[#003628]"
                    >
                        <FileText class="size-3.5" />
                    </div>
                    <h3
                        class="text-xs font-black tracking-widest text-[#003628] uppercase"
                    >
                        {{ title }}
                    </h3>
                    <span
                        class="rounded-full bg-[#003628]/10 px-2 py-0.5 text-[10px] font-black text-[#003628] tabular-nums"
                    >
                        {{ totalDocumentsCount }}
                    </span>
                </div>
                <p class="text-[11px] text-slate-400">
                    {{ subtitle }}
                </p>
            </div>

            <div class="flex items-center gap-2">
                <!-- Search Box -->
                <div class="relative w-full sm:w-64">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-slate-400"
                    />
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Cari file, form, pengunggah..."
                        class="h-8.5 w-full rounded-xl border border-slate-200 bg-white pr-7 pl-8.5 text-xs font-medium text-slate-800 placeholder-slate-400 transition focus:border-[#003628] focus:ring-1 focus:ring-[#003628] focus:outline-hidden"
                        @input="currentPage = 1"
                    />
                    <button
                        v-if="searchQuery"
                        type="button"
                        class="absolute top-1/2 right-2 -translate-y-1/2 rounded-full p-0.5 text-slate-400 hover:text-slate-600"
                        @click="
                            searchQuery = '';
                            currentPage = 1;
                        "
                    >
                        <X class="size-3.5" />
                    </button>
                </div>
            </div>
        </div>

        <!-- 3 Summary Stat Cards -->
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <!-- Card 1: Total Dokumen -->
            <div
                class="flex items-center gap-3.5 rounded-2xl border border-slate-100 bg-white p-4 shadow-xs"
            >
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700"
                >
                    <FileText class="size-5" />
                </div>
                <div class="min-w-0">
                    <p
                        class="text-[10px] font-black tracking-widest text-slate-400 uppercase"
                    >
                        Total Dokumen
                    </p>
                    <p class="text-base font-black text-slate-800">
                        {{ totalDocumentsCount }}
                        <span class="text-xs font-semibold text-slate-500"
                            >Berkas</span
                        >
                    </p>
                    <p class="text-[10px] text-slate-400">
                        Arsip lampiran aset
                    </p>
                </div>
            </div>

            <!-- Card 2: Formulir Sistem -->
            <div
                class="flex items-center gap-3.5 rounded-2xl border border-slate-100 bg-white p-4 shadow-xs"
            >
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-sky-50 text-sky-700"
                >
                    <Layers class="size-5" />
                </div>
                <div class="min-w-0">
                    <p
                        class="text-[10px] font-black tracking-widest text-slate-400 uppercase"
                    >
                        Formulir Terkait
                    </p>
                    <p class="text-base font-black text-slate-800">
                        {{ systemFormsCount }}
                        <span class="text-xs font-semibold text-slate-500"
                            >Formulir</span
                        >
                    </p>
                    <p class="text-[10px] text-slate-400">
                        STB, BAST & formulir legal
                    </p>
                </div>
            </div>

            <!-- Card 3: Dokumen Terakhir -->
            <div
                class="flex items-center gap-3.5 rounded-2xl border border-slate-100 bg-white p-4 shadow-xs"
            >
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-700"
                >
                    <Calendar class="size-5" />
                </div>
                <div class="min-w-0">
                    <p
                        class="text-[10px] font-black tracking-widest text-slate-400 uppercase"
                    >
                        Unggahan Terakhir
                    </p>
                    <p class="truncate text-xs font-black text-slate-800">
                        {{ latestUploadDate }}
                    </p>
                    <p class="text-[10px] text-slate-400">
                        Tanggal berkas teranyar
                    </p>
                </div>
            </div>
        </div>

        <!-- Table Container -->
        <div
            class="overflow-hidden rounded-2xl border border-slate-200/70 bg-white shadow-xs"
        >
            <div
                v-if="filteredFiles.length === 0"
                class="flex flex-col items-center justify-center px-4 py-16 text-center"
            >
                <div
                    class="mb-3 flex size-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"
                >
                    <FileText class="size-6" />
                </div>
                <p class="text-sm font-bold text-slate-700">
                    {{
                        searchQuery
                            ? 'Tidak ada dokumen yang sesuai dengan pencarian'
                            : 'Belum ada dokumen terunggah'
                    }}
                </p>
                <p class="mt-1 max-w-sm text-xs text-slate-400">
                    {{
                        searchQuery
                            ? `Tidak ditemukan dokumen dengan kata kunci "${searchQuery}".`
                            : 'Unggah dokumen manual, formulir serah terima, atau berkas pendukung aset ini.'
                    }}
                </p>
                <button
                    v-if="searchQuery"
                    type="button"
                    class="mt-3 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-bold text-slate-600 hover:bg-slate-50"
                    @click="
                        searchQuery = '';
                        currentPage = 1;
                    "
                >
                    Reset Pencarian
                </button>
                <button
                    v-else
                    type="button"
                    class="mt-4 flex items-center gap-1.5 rounded-xl bg-[#003628] px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-[#003628]/90"
                    @click="$emit('upload-document')"
                >
                    <Plus class="size-3.5" />
                    <span>Upload Dokumen Pertama</span>
                </button>
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full border-collapse text-left">
                    <thead>
                        <tr
                            class="border-b border-slate-100 bg-slate-50/60 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                        >
                            <th class="px-5 py-3.5">Dokumen & Formulir</th>
                            <th class="px-5 py-3.5">Pengunggah & Tanggal</th>
                            <th class="px-5 py-3.5">Catatan / Deskripsi</th>
                            <th class="px-4 py-3.5 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="file in paginatedFiles"
                            :key="file.id"
                            class="group transition-colors hover:bg-slate-50/70"
                        >
                            <!-- Dokumen & Formulir -->
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-[#003628] shadow-2xs transition-all group-hover:bg-[#003628] group-hover:text-white"
                                    >
                                        <component
                                            :is="
                                                getFileIcon(
                                                    file.filename,
                                                    file.form_type,
                                                )
                                            "
                                            class="size-5"
                                        />
                                    </div>
                                    <div class="min-w-0 space-y-0.5">
                                        <div
                                            class="flex flex-wrap items-center gap-1.5"
                                        >
                                            <span
                                                class="rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-black tracking-wider text-slate-500 uppercase"
                                            >
                                                {{
                                                    file.form_name || 'Dokumen'
                                                }}
                                            </span>
                                            <Link
                                                v-if="file.doc_url"
                                                :href="file.doc_url"
                                                class="inline-flex items-center gap-0.5 font-mono text-[11px] font-black text-[#003628] hover:underline"
                                            >
                                                <span>{{
                                                    file.doc_no ||
                                                    'Buka Dokumen'
                                                }}</span>
                                                <ExternalLink
                                                    class="size-2.5 opacity-70"
                                                />
                                            </Link>
                                        </div>
                                        <p
                                            class="max-w-xs truncate text-xs font-bold text-slate-900 sm:max-w-sm"
                                            :title="file.filename"
                                        >
                                            {{ file.filename }}
                                        </p>
                                    </div>
                                </div>
                            </td>

                            <!-- Pengunggah & Tanggal -->
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <div class="space-y-0.5">
                                    <div class="flex items-center gap-1.5">
                                        <div
                                            class="flex size-4.5 items-center justify-center rounded-full bg-slate-100 text-slate-500"
                                        >
                                            <User class="size-2.5" />
                                        </div>
                                        <span
                                            class="text-xs font-bold text-slate-800"
                                        >
                                            {{
                                                file.created_by &&
                                                file.created_by !== '-'
                                                    ? file.created_by
                                                    : 'Sistem'
                                            }}
                                        </span>
                                    </div>
                                    <p
                                        class="text-[10px] font-medium text-slate-400 tabular-nums"
                                    >
                                        {{ file.date }}
                                    </p>
                                </div>
                            </td>

                            <!-- Catatan -->
                            <td class="px-5 py-3.5">
                                <p
                                    class="line-clamp-2 max-w-[320px] text-xs leading-relaxed text-slate-600"
                                    :title="file.notes || '—'"
                                >
                                    {{ file.notes || '—' }}
                                </p>
                            </td>

                            <!-- Aksi -->
                            <td
                                class="px-4 py-3.5 text-center whitespace-nowrap"
                            >
                                <div
                                    class="flex items-center justify-center gap-1.5"
                                >
                                    <button
                                        type="button"
                                        class="inline-flex h-7.5 items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 text-[11px] font-bold text-slate-700 shadow-2xs transition hover:bg-slate-50 hover:text-slate-900 active:scale-95"
                                        title="Lihat Detail Dokumen"
                                        @click="handleOpenDetail(file)"
                                    >
                                        <Eye class="size-3.5 text-slate-400" />
                                        <span>Detail</span>
                                    </button>

                                    <button
                                        v-if="isPdfFile(file.filename)"
                                        type="button"
                                        class="inline-flex h-7.5 w-7.5 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 shadow-2xs transition hover:border-[#003628]/30 hover:bg-emerald-50 hover:text-[#003628] active:scale-95"
                                        title="Pratinjau PDF"
                                        @click="
                                            $emit('open-pdf', file.download_url)
                                        "
                                    >
                                        <FileText class="size-3.5" />
                                    </button>

                                    <a
                                        v-if="file.download_url"
                                        :href="file.download_url"
                                        download
                                        class="inline-flex h-7.5 w-7.5 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 shadow-2xs transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900 active:scale-95"
                                        title="Unduh File"
                                    >
                                        <Download class="size-3.5" />
                                    </a>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Built-in Pagination -->
            <div
                v-if="totalPages > 1"
                class="border-t border-slate-100 px-4 py-3"
            >
                <AppPagination
                    :current-page="currentPage"
                    :total-pages="totalPages"
                    :items-per-page="itemsPerPage"
                    :total-items="filteredFiles.length"
                    @update:current-page="(p) => (currentPage = p)"
                />
            </div>
        </div>
    </div>
</template>
