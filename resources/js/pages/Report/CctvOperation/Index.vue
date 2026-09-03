<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import { ref, defineAsyncComponent, computed } from "vue";
import AppLayout from "@/layouts/AppLayout.vue";
import {
    Camera, LayoutDashboard, Play, RefreshCw,
    Loader2, Fingerprint, Server, FileDown,
    CheckCircle2, XCircle, SlidersHorizontal, Activity,
    FileText, AlertCircle, Search,
} from "lucide-vue-next";

defineProps<{ sites: string[] }>();

const TabSummary = defineAsyncComponent(() => import("./TabSummary.vue"));
const TabDevice  = defineAsyncComponent(() => import("./TabDevice.vue"));

const urlParams = new URLSearchParams(window.location.search);
const activeTab = ref<string>(urlParams.get("tab") ?? "summary");

const mainTabs = [
    { id: "summary", label: "Summary",     icon: LayoutDashboard },
    { id: "nvr",     label: "NVR",         icon: Server          },
    { id: "cctv",    label: "CCTV",        icon: Camera          },
    { id: "finger",  label: "Fingerprint", icon: Fingerprint     },
];

const activeView     = ref<"uptime"|"record"|"maintenance">("uptime");
const filterLocation = ref("");
const searchDevice   = ref("");
const locationOptions = ref<string[]>([]);

function setTab(id: string) {
    activeTab.value = id;
    activeView.value = "uptime";
    filterLocation.value = "";
    searchDevice.value = "";
    locationOptions.value = [];
    const url = new URL(window.location.href);
    url.searchParams.set("tab", id);
    window.history.replaceState({}, "", url.toString());
}

const isDeviceTab = computed(() => activeTab.value !== "summary");
const isNvrTab    = computed(() => activeTab.value === "nvr");

function formatDate(d: Date) { return d.toISOString().slice(0, 10); }

const filterFrom   = ref(formatDate(new Date(Date.now() - 29 * 86400000)));
const filterTo     = ref(formatDate(new Date()));
const applyTrigger = ref(0);
const openFlyout   = ref<"filter"|"fetch"|"export"|null>(null);

function toggleFlyout(name: "filter"|"fetch"|"export") {
    openFlyout.value = openFlyout.value === name ? null : name;
}
function closeFlyout() { openFlyout.value = null; }
function applyFilter() { applyTrigger.value++; closeFlyout(); }

const fetchDate    = ref(formatDate(new Date(Date.now() - 86400000)));
const fetchLoading = ref(false);
const fetchMsg     = ref("");
const fetchOk      = ref(false);

async function doFetch() {
    fetchLoading.value = true; fetchMsg.value = ""; fetchOk.value = false;
    const csrf = (document.querySelector("meta[name=csrf-token]") as HTMLMetaElement)?.content ?? "";
    try {
        const res = await fetch("/cctv-operation/fetch", {
            method: "POST",
            headers: { "Content-Type": "application/json", "Accept": "application/json", "X-CSRF-TOKEN": csrf },
            body: JSON.stringify({ date: fetchDate.value }),
        });
        const d = await res.json();
        fetchMsg.value = d.message ?? "Selesai";
        fetchOk.value  = res.ok;
        applyTrigger.value++;
    } catch (e: any) {
        fetchMsg.value = "Error: " + e.message;
    } finally { fetchLoading.value = false; }
}

const exportChecks  = ref({ summary: true, nvr: true, cctv: true, finger: true });
const exportLoading = ref(false);

async function doExport() {
    exportLoading.value = true;
    const params = new URLSearchParams({
        from:    filterFrom.value,
        to:      filterTo.value,
        summary: exportChecks.value.summary ? '1' : '0',
        nvr:     exportChecks.value.nvr     ? '1' : '0',
        cctv:    exportChecks.value.cctv    ? '1' : '0',
        finger:  exportChecks.value.finger  ? '1' : '0',
    });
    window.location.href = '/cctv-operation/export?' + params.toString();
    setTimeout(() => { exportLoading.value = false; closeFlyout(); }, 1000);
}

const periodLabel = computed(() => {
    const mo = ["Jan","Feb","Mar","Apr","Mei","Jun","Jul","Agu","Sep","Okt","Nov","Des"];
    const f = new Date(filterFrom.value + "T00:00:00");
    const t = new Date(filterTo.value   + "T00:00:00");
    return `${f.getDate()} ${mo[f.getMonth()]} ${f.getFullYear()} - ${t.getDate()} ${mo[t.getMonth()]} ${t.getFullYear()}`;
});

function onLocationsLoaded(locs: string[]) {
    locationOptions.value = locs;
}
</script>

<template>
    <AppLayout :breadcrumbs="[
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Report', href: '/reports' },
        { title: 'CCTV Operation', href: '/cctv-operation' },
    ]">
        <Head title="CCTV Operation" />

        <div class="app-page-shell">

            <div class="bg-white rounded-[28px] border border-slate-200/70 shadow-xl shadow-slate-200/50">

                <!-- HEADER -->
                <div class="flex items-center justify-between gap-4 px-6 py-4 border-b border-slate-100">

                    <!-- Brand -->
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-2xl bg-[#003628] flex items-center justify-center shadow-md shadow-[#003628]/25 shrink-0">
                            <Camera class="size-5 text-white"/>
                        </div>
                        <div>
                            <h1 class="text-[15px] font-black tracking-tight text-slate-900 leading-none">
                                CCTV <span class="text-[#003628]">Operation</span>
                            </h1>
                            <p class="text-[9px] text-slate-400 mt-0.5">NVR / CCTV / Fingerprint</p>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-2">
                        <div v-if="openFlyout" class="fixed inset-0 z-40" @click="closeFlyout"/>

                        <!-- Period — plain text, no box -->
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
                                        <input v-model="filterFrom" type="date" class="w-full h-9 px-3 rounded-xl border border-slate-100 bg-slate-50 text-[11px] font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#003628]/20"/>
                                    </div>
                                    <div>
                                        <label class="text-[9px] font-bold text-slate-400 uppercase block mb-1">Sampai</label>
                                        <input v-model="filterTo" type="date" class="w-full h-9 px-3 rounded-xl border border-slate-100 bg-slate-50 text-[11px] font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#003628]/20"/>
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
                                    <label v-for="[key, lbl] in [['summary','Summary'],['nvr','NVR'],['cctv','CCTV'],['finger','Fingerprint']]" :key="key" class="flex items-center gap-2.5 cursor-pointer">
                                        <input type="checkbox" v-model="(exportChecks as any)[key]" class="h-3.5 w-3.5 rounded border-slate-300 text-[#003628] focus:ring-[#003628]/20"/>
                                        <span class="text-[11px] font-bold text-slate-700">{{ lbl }}</span>
                                    </label>
                                </div>
                                <button type="button"
                                    :disabled="exportLoading || (!exportChecks.summary && !exportChecks.nvr && !exportChecks.cctv && !exportChecks.finger)"
                                    class="w-full h-9 rounded-xl bg-[#003628] text-white text-[10px] font-black uppercase tracking-widest hover:opacity-90 transition-all disabled:opacity-50 flex items-center justify-center gap-1.5 shadow-md shadow-[#003628]/20 active:scale-95"
                                    @click="doExport">
                                    <Loader2 v-if="exportLoading" class="size-3.5 animate-spin"/>
                                    <FileDown v-else class="size-3.5"/> Download
                                </button>
                            </div>
                            </Transition>
                        </div>

                        <!-- Fetch — only colored button -->
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
                                <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Fetch Uptime Data</p>
                                <div>
                                    <label class="text-[9px] font-bold text-slate-400 uppercase block mb-1">Tanggal</label>
                                    <input v-model="fetchDate" type="date" class="w-full h-9 px-3 rounded-xl border border-slate-100 bg-slate-50 text-[11px] font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#003628]/20"/>
                                </div>
                                <button type="button"
                                    class="w-full h-9 rounded-xl bg-[#003628] text-white text-[10px] font-black uppercase tracking-widest hover:opacity-90 transition-all flex items-center justify-center gap-1.5 disabled:opacity-50 shadow-md shadow-[#003628]/20 active:scale-95"
                                    :disabled="fetchLoading" @click="doFetch">
                                    <Loader2 v-if="fetchLoading" class="size-3.5 animate-spin"/>
                                    <Play v-else class="size-3.5"/>
                                    {{ fetchLoading ? "Fetching..." : "Fetch Sekarang" }}
                                </button>
                                <Transition enter-from-class="opacity-0 -translate-y-1" enter-active-class="transition-all duration-200">
                                <div v-if="fetchMsg" class="flex items-start gap-2 px-3 py-2 rounded-xl text-[10px] font-bold border"
                                    :class="fetchOk ? 'bg-emerald-50 border-emerald-200 text-emerald-700' : 'bg-rose-50 border-rose-200 text-rose-700'">
                                    <CheckCircle2 v-if="fetchOk" class="size-3.5 shrink-0 mt-0.5"/>
                                    <XCircle v-else class="size-3.5 shrink-0 mt-0.5"/>
                                    <span>{{ fetchMsg }}</span>
                                </div>
                                </Transition>
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

                    <!-- Sub-views right (device tabs only) -->
                    <div v-if="isDeviceTab" class="flex items-center gap-2 py-2 shrink-0">
                        <div class="flex items-end gap-0 self-stretch">
                            <button type="button"
                                class="flex items-center gap-1.5 h-full px-3 text-[10px] font-black uppercase tracking-widest transition-all border-b-2 -mb-px"
                                :class="activeView==='uptime' ? 'border-[#003628] text-[#003628]' : 'border-transparent text-slate-400 hover:text-slate-600'"
                                @click="activeView='uptime'">
                                <Activity class="size-3.5"/> Uptime
                            </button>
                            <button v-if="isNvrTab" type="button"
                                class="flex items-center gap-1.5 h-full px-3 text-[10px] font-black uppercase tracking-widest transition-all border-b-2 -mb-px"
                                :class="activeView==='record' ? 'border-[#003628] text-[#003628]' : 'border-transparent text-slate-400 hover:text-slate-600'"
                                @click="activeView='record'">
                                <FileText class="size-3.5"/> Duration Record
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
                        <div class="relative">
                            <Search class="size-3 absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"/>
                            <input v-model="searchDevice" type="text" placeholder="Cari device..."
                                class="h-7 pl-7 pr-2.5 rounded-lg border border-slate-200 bg-white text-[9px] font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#003628]/20 w-32"/>
                        </div>
                    </div>
                </div>

                <!-- TAB CONTENT -->
                <div class="p-6">
                    <Suspense>
                        <TabSummary v-if="activeTab==='summary'"
                            :filter-from="filterFrom" :filter-to="filterTo" :apply-trigger="applyTrigger"/>
                        <TabDevice  v-else-if="activeTab==='nvr'"
                            device-type="nvr"
                            :filter-from="filterFrom" :filter-to="filterTo" :apply-trigger="applyTrigger"
                            :active-view="activeView" :filter-location="filterLocation" :search-device="searchDevice"
                            @locations-loaded="onLocationsLoaded"/>
                        <TabDevice  v-else-if="activeTab==='cctv'"
                            device-type="cctv"
                            :filter-from="filterFrom" :filter-to="filterTo" :apply-trigger="applyTrigger"
                            :active-view="activeView" :filter-location="filterLocation" :search-device="searchDevice"
                            @locations-loaded="onLocationsLoaded"/>
                        <TabDevice  v-else-if="activeTab==='finger'"
                            device-type="finger"
                            :filter-from="filterFrom" :filter-to="filterTo" :apply-trigger="applyTrigger"
                            :active-view="activeView" :filter-location="filterLocation" :search-device="searchDevice"
                            @locations-loaded="onLocationsLoaded"/>
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
