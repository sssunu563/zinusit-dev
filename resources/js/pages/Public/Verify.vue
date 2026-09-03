<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { ShieldCheck, Lock, ArrowRight, Loader2 } from 'lucide-vue-next';
import { ref } from 'vue';
import { verify } from '@/routes/public';

// Ensure axios sends cookies with requests for session-based auth
axios.defaults.withCredentials = true;

const props = defineProps<{
    id: number;
    type: string;
}>();

const pin = ref('');
const error = ref('');
const loading = ref(false);

const handleVerify = async () => {
    if (pin.value.length < 4) {
        error.value = 'Masukkan minimal 4 digit.';
        return;
    }

    loading.value = true;
    error.value = '';

    try {
        await axios.post(verify.url(props.id), { pin: pin.value });
        // Refresh page to trigger handleStbPublic logic in controller
        window.location.reload();
    } catch (e: any) {
        error.value = e.response?.data?.message || 'Terjadi kesalahan saat verifikasi.';
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <div class="min-h-screen bg-[#F8FAFC] flex flex-col items-center justify-center p-6 font-sans">
        <Head title="Verifikasi Dokumen" />

        <div class="max-w-md w-full space-y-8">
            <div class="text-center space-y-6">
                <div class="inline-flex items-center justify-center size-20 rounded-[32px] bg-primary/10 border border-primary/20 shadow-sm">
                    <ShieldCheck class="size-10 text-primary" />
                </div>
                
                <div>
                    <h1 class="text-2xl font-black text-slate-900 uppercase tracking-tight">Verifikasi Dokumen</h1>
                    <p class="mt-2 text-sm font-medium text-slate-500 leading-relaxed px-4">
                        Untuk alasan keamanan, silakan masukkan **4 digit terakhir** nomor telepon penerima yang terdaftar pada dokumen ini.
                    </p>
                </div>
            </div>

            <div class="bg-white rounded-[40px] border border-slate-200 p-8 shadow-sm space-y-6">
                <div class="space-y-2">
                    <label class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400 ml-1">Kode PIN (Phone Last 4)</label>
                    <div class="relative group">
                        <div class="absolute left-5 top-1/2 -translate-y-1/2">
                            <Lock class="size-5 text-slate-300 group-focus-within:text-primary transition-colors" />
                        </div>
                        <input 
                            v-model="pin"
                            type="password" 
                            inputmode="numeric"
                            placeholder="••••"
                            maxlength="4"
                            class="w-full bg-slate-50 border-slate-200 rounded-3xl py-4 pl-14 pr-6 text-xl font-black tracking-[0.5em] text-slate-800 placeholder:text-slate-200 focus:ring-4 focus:ring-primary/10 focus:border-primary transition-all text-center"
                            @keyup.enter="handleVerify"
                        />
                    </div>
                    <p v-if="error" class="text-xs font-bold text-red-500 mt-2 px-1">{{ error }}</p>
                </div>

                <button 
                    @click="handleVerify"
                    :disabled="loading || pin.length < 4"
                    class="w-full bg-primary text-white rounded-3xl py-4 px-6 flex items-center justify-center gap-3 text-[13px] font-black uppercase tracking-widest hover:scale-[1.02] active:scale-95 disabled:opacity-50 disabled:grayscale disabled:hover:scale-100 transition-all shadow-xl shadow-primary/20"
                >
                    <Loader2 v-if="loading" class="size-5 animate-spin" />
                    <template v-else>
                        <span>Verifikasi Sekarang</span>
                        <ArrowRight class="size-5" />
                    </template>
                </button>
            </div>

            <div class="text-center">
                <p class="text-[10px] font-bold text-slate-300 uppercase tracking-[0.2em]">© {{ new Date().getFullYear() }} Zinus IT Department</p>
            </div>
        </div>
    </div>
</template>

<style scoped>
.font-sans {
    font-family: 'Outfit', sans-serif;
}
</style>
