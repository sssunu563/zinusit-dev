<script setup lang="ts">
/* eslint-disable vue/no-mutating-props */
import {
    LucideCamera as Camera,
    LucideFileText as FileText,
} from 'lucide-vue-next';

interface PeminjamanFormData {
    photo: File | null;
    remark: string;
}

const props = defineProps<{
    formData: PeminjamanFormData;
    photoPreview: string | null;
    handlePhotoChange: (event: Event) => void | Promise<void>;
    handlePhotoPreviewError?: () => void;
    sectionKicker: string;
    sectionTitle: string;
    sectionCopy: string;
    remarkLabel: string;
}>();

const normalizePhotoSource = (value: string | null | undefined): string => {
    if (!value) return '';
    const source = String(value).trim();
    if (
        source.startsWith('blob:') ||
        source.startsWith('data:') ||
        source.startsWith('http://') ||
        source.startsWith('https://')
    )
        return source;
    if (source.startsWith('/storage/')) return source;
    if (source.startsWith('storage/')) return `/${source}`;
    if (source.startsWith('/')) return `/storage${source}`;
    if (source.startsWith('public/'))
        return `/storage/${source.replace(/^public\//, '')}`;
    return `/storage/${source.replace(/^\/+/, '')}`;
};
</script>

<template>
    <section
        class="animate-in space-y-6 duration-500 fade-in slide-in-from-bottom-2"
    >
        <div class="mb-2 flex items-center gap-3">
            <div class="h-px flex-1 bg-slate-100" />
            <span
                class="text-[10px] font-black tracking-[0.2em] text-slate-400 uppercase"
                >{{ sectionKicker }} & {{ remarkLabel }}</span
            >
            <div class="h-px flex-1 bg-slate-100" />
        </div>

        <div class="grid grid-cols-2 items-start gap-8">
            <!-- 1. PHOTO ATTACHMENT -->
            <div class="space-y-3">
                <label
                    class="text-[11px] font-black tracking-widest text-slate-400 uppercase"
                    >Foto Aset</label
                >

                <label
                    class="group relative block cursor-pointer transition-all active:scale-[0.99]"
                >
                    <div
                        class="overflow-hidden rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50/50 shadow-sm transition-all hover:border-[#003628]/30 hover:bg-white"
                    >
                        <div
                            class="relative flex min-h-[160px] items-center justify-center p-2"
                        >
                            <img
                                v-if="photoPreview"
                                :src="normalizePhotoSource(photoPreview)"
                                class="max-h-[250px] w-full rounded-xl object-contain shadow-xl transition-transform duration-500 group-hover:scale-[1.02]"
                                alt="Foto aset"
                                @error="props.handlePhotoPreviewError"
                            />
                            <div
                                v-else
                                class="flex flex-col items-center justify-center space-y-3 p-6 text-center"
                            >
                                <div
                                    class="flex size-12 items-center justify-center rounded-full border border-slate-100 bg-white text-slate-300 shadow-sm transition-all group-hover:scale-110 group-hover:text-[#003628]"
                                >
                                    <Camera class="size-6" />
                                </div>
                                <div class="space-y-0.5">
                                    <span
                                        class="block text-[10px] font-black tracking-widest text-slate-500 uppercase"
                                        >Ambil Foto</span
                                    >
                                    <span
                                        class="block text-[9px] font-bold text-slate-400 uppercase"
                                        >JPEG/PNG Maks 10MB</span
                                    >
                                </div>
                            </div>

                            <!-- Click Overlay -->
                            <div
                                class="absolute inset-0 flex items-center justify-center bg-[#003628]/60 opacity-0 backdrop-blur-sm transition-opacity group-hover:opacity-100"
                            >
                                <div
                                    class="flex scale-90 items-center gap-2 rounded-full bg-white px-4 py-2 shadow-2xl transition-transform group-hover:scale-100"
                                >
                                    <Camera class="size-3 text-[#003628]" />
                                    <span
                                        class="text-[9px] font-black tracking-widest text-[#003628] uppercase"
                                    >
                                        {{ photoPreview ? 'Ubah' : 'Unggah' }}
                                    </span>
                                </div>
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

            <!-- 2. REMARKS -->
            <div class="space-y-3">
                <label
                    class="text-[11px] font-black tracking-widest text-slate-400 uppercase"
                    >{{ remarkLabel }}</label
                >
                <div class="relative min-h-[160px]">
                    <textarea
                        v-model="formData.remark"
                        class="h-[160px] w-full resize-none rounded-2xl border border-slate-200 bg-white px-4 py-3 text-[13px] font-medium text-slate-900 shadow-sm transition-all outline-none placeholder:text-slate-300 focus:border-[#003628] focus:ring-4 focus:ring-[#003628]/5"
                        placeholder="Masukkan catatan khusus, kondisi aset, atau spesifikasi tambahan..."
                    ></textarea>
                    <div
                        class="pointer-events-none absolute right-4 bottom-4 opacity-10"
                    >
                        <FileText class="size-8 text-[#003628]" />
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
