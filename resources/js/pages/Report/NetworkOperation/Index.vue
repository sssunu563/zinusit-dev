<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, computed, defineAsyncComponent } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    Network, Activity, Shield, Wifi, LayoutDashboard,
    FileDown, Play, RefreshCw, Loader2,
    CheckCircle2, XCircle, Database, Radio, SlidersHorizontal,
    FileText, AlertCircle, Search, Settings,
} from 'lucide-vue-next';

defineProps<{ locations: string[] }>();

const TabSummary   = defineAsyncComponent(() => import('./TabSummary.vue'));
const TabUptime    = defineAsyncComponent(() => import('./TabUptime.vue'));
const TabIspSla    = defineAsyncComponent(() => import('./TabIspSla.vue'));
const TabBandwidth = defineAsyncComponent(() => import('./TabBandwidth.vue'));

const urlParams = new URLSearchParams(window.location.search);
const activeTab = ref<string>(urlParams.get('tab') ?? 'summary');

const mainTabs = [
    { id: 'summary',   label: 'Summary',        icon: LayoutDashboard },
    { id: 'uptime',    label: 'Uptime & Backup', icon: Activity },
    { id: 'isp-sla',   label: 'ISP SLA',         icon: Shield },
    { id: 'bandwidth', label: 'Bandwidth Usage', icon: Wifi },
];

// Sub-views for uptime tab
const activeView     = ref<"uptime"|"backup"|"maintenance">("uptime");
const filterLocation = ref("");
const filterGroup    = ref("");
const searchDevice   = ref("");
const locationOptions = ref<string[]>([]);
const groupOptions    = ref<string[]>([]);

const isUptimeTab = computed(() => activeTab.value === 'uptime');

function setTab(id: string) {
    activeTab.value = id;
    activeView.value = "uptime";
    filterLocation.value = "";
    filterGroup.value = "";
    searchDevice.value = "";
    locationOptions.value = [];
    groupOptions.value = [];
    const url = new URL(window.location.href);
    url.searchParams.set('tab', id);
    window.history.replaceState({}, '', url.toString());
}

const currentComponent = computed(() => {
    switch (activeTab.value) {
        case 'uptime':    return TabUptime;
        case 'isp-sla':   return TabIspSla;
        case 'bandwidth': return TabBandwidth;
        default:          return TabSummary;
    }
});

function formatDate(d: Date) { return d.toISOString().slice(0, 10); }

const filterFrom = ref(formatDate(new Date(Date.now() - 29 * 86400000)));
const filterTo   = ref(formatDate(new Date()));
const fetchDate  = ref(formatDate(new Date(Date.now() - 86400000)));

const openFlyout = ref<'filter' | 'export' | 'fetch' | null>(null);
function toggleFlyout(name: 'filter' | 'export' | 'fetch') {
    openFlyout.value = openFlyout.value === name ? null : name;
}
function closeFlyout() { openFlyout.value = null; }

const applyTrigger = ref(0);
function applyFilter() { applyTrigger.value++; closeFlyout(); }

const fetchLoading  = ref(false);
const fetchMessage  = ref('');
const fetchStatus   = ref<'idle' | 'success' | 'error'>('idle');
const fetchType     = ref<'uptime' | 'bandwidth' | null>(null);

async function doFetch(type: 'uptime' | 'bandwidth') {
    if (!fetchDate.value) return;
    fetchLoading.value = true;
    fetchMessage.value = '';
    fetchStatus.value  = 'idle';
    fetchType.value    = type;
    const csrf = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
    const url = type === 'uptime' ? '/uptime/fetch' : '/network-operation/bandwidth/fetch';
    try {
        const res  = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ date: fetchDate.value }),
        });
        const data = await res.json();
        fetchMessage.value = data.message ?? '';
        fetchStatus.value  = res.ok || res.status === 206 ? 'success' : 'error';
        if (data.errors?.length) fetchMessage.value += '\n' + data.errors.join('\n');
        if (res.ok) { applyFilter(); }
    } catch {
        fetchMessage.value = 'Gagal menghubungi server.';
        fetchStatus.value  = 'error';
    } finally {
        fetchLoading.value = false;
        closeFlyout();
    }
}

const exportChecks = ref({ summary: true, bandwidth: true, uptime: false, isp: false });
const exportLoading = ref(false);

async function doExport() {
    exportLoading.value = true;
    const params = new URLSearchParams({
        from:      filterFrom.value,
        to:        filterTo.value,
        summary:   exportChecks.value.summary   ? '1' : '0',
        bandwidth: exportChecks.value.bandwidth ? '1' : '0',
        uptime:    exportChecks.value.uptime    ? '1' : '0',
        isp:       exportChecks.value.isp       ? '1' : '0',
    });
    window.location.href = '/network-operation/export?' + params.toString();
    setTimeout(() => { exportLoading.value = false; closeFlyout(); }, 1000);
}

const periodLabel = computed(() => {
    const mo = ["Jan","Feb","Mar","Apr","Mei","Jun","Jul","Agu","Sep","Okt","Nov","Des"];
    const f = new Date(filterFrom.value + "T00:00:00");
    const t = new Date(filterTo.value   + "T00:00:00");
    return `${f.getDate()} ${mo[f.getMonth()]} ${f.getFullYear()} - ${t.getDate()} ${mo[t.getMonth()]} ${t.getFullYear()}`;
});

function onLocationsLoaded(locs: string[]) { locationOptions.value = locs; }
function onGroupsLoaded(groups: string[])   { groupOptions.value = groups; }

// Settings modal for backup — passed through to TabUptime
const showSettingsModal = ref(false);
</script>

<template>
    <AppLayout :breadcrumbs="[
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Report', href: '/reports' },
        { title: 'Network Operation', href: '/network-operation' },
    ]">
        <Head title="Network Operation" />

        <div class="app-page-shell">

            <div class="bg-white rounded-[28px] border border-slate-200/70 shadow-xl shadow-slate-200/50">

                <!-- HEADER -->
                <div class="flex items-center justify-between gap-4 px-6 py-4 border-b border-slate-100">

                    <!-- Brand -->
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-2xl bg-[#003628] flex items-center justify-center shadow-md shadow-[#003628]/25 shrink-0">
                            <Network class="size-5 text-white"/>
                        </div>
                        <div>
                            <h1 class="text-[15px] font-black tracking-tight text-slate-900 leading-none">
                                Network <span class="text-[#003628]">Operation</span>
                            </h1>
                            <p class="text-[9px] text-slate-400 mt-0.5">Uptime / ISP SLA / Bandwidth</p>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-2">
                        <div v-if="openFlyout" class="fixed inset-0 z-40" @click="closeFlyout"/>

                        <!-- Period plain text -->
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
                                        <input type="checkbox" v-model="exportChecks.bandwidth" class="h-3.5 w-3.5 rounded border-slate-300 text-[#003628] focus:ring-[#003628]/20"/>
                                        <span class="text-[11px] font-bold text-slate-700">Bandwidth Usage</span>
                                    </label>
                                    <label class="flex items-center gap-2.5 cursor-pointer">
                                        <input type="checkbox" v-model="exportChecks.uptime" class="h-3.5 w-3.5 rounded border-slate-300 text-[#003628] focus:ring-[#003628]/20"/>
                                        <span class="text-[11px] font-bold text-slate-700">Uptime Harian</span>
                                    </label>
                                    <label class="flex items-center gap-2.5 cursor-pointer">
                                        <input type="checkbox" v-model="exportChecks.isp" class="h-3.5 w-3.5 rounded border-slate-300 text-[#003628] focus:ring-[#003628]/20"/>
                                        <span class="text-[11px] font-bold text-slate-700">ISP SLA</span>
                                    </label>
                                </div>
                                <p class="text-[9px] text-slate-400">Setiap tab = 1 sheet dalam file Excel</p>
                                <button type="button"
                                    :disabled="exportLoading || (!exportChecks.bandwidth && !exportChecks.uptime && !exportChecks.isp && !exportChecks.summary)"
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
                                <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Tarik Data</p>
                                <div>
                                    <label class="text-[9px] font-bold text-slate-400 uppercase block mb-1">Tanggal</label>
                                    <input v-model="fetchDate" type="date" :max="formatDate(new Date())" class="w-full h-9 px-3 rounded-xl border border-slate-100 bg-slate-50 text-[11px] font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#003628]/20"/>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <button type="button" :disabled="fetchLoading"
                                        class="h-9 rounded-xl bg-[#003628] text-white text-[10px] font-black uppercase tracking-widest hover:opacity-90 transition-all disabled:opacity-50 flex items-center justify-center gap-1.5 shadow-sm active:scale-95"
                                        @click="doFetch('uptime')">
                                        <Loader2 v-if="fetchLoading && fetchType==='uptime'" class="size-3 animate-spin"/>
                                        <Radio v-else class="size-3"/>
                                        Uptime
                                    </button>
                                    <button type="button" :disabled="fetchLoading"
                                        class="h-9 rounded-xl border border-slate-200 bg-slate-50 text-slate-700 text-[10px] font-black uppercase tracking-widest hover:bg-slate-100 transition-all disabled:opacity-50 flex items-center justify-center gap-1.5 active:scale-95"
                                        @click="doFetch('bandwidth')">
                                        <Loader2 v-if="fetchLoading && fetchType==='bandwidth'" class="size-3 animate-spin"/>
                                        <Database v-else class="size-3"/>
                                        Bandwidth
                                    </button>
                                </div>
                            </div>
                            </Transition>
                        </div>
                    </div>
                </div>

                <!-- TAB BAR -->
                <div class="flex items-center justify-between gap-2 px-6 border-b border-slate-100 bg-slate-50/40 min-h-[44px]">

                    <!-- Main tabs left -->
                    <div class="flex items-end gap-0 self-stretch">
                        <button v-for="tab in mainTabs" :key="tab.id" type="button"
                            class="flex items-center gap-1.5 h-full px-4 text-[10px] font-black uppercase tracking-widest transition-all border-b-2 -mb-px"
                            :class="activeTab === tab.id ? 'border-[#003628] text-[#003628]' : 'border-transparent text-slate-400 hover:text-slate-600'"
                            @click="setTab(tab.id)">
                            <component :is="tab.icon" class="size-3.5"/>
                            {{ tab.label }}
                        </button>
                    </div>

                    <!-- Sub-views right (uptime tab only) -->
                    <div v-if="isUptimeTab" class="flex items-center gap-2 py-2 shrink-0">
                        <div class="flex items-end gap-0 self-stretch">
                            <button type="button"
                                class="flex items-center gap-1.5 h-full px-3 text-[10px] font-black uppercase tracking-widest transition-all border-b-2 -mb-px"
                                :class="activeView==='uptime' ? 'border-[#003628] text-[#003628]' : 'border-transparent text-slate-400 hover:text-slate-600'"
                                @click="activeView='uptime'">
                                <Activity class="size-3.5"/> Uptime
                            </button>
                            <button type="button"
                                class="flex items-center gap-1.5 h-full px-3 text-[10px] font-black uppercase tracking-widest transition-all border-b-2 -mb-px"
                                :class="activeView==='backup' ? 'border-[#003628] text-[#003628]' : 'border-transparent text-slate-400 hover:text-slate-600'"
                                @click="activeView='backup'">
                                <FileText class="size-3.5"/> Backup Config
                            </button>
                            <button type="button"
                                class="flex items-center gap-1.5 h-full px-3 text-[10px] font-black uppercase tracking-widest transition-all border-b-2 -mb-px"
                                :class="activeView==='maintenance' ? 'border-rose-500 text-rose-500' : 'border-transparent text-slate-400 hover:text-slate-600'"
                                @click="activeView='maintenance'">
                                <AlertCircle class="size-3.5"/> Maintenance
                            </button>
                        </div>
                        <div class="h-5 w-px bg-slate-200"/>
                        <select v-model="filterLocation"
                            class="h-7 px-2.5 rounded-lg border border-slate-200 bg-white text-[9px] font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#003628]/20">
                            <option value="">Semua Lokasi</option>
                            <option v-for="loc in locationOptions" :key="loc" :value="loc">{{ loc }}</option>
                        </select>
                        <select v-model="filterGroup"
                            class="h-7 px-2.5 rounded-lg border border-slate-200 bg-white text-[9px] font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#003628]/20">
                            <option value="">Semua Group</option>
                            <option v-for="g in groupOptions" :key="g" :value="g">{{ g }}</option>
                        </select>
                        <div class="relative">
                            <Search class="size-3 absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"/>
                            <input v-model="searchDevice" type="text" placeholder="Cari device..."
                                class="h-7 pl-7 pr-2.5 rounded-lg border border-slate-200 bg-white text-[9px] font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#003628]/20 w-32"/>
                        </div>
                        <button v-if="activeView==='backup'" type="button"
                            class="h-7 px-2.5 rounded-lg border border-slate-200 bg-white text-[9px] font-black uppercase tracking-widest text-slate-500 hover:bg-slate-50 flex items-center gap-1 transition-all"
                            @click="showSettingsModal=true">
                            <Settings class="size-3"/> Settings
                        </button>
                    </div>
                </div>

                <!-- Fetch result message -->
                <div v-if="fetchMessage" class="mx-6 mt-4">
                    <div class="flex items-start gap-2.5 px-4 py-3 rounded-xl text-[11px] font-bold border whitespace-pre-line"
                        :class="fetchStatus==='success' ? 'bg-emerald-50 border-emerald-200 text-emerald-700' : 'bg-rose-50 border-rose-200 text-rose-700'">
                        <CheckCircle2 v-if="fetchStatus==='success'" class="size-4 shrink-0 mt-0.5"/>
                        <XCircle v-else class="size-4 shrink-0 mt-0.5"/>
                        <span>{{ fetchMessage }}</span>
                    </div>
                </div>

                <!-- TAB CONTENT -->
                <div class="p-6">
                    <Suspense>
                        <TabSummary v-if="activeTab==='summary'"
                            :filter-from="filterFrom" :filter-to="filterTo" :apply-trigger="applyTrigger" :locations="[]"/>
                        <TabUptime v-else-if="activeTab==='uptime'"
                            :filter-from="filterFrom" :filter-to="filterTo" :apply-trigger="applyTrigger" :locations="[]"
                            :active-view="activeView" :filter-location="filterLocation" :filter-group="filterGroup" :search-device="searchDevice"
                            :show-settings-modal="showSettingsModal"
                            @locations-loaded="onLocationsLoaded" @groups-loaded="onGroupsLoaded"
                            @update:show-settings-modal="showSettingsModal=$event"/>
                        <TabIspSla v-else-if="activeTab==='isp-sla'"
                            :filter-from="filterFrom" :filter-to="filterTo" :apply-trigger="applyTrigger" :locations="[]"/>
                        <TabBandwidth v-else-if="activeTab==='bandwidth'"
                            :filter-from="filterFrom" :filter-to="filterTo" :apply-trigger="applyTrigger" :locations="[]"/>
                        <template #fallback>
                            <div class="space-y-3 py-2">
                                <div v-for="n in 3" :key="n" class="h-20 bg-slate-50 rounded-xl border border-slate-100 animate-pulse"/>
                            </div>
                        </template>
                    </Suspense>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
