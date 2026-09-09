<script setup lang="ts">
import { ref, computed } from 'vue';
import {
    Wrench,
    Calendar,
    Wallet,
    Search,
    X,
    FileText,
    CheckCircle2,
    Clock,
    Building2,
    ShieldCheck,
    Check,
} from 'lucide-vue-next';
import AppPagination from '@/components/AppPagination.vue';

export interface MaintenanceRecord {
    id: number | string;
    name?: string;
    supplier?: string;
    type?: string;
    start_date?: string;
    completion_date?: string | null;
    cost?: string | number | null;
    notes?: string | null;
    is_warranty?: boolean | number;
    [key: string]: any;
}

interface Props {
    maintenances: MaintenanceRecord[];
    loading?: boolean;
    title?: string;
    subtitle?: string;
}

const props = withDefaults(defineProps<Props>(), {
    maintenances: () => [],
    loading: false,
    title: 'CATATAN SERVIS & PEMELIHARAAN',
    subtitle: 'Riwayat pemeliharaan, perbaikan, kalibrasi, dan penggantian suku cadang.',
});

const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = 10;

// Filtered records
const filteredMaintenances = computed(() => {
    if (!searchQuery.value.trim()) return props.maintenances;
    const q = searchQuery.value.toLowerCase().trim();
    return props.maintenances.filter((m) => {
        return (
            (m.name && m.name.toLowerCase().includes(q)) ||
            (m.supplier && m.supplier.toLowerCase().includes(q)) ||
            (m.type && m.type.toLowerCase().includes(q)) ||
            (m.notes && m.notes.toLowerCase().includes(q)) ||
            (m.start_date && m.start_date.toLowerCase().includes(q)) ||
            (m.completion_date && m.completion_date.toLowerCase().includes(q)) ||
            (m.cost && String(m.cost).toLowerCase().includes(q))
        );
    });
});

// Pagination
const totalPages = computed(() =>
    Math.ceil(filteredMaintenances.value.length / itemsPerPage) || 1,
);

const paginatedMaintenances = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    return filteredMaintenances.value.slice(start, start + itemsPerPage);
});

// Helper: parse cost to numeric
function parseCost(val: any): number {
    if (!val) return 0;
    if (typeof val === 'number') return val;
    const cleanStr = String(val).replace(/[^0-9.-]+/g, '');
    const num = parseFloat(cleanStr);
    return isNaN(num) ? 0 : num;
}

// Summary stat 1: Total records
const totalCount = computed(() => props.maintenances.length);

// Summary stat 2: Total cost
const totalCostFormatted = computed(() => {
    let sum = 0;
    for (const m of props.maintenances) {
        sum += parseCost(m.cost);
    }
    if (sum === 0) return '—';
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(sum);
});

// Summary stat 3: Latest maintenance date
const latestDate = computed(() => {
    if (!props.maintenances.length) return '—';
    return props.maintenances[0]?.start_date || props.maintenances[0]?.completion_date || '—';
});

// Type badge color resolver
function getTypeBadgeClass(typeStr?: string | null): string {
    const t = (typeStr || '').toLowerCase();
    if (t.includes('repair') || t.includes('perbaikan') || t.includes('kerusakan')) {
        return 'bg-rose-50 text-rose-700 border-rose-200/80';
    }
    if (t.includes('upgrade') || t.includes('peningkatan')) {
        return 'bg-sky-50 text-sky-700 border-sky-200/80';
    }
    if (t.includes('maintenance') || t.includes('pemeliharaan') || t.includes('berkala')) {
        return 'bg-emerald-50 text-emerald-700 border-emerald-200/80';
    }
    if (t.includes('warranty') || t.includes('garansi')) {
        return 'bg-indigo-50 text-indigo-700 border-indigo-200/80';
    }
    if (t.includes('hardware') || t.includes('support')) {
        return 'bg-amber-50 text-amber-700 border-amber-200/80';
    }
    return 'bg-slate-100 text-slate-700 border-slate-200';
}
</script>

<template>
    <div class="space-y-6">
        <!-- 1. SUMMARY STAT CARDS -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <!-- Total Records -->
            <div
                class="relative overflow-hidden rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs transition hover:border-slate-300"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-black tracking-widest text-slate-400 uppercase">
                            Total Catatan Servis
                        </p>
                        <h3 class="mt-2 text-2xl font-black text-slate-900">
                            {{ totalCount }}
                        </h3>
                    </div>
                    <div
                        class="flex size-11 items-center justify-center rounded-2xl bg-[#003628]/10 text-[#003628] shadow-xs"
                    >
                        <Wrench class="size-5" />
                    </div>
                </div>
                <p class="mt-2 text-[11px] font-medium text-slate-500">
                    Akumulasi pemeliharaan hardware
                </p>
            </div>

            <!-- Total Cost -->
            <div
                class="relative overflow-hidden rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs transition hover:border-slate-300"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-black tracking-widest text-slate-400 uppercase">
                            Total Biaya Servis
                        </p>
                        <h3 class="mt-2 text-xl font-black text-[#003628] truncate max-w-[200px]">
                            {{ totalCostFormatted }}
                        </h3>
                    </div>
                    <div
                        class="flex size-11 items-center justify-center rounded-2xl bg-amber-50 text-amber-600 shadow-xs"
                    >
                        <Wallet class="size-5" />
                    </div>
                </div>
                <p class="mt-2 text-[11px] font-medium text-slate-500">
                    Estimasi biaya perawatan tercatat
                </p>
            </div>

            <!-- Latest Maintenance Date -->
            <div
                class="relative overflow-hidden rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs transition hover:border-slate-300"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-black tracking-widest text-slate-400 uppercase">
                            Servis Terakhir
                        </p>
                        <h3 class="mt-2 text-lg font-black text-slate-800 truncate">
                            {{ latestDate }}
                        </h3>
                    </div>
                    <div
                        class="flex size-11 items-center justify-center rounded-2xl bg-sky-50 text-sky-600 shadow-xs"
                    >
                        <Calendar class="size-5" />
                    </div>
                </div>
                <p class="mt-2 text-[11px] font-medium text-slate-500">
                    Tanggal servis atau perbaikan terkini
                </p>
            </div>
        </div>

        <!-- 2. SEARCH BAR & HEADER -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h4 class="text-xs font-black tracking-widest text-slate-800 uppercase">
                    {{ title }}
                </h4>
                <p class="text-[11px] text-slate-400">
                    {{ subtitle }}
                </p>
            </div>

            <div class="flex items-center gap-3">
                <!-- Search Input -->
                <div class="relative w-full sm:w-64">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-slate-400"
                    />
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Cari servis, vendor, biaya..."
                        class="h-9 w-full rounded-xl border border-slate-200 bg-white pr-8 pl-8 text-xs font-medium text-slate-700 placeholder-slate-400 transition focus:border-[#003628] focus:ring-1 focus:ring-[#003628] focus:outline-none"
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

        <!-- Filter Count Indicator -->
        <div
            v-if="searchQuery"
            class="flex items-center justify-between rounded-xl bg-slate-50 px-4 py-2 text-[11px] text-slate-500 border border-slate-200/60"
        >
            <span>
                Menampilkan <strong>{{ filteredMaintenances.length }}</strong> dari total
                <strong>{{ maintenances.length }}</strong> catatan pemeliharaan.
            </span>
            <button
                type="button"
                class="font-bold text-[#003628] hover:underline"
                @click="searchQuery = ''"
            >
                Reset Pencarian
            </button>
        </div>

        <!-- 3. MAIN TABLE CONTAINER -->
        <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-xs">
            <!-- Loading State -->
            <div v-if="loading" class="flex flex-col items-center justify-center py-20">
                <div class="size-8 animate-spin rounded-full border-3 border-slate-200 border-t-[#003628]" />
                <p class="mt-4 text-xs font-bold text-slate-500">
                    Memuat catatan pemeliharaan...
                </p>
            </div>

            <!-- Empty State -->
            <div
                v-else-if="!filteredMaintenances.length"
                class="flex flex-col items-center justify-center py-16 text-center"
            >
                <div
                    class="flex size-14 items-center justify-center rounded-2xl bg-slate-50 text-slate-300"
                >
                    <Wrench class="size-7" />
                </div>
                <h4 class="mt-4 text-xs font-bold tracking-wider text-slate-700 uppercase">
                    {{ searchQuery ? 'Tidak Ada Catatan yang Cocok' : 'Belum Ada Riwayat Servis' }}
                </h4>
                <p class="mt-1 max-w-sm text-xs text-slate-400">
                    {{
                        searchQuery
                            ? `Tidak ditemukan riwayat servis dengan kata kunci "${searchQuery}". Coba kata kunci lain.`
                            : 'Perangkat ini belum memiliki catatan servis, kalibrasi, atau pemeliharaan berkala.'
                    }}
                </p>
                <button
                    v-if="searchQuery"
                    type="button"
                    class="mt-4 rounded-xl border border-slate-200 bg-white px-4 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-50 shadow-2xs transition"
                    @click="searchQuery = ''"
                >
                    Bersihkan Pencarian
                </button>
            </div>

            <!-- Records Table -->
            <div v-else class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-600">
                    <thead
                        class="border-b border-slate-100 bg-slate-50/75 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                    >
                        <tr>
                            <th class="px-5 py-3.5">
                                Provider & Judul Servis
                            </th>
                            <th class="px-5 py-3.5">
                                Jenis Pemeliharaan
                            </th>
                            <th class="px-5 py-3.5">
                                Jadwal Pelaksanaan
                            </th>
                            <th class="px-5 py-3.5">
                                Biaya
                            </th>
                            <th class="px-5 py-3.5">
                                Catatan & Keterangan
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="row in paginatedMaintenances"
                            :key="row.id"
                            class="transition hover:bg-slate-50/70"
                        >
                            <!-- Provider & Title -->
                            <td class="px-5 py-4">
                                <div class="flex items-start gap-3">
                                    <div
                                        class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-[#003628] border border-emerald-100/80 shadow-2xs"
                                    >
                                        <Wrench class="size-4" />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-bold text-slate-900 leading-snug">
                                            {{ row.name || 'Servis Pemeliharaan' }}
                                        </p>
                                        <p class="mt-0.5 text-[11px] font-medium text-slate-400 flex items-center gap-1">
                                            <Building2 class="size-3 text-slate-400 shrink-0" />
                                            <span class="truncate">{{ row.supplier || 'Internal IT / Umum' }}</span>
                                        </p>
                                    </div>
                                </div>
                            </td>

                            <!-- Type -->
                            <td class="px-5 py-4 whitespace-nowrap">
                                <div class="flex flex-col gap-1 items-start">
                                    <span
                                        class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider"
                                        :class="getTypeBadgeClass(row.type)"
                                    >
                                        {{ row.type || 'Maintenance' }}
                                    </span>
                                    <span
                                        v-if="row.is_warranty"
                                        class="inline-flex items-center gap-1 text-[10px] font-bold text-indigo-600"
                                    >
                                        <ShieldCheck class="size-3" />
                                        Garansi
                                    </span>
                                </div>
                            </td>

                            <!-- Schedule -->
                            <td class="px-5 py-4 whitespace-nowrap">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-1.5 text-xs font-bold text-slate-800">
                                        <Calendar class="size-3.5 text-slate-400" />
                                        <span>{{ row.start_date || '—' }}</span>
                                    </div>
                                    <div
                                        v-if="row.completion_date"
                                        class="flex items-center gap-1 text-[10px] font-semibold text-emerald-700"
                                    >
                                        <Check class="size-3 stroke-[2.5]" />
                                        <span>Selesai: {{ row.completion_date }}</span>
                                    </div>
                                    <div
                                        v-else
                                        class="flex items-center gap-1 text-[10px] font-medium text-amber-600"
                                    >
                                        <Clock class="size-3" />
                                        <span>Dalam Proses</span>
                                    </div>
                                </div>
                            </td>

                            <!-- Cost -->
                            <td class="px-5 py-4 whitespace-nowrap">
                                <span class="font-mono text-xs font-black text-[#003628]">
                                    {{ row.cost ? (typeof row.cost === 'number' ? 'Rp ' + Number(row.cost).toLocaleString('id-ID') : row.cost) : '—' }}
                                </span>
                            </td>

                            <!-- Notes -->
                            <td class="px-5 py-4">
                                <div
                                    class="max-w-xs text-[11px] text-slate-600 leading-relaxed break-words line-clamp-2"
                                    :title="row.notes || ''"
                                >
                                    {{ row.notes || '—' }}
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div
                v-if="totalPages > 1"
                class="border-t border-slate-100 bg-slate-50/50 p-3"
            >
                <AppPagination
                    :current-page="currentPage"
                    :total-pages="totalPages"
                    :items-per-page="itemsPerPage"
                    :total-items="filteredMaintenances.length"
                    @update:current-page="(p) => (currentPage = p)"
                />
            </div>
        </div>
    </div>
</template>
