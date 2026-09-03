<script setup lang="ts">
/* eslint-disable vue/no-mutating-props */
import { 
    LucideCamera as Camera, 
    LucideFileText as FileText, 
    LucideLoader2 as Loader2,
    CheckCircle2 
} from 'lucide-vue-next';

interface StbFormData {
    photo: File | null;
    remark: string;
}

defineProps<{
    formData: StbFormData;
    photoPreview: string | null;
    photoSummary: {
        title: string;
        helper: string;
    } | null;
    isCompressingPhoto: boolean;
    handlePhotoChange: (event: Event) => void | Promise<void>;
    handlePhotoPreviewError: () => void;
    sectionKicker: string;
    sectionTitle: string;
    sectionCopy: string;
    photoLabel: string;
    remarkLabel: string;
    previewAlt: string;
    emptyPhotoLabel: string;
}>();
</script>

<template>
<section class="space-y-6 animate-in fade-in slide-in-from-bottom-2 duration-500">
    <div class="flex items-center gap-3 mb-2">
        <div class="h-px flex-1 bg-slate-100" />
        <span class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">Lampiran & Catatan</span>
        <div class="h-px flex-1 bg-slate-100" />
    </div>

    <div class="grid grid-cols-2 gap-8 items-start">
        <div class="space-y-3">
            <label class="text-[11px] font-black uppercase tracking-widest text-slate-400">Foto Aset</label>
            <label class="group relative block cursor-pointer transition-all active:scale-[0.99]">
                <div class="overflow-hidden rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50/50 hover:bg-white hover:border-[#003628]/30 transition-all shadow-sm">
                    <div class="relative min-h-[160px] flex items-center justify-center p-2">
                        <img
                            v-if="photoPreview"
                            :src="photoPreview"
                            class="rounded-xl max-h-[250px] w-full object-contain shadow-xl transition-transform duration-500 group-hover:scale-[1.02]"
                            :alt="previewAlt"
                            @error="handlePhotoPreviewError"
                        />
                        <div v-else class="flex flex-col items-center justify-center p-6 text-center space-y-3">
                            <div class="size-12 rounded-full bg-white flex items-center justify-center text-slate-300 shadow-sm border border-slate-100 group-hover:text-[#003628] group-hover:scale-110 transition-all">
                                <Camera class="size-6" />
                            </div>
                            <div class="space-y-0.5">
                                <span class="block text-[10px] font-black text-slate-500 uppercase tracking-widest">Ambil Foto</span>
                                <span class="block text-[9px] text-slate-400 font-bold uppercase">JPEG/PNG Maks 10MB</span>
                            </div>
                        </div>

                        <!-- Click Overlay -->
                        <div class="absolute inset-0 bg-[#003628]/60 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center backdrop-blur-sm">
                             <div class="bg-white px-4 py-2 rounded-full shadow-2xl scale-90 group-hover:scale-100 transition-transform flex items-center gap-2">
                                <Camera class="size-3 text-[#003628]" />
                                <span class="text-[9px] font-black text-[#003628] uppercase tracking-widest">
                                    {{ photoPreview ? 'Ubah' : 'Unggah' }}
                                </span>
                             </div>
                        </div>
                    </div>

                    <!-- Status Bar -->
                    <div v-if="photoSummary || isCompressingPhoto" class="bg-white/80 border-t border-slate-100 px-4 py-2 flex items-center justify-between">
                        <div v-if="photoSummary" class="flex items-center gap-1.5">
                            <CheckCircle2 class="size-3.5 text-emerald-500" />
                            <span class="text-[9px] font-black text-slate-600 uppercase tracking-widest truncate max-w-[120px]">{{ photoSummary.title }}</span>
                        </div>
                        <div v-if="isCompressingPhoto" class="flex items-center gap-2">
                            <Loader2 class="size-3 text-[#003628] animate-spin" />
                            <span class="text-[9px] font-black text-[#003628] uppercase tracking-widest">Mengoptimalkan...</span>
                        </div>
                    </div>
                </div>
                <input
                    type="file"
                    class="hidden"
                    accept="image/*"
                    @change="handlePhotoChange"
                />
            </label>
        </div>

        <div class="space-y-3">
            <label class="text-[11px] font-black uppercase tracking-widest text-slate-400">Catatan Tambahan</label>
            <div class="relative h-full min-h-[160px]">
                <textarea
                    v-model="formData.remark"
                    class="w-full h-full min-h-[160px] px-4 py-3 rounded-2xl border border-slate-200 bg-white text-[13px] font-medium text-slate-900 focus:border-[#003628] focus:ring-4 focus:ring-[#003628]/5 transition-all outline-none placeholder:text-slate-300 resize-none shadow-sm"
                    placeholder="Masukkan catatan khusus atau kondisi aset..."
                ></textarea>
                <div class="absolute bottom-4 right-4 opacity-10 pointer-events-none">
                    <FileText class="size-8 text-[#003628]" />
                </div>
            </div>
        </div>
    </div>
</section>
</template>
