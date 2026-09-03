<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { BarChart3, TrendingUp, Clock, Package, AlertCircle, Calendar, ArrowUpRight, ArrowDownRight } from 'lucide-vue-next';
import { ref, onMounted } from 'vue';

const stats = ref<any>(null);
const isLoading = ref(true);

onMounted(async () => {
    try {
        const response = await fetch('/reports/stats');
        if (response.ok) {
            stats.value = await response.json();
        }
    } catch (error) {
        console.error('Gagal mengambil data laporan:', error);
    } finally {
        isLoading.value = false;
    }
});

function getPercentage(count: number, total: number) {
    if (total === 0) return 0;
    return Math.round((count / total) * 100);
}

const slaTotal = computed(() => {
    if (!stats.value?.slaPerformance) return 0;
    return stats.value.slaPerformance.reduce((acc: number, curr: any) => acc + curr.count, 0);
});

import { computed } from 'vue';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
</script>

<template>
    <Head title="Reporting & Analytics" />

    <AppLayout :breadcrumbs="[{ title: 'Dashboard', href: '/dashboard' }, { title: 'Reports', href: '/reports' }]">
        <div class="mb-10 flex flex-col md:flex-row md:items-end justify-between gap-4">
            <div>
                <h1 class="text-4xl font-black text-slate-800 tracking-tight mb-2">Reporting & <span class="text-primary">Analytics</span></h1>
                <p class="text-slate-500 font-medium">Pantau performa aset, SLA helpdesk, dan konsumsi logistik secara real-time.</p>
            </div>
            <div class="flex gap-2">
                <div class="px-4 py-2 bg-white border border-slate-200 rounded-2xl shadow-sm flex items-center gap-2">
                    <Calendar class="w-4 h-4 text-slate-400" />
                    <span class="text-sm font-bold text-slate-600">Terakhir 3 Bulan</span>
                </div>
            </div>
        </div>

        <div v-if="isLoading" class="grid grid-cols-1 md:grid-cols-3 gap-6 animate-pulse">
            <div v-for="i in 3" :key="i" class="h-64 bg-slate-100 rounded-3xl"></div>
        </div>

        <div v-else class="space-y-8">
            <!-- Top Stats Row -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Asset Aging -->
                <Card class="border-none shadow-xl shadow-slate-200/50 rounded-[2.5rem] overflow-hidden bg-white group hover:translate-y-[-4px] transition-all duration-500">
                    <CardHeader class="pb-2 pt-8 px-8">
                        <div class="w-12 h-12 bg-blue-50 rounded-2xl flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                            <Package class="w-6 h-6 text-blue-600" />
                        </div>
                        <CardTitle class="text-xl font-black tracking-tight">Asset Aging</CardTitle>
                        <CardDescription class="font-medium text-slate-400">Distribusi umur aset berdasarkan tahun perolehan.</CardDescription>
                    </CardHeader>
                    <CardContent class="px-8 pb-8">
                        <div class="mt-6 flex items-end gap-2 h-32">
                            <div v-for="item in stats?.assetAging" :key="item.year" class="flex-1 flex flex-col items-center gap-2 group/bar">
                                <div 
                                    class="w-full bg-blue-100 rounded-lg relative overflow-hidden group-hover/bar:bg-blue-600 transition-colors"
                                    :style="{ height: `${(item.count / 100) * 100}%` }"
                                >
                                    <div class="absolute inset-x-0 top-0 h-1 bg-white/20"></div>
                                </div>
                                <span class="text-[10px] font-black text-slate-400 uppercase tracking-tighter">{{ item.year }}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- SLA Performance -->
                <Card class="border-none shadow-xl shadow-slate-200/50 rounded-[2.5rem] overflow-hidden bg-white group hover:translate-y-[-4px] transition-all duration-500">
                    <CardHeader class="pb-2 pt-8 px-8">
                        <div class="w-12 h-12 bg-emerald-50 rounded-2xl flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                            <Clock class="w-6 h-6 text-emerald-600" />
                        </div>
                        <CardTitle class="text-xl font-black tracking-tight">SLA Performance</CardTitle>
                        <CardDescription class="font-medium text-slate-400">Kecepatan penyelesaian tiket helpdesk.</CardDescription>
                    </CardHeader>
                    <CardContent class="px-8 pb-8">
                        <div class="mt-6 space-y-4">
                            <div v-for="item in stats?.slaPerformance" :key="item.label" class="space-y-1.5">
                                <div class="flex justify-between text-xs font-black uppercase tracking-widest text-slate-500">
                                    <span>{{ item.label }}</span>
                                    <span class="text-slate-800">{{ getPercentage(item.count, slaTotal) }}%</span>
                                </div>
                                <div class="h-3 w-full bg-slate-100 rounded-full overflow-hidden">
                                    <div 
                                        class="h-full bg-emerald-500 rounded-full transition-all duration-1000"
                                        :style="{ width: `${getPercentage(item.count, slaTotal)}%` }"
                                    ></div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Consumable Burn Rate -->
                <Card class="border-none shadow-xl shadow-slate-200/50 rounded-[2.5rem] overflow-hidden bg-white group hover:translate-y-[-4px] transition-all duration-500">
                    <CardHeader class="pb-2 pt-8 px-8">
                        <div class="w-12 h-12 bg-amber-50 rounded-2xl flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                            <TrendingUp class="w-6 h-6 text-amber-600" />
                        </div>
                        <CardTitle class="text-xl font-black tracking-tight">Consumable Usage</CardTitle>
                        <CardDescription class="font-medium text-slate-400">Trend penggunaan logistik bulanan.</CardDescription>
                    </CardHeader>
                    <CardContent class="px-8 pb-8">
                        <div class="mt-6 h-32 flex items-end gap-1">
                            <div v-for="(item, index) in stats?.consumableRate" :key="item.month" class="flex-1 flex flex-col items-center gap-2 group/line">
                                <div class="w-full bg-amber-100 rounded-t-lg group-hover/line:bg-amber-500 transition-colors" :style="{ height: `${(item.usage / 600) * 100}%` }"></div>
                                <span class="text-[10px] font-black text-slate-400 uppercase tracking-tighter">{{ item.month }}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Detailed Table/Grid Placeholder -->
            <Card class="border-none shadow-xl shadow-slate-200/50 rounded-[2.5rem] overflow-hidden bg-white">
                <div class="p-8 border-b border-slate-50 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-black text-slate-800">Critical Alerts</h3>
                        <p class="text-sm font-medium text-slate-400">Aset yang membutuhkan perhatian segera.</p>
                    </div>
                    <Button variant="outline" class="rounded-xl border-slate-200 text-xs font-bold uppercase tracking-widest">Lihat Semua</Button>
                </div>
                <div class="divide-y divide-slate-50">
                    <div v-for="i in 3" :key="i" class="p-6 flex items-center gap-4 hover:bg-slate-50/50 transition-colors cursor-pointer group">
                        <div class="w-12 h-12 rounded-2xl bg-rose-50 flex items-center justify-center shrink-0">
                            <AlertCircle class="w-6 h-6 text-rose-500" />
                        </div>
                        <div class="flex-1">
                            <p class="text-sm font-black text-slate-700">Dell Latitude 5420 - LP-00{{ i }}</p>
                            <p class="text-xs font-medium text-slate-400">Garansi berakhir dalam 15 hari</p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs font-black text-rose-500 uppercase tracking-widest">Urgent</p>
                            <p class="text-[10px] font-medium text-slate-400">Exp: 12 May 2024</p>
                        </div>
                        <ArrowUpRight class="w-5 h-5 text-slate-300 group-hover:text-primary transition-colors" />
                    </div>
                </div>
            </Card>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Custom animations or refinements */
</style>
