<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    Wifi, RefreshCw, Download, Upload, MapPin,
    Calendar, Filter, Play, CheckCircle2, AlertTriangle,
    XCircle, Clock, Loader2, BarChart3, Table2, FileText,
    FileDown, Settings2, ChevronUp, ChevronDown, ChevronsUpDown,
    Search, X,
} from 'lucide-vue-next';
import { ref, computed, onMounted, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps<{ locations: string[] }>();

const filterFrom     = ref(formatDate(new Date(Date.now() - 29 * 86400000)));
const filterTo       = ref(formatDate(new Date()));
const filterLocation = ref('');

const summaryData = ref<{ dates: string[]; series: any[]; cards: any[] } | null>(null);
const tableData   = ref<any[]>([]);
const logData     = ref<any[]>([]);

const loadingSummary = ref(false);
const loadingTable   = ref(false);
const loadingLog     = ref(false);

const fetchDate    = ref(formatDate(new Date(Date.now() - 86400000)));
const fetchLoading = ref(false);
const fetchMessage = ref('');
const fetchStatus  = ref<'idle' | 'success' | 'error'>('idle');

const showLogModal    = ref(false);
const logSearch       = ref('');
const logFilterStatus = ref('');

// -- Flyout state
const openFlyout = ref<'filter' | 'export' | 'fetch' | null>(null);
function toggleFlyout(name: 'filter' | 'export' | 'fetch') {
    openFlyout.value = openFlyout.value === name ? null : name;
}
function closeFlyout() { openFlyout.value = null; }

const filteredLogData = computed(() => {
    let data = logData.value;
    if (logSearch.value) {
        const q = logSearch.value.toLowerCase();
        data = data.filter((l: any) =>
            l.fetch_date.includes(q) ||
            (l.triggered_by || '').toLowerCase().includes(q) ||
            (l.notes || '').toLowerCase().includes(q)
        );
    }
    if (logFilterStatus.value) {
        data = data.filter((l: any) => l.status === logFilterStatus.value);
    }
    return data;
});

const sortCol = ref('date');
const sortDir = ref<'asc' | 'desc'>('desc');

function toggleSort(col: string) {
    if (sortCol.value === col) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortCol.value = col;
        sortDir.value = 'asc';
    }
}

const sortedTableData = computed(() => {
    const data = [...tableData.value];
    data.sort((a: any, b: any) => {
        const av = a[sortCol.value] ?? '';
        const bv = b[sortCol.value] ?? '';
        if (typeof av === 'number' && typeof bv === 'number') {
            return sortDir.value === 'asc' ? av - bv : bv - av;
        }
        return sortDir.value === 'asc'
            ? String(av).localeCompare(String(bv))
            : String(bv).localeCompare(String(av));
    });
    return data;
});

const tablePage    = ref(1);
const tablePerPage = ref(15);

const tablePaginated = computed(() => {
    const start = (tablePage.value - 1) * tablePerPage.value;
    return sortedTableData.value.slice(start, start + tablePerPage.value);
});

const tableTotalPages = computed(() =>
    Math.max(1, Math.ceil(tableData.value.length / tablePerPage.value))
);

const tablePageNumbers = computed(() => {
    const total = tableTotalPages.value;
    const cur   = tablePage.value;
    const pages: (number | '...')[] = [];
    if (total <= 7) {
        for (let i = 1; i <= total; i++) pages.push(i);
    } else {
        pages.push(1);
        if (cur > 3) pages.push('...');
        for (let i = Math.max(2, cur - 1); i <= Math.min(total - 1, cur + 1); i++) pages.push(i);
        if (cur < total - 2) pages.push('...');
        pages.push(total);
    }
    return pages;
});

watch(tableData, () => { tablePage.value = 1; });

function formatDate(d: Date): string {
    return d.toISOString().slice(0, 10);
}

function buildParams() {
    const p = new URLSearchParams({ from: filterFrom.value, to: filterTo.value });
    if (filterLocation.value) p.set('location', filterLocation.value);
    return p.toString();
}

async function loadSummary() {
    loadingSummary.value = true;
    try {
        const res = await fetch(`/bandwidth/summary?${buildParams()}`);
        summaryData.value = await res.json();
    } finally { loadingSummary.value = false; }
}

async function loadTable() {
    loadingTable.value = true;
    try {
        const res = await fetch(`/bandwidth/data?${buildParams()}`);
        tableData.value = await res.json();
    } finally { loadingTable.value = false; }
}

async function loadLog() {
    loadingLog.value = true;
    try {
        const res = await fetch('/cctv/logs');
        logData.value = await res.json();
    } finally { loadingLog.value = false; }
}

function applyFilter() {
    loadSummary();
    loadTable();
}

onMounted(() => { loadSummary(); loadTable(); loadLog(); });

async function doManualFetch() {
    if (!fetchDate.value) return;
    fetchLoading.value = true;
    fetchMessage.value = '';
    fetchStatus.value  = 'idle';
    const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
    try {
        const res  = await fetch('/cctv/fetch', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ date: fetchDate.value }),
        });
        const data = await res.json();
        fetchMessage.value = data.message;
        fetchStatus.value  = res.ok || res.status === 206 ? 'success' : 'error';
        if (data.errors?.length) fetchMessage.value += '\n' + data.errors.join('\n');
        if (res.ok) { summaryData.value = null; tableData.value = []; logData.value = []; loadSummary(); loadTable(); loadLog(); }
    } catch { fetchMessage.value = 'Gagal menghubungi server.'; fetchStatus.value = 'error'; }
    finally  { fetchLoading.value = false; }
}

const COLORS = ['#003628','#0ea5e9','#f59e0b','#8b5cf6','#ef4444','#10b981','#f97316','#6366f1','#ec4899','#14b8a6','#a855f7','#84cc16'];
function seriesColor(i: number) { return COLORS[i % COLORS.length]; }

const CHART_W = 900, CHART_H = 320, CHART_PAD_L = 70, CHART_PAD_R = 20, CHART_PAD_T = 20, CHART_PAD_B = 40;

const chartMax = computed(() => {
    if (!summaryData.value) return 1;
    let max = 0;
    for (const s of summaryData.value.series) for (const v of s.data) if (v !== null && v > max) max = v;
    return max || 1;
});

function toX(i: number, n: number) {
    const w = CHART_W - CHART_PAD_L - CHART_PAD_R;
    return n <= 1 ? CHART_PAD_L + w / 2 : CHART_PAD_L + (i / (n - 1)) * w;
}
function toY(v: number) {
    const h = CHART_H - CHART_PAD_T - CHART_PAD_B;
    return CHART_PAD_T + h - (v / chartMax.value) * h;
}
function buildLinePath(data: (number|null)[], n: number) {
    let p = '', started = false;
    for (let i = 0; i < data.length; i++) {
        if (data[i] === null) { started = false; continue; }
        const x = toX(i, n), y = toY(data[i]!);
        p += started ? ` L ${x} ${y}` : `M ${x} ${y}`; started = true;
    }
    return p;
}
function buildAreaPath(data: (number|null)[], n: number) {
    const bot = CHART_H - CHART_PAD_B; let p = '', seg = -1;
    for (let i = 0; i <= data.length; i++) {
        const v = i < data.length ? data[i] : null;
        if (v !== null && seg === -1) { seg = i; p += `M ${toX(i,n)} ${bot} L ${toX(i,n)} ${toY(v)}`; }
        else if (v !== null) { p += ` L ${toX(i,n)} ${toY(v)}`; }
        else if (seg !== -1) { p += ` L ${toX(i-1,n)} ${bot} Z`; seg = -1; }
    }
    return p;
}

const yTicks = computed(() => {
    const max = chartMax.value, step = Math.ceil(max / 5 / 10) * 10 || 10, ticks = [];
    for (let v = 0; v <= max * 1.1; v += step) ticks.push(v);
    return ticks;
});
const xTickIndices = computed(() => {
    const dates = summaryData.value?.dates ?? [], n = dates.length;
    if (!n) return [];
    const step = Math.max(1, Math.ceil(n / 10)), idx: number[] = [];
    for (let i = 0; i < n; i += step) idx.push(i);
    if (idx[idx.length - 1] !== n - 1) idx.push(n - 1);
    return idx;
});

const chartSvg = computed((): string => {
    const sd = summaryData.value;
    if (!sd || !sd.dates.length) return '';
    const n = sd.dates.length;
    let svg = `<svg viewBox="0 0 ${CHART_W} ${CHART_H}" width="100%" style="font-family:inherit;display:block;min-width:600px;">`;

    for (const tick of yTicks.value) {
        const y = toY(tick);
        svg += `<line x1="${CHART_PAD_L}" y1="${y}" x2="${CHART_W-CHART_PAD_R}" y2="${y}" stroke="#e2e8f0" stroke-width="1"/>`;
        svg += `<text x="${CHART_PAD_L-6}" y="${y+4}" text-anchor="end" font-size="9" fill="#94a3b8">${tick}</text>`;
    }
    for (const idx of xTickIndices.value) {
        svg += `<text x="${toX(idx,n)}" y="${CHART_H-CHART_PAD_B+16}" text-anchor="middle" font-size="9" fill="#94a3b8">${sd.dates[idx]?.slice(5)??''}</text>`;
    }
    svg += `<line x1="${CHART_PAD_L}" y1="${CHART_PAD_T}" x2="${CHART_PAD_L}" y2="${CHART_H-CHART_PAD_B}" stroke="#cbd5e1" stroke-width="1"/>`;
    svg += `<line x1="${CHART_PAD_L}" y1="${CHART_H-CHART_PAD_B}" x2="${CHART_W-CHART_PAD_R}" y2="${CHART_H-CHART_PAD_B}" stroke="#cbd5e1" stroke-width="1"/>`;
    for (let si = 0; si < sd.series.length; si++) {
        const d = buildAreaPath(sd.series[si].data, n);
        if (d) svg += `<path d="${d}" fill="${seriesColor(si)}" fill-opacity="0.07"/>`;
    }
    for (let si = 0; si < sd.series.length; si++) {
        const d = buildLinePath(sd.series[si].data, n);
        if (d) svg += `<path d="${d}" stroke="${seriesColor(si)}" stroke-width="1.8" fill="none" stroke-linejoin="round" stroke-linecap="round"/>`;
    }
    for (let si = 0; si < sd.series.length; si++) {
        for (let di = 0; di < sd.series[si].data.length; di++) {
            const v = sd.series[si].data[di];
            if (v === null) continue;
            svg += `<circle cx="${toX(di,n)}" cy="${toY(v)}" r="3" fill="${seriesColor(si)}" stroke="white" stroke-width="1.5"><title>${sd.dates[di]} · ${sd.series[si].name}: ${v} Mbps</title></circle>`;
        }
    }
    svg += '</svg>';
    return svg;
});

function exportUrl() {
    const p = new URLSearchParams({ from: filterFrom.value, to: filterTo.value });
    return '/cctv/export?' + p.toString();
}

function logStatusConf(status: string) {
    if (status === 'success') return { icon: CheckCircle2, color: 'text-emerald-600', bg: 'bg-emerald-50 border-emerald-100' };
    if (status === 'partial') return { icon: AlertTriangle, color: 'text-amber-600',  bg: 'bg-amber-50 border-amber-100' };
    return                           { icon: XCircle,       color: 'text-rose-600',   bg: 'bg-rose-50 border-rose-100' };
}

function sortIcon(col: string) {
    if (sortCol.value !== col) return ChevronsUpDown;
    return sortDir.value === 'asc' ? ChevronUp : ChevronDown;
}
</script>
<template>
    <Head title="CCTV Operation" />
    <AppLayout :breadcrumbs="[{title:'Dashboard',href:'/dashboard'},{title:'Report',href:'/reports'},{title:'CCTV Operation',href:'/bandwidth'}]">
        <div class="space-y-5 max-w-7xl mx-auto py-2">

            <!-- HEADER -->
            <!-- Click-outside backdrop to close flyouts -->
            <div v-if="openFlyout" class="fixed inset-0 z-40" @click="closeFlyout"></div>

            <div class="flex items-center justify-between gap-4 flex-wrap">
                <div>
                    <h1 class="text-xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                        <Wifi class="size-5 text-[#003628]" /> CCTV Operation
                    </h1>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-0.5">PRTG Network Monitor - CCTV</p>
                </div>

                <div class="flex items-center gap-2">

                    <!-- Filter button + flyout -->
                    <div class="relative z-50">
                        <button type="button" class="h-9 w-9 rounded-xl bg-white border border-slate-200 shadow-sm flex items-center justify-center transition-all hover:bg-slate-50 hover:border-slate-300" :class="openFlyout==='filter'?'bg-slate-100 border-slate-300':''" title="Filter Tanggal" @click.stop="toggleFlyout('filter')">
                            <Filter class="size-4 text-slate-600" />
                        </button>
                        <div v-if="openFlyout==='filter'" class="absolute right-0 top-full mt-2 w-72 bg-white rounded-2xl shadow-2xl border border-slate-200 p-5 space-y-4" @click.stop>
                            <p class="text-[9px] font-black uppercase tracking-widest text-slate-400 flex items-center gap-1.5">
                                <Filter class="size-3" /> Rentang Tanggal
                            </p>
                            <div class="flex items-center gap-2">
                                <input v-model="filterFrom" type="date" :max="filterTo" class="flex-1 h-9 px-3 rounded-xl border border-slate-200 bg-slate-50 text-[11px] font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#003628]/20" />
                                <span class="text-slate-300 text-xs font-bold shrink-0">-</span>
                                <input v-model="filterTo" type="date" :min="filterFrom" :max="formatDate(new Date())" class="flex-1 h-9 px-3 rounded-xl border border-slate-200 bg-slate-50 text-[11px] font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#003628]/20" />
                            </div>
                            <button type="button" class="w-full h-9 rounded-xl bg-[#003628] text-white text-[10px] font-black uppercase tracking-widest hover:brightness-110 transition-all active:scale-95 flex items-center justify-center gap-2 shadow-md shadow-[#003628]/20" @click="applyFilter(); closeFlyout()">
                                <RefreshCw class="size-3.5" /> Apply Filter
                            </button>
                        </div>
                    </div>

                    <!-- Export button + flyout -->
                    <div class="relative z-50">
                        <button type="button" class="h-9 w-9 rounded-xl bg-white border border-slate-200 shadow-sm flex items-center justify-center transition-all hover:bg-slate-50 hover:border-slate-300" :class="openFlyout==='export'?'bg-slate-100 border-slate-300':''" title="Export Excel" @click.stop="toggleFlyout('export')">
                            <FileDown class="size-4 text-slate-600" />
                        </button>
                        <div v-if="openFlyout==='export'" class="absolute right-0 top-full mt-2 w-64 bg-white rounded-2xl shadow-2xl border border-slate-200 p-5 space-y-3" @click.stop>
                            <p class="text-[9px] font-black uppercase tracking-widest text-slate-400 flex items-center gap-1.5">
                                <FileDown class="size-3" /> Export Data
                            </p>
                            <p class="text-[10px] text-slate-400 font-medium">{{ filterFrom }} sampai {{ filterTo }}</p>
                            <a :href="exportUrl()" class="w-full h-9 rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-700 text-[10px] font-black uppercase tracking-widest hover:bg-emerald-100 transition-all active:scale-95 flex items-center justify-center gap-2" @click="closeFlyout">
                                <FileDown class="size-3.5" /> Export Excel
                            </a>
                        </div>
                    </div>

                    <!-- Fetch button + flyout -->
                    <div class="relative z-50">
                        <button type="button" class="h-9 w-9 rounded-xl bg-white border border-slate-200 shadow-sm flex items-center justify-center transition-all hover:bg-slate-50 hover:border-slate-300" :class="openFlyout==='fetch'?'bg-slate-100 border-slate-300':''" title="Tarik Data PRTG" @click.stop="toggleFlyout('fetch')">
                            <Play class="size-4 text-slate-600" />
                        </button>
                        <div v-if="openFlyout==='fetch'" class="absolute right-0 top-full mt-2 w-72 bg-white rounded-2xl shadow-2xl border border-slate-200 p-5 space-y-4" @click.stop>
                            <p class="text-[9px] font-black uppercase tracking-widest text-slate-400 flex items-center gap-1.5">
                                <Play class="size-3" /> Tarik Data PRTG
                            </p>
                            <p class="text-[10px] text-slate-400 font-medium leading-relaxed">Pilih tanggal lalu klik Fetch untuk menarik data dari PRTG ke database.</p>
                            <div class="flex items-center gap-2">
                                <input v-model="fetchDate" type="date" :max="formatDate(new Date())" class="flex-1 h-9 px-3 rounded-xl border border-slate-200 bg-slate-50 text-[11px] font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#003628]/20" />
                                <button type="button" :disabled="fetchLoading" class="h-9 px-4 rounded-xl bg-[#003628] text-white text-[10px] font-black uppercase tracking-widest hover:brightness-110 transition-all active:scale-95 disabled:opacity-50 flex items-center gap-1.5 shadow-md shadow-[#003628]/20 shrink-0" @click="doManualFetch">
                                    <Loader2 v-if="fetchLoading" class="size-3 animate-spin" />
                                    <Play v-else class="size-3" />
                                    Fetch
                                </button>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- FETCH MESSAGE -->
            <div v-if="fetchMessage" class="flex items-start gap-2.5 px-4 py-3 rounded-xl text-[11px] font-bold border whitespace-pre-line shadow-sm" :class="fetchStatus==='success'?'bg-emerald-50 border-emerald-200 text-emerald-700':'bg-rose-50 border-rose-200 text-rose-700'">
                <CheckCircle2 v-if="fetchStatus==='success'" class="size-4 shrink-0 mt-0.5" />
                <XCircle v-else class="size-4 shrink-0 mt-0.5" />
                <span>{{ fetchMessage }}</span>
            </div>

            <!-- TOP: 3 LOCATION CARDS -->
            <div v-if="loadingSummary" class="grid grid-cols-3 gap-4">
                <div v-for="n in 3" :key="n" class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5 space-y-3 animate-pulse">
                    <div class="h-5 bg-slate-100 rounded-full w-2/3"></div>
                    <div class="h-px bg-slate-100"></div>
                    <div class="h-4 bg-slate-100 rounded-full w-full"></div>
                    <div class="h-4 bg-slate-100 rounded-full w-5/6"></div>
                    <div class="h-4 bg-slate-100 rounded-full w-4/6"></div>
                </div>
            </div>
            <div v-else-if="!summaryData || !summaryData.cards || summaryData.cards.length===0" class="grid grid-cols-3 gap-4">
                <div class="col-span-3 py-12 text-center bg-white rounded-2xl border border-slate-100 shadow-sm">
                    <MapPin class="size-8 text-slate-200 mx-auto mb-2" />
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-300">Belum ada data lokasi</p>
                </div>
            </div>
            <div v-else class="grid grid-cols-3 gap-4">
                <div v-for="card in summaryData.cards" :key="card.location" class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="flex items-center justify-between px-5 pt-4 pb-3">
                        <span class="text-base font-black text-slate-900 tracking-tight">{{ card.location }}</span>
                        <MapPin class="size-4 text-slate-300 shrink-0" />
                    </div>
                    <div class="h-px bg-slate-100 mx-5"></div>
                    <div class="px-5 py-3">
                        <table class="w-full">
                            <thead>
                                <tr class="bg-slate-50">
                                    <th class="py-2 pr-3 text-left text-[9px] font-black uppercase tracking-widest text-slate-400 rounded-l-lg">Provider / Desc</th>
                                    <th class="py-2 px-2 text-right text-[9px] font-black uppercase tracking-widest text-slate-400">AVG</th>
                                    <th class="py-2 pl-2 text-right text-[9px] font-black uppercase tracking-widest text-slate-400 rounded-r-lg">MAX</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <tr v-for="s in card.series" :key="s.name" class="hover:bg-slate-50/50 transition-colors">
                                    <td class="py-2 pr-3 text-[10px] font-bold text-slate-600 truncate max-w-[120px]">{{ s.name }}</td>
                                    <td class="py-2 px-2 text-right text-[11px] font-black text-slate-700 tabular-nums">{{ s.avg_mbps !== null ? s.avg_mbps : '-' }}</td>
                                    <td class="py-2 pl-2 text-right text-[11px] font-black tabular-nums" :class="s.max_mbps>50?'text-emerald-600':s.max_mbps>20?'text-amber-600':'text-rose-600'">{{ s.max_mbps !== null ? s.max_mbps : '-' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- MIDDLE: Chart + Log -->
            <div class="grid grid-cols-3 gap-4">
                <!-- Chart col-span-2 -->
                <div class="col-span-2 bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
                        <div class="flex items-center gap-2">
                            <BarChart3 class="size-4 text-[#003628]" />
                            <p class="text-sm font-black text-slate-800">CCTV Operation (Mbps)</p>
                        </div>
                        <span class="text-[10px] font-bold text-slate-400 tabular-nums">{{ filterFrom }} - {{ filterTo }}</span>
                    </div>
                    <div v-if="loadingSummary" class="p-5 space-y-3">
                        <div class="h-64 bg-slate-100 rounded-xl animate-pulse"></div>
                    </div>
                    <div v-else-if="!summaryData||summaryData.dates.length===0" class="py-20 text-center">
                        <BarChart3 class="size-10 text-slate-200 mx-auto mb-3" />
                        <p class="text-[11px] font-black uppercase tracking-widest text-slate-300">Belum ada data</p>
                    </div>
                    <div v-else class="p-5">
                        <div v-html="chartSvg" class="w-full"></div>
                    </div>
                    <div v-if="summaryData&&summaryData.series&&summaryData.series.length" class="px-5 pb-4 pt-2 border-t border-slate-50 flex flex-wrap gap-x-4 gap-y-2">
                        <div v-for="(s,si) in summaryData.series" :key="s.name" class="flex items-center gap-1.5">
                            <div class="w-4 h-1 rounded-full shrink-0" :style="{backgroundColor:seriesColor(si)}"></div>
                            <span class="text-[9px] font-bold text-slate-500">{{ s.name }}</span>
                        </div>
                    </div>
                </div>

                <!-- Fetch Log col-span-1 -->
                <div class="col-span-1 bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden flex flex-col">
                    <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100 shrink-0">
                        <div class="flex items-center gap-2">
                            <FileText class="size-4 text-[#003628]" />
                            <p class="text-sm font-black text-slate-800">Fetch Log</p>
                        </div>
                        <button type="button" class="h-7 w-7 rounded-lg flex items-center justify-center hover:bg-slate-100 transition-colors text-slate-400" @click="loadLog">
                            <RefreshCw class="size-3.5" :class="{'animate-spin':loadingLog}" />
                        </button>
                    </div>
                    <div v-if="loadingLog" class="p-4 space-y-2">
                        <div v-for="n in 4" :key="n" class="h-12 bg-slate-100 rounded-xl animate-pulse"></div>
                    </div>
                    <div v-else-if="logData.length===0" class="flex-1 flex flex-col items-center justify-center py-10 text-center">
                        <Clock class="size-7 text-slate-200 mx-auto mb-2" />
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-300">Belum ada log</p>
                    </div>
                    <div v-else class="flex flex-col flex-1 min-h-0">
                        <div class="overflow-y-auto max-h-[420px] divide-y divide-slate-50">
                            <div v-for="log in logData.slice(0,15)" :key="log.id" class="px-4 py-3 hover:bg-slate-50/50 transition-colors">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <div class="shrink-0 px-2 py-0.5 rounded-lg border text-[8px] font-black uppercase tracking-widest flex items-center gap-1" :class="logStatusConf(log.status).bg">
                                        <component :is="logStatusConf(log.status).icon" class="size-3" :class="logStatusConf(log.status).color" />
                                        <span :class="logStatusConf(log.status).color">{{ log.status }}</span>
                                    </div>
                                    <span class="text-[11px] font-black text-slate-800 tabular-nums">{{ log.fetch_date }}</span>
                                    <span class="text-[10px] font-bold text-slate-400">{{ log.sensors_ok }} OK · {{ log.sensors_fail }} gagal</span>
                                    <span v-if="log.is_manual" class="px-2 py-0.5 rounded-full bg-violet-50 border border-violet-100 text-[8px] font-black uppercase tracking-widest text-violet-600">Manual</span>
                                </div>
                                <div class="flex items-center justify-between mt-1">
                                    <span class="text-[9px] font-bold text-slate-500">{{ log.triggered_by }}</span>
                                    <span class="text-[9px] text-slate-300 tabular-nums">{{ log.created_at }}</span>
                                </div>
                            </div>
                        </div>
                        <div v-if="logData.length>15" class="shrink-0 px-4 py-3 border-t border-slate-100">
                            <button type="button" class="w-full h-8 rounded-xl border border-slate-200 bg-slate-50 text-slate-600 text-[10px] font-black uppercase tracking-widest hover:bg-slate-100 transition-all flex items-center justify-center gap-1.5" @click="showLogModal=true">
                                <FileText class="size-3.5" /> Show More ({{ logData.length }} total)
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- BOTTOM: Sortable Table -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
                    <div class="flex items-center gap-2">
                        <Table2 class="size-4 text-[#003628]" />
                        <div>
                            <p class="text-sm font-black text-slate-800">Detail Data</p>
                            <p class="text-[10px] font-bold text-slate-400">{{ filterFrom }} - {{ filterTo }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-[10px] font-bold text-slate-400">Per halaman</span>
                        <select v-model="tablePerPage" class="h-7 px-2 rounded-lg border border-slate-200 bg-slate-50 text-[10px] font-bold text-slate-600 focus:outline-none" @change="tablePage=1">
                            <option :value="10">10</option>
                            <option :value="15">15</option>
                            <option :value="25">25</option>
                            <option :value="50">50</option>
                        </select>
                    </div>
                </div>
                <div v-if="loadingTable" class="p-4 space-y-2">
                    <div v-for="n in 5" :key="n" class="h-11 bg-slate-100 rounded-xl animate-pulse"></div>
                </div>
                <div v-else-if="tableData.length===0" class="py-16 text-center">
                    <Table2 class="size-8 text-slate-200 mx-auto mb-2" />
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-300">Tidak ada data</p>
                </div>
                <div v-else>
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100">
                                <th class="px-5 py-3 text-left cursor-pointer select-none hover:bg-slate-100 transition-colors" @click="toggleSort('date')">
                                    <span class="flex items-center gap-1 text-[9px] font-black uppercase tracking-widest text-slate-400">
                                        Tanggal <component :is="sortIcon('date')" class="size-3 shrink-0" />
                                    </span>
                                </th>
                                <th class="px-5 py-3 text-left cursor-pointer select-none hover:bg-slate-100 transition-colors" @click="toggleSort('location')">
                                    <span class="flex items-center gap-1 text-[9px] font-black uppercase tracking-widest text-slate-400">
                                        Area <component :is="sortIcon('location')" class="size-3 shrink-0" />
                                    </span>
                                </th>
                                <th class="px-5 py-3 text-left cursor-pointer select-none hover:bg-slate-100 transition-colors" @click="toggleSort('provider')">
                                    <span class="flex items-center gap-1 text-[9px] font-black uppercase tracking-widest text-slate-400">
                                        Provider <component :is="sortIcon('provider')" class="size-3 shrink-0" />
                                    </span>
                                </th>
                                <th class="px-5 py-3 text-right cursor-pointer select-none hover:bg-slate-100 transition-colors" @click="toggleSort('download')">
                                    <span class="flex items-center justify-end gap-1 text-[9px] font-black uppercase tracking-widest text-slate-400">
                                        <Download class="size-3" /> Download <component :is="sortIcon('download')" class="size-3 shrink-0" />
                                    </span>
                                </th>
                                <th class="px-5 py-3 text-right cursor-pointer select-none hover:bg-slate-100 transition-colors" @click="toggleSort('upload')">
                                    <span class="flex items-center justify-end gap-1 text-[9px] font-black uppercase tracking-widest text-slate-400">
                                        <Upload class="size-3" /> Upload <component :is="sortIcon('upload')" class="size-3 shrink-0" />
                                    </span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <tr v-for="row in tablePaginated" :key="`${row.date}-${row.location}-${row.provider}`" class="hover:bg-slate-50/60 transition-colors">
                                <td class="px-5 py-3 text-[11px] font-bold text-slate-600 tabular-nums">{{ row.date }}</td>
                                <td class="px-5 py-3">
                                    <span class="flex items-center gap-1.5 text-[11px] font-bold text-slate-800">
                                        <MapPin class="size-3 text-slate-300 shrink-0" /> {{ row.location }}
                                    </span>
                                </td>
                                <td class="px-5 py-3">
                                    <span class="px-2 py-0.5 rounded-full bg-slate-50 border border-slate-100 text-[9px] font-black uppercase tracking-widest text-slate-600">{{ row.provider }}</span>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <span v-if="row.download!==null" class="text-[12px] font-black tabular-nums" :class="row.download>50?'text-emerald-600':row.download>20?'text-amber-600':'text-rose-600'">
                                        {{ row.download }} <span class="text-[9px] font-bold text-slate-400">Mbps</span>
                                    </span>
                                    <span v-else class="text-slate-300">-</span>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <span v-if="row.upload!==null" class="text-[12px] font-black tabular-nums text-sky-600">
                                        {{ row.upload }} <span class="text-[9px] font-bold text-slate-400">Mbps</span>
                                    </span>
                                    <span v-else class="text-slate-300">-</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="flex items-center justify-between px-5 py-3 border-t border-slate-100 bg-slate-50/40">
                        <span class="text-[10px] font-bold text-slate-400">{{ (tablePage-1)*tablePerPage+1 }}-{{ Math.min(tablePage*tablePerPage,tableData.length) }} dari {{ tableData.length }} baris</span>
                        <div class="flex items-center gap-1">
                            <button type="button" :disabled="tablePage===1" class="h-7 w-7 rounded-lg flex items-center justify-center text-[13px] font-black transition-all disabled:opacity-30 hover:bg-slate-200 text-slate-600" @click="tablePage--">&#8249;</button>
                            <template v-for="p in tablePageNumbers" :key="String(p)">
                                <span v-if="p==='...'" class="h-7 w-5 flex items-center justify-center text-[10px] text-slate-300">-</span>
                                <button v-else type="button" class="h-7 w-7 rounded-lg text-[10px] font-black transition-all" :class="tablePage===p?'bg-[#003628] text-white shadow-sm':'hover:bg-slate-100 text-slate-600'" @click="tablePage=p as number">{{ p }}</button>
                            </template>
                            <button type="button" :disabled="tablePage===tableTotalPages" class="h-7 w-7 rounded-lg flex items-center justify-center text-[13px] font-black transition-all disabled:opacity-30 hover:bg-slate-200 text-slate-600" @click="tablePage++">&#8250;</button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </AppLayout>

    <!-- LOG MODAL -->
    <Teleport to="body">
        <div v-if="showLogModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4" @click.self="showLogModal=false">
            <div class="bg-white w-full max-w-2xl rounded-2xl shadow-2xl overflow-hidden border border-slate-200 flex flex-col max-h-[85vh]">
                <!-- Modal header -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 shrink-0">
                    <div class="flex items-center gap-2">
                        <FileText class="size-4 text-[#003628]" />
                        <h3 class="text-sm font-black text-slate-800">Fetch Log History</h3>
                        <span class="px-2 py-0.5 rounded-full bg-slate-100 text-[9px] font-black text-slate-500">{{ filteredLogData.length }} entri</span>
                    </div>
                    <button type="button" class="h-8 w-8 rounded-xl border border-slate-200 bg-white flex items-center justify-center text-slate-400 hover:text-slate-700 hover:border-slate-300 transition-all" @click="showLogModal=false">
                        <X class="size-4" />
                    </button>
                </div>
                <!-- Search + filter -->
                <div class="flex items-center gap-2 px-6 py-3 border-b border-slate-100 shrink-0">
                    <div class="relative flex-1">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 size-3.5 text-slate-300" />
                        <input v-model="logSearch" type="text" placeholder="Cari tanggal, user, notes..." class="w-full h-9 pl-9 pr-4 rounded-xl border border-slate-200 bg-slate-50 text-[11px] font-bold text-slate-700 placeholder:text-slate-300 focus:outline-none focus:ring-2 focus:ring-[#003628]/20" />
                    </div>
                    <select v-model="logFilterStatus" class="h-9 px-3 rounded-xl border border-slate-200 bg-slate-50 text-[11px] font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#003628]/20">
                        <option value="">Semua Status</option>
                        <option value="success">Success</option>
                        <option value="partial">Partial</option>
                        <option value="failed">Failed</option>
                    </select>
                </div>
                <!-- Log list -->
                <div class="overflow-y-auto flex-1 divide-y divide-slate-50">
                    <div v-if="filteredLogData.length===0" class="py-16 text-center">
                        <Clock class="size-7 text-slate-200 mx-auto mb-2" />
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-300">Tidak ada hasil</p>
                    </div>
                    <div v-for="log in filteredLogData" :key="log.id" class="px-6 py-3 hover:bg-slate-50/50 transition-colors">
                        <div class="flex items-center gap-2 flex-wrap">
                            <div class="shrink-0 px-2 py-0.5 rounded-lg border text-[8px] font-black uppercase tracking-widest flex items-center gap-1" :class="logStatusConf(log.status).bg">
                                <component :is="logStatusConf(log.status).icon" class="size-3" :class="logStatusConf(log.status).color" />
                                <span :class="logStatusConf(log.status).color">{{ log.status }}</span>
                            </div>
                            <span class="text-[12px] font-black text-slate-800 tabular-nums">{{ log.fetch_date }}</span>
                            <span class="text-[10px] font-bold text-slate-400">{{ log.sensors_ok }} OK · {{ log.sensors_fail }} gagal</span>
                            <span v-if="log.is_manual" class="px-2 py-0.5 rounded-full bg-violet-50 border border-violet-100 text-[8px] font-black uppercase tracking-widest text-violet-600">Manual</span>
                        </div>
                        <div class="flex items-center justify-between mt-1">
                            <span class="text-[9px] font-bold text-slate-500">{{ log.triggered_by }}</span>
                            <span class="text-[9px] text-slate-300 tabular-nums">{{ log.created_at }}</span>
                        </div>
                        <p v-if="log.notes" class="text-[9px] text-slate-400 font-mono mt-1 truncate">{{ log.notes }}</p>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>

</template>
