<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { 
    LucideClipboardCheck as ClipboardCheck, 
    LucidePlus as Plus, 
    LucideArrowRight as ArrowRight,
    LucideLayoutGrid as LayoutGrid,
    LucideFileSearch as FileSearch
} from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Inspection',
        href: '/inspection-menu',
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Inspection Menu" />

        <div class="px-4 py-8 max-w-[1200px] mx-auto space-y-8">
            <!-- Header Section (Simplified) -->
            <header class="flex flex-col md:flex-row md:items-start justify-between gap-6">
                <div class="space-y-1">
                    <h1 class="text-3xl font-black tracking-tight text-slate-900 lg:text-4xl">
                        Infrastructure <span class="text-primary italic">Audit</span>
                    </h1>
                    <div class="flex items-center gap-2 text-[10px] font-black tracking-widest text-slate-400 uppercase">
                        <LayoutGrid class="size-3 text-primary" />
                        Technical Audit Modules
                    </div>
                </div>

                <div class="flex items-center gap-2">
                     <div class="h-12 px-5 rounded-2xl bg-white border border-slate-100 flex items-center justify-center shadow-sm">
                        <ClipboardCheck class="size-5 text-primary" />
                    </div>
                </div>
            </header>

            <!-- Module Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- IT Device Report Card -->
                <div class="group relative overflow-hidden rounded-[32px] border border-slate-100 bg-white p-8 transition-all hover:border-primary/20 hover:shadow-2xl hover:shadow-slate-200/50">
                    <div class="absolute inset-0 bg-gradient-to-br from-primary/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity" />
                    
                    <div class="relative z-10 space-y-6">
                        <div class="h-14 w-14 rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-center text-primary transition-transform group-hover:scale-110">
                            <FileSearch class="size-7" />
                        </div>
                        
                        <div class="space-y-2">
                            <div class="flex items-center gap-2">
                                <span class="h-1.5 w-1.5 rounded-full bg-primary" />
                                <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">Active Module</span>
                            </div>
                            <h3 class="text-2xl font-black text-slate-900 italic">Technical Report</h3>
                            <p class="text-sm text-slate-500 leading-relaxed">
                                Modul utama untuk laporan inspeksi perangkat IT, pengecekan rutin, dan dokumentasi masalah teknis di setiap site.
                            </p>
                        </div>

                        <div class="pt-4 flex flex-col sm:flex-row items-center gap-3">
                            <Link
                                href="/inspection"
                                class="w-full sm:w-auto h-11 px-6 rounded-xl bg-primary flex items-center justify-center gap-2 text-white text-sm font-bold hover:bg-primary/90 transition-all active:scale-95 shadow-lg shadow-primary/20"
                            >
                                View Table
                                <ArrowRight class="size-4" />
                            </Link>
                            <Link
                                href="/inspection/create"
                                class="w-full sm:w-auto h-11 px-6 rounded-xl border border-slate-200 bg-white flex items-center justify-center gap-2 text-slate-600 text-sm font-bold hover:bg-slate-50 transition-all active:scale-95 shadow-sm"
                            >
                                <Plus class="size-4" />
                                New Record
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Coming Soon Placeholder -->
                <div class="relative overflow-hidden rounded-[32px] border-2 border-dashed border-slate-100 bg-white/50 p-8 flex flex-col items-center justify-center text-center space-y-6 grayscale opacity-60">
                    <div class="h-14 w-14 rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-300">
                        <Plus class="size-7" />
                    </div>
                    
                    <div class="space-y-2">
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 italic">Future Architecture</p>
                        <h3 class="text-2xl font-black text-slate-300">Roadmap</h3>
                        <p class="text-sm text-slate-400 leading-relaxed max-w-xs">
                            Modul audit tambahan untuk spesifik site atau integrasi perangkat lain sedang dalam tahap pengembangan.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
