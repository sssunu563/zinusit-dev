<script setup lang="ts">
import { LucideShieldAlert as ShieldAlert } from 'lucide-vue-next';
</script>

<template>
    <section class="rounded-[24px] border border-primary/10 bg-primary/5 p-6 shadow-xl shadow-primary/5 overflow-hidden relative group">
        <!-- Decorative subtle element -->
        <div class="absolute -right-8 -top-8 h-24 w-24 rounded-full bg-primary/5 blur-3xl group-hover:bg-primary/10 transition-colors" />
        
        <div class="flex flex-col gap-6 md:flex-row md:items-center justify-between relative z-10">
            <div class="space-y-2">
                <div class="flex items-center gap-2 text-[10px] font-black tracking-widest text-primary uppercase">
                    <ShieldAlert class="size-3" />
                    Panduan Tanda Tangan
                </div>
                <div class="h-2"></div>
                <p class="text-xs font-medium text-slate-500 max-w-xl leading-relaxed">
                    Silakan tanda tangan pada area yang telah ditentukan di bawah. Klik tombol 
                    <strong class="text-primary uppercase tracking-tighter">Sign</strong> untuk membuka papan tanda tangan.
                </p>
            </div>

            <div class="flex flex-col gap-2 p-4 rounded-2xl bg-white/60 border border-white/80 shadow-sm backdrop-blur-sm min-w-[180px]">
                <div class="flex items-center gap-2 text-[9px] font-black text-slate-400 uppercase tracking-widest">
                    <div class="h-1 w-1 rounded-full bg-primary" /> 1. Cari Nama
                </div>
                <div class="flex items-center gap-2 text-[9px] font-black text-slate-400 uppercase tracking-widest">
                    <div class="h-1 w-1 rounded-full bg-primary" /> 2. Klik Sign
                </div>
                <div class="flex items-center gap-2 text-[9px] font-black text-slate-400 uppercase tracking-widest">
                    <div class="h-1 w-1 rounded-full bg-primary" /> 3. Tanda Tangan
                </div>
            </div>
        </div>
    </section>
</template>
