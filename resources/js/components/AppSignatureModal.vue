<script setup lang="ts">
import type { ComponentPublicInstance } from 'vue';

interface ApprovalCardItem {
    title: string;
    name: string;
    badge: string;
}

defineProps<{
    open: boolean;
    activeApprovalCard: ApprovalCardItem | null;
    signatureError: string;
    signatureProcessing: boolean;
    setCanvasRef: (element: Element | ComponentPublicInstance | null) => void;
    startDrawing: (event: MouseEvent | TouchEvent) => void;
    drawSignature: (event: MouseEvent | TouchEvent) => void;
    stopDrawing: () => void;
    clearSignature: () => void;
    closeSignatureModal: () => void;
    submitSignature: () => void;
    modalKicker?: string;
    helperCopy?: string;
    clearLabel?: string;
    cancelLabel?: string;
    submitLabel?: string;
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
            class="signature-modal-backdrop"
            @click.self="closeSignatureModal"
        >
            <Transition
                enter-active-class="transition duration-300 ease-out"
                enter-from-class="opacity-0 translate-y-8 scale-95"
                enter-to-class="opacity-100 translate-y-0 scale-100"
                leave-active-class="transition duration-200 ease-in"
                leave-from-class="opacity-100 translate-y-0 scale-100"
                leave-to-class="opacity-0 translate-y-8 scale-95"
            >
                <div class="signature-modal">
                    <div class="signature-modal-shell">
                        <div
                            class="flex items-start justify-between gap-4 border-b border-slate-100 pb-6"
                        >
                            <div class="space-y-1">
                                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-[#003628]">
                                    {{ modalKicker || 'Autentikasi Identitas' }}
                                </p>
                                <h3 class="text-xl font-black text-slate-900 italic tracking-tight">
                                    {{ activeApprovalCard?.title || 'Tanda Tangan Manual' }}
                                </h3>
                                <p class="text-sm font-bold text-slate-500">
                                    {{ activeApprovalCard?.name || '-' }}
                                </p>
                            </div>
                            <button
                                type="button"
                                class="h-10 px-4 rounded-xl border border-slate-200 bg-white text-xs font-bold text-slate-600 hover:text-slate-900 transition-all active:scale-95"
                                @click="closeSignatureModal"
                            >
                                {{ cancelLabel || 'Batal' }}
                            </button>
                        </div>

                        <div class="signature-note-box mt-6">
                            {{
                                helperCopy ||
                                'Berikan tanda tangan yang jelas pada area yang ditentukan. Gunakan satu tarikan garis untuk hasil optimal pada laporan PDF.'
                            }}
                        </div>

                        <div class="signature-pad-shell mt-6">
                            <canvas
                                :ref="setCanvasRef"
                                class="signature-canvas shadow-sm"
                                @mousedown="startDrawing"
                                @mousemove="drawSignature"
                                @mouseup="stopDrawing"
                                @mouseleave="stopDrawing"
                                @touchstart="startDrawing"
                                @touchmove="drawSignature"
                                @touchend="stopDrawing"
                            ></canvas>
                        </div>

                        <div
                            v-if="signatureError"
                            class="mt-4 rounded-xl bg-red-50 border border-red-100 p-4 text-xs font-bold text-red-600"
                        >
                            {{ signatureError }}
                        </div>

                        <div class="mt-8 flex items-center justify-between gap-3">
                            <button
                                type="button"
                                class="h-12 px-6 rounded-2xl border border-slate-200 bg-white text-xs font-black uppercase tracking-widest text-slate-400 hover:text-slate-900 transition-all active:scale-95 disabled:opacity-50"
                                :disabled="signatureProcessing"
                                @click="clearSignature"
                            >
                                {{ clearLabel || 'Hapus Pad' }}
                            </button>
                            <button
                                type="button"
                                class="h-12 px-8 rounded-2xl bg-[#003628] text-white text-xs font-black uppercase tracking-widest shadow-xl shadow-emerald-900/20 hover:brightness-105 transition-all active:scale-95 disabled:opacity-50"
                                :disabled="signatureProcessing"
                                @click="submitSignature"
                            >
                                {{
                                    signatureProcessing
                                        ? 'Mengirim...'
                                        : submitLabel || 'Simpan Tanda Tangan'
                                }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </div>
    </Transition>
</template>

<style scoped>
.signature-modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 60;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(15, 23, 42, 0.4);
    padding: 16px;
    backdrop-filter: blur(8px);
}

.signature-modal {
    width: min(100%, 720px);
}

.signature-modal-shell {
    border: 1px solid #e2e8f0;
    border-radius: 32px;
    background: #ffffff;
    padding: 32px;
    box-shadow: 0 40px 80px -20px rgba(15, 23, 42, 0.15);
}

.signature-note-box {
    border: 1px solid #f1f5f9;
    border-radius: 20px;
    background: #f8fafc;
    padding: 16px 20px;
    font-size: 13px;
    font-weight: 500;
    line-height: 1.6;
    color: #64748b;
    font-style: italic;
}

.signature-canvas {
    display: block;
    width: 100%;
    height: 240px;
    border-radius: 16px;
    background: #ffffff;
    touch-action: none;
}

.signature-pad-shell {
    border: 1px solid #e2e8f0;
    border-radius: 24px;
    background: #f8fafc;
    padding: 16px;
}

@media (max-width: 640px) {
    .signature-modal-shell {
        padding: 24px;
        border-radius: 24px;
    }

    .signature-canvas {
        height: 190px;
    }
}
</style>
