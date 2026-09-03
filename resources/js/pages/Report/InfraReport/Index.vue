<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    Shield,
    Calendar,
    FileText,
    LayoutDashboard,
    FileDown,
    SlidersHorizontal,
    RefreshCw,
    CheckCircle2,
    AlertCircle,
    ChevronLeft,
    ChevronRight,
} from 'lucide-vue-next';
import TabWeekly from './TabWeekly.vue';
import TabMonthly from './TabMonthly.vue';

const activeTab = ref<'weekly' | 'monthly'>('weekly');

// --- Weekly Logic ---
// --- Weekly Logic ---
const formatDate = (d: Date) => {
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};
const today = new Date();
const sevenDaysAgo = new Date();
sevenDaysAgo.setDate(today.getDate() - 6);

const weeklyFrom = ref(formatDate(sevenDaysAgo));
const weeklyTo = ref(formatDate(today));

// Flag to prevent recursive watch updates
let isAdjusting = false;

// --- Auto-adjust date handlers ---
watch(weeklyFrom, (newVal, oldVal) => {
    if (isAdjusting || !newVal || newVal === oldVal) return;
    const from = new Date(newVal + 'T00:00:00');
    if (isNaN(from.getTime())) return;

    isAdjusting = true;
    const to = new Date(from);
    to.setDate(from.getDate() + 6);
    weeklyTo.value = formatDate(to);
    dateError.value = null;
    setTimeout(() => {
        isAdjusting = false;
    }, 0);
});

watch(weeklyTo, (newVal, oldVal) => {
    if (isAdjusting || !newVal || newVal === oldVal) return;
    const to = new Date(newVal + 'T00:00:00');
    if (isNaN(to.getTime())) return;

    isAdjusting = true;
    const from = new Date(to);
    from.setDate(to.getDate() - 6);
    weeklyFrom.value = formatDate(from);
    dateError.value = null;
    setTimeout(() => {
        isAdjusting = false;
    }, 0);
});

// --- Monthly Logic ---
const currentMonth = today.getMonth(); // 0-11
const currentYear = today.getFullYear();

const monthlyMonth = ref(currentMonth + 1); // 1-12
const monthlyYear = ref(currentYear);

const months = [
    'Januari',
    'Februari',
    'Maret',
    'April',
    'Mei',
    'Juni',
    'Juli',
    'Agustus',
    'September',
    'Oktober',
    'November',
    'Desember',
];
const years = Array.from({ length: 5 }, (_, i) => currentYear - 2 + i);

// --- General UI ---
const openFlyout = ref<'filter' | 'export' | null>(null);
const toggleFlyout = (name: 'filter' | 'export') => {
    openFlyout.value = openFlyout.value === name ? null : name;
};
const closeFlyout = () => {
    openFlyout.value = null;
};

const dateError = ref<string | null>(null);
const applyTrigger = ref(0);
const applyFilter = () => {
    // Validate max 7 days for weekly
    if (activeTab.value === 'weekly') {
        const from = new Date(weeklyFrom.value + 'T00:00:00');
        const to = new Date(weeklyTo.value + 'T00:00:00');
        const diffDays = (to.getTime() - from.getTime()) / (1000 * 3600 * 24);

        if (diffDays > 6) {
            dateError.value = 'Maksimal rentang 7 hari untuk Weekly Report';
            return;
        }
        if (diffDays < 0) {
            dateError.value = 'Tanggal akhir harus setelah tanggal mulai';
            return;
        }
        dateError.value = null;
    }
    applyTrigger.value++;
    closeFlyout();
};

const doExport = () => {
    const params = new URLSearchParams({
        from: activeTab.value === 'weekly' ? weeklyFrom.value : '',
        to: activeTab.value === 'weekly' ? weeklyTo.value : '',
    });
    window.location.href = '/infra-report/export?' + params.toString();
    closeFlyout();
};

const currentComponent = computed(() =>
    activeTab.value === 'weekly' ? TabWeekly : TabMonthly,
);

const periodLabel = computed(() => {
    if (activeTab.value === 'weekly') {
        const f = new Date(weeklyFrom.value + 'T00:00:00');
        const t = new Date(weeklyTo.value + 'T00:00:00');
        const fmt = (d: Date) =>
            d.getDate() + ' ' + months[d.getMonth()] + ' ' + d.getFullYear();
        return `${fmt(f)} - ${fmt(t)}`;
    } else {
        return `${months[monthlyMonth.value - 1]} ${monthlyYear.value}`;
    }
});
</script>

<template>
    <AppLayout
        :breadcrumbs="[
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Report', href: '/reports' },
            { title: 'Infra Report', href: '/infra-report' },
        ]"
    >
        <Head title="Infra Report" />

        <div class="app-page-shell">
            <div
                class="overflow-hidden rounded-[28px] border border-slate-200/70 bg-white shadow-xl shadow-slate-200/50"
            >
                <!-- HEADER -->
                <div
                    class="flex items-center justify-between gap-4 border-b border-slate-100 bg-white px-6 py-5"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-[#003628] shadow-md shadow-[#003628]/25"
                        >
                            <Shield class="size-5 text-white" />
                        </div>
                        <div>
                            <h1
                                class="text-[16px] leading-none font-black tracking-tight text-slate-900 uppercase"
                            >
                                Infra <span class="text-[#003628]">Report</span>
                            </h1>
                            <p
                                class="mt-1.5 text-[9px] font-bold tracking-[0.2em] text-slate-400 uppercase"
                            >
                                Weekly & Monthly Integration
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <div
                            v-if="openFlyout"
                            class="fixed inset-0 z-40"
                            @click="closeFlyout"
                        />

                        <span
                            class="mr-2 hidden text-[10px] font-black tracking-widest text-slate-400 uppercase tabular-nums md:block"
                        >
                            {{ periodLabel }}
                        </span>

                        <!-- Filter -->
                        <div class="relative z-50">
                            <button
                                @click="toggleFlyout('filter')"
                                class="flex h-8 items-center gap-1.5 rounded-lg border px-3 text-[10px] font-black tracking-widest uppercase transition-all"
                                :class="
                                    openFlyout === 'filter'
                                        ? 'border-slate-200 bg-slate-100 text-slate-700'
                                        : 'border-slate-200 bg-white text-slate-500 hover:text-slate-700'
                                "
                            >
                                <SlidersHorizontal class="size-3.5" /> Filter
                            </button>

                            <Transition
                                enter-from-class="opacity-0 translate-y-1 scale-[0.97]"
                                enter-active-class="transition-all duration-150 ease-out origin-top-right"
                                leave-to-class="opacity-0 translate-y-1 scale-[0.97]"
                                leave-active-class="transition-all duration-100 ease-in origin-top-right"
                            >
                                <div
                                    v-if="openFlyout === 'filter'"
                                    class="absolute top-10 right-0 w-72 space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl"
                                    @click.stop
                                >
                                    <template v-if="activeTab === 'weekly'">
                                        <p
                                            class="text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                        >
                                            Weekly Range (Max 7 Days)
                                        </p>
                                        <div class="grid grid-cols-2 gap-2">
                                            <div>
                                                <label
                                                    class="mb-1 block text-[9px] font-bold text-slate-400 uppercase"
                                                    >Dari</label
                                                >
                                                <input
                                                    v-model="weeklyFrom"
                                                    type="date"
                                                    class="h-9 w-full rounded-xl border border-slate-100 bg-slate-50 px-3 text-[11px] font-bold text-slate-700 focus:ring-2 focus:ring-[#003628]/20 focus:outline-none"
                                                    @keydown.up.down.prevent
                                                />
                                            </div>
                                            <div>
                                                <label
                                                    class="mb-1 block text-[9px] font-bold text-slate-400 uppercase"
                                                    >Sampai</label
                                                >
                                                <input
                                                    v-model="weeklyTo"
                                                    type="date"
                                                    class="h-9 w-full rounded-xl border border-slate-100 bg-slate-50 px-3 text-[11px] font-bold text-slate-700 focus:ring-2 focus:ring-[#003628]/20 focus:outline-none"
                                                    @keydown.up.down.prevent
                                                />
                                            </div>
                                        </div>
                                    </template>
                                    <template v-else>
                                        <p
                                            class="text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                        >
                                            Select Month & Year
                                        </p>
                                        <div class="grid grid-cols-2 gap-2">
                                            <div>
                                                <label
                                                    class="mb-1 block text-[9px] font-bold text-slate-400 uppercase"
                                                    >Bulan</label
                                                >
                                                <select
                                                    v-model="monthlyMonth"
                                                    class="h-9 w-full rounded-xl border border-slate-100 bg-slate-50 px-2 text-[11px] font-bold text-slate-700 focus:outline-none"
                                                >
                                                    <option
                                                        v-for="(m, i) in months"
                                                        :key="i"
                                                        :value="i + 1"
                                                    >
                                                        {{ m }}
                                                    </option>
                                                </select>
                                            </div>
                                            <div>
                                                <label
                                                    class="mb-1 block text-[9px] font-bold text-slate-400 uppercase"
                                                    >Tahun</label
                                                >
                                                <select
                                                    v-model="monthlyYear"
                                                    class="h-9 w-full rounded-xl border border-slate-100 bg-slate-50 px-2 text-[11px] font-bold text-slate-700 focus:outline-none"
                                                >
                                                    <option
                                                        v-for="y in years"
                                                        :key="y"
                                                        :value="y"
                                                    >
                                                        {{ y }}
                                                    </option>
                                                </select>
                                            </div>
                                        </div>
                                    </template>
                                    <!-- Error Message -->
                                    <div
                                        v-if="dateError"
                                        class="flex items-center gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-[10px] font-bold text-rose-600"
                                    >
                                        <AlertCircle class="size-4" />
                                        {{ dateError }}
                                    </div>

                                    <button
                                        @click="applyFilter"
                                        class="flex h-9 w-full items-center justify-center gap-2 rounded-xl bg-[#003628] text-[10px] font-black tracking-widest text-white uppercase shadow-lg shadow-[#003628]/20 transition-all active:scale-95"
                                    >
                                        <RefreshCw class="size-3.5" /> Terapkan
                                        Filter
                                    </button>
                                </div>
                            </Transition>
                        </div>

                        <!-- Export -->
                        <button
                            @click="doExport"
                            class="flex h-8 items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-3 text-[10px] font-black tracking-widest text-slate-500 uppercase transition-all hover:bg-white hover:text-[#003628]"
                        >
                            <FileDown class="size-3.5" /> Export
                        </button>
                    </div>
                </div>

                <!-- TAB BAR -->
                <div
                    class="flex min-h-[44px] items-center gap-0 border-b border-slate-100 bg-slate-50/40 px-6"
                >
                    <button
                        @click="activeTab = 'weekly'"
                        class="-mb-px flex h-full items-center gap-2 border-b-2 px-6 text-[10px] font-black tracking-[0.2em] uppercase transition-all"
                        :class="
                            activeTab === 'weekly'
                                ? 'border-[#003628] text-[#003628]'
                                : 'border-transparent text-slate-400 hover:text-slate-600'
                        "
                    >
                        <Calendar class="size-3.5" /> Weekly Report
                    </button>
                    <button
                        @click="activeTab = 'monthly'"
                        class="-mb-px flex h-full items-center gap-2 border-b-2 px-6 text-[10px] font-black tracking-[0.2em] uppercase transition-all"
                        :class="
                            activeTab === 'monthly'
                                ? 'border-[#003628] text-[#003628]'
                                : 'border-transparent text-slate-400 hover:text-slate-600'
                        "
                    >
                        <FileText class="size-3.5" /> Monthly Report
                    </button>
                </div>

                <!-- CONTENT -->
                <div class="p-8">
                    <component
                        :is="currentComponent"
                        :from="activeTab === 'weekly' ? weeklyFrom : null"
                        :to="activeTab === 'weekly' ? weeklyTo : null"
                        :month="activeTab === 'monthly' ? monthlyMonth : null"
                        :year="activeTab === 'monthly' ? monthlyYear : null"
                        :apply-trigger="applyTrigger"
                    />
                </div>
            </div>

            <!-- Footer Note -->
            <div class="mt-6 flex items-center justify-between px-2">
                <div
                    class="flex items-center gap-2 text-[10px] font-bold text-slate-400"
                >
                    <CheckCircle2 class="size-3.5 text-emerald-500" />
                    Data aggregated from Network, CCTV, Server & Support modules
                </div>
                <div
                    class="text-[9px] font-black tracking-widest text-slate-300 uppercase"
                >
                    ZINUS IT Infrastructure Console
                </div>
            </div>
        </div>
    </AppLayout>
</template>
