<script setup lang="ts">
import { LucideShieldAlert as ShieldAlert, LucideBox as Box } from 'lucide-vue-next';

const props = defineProps<{
    pageTitle: string;
    integrationDisabled?: boolean;
}>();
</script>

<template>
    <section class="mb-8 overflow-hidden rounded-[32px] border border-slate-100 bg-white p-8 shadow-xl shadow-slate-200/40 relative">
        <div class="absolute top-0 right-0 -mr-16 -mt-16 h-48 w-48 rounded-full bg-primary/5 blur-[80px] pointer-events-none" />
        
        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="space-y-3">
                <div class="inline-flex items-center gap-2 rounded-full border border-slate-100 bg-slate-50 px-3 py-1 text-[10px] font-black tracking-[0.15em] text-slate-400 uppercase">
                    <Box class="size-3 text-primary" />
                    Infrastructure Directory
                </div>
                <h1 class="text-3xl font-black tracking-tight text-slate-900 lg:text-4xl">
                    {{ props.pageTitle }} <span class="text-primary italic">Stock</span>
                </h1>
                <p class="max-w-xl text-sm leading-relaxed text-slate-500">
                    Manajemen inventaris, pelacakan unit, dan sinkronisasi data infrastruktur IT secara real-time.
                </p>
            </div>

            <div class="flex items-center gap-3">
                <div
                    v-if="props.integrationDisabled"
                    class="h-10 px-4 rounded-xl border border-amber-100 bg-amber-50 flex items-center gap-2 text-amber-500 text-[10px] font-black uppercase tracking-widest shadow-sm"
                >
                    <ShieldAlert class="size-3" />
                    Snipe-IT Offline
                </div>
            </div>
        </div>
    </section>
</template>
