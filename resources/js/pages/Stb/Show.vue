<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    CheckCircle2,
    Edit2,
    PrinterIcon,
    RotateCcw,
    Trash2,
    ArrowLeft,
    ShieldCheck,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { LABELS } from '@/constants/labels';
import AppRelatedDocumentsPanel from '@/components/AppRelatedDocumentsPanel.vue';
import AppSignatureLinkHelpPanel from '@/components/AppSignatureLinkHelpPanel.vue';
import FlashAlert from '@/components/FlashAlert.vue';
import { useActionNotice } from '@/composables/useActionNotice';
import {
    createApprovalBadge,
    createApprovalSignatureState,
    useDocumentApprovalCards,
} from '@/composables/useDocumentApprovalCards';
import { useDocumentAssetBootstrap } from '@/composables/useDocumentAssetBootstrap';
import { useDocumentFlowPresentation } from '@/composables/useDocumentFlowPresentation';
import { useDocumentPageActions } from '@/composables/useDocumentPageActions';
import { useSignaturePadWorkflow } from '@/composables/useSignaturePadWorkflow';
import AppLayout from '@/layouts/AppLayout.vue';
import StbConfirmDialog from '@/pages/Stb/Partials/StbConfirmDialog.vue';
import StbPrintableDocument from '@/pages/Stb/Partials/StbPrintableDocument.vue';
import StbSignatureModal from '@/pages/Stb/Partials/StbSignatureModal.vue';
import type { StbItem } from '@/pages/Stb/types';
import type { BreadcrumbItem } from '@/types';
import {
    formatStbDocId,
    isStbLoanOut,
    isStbReturnDocument,
    resolveStbPhotoLabel,
    resolveStbDocumentLabel,
    resolveStbRequesterRoleLabels,
} from '@/utils/stb';
import {
    ensureStbAssetsLoaded,
    ensureStbDirectoryLoaded,
    getStbAssetLabel,
    getStbDeptHeadLabel,
    getStbGroupParts,
    getStbUserEmail,
    getStbUserLabel,
    getStbUserPhone,
    getStbUserPosition,
} from '@/utils/stbDirectory';

interface STB {
    id: number;
    deliver_date: string | null;
    status: number | null;
    document_type?: string | null;
    movement_type?: string | null;
    linked_stb_id?: number | null;
    returned_at?: string | null;
    it_drafter_id: number | null;
    it_checker_id: number | null;
    it_approved_id: number | null;
    req_doc_no: string | null;
    po_doc_no: string | null;
    user_id: number | null;
    group_id: number | null;
    building: string | null;
    use_date: string | null;
    batch_no: string | null;
    photo: string | null;
    remark: string | null;
    it_drafter_signature_path: string | null;
    it_drafter_signed_at: string | null;
    it_checker_signature_path: string | null;
    it_checker_signed_at: string | null;
    it_approved_signature_path: string | null;
    it_approved_signed_at: string | null;
    requester_received_signature_path: string | null;
    requester_received_signed_at: string | null;
    requester_dept_head_signature_path: string | null;
    requester_dept_head_signed_at: string | null;
    completed_pdf_path: string | null;
    completed_at: string | null;
    cancelled_at?: string | null;
    created_at: string;
    updated_at: string;
    items: StbItem[];
}

interface RelatedDocument {
    id: number;
    docId: string;
    href: string;
    relationLabel: string;
    documentLabel: string;
    userName: string;
    deliverDate: string | null;
    completedAt: string | null;
    returnedAt: string | null;
}

type SignatureRole =
    | 'it_drafter'
    | 'it_checker'
    | 'it_approved'
    | 'requester_received'
    | 'requester_dept_head';

interface Props {
    stb: STB;
    isFullySigned?: boolean;
    isCompleted?: boolean;
    isCancelled?: boolean;
    completedPdfUrl?: string | null;
    linkedDocument?: RelatedDocument | null;
    relatedDocuments?: RelatedDocument[];
    sharedMode?: boolean;
    shareUrl?: string | null;
    shareSignUrls?: Record<string, string>;
}

const props = withDefaults(defineProps<Props>(), {
    isFullySigned: false,
    isCompleted: false,
    completedPdfUrl: null,
    linkedDocument: null,
    relatedDocuments: () => [],
    sharedMode: false,
    shareUrl: null,
    shareSignUrls: () => ({}),
});

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'STB', href: '/stb' },
    { title: 'Detail', href: `/stb/${props.stb.id}` },
]);

const formatDate = (date?: string | null) => {
    if (!date) {
        return '-';
    }

    return new Date(date).toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};

const formatDateTime = (date?: string | null) => {
    if (!date) {
        return '-';
    }

    return new Date(date).toLocaleString('id-ID', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const deleteConfirmOpen = ref(false);

const openDeleteConfirm = () => {
    deleteConfirmOpen.value = true;
};

const closeDeleteConfirm = () => {
    deleteConfirmOpen.value = false;
};

const deleteStb = () => {
    router.post(
        `/stb/${props.stb.id}/cancel`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                closeDeleteConfirm();
            },
        },
    );
};

const groupParts = computed(() =>
    getStbGroupParts(props.stb.group_id, props.stb.user_id),
);
const userName = computed(() => getStbUserLabel(props.stb.user_id));
const phoneNumber = computed(() => getStbUserPhone(props.stb.user_id));
const email = computed(() => getStbUserEmail(props.stb.user_id));
const position = computed(() => getStbUserPosition(props.stb.user_id));
const deptHead = computed(() => getStbDeptHeadLabel(props.stb.user_id));
const itDrafterName = computed(() => getStbUserLabel(props.stb.it_drafter_id));
const itCheckerName = computed(() => getStbUserLabel(props.stb.it_checker_id));
const itApprovedName = computed(() =>
    getStbUserLabel(props.stb.it_approved_id),
);
const getAssetLabel = (item: StbItem) => getStbAssetLabel(item);
const docId = computed(
    () =>
        formatStbDocId({
            id: props.stb.id,
            locationName: groupParts.value.location,
            date: props.stb.created_at,
        }) || props.stb.id.toString(),
);
const statusLabel = computed(() =>
    isCancelled.value ? 'Dibatalkan' : resolveStbDocumentLabel(props.stb),
);
const isCompleted = computed(() =>
    Boolean(
        props.isCompleted ||
        (props.stb.completed_at && props.stb.completed_pdf_path),
    ),
);
const isCancelled = computed(() =>
    Boolean(props.isCancelled || props.stb.cancelled_at),
);
const hasAllSignatures = computed(() =>
    Boolean(
        props.stb.it_drafter_signature_path &&
        props.stb.it_checker_signature_path &&
        props.stb.it_approved_signature_path &&
        props.stb.requester_received_signature_path &&
        props.stb.requester_dept_head_signature_path,
    ),
);
const canComplete = computed(
    () =>
        !props.sharedMode &&
        hasAllSignatures.value &&
        !isCompleted.value &&
        !isCancelled.value &&
        !isStbLoanOut(props.stb),
);
const loanReturnHref = computed(() => {
    if (
        !isStbLoanOut(props.stb) ||
        !hasAllSignatures.value ||
        isCancelled.value ||
        props.stb.returned_at
    ) {
        return null;
    }

    return `/peminjaman/create?movementType=return&linkedStbId=${props.stb.id}`;
});
const showRelationPanel = computed(
    () =>
        Boolean(props.linkedDocument) ||
        props.relatedDocuments.length > 0 ||
        Boolean(loanReturnHref.value),
);
const requesterRoleLabels = computed(() =>
    resolveStbRequesterRoleLabels(props.stb),
);
const isReturnFlow = computed(() => isStbReturnDocument(props.stb));
const photoLabel = computed(() => resolveStbPhotoLabel(props.stb));
const { relationCompletedLabel } = useDocumentFlowPresentation({
    isReturnFlow,
    isLoanOut: computed(() => isStbLoanOut(props.stb)),
    isCompleted,
    isCancelled,
    baseDetailLabel: 'Detail STB',
    photoLabel,
});

const signedCount = computed(() => {
    let count = 0;
    if (props.stb.it_drafter_signature_path) count++;
    if (props.stb.it_checker_signature_path) count++;
    if (props.stb.it_approved_signature_path) count++;
    if (props.stb.requester_received_signature_path) count++;
    if (props.stb.requester_dept_head_signature_path) count++;
    return count;
});

interface ApprovalCardItem {
    role: string;
    title: string;
    name: string;
    signaturePath: string | null;
    signedAt: string | null;
    badge: string;
}

const completeConfirmOpen = ref(false);
const { actionNotice, setActionNotice } = useActionNotice();
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
    sharedMode: computed(() => props.sharedMode ?? false),
    shareSignUrls: computed(() => props.shareSignUrls ?? {}),
    docId,
    reloadOnly: ['stb', 'sharedMode'],
    resolveSignUrl: (role) => `/stb/${props.stb.id}/sign/${role}`,
    resolveClearUrl: (role) => `/stb/${props.stb.id}/sign/${role}`,
});

const approvalCardSource = computed<ApprovalCardItem[]>(() => [
    {
        role: 'it_drafter',
        title: 'Pembuat',
        name: itDrafterName.value,
        signaturePath: createApprovalSignatureState(
            props.stb.it_drafter_signature_path,
            props.stb.it_drafter_signed_at,
        ).path,
        signedAt: props.stb.it_drafter_signed_at,
        badge: createApprovalBadge(props.stb.it_drafter_signature_path),
    },
    {
        role: 'it_checker',
        title: 'Pemeriksa',
        name: itCheckerName.value,
        signaturePath: createApprovalSignatureState(
            props.stb.it_checker_signature_path,
            props.stb.it_checker_signed_at,
        ).path,
        signedAt: props.stb.it_checker_signed_at,
        badge: createApprovalBadge(props.stb.it_checker_signature_path),
    },
    {
        role: 'it_approved',
        title: 'Penyetuju',
        name: itApprovedName.value,
        signaturePath: createApprovalSignatureState(
            props.stb.it_approved_signature_path,
            props.stb.it_approved_signed_at,
        ).path,
        signedAt: props.stb.it_approved_signed_at,
        badge: createApprovalBadge(props.stb.it_approved_signature_path),
    },
    {
        role: 'requester_received',
        title: requesterRoleLabels.value.receiver,
        name: userName.value,
        signaturePath: createApprovalSignatureState(
            props.stb.requester_received_signature_path,
            props.stb.requester_received_signed_at,
        ).path,
        signedAt: props.stb.requester_received_signed_at,
        badge: createApprovalBadge(props.stb.requester_received_signature_path),
    },
    {
        role: 'requester_dept_head',
        title: requesterRoleLabels.value.approver,
        name: deptHead.value,
        signaturePath: createApprovalSignatureState(
            props.stb.requester_dept_head_signature_path,
            props.stb.requester_dept_head_signed_at,
        ).path,
        signedAt: props.stb.requester_dept_head_signed_at,
        badge: createApprovalBadge(
            props.stb.requester_dept_head_signature_path,
        ),
    },
]);

const { activeApprovalCard, activeClearCard } =
    useDocumentApprovalCards<string>({
        activeRole: signatureRole,
        clearRole: clearConfirmRole,
        cards: approvalCardSource,
    });

const openCompleteConfirm = () => {
    completeConfirmOpen.value = true;
};

const closeCompleteConfirm = () => {
    completeConfirmOpen.value = false;
};

const { completeProcessing, printDocument, copyShareLink, completeDocument } =
    useDocumentPageActions({
        shareUrl: computed(() => props.shareUrl ?? null),
        completedPdfUrl: computed(() => props.completedPdfUrl),
        printUrl: computed(() => `/stb/${props.stb.id}/print`),
        completeUrl: computed(() => `/stb/${props.stb.id}/complete`),
        canComplete,
        reloadOnly: ['stb', 'isFullySigned', 'isCompleted'],
        completeErrorMessage: 'Gagal memfinalisasi dokumen.',
        closeCompleteConfirm,
        setActionNotice,
    });

useDocumentAssetBootstrap(
    () => props.stb.items,
    ensureStbDirectoryLoaded,
    ensureStbAssetsLoaded,
);
</script>

<template>
    <component
        :is="props.sharedMode ? 'div' : AppLayout"
        v-bind="props.sharedMode ? {} : { breadcrumbs }"
    >
        <div class="mx-auto w-full max-w-[210mm] px-2 py-4">
            <div class="app-page-shell space-y-4">
                <Head :title="docId" />

                <Link
                    v-if="!props.sharedMode"
                    href="/stb"
                    class="inline-flex h-9 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-xs font-bold text-slate-500 shadow-sm transition-all hover:text-slate-900 active:scale-95"
                >
                    <ArrowLeft class="size-3.5" />
                    <span>Kembali</span>
                </Link>

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
                    v-if="!props.sharedMode"
                    class="relative overflow-hidden rounded-[32px] border border-slate-200 bg-white shadow-xl shadow-slate-200/40"
                >
                    <!-- Decorative background -->
                    <div
                        class="pointer-events-none absolute top-0 right-0 -mt-24 -mr-24 h-96 w-96 rounded-full bg-primary/5 blur-[120px]"
                    />

                    <div class="relative z-10 p-8 md:p-10">
                        <StbPrintableDocument
                            :stb="props.stb"
                            :doc-id="docId"
                            :group-parts="groupParts"
                            :user-name="userName"
                            :phone-number="phoneNumber"
                            :email="email"
                            :position="position"
                            :dept-head="deptHead"
                            :it-drafter-name="itDrafterName"
                            :it-checker-name="itCheckerName"
                            :it-approved-name="itApprovedName"
                            :status-label="statusLabel"
                            :is-completed="isCompleted"
                            :is-cancelled="isCancelled"
                            :format-date="formatDate"
                            :format-date-time="formatDateTime"
                            :get-asset-label="getAssetLabel"
                            :open-signature-modal="openSignatureModal"
                            :open-clear-confirm="openClearConfirm"
                        >
                            <template #header>
                                <div class="space-y-1">
                                    <h1
                                        class="text-3xl font-black tracking-tight text-slate-900 uppercase"
                                    >
                                        {{ docId }}
                                    </h1>
                                    <div
                                        class="flex items-center gap-2 text-[10px] font-black tracking-[0.15em] uppercase"
                                    >
                                        <span
                                            :class="
                                                isCancelled
                                                    ? 'text-red-500'
                                                    : 'text-[#003628]'
                                            "
                                            >{{ statusLabel }}</span
                                        >
                                        <span class="text-slate-200">—</span>
                                        <span class="text-slate-400"
                                            >{{ signedCount }} / 5 TANDA
                                            TANGAN</span
                                        >
                                    </div>
                                </div>
                            </template>
                            <template #actions>
                                <div class="flex flex-wrap items-center gap-3">
                                    <button
                                        type="button"
                                        class="flex h-11 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-slate-500 shadow-sm transition-all hover:bg-slate-50 hover:text-slate-900 active:scale-95"
                                        title="Print Archive"
                                        @click="printDocument"
                                    >
                                        <PrinterIcon class="size-4" />
                                    </button>
                                    <button
                                        v-if="canComplete"
                                        type="button"
                                        class="flex h-11 items-center gap-2 rounded-xl bg-[#003628] px-6 text-sm font-bold text-white shadow-lg shadow-emerald-900/20 transition-all hover:brightness-110 active:scale-95"
                                        :disabled="completeProcessing"
                                        @click="openCompleteConfirm"
                                    >
                                        <CheckCircle2 class="size-4" />
                                        <span>{{
                                            completeProcessing
                                                ? 'Memproses...'
                                                : 'Selesaikan'
                                        }}</span>
                                    </button>
                                    <Link
                                        v-if="loanReturnHref"
                                        :href="loanReturnHref"
                                        class="flex h-11 items-center gap-2 rounded-xl bg-[#003628] px-6 text-sm font-bold text-white shadow-lg shadow-emerald-900/20 transition-all hover:brightness-110 active:scale-95"
                                    >
                                        <RotateCcw class="size-4" />
                                        <span>Verifikasi Pengembalian</span>
                                    </Link>
                                    <div
                                        class="ml-auto flex items-center gap-2 lg:ml-0"
                                    >
                                        <Link
                                            v-if="
                                                signedCount === 0 &&
                                                !isCompleted &&
                                                !isCancelled
                                            "
                                            :href="`/stb/${props.stb.id}/edit`"
                                            class="flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-white text-amber-600 shadow-sm transition-all hover:border-amber-100 hover:bg-amber-50 active:scale-90"
                                            title="Modify Document"
                                        >
                                            <Edit2 class="size-4" />
                                        </Link>
                                        <button
                                            v-if="
                                                signedCount === 0 &&
                                                !isCompleted &&
                                                !isCancelled
                                            "
                                            type="button"
                                            class="flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-white text-red-500 shadow-sm transition-all hover:border-red-100 hover:bg-red-50 active:scale-90"
                                            title="Purge Document"
                                            @click="openDeleteConfirm"
                                        >
                                            <Trash2 class="size-4" />
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </StbPrintableDocument>
                    </div>
                </div>

                <!-- Shared mode: show document with signature capability -->
                <div
                    v-else
                    class="relative overflow-hidden rounded-[32px] border border-slate-200 bg-white shadow-xl shadow-slate-200/40"
                >
                    <div class="relative z-10 p-8 md:p-10">
                        <AppSignatureLinkHelpPanel class="mb-6" />
                        <StbPrintableDocument
                            :stb="props.stb"
                            :doc-id="docId"
                            :group-parts="groupParts"
                            :user-name="userName"
                            :phone-number="phoneNumber"
                            :email="email"
                            :position="position"
                            :dept-head="deptHead"
                            :it-drafter-name="itDrafterName"
                            :it-checker-name="itCheckerName"
                            :it-approved-name="itApprovedName"
                            :status-label="statusLabel"
                            :is-completed="isCompleted"
                            :is-cancelled="isCancelled"
                            :shared-mode="true"
                            :format-date="formatDate"
                            :format-date-time="formatDateTime"
                            :get-asset-label="getAssetLabel"
                            :open-signature-modal="openSignatureModal"
                            :open-clear-confirm="openClearConfirm"
                        >
                            <template #header>
                                <div class="space-y-1">
                                    <h1
                                        class="text-2xl font-black tracking-tight text-slate-900 uppercase"
                                    >
                                        {{ docId }}
                                    </h1>
                                    <div
                                        class="flex items-center gap-2 text-[10px] font-black tracking-[0.15em] uppercase"
                                    >
                                        <span
                                            :class="
                                                isCancelled
                                                    ? 'text-red-500'
                                                    : 'text-[#003628]'
                                            "
                                            >{{ statusLabel }}</span
                                        >
                                        <span class="text-slate-200">—</span>
                                        <span class="text-slate-400"
                                            >{{ signedCount }} / 5 TANDA
                                            TANGAN</span
                                        >
                                    </div>
                                </div>
                            </template>
                            <template #actions>
                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        class="flex h-10 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-slate-500 shadow-sm transition-all hover:bg-slate-50 hover:text-slate-900 active:scale-95"
                                        @click="printDocument"
                                    >
                                        <PrinterIcon class="size-4" />
                                    </button>
                                </div>
                            </template>
                        </StbPrintableDocument>
                    </div>
                </div>

                <AppRelatedDocumentsPanel
                    v-if="!props.sharedMode && showRelationPanel"
                    :linked-document="props.linkedDocument"
                    :related-documents="props.relatedDocuments"
                    :loan-return-href="loanReturnHref"
                    :relation-completed-label="relationCompletedLabel"
                    :format-date="formatDate"
                />

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

                <StbConfirmDialog
                    :open="deleteConfirmOpen"
                    kicker="Batalkan Dokumen"
                    :title="`Batalkan dokumen ${docId}?`"
                    description="Dokumen akan dipindahkan ke daftar dibatalkan dan dikunci dari edit, tanda tangan, serta finalisasi."
                    confirm-label="Ya, Batalkan"
                    cancel-label="Tidak"
                    confirm-variant="danger"
                    @close="closeDeleteConfirm"
                    @confirm="deleteStb"
                />

                <StbConfirmDialog
                    :open="Boolean(clearConfirmRole)"
                    kicker="Hapus Tanda Tangan"
                    :title="`Hapus tanda tangan ${activeClearCard?.title || ''}?`"
                    :description="`Tanda tangan untuk ${activeClearCard?.name || '-'} akan dikosongkan lagi.`"
                    :confirm-label="
                        signatureProcessing
                            ? 'Menghapus...'
                            : 'Ya, Hapus Tanda Tangan'
                    "
                    :processing="signatureProcessing || !clearConfirmRole"
                    confirm-variant="danger"
                    cancel-label="Tidak"
                    @close="closeClearConfirm"
                    @confirm="
                        clearConfirmRole && clearSignatureRole(clearConfirmRole)
                    "
                />

                <StbConfirmDialog
                    :open="completeConfirmOpen"
                    kicker="Finalisasi Dokumen"
                    title="Kunci dokumen ke PDF final?"
                    description="Setelah difinalisasi, dokumen masuk ke daftar final, aksi edit atau hapus ditutup, dan fitur cetak memakai PDF final."
                    :confirm-label="
                        completeProcessing
                            ? 'Memfinalisasi...'
                            : 'Ya, Finalisasi'
                    "
                    :processing="completeProcessing"
                    confirm-variant="warning"
                    cancel-label="Tidak"
                    @close="closeCompleteConfirm"
                    @confirm="completeDocument"
                />
            </div>
        </div>
    </component>
</template>
