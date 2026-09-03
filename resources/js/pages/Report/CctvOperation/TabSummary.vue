<script setup lang="ts">
import { ref, onMounted, watch, computed } from 'vue';
import { Camera, Fingerprint, Server, AlertCircle, MapPin, Activity } from 'lucide-vue-next';

const props = defineProps<{
    filterFrom: string;
    filterTo: string;
    applyTrigger: number;
}>();

const loading     = ref(true);
const data        = ref<any>(null);

async function loadAll() {
    loading.value = true;
    try {
        const res = await fetch(`/cctv-operation/summary?from=${props.filterFrom}&to=${props.filterTo}`);
        if (res.ok) data.value = await res.json();
    } finally { loading.value = false; }
}

onMounted(() => loadAll());
watch(() => props.applyTrigger, () => loadAll());

const cctv        = computed(() => data.value?.cctv   ?? null);
const finger      = computed(() => data.value?.finger  ?? null);
const nvr         = computed(() => data.value?.nvr     ?? null);
const openTickets = computed(() => data.value?.open_tickets ?? []);
const hasData     = computed(() => cctv.value?.total_devices || finger.value?.total_devices || nvr.value?.total_devices);

// Uptime status helpers — only used for the number color & bar, not icons
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
function pctLabel(p: number | null) {
    if (p === null) return 'No Data';
    if (p >= 99.5)  return 'Good';
    if (p >= 95)    return 'Fair';
    return 'Low';
}
function pctLabelCls(p: number | null) {
    if (p === null) return 'text-slate-400';
    if (p >= 99.5)  return 'text-emerald-600';
    if (p >= 95)    return 'text-amber-500';
    return 'text-rose-500';
}

const TYPE_LABELS: Record<string, string> = { cctv: 'CCTV', finger: 'Fingerprint', nvr: 'NVR' };

// All icons use the same neutral style — no per-type color
const typeConfig = [
    { key: 'nvr',    label: 'NVR',         icon: Server      },
    { key: 'cctv',   label: 'CCTV',        icon: Camera      },
    { key: 'finger', label: 'Fingerprint', icon: Fingerprint },
];
</script>

<template>
<div class="space-y-4">

    <!-- SKELETON -->
    <template v-if="loading">
        <div class="grid grid-cols-4 gap-3">
            <div v-for="n in 4" :key="n" class="h-20 bg-slate-50 rounded-2xl border border-slate-100 animate-pulse"/>
        </div>
        <div class="h-48 bg-slate-50 rounded-2xl border border-slate-100 animate-pulse"/>
    </template>

    <!-- EMPTY -->
    <template v-else-if="!hasData">
        <div class="rounded-2xl border-2 border-dashed border-slate-100 py-20 text-center">
            <div class="h-12 w-12 rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-center mx-auto mb-3">
                <Camera class="size-6 text-slate-300"/>
            </div>
            <p class="text-[11px] font-black uppercase tracking-widest text-slate-300">Belum ada data</p>
            <p class="text-[10px] text-slate-300 mt-1">Gunakan tombol Fetch Data untuk mengambil data</p>
        </div>
    </template>

    <template v-else>

        <!-- ── ROW 1: KPI CARDS (4 col) ─────────────────────────────── -->
        <div class="grid grid-cols-4 gap-3">

            <!-- Uptime cards — neutral icon, colored number only -->
            <div v-for="cfg in typeConfig" :key="cfg.key"
                class="rounded-2xl border border-slate-100 bg-white p-4 flex items-center gap-3.5 hover:border-slate-200 hover:shadow-sm transition-all">
                <!-- Icon: always slate -->
                <div class="h-10 w-10 rounded-xl bg-slate-50 border border-slate-100 flex items-center justify-center shrink-0">
                    <component :is="cfg.icon" class="size-4.5 text-slate-500"/>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">{{ cfg.label }}</p>
                    <p class="text-[18px] font-black tabular-nums leading-tight mt-0.5"
                        :class="pctColor((data as any)?.[cfg.key]?.overall_avg)">
                        {{ (data as any)?.[cfg.key]?.overall_avg != null ? (data as any)[cfg.key].overall_avg + '%' : '—' }}
                    </p>
                    <p class="text-[9px] text-slate-400 mt-0.5">
                        {{ (data as any)?.[cfg.key]?.total_devices ?? 0 }} device
                        <span class="ml-1 font-black" :class="pctLabelCls((data as any)?.[cfg.key]?.overall_avg)">
                            · {{ pctLabel((data as any)?.[cfg.key]?.overall_avg) }}
                        </span>
                    </p>
                </div>
            </div>

            <!-- Open Tickets card -->
            <div class="rounded-2xl border border-slate-100 bg-white p-4 flex items-center gap-3.5 hover:border-slate-200 hover:shadow-sm transition-all">
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
                    <div class="flex items-center gap-2 mt-0.5 flex-wrap">
                        <template v-for="cfg in typeConfig" :key="cfg.key">
                            <span v-if="openTickets.filter((t:any) => t.device_type === cfg.key).length > 0"
                                class="text-[9px] text-slate-400">
                                {{ cfg.label }}
                                <span class="font-black text-rose-500">{{ openTickets.filter((t:any) => t.device_type === cfg.key).length }}</span>
                            </span>
                        </template>
                        <span v-if="openTickets.length === 0" class="text-[9px] text-slate-300">Tidak ada</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── ROW 2: UPTIME PER LOKASI ──────────────────────────────── -->
        <div class="rounded-2xl border border-slate-100 bg-white overflow-hidden">

            <div class="flex items-center gap-2.5 px-5 py-3.5 border-b border-slate-100">
                <Activity class="size-4 text-slate-400"/>
                <p class="text-[11px] font-black text-slate-700">Uptime per Lokasi</p>
            </div>

            <div class="divide-y divide-slate-50">
                <div v-for="cfg in typeConfig" :key="'loc-'+cfg.key">

                    <!-- Type sub-header -->
                    <div class="flex items-center gap-2 px-5 py-2 bg-slate-50/50">
                        <component :is="cfg.icon" class="size-3.5 text-slate-400 shrink-0"/>
                        <p class="text-[9px] font-black uppercase tracking-widest text-slate-500">{{ cfg.label }}</p>
                        <span class="ml-auto text-[9px] text-slate-400">
                            {{ (data as any)?.[cfg.key]?.loc_summary?.length ?? 0 }} lokasi
                        </span>
                    </div>

                    <!-- Location cards -->
                    <div v-if="(data as any)?.[cfg.key]?.loc_summary?.length" class="px-5 py-3">
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-2">
                            <div v-for="ls in (data as any)[cfg.key].loc_summary" :key="ls.location"
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
                                    <div class="h-full rounded-full transition-all duration-700"
                                        :class="pctBar(ls.avg_uptime)"
                                        :style="{ width: Math.min(ls.avg_uptime ?? 0, 100) + '%' }"/>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="px-5 py-4">
                        <p class="text-[9px] text-slate-300 font-bold">Belum ada data</p>
                    </div>

                </div>
            </div>
        </div>

    </template>
</div>
</template>
