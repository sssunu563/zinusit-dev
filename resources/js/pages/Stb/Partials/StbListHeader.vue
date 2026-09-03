<script setup lang="ts">
import { LucideFileCheck as FileCheck, LucideFileSignature as FileSignature } from 'lucide-vue-next';
</script>

<template>
    <section class="mb-8 overflow-hidden rounded-[32px] border border-slate-100 bg-white p-8 shadow-xl shadow-slate-200/40 relative">
        <div class="absolute top-0 right-0 -mr-16 -mt-16 h-48 w-48 rounded-full bg-primary/5 blur-[80px] pointer-events-none" />
        
        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="space-y-3">
                <div class="inline-flex items-center gap-2 rounded-full border border-slate-100 bg-slate-50 px-3 py-1 text-[10px] font-black tracking-[0.15em] text-slate-400 uppercase">
                    <FileSignature class="size-3 text-primary" />
                    Document Control
                </div>
                <h1 class="text-3xl font-black tracking-tight text-slate-900 lg:text-4xl">
                    Serah Terima <span class="text-primary italic">Barang</span>
                </h1>
                <p class="max-w-xl text-sm leading-relaxed text-slate-500">
                    Hand Over, Loan, and Return management for IT infrastructure assets with digital validation.
                </p>
            </div>

            <div class="flex items-center gap-2">
                <div class="h-12 px-5 rounded-2xl bg-white border border-slate-100 flex items-center justify-center shadow-sm">
                    <FileCheck class="size-5 text-primary" />
                </div>
            </div>
        </div>
    </section>
</template>
