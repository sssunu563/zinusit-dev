<script setup lang="ts">
import { ref, onMounted, watch, computed } from 'vue';
import { Activity, Shield, Wifi, AlertCircle, CheckCircle2, XCircle, Server, MapPin, Database } from 'lucide-vue-next';

const props = defineProps<{
    locations: string[];
    filterFrom: string;
    filterTo: string;
    applyTrigger: number;
}>();

const loading    = ref(true);
const bwData     = ref<any>(null);
const uptimeData = ref<any>(null);
const ispData    = ref<any>(null);
const maintData  = ref<any[]>([]);
const backupData = ref<any>(null);

async function loadAll() {
    loading.value = true;
    try {
        const [bwRes, upRes, ispRes, maintRes, bkpRes] = await Promise.all([
            fetch(`/bandwidth/summary?from=${props.filterFrom}&to=${props.filterTo}`),
            fetch(`/uptime/data?from=${props.filterFrom}&to=${props.filterTo}`),
            fetch(`/isp-sla/data?from=${props.filterFrom}&to=${props.filterTo}`),
            fetch(`/uptime/maintenance-logs?from=${props.filterFrom}&to=${props.filterTo}&status=open`),
            fetch(`/uptime/backup-summary?from=${props.filterFrom}&to=${props.filterTo}`),
        ]);
        if (bwRes.ok)    bwData.value     = await bwRes.json();
        if (upRes.ok)    uptimeData.value  = await upRes.json();
        if (ispRes.ok)   ispData.value    = await ispRes.json();
        if (maintRes.ok) maintData.value  = await maintRes.json();
        if (bkpRes.ok)   backupData.value = await bkpRes.json();
    } finally { loading.value = false; }
}

onMounted(() => loadAll());
watch(() => props.applyTrigger, () => loadAll());

const uptimeSummary  = computed(() => uptimeData.value?.loc_summary ?? []);
const totalDevices   = computed(() => uptimeData.value?.devices?.length ?? 0);
const avgUptime      = computed(() => {
    const locs = uptimeSummary.value.filter((l: any) => l.avg_uptime !== null);
    if (!locs.length) return null;
    return parseFloat((locs.reduce((s: number, l: any) => s + l.avg_uptime, 0) / locs.length).toFixed(2));
});
const ispSummary     = computed(() => ispData.value?.location_summary ?? []);
const ispOnSla       = computed(() => ispSummary.value.filter((l: any) => l.on_sla === true).length);
const ispTotal       = computed(() => ispSummary.value.length);
const bwCards        = computed(() => bwData.value?.cards ?? []);
const openTickets    = computed(() => maintData.value.filter((m: any) => m.status === 'open'));
const backupLocSummary   = computed(() => backupData.value?.loc_summary ?? []);
const backupTotalDevices = computed(() => backupData.value?.total_devices ?? 0);
const backupOkDevices    = computed(() => backupData.value?.ok_devices ?? 0);
const backupOverallPct   = computed(() => backupData.value?.overall_pct ?? null);

// Uptime color helpers
function pctColor(p: number | null) {
    if (p === null) return 'text-slate-400';
    if (p >= 99.5)  return 'text-emerald-600';
    if (p >= 95)    return 'text-amber-500';
    return 'text-rose-500';
}
function pctBar(p: number | null) {
    if (p === null) return 'bg-slate-200';
    if (p >= 99.5)  return 'bg-emerald-400';
    if (p >= 95)    return 'bg-amber-400';
    return 'bg-rose-400';
}
function pctBorder(p: number | null) {
    if (p === null) return 'border-slate-100';
    if (p >= 99.5)  return 'border-emerald-200';
    if (p >= 95)    return 'border-amber-200';
    return 'border-rose-200';
}
function pctBadge(p: number | null) {
    if (p === null) return 'bg-slate-100 text-slate-400';
    if (p >= 99.5)  return 'bg-emerald-100 text-emerald-700';
    if (p >= 95)    return 'bg-amber-100 text-amber-700';
    return 'bg-rose-100 text-rose-600';
}
function pctLabel(p: number | null) {
    if (p === null) return 'No Data';
    if (p >= 99.5)  return 'Good';
    if (p >= 95)    return 'Fair';
    return 'Low';
}
// Backup color helpers
function bkpColor(p: number | null) {
    if (p === null) return 'text-slate-400';
    if (p >= 90) return 'text-emerald-600';
    if (p >= 70) return 'text-amber-500';
    return 'text-rose-500';
}
function bkpBar(p: number | null) {
    if (p === null) return 'bg-slate-200';
    if (p >= 90) return 'bg-emerald-400';
    if (p >= 70) return 'bg-amber-400';
    return 'bg-rose-400';
}
function bkpBorder(p: number | null) {
    if (p === null) return 'border-slate-100';
    if (p >= 90) return 'border-emerald-200';
    if (p >= 70) return 'border-amber-200';
    return 'border-rose-200';
}
function bkpBadge(p: number | null) {
    if (p === null) return 'bg-slate-100 text-slate-400';
    if (p >= 90) return 'bg-emerald-100 text-emerald-700';
    if (p >= 70) return 'bg-amber-100 text-amber-700';
    return 'bg-rose-100 text-rose-600';
}
</script>

<template>
<div class="space-y-4">

    <!-- SKELETON -->
    <template v-if="loading">
        <div class="grid grid-cols-5 gap-3">
            <div v-for="n in 5" :key="n" class="h-20 bg-slate-50 rounded-2xl border border-slate-100 animate-pulse"/>
        </div>
        <div class="h-48 bg-slate-50 rounded-2xl border border-slate-100 animate-pulse"/>
    </template>

    <template v-else>

        <!-- KPI CARDS (5 col) — same compact style as CCTV -->
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">

            <!-- Avg Uptime -->
            <div class="rounded-2xl border border-slate-100 bg-white p-4 flex items-center gap-3.5 hover:border-slate-200 hover:shadow-sm transition-all"
                :class="pctBorder(avgUptime)">
                <div class="h-10 w-10 rounded-xl bg-slate-50 border border-slate-100 flex items-center justify-center shrink-0">
                    <Activity class="size-4.5 text-slate-500"/>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Avg Uptime</p>
                    <p class="text-[18px] font-black tabular-nums leading-tight mt-0.5" :class="pctColor(avgUptime)">
                        {{ avgUptime != null ? avgUptime + '%' : '—' }}
                    </p>
                    <p class="text-[9px] text-slate-400 mt-0.5">
                        {{ totalDevices }} device
                        <span class="ml-1 font-black" :class="pctColor(avgUptime)">· {{ pctLabel(avgUptime) }}</span>
                    </p>
                </div>
            </div>

            <!-- ISP SLA -->
            <div class="rounded-2xl border border-slate-100 bg-white p-4 flex items-center gap-3.5 hover:border-slate-200 hover:shadow-sm transition-all"
                :class="ispTotal > 0 && ispOnSla === ispTotal ? 'border-emerald-200' : ispTotal > 0 ? 'border-amber-200' : 'border-slate-100'">
                <div class="h-10 w-10 rounded-xl bg-slate-50 border border-slate-100 flex items-center justify-center shrink-0">
                    <Shield class="size-4.5 text-slate-500"/>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">ISP SLA</p>
                    <p class="text-[18px] font-black tabular-nums leading-tight mt-0.5"
                        :class="ispTotal > 0 && ispOnSla === ispTotal ? 'text-emerald-600' : ispTotal > 0 ? 'text-amber-500' : 'text-slate-400'">
                        {{ ispTotal > 0 ? ispOnSla + '/' + ispTotal : '—' }}
                    </p>
                    <p class="text-[9px] text-slate-400 mt-0.5">
                        Lokasi on SLA
                        <span class="ml-1 font-black" :class="ispTotal > 0 && ispOnSla === ispTotal ? 'text-emerald-600' : 'text-amber-500'">
                            · {{ ispTotal > 0 && ispOnSla === ispTotal ? 'All OK' : 'Partial' }}
                        </span>
                    </p>
                </div>
            </div>

            <!-- Bandwidth -->
            <div class="rounded-2xl border border-slate-100 bg-white p-4 flex items-center gap-3.5 hover:border-slate-200 hover:shadow-sm transition-all">
                <div class="h-10 w-10 rounded-xl bg-slate-50 border border-slate-100 flex items-center justify-center shrink-0">
                    <Wifi class="size-4.5 text-slate-500"/>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Bandwidth</p>
                    <p class="text-[18px] font-black tabular-nums leading-tight mt-0.5 text-slate-700">
                        {{ bwCards.length }}
                    </p>
                    <p class="text-[9px] text-slate-400 mt-0.5">Lokasi dipantau</p>
                </div>
            </div>

            <!-- Backup -->
            <div class="rounded-2xl border border-slate-100 bg-white p-4 flex items-center gap-3.5 hover:border-slate-200 hover:shadow-sm transition-all"
                :class="bkpBorder(backupOverallPct)">
                <div class="h-10 w-10 rounded-xl bg-slate-50 border border-slate-100 flex items-center justify-center shrink-0">
                    <Database class="size-4.5 text-slate-500"/>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Backup</p>
                    <p class="text-[18px] font-black tabular-nums leading-tight mt-0.5" :class="bkpColor(backupOverallPct)">
                        {{ backupTotalDevices > 0 ? backupOkDevices + '/' + backupTotalDevices : '—' }}
                    </p>
                    <p class="text-[9px] text-slate-400 mt-0.5">
                        {{ backupOverallPct != null ? backupOverallPct + '%' : 'Belum ada data' }}
                        <span v-if="backupOverallPct != null" class="ml-1 font-black" :class="bkpColor(backupOverallPct)">
                            · {{ backupOverallPct >= 90 ? 'Good' : backupOverallPct >= 70 ? 'Fair' : 'Low' }}
                        </span>
                    </p>
                </div>
            </div>

            <!-- Open Tickets -->
            <div class="rounded-2xl border border-slate-100 bg-white p-4 flex items-center gap-3.5 hover:border-slate-200 hover:shadow-sm transition-all"
                :class="openTickets.length > 0 ? 'border-rose-200' : 'border-slate-100'">
                <div class="h-10 w-10 rounded-xl flex items-center justify-center shrink-0"
                    :class="openTickets.length > 0 ? 'bg-rose-50 border border-rose-100' : 'bg-slate-50 border border-slate-100'">
                    <AlertCircle class="size-4.5" :class="openTickets.length > 0 ? 'text-rose-500' : 'text-slate-400'"/>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Open Tickets</p>
                    <p class="text-[18px] font-black tabular-nums leading-tight mt-0.5"
                        :class="openTickets.length > 0 ? 'text-rose-500' : 'text-slate-400'">
                        {{ openTickets.length }}
                    </p>
                    <p class="text-[9px] text-slate-400 mt-0.5">
                        {{ openTickets.length === 0 ? 'Tidak ada' : 'Device bermasalah' }}
                    </p>
                </div>
            </div>
        </div>

        <!-- DETAIL SECTIONS — same card+section-header style as CCTV -->
        <div class="rounded-2xl border border-slate-100 bg-white overflow-hidden">

            <div class="flex items-center gap-2.5 px-5 py-3.5 border-b border-slate-100">
                <Activity class="size-4 text-slate-400"/>
                <p class="text-[11px] font-black text-slate-700">Detail per Kategori</p>
            </div>

            <div class="divide-y divide-slate-50">

                <!-- Uptime per lokasi -->
                <div v-if="uptimeSummary.length">
                    <div class="flex items-center gap-2 px-5 py-2 bg-slate-50/50">
                        <Activity class="size-3.5 text-slate-400 shrink-0"/>
                        <p class="text-[9px] font-black uppercase tracking-widest text-slate-500">Uptime per Lokasi</p>
                        <span class="ml-auto text-[9px] text-slate-400">{{ uptimeSummary.length }} lokasi</span>
                    </div>
                    <div class="px-5 py-3">
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-2">
                            <div v-for="ls in uptimeSummary" :key="ls.location"
                                class="rounded-xl border border-slate-100 bg-slate-50/60 p-3 hover:bg-white hover:border-slate-200 hover:shadow-sm transition-all">
                                <div class="flex items-center gap-1.5 mb-1.5">
                                    <MapPin class="size-3 text-slate-400 shrink-0"/>
                                    <p class="text-[9px] font-black uppercase tracking-widest text-slate-500 truncate">{{ ls.location }}</p>
                                </div>
                                <p class="text-[15px] font-black tabular-nums leading-none" :class="pctColor(ls.avg_uptime)">
                                    {{ ls.avg_uptime != null ? ls.avg_uptime.toFixed(2) + '%' : '—' }}
                                </p>
                                <p class="text-[9px] text-slate-400 mt-1">{{ ls.total }} device</p>
                                <div class="mt-2 h-1 bg-slate-200 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full transition-all duration-700" :class="pctBar(ls.avg_uptime)"
                                        :style="{ width: Math.min(ls.avg_uptime ?? 0, 100) + '%' }"/>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ISP SLA per lokasi -->
                <div v-if="ispSummary.length">
                    <div class="flex items-center gap-2 px-5 py-2 bg-slate-50/50">
                        <Shield class="size-3.5 text-slate-400 shrink-0"/>
                        <p class="text-[9px] font-black uppercase tracking-widest text-slate-500">ISP SLA per Lokasi</p>
                        <span class="ml-auto text-[9px] text-slate-400">{{ ispSummary.length }} lokasi</span>
                    </div>
                    <div class="px-5 py-3">
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-2">
                            <div v-for="loc in ispSummary" :key="loc.location"
                                class="rounded-xl border border-slate-100 bg-slate-50/60 p-3 hover:bg-white hover:border-slate-200 hover:shadow-sm transition-all"
                                :class="loc.on_sla===true ? 'border-emerald-200' : loc.on_sla===false ? 'border-rose-200' : 'border-slate-100'">
                                <div class="flex items-center gap-1.5 mb-1.5">
                                    <CheckCircle2 v-if="loc.on_sla===true" class="size-3 text-emerald-500 shrink-0"/>
                                    <XCircle v-else-if="loc.on_sla===false" class="size-3 text-rose-500 shrink-0"/>
                                    <Shield v-else class="size-3 text-slate-400 shrink-0"/>
                                    <p class="text-[9px] font-black uppercase tracking-widest text-slate-500 truncate">{{ loc.location }}</p>
                                </div>
                                <p class="text-[15px] font-black tabular-nums leading-none"
                                    :class="loc.on_sla===true ? 'text-emerald-600' : loc.on_sla===false ? 'text-rose-500' : 'text-slate-400'">
                                    {{ loc.avg_pct != null ? loc.avg_pct.toFixed(2) + '%' : '—' }}
                                </p>
                                <p class="text-[9px] mt-1 font-bold"
                                    :class="loc.on_sla===true ? 'text-emerald-500' : loc.on_sla===false ? 'text-rose-500' : 'text-slate-400'">
                                    {{ loc.on_sla===true ? 'On SLA' : loc.on_sla===false ? 'SLA Breach' : 'No Data' }}
                                </p>
                                <p class="text-[8px] text-slate-400">Target {{ loc.target }}%</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bandwidth per lokasi -->
                <div v-if="bwCards.length">
                    <div class="flex items-center gap-2 px-5 py-2 bg-slate-50/50">
                        <Wifi class="size-3.5 text-slate-400 shrink-0"/>
                        <p class="text-[9px] font-black uppercase tracking-widest text-slate-500">Bandwidth Usage</p>
                        <span class="ml-auto text-[9px] text-slate-400">{{ bwCards.length }} lokasi</span>
                    </div>
                    <div class="px-5 py-3">
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
                            <div v-for="card in bwCards" :key="card.location"
                                class="rounded-xl border border-slate-100 bg-slate-50/60 p-3 hover:bg-white hover:border-slate-200 hover:shadow-sm transition-all">
                                <div class="flex items-center gap-1.5 mb-2">
                                    <MapPin class="size-3 text-slate-400 shrink-0"/>
                                    <p class="text-[9px] font-black uppercase tracking-widest text-slate-500 truncate">{{ card.location }}</p>
                                </div>
                                <div class="space-y-1">
                                    <div v-for="s in card.series" :key="s.name" class="flex items-center justify-between gap-1">
                                        <p class="text-[9px] text-slate-500 truncate max-w-[80px]">{{ s.name }}</p>
                                        <p class="text-[9px] font-black text-slate-700 tabular-nums shrink-0">
                                            {{ s.avg_mbps != null ? s.avg_mbps + ' Mbps' : '—' }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Backup per lokasi -->
                <div v-if="backupLocSummary.length">
                    <div class="flex items-center gap-2 px-5 py-2 bg-slate-50/50">
                        <Database class="size-3.5 text-slate-400 shrink-0"/>
                        <p class="text-[9px] font-black uppercase tracking-widest text-slate-500">Backup Device per Lokasi</p>
                        <span class="ml-auto text-[9px] text-slate-400">{{ backupLocSummary.length }} lokasi</span>
                    </div>
                    <div class="px-5 py-3">
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-2">
                            <div v-for="ls in backupLocSummary" :key="ls.location"
                                class="rounded-xl border border-slate-100 bg-slate-50/60 p-3 hover:bg-white hover:border-slate-200 hover:shadow-sm transition-all"
                                :class="bkpBorder(ls.pct)">
                                <div class="flex items-center gap-1.5 mb-1.5">
                                    <MapPin class="size-3 text-slate-400 shrink-0"/>
                                    <p class="text-[9px] font-black uppercase tracking-widest text-slate-500 truncate">{{ ls.location }}</p>
                                </div>
                                <p class="text-[15px] font-black tabular-nums leading-none" :class="bkpColor(ls.pct)">
                                    {{ ls.pct != null ? ls.pct + '%' : '—' }}
                                </p>
                                <p class="text-[9px] text-slate-400 mt-1">{{ ls.ok }}/{{ ls.total }} device</p>
                                <div class="mt-2 h-1 bg-slate-200 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full transition-all duration-700" :class="bkpBar(ls.pct)"
                                        :style="{ width: Math.min(ls.pct ?? 0, 100) + '%' }"/>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Open Maintenance Tickets — hidden from web view, still exported to Excel -->
        <div v-if="false" class="rounded-2xl border border-rose-100 bg-white overflow-hidden">
            <div class="flex items-center gap-2.5 px-5 py-3.5 border-b border-rose-50">
                <div class="h-7 w-7 rounded-lg bg-rose-100 flex items-center justify-center shrink-0">
                    <AlertCircle class="size-3.5 text-rose-600"/>
                </div>
                <p class="text-[11px] font-black text-rose-700">Open Maintenance Tickets</p>
                <span class="ml-auto px-2 py-0.5 rounded-full bg-rose-100 text-[9px] font-black text-rose-600">{{ openTickets.length }}</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="border-b border-slate-50 bg-slate-50/50">
                            <th class="px-4 py-2.5 text-left text-[9px] font-black uppercase tracking-[0.12em] text-slate-400">Device</th>
                            <th class="px-3 py-2.5 text-left text-[9px] font-black uppercase tracking-[0.12em] text-slate-400">Lokasi</th>
                            <th class="px-3 py-2.5 text-left text-[9px] font-black uppercase tracking-[0.12em] text-slate-400">Tipe</th>
                            <th class="px-3 py-2.5 text-left text-[9px] font-black uppercase tracking-[0.12em] text-slate-400">Mulai</th>
                            <th class="px-3 py-2.5 text-left text-[9px] font-black uppercase tracking-[0.12em] text-slate-400">Durasi</th>
                            <th class="px-3 py-2.5 text-left text-[9px] font-black uppercase tracking-[0.12em] text-slate-400">Catatan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-for="t in openTickets" :key="t.id" class="hover:bg-rose-50/20 transition-colors">
                            <td class="px-4 py-2.5">
                                <p class="text-[11px] font-bold text-slate-800 truncate max-w-[160px]">{{ t.device_name }}</p>
                                <p class="text-[9px] font-mono text-slate-400">{{ t.ip_address }}</p>
                            </td>
                            <td class="px-3 py-2.5 text-[10px] font-bold text-slate-600">{{ t.location }}</td>
                            <td class="px-3 py-2.5">
                                <span class="px-2 py-0.5 rounded-full bg-rose-50 border border-rose-100 text-[8px] font-black uppercase text-rose-600">{{ t.event_type }}</span>
                            </td>
                            <td class="px-3 py-2.5 text-[10px] font-bold text-slate-600 tabular-nums">{{ t.started_at }}</td>
                            <td class="px-3 py-2.5 text-[10px] font-black text-rose-600">{{ t.duration }}</td>
                            <td class="px-3 py-2.5 text-[10px] text-slate-400 truncate max-w-[200px]">{{ t.notes ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Empty state -->
        <div v-if="!uptimeSummary.length && !ispSummary.length && !bwCards.length"
            class="rounded-2xl border-2 border-dashed border-slate-100 py-20 text-center">
            <div class="h-12 w-12 rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-center mx-auto mb-3">
                <Activity class="size-6 text-slate-300"/>
            </div>
            <p class="text-[11px] font-black uppercase tracking-widest text-slate-300">Belum ada data</p>
            <p class="text-[10px] text-slate-300 mt-1">Fetch data dari tab Uptime, ISP SLA, dan Bandwidth</p>
        </div>

    </template>
</div>
</template>
