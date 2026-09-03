<script setup lang="ts">
import { ref, computed, watch, onMounted } from "vue";
import {
    Activity, Loader2, CheckCircle2, XCircle, AlertTriangle,
    Clock, Server, X, Save, FileText, Settings, WrenchIcon, Search,
    ChevronUp, ChevronDown, ChevronsUpDown, AlertCircle, Plus, EyeOff, Eye, Database, Trash2,
} from "lucide-vue-next";

const props = defineProps<{
    locations: string[];
    filterFrom: string;
    filterTo: string;
    applyTrigger: number;
    activeView: "uptime"|"backup"|"maintenance";
    filterLocation: string;
    filterGroup: string;
    searchDevice: string;
    showSettingsModal?: boolean;
}>();

const emit = defineEmits<{
    (e: 'locations-loaded', locs: string[]): void;
    (e: 'groups-loaded', groups: string[]): void;
    (e: 'update:showSettingsModal', val: boolean): void;
}>();

const loading      = ref(false);
const uptimeData   = ref<any>(null);
const backupData   = ref<any>(null);
const logData      = ref<any[]>([]);

const filterLocationLocal = ref(props.filterLocation);
const filterGroupLocal    = ref(props.filterGroup);
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

const showSettings     = ref(false);
const settingsLoading  = ref(false);
const settingsDevices  = ref<any[]>([]);
const settingsSelected = ref<Set<number>>(new Set());
const settingsSaving   = ref(false);
const settingsSearch   = ref("");

const showMaintenance = ref(false);
const maintDevice     = ref<any>(null);
const maintNote       = ref("");
const maintUntil      = ref("");
const maintSaving     = ref(false);

const savingBackup = ref(false);

const ID_MONTHS = ["Jan","Feb","Mar","Apr","Mei","Jun","Jul","Agu","Sep","Okt","Nov","Des"];

// Short date label for column header: "4 Mei" (no year to save space)
function dateLabel(dateStr: string) {
    const d = new Date(dateStr + "T00:00:00");
    return d.getDate() + " " + ID_MONTHS[d.getMonth()];
}

const displayPeriod = computed(() => {
    const f = new Date(props.filterFrom + "T00:00:00");
    const t = new Date(props.filterTo + "T00:00:00");
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

// Date column width: adapts to number of days
// With overflow-x-auto + width:max-content, table scrolls if needed
// Fewer days = wider cols; more days = narrower cols
const datColStyle = computed(() => {
    const n = uptimeData.value?.daily_dates?.length ?? 1;
    const w = n <= 7 ? 52 : n <= 14 ? 44 : n <= 21 ? 40 : 36;
    return { minWidth: w + "px", width: w + "px" };
});

const backupColStyle = computed(() => {
    const n = backupData.value?.months?.length ?? 1;
    const w = n <= 3 ? 120 : n <= 6 ? 90 : 72;
    return { minWidth: w + "px", width: w + "px" };
});

// Per-location backup summary: count devices with at least one OK backup in the period
const backupLocStats = computed(() => {
    const grid = backupData.value?.grid ?? [];
    const map: Record<string, { total: number; ok: number }> = {};
    for (const row of grid) {
        const loc = row.location ?? 'Unknown';
        if (!map[loc]) map[loc] = { total: 0, ok: 0 };
        map[loc].total++;
        // OK if any month in range has has_backup = true
        const hasAny = row.months?.some((m: any) => m.has_backup === true);
        if (hasAny) map[loc].ok++;
    }
    return Object.entries(map).map(([location, s]) => ({
        location,
        total: s.total,
        ok: s.ok,
        pct: s.total > 0 ? (s.ok / s.total) * 100 : 0,
    }));
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

const filteredSettingsDevices = computed(() => {
    const q = settingsSearch.value.toLowerCase();
    if (!q) return settingsDevices.value;
    return settingsDevices.value.filter((d: any) =>
        d.device_name.toLowerCase().includes(q) ||
        d.display_group.toLowerCase().includes(q) ||
        d.location.toLowerCase().includes(q)
    );
});
const settingsByLocation = computed(() => {
    const map: Record<string, any[]> = {};
    for (const d of filteredSettingsDevices.value) {
        const key = d.location ?? d.site;
        if (!map[key]) map[key] = [];
        map[key].push(d);
    }
    return map;
});

async function loadUptime() {
    loading.value = true;
    try {
        const params = new URLSearchParams({ from: props.filterFrom, to: props.filterTo, location: filterLocationLocal.value, group: filterGroupLocal.value });
        const res = await fetch("/uptime/data?" + params);
        if (res.ok) {
            uptimeData.value = await res.json();
            emit('locations-loaded', uptimeData.value?.locations ?? []);
            emit('groups-loaded', uptimeData.value?.groups ?? []);
        }
    } finally { loading.value = false; }
}
async function loadBackup() {
    const res = await fetch("/uptime/backup?from=" + props.filterFrom + "&to=" + props.filterTo);
    if (res.ok) backupData.value = await res.json();
}
async function loadLogs() {
    const res = await fetch("/uptime/logs");
    if (res.ok) logData.value = await res.json();
}
async function loadAll() {
    loading.value = true;
    try { await Promise.all([loadUptime(), loadBackup(), loadLogs(), loadMaintLogs()]); }
    finally { loading.value = false; }
}

onMounted(() => loadAll());
watch(() => props.applyTrigger, () => loadAll());
watch(() => props.activeView, (v) => { if (v === "backup") loadBackup(); if (v === "maintenance") loadMaintLogs(); });
watch(() => props.filterLocation, (v) => { filterLocationLocal.value = v; loadUptime(); });
watch(() => props.filterGroup,    (v) => { filterGroupLocal.value = v; loadUptime(); });
watch(() => props.searchDevice,   (v) => { searchDeviceLocal.value = v; });

async function toggleBackup(deviceId: number, year: number, month: number, current: boolean|null) {
    savingBackup.value = true;
    const csrf = (document.querySelector("meta[name=csrf-token]") as HTMLMetaElement)?.content ?? "";
    try {
        await fetch("/uptime/backup", {
            method: "PUT",
            headers: { "Content-Type":"application/json","Accept":"application/json","X-CSRF-TOKEN":csrf },
            body: JSON.stringify({ device_id: deviceId, year, month, has_backup: !current }),
        });
        await loadBackup();
    } finally { savingBackup.value = false; }
}

async function openSettings() {
    emit('update:showSettingsModal', true); settingsLoading.value = true; settingsSearch.value = "";
    try {
        const res = await fetch("/uptime/backup-settings");
        if (res.ok) {
            settingsDevices.value  = await res.json();
            settingsSelected.value = new Set(settingsDevices.value.filter((d: any) => d.monitor_backup).map((d: any) => d.id));
        }
    } finally { settingsLoading.value = false; }
}
function toggleSettingsDevice(id: number) {
    const next = new Set(settingsSelected.value);
    if (next.has(id)) next.delete(id); else next.add(id);
    settingsSelected.value = next;
}
function toggleLocationAll(loc: string) {
    const locDevs = settingsDevices.value.filter((d: any) => (d.location ?? d.site) === loc);
    const allSel  = locDevs.every((d: any) => settingsSelected.value.has(d.id));
    const next    = new Set(settingsSelected.value);
    for (const d of locDevs) { if (allSel) next.delete(d.id); else next.add(d.id); }
    settingsSelected.value = next;
}
function isLocationAllSelected(loc: string) {
    const locDevs = settingsDevices.value.filter((d: any) => (d.location ?? d.site) === loc);
    return locDevs.length > 0 && locDevs.every((d: any) => settingsSelected.value.has(d.id));
}
async function saveSettings() {
    settingsSaving.value = true;
    const csrf = (document.querySelector("meta[name=csrf-token]") as HTMLMetaElement)?.content ?? "";
    try {
        await fetch("/uptime/backup-settings", {
            method: "PUT",
            headers: { "Content-Type":"application/json","Accept":"application/json","X-CSRF-TOKEN":csrf },
            body: JSON.stringify({ device_ids: Array.from(settingsSelected.value) }),
        });
        emit('update:showSettingsModal', false);
        await loadBackup();
    } finally { settingsSaving.value = false; }
}

function openMaintenance(dev: any) {
    maintDevice.value = dev; maintNote.value = dev.maintenance_note ?? ""; maintUntil.value = dev.maintenance_until ?? "";
    showMaintenance.value = true;
}
async function saveMaintenance() {
    if (!maintDevice.value) return;
    maintSaving.value = true;
    const csrf = (document.querySelector("meta[name=csrf-token]") as HTMLMetaElement)?.content ?? "";
    try {
        await fetch("/uptime/maintenance", {
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
    const newVal = !dev.is_excluded;
    await fetch("/uptime/excluded", {
        method: "PUT",
        headers: { "Content-Type":"application/json","Accept":"application/json","X-CSRF-TOKEN":csrf },
        body: JSON.stringify({ device_id: dev.id, is_excluded: newVal }),
    });
    await loadUptime();
}

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
const EVENT_LABELS: Record<string,string> = { maintenance:"Maintenance", restart:"Restart", down:"Down", auto_detected:"Auto Detected" };

async function loadMaintLogs() {
    maintLoading.value = true;
    try {
        const res = await fetch(`/uptime/maintenance-logs?from=${props.filterFrom}&to=${props.filterTo}`);
        if (res.ok) maintLogs.value = await res.json();
    } finally { maintLoading.value = false; }
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
        const url    = editingMaintLog.value ? `/uptime/maintenance-logs/${editingMaintLog.value.id}` : "/uptime/maintenance-logs";
        const method = editingMaintLog.value ? "PUT" : "POST";
        
        const payload: any = {
            device_id: parseInt(maintForm.value.device_id),
            started_at: maintForm.value.started_at,
            event_type: maintForm.value.event_type,
            notes: maintForm.value.notes,
            status: maintForm.value.status,
        };
        
        if (maintForm.value.status === 'closed') {
            payload.resolved_at = maintForm.value.resolved_at || new Date().toISOString().slice(0, 10);
        } else {
            payload.resolved_at = null;
        }

        const res = await fetch(url, {
            method, 
            headers: { "Content-Type":"application/json","Accept":"application/json","X-CSRF-TOKEN":csrf },
            body: JSON.stringify(payload),
        });
        if (res.ok) { showMaintForm.value = false; await loadMaintLogs(); }
    } finally { savingMaintLog.value = false; }
}
async function closeMaintLog(log: any) {
    const csrf = (document.querySelector("meta[name=csrf-token]") as HTMLMetaElement)?.content ?? "";
    await fetch(`/uptime/maintenance-logs/${log.id}`, {
        method: "PUT", headers: { "Content-Type":"application/json","Accept":"application/json","X-CSRF-TOKEN":csrf },
        body: JSON.stringify({ status: "closed", resolved_at: new Date().toISOString().slice(0,10) }),
    });
    await loadMaintLogs();
}
async function deleteMaintLog(id: number) {
    if (!confirm("Hapus log ini?")) return;
    const csrf = (document.querySelector("meta[name=csrf-token]") as HTMLMetaElement)?.content ?? "";
    await fetch(`/uptime/maintenance-logs/${id}`, { method: "DELETE", headers: { "X-CSRF-TOKEN":csrf } });
    await loadMaintLogs();
}
function maintStatusBadge(s: string) {
    return s === "open" ? "bg-rose-50 text-rose-600 border-rose-100" : "bg-emerald-50 text-emerald-600 border-emerald-100";
}
</script><template>
<div class="space-y-5 w-full min-w-0">

    <!-- Loading -->
    <div v-if="loading" class="space-y-3">
        <div v-for="n in 4" :key="n" class="h-16 bg-white rounded-2xl border border-slate-100 animate-pulse"></div>
    </div>

    <!-- UPTIME VIEW -->
    <div v-else-if="props.activeView==='uptime'" class="space-y-5 w-full min-w-0">

        <!-- Location summary cards -->
        <div v-if="uptimeData?.loc_summary?.length" class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div v-for="ls in uptimeData.loc_summary" :key="ls.location" class="bg-white rounded-2xl border shadow-sm p-4 flex items-center gap-3" :class="ls.avg_uptime===null?'border-slate-100':ls.avg_uptime>=99.5?'border-emerald-200':ls.avg_uptime>=95?'border-amber-200':'border-rose-200'">
                <div class="h-9 w-9 rounded-xl flex items-center justify-center shrink-0" :class="ls.avg_uptime===null?'bg-slate-50':ls.avg_uptime>=99.5?'bg-emerald-50':ls.avg_uptime>=95?'bg-amber-50':'bg-rose-50'">
                    <Activity class="size-4" :class="ls.avg_uptime===null?'text-slate-400':ls.avg_uptime>=99.5?'text-emerald-600':ls.avg_uptime>=95?'text-amber-600':'text-rose-600'"/>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-[9px] font-black uppercase tracking-widest text-slate-400 truncate">{{ ls.location }}</p>
                    <p class="text-xl font-black tabular-nums mt-0.5" :class="ls.avg_uptime===null?'text-slate-400':ls.avg_uptime>=99.5?'text-emerald-600':ls.avg_uptime>=95?'text-amber-600':'text-rose-600'">
                        {{ ls.avg_uptime !== null ? ls.avg_uptime.toFixed(2)+"%" : "---" }}
                    </p>
                    <p class="text-[9px] font-bold text-slate-400">{{ ls.total }} device</p>
                </div>
            </div>
        </div>

        <!-- Device table -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm w-full min-w-0 max-w-full overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
                <div class="flex items-center gap-2">
                    <Server class="size-4 text-[#003628]"/>
                    <p class="text-sm font-black text-slate-800">Uptime Harian</p>
                    <span class="text-[9px] font-bold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full">{{ displayPeriod }}</span>
                    <span v-if="sortedDevices.length" class="text-[9px] font-bold text-slate-400">{{ sortedDevices.length }} device</span>
                </div>
                <div class="flex items-center gap-3 text-[9px] font-bold text-slate-400">
                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-emerald-400 inline-block"></span> >=99.5%</span>
                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-amber-400 inline-block"></span> >=95%</span>
                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-rose-400 inline-block"></span> &lt;95%</span>
                </div>
            </div>
            <div class="overflow-x-auto w-full max-w-full">
            <table class="border-separate border-spacing-0 text-left" style="width:max-content;min-width:100%">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100">
                            <th class="px-3 py-3 text-[9px] font-black uppercase tracking-widest text-slate-400 cursor-pointer select-none hover:bg-slate-100 sticky left-0 bg-slate-50 z-20 min-w-[200px] w-[200px] border-b border-slate-100" style="left: 0px" @click="toggleSort('device_name')">
                                <span class="flex items-center gap-1">Device <component :is="sortIcon('device_name')" class="size-3 shrink-0"/></span>
                            </th>
                            <th class="px-3 py-3 text-[9px] font-black uppercase tracking-widest text-slate-400 cursor-pointer select-none hover:bg-slate-100 sticky bg-slate-50 z-20 min-w-[100px] w-[100px] border-b border-slate-100" style="left: 200px" @click="toggleSort('ip_address')">
                                <span class="flex items-center gap-1">IP <component :is="sortIcon('ip_address')" class="size-3 shrink-0"/></span>
                            </th>
                            <th class="px-3 py-3 text-[9px] font-black uppercase tracking-widest text-slate-400 cursor-pointer select-none hover:bg-slate-100 sticky bg-slate-50 z-20 min-w-[120px] w-[120px] border-b border-slate-100" style="left: 300px" @click="toggleSort('location')">
                                <span class="flex items-center gap-1">Lokasi <component :is="sortIcon('location')" class="size-3 shrink-0"/></span>
                            </th>
                            <th class="px-3 py-3 text-[9px] font-black uppercase tracking-widest text-slate-400 cursor-pointer select-none hover:bg-slate-100 sticky bg-slate-50 z-20 min-w-[120px] w-[120px] border-b border-slate-100" style="left: 420px" @click="toggleSort('display_group')">
                                <span class="flex items-center gap-1">Group <component :is="sortIcon('display_group')" class="size-3 shrink-0"/></span>
                            </th>
                            <th class="px-2 py-3 text-right text-[9px] font-black uppercase tracking-widest text-slate-400 cursor-pointer select-none hover:bg-slate-100 border-r border-b border-slate-200 sticky bg-slate-50 z-20 min-w-[80px] w-[80px]" style="left: 540px" @click="toggleSort('avg_uptime')">
                                <span class="flex items-center justify-end gap-1">Avg <component :is="sortIcon('avg_uptime')" class="size-3 shrink-0"/></span>
                            </th>
                            <th v-for="d in (uptimeData?.daily_dates ?? [])" :key="d.date" :style="datColStyle" class="px-0.5 py-3 text-center text-[9px] font-black uppercase tracking-widest text-slate-400 border-b border-slate-100">
                                <span class="block">{{ new Date(d.date+'T00:00:00').getDate() }}</span>
                                <span class="block text-[7px] font-bold text-slate-300">{{ ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'][new Date(d.date+'T00:00:00').getMonth()] }}</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-for="dev in sortedDevices" :key="dev.id" class="hover:bg-slate-50/40 transition-colors group" :class="dev.is_excluded ? 'opacity-50' : dev.in_maintenance ? 'bg-amber-50/30' : ''">
                            <td class="px-3 py-2 sticky bg-white group-hover:bg-slate-50/40 z-10 min-w-[200px] w-[200px] border-b border-slate-50" style="left: 0px" :class="dev.is_excluded ? 'bg-white' : dev.in_maintenance ? 'bg-amber-50/30 group-hover:bg-amber-50/50' : ''">
                                <div class="flex items-center gap-1.5">
                                    <p class="text-[11px] font-bold text-slate-800 whitespace-nowrap" :class="dev.is_excluded ? 'line-through text-slate-400' : ''">{{ dev.device_name }}</p>
                                    <!-- Maintenance button -->
                                    <button v-if="dev.in_maintenance" type="button" class="shrink-0 px-1.5 py-0.5 rounded-md bg-amber-100 text-amber-700 text-[8px] font-black uppercase tracking-widest hover:bg-amber-200 transition-all" :title="dev.maintenance_note" @click="openMaintenance(dev)">MNT</button>
                                    <button v-else type="button" class="shrink-0 opacity-0 group-hover:opacity-100 h-5 w-5 rounded-md flex items-center justify-center text-slate-300 hover:text-slate-600 hover:bg-slate-100 transition-all" @click="openMaintenance(dev)"><WrenchIcon class="size-3"/></button>
                                    <!-- Disable/Enable button -->
                                    <button type="button"
                                        class="shrink-0 h-5 w-5 rounded-md flex items-center justify-center transition-all"
                                        :class="dev.is_excluded ? 'opacity-100 bg-slate-200 text-slate-500 hover:bg-rose-100 hover:text-rose-600' : 'opacity-0 group-hover:opacity-100 text-slate-300 hover:text-slate-600 hover:bg-slate-100'"
                                        :title="dev.is_excluded ? 'Device dinonaktifkan dari hitungan — klik untuk aktifkan' : 'Nonaktifkan dari hitungan avg'"
                                        @click="toggleExcluded(dev)">
                                        <EyeOff v-if="!dev.is_excluded" class="size-3"/>
                                        <Eye v-else class="size-3"/>
                                    </button>
                                </div>
                            </td>
                            <td class="px-3 py-2 text-[10px] font-mono text-slate-500 whitespace-nowrap sticky bg-white group-hover:bg-slate-50/40 z-10 min-w-[100px] w-[100px] border-b border-slate-50" style="left: 200px">{{ dev.ip_address ?? "---" }}</td>
                            <td class="px-3 py-2 text-[10px] font-bold text-slate-600 whitespace-nowrap sticky bg-white group-hover:bg-slate-50/40 z-10 min-w-[120px] w-[120px] border-b border-slate-50" style="left: 300px">{{ dev.location }}</td>
                            <td class="px-3 py-2 text-[10px] text-slate-400 whitespace-nowrap sticky bg-white group-hover:bg-slate-50/40 z-10 min-w-[120px] w-[120px] border-b border-slate-50" style="left: 420px">{{ dev.display_group }}</td>
                            <td class="px-2 py-2 text-right border-r border-slate-100 sticky bg-white group-hover:bg-slate-50/40 z-10 min-w-[80px] w-[80px] border-b border-slate-50" style="left: 540px">
                                <span class="text-[11px] font-black tabular-nums" :class="uptimeBadge(dev.avg_uptime)">{{ dev.avg_uptime !== null ? dev.avg_uptime.toFixed(1)+"%" : "---" }}</span>
                            </td>
                            <td v-for="d in dev.daily" :key="d.date" :style="datColStyle" class="px-0.5 py-1.5 text-center border-b border-slate-50">
                                <span v-if="!d.in_range || d.uptime === null"
                                    class="block text-[8px] font-bold text-slate-300 tabular-nums leading-none">—</span>
                                <span v-else-if="d.uptime >= 99.5"
                                    class="block text-[8px] font-black tabular-nums leading-none text-emerald-600"
                                    :title="d.date + ': ' + d.uptime.toFixed(1) + '%'">{{ d.uptime === 100 ? '100' : d.uptime.toFixed(1) }}%</span>
                                <span v-else-if="d.uptime >= 95"
                                    class="block text-[8px] font-black tabular-nums leading-none text-amber-500"
                                    :title="d.date + ': ' + d.uptime.toFixed(1) + '%'">{{ d.uptime.toFixed(1) }}%</span>
                                <span v-else
                                    class="block text-[8px] font-black tabular-nums leading-none text-rose-500"
                                    :title="d.date + ': ' + d.uptime.toFixed(1) + '%'">{{ d.uptime.toFixed(1) }}%</span>
                            </td>
                        </tr>
                        <tr v-if="!sortedDevices.length">
                            <td colspan="40" class="px-5 py-16 text-center text-[10px] font-black uppercase tracking-widest text-slate-300">Belum ada data</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Fetch log -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
                <Clock class="size-4 text-[#003628]"/>
                <p class="text-sm font-black text-slate-800">Fetch Log</p>
                <span class="px-2 py-0.5 rounded-full bg-slate-100 text-[9px] font-black text-slate-500">{{ logData.length }} entri</span>
            </div>
            <div v-if="!logData.length" class="py-10 text-center"><p class="text-[10px] font-black uppercase tracking-widest text-slate-300">Belum ada log</p></div>
            <div v-else class="divide-y divide-slate-50 max-h-64 overflow-y-auto">
                <div v-for="log in logData.slice(0,30)" :key="log.id" class="flex items-center gap-3 px-5 py-3 hover:bg-slate-50/50 transition-colors flex-wrap">
                    <div class="shrink-0 px-2 py-0.5 rounded-lg border text-[8px] font-black uppercase tracking-widest flex items-center gap-1" :class="logStatusConf(log.status).bg">
                        <component :is="logStatusConf(log.status).icon" class="size-3" :class="logStatusConf(log.status).color"/>
                        <span :class="logStatusConf(log.status).color">{{ log.status }}</span>
                    </div>
                    <span class="text-[11px] font-black text-slate-800 tabular-nums">{{ log.fetch_date }}</span>
                    <span class="text-[10px] font-bold text-slate-500 uppercase">{{ log.source }}<span v-if="log.source_instance && log.source_instance!=='main'">-{{ log.source_instance }}</span></span>
                    <span class="text-[10px] text-slate-400">{{ log.devices_ok }} OK - {{ log.devices_fail }} gagal</span>
                    <span v-if="log.is_manual" class="px-2 py-0.5 rounded-full bg-violet-50 border border-violet-100 text-[8px] font-black uppercase tracking-widest text-violet-600">Manual</span>
                    <span class="ml-auto text-[9px] text-slate-300 tabular-nums">{{ log.created_at }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- BACKUP VIEW -->
    <div v-else-if="props.activeView==='backup'" class="space-y-5 w-full min-w-0 max-w-full">

        <!-- Per-location summary cards -->
        <div v-if="backupData?.grid?.length" class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div v-for="ls in backupLocStats" :key="ls.location"
                class="bg-white rounded-2xl border shadow-sm p-4 flex items-center gap-3"
                :class="ls.pct >= 90 ? 'border-emerald-200' : ls.pct >= 70 ? 'border-amber-200' : 'border-rose-200'">
                <div class="h-9 w-9 rounded-xl flex items-center justify-center shrink-0"
                    :class="ls.pct >= 90 ? 'bg-emerald-50' : ls.pct >= 70 ? 'bg-amber-50' : 'bg-rose-50'">
                    <Database class="size-4"
                        :class="ls.pct >= 90 ? 'text-emerald-600' : ls.pct >= 70 ? 'text-amber-600' : 'text-rose-600'"/>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-[9px] font-black uppercase tracking-widest text-slate-400 truncate">{{ ls.location }}</p>
                    <p class="text-xl font-black tabular-nums mt-0.5"
                        :class="ls.pct >= 90 ? 'text-emerald-600' : ls.pct >= 70 ? 'text-amber-600' : 'text-rose-600'">
                        {{ ls.pct.toFixed(0) }}%
                    </p>
                    <p class="text-[9px] font-bold text-slate-400">{{ ls.ok }}/{{ ls.total }} device terbackup</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm w-full min-w-0 max-w-full overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
                <div class="flex items-center gap-2">
                    <FileText class="size-4 text-[#003628]"/>
                    <p class="text-sm font-black text-slate-800">Backup Config Status</p>
                    <span class="text-[9px] font-bold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full">{{ displayPeriod }}</span>
                </div>
                <div class="flex items-center gap-2 text-[9px] font-bold text-slate-400">
                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-emerald-400 inline-block"></span> B = Ada</span>
                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-slate-200 inline-block"></span> --- = Tidak Ada</span>
                </div>
            </div>
            <div class="overflow-x-auto w-full max-w-full">
            <table class="border-collapse text-left" style="width:max-content;min-width:100%">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100">
                            <th class="px-3 py-3 text-[9px] font-black uppercase tracking-widest text-slate-400 min-w-[120px]">Lokasi</th>
                            <th class="px-3 py-3 text-[9px] font-black uppercase tracking-widest text-slate-400 min-w-[200px]">Device</th>
                            <th class="px-3 py-3 text-[9px] font-black uppercase tracking-widest text-slate-400 border-r border-slate-200 min-w-[120px]">Group</th>
                            <th v-for="m in (backupData?.months ?? [])" :key="m.year+'-'+m.month" :style="backupColStyle" class="px-1 py-3 text-center text-[9px] font-black uppercase tracking-widest text-slate-400">
                                {{ m.label }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-for="row in (backupData?.grid ?? [])" :key="row.device_id" class="hover:bg-slate-50/40 transition-colors group">
                            <td class="px-3 py-2 text-[10px] font-bold text-slate-600 whitespace-nowrap">{{ row.location }}</td>
                            <td class="px-3 py-2 text-[11px] font-bold text-slate-800 whitespace-nowrap">{{ row.device_name }}</td>
                            <td class="px-3 py-2 text-[10px] text-slate-400 border-r border-slate-100 whitespace-nowrap">{{ row.display_group }}</td>
                            <td v-for="(m, mi) in row.months" :key="mi" :style="backupColStyle" class="px-0.5 py-1.5 text-center">
                                <button type="button" class="w-full h-7 rounded-lg text-[10px] font-black transition-all hover:ring-2 hover:ring-[#003628]/20 cursor-pointer" :class="m.has_backup===true?'bg-emerald-50 text-emerald-700 border border-emerald-100':m.has_backup===false?'bg-slate-50 text-slate-300 border border-slate-100':'bg-slate-50 text-slate-300 border border-dashed border-slate-200'" :disabled="savingBackup" @click="toggleBackup(row.device_id, m.year, m.month, m.has_backup)">
                                    {{ m.has_backup===true ? "B" : "---" }}
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!(backupData?.grid?.length)">
                            <td colspan="40" class="px-5 py-16 text-center text-[10px] font-black uppercase tracking-widest text-slate-300">Belum ada device dipantau - klik Settings untuk memilih device</td>
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
                            <td class="px-4 py-3 text-[10px] text-slate-500 max-w-[160px] truncate">{{ log.notes ?? '—' }}</td>
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
<div v-else class="bg-white rounded-2xl border border-dashed border-slate-200 py-20 text-center">
        <Activity class="size-10 text-slate-200 mx-auto mb-3"/>
        <p class="text-[11px] font-black uppercase tracking-widest text-slate-300">Belum ada data</p>
    </div>

    <!-- BACKUP SETTINGS MODAL -->
    <Teleport to="body">
        <div v-if="props.showSettingsModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4" @click.self="showSettings=false">
            <div class="bg-white w-full max-w-3xl rounded-2xl shadow-2xl overflow-hidden border border-slate-200 max-h-[90vh] flex flex-col">
                <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 shrink-0">
                    <div>
                        <h3 class="text-sm font-black text-slate-800 flex items-center gap-2"><Settings class="size-4 text-[#003628]"/> Backup Monitoring Settings</h3>
                        <p class="text-[10px] text-slate-400 mt-0.5">Pilih device mana yang akan dipantau backup config-nya</p>
                    </div>
                    <button type="button" class="h-8 w-8 rounded-xl border border-slate-200 flex items-center justify-center text-slate-400 hover:text-slate-700 transition-all" @click="showSettings=false"><X class="size-4"/></button>
                </div>
                <div class="px-6 py-3 border-b border-slate-100 shrink-0">
                    <div class="relative">
                        <Search class="size-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"/>
                        <input v-model="settingsSearch" type="text" placeholder="Cari device, group, atau lokasi..." class="w-full h-9 pl-8 pr-3 rounded-xl border border-slate-200 bg-slate-50 text-[10px] font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#003628]/20"/>
                    </div>
                </div>
                <div v-if="settingsLoading" class="flex-1 flex items-center justify-center py-20">
                    <Loader2 class="size-6 text-slate-300 animate-spin"/>
                </div>
                <div v-else class="flex-1 overflow-y-auto p-6 space-y-5">
                    <div v-for="(devs, loc) in settingsByLocation" :key="loc">
                        <div class="flex items-center gap-2 mb-2">
                            <input type="checkbox" :id="'loc-'+loc" :checked="isLocationAllSelected(loc)" @change="toggleLocationAll(loc)" class="h-4 w-4 rounded border-slate-300 text-[#003628] focus:ring-[#003628]/20"/>
                            <label :for="'loc-'+loc" class="text-[10px] font-black uppercase tracking-widest text-slate-600 cursor-pointer">{{ loc }}</label>
                            <span class="text-[9px] font-bold text-slate-400">{{ devs.length }} device</span>
                        </div>
                        <div class="grid grid-cols-2 gap-2 pl-6">
                            <label v-for="d in devs" :key="d.id" class="flex items-start gap-2 p-2 rounded-lg border border-slate-100 hover:bg-slate-50 transition-all cursor-pointer" :class="settingsSelected.has(d.id)?'bg-emerald-50 border-emerald-200':''">
                                <input type="checkbox" :checked="settingsSelected.has(d.id)" @change="toggleSettingsDevice(d.id)" class="h-4 w-4 rounded border-slate-300 text-[#003628] focus:ring-[#003628]/20 mt-0.5 shrink-0"/>
                                <div class="flex-1 min-w-0">
                                    <p class="text-[11px] font-bold text-slate-800 truncate">{{ d.device_name }}</p>
                                    <p class="text-[9px] text-slate-400 truncate">{{ d.display_group }} - {{ d.ip_address }}</p>
                                </div>
                            </label>
                        </div>
                    </div>
                    <div v-if="!Object.keys(settingsByLocation).length" class="py-10 text-center">
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-300">Tidak ada device ditemukan</p>
                    </div>
                </div>
                <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-between shrink-0">
                    <p class="text-[10px] font-bold text-slate-500">{{ settingsSelected.size }} device dipilih</p>
                    <div class="flex gap-3">
                        <button type="button" class="h-9 px-4 rounded-xl border border-slate-200 bg-slate-50 text-slate-600 text-[10px] font-black uppercase tracking-widest hover:bg-slate-100 transition-all" @click="showSettings=false">Batal</button>
                        <button type="button" :disabled="settingsSaving" class="h-9 px-4 rounded-xl bg-[#003628] text-white text-[10px] font-black uppercase tracking-widest hover:brightness-110 transition-all disabled:opacity-50 flex items-center gap-2 shadow-md shadow-[#003628]/20" @click="saveSettings">
                            <Loader2 v-if="settingsSaving" class="size-3 animate-spin"/><Save v-else class="size-3"/> Simpan
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>

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
                        <p class="text-[10px] font-mono text-slate-500">{{ editingMaintLog.ip_address }} • {{ editingLog?.location }}</p>
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
</div>
</template>
