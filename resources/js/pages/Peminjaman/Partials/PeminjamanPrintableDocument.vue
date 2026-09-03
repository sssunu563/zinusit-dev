<script setup lang="ts">
import { computed } from 'vue';
import PeminjamanPrintableBase from './PeminjamanPrintableBase.vue';
import type { GroupParts, PeminjamanItem } from '@/pages/Peminjaman/types';
import {
    isPeminjamanLoanOut,
    isPeminjamanReturnDocument,
    resolvePeminjamanPhotoLabel,
    resolvePeminjamanRemarkLabel,
    resolvePeminjamanRequesterRoleLabels,
} from '@/pages/Peminjaman/utils/peminjaman';

type SignatureRole =
    | 'it_drafter'
    | 'requester_received';

interface PeminjamanDocument {
    status?: number | null;
    document_type?: string | null;
    movement_type?: string | null;
    use_date: string | null;
    po_doc_no: string | null;
    created_at: string;
    photo: string | null;
    remark: string | null;
    items: PeminjamanItem[];
    it_drafter_signature_path: string | null;
    it_drafter_signed_at: string | null;
    requester_received_signature_path: string | null;
    requester_received_signed_at: string | null;
}

const props = defineProps<{
    peminjaman: PeminjamanDocument;
    docId: string;
    groupParts: GroupParts;
    userName: string;
    phoneNumber: string;
    email: string;
    position: string;
    deptHead: string;
    itDrafterName: string;
    statusLabel: string;
    isCompleted: boolean;
    isCancelled: boolean;
    sharedMode: boolean;
    formatDate: (date?: string | null) => string;
    formatDateTime: (date?: string | null) => string;
    getAssetLabel: (item: PeminjamanItem) => string;
    openSignatureModal: (role: string) => void;
    openClearConfirm: (role: string) => void;
}>();

const signatureSections = computed(() => [
    {
        key: 'signatures',
        title: 'PENANDATANGAN',
        columns: [
            {
                role: 'it_drafter' as SignatureRole,
                label: 'IT Drafter',
                name: props.itDrafterName,
                signaturePath: props.peminjaman.it_drafter_signature_path,
                signedAt: props.peminjaman.it_drafter_signed_at,
                imageAlt: 'IT Drafter Signature',
            },
            {
                role: 'requester_received' as SignatureRole,
                label: resolvePeminjamanRequesterRoleLabels(props.peminjaman)
                    .receiver,
                name: props.userName,
                signaturePath:
                    props.peminjaman.requester_received_signature_path,
                signedAt: props.peminjaman.requester_received_signed_at,
                imageAlt: 'Borrower Signature',
            },
        ],
    },
]);

const documentDateLabel = computed(() => {
    if (isPeminjamanReturnDocument(props.peminjaman)) {
        return 'Tanggal Pengembalian';
    }

    if (isPeminjamanLoanOut(props.peminjaman)) {
        return 'Tanggal Peminjaman';
    }

    return 'Tanggal Penyerahan';
});

const printPhotoLabel = computed(() =>
    resolvePeminjamanPhotoLabel(props.peminjaman),
);
const printRemarkLabel = computed(() =>
    resolvePeminjamanRemarkLabel(props.peminjaman),
);
const printDocumentTitle = computed(() => {
    if (isPeminjamanReturnDocument(props.peminjaman)) {
        return 'Form Pengembalian Peminjaman';
    }

    if (isPeminjamanLoanOut(props.peminjaman)) {
        return 'Form Peminjaman';
    }

    return 'Form Dokumen Asset';
});
const printDocumentIntro = computed(() => {
    if (isPeminjamanReturnDocument(props.peminjaman)) {
        return 'Tata letak final untuk verifikasi pengembalian asset dan arsip dokumen.';
    }

    if (isPeminjamanLoanOut(props.peminjaman)) {
        return 'Tata letak final untuk proses peminjaman asset dan arsip dokumen.';
    }

    return 'Tata letak final siap dicetak dan dibagikan untuk tanda tangan.';
});
const signerIntro = computed(() => {
    if (isPeminjamanReturnDocument(props.peminjaman)) {
        return 'Pihak yang menandatangani dokumen pengembalian ini:';
    }

    if (isPeminjamanLoanOut(props.peminjaman)) {
        return 'Pihak yang menandatangani dokumen peminjaman ini:';
    }

    return 'Saya yang bertandatangan di bawah ini:';
});
const agreementIntro = computed(() => {
    if (isPeminjamanReturnDocument(props.peminjaman)) {
        return 'Dengan ini menyatakan bahwa asset telah dikembalikan dan diverifikasi sesuai kondisi saat diterima kembali:';
    }

    if (isPeminjamanLoanOut(props.peminjaman)) {
        return 'Dengan ini menyetujui ketentuan peminjaman asset perusahaan sebagai berikut:';
    }

    return 'Telah menyetujui ketentuan yang berlaku dalam keadaan sadar dan tanpa ada paksaan dari pihak manapun:';
});
const agreementBody = computed(() => {
    if (isPeminjamanReturnDocument(props.peminjaman)) {
        return [
            '(A) Asset yang dikembalikan telah diperiksa secara visual dan dicocokkan dengan data pada dokumen ini.',
            '(B) Kondisi asset saat kembali, termasuk kerusakan atau kekurangan aksesori, dicatat sebagai dasar tindak lanjut.',
            '(C) Hasil verifikasi pengembalian menjadi acuan untuk proses perbaikan, penggantian, atau penutupan pinjaman.',
        ];
    }

    if (isPeminjamanLoanOut(props.peminjaman)) {
        return [
            '(A) Asset digunakan hanya untuk kepentingan pekerjaan dan dijaga keamanannya selama masa pinjam.',
            '(B) Asset tidak dipindahtangankan, dipinjamkan ulang, atau diubah tanpa persetujuan pihak terkait.',
            '(C) Peminjam bertanggung jawab atas kehilangan atau kerusakan akibat kelalaian pribadi selama masa peminjaman.',
        ];
    }

    return [
        '(A) Menyimpan dan menjaga semua dokumen, informasi, atau keterangan yang terdapat di dalam barang/ asset yang dianggap sebagai rahasia Perusahaan.',
        '(B) Menjaga dan berusaha mencegah kemungkinan hal-hal yang dapat membahayakan barang/ asset perusahaan.',
        '(C) Merawat, menjaga keamanan/ kebersihan dan memelihara barang/ asset milik perusahaan yang dipercayakan kepadanya atau yang digunakan dalam melaksanakan pekerjaannya.',
        '(D) Bertanggungjawab melakukan penggantian apabila melakukan kesalahan/ kelalaian pribadi yang mengakibatkan rusak/hilangnya barang/aset perusahaan.',
    ];
});
const violationHeading = computed(() =>
    isPeminjamanReturnDocument(props.peminjaman)
        ? 'Temuan Ketidaksesuaian:'
        : 'Pelanggaran:',
);
const violationBody = computed(() => {
    if (isPeminjamanReturnDocument(props.peminjaman)) {
        return [
            '(A) Asset kembali dalam kondisi tidak sesuai dengan catatan pemakaian atau item pendukung tidak lengkap.',
            '(B) Terdapat kerusakan, modifikasi, atau kehilangan yang perlu ditindaklanjuti oleh pihak terkait.',
        ];
    }

    return [
        '(A) Membawa keluar atau menyalahgunakan barang barang milik perusahaan dan/ atau perlengkapan milik perusahaan untuk kepentingan pribadi tanpa izin pimpinan perusahaan.',
        '(B) Menyalahgunakan barang-barang milik perusahaan yang dipercayakan kepadanya untuk kepentingan dan keuntungan pribadi ataupun pihak ketiga lainnya.',
    ];
});
</script>

<template>
    <PeminjamanPrintableBase
        :document="peminjaman"
        :doc-id="docId"
        :group-parts="groupParts"
        :user-name="userName"
        :phone-number="phoneNumber"
        :email="email"
        :position="position"
        :status-label="statusLabel"
        :is-completed="isCompleted"
        :is-cancelled="isCancelled"
        :shared-mode="sharedMode"
        :format-date="formatDate"
        :format-date-time="formatDateTime"
        :get-asset-label="getAssetLabel"
        :open-signature-modal="openSignatureModal"
        :open-clear-confirm="openClearConfirm"
        header-title="FORM PEMINJAMAN"
        header-subtitle="PT. Zinus Global Indonesia"
        :header-doc-no="docId"
        :print-document-title="printDocumentTitle"
        :print-document-intro="printDocumentIntro"
        :document-date-label="documentDateLabel"
        :signer-intro="signerIntro"
        :signature-sections="signatureSections"
    >
        <template #header>
            <slot name="header" />
        </template>
        <template #actions>
            <slot name="actions" />
        </template>
    </PeminjamanPrintableBase>
</template>
