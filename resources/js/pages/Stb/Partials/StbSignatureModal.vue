<script setup lang="ts">
import type { ComponentPublicInstance } from 'vue';
import AppSignatureModal from '@/components/AppSignatureModal.vue';

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
}>();
</script>

<template>
    <AppSignatureModal
        v-bind="$props"
        modal-kicker="Tanda Tangan Manual"
        clear-label="Hapus"
        cancel-label="Batal"
        submit-label="Simpan Tanda Tangan"
    />
</template>
