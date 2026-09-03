<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, computed, defineAsyncComponent, Suspense } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    Server, Database, Thermometer, LayoutDashboard,
    SlidersHorizontal, FileDown, Play, RefreshCw, Loader2,
    CheckCircle2, XCircle, AlertCircle,
} from 'lucide-vue-next';

const props = defineProps<{ locations: string[] }>();

const TabSummary = defineAsyncComponent(() => import('./TabSummary.vue'));
const TabServerData = defineAsyncComponent(() => import('./TabServerData.vue'));
const TabTemperature = defineAsyncComponent(() => import('./TabTemperature.vue'));
const TabMaintenance = defineAsyncComponent(() => import('./TabMaintenance.vue'));

const urlParams = new URLSearchParams(window.location.search);
const activeTab = ref<string>(urlParams.get('tab') ?? 'summary');

const tabs = [
    { id: 'summary', label: 'Summary', icon: LayoutDashboard, component: TabSummary },
    { id: 'data', label: 'Server Data', icon: Server, component: TabServerData },
    { id: 'temp', label: 'Server Temperature', icon: Thermometer, component: TabTemperature },
    { id: 'maintenance', label: 'Maintenance', icon: AlertCircle, component: TabMaintenance },
];

const currentComponent = computed(() => {
    return tabs.find(t => t.id === activeTab.value)?.component || TabSummary;
});

function setTab(id: string) {
    activeTab.value = id;
    const url = new URL(window.location.href);
    url.searchParams.set('tab', id);
    window.history.pushState({}, '', url);
}

// Global Filter Logic
const filterFrom = ref(urlParams.get('from') ?? new Date(Date.now() - 30 * 86400000).toISOString().slice(0, 10));
const filterTo = ref(urlParams.get('to') ?? new Date().toISOString().slice(0, 10));
const applyTrigger = ref(0);

// Sub-tab / Sub-filter states
const activeMetric = ref<"cpu"|"memory"|"disk">("cpu");
const filterLocation = ref("");
const searchQuery   = ref("");

const openFlyout = ref<'filter' | 'fetch' | 'export' | null>(null);
function toggleFlyout(name: 'filter' | 'fetch' | 'export') {
    openFlyout.value = openFlyout.value === name ? null : name;
}
function closeFlyout() {
    openFlyout.value = null;
}

function applyFilter() {
    applyTrigger.value++;
    closeFlyout();
}

function formatDate(date: Date) {
    return date.toISOString().slice(0, 10);
}

// Export Logic
const exportLoading = ref(false);
const exportChecks = ref({ summary: true, resources: true, temperature: true });
async function doExport() {
    exportLoading.value = true;
    try {
        const params = new URLSearchParams({
            from: filterFrom.value,
            to: filterTo.value,
            s: exportChecks.value.summary ? '1':'0',
            r: exportChecks.value.resources ? '1':'0',
            t: exportChecks.value.temperature ? '1':'0'
        });
        window.location.href = "/server-operation/export?" + params;
        setTimeout(() => { exportLoading.value = false; closeFlyout(); }, 2000);
    } catch (e) {
        exportLoading.value = false;
    }
}

// Fetch Logic
const fetchDate = ref(formatDate(new Date()));
const fetchLoading = ref(false);
const fetchMessage = ref("");
const fetchStatus  = ref<"success"|"error">("success");

async function doFetch() {
    fetchLoading.value = true;
    fetchMessage.value = "";
    const csrf = (document.querySelector("meta[name=csrf-token]") as HTMLMetaElement)?.content ?? "";
    try {
        const res = await fetch("/server-operation/fetch", {
            method: "POST",
            headers: { "Content-Type":"application/json", "Accept":"application/json", "X-CSRF-TOKEN":csrf },
            body: JSON.stringify({ date: fetchDate.value })
        });
        const data = await res.json();
        fetchStatus.value = res.ok ? "success" : "error";
        fetchMessage.value = data.message;
        if (res.ok) applyTrigger.value++;
    } catch (e) {
        fetchStatus.value = "error";
        fetchMessage.value = "Koneksi gagal saat menarik data.";
    } finally {
        fetchLoading.value = false;
        closeFlyout();
    }
}

const periodLabel = computed(() => {
    const mo = ["Jan","Feb","Mar","Apr","Mei","Jun","Jul","Agu","Sep","Okt","Nov","Des"];
    const f = new Date(filterFrom.value + "T00:00:00");
    const t = new Date(filterTo.value   + "T00:00:00");
    return `${f.getDate()} ${mo[f.getMonth()]} ${f.getFullYear()} - ${t.getDate()} ${mo[t.getMonth()]} ${t.getFullYear()}`;
});

</script>

<template>
    <AppLayout :breadcrumbs="[
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Report', href: '/reports' },
        { title: 'Server Operation', href: '/server-operation' },
    ]">
        <Head title="Server Operation" />

        <div class="app-page-shell">

            <div class="bg-white rounded-[28px] border border-slate-200/70 shadow-xl shadow-slate-200/50">

                <!-- HEADER -->
                <div class="flex items-center justify-between gap-4 px-6 py-4 border-b border-slate-100">

                    <!-- Brand -->
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-2xl bg-[#003628] flex items-center justify-center shadow-md shadow-[#003628]/25 shrink-0">
                            <Server class="size-5 text-white"/>
                        </div>
                        <div>
                            <h1 class="text-[15px] font-black tracking-tight text-slate-900 leading-none">
                                Server <span class="text-[#003628]">Operation</span>
                            </h1>
                            <p class="text-[9px] text-slate-400 mt-0.5">Summary / Server Data / Temp / Maint</p>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-2">
                        <div v-if="openFlyout" class="fixed inset-0 z-40" @click="closeFlyout"/>

                        <span class="hidden md:block text-[10px] font-bold text-slate-400 tabular-nums select-none mr-1">
                            {{ periodLabel }}
                        </span>

                        <!-- Filter -->
                        <div class="relative z-50">
                            <button type="button"
                                class="h-8 px-3 rounded-lg border text-[10px] font-black uppercase tracking-widest flex items-center gap-1.5 transition-all"
                                :class="openFlyout==='filter'
                                    ? 'bg-slate-100 border-slate-200 text-slate-700'
                                    : 'bg-white border-slate-200 text-slate-500 hover:text-slate-700 hover:bg-slate-50'"
                                @click.stop="toggleFlyout('filter')">
                                <SlidersHorizontal class="size-3.5"/> Filter
                            </button>
                            <Transition enter-from-class="opacity-0 translate-y-1 scale-[0.97]" enter-active-class="transition-all duration-150 ease-out origin-top-right" leave-to-class="opacity-0 translate-y-1 scale-[0.97]" leave-active-class="transition-all duration-100 ease-in origin-top-right">
                            <div v-if="openFlyout==='filter'"
                                class="fixed md:absolute right-4 md:right-0 top-auto md:top-10 z-50 w-72 bg-white rounded-2xl border border-slate-200 shadow-2xl shadow-slate-900/10 p-5 space-y-3"
                                @click.stop>
                                <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Rentang Tanggal</p>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="text-[9px] font-bold text-slate-400 uppercase block mb-1">Dari</label>
                                        <input v-model="filterFrom" type="date" :max="filterTo" class="w-full h-9 px-3 rounded-xl border border-slate-100 bg-slate-50 text-[11px] font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#003628]/20"/>
                                    </div>
                                    <div>
                                        <label class="text-[9px] font-bold text-slate-400 uppercase block mb-1">Sampai</label>
                                        <input v-model="filterTo" type="date" :min="filterFrom" :max="formatDate(new Date())" class="w-full h-9 px-3 rounded-xl border border-slate-100 bg-slate-50 text-[11px] font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#003628]/20"/>
                                    </div>
                                </div>
                                <button type="button" class="w-full h-9 rounded-xl bg-[#003628] text-white text-[10px] font-black uppercase tracking-widest hover:opacity-90 transition-all flex items-center justify-center gap-1.5 shadow-md shadow-[#003628]/20 active:scale-95" @click="applyFilter">
                                    <RefreshCw class="size-3.5"/> Terapkan
                                </button>
                            </div>
                            </Transition>
                        </div>

                        <!-- Export -->
                        <div class="relative z-50">
                            <button type="button"
                                class="h-8 px-3 rounded-lg border text-[10px] font-black uppercase tracking-widest flex items-center gap-1.5 transition-all"
                                :class="openFlyout==='export'
                                    ? 'bg-slate-100 border-slate-200 text-slate-700'
                                    : 'bg-white border-slate-200 text-slate-500 hover:text-slate-700 hover:bg-slate-50'"
                                @click.stop="toggleFlyout('export')">
                                <FileDown class="size-3.5"/> Export
                            </button>
                            <Transition enter-from-class="opacity-0 translate-y-1 scale-[0.97]" enter-active-class="transition-all duration-150 ease-out origin-top-right" leave-to-class="opacity-0 translate-y-1 scale-[0.97]" leave-active-class="transition-all duration-100 ease-in origin-top-right">
                            <div v-if="openFlyout==='export'"
                                class="fixed md:absolute right-4 md:right-0 top-auto md:top-10 z-50 w-64 bg-white rounded-2xl border border-slate-200 shadow-2xl shadow-slate-900/10 p-5 space-y-3"
                                @click.stop>
                                <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Export Excel</p>
                                <p class="text-[10px] text-slate-400 tabular-nums -mt-1">{{ periodLabel }}</p>
                                <div class="space-y-2 bg-slate-50 rounded-xl p-3 border border-slate-100">
                                    <label class="flex items-center gap-2.5 cursor-pointer">
                                        <input type="checkbox" v-model="exportChecks.summary" class="h-3.5 w-3.5 rounded border-slate-300 text-[#003628] focus:ring-[#003628]/20"/>
                                        <span class="text-[11px] font-bold text-slate-700">Summary</span>
                                    </label>
                                    <label class="flex items-center gap-2.5 cursor-pointer">
                                        <input type="checkbox" v-model="exportChecks.resources" class="h-3.5 w-3.5 rounded border-slate-300 text-[#003628] focus:ring-[#003628]/20"/>
                                        <span class="text-[11px] font-bold text-slate-700">Server Data</span>
                                    </label>
                                    <label class="flex items-center gap-2.5 cursor-pointer">
                                        <input type="checkbox" v-model="exportChecks.temperature" class="h-3.5 w-3.5 rounded border-slate-300 text-[#003628] focus:ring-[#003628]/20"/>
                                        <span class="text-[11px] font-bold text-slate-700">Temperature</span>
                                    </label>
                                </div>
                                <button type="button"
                                    :disabled="exportLoading || (!exportChecks.summary && !exportChecks.resources && !exportChecks.temperature)"
                                    class="w-full h-9 rounded-xl bg-[#003628] text-white text-[10px] font-black uppercase tracking-widest hover:opacity-90 transition-all disabled:opacity-50 flex items-center justify-center gap-1.5 shadow-md shadow-[#003628]/20 active:scale-95"
                                    @click="doExport">
                                    <Loader2 v-if="exportLoading" class="size-3.5 animate-spin"/>
                                    <FileDown v-else class="size-3.5"/> Download
                                </button>
                            </div>
                            </Transition>
                        </div>

                        <!-- Fetch -->
                        <div class="relative z-50">
                            <button type="button"
                                class="h-8 px-3.5 rounded-lg bg-[#003628] text-white text-[10px] font-black uppercase tracking-widest flex items-center gap-1.5 hover:opacity-90 transition-all shadow-md shadow-[#003628]/25 active:scale-95"
                                @click.stop="toggleFlyout('fetch')">
                                <Play class="size-3.5"/> Fetch Data
                            </button>
                            <Transition enter-from-class="opacity-0 translate-y-1 scale-[0.97]" enter-active-class="transition-all duration-150 ease-out origin-top-right" leave-to-class="opacity-0 translate-y-1 scale-[0.97]" leave-active-class="transition-all duration-100 ease-in origin-top-right">
                            <div v-if="openFlyout==='fetch'"
                                class="fixed md:absolute right-4 md:right-0 top-auto md:top-10 z-50 w-64 bg-white rounded-2xl border border-slate-200 shadow-2xl shadow-slate-900/10 p-5 space-y-3"
                                @click.stop>
                                <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Sinkron PRTG</p>
                                <div>
                                    <label class="text-[9px] font-bold text-slate-400 uppercase block mb-1">Tanggal</label>
                                    <input v-model="fetchDate" type="date" :max="formatDate(new Date())"
                                        class="w-full h-9 px-3 rounded-xl border border-slate-100 bg-slate-50 text-[11px] font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#003628]/20"/>
                                </div>
                                <button type="button"
                                    class="w-full h-9 rounded-xl bg-[#003628] text-white text-[10px] font-black uppercase tracking-widest hover:opacity-90 transition-all flex items-center justify-center gap-1.5 disabled:opacity-50 shadow-md shadow-[#003628]/20 active:scale-95"
                                    :disabled="fetchLoading" @click="doFetch">
                                    <Loader2 v-if="fetchLoading" class="size-3.5 animate-spin"/>
                                    <Play v-else class="size-3.5"/>
                                    {{ fetchLoading ? "Fetching..." : "Fetch Sekarang" }}
                                </button>
                                <Transition enter-from-class="opacity-0 -translate-y-1" enter-active-class="transition-all duration-200">
                                <div v-if="fetchMessage" class="flex items-start gap-2 px-3 py-2 rounded-xl text-[10px] font-bold border"
                                    :class="fetchStatus === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-700' : 'bg-rose-50 border-rose-200 text-rose-700'">
                                    <CheckCircle2 v-if="fetchStatus==='success'" class="size-3.5 shrink-0 mt-0.5"/>
                                    <XCircle v-else class="size-3.5 shrink-0 mt-0.5"/>
                                    <span>{{ fetchMessage }}</span>
                                </div>
                                </Transition>
                            </div>
                            </Transition>
                        </div>
                    </div>
                </div>

                <!-- TAB BAR -->

                <!-- Fetch feedback (setelah close flyout) -->
                <Transition enter-from-class="opacity-0 -translate-y-1" enter-active-class="transition-all duration-200">
                <div v-if="fetchMessage" class="px-6 py-3 border-b border-slate-100 flex items-start gap-2 text-[10px] font-bold"
                    :class="fetchStatus === 'success' ? 'bg-emerald-50/80 text-emerald-800' : 'bg-rose-50/80 text-rose-800'">
                    <CheckCircle2 v-if="fetchStatus==='success'" class="size-3.5 shrink-0 mt-0.5"/>
                    <XCircle v-else class="size-3.5 shrink-0 mt-0.5"/>
                    <span>{{ fetchMessage }}</span>
                </div>
                </Transition>
                <div class="flex items-center justify-between gap-2 px-6 border-b border-slate-100 bg-slate-50/40 min-h-[44px]">
                    <!-- Main tabs left -->
                    <div class="flex items-end gap-0 self-stretch">
                        <button v-for="tab in tabs" :key="tab.id" type="button"
                            class="flex items-center gap-1.5 h-full px-4 text-[10px] font-black uppercase tracking-widest transition-all border-b-2 -mb-px"
                            :class="activeTab === tab.id ? 'border-[#003628] text-[#003628]' : 'border-transparent text-slate-400 hover:text-slate-600'"
                            @click="setTab(tab.id)">
                            <component :is="tab.icon" class="size-3.5"/>
                            {{ tab.label }}
                        </button>
                    </div>

                    <!-- Sub-views / Filters right -->
                    <div class="flex items-center gap-2 py-2 shrink-0">
                        <!-- Server Data Sub-Tabs -->
                        <template v-if="activeTab === 'data'">
                            <div class="flex items-end gap-0 self-stretch mr-2">
                                <button type="button"
                                    class="flex items-center gap-1.5 h-full px-3 text-[10px] font-black uppercase tracking-widest transition-all border-b-2 -mb-px"
                                    :class="activeMetric==='cpu' ? 'border-[#003628] text-[#003628]' : 'border-transparent text-slate-400 hover:text-slate-600'"
                                    @click="activeMetric='cpu'">
                                    <Cpu class="size-3.5"/> CPU
                                </button>
                                <button type="button"
                                    class="flex items-center gap-1.5 h-full px-3 text-[10px] font-black uppercase tracking-widest transition-all border-b-2 -mb-px"
                                    :class="activeMetric==='memory' ? 'border-[#003628] text-[#003628]' : 'border-transparent text-slate-400 hover:text-slate-600'"
                                    @click="activeMetric='memory'">
                                    <Activity class="size-3.5"/> RAM
                                </button>
                                <button type="button"
                                    class="flex items-center gap-1.5 h-full px-3 text-[10px] font-black uppercase tracking-widest transition-all border-b-2 -mb-px"
                                    :class="activeMetric==='disk' ? 'border-[#003628] text-[#003628]' : 'border-transparent text-slate-400 hover:text-slate-600'"
                                    @click="activeMetric='disk'">
                                    <HardDrive class="size-3.5"/> Disk
                                </button>
                            </div>
                            <div class="h-5 w-px bg-slate-200 mr-2"/>
                        </template>

                        <!-- Global Filters -->
                        <select v-if="activeTab !== 'summary'" v-model="filterLocation"
                            class="h-7 px-2 rounded-lg border border-slate-200 bg-white text-[9px] font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#003628]/20">
                            <option value="">Semua Lokasi</option>
                            <option v-for="loc in props.locations" :key="loc" :value="loc">{{ loc }}</option>
                        </select>
                        <div v-if="activeTab !== 'summary'" class="relative">
                            <Search class="size-3 absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"/>
                            <input v-model="searchQuery" type="text" :placeholder="activeTab === 'temp' ? 'Cari sensor...' : 'Cari server...'"
                                class="h-7 pl-7 pr-2.5 rounded-lg border border-slate-200 bg-white text-[9px] font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#003628]/20 w-32"/>
                        </div>
                    </div>
                </div>

                <!-- TAB CONTENT -->
                <div class="p-6">
                    <Suspense>
                        <component
                            :is="currentComponent"
                            :filter-from="filterFrom"
                            :filter-to="filterTo"
                            :apply-trigger="applyTrigger"
                            :locations="props.locations ?? []"
                            :active-metric="activeMetric"
                            :filter-location="filterLocation"
                            :search-query="searchQuery"
                        />
                        <template #fallback>
                            <div class="space-y-3 py-2">
                                <div v-for="n in 4" :key="n" class="h-14 bg-slate-50 rounded-xl border border-slate-100 animate-pulse"/>
                            </div>
                        </template>
                    </Suspense>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
