<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{
    reportId: string;
    printReport: () => void;
}>();

const formatDateTime = (date?: string | null) => {
    if (!date) return '-';
    return new Date(date).toLocaleString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};
</script>

<template>
    <div class="mb-5 flex flex-col md:flex-row md:items-start md:justify-between gap-4 border-b border-border pb-5 print:hidden">
        <div class="flex-1">
            <div class="flex items-center gap-3">
                <h2 class="text-xl font-black text-[#003628] tracking-tight uppercase">Pratinjau Inspection</h2>
                <span class="inline-flex items-center rounded-full bg-emerald-600 px-2 py-0.5 text-[10px] font-black tracking-wider text-white uppercase shadow-sm">
                    Laporan Selesai
                </span>
            </div>
            <p class="mt-1 flex items-center gap-2 text-sm font-medium text-slate-400">
                <span class="font-black text-[10px] tracking-widest text-[#d99528]">{{ reportId }}</span>
                <span>•</span>
                <span class="text-[10px] font-black uppercase tracking-tight">{{ formatDateTime(new Date().toISOString()) }}</span>
            </p>
        </div>
        
        <div class="flex items-center gap-2 shrink-0">
            <button
                @click="printReport"
                class="h-9 px-4 rounded-lg bg-[#003628] text-white text-xs font-black uppercase tracking-widest shadow-md hover:brightness-110 transition-all flex items-center gap-2"
            >
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-4a2 2 0 012-2H5a2 2 0 012 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                Cetak
            </button>
            <Link href="/inspection" class="app-button-secondary">
                Kembali
            </Link>
        </div>
    </div>
</template>

<style scoped>
/* Scoped styles kept minimal */
</style>
