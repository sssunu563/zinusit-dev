<script setup lang="ts">
import { ref, computed, watch, onMounted } from "vue";
import {
    Thermometer, Loader2, Search, ChevronUp, ChevronDown, ChevronsUpDown, Table2
} from "lucide-vue-next";

const props = defineProps<{
    locations: string[];
    filterFrom: string;
    filterTo: string;
    applyTrigger: number;
    filterLocation: string;
    searchQuery: string;
}>();

const loading  = ref(false);
const tempData = ref<any>(null);

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

// ── colour helpers ────────────────────────────────────────────────────────────
function tempColor(val: number | null) {
    if (val === null) return "bg-slate-50 text-slate-200";
    if (val >= 35)   return "bg-rose-50 text-rose-700";
    if (val >= 28)   return "bg-amber-50 text-amber-700";
    return "bg-emerald-50 text-emerald-700";
}

// ── column width — driven by daily_dates array (same as uptime) ───────────────
const datColStyle = computed(() => {
    const n = tempData.value?.daily_dates?.length ?? 1;
    const w = n <= 7 ? 52 : n <= 14 ? 44 : n <= 21 ? 40 : 36;
    return { minWidth: w + "px", width: w + "px" };
});

// ── pre-build a date→temp map for each row so the template is O(1) ───────────
// API shape: row.daily = [{ date: "YYYY-MM-DD", temp: 24.1 }, ...]
const rowsWithMap = computed(() => {
    if (!tempData.value?.daily_rows) return [];
    return tempData.value.daily_rows.map((row: any) => {
        const map: Record<string, number | null> = {};
        for (const d of (row.daily ?? [])) {
            map[d.date] = d.temp ?? null;
        }
        return { ...row, dailyMap: map };
    });
});

// ── filtered + sorted rows ────────────────────────────────────────────────────
const sortedRows = computed(() => {
    const q = props.searchQuery.toLowerCase();
    let rows = rowsWithMap.value.filter((r: any) => {
        if (q && !r.description?.toLowerCase().includes(q) && !r.location?.toLowerCase().includes(q)) return false;
        if (props.filterLocation && r.location !== props.filterLocation) return false;
        return true;
    });
    rows = [...rows].sort((a: any, b: any) => {
        let av = a[sortCol.value] ?? "";
        let bv = b[sortCol.value] ?? "";
        if (sortCol.value === "avg_temp") { av = a.avg_temp ?? -1; bv = b.avg_temp ?? -1; }
        if (typeof av === "number") return sortDir.value === "asc" ? av - bv : bv - av;
        return sortDir.value === "asc"
            ? String(av).localeCompare(String(bv))
            : String(bv).localeCompare(String(av));
    });
    return rows;
});

// ── daily date list — same shape as uptime: [{ date: "YYYY-MM-DD" }, ...] ────
const dailyDates = computed<string[]>(() =>
    (tempData.value?.daily_dates ?? []).map((d: any) => d.date)
);

async function loadData() {
    loading.value = true;
    try {
        const params = new URLSearchParams({
            from:     props.filterFrom,
            to:       props.filterTo,
            location: props.filterLocation,
        });
        const res = await fetch("/server-operation/temperature?" + params);
        if (res.ok) tempData.value = await res.json();
    } finally { loading.value = false; }
}

onMounted(() => loadData());
watch(() => [props.applyTrigger, props.filterLocation], () => loadData());
</script>

<template>
<div class="w-full min-w-0 pb-10 space-y-5">

    <!-- LOCATION SUMMARY CARDS -->
    <div v-if="!loading && tempData?.loc_summary?.length"
        class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
        <div v-for="ls in tempData.loc_summary" :key="ls.location"
            class="rounded-2xl border border-slate-100 bg-white p-4 flex items-center gap-3.5 hover:border-slate-200 hover:shadow-sm transition-all">
            <div class="h-10 w-10 rounded-xl flex items-center justify-center shrink-0 border"
                :class="tempColor(ls.avg_temp)">
                <Thermometer class="size-4.5" />
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-[9px] font-black uppercase tracking-widest text-slate-400 truncate">{{ ls.location }}</p>
                <p class="text-[18px] font-black tabular-nums leading-tight mt-0.5 text-slate-800">
                    {{ ls.avg_temp?.toFixed(1) }}<span class="text-[11px] font-bold text-slate-400 ml-0.5">°C</span>
                </p>
                <p class="text-[9px] text-slate-400 mt-0.5">Avg Ambient</p>
            </div>
        </div>
    </div>

    <!-- MATRIX TABLE -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">

        <div v-if="loading" class="flex items-center justify-center py-40">
            <Loader2 class="size-8 text-[#003628] animate-spin" />
        </div>

        <template v-else>
            <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-b border-slate-100">
                <div class="flex items-center gap-3">
                    <div class="h-9 w-9 rounded-xl bg-[#003628]/5 flex items-center justify-center shrink-0">
                        <Table2 class="size-4 text-[#003628]" />
                    </div>
                    <div>
                        <p class="text-[13px] font-black text-slate-800 tracking-tight">Daily Temperature Matrix</p>
                        <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-0.5">Celsius (°C) · {{ sortedRows.length }} Sensors</p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-50 border border-emerald-100 text-[8px] font-black uppercase text-emerald-600">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0"></span> Ideal &lt;28°C
                    </div>
                    <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-amber-50 border border-amber-100 text-[8px] font-black uppercase text-amber-600">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 shrink-0"></span> Warm &gt;28°C
                    </div>
                    <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-rose-50 border border-rose-100 text-[8px] font-black uppercase text-rose-600">
                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500 shrink-0"></span> Critical &gt;35°C
                    </div>
                </div>
            </div>

            <div v-if="!sortedRows.length" class="py-20 text-center">
                <Thermometer class="size-10 text-slate-200 mx-auto mb-3" />
                <p class="text-[11px] font-black uppercase tracking-widest text-slate-300">Belum ada data suhu</p>
            </div>

            <div v-else class="overflow-x-auto w-full relative">
                <table class="border-collapse text-left" style="width:max-content;min-width:100%">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100">
                            <!-- Frozen: Sensor -->
                            <th class="sticky left-0 z-20 bg-slate-50 px-3 py-3 text-[9px] font-black uppercase tracking-widest text-slate-400 w-[220px] min-w-[220px] cursor-pointer hover:bg-slate-100"
                                @click="toggleSort('description')">
                                <div class="flex items-center gap-1.5">Sensor <component :is="sortIcon('description')" class="size-3.5"/></div>
                            </th>
                            <!-- Frozen: Lokasi -->
                            <th class="sticky left-[220px] z-20 bg-slate-50 px-3 py-3 text-[9px] font-black uppercase tracking-widest text-slate-400 w-[110px] min-w-[110px] cursor-pointer hover:bg-slate-100"
                                @click="toggleSort('location')">
                                <div class="flex items-center gap-1.5">Lokasi <component :is="sortIcon('location')" class="size-3.5"/></div>
                            </th>
                            <!-- Frozen: Avg -->
                            <th class="sticky left-[330px] z-20 bg-slate-50 border-r border-slate-200 px-3 py-3 text-[9px] font-black uppercase tracking-widest text-slate-400 w-[72px] min-w-[72px] cursor-pointer hover:bg-slate-100"
                                style="box-shadow:2px 0 5px -1px rgba(0,0,0,0.08)"
                                @click="toggleSort('avg_temp')">
                                <div class="flex items-center gap-1.5">Avg <component :is="sortIcon('avg_temp')" class="size-3.5"/></div>
                            </th>
                            <!-- Daily columns -->
                            <th v-for="date in dailyDates" :key="date" :style="datColStyle"
                                class="px-1 py-3 text-center text-[9px] font-black uppercase tracking-widest text-slate-400">
                                <span class="block">{{ new Date(date+'T00:00:00').getDate() }}</span>
                                <span class="block text-[8px] font-bold text-slate-300">{{ ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'][new Date(date+'T00:00:00').getMonth()] }}</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-for="row in sortedRows" :key="row.sensor_id"
                            class="hover:bg-slate-50/40 transition-colors group">
                            <!-- Frozen: Sensor -->
                            <td class="sticky left-0 z-10 bg-white px-3 py-2.5 group-hover:bg-slate-50/40">
                                <p class="text-[11px] font-bold text-slate-800 truncate max-w-[190px]">{{ row.description }}</p>
                                <p class="text-[9px] font-mono text-slate-400">{{ row.device_name }}</p>
                            </td>
                            <!-- Frozen: Lokasi -->
                            <td class="sticky left-[220px] z-10 bg-white px-3 py-2.5 text-[10px] font-bold text-slate-600 whitespace-nowrap group-hover:bg-slate-50/40">
                                {{ row.location }}
                            </td>
                            <!-- Frozen: Avg badge -->
                            <td class="sticky left-[330px] z-10 bg-white border-r border-slate-100 px-3 py-2.5 text-center group-hover:bg-slate-50/40"
                                style="box-shadow:2px 0 5px -1px rgba(0,0,0,0.08)">
                                <span class="px-2 py-1 rounded-lg text-[10px] font-black tabular-nums"
                                    :class="tempColor(row.avg_temp)">
                                    {{ row.avg_temp?.toFixed(1) }}°
                                </span>
                            </td>
                            <!-- Daily cells -->
                            <td v-for="date in dailyDates" :key="date" :style="datColStyle"
                                class="px-0.5 py-1.5 text-center border-b border-slate-50">
                                <span v-if="row.dailyMap[date] !== null && row.dailyMap[date] !== undefined"
                                    class="block text-[8px] font-black tabular-nums leading-none"
                                    :class="row.dailyMap[date] >= 35 ? 'text-rose-600' : row.dailyMap[date] >= 28 ? 'text-amber-500' : 'text-emerald-600'">
                                    {{ Number(row.dailyMap[date]).toFixed(1) }}°
                                </span>
                                <span v-else class="block text-[8px] font-bold text-slate-200 leading-none">·</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>
    </div>

</div>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>
