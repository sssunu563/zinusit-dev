<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { X, Upload, CheckCircle2, Loader2, Camera } from 'lucide-vue-next';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

const props = defineProps({
    show: Boolean,
    peminjamanId: [Number, String],
});

const emit = defineEmits(['close', 'success']);

const processing = ref(false);
const error = ref(null);
const photoFile = ref(null);
const photoPreview = ref(null);
const fileInput = ref(null);

const handleFileSelect = (e) => {
    const file = e.target.files[0];
    if (file) {
        photoFile.value = file;
        const reader = new FileReader();
        reader.onload = (e) => {
            photoPreview.value = e.target.result;
        };
        reader.readAsDataURL(file);
    }
};

const triggerFileInput = () => {
    fileInput.value.click();
};

const submit = async () => {
    if (!photoFile.value) {
        error.value = 'Foto pengembalian wajib diunggah.';
        return;
    }

    processing.value = true;
    error.value = null;

    const formData = new FormData();
    formData.append('photo', photoFile.value);
    formData.append('_method', 'POST');

    try {
        const res = await axios.post(`/peminjaman/${props.peminjamanId}/quick-return`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        emit('success');
        close();
        // Buka PDF di tab baru jika tersedia
        const pdfUrl = res.data?.completed_pdf_url;
        if (pdfUrl) {
            window.open(pdfUrl, '_blank');
        }
        // Redirect ke halaman detail peminjaman
        router.visit(`/peminjaman/${props.peminjamanId}`, { replace: true });
    } catch (err) {
        const msg = err?.response?.data?.message || 'Gagal memproses pengembalian.';
        error.value = msg;
    } finally {
        processing.value = false;
    }
};

const close = () => {
    if (processing.value) return;
    photoFile.value = null;
    photoPreview.value = null;
    error.value = null;
    emit('close');
};

const updateOpen = (val) => {
    if (!val) close();
};
</script>

<template>
    <Dialog :open="show" @update:open="updateOpen">
        <DialogContent class="sm:max-w-[480px] p-0 overflow-hidden rounded-[32px] border-none shadow-2xl">
            <div class="p-8">
                <!-- Header -->
                <div class="flex items-center justify-between mb-8">
                    <div class="space-y-1">
                        <DialogTitle class="text-2xl font-black text-slate-900 italic tracking-tight">
                            Quick <span class="text-primary not-italic">Return</span>
                        </DialogTitle>
                        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">Finalisasi Pengembalian Aset</p>
                    </div>
                    <button @click="close" class="h-10 w-10 rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-400 hover:text-slate-900 transition-colors">
                        <X class="size-5" />
                    </button>
                </div>

                <!-- Content -->
                <div class="space-y-6">
                    <!-- Photo Upload Area -->
                    <div 
                        @click="triggerFileInput"
                        class="relative aspect-video rounded-[24px] border-2 border-dashed transition-all cursor-pointer overflow-hidden flex flex-col items-center justify-center gap-3 group"
                        :class="photoPreview ? 'border-primary/50' : 'border-slate-200 hover:border-primary/30 bg-slate-50/50'"
                    >
                        <input 
                            type="file" 
                            ref="fileInput" 
                            class="hidden" 
                            accept="image/*" 
                            capture="environment"
                            @change="handleFileSelect"
                        >

                        <template v-if="photoPreview">
                            <img :src="photoPreview" class="absolute inset-0 w-full h-full object-cover" />
                            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                <div class="flex flex-col items-center gap-2 text-white">
                                    <Camera class="size-6" />
                                    <span class="text-[10px] font-black uppercase tracking-widest">Ganti Foto</span>
                                </div>
                            </div>
                        </template>

                        <template v-else>
                            <div class="h-12 w-12 rounded-2xl bg-white border border-slate-100 shadow-sm flex items-center justify-center group-hover:scale-110 transition-transform">
                                <Upload class="size-5 text-primary" />
                            </div>
                            <div class="text-center space-y-1">
                                <p class="text-xs font-bold text-slate-600">Ambil/Unggah Foto Barang</p>
                                <p class="text-[9px] font-medium text-slate-400 italic">Bukti kondisi barang saat kembali</p>
                            </div>
                        </template>
                    </div>

                    <!-- Error Message -->
                    <div v-if="error" class="p-4 rounded-2xl bg-red-50 border border-red-100 text-red-600 text-[11px] font-bold">
                        {{ error }}
                    </div>

                    <!-- Instructions -->
                    <div class="p-5 rounded-3xl bg-slate-50/50 border border-slate-100 space-y-3">
                        <div class="flex gap-3">
                            <CheckCircle2 class="size-4 text-emerald-500 shrink-0" />
                            <p class="text-[11px] text-slate-500 leading-relaxed font-medium">Aset akan otomatis berstatus <span class="font-black text-slate-700">Ready to Deploy</span> di Snipe-IT.</p>
                        </div>
                        <div class="flex gap-3">
                            <CheckCircle2 class="size-4 text-emerald-500 shrink-0" />
                            <p class="text-[11px] text-slate-500 leading-relaxed font-medium">Dokumen ini akan diperbarui dengan stempel <span class="font-black text-slate-700">Returned</span>.</p>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="mt-8 flex gap-3">
                    <button 
                        @click="close"
                        class="flex-1 h-12 rounded-2xl border border-slate-200 text-slate-500 text-xs font-black uppercase tracking-widest hover:bg-slate-50 transition-colors"
                    >
                        Batal
                    </button>
                    <button 
                        @click="submit"
                        :disabled="processing || !photoFile"
                        class="flex-[2] h-12 rounded-2xl bg-primary text-white text-xs font-black uppercase tracking-widest shadow-xl shadow-primary/20 hover:scale-[1.02] active:scale-95 transition-all disabled:opacity-50 disabled:scale-100 flex items-center justify-center gap-2"
                    >
                        <Loader2 v-if="processing" class="size-4 animate-spin" />
                        <template v-else>
                            Proses Pengembalian
                        </template>
                    </button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
