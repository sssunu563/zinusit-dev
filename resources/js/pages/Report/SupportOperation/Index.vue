<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, computed, defineAsyncComponent, Suspense } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    LifeBuoy, LayoutDashboard, Database,
    SlidersHorizontal, FileDown, RefreshCw, Loader2,
} from 'lucide-vue-next';

const TabSummary = defineAsyncComponent(() => import('./TabSummary.vue'));
const TabSupportData = defineAsyncComponent(() => import('./TabSupportData.vue'));

const urlParams = new URLSearchParams(window.location.search);
const activeTab = ref<string>(urlParams.get('tab') ?? 'summary');

const tabs = [
    { id: 'summary', label: 'Summary', icon: LayoutDashboard, component: TabSummary },
    { id: 'data', label: 'Support Data', icon: Database, component: TabSupportData },
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

const filterFrom = ref(urlParams.get('from') ?? new Date(Date.now() - 30 * 86400000).toISOString().slice(0, 10));
const filterTo = ref(urlParams.get('to') ?? new Date().toISOString().slice(0, 10));
const applyTrigger = ref(0);

const openFlyout = ref<'filter' | 'export' | null>(null);
function toggleFlyout(name: 'filter' | 'export') {
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

const periodLabel = computed(() => {
    const mo = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    const f = new Date(filterFrom.value + 'T00:00:00');
    const t = new Date(filterTo.value + 'T00:00:00');
    return `${f.getDate()} ${mo[f.getMonth()]} ${f.getFullYear()} - ${t.getDate()} ${mo[t.getMonth()]} ${t.getFullYear()}`;
});

const exportLoading = ref(false);
const exportChecks = ref({ summary: true, data: true });
async function doExport() {
    exportLoading.value = true;
    try {
        const params = new URLSearchParams({
            from: filterFrom.value,
            to: filterTo.value,
            summary: exportChecks.value.summary ? '1' : '0',
            data: exportChecks.value.data ? '1' : '0',
        });
        window.location.href = '/support-operation/export?' + params;
        setTimeout(() => { exportLoading.value = false; closeFlyout(); }, 2000);
    } catch {
        exportLoading.value = false;
    }
}
</script>

<template>
    <AppLayout :breadcrumbs="[
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Report', href: '/reports' },
        { title: 'Support Operation', href: '/support-operation' },
    ]">
        <Head title="Support Operation" />

        <div class="app-page-shell">

            <div class="bg-white rounded-[28px] border border-slate-200/70 shadow-xl shadow-slate-200/50">

                <div class="flex items-center justify-between gap-4 px-6 py-4 border-b border-slate-100">

                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-2xl bg-[#003628] flex items-center justify-center shadow-md shadow-[#003628]/25 shrink-0">
                            <LifeBuoy class="size-5 text-white" />
                        </div>
                        <div>
                            <h1 class="text-[15px] font-black tracking-tight text-slate-900 leading-none">
                                Support <span class="text-[#003628]">Operation</span>
                            </h1>
                            <p class="text-[9px] text-slate-400 mt-0.5">Summary / Support Data</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <div v-if="openFlyout" class="fixed inset-0 z-40" @click="closeFlyout" />

                        <span class="hidden md:block text-[10px] font-bold text-slate-400 tabular-nums select-none mr-1">
                            {{ periodLabel }}
                        </span>

                        <div class="relative z-50">
                            <button type="button"
                                class="h-8 px-3 rounded-lg border text-[10px] font-black uppercase tracking-widest flex items-center gap-1.5 transition-all"
                                :class="openFlyout==='filter'
                                    ? 'bg-slate-100 border-slate-200 text-slate-700'
                                    : 'bg-white border-slate-200 text-slate-500 hover:text-slate-700 hover:bg-slate-50'"
                                @click.stop="toggleFlyout('filter')">
                                <SlidersHorizontal class="size-3.5" /> Filter
                            </button>
                            <Transition enter-from-class="opacity-0 translate-y-1 scale-[0.97]" enter-active-class="transition-all duration-150 ease-out origin-top-right" leave-to-class="opacity-0 translate-y-1 scale-[0.97]" leave-active-class="transition-all duration-100 ease-in origin-top-right">
                                <div v-if="openFlyout==='filter'"
                                    class="fixed md:absolute right-4 md:right-0 top-auto md:top-10 z-50 w-72 bg-white rounded-2xl border border-slate-200 shadow-2xl shadow-slate-900/10 p-5 space-y-3"
                                    @click.stop>
                                    <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Rentang Tanggal</p>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <label class="text-[9px] font-bold text-slate-400 uppercase block mb-1">Dari</label>
                                            <input v-model="filterFrom" type="date" :max="filterTo" class="w-full h-9 px-3 rounded-xl border border-slate-100 bg-slate-50 text-[11px] font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#003628]/20" />
                                        </div>
                                        <div>
                                            <label class="text-[9px] font-bold text-slate-400 uppercase block mb-1">Sampai</label>
                                            <input v-model="filterTo" type="date" :min="filterFrom" :max="formatDate(new Date())" class="w-full h-9 px-3 rounded-xl border border-slate-100 bg-slate-50 text-[11px] font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#003628]/20" />
                                        </div>
                                    </div>
                                    <button type="button" class="w-full h-9 rounded-xl bg-[#003628] text-white text-[10px] font-black uppercase tracking-widest hover:opacity-90 transition-all flex items-center justify-center gap-1.5 shadow-md shadow-[#003628]/20 active:scale-95" @click="applyFilter">
                                        <RefreshCw class="size-3.5" /> Terapkan
                                    </button>
                                </div>
                            </Transition>
                        </div>

                        <div class="relative z-50">
                            <button type="button"
                                class="h-8 px-3 rounded-lg border text-[10px] font-black uppercase tracking-widest flex items-center gap-1.5 transition-all"
                                :class="openFlyout==='export'
                                    ? 'bg-slate-100 border-slate-200 text-slate-700'
                                    : 'bg-white border-slate-200 text-slate-500 hover:text-slate-700 hover:bg-slate-50'"
                                @click.stop="toggleFlyout('export')">
                                <FileDown class="size-3.5" /> Export
                            </button>
                            <Transition enter-from-class="opacity-0 translate-y-1 scale-[0.97]" enter-active-class="transition-all duration-150 ease-out origin-top-right" leave-to-class="opacity-0 translate-y-1 scale-[0.97]" leave-active-class="transition-all duration-100 ease-in origin-top-right">
                                <div v-if="openFlyout==='export'"
                                    class="fixed md:absolute right-4 md:right-0 top-auto md:top-10 z-50 w-64 bg-white rounded-2xl border border-slate-200 shadow-2xl shadow-slate-900/10 p-5 space-y-3"
                                    @click.stop>
                                    <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Export Excel</p>
                                    <p class="text-[10px] text-slate-400 tabular-nums -mt-1">{{ periodLabel }}</p>
                                    <div class="space-y-2 bg-slate-50 rounded-xl p-3 border border-slate-100">
                                        <label class="flex items-center gap-2.5 cursor-pointer">
                                            <input type="checkbox" v-model="exportChecks.summary" class="h-3.5 w-3.5 rounded border-slate-300 text-[#003628] focus:ring-[#003628]/20" />
                                            <span class="text-[11px] font-bold text-slate-700">Summary</span>
                                        </label>
                                        <label class="flex items-center gap-2.5 cursor-pointer">
                                            <input type="checkbox" v-model="exportChecks.data" class="h-3.5 w-3.5 rounded border-slate-300 text-[#003628] focus:ring-[#003628]/20" />
                                            <span class="text-[11px] font-bold text-slate-700">Support Data</span>
                                        </label>
                                    </div>
                                    <button type="button"
                                        :disabled="exportLoading || (!exportChecks.summary && !exportChecks.data)"
                                        class="w-full h-9 rounded-xl bg-[#003628] text-white text-[10px] font-black uppercase tracking-widest hover:opacity-90 transition-all disabled:opacity-50 flex items-center justify-center gap-1.5 shadow-md shadow-[#003628]/20 active:scale-95"
                                        @click="doExport">
                                        <Loader2 v-if="exportLoading" class="size-3.5 animate-spin" />
                                        <FileDown v-else class="size-3.5" /> Download
                                    </button>
                                </div>
                            </Transition>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2 px-6 border-b border-slate-100 bg-slate-50/40 min-h-[44px]">
                    <div class="flex items-end gap-0 self-stretch">
                        <button v-for="tab in tabs" :key="tab.id" type="button"
                            class="flex items-center gap-1.5 h-full px-4 text-[10px] font-black uppercase tracking-widest transition-all border-b-2 -mb-px"
                            :class="activeTab === tab.id ? 'border-[#003628] text-[#003628]' : 'border-transparent text-slate-400 hover:text-slate-600'"
                            @click="setTab(tab.id)">
                            <component :is="tab.icon" class="size-3.5" />
                            {{ tab.label }}
                        </button>
                    </div>
                </div>

                <div class="p-6">
                    <Suspense>
                        <component
                            :is="currentComponent"
                            :filter-from="filterFrom"
                            :filter-to="filterTo"
                            :apply-trigger="applyTrigger"
                        />
                        <template #fallback>
                            <div class="space-y-3 py-2">
                                <div v-for="n in 4" :key="n" class="h-14 bg-slate-50 rounded-xl border border-slate-100 animate-pulse" />
                            </div>
                        </template>
                    </Suspense>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
