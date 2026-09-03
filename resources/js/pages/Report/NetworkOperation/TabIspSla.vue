<script setup lang="ts">
import { ref, computed, watch, onMounted } from "vue";
import { Shield, Plus, Pencil, Trash2, CheckCircle2, XCircle, AlertTriangle, X, Save, Loader2, MapPin, Clock, Settings } from "lucide-vue-next";

const props = defineProps<{ locations: string[]; filterFrom: string; filterTo: string; applyTrigger: number }>();

const loading      = ref(false);
const slaData      = ref<any>(null);
const downHistory  = ref<any[]>([]);
const historyPage  = ref(1);
const historyPerPage = 10;
const historyPages = computed(() => Math.max(1, Math.ceil(downHistory.value.length / historyPerPage)));
const pagedHistory = computed(() => {
    const start = (historyPage.value - 1) * historyPerPage;
    return downHistory.value.slice(start, start + historyPerPage);
});
const contracts    = ref<any[]>([]);

// Inline edit
const editingCell  = ref<{ contractId: number; year: number; month: number } | null>(null);
const editValue    = ref("");
const editNotes    = ref("");
const savingCell   = ref(false);

// Down history modal
const showAddIncident = ref(false);
const incidentForm    = ref({ contract_id: "", incident_date: "", case_description: "", action_taken: "", duration_minutes: "" });
const savingIncident  = ref(false);

const showManageContracts = ref(false);
const contractForm = ref({ location: "", fct: "", provider: "", bandwidth: "", target_pct: "99.00", sort_order: "0" });
const editingContractId = ref<number | null>(null);  // store ID separately to avoid reactivity issues
const editingContract   = ref<any>(null);
const savingContract    = ref(false);

function openAddContract() {
    editingContractId.value = null;
    editingContract.value   = null;
    contractForm.value = { location: "", fct: "", provider: "", bandwidth: "", target_pct: "99.00", sort_order: "0" };
}

function openEditContract(c: any) {
    editingContractId.value = c.id;
    editingContract.value   = c;
    contractForm.value = {
        location: c.location, fct: c.fct, provider: c.provider,
        bandwidth: c.bandwidth, target_pct: String(c.target_pct), sort_order: String(c.sort_order ?? 0),
    };
}

async function saveContract() {
    savingContract.value = true;
    const csrf = (document.querySelector("meta[name=csrf-token]") as HTMLMetaElement)?.content ?? "";
    const body = { ...contractForm.value, target_pct: parseFloat(contractForm.value.target_pct), sort_order: parseInt(contractForm.value.sort_order) };
    const id   = editingContractId.value;  // capture before any reset
    try {
        const url    = id ? `/isp-sla/contracts/${id}` : "/isp-sla/contracts";
        const method = id ? "PUT" : "POST";
        const res    = await fetch(url, {
            method,
            headers: { "Content-Type": "application/json", "Accept": "application/json", "X-CSRF-TOKEN": csrf },
            body: JSON.stringify(body),
        });
        const data = await res.json();
        if (res.ok) {
            editingContractId.value = null;
            editingContract.value   = null;
            contractForm.value = { location: "", fct: "", provider: "", bandwidth: "", target_pct: "99.00", sort_order: "0" };
            await loadData();
        } else {
            console.error("[saveContract] Error:", res.status, data);
            alert("Gagal menyimpan: " + (data.message ?? JSON.stringify(data)));
        }
    } catch (e) {
        console.error("[saveContract] Exception:", e);
    } finally { savingContract.value = false; }
}

async function toggleContractActive(c: any) {
    const csrf = (document.querySelector("meta[name=csrf-token]") as HTMLMetaElement)?.content ?? "";
    await fetch(`/isp-sla/contracts/${c.id}`, {
        method: "PUT",
        headers: { "Content-Type": "application/json", "Accept": "application/json", "X-CSRF-TOKEN": csrf },
        body: JSON.stringify({ is_active: !c.is_active }),
    });
    await loadData();
}

// (removed — use inline cell click instead)

// Month options for select
const monthOptions = [
    { v: 1, l: "Januari" }, { v: 2, l: "Februari" }, { v: 3, l: "Maret" },
    { v: 4, l: "April" },   { v: 5, l: "Mei" },       { v: 6, l: "Juni" },
    { v: 7, l: "Juli" },    { v: 8, l: "Agustus" },   { v: 9, l: "September" },
    { v: 10, l: "Oktober" },{ v: 11, l: "November" }, { v: 12, l: "Desember" },
];

const dragIndex   = ref<number | null>(null);
const dragOverIdx = ref<number | null>(null);

function onDragStart(idx: number) { dragIndex.value = idx; }
function onDragOver(idx: number)  { dragOverIdx.value = idx; }
function onDragEnd()              { dragIndex.value = null; dragOverIdx.value = null; }

async function onDrop(targetIdx: number) {
    if (dragIndex.value === null || dragIndex.value === targetIdx) { onDragEnd(); return; }
    const reordered = [...contracts.value];
    const [moved]   = reordered.splice(dragIndex.value, 1);
    reordered.splice(targetIdx, 0, moved);
    contracts.value = reordered;
    onDragEnd();

    // Persist new order
    const csrf = (document.querySelector("meta[name=csrf-token]") as HTMLMetaElement)?.content ?? "";
    await fetch("/isp-sla/contracts/reorder", {
        method: "POST",
        headers: { "Content-Type": "application/json", "Accept": "application/json", "X-CSRF-TOKEN": csrf },
        body: JSON.stringify({ ids: reordered.map((c: any) => c.id) }),
    });
}
function monthLabel(year: number, month: number) {
    return new Date(year, month - 1, 1).toLocaleDateString("id-ID", { month: "short", year: "2-digit" });
}

function uptimeColor(pct: number | null, target: number) {
    if (pct === null) return "bg-slate-100 text-slate-400";
    if (pct >= target) return "bg-emerald-50 text-emerald-700 font-black";
    if (pct >= target - 1) return "bg-amber-50 text-amber-700 font-black";
    return "bg-rose-50 text-rose-700 font-black";
}

function cardColor(onSla: boolean | null) {
    if (onSla === null) return "border-slate-100";
    return onSla ? "border-emerald-200" : "border-rose-200";
}

async function loadData() {
    loading.value = true;
    try {
        const [slaRes, histRes, ctrRes] = await Promise.all([
            fetch(`/isp-sla/data?from=${props.filterFrom}&to=${props.filterTo}`),
            fetch(`/isp-sla/down-history?from=${props.filterFrom}&to=${props.filterTo}`),
            fetch("/isp-sla/contracts"),  // all contracts for management modal
        ]);
        if (slaRes.ok)  slaData.value    = await slaRes.json();
        if (histRes.ok) downHistory.value = await histRes.json();
        if (ctrRes.ok)  contracts.value   = await ctrRes.json();
    } finally { loading.value = false; }
}

// Active-only contracts for dropdowns
const activeContracts = computed(() => contracts.value.filter((c: any) => c.is_active));

onMounted(() => loadData());
watch(() => props.applyTrigger, () => { loadData(); historyPage.value = 1; });

function startEdit(contractId: number, year: number, month: number, currentPct: number | null, currentNotes: string | null) {
    editingCell.value = { contractId, year, month };
    editValue.value   = currentPct !== null ? String(currentPct) : "";
    editNotes.value   = currentNotes ?? "";
}

function cancelEdit() { editingCell.value = null; }

async function saveEdit() {
    if (!editingCell.value) return;
    savingCell.value = true;
    const csrf = (document.querySelector("meta[name=csrf-token]") as HTMLMetaElement)?.content ?? "";
    try {
        const res = await fetch("/isp-sla/monthly", {
            method: "PUT",
            headers: { "Content-Type": "application/json", "Accept": "application/json", "X-CSRF-TOKEN": csrf },
            body: JSON.stringify({
                contract_id: editingCell.value.contractId,
                year:        editingCell.value.year,
                month:       editingCell.value.month,
                uptime_pct:  parseFloat(editValue.value),
                notes:       editNotes.value || null,
            }),
        });
        if (res.ok) { await loadData(); editingCell.value = null; }
    } finally { savingCell.value = false; }
}

async function saveIncident() {
    savingIncident.value = true;
    const csrf = (document.querySelector("meta[name=csrf-token]") as HTMLMetaElement)?.content ?? "";
    try {
        const res = await fetch("/isp-sla/down-history", {
            method: "POST",
            headers: { "Content-Type": "application/json", "Accept": "application/json", "X-CSRF-TOKEN": csrf },
            body: JSON.stringify({ ...incidentForm.value, duration_minutes: parseInt(incidentForm.value.duration_minutes) }),
        });
        if (res.ok) {
            showAddIncident.value = false;
            incidentForm.value = { contract_id: "", incident_date: "", case_description: "", action_taken: "", duration_minutes: "" };
            await loadData();
        }
    } finally { savingIncident.value = false; }
}

async function deleteIncident(id: number) {
    if (!confirm("Hapus insiden ini?")) return;
    const csrf = (document.querySelector("meta[name=csrf-token]") as HTMLMetaElement)?.content ?? "";
    await fetch(`/isp-sla/down-history/${id}`, { method: "DELETE", headers: { "X-CSRF-TOKEN": csrf } });
    await loadData();
}
</script>
<template>
    <div class="space-y-5 w-full min-w-0 max-w-full">

        <!-- Loading -->
        <div v-if="loading" class="space-y-3">
            <div v-for="n in 3" :key="n" class="h-20 bg-white rounded-2xl border border-slate-100 animate-pulse"></div>
        </div>

        <template v-else-if="slaData">

            <!-- Location summary cards — 1 row grid -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3">
                <div v-for="loc in slaData.location_summary" :key="loc.location"
                    class="bg-white rounded-2xl border shadow-sm p-4 flex items-center gap-3.5 hover:shadow-md transition-all"
                    :class="cardColor(loc.on_sla)">
                    <div class="h-10 w-10 rounded-xl flex items-center justify-center shrink-0"
                        :class="loc.on_sla===true?'bg-emerald-50 border border-emerald-100':loc.on_sla===false?'bg-rose-50 border border-rose-100':'bg-slate-50 border border-slate-100'">
                        <CheckCircle2 v-if="loc.on_sla===true"  class="size-5 text-emerald-600" />
                        <XCircle      v-else-if="loc.on_sla===false" class="size-5 text-rose-600" />
                        <Shield       v-else class="size-5 text-slate-400" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-[9px] font-black uppercase tracking-widest text-slate-400 truncate">{{ loc.location }}</p>
                        <p class="text-[18px] font-black tabular-nums leading-tight mt-0.5"
                            :class="loc.on_sla===true?'text-emerald-600':loc.on_sla===false?'text-rose-600':'text-slate-400'">
                            {{ loc.avg_pct !== null ? loc.avg_pct.toFixed(2) + "%" : "—" }}
                        </p>
                        <p class="text-[9px] font-bold mt-0.5"
                            :class="loc.on_sla===true?'text-emerald-500':loc.on_sla===false?'text-rose-500':'text-slate-400'">
                            {{ loc.on_sla===true ? "On SLA" : loc.on_sla===false ? "SLA Breach" : "No Data" }}
                            <span class="text-slate-300 ml-1">· {{ loc.target }}%</span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- SLA Performance Table -->
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
                    <div class="flex items-center gap-2">
                        <Shield class="size-4 text-[#003628]" />
                        <p class="text-sm font-black text-slate-800">SLA Performance</p>
                        <span class="px-2 py-0.5 rounded-full bg-slate-100 text-[9px] font-black text-slate-500 tabular-nums">{{ filterFrom }} — {{ filterTo }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="hidden sm:flex items-center gap-2 text-[9px] font-bold text-slate-400 mr-2">
                            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-emerald-400 inline-block"></span> On SLA</span>
                            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-rose-400 inline-block"></span> Breach</span>
                            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-slate-200 inline-block"></span> No Data</span>
                        </div>
                        <button type="button" class="h-8 px-3 rounded-xl border border-slate-200 bg-white text-slate-600 text-[10px] font-black uppercase tracking-widest hover:bg-slate-50 transition-all flex items-center gap-1.5" @click="showManageContracts=true">
                            <Settings class="size-3.5" /> Kelola ISP
                        </button>
                    </div>
                </div>
                <div class="overflow-x-auto w-full max-w-full">
                    <table class="w-full border-collapse text-left">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100">
                                <th class="px-4 py-3 text-[9px] font-black uppercase tracking-widest text-slate-400 min-w-[140px]">Location</th>
                                <th class="px-3 py-3 text-[9px] font-black uppercase tracking-widest text-slate-400">Fct</th>
                                <th class="px-3 py-3 text-[9px] font-black uppercase tracking-widest text-slate-400">Provider</th>
                                <th class="px-3 py-3 text-[9px] font-black uppercase tracking-widest text-slate-400">B/W</th>
                                <th class="px-3 py-3 text-[9px] font-black uppercase tracking-widest text-slate-400 text-right">Target</th>
                                <th class="px-3 py-3 text-[9px] font-black uppercase tracking-widest text-slate-400 text-right">Avg</th>
                                <th v-for="m in slaData.months" :key="m.year+'-'+m.month"
                                    class="px-2 py-3 text-[9px] font-black uppercase tracking-widest text-slate-400 text-center min-w-[72px]">
                                    {{ monthLabel(m.year, m.month) }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <tr v-for="row in slaData.rows" :key="row.id" class="hover:bg-slate-50/40 transition-colors group">
                                <td class="px-4 py-3 text-[11px] font-bold text-slate-800 whitespace-nowrap">
                                    <span class="flex items-center gap-1.5">
                                        <MapPin class="size-3 text-slate-300 shrink-0" />{{ row.location }}
                                    </span>
                                </td>
                                <td class="px-3 py-3 text-[10px] font-bold text-slate-500">{{ row.fct }}</td>
                                <td class="px-3 py-3">
                                    <span class="px-2 py-0.5 rounded-full bg-slate-50 border border-slate-100 text-[9px] font-black uppercase tracking-widest text-slate-600">{{ row.provider }}</span>
                                </td>
                                <td class="px-3 py-3 text-[10px] font-mono text-slate-500">{{ row.bandwidth }}</td>
                                <td class="px-3 py-3 text-[11px] font-black text-slate-700 text-right tabular-nums">{{ row.target_pct }}%</td>
                                <td class="px-3 py-3 text-right">
                                    <span class="text-[11px] font-black tabular-nums"
                                        :class="row.on_sla===true?'text-emerald-600':row.on_sla===false?'text-rose-600':'text-slate-400'">
                                        {{ row.avg_pct !== null ? row.avg_pct.toFixed(2) + "%" : "—" }}
                                    </span>
                                </td>
                                <!-- Month cells -->
                                <td v-for="(m, mi) in row.months" :key="mi" class="px-1 py-2 text-center">
                                    <!-- Editing state -->
                                    <div v-if="editingCell && editingCell.contractId===row.id && editingCell.year===m.year && editingCell.month===m.month"
                                        class="flex flex-col gap-1 min-w-[120px]">
                                        <input v-model="editValue" type="number" step="0.001" min="0" max="100"
                                            class="w-full h-7 px-2 rounded-lg border border-[#003628]/30 bg-white text-[11px] font-bold text-center focus:outline-none focus:ring-2 focus:ring-[#003628]/20"
                                            placeholder="99.99" @keydown.enter="saveEdit" @keydown.escape="cancelEdit" />
                                        <div class="flex gap-1">
                                            <button type="button" :disabled="savingCell" class="flex-1 h-6 rounded-lg bg-[#003628] text-white text-[8px] font-black flex items-center justify-center" @click="saveEdit">
                                                <Loader2 v-if="savingCell" class="size-3 animate-spin" />
                                                <Save v-else class="size-3" />
                                            </button>
                                            <button type="button" class="flex-1 h-6 rounded-lg bg-slate-100 text-slate-500 text-[8px] font-black flex items-center justify-center" @click="cancelEdit">
                                                <X class="size-3" />
                                            </button>
                                        </div>
                                    </div>
                                    <!-- Display state -->
                                    <button v-else type="button"
                                        class="w-full min-w-[64px] h-7 rounded-lg text-[10px] font-bold tabular-nums transition-all hover:ring-2 hover:ring-[#003628]/20 cursor-pointer"
                                        :class="uptimeColor(m.uptime_pct, row.target_pct)"
                                        :title="m.uptime_pct !== null ? m.uptime_pct + '% — klik untuk edit' + (m.notes ? '\n' + m.notes : '') : 'Klik untuk input data'"
                                        @click="startEdit(row.id, m.year, m.month, m.uptime_pct, m.notes)">
                                        {{ m.uptime_pct !== null ? m.uptime_pct.toFixed(2) + "%" : "+" }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>




            <!-- Down History -->
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
                    <div class="flex items-center gap-2">
                        <Clock class="size-4 text-[#003628]" />
                        <p class="text-sm font-black text-slate-800">Down History</p>
                        <span class="px-2 py-0.5 rounded-full bg-slate-100 text-[9px] font-black text-slate-500">{{ downHistory.length }} insiden</span>
                    </div>
                    <button type="button" class="h-8 px-4 rounded-xl bg-[#003628] text-white text-[10px] font-black uppercase tracking-widest hover:brightness-110 transition-all flex items-center gap-1.5 shadow-sm shadow-[#003628]/20" @click="showAddIncident=true">
                        <Plus class="size-3.5" /> Tambah Insiden
                    </button>
                </div>

                <div v-if="downHistory.length===0" class="py-12 text-center">
                    <CheckCircle2 class="size-8 text-emerald-200 mx-auto mb-2" />
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-300">Tidak ada insiden</p>
                </div>

                <div v-else>
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100">
                            <th class="px-5 py-3 text-left text-[9px] font-black uppercase tracking-widest text-slate-400">No</th>
                            <th class="px-5 py-3 text-left text-[9px] font-black uppercase tracking-widest text-slate-400">Tanggal</th>
                            <th class="px-5 py-3 text-left text-[9px] font-black uppercase tracking-widest text-slate-400">Location</th>
                            <th class="px-5 py-3 text-left text-[9px] font-black uppercase tracking-widest text-slate-400">Provider</th>
                            <th class="px-5 py-3 text-left text-[9px] font-black uppercase tracking-widest text-slate-400">Case</th>
                            <th class="px-5 py-3 text-left text-[9px] font-black uppercase tracking-widest text-slate-400">Action</th>
                            <th class="px-5 py-3 text-right text-[9px] font-black uppercase tracking-widest text-slate-400">Durasi</th>
                            <th class="px-5 py-3 text-center text-[9px] font-black uppercase tracking-widest text-slate-400">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-for="(inc, idx) in pagedHistory" :key="inc.id" class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-5 py-3 text-[10px] font-bold text-slate-400 tabular-nums">{{ (historyPage-1)*historyPerPage + idx + 1 }}</td>
                            <td class="px-5 py-3 text-[11px] font-bold text-slate-700 tabular-nums">{{ inc.incident_date }}</td>
                            <td class="px-5 py-3 text-[11px] font-bold text-slate-800">{{ inc.location }} <span class="text-slate-400 font-normal">{{ inc.fct }}</span></td>
                            <td class="px-5 py-3"><span class="px-2 py-0.5 rounded-full bg-slate-50 border border-slate-100 text-[9px] font-black uppercase tracking-widest text-slate-600">{{ inc.provider }}</span></td>
                            <td class="px-5 py-3 text-[10px] text-slate-600 max-w-[200px] truncate">{{ inc.case_description || "—" }}</td>
                            <td class="px-5 py-3 text-[10px] text-slate-600 max-w-[200px] truncate">{{ inc.action_taken || "—" }}</td>
                            <td class="px-5 py-3 text-right">
                                <span class="px-2 py-1 rounded-lg bg-rose-50 border border-rose-100 text-[9px] font-black text-rose-600 tabular-nums">{{ inc.duration_label }}</span>
                            </td>
                            <td class="px-5 py-3 text-center">
                                <button type="button" class="h-7 w-7 rounded-lg bg-rose-50 text-rose-500 hover:bg-rose-100 transition-all flex items-center justify-center mx-auto" @click="deleteIncident(inc.id)">
                                    <Trash2 class="size-3.5" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <!-- Pagination -->
                <div v-if="historyPages > 1" class="flex items-center justify-between px-5 py-3 border-t border-slate-100 bg-slate-50/40">
                    <span class="text-[10px] font-bold text-slate-400">{{ (historyPage-1)*historyPerPage+1 }}-{{ Math.min(historyPage*historyPerPage, downHistory.length) }} dari {{ downHistory.length }}</span>
                    <div class="flex items-center gap-1">
                        <button type="button" :disabled="historyPage===1" class="h-7 w-7 rounded-lg border border-slate-200 bg-white text-slate-500 text-[10px] font-black disabled:opacity-40 hover:bg-slate-50 transition-all flex items-center justify-center" @click="historyPage--">&lt;</button>
                        <button v-for="p in historyPages" :key="p" type="button" class="h-7 w-7 rounded-lg border text-[10px] font-black transition-all" :class="p===historyPage?'bg-[#003628] text-white border-[#003628]':'border-slate-200 bg-white text-slate-500 hover:bg-slate-50'" @click="historyPage=p">{{ p }}</button>
                        <button type="button" :disabled="historyPage===historyPages" class="h-7 w-7 rounded-lg border border-slate-200 bg-white text-slate-500 text-[10px] font-black disabled:opacity-40 hover:bg-slate-50 transition-all flex items-center justify-center" @click="historyPage++">&gt;</button>
                    </div>
                </div>
                </div>
            </div>

        </template>

        <!-- Empty state -->
        <div v-else class="bg-white rounded-2xl border border-dashed border-slate-200 py-20 text-center">
            <Shield class="size-10 text-slate-200 mx-auto mb-3" />
            <p class="text-[11px] font-black uppercase tracking-widest text-slate-300">Belum ada data ISP SLA</p>
            <p class="text-[10px] text-slate-300 mt-1">Tambahkan kontrak ISP terlebih dahulu</p>
        </div>

        <!-- Add Incident Modal -->
        <Teleport to="body">
            <div v-if="showAddIncident" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4" @click.self="showAddIncident=false">                <div class="bg-white w-full max-w-lg rounded-2xl shadow-2xl overflow-hidden border border-slate-200">
                    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
                        <h3 class="text-sm font-black text-slate-800 flex items-center gap-2"><Plus class="size-4 text-[#003628]" /> Tambah Insiden</h3>
                        <button type="button" class="h-8 w-8 rounded-xl border border-slate-200 flex items-center justify-center text-slate-400 hover:text-slate-700 transition-all" @click="showAddIncident=false">
                            <X class="size-4" />
                        </button>
                    </div>
                    <div class="p-6 space-y-4">
                        <div class="space-y-1.5">
                            <label class="text-[9px] font-black uppercase tracking-widest text-slate-400">ISP / Circuit</label>
                            <select v-model="incidentForm.contract_id" class="w-full h-9 px-3 rounded-xl border border-slate-200 bg-slate-50 text-[11px] font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#003628]/20">
                                <option value="">Pilih ISP...</option>
                                <option v-for="c in activeContracts" :key="c.id" :value="c.id">{{ c.location }} — {{ c.fct }} {{ c.provider }} ({{ c.bandwidth }})</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1.5">
                                <label class="text-[9px] font-black uppercase tracking-widest text-slate-400">Tanggal Insiden</label>
                                <input v-model="incidentForm.incident_date" type="date" class="w-full h-9 px-3 rounded-xl border border-slate-200 bg-slate-50 text-[11px] font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#003628]/20" />
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-[9px] font-black uppercase tracking-widest text-slate-400">Durasi (menit)</label>
                                <input v-model="incidentForm.duration_minutes" type="number" min="1" placeholder="60" class="w-full h-9 px-3 rounded-xl border border-slate-200 bg-slate-50 text-[11px] font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#003628]/20" />
                            </div>
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-[9px] font-black uppercase tracking-widest text-slate-400">Deskripsi Kasus</label>
                            <textarea v-model="incidentForm.case_description" rows="2" placeholder="Jelaskan penyebab downtime..." class="w-full px-3 py-2 rounded-xl border border-slate-200 bg-slate-50 text-[11px] font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#003628]/20 resize-none"></textarea>
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-[9px] font-black uppercase tracking-widest text-slate-400">Tindakan yang Diambil</label>
                            <textarea v-model="incidentForm.action_taken" rows="2" placeholder="Tindakan penanganan..." class="w-full px-3 py-2 rounded-xl border border-slate-200 bg-slate-50 text-[11px] font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#003628]/20 resize-none"></textarea>
                        </div>
                        <div class="flex gap-3 pt-2">
                            <button type="button" class="flex-1 h-9 rounded-xl border border-slate-200 bg-slate-50 text-slate-600 text-[10px] font-black uppercase tracking-widest hover:bg-slate-100 transition-all" @click="showAddIncident=false">Batal</button>
                            <button type="button" :disabled="savingIncident || !incidentForm.contract_id || !incidentForm.incident_date || !incidentForm.duration_minutes" class="flex-1 h-9 rounded-xl bg-[#003628] text-white text-[10px] font-black uppercase tracking-widest hover:brightness-110 transition-all disabled:opacity-50 flex items-center justify-center gap-2" @click="saveIncident">
                                <Loader2 v-if="savingIncident" class="size-3 animate-spin" />
                                <Save v-else class="size-3" />
                                Simpan
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Manage Contracts Modal -->
        <Teleport to="body">
            <div v-if="showManageContracts" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4" @click.self="showManageContracts=false">
                <div class="bg-white w-full max-w-2xl rounded-2xl shadow-2xl overflow-hidden border border-slate-200 flex flex-col max-h-[85vh]">
                    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 shrink-0">
                        <h3 class="text-sm font-black text-slate-800 flex items-center gap-2">
                            <Settings class="size-4 text-[#003628]" /> Kelola Kontrak ISP
                        </h3>
                        <button type="button" class="h-8 w-8 rounded-xl border border-slate-200 flex items-center justify-center text-slate-400 hover:text-slate-700 transition-all" @click="showManageContracts=false">
                            <X class="size-4" />
                        </button>
                    </div>

                    <!-- Add/Edit form -->
                    <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 shrink-0">
                        <p class="text-[9px] font-black uppercase tracking-widest text-slate-400 mb-3">
                            {{ editingContract ? "Edit Kontrak" : "Tambah Kontrak Baru" }}
                        </p>
                        <div class="grid grid-cols-3 gap-2">
                            <input v-model="contractForm.location" type="text" placeholder="Location (F1 Bogor)" class="h-8 px-3 rounded-xl border border-slate-200 bg-white text-[11px] font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#003628]/20" />
                            <input v-model="contractForm.fct" type="text" placeholder="Fct (F1)" class="h-8 px-3 rounded-xl border border-slate-200 bg-white text-[11px] font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#003628]/20" />
                            <input v-model="contractForm.provider" type="text" placeholder="Provider (ISAT)" class="h-8 px-3 rounded-xl border border-slate-200 bg-white text-[11px] font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#003628]/20" />
                            <input v-model="contractForm.bandwidth" type="text" placeholder="B/W (180 Mbps)" class="h-8 px-3 rounded-xl border border-slate-200 bg-white text-[11px] font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#003628]/20" />
                            <input v-model="contractForm.target_pct" type="number" step="0.01" min="0" max="100" placeholder="Target % (99.50)" class="h-8 px-3 rounded-xl border border-slate-200 bg-white text-[11px] font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#003628]/20" />
                            <input v-model="contractForm.sort_order" type="number" placeholder="Urutan (0)" class="h-8 px-3 rounded-xl border border-slate-200 bg-white text-[11px] font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#003628]/20" />
                        </div>
                        <div class="flex gap-2 mt-3">
                            <button type="button" :disabled="savingContract || !contractForm.location || !contractForm.provider" class="h-8 px-4 rounded-xl bg-[#003628] text-white text-[10px] font-black uppercase tracking-widest hover:brightness-110 transition-all disabled:opacity-50 flex items-center gap-1.5" @click="saveContract">
                                <Loader2 v-if="savingContract" class="size-3 animate-spin" />
                                <Save v-else class="size-3" />
                                {{ editingContract ? "Update" : "Tambah" }}
                            </button>
                            <button v-if="editingContract" type="button" class="h-8 px-4 rounded-xl border border-slate-200 bg-white text-slate-600 text-[10px] font-black uppercase tracking-widest hover:bg-slate-50 transition-all" @click="editingContract=null; contractForm={location:'',fct:'',provider:'',bandwidth:'',target_pct:'99.00',sort_order:'0'}">
                                Batal Edit
                            </button>
                        </div>
                    </div>

                    <!-- Contract list -->
                    <div class="overflow-y-auto flex-1">
                        <table class="w-full border-collapse">
                            <thead class="sticky top-0">
                                <tr class="bg-slate-50 border-b border-slate-100">
                                    <th class="px-4 py-2.5 text-left text-[9px] font-black uppercase tracking-widest text-slate-400">Location</th>
                                    <th class="px-3 py-2.5 text-left text-[9px] font-black uppercase tracking-widest text-slate-400">Fct</th>
                                    <th class="px-3 py-2.5 text-left text-[9px] font-black uppercase tracking-widest text-slate-400">Provider</th>
                                    <th class="px-3 py-2.5 text-left text-[9px] font-black uppercase tracking-widest text-slate-400">B/W</th>
                                    <th class="px-3 py-2.5 text-right text-[9px] font-black uppercase tracking-widest text-slate-400">Target</th>
                                    <th class="px-3 py-2.5 text-center text-[9px] font-black uppercase tracking-widest text-slate-400">Status</th>
                                    <th class="px-3 py-2.5 text-center text-[9px] font-black uppercase tracking-widest text-slate-400">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <tr v-for="c in contracts" :key="c.id" class="hover:bg-slate-50/50 transition-colors" :class="!c.is_active ? 'opacity-50' : ''">
                                    <td class="px-4 py-2.5 text-[11px] font-bold text-slate-800">{{ c.location }}</td>
                                    <td class="px-3 py-2.5 text-[10px] text-slate-500">{{ c.fct }}</td>
                                    <td class="px-3 py-2.5"><span class="px-2 py-0.5 rounded-full bg-slate-50 border border-slate-100 text-[9px] font-black uppercase tracking-widest text-slate-600">{{ c.provider }}</span></td>
                                    <td class="px-3 py-2.5 text-[10px] font-mono text-slate-500">{{ c.bandwidth }}</td>
                                    <td class="px-3 py-2.5 text-right text-[11px] font-black text-slate-700 tabular-nums">{{ c.target_pct }}%</td>
                                    <td class="px-3 py-2.5 text-center">
                                        <button type="button" class="px-2 py-0.5 rounded-full text-[8px] font-black uppercase tracking-widest border transition-all" :class="c.is_active ? 'bg-emerald-50 border-emerald-100 text-emerald-600 hover:bg-emerald-100' : 'bg-slate-50 border-slate-100 text-slate-400 hover:bg-slate-100'" @click="toggleContractActive(c)">
                                            {{ c.is_active ? "Aktif" : "Nonaktif" }}
                                        </button>
                                    </td>
                                    <td class="px-3 py-2.5 text-center">
                                        <button type="button" class="h-7 w-7 rounded-lg bg-slate-50 text-slate-500 hover:bg-[#003628] hover:text-white transition-all flex items-center justify-center mx-auto" @click="openEditContract(c)">
                                            <Pencil class="size-3.5" />
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="contracts.length===0">
                                    <td colspan="7" class="px-4 py-8 text-center text-[10px] font-bold text-slate-300 uppercase tracking-widest">Belum ada kontrak ISP</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </Teleport>

    </div>
</template>