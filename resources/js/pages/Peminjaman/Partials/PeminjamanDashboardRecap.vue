<script setup lang="ts">
import { 
    Users, 
    CalendarClock, 
    PackageCheck, 
    ArrowUpRight,
    TrendingUp
} from 'lucide-vue-next';

interface Stats {
    activeLoans: number;
    overdueLoans: number;
    totalAssetsBorrowed: number;
}

defineProps<{
    stats: Stats;
}>();
</script>

<template>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
        <!-- Active Loans Card -->
        <div class="group relative overflow-hidden rounded-[32px] bg-white border border-slate-200 p-8 shadow-sm transition-all hover:shadow-2xl hover:shadow-primary/5 hover:-translate-y-1">
            <div class="absolute -right-10 -top-10 size-40 rounded-full bg-primary/5 blur-3xl group-hover:bg-primary/10 transition-colors" />
            
            <div class="flex items-center justify-between mb-6 relative z-10">
                <div class="size-14 rounded-2xl bg-primary/5 flex items-center justify-center text-primary group-hover:scale-110 transition-transform duration-500">
                    <PackageCheck class="size-7" />
                </div>
                <div class="flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 border border-emerald-100">
                    <TrendingUp class="size-3 text-emerald-600" />
                    <span class="text-[10px] font-black text-emerald-700 uppercase tracking-widest">Active</span>
                </div>
            </div>

            <div class="relative z-10">
                <h3 class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400 mb-2">Assets in Deployment</h3>
                <div class="flex items-baseline gap-2">
                    <span class="text-4xl font-black text-slate-900 tracking-tight italic">{{ stats.activeLoans }}</span>
                    <span class="text-sm font-bold text-slate-400">Documents</span>
                </div>
                
                <div class="mt-8 pt-6 border-t border-slate-50 flex items-center justify-between">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Ongoing Lifecycle</p>
                    <ArrowUpRight class="size-4 text-slate-300 group-hover:text-primary transition-colors" />
                </div>
            </div>
        </div>

        <!-- Overdue Loans Card -->
        <div class="group relative overflow-hidden rounded-[32px] bg-white border border-slate-200 p-8 shadow-sm transition-all hover:shadow-2xl hover:shadow-red-500/5 hover:-translate-y-1">
            <div class="absolute -right-10 -top-10 size-40 rounded-full bg-red-50 blur-3xl group-hover:bg-red-100 transition-colors" />
            
            <div class="flex items-center justify-between mb-6 relative z-10">
                <div class="size-14 rounded-2xl bg-red-50 flex items-center justify-center text-red-500 group-hover:scale-110 transition-transform duration-500">
                    <CalendarClock class="size-7" />
                </div>
                <div v-if="stats.overdueLoans > 0" class="flex items-center gap-1.5 px-3 py-1 rounded-full bg-red-50 border border-red-100 animate-pulse">
                    <span class="text-[10px] font-black text-red-700 uppercase tracking-widest">Urgent</span>
                </div>
            </div>

            <div class="relative z-10">
                <h3 class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400 mb-2">Overdue Clearance</h3>
                <div class="flex items-baseline gap-2">
                    <span class="text-4xl font-black text-red-600 tracking-tight italic">{{ stats.overdueLoans }}</span>
                    <span class="text-sm font-bold text-slate-400">Exceeded</span>
                </div>
                
                <div class="mt-8 pt-6 border-t border-slate-50 flex items-center justify-between">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Returns Required</p>
                    <ArrowUpRight class="size-4 text-slate-300 group-hover:text-red-500 transition-colors" />
                </div>
            </div>
        </div>

        <!-- Total Assets Card -->
        <div class="group relative overflow-hidden rounded-[32px] bg-slate-900 p-8 shadow-sm transition-all hover:shadow-2xl hover:shadow-slate-900/20 hover:-translate-y-1">
            <div class="absolute -right-10 -top-10 size-64 rounded-full bg-primary/20 blur-3xl opacity-50" />
            
            <div class="flex items-center justify-between mb-6 relative z-10">
                <div class="size-14 rounded-2xl bg-white/5 flex items-center justify-center text-primary group-hover:scale-110 transition-transform duration-500">
                    <Users class="size-7" />
                </div>
                <div class="px-3 py-1 rounded-full bg-white/5 border border-white/10 text-[10px] font-black text-white/40 uppercase tracking-widest">
                    Global
                </div>
            </div>

            <div class="relative z-10">
                <h3 class="text-[11px] font-black uppercase tracking-[0.2em] text-white/40 mb-2">Inventory Circulation</h3>
                <div class="flex items-baseline gap-2">
                    <span class="text-4xl font-black text-white tracking-tight italic">{{ stats.totalAssetsBorrowed }}</span>
                    <span class="text-sm font-bold text-white/40">Units</span>
                </div>
                
                <div class="mt-8 pt-6 border-t border-white/5 flex items-center justify-between">
                    <p class="text-[10px] font-bold text-white/40 uppercase tracking-widest">Volume Handled</p>
                    <TrendingUp class="size-4 text-primary" />
                </div>
            </div>
        </div>
    </div>
</template>
