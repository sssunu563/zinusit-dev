<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { Calendar, AlertCircle } from 'lucide-vue-next';

const props = defineProps<{
    month: number | null;
    year: number | null;
    applyTrigger: number;
}>();

const loading = ref(false);

async function loadData() {
    loading.value = true;
    // Simulation
    await new Promise(r => setTimeout(r, 800));
    loading.value = false;
}

onMounted(() => loadData());
watch(() => props.applyTrigger, () => loadData());
</script>

<template>
    <div class="space-y-6">
        <div class="bg-emerald-50 border border-emerald-100 rounded-2xl p-6 flex items-start gap-4">
            <div class="h-10 w-10 rounded-xl bg-emerald-100 flex items-center justify-center shrink-0">
                <Calendar class="size-5 text-emerald-600"/>
            </div>
            <div>
                <h3 class="text-sm font-black text-emerald-900 uppercase tracking-tight">Monthly Infra Report Placeholder</h3>
                <p class="text-[11px] text-emerald-700/70 mt-1 leading-relaxed">
                    Halaman ini akan menampilkan rekap bulanan untuk <strong>Bulan {{ month }} Tahun {{ year }}</strong>. 
                    <br/>Seluruh metrik performa infrastruktur akan diagregasikan dalam satu bulan penuh.
                </p>
            </div>
        </div>

        <!-- Placeholder for sections -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 opacity-40 grayscale pointer-events-none">
            <div v-for="n in 4" :key="n" class="h-48 rounded-[24px] border border-slate-200 border-dashed flex flex-col items-center justify-center gap-3">
                <AlertCircle class="size-6 text-slate-300"/>
                <p class="text-[9px] font-black uppercase tracking-widest text-slate-300">Section Content Waiting for Format</p>
            </div>
        </div>
    </div>
</template>
