<script setup lang="ts">
import { Download, ExternalLink, X } from 'lucide-vue-next';

defineProps<{
    open: boolean;
    url: string | null;
    title?: string;
}>();

const emit = defineEmits<{
    (event: 'close'): void;
}>();
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open && url"
            class="fixed inset-0 z-[120] flex items-center justify-center bg-slate-950/70 p-3 backdrop-blur-sm sm:p-6"
            role="dialog"
            aria-modal="true"
            @click.self="emit('close')"
        >
            <section
                class="flex h-[94vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl"
            >
                <header
                    class="flex shrink-0 items-center justify-between gap-3 border-b border-slate-200 px-4 py-3"
                >
                    <h2 class="truncate text-sm font-bold text-slate-800">
                        {{ title || 'PDF Viewer' }}
                    </h2>
                    <div class="flex items-center gap-2">
                        <a
                            :href="url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="flex h-9 items-center gap-1.5 rounded-lg border border-slate-200 px-3 text-xs font-semibold text-slate-600 hover:bg-slate-50"
                        >
                            <ExternalLink class="size-3.5" /> Buka tab
                        </a>
                        <a
                            :href="url"
                            download
                            class="flex h-9 items-center gap-1.5 rounded-lg bg-[#003628] px-3 text-xs font-semibold text-white hover:brightness-110"
                        >
                            <Download class="size-3.5" /> Download
                        </a>
                        <button
                            type="button"
                            class="flex size-9 items-center justify-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50"
                            title="Tutup"
                            @click="emit('close')"
                        >
                            <X class="size-4" />
                        </button>
                    </div>
                </header>
                <iframe
                    :src="url"
                    :title="title || 'PDF Viewer'"
                    class="min-h-0 w-full flex-1 bg-slate-100"
                />
            </section>
        </div>
    </Teleport>
</template>
