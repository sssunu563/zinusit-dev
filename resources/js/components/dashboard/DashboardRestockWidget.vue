<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowUpRight,
    History,
    PackageCheck,
    TrendingDown,
} from 'lucide-vue-next';

interface ConsumableItem {
    id: number;
    name: string;
    remaining: number;
    minimum?: number;
    location?: string;
    status: string;
    statusLabel: string;
    forecast: string;
    href: string;
}

interface StockHistoryItem {
    id: number;
    assetId: number;
    assetName: string;
    qty: number;
    poNumber: string | null;
    purchaseDate: string | null;
    createdAt: string;
    notes: string | null;
    href: string;
}

interface RestockLeader {
    assetId: number;
    assetName: string;
    totalQty: number;
    transactions: number;
    latestPurchaseDate: string | null;
    href: string;
}

defineProps<{
    focusItems: ConsumableItem[];
    stockHistory: StockHistoryItem[];
    restockLeaders?: RestockLeader[];
}>();
</script>

<template>
    <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
        <!-- Critical & Forecast Consumables -->
        <section
            class="rounded-2xl border border-amber-100 bg-linear-to-b from-amber-50/40 to-white p-5 shadow-xs"
        >
            <div class="mb-4 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div
                        class="flex h-6 w-6 items-center justify-center rounded-lg bg-amber-100 text-amber-800"
                    >
                        <AlertTriangle class="size-3.5" />
                    </div>
                    <div>
                        <h2
                            class="text-sm font-bold tracking-tight text-slate-900"
                        >
                            Inventori Kritis & Estimasi Habis
                        </h2>
                        <p class="text-xs text-slate-400">
                            Prediksi burn-down berdasarkan pemakaian 90 hari
                        </p>
                    </div>
                </div>
                <Link
                    href="/asset/consumable"
                    class="inline-flex items-center gap-1 text-xs font-semibold text-amber-700 hover:underline"
                >
                    Semua <ArrowUpRight class="size-3" />
                </Link>
            </div>

            <div class="space-y-2.5">
                <Link
                    v-for="item in focusItems"
                    :key="item.id"
                    :href="item.href"
                    class="group flex items-center justify-between rounded-xl border border-amber-200/50 bg-white p-3 shadow-2xs transition-all hover:border-amber-300 hover:shadow-sm"
                >
                    <div class="min-w-0 flex-1 pr-3">
                        <div class="flex items-center gap-2">
                            <h3
                                class="truncate text-xs font-bold text-slate-900 group-hover:text-amber-800"
                            >
                                {{ item.name }}
                            </h3>
                            <span
                                class="py-0.2 rounded-md px-1.5 text-[10px] font-bold tracking-wider uppercase"
                                :class="
                                    item.status === 'empty'
                                        ? 'bg-rose-50 text-rose-600'
                                        : 'bg-amber-50 text-amber-700'
                                "
                            >
                                {{ item.statusLabel }}
                            </span>
                        </div>
                        <div
                            class="mt-1 flex items-center gap-2 text-[11px] text-slate-400"
                        >
                            <span
                                class="inline-flex items-center gap-1 font-medium text-amber-600"
                            >
                                <TrendingDown class="size-3" />
                                {{ item.forecast }}
                            </span>
                            <span v-if="item.location"
                                >· {{ item.location }}</span
                            >
                        </div>
                    </div>

                    <div class="shrink-0 text-right">
                        <div
                            class="text-base font-black"
                            :class="
                                item.status === 'empty'
                                    ? 'text-rose-600'
                                    : 'text-amber-600'
                            "
                        >
                            {{ item.remaining }}
                            <span
                                class="text-[10px] font-semibold text-slate-400"
                                >unit</span
                            >
                        </div>
                    </div>
                </Link>

                <div
                    v-if="!focusItems.length"
                    class="flex flex-col items-center justify-center rounded-xl border border-dashed border-emerald-200 bg-emerald-50/50 py-8 text-center"
                >
                    <PackageCheck class="mb-1 size-6 text-emerald-500" />
                    <p class="text-xs font-bold text-emerald-700">
                        Semua stok barang habis pakai aman
                    </p>
                    <p class="text-[11px] text-emerald-600/80">
                        Tidak ada item dalam ambang batas kritis
                    </p>
                </div>
            </div>
        </section>

        <!-- Recent PO Restock Logs -->
        <section
            class="rounded-2xl border border-slate-100 bg-white p-5 shadow-xs"
        >
            <div class="mb-4 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div
                        class="flex h-6 w-6 items-center justify-center rounded-lg bg-[#003628]/10 text-[#003628]"
                    >
                        <History class="size-3.5" />
                    </div>
                    <div>
                        <h2
                            class="text-sm font-bold tracking-tight text-slate-900"
                        >
                            Riwayat Pembelian & Penambahan Stok
                        </h2>
                        <p class="text-xs text-slate-400">
                            Transaksi penambahan stok barang habis pakai terbaru
                        </p>
                    </div>
                </div>
                <Link
                    href="/asset/consumable"
                    class="inline-flex items-center gap-1 text-xs font-semibold text-[#003628] hover:underline"
                >
                    Detail <ArrowUpRight class="size-3" />
                </Link>
            </div>

            <div class="divide-y divide-slate-50">
                <div
                    v-for="log in stockHistory"
                    :key="log.id"
                    class="flex items-center justify-between py-2.5 first:pt-0 last:pb-0"
                >
                    <div class="min-w-0 flex-1 pr-3">
                        <p class="truncate text-xs font-bold text-slate-800">
                            {{ log.assetName }}
                        </p>
                        <div
                            class="mt-0.5 flex items-center gap-2 text-[10px] text-slate-400"
                        >
                            <span
                                v-if="log.poNumber"
                                class="font-semibold text-slate-600"
                            >
                                PO: {{ log.poNumber }}
                            </span>
                            <span>·</span>
                            <span>{{ log.purchaseDate || log.createdAt }}</span>
                        </div>
                    </div>
                    <div class="shrink-0 text-right">
                        <span
                            class="inline-flex items-center gap-1 rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-bold text-emerald-700"
                        >
                            +{{ log.qty }} unit
                        </span>
                    </div>
                </div>

                <div
                    v-if="!stockHistory.length"
                    class="py-8 text-center text-xs font-medium text-slate-400"
                >
                    Belum ada riwayat penambahan stok
                </div>
            </div>
        </section>
    </div>
</template>
