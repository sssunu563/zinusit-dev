<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CheckCircle2,
    Edit2,
    Printer,
    AlertTriangle,
    Share2,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import StbSignatureModal from '@/pages/Stb/Partials/StbSignatureModal.vue';
import StbConfirmDialog from '@/pages/Stb/Partials/StbConfirmDialog.vue';
import InspectionDocument from '@/pages/Inspection/Partials/InspectionDocument.vue';
import { useSignaturePadWorkflow } from '@/composables/useSignaturePadWorkflow';
import {
    createApprovalBadge,
    createApprovalSignatureState,
    useDocumentApprovalCards,
} from '@/composables/useDocumentApprovalCards';
import { useDocumentPageActions } from '@/composables/useDocumentPageActions';
import { useActionNotice } from '@/composables/useActionNotice';
import type { BreadcrumbItem } from '@/types';

interface Props {
    inspection: any;
    shareUrl?: string;
    shareSignUrls?: Record<string, string>;
}
const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inspections', href: '/inspection' },
    {
        title: props.inspection.report_id,
        href: `/inspection/${props.inspection.id}`,
    },
];

const formatDate = (d?: string | null) => {
    if (!d) return '-';
    return new Date(d).toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};

const isCompleted = computed(() => !!props.inspection.completed_at);
const isCancelled = computed(() => false);

const hasAllSignatures = computed(
    () =>
        !!(
            props.inspection.it_signature &&
            props.inspection.checked_signature &&
            props.inspection.user_signature &&
            props.inspection.leader_signature
        ),
);
const canComplete = computed(
    () => hasAllSignatures.value && !isCompleted.value,
);

// device category display
const catLabel = (cat: string) =>
    ({
        pc: 'PC',
        laptop: 'Laptop',
        printer: 'Printer',
        monitor: 'Monitor',
        other: 'Other',
        network: 'Network Device',
    })[cat] ?? cat;

const docId = computed(() => props.inspection.report_id);
const { actionNotice, setActionNotice } = useActionNotice();

// Share link copy
const copyShareLink = async () => {
    if (!props.shareUrl) return;
    try {
        await navigator.clipboard.writeText(props.shareUrl);
    } catch {
        const el = document.createElement('textarea');
        el.value = props.shareUrl;
        el.style.position = 'fixed';
        el.style.opacity = '0';
        document.body.appendChild(el);
        el.select();
        document.execCommand('copy');
        document.body.removeChild(el);
    }
    setActionNotice('Share link berhasil disalin.', 'success');
};

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
    sharedMode: ref(false),
    shareSignUrls: ref(undefined),
    docId,
    reloadOnly: ['inspection'],
    resolveSignUrl: (role) => `/inspection/${props.inspection.id}/sign/${role}`,
    resolveClearUrl: (role) =>
        `/inspection/${props.inspection.id}/sign/${role}`,
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

const { activeApprovalCard, activeClearCard, signedCount } =
    useDocumentApprovalCards<string>({
        activeRole: signatureRole,
        clearRole: clearConfirmRole,
        cards: approvalCardSource,
    });

const completeConfirmOpen = ref(false);

const { completeProcessing, printDocument, completeDocument } =
    useDocumentPageActions({
        shareUrl: ref(null),
        completedPdfUrl: computed(() =>
            props.inspection.completed_pdf_path
                ? `/storage/${props.inspection.completed_pdf_path}`
                : null,
        ),
        printUrl: computed(() => `/inspection/${props.inspection.id}/print`),
        completeUrl: computed(
            () => `/inspection/${props.inspection.id}/complete`,
        ),
        canComplete,
        reloadOnly: ['inspection'],
        completeErrorMessage: 'Gagal menyelesaikan inspection.',
        closeCompleteConfirm: () => {
            completeConfirmOpen.value = false;
        },
        setActionNotice,
    });

const getSig = (role: string) =>
    approvalCardSource.value.find((c) => c.role === role);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`Inspection: ${inspection.report_id}`" />

        <div class="mx-auto w-full max-w-[210mm] px-2 py-4">
            <div class="app-page-shell space-y-4">
                <!-- Back -->
                <Link
                    href="/inspection"
                    class="inline-flex h-9 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-xs font-bold text-slate-500 shadow-sm transition-all hover:text-slate-900 active:scale-95"
                >
                    <ArrowLeft class="size-3.5" /> Kembali
                </Link>

                <!-- Action notice -->
                <div
                    v-if="actionNotice"
                    :class="[
                        'app-inline-notice',
                        actionNotice.type === 'success'
                            ? 'app-inline-notice-success'
                            : 'app-inline-notice-danger',
                    ]"
                >
                    {{ actionNotice.message }}
                </div>

                <div
                    class="relative overflow-hidden rounded-[32px] border border-slate-200 bg-white shadow-xl shadow-slate-200/40"
                >
                    <div
                        class="pointer-events-none absolute top-0 right-0 -mt-24 -mr-24 h-96 w-96 rounded-full bg-[#003628]/5 blur-[120px]"
                    />

                    <div class="relative z-10 p-8 md:p-10">
                        <!-- Header row -->
                        <div
                            class="mb-6 flex flex-col gap-4 border-b border-slate-100 pb-6 md:flex-row md:items-start md:justify-between print:hidden"
                        >
                            <div>
                                <h1
                                    class="text-3xl font-black tracking-tight text-slate-900 uppercase"
                                >
                                    {{ inspection.report_id }}
                                </h1>
                                <div
                                    class="mt-1.5 flex items-center gap-2 text-[10px] font-black tracking-[0.15em] uppercase"
                                >
                                    <span class="text-[#003628]">{{
                                        inspection.report_type || 'Inspection'
                                    }}</span>
                                    <span class="text-slate-200">—</span>
                                    <span class="text-slate-400"
                                        >{{ signedCount }} / 4 TANDA
                                        TANGAN</span
                                    >
                                    <span
                                        v-if="isCompleted"
                                        class="ml-1 inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[9px] text-emerald-600"
                                    >
                                        <CheckCircle2 class="size-3" />
                                        Completed
                                    </span>
                                </div>
                            </div>
                            <div
                                class="flex shrink-0 flex-wrap items-center gap-2"
                            >
                                <!-- Share link — only while collecting signatures (not all signed yet, not completed) -->
                                <button
                                    v-if="shareUrl"
                                    type="button"
                                    class="flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-white text-[#003628] shadow-sm transition-all hover:border-[#003628]/20 hover:bg-[#003628]/5 active:scale-95"
                                    title="Salin link tanda tangan"
                                    @click="copyShareLink"
                                >
                                    <Share2 class="size-4" />
                                </button>
                                <!-- Print — always visible, goes to PDF if completed -->
                                <button
                                    type="button"
                                    class="flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 shadow-sm transition-all hover:bg-slate-50 hover:text-slate-900 active:scale-95"
                                    title="Print / PDF"
                                    @click="printDocument"
                                >
                                    <Printer class="size-4" />
                                </button>
                                <!-- Finalize — only when all 4 signed and not yet completed -->
                                <button
                                    v-if="canComplete"
                                    type="button"
                                    class="flex h-11 items-center gap-2 rounded-xl bg-[#003628] px-6 text-sm font-bold text-white shadow-lg shadow-emerald-900/20 transition-all hover:brightness-110 active:scale-95"
                                    :disabled="completeProcessing"
                                    @click="completeConfirmOpen = true"
                                >
                                    <CheckCircle2 class="size-4" />
                                    {{
                                        completeProcessing
                                            ? 'Memproses...'
                                            : 'Selesaikan'
                                    }}
                                </button>
                                <!-- Edit — only when zero signatures collected -->
                                <Link
                                    v-if="signedCount === 0 && !isCompleted"
                                    :href="`/inspection/${inspection.id}/edit`"
                                    class="flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-white text-amber-600 shadow-sm transition-all hover:border-amber-100 hover:bg-amber-50 active:scale-90"
                                    title="Edit"
                                >
                                    <Edit2 class="size-4" />
                                </Link>
                            </div>
                        </div>

                        <!-- Sync error -->
                        <div
                            v-if="
                                inspection.snipeit_sync_status === 'failed' &&
                                inspection.snipeit_sync_log
                            "
                            class="mb-5 flex gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-4"
                        >
                            <AlertTriangle
                                class="mt-0.5 size-4 shrink-0 text-rose-500"
                            />
                            <div>
                                <p
                                    class="mb-1 text-[11px] font-black tracking-widest text-rose-600 uppercase"
                                >
                                    Snipe-IT Sync Log
                                </p>
                                <pre
                                    class="font-mono text-[10px] whitespace-pre-wrap text-rose-700"
                                    >{{ inspection.snipeit_sync_log }}</pre
                                >
                            </div>
                        </div>

                        <InspectionDocument
                            :inspection="inspection"
                            :is-completed="isCompleted"
                            :open-signature-modal="openSignatureModal"
                            :open-clear-confirm="openClearConfirm"
                        />
                    </div>
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

        <!-- Complete Confirm -->
        <StbConfirmDialog
            :open="completeConfirmOpen"
            kicker="Finalisasi Inspection"
            title="Selesaikan inspection ini?"
            description="Setelah diselesaikan: asset akan di-checkin dan statusnya diubah ke Broken di Snipe-IT. Tindakan ini tidak bisa dibatalkan."
            :confirm-label="
                completeProcessing ? 'Memproses...' : 'Ya, Selesaikan'
            "
            :processing="completeProcessing"
            confirm-variant="warning"
            cancel-label="Tidak"
            @close="completeConfirmOpen = false"
            @confirm="completeDocument"
        />
    </AppLayout>
</template>
