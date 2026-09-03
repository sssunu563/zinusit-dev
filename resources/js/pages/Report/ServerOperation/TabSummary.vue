<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { Server, Cpu, HardDrive, Thermometer, Clock, RefreshCw, Activity, ActivitySquare, AlertCircle, MapPin } from 'lucide-vue-next';

const props = defineProps<{
    locations: string[];
    filterFrom: string;
    filterTo: string;
    applyTrigger: number;
}>();

const loading = ref(true);
const summary = ref<any>(null);

async function loadData() {
    loading.value = true;
    try {
        const res = await fetch(
            `/server-operation/summary?from=${props.filterFrom}&to=${props.filterTo}`,
        );
        if (res.ok) summary.value = await res.json();
    } catch (err) {
        console.error(err);
    } finally {
        loading.value = false;
    }
}

function getStatusColor(val: number) {
    if (val > 80) return '#e11d48'; // rose-600
    if (val > 60) return '#f59e0b'; // amber-500
    return '#10b981'; // emerald-500
}

onMounted(() => loadData());
watch(() => props.applyTrigger, () => loadData());

</script>

<template>
    <div class="space-y-4">
        <!-- SKELETON -->
        <template v-if="loading">
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <div v-for="n in 4" :key="n" class="h-20 bg-slate-50 rounded-2xl border border-slate-100 animate-pulse"/>
            </div>
            <div class="h-48 bg-slate-50 rounded-2xl border border-slate-100 animate-pulse"/>
        </template>

        <template v-else-if="summary">
            <!-- KPI CARDS -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <!-- Infrastructure -->
                <div class="rounded-2xl border border-slate-100 bg-white p-4 flex items-center gap-3.5 hover:border-slate-200 hover:shadow-sm transition-all">
                    <div class="h-10 w-10 rounded-xl bg-slate-50 border border-slate-100 flex items-center justify-center shrink-0">
                        <Server class="size-4.5 text-slate-500"/>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Total Infrastructure</p>
                        <p class="text-[18px] font-black tabular-nums leading-tight mt-0.5 text-slate-700">{{ summary.active_devices ?? 0 }}</p>
                        <p class="text-[9px] text-slate-400 mt-0.5">Active Servers</p>
                    </div>
                </div>

                <!-- CPU -->
                <div class="rounded-2xl border border-slate-100 bg-white p-4 flex items-center gap-3.5 hover:border-slate-200 hover:shadow-sm transition-all"
                    :class="summary.avg_cpu_usage > 80 ? 'border-rose-200' : 'border-slate-100'">
                    <div class="h-10 w-10 rounded-xl flex items-center justify-center shrink-0"
                        :class="summary.avg_cpu_usage > 80 ? 'bg-rose-50 border border-rose-100' : 'bg-amber-50 border border-amber-100'">
                        <Cpu class="size-4.5" :class="summary.avg_cpu_usage > 80 ? 'text-rose-500' : 'text-amber-500'"/>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Avg CPU Load</p>
                        <p class="text-[18px] font-black tabular-nums leading-tight mt-0.5" :class="summary.avg_cpu_usage > 80 ? 'text-rose-500' : 'text-amber-500'">
                            {{ summary.avg_cpu_usage?.toFixed(1) ?? '0.0' }}%
                        </p>
                        <p class="text-[9px] text-slate-400 mt-0.5">Overall Usage</p>
                    </div>
                </div>

                <!-- RAM -->
                <div class="rounded-2xl border border-slate-100 bg-white p-4 flex items-center gap-3.5 hover:border-slate-200 hover:shadow-sm transition-all">
                    <div class="h-10 w-10 rounded-xl bg-emerald-50 border border-emerald-100 flex items-center justify-center shrink-0">
                        <Activity class="size-4.5 text-emerald-500"/>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Memory Util</p>
                        <p class="text-[18px] font-black tabular-nums leading-tight mt-0.5 text-emerald-600">
                            {{ summary.avg_memory_usage?.toFixed(1) ?? '0.0' }}%
                        </p>
                        <p class="text-[9px] text-slate-400 mt-0.5">Available RAM</p>
                    </div>
                </div>

                <!-- Temperature -->
                <div class="rounded-2xl border border-slate-100 bg-white p-4 flex items-center gap-3.5 hover:border-slate-200 hover:shadow-sm transition-all">
                    <div class="h-10 w-10 rounded-xl bg-slate-50 border border-slate-100 flex items-center justify-center shrink-0">
                        <Thermometer class="size-4.5 text-slate-500"/>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Thermal Status</p>
                        <p class="text-[18px] font-black tabular-nums leading-tight mt-0.5 text-slate-700">
                            {{ summary.avg_temperature?.toFixed(1) ?? '0.0' }}°C
                        </p>
                        <p class="text-[9px] text-slate-400 mt-0.5">Avg DC Temp</p>
                    </div>
                </div>
            </div>

            <!-- DETAIL SECTIONS -->
            <div class="rounded-2xl border border-slate-100 bg-white overflow-hidden">
                <div class="flex items-center gap-2.5 px-5 py-3.5 border-b border-slate-100">
                    <Server class="size-4 text-slate-400"/>
                    <p class="text-[11px] font-black text-slate-700 uppercase tracking-tight">Infrastructure Performance Analysis</p>
                </div>

                <div class="divide-y divide-slate-50">
                    <!-- Performance per Lokasi -->
                    <div v-if="summary.location_stats?.length">
                        <div class="flex items-center gap-2 px-5 py-2 bg-slate-50/50">
                            <ActivitySquare class="size-3.5 text-slate-400 shrink-0"/>
                            <p class="text-[9px] font-black uppercase tracking-widest text-slate-500">Performance per Lokasi</p>
                            <span class="ml-auto text-[9px] text-slate-400">{{ summary.location_stats.length }} lokasi</span>
                        </div>
                        <div class="px-5 py-3">
                            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-2">
                                <div v-for="loc in summary.location_stats" :key="loc.location"
                                    class="rounded-xl border border-slate-100 bg-slate-50/60 p-3 hover:bg-white hover:border-slate-200 hover:shadow-sm transition-all"
                                    :class="loc.avg_cpu > 80 ? 'border-rose-200' : 'border-slate-100'">
                                    <div class="flex items-center gap-1.5 mb-1.5">
                                        <MapPin class="size-3 text-slate-400 shrink-0"/>
                                        <p class="text-[9px] font-black uppercase tracking-widest text-slate-500 truncate">{{ loc.location }}</p>
                                    </div>
                                    <p class="text-[15px] font-black tabular-nums leading-none" :class="loc.avg_cpu > 80 ? 'text-rose-600' : 'text-slate-700'">
                                        {{ loc.avg_cpu?.toFixed(1) }}% <span class="text-[8px] text-slate-400 ml-0.5">CPU</span>
                                    </p>
                                    <p class="text-[9px] text-slate-400 mt-1">{{ loc.count }} servers</p>
                                    <div class="mt-2 h-1 bg-slate-200 rounded-full overflow-hidden">
                                        <div class="h-full rounded-full transition-all duration-700" :class="loc.avg_cpu > 80 ? 'bg-rose-400' : 'bg-emerald-400'"
                                            :style="{ width: Math.min(loc.avg_cpu, 100) + '%' }"/>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
        
        <div v-else class="rounded-2xl border-2 border-dashed border-slate-100 py-20 text-center">
            <div class="h-12 w-12 rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-center mx-auto mb-3">
                <AlertCircle class="size-6 text-slate-300"/>
            </div>
            <p class="text-[11px] font-black uppercase tracking-widest text-slate-300">Belum ada data</p>
            <p class="text-[10px] text-slate-300 mt-1">Pastikan sinkronisasi PRTG berjalan atau cek filter tanggal</p>
            <button @click="loadData" class="mt-6 h-9 px-6 rounded-xl border border-slate-200 bg-white text-[9px] font-black uppercase tracking-widest hover:bg-slate-50 transition-all active:scale-95">
                Refresh Dashboard
            </button>
        </div>
    </div>
</template>
