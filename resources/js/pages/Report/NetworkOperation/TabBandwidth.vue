<script setup lang="ts">
import {
    Wifi, RefreshCw, Download, Upload,
    BarChart3, Table2, FileText, CheckCircle2,
    AlertTriangle, XCircle, Clock, Loader2, X,
    ChevronRight,
} from 'lucide-vue-next';
import { ref, computed, onMounted, watch } from 'vue';

const props = defineProps<{
    locations: string[];
    filterFrom: string;
    filterTo: string;
    applyTrigger: number;
}>();

const filterLocation = ref('');  // kept for buildParams compatibility

const loadingSummary = ref(false);
const loadingTable   = ref(false);
const loadingLog     = ref(false);

const summaryData = ref<any>(null);
const tableData   = ref<any[]>([]);
const logData     = ref<any[]>([]);

// Log detail modal
const showLogModal    = ref(false);
const selectedLog     = ref<any>(null);

const ID_MONTHS_BW = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];

function bwDateLabel(dateStr: string) {
    const d = new Date(dateStr + 'T00:00:00');
    return d.getDate() + ' ' + ID_MONTHS_BW[d.getMonth()];
}

function buildParams() {
    const p = new URLSearchParams({ from: props.filterFrom, to: props.filterTo });
    if (filterLocation.value) p.set('location', filterLocation.value);
    return p.toString();
}

function logStatusConf(status: string) {
    if (status === 'success') return { icon: CheckCircle2, color: 'text-emerald-600', bg: 'bg-emerald-50 border-emerald-100' };
    if (status === 'partial') return { icon: AlertTriangle, color: 'text-amber-600',  bg: 'bg-amber-50 border-amber-100' };
    return                           { icon: XCircle,       color: 'text-rose-600',   bg: 'bg-rose-50 border-rose-100' };
}

function dlColor(_v: number|null) { return 'text-emerald-600'; }

const SERIES_COLORS = [
    '#003628','#10b981','#3b82f6','#f59e0b','#8b5cf6',
    '#ef4444','#06b6d4','#84cc16','#f97316','#ec4899',
];
function seriesColor(idx: number) { return SERIES_COLORS[idx % SERIES_COLORS.length]; }

// Map series name to chart color
const seriesColorMap = computed(() => {
    const map: Record<string, string> = {};
    if (!summaryData.value?.series) return map;
    summaryData.value.series.forEach((s: any, i: number) => { map[s.name] = SERIES_COLORS[i % SERIES_COLORS.length]; });
    return map;
});
function cardSeriesColor(location: string, seriesName: string): string {
    const fullKey = location + ' ' + seriesName;
    if (seriesColorMap.value[fullKey]) return seriesColorMap.value[fullKey];
    const entry = Object.entries(seriesColorMap.value).find(([k]) => k.includes(seriesName));
    return entry ? entry[1] : '#64748b';
}
function pivotDlColor(location: string, provider: string): string {
    const key = location + ' ' + provider + ' Download (Mbps)';
    if (seriesColorMap.value[key]) return seriesColorMap.value[key];
    const entry = Object.entries(seriesColorMap.value).find(([k]) => k.startsWith(location + ' ' + provider));
    return entry ? entry[1] : '#64748b';
}
function pivotUlColor(location: string, provider: string): string {
    const key = location + ' ' + provider + ' Upload (Mbps)';
    if (seriesColorMap.value[key]) return seriesColorMap.value[key];
    const entries = Object.entries(seriesColorMap.value).filter(([k]) => k.startsWith(location + ' ' + provider));
    return entries.length > 1 ? entries[1][1] : (entries[0]?.[1] ?? '#94a3b8');
}

const chartSvg = computed(() => {
    if (!summaryData.value?.dates?.length || !summaryData.value?.series?.length) return '';
    const dates  = summaryData.value.dates as string[];
    const series = summaryData.value.series as any[];
    const W = 700, H = 180;
    const padL = 44, padR = 16, padT = 12, padB = 32;
    const chartW = W - padL - padR;
    const chartH = H - padT - padB;
    let maxVal = 0;
    for (const s of series) for (const v of s.data) if (v !== null && v > maxVal) maxVal = v;
    if (maxVal === 0) maxVal = 1;
    const yMax = Math.ceil(maxVal * 1.15);
    const xStep = dates.length > 1 ? chartW / (dates.length - 1) : chartW;
    const xPos = (i: number) => padL + (dates.length > 1 ? i * xStep : chartW / 2);
    const yPos = (v: number) => padT + chartH - (v / yMax) * chartH;
    const yTicks = 4;
    let gridLines = '', yLabels = '';
    for (let i = 0; i <= yTicks; i++) {
        const val = (yMax / yTicks) * i;
        const y   = yPos(val);
        gridLines += `<line x1="${padL}" y1="${y}" x2="${W - padR}" y2="${y}" stroke="#f1f5f9" stroke-width="1"/>`;
        yLabels   += `<text x="${padL - 4}" y="${y + 3.5}" text-anchor="end" font-size="8" fill="#94a3b8" font-family="sans-serif">${val >= 1000 ? (val/1000).toFixed(1)+'G' : val.toFixed(0)}</text>`;
    }
    const step = Math.ceil(dates.length / 12);
    let xLabels = '';
    for (let i = 0; i < dates.length; i += step) {
        xLabels += `<text x="${xPos(i)}" y="${H - padB + 14}" text-anchor="middle" font-size="8" fill="#94a3b8" font-family="sans-serif">${bwDateLabel(dates[i])}</text>`;
    }
    let paths = '';
    for (let si = 0; si < series.length; si++) {
        const s = series[si]; const color = seriesColor(si);
        let d = ''; let first = true;
        for (let i = 0; i < s.data.length; i++) {
            if (s.data[i] === null) { first = true; continue; }
            const x = xPos(i), y = yPos(s.data[i]);
            d += first ? `M${x},${y}` : `L${x},${y}`; first = false;
        }
        if (d) paths += `<path d="${d}" fill="none" stroke="${color}" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round"/>`;
    }
    return `<svg viewBox="0 0 ${W} ${H}" xmlns="http://www.w3.org/2000/svg" class="w-full h-auto">${gridLines}${yLabels}${xLabels}${paths}</svg>`;
});

// Pivot: generate ALL dates in range, not just dates with data
const allDatesInRange = computed(() => {
    const dates: string[] = [];
    const from = new Date(props.filterFrom + 'T00:00:00');
    const to   = new Date(props.filterTo   + 'T00:00:00');
    const cur  = new Date(from);
    while (cur <= to) {
        dates.push(cur.toISOString().slice(0, 10));
        cur.setDate(cur.getDate() + 1);
    }
    return dates;
});

const pivotData = computed(() => {
    const dates = allDatesInRange.value;
    const rowMap: Record<string, any> = {};
    for (const r of tableData.value) {
        const key = r.location + '||' + r.provider;
        if (!rowMap[key]) rowMap[key] = { location: r.location, provider: r.provider, cells: {} };
        rowMap[key].cells[r.date] = { download: r.download, upload: r.upload };
    }
    // Calculate AVG DL and AVG UL per row
    const rows = Object.values(rowMap).map((row: any) => {
        const dlVals = Object.values(row.cells).map((c: any) => c.download).filter((v: any) => v !== null) as number[];
        const ulVals = Object.values(row.cells).map((c: any) => c.upload).filter((v: any) => v !== null) as number[];
        row.avg_dl = dlVals.length > 0 ? Math.round(dlVals.reduce((a, b) => a + b, 0) / dlVals.length * 100) / 100 : null;
        row.avg_ul = ulVals.length > 0 ? Math.round(ulVals.reduce((a, b) => a + b, 0) / ulVals.length * 100) / 100 : null;
        return row;
    }).sort((a: any, b: any) => {
        const lc = a.location.localeCompare(b.location);
        return lc !== 0 ? lc : a.provider.localeCompare(b.provider);
    });
    return { dates, rows };
});

const bwColStyle = computed(() => ({ minWidth: '100px', width: '100px' }));

async function loadSummary() {
    loadingSummary.value = true;
    try {
        const res = await fetch('/bandwidth/summary?' + buildParams());
        if (res.ok) summaryData.value = await res.json();
    } finally { loadingSummary.value = false; }
}
async function loadTable() {
    loadingTable.value = true;
    try {
        const res = await fetch('/bandwidth/data?' + buildParams());
        if (res.ok) tableData.value = await res.json();
    } finally { loadingTable.value = false; }
}
async function loadLog() {
    loadingLog.value = true;
    try {
        const res = await fetch('/network-operation/bandwidth/logs');
        if (res.ok) logData.value = await res.json();
    } finally { loadingLog.value = false; }
}
async function loadAll() { await Promise.all([loadSummary(), loadTable(), loadLog()]); }

onMounted(() => loadAll());
watch(() => props.applyTrigger, () => { loadAll(); loadLog(); });

function openLogDetail(log: any) { selectedLog.value = log; showLogModal.value = true; }
</script>

<template>
<div class="space-y-5 w-full min-w-0">

    <!-- SUMMARY CARDS -->
    <div v-if="loadingSummary" class="grid grid-cols-3 gap-4">
        <div v-for="n in 3" :key="n" class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5 space-y-3 animate-pulse">
            <div class="h-5 bg-slate-100 rounded-full w-2/3"></div>
            <div class="h-px bg-slate-100"></div>
            <div class="h-4 bg-slate-100 rounded-full w-full"></div>
            <div class="h-4 bg-slate-100 rounded-full w-5/6"></div>
        </div>
    </div>
    <div v-else-if="!summaryData?.cards?.length" class="grid grid-cols-3 gap-4">
        <div class="col-span-3 py-12 text-center bg-white rounded-2xl border border-slate-100 shadow-sm">
            <BarChart3 class="size-8 text-slate-200 mx-auto mb-2"/>
            <p class="text-[10px] font-black uppercase tracking-widest text-slate-300">Belum ada data lokasi</p>
        </div>
    </div>
    <div v-else class="grid grid-cols-3 gap-4">
        <div v-for="card in summaryData.cards" :key="card.location" class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="flex items-center justify-between px-5 pt-4 pb-3">
                <span class="text-base font-black text-slate-900 tracking-tight">{{ card.location }}</span>
                <Wifi class="size-4 text-slate-300 shrink-0"/>
            </div>
            <div class="h-px bg-slate-100 mx-5"></div>
            <div class="px-5 py-3">
                <table class="w-full">
                    <thead>
                        <tr class="bg-slate-50">
                            <th class="py-2 pr-3 text-left text-[9px] font-black uppercase tracking-widest text-slate-400 rounded-l-lg">Provider</th>
                            <th class="py-2 px-2 text-right text-[9px] font-black uppercase tracking-widest text-slate-400">AVG</th>
                            <th class="py-2 pl-2 text-right text-[9px] font-black uppercase tracking-widest text-slate-400 rounded-r-lg">MAX</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-for="s in card.series" :key="s.name" class="hover:bg-slate-50/50 transition-colors">
                            <td class="py-2 pr-3 text-[10px] font-bold text-slate-600 whitespace-nowrap">
                                <span class="flex items-center gap-1.5">
                                    <span class="w-3 h-1.5 rounded-full shrink-0 inline-block" :style="{ backgroundColor: cardSeriesColor(card.location, s.name) }"></span>
                                    {{ s.name }}
                                </span>
                            </td>
                            <td class="py-2 px-2 text-right text-[11px] font-black tabular-nums" :style="{ color: cardSeriesColor(card.location, s.name) }">{{ s.avg_mbps != null ? s.avg_mbps + ' Mbps' : '-' }}</td>
                            <td class="py-2 pl-2 text-right text-[11px] font-black tabular-nums" :style="{ color: cardSeriesColor(card.location, s.name) }">
                                {{ s.max_mbps != null ? s.max_mbps + ' Mbps' : '-' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- CHART + LOG -->
    <div class="grid grid-cols-3 gap-4">
        <div class="col-span-2 bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
                <div class="flex items-center gap-2">
                    <BarChart3 class="size-4 text-[#003628]"/>
                    <p class="text-sm font-black text-slate-800">Bandwidth Usage (Mbps)</p>
                </div>
                <span class="text-[10px] font-bold text-slate-400 tabular-nums">{{ props.filterFrom }} &ndash; {{ props.filterTo }}</span>
            </div>
            <div v-if="loadingSummary" class="p-5"><div class="h-56 bg-slate-100 rounded-xl animate-pulse"></div></div>
            <div v-else-if="!summaryData?.dates?.length" class="py-20 text-center">
                <BarChart3 class="size-10 text-slate-200 mx-auto mb-3"/>
                <p class="text-[11px] font-black uppercase tracking-widest text-slate-300">Belum ada data</p>
            </div>
            <div v-else class="p-5">
                <div v-html="chartSvg" class="w-full"></div>
            </div>
            <div v-if="summaryData?.series?.length" class="px-5 pb-4 pt-2 border-t border-slate-50 flex flex-wrap gap-x-4 gap-y-2">
                <div v-for="(s, si) in summaryData.series" :key="s.name" class="flex items-center gap-1.5">
                    <div class="w-4 h-1 rounded-full shrink-0" :style="{ backgroundColor: seriesColor(si) }"></div>
                    <span class="text-[9px] font-bold text-slate-500">{{ s.name }}</span>
                </div>
            </div>
        </div>

        <!-- Fetch Log -->
        <div class="col-span-1 bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden flex flex-col">
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100 shrink-0">
                <div class="flex items-center gap-2">
                    <FileText class="size-4 text-[#003628]"/>
                    <p class="text-sm font-black text-slate-800">Fetch Log</p>
                    <span v-if="logData.length" class="px-2 py-0.5 rounded-full bg-slate-100 text-[9px] font-black text-slate-500">{{ logData.length }}</span>
                </div>
                <div class="flex items-center gap-1">
                    <button type="button" class="h-7 px-2 rounded-lg flex items-center gap-1 hover:bg-slate-100 transition-colors text-slate-400 text-[9px] font-black uppercase tracking-widest" @click="selectedLog=null; showLogModal=true">
                        <FileText class="size-3"/> More
                    </button>
                    <button type="button" class="h-7 w-7 rounded-lg flex items-center justify-center hover:bg-slate-100 transition-colors text-slate-400" @click="loadLog">
                        <RefreshCw class="size-3.5" :class="{ 'animate-spin': loadingLog }"/>
                    </button>
                </div>
            </div>
            <div v-if="loadingLog" class="p-4 space-y-2">
                <div v-for="n in 4" :key="n" class="h-12 bg-slate-100 rounded-xl animate-pulse"></div>
            </div>
            <div v-else-if="!logData.length" class="flex-1 flex flex-col items-center justify-center py-10 text-center">
                <Clock class="size-7 text-slate-200 mx-auto mb-2"/>
                <p class="text-[10px] font-black uppercase tracking-widest text-slate-300">Belum ada log</p>
            </div>
            <div v-else class="overflow-y-auto max-h-[380px] divide-y divide-slate-50">
                <button v-for="log in logData.slice(0, 10)" :key="log.id" type="button"
                    class="w-full px-4 py-3 hover:bg-slate-50/80 transition-colors text-left group"
                    @click="openLogDetail(log)">
                    <div class="flex items-center gap-2 flex-wrap">
                        <div class="shrink-0 px-2 py-0.5 rounded-lg border text-[8px] font-black uppercase tracking-widest flex items-center gap-1" :class="logStatusConf(log.status).bg">
                            <component :is="logStatusConf(log.status).icon" class="size-3" :class="logStatusConf(log.status).color"/>
                            <span :class="logStatusConf(log.status).color">{{ log.status }}</span>
                        </div>
                        <span class="text-[11px] font-black text-slate-800 tabular-nums">{{ log.fetch_date }}</span>
                        <span class="text-[10px] font-bold text-slate-400">{{ log.sensors_ok }} OK &middot; {{ log.sensors_fail }} gagal</span>
                        <span v-if="log.is_manual" class="px-2 py-0.5 rounded-full bg-violet-50 border border-violet-100 text-[8px] font-black uppercase tracking-widest text-violet-600">Manual</span>
                        <ChevronRight class="size-3 text-slate-300 ml-auto group-hover:text-slate-500 transition-colors shrink-0"/>
                    </div>
                    <div class="flex items-center justify-between mt-1">
                        <span class="text-[9px] font-bold text-slate-500">{{ log.triggered_by }}</span>
                        <span class="text-[9px] text-slate-300 tabular-nums">{{ log.created_at }}</span>
                    </div>
                </button>
            </div>
        </div>
    </div>

    <!-- PIVOT TABLE -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
            <div class="flex items-center gap-2">
                <Table2 class="size-4 text-[#003628]"/>
                <p class="text-sm font-black text-slate-800">Detail Bandwidth</p>
                <span class="text-[9px] font-bold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full">{{ props.filterFrom }} &ndash; {{ props.filterTo }}</span>
                
            </div>
            <!-- Legend: DL emerald, UL sky -->
            <div class="flex items-center gap-3 text-[9px] font-bold text-slate-400">
                <span class="flex items-center gap-1"><Download class="size-3 text-emerald-500"/> DL</span>
                <span class="flex items-center gap-1"><Upload class="size-3 text-sky-500"/> UL</span>
            </div>
        </div>
        <div v-if="loadingTable" class="p-4 space-y-2">
            <div v-for="n in 5" :key="n" class="h-10 bg-slate-100 rounded-xl animate-pulse"></div>
        </div>
        <div v-else-if="!pivotData.rows.length" class="py-16 text-center">
            <Table2 class="size-8 text-slate-200 mx-auto mb-2"/>
            <p class="text-[10px] font-black uppercase tracking-widest text-slate-300">Tidak ada data</p>
        </div>
        <div v-else style="overflow-x:auto">
            <table class="border-collapse text-left" style="width:max-content;min-width:100%">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100">
                        <th style="width:120px;min-width:120px" class="px-3 py-3 text-[9px] font-black uppercase tracking-widest text-slate-400 sticky left-0 bg-slate-50 z-20">Lokasi</th>
                        <th style="width:140px;min-width:140px" class="px-3 py-3 text-[9px] font-black uppercase tracking-widest text-slate-400 sticky left-[120px] bg-slate-50 z-20">Provider</th>
                        <th style="width:110px;min-width:110px" class="px-2 py-3 text-center text-[9px] font-black uppercase tracking-widest text-slate-400 sticky left-[260px] bg-slate-50 z-20 border-r border-slate-200">
                            <div class="flex flex-col items-center gap-0.5">
                                <span>Avg</span>
                                <div class="flex items-center gap-1 text-[8px] font-bold normal-case tracking-normal">
                                    <Download class="size-2.5 text-slate-300"/><Upload class="size-2.5 text-slate-300"/>
                                </div>
                            </div>
                        </th>
                        <th v-for="date in pivotData.dates" :key="date" :style="bwColStyle" class="px-1 py-3 text-center text-[9px] font-black uppercase tracking-widest text-slate-400">
                            {{ bwDateLabel(date) }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr v-for="row in pivotData.rows" :key="row.location+'||'+row.provider" class="hover:bg-slate-50/40 transition-colors group">
                        <td style="width:120px;min-width:120px" class="px-3 py-2 text-[10px] font-bold text-slate-700 sticky left-0 bg-white group-hover:bg-slate-50/40 z-10 truncate">{{ row.location }}</td>
                        <td style="width:140px;min-width:140px" class="px-3 py-2 sticky left-[120px] bg-white group-hover:bg-slate-50/40 z-10">
                            <span class="text-[10px] font-bold text-slate-600 uppercase tracking-tight">{{ row.provider }}</span>
                        </td>
                        <!-- AVG frozen column -->
                        <td style="width:110px;min-width:110px" class="px-2 py-2 sticky left-[260px] bg-white group-hover:bg-slate-50/40 z-10 border-r border-slate-100">
                            <div class="space-y-0.5">
                                <div class="flex items-center justify-center gap-0.5">
                                    <Download class="size-2.5 shrink-0" :style="{ color: pivotDlColor(row.location, row.provider) }"/>
                                    <span class="text-[9px] font-black tabular-nums" :style="{ color: pivotDlColor(row.location, row.provider) }">{{ row.avg_dl != null ? row.avg_dl + ' Mbps' : '---' }}</span>
                                </div>
                                <div class="flex items-center justify-center gap-0.5">
                                    <Upload class="size-2.5 shrink-0" :style="{ color: pivotUlColor(row.location, row.provider) }"/>
                                    <span class="text-[9px] font-bold tabular-nums" :style="{ color: pivotUlColor(row.location, row.provider) }">{{ row.avg_ul != null ? row.avg_ul + ' Mbps' : '---' }}</span>
                                </div>
                            </div>
                        </td>
                        <td v-for="date in pivotData.dates" :key="date" :style="bwColStyle" class="px-1 py-1.5 text-center">
                            <div v-if="row.cells[date]" class="space-y-0.5">
                                <div v-if="row.cells[date].download !== null" class="flex items-center justify-center gap-0.5">
                                    <Download class="size-2.5 shrink-0" :style="{ color: pivotDlColor(row.location, row.provider) }"/>
                                    <span class="text-[9px] font-black tabular-nums" :style="{ color: pivotDlColor(row.location, row.provider) }">{{ row.cells[date].download }} Mbps</span>
                                </div>
                                <div v-if="row.cells[date].upload !== null" class="flex items-center justify-center gap-0.5">
                                    <Upload class="size-2.5 shrink-0" :style="{ color: pivotUlColor(row.location, row.provider) }"/>
                                    <span class="text-[9px] font-bold tabular-nums" :style="{ color: pivotUlColor(row.location, row.provider) }">{{ row.cells[date].upload }} Mbps</span>
                                </div>
                            </div>
                            <span v-else class="text-slate-200 text-[9px]">&mdash;</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- LOG DETAIL MODAL -->
    <Teleport to="body">
        <div v-if="showLogModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4" @click.self="showLogModal=false">
            <div class="bg-white w-full max-w-lg rounded-2xl shadow-2xl overflow-hidden border border-slate-200 max-h-[85vh] flex flex-col">
                <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 shrink-0">
                    <div>
                        <h3 class="text-sm font-black text-slate-800 flex items-center gap-2">
                            <FileText class="size-4 text-[#003628]"/> Detail Fetch Log
                        </h3>
                        <p v-if="selectedLog" class="text-[10px] text-slate-400 mt-0.5">
                            {{ selectedLog.fetch_date }} &middot; {{ selectedLog.triggered_by }} &middot;
                            <span class="text-emerald-600 font-bold">{{ selectedLog.sensors_ok }} OK</span>
                            <span v-if="selectedLog.sensors_fail > 0"> &middot; <span class="text-rose-600 font-bold">{{ selectedLog.sensors_fail }} gagal</span></span>
                        </p>
                    </div>
                    <button type="button" class="h-8 w-8 rounded-xl border border-slate-200 flex items-center justify-center text-slate-400 hover:text-slate-700 transition-all" @click="showLogModal=false">
                        <X class="size-4"/>
                    </button>
                </div>
                <div class="overflow-y-auto flex-1">
                    <!-- Detail view when log selected -->
                    <div v-if="selectedLog" class="p-5 space-y-3">
                        <div class="flex items-center gap-2">
                            <div class="px-2 py-0.5 rounded-lg border text-[8px] font-black uppercase tracking-widest flex items-center gap-1" :class="logStatusConf(selectedLog.status).bg">
                                <component :is="logStatusConf(selectedLog.status).icon" class="size-3" :class="logStatusConf(selectedLog.status).color"/>
                                <span :class="logStatusConf(selectedLog.status).color">{{ selectedLog.status }}</span>
                            </div>
                            <span v-if="selectedLog.is_manual" class="px-2 py-0.5 rounded-full bg-violet-50 border border-violet-100 text-[8px] font-black uppercase tracking-widest text-violet-600">Manual</span>
                            <button type="button" class="ml-auto text-[9px] text-slate-400 hover:text-slate-600 font-bold" @click="selectedLog=null">Kembali ke list</button>
                        </div>
                        <div v-if="selectedLog.notes" class="space-y-1">
                            <p class="text-[9px] font-black uppercase tracking-widest text-slate-400 mb-2">Detail Gagal</p>
                            <div v-for="(note, i) in selectedLog.notes.split('\n').filter((n: string) => n.trim())" :key="i"
                                class="flex items-start gap-2 px-3 py-2 rounded-xl bg-rose-50 border border-rose-100">
                                <XCircle class="size-3.5 text-rose-400 shrink-0 mt-0.5"/>
                                <span class="text-[10px] font-medium text-rose-700 break-all">{{ note }}</span>
                            </div>
                        </div>
                        <div v-else class="px-4 py-8 text-center">
                            <CheckCircle2 class="size-8 text-emerald-300 mx-auto mb-2"/>
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-300">Semua sensor berhasil</p>
                        </div>
                    </div>
                    <!-- List view when no log selected -->
                    <div v-else class="divide-y divide-slate-50">
                        <button v-for="log in logData" :key="log.id" type="button"
                            class="w-full px-5 py-3 hover:bg-slate-50/80 transition-colors text-left group"
                            @click="selectedLog=log">
                            <div class="flex items-center gap-2 flex-wrap">
                                <div class="shrink-0 px-2 py-0.5 rounded-lg border text-[8px] font-black uppercase tracking-widest flex items-center gap-1" :class="logStatusConf(log.status).bg">
                                    <component :is="logStatusConf(log.status).icon" class="size-3" :class="logStatusConf(log.status).color"/>
                                    <span :class="logStatusConf(log.status).color">{{ log.status }}</span>
                                </div>
                                <span class="text-[11px] font-black text-slate-800 tabular-nums">{{ log.fetch_date }}</span>
                                <span class="text-[10px] font-bold text-slate-400">{{ log.sensors_ok }} OK - {{ log.sensors_fail }} gagal</span>
                                <span v-if="log.is_manual" class="px-2 py-0.5 rounded-full bg-violet-50 border border-violet-100 text-[8px] font-black uppercase tracking-widest text-violet-600">Manual</span>
                                <ChevronRight class="size-3 text-slate-300 ml-auto group-hover:text-slate-500 shrink-0"/>
                            </div>
                            <div class="flex items-center justify-between mt-1">
                                <span class="text-[9px] font-bold text-slate-500">{{ log.triggered_by }}</span>
                                <span class="text-[9px] text-slate-300 tabular-nums">{{ log.created_at }}</span>
                            </div>
                        </button>
                        <div v-if="!logData.length" class="py-12 text-center">
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-300">Belum ada log</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>

</div>
</template>
