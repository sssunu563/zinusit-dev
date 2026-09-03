<script setup lang="ts">
defineProps<{
    open: boolean;
    kicker: string;
    title: string;
    description: string;
    confirmLabel: string;
    confirmVariant?: 'primary' | 'warning' | 'danger';
    processing?: boolean;
    cancelLabel?: string;
    subject?: string | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'confirm'): void;
}>();
</script>

<template>
    <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div
            v-if="open"
            class="fixed inset-0 z-[70] flex items-center justify-center p-4 backdrop-blur-sm bg-slate-900/40"
            @click.self="emit('close')"
        >
            <Transition
                enter-active-class="transition duration-300 ease-out"
                enter-from-class="opacity-0 translate-y-8 scale-95"
                enter-to-class="opacity-100 translate-y-0 scale-100"
                leave-active-class="transition duration-200 ease-in"
                leave-from-class="opacity-100 translate-y-0 scale-100"
                leave-to-class="opacity-0 translate-y-8 scale-95"
            >
                <div
                    class="w-full max-w-[440px] rounded-[32px] border border-slate-200 bg-white p-8 shadow-2xl"
                >
                    <div class="space-y-1 mb-6">
                        <p 
                            class="text-[10px] font-black uppercase tracking-[0.2em] mb-2"
                            :class="[
                                confirmVariant === 'danger' ? 'text-red-500' : 
                                confirmVariant === 'warning' ? 'text-amber-500' : 'text-[#003628]'
                            ]"
                        >
                            {{ kicker }}
                        </p>
                        <h3 class="text-xl font-black text-slate-900 italic tracking-tight">
                            {{ title }}
                        </h3>
                        <p class="text-sm font-medium text-slate-500 leading-relaxed pt-2">
                            {{ description }}
                        </p>

                        <div
                            v-if="subject"
                            class="mt-4 p-3 rounded-xl bg-slate-50 border border-slate-100 text-[11px] font-bold text-slate-700 font-mono"
                        >
                            {{ subject }}
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 mt-8">
                        <button
                            type="button"
                            class="h-11 px-6 rounded-xl border border-slate-200 bg-white text-xs font-bold text-slate-600 hover:text-slate-900 hover:bg-slate-50 transition-all active:scale-95"
                            @click="emit('close')"
                        >
                            {{ cancelLabel || 'Batal' }}
                        </button>
                        <button
                            type="button"
                            :class="[
                                'h-11 px-6 rounded-xl text-xs font-bold transition-all active:scale-95 disabled:opacity-50 text-white shadow-lg',
                                confirmVariant === 'danger'
                                    ? 'bg-red-500 hover:bg-red-600 shadow-red-500/20'
                                    : confirmVariant === 'warning'
                                      ? 'bg-[#d99528] hover:brightness-110 shadow-orange-500/20'
                                      : 'bg-[#003628] hover:brightness-110 shadow-emerald-900/20'
                            ]"
                            :disabled="processing"
                            @click="emit('confirm')"
                        >
                            <span v-if="processing" class="flex items-center gap-2">
                                <svg class="animate-spin h-3 w-3 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Memproses...
                            </span>
                            <span v-else>{{ confirmLabel }}</span>
                        </button>
                    </div>
                </div>
            </Transition>
        </div>
    </Transition>
</template>
