<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    Wifi,
    RefreshCw,
    Download,
    Upload,
    MapPin,
    Calendar,
    Filter,
    Play,
    CheckCircle2,
    AlertTriangle,
    XCircle,
    Clock,
    Loader2,
    BarChart3,
    Table2,
    FileText,
    FileDown,
    Settings2,
    ChevronUp,
    ChevronDown,
    ChevronsUpDown,
    Search,
    X,
} from 'lucide-vue-next';
import { ref, computed, onMounted, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps<{ locations: string[] }>();

const filterFrom = ref(formatDate(new Date(Date.now() - 29 * 86400000)));
const filterTo = ref(formatDate(new Date()));
const filterLocation = ref('');

const summaryData = ref<{
    dates: string[];
    series: any[];
    cards: any[];
} | null>(null);
const tableData = ref<any[]>([]);
const logData = ref<any[]>([]);

const loadingSummary = ref(false);
const loadingTable = ref(false);
const loadingLog = ref(false);

const fetchDate = ref(formatDate(new Date(Date.now() - 86400000)));
const fetchLoading = ref(false);
const fetchMessage = ref('');
const fetchStatus = ref<'idle' | 'success' | 'error'>('idle');

const showLogModal = ref(false);
const logSearch = ref('');
const logFilterStatus = ref('');

// -- Flyout state
const openFlyout = ref<'filter' | 'export' | 'fetch' | null>(null);
function toggleFlyout(name: 'filter' | 'export' | 'fetch') {
    openFlyout.value = openFlyout.value === name ? null : name;
}
function closeFlyout() {
    openFlyout.value = null;
}

const filteredLogData = computed(() => {
    let data = logData.value;
    if (logSearch.value) {
        const q = logSearch.value.toLowerCase();
        data = data.filter(
            (l: any) =>
                l.fetch_date.includes(q) ||
                (l.triggered_by || '').toLowerCase().includes(q) ||
                (l.notes || '').toLowerCase().includes(q),
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

const tablePage = ref(1);
const tablePerPage = ref(15);

const tablePaginated = computed(() => {
    const start = (tablePage.value - 1) * tablePerPage.value;
    return sortedTableData.value.slice(start, start + tablePerPage.value);
});

const tableTotalPages = computed(() =>
    Math.max(1, Math.ceil(tableData.value.length / tablePerPage.value)),
);

const tablePageNumbers = computed(() => {
    const total = tableTotalPages.value;
    const cur = tablePage.value;
    const pages: (number | '...')[] = [];
    if (total <= 7) {
        for (let i = 1; i <= total; i++) pages.push(i);
    } else {
        pages.push(1);
        if (cur > 3) pages.push('...');
        for (
            let i = Math.max(2, cur - 1);
            i <= Math.min(total - 1, cur + 1);
            i++
        )
            pages.push(i);
        if (cur < total - 2) pages.push('...');
        pages.push(total);
    }
    return pages;
});

watch(tableData, () => {
    tablePage.value = 1;
});

function formatDate(d: Date): string {
    return d.toISOString().slice(0, 10);
}

function buildParams() {
    const p = new URLSearchParams({
        from: filterFrom.value,
        to: filterTo.value,
    });
    if (filterLocation.value) p.set('location', filterLocation.value);
    return p.toString();
}

async function loadSummary() {
    loadingSummary.value = true;
    try {
        const res = await fetch(`/bandwidth/summary?${buildParams()}`);
        summaryData.value = await res.json();
    } finally {
        loadingSummary.value = false;
    }
}

async function loadTable() {
    loadingTable.value = true;
    try {
        const res = await fetch(`/bandwidth/data?${buildParams()}`);
        tableData.value = await res.json();
    } finally {
        loadingTable.value = false;
    }
}

async function loadLog() {
    loadingLog.value = true;
    try {
        const res = await fetch('/bandwidth/logs');
        logData.value = await res.json();
    } finally {
        loadingLog.value = false;
    }
}

function applyFilter() {
    loadSummary();
    loadTable();
}

onMounted(() => {
    loadSummary();
    loadTable();
    loadLog();
});

async function doManualFetch() {
    if (!fetchDate.value) return;
    fetchLoading.value = true;
    fetchMessage.value = '';
    fetchStatus.value = 'idle';
    const csrfToken =
        (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)
            ?.content ?? '';
    try {
        const res = await fetch('/bandwidth/fetch', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({ date: fetchDate.value }),
        });
        const data = await res.json();
        fetchMessage.value = data.message;
        fetchStatus.value = res.ok || res.status === 206 ? 'success' : 'error';
        if (data.errors?.length)
            fetchMessage.value += '\n' + data.errors.join('\n');
        if (res.ok) {
            summaryData.value = null;
            tableData.value = [];
            logData.value = [];
            loadSummary();
            loadTable();
            loadLog();
        }
    } catch {
        fetchMessage.value = 'Gagal menghubungi server.';
        fetchStatus.value = 'error';
    } finally {
        fetchLoading.value = false;
    }
}

const COLORS = [
    '#003628',
    '#0ea5e9',
    '#f59e0b',
    '#8b5cf6',
    '#ef4444',
    '#10b981',
    '#f97316',
    '#6366f1',
    '#ec4899',
    '#14b8a6',
    '#a855f7',
    '#84cc16',
];
function seriesColor(i: number) {
    return COLORS[i % COLORS.length];
}

const CHART_W = 900,
    CHART_H = 320,
    CHART_PAD_L = 70,
    CHART_PAD_R = 20,
    CHART_PAD_T = 20,
    CHART_PAD_B = 40;

const chartMax = computed(() => {
    if (!summaryData.value) return 1;
    let max = 0;
    for (const s of summaryData.value.series)
        for (const v of s.data) if (v !== null && v > max) max = v;
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
function buildLinePath(data: (number | null)[], n: number) {
    let p = '',
        started = false;
    for (let i = 0; i < data.length; i++) {
        if (data[i] === null) {
            started = false;
            continue;
        }
        const x = toX(i, n),
            y = toY(data[i]!);
        p += started ? ` L ${x} ${y}` : `M ${x} ${y}`;
        started = true;
    }
    return p;
}
function buildAreaPath(data: (number | null)[], n: number) {
    const bot = CHART_H - CHART_PAD_B;
    let p = '',
        seg = -1;
    for (let i = 0; i <= data.length; i++) {
        const v = i < data.length ? data[i] : null;
        if (v !== null && seg === -1) {
            seg = i;
            p += `M ${toX(i, n)} ${bot} L ${toX(i, n)} ${toY(v)}`;
        } else if (v !== null) {
            p += ` L ${toX(i, n)} ${toY(v)}`;
        } else if (seg !== -1) {
            p += ` L ${toX(i - 1, n)} ${bot} Z`;
            seg = -1;
        }
    }
    return p;
}

const yTicks = computed(() => {
    const max = chartMax.value,
        step = Math.ceil(max / 5 / 10) * 10 || 10,
        ticks = [];
    for (let v = 0; v <= max * 1.1; v += step) ticks.push(v);
    return ticks;
});
const xTickIndices = computed(() => {
    const dates = summaryData.value?.dates ?? [],
        n = dates.length;
    if (!n) return [];
    const step = Math.max(1, Math.ceil(n / 10)),
        idx: number[] = [];
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
        svg += `<line x1="${CHART_PAD_L}" y1="${y}" x2="${CHART_W - CHART_PAD_R}" y2="${y}" stroke="#e2e8f0" stroke-width="1"/>`;
        svg += `<text x="${CHART_PAD_L - 6}" y="${y + 4}" text-anchor="end" font-size="9" fill="#94a3b8">${tick}</text>`;
    }
    for (const idx of xTickIndices.value) {
        svg += `<text x="${toX(idx, n)}" y="${CHART_H - CHART_PAD_B + 16}" text-anchor="middle" font-size="9" fill="#94a3b8">${sd.dates[idx]?.slice(5) ?? ''}</text>`;
    }
    svg += `<line x1="${CHART_PAD_L}" y1="${CHART_PAD_T}" x2="${CHART_PAD_L}" y2="${CHART_H - CHART_PAD_B}" stroke="#cbd5e1" stroke-width="1"/>`;
    svg += `<line x1="${CHART_PAD_L}" y1="${CHART_H - CHART_PAD_B}" x2="${CHART_W - CHART_PAD_R}" y2="${CHART_H - CHART_PAD_B}" stroke="#cbd5e1" stroke-width="1"/>`;
    for (let si = 0; si < sd.series.length; si++) {
        const d = buildAreaPath(sd.series[si].data, n);
        if (d)
            svg += `<path d="${d}" fill="${seriesColor(si)}" fill-opacity="0.07"/>`;
    }
    for (let si = 0; si < sd.series.length; si++) {
        const d = buildLinePath(sd.series[si].data, n);
        if (d)
            svg += `<path d="${d}" stroke="${seriesColor(si)}" stroke-width="1.8" fill="none" stroke-linejoin="round" stroke-linecap="round"/>`;
    }
    for (let si = 0; si < sd.series.length; si++) {
        for (let di = 0; di < sd.series[si].data.length; di++) {
            const v = sd.series[si].data[di];
            if (v === null) continue;
            svg += `<circle cx="${toX(di, n)}" cy="${toY(v)}" r="3" fill="${seriesColor(si)}" stroke="white" stroke-width="1.5"><title>${sd.dates[di]} ? ${sd.series[si].name}: ${v} Mbps</title></circle>`;
        }
    }
    svg += '</svg>';
    return svg;
});

function exportUrl() {
    const p = new URLSearchParams({
        from: filterFrom.value,
        to: filterTo.value,
    });
    return '/bandwidth/export?' + p.toString();
}

function logStatusConf(status: string) {
    if (status === 'success')
        return {
            icon: CheckCircle2,
            color: 'text-emerald-600',
            bg: 'bg-emerald-50 border-emerald-100',
        };
    if (status === 'partial')
        return {
            icon: AlertTriangle,
            color: 'text-amber-600',
            bg: 'bg-amber-50 border-amber-100',
        };
    return {
        icon: XCircle,
        color: 'text-rose-600',
        bg: 'bg-rose-50 border-rose-100',
    };
}

function sortIcon(col: string) {
    if (sortCol.value !== col) return ChevronsUpDown;
    return sortDir.value === 'asc' ? ChevronUp : ChevronDown;
}
</script>
<template>
    <Head title="Bandwidth Usage" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Report', href: '/reports' },
            { title: 'Bandwidth Usage', href: '/bandwidth' },
        ]"
    >
        <div class="mx-auto max-w-7xl space-y-5 py-2">
            <!-- HEADER -->
            <!-- Click-outside backdrop to close flyouts -->
            <div
                v-if="openFlyout"
                class="fixed inset-0 z-40"
                @click="closeFlyout"
            ></div>

            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1
                        class="flex items-center gap-2 text-xl font-black tracking-tight text-slate-900"
                    >
                        <Wifi class="size-5 text-[#003628]" /> Bandwidth Usage
                    </h1>
                    <p
                        class="mt-0.5 text-[10px] font-bold tracking-widest text-slate-400 uppercase"
                    >
                        PRTG Network Monitor
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <!-- Filter button + flyout -->
                    <div class="relative z-50">
                        <button
                            type="button"
                            class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white shadow-sm transition-all hover:border-slate-300 hover:bg-slate-50"
                            :class="
                                openFlyout === 'filter'
                                    ? 'border-slate-300 bg-slate-100'
                                    : ''
                            "
                            title="Filter Tanggal"
                            @click.stop="toggleFlyout('filter')"
                        >
                            <Filter class="size-4 text-slate-600" />
                        </button>
                        <div
                            v-if="openFlyout === 'filter'"
                            class="absolute top-full right-0 mt-2 w-72 space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl"
                            @click.stop
                        >
                            <p
                                class="flex items-center gap-1.5 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                            >
                                <Filter class="size-3" /> Rentang Tanggal
                            </p>
                            <div class="flex items-center gap-2">
                                <input
                                    v-model="filterFrom"
                                    type="date"
                                    :max="filterTo"
                                    class="h-9 flex-1 rounded-xl border border-slate-200 bg-slate-50 px-3 text-[11px] font-bold text-slate-700 focus:ring-2 focus:ring-[#003628]/20 focus:outline-none"
                                />
                                <span
                                    class="shrink-0 text-xs font-bold text-slate-300"
                                    >-</span
                                >
                                <input
                                    v-model="filterTo"
                                    type="date"
                                    :min="filterFrom"
                                    :max="formatDate(new Date())"
                                    class="h-9 flex-1 rounded-xl border border-slate-200 bg-slate-50 px-3 text-[11px] font-bold text-slate-700 focus:ring-2 focus:ring-[#003628]/20 focus:outline-none"
                                />
                            </div>
                            <button
                                type="button"
                                class="flex h-9 w-full items-center justify-center gap-2 rounded-xl bg-[#003628] text-[10px] font-black tracking-widest text-white uppercase shadow-md shadow-[#003628]/20 transition-all hover:brightness-110 active:scale-95"
                                @click="
                                    applyFilter();
                                    closeFlyout();
                                "
                            >
                                <RefreshCw class="size-3.5" /> Apply Filter
                            </button>
                        </div>
                    </div>

                    <!-- Export button + flyout -->
                    <div class="relative z-50">
                        <button
                            type="button"
                            class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white shadow-sm transition-all hover:border-slate-300 hover:bg-slate-50"
                            :class="
                                openFlyout === 'export'
                                    ? 'border-slate-300 bg-slate-100'
                                    : ''
                            "
                            title="Export Excel"
                            @click.stop="toggleFlyout('export')"
                        >
                            <FileDown class="size-4 text-slate-600" />
                        </button>
                        <div
                            v-if="openFlyout === 'export'"
                            class="absolute top-full right-0 mt-2 w-64 space-y-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl"
                            @click.stop
                        >
                            <p
                                class="flex items-center gap-1.5 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                            >
                                <FileDown class="size-3" /> Export Data
                            </p>
                            <p class="text-[10px] font-medium text-slate-400">
                                {{ filterFrom }} sampai {{ filterTo }}
                            </p>
                            <a
                                :href="exportUrl()"
                                class="flex h-9 w-full items-center justify-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 text-[10px] font-black tracking-widest text-emerald-700 uppercase transition-all hover:bg-emerald-100 active:scale-95"
                                @click="closeFlyout"
                            >
                                <FileDown class="size-3.5" /> Export Excel
                            </a>
                        </div>
                    </div>

                    <!-- Fetch button + flyout -->
                    <div class="relative z-50">
                        <button
                            type="button"
                            class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white shadow-sm transition-all hover:border-slate-300 hover:bg-slate-50"
                            :class="
                                openFlyout === 'fetch'
                                    ? 'border-slate-300 bg-slate-100'
                                    : ''
                            "
                            title="Tarik Data PRTG"
                            @click.stop="toggleFlyout('fetch')"
                        >
                            <Play class="size-4 text-slate-600" />
                        </button>
                        <div
                            v-if="openFlyout === 'fetch'"
                            class="absolute top-full right-0 mt-2 w-72 space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl"
                            @click.stop
                        >
                            <p
                                class="flex items-center gap-1.5 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                            >
                                <Play class="size-3" /> Tarik Data PRTG
                            </p>
                            <p
                                class="text-[10px] leading-relaxed font-medium text-slate-400"
                            >
                                Pilih tanggal lalu klik Fetch untuk menarik data
                                dari PRTG ke database.
                            </p>
                            <div class="flex items-center gap-2">
                                <input
                                    v-model="fetchDate"
                                    type="date"
                                    :max="formatDate(new Date())"
                                    class="h-9 flex-1 rounded-xl border border-slate-200 bg-slate-50 px-3 text-[11px] font-bold text-slate-700 focus:ring-2 focus:ring-[#003628]/20 focus:outline-none"
                                />
                                <button
                                    type="button"
                                    :disabled="fetchLoading"
                                    class="flex h-9 shrink-0 items-center gap-1.5 rounded-xl bg-[#003628] px-4 text-[10px] font-black tracking-widest text-white uppercase shadow-md shadow-[#003628]/20 transition-all hover:brightness-110 active:scale-95 disabled:opacity-50"
                                    @click="doManualFetch"
                                >
                                    <Loader2
                                        v-if="fetchLoading"
                                        class="size-3 animate-spin"
                                    />
                                    <Play v-else class="size-3" />
                                    Fetch
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FETCH MESSAGE -->
            <div
                v-if="fetchMessage"
                class="flex items-start gap-2.5 rounded-xl border px-4 py-3 text-[11px] font-bold whitespace-pre-line shadow-sm"
                :class="
                    fetchStatus === 'success'
                        ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                        : 'border-rose-200 bg-rose-50 text-rose-700'
                "
            >
                <CheckCircle2
                    v-if="fetchStatus === 'success'"
                    class="mt-0.5 size-4 shrink-0"
                />
                <XCircle v-else class="mt-0.5 size-4 shrink-0" />
                <span>{{ fetchMessage }}</span>
            </div>

            <!-- TOP: 3 LOCATION CARDS -->
            <div v-if="loadingSummary" class="grid grid-cols-3 gap-4">
                <div
                    v-for="n in 3"
                    :key="n"
                    class="animate-pulse space-y-3 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm"
                >
                    <div class="h-5 w-2/3 rounded-full bg-slate-100"></div>
                    <div class="h-px bg-slate-100"></div>
                    <div class="h-4 w-full rounded-full bg-slate-100"></div>
                    <div class="h-4 w-5/6 rounded-full bg-slate-100"></div>
                    <div class="h-4 w-4/6 rounded-full bg-slate-100"></div>
                </div>
            </div>
            <div
                v-else-if="
                    !summaryData ||
                    !summaryData.cards ||
                    summaryData.cards.length === 0
                "
                class="grid grid-cols-3 gap-4"
            >
                <div
                    class="col-span-3 rounded-2xl border border-slate-100 bg-white py-12 text-center shadow-sm"
                >
                    <MapPin class="mx-auto mb-2 size-8 text-slate-200" />
                    <p
                        class="text-[10px] font-black tracking-widest text-slate-300 uppercase"
                    >
                        Belum ada data lokasi
                    </p>
                </div>
            </div>
            <div v-else class="grid grid-cols-3 gap-4">
                <div
                    v-for="card in summaryData.cards"
                    :key="card.location"
                    class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm"
                >
                    <div
                        class="flex items-center justify-between px-5 pt-4 pb-3"
                    >
                        <span
                            class="text-base font-black tracking-tight text-slate-900"
                            >{{ card.location }}</span
                        >
                        <MapPin class="size-4 shrink-0 text-slate-300" />
                    </div>
                    <div class="mx-5 h-px bg-slate-100"></div>
                    <div class="px-5 py-3">
                        <table class="w-full">
                            <thead>
                                <tr class="bg-slate-50">
                                    <th
                                        class="rounded-l-lg py-2 pr-3 text-left text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                    >
                                        Provider / Desc
                                    </th>
                                    <th
                                        class="px-2 py-2 text-right text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                    >
                                        AVG
                                    </th>
                                    <th
                                        class="rounded-r-lg py-2 pl-2 text-right text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                    >
                                        MAX
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <tr
                                    v-for="s in card.series"
                                    :key="s.name"
                                    class="transition-colors hover:bg-slate-50/50"
                                >
                                    <td
                                        class="max-w-[120px] truncate py-2 pr-3 text-[10px] font-bold text-slate-600"
                                    >
                                        {{ s.name }}
                                    </td>
                                    <td
                                        class="px-2 py-2 text-right text-[11px] font-black text-slate-700 tabular-nums"
                                    >
                                        {{
                                            s.avg_mbps !== null
                                                ? s.avg_mbps
                                                : '-'
                                        }}
                                    </td>
                                    <td
                                        class="py-2 pl-2 text-right text-[11px] font-black tabular-nums"
                                        :class="
                                            s.max_mbps > 50
                                                ? 'text-emerald-600'
                                                : s.max_mbps > 20
                                                  ? 'text-amber-600'
                                                  : 'text-rose-600'
                                        "
                                    >
                                        {{
                                            s.max_mbps !== null
                                                ? s.max_mbps
                                                : '-'
                                        }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- MIDDLE: Chart + Log -->
            <div class="grid grid-cols-3 gap-4">
                <!-- Chart col-span-2 -->
                <div
                    class="col-span-2 overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm"
                >
                    <div
                        class="flex items-center justify-between border-b border-slate-100 px-5 py-4"
                    >
                        <div class="flex items-center gap-2">
                            <BarChart3 class="size-4 text-[#003628]" />
                            <p class="text-sm font-black text-slate-800">
                                Bandwidth Usage (Mbps)
                            </p>
                        </div>
                        <span
                            class="text-[10px] font-bold text-slate-400 tabular-nums"
                            >{{ filterFrom }} - {{ filterTo }}</span
                        >
                    </div>
                    <div v-if="loadingSummary" class="space-y-3 p-5">
                        <div
                            class="h-64 animate-pulse rounded-xl bg-slate-100"
                        ></div>
                    </div>
                    <div
                        v-else-if="
                            !summaryData || summaryData.dates.length === 0
                        "
                        class="py-20 text-center"
                    >
                        <BarChart3
                            class="mx-auto mb-3 size-10 text-slate-200"
                        />
                        <p
                            class="text-[11px] font-black tracking-widest text-slate-300 uppercase"
                        >
                            Belum ada data
                        </p>
                    </div>
                    <div v-else class="p-5">
                        <div v-html="chartSvg" class="w-full"></div>
                    </div>
                    <div
                        v-if="
                            summaryData &&
                            summaryData.series &&
                            summaryData.series.length
                        "
                        class="flex flex-wrap gap-x-4 gap-y-2 border-t border-slate-50 px-5 pt-2 pb-4"
                    >
                        <div
                            v-for="(s, si) in summaryData.series"
                            :key="s.name"
                            class="flex items-center gap-1.5"
                        >
                            <div
                                class="h-1 w-4 shrink-0 rounded-full"
                                :style="{ backgroundColor: seriesColor(si) }"
                            ></div>
                            <span class="text-[9px] font-bold text-slate-500">{{
                                s.name
                            }}</span>
                        </div>
                    </div>
                </div>

                <!-- Fetch Log col-span-1 -->
                <div
                    class="col-span-1 flex flex-col overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm"
                >
                    <div
                        class="flex shrink-0 items-center justify-between border-b border-slate-100 px-5 py-4"
                    >
                        <div class="flex items-center gap-2">
                            <FileText class="size-4 text-[#003628]" />
                            <p class="text-sm font-black text-slate-800">
                                Fetch Log
                            </p>
                        </div>
                        <button
                            type="button"
                            class="flex h-7 w-7 items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-slate-100"
                            @click="loadLog"
                        >
                            <RefreshCw
                                class="size-3.5"
                                :class="{ 'animate-spin': loadingLog }"
                            />
                        </button>
                    </div>
                    <div v-if="loadingLog" class="space-y-2 p-4">
                        <div
                            v-for="n in 4"
                            :key="n"
                            class="h-12 animate-pulse rounded-xl bg-slate-100"
                        ></div>
                    </div>
                    <div
                        v-else-if="logData.length === 0"
                        class="flex flex-1 flex-col items-center justify-center py-10 text-center"
                    >
                        <Clock class="mx-auto mb-2 size-7 text-slate-200" />
                        <p
                            class="text-[10px] font-black tracking-widest text-slate-300 uppercase"
                        >
                            Belum ada log
                        </p>
                    </div>
                    <div v-else class="flex min-h-0 flex-1 flex-col">
                        <div
                            class="max-h-[420px] divide-y divide-slate-50 overflow-y-auto"
                        >
                            <div
                                v-for="log in logData.slice(0, 15)"
                                :key="log.id"
                                class="px-4 py-3 transition-colors hover:bg-slate-50/50"
                            >
                                <div class="flex flex-wrap items-center gap-2">
                                    <div
                                        class="flex shrink-0 items-center gap-1 rounded-lg border px-2 py-0.5 text-[8px] font-black tracking-widest uppercase"
                                        :class="logStatusConf(log.status).bg"
                                    >
                                        <component
                                            :is="logStatusConf(log.status).icon"
                                            class="size-3"
                                            :class="
                                                logStatusConf(log.status).color
                                            "
                                        />
                                        <span
                                            :class="
                                                logStatusConf(log.status).color
                                            "
                                            >{{ log.status }}</span
                                        >
                                    </div>
                                    <span
                                        class="text-[11px] font-black text-slate-800 tabular-nums"
                                        >{{ log.fetch_date }}</span
                                    >
                                    <span
                                        class="text-[10px] font-bold text-slate-400"
                                        >{{ log.sensors_ok }} OK ?
                                        {{ log.sensors_fail }} gagal</span
                                    >
                                    <span
                                        v-if="log.is_manual"
                                        class="rounded-full border border-violet-100 bg-violet-50 px-2 py-0.5 text-[8px] font-black tracking-widest text-violet-600 uppercase"
                                        >Manual</span
                                    >
                                </div>
                                <div
                                    class="mt-1 flex items-center justify-between"
                                >
                                    <span
                                        class="text-[9px] font-bold text-slate-500"
                                        >{{ log.triggered_by }}</span
                                    >
                                    <span
                                        class="text-[9px] text-slate-300 tabular-nums"
                                        >{{ log.created_at }}</span
                                    >
                                </div>
                            </div>
                        </div>
                        <div
                            v-if="logData.length > 15"
                            class="shrink-0 border-t border-slate-100 px-4 py-3"
                        >
                            <button
                                type="button"
                                class="flex h-8 w-full items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 text-[10px] font-black tracking-widest text-slate-600 uppercase transition-all hover:bg-slate-100"
                                @click="showLogModal = true"
                            >
                                <FileText class="size-3.5" /> Show More ({{
                                    logData.length
                                }}
                                total)
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- BOTTOM: Sortable Table -->
            <div
                class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm"
            >
                <div
                    class="flex items-center justify-between border-b border-slate-100 px-5 py-4"
                >
                    <div class="flex items-center gap-2">
                        <Table2 class="size-4 text-[#003628]" />
                        <div>
                            <p class="text-sm font-black text-slate-800">
                                Detail Data
                            </p>
                            <p class="text-[10px] font-bold text-slate-400">
                                {{ filterFrom }} - {{ filterTo }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-[10px] font-bold text-slate-400"
                            >Per halaman</span
                        >
                        <select
                            v-model="tablePerPage"
                            class="h-7 rounded-lg border border-slate-200 bg-slate-50 px-2 text-[10px] font-bold text-slate-600 focus:outline-none"
                            @change="tablePage = 1"
                        >
                            <option :value="10">10</option>
                            <option :value="15">15</option>
                            <option :value="25">25</option>
                            <option :value="50">50</option>
                        </select>
                    </div>
                </div>
                <div v-if="loadingTable" class="space-y-2 p-4">
                    <div
                        v-for="n in 5"
                        :key="n"
                        class="h-11 animate-pulse rounded-xl bg-slate-100"
                    ></div>
                </div>
                <div
                    v-else-if="tableData.length === 0"
                    class="py-16 text-center"
                >
                    <Table2 class="mx-auto mb-2 size-8 text-slate-200" />
                    <p
                        class="text-[10px] font-black tracking-widest text-slate-300 uppercase"
                    >
                        Tidak ada data
                    </p>
                </div>
                <div v-else>
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50">
                                <th
                                    class="cursor-pointer px-5 py-3 text-left transition-colors select-none hover:bg-slate-100"
                                    @click="toggleSort('date')"
                                >
                                    <span
                                        class="flex items-center gap-1 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                    >
                                        Tanggal
                                        <component
                                            :is="sortIcon('date')"
                                            class="size-3 shrink-0"
                                        />
                                    </span>
                                </th>
                                <th
                                    class="cursor-pointer px-5 py-3 text-left transition-colors select-none hover:bg-slate-100"
                                    @click="toggleSort('location')"
                                >
                                    <span
                                        class="flex items-center gap-1 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                    >
                                        Area
                                        <component
                                            :is="sortIcon('location')"
                                            class="size-3 shrink-0"
                                        />
                                    </span>
                                </th>
                                <th
                                    class="cursor-pointer px-5 py-3 text-left transition-colors select-none hover:bg-slate-100"
                                    @click="toggleSort('provider')"
                                >
                                    <span
                                        class="flex items-center gap-1 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                    >
                                        Provider
                                        <component
                                            :is="sortIcon('provider')"
                                            class="size-3 shrink-0"
                                        />
                                    </span>
                                </th>
                                <th
                                    class="cursor-pointer px-5 py-3 text-right transition-colors select-none hover:bg-slate-100"
                                    @click="toggleSort('download')"
                                >
                                    <span
                                        class="flex items-center justify-end gap-1 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                    >
                                        <Download class="size-3" /> Download
                                        <component
                                            :is="sortIcon('download')"
                                            class="size-3 shrink-0"
                                        />
                                    </span>
                                </th>
                                <th
                                    class="cursor-pointer px-5 py-3 text-right transition-colors select-none hover:bg-slate-100"
                                    @click="toggleSort('upload')"
                                >
                                    <span
                                        class="flex items-center justify-end gap-1 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                    >
                                        <Upload class="size-3" /> Upload
                                        <component
                                            :is="sortIcon('upload')"
                                            class="size-3 shrink-0"
                                        />
                                    </span>
                                </th>
                                <th class="px-5 py-3 text-left">
                                    <span
                                        class="text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                        >Remark</span
                                    >
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <tr
                                v-for="row in tablePaginated"
                                :key="`${row.date}-${row.location}-${row.provider}`"
                                class="transition-colors hover:bg-slate-50/60"
                            >
                                <td
                                    class="px-5 py-3 text-[11px] font-bold text-slate-600 tabular-nums"
                                >
                                    {{ row.date }}
                                </td>
                                <td class="px-5 py-3">
                                    <span
                                        class="flex items-center gap-1.5 text-[11px] font-bold text-slate-800"
                                    >
                                        <MapPin
                                            class="size-3 shrink-0 text-slate-300"
                                        />
                                        {{ row.location }}
                                    </span>
                                </td>
                                <td class="px-5 py-3">
                                    <span
                                        class="rounded-full border border-slate-100 bg-slate-50 px-2 py-0.5 text-[9px] font-black tracking-widest text-slate-600 uppercase"
                                        >{{ row.provider }}</span
                                    >
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <span
                                        v-if="row.download !== null"
                                        class="text-[12px] font-black tabular-nums"
                                        :class="
                                            row.download > 50
                                                ? 'text-emerald-600'
                                                : row.download > 20
                                                  ? 'text-amber-600'
                                                  : 'text-rose-600'
                                        "
                                    >
                                        {{ row.download }}
                                        <span
                                            class="text-[9px] font-bold text-slate-400"
                                            >Mbps</span
                                        >
                                    </span>
                                    <span v-else class="text-slate-300">-</span>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <span
                                        v-if="row.upload !== null"
                                        class="text-[12px] font-black tabular-nums"
                                        :class="
                                            row.upload > 50
                                                ? 'text-emerald-600'
                                                : row.upload > 20
                                                  ? 'text-amber-600'
                                                  : 'text-rose-600'
                                        "
                                    >
                                        {{ row.upload }}
                                        <span
                                            class="text-[9px] font-bold text-slate-400"
                                            >Mbps</span
                                        >
                                    </span>
                                    <span v-else class="text-slate-300">-</span>
                                </td>
                                <td class="px-5 py-3">
                                    <span
                                        class="max-w-[200px] truncate text-[10px] text-slate-500"
                                        :title="row.remark"
                                        >{{ row.remark ?? '—' }}</span
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div
                        class="flex items-center justify-between border-t border-slate-100 bg-slate-50/40 px-5 py-3"
                    >
                        <span class="text-[10px] font-bold text-slate-400"
                            >{{ (tablePage - 1) * tablePerPage + 1 }}-{{
                                Math.min(
                                    tablePage * tablePerPage,
                                    tableData.length,
                                )
                            }}
                            dari {{ tableData.length }} baris</span
                        >
                        <div class="flex items-center gap-1">
                            <button
                                type="button"
                                :disabled="tablePage === 1"
                                class="flex h-7 w-7 items-center justify-center rounded-lg text-[13px] font-black text-slate-600 transition-all hover:bg-slate-200 disabled:opacity-30"
                                @click="tablePage--"
                            >
                                &#8249;
                            </button>
                            <template
                                v-for="p in tablePageNumbers"
                                :key="String(p)"
                            >
                                <span
                                    v-if="p === '...'"
                                    class="flex h-7 w-5 items-center justify-center text-[10px] text-slate-300"
                                    >-</span
                                >
                                <button
                                    v-else
                                    type="button"
                                    class="h-7 w-7 rounded-lg text-[10px] font-black transition-all"
                                    :class="
                                        tablePage === p
                                            ? 'bg-[#003628] text-white shadow-sm'
                                            : 'text-slate-600 hover:bg-slate-100'
                                    "
                                    @click="tablePage = p as number"
                                >
                                    {{ p }}
                                </button>
                            </template>
                            <button
                                type="button"
                                :disabled="tablePage === tableTotalPages"
                                class="flex h-7 w-7 items-center justify-center rounded-lg text-[13px] font-black text-slate-600 transition-all hover:bg-slate-200 disabled:opacity-30"
                                @click="tablePage++"
                            >
                                &#8250;
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>

    <!-- LOG MODAL -->
    <Teleport to="body">
        <div
            v-if="showLogModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4 backdrop-blur-sm"
            @click.self="showLogModal = false"
        >
            <div
                class="flex max-h-[85vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl"
            >
                <!-- Modal header -->
                <div
                    class="flex shrink-0 items-center justify-between border-b border-slate-100 px-6 py-4"
                >
                    <div class="flex items-center gap-2">
                        <FileText class="size-4 text-[#003628]" />
                        <h3 class="text-sm font-black text-slate-800">
                            Fetch Log History
                        </h3>
                        <span
                            class="rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-black text-slate-500"
                            >{{ filteredLogData.length }} entri</span
                        >
                    </div>
                    <button
                        type="button"
                        class="flex h-8 w-8 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-400 transition-all hover:border-slate-300 hover:text-slate-700"
                        @click="showLogModal = false"
                    >
                        <X class="size-4" />
                    </button>
                </div>
                <!-- Search + filter -->
                <div
                    class="flex shrink-0 items-center gap-2 border-b border-slate-100 px-6 py-3"
                >
                    <div class="relative flex-1">
                        <Search
                            class="absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-slate-300"
                        />
                        <input
                            v-model="logSearch"
                            type="text"
                            placeholder="Cari tanggal, user, notes..."
                            class="h-9 w-full rounded-xl border border-slate-200 bg-slate-50 pr-4 pl-9 text-[11px] font-bold text-slate-700 placeholder:text-slate-300 focus:ring-2 focus:ring-[#003628]/20 focus:outline-none"
                        />
                    </div>
                    <select
                        v-model="logFilterStatus"
                        class="h-9 rounded-xl border border-slate-200 bg-slate-50 px-3 text-[11px] font-bold text-slate-700 focus:ring-2 focus:ring-[#003628]/20 focus:outline-none"
                    >
                        <option value="">Semua Status</option>
                        <option value="success">Success</option>
                        <option value="partial">Partial</option>
                        <option value="failed">Failed</option>
                    </select>
                </div>
                <!-- Log list -->
                <div class="flex-1 divide-y divide-slate-50 overflow-y-auto">
                    <div
                        v-if="filteredLogData.length === 0"
                        class="py-16 text-center"
                    >
                        <Clock class="mx-auto mb-2 size-7 text-slate-200" />
                        <p
                            class="text-[10px] font-black tracking-widest text-slate-300 uppercase"
                        >
                            Tidak ada hasil
                        </p>
                    </div>
                    <div
                        v-for="log in filteredLogData"
                        :key="log.id"
                        class="px-6 py-3 transition-colors hover:bg-slate-50/50"
                    >
                        <div class="flex flex-wrap items-center gap-2">
                            <div
                                class="flex shrink-0 items-center gap-1 rounded-lg border px-2 py-0.5 text-[8px] font-black tracking-widest uppercase"
                                :class="logStatusConf(log.status).bg"
                            >
                                <component
                                    :is="logStatusConf(log.status).icon"
                                    class="size-3"
                                    :class="logStatusConf(log.status).color"
                                />
                                <span
                                    :class="logStatusConf(log.status).color"
                                    >{{ log.status }}</span
                                >
                            </div>
                            <span
                                class="text-[12px] font-black text-slate-800 tabular-nums"
                                >{{ log.fetch_date }}</span
                            >
                            <span class="text-[10px] font-bold text-slate-400"
                                >{{ log.sensors_ok }} OK ?
                                {{ log.sensors_fail }} gagal</span
                            >
                            <span
                                v-if="log.is_manual"
                                class="rounded-full border border-violet-100 bg-violet-50 px-2 py-0.5 text-[8px] font-black tracking-widest text-violet-600 uppercase"
                                >Manual</span
                            >
                        </div>
                        <div class="mt-1 flex items-center justify-between">
                            <span class="text-[9px] font-bold text-slate-500">{{
                                log.triggered_by
                            }}</span>
                            <span
                                class="text-[9px] text-slate-300 tabular-nums"
                                >{{ log.created_at }}</span
                            >
                        </div>
                        <p
                            v-if="log.notes"
                            class="mt-1 truncate font-mono text-[9px] text-slate-400"
                        >
                            {{ log.notes }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
