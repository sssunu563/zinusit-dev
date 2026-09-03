<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import { useIntervalFn } from '@vueuse/core';
import {
    Activity,
    ArrowUpRight,
    CheckCircle2,
    Clock,
    FileText,
    History,
    Package,
    Plus,
    Search,
    ShieldAlert,
    TicketIcon,
    TrendingUp,
    ShieldCheck,
    Monitor,
    ExternalLink,
    Wrench,
    LayoutGrid,
    AlertTriangle,
    RefreshCw,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted } from 'vue';
import AppDashboardAreaChart from '@/components/AppDashboardAreaChart.vue';
import AppDashboardDoughnut from '@/components/AppDashboardDoughnut.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

interface Summary {
    totalAssets: number;
    totalStb: number;
    totalPeminjaman: number;
    totalInspections: number;
    totalTickets: number;
}
interface Stats {
    activeTickets: number;
    pendingApprovals: number;
    lowStockItems: number;
    resolvedToday: number;
    activeUsersToday: number;
    hardwareReady: number;
}
interface TicketItem {
    id: number;
    requester: string;
    category: string;
    priority: string;
    status: string;
    createdAt: string;
    href: string;
}
interface ActivityItem {
    type: string;
    label: string;
    title: string;
    time: string;
    tone: string;
    href: string;
}
interface AssetBreakdownItem {
    label: string;
    count: number;
    href: string;
    tone: string;
}
interface ConsumableItem {
    id: number;
    name: string;
    remaining: number;
    forecast: string;
    status: string;
    statusLabel: string;
    href: string;
}
interface WarrantyItem {
    id: number;
    name: string;
    tag: string;
    expiry: string;
    daysLeft: number;
    href: string;
}

const props = defineProps<{
    summary: Summary;
    stats: Stats;
    recentTickets: TicketItem[];
    recentActivities: ActivityItem[];
    assetBreakdown: AssetBreakdownItem[];
    consumables: { focusItems: ConsumableItem[] };
    expiringWarranties: WarrantyItem[];
    trend: any[];
    generatedAt: string;
    moduleApprovals?: any[];
    queues?: any;
    assetHighlights?: any[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
];

const getPriorityColor = (p: string) =>
    ({
        Urgent: 'text-rose-600 bg-rose-50 border-rose-100',
        High: 'text-amber-600 bg-amber-50 border-amber-100',
    })[p] ?? 'text-slate-500 bg-slate-50 border-slate-100';

const toneColor: Record<string, string> = {
    emerald: '#10b981',
    sky: '#0ea5e9',
    amber: '#f59e0b',
    rose: '#f43f5e',
    slate: '#64748b',
    purple: '#a855f7',
};

// Top stat cards
const statCards = computed(() => [
    {
        label: 'Total Asset',
        value: props.summary.totalAssets,
        icon: Package,
        color: '#003628',
        bg: 'bg-[#003628]/5',
        href: '/asset',
    },
    {
        label: 'Tiket Aktif',
        value: props.stats.activeTickets,
        icon: TicketIcon,
        color: '#f59e0b',
        bg: 'bg-amber-50',
        href: '/helpdesk',
    },
    {
        label: 'Pending Approval',
        value: props.stats.pendingApprovals,
        icon: Clock,
        color: '#0ea5e9',
        bg: 'bg-sky-50',
        href: '/stb',
    },
    {
        label: 'Stok Kritis',
        value: props.stats.lowStockItems,
        icon: AlertTriangle,
        color: '#f43f5e',
        bg: 'bg-rose-50',
        href: '/asset/consumable',
    },
    {
        label: 'Hardware Siap',
        value: props.stats.hardwareReady,
        icon: Monitor,
        color: '#10b981',
        bg: 'bg-emerald-50',
        href: '/asset',
    },
    {
        label: 'Inspection',
        value: props.summary.totalInspections,
        icon: Wrench,
        color: '#a855f7',
        bg: 'bg-purple-50',
        href: '/inspection',
    },
]);

// Module quick-access
const modules = computed(() => [
    {
        label: 'STB',
        count: props.summary.totalStb,
        pending: props.queues?.pendingStb ?? 0,
        href: '/stb',
        color: '#003628',
    },
    {
        label: 'Peminjaman',
        count: props.summary.totalPeminjaman,
        pending: props.queues?.pendingPeminjaman ?? 0,
        href: '/peminjaman',
        color: '#0ea5e9',
    },
    {
        label: 'Inspection',
        count: props.summary.totalInspections,
        pending: 0,
        href: '/inspection',
        color: '#a855f7',
    },
    {
        label: 'Helpdesk',
        count: props.summary.totalTickets,
        pending: props.stats.activeTickets,
        href: '/helpdesk',
        color: '#f59e0b',
    },
]);

const { pause, resume } = useIntervalFn(() => {
    router.reload({
        only: [
            'summary',
            'stats',
            'recentTickets',
            'recentActivities',
            'assetBreakdown',
            'consumables',
            'expiringWarranties',
            'trend',
            'generatedAt',
        ],
        preserveScroll: true,
        preserveState: true,
    });
}, 30000);

onMounted(() => resume());
onUnmounted(() => pause());
</script>

<template>
    <Head title="Dashboard" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-[1800px] space-y-5 p-4 md:p-6">
            <header
                class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center"
            >
                <div>
                    <h1
                        class="text-xl font-black tracking-tight text-slate-900"
                    >
                        Dashboard
                    </h1>
                    <div
                        class="mt-1 flex items-center gap-2 text-[10px] font-semibold tracking-widest text-slate-400 uppercase"
                    >
                        <span class="inline-flex items-center gap-1.5">
                            <span
                                class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-400"
                            />
                            <span class="text-emerald-500">Live</span>
                        </span>
                        <span class="text-slate-200">·</span>
                        <span>{{ generatedAt }}</span>
                        <span class="text-slate-200">·</span>
                        <span>{{ stats.activeUsersToday }} pengguna aktif</span>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <a
                        href="/check-assets"
                        target="_blank"
                        class="flex h-8 items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 text-[10px] font-bold tracking-widest text-slate-500 uppercase shadow-sm transition-all hover:border-[#003628]/30 hover:text-[#003628]"
                    >
                        <ExternalLink class="size-3.5" /> Portal Aset
                    </a>
                    <Link
                        href="/helpdesk"
                        class="flex h-8 items-center gap-2 rounded-lg bg-[#003628] px-4 text-[10px] font-bold tracking-widest text-white uppercase shadow-md shadow-[#003628]/25 transition-all hover:bg-[#004d38] active:scale-95"
                    >
                        <Plus class="size-3.5" /> New Ticket
                    </Link>
                </div>
            </header>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
                <Link
                    v-for="card in statCards"
                    :key="card.label"
                    :href="card.href"
                    class="group flex flex-col gap-3 rounded-xl border border-slate-100 bg-white p-4 transition-all duration-200 hover:-translate-y-0.5 hover:border-slate-200 hover:shadow-md"
                >
                    <div class="flex items-center justify-between">
                        <div
                            class="flex h-9 w-9 items-center justify-center rounded-xl"
                            :class="card.bg"
                        >
                            <component
                                :is="card.icon"
                                class="size-4"
                                :style="{ color: card.color }"
                            />
                        </div>
                        <ArrowUpRight
                            class="size-3.5 text-slate-200 transition-colors group-hover:text-slate-400"
                        />
                    </div>
                    <div>
                        <div
                            class="text-[26px] leading-none font-black tracking-tight text-slate-900"
                        >
                            {{ card.value }}
                        </div>
                        <div
                            class="mt-1.5 text-[10px] font-semibold tracking-widest text-slate-400 uppercase"
                        >
                            {{ card.label }}
                        </div>
                    </div>
                </Link>
            </div>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <Link
                    v-for="mod in modules"
                    :key="mod.label"
                    :href="mod.href"
                    class="group flex items-center justify-between rounded-xl border border-slate-100 bg-white px-4 py-3.5 transition-all duration-200 hover:border-slate-200 hover:shadow-sm"
                >
                    <div>
                        <div
                            class="text-[10px] font-semibold tracking-widest text-slate-400 uppercase"
                        >
                            {{ mod.label }}
                        </div>
                        <div
                            class="mt-0.5 text-xl leading-none font-black text-slate-900"
                        >
                            {{ mod.count }}
                            <span class="text-[11px] font-medium text-slate-300"
                                >total</span
                            >
                        </div>
                    </div>
                    <div
                        v-if="mod.pending > 0"
                        class="flex h-6 min-w-[26px] items-center justify-center rounded-full px-2 text-[10px] font-black text-white"
                        :style="{ backgroundColor: mod.color }"
                    >
                        {{ mod.pending }}
                    </div>
                    <div
                        v-else
                        class="flex h-7 w-7 items-center justify-center rounded-full bg-emerald-50"
                    >
                        <CheckCircle2 class="size-3.5 text-emerald-400" />
                    </div>
                </Link>
            </div>

            <div class="grid grid-cols-1 gap-5 xl:grid-cols-[1fr_360px]">
                <!-- LEFT: Chart + Tickets + Consumables -->
                <div class="space-y-5">
                    <!-- Trend Chart -->
                    <section
                        class="rounded-xl border border-slate-100 bg-white p-5"
                    >
                        <div
                            class="mb-5 flex flex-col justify-between gap-3 sm:flex-row sm:items-center"
                        >
                            <div>
                                <h2
                                    class="flex items-center gap-2 text-[12px] font-black tracking-widest text-slate-900 uppercase"
                                >
                                    <TrendingUp class="size-4 text-[#003628]" />
                                    Tren Operasional
                                </h2>
                                <p
                                    class="mt-0.5 text-[10px] font-semibold tracking-widest text-slate-400 uppercase"
                                >
                                    6 bulan terakhir
                                </p>
                            </div>
                            <div class="flex items-center gap-4">
                                <div
                                    v-for="(item, i) in [
                                        ['Tiket', '#10b981'],
                                        ['Handover', '#0ea5e9'],
                                        ['Pinjam', '#f59e0b'],
                                    ]"
                                    :key="i"
                                    class="flex items-center gap-1.5 text-[9px] font-semibold tracking-widest text-slate-400 uppercase"
                                >
                                    <div
                                        class="h-2 w-5 rounded-full"
                                        :style="{ backgroundColor: item[1] }"
                                    />
                                    {{ item[0] }}
                                </div>
                            </div>
                        </div>
                        <AppDashboardAreaChart :data="trend" :height="240" />
                    </section>

                    <!-- Recent Tickets + Stats row -->
                    <div
                        class="grid grid-cols-1 gap-4 md:grid-cols-[1fr_260px]"
                    >
                        <!-- Recent Tickets -->
                        <section
                            class="overflow-hidden rounded-xl border border-slate-100 bg-white"
                        >
                            <div
                                class="flex items-center justify-between border-b border-slate-50 px-4 py-3.5"
                            >
                                <h2
                                    class="text-[11px] font-black tracking-widest text-slate-900 uppercase"
                                >
                                    Tiket Terbaru
                                </h2>
                                <Link
                                    href="/helpdesk"
                                    class="flex items-center gap-1 text-[10px] font-semibold tracking-widest text-[#003628] uppercase transition-opacity hover:opacity-70"
                                >
                                    Lihat Semua <ArrowUpRight class="size-3" />
                                </Link>
                            </div>
                            <div class="divide-y divide-slate-50">
                                <Link
                                    v-for="ticket in recentTickets"
                                    :key="ticket.id"
                                    :href="ticket.href"
                                    class="group flex items-center justify-between px-4 py-3 transition-colors hover:bg-slate-50/70"
                                >
                                    <div
                                        class="flex min-w-0 items-center gap-3"
                                    >
                                        <div
                                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[#003628]/5 text-[11px] font-black text-[#003628]"
                                        >
                                            {{
                                                ticket.requester
                                                    .charAt(0)
                                                    .toUpperCase()
                                            }}
                                        </div>
                                        <div class="min-w-0">
                                            <p
                                                class="truncate text-[12px] font-semibold text-slate-800 transition-colors group-hover:text-[#003628]"
                                            >
                                                {{ ticket.requester }}
                                            </p>
                                            <p
                                                class="mt-0.5 text-[9px] font-medium tracking-widest text-slate-400 uppercase"
                                            >
                                                {{ ticket.category }} ·
                                                {{ ticket.createdAt }}
                                            </p>
                                        </div>
                                    </div>
                                    <span
                                        class="ml-3 shrink-0 rounded-md border px-2 py-0.5 text-[9px] font-bold tracking-widest uppercase"
                                        :class="
                                            getPriorityColor(ticket.priority)
                                        "
                                    >
                                        {{ ticket.priority }}
                                    </span>
                                </Link>
                                <div
                                    v-if="!recentTickets.length"
                                    class="px-4 py-8 text-center text-[10px] font-medium tracking-widest text-slate-300 uppercase"
                                >
                                    Tidak ada tiket aktif
                                </div>
                            </div>
                        </section>

                        <!-- Quick Stats column -->
                        <div class="space-y-3">
                            <!-- Resolved today -->
                            <div
                                class="rounded-xl border border-slate-100 bg-white p-4"
                            >
                                <div
                                    class="mb-2 flex items-center justify-between"
                                >
                                    <div
                                        class="text-[10px] font-semibold tracking-widest text-slate-400 uppercase"
                                    >
                                        Selesai Hari Ini
                                    </div>
                                    <CheckCircle2
                                        class="size-3.5 text-[#003628]/40"
                                    />
                                </div>
                                <div
                                    class="text-3xl leading-none font-black tracking-tight text-slate-900"
                                >
                                    {{ stats.resolvedToday }}
                                </div>
                                <div
                                    class="mt-3 h-1 overflow-hidden rounded-full bg-slate-100"
                                >
                                    <div
                                        class="h-full rounded-full bg-[#003628] transition-all duration-700"
                                        :style="{
                                            width: `${Math.min(stats.resolvedToday * 20, 100)}%`,
                                        }"
                                    />
                                </div>
                                <div
                                    class="mt-1.5 text-[9px] font-medium tracking-widest text-slate-400 uppercase"
                                >
                                    tiket helpdesk
                                </div>
                            </div>
                            <!-- Hardware ready -->
                            <div
                                class="rounded-xl border border-slate-100 bg-white p-4"
                            >
                                <div
                                    class="mb-2 flex items-center justify-between"
                                >
                                    <div
                                        class="text-[10px] font-semibold tracking-widest text-slate-400 uppercase"
                                    >
                                        Hardware Siap
                                    </div>
                                    <Monitor
                                        class="size-3.5 text-emerald-400/60"
                                    />
                                </div>
                                <div
                                    class="text-3xl leading-none font-black tracking-tight text-emerald-600"
                                >
                                    {{ stats.hardwareReady }}
                                </div>
                                <div
                                    class="mt-2 text-[9px] font-medium tracking-widest text-slate-400 uppercase"
                                >
                                    unit tersedia
                                </div>
                            </div>
                            <!-- Consumable alert -->
                            <div
                                class="rounded-xl border border-slate-100 bg-white p-4"
                            >
                                <div
                                    class="mb-2 flex items-center justify-between"
                                >
                                    <div
                                        class="text-[10px] font-semibold tracking-widest text-slate-400 uppercase"
                                    >
                                        Stok Kritis
                                    </div>
                                    <AlertTriangle
                                        class="size-3.5"
                                        :class="
                                            stats.lowStockItems > 0
                                                ? 'text-rose-400/60'
                                                : 'text-emerald-400/60'
                                        "
                                    />
                                </div>
                                <div
                                    class="text-3xl leading-none font-black tracking-tight"
                                    :class="
                                        stats.lowStockItems > 0
                                            ? 'text-rose-500'
                                            : 'text-emerald-500'
                                    "
                                >
                                    {{ stats.lowStockItems }}
                                </div>
                                <div
                                    class="mt-2 text-[9px] font-medium tracking-widest text-slate-400 uppercase"
                                >
                                    item consumable
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Consumable Forecast -->
                    <section
                        v-if="consumables.focusItems.length"
                        class="rounded-xl border border-amber-100 bg-amber-50/60 p-4"
                    >
                        <div class="mb-3 flex items-center justify-between">
                            <h2
                                class="flex items-center gap-2 text-[11px] font-black tracking-widest text-amber-700 uppercase"
                            >
                                <AlertTriangle class="size-3.5" /> Inventory
                                Forecast
                            </h2>
                            <Link
                                href="/asset/consumable"
                                class="flex items-center gap-1 text-[10px] font-semibold tracking-widest text-amber-600 uppercase transition-opacity hover:opacity-70"
                            >
                                Lihat Semua <ArrowUpRight class="size-3" />
                            </Link>
                        </div>
                        <div
                            class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3"
                        >
                            <Link
                                v-for="item in consumables.focusItems"
                                :key="item.id"
                                :href="item.href"
                                class="group flex items-center justify-between rounded-lg border border-amber-100 bg-white p-3 shadow-sm transition-all hover:border-amber-200 hover:bg-amber-50"
                            >
                                <div class="min-w-0">
                                    <p
                                        class="truncate text-[11px] font-semibold text-slate-700"
                                    >
                                        {{ item.name }}
                                    </p>
                                    <p
                                        class="mt-0.5 text-[9px] font-medium tracking-widest text-amber-500 uppercase"
                                    >
                                        {{ item.forecast }}
                                    </p>
                                </div>
                                <span
                                    class="ml-2 shrink-0 text-[13px] font-black"
                                    :class="
                                        item.status === 'empty'
                                            ? 'text-rose-500'
                                            : 'text-amber-500'
                                    "
                                >
                                    {{ item.remaining }}
                                </span>
                            </Link>
                        </div>
                    </section>
                </div>

                <!-- RIGHT: Asset mix + Warranty + Timeline -->
                <aside class="space-y-5">
                    <!-- Asset Composition -->
                    <section
                        class="rounded-xl border border-slate-100 bg-white p-5"
                    >
                        <div class="mb-4 flex items-start justify-between">
                            <div>
                                <h2
                                    class="text-[11px] font-black tracking-widest text-slate-900 uppercase"
                                >
                                    Komposisi Asset
                                </h2>
                                <p
                                    class="mt-0.5 text-[10px] font-semibold tracking-widest text-slate-400 uppercase"
                                >
                                    {{ summary.totalAssets }} item total
                                </p>
                            </div>
                            <Link
                                href="/asset"
                                class="flex items-center gap-1 text-[10px] font-semibold tracking-widest text-[#003628] uppercase transition-opacity hover:opacity-70"
                            >
                                Detail <ArrowUpRight class="size-3" />
                            </Link>
                        </div>
                        <div class="mb-4 flex justify-center">
                            <AppDashboardDoughnut
                                :data="assetBreakdown"
                                :total="summary.totalAssets"
                                :size="170"
                                :strokeWidth="22"
                            />
                        </div>
                        <div class="space-y-1">
                            <Link
                                v-for="item in assetBreakdown"
                                :key="item.label"
                                :href="item.href"
                                class="group flex items-center justify-between rounded-lg px-3 py-1.5 transition-colors hover:bg-slate-50"
                            >
                                <div class="flex items-center gap-2.5">
                                    <div
                                        class="h-2 w-2 shrink-0 rounded-full"
                                        :style="{
                                            backgroundColor:
                                                toneColor[item.tone] ??
                                                '#94a3b8',
                                        }"
                                    />
                                    <span
                                        class="text-[11px] font-medium text-slate-600 transition-colors group-hover:text-slate-900"
                                        >{{ item.label }}</span
                                    >
                                </div>
                                <span
                                    class="text-[12px] font-bold text-slate-900"
                                    >{{ item.count }}</span
                                >
                            </Link>
                        </div>
                    </section>

                    <!-- Warranty Alert -->
                    <section
                        class="overflow-hidden rounded-xl border border-slate-100 bg-white"
                    >
                        <div
                            class="flex items-center justify-between border-b border-slate-50 px-4 py-3.5"
                        >
                            <h2
                                class="flex items-center gap-2 text-[11px] font-black tracking-widest text-slate-900 uppercase"
                            >
                                <ShieldCheck class="size-3.5 text-rose-400" />
                                Garansi Hampir Habis
                            </h2>
                            <span
                                v-if="expiringWarranties.length"
                                class="flex h-5 min-w-[20px] items-center justify-center rounded-full bg-rose-500 px-1.5 text-[9px] font-bold text-white"
                            >
                                {{ expiringWarranties.length }}
                            </span>
                        </div>
                        <div class="divide-y divide-slate-50">
                            <Link
                                v-for="asset in expiringWarranties"
                                :key="asset.id"
                                :href="asset.href"
                                class="group flex items-center gap-3 px-4 py-3 transition-colors hover:bg-slate-50/70"
                            >
                                <div
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-rose-50 text-rose-400 transition-all group-hover:bg-rose-500 group-hover:text-white"
                                >
                                    <Monitor class="size-3.5" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="truncate text-[11px] font-semibold text-slate-800"
                                    >
                                        {{ asset.name }}
                                    </p>
                                    <p
                                        class="mt-0.5 text-[9px] font-medium tracking-widest text-rose-400 uppercase"
                                    >
                                        {{ asset.expiry }} ·
                                        {{ asset.daysLeft }}h lagi
                                    </p>
                                </div>
                            </Link>
                            <div
                                v-if="!expiringWarranties.length"
                                class="px-4 py-6 text-center"
                            >
                                <div
                                    class="inline-flex items-center gap-1.5 text-[10px] font-medium tracking-widest text-emerald-400 uppercase"
                                >
                                    <ShieldCheck class="size-3.5" /> Semua
                                    garansi aman
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Activity Timeline -->
                    <section
                        class="rounded-xl border border-slate-100 bg-white p-5"
                    >
                        <h2
                            class="mb-4 flex items-center gap-2 text-[11px] font-black tracking-widest text-slate-900 uppercase"
                        >
                            <History class="size-3.5 text-[#003628]" />
                            Aktivitas Terbaru
                        </h2>
                        <div class="relative space-y-4">
                            <div
                                class="absolute top-2 bottom-2 left-[7px] w-px bg-slate-100"
                            />
                            <div
                                v-for="(activity, i) in recentActivities"
                                :key="i"
                                class="group relative pl-6"
                            >
                                <div
                                    class="absolute top-1 left-0 z-10 h-3.5 w-3.5 rounded-full border-2 border-white bg-slate-200 shadow-sm transition-colors group-hover:bg-[#003628]"
                                    :style="{
                                        backgroundColor: toneColor[
                                            activity.tone
                                        ]
                                            ? toneColor[activity.tone] + '30'
                                            : undefined,
                                    }"
                                />
                                <div class="space-y-0.5">
                                    <div
                                        class="flex items-center justify-between gap-2"
                                    >
                                        <span
                                            class="text-[9px] font-bold tracking-widest uppercase"
                                            :style="{
                                                color:
                                                    toneColor[activity.tone] ??
                                                    '#64748b',
                                            }"
                                        >
                                            {{ activity.label }}
                                        </span>
                                        <span
                                            class="shrink-0 text-[9px] font-medium text-slate-300"
                                            >{{ activity.time }}</span
                                        >
                                    </div>
                                    <p
                                        class="line-clamp-1 text-[12px] leading-snug font-semibold text-slate-800"
                                    >
                                        {{ activity.title }}
                                    </p>
                                    <Link
                                        :href="activity.href"
                                        class="inline-flex items-center gap-1 text-[9px] font-bold tracking-widest text-slate-400 uppercase transition-colors hover:text-[#003628]"
                                    >
                                        Detail <ArrowUpRight class="size-2.5" />
                                    </Link>
                                </div>
                            </div>
                            <div
                                v-if="!recentActivities.length"
                                class="py-4 text-center text-[10px] font-medium tracking-widest text-slate-300 uppercase"
                            >
                                Belum ada aktivitas
                            </div>
                        </div>
                    </section>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>
