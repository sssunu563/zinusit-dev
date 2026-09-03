<script setup lang="ts">
import { ref, computed, watch, onMounted } from "vue";
import {
    Activity, Loader2, CheckCircle2, XCircle, AlertTriangle,
    Clock, Server, Camera, Fingerprint, X, Save, WrenchIcon, Search,
    ChevronUp, ChevronDown, ChevronsUpDown, AlertCircle, Plus, EyeOff, Eye, FileText, Trash2,
} from "lucide-vue-next";

const props = defineProps<{
    deviceType: string;
    filterFrom: string;
    filterTo: string;
    applyTrigger: number;
    activeView: "uptime"|"record"|"maintenance";
    filterLocation: string;
    searchDevice: string;
}>();

const emit = defineEmits<{ (e: 'locations-loaded', locs: string[]): void }>();

const loading    = ref(false);
const uptimeData = ref<any>(null);
const logData    = ref<any[]>([]);
const filterLocationLocal = ref(props.filterLocation);
const searchDeviceLocal   = ref(props.searchDevice);
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

const showMaintenance = ref(false);
const maintDevice     = ref<any>(null);
const maintNote       = ref("");
const maintUntil      = ref("");
const maintSaving     = ref(false);

const maintLogs      = ref<any[]>([]);
const maintLoading   = ref(false);
const maintPage      = ref(1);
const MAINT_PER_PAGE = 15;
const maintPages     = computed(() => Math.max(1, Math.ceil(maintLogs.value.length / MAINT_PER_PAGE)));
const pagedMaintLogs = computed(() => maintLogs.value.slice((maintPage.value-1)*MAINT_PER_PAGE, maintPage.value*MAINT_PER_PAGE));
const showMaintForm   = ref(false);
const editingMaintLog = ref<any>(null);
const maintForm = ref({
    device_id: "",
    status: "open" as "open" | "closed",
    event_type: "maintenance",
    started_at: "",
    resolved_at: "",
    notes: ""
});

const savingMaintLog  = ref(false);

// NVR Duration Record
const nvrRecordData  = ref<any>(null);
const showNvrModal   = ref(false);
const nvrModalDevice = ref<any>(null);
const nvrModalMonth  = ref<any>(null);
const nvrForm        = ref({ check_date: '', last_record_date: '', notes: '' });
const nvrSaving      = ref(false);
const nvrDurationDays = computed(() => {
    if (!nvrForm.value.check_date || !nvrForm.value.last_record_date) return null;
    const c = new Date(nvrForm.value.check_date);
    const l = new Date(nvrForm.value.last_record_date);
    return Math.round((c.getTime() - l.getTime()) / 86400000);
});
const EVENT_LABELS: Record<string,string> = { maintenance:"Maintenance", restart:"Restart", down:"Down", auto_detected:"Auto Detected" };

const ID_MONTHS = ["Jan","Feb","Mar","Apr","Mei","Jun","Jul","Agu","Sep","Okt","Nov","Des"];
function dateLabel(dateStr: string) {
    const d = new Date(dateStr + "T00:00:00");
    return d.getDate() + " " + ID_MONTHS[d.getMonth()];
}
const displayPeriod = computed(() => {
    const f = new Date(props.filterFrom + "T00:00:00");
    const t = new Date(props.filterTo   + "T00:00:00");
    const fmt = (d: Date) => d.getDate() + " " + ID_MONTHS[d.getMonth()] + " " + d.getFullYear();
    return fmt(f) + " - " + fmt(t);
});

function uptimeColor(pct: number|null, inRange: boolean) {
    if (!inRange) return "bg-slate-50 text-slate-200";
    if (pct === null) return "bg-slate-100 text-slate-400";
    if (pct >= 99.5)  return "bg-emerald-50 text-emerald-700";
    if (pct >= 95)    return "bg-amber-50 text-amber-700";
    return "bg-rose-50 text-rose-700";
}
function uptimeBadge(pct: number|null) {
    if (pct === null) return "text-slate-300";
    if (pct >= 99.5)  return "text-emerald-600";
    if (pct >= 95)    return "text-amber-600";
    return "text-rose-600";
}
function logStatusConf(status: string) {
    if (status === "success") return { color: "text-emerald-600", bg: "bg-emerald-50 border-emerald-100", icon: CheckCircle2 };
    if (status === "partial") return { color: "text-amber-600",   bg: "bg-amber-50 border-amber-100",   icon: AlertTriangle };
    return                           { color: "text-rose-600",    bg: "bg-rose-50 border-rose-100",     icon: XCircle };
}
function maintStatusBadge(s: string) {
    return s === "open" ? "bg-rose-50 text-rose-600 border-rose-100" : "bg-emerald-50 text-emerald-600 border-emerald-100";
}

const datColStyle = computed(() => {
    const n = uptimeData.value?.daily_dates?.length ?? 1;
    const w = n <= 7 ? 52 : n <= 14 ? 44 : n <= 21 ? 40 : 36;
    return { minWidth: w + "px", width: w + "px" };
});

const sortedDevices = computed(() => {
    if (!uptimeData.value?.devices) return [];
    const q = searchDeviceLocal.value.toLowerCase();
    let devs = uptimeData.value.devices.filter((d: any) => {
        if (q && !d.device_name.toLowerCase().includes(q) && !(d.ip_address ?? "").toLowerCase().includes(q)) return false;
        return true;
    });
    devs = [...devs].sort((a: any, b: any) => {
        let av = a[sortCol.value] ?? "";
        let bv = b[sortCol.value] ?? "";
        if (sortCol.value === "avg_uptime") { av = a.avg_uptime ?? -1; bv = b.avg_uptime ?? -1; }
        if (typeof av === "number") return sortDir.value === "asc" ? av - bv : bv - av;
        return sortDir.value === "asc" ? String(av).localeCompare(String(bv)) : String(bv).localeCompare(String(av));
    });
    return devs;
});

const typeLabel = computed(() => ({ nvr: "NVR", cctv: "CCTV", finger: "Fingerprint" }[props.deviceType] ?? props.deviceType));
const TypeIcon  = computed(() => ({ nvr: Server, cctv: Camera, finger: Fingerprint }[props.deviceType] ?? Camera));

async function loadUptime() {
    loading.value = true;
    try {
        const params = new URLSearchParams({ from: props.filterFrom, to: props.filterTo, type: props.deviceType, location: filterLocationLocal.value });
        const res = await fetch("/cctv-operation/data?" + params);
        if (res.ok) {
            uptimeData.value = await res.json();
            emit('locations-loaded', uptimeData.value?.locations ?? []);
        }
    } finally { loading.value = false; }
}
async function loadLogs() {
    const res = await fetch("/cctv-operation/logs?type=" + props.deviceType);
    if (res.ok) logData.value = await res.json();
}
async function loadMaintLogs() {
    maintLoading.value = true;
    try {
        const res = await fetch(`/cctv-operation/maintenance-logs?from=${props.filterFrom}&to=${props.filterTo}&type=${props.deviceType}`);
        if (res.ok) maintLogs.value = await res.json();
    } finally { maintLoading.value = false; }
}
async function loadNvrRecords() {
    if (props.deviceType !== 'nvr') return;
    const res = await fetch('/cctv-operation/nvr-records?from=' + props.filterFrom + '&to=' + props.filterTo);
    if (res.ok) nvrRecordData.value = await res.json();
}
function openNvrModal(device: any, month: any) {
    nvrModalDevice.value = device;
    nvrModalMonth.value  = month;
    nvrForm.value = {
        check_date:       month.check_date ?? new Date().toISOString().slice(0,10),
        last_record_date: month.last_record_date ?? '',
        notes:            month.notes ?? '',
    };
    showNvrModal.value = true;
}
async function saveNvrRecord() {
    if (!nvrModalDevice.value || !nvrModalMonth.value) return;
    nvrSaving.value = true;
    const csrf = (document.querySelector('meta[name=csrf-token]') as HTMLMetaElement)?.content ?? '';
    try {
        await fetch('/cctv-operation/nvr-records', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({
                device_id:        nvrModalDevice.value.device_id,
                year:             nvrModalMonth.value.year,
                month:            nvrModalMonth.value.month,
                check_date:       nvrForm.value.check_date,
                last_record_date: nvrForm.value.last_record_date,
                notes:            nvrForm.value.notes || null,
            }),
        });
        showNvrModal.value = false;
        await loadNvrRecords();
    } finally { nvrSaving.value = false; }
}

async function loadAll() {
    loading.value = true;
    try { await Promise.all([loadUptime(), loadLogs(), loadMaintLogs(), loadNvrRecords()]); }
    finally { loading.value = false; }
}

onMounted(() => loadAll());
watch(() => props.applyTrigger, () => loadAll());
watch(() => props.activeView, (v) => { if (v === "maintenance") loadMaintLogs(); if (v === "record") loadNvrRecords(); });
watch(() => props.filterLocation, (v) => { filterLocationLocal.value = v; loadUptime(); });
watch(() => props.searchDevice,   (v) => { searchDeviceLocal.value = v; });

function openMaintenance(dev: any) {
    maintDevice.value = dev; maintNote.value = dev.maintenance_note ?? ""; maintUntil.value = dev.maintenance_until ?? "";
    showMaintenance.value = true;
}
async function saveMaintenance() {
    if (!maintDevice.value) return;
    maintSaving.value = true;
    const csrf = (document.querySelector("meta[name=csrf-token]") as HTMLMetaElement)?.content ?? "";
    try {
        await fetch("/cctv-operation/maintenance", {
            method: "PUT",
            headers: { "Content-Type":"application/json","Accept":"application/json","X-CSRF-TOKEN":csrf },
            body: JSON.stringify({ device_id: maintDevice.value.id, note: maintNote.value || null, until: maintUntil.value || null }),
        });
        showMaintenance.value = false;
        await loadUptime();
    } finally { maintSaving.value = false; }
}
async function toggleExcluded(dev: any) {
    const csrf = (document.querySelector("meta[name=csrf-token]") as HTMLMetaElement)?.content ?? "";
    await fetch("/cctv-operation/excluded", {
        method: "PUT",
        headers: { "Content-Type":"application/json","Accept":"application/json","X-CSRF-TOKEN":csrf },
        body: JSON.stringify({ device_id: dev.id, is_excluded: !dev.is_excluded }),
    });
    await loadUptime();
}
function openMaintForm(log?: any) {
    editingMaintLog.value = log ?? null;
    if (log) {
        maintForm.value = {
            device_id: String(log.device_id),
            status: log.status,
            event_type: log.event_type,
            started_at: log.started_at || "",
            resolved_at: log.resolved_at || "",
            notes: log.notes ?? ""
        };
    } else {
        maintForm.value = {
            device_id: "",
            status: "open",
            event_type: "maintenance",
            started_at: new Date().toISOString().slice(0, 10),
            resolved_at: "",
            notes: ""
        };
    }
    showMaintForm.value = true;
}
async function saveMaintLog() {
    if (!maintForm.value.device_id || !maintForm.value.started_at) return;
    savingMaintLog.value = true;
    const csrf = (document.querySelector("meta[name=csrf-token]") as HTMLMetaElement)?.content ?? "";
    try {
        const url    = editingMaintLog.value ? `/cctv-operation/maintenance-logs/${editingMaintLog.value.id}` : "/cctv-operation/maintenance-logs";
        const method = editingMaintLog.value ? "PUT" : "POST";
        
        const payload: any = {
            device_id: parseInt(maintForm.value.device_id),
            started_at: maintForm.value.started_at,
            event_type: maintForm.value.event_type,
            notes: maintForm.value.notes,
            status: maintForm.value.status,
            type: props.deviceType, // Important for CCTV
        };
        
        if (maintForm.value.status === 'closed') {
            payload.resolved_at = maintForm.value.resolved_at || new Date().toISOString().slice(0, 10);
        } else {
            payload.resolved_at = null;
        }

        const res = await fetch(url, {
            method, headers: { "Content-Type":"application/json","Accept":"application/json","X-CSRF-TOKEN":csrf },
            body: JSON.stringify(payload),
        });
        if (res.ok) { showMaintForm.value = false; await loadMaintLogs(); }
    } finally { savingMaintLog.value = false; }
}
async function closeMaintLog(log: any) {
    const csrf = (document.querySelector("meta[name=csrf-token]") as HTMLMetaElement)?.content ?? "";
    await fetch(`/cctv-operation/maintenance-logs/${log.id}`, {
        method: "PUT", headers: { "Content-Type":"application/json","Accept":"application/json","X-CSRF-TOKEN":csrf },
        body: JSON.stringify({ status: "closed", resolved_at: new Date().toISOString().slice(0,10) }),
    });
    await loadMaintLogs();
}
async function deleteMaintLog(id: number) {
    if (!confirm("Hapus log ini?")) return;
    const csrf = (document.querySelector("meta[name=csrf-token]") as HTMLMetaElement)?.content ?? "";
    await fetch(`/cctv-operation/maintenance-logs/${id}`, { method: "DELETE", headers: { "X-CSRF-TOKEN":csrf } });
    await loadMaintLogs();
}
</script>

<template>
<div class="space-y-5 w-full min-w-0">

    <!-- UPTIME VIEW -->
    <div v-if="props.activeView==='uptime'" class="space-y-5 w-full min-w-0">
        <div v-if="loading" class="bg-white rounded-2xl border border-slate-100 shadow-sm p-8 flex items-center justify-center gap-3">
            <Loader2 class="size-5 text-slate-400 animate-spin"/>
            <span class="text-[11px] font-bold text-slate-400">Loading data...</span>
        </div>
        <div v-else-if="!uptimeData?.devices?.length" class="bg-white rounded-2xl border border-dashed border-slate-200 py-20 text-center">
            <Activity class="size-10 text-slate-200 mx-auto mb-3"/>
            <p class="text-[11px] font-black uppercase tracking-widest text-slate-300">Belum ada data</p>
        </div>
        <div v-else class="bg-white rounded-2xl border border-slate-100 shadow-sm">
            <div class="overflow-x-auto w-full relative">
                <table class="border-collapse text-left" style="width:max-content;min-width:100%">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100">
                            <th class="sticky left-0 z-20 bg-slate-50 px-3 py-3 text-[9px] font-black uppercase tracking-widest text-slate-400 w-[100px] min-w-[100px] cursor-pointer hover:bg-slate-100" @click="toggleSort('location')">
                                <div class="flex items-center gap-1.5">Lokasi <component :is="sortIcon('location')" class="size-3.5"/></div>
                            </th>
                            <th class="sticky left-[100px] z-20 bg-slate-50 px-3 py-3 text-[9px] font-black uppercase tracking-widest text-slate-400 w-[220px] min-w-[220px] cursor-pointer hover:bg-slate-100" @click="toggleSort('device_name')">
                                <div class="flex items-center gap-1.5">Device <component :is="sortIcon('device_name')" class="size-3.5"/></div>
                            </th>
                            <th class="sticky left-[320px] z-20 bg-slate-50 px-3 py-3 text-[9px] font-black uppercase tracking-widest text-slate-400 w-[110px] min-w-[110px]">Group</th>
                            <th class="sticky left-[430px] z-20 bg-slate-50 border-r border-slate-200 px-3 py-3 text-[9px] font-black uppercase tracking-widest text-slate-400 w-[72px] min-w-[72px] cursor-pointer hover:bg-slate-100" style="box-shadow:2px 0 5px -1px rgba(0,0,0,0.08)" @click="toggleSort('avg_uptime')">
                                <div class="flex items-center gap-1.5">Avg <component :is="sortIcon('avg_uptime')" class="size-3.5"/></div>
                            </th>
                            <th v-for="d in (uptimeData?.daily_dates ?? [])" :key="d.date" class="px-1 py-3 text-center text-[9px] font-black uppercase tracking-widest text-slate-400" :style="datColStyle">
                                <span class="block">{{ new Date(d.date+'T00:00:00').getDate() }}</span>
                                <span class="block text-[8px] font-bold text-slate-300">{{ ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'][new Date(d.date+'T00:00:00').getMonth()] }}</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-for="dev in sortedDevices" :key="dev.id" class="hover:bg-slate-50/40 transition-colors group" :class="dev.is_excluded?'opacity-50':''">
                            <td class="sticky left-0 z-10 bg-white px-3 py-3 text-[10px] font-bold text-slate-600 whitespace-nowrap group-hover:bg-slate-50/40">{{ dev.location }}</td>
                            <td class="sticky left-[100px] z-10 bg-white px-3 py-2 group-hover:bg-slate-50/40">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex-1 min-w-0">
                                        <p class="text-[11px] font-bold text-slate-800 truncate max-w-[150px]">{{ dev.device_name || dev.ip_address }}</p>
                                        <p v-if="dev.device_name" class="text-[9px] font-mono text-slate-400">{{ dev.ip_address }}</p>
                                    </div>
                                    <div class="flex items-center gap-1 shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <button type="button" class="h-7 w-7 rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-100 transition-all flex items-center justify-center" @click="openMaintenance(dev)" title="Maintenance"><WrenchIcon class="size-3.5"/></button>
                                        <button type="button" class="h-7 w-7 rounded-lg transition-all flex items-center justify-center" :class="dev.is_excluded?'bg-emerald-50 text-emerald-600 hover:bg-emerald-100':'bg-slate-50 text-slate-500 hover:bg-slate-100'" @click="toggleExcluded(dev)" :title="dev.is_excluded?'Enable':'Disable'">
                                            <component :is="dev.is_excluded?Eye:EyeOff" class="size-3.5"/>
                                        </button>
                                    </div>
                                </div>
                            </td>
                            <td class="sticky left-[320px] z-10 bg-white px-3 py-3 text-[10px] text-slate-400 whitespace-nowrap group-hover:bg-slate-50/40">{{ dev.display_group }}</td>
                            <td class="sticky left-[430px] z-10 bg-white border-r border-slate-100 px-3 py-3 text-center group-hover:bg-slate-50/40" style="box-shadow:2px 0 5px -1px rgba(0,0,0,0.08)">
                                <span class="px-2 py-1 rounded-lg text-[10px] font-black" :class="uptimeColor(dev.avg_uptime, true)">{{ dev.avg_uptime !== null ? dev.avg_uptime + '%' : '�' }}</span>
                            </td>
                            <td v-for="(daily, di) in dev.daily" :key="di" class="px-0.5 py-1.5 text-center" :style="datColStyle">
                                <span v-if="!daily.in_range || daily.uptime === null"
                                    class="block text-[8px] font-bold text-slate-300 tabular-nums leading-none">—</span>
                                <span v-else-if="daily.uptime >= 99.5"
                                    class="block text-[8px] font-black tabular-nums leading-none text-emerald-600"
                                    :title="daily.uptime + '%'">{{ daily.uptime === 100 ? '100' : Number(daily.uptime).toFixed(1) }}%</span>
                                <span v-else-if="daily.uptime >= 95"
                                    class="block text-[8px] font-black tabular-nums leading-none text-amber-500"
                                    :title="daily.uptime + '%'">{{ Number(daily.uptime).toFixed(1) }}%</span>
                                <span v-else
                                    class="block text-[8px] font-black tabular-nums leading-none text-rose-500"
                                    :title="daily.uptime + '%'">{{ Number(daily.uptime).toFixed(1) }}%</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- FETCH LOG -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
                <Clock class="size-4 text-[#003628]"/>
                <p class="text-sm font-black text-slate-800">Fetch Log</p>
                <span class="px-2 py-0.5 rounded-full bg-slate-100 text-[9px] font-black text-slate-500">{{ logData.length }} entri</span>
            </div>
            <div v-if="!logData.length" class="py-10 text-center">
                <p class="text-[10px] font-black uppercase tracking-widest text-slate-300">Belum ada log</p>
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
                    <!-- Source -->
                    <span class="text-[10px] font-bold text-slate-500 uppercase">{{ log.source }}
                        <span v-if="log.source_instance && log.source_instance !== 'main'" class="text-slate-400">/{{ log.source_instance }}</span>
                    </span>
                    <!-- Device type badge -->
                    <span class="px-2 py-0.5 rounded-full text-[8px] font-black uppercase"
                        :class="log.device_type==='nvr'?'bg-violet-50 border border-violet-100 text-violet-600':log.device_type==='cctv'?'bg-sky-50 border border-sky-100 text-sky-600':'bg-emerald-50 border border-emerald-100 text-emerald-600'">
                        {{ log.device_type?.toUpperCase() }}
                    </span>
                    <!-- Group -->
                    <span v-if="log.group_name" class="text-[10px] text-slate-400 truncate max-w-[160px]">{{ log.group_name }}</span>
                    <!-- OK / Fail counts -->
                    <span class="ml-auto flex items-center gap-2 shrink-0">
                        <span class="text-[10px] font-black text-emerald-600 tabular-nums">{{ log.devices_ok }} OK</span>
                        <span v-if="log.devices_fail > 0" class="text-[10px] font-black text-rose-500 tabular-nums">{{ log.devices_fail }} fail</span>
                    </span>
                    <!-- Manual badge + triggered by -->
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
    <div v-else-if="props.activeView==='record'" class="space-y-5 w-full min-w-0 max-w-full">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm w-full min-w-0 max-w-full">
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
                <div class="flex items-center gap-2">
                    <FileText class="size-4 text-[#003628]"/>
                    <p class="text-sm font-black text-slate-800">Duration Record NVR</p>
                    <span class="text-[9px] font-bold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full">{{ displayPeriod }}</span>
                </div>
                <div class="text-[9px] font-bold text-slate-400">Klik cell untuk input data</div>
            </div>
            <div class="overflow-x-auto w-full relative">
                <table class="border-collapse text-left" style="width:max-content;min-width:100%">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100">
                            <th class="sticky left-0 z-20 bg-slate-50 px-3 py-3 text-[9px] font-black uppercase tracking-widest text-slate-400 w-[110px] min-w-[110px]">Lokasi</th>
                            <th class="sticky left-[110px] z-20 bg-slate-50 px-3 py-3 text-[9px] font-black uppercase tracking-widest text-slate-400 w-[200px] min-w-[200px]">Device</th>
                            <th class="sticky left-[310px] z-20 bg-slate-50 border-r border-slate-200 px-3 py-3 text-[9px] font-black uppercase tracking-widest text-slate-400 w-[110px] min-w-[110px]" style="box-shadow:2px 0 5px -1px rgba(0,0,0,0.08)">Group</th>
                            <th v-for="m in (nvrRecordData?.months ?? [])" :key="m.year+'-'+m.month" class="px-2 py-3 text-center text-[9px] font-black uppercase tracking-widest text-slate-400 min-w-[110px]">{{ m.label }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-for="row in (nvrRecordData?.grid ?? [])" :key="row.device_id" class="hover:bg-slate-50/40 transition-colors group">
                            <td class="sticky left-0 z-10 bg-white px-3 py-2 text-[10px] font-bold text-slate-600 whitespace-nowrap group-hover:bg-slate-50/40">{{ row.location }}</td>
                            <td class="sticky left-[110px] z-10 bg-white px-3 py-2 text-[11px] font-bold text-slate-800 whitespace-nowrap group-hover:bg-slate-50/40">{{ row.device_name }}</td>
                            <td class="sticky left-[310px] z-10 bg-white border-r border-slate-100 px-3 py-2 text-[10px] text-slate-400 whitespace-nowrap group-hover:bg-slate-50/40" style="box-shadow:2px 0 5px -1px rgba(0,0,0,0.08)">{{ row.display_group }}</td>
                            <td v-for="(m, mi) in row.months" :key="mi" class="px-1 py-1.5 text-center min-w-[110px]">
                                <button type="button"
                                    class="w-full h-8 rounded-lg text-[10px] font-black transition-all hover:ring-2 hover:ring-[#003628]/20 cursor-pointer"
                                    :class="m.duration_days !== null && m.duration_days !== undefined ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-slate-50 text-slate-300 border border-dashed border-slate-200'"
                                    @click="openNvrModal(row, m)">
                                    {{ m.duration_days !== null && m.duration_days !== undefined ? m.duration_days + ' hari' : '---' }}
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!(nvrRecordData?.grid?.length)">
                            <td colspan="40" class="px-5 py-16 text-center text-[10px] font-black uppercase tracking-widest text-slate-300">Belum ada data NVR</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- MAINTENANCE LOG VIEW -->
    <div v-else-if="props.activeView==='maintenance'" class="space-y-5">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
                <div class="flex items-center gap-2">
                    <AlertCircle class="size-4 text-rose-500"/>
                    <p class="text-sm font-black text-slate-800">Maintenance Log</p>
                    <span class="px-2 py-0.5 rounded-full bg-slate-100 text-[9px] font-black text-slate-500">{{ maintLogs.length }} entri</span>
                </div>
                <button type="button" class="h-8 px-3 rounded-xl bg-[#003628] text-white text-[10px] font-black uppercase tracking-widest hover:brightness-110 transition-all flex items-center gap-1.5 shadow-sm" @click="openMaintForm()">
                    <Plus class="size-3.5"/> Tambah Log
                </button>
            </div>
            <div v-if="maintLoading" class="p-4 space-y-2">
                <div v-for="n in 4" :key="n" class="h-12 bg-slate-100 rounded-xl animate-pulse"></div>
            </div>
            <div v-else-if="!maintLogs.length" class="py-12 text-center">
                <AlertCircle class="size-8 text-slate-200 mx-auto mb-2"/>
                <p class="text-[10px] font-black uppercase tracking-widest text-slate-300">Tidak ada maintenance log</p>
            </div>
            <div v-else>
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100">
                            <th class="px-4 py-3 text-[9px] font-black uppercase tracking-widest text-slate-400 text-left">Status</th>
                            <th class="px-4 py-3 text-[9px] font-black uppercase tracking-widest text-slate-400 text-left">Device</th>
                            <th class="px-4 py-3 text-[9px] font-black uppercase tracking-widest text-slate-400 text-left">Lokasi</th>
                            <th class="px-4 py-3 text-[9px] font-black uppercase tracking-widest text-slate-400 text-left">Tipe</th>
                            <th class="px-4 py-3 text-[9px] font-black uppercase tracking-widest text-slate-400 text-left">Mulai</th>
                            <th class="px-4 py-3 text-[9px] font-black uppercase tracking-widest text-slate-400 text-left">Selesai</th>
                            <th class="px-4 py-3 text-[9px] font-black uppercase tracking-widest text-slate-400 text-left">Durasi</th>
                            <th class="px-4 py-3 text-[9px] font-black uppercase tracking-widest text-slate-400 text-left">Catatan</th>
                            <th class="px-4 py-3 text-[9px] font-black uppercase tracking-widest text-slate-400 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-for="log in pagedMaintLogs" :key="log.id" class="hover:bg-slate-50/40 transition-colors group">
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded-lg border text-[8px] font-black uppercase tracking-widest" :class="maintStatusBadge(log.status)">
                                    {{ log.status === 'open' ? 'Open' : 'Closed' }}
                                </span>
                                <span v-if="log.is_auto" class="ml-1 px-1.5 py-0.5 rounded-md bg-amber-50 border border-amber-100 text-[7px] font-black uppercase text-amber-600">Auto</span>
                            </td>
                            <td class="px-4 py-3">
                                <p class="text-[11px] font-bold text-slate-800 truncate max-w-[140px]">{{ log.device_name }}</p>
                                <p class="text-[9px] font-mono text-slate-400">{{ log.ip_address }}</p>
                            </td>
                            <td class="px-4 py-3 text-[10px] font-bold text-slate-600">{{ log.location }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded-full bg-slate-50 border border-slate-100 text-[9px] font-black text-slate-600">{{ EVENT_LABELS[log.event_type] ?? log.event_type }}</span>
                            </td>
                            <td class="px-4 py-3 text-[10px] font-bold text-slate-700 tabular-nums">{{ log.started_at }}</td>
                            <td class="px-4 py-3 text-[10px] tabular-nums" :class="log.resolved_at ? 'text-slate-600' : 'text-rose-500 font-bold'">
                                {{ log.resolved_at ?? 'Belum selesai' }}
                            </td>
                            <td class="px-4 py-3 text-[10px] text-slate-500">{{ log.duration }}</td>
                            <td class="px-4 py-3 text-[10px] text-slate-500 max-w-[160px] truncate">{{ log.notes ?? '�' }}</td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <button v-if="log.status==='open'" type="button" class="h-7 px-3 rounded-lg bg-emerald-50 text-emerald-600 text-[9px] font-black hover:bg-emerald-100 transition-all" @click="closeMaintLog(log)" title="Tandai selesai">Close</button>
                                    <button type="button" class="h-7 w-7 rounded-lg bg-slate-50 text-slate-500 hover:bg-slate-100 transition-all flex items-center justify-center" @click="openMaintForm(log)" title="Edit"><WrenchIcon class="size-3.5"/></button>
                                    <button type="button" class="h-7 w-7 rounded-lg bg-rose-50 text-rose-500 hover:bg-rose-100 transition-all flex items-center justify-center" @click="deleteMaintLog(log.id)" title="Hapus"><Trash2 class="size-3.5"/></button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <!-- Pagination -->
                <div v-if="maintPages > 1" class="flex items-center justify-between px-5 py-3 border-t border-slate-100 bg-slate-50/40">
                    <span class="text-[10px] font-bold text-slate-400">{{ (maintPage-1)*MAINT_PER_PAGE+1 }}-{{ Math.min(maintPage*MAINT_PER_PAGE, maintLogs.length) }} dari {{ maintLogs.length }}</span>
                    <div class="flex items-center gap-1">
                        <button type="button" :disabled="maintPage===1" class="h-7 w-7 rounded-lg border border-slate-200 bg-white text-slate-500 text-[10px] font-black disabled:opacity-40 hover:bg-slate-50 flex items-center justify-center" @click="maintPage--">&lt;</button>
                        <button v-for="p in maintPages" :key="p" type="button" class="h-7 w-7 rounded-lg border text-[10px] font-black" :class="p===maintPage?'bg-[#003628] text-white border-[#003628]':'border-slate-200 bg-white text-slate-500 hover:bg-slate-50'" @click="maintPage=p">{{ p }}</button>
                        <button type="button" :disabled="maintPage===maintPages" class="h-7 w-7 rounded-lg border border-slate-200 bg-white text-slate-500 text-[10px] font-black disabled:opacity-40 hover:bg-slate-50 flex items-center justify-center" @click="maintPage++">&gt;</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MAINTENANCE MODAL -->
    <Teleport to="body">
        <div v-if="showMaintenance" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4" @click.self="showMaintenance=false">
            <div class="bg-white w-full max-w-sm rounded-2xl shadow-2xl overflow-hidden border border-slate-200">
                <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
                    <h3 class="text-sm font-black text-slate-800 flex items-center gap-2"><WrenchIcon class="size-4 text-amber-500"/> Maintenance Note</h3>
                    <button type="button" class="h-8 w-8 rounded-xl border border-slate-200 flex items-center justify-center text-slate-400 hover:text-slate-700 transition-all" @click="showMaintenance=false"><X class="size-4"/></button>
                </div>
                <div class="p-6 space-y-4">
                    <div v-if="maintDevice" class="px-3 py-2 rounded-xl bg-slate-50 border border-slate-100">
                        <p class="text-[11px] font-bold text-slate-800">{{ maintDevice.device_name }}</p>
                        <p class="text-[9px] text-slate-400">{{ maintDevice.ip_address }} - {{ maintDevice.location }}</p>
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-[9px] font-black uppercase tracking-widest text-slate-400">Catatan Maintenance</label>
                        <textarea v-model="maintNote" rows="3" placeholder="Contoh: Dimatikan untuk upgrade firmware..." class="w-full px-3 py-2 rounded-xl border border-slate-200 bg-slate-50 text-[11px] font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#003628]/20 resize-none"></textarea>
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-[9px] font-black uppercase tracking-widest text-slate-400">Maintenance Sampai (opsional)</label>
                        <input v-model="maintUntil" type="date" class="w-full h-9 px-3 rounded-xl border border-slate-200 bg-slate-50 text-[11px] font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#003628]/20"/>
                        <p class="text-[9px] text-slate-400">Kosongkan jika tidak ada batas waktu</p>
                    </div>
                    <div class="flex gap-3">
                        <button type="button" class="flex-1 h-9 rounded-xl border border-slate-200 bg-slate-50 text-slate-600 text-[10px] font-black uppercase tracking-widest hover:bg-slate-100 transition-all" @click="showMaintenance=false">Batal</button>
                        <button v-if="maintDevice?.maintenance_note" type="button" class="h-9 px-3 rounded-xl border border-rose-200 bg-rose-50 text-rose-600 text-[10px] font-black uppercase tracking-widest hover:bg-rose-100 transition-all" :disabled="maintSaving" @click="maintNote=''; maintUntil=''; saveMaintenance()">Hapus</button>
                        <button type="button" :disabled="maintSaving" class="flex-1 h-9 rounded-xl bg-[#003628] text-white text-[10px] font-black uppercase tracking-widest hover:brightness-110 transition-all disabled:opacity-50 flex items-center justify-center gap-2 shadow-md shadow-[#003628]/20" @click="saveMaintenance">
                            <Loader2 v-if="maintSaving" class="size-3 animate-spin"/><Save v-else class="size-3"/> Simpan
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>


    <Teleport to="body">
        <div v-if="showMaintForm" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4" @click.self="showMaintForm=false">
            <div class="bg-white w-full max-w-md rounded-[28px] shadow-2xl overflow-hidden border border-slate-200">
                <!-- Header -->
                <div class="flex items-center justify-between px-8 py-6 border-b border-slate-100">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-2xl bg-amber-50 border border-amber-100 flex items-center justify-center shrink-0">
                            <WrenchIcon class="size-5 text-amber-600"/>
                        </div>
                        <div>
                            <h3 class="text-[15px] font-black text-slate-900 leading-none">{{ editingMaintLog ? 'Edit' : 'Tambah' }} Maintenance Log</h3>
                            <p v-if="editingMaintLog" class="text-[10px] text-slate-400 mt-1">ID Log: #{{ editingMaintLog.id }}</p>
                            <p v-else class="text-[10px] text-slate-400 mt-1">Input data maintenance baru</p>
                        </div>
                    </div>
                    <button type="button" class="h-8 w-8 rounded-xl border border-slate-200 flex items-center justify-center text-slate-400 hover:text-slate-700 transition-all hover:bg-slate-50" @click="showMaintForm=false">
                        <X class="size-4"/>
                    </button>
                </div>

                <div class="p-8 space-y-6">
                    <!-- Device Selection (if creating) or Info (if editing) -->
                    <div v-if="editingMaintLog" class="p-4 rounded-2xl bg-slate-50 border border-slate-100">
                        <p class="text-[11px] font-black uppercase tracking-widest text-slate-400 mb-1">Device Information</p>
                        <p class="text-[13px] font-bold text-slate-800">{{ editingMaintLog.device_name }}</p>
                        <p class="text-[10px] font-mono text-slate-500">{{ editingMaintLog.ip_address }} • {{ editingMaintLog.location }}</p>
                    </div>
                    <div v-else class="space-y-1.5">
                        <label class="text-[9px] font-black uppercase tracking-widest text-slate-400 ml-1">Device</label>
                        <select v-model="maintForm.device_id" class="w-full h-11 px-4 rounded-xl border border-slate-200 bg-slate-50 text-[11px] font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#003628]/20 transition-all appearance-none cursor-pointer">
                            <option value="">Pilih device...</option>
                            <option v-for="dev in (uptimeData?.devices ?? [])" :key="dev.id" :value="String(dev.id)">{{ dev.device_name }} ({{ dev.location }})</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <label class="text-[9px] font-black uppercase tracking-widest text-slate-400 ml-1">Status</label>
                            <select v-model="maintForm.status" class="w-full h-11 px-4 rounded-xl border border-slate-200 bg-slate-50 text-[11px] font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#003628]/20 transition-all appearance-none cursor-pointer">
                                <option value="open">Open</option>
                                <option value="closed">Closed</option>
                            </select>
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-[9px] font-black uppercase tracking-widest text-slate-400 ml-1">Event Type</label>
                            <select v-model="maintForm.event_type" class="w-full h-11 px-4 rounded-xl border border-slate-200 bg-slate-50 text-[11px] font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#003628]/20 transition-all appearance-none cursor-pointer">
                                <option v-for="(label, val) in EVENT_LABELS" :key="val" :value="val">{{ label }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <label class="text-[9px] font-black uppercase tracking-widest text-slate-400 ml-1">Started At</label>
                            <input v-model="maintForm.started_at" type="date" class="w-full h-11 px-4 rounded-xl border border-slate-200 bg-slate-50 text-[11px] font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#003628]/20 transition-all"/>
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-[9px] font-black uppercase tracking-widest text-slate-400 ml-1">Resolved At</label>
                            <input v-model="maintForm.resolved_at" type="date" class="w-full h-11 px-4 rounded-xl border border-slate-200 bg-slate-50 text-[11px] font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#003628]/20 transition-all" :disabled="maintForm.status === 'open'"/>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-[9px] font-black uppercase tracking-widest text-slate-400 ml-1">Notes</label>
                        <textarea v-model="maintForm.notes" rows="3" placeholder="Maintenance notes..." class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-[11px] font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#003628]/20 transition-all resize-none"></textarea>
                    </div>

                    <div class="flex gap-4 pt-2">
                        <button type="button" class="flex-1 h-11 rounded-xl border border-slate-200 bg-slate-50 text-slate-600 text-[10px] font-black uppercase tracking-widest hover:bg-slate-100 transition-all active:scale-[0.98]" @click="showMaintForm = false">Batal</button>
                        <button type="button" :disabled="savingMaintLog || !maintForm.device_id || !maintForm.started_at" class="flex-[1.5] h-11 rounded-xl bg-[#003628] text-white text-[10px] font-black uppercase tracking-widest hover:brightness-110 transition-all disabled:opacity-50 flex items-center justify-center gap-2 shadow-lg shadow-[#003628]/20 active:scale-[0.98]" @click="saveMaintLog">
                            <Loader2 v-if="savingMaintLog" class="size-4 animate-spin"/>
                            <Save v-else class="size-4"/>
                            Simpan Perubahan
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- NVR DURATION RECORD MODAL -->
    <Teleport to="body">
        <div v-if="showNvrModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4" @click.self="showNvrModal=false">
            <div class="bg-white w-full max-w-sm rounded-2xl shadow-2xl overflow-hidden border border-slate-200">
                <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
                    <h3 class="text-sm font-black text-slate-800 flex items-center gap-2"><FileText class="size-4 text-[#003628]"/> Duration Record</h3>
                    <button type="button" class="h-8 w-8 rounded-xl border border-slate-200 flex items-center justify-center text-slate-400 hover:text-slate-700" @click="showNvrModal=false"><X class="size-4"/></button>
                </div>
                <div class="p-6 space-y-4">
                    <div v-if="nvrModalDevice" class="px-3 py-2 rounded-xl bg-slate-50 border border-slate-100">
                        <p class="text-[11px] font-bold text-slate-800">{{ nvrModalDevice.device_name }}</p>
                        <p class="text-[9px] text-slate-400">{{ nvrModalDevice.location }} � {{ nvrModalMonth?.label }}</p>
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-[9px] font-black uppercase tracking-widest text-slate-400">Tanggal Cek</label>
                        <input v-model="nvrForm.check_date" type="date" class="w-full h-9 px-3 rounded-xl border border-slate-200 bg-slate-50 text-[11px] font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#003628]/20"/>
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-[9px] font-black uppercase tracking-widest text-slate-400">Last Record Date</label>
                        <input v-model="nvrForm.last_record_date" type="date" class="w-full h-9 px-3 rounded-xl border border-slate-200 bg-slate-50 text-[11px] font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#003628]/20"/>
                    </div>
                    <div class="px-3 py-2 rounded-xl bg-slate-50 border border-slate-100 flex items-center justify-between">
                        <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">Record Duration</span>
                        <span class="text-sm font-black" :class="nvrDurationDays !== null ? 'text-emerald-600' : 'text-slate-300'">
                            {{ nvrDurationDays !== null ? nvrDurationDays + ' hari' : '�' }}
                        </span>
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-[9px] font-black uppercase tracking-widest text-slate-400">Catatan (opsional)</label>
                        <textarea v-model="nvrForm.notes" rows="2" placeholder="Catatan tambahan..." class="w-full px-3 py-2 rounded-xl border border-slate-200 bg-slate-50 text-[11px] font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#003628]/20 resize-none"></textarea>
                    </div>
                    <div class="flex gap-3">
                        <button type="button" class="flex-1 h-9 rounded-xl border border-slate-200 bg-slate-50 text-slate-600 text-[10px] font-black uppercase tracking-widest hover:bg-slate-100 transition-all" @click="showNvrModal=false">Batal</button>
                        <button type="button" :disabled="nvrSaving" class="flex-1 h-9 rounded-xl bg-[#003628] text-white text-[10px] font-black uppercase tracking-widest hover:brightness-110 transition-all disabled:opacity-50 flex items-center justify-center gap-2" @click="saveNvrRecord">
                            <Loader2 v-if="nvrSaving" class="size-3 animate-spin"/><Save v-else class="size-3"/> Simpan
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</div>

</template>