<script setup lang="ts">
import { ref, computed, watch, onMounted } from "vue";
import {
    Activity, Loader2, CheckCircle2, XCircle, AlertTriangle,
    Clock, FileText, Search, Table2, RefreshCw,
    ChevronUp, ChevronDown, ChevronsUpDown, Cpu, HardDrive,
    Eye, EyeOff,
} from "lucide-vue-next";

const props = defineProps<{
    locations: string[];
    filterFrom: string;
    filterTo: string;
    applyTrigger: number;
    activeMetric: "cpu" | "memory" | "disk";
    filterLocation: string;
    searchQuery: string;
}>();

const loading       = ref(false);
const loadingLogs   = ref(false);
const serverData    = ref<any>(null);
const logData       = ref<any[]>([]);

const sortCol = ref("location");
const sortDir = ref<"asc"|"desc">("asc");

function toggleSort(col: string) {
    if (sortCol.value === col) sortDir.value = sortDir.value === "asc" ? "desc" : "asc";
    else { sortCol.value = col; sortDir.value = "asc"; }
}
function sortIcon(col: string) {
    if (sortCol.value !== col) return ChevronsUpDown;
    return sortDir.value === "asc" ? ChevronUp : ChevronDown;
}

const ID_MONTHS = ["Jan","Feb","Mar","Apr","Mei","Jun","Jul","Agu","Sep","Okt","Nov","Des"];

function dateLabel(dateStr: string) {
    const d = new Date(dateStr + "T00:00:00");
    return d.getDate() + " " + ID_MONTHS[d.getMonth()];
}

function fmtPct(val: number|null) {
    if (val === null) return "---";
    if (val > 0 && val < 1) return "<1%";
    return Math.round(val) + "%";
}

function metricColor(val: number|null, type: 'cpu'|'memory') {
    if (val === null) return "text-slate-300";
    if (type === 'cpu') {
        if (val >= 90) return "text-rose-600 font-black";
        if (val >= 70) return "text-amber-600 font-bold";
        return "text-emerald-600";
    }
    if (type === 'memory') {
        if (val >= 90) return "text-rose-600 font-black";
        if (val >= 80) return "text-amber-600 font-bold";
        return "text-emerald-600";
    }
    return "text-slate-600";
}

function metricBadge(val: number|null, type: 'cpu'|'memory') {
    if (val === null) return "bg-slate-100 text-slate-400";
    if (type === 'cpu') {
        if (val >= 90) return "bg-rose-50 text-rose-700";
        if (val >= 70) return "bg-amber-50 text-amber-700";
        return "bg-emerald-50 text-emerald-700";
    }
    if (type === 'memory') {
        if (val >= 90) return "bg-rose-50 text-rose-700";
        if (val >= 80) return "bg-amber-50 text-amber-700";
        return "bg-emerald-50 text-emerald-700";
    }
    return "bg-slate-100 text-slate-600";
}

const datColStyle = computed(() => {
    const n = serverData.value?.daily_dates?.length ?? 1;
    const w = n <= 7 ? 52 : n <= 14 ? 44 : n <= 21 ? 40 : 36;
    return { minWidth: w + "px", width: w + "px" };
});

/** HDD field is pipe-separated text; tolerate legacy scalar from API */
function diskChunks(val: string | number | null | undefined): string[] {
    if (val === null || val === undefined) return [];
    const s = typeof val === 'string' ? val.trim() : String(val).trim();
    if (!s) return [];
    return s.split('|').map((x) => x.trim()).filter(Boolean);
}

const matrixTitle = computed(() => {
    if (props.activeMetric === 'cpu') return 'CPU Usage Matrix';
    if (props.activeMetric === 'memory') return 'RAM Usage Matrix';
    return 'Free Disk Matrix';
});

const scaleHint = computed(() => {
    if (props.activeMetric === 'disk') return 'SCALE: GB FREE / LABEL';
    return 'SCALE: PERCENTAGE';
});

const sortedDevices = computed(() => {
    if (!serverData.value?.devices) return [];
    const q = props.searchQuery.toLowerCase();
    let devs = serverData.value.devices.filter((d: any) => {
        if (q && !d.device_name.toLowerCase().includes(q) && !(d.ip_address ?? "").toLowerCase().includes(q)) return false;
        if (props.filterLocation && d.location !== props.filterLocation) return false;
        return true;
    });
    devs = [...devs].sort((a: any, b: any) => {
        let av = a[sortCol.value] ?? "";
        let bv = b[sortCol.value] ?? "";
        if (sortCol.value === "avg_cpu") { av = a.avg_cpu ?? -1; bv = b.avg_cpu ?? -1; }
        if (sortCol.value === "avg_memory") { av = a.avg_memory ?? -1; bv = b.avg_memory ?? -1; }
        if (typeof av === "number") return sortDir.value === "asc" ? av - bv : bv - av;
        return sortDir.value === "asc" ? String(av).localeCompare(String(bv)) : String(bv).localeCompare(String(av));
    });
    return devs;
});

async function loadData() {
    loading.value = true;
    try {
        const params = new URLSearchParams({ 
            from: props.filterFrom, 
            to: props.filterTo, 
            location: props.filterLocation
        });
        const res = await fetch("/server-operation/data?" + params);
        if (res.ok) serverData.value = await res.json();
    } finally { loading.value = false; }
}

async function loadLogs() {
    loadingLogs.value = true;
    try {
        const res = await fetch("/server-operation/logs");
        if (res.ok) logData.value = await res.json();
    } finally { loadingLogs.value = false; }
}

async function toggleExcluded(dev: any) {
    const csrf = (document.querySelector("meta[name=csrf-token]") as HTMLMetaElement)?.content ?? "";
    await fetch("/server-operation/excluded", {
        method: "PUT",
        headers: { "Content-Type":"application/json","Accept":"application/json","X-CSRF-TOKEN":csrf },
        body: JSON.stringify({ device_id: dev.id, is_excluded: !dev.is_excluded }),
    });
    await loadData();
}

function logStatusConf(status: string) {
    if (status === "success") return { label: "SUCCESS", color: "text-emerald-600", bg: "bg-emerald-50 border-emerald-100", icon: CheckCircle2 };
    if (status === "partial") return { label: "PARTIAL", color: "text-amber-600",   bg: "bg-amber-50 border-amber-100",   icon: AlertTriangle };
    return                           { label: "FAILED",  color: "text-rose-600",    bg: "bg-rose-50 border-rose-100",     icon: XCircle };
}

onMounted(() => {
    loadData();
    loadLogs();
});

watch(() => [props.applyTrigger, props.filterLocation], () => {
    loadData();
    loadLogs();
});

</script>

<template>
<div class="w-full min-w-0 pb-10 space-y-5">

    <!-- MATRIX TABLE -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100 flex-wrap gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="h-9 w-9 rounded-xl bg-[#003628]/5 flex items-center justify-center shrink-0">
                    <Table2 class="size-4 text-[#003628]" />
                </div>
                <div class="min-w-0">
                    <p class="text-[13px] font-black text-slate-800 tracking-tight">{{ matrixTitle }}</p>
                    <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-0.5">{{ sortedDevices.length }} unit server terdeteksi</p>
                </div>
            </div>
            <div class="px-3 py-1 rounded-lg bg-slate-50 border border-slate-100 text-[9px] font-black text-slate-400 tabular-nums uppercase tracking-widest">
                {{ scaleHint }}
            </div>
        </div>

        <div v-if="loading" class="flex items-center justify-center py-40">
            <Loader2 class="size-8 text-[#003628] animate-spin" />
        </div>

        <div v-else-if="!sortedDevices.length" class="py-20 text-center">
            <Search class="size-10 text-slate-100 mx-auto mb-4" />
            <p class="text-[11px] font-black text-slate-400 uppercase tracking-widest">Tidak ada server ditemukan</p>
        </div>

        <div v-else class="overflow-x-auto w-full relative">
            <table class="border-collapse text-left" style="width:max-content;min-width:100%">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100">
                        <!-- Frozen: Lokasi -->
                        <th class="sticky left-0 z-20 bg-slate-50 px-3 py-3 text-[9px] font-black uppercase tracking-widest text-slate-400 w-[110px] min-w-[110px] cursor-pointer hover:bg-slate-100"
                            @click="toggleSort('location')">
                            <div class="flex items-center gap-1.5">Lokasi <component :is="sortIcon('location')" class="size-3.5"/></div>
                        </th>
                        <!-- Frozen: Device -->
                        <th class="sticky left-[110px] z-20 bg-slate-50 px-3 py-3 text-[9px] font-black uppercase tracking-widest text-slate-400 w-[220px] min-w-[220px] cursor-pointer hover:bg-slate-100"
                            @click="toggleSort('device_name')">
                            <div class="flex items-center gap-1.5">Device <component :is="sortIcon('device_name')" class="size-3.5"/></div>
                        </th>
                        <!-- Frozen: Group -->
                        <th class="sticky left-[330px] z-20 bg-slate-50 px-3 py-3 text-[9px] font-black uppercase tracking-widest text-slate-400 w-[110px] min-w-[110px]">Group</th>
                        <!-- Frozen: Avg -->
                        <th class="sticky left-[440px] z-20 bg-slate-50 border-r border-slate-200 px-3 py-3 text-[9px] font-black uppercase tracking-widest text-slate-400 w-[72px] min-w-[72px] cursor-pointer hover:bg-slate-100"
                            style="box-shadow:2px 0 5px -1px rgba(0,0,0,0.08)"
                            @click="toggleSort(activeMetric === 'cpu' ? 'avg_cpu' : 'avg_memory')">
                            <div class="flex items-center gap-1.5">Avg <component :is="sortIcon(activeMetric === 'cpu' ? 'avg_cpu' : 'avg_memory')" class="size-3.5"/></div>
                        </th>
                        <!-- Daily date columns -->
                        <th v-for="d in (serverData?.daily_dates ?? [])" :key="d.date"
                            :style="datColStyle"
                            class="px-1 py-3 text-center text-[9px] font-black uppercase tracking-widest text-slate-400">
                            <span class="block">{{ new Date(d.date+'T00:00:00').getDate() }}</span>
                            <span class="block text-[8px] font-bold text-slate-300">{{ ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'][new Date(d.date+'T00:00:00').getMonth()] }}</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr v-for="dev in sortedDevices" :key="dev.id"
                        class="hover:bg-slate-50/40 transition-colors group"
                        :class="dev.is_excluded ? 'opacity-50' : ''">

                        <!-- Frozen: Lokasi -->
                        <td class="sticky left-0 z-10 bg-white px-3 py-3 text-[10px] font-bold text-slate-600 whitespace-nowrap group-hover:bg-slate-50/40">
                            {{ dev.location }}
                        </td>

                        <!-- Frozen: Device + action buttons -->
                        <td class="sticky left-[110px] z-10 bg-white px-3 py-2 group-hover:bg-slate-50/40">
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex-1 min-w-0">
                                    <p class="text-[11px] font-bold text-slate-800 truncate max-w-[150px]">{{ dev.device_name }}</p>
                                    <p class="text-[9px] font-mono text-slate-400">{{ dev.ip_address }}</p>
                                </div>
                                <!-- Icon buttons — visible on row hover -->
                                <div class="flex items-center gap-1 shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <button type="button"
                                        class="h-7 w-7 rounded-lg transition-all flex items-center justify-center"
                                        :class="dev.is_excluded ? 'bg-emerald-50 text-emerald-600 hover:bg-emerald-100' : 'bg-slate-50 text-slate-500 hover:bg-slate-100'"
                                        :title="dev.is_excluded ? 'Enable' : 'Disable'"
                                        @click="toggleExcluded(dev)">
                                        <component :is="dev.is_excluded ? Eye : EyeOff" class="size-3.5"/>
                                    </button>
                                </div>
                            </div>
                        </td>

                        <!-- Frozen: Group -->
                        <td class="sticky left-[330px] z-10 bg-white px-3 py-3 text-[10px] text-slate-400 whitespace-nowrap group-hover:bg-slate-50/40">
                            {{ dev.display_group }}
                        </td>

                        <!-- Frozen: Avg badge -->
                        <td class="sticky left-[440px] z-10 bg-white border-r border-slate-100 px-3 py-3 text-center group-hover:bg-slate-50/40"
                            style="box-shadow:2px 0 5px -1px rgba(0,0,0,0.08)">
                            <span v-if="activeMetric === 'cpu'"
                                class="px-2 py-1 rounded-lg text-[10px] font-black"
                                :class="metricBadge(dev.avg_cpu, 'cpu')">
                                {{ fmtPct(dev.avg_cpu) }}
                            </span>
                            <span v-else-if="activeMetric === 'memory'"
                                class="px-2 py-1 rounded-lg text-[10px] font-black"
                                :class="metricBadge(dev.avg_memory, 'memory')">
                                {{ fmtPct(dev.avg_memory) }}
                            </span>
                            <span v-else class="text-slate-300 text-[10px]">—</span>
                        </td>

                        <!-- Daily data cells -->
                        <td v-for="d in dev.daily" :key="d.date" :style="datColStyle" class="px-0.5 py-1.5 text-center border-b border-slate-50">
                            <template v-if="d.in_range">
                                <!-- CPU -->
                                <template v-if="activeMetric === 'cpu'">
                                    <span v-if="d.cpu !== null"
                                        class="block text-[8px] font-black tabular-nums leading-none"
                                        :class="d.cpu >= 90 ? 'text-rose-600' : d.cpu >= 70 ? 'text-amber-500' : 'text-emerald-600'"
                                        :title="d.date + ': ' + fmtPct(d.cpu)">{{ fmtPct(d.cpu) }}</span>
                                    <span v-else class="block text-[8px] font-bold text-slate-300 leading-none">—</span>
                                </template>
                                <!-- Memory -->
                                <template v-else-if="activeMetric === 'memory'">
                                    <span v-if="d.memory !== null"
                                        class="block text-[8px] font-black tabular-nums leading-none"
                                        :class="d.memory >= 90 ? 'text-rose-600' : d.memory >= 80 ? 'text-amber-500' : 'text-emerald-600'"
                                        :title="d.date + ': ' + fmtPct(d.memory)">{{ fmtPct(d.memory) }}</span>
                                    <span v-else class="block text-[8px] font-bold text-slate-300 leading-none">—</span>
                                </template>
                                <!-- Disk -->
                                <template v-else-if="activeMetric === 'disk'">
                                    <template v-if="diskChunks(d.disk).length">
                                        <div v-for="(chunk, ci) in diskChunks(d.disk)" :key="ci"
                                            class="text-[7px] font-black tabular-nums leading-tight text-slate-600 truncate"
                                            :title="chunk">{{ chunk }}</div>
                                    </template>
                                    <span v-else class="block text-[8px] font-bold text-slate-300 leading-none">—</span>
                                </template>
                            </template>
                            <span v-else class="block text-[8px] font-bold text-slate-200 leading-none">·</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- FETCH LOG -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
            <div class="flex items-center gap-2.5">
                <Clock class="size-4 text-[#003628]" />
                <p class="text-sm font-black text-slate-800">Fetch Log</p>
                <span class="px-2 py-0.5 rounded-full bg-slate-100 text-[9px] font-black text-slate-500">{{ logData.length }} entri</span>
            </div>
            <button type="button"
                class="text-[9px] font-black uppercase text-[#003628] hover:opacity-70 transition-all flex items-center gap-1.5"
                @click="loadLogs">
                <RefreshCw class="size-3" :class="{'animate-spin':loadingLogs}" /> Refresh
            </button>
        </div>

        <div v-if="!logData.length" class="py-10 text-center">
            <p class="text-[10px] font-black uppercase tracking-widest text-slate-300">Belum ada log penarikan data</p>
        </div>

        <div v-else class="divide-y divide-slate-50 max-h-64 overflow-y-auto">
            <div v-for="log in logData.slice(0, 30)" :key="log.id"
                class="flex items-center gap-3 px-5 py-3 hover:bg-slate-50/50 transition-colors flex-wrap">
                <!-- Status badge -->
                <div class="shrink-0 px-2 py-0.5 rounded-lg border text-[8px] font-black uppercase tracking-widest flex items-center gap-1"
                    :class="logStatusConf(log.status).bg">
                    <component :is="logStatusConf(log.status).icon" class="size-3" :class="logStatusConf(log.status).color"/>
                    <span :class="logStatusConf(log.status).color">{{ log.status }}</span>
                </div>
                <!-- Date -->
                <span class="text-[11px] font-black text-slate-800 tabular-nums">{{ log.fetch_date }}</span>
                <!-- Group -->
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-tight truncate max-w-[160px]">{{ log.group_name }}</span>
                <!-- Stats -->
                <span class="ml-auto flex items-center gap-2 shrink-0">
                    <span class="text-[10px] font-black text-emerald-600 tabular-nums">{{ log.devices_ok }} OK</span>
                    <span v-if="log.devices_fail > 0" class="text-[10px] font-black text-rose-500 tabular-nums">{{ log.devices_fail }} fail</span>
                </span>
                <!-- Manual + triggered by -->
                <span class="text-[9px] text-slate-400 whitespace-nowrap shrink-0">
                    <span v-if="log.is_manual" class="px-1.5 py-0.5 rounded-md bg-amber-50 border border-amber-100 text-[7px] font-black uppercase text-amber-600 mr-1">Manual</span>
                    {{ log.triggered_by }}
                </span>
                <!-- Timestamp -->
                <span class="text-[9px] text-slate-300 whitespace-nowrap shrink-0">{{ log.created_at }}</span>
            </div>
        </div>
    </div>

</div>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>
