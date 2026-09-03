<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { LifeBuoy, CheckCircle2, AlertCircle, ActivitySquare, Server, MessageSquare, MapPin, Tag } from 'lucide-vue-next';

const props = defineProps<{
    filterFrom: string;
    filterTo: string;
    applyTrigger: number;
}>();

const loading = ref(true);
const summary = ref<any>(null);

async function loadData() {
    loading.value = true;
    try {
        const res = await fetch(`/support-operation/summary?from=${props.filterFrom}&to=${props.filterTo}`);
        if (res.ok) summary.value = await res.json();
    } finally {
        loading.value = false;
    }
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
                <!-- Total Tickets -->
                <div class="rounded-2xl border border-slate-100 bg-white p-4 flex items-center gap-3.5 hover:border-slate-200 hover:shadow-sm transition-all">
                    <div class="h-10 w-10 rounded-xl bg-slate-50 border border-slate-100 flex items-center justify-center shrink-0">
                        <MessageSquare class="size-4.5 text-slate-500"/>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Total Tickets</p>
                        <p class="text-[18px] font-black tabular-nums leading-tight mt-0.5 text-slate-700">{{ summary.total }}</p>
                        <p class="text-[9px] text-slate-400 mt-0.5">Tickets Received</p>
                    </div>
                </div>

                <!-- Active / Open -->
                <div class="rounded-2xl border border-slate-100 bg-white p-4 flex items-center gap-3.5 hover:border-slate-200 hover:shadow-sm transition-all"
                    :class="summary.open > 0 ? 'border-rose-200' : 'border-slate-100'">
                    <div class="h-10 w-10 rounded-xl flex items-center justify-center shrink-0"
                        :class="summary.open > 0 ? 'bg-rose-50 border border-rose-100' : 'bg-slate-50 border border-slate-100'">
                        <AlertCircle class="size-4.5" :class="summary.open > 0 ? 'text-rose-500' : 'text-slate-400'"/>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Active / Open</p>
                        <p class="text-[18px] font-black tabular-nums leading-tight mt-0.5" :class="summary.open > 0 ? 'text-rose-500' : 'text-slate-400'">
                            {{ summary.open }}
                        </p>
                        <p class="text-[9px] text-slate-400 mt-0.5">In Progress</p>
                    </div>
                </div>

                <!-- Resolved -->
                <div class="rounded-2xl border border-slate-100 bg-white p-4 flex items-center gap-3.5 hover:border-slate-200 hover:shadow-sm transition-all"
                    :class="summary.closed > 0 ? 'border-emerald-100' : 'border-slate-100'">
                    <div class="h-10 w-10 rounded-xl bg-emerald-50 border border-emerald-100 flex items-center justify-center shrink-0">
                        <CheckCircle2 class="size-4.5 text-emerald-500"/>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Resolved</p>
                        <p class="text-[18px] font-black tabular-nums leading-tight mt-0.5 text-emerald-600">{{ summary.closed }}</p>
                        <p class="text-[9px] text-slate-400 mt-0.5">Completed Tickets</p>
                    </div>
                </div>

                <!-- Resolution Rate -->
                <div class="rounded-2xl border border-slate-100 bg-white p-4 flex items-center gap-3.5 hover:border-slate-200 hover:shadow-sm transition-all">
                    <div class="h-10 w-10 rounded-xl bg-slate-50 border border-slate-100 flex items-center justify-center shrink-0">
                        <ActivitySquare class="size-4.5 text-slate-500"/>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Resolution Rate</p>
                        <p class="text-[18px] font-black tabular-nums leading-tight mt-0.5 text-slate-700">
                            {{ summary.total > 0 ? ((summary.closed / summary.total) * 100).toFixed(1) : 0 }}%
                        </p>
                        <p class="text-[9px] text-slate-400 mt-0.5">Overall Performance</p>
                    </div>
                </div>
            </div>

            <!-- DETAIL SECTIONS -->
            <div class="rounded-2xl border border-slate-100 bg-white overflow-hidden">
                <div class="flex items-center gap-2.5 px-5 py-3.5 border-b border-slate-100">
                    <LifeBuoy class="size-4 text-slate-400"/>
                    <p class="text-[11px] font-black text-slate-700 uppercase tracking-tight">Support Metrics Breakdown</p>
                </div>

                <div class="divide-y divide-slate-50">
                    <!-- Resolution per Lokasi -->
                    <div v-if="summary.location_stats?.length">
                        <div class="flex items-center gap-2 px-5 py-2 bg-slate-50/50">
                            <Server class="size-3.5 text-slate-400 shrink-0"/>
                            <p class="text-[9px] font-black uppercase tracking-widest text-slate-500">Resolution per Lokasi</p>
                            <span class="ml-auto text-[9px] text-slate-400">{{ summary.location_stats.length }} lokasi</span>
                        </div>
                        <div class="px-5 py-3">
                            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-2">
                                <div v-for="loc in summary.location_stats" :key="loc.location"
                                    class="rounded-xl border border-slate-100 bg-slate-50/60 p-3 hover:bg-white hover:border-slate-200 hover:shadow-sm transition-all"
                                    :class="loc.pct >= 90 ? 'border-emerald-200' : loc.pct >= 70 ? 'border-amber-200' : 'border-rose-200'">
                                    <div class="flex items-center gap-1.5 mb-1.5">
                                        <MapPin class="size-3 text-slate-400 shrink-0"/>
                                        <p class="text-[9px] font-black uppercase tracking-widest text-slate-500 truncate">{{ loc.location }}</p>
                                    </div>
                                    <p class="text-[15px] font-black tabular-nums leading-none" 
                                        :class="loc.pct >= 90 ? 'text-emerald-600' : loc.pct >= 70 ? 'text-amber-500' : 'text-rose-500'">
                                        {{ loc.pct }}%
                                    </p>
                                    <p class="text-[9px] text-slate-400 mt-1">{{ loc.total }} tickets</p>
                                    <div class="mt-2 h-1 bg-slate-200 rounded-full overflow-hidden">
                                        <div class="h-full rounded-full transition-all duration-700" 
                                            :class="loc.pct >= 90 ? 'bg-emerald-400' : loc.pct >= 70 ? 'bg-amber-400' : 'bg-rose-400'"
                                            :style="{ width: loc.pct + '%' }"/>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Category Distribution -->
                    <div v-if="summary.category_stats?.length">
                        <div class="flex items-center gap-2 px-5 py-2 bg-slate-50/50">
                            <Tag class="size-3.5 text-slate-400 shrink-0"/>
                            <p class="text-[9px] font-black uppercase tracking-widest text-slate-500">Category Distribution</p>
                        </div>
                        <div class="px-5 py-4 flex flex-wrap gap-2">
                            <div v-for="cat in summary.category_stats" :key="cat.category" 
                                class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-slate-50 border border-slate-100 group hover:bg-white hover:border-slate-200 transition-all">
                                <span class="text-[11px] font-black text-slate-700">{{ cat.category }}</span>
                                <span class="text-[9px] font-black bg-white border border-slate-100 text-[#003628] px-1.5 py-0.5 rounded-md tabular-nums">{{ cat.count }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <div v-else class="rounded-2xl border-2 border-dashed border-slate-100 py-20 text-center">
            <div class="h-12 w-12 rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-center mx-auto mb-3">
                <LifeBuoy class="size-6 text-slate-300"/>
            </div>
            <p class="text-[11px] font-black uppercase tracking-widest text-slate-300">Belum ada data</p>
        </div>
    </div>
</template>
