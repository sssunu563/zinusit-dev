<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Monitor, User, MapPin, Hash, Cpu, Key, Calendar } from 'lucide-vue-next';
import QrcodeVue from 'qrcode.vue';

interface Component {
    name: string;
    category: string | null;
    qty: number;
}

interface Asset {
    name: string;
    asset_tag: string;
    serial: string;
    model: string;
    image: string | null;
    status: string;
    assigned_to: string;
    location: string;
    components: Component[];
}

defineProps<{
    asset: Asset;
}>();

const formatDate = (date?: string) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric'
    });
};
</script>

<template>
    <div class="min-h-screen bg-[#F8FAFC] flex flex-col font-sans">
        <Head :title="`Asset Info: ${asset.name}`" />

        <!-- Top Header -->
        <header class="bg-white border-b border-slate-200 sticky top-0 z-50">
            <div class="max-w-xl mx-auto px-6 h-20 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <img src="/form-logo.png" alt="ZinusIT" class="h-8 w-auto" />
                    <div class="h-5 w-[1px] bg-slate-200" />
                    <span class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Asset Profile</span>
                </div>
                <div class="px-3 py-1 rounded-full bg-emerald-50 border border-emerald-100">
                    <span class="text-[9px] font-black uppercase tracking-widest text-emerald-600">{{ asset.status }}</span>
                </div>
            </div>
        </header>

        <main class="flex-1 max-w-xl mx-auto w-full px-6 py-10 space-y-8">
            <!-- Hero Section -->
            <div class="relative overflow-hidden rounded-[40px] bg-white border border-slate-200 p-8 shadow-sm">
                <div class="absolute -right-12 -top-12 size-48 rounded-full bg-primary/5 blur-3xl" />
                
                <div class="relative flex flex-col items-center text-center gap-6">
                    <div class="size-32 rounded-[32px] bg-slate-50 border border-slate-100 flex items-center justify-center overflow-hidden shadow-inner">
                        <img v-if="asset.image" :src="asset.image" class="w-full h-full object-cover" />
                        <Monitor v-else class="size-12 text-slate-300" />
                    </div>
                    
                    <div>
                        <h1 class="text-xl font-black text-slate-900 uppercase tracking-tight mb-1">{{ asset.name }}</h1>
                        <p class="text-sm font-bold text-slate-400">{{ asset.model }}</p>
                    </div>

                    <div class="flex items-center gap-2 px-6 py-2 rounded-2xl bg-slate-50 border border-slate-100 w-full">
                        <Hash class="size-4 text-slate-400" />
                        <span class="text-[11px] font-black text-slate-600 uppercase tracking-widest">{{ asset.asset_tag }}</span>
                        <div class="h-3 w-[1px] bg-slate-200 mx-2" />
                        <span class="text-[11px] font-medium text-slate-400 truncate">{{ asset.serial }}</span>
                    </div>
                </div>
            </div>

            <!-- Current Custodian Card -->
            <div class="group relative overflow-hidden rounded-[40px] bg-primary p-8 shadow-2xl shadow-primary/20 transition-transform hover:scale-[1.01]">
                <div class="absolute -right-8 -bottom-8 size-48 rounded-full bg-white/10 blur-2xl" />
                
                <div class="relative space-y-6">
                    <div class="flex items-center gap-3">
                        <div class="size-10 rounded-2xl bg-white/10 flex items-center justify-center backdrop-blur-sm border border-white/10">
                            <User class="size-5 text-white" />
                        </div>
                        <div>
                            <p class="text-[9px] font-black uppercase tracking-[0.2em] text-white/50">Current Custodian</p>
                            <p class="text-[13px] font-black text-white uppercase">{{ asset.assigned_to }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4">
                        <div class="flex items-center gap-3">
                            <MapPin class="size-4 text-white/40" />
                            <span class="text-[11px] font-bold text-white/80 uppercase tracking-widest">{{ asset.location }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Components & Specs -->
            <div v-if="asset.components && asset.components.length > 0" class="space-y-4">
                <div class="flex items-center gap-3 px-1">
                    <div class="h-4 w-1 rounded-full bg-primary" />
                    <h3 class="text-[10px] font-black uppercase tracking-[0.2em] text-primary">Embedded Components</h3>
                </div>

                <div class="grid grid-cols-1 gap-3">
                    <div v-for="comp in asset.components" :key="comp.name" class="p-4 rounded-3xl bg-white border border-slate-200 flex items-center justify-between group hover:border-primary/30 transition-all">
                        <div class="flex items-center gap-4">
                            <div class="size-10 rounded-2xl bg-slate-50 flex items-center justify-center group-hover:bg-primary/5 transition-colors">
                                <Cpu class="size-5 text-slate-400 group-hover:text-primary" />
                            </div>
                            <div>
                                <p class="text-[12px] font-black text-slate-800 uppercase leading-none mb-1">{{ comp.name }}</p>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">{{ comp.category }}</p>
                            </div>
                        </div>
                        <div class="px-3 py-1 rounded-xl bg-slate-50 border border-slate-100">
                            <span class="text-[11px] font-black text-slate-600">x{{ comp.qty }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Troubleshooting Section -->
            <div class="pt-6 border-t border-slate-200">
                <div class="p-6 rounded-[32px] bg-slate-900 text-white relative overflow-hidden">
                    <div class="absolute -right-4 -bottom-4 size-24 rounded-full bg-primary/20 blur-2xl" />
                    <p class="text-lg font-black italic mb-2">Need Support?</p>
                    <p class="text-xs text-white/60 mb-6 leading-relaxed">If this asset requires maintenance or you found it misplaced, please contact IT Helpdesk instantly.</p>
                    <a href="#" class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl bg-primary text-white text-[11px] font-black uppercase tracking-widest hover:scale-105 active:scale-95 transition-all">
                        Open Support Ticket
                    </a>
                </div>
            </div>
        </main>

        <footer class="py-10 text-center">
            <p class="text-[10px] font-black uppercase tracking-[0.3em] text-slate-300">© {{ new Date().getFullYear() }} Zinus IT Asset Management</p>
        </footer>
    </div>
</template>

<style scoped>
.font-sans {
    font-family: 'Outfit', sans-serif;
}
</style>
