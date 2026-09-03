<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { 
    Clock, 
    ArrowRight, 
    ChevronRight, 
    History, 
    FileText, 
    ShieldCheck, 
    User, 
    MapPin,
    AlertCircle,
    CheckCircle2
} from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';

interface TimelineEvent {
    id: string;
    date: string;
    type: string;
    title: string;
    description: string;
    user: string;
    location: string;
    condition: string;
    link: string;
    tone: 'primary' | 'success' | 'warning' | 'danger' | 'info';
}

const props = defineProps<{
    serial: string;
    timeline: TimelineEvent[];
}>();

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric'
    });
};

const formatTime = (date: string) => {
    return new Date(date).toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit'
    });
};

const getToneClasses = (tone: string) => {
    switch(tone) {
        case 'success': return 'bg-emerald-50 text-emerald-600 border-emerald-100';
        case 'warning': return 'bg-amber-50 text-amber-600 border-amber-100';
        case 'danger': return 'bg-red-50 text-red-600 border-red-100';
        case 'info': return 'bg-blue-50 text-blue-600 border-blue-100';
        default: return 'bg-slate-50 text-slate-600 border-slate-100';
    }
};

const getIconClasses = (tone: string) => {
    switch(tone) {
        case 'success': return 'bg-emerald-500 text-white';
        case 'warning': return 'bg-amber-500 text-white';
        case 'danger': return 'bg-red-500 text-white';
        case 'info': return 'bg-blue-500 text-white';
        default: return 'bg-primary text-white';
    }
};
</script>

<template>
    <AppLayout>
        <Head :title="`Asset Timeline: ${serial}`" />

        <div class="py-12 px-6 max-w-4xl mx-auto">
            <!-- Header -->
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-12">
                <div class="space-y-4">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-primary/10 text-primary text-[10px] font-black uppercase tracking-[0.2em]">
                        <History class="size-3" />
                        Asset Lifecycle
                    </div>
                    <h1 class="text-4xl font-black text-slate-900 tracking-tight uppercase">
                        Timeline Aset
                    </h1>
                    <p class="text-slate-400 font-bold flex items-center gap-2">
                        <span class="text-slate-900">{{ serial }}</span>
                        <span class="size-1 rounded-full bg-slate-200" />
                        History of handovers, loans, and inspections
                    </p>
                </div>
            </div>

            <!-- Timeline Content -->
            <div v-if="timeline.length > 0" class="relative">
                <!-- Vertical Line -->
                <div class="absolute left-[21px] top-4 bottom-4 w-0.5 bg-slate-100" />

                <div class="space-y-12">
                    <div v-for="(event, index) in timeline" :key="event.id" class="relative pl-16 group">
                        <!-- Timeline Point -->
                        <div 
                            :class="[
                                'absolute left-0 top-1 size-11 rounded-2xl flex items-center justify-center border-4 border-[#F8FAFC] z-10 transition-transform group-hover:scale-110',
                                getIconClasses(event.tone)
                            ]"
                        >
                            <FileText v-if="event.type === 'Handover'" class="size-5" />
                            <ShieldCheck v-else class="size-5" />
                        </div>

                        <!-- Content Card -->
                        <div class="bg-white rounded-[32px] border border-slate-200 p-6 shadow-sm hover:shadow-xl hover:border-primary/20 transition-all duration-300">
                            <div class="flex flex-col md:flex-row md:items-start justify-between gap-4">
                                <div class="space-y-4 flex-1">
                                    <div class="flex flex-wrap items-center gap-3">
                                        <span class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">
                                            {{ formatDate(event.date) }} • {{ formatTime(event.date) }}
                                        </span>
                                        <div :class="['px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest border', getToneClasses(event.tone)]">
                                            {{ event.title }}
                                        </div>
                                    </div>

                                    <h2 class="text-lg font-black text-slate-900 uppercase leading-none">
                                        {{ event.description }}
                                    </h2>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-4 border-t border-slate-50">
                                        <div class="flex items-center gap-3">
                                            <div class="size-8 rounded-xl bg-slate-50 flex items-center justify-center text-slate-400">
                                                <User class="size-4" />
                                            </div>
                                            <div>
                                                <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Person in Charge</p>
                                                <p class="text-[11px] font-bold text-slate-700 uppercase">{{ event.user || '-' }}</p>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <div class="size-8 rounded-xl bg-slate-50 flex items-center justify-center text-slate-400">
                                                <MapPin class="size-4" />
                                            </div>
                                            <div>
                                                <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Location</p>
                                                <p class="text-[11px] font-bold text-slate-700 uppercase">{{ event.location || '-' }}</p>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="event.condition" class="flex items-center gap-2 text-xs font-bold" :class="event.condition.toLowerCase().includes('broken') ? 'text-red-500' : 'text-slate-500'">
                                        <AlertCircle v-if="event.condition.toLowerCase().includes('broken')" class="size-4" />
                                        <CheckCircle2 v-else class="size-4" />
                                        Note: {{ event.condition }}
                                    </div>
                                </div>

                                <Link 
                                    :href="event.link"
                                    class="size-12 rounded-2xl bg-slate-50 flex items-center justify-center text-slate-400 hover:bg-primary hover:text-white transition-all shadow-sm"
                                >
                                    <ChevronRight class="size-6" />
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="text-center py-20 bg-white rounded-[40px] border-2 border-dashed border-slate-200">
                <div class="inline-flex size-16 rounded-3xl bg-slate-50 items-center justify-center mb-6">
                    <History class="size-8 text-slate-200" />
                </div>
                <h3 class="text-lg font-black text-slate-900 uppercase tracking-tight">No History Found</h3>
                <p class="text-slate-400 font-bold max-w-xs mx-auto mt-2">
                    Aset ini belum memiliki catatan riwayat STB maupun Inspeksi di sistem.
                </p>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.font-sans {
    font-family: 'Outfit', sans-serif;
}
</style>
