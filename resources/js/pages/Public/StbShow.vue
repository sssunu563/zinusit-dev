<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { FileText, User, MapPin, Hash, Package, Calendar, CheckCircle2, Clock, AlertTriangle } from 'lucide-vue-next';
import SignatureRenderer from '@/components/SignatureRenderer.vue';

interface StbItem {
    nama: string;
    kategori: string;
    type: string;
    jumlah: number;
    condition: string | null;
    serial_no: string | null;
}

interface Stb {
    id: number;
    document_type: string;
    movement_type: string;
    user_name: string;
    location_name: string;
    deliver_date: string | null;
    is_completed: boolean;
    cancelled_at: string | null;
    remark: string | null;
    items: StbItem[];
    it_drafter_signature_path: string | null;
    it_checker_signature_path: string | null;
    it_approved_signature_path: string | null;
    requester_received_signature_path: string | null;
}

const props = defineProps<{
    stb: Stb;
}>();

const formatDate = (date?: string | null) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric'
    });
};
</script>

<template>
    <div class="min-h-screen bg-[#F8FAFC] flex flex-col font-sans pb-10">
        <Head :title="`STB: ${stb.user_name}`" />

        <!-- Header -->
        <header class="bg-white border-b border-slate-200 sticky top-0 z-50">
            <div class="max-w-xl mx-auto px-6 h-20 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <img src="/form-logo.png" alt="ZinusIT" class="h-8 w-auto" />
                    <div class="h-5 w-[1px] bg-slate-200" />
                    <span class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Public Document</span>
                </div>
                
                <div v-if="stb.cancelled_at" class="px-3 py-1 rounded-full bg-red-50 border border-red-100">
                    <span class="text-[9px] font-black uppercase tracking-widest text-red-600">Cancelled</span>
                </div>
                <div v-else-if="stb.is_completed" class="px-3 py-1 rounded-full bg-emerald-50 border border-emerald-100">
                    <span class="text-[9px] font-black uppercase tracking-widest text-emerald-600">Completed</span>
                </div>
                <div v-else class="px-3 py-1 rounded-full bg-amber-50 border border-amber-100">
                    <span class="text-[9px] font-black uppercase tracking-widest text-amber-600">In Progress</span>
                </div>
            </div>
        </header>

        <main class="flex-1 max-w-xl mx-auto w-full px-6 py-10 space-y-8">
            <!-- Doc Header -->
            <div class="bg-white rounded-[40px] border border-slate-200 p-8 shadow-sm relative overflow-hidden">
                <div class="absolute -right-12 -top-12 size-48 rounded-full bg-primary/5 blur-3xl" />
                
                <div class="relative space-y-6">
                    <div class="flex items-center gap-4">
                        <div class="size-14 rounded-3xl bg-primary flex items-center justify-center text-white">
                            <FileText class="size-7" />
                        </div>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">Document Type</p>
                            <h1 class="text-xl font-black text-slate-900 uppercase tracking-tight">STB {{ stb.document_type }}</h1>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-6 pt-6 border-t border-slate-100">
                        <div>
                            <p class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-400 mb-2">Recipient</p>
                            <div class="flex items-center gap-2">
                                <User class="size-4 text-primary" />
                                <span class="text-[13px] font-bold text-slate-700 uppercase">{{ stb.user_name }}</span>
                            </div>
                        </div>
                        <div>
                            <p class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-400 mb-2">Location</p>
                            <div class="flex items-center gap-2">
                                <MapPin class="size-4 text-primary" />
                                <span class="text-[13px] font-bold text-slate-700 uppercase">{{ stb.location_name }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Items List -->
            <div class="space-y-4">
                <div class="flex items-center justify-between px-1">
                    <div class="flex items-center gap-3">
                        <div class="h-4 w-1 rounded-full bg-primary" />
                        <h3 class="text-[10px] font-black uppercase tracking-[0.2em] text-primary">Item Details</h3>
                    </div>
                    <span class="text-[10px] font-bold text-slate-400">{{ stb.items.length }} Items</span>
                </div>

                <div class="space-y-3">
                    <div v-for="item in stb.items" :key="item.serial_no || item.nama" 
                        class="p-5 rounded-[32px] bg-white border border-slate-200 flex flex-col gap-4 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <div class="size-10 rounded-2xl bg-slate-50 flex items-center justify-center">
                                    <Package class="size-5 text-slate-400" />
                                </div>
                                <div>
                                    <p class="text-[13px] font-black text-slate-800 uppercase leading-none mb-1">{{ item.nama }}</p>
                                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">{{ item.type }}</p>
                                </div>
                            </div>
                            <div class="px-3 py-1 rounded-xl bg-slate-50 border border-slate-100">
                                <span class="text-[11px] font-black text-slate-600">x{{ item.jumlah }}</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 flex-wrap pt-3 border-t border-slate-50">
                            <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-slate-50 text-[10px] font-bold text-slate-500 uppercase tracking-wider">
                                <Hash class="size-3 text-slate-300" />
                                SN: {{ item.serial_no || '-' }}
                            </div>
                            <div v-if="item.condition" 
                                :class="[
                                    'flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[10px] font-black uppercase tracking-wider',
                                    item.condition.toLowerCase() === 'good' ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-600'
                                ]"
                            >
                                <CheckCircle2 v-if="item.condition.toLowerCase() === 'good'" class="size-3" />
                                <AlertTriangle v-else class="size-3" />
                                Kondisi: {{ item.condition }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Signature Status (Visual indicator only for public) -->
            <div class="p-8 rounded-[40px] bg-slate-900 text-white relative overflow-hidden shadow-2xl shadow-slate-900/20">
                <div class="absolute -right-8 -bottom-8 size-48 rounded-full bg-white/5 blur-3xl" />
                
                <h4 class="text-[10px] font-black uppercase tracking-[0.2em] text-white/40 mb-6">Signature Log</h4>
                
                <div class="space-y-4">
                    <div class="flex items-center gap-4">
                        <div :class="['size-2 rounded-full', stb.it_drafter_signature_path ? 'bg-emerald-400 shadow-[0_0_8px_rgba(52,211,153,0.5)]' : 'bg-slate-700']" />
                        <span class="text-[11px] font-bold uppercase tracking-widest" :class="stb.it_drafter_signature_path ? 'text-white' : 'text-slate-500'">IT Drafter</span>
                    </div>
                    <div class="flex items-center gap-4">
                        <div :class="['size-2 rounded-full', stb.it_checker_signature_path ? 'bg-emerald-400 shadow-[0_0_8px_rgba(52,211,153,0.5)]' : 'bg-slate-700']" />
                        <span class="text-[11px] font-bold uppercase tracking-widest" :class="stb.it_checker_signature_path ? 'text-white' : 'text-slate-500'">IT Checker</span>
                    </div>
                    <div class="flex items-center gap-4">
                        <div :class="['size-2 rounded-full', stb.it_approved_signature_path ? 'bg-emerald-400 shadow-[0_0_8px_rgba(52,211,153,0.5)]' : 'bg-slate-700']" />
                        <span class="text-[11px] font-bold uppercase tracking-widest" :class="stb.it_approved_signature_path ? 'text-white' : 'text-slate-500'">IT Approved</span>
                    </div>
                    <div class="flex items-center gap-4">
                        <div :class="['size-2 rounded-full', stb.requester_received_signature_path ? 'bg-emerald-400 shadow-[0_0_8px_rgba(52,211,153,0.5)]' : 'bg-slate-700']" />
                        <span class="text-[11px] font-bold uppercase tracking-widest" :class="stb.requester_received_signature_path ? 'text-white' : 'text-slate-500'">Recipient</span>
                    </div>
                </div>
            </div>

            <div class="text-center pt-10">
                <p class="text-[10px] font-black uppercase tracking-[0.3em] text-slate-300">© {{ new Date().getFullYear() }} Zinus IT Asset Management</p>
            </div>
        </main>
    </div>
</template>

<style scoped>
.font-sans {
    font-family: 'Outfit', sans-serif;
}
</style>
