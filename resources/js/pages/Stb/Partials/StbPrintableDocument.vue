<script setup lang="ts">
import { computed } from 'vue';
import AppPrintableDocument from '@/components/AppPrintableDocument.vue';
import type { GroupParts, StbItem } from '@/pages/Stb/types';
import {
    isStbLoanOut,
    isStbReturnDocument,
    resolveStbPhotoLabel,
    resolveStbRemarkLabel,
    resolveStbRequesterRoleLabels,
} from '@/utils/stb';

type SignatureRole =
    | 'it_drafter'
    | 'it_checker'
    | 'it_approved'
    | 'requester_received'
    | 'requester_dept_head';

interface StbDocument {
    status?: number | null;
    document_type?: string | null;
    movement_type?: string | null;
    deliver_date: string | null;
    building: string | null;
    use_date: string | null;
    batch_no: string | null;
    req_doc_no: string | null;
    po_doc_no: string | null;
    created_at: string;
    photo: string | null;
    remark: string | null;
    items: StbItem[];
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
}

const props = defineProps<{
    stb: StbDocument;
    docId: string;
    groupParts: GroupParts;
    userName: string;
    phoneNumber: string;
    email: string;
    position: string;
    deptHead: string;
    itDrafterName: string;
    itCheckerName: string;
    itApprovedName: string;
    statusLabel: string;
    isCompleted: boolean;
    isCancelled: boolean;
    formatDate: (date?: string | null) => string;
    formatDateTime: (date?: string | null) => string;
    getAssetLabel: (item: StbItem) => string;
    openSignatureModal: (role: string) => void;
    openClearConfirm: (role: string) => void;
    shareUrl?: string;
}>();

const signatureSections = computed(() => [
    {
        key: 'it',
        title: 'IT',
        columns: [
            {
                role: 'it_drafter' as SignatureRole,
                label: 'Drafter',
                name: props.itDrafterName,
                signaturePath: props.stb.it_drafter_signature_path,
                signedAt: props.stb.it_drafter_signed_at,
                imageAlt: 'IT Drafter Signature',
            },
            {
                role: 'it_checker' as SignatureRole,
                label: 'Checker',
                name: props.itCheckerName,
                signaturePath: props.stb.it_checker_signature_path,
                signedAt: props.stb.it_checker_signed_at,
                imageAlt: 'IT Checker Signature',
            },
            {
                role: 'it_approved' as SignatureRole,
                label: 'Approved',
                name: props.itApprovedName,
                signaturePath: props.stb.it_approved_signature_path,
                signedAt: props.stb.it_approved_signed_at,
                imageAlt: 'IT Approved Signature',
            },
        ],
    },
    {
        key: 'requester',
        title: resolveStbRequesterRoleLabels(props.stb).section,
        columns: [
            {
                role: 'requester_received' as SignatureRole,
                label: resolveStbRequesterRoleLabels(props.stb).receiver,
                name: props.userName,
                signaturePath: props.stb.requester_received_signature_path,
                signedAt: props.stb.requester_received_signed_at,
                imageAlt: 'Requester Received Signature',
            },
            {
                role: 'requester_dept_head' as SignatureRole,
                label: resolveStbRequesterRoleLabels(props.stb).approver,
                name: props.deptHead,
                signaturePath: props.stb.requester_dept_head_signature_path,
                signedAt: props.stb.requester_dept_head_signed_at,
                imageAlt: 'Requester Dept Head Signature',
            },
        ],
    },
]);

const documentDateLabel = computed(() => {
    if (isStbReturnDocument(props.stb)) {
        return 'Return Date';
    }

    if (isStbLoanOut(props.stb)) {
        return 'Loan Date';
    }

    return 'Deliver Date';
});

const printPhotoLabel = computed(() => resolveStbPhotoLabel(props.stb));
const printRemarkLabel = computed(() => resolveStbRemarkLabel(props.stb));
const printDocumentTitle = computed(() => {
    if (isStbReturnDocument(props.stb)) {
        return 'FORM PENGEMBALIAN BARANG';
    }
    return 'FORM SERAH TERIMA BARANG';
});
const printDocumentIntro = computed(() => {
    return 'Tata letak akhir siap cetak dan bagikan untuk tanda tangan.';
});
const signerIntro = computed(() => {
    if (isStbReturnDocument(props.stb)) {
        return 'Aset di bawah ini telah dikembalikan oleh:';
    }

    if (isStbLoanOut(props.stb)) {
        return 'Saya yang bertandatangan di bawah ini (Peminjam):';
    }

    return 'Saya yang bertandatangan di bawah ini:';
});
const agreementIntro = computed(() => {
    return 'Telah menyetujui ketentuan yang berlaku dalam keadaan sadar dan tanpa ada paksaan dari pihak manapun:';
});
const agreementBody = computed(() => {
    return [
        '(A) Menyimpan dan menjaga semua dokumen, informasi, atau keterangan yang terdapat di dalam barang/ asset yang dianggap sebagai rahasia Perusahaan.',
        '(B) Menjaga dan berusaha mencegah kemungkinan hal-hal yang dapat membahayakan barang/ asset perusahaan.',
        '(C) Merawat, menjaga keamanan/ kebersihan dan memelihara barang/ asset milik perusahaan yang dipercayakan kepadanya atau yang digunakan dalam melaksanakan pekerjaannya.',
        '(D) Bertanggungjawab melakukan penggantian apabila melakukan kesalahan/ kelalaian pribadi yang mengakibatkan rusak/hilangnya barang/aset perusahaan.',
    ];
});
const violationHeading = computed(() => {
    return 'Pelanggaran:';
});
const violationBody = computed(() => {
    return [
        '(A) Membawa keluar atau menyalahgunakan barang-barang milik perusahaan dan/atau perlengkapan milik perusahaan untuk kepentingan pribadi tanpa izin pimpinan perusahaan.',
        '(B) Menyalahgunakan barang-barang milik perusahaan yang dipercayakan kepadanya untuk kepentingan dan keuntungan pribadi ataupun pihak ketiga lainnya.',
    ];
});
</script>

<template>
    <AppPrintableDocument
        :document="stb"
        :doc-id="docId"
        :group-parts="groupParts"
        :user-name="userName"
        :phone-number="phoneNumber"
        :email="email"
        :position="position"
        :status-label="statusLabel"
        :is-completed="isCompleted"
        :is-cancelled="isCancelled"
        :format-date="formatDate"
        :format-date-time="formatDateTime"
        :get-asset-label="getAssetLabel"
        :open-signature-modal="openSignatureModal"
        :open-clear-confirm="openClearConfirm"
        header-title="FORM SERAH TERIMA BARANG"
        :header-subtitle="`PT. ${groupParts.company || 'Zinus Global Indonesia'}`"
        header-doc-no="Doc. No. IT/STB/XII/24/01"
        :print-document-title="printDocumentTitle"
        :print-document-intro="printDocumentIntro"
        :document-date-label="documentDateLabel"
        :signer-intro="signerIntro"
        :agreement-intro="agreementIntro"
        :agreement-body="agreementBody"
        :violation-heading="violationHeading"
        :violation-body="violationBody"
        :print-photo-label="printPhotoLabel"
        :print-remark-label="printRemarkLabel"
        :movement-type="stb.movement_type || ''"
        :signature-sections="signatureSections"
    >
        <template #header>
            <slot name="header" />
        </template>
        <template #actions>
            <slot name="actions" />
        </template>
    </AppPrintableDocument>
</template>
