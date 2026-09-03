<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { PrinterIcon } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AppSignatureLinkHelpPanel from '@/components/AppSignatureLinkHelpPanel.vue';
import InspectionDocument from '@/pages/Inspection/Partials/InspectionDocument.vue';
import StbSignatureModal from '@/pages/Stb/Partials/StbSignatureModal.vue';
import StbConfirmDialog from '@/pages/Stb/Partials/StbConfirmDialog.vue';
import { useSignaturePadWorkflow } from '@/composables/useSignaturePadWorkflow';
import {
    createApprovalBadge,
    createApprovalSignatureState,
    useDocumentApprovalCards,
} from '@/composables/useDocumentApprovalCards';

interface Props {
    inspection: any;
    sharedMode?: boolean;
    shareSignUrls?: Record<string, string>;
}

const props = withDefaults(defineProps<Props>(), {
    sharedMode: true,
    shareSignUrls: () => ({}),
});

const isCancelled = computed(() => false);
const isCompleted = computed(() => !!props.inspection.completed_at);
const docId = computed(() => props.inspection.report_id);

const {
    signatureModalOpen,
    signatureRole,
    clearConfirmRole,
    signatureProcessing,
    signatureError,
    setCanvasRef,
    startDrawing,
    drawSignature,
    stopDrawing,
    clearSignature,
    openSignatureModal,
    closeSignatureModal,
    openClearConfirm,
    closeClearConfirm,
    submitSignature,
    clearSignatureRole,
} = useSignaturePadWorkflow<string>({
    isCancelled,
    sharedMode: computed(() => true),
    shareSignUrls: computed(() => props.shareSignUrls ?? {}),
    docId,
    reloadOnly: ['inspection'],
    resolveSignUrl: (role) => props.shareSignUrls?.[role] ?? '',
    resolveClearUrl: (role) => props.shareSignUrls?.[role] ?? '',
});

const approvalCardSource = computed(() => [
    {
        role: 'it',
        title: 'IT',
        name: props.inspection.it_staff || '-',
        signaturePath: createApprovalSignatureState(
            props.inspection.it_signature,
            null,
        ).path,
        signedAt: null,
        badge: createApprovalBadge(props.inspection.it_signature),
    },
    {
        role: 'checked',
        title: 'Checked',
        name: props.inspection.checked_by || '-',
        signaturePath: createApprovalSignatureState(
            props.inspection.checked_signature,
            null,
        ).path,
        signedAt: null,
        badge: createApprovalBadge(props.inspection.checked_signature),
    },
    {
        role: 'user',
        title: 'User',
        name: props.inspection.user || '-',
        signaturePath: createApprovalSignatureState(
            props.inspection.user_signature,
            null,
        ).path,
        signedAt: null,
        badge: createApprovalBadge(props.inspection.user_signature),
    },
    {
        role: 'leader',
        title: 'Leader / Head Dept.',
        name: props.inspection.dept_head || '-',
        signaturePath: createApprovalSignatureState(
            props.inspection.leader_signature,
            null,
        ).path,
        signedAt: null,
        badge: createApprovalBadge(props.inspection.leader_signature),
    },
]);

const { activeApprovalCard, activeClearCard } =
    useDocumentApprovalCards<string>({
        activeRole: signatureRole,
        clearRole: clearConfirmRole,
        cards: approvalCardSource,
    });
</script>

<template>
    <Head :title="`Inspection: ${props.inspection.report_id}`" />

    <div class="min-h-screen bg-[#f4f6f5] px-4 py-6">
        <div
            class="mx-auto max-w-[210mm] overflow-hidden rounded-2xl bg-white shadow-sm"
        >
            <!-- Header bar -->
            <div
                class="flex items-center justify-between border-b border-slate-100 px-6 py-4"
            >
                <div>
                    <h2 class="text-base font-black text-slate-900">
                        {{ props.inspection.report_id }}
                    </h2>
                    <p
                        class="mt-0.5 text-[10px] font-bold tracking-widest text-slate-400 uppercase"
                    >
                        Inspection Report
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 shadow-sm transition-all hover:bg-slate-50 hover:text-slate-900 active:scale-95"
                        title="Print"
                        @click="() => window.print()"
                    >
                        <PrinterIcon class="size-4" />
                    </button>
                    <span
                        v-if="isCompleted"
                        class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-[9px] font-black tracking-widest text-emerald-600 uppercase"
                    >
                        Completed
                    </span>
                </div>
            </div>

            <AppSignatureLinkHelpPanel class="mx-6 mt-4" />

            <!-- Document -->
            <div class="p-6">
                <InspectionDocument
                    :inspection="props.inspection"
                    :is-completed="isCompleted"
                    :shared-mode="true"
                    :open-signature-modal="openSignatureModal"
                    :open-clear-confirm="openClearConfirm"
                />
            </div>
        </div>
    </div>

    <!-- Signature Modal -->
    <StbSignatureModal
        :open="signatureModalOpen"
        :active-approval-card="activeApprovalCard"
        :signature-error="signatureError"
        :signature-processing="signatureProcessing"
        :set-canvas-ref="setCanvasRef"
        :start-drawing="startDrawing"
        :draw-signature="drawSignature"
        :stop-drawing="stopDrawing"
        :clear-signature="clearSignature"
        :close-signature-modal="closeSignatureModal"
        :submit-signature="submitSignature"
    />

    <!-- Clear Signature Confirm -->
    <StbConfirmDialog
        :open="Boolean(clearConfirmRole)"
        kicker="Hapus Tanda Tangan"
        :title="`Hapus tanda tangan ${activeClearCard?.title || ''}?`"
        :description="`Tanda tangan untuk ${activeClearCard?.name || '-'} akan dikosongkan.`"
        :confirm-label="signatureProcessing ? 'Menghapus...' : 'Ya, Hapus'"
        :processing="signatureProcessing || !clearConfirmRole"
        confirm-variant="danger"
        cancel-label="Tidak"
        @close="closeClearConfirm"
        @confirm="clearConfirmRole && clearSignatureRole(clearConfirmRole)"
    />
</template>
