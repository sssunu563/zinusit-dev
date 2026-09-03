<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    PrinterIcon,
    CheckCircle2,
    RotateCcw,
    Edit2,
    Trash2,
    Share2,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AppRelatedDocumentsPanel from '@/components/AppRelatedDocumentsPanel.vue';
import AppSignatureLinkHelpPanel from '@/components/AppSignatureLinkHelpPanel.vue';
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
import PeminjamanConfirmDialog from '@/pages/Peminjaman/Partials/PeminjamanConfirmDialog.vue';
import PeminjamanPrintableDocument from '@/pages/Peminjaman/Partials/PeminjamanPrintableDocument.vue';
import PeminjamanSignatureModal from '@/pages/Peminjaman/Partials/PeminjamanSignatureModal.vue';
import QuickReturnModal from '@/pages/Peminjaman/Partials/QuickReturnModal.vue';
import type { PeminjamanItem } from '@/pages/Peminjaman/types';
import {
    resolvePeminjamanAssetLabel,
    resolvePeminjamanDateLabel,
    formatPeminjamanDocId,
    isPeminjamanLoanOut,
    isPeminjamanReturnDocument,
    resolvePeminjamanPhotoLabel,
    resolvePeminjamanDocumentLabel,
    resolvePeminjamanRequesterRoleLabels,
} from '@/pages/Peminjaman/utils/peminjaman';
import {
    ensurePeminjamanAssetsLoaded,
    ensurePeminjamanDirectoryLoaded,
    getPeminjamanAssetLabel,
    getPeminjamanDeptHeadLabel,
    getPeminjamanGroupParts,
    getPeminjamanUserEmail,
    getPeminjamanUserLabel,
    getPeminjamanUserPhone,
    getPeminjamanUserPosition,
} from '@/pages/Peminjaman/utils/peminjamanDirectory';
import type { BreadcrumbItem } from '@/types';

interface PeminjamanRecord {
    id: number;
    status: number | null;
    document_type?: string | null;
    movement_type?: string | null;
    linkedLoanId?: number | null;
    returned_at?: string | null;
    it_drafter_id: number | null;
    po_doc_no: string | null;
    user_id: number | null;
    group_id: number | null;
    use_date: string | null;
    expected_return_date: string | null;
    photo: string | null;
    attachments: Array<{ id: number; file_path: string; file_type?: string }>;
    remark: string | null;
    it_drafter_signature_path: string | null;
    it_drafter_signed_at: string | null;
    requester_received_signature_path: string | null;
    requester_received_signed_at: string | null;
    requester_dept_head_signature_path: string | null;
    requester_dept_head_signed_at: string | null;
    completed_pdf_path: string | null;
    completed_at: string | null;
    cancelled_at?: string | null;
    created_at: string;
    updated_at: string;
    items: PeminjamanItem[];
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
    | 'requester_received'
    | 'requester_dept_head';

interface HistoryItem {
    id: number;
    serial_no: string;
    user_label: string;
    movement_type: 'out' | 'return';
    completed_at: string | null;
    remark: string | null;
}

interface Props {
    peminjaman: PeminjamanRecord;
    sharedMode?: boolean;
    shareSignUrls?: Partial<Record<SignatureRole, string>>;
    isFullySigned?: boolean;
    isCompleted?: boolean;
    isCancelled?: boolean;
    completedPdfUrl?: string | null;
    linkedDocument?: RelatedDocument | null;
    relatedDocuments?: RelatedDocument[];
    assetHistory?: Record<string, HistoryItem[]>;
}

const props = withDefaults(defineProps<Props>(), {
    sharedMode: false,
    shareSignUrls: () => ({}),
    isFullySigned: false,
    isCompleted: false,
    completedPdfUrl: null,
    linkedDocument: null,
    relatedDocuments: () => [],
    assetHistory: () => ({}),
});

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Peminjaman', href: '/peminjaman' },
    { title: 'Detail', href: `/peminjaman/${props.peminjaman.id}` },
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
const quickReturnOpen = ref(false);

const openDeleteConfirm = () => {
    deleteConfirmOpen.value = true;
};

const closeDeleteConfirm = () => {
    deleteConfirmOpen.value = false;
};

const deletePeminjaman = () => {
    router.post(
        `/peminjaman/${props.peminjaman.id}/cancel`,
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
    getPeminjamanGroupParts(
        props.peminjaman.group_id,
        props.peminjaman.user_id,
    ),
);
const userName = computed(() =>
    getPeminjamanUserLabel(props.peminjaman.user_id),
);
const phoneNumber = computed(() =>
    getPeminjamanUserPhone(props.peminjaman.user_id),
);
const email = computed(() => getPeminjamanUserEmail(props.peminjaman.user_id));
const position = computed(() =>
    getPeminjamanUserPosition(props.peminjaman.user_id),
);
const deptHead = computed(() =>
    getPeminjamanDeptHeadLabel(props.peminjaman.user_id),
);
const itDrafterName = computed(() =>
    getPeminjamanUserLabel(props.peminjaman.it_drafter_id),
);
const getAssetLabel = (item: PeminjamanItem) => getPeminjamanAssetLabel(item);
const docId = computed(
    () =>
        formatPeminjamanDocId({
            id: props.peminjaman.id,
            locationName: props.peminjaman.location_name,
            date: props.peminjaman.created_at,
        }) || props.peminjaman.id.toString(),
);
const statusLabel = computed(() =>
    isCancelled.value
        ? 'Dibatalkan'
        : resolvePeminjamanDocumentLabel(props.peminjaman),
);
const isCompleted = computed(() =>
    Boolean(
        props.isCompleted ||
        props.peminjaman.is_completed ||
        props.peminjaman.completed_at,
    ),
);
const isCancelled = computed(() =>
    Boolean(props.isCancelled || props.peminjaman.cancelled_at),
);
const hasAllSignatures = computed(() =>
    Boolean(
        props.peminjaman.it_drafter_signature_path &&
        props.peminjaman.requester_received_signature_path,
    ),
);
const canComplete = computed(
    () =>
        !props.sharedMode &&
        hasAllSignatures.value &&
        !isCompleted.value &&
        !isCancelled.value,
);
const canReturn = computed(() => {
    return (
        isPeminjamanLoanOut(props.peminjaman) &&
        isCompleted.value &&
        !isCancelled.value &&
        !props.peminjaman.returned_at
    );
});

const loanReturnHref = computed(() => null);

const showRelationPanel = computed(
    () =>
        Boolean(props.linkedDocument) ||
        props.relatedDocuments.length > 0 ||
        Boolean(loanReturnHref.value),
);
const requesterRoleLabels = computed(() =>
    resolvePeminjamanRequesterRoleLabels(props.peminjaman),
);
const isReturnFlow = computed(() =>
    isPeminjamanReturnDocument(props.peminjaman),
);
const photoLabel = computed(() =>
    resolvePeminjamanPhotoLabel(props.peminjaman),
);

const { relationCompletedLabel } = useDocumentFlowPresentation({
    isReturnFlow,
    isLoanOut: computed(() => isPeminjamanLoanOut(props.peminjaman)),
    isCompleted,
    isCancelled,
    baseDetailLabel: 'Detail Dokumen',
    photoLabel,
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

const copyShareLink = async () => {
    if (!props.shareUrl) return;

    try {
        await navigator.clipboard.writeText(props.shareUrl);
    } catch {
        const input = document.createElement('textarea');
        input.value = props.shareUrl;
        input.style.position = 'fixed';
        input.style.opacity = '0';
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        document.body.removeChild(input);
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
    sharedMode: computed(() => props.sharedMode),
    shareSignUrls: computed(() => props.shareSignUrls),
    docId,
    reloadOnly: ['peminjaman', 'sharedMode'],
    resolveSignUrl: (role) => `/peminjaman/${props.peminjaman.id}/sign/${role}`,
    resolveClearUrl: (role) =>
        `/peminjaman/${props.peminjaman.id}/sign/${role}`,
});

const approvalCardSource = computed<ApprovalCardItem[]>(() => [
    {
        role: 'it_drafter',
        title: 'IT Drafter',
        name: itDrafterName.value,
        signaturePath: createApprovalSignatureState(
            props.peminjaman.it_drafter_signature_path,
            props.peminjaman.it_drafter_signed_at,
        ).path,
        signedAt: props.peminjaman.it_drafter_signed_at,
        badge: createApprovalBadge(props.peminjaman.it_drafter_signature_path),
    },
    {
        role: 'requester_received',
        title: requesterRoleLabels.value.receiver,
        name: userName.value,
        signaturePath: createApprovalSignatureState(
            props.peminjaman.requester_received_signature_path,
            props.peminjaman.requester_received_signed_at,
        ).path,
        signedAt: props.peminjaman.requester_received_signed_at,
        badge: createApprovalBadge(
            props.peminjaman.requester_received_signature_path,
        ),
    },
]);

const { approvalCards, activeApprovalCard, activeClearCard, signedCount } =
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

const { completeProcessing, printDocument, completeDocument } =
    useDocumentPageActions({
        completedPdfUrl: computed(() => props.completedPdfUrl),
        printUrl: computed(() => `/peminjaman/${props.peminjaman.id}/print`),
        completeUrl: computed(
            () => `/peminjaman/${props.peminjaman.id}/complete`,
        ),
        canComplete,
        reloadOnly: [
            'peminjaman',
            'sharedMode',
            'isFullySigned',
            'isCompleted',
        ],
        shareUrl: computed(() => props.shareUrl),
        completeErrorMessage: 'Gagal memfinalisasi dokumen.',
        closeCompleteConfirm,
        setActionNotice,
    });

useDocumentAssetBootstrap(
    () => props.peminjaman.items,
    ensurePeminjamanDirectoryLoaded,
    ensurePeminjamanAssetsLoaded,
);
</script>

<template>
    <component
        :is="props.sharedMode ? 'div' : AppLayout"
        v-bind="props.sharedMode ? {} : { breadcrumbs }"
    >
        <div class="mx-auto w-full max-w-[210mm] px-2 py-4">
            <div
                class="app-page-shell"
                :class="props.sharedMode ? 'space-y-0' : 'space-y-4'"
            >
                <Head :title="docId" />

                <div
                    v-if="props.sharedMode"
                    class="overflow-hidden rounded-t-2xl border border-b-0 border-slate-200 bg-white shadow-sm"
                >
                    <div
                        class="flex items-center justify-between border-b border-slate-100 px-5 py-4"
                    >
                        <div>
                            <h2 class="text-base font-black text-slate-900">
                                {{ docId }}
                            </h2>
                            <p
                                class="mt-0.5 text-[10px] font-bold tracking-widest text-slate-400 uppercase"
                            >
                                Peminjaman Report
                            </p>
                        </div>
                        <button
                            type="button"
                            class="flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 shadow-sm transition-all hover:bg-slate-50 hover:text-slate-900 active:scale-95"
                            title="Print"
                            @click="printDocument"
                        >
                            <PrinterIcon class="size-4" />
                        </button>
                    </div>
                    <AppSignatureLinkHelpPanel class="mx-5 my-4" />
                </div>

                <Link
                    v-if="!props.sharedMode"
                    href="/peminjaman"
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
                    class="relative overflow-hidden border border-slate-200 bg-white shadow-xl shadow-slate-200/40"
                    :class="
                        props.sharedMode
                            ? 'rounded-b-2xl border-t-0 shadow-sm'
                            : 'rounded-[32px]'
                    "
                >
                    <!-- Decorative background -->
                    <div
                        class="pointer-events-none absolute top-0 right-0 -mt-24 -mr-24 h-96 w-96 rounded-full bg-primary/5 blur-[120px]"
                    />

                    <div class="relative z-10 p-8 md:p-10">
                        <PeminjamanPrintableDocument
                            :peminjaman="props.peminjaman"
                            :doc-id="docId"
                            :group-parts="groupParts"
                            :user-name="userName"
                            :phone-number="phoneNumber"
                            :email="email"
                            :position="position"
                            :dept-head="deptHead"
                            :it-drafter-name="itDrafterName"
                            :status-label="statusLabel"
                            :is-completed="isCompleted"
                            :is-cancelled="isCancelled"
                            :shared-mode="props.sharedMode"
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
                                            >{{ signedCount }} /
                                            {{ approvalCards.length }} TANDA
                                            TANGAN</span
                                        >
                                    </div>
                                </div>
                            </template>
                            <template #actions>
                                <div class="flex flex-wrap items-center gap-3">
                                    <button
                                        v-if="
                                            !props.sharedMode &&
                                            !isCompleted &&
                                            !isCancelled
                                        "
                                        type="button"
                                        class="flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-white text-[#003628] shadow-sm transition-all hover:bg-slate-50 active:scale-95"
                                        title="Salin link public"
                                        @click="copyShareLink"
                                    >
                                        <Share2 class="size-4" />
                                    </button>
                                    <button
                                        type="button"
                                        class="flex h-11 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-slate-500 shadow-sm transition-all hover:bg-slate-50 hover:text-slate-900 active:scale-95"
                                        title="Print Archive"
                                        @click="printDocument"
                                    >
                                        <PrinterIcon class="size-4" />
                                    </button>

                                    <button
                                        v-if="canReturn"
                                        type="button"
                                        class="flex h-11 items-center gap-2 rounded-xl border border-emerald-100 bg-emerald-50 px-5 text-[10px] font-black tracking-widest text-emerald-600 uppercase shadow-sm transition-all hover:bg-emerald-100 active:scale-95"
                                        @click="quickReturnOpen = true"
                                    >
                                        <RotateCcw class="size-4" />
                                        <span>Proses Pengembalian</span>
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
                                                !props.sharedMode &&
                                                signedCount === 0 &&
                                                !isCompleted &&
                                                !isCancelled
                                            "
                                            :href="`/peminjaman/${props.peminjaman.id}/edit`"
                                            class="flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-white text-amber-600 shadow-sm transition-all hover:border-amber-100 hover:bg-amber-50 active:scale-90"
                                            title="Modify Document"
                                        >
                                            <Edit2 class="size-4" />
                                        </Link>
                                        <button
                                            v-if="
                                                !props.sharedMode &&
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
                        </PeminjamanPrintableDocument>
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

                <PeminjamanSignatureModal
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

                <PeminjamanConfirmDialog
                    :open="deleteConfirmOpen"
                    kicker="Batalkan Dokumen"
                    :title="`Batalkan dokumen ${docId}?`"
                    description="Dokumen akan dipindahkan ke daftar dibatalkan dan dikunci dari edit, tanda tangan, serta finalisasi."
                    confirm-label="Ya, Batalkan"
                    cancel-label="Tidak"
                    confirm-variant="danger"
                    @close="closeDeleteConfirm"
                    @confirm="deletePeminjaman"
                />

                <PeminjamanConfirmDialog
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

                <PeminjamanConfirmDialog
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

                <QuickReturnModal
                    :show="quickReturnOpen"
                    :peminjaman-id="props.peminjaman.id"
                    @close="quickReturnOpen = false"
                />
            </div>
        </div>
    </component>
</template>
