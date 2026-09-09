<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import {
    Plus,
    Minus,
    Calendar,
    Clock,
    FileText,
    Download,
    Hash,
    RotateCcw,
    RefreshCw,
    Search,
    X,
    Package,
    Layers,
    Receipt,
} from 'lucide-vue-next';
import AppPagination from '@/components/AppPagination.vue';

export interface StockRecord {
    id: number;
    qty: number;
    po_number: string;
    purchase_date: string;
    notes: string | null;
    document_url: string | null;
    created_by: string;
    created_at: string;
}

interface Props {
    stockHistory: StockRecord[];
    loading?: boolean;
    unitLabel?: string;
    title?: string;
    showAddButton?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    loading: false,
    unitLabel: 'Unit',
    title: 'Riwayat Stok',
    showAddButton: true,
});

const emit = defineEmits<{
    (e: 'addStock'): void;
    (e: 'openPdf', url: string): void;
}>();

const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = 10;

const filteredRecords = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return props.stockHistory;

    return props.stockHistory.filter((rec) => {
        const po = (rec.po_number || '').toLowerCase();
        const notes = (rec.notes || '').toLowerCase();
        const user = (rec.created_by || '').toLowerCase();
        const date = (rec.purchase_date || '').toLowerCase();
        const createdAt = (rec.created_at || '').toLowerCase();
        const qty = String(rec.qty);

        return (
            po.includes(q) ||
            notes.includes(q) ||
            user.includes(q) ||
            date.includes(q) ||
            createdAt.includes(q) ||
            qty.includes(q)
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

const totalUnitsAdded = computed(() => {
    return props.stockHistory.reduce(
        (sum, rec) => sum + (Number(rec.qty) || 0),
        0,
    );
});

const latestPurchase = computed(() => {
    if (!props.stockHistory.length) return '—';
    return props.stockHistory[0]?.purchase_date || props.stockHistory[0]?.created_at || '—';
});

const isPdf = (url: string | null): boolean => {
    if (!url) return false;
    return /\.pdf$/i.test(url);
};

const handleDocumentClick = (url: string) => {
    if (isPdf(url)) {
        emit('openPdf', url);
    } else {
        window.open(url, '_blank', 'noopener,noreferrer');
    }
};
</script>

<template>
    <div class="space-y-4">
        <!-- HEADER & ACTIONS -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="space-y-0.5">
                <h3 class="flex items-center gap-2 text-[11px] font-black tracking-widest text-[#003628] uppercase">
                    <Layers class="size-4" />
                    <span>{{ title }}</span>
                    <span
                        v-if="stockHistory.length > 0"
                        class="ml-1 rounded-full bg-[#003628]/10 px-2 py-0.5 text-[10px] font-black text-[#003628] tabular-nums"
                    >
                        {{ stockHistory.length }}
                    </span>
                </h3>
                <p class="text-[11px] font-medium text-slate-400">
                    Daftar batch pengadaan, nomor purchase order, dan riwayat mutasi stok.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <!-- Quick Search -->
                <div v-if="stockHistory.length > 0" class="relative w-full sm:w-60">
                    <Search class="pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-slate-400" />
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Cari PO, user, atau catatan..."
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

                <!-- Add Stock Action -->
                <button
                    v-if="showAddButton"
                    type="button"
                    class="flex h-8 items-center gap-1.5 rounded-lg border border-[#003628] bg-[#003628] px-3.5 text-xs font-bold text-white shadow-xs transition hover:bg-[#003628]/90 active:scale-95"
                    @click="emit('addStock')"
                >
                    <Plus class="size-3.5 stroke-[2.5]" />
                    <span>Tambah Stok</span>
                </button>
            </div>
        </div>

        <!-- STATS OVERVIEW CARDS (When Data Exists) -->
        <div
            v-if="stockHistory.length > 0"
            class="grid grid-cols-1 gap-3 sm:grid-cols-3"
        >
            <div class="flex items-center gap-3 rounded-xl border border-slate-200/70 bg-gradient-to-br from-slate-50/80 to-slate-50/30 p-3.5">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200/50">
                    <Package class="size-5" />
                </div>
                <div class="min-w-0">
                    <p class="text-[10px] font-bold tracking-widest text-slate-400 uppercase">
                        Total Ditambahkan
                    </p>
                    <p class="text-base font-black text-slate-800 tabular-nums">
                        +{{ totalUnitsAdded }}
                        <span class="text-xs font-semibold text-slate-500 uppercase">{{ unitLabel }}</span>
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-3 rounded-xl border border-slate-200/70 bg-gradient-to-br from-slate-50/80 to-slate-50/30 p-3.5">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-[#003628]/5 text-[#003628] ring-1 ring-[#003628]/10">
                    <Receipt class="size-5" />
                </div>
                <div class="min-w-0">
                    <p class="text-[10px] font-bold tracking-widest text-slate-400 uppercase">
                        Total Transaksi Batch
                    </p>
                    <p class="text-base font-black text-slate-800 tabular-nums">
                        {{ stockHistory.length }}
                        <span class="text-xs font-semibold text-slate-500">Batch</span>
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-3 rounded-xl border border-slate-200/70 bg-gradient-to-br from-slate-50/80 to-slate-50/30 p-3.5">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-sky-50 text-sky-700 ring-1 ring-sky-200/50">
                    <Calendar class="size-5" />
                </div>
                <div class="min-w-0">
                    <p class="text-[10px] font-bold tracking-widest text-slate-400 uppercase">
                        Pembelian Terakhir
                    </p>
                    <p class="truncate text-base font-black text-slate-800">
                        {{ latestPurchase }}
                    </p>
                </div>
            </div>
        </div>

        <!-- LOADING STATE -->
        <div
            v-if="loading"
            class="flex flex-col items-center justify-center rounded-2xl border border-slate-100 bg-white py-16 text-center"
        >
            <RefreshCw class="size-6 animate-spin text-[#003628]" />
            <p class="mt-2 text-xs font-bold tracking-wider text-slate-400 uppercase">
                Memuat riwayat stok...
            </p>
        </div>

        <!-- EMPTY STATE (NO RECORDS EVER) -->
        <div
            v-else-if="stockHistory.length === 0"
            class="rounded-[24px] border-2 border-dashed border-slate-200/80 bg-slate-50/30 py-16 text-center"
        >
            <div class="mx-auto mb-3 flex size-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                <RotateCcw class="size-6 text-slate-400" />
            </div>
            <h4 class="text-xs font-black tracking-widest text-slate-700 uppercase">
                Belum Ada Riwayat Stok
            </h4>
            <p class="mx-auto mt-1 max-w-sm text-xs text-slate-400">
                Riwayat penambahan stok, purchase order, dan dokumen faktur pembelian akan tercatat rapi di sini.
            </p>
            <button
                v-if="showAddButton"
                type="button"
                class="mt-4 inline-flex items-center gap-1.5 rounded-xl bg-[#003628] px-4 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-[#003628]/90 active:scale-95"
                @click="emit('addStock')"
            >
                <Plus class="size-3.5 stroke-[2.5]" />
                <span>Tambah Stok Sekarang</span>
            </button>
        </div>

        <!-- NO SEARCH RESULTS -->
        <div
            v-else-if="filteredRecords.length === 0"
            class="rounded-2xl border border-slate-200/70 bg-white py-12 text-center"
        >
            <Search class="mx-auto mb-2 size-8 text-slate-300" />
            <p class="text-xs font-bold text-slate-700">
                Tidak ada riwayat stok yang cocok dengan "{{ searchQuery }}"
            </p>
            <p class="mt-0.5 text-[11px] text-slate-400">
                Coba gunakan kata kunci pencarian nomor PO, nama pembuat, atau tanggal lain.
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
                                Perubahan Stok
                            </th>
                            <th class="px-5 py-3.5 text-left text-[10px] font-black tracking-widest text-slate-400 uppercase">
                                No. Purchase Order
                            </th>
                            <th class="px-5 py-3.5 text-left text-[10px] font-black tracking-widest text-slate-400 uppercase">
                                Tanggal Pembelian
                            </th>
                            <th class="px-5 py-3.5 text-left text-[10px] font-black tracking-widest text-slate-400 uppercase">
                                Dibuat Oleh
                            </th>
                            <th class="px-5 py-3.5 text-left text-[10px] font-black tracking-widest text-slate-400 uppercase">
                                Catatan
                            </th>
                            <th class="px-4 py-3.5 text-center text-[10px] font-black tracking-widest text-slate-400 uppercase">
                                Dokumen
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100/80">
                        <tr
                            v-for="rec in paginatedRecords"
                            :key="rec.id"
                            class="group transition-colors hover:bg-slate-50/50"
                        >
                            <!-- QTY -->
                            <td class="px-5 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex size-8 shrink-0 items-center justify-center rounded-lg border shadow-2xs"
                                        :class="
                                            rec.qty >= 0
                                                ? 'border-emerald-200/70 bg-emerald-50 text-emerald-700'
                                                : 'border-rose-200/70 bg-rose-50 text-rose-700'
                                        "
                                    >
                                        <Plus v-if="rec.qty >= 0" class="size-4 stroke-[2.5]" />
                                        <Minus v-else class="size-4 stroke-[2.5]" />
                                    </div>
                                    <div>
                                        <div class="flex items-baseline gap-1">
                                            <span
                                                class="font-mono text-sm font-black tracking-tight tabular-nums"
                                                :class="rec.qty >= 0 ? 'text-emerald-700' : 'text-rose-700'"
                                            >
                                                {{ rec.qty > 0 ? '+' : '' }}{{ rec.qty }}
                                            </span>
                                            <span class="text-[10px] font-black tracking-widest text-slate-400 uppercase">
                                                {{ unitLabel }}
                                            </span>
                                        </div>
                                        <span
                                            class="inline-block text-[9px] font-bold tracking-tight uppercase"
                                            :class="rec.qty >= 0 ? 'text-emerald-600/80' : 'text-rose-600/80'"
                                        >
                                            {{ rec.qty >= 0 ? 'Stok Masuk' : 'Penyesuaian' }}
                                        </span>
                                    </div>
                                </div>
                            </td>

                            <!-- PO NUMBER -->
                            <td class="px-5 py-4 whitespace-nowrap">
                                <div v-if="rec.po_number && rec.po_number !== '-'" class="flex items-center gap-1.5">
                                    <span class="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-slate-50 px-2 py-0.5 font-mono text-[11px] font-black text-slate-800 shadow-2xs">
                                        <Hash class="size-3 text-slate-400" />
                                        {{ rec.po_number }}
                                    </span>
                                </div>
                                <span v-else class="text-xs font-medium text-slate-300 italic">
                                    Tanpa PO
                                </span>
                            </td>

                            <!-- PURCHASE DATE -->
                            <td class="px-5 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-1.5 text-xs font-semibold text-slate-700">
                                    <Calendar class="size-3.5 text-slate-400 shrink-0" />
                                    <span>{{ rec.purchase_date && rec.purchase_date !== '-' ? rec.purchase_date : '—' }}</span>
                                </div>
                            </td>

                            <!-- CREATED BY -->
                            <td class="px-5 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-2.5">
                                    <div class="flex size-7 shrink-0 items-center justify-center rounded-full bg-[#003628]/10 text-[11px] font-black text-[#003628] ring-1 ring-[#003628]/15">
                                        {{ (rec.created_by || 'U').charAt(0).toUpperCase() }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate text-xs font-bold text-slate-800">
                                            {{ rec.created_by || 'System' }}
                                        </p>
                                        <p class="flex items-center gap-1 text-[10px] font-medium text-slate-400">
                                            <Clock class="size-2.5 text-slate-300 shrink-0" />
                                            {{ rec.created_at }}
                                        </p>
                                    </div>
                                </div>
                            </td>

                            <!-- NOTES -->
                            <td class="px-5 py-4">
                                <div v-if="rec.notes" class="max-w-xs text-xs font-medium text-slate-600 leading-relaxed break-words">
                                    {{ rec.notes }}
                                </div>
                                <span v-else class="text-xs font-normal text-slate-300 italic">
                                    —
                                </span>
                            </td>

                            <!-- DOCUMENT / ACTION -->
                            <td class="px-4 py-4 text-center whitespace-nowrap">
                                <div class="flex items-center justify-center gap-1.5">
                                    <button
                                        v-if="rec.document_url && isPdf(rec.document_url)"
                                        type="button"
                                        class="inline-flex h-7 items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 text-[11px] font-bold text-slate-700 shadow-2xs transition hover:border-[#003628]/30 hover:bg-slate-50 hover:text-[#003628] active:scale-95"
                                        title="Buka Dokumen PDF"
                                        @click="handleDocumentClick(rec.document_url)"
                                    >
                                        <FileText class="size-3 text-rose-500" />
                                        <span>Lihat PDF</span>
                                    </button>
                                    <button
                                        v-else-if="rec.document_url"
                                        type="button"
                                        class="inline-flex h-7 items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 text-[11px] font-bold text-slate-700 shadow-2xs transition hover:border-[#003628]/30 hover:bg-slate-50 hover:text-[#003628] active:scale-95"
                                        title="Buka atau Unduh Lampiran"
                                        @click="handleDocumentClick(rec.document_url)"
                                    >
                                        <Download class="size-3 text-[#003628]" />
                                        <span>Lampiran</span>
                                    </button>
                                    <span v-else class="text-xs font-normal text-slate-300">
                                        —
                                    </span>
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
