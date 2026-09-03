<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CreateServerOpComponents extends Command
{
    protected $signature = 'create:server-op';
    protected $description = 'Create all Server Operation Vue components in one go';

    public function handle()
    {
        $base = resource_path('js/pages/Report/ServerOperation');
        @mkdir($base, 0755, true);

        // Create each file with a simple static approach
        $files = [
            'TabTemperature.vue',
            'TabServerData.vue',
            'TabSummary.vue',
            'Index.vue',
        ];

        foreach ($files as $f) {
            $this->createComponent($base, $f);
        }

        $this->info('✅ All components created! Run: npm run build');
        return 0;
    }

    private function createComponent($base, $name)
    {
        $path = "{$base}/{$name}";
        if ($name === 'Index.vue') {
            file_put_contents($path, $this->indexContent());
        } else if ($name === 'TabSummary.vue') {
            file_put_contents($path, $this->summaryContent());
        } else if ($name === 'TabServerData.vue') {
            file_put_contents($path, $this->serverDataContent());
        } else if ($name === 'TabTemperature.vue') {
            file_put_contents($path, $this->temperatureContent());
        }
        $this->line("✓ {$name}");
    }

    private function indexContent() { return '<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import { ref, computed, defineAsyncComponent } from "vue";
import AppLayout from "@/layouts/AppLayout.vue";
import {
    Server, Thermometer, Database, LayoutDashboard, Filter, FileDown, Play, RefreshCw, Loader2,
    CheckCircle2, XCircle,
} from "lucide-vue-next";

defineProps<{ locations: string[] }>();

const TabSummary = defineAsyncComponent(() => import("./TabSummary.vue"));
const TabServerData = defineAsyncComponent(() => import("./TabServerData.vue"));
const TabTemperature = defineAsyncComponent(() => import("./TabTemperature.vue"));

const urlParams = new URLSearchParams(window.location.search);
const activeTab = ref<string>(urlParams.get("tab") ?? "summary");

const tabs = [
    { id: "summary", label: "Summary", icon: LayoutDashboard },
    { id: "server-data", label: "Server Data", icon: Database },
    { id: "server-temperature", label: "Server Temperature", icon: Thermometer },
];

function setTab(id: string) {
    activeTab.value = id;
    const url = new URL(window.location.href);
    url.searchParams.set("tab", id);
    window.history.replaceState({}, "", url.toString());
}

const currentComponent = computed(() => {
    switch (activeTab.value) {
        case "server-data": return TabServerData;
        case "server-temperature": return TabTemperature;
        default: return TabSummary;
    }
});

function formatDate(d: Date) { return d.toISOString().slice(0, 10); }

const filterFrom = ref(formatDate(new Date(Date.now() - 29 * 86400000)));
const filterTo = ref(formatDate(new Date()));
const fetchDate = ref(formatDate(new Date(Date.now() - 86400000)));

const openFlyout = ref<"filter" | "export" | "fetch" | null>(null);
const isFetching = ref(false);
const fetchMessage = ref("");
const fetchError = ref("");

function closeFlyout() { openFlyout.value = null; }
function handleTabChange(id: string) { setTab(id); }

async function handleFetch() {
    if (isFetching.value) return;
    isFetching.value = true;
    fetchError.value = "";
    fetchMessage.value = "Fetching data...";
    try {
        const response = await fetch("/api/server-operation/fetch", {
            method: "POST",
            headers: { "Content-Type": "application/json", "X-Requested-With": "XMLHttpRequest" },
            body: JSON.stringify({ fetch_date: fetchDate.value })
        });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        const data = await response.json();
        fetchMessage.value = data.message || "Data fetched successfully!";
        setTimeout(() => { openFlyout.value = null; }, 2000);
    } catch (err: any) {
        fetchError.value = err.message || "Failed to fetch data";
    } finally {
        isFetching.value = false;
    }
}

async function handleExport() {
    try {
        const response = await fetch("/api/server-operation/export", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ from_date: filterFrom.value, to_date: filterTo.value, tab: activeTab.value })
        });
        if (!response.ok) throw new Error(`Export failed: ${response.status}`);
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = url;
        a.download = `server-operation-${activeTab.value}-${new Date().toISOString().slice(0, 10)}.xlsx`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
    } catch (err: any) {
        console.error("Export error:", err);
    }
}
</script>

<template>
    <Head title="Server Operation" />
    <AppLayout>
        <div class="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 p-6">
            <div class="mb-8">
                <div class="flex items-center gap-3 mb-3">
                    <div class="p-3 bg-blue-100 rounded-lg"><Server class="w-7 h-7 text-blue-600" /></div>
                    <div>
                        <h1 class="text-3xl font-bold text-slate-900">Server Operation</h1>
                        <p class="text-slate-600 mt-1">CPU, Memory, Disk, and Temperature Monitoring</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow mb-6">
                <div class="flex gap-1 p-4 border-b border-slate-200 overflow-x-auto">
                    <button v-for="tab in tabs" :key="tab.id" @click="handleTabChange(tab.id)" :class="[
                        \"px-4 py-2 rounded-lg font-medium transition flex items-center gap-2 whitespace-nowrap\",
                        activeTab === tab.id ? \"bg-blue-100 text-blue-700\" : \"text-slate-600 hover:bg-slate-100\"
                    ]">
                        <component :is="tab.icon" class="w-4 h-4" />
                        {{ tab.label }}
                    </button>
                </div>
                <div class="flex gap-2 p-4 border-b border-slate-200 flex-wrap">
                    <button @click="openFlyout = openFlyout === \"filter\" ? null : \"filter\"" class="flex items-center gap-2 px-4 py-2 bg-slate-100 text-slate-700 rounded-lg hover:bg-slate-200 transition">
                        <Filter class="w-4 h-4" /> Filter
                    </button>
                    <button @click="handleExport" class="flex items-center gap-2 px-4 py-2 bg-green-100 text-green-700 rounded-lg hover:bg-green-200 transition">
                        <FileDown class="w-4 h-4" /> Export
                    </button>
                    <button @click="openFlyout = openFlyout === \"fetch\" ? null : \"fetch\"" class="flex items-center gap-2 px-4 py-2 bg-orange-100 text-orange-700 rounded-lg hover:bg-orange-200 transition">
                        <Play class="w-4 h-4" /> Fetch Data
                    </button>
                </div>
                <div v-if="openFlyout === \"filter\"" class="p-4 bg-slate-50 border-b border-slate-200">
                    <h3 class="font-semibold mb-3">Date Range Filter</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div><label class="block text-sm font-medium text-slate-700 mb-1">From Date</label><input v-model="filterFrom" type="date" class="w-full px-3 py-2 border border-slate-300 rounded-lg" /></div>
                        <div><label class="block text-sm font-medium text-slate-700 mb-1">To Date</label><input v-model="filterTo" type="date" class="w-full px-3 py-2 border border-slate-300 rounded-lg" /></div>
                    </div>
                    <button @click="closeFlyout" class="mt-3 w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">Apply</button>
                </div>
                <div v-if="openFlyout === \"fetch\"" class="p-4 bg-slate-50 border-b border-slate-200">
                    <h3 class="font-semibold mb-3">Fetch Data</h3>
                    <div class="mb-3"><label class="block text-sm font-medium text-slate-700 mb-1">Fetch Date</label><input v-model="fetchDate" type="date" class="w-full px-3 py-2 border border-slate-300 rounded-lg" /></div>
                    <button @click="handleFetch" :disabled="isFetching" class="w-full px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition disabled:opacity-50">
                        <span v-if="isFetching" class="flex items-center justify-center gap-2"><Loader2 class="w-4 h-4 animate-spin" />Fetching...</span>
                        <span v-else>Start Fetch</span>
                    </button>
                    <div v-if="fetchMessage" class="mt-3 p-3 bg-green-100 text-green-700 rounded-lg flex items-center gap-2"><CheckCircle2 class="w-5 h-5" />{{ fetchMessage }}</div>
                    <div v-if="fetchError" class="mt-3 p-3 bg-red-100 text-red-700 rounded-lg flex items-center gap-2"><XCircle class="w-5 h-5" />{{ fetchError }}</div>
                </div>
            </div>
            <component :is="currentComponent" :fromDate="filterFrom" :toDate="filterTo" />
        </div>
    </AppLayout>
</template>

<style scoped></style>'; }

    private function summaryContent() { return '<script setup lang="ts">
import { ref, onMounted } from "vue";
import { AlertCircle, TrendingUp, Server, Thermometer } from "lucide-vue-next";

defineProps<{ fromDate: string; toDate: string }>();

const summary = ref<any>(null);
const loading = ref(true);
const error = ref("");

onMounted(async () => {
    try {
        const response = await fetch("/api/server-operation/summary?from=" + new Date().toISOString().split("T")[0] + "&to=" + new Date().toISOString().split("T")[0]);
        if (!response.ok) throw new Error("Failed to load summary");
        summary.value = await response.json();
    } catch (err: any) {
        error.value = err.message;
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div class="space-y-6">
        <div v-if="loading" class="flex justify-center py-8"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div></div>
        <div v-if="error" class="p-4 bg-red-100 text-red-700 rounded-lg flex items-center gap-2"><AlertCircle class="w-5 h-5" />{{ error }}</div>
        <div v-if="summary && !loading" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-white p-6 rounded-lg shadow"><div class="flex items-center justify-between"><div><p class="text-slate-600 text-sm">Total Devices</p><p class="text-3xl font-bold text-slate-900 mt-1">{{ summary.total_devices }}</p></div><Server class="w-12 h-12 text-blue-500 opacity-20" /></div></div>
            <div class="bg-white p-6 rounded-lg shadow"><div class="flex items-center justify-between"><div><p class="text-slate-600 text-sm">Avg CPU Usage</p><p class="text-3xl font-bold text-slate-900 mt-1">{{ summary.avg_cpu_usage?.toFixed(1) }}%</p></div><TrendingUp class="w-12 h-12 text-green-500 opacity-20" /></div></div>
            <div class="bg-white p-6 rounded-lg shadow"><div class="flex items-center justify-between"><div><p class="text-slate-600 text-sm">Avg Memory Usage</p><p class="text-3xl font-bold text-slate-900 mt-1">{{ summary.avg_memory_usage?.toFixed(1) }}%</p></div><TrendingUp class="w-12 h-12 text-orange-500 opacity-20" /></div></div>
            <div class="bg-white p-6 rounded-lg shadow"><div class="flex items-center justify-between"><div><p class="text-slate-600 text-sm">Avg Temperature</p><p class="text-3xl font-bold text-slate-900 mt-1">{{ summary.avg_temperature?.toFixed(1) }}°C</p></div><Thermometer class="w-12 h-12 text-red-500 opacity-20" /></div></div>
        </div>
    </div>
</template>

<style scoped></style>'; }

    private function serverDataContent() { return '<script setup lang="ts">
import { ref, onMounted, computed } from "vue";
import { AlertCircle, ChevronUp, ChevronDown, Minus } from "lucide-vue-next";

defineProps<{ fromDate: string; toDate: string }>();

const data = ref<any[]>([]);
const loading = ref(true);
const error = ref("");
const sortBy = ref("device_name");
const sortDesc = ref(false);

onMounted(async () => {
    try {
        const response = await fetch("/api/server-operation/data?from=" + new Date().toISOString().split("T")[0] + "&to=" + new Date().toISOString().split("T")[0]);
        if (!response.ok) throw new Error("Failed to load server data");
        const result = await response.json();
        data.value = result.devices || [];
    } catch (err: any) {
        error.value = err.message;
    } finally {
        loading.value = false;
    }
});

const sorted = computed(() => {
    const copy = [...data.value];
    copy.sort((a, b) => {
        const aVal = a[sortBy.value] || "";
        const bVal = b[sortBy.value] || "";
        const cmp = aVal > bVal ? 1 : aVal < bVal ? -1 : 0;
        return sortDesc.value ? -cmp : cmp;
    });
    return copy;
});
</script>

<template>
    <div>
        <div v-if="loading" class="flex justify-center py-8"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div></div>
        <div v-if="error" class="p-4 bg-red-100 text-red-700 rounded-lg flex items-center gap-2"><AlertCircle class="w-5 h-5" />{{ error }}</div>
        <div v-if="!loading && data.length > 0" class="bg-white rounded-lg shadow overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-100 border-b border-slate-300">
                    <tr>
                        <th class="px-6 py-3 text-left text-sm font-medium text-slate-700 cursor-pointer" @click="sortBy = \"device_name\""><div class="flex items-center gap-2">Device <component :is="sortBy === \"device_name\" ? (sortDesc ? ChevronDown : ChevronUp) : Minus" class="w-4 h-4" /></div></th>
                        <th class="px-6 py-3 text-left text-sm font-medium text-slate-700">IP Address</th>
                        <th class="px-6 py-3 text-left text-sm font-medium text-slate-700">Location</th>
                        <th class="px-6 py-3 text-left text-sm font-medium text-slate-700 cursor-pointer" @click="sortBy = \"cpu_usage_percent\""><div class="flex items-center gap-2">CPU (%) <component :is="sortBy === \"cpu_usage_percent\" ? (sortDesc ? ChevronDown : ChevronUp) : Minus" class="w-4 h-4" /></div></th>
                        <th class="px-6 py-3 text-left text-sm font-medium text-slate-700">Memory (%)</th>
                        <th class="px-6 py-3 text-left text-sm font-medium text-slate-700">Disk Free</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <tr v-for="device in sorted" :key="device.host_id" class="hover:bg-slate-50">
                        <td class="px-6 py-3 text-sm font-medium text-slate-900">{{ device.device_name }}</td>
                        <td class="px-6 py-3 text-sm text-slate-700">{{ device.ip_address }}</td>
                        <td class="px-6 py-3 text-sm text-slate-700">{{ device.location }}</td>
                        <td class="px-6 py-3 text-sm"><div class="flex items-center gap-2"><div class="w-16 bg-slate-200 rounded h-2"><div class="bg-blue-500 rounded h-2" :style="{ width: (device.cpu_usage_percent || 0) + \"%\" }"></div></div><span class="text-slate-700">{{ device.cpu_usage_percent?.toFixed(1) }}%</span></div></td>
                        <td class="px-6 py-3 text-sm"><div class="flex items-center gap-2"><div class="w-16 bg-slate-200 rounded h-2"><div class="bg-green-500 rounded h-2" :style="{ width: (device.memory_usage_percent || 0) + \"%\" }"></div></div><span class="text-slate-700">{{ device.memory_usage_percent?.toFixed(1) }}%</span></div></td>
                        <td class="px-6 py-3 text-sm text-slate-700">{{ device.hdd_free_percent }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-if="!loading && data.length === 0" class="text-center py-8 text-slate-600">No server data available</div>
    </div>
</template>

<style scoped></style>'; }

    private function temperatureContent() { return '<script setup lang="ts">
import { ref, onMounted, computed } from "vue";
import { AlertCircle, ChevronUp, ChevronDown, Minus, Thermometer } from "lucide-vue-next";

defineProps<{ fromDate: string; toDate: string }>();

const data = ref<any[]>([]);
const loading = ref(true);
const error = ref("");
const sortBy = ref("location");
const sortDesc = ref(false);

onMounted(async () => {
    try {
        const response = await fetch("/api/server-operation/temperature?from=" + new Date().toISOString().split("T")[0] + "&to=" + new Date().toISOString().split("T")[0]);
        if (!response.ok) throw new Error("Failed to load temperature data");
        const result = await response.json();
        data.value = result.sensors || [];
    } catch (err: any) {
        error.value = err.message;
    } finally {
        loading.value = false;
    }
});

const sorted = computed(() => {
    const copy = [...data.value];
    copy.sort((a, b) => {
        const aVal = a[sortBy.value] || "";
        const bVal = b[sortBy.value] || "";
        const cmp = aVal > bVal ? 1 : aVal < bVal ? -1 : 0;
        return sortDesc.value ? -cmp : cmp;
    });
    return copy;
});

function getTempColor(temp: number) {
    if (temp < 30) return "bg-blue-100 text-blue-900";
    if (temp < 40) return "bg-green-100 text-green-900";
    if (temp < 50) return "bg-yellow-100 text-yellow-900";
    if (temp < 60) return "bg-orange-100 text-orange-900";
    return "bg-red-100 text-red-900";
}
</script>

<template>
    <div>
        <div v-if="loading" class="flex justify-center py-8"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div></div>
        <div v-if="error" class="p-4 bg-red-100 text-red-700 rounded-lg flex items-center gap-2"><AlertCircle class="w-5 h-5" />{{ error }}</div>
        <div v-if="!loading && data.length > 0" class="bg-white rounded-lg shadow overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-100 border-b border-slate-300">
                    <tr>
                        <th class="px-6 py-3 text-left text-sm font-medium text-slate-700">Sensor ID</th>
                        <th class="px-6 py-3 text-left text-sm font-medium text-slate-700 cursor-pointer" @click="sortBy = \"location\""><div class="flex items-center gap-2">Location <component :is="sortBy === \"location\" ? (sortDesc ? ChevronDown : ChevronUp) : Minus" class="w-4 h-4" /></div></th>
                        <th class="px-6 py-3 text-left text-sm font-medium text-slate-700">Description</th>
                        <th class="px-6 py-3 text-left text-sm font-medium text-slate-700 cursor-pointer" @click="sortBy = \"value_celsius\""><div class="flex items-center gap-2">Temperature <component :is="sortBy === \"value_celsius\" ? (sortDesc ? ChevronDown : ChevronUp) : Minus" class="w-4 h-4" /></div></th>
                        <th class="px-6 py-3 text-left text-sm font-medium text-slate-700">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <tr v-for="sensor in sorted" :key="sensor.id" class="hover:bg-slate-50">
                        <td class="px-6 py-3 text-sm font-medium text-slate-900">{{ sensor.sensor_id }}</td>
                        <td class="px-6 py-3 text-sm text-slate-700">{{ sensor.location }}</td>
                        <td class="px-6 py-3 text-sm text-slate-700">{{ sensor.description }}</td>
                        <td class="px-6 py-3 text-sm"><span :class="[\"px-3 py-1 rounded-full font-medium flex items-center gap-2 w-fit\", getTempColor(sensor.value_celsius)]"><Thermometer class="w-4 h-4" />{{ sensor.value_celsius?.toFixed(1) }}°C</span></td>
                        <td class="px-6 py-3 text-sm text-slate-700">{{ sensor.report_date }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-if="!loading && data.length === 0" class="text-center py-8 text-slate-600">No temperature data available</div>
    </div>
</template>

<style scoped></style>'; }
}
