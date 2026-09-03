<script setup lang="ts">
import { ref, computed, watch, onMounted } from "vue";
import {
    Loader2, CheckCircle2, XCircle, AlertTriangle,
    Clock, Server, X, Save, WrenchIcon, Search,
    ChevronUp, ChevronDown, ChevronsUpDown, AlertCircle, Plus, EyeOff, Eye, FileText, Trash2,
} from "lucide-vue-next";

const props = defineProps<{
    filterFrom: string;
    filterTo: string;
    applyTrigger: number;
}>();

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

const serverDevices = ref<any[]>([]);

async function loadDevices() {
    const res = await fetch("/server-operation/data?summary=1"); // We just need the list
    if (res.ok) {
        const d = await res.json();
        serverDevices.value = d.devices ?? [];
    }
}

async function loadMaintLogs() {
    maintLoading.value = true;
    try {
        const res = await fetch(`/server-operation/maintenance-logs?from=${props.filterFrom}&to=${props.filterTo}`);
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
        const url    = editingMaintLog.value ? `/server-operation/maintenance-logs/${editingMaintLog.value.id}` : "/server-operation/maintenance-logs";
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
            method, headers: { "Content-Type":"application/json","Accept":"application/json","X-CSRF-TOKEN":csrf },
            body: JSON.stringify(payload),
        });
        if (res.ok) { showMaintForm.value = false; await loadMaintLogs(); }
    } finally { savingMaintLog.value = false; }
}

async function closeMaintLog(log: any) {
    const csrf = (document.querySelector("meta[name=csrf-token]") as HTMLMetaElement)?.content ?? "";
    await fetch(`/server-operation/maintenance-logs/${log.id}`, {
        method: "PUT", headers: { "Content-Type":"application/json","Accept":"application/json","X-CSRF-TOKEN":csrf },
        body: JSON.stringify({ status: "closed", resolved_at: new Date().toISOString().slice(0,10) }),
    });
    await loadMaintLogs();
}

async function deleteMaintLog(id: number) {
    if (!confirm("Hapus log ini?")) return;
    const csrf = (document.querySelector("meta[name=csrf-token]") as HTMLMetaElement)?.content ?? "";
    await fetch(`/server-operation/maintenance-logs/${id}`, { method: "DELETE", headers: { "X-CSRF-TOKEN":csrf } });
    await loadMaintLogs();
}

function maintStatusBadge(s: string) {
    return s === "open" ? "bg-rose-50 text-rose-600 border-rose-100" : "bg-emerald-50 text-emerald-600 border-emerald-100";
}

onMounted(() => {
    loadMaintLogs();
    loadDevices();
});
watch(() => props.applyTrigger, () => loadMaintLogs());

</script>

<template>
<div class="space-y-5">
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
            <div class="overflow-x-auto w-full">
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
            </div>

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

    <!-- MAINTENANCE FORM MODAL -->
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
                            <option v-for="dev in serverDevices" :key="dev.id" :value="String(dev.id)">{{ dev.device_name }} ({{ dev.location }})</option>
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
