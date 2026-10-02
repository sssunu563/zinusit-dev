<script setup lang="ts">
import { computed } from 'vue';
import {
    Activity,
    AlertCircle,
    ArrowUpRight,
    Award,
    BarChart3,
    CheckCircle2,
    Clock,
    Flame,
    Inbox,
    Shield,
    Ticket,
    TrendingUp,
    Users,
    Wrench,
} from 'lucide-vue-next';

interface Analytics {
    total: number;
    open: number;
    inProgress: number;
    closed: number;
    completionRate: number;
    avgResolutionTime: number;
    topCategories: Array<{ name: string; count: number; percentage: number }>;
    topRequesters: Array<{ name: string; department: string; location: string; count: number; percentage: number }>;
    statusDistribution: Array<{ status: string; count: number; percentage: number }>;
    priorityDistribution: Array<{ priority: string; count: number; percentage: number }>;
    recentTickets: Array<{
        id: number;
        requester: string;
        category: string;
        status: string;
        priority: string;
        created_at?: string | null;
        issue_description: string;
    }>;
    technicianStats: Array<{ name: string; count: number }>;
}

interface Props {
    analytics: Analytics;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'switch-tab', tab: 'summary' | 'list'): void;
}>();

// ── Computed Metrics ──
const activeCount = computed(() => props.analytics.open + props.analytics.inProgress);
const activePct = computed(() =>
    props.analytics.total > 0
        ? Math.round((activeCount.value / props.analytics.total) * 1000) / 10
        : 0
);

// ── Helpers ──
function initials(name: string): string {
    const parts = (name || '').trim().split(/\s+/);
    return parts.length >= 2
        ? (parts[0][0] + parts[1][0]).toUpperCase()
        : (name || 'U').slice(0, 2).toUpperCase();
}

const AVATAR_BG = ['bg-slate-100 text-slate-700', 'bg-[#003628]/10 text-[#003628]', 'bg-zinc-100 text-zinc-700'];
function avatarCls(name: string) {
    let h = 0;
    for (let i = 0; i < (name || '').length; i++) h = name.charCodeAt(i) + ((h << 5) - h);
    return AVATAR_BG[Math.abs(h) % AVATAR_BG.length];
}

function getPriorityColor(priority: string) {
    switch (priority) {
        case 'Critical':
            return { bg: 'bg-rose-50 text-rose-700 border-rose-200', dot: 'bg-rose-500' };
        case 'High':
            return { bg: 'bg-orange-50 text-orange-700 border-orange-200', dot: 'bg-orange-500' };
        case 'Medium':
            return { bg: 'bg-amber-50 text-amber-700 border-amber-200', dot: 'bg-amber-500' };
        default:
            return { bg: 'bg-slate-50 text-slate-700 border-slate-200', dot: 'bg-slate-400' };
    }
}
</script>

<template>
    <div class="flex h-full min-h-0 flex-col gap-3.5">

        <!-- ══════════════════════════════
             1. TOP METRICS STRIP (Simetris 4 Card)
        ══════════════════════════════ -->
        <div class="grid flex-shrink-0 grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">

            <!-- Card 1: Total Tiket -->
            <div
                class="group relative flex flex-col justify-between overflow-hidden rounded-xl border border-t-4 border-slate-200/90 bg-white p-4 shadow-2xs transition-all duration-200 hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-xs"
                style="border-top-color: #003628"
            >
                <div class="flex items-center justify-between">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#003628]/10 text-[#003628]">
                        <Ticket class="size-4" />
                    </div>
                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[9px] font-bold text-emerald-700">
                        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500" />
                        Total Database
                    </span>
                </div>
                <div class="mt-3">
                    <div class="text-3xl font-black tracking-tight text-slate-900">{{ analytics.total }}</div>
                    <div class="text-[10px] font-bold tracking-wider text-slate-500 uppercase">Total Tiket Terdaftar</div>
                    <div class="mt-0.5 text-[10px] font-medium text-slate-400">Seluruh laporan kendala & permintaan IT Zinus</div>
                </div>
            </div>

            <!-- Card 2: Perlu Penanganan (Open + In Progress) -->
            <div
                class="group relative flex cursor-pointer flex-col justify-between overflow-hidden rounded-xl border border-t-4 border-slate-200/90 bg-white p-4 shadow-2xs transition-all duration-200 hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-xs"
                style="border-top-color: #f59e0b"
                @click="emit('switch-tab', 'list')"
            >
                <div class="flex items-center justify-between">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50 text-amber-700">
                        <Clock class="size-4" />
                    </div>
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-slate-50 text-slate-400 transition-colors group-hover:bg-[#003628]/10 group-hover:text-[#003628]">
                        <ArrowUpRight class="size-3.5" />
                    </span>
                </div>
                <div class="mt-3">
                    <div class="flex items-baseline gap-2">
                        <span class="text-3xl font-black tracking-tight text-slate-900">{{ activeCount }}</span>
                        <span class="text-xs font-bold text-amber-700">({{ activePct }}%)</span>
                    </div>
                    <div class="text-[10px] font-bold tracking-wider text-slate-500 uppercase">Perlu Penanganan (Aktif)</div>
                    <div class="mt-0.5 text-[10px] font-medium text-slate-400">Open: {{ analytics.open }} • Diproses: {{ analytics.inProgress }}</div>
                </div>
            </div>

            <!-- Card 3: Tiket Selesai -->
            <div
                class="group relative flex flex-col justify-between overflow-hidden rounded-xl border border-t-4 border-slate-200/90 bg-white p-4 shadow-2xs transition-all duration-200 hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-xs"
                style="border-top-color: #10b981"
            >
                <div class="flex items-center justify-between">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700">
                        <CheckCircle2 class="size-4" />
                    </div>
                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[9px] font-bold text-emerald-700">
                        {{ analytics.completionRate }}% Sukses
                    </span>
                </div>
                <div class="mt-3">
                    <div class="text-3xl font-black tracking-tight text-slate-900">{{ analytics.closed }}</div>
                    <div class="text-[10px] font-bold tracking-wider text-slate-500 uppercase">Tiket Selesai (Closed)</div>
                    <div class="mt-0.5 text-[10px] font-medium text-slate-400">Tingkat penyelesaian masalah IT tercapai</div>
                </div>
            </div>

            <!-- Card 4: Rata-rata Resolusi -->
            <div
                class="group relative flex flex-col justify-between overflow-hidden rounded-xl border border-t-4 border-slate-200/90 bg-white p-4 shadow-2xs transition-all duration-200 hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-xs"
                style="border-top-color: #3b82f6"
            >
                <div class="flex items-center justify-between">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-700">
                        <TrendingUp class="size-4" />
                    </div>
                    <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2 py-0.5 text-[9px] font-bold text-blue-700">
                        Kecepatan IT
                    </span>
                </div>
                <div class="mt-3">
                    <div class="text-3xl font-black tracking-tight text-slate-900">{{ analytics.avgResolutionTime }} <span class="text-lg font-bold text-slate-500">jam</span></div>
                    <div class="text-[10px] font-bold tracking-wider text-slate-500 uppercase">Rata-Rata Resolusi</div>
                    <div class="mt-0.5 text-[10px] font-medium text-slate-400">Durasi rata-rata penutupan laporan tiket</div>
                </div>
            </div>

        </div>

        <!-- ══════════════════════════════
             2. MAIN LOWER AREA: 3 CARDS KIRI + 1 CARD VERTICAL KANAN
        ══════════════════════════════ -->
        <div class="grid min-h-0 flex-1 grid-cols-1 gap-3.5 xl:grid-cols-12">

            <!-- SISI KIRI (3 Kolom Berdampingan: Status & Prioritas, Kategori, Teknisi) -->
            <div class="grid min-h-0 flex-1 grid-cols-1 md:grid-cols-3 gap-3.5 xl:col-span-8 2xl:col-span-9">

                <!-- ── Card 1: Status & Prioritas ── -->
                <div class="flex min-h-0 flex-col overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-2xs">
                    <!-- Head -->
                    <div class="flex flex-shrink-0 items-center justify-between border-b border-slate-100 bg-slate-50/40 px-3.5 py-3">
                        <div class="flex items-center gap-2">
                            <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-[#003628]/10 text-[#003628]">
                                <Shield class="size-3.5" />
                            </div>
                            <h2 class="text-xs font-bold text-slate-800">Status & Prioritas</h2>
                        </div>
                        <span class="rounded-md border border-slate-200/80 bg-white px-1.5 py-0.5 text-[10px] font-bold text-slate-600 shadow-2xs">
                            Live
                        </span>
                    </div>

                    <!-- List (Scrollable) -->
                    <div class="min-h-0 flex-1 overflow-y-auto p-3 space-y-3 custom-scroll">
                        <!-- Status Bars -->
                        <div class="space-y-2.5">
                            <div
                                v-for="status in analytics.statusDistribution"
                                :key="status.status"
                                class="rounded-lg border border-slate-100 bg-slate-50/50 p-2.5 transition-colors hover:bg-slate-50"
                            >
                                <div class="flex items-center justify-between text-[11px]">
                                    <div class="flex items-center gap-1.5">
                                        <span
                                            class="h-2 w-2 flex-shrink-0 rounded-full"
                                            :class="{
                                                'bg-amber-500': status.status === 'Open',
                                                'bg-blue-500': status.status === 'In Progress',
                                                'bg-emerald-500': status.status === 'Closed'
                                            }"
                                        />
                                        <span class="font-semibold text-slate-800">{{ status.status }}</span>
                                    </div>
                                    <span class="rounded bg-white px-1.5 py-0.5 text-[10px] font-bold text-slate-700 shadow-2xs border border-slate-100">
                                        {{ status.count }} tiket
                                    </span>
                                </div>
                                <div class="mt-2 flex items-center gap-2">
                                    <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-200/70">
                                        <div
                                            class="h-full rounded-full transition-all duration-500"
                                            :class="{
                                                'bg-amber-500': status.status === 'Open',
                                                'bg-blue-500': status.status === 'In Progress',
                                                'bg-emerald-500': status.status === 'Closed'
                                            }"
                                            :style="{ width: `${status.percentage}%` }"
                                        />
                                    </div>
                                    <span class="text-[9px] font-medium text-slate-400 w-9 text-right">{{ status.percentage }}%</span>
                                </div>
                            </div>
                        </div>

                        <!-- Priority Breakdown -->
                        <div class="border-t border-slate-100 pt-3">
                            <div class="mb-2 flex items-center justify-between">
                                <span class="text-[10px] font-bold tracking-wider text-slate-400 uppercase">Tingkat Prioritas</span>
                                <span class="text-[10px] font-bold text-slate-500">{{ analytics.priorityDistribution.length }} Level</span>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div
                                    v-for="p in analytics.priorityDistribution"
                                    :key="p.priority"
                                    class="rounded-lg border p-2 text-center"
                                    :class="getPriorityColor(p.priority).bg"
                                >
                                    <div class="text-[10px] font-semibold opacity-75">{{ p.priority }}</div>
                                    <div class="text-base font-black tracking-tight">{{ p.count }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Foot -->
                    <div class="flex flex-shrink-0 items-center justify-between border-t border-slate-100 bg-slate-50/30 px-3.5 py-2 text-[10px] font-medium text-slate-500">
                        <span>Aktif Penanganan</span>
                        <span class="font-bold text-slate-700">{{ activeCount }} tiket</span>
                    </div>
                </div>

                <!-- ── Card 2: Kategori Permasalahan ── -->
                <div class="flex min-h-0 flex-col overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-2xs">
                    <!-- Head -->
                    <div class="flex flex-shrink-0 items-center justify-between border-b border-slate-100 bg-slate-50/40 px-3.5 py-3">
                        <div class="flex items-center gap-2">
                            <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-[#003628]/10 text-[#003628]">
                                <BarChart3 class="size-3.5" />
                            </div>
                            <h2 class="text-xs font-bold text-slate-800">Kategori Masalah</h2>
                        </div>
                        <span class="rounded-md border border-slate-200/80 bg-white px-1.5 py-0.5 text-[10px] font-bold text-slate-600 shadow-2xs">
                            {{ analytics.topCategories.length }} Kategori
                        </span>
                    </div>

                    <!-- List (Scrollable) -->
                    <div class="min-h-0 flex-1 overflow-y-auto p-3 space-y-2.5 custom-scroll">
                        <div
                            v-for="(cat, i) in analytics.topCategories"
                            :key="cat.name"
                            class="rounded-lg border border-slate-100 bg-slate-50/50 p-2.5 transition-colors hover:bg-slate-50"
                        >
                            <div class="flex items-center justify-between text-[11px]">
                                <div class="flex items-center gap-1.5 truncate">
                                    <span class="flex h-4 w-4 items-center justify-center rounded-full bg-slate-200 text-[9px] font-black text-slate-700 shrink-0">
                                        {{ i + 1 }}
                                    </span>
                                    <span class="truncate font-semibold text-slate-800" :title="cat.name">{{ cat.name }}</span>
                                </div>
                                <span class="ml-1.5 flex-shrink-0 rounded bg-white px-1.5 py-0.5 text-[10px] font-bold text-slate-700 shadow-2xs border border-slate-100">
                                    {{ cat.count }} tiket
                                </span>
                            </div>
                            <div class="mt-2 flex items-center gap-2">
                                <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-200/70">
                                    <div
                                        class="h-full rounded-full transition-all duration-500 bg-[#003628]"
                                        :style="{ width: `${cat.percentage}%` }"
                                    />
                                </div>
                                <span class="text-[9px] font-medium text-slate-400 w-9 text-right">{{ cat.percentage }}%</span>
                            </div>
                        </div>

                        <div
                            v-if="analytics.topCategories.length === 0"
                            class="py-12 text-center text-[11px] text-slate-400 font-medium"
                        >
                            Belum ada data kategori
                        </div>
                    </div>

                    <!-- Foot -->
                    <div class="flex flex-shrink-0 items-center justify-between border-t border-slate-100 bg-slate-50/30 px-3.5 py-2 text-[10px] font-medium text-slate-500">
                        <span>Terpopuler</span>
                        <span class="font-bold text-slate-700 truncate max-w-[130px]">{{ analytics.topCategories[0]?.name || '—' }}</span>
                    </div>
                </div>

                <!-- ── Card 3: Teknisi IT ── -->
                <div class="flex min-h-0 flex-col overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-2xs">
                    <!-- Head -->
                    <div class="flex flex-shrink-0 items-center justify-between border-b border-slate-100 bg-slate-50/40 px-3.5 py-3">
                        <div class="flex items-center gap-2">
                            <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-[#003628]/10 text-[#003628]">
                                <Award class="size-3.5" />
                            </div>
                            <h2 class="text-xs font-bold text-slate-800">Teknisi IT</h2>
                        </div>
                        <span class="rounded-md border border-slate-200/80 bg-white px-1.5 py-0.5 text-[10px] font-bold text-slate-600 shadow-2xs">
                            {{ analytics.technicianStats.length }} Personil
                        </span>
                    </div>

                    <!-- List (Scrollable) -->
                    <div class="min-h-0 flex-1 overflow-y-auto p-3 space-y-2.5 custom-scroll">
                        <div
                            v-for="(tech, i) in analytics.technicianStats"
                            :key="tech.name"
                            class="flex items-center gap-2.5 rounded-lg border border-slate-100 bg-slate-50/50 p-2.5 transition-colors hover:bg-slate-50"
                        >
                            <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-[#003628] text-white text-[10px] font-black shadow-xs">
                                {{ initials(tech.name) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-[11px] font-bold text-slate-800 truncate">{{ tech.name }}</p>
                                <p class="text-[10px] text-slate-400 font-medium">{{ tech.count }} tiket selesai</p>
                            </div>
                            <span class="flex-shrink-0 rounded-md bg-white border border-slate-200/80 px-1.5 py-0.5 text-[9px] font-black text-slate-600 shadow-2xs">
                                #{{ i + 1 }}
                            </span>
                        </div>

                        <div
                            v-if="analytics.technicianStats.length === 0"
                            class="py-12 text-center text-[11px] text-slate-400 font-medium"
                        >
                            Belum ada statistik teknisi
                        </div>
                    </div>

                    <!-- Foot -->
                    <div class="flex flex-shrink-0 items-center justify-between border-t border-slate-100 bg-slate-50/30 px-3.5 py-2 text-[10px] font-medium text-slate-500">
                        <span>Top Performer</span>
                        <span class="font-bold text-slate-700 truncate max-w-[130px]">{{ analytics.technicianStats[0]?.name || '—' }}</span>
                    </div>
                </div>

            </div>

            <!-- SISI KANAN (1 Card Vertikal: Pelapor Teraktif) -->
            <div class="flex min-h-0 flex-col overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-2xs xl:col-span-4 2xl:col-span-3">
                <!-- Head -->
                <div class="flex flex-shrink-0 items-center justify-between border-b border-slate-100 bg-slate-50/40 px-3.5 py-3">
                    <div class="flex items-center gap-2">
                        <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-[#003628]/10 text-[#003628]">
                            <Users class="size-3.5" />
                        </div>
                        <h2 class="text-xs font-bold text-slate-800">Pelapor Teraktif</h2>
                    </div>
                    <span class="rounded-md border border-slate-200/80 bg-white px-1.5 py-0.5 text-[10px] font-bold text-slate-600 shadow-2xs">
                        {{ analytics.topRequesters.length }} User
                    </span>
                </div>

                <!-- List (Scrollable) -->
                <div class="min-h-0 flex-1 overflow-y-auto p-3 space-y-2 custom-scroll">
                    <div
                        v-for="user in analytics.topRequesters"
                        :key="user.name"
                        class="flex items-center gap-2.5 rounded-lg border border-slate-100 bg-slate-50/40 p-2.5 transition-colors hover:bg-slate-50"
                    >
                        <div
                            class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg text-[10px] font-black border border-slate-200/60 shadow-2xs"
                            :class="avatarCls(user.name)"
                        >
                            {{ initials(user.name) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-[11px] font-bold text-slate-800 truncate">{{ user.name }}</p>
                            <p class="text-[10px] text-slate-400 font-medium truncate">{{ user.department || user.location || 'Staff' }}</p>
                        </div>
                        <div class="flex flex-col items-end flex-shrink-0">
                            <span class="text-[11px] font-black text-slate-900">{{ user.count }}</span>
                            <span class="text-[8px] font-bold text-slate-400 uppercase">tiket</span>
                        </div>
                    </div>

                    <div
                        v-if="analytics.topRequesters.length === 0"
                        class="py-16 text-center text-[11px] text-slate-400 font-medium"
                    >
                        Belum ada data pelapor
                    </div>
                </div>

                <!-- Foot -->
                <div class="flex flex-shrink-0 items-center justify-between border-t border-slate-100 bg-slate-50/30 px-3.5 py-2 text-[10px] font-medium text-slate-500">
                    <span>Pembaruan Data</span>
                    <div class="flex items-center gap-1 font-bold text-emerald-700">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse" />
                        <span>Real-time</span>
                    </div>
                </div>
            </div>

        </div>

    </div>
</template>

<style scoped>
.custom-scroll {
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 transparent;
}
.custom-scroll::-webkit-scrollbar {
    width: 4px;
}
.custom-scroll::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scroll::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 9999px;
}
.custom-scroll::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}
</style>
