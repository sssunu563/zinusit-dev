<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import {
    User,
    Laptop,
    Monitor,
    Cpu,
    Calendar,
    Search,
    X,
    Users,
    FileText,
    Hash,
    Layers,
    Clock,
    Tag,
    HardDrive,
    Smartphone,
    Info,
    CheckCircle2,
} from 'lucide-vue-next';
import AppPagination from '@/components/AppPagination.vue';

export interface CheckoutRecord {
    id: number;
    name: string;
    secondary?: string;
    email?: string;
    company?: string;
    location?: string;
    note?: string;
    date: string;
    image?: string;
    qty?: number;
}

interface Props {
    records: CheckoutRecord[];
    assetType: string;
    unitLabel?: string;
    title?: string;
}

const props = withDefaults(defineProps<Props>(), {
    unitLabel: 'Unit',
    title: 'Daftar Penugasan Aktif',
});

const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = 10;

interface ParsedNote {
    isStructured: boolean;
    stbNo?: string;
    docId?: string;
    item?: string;
    sn?: string;
    assignee?: string;
    remark?: string;
    reference?: string;
    rawText: string;
}

function parseNote(raw: string | null | undefined): ParsedNote {
    const text = (raw || '').replace(/^\*+|\*+$/g, '').trim();
    if (!text || text === '-') {
        return { isStructured: false, rawText: '' };
    }

    if (!text.includes('|')) {
        return { isStructured: false, rawText: text };
    }

    const segments = text.split('|').map((s) => s.trim());
    const result: ParsedNote = { isStructured: true, rawText: text };

    for (const seg of segments) {
        if (/^STB/i.test(seg) && !seg.includes(':')) {
            result.stbNo = seg;
        } else if (/^STB\s*:\s*(.*)/i.test(seg)) {
            const m = seg.match(/^STB\s*:\s*(.*)/i);
            if (m) result.stbNo = m[1].trim();
        } else if (/^Doc ID\s*:\s*(.*)/i.test(seg)) {
            const m = seg.match(/^Doc ID\s*:\s*(.*)/i);
            if (m) result.docId = m[1].trim();
        } else if (/^Item\s*:\s*(.*)/i.test(seg)) {
            const m = seg.match(/^Item\s*:\s*(.*)/i);
            if (m) result.item = m[1].trim();
        } else if (/^SN\s*:\s*(.*)/i.test(seg)) {
            const m = seg.match(/^SN\s*:\s*(.*)/i);
            if (m && m[1].trim() !== '-') result.sn = m[1].trim();
        } else if (/^Assign\s*:\s*(.*)/i.test(seg)) {
            const m = seg.match(/^Assign\s*:\s*(.*)/i);
            if (m) result.assignee = m[1].trim();
        } else if (/^Catatan\s*:\s*(.*)/i.test(seg)) {
            const m = seg.match(/^Catatan\s*:\s*(.*)/i);
            if (m) result.remark = m[1].trim();
        } else if (/^Ref\s*:\s*(.*)/i.test(seg)) {
            const m = seg.match(/^Ref\s*:\s*(.*)/i);
            if (m) result.reference = m[1].trim();
        }
    }

    if (!result.stbNo && result.docId) {
        result.stbNo = result.docId;
    }

    return result;
}

const isComponent = computed(() => props.assetType === 'component');

function getDeviceIcon(targetName: string) {
    const s = targetName.toLowerCase();
    if (s.includes('zenbook') || s.includes('laptop') || s.includes('thinkpad') || s.includes('macbook') || s.includes('nb-')) {
        return Laptop;
    }
    if (s.includes('monitor') || s.includes('display')) {
        return Monitor;
    }
    if (s.includes('phone') || s.includes('hp')) {
        return Smartphone;
    }
    if (s.includes('storage') || s.includes('hdd') || s.includes('ssd')) {
        return HardDrive;
    }
    return Cpu;
}

const filteredRecords = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return props.records;

    return props.records.filter((rec) => {
        const name = (rec.name || '').toLowerCase();
        const secondary = (rec.secondary || '').toLowerCase();
        const note = (rec.note || '').toLowerCase();
        const email = (rec.email || '').toLowerCase();
        const date = (rec.date || '').toLowerCase();

        return (
            name.includes(q) ||
            secondary.includes(q) ||
            note.includes(q) ||
            email.includes(q) ||
            date.includes(q)
        );
    });
});

watch(searchQuery, () => {
    currentPage.value = 1;
});

const totalPages = computed(() =>
    Math.ceil(filteredRecords.value.length / itemsPerPage),
);

const paginatedRecords = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    return filteredRecords.value.slice(start, start + itemsPerPage);
});

const totalQuantityAssigned = computed(() => {
    return props.records.reduce((sum, r) => sum + (Number(r.qty) || 1), 0);
});

const latestCheckoutDate = computed(() => {
    if (!props.records.length) return '—';
    return props.records[0]?.date || '—';
});
</script>

<template>
    <div class="space-y-4">
        <!-- HEADER & ACTIONS -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="space-y-0.5">
                <h3 class="flex items-center gap-2 text-[11px] font-black tracking-widest text-[#003628] uppercase">
                    <Users class="size-4" />
                    <span>{{ title }}</span>
                    <span
                        v-if="records.length > 0"
                        class="ml-1 rounded-full bg-[#003628]/10 px-2 py-0.5 text-[10px] font-black text-[#003628] tabular-nums"
                    >
                        {{ records.length }}
                    </span>
                </h3>
                <p class="text-[11px] font-medium text-slate-400">
                    {{
                        isComponent
                            ? 'Daftar perangkat hardware induk tempat komponen ini terpasang.'
                            : 'Daftar pengguna dan penugasan aktif dari unit aset ini.'
                    }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <!-- Search Filter -->
                <div v-if="records.length > 0" class="relative w-full sm:w-64">
                    <Search class="pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-slate-400" />
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Cari penerima, serial, atau STB..."
                        class="h-8 w-full rounded-lg border border-slate-200 bg-white pr-8 pl-8 text-xs font-medium text-slate-800 placeholder:text-slate-400 focus:border-[#003628] focus:ring-1 focus:ring-[#003628] focus:outline-none"
                    />
                    <button
                        v-if="searchQuery"
                        type="button"
                        class="absolute top-1/2 right-2.5 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                        @click="searchQuery = ''"
                    >
                        <X class="size-3.5" />
                    </button>
                </div>
            </div>
        </div>

        <!-- STATS OVERVIEW CARDS -->
        <div
            v-if="records.length > 0"
            class="grid grid-cols-1 gap-3 sm:grid-cols-3"
        >
            <div class="flex items-center gap-3 rounded-xl border border-slate-200/70 bg-gradient-to-br from-slate-50/80 to-slate-50/30 p-3.5">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200/50">
                    <CheckCircle2 class="size-5" />
                </div>
                <div class="min-w-0">
                    <p class="text-[10px] font-bold tracking-widest text-slate-400 uppercase">
                        Total Unit Terpasang / Dipakai
                    </p>
                    <p class="text-base font-black text-slate-800 tabular-nums">
                        {{ totalQuantityAssigned }}
                        <span class="text-xs font-semibold text-slate-500 uppercase">{{ unitLabel }}</span>
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-3 rounded-xl border border-slate-200/70 bg-gradient-to-br from-slate-50/80 to-slate-50/30 p-3.5">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-[#003628]/5 text-[#003628] ring-1 ring-[#003628]/10">
                    <component :is="isComponent ? Laptop : Users" class="size-5" />
                </div>
                <div class="min-w-0">
                    <p class="text-[10px] font-bold tracking-widest text-slate-400 uppercase">
                        {{ isComponent ? 'Target Hardware' : 'Penerima Aktif' }}
                    </p>
                    <p class="text-base font-black text-slate-800 tabular-nums">
                        {{ records.length }}
                        <span class="text-xs font-semibold text-slate-500">{{ isComponent ? 'Perangkat' : 'Pengguna' }}</span>
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-3 rounded-xl border border-slate-200/70 bg-gradient-to-br from-slate-50/80 to-slate-50/30 p-3.5">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-sky-50 text-sky-700 ring-1 ring-sky-200/50">
                    <Calendar class="size-5" />
                </div>
                <div class="min-w-0">
                    <p class="text-[10px] font-bold tracking-widest text-slate-400 uppercase">
                        Penugasan Terakhir
                    </p>
                    <p class="truncate text-base font-black text-slate-800">
                        {{ latestCheckoutDate }}
                    </p>
                </div>
            </div>
        </div>

        <!-- EMPTY STATE (NO ASSIGNMENTS EVER) -->
        <div
            v-if="records.length === 0"
            class="rounded-[24px] border-2 border-dashed border-slate-200/80 bg-slate-50/30 py-16 text-center"
        >
            <div class="mx-auto mb-3 flex size-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                <Users class="size-6 text-slate-400" />
            </div>
            <h4 class="text-xs font-black tracking-widest text-slate-700 uppercase">
                Belum Ada Penugasan Aktif
            </h4>
            <p class="mx-auto mt-1 max-w-sm text-xs text-slate-400">
                Semua unit saat ini masih tersimpan di inventaris (Ready in Stock) dan belum diserahkan ke perangkat atau pengguna.
            </p>
        </div>

        <!-- NO SEARCH RESULTS -->
        <div
            v-else-if="filteredRecords.length === 0"
            class="rounded-2xl border border-slate-200/70 bg-white py-12 text-center"
        >
            <Search class="mx-auto mb-2 size-8 text-slate-300" />
            <p class="text-xs font-bold text-slate-700">
                Tidak ada penugasan yang cocok dengan "{{ searchQuery }}"
            </p>
            <p class="mt-0.5 text-[11px] text-slate-400">
                Coba gunakan kata kunci lain seperti nama perangkat, nomor STB, atau nama pengguna.
            </p>
            <button
                type="button"
                class="mt-3 inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-100"
                @click="searchQuery = ''"
            >
                <X class="size-3" /> Reset Pencarian
            </button>
        </div>

        <!-- DATA TABLE -->
        <div
            v-else
            class="overflow-hidden rounded-xl border border-slate-200/70 bg-white shadow-xs"
        >
            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/60">
                            <th class="px-5 py-3.5 text-left text-[10px] font-black tracking-widest text-slate-400 uppercase">
                                {{ isComponent ? 'Target Perangkat' : 'Penerima / Target' }}
                            </th>
                            <th class="px-5 py-3.5 text-left text-[10px] font-black tracking-widest text-slate-400 uppercase">
                                Dokumen & Konteks Penugasan
                            </th>
                            <th class="px-5 py-3.5 text-left text-[10px] font-black tracking-widest text-slate-400 uppercase">
                                Catatan
                            </th>
                            <th class="px-5 py-3.5 text-right text-[10px] font-black tracking-widest text-slate-400 uppercase">
                                Tanggal Checkout
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100/80">
                        <tr
                            v-for="rec in paginatedRecords"
                            :key="rec.id"
                            class="group transition-colors hover:bg-slate-50/50"
                        >
                            <!-- TARGET / RECIPIENT -->
                            <td class="px-5 py-4">
                                <div class="flex items-start gap-3">
                                    <!-- Device Icon or Avatar -->
                                    <div
                                        v-if="isComponent"
                                        class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600 ring-1 ring-slate-200/60"
                                    >
                                        <component :is="getDeviceIcon(rec.name)" class="size-5 text-[#003628]" />
                                    </div>
                                    <div
                                        v-else
                                        class="relative mt-0.5 flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-full bg-[#003628]/10 text-xs font-black text-[#003628] ring-1 ring-[#003628]/15"
                                    >
                                        <img
                                            v-if="rec.image"
                                            :src="rec.image"
                                            :alt="rec.name"
                                            class="size-full object-cover"
                                        />
                                        <span v-else>{{ (rec.name || 'U').charAt(0).toUpperCase() }}</span>
                                    </div>

                                    <div class="min-w-0 space-y-1">
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <p class="font-bold text-xs text-slate-900 leading-snug">
                                                {{ rec.name }}
                                            </p>
                                            <span
                                                v-if="rec.qty && rec.qty > 1"
                                                class="rounded-full bg-emerald-50 px-2 py-0.5 text-[9px] font-black text-emerald-700 border border-emerald-100"
                                            >
                                                {{ rec.qty }} {{ unitLabel }}
                                            </span>
                                        </div>

                                        <!-- Secondary details (asset tag, username, email, company) -->
                                        <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[10px] text-slate-400">
                                            <span
                                                v-if="rec.secondary"
                                                class="inline-flex items-center gap-1 font-mono font-bold text-slate-600"
                                            >
                                                <Tag class="size-2.5 text-slate-400" />
                                                {{ rec.secondary }}
                                            </span>
                                            <span v-if="rec.email" class="text-slate-400 truncate max-w-[180px]">
                                                {{ rec.email }}
                                            </span>
                                            <span v-if="rec.company" class="text-slate-400">
                                                • {{ rec.company }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- CONTEXT / STRUCTURED DOC -->
                            <td class="px-5 py-4">
                                <template v-if="parseNote(rec.note).isStructured">
                                    <div class="space-y-1.5">
                                        <!-- STB Document & Assignee Badges -->
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <span
                                                v-if="parseNote(rec.note).stbNo"
                                                class="inline-flex items-center gap-1 rounded-md border border-emerald-200 bg-emerald-50 px-2 py-0.5 font-mono text-[10px] font-bold text-emerald-800 shadow-2xs"
                                            >
                                                <FileText class="size-3 text-emerald-600" />
                                                {{ parseNote(rec.note).stbNo }}
                                            </span>

                                            <span
                                                v-if="parseNote(rec.note).assignee"
                                                class="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-slate-50 px-2 py-0.5 text-[10px] font-semibold text-slate-700"
                                            >
                                                <User class="size-2.5 text-slate-400" />
                                                <span>{{ parseNote(rec.note).assignee }}</span>
                                            </span>

                                            <span
                                                v-if="parseNote(rec.note).sn"
                                                class="inline-flex items-center gap-1 rounded-md border border-slate-100 bg-slate-50 px-1.5 py-0.5 font-mono text-[9px] text-slate-500"
                                            >
                                                SN: {{ parseNote(rec.note).sn }}
                                            </span>
                                        </div>

                                        <!-- Reference Tag if present -->
                                        <div
                                            v-if="parseNote(rec.note).reference"
                                            class="text-[10px] font-medium text-slate-400"
                                        >
                                            Ref: <span class="font-mono font-bold text-slate-600">{{ parseNote(rec.note).reference }}</span>
                                        </div>
                                    </div>
                                </template>
                                <template v-else>
                                    <span class="text-xs text-slate-400 italic">
                                        Penugasan Langsung
                                    </span>
                                </template>
                            </td>

                            <!-- CATATAN / REMARKS -->
                            <td class="px-5 py-4">
                                <div v-if="parseNote(rec.note).isStructured">
                                    <p
                                        v-if="parseNote(rec.note).remark"
                                        class="max-w-xs text-xs text-slate-700 leading-relaxed break-words"
                                    >
                                        {{ parseNote(rec.note).remark }}
                                    </p>
                                    <span v-else class="text-xs text-slate-300 italic">—</span>
                                </div>
                                <div v-else>
                                    <p
                                        v-if="parseNote(rec.note).rawText"
                                        class="max-w-xs text-xs text-slate-700 leading-relaxed break-words"
                                    >
                                        {{ parseNote(rec.note).rawText }}
                                    </p>
                                    <span v-else class="text-xs text-slate-300 italic">—</span>
                                </div>
                            </td>

                            <!-- DATE -->
                            <td class="px-5 py-4 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-700 tabular-nums">
                                    <Calendar class="size-3.5 text-slate-400" />
                                    <span>{{ rec.date }}</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- PAGINATION -->
            <div v-if="totalPages > 1" class="border-t border-slate-100 bg-slate-50/40 p-3">
                <AppPagination
                    :current-page="currentPage"
                    :total-pages="totalPages"
                    :items-per-page="itemsPerPage"
                    :total-items="filteredRecords.length"
                    @update:current-page="(p) => (currentPage = p)"
                />
            </div>
        </div>
    </div>
</template>
