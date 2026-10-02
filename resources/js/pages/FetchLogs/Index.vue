<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import { ref, computed } from "vue";
import AppLayout from "@/layouts/AppLayout.vue";
import {
    Activity, CheckCircle2, XCircle, AlertTriangle, Clock, 
    Server, Camera, Wifi, TrendingUp, Fingerprint, RotateCcw
} from "lucide-vue-next";

interface FetchLog {
    id: number;
    fetch_date: string;
    source: string;
    source_instance?: string;
    device_type: 'nvr' | 'cctv' | 'finger' | 'server' | 'network' | 'bandwidth';
    group_name?: string;
    devices_ok: number;
    devices_fail: number;
    status: 'success' | 'partial' | 'failed';
    is_manual: boolean;
    triggered_by: string;
    created_at: string;
}

const props = defineProps<{
    logs: FetchLog[];
}>();

// Filter & Pagination
const filterType = ref<string>('all');
const searchQuery = ref('');
const currentPage = ref(1);
const pageSize = ref(50);

// Filter logs
const filteredLogs = computed(() => {
    let logs = props.logs;
    
    // Filter by type
    if (filterType.value !== 'all') {
        logs = logs.filter(log => log.device_type === filterType.value);
    }
    
    // Search
    const q = searchQuery.value.toLowerCase();
    if (q) {
        logs = logs.filter(log => 
            log.source.toLowerCase().includes(q) ||
            log.device_type?.toLowerCase().includes(q) ||
            log.triggered_by?.toLowerCase().includes(q) ||
            log.group_name?.toLowerCase().includes(q)
        );
    }
    
    return logs;
});

// Pagination
const totalPages = computed(() => Math.max(1, Math.ceil(filteredLogs.value.length / pageSize.value)));
const pagedLogs = computed(() => {
    const start = (currentPage.value - 1) * pageSize.value;
    return filteredLogs.value.slice(start, start + pageSize.value);
});

// Reset page when filter changes
function resetPage() {
    currentPage.value = 1;
}

// Status configuration
function logStatusConf(status: string) {
    if (status === "success") return { color: "text-emerald-600", bg: "bg-emerald-50 border-emerald-100", icon: CheckCircle2 };
    if (status === "partial") return { color: "text-amber-600",   bg: "bg-amber-50 border-amber-100",   icon: AlertTriangle };
    return                           { color: "text-rose-600",    bg: "bg-rose-50 border-rose-100",     icon: XCircle };
}

// Device type badge
function deviceTypeBadge(type: string) {
    const badges: Record<string, string> = {
        nvr: 'bg-violet-50 border-violet-100 text-violet-600',
        cctv: 'bg-sky-50 border-sky-100 text-sky-600',
        finger: 'bg-emerald-50 border-emerald-100 text-emerald-600',
        server: 'bg-orange-50 border-orange-100 text-orange-600',
        network: 'bg-blue-50 border-blue-100 text-blue-600',
        bandwidth: 'bg-purple-50 border-purple-100 text-purple-600',
    };
    return badges[type] || 'bg-slate-50 border-slate-100 text-slate-600';
}

// Stats
const stats = computed(() => {
    const all = filteredLogs.value;
    return {
        total: all.length,
        success: all.filter(l => l.status === 'success').length,
        partial: all.filter(l => l.status === 'partial').length,
        failed: all.filter(l => l.status === 'failed').length,
        nvr: all.filter(l => l.device_type === 'nvr').length,
        cctv: all.filter(l => l.device_type === 'cctv').length,
        finger: all.filter(l => l.device_type === 'finger').length,
        server: all.filter(l => l.device_type === 'server').length,
        network: all.filter(l => l.device_type === 'network').length,
        bandwidth: all.filter(l => l.device_type === 'bandwidth').length,
    };
});
</script>

<template>
    <AppLayout :breadcrumbs="[
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Fetch Logs', href: '/fetch-logs' },
    ]">
        <Head title="Fetch Logs" />

        <div class="app-page-shell">
            <div class="bg-white rounded-[28px] border border-slate-200/70 shadow-xl shadow-slate-200/50">
                
                <!-- HEADER - Compact Style like Network Operation -->
                <div class="flex items-center justify-between gap-4 px-6 py-4 border-b border-slate-100">
                    <!-- Left: Icon + Title + Subtitle -->
                    <div class="flex items-center gap-2.5">
                        <div class="h-10 w-10 rounded-2xl bg-[#003628] flex items-center justify-center shadow-md shadow-[#003628]/25 shrink-0">
                            <Activity class="size-5 text-white"/>
                        </div>
                        <div>
                            <h1 class="text-[15px] font-black tracking-tight text-slate-900 leading-none">
                                Fetch Logs
                            </h1>
                            <p class="text-[9px] text-slate-400 mt-0.5">CCTV / Server / Network / Bandwidth</p>
                        </div>
                    </div>

                    <!-- Right: Stats + Actions -->
                    <div class="flex items-center gap-2">
                        <span class="text-[10px] font-bold text-slate-400 tabular-nums select-none">
                            {{ stats.total }} logs
                        </span>
                    </div>
                </div>

                <!-- FILTERS & STATS -->
                <div class="px-6 py-4 border-b border-slate-100 space-y-3">
                    <!-- Stats Row -->
                    <div class="flex items-center gap-2 flex-wrap">
                        <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-emerald-50 border border-emerald-100">
                            <CheckCircle2 class="size-3.5 text-emerald-600"/>
                            <span class="text-[9px] font-bold text-emerald-700">{{ stats.success }} Success</span>
                        </div>
                        <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-amber-50 border border-amber-100">
                            <AlertTriangle class="size-3.5 text-amber-600"/>
                            <span class="text-[9px] font-bold text-amber-700">{{ stats.partial }} Partial</span>
                        </div>
                        <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-rose-50 border border-rose-100">
                            <XCircle class="size-3.5 text-rose-600"/>
                            <span class="text-[9px] font-bold text-rose-700">{{ stats.failed }} Failed</span>
                        </div>
                    </div>
                    
                    <!-- Filter Row -->
                    <div class="flex items-center gap-3 flex-wrap justify-between">
                        <div class="flex items-center gap-2 flex-wrap">
                            <button @click="filterType = 'all'; resetPage()" 
                                class="h-7 px-3 rounded-md text-[9px] font-bold uppercase tracking-wider transition-all"
                                :class="filterType === 'all' ? 'bg-[#003628] text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'">
                                All ({{ stats.total }})
                            </button>
                            <button @click="filterType = 'cctv'; resetPage()" 
                                class="h-7 px-3 rounded-md text-[9px] font-bold uppercase tracking-wider transition-all flex items-center gap-1.5"
                                :class="filterType === 'cctv' ? 'bg-sky-600 text-white' : 'bg-sky-50 text-sky-700 hover:bg-sky-100'">
                                <Camera class="size-3"/> CCTV ({{ stats.cctv }})
                            </button>
                            <button @click="filterType = 'nvr'; resetPage()" 
                                class="h-7 px-3 rounded-md text-[9px] font-bold uppercase tracking-wider transition-all flex items-center gap-1.5"
                                :class="filterType === 'nvr' ? 'bg-violet-600 text-white' : 'bg-violet-50 text-violet-700 hover:bg-violet-100'">
                                <Server class="size-3"/> NVR ({{ stats.nvr }})
                            </button>
                            <button @click="filterType = 'finger'; resetPage()" 
                                class="h-7 px-3 rounded-md text-[9px] font-bold uppercase tracking-wider transition-all flex items-center gap-1.5"
                                :class="filterType === 'finger' ? 'bg-emerald-600 text-white' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100'">
                                <Fingerprint class="size-3"/> Finger ({{ stats.finger }})
                            </button>
                            <button @click="filterType = 'server'; resetPage()" 
                                class="h-7 px-3 rounded-md text-[9px] font-bold uppercase tracking-wider transition-all flex items-center gap-1.5"
                                :class="filterType === 'server' ? 'bg-orange-600 text-white' : 'bg-orange-50 text-orange-700 hover:bg-orange-100'">
                                <Server class="size-3"/> Server ({{ stats.server }})
                            </button>
                            <button @click="filterType = 'network'; resetPage()" 
                                class="h-7 px-3 rounded-md text-[9px] font-bold uppercase tracking-wider transition-all flex items-center gap-1.5"
                                :class="filterType === 'network' ? 'bg-blue-600 text-white' : 'bg-blue-50 text-blue-700 hover:bg-blue-100'">
                                <Wifi class="size-3"/> Network ({{ stats.network }})
                            </button>
                            <button @click="filterType = 'bandwidth'; resetPage()" 
                                class="h-7 px-3 rounded-md text-[9px] font-bold uppercase tracking-wider transition-all flex items-center gap-1.5"
                                :class="filterType === 'bandwidth' ? 'bg-purple-600 text-white' : 'bg-purple-50 text-purple-700 hover:bg-purple-100'">
                                <TrendingUp class="size-3"/> Bandwidth ({{ stats.bandwidth }})
                            </button>
                        </div>
                        
                        <div>
                            <input v-model="searchQuery" @input="resetPage" type="text" placeholder="Search logs..." 
                                class="h-7 px-3 rounded-md border border-slate-200 bg-white text-[9px] font-bold text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-1 focus:ring-[#003628]/30 w-48"/>
                        </div>
                    </div>
                </div>

                <!-- LOGS TABLE -->
                <div class="divide-y divide-slate-50">
                    <div v-if="!pagedLogs.length" class="py-20 text-center">
                        <Activity class="size-12 text-slate-200 mx-auto mb-3"/>
                        <p class="text-[11px] font-black uppercase tracking-widest text-slate-300">Tidak ada log</p>
                    </div>
                    
                    <div v-for="log in pagedLogs" :key="log.id"
                        class="flex items-center gap-3 px-6 py-3 hover:bg-slate-50/50 transition-colors flex-wrap">
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
                        <span class="px-2 py-0.5 rounded-full text-[8px] font-black uppercase border"
                            :class="deviceTypeBadge(log.device_type)">
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

                <!-- PAGINATION -->
                <div v-if="totalPages > 1" class="flex items-center justify-between px-6 py-3 border-t border-slate-100 bg-slate-50/50">
                    <div class="flex items-center gap-2">
                        <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">Showing</span>
                        <select v-model.number="pageSize" @change="resetPage" class="h-7 px-2 pr-6 rounded-md border border-slate-200 bg-white text-[9px] font-bold text-slate-700 focus:outline-none focus:ring-1 focus:ring-[#003628]/30 cursor-pointer">
                            <option :value="20">20</option>
                            <option :value="50">50</option>
                            <option :value="100">100</option>
                            <option :value="200">200</option>
                        </select>
                        <span class="text-[9px] font-bold text-slate-500">of {{ filteredLogs.length }}</span>
                    </div>
                    
                    <div class="flex items-center gap-1">
                        <button type="button" :disabled="currentPage===1" 
                            class="h-7 px-2 rounded-md border border-slate-200 bg-white text-slate-600 text-[9px] font-bold disabled:opacity-30 disabled:cursor-not-allowed hover:bg-slate-50 transition-all"
                            @click="currentPage--">‹</button>
                        
                        <div class="flex items-center gap-0.5 mx-1">
                            <template v-for="p in totalPages" :key="p">
                                <button v-if="p === 1 || p === totalPages || Math.abs(p - currentPage) <= 1" 
                                    type="button" 
                                    class="h-7 min-w-[28px] px-1.5 rounded-md text-[9px] font-black transition-all" 
                                    :class="p===currentPage?'bg-[#003628] text-white shadow-sm':'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50'" 
                                    @click="currentPage=p">{{ p }}</button>
                                <span v-else-if="(p === 2 && currentPage > 3) || (p === totalPages - 1 && currentPage < totalPages - 2)" 
                                    class="text-slate-300 text-[9px] px-1">···</span>
                            </template>
                        </div>
                        
                        <button type="button" :disabled="currentPage===totalPages" 
                            class="h-7 px-2 rounded-md border border-slate-200 bg-white text-slate-600 text-[9px] font-bold disabled:opacity-30 disabled:cursor-not-allowed hover:bg-slate-50 transition-all"
                            @click="currentPage++">›</button>
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
