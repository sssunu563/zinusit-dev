<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import {
    History,
    Activity,
    Clock,
    User,
    Search,
    X,
    Folder,
    ClipboardList,
    SearchCheck,
    Briefcase,
    FileText,
    ExternalLink,
    Eye,
    Pencil,
    Download,
    RotateCcw,
    Plus,
    AlertTriangle,
    Tag,
    Hash,
} from 'lucide-vue-next';
import AppPagination from '@/components/AppPagination.vue';
import type { ActivityDetailItem } from '@/pages/Asset/Partials/AssetActivityDetailSheet.vue';

export interface ActivityRecord {
    id: string | number;
    action_type: string;
    action_label?: string | null;
    user: string;
    user_id?: number | null;
    user_image?: string;
    target: string;
    target_type?: string;
    note: string;
    date: string;
    file_status?: 'success' | 'failed' | null;
    file_name?: string;
    file_url?: string | null;
    doc_no?: string | null;
    doc_url?: string | null;
    pdf_url?: string | null;
    form_type?: string | null;
    form_name?: string | null;
    role?: string | null;
    quantity?: number | string | null;
    changed?: string | null;
    log_meta?: Record<string, any> | null;
}

interface Props {
    history: ActivityRecord[];
    title?: string;
    subtitle?: string;
}

const props = withDefaults(defineProps<Props>(), {
    title: 'RIWAYAT LOG & AKTIVITAS',
    subtitle:
        'Catatan jejak audit, transaksi formulir, mutasi, dan perubahan data aset.',
});

const emit = defineEmits<{
    (e: 'open-pdf', url: string): void;
    (e: 'open-detail', item: ActivityRecord): void;
}>();

const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = 15;

// Filtered Records
const filteredHistory = computed(() => {
    if (!searchQuery.value.trim()) return props.history;
    const q = searchQuery.value.toLowerCase().trim();
    return props.history.filter((rec) => {
        return (
            (rec.user && rec.user.toLowerCase().includes(q)) ||
            (rec.action_label && rec.action_label.toLowerCase().includes(q)) ||
            (rec.action_type && rec.action_type.toLowerCase().includes(q)) ||
            (rec.form_name && rec.form_name.toLowerCase().includes(q)) ||
            (rec.doc_no && rec.doc_no.toLowerCase().includes(q)) ||
            (rec.note && rec.note.toLowerCase().includes(q)) ||
            (rec.target && rec.target.toLowerCase().includes(q)) ||
            (rec.role && rec.role.toLowerCase().includes(q)) ||
            (rec.date && rec.date.toLowerCase().includes(q))
        );
    });
});

// Pagination
const totalPages = computed(
    () => Math.ceil(filteredHistory.value.length / itemsPerPage) || 1,
);

const paginatedHistory = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    return filteredHistory.value.slice(start, start + itemsPerPage);
});

// Summary Stats
const totalHistoryCount = computed(() => props.history.length);

const latestActivity = computed(() => {
    if (!props.history.length) return { label: '—', date: 'Belum ada data' };
    const first = props.history[0];
    return {
        label: first.action_label || first.action_type || 'Aktivitas',
        date: first.date || '—',
    };
});

const lastUser = computed(() => {
    if (!props.history.length) return '—';
    const first = props.history[0];
    return first.user || 'Sistem';
});

// Icon helpers
function getFormIcon(formType?: string | null) {
    switch ((formType || '').toLowerCase()) {
        case 'stb':
            return Folder;
        case 'peminjaman':
            return ClipboardList;
        case 'inspection':
            return SearchCheck;
        case 'ticket':
        case 'helpdesk':
            return Briefcase;
        default:
            return FileText;
    }
}

function getActionIcon(actionType?: string | null) {
    const a = (actionType || '').toLowerCase();
    if (a.includes('update') || a.includes('edit')) return Pencil;
    if (a.includes('upload') || a.includes('download')) return Download;
    if (a.includes('check')) return RotateCcw;
    if (a.includes('create') || a.includes('add')) return Plus;
    if (a.includes('delete') || a.includes('destroy')) return AlertTriangle;
    if (a.includes('stb') || a.includes('mutasi')) return Folder;
    if (a.includes('inspection')) return SearchCheck;
    return Activity;
}

// Action badge styling
function getActionBadgeClass(actionType?: string | null) {
    const a = (actionType || '').toLowerCase();
    if (
        [
            'created',
            'create',
            'stb_complete',
            'completed',
            'diselesaikan',
            'dibuat',
        ].some((k) => a.includes(k))
    ) {
        return 'bg-emerald-50 text-emerald-700 border-emerald-200/70';
    }
    if (
        [
            'checkout',
            'updated',
            'update',
            'edit',
            'sign',
            'tanda tangan',
            'diperbarui',
        ].some((k) => a.includes(k))
    ) {
        return 'bg-amber-50 text-amber-700 border-amber-200/70';
    }
    if (
        [
            'checkin',
            'mutasi',
            'kembali',
            'serah terima',
            'peminjaman',
            'penyerahan',
        ].some((k) => a.includes(k))
    ) {
        return 'bg-sky-50 text-sky-700 border-sky-200/70';
    }
    if (
        ['deleted', 'delete', 'cancelled', 'batal', 'failed'].some((k) =>
            a.includes(k),
        )
    ) {
        return 'bg-rose-50 text-rose-700 border-rose-200/70';
    }
    return 'bg-slate-100 text-slate-700 border-slate-200';
}

// Note Parser for STB & piped structured strings
interface ParsedActivityNote {
    isStructured: boolean;
    stbNo?: string;
    docId?: string;
    item?: string;
    serial?: string;
    assignee?: string;
    refTag?: string;
    comment?: string;
    raw: string;
}

function parseActivityNote(noteStr?: string | null): ParsedActivityNote {
    if (!noteStr || noteStr.trim() === '') {
        return { isStructured: false, raw: '—' };
    }
    const trimmed = noteStr.trim().replace(/^\*+|\*+$/g, '');
    if (
        trimmed.includes('|') &&
        (trimmed.includes('STB') ||
            trimmed.includes('Doc ID') ||
            trimmed.includes('Assign:') ||
            trimmed.includes('SN:'))
    ) {
        const parts = trimmed.split('|').map((p) => p.trim());
        const res: ParsedActivityNote = { isStructured: true, raw: trimmed };

        for (const p of parts) {
            if (
                p.startsWith('STB-') ||
                p.startsWith('STB_') ||
                p.startsWith('STB:')
            ) {
                res.stbNo = p
                    .replace(/^STB[:\s]+/, '')
                    .replace(/^STB-/, 'STB-')
                    .replace(/^STB_/, 'STB-');
            } else if (p.toLowerCase().startsWith('doc id:')) {
                res.docId = p.substring(7).trim();
            } else if (p.toLowerCase().startsWith('item:')) {
                res.item = p.substring(5).trim();
            } else if (p.toLowerCase().startsWith('sn:')) {
                const sn = p.substring(3).trim();
                if (sn && sn !== '-') res.serial = sn;
            } else if (p.toLowerCase().startsWith('assign:')) {
                res.assignee = p.substring(7).trim();
            } else if (p.toLowerCase().startsWith('catatan:')) {
                res.comment = p.substring(8).trim();
            } else if (p.toLowerCase().startsWith('ref:')) {
                res.refTag = p.substring(4).trim();
            } else if (p.toLowerCase().startsWith('aset disertakan dalam')) {
                res.stbNo = p.replace(/^aset disertakan dalam\s*/i, '');
            }
        }
        return res;
    }

    return { isStructured: false, raw: trimmed, comment: trimmed };
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
                        <History class="size-3.5" />
                    </div>
                    <h3
                        class="text-xs font-black tracking-widest text-[#003628] uppercase"
                    >
                        {{ title }}
                    </h3>
                    <span
                        class="rounded-full bg-[#003628]/10 px-2 py-0.5 text-[10px] font-black text-[#003628] tabular-nums"
                    >
                        {{ totalHistoryCount }}
                    </span>
                </div>
                <p class="text-[11px] text-slate-400">
                    {{ subtitle }}
                </p>
            </div>

            <!-- Search Box -->
            <div class="relative w-full sm:w-72">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-slate-400"
                />
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Cari aktivitas, user, formulir, catatan..."
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

        <!-- 3 Summary Stat Cards -->
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <!-- Card 1: Total Log -->
            <div
                class="flex items-center gap-3.5 rounded-2xl border border-slate-100 bg-white p-4 shadow-xs"
            >
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700"
                >
                    <Activity class="size-5" />
                </div>
                <div class="min-w-0">
                    <p
                        class="text-[10px] font-black tracking-widest text-slate-400 uppercase"
                    >
                        Total Aktifitas
                    </p>
                    <p class="text-base font-black text-slate-800">
                        {{ totalHistoryCount }}
                        <span class="text-xs font-semibold text-slate-500"
                            >Aktivitas</span
                        >
                    </p>
                    <p class="text-[10px] text-slate-400">
                        Log transaksi & sistem tercatat
                    </p>
                </div>
            </div>

            <!-- Card 2: Aktivitas Terakhir -->
            <div
                class="flex items-center gap-3.5 rounded-2xl border border-slate-100 bg-white p-4 shadow-xs"
            >
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-sky-50 text-sky-700"
                >
                    <Clock class="size-5" />
                </div>
                <div class="min-w-0">
                    <p
                        class="text-[10px] font-black tracking-widest text-slate-400 uppercase"
                    >
                        Aktivitas Terakhir
                    </p>
                    <p
                        class="truncate text-xs font-black text-slate-800 uppercase"
                    >
                        {{ latestActivity.label }}
                    </p>
                    <p class="truncate text-[10px] text-slate-400 tabular-nums">
                        {{ latestActivity.date }}
                    </p>
                </div>
            </div>

            <!-- Card 3: User Terakhir -->
            <div
                class="flex items-center gap-3.5 rounded-2xl border border-slate-100 bg-white p-4 shadow-xs"
            >
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-700"
                >
                    <User class="size-5" />
                </div>
                <div class="min-w-0">
                    <p
                        class="text-[10px] font-black tracking-widest text-slate-400 uppercase"
                    >
                        User Terakhir
                    </p>
                    <p class="truncate text-xs font-black text-slate-800">
                        {{ lastUser }}
                    </p>
                    <p class="text-[10px] text-slate-400">
                        Aktivitas terakhir oleh
                    </p>
                </div>
            </div>
        </div>

        <!-- Table Container -->
        <div
            class="overflow-hidden rounded-2xl border border-slate-200/70 bg-white shadow-xs"
        >
            <div
                v-if="filteredHistory.length === 0"
                class="flex flex-col items-center justify-center px-4 py-16 text-center"
            >
                <div
                    class="mb-3 flex size-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"
                >
                    <History class="size-6" />
                </div>
                <p class="text-sm font-bold text-slate-700">
                    {{
                        searchQuery
                            ? 'Tidak ada riwayat aktivitas yang cocok'
                            : 'Belum ada riwayat aktivitas tercatat'
                    }}
                </p>
                <p class="mt-1 max-w-sm text-xs text-slate-400">
                    {{
                        searchQuery
                            ? `Tidak ditemukan log yang cocok dengan kata kunci "${searchQuery}".`
                            : 'Seluruh penambahan, pengeluaran, perbaikan, dan mutasi aset akan tercatat di sini.'
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
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full border-collapse text-left">
                    <thead>
                        <tr
                            class="border-b border-slate-100 bg-slate-50/60 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                        >
                            <th class="px-5 py-3.5">Timeline</th>
                            <th class="px-5 py-3.5">Otorisasi Oleh</th>
                            <th class="px-5 py-3.5">Formulir & Dokumen</th>
                            <th class="px-5 py-3.5">Operasi & Role</th>
                            <th class="px-5 py-3.5">Catatan / Detail</th>
                            <th class="px-4 py-3.5 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="rec in paginatedHistory"
                            :key="rec.id"
                            class="group transition-colors hover:bg-slate-50/70"
                        >
                            <!-- Timeline -->
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    <component
                                        :is="getActionIcon(rec.action_type)"
                                        class="size-3.5 text-slate-400 transition-colors group-hover:text-[#003628]"
                                    />
                                    <span
                                        class="font-mono text-xs font-bold text-slate-800 tabular-nums"
                                    >
                                        {{ rec.date }}
                                    </span>
                                </div>
                            </td>

                            <!-- Otorisasi Oleh -->
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    <div
                                        class="flex size-7 items-center justify-center rounded-lg border border-slate-200/60 bg-slate-100 text-slate-600"
                                    >
                                        <User class="size-3.5" />
                                    </div>
                                    <Link
                                        v-if="rec.user_id"
                                        :href="`/users/${rec.user_id}`"
                                        class="text-xs font-bold text-slate-900 transition hover:text-[#003628] hover:underline"
                                    >
                                        {{ rec.user || 'Sistem' }}
                                    </Link>
                                    <span
                                        v-else
                                        class="text-xs font-bold text-slate-800"
                                    >
                                        {{ rec.user || 'Sistem' }}
                                    </span>
                                </div>
                            </td>

                            <!-- Formulir & Dokumen -->
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-2.5">
                                    <div
                                        class="flex size-7 shrink-0 items-center justify-center rounded-lg border border-emerald-100/60 bg-emerald-50 text-[#003628]"
                                    >
                                        <component
                                            :is="getFormIcon(rec.form_type)"
                                            class="size-3.5"
                                        />
                                    </div>
                                    <div class="min-w-0 space-y-0.5">
                                        <p
                                            class="text-[10px] font-black tracking-wider text-slate-400 uppercase"
                                        >
                                            {{
                                                rec.form_name ||
                                                'Aktivitas Aset'
                                            }}
                                        </p>
                                        <Link
                                            v-if="rec.doc_url"
                                            :href="rec.doc_url"
                                            class="inline-flex max-w-[200px] items-center gap-1 truncate text-xs font-bold text-[#003628] transition hover:underline"
                                            :title="
                                                rec.doc_no ||
                                                rec.file_name ||
                                                'Buka Dokumen'
                                            "
                                        >
                                            <span class="truncate">{{
                                                rec.doc_no ||
                                                rec.file_name ||
                                                'Buka Dokumen'
                                            }}</span>
                                            <ExternalLink
                                                class="size-2.5 shrink-0 opacity-70"
                                            />
                                        </Link>
                                        <a
                                            v-else-if="
                                                rec.file_url && rec.file_name
                                            "
                                            :href="rec.file_url"
                                            target="_blank"
                                            class="inline-flex max-w-[200px] items-center gap-1 truncate text-xs font-bold text-[#003628] transition hover:underline"
                                            :title="rec.file_name"
                                        >
                                            <span class="truncate">{{
                                                rec.file_name
                                            }}</span>
                                            <ExternalLink
                                                class="size-2.5 shrink-0 opacity-70"
                                            />
                                        </a>
                                        <span
                                            v-else
                                            class="block max-w-[200px] truncate text-xs font-semibold text-slate-700"
                                        >
                                            {{
                                                rec.doc_no ||
                                                rec.file_name ||
                                                '—'
                                            }}
                                        </span>
                                    </div>
                                </div>
                            </td>

                            <!-- Operasi & Role -->
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <div class="flex flex-col items-start gap-1">
                                    <span
                                        class="inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-black tracking-wider uppercase"
                                        :class="
                                            getActionBadgeClass(
                                                rec.action_type ||
                                                    rec.action_label,
                                            )
                                        "
                                    >
                                        {{
                                            rec.action_label || rec.action_type
                                        }}
                                    </span>
                                    <span
                                        v-if="rec.role"
                                        class="text-[10px] font-bold text-slate-400"
                                    >
                                        Role: {{ rec.role }}
                                    </span>
                                    <span
                                        v-else-if="
                                            rec.target && rec.target !== '-'
                                        "
                                        class="max-w-[140px] truncate text-[10px] font-bold text-slate-400"
                                        :title="rec.target"
                                    >
                                        Target: {{ rec.target }}
                                    </span>
                                </div>
                            </td>

                            <!-- Catatan / Detail (Structured STB Parser) -->
                            <td class="px-5 py-3.5">
                                <div class="max-w-sm space-y-1.5">
                                    <!-- When structured note with STB is detected -->
                                    <div
                                        v-if="
                                            parseActivityNote(rec.note)
                                                .isStructured
                                        "
                                        class="flex flex-col gap-1"
                                    >
                                        <div
                                            class="flex flex-wrap items-center gap-1.5"
                                        >
                                            <span
                                                v-if="
                                                    parseActivityNote(rec.note)
                                                        .stbNo
                                                "
                                                class="inline-flex items-center gap-1 rounded-md border border-emerald-200/60 bg-emerald-50 px-1.5 py-0.5 font-mono text-[10px] font-bold text-emerald-800"
                                            >
                                                <Folder
                                                    class="size-2.5 text-emerald-600"
                                                />
                                                {{
                                                    parseActivityNote(rec.note)
                                                        .stbNo
                                                }}
                                            </span>
                                            <span
                                                v-if="
                                                    parseActivityNote(rec.note)
                                                        .serial
                                                "
                                                class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-1.5 py-0.5 font-mono text-[10px] font-bold text-slate-700"
                                            >
                                                <Hash
                                                    class="size-2.5 text-slate-400"
                                                />
                                                {{
                                                    parseActivityNote(rec.note)
                                                        .serial
                                                }}
                                            </span>
                                            <span
                                                v-if="
                                                    parseActivityNote(rec.note)
                                                        .assignee
                                                "
                                                class="inline-flex items-center gap-1 rounded-md border border-sky-100 bg-sky-50 px-1.5 py-0.5 text-[10px] font-bold text-sky-800"
                                            >
                                                <User
                                                    class="size-2.5 text-sky-600"
                                                />
                                                {{
                                                    parseActivityNote(rec.note)
                                                        .assignee
                                                }}
                                            </span>
                                        </div>
                                        <p
                                            v-if="
                                                parseActivityNote(rec.note)
                                                    .comment
                                            "
                                            class="line-clamp-2 text-xs leading-relaxed text-slate-600"
                                            :title="
                                                parseActivityNote(rec.note)
                                                    .comment
                                            "
                                        >
                                            {{
                                                parseActivityNote(rec.note)
                                                    .comment
                                            }}
                                        </p>
                                    </div>

                                    <!-- Standard note -->
                                    <p
                                        v-else
                                        class="line-clamp-2 text-xs leading-relaxed text-slate-600"
                                        :title="rec.note || '—'"
                                    >
                                        {{ rec.note || '—' }}
                                    </p>

                                    <!-- Changed field diff if any -->
                                    <div
                                        v-if="rec.changed"
                                        class="line-clamp-1 inline-block rounded-sm bg-slate-100 px-1.5 py-0.5 font-mono text-[10px] text-slate-500"
                                        :title="rec.changed"
                                    >
                                        {{ rec.changed }}
                                    </div>
                                </div>
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
                                        title="Lihat Detail Log"
                                        @click="$emit('open-detail', rec)"
                                    >
                                        <Eye class="size-3.5 text-slate-400" />
                                        <span>Detail</span>
                                    </button>

                                    <button
                                        v-if="
                                            rec.pdf_url ||
                                            (rec.file_url &&
                                                /\.pdf$/i.test(rec.file_url))
                                        "
                                        type="button"
                                        class="inline-flex h-7.5 w-7.5 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 shadow-2xs transition hover:border-[#003628]/30 hover:bg-emerald-50 hover:text-[#003628] active:scale-95"
                                        title="Lihat Dokumen PDF"
                                        @click="
                                            $emit(
                                                'open-pdf',
                                                rec.pdf_url || rec.file_url!,
                                            )
                                        "
                                    >
                                        <FileText class="size-3.5" />
                                    </button>
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
                    :total-items="filteredHistory.length"
                    @update:current-page="(p) => (currentPage = p)"
                />
            </div>
        </div>
    </div>
</template>
