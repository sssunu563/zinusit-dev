<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import PeminjamanForm from '@/pages/Peminjaman/Partials/PeminjamanForm.vue';
import type { LoanReferenceOption } from '@/pages/Peminjaman/types';
import {
    formatPeminjamanDocId,
    resolvePeminjamanFlowTitle,
} from '@/pages/Peminjaman/utils/peminjaman';
import type { BreadcrumbItem } from '@/types';

interface PeminjamanItem {
    id: number;
    nama: string;
    kategori: string | null;
    type: string;
    jumlah: number;
    serial_no: string;
    inventory_number: string | null;
    computer_id: number | null;
}

interface PeminjamanRecord {
    id: number;
    status: number | null;
    document_type?: string | null;
    movement_type?: string | null;
    linkedLoanId?: number | null;
    it_drafter_id: number | null;

    user_id: number | null;
    group_id: number | null;
    use_date: string | null;
    expected_return_date: string | null;
    photo: string | null;
    remark: string | null;
    created_at: string;
    updated_at: string;
    items: PeminjamanItem[];
}

interface Props {
    peminjaman: PeminjamanRecord;
    loanReferences: LoanReferenceOption[];
}

interface PeminjamanItemPayload {
    id?: number | null;
    nama: string;
    kategori: string;
    type: string;
    jumlah: number | null;
    serialNo: string;
    inventory_number?: string | null;
    computer_id: number | null;
}

interface PeminjamanFormPayload {
    _method: 'put';
    docId?: string | null;
    id: number | null;
    status: number | string | null;
    documentType: string | null;
    movementType: string | null;
    linkedLoanId: number | string | null;
    itDrafter_id: number | string | null;

    user_id: number | string | null;
    group_id: number | string | null;
    useDate: string;
    expectedReturnDate: string;
    photo: File | string | null;
    remark: string;
    createDate: string;
    items: PeminjamanItemPayload[];
}

const props = defineProps<Props>();

const flowTitle = computed(
    () =>
        `Edit ${resolvePeminjamanFlowTitle(props.peminjaman, 'Peminjaman Asset')}`,
);
const docId = computed(
    () =>
        formatPeminjamanDocId({
            id: props.peminjaman.id,
            locationName: props.peminjaman.location_name,
            date: props.peminjaman.created_at,
        }) || `${props.peminjaman.id}`,
);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Peminjaman', href: '/peminjaman' },
    { title: 'Edit', href: `/peminjaman/${props.peminjaman.id}/edit` },
];

// Pass peminjaman data langsung tanpa modifikasi
const initialData = computed(() => props.peminjaman);

const form = useForm<PeminjamanFormPayload>({
    _method: 'put',
    docId: docId.value,
    id: props.peminjaman.id,
    status: props.peminjaman.status ?? '',
    documentType: 'loan',
    movementType: props.peminjaman.movement_type ?? 'out',
    linkedLoanId: props.peminjaman.linkedLoanId ?? '',
    itDrafter_id: props.peminjaman.it_drafter_id ?? '',

    user_id: props.peminjaman.user_id ?? '',
    group_id: props.peminjaman.group_id ?? '',
    useDate: props.peminjaman.use_date || '',
    expectedReturnDate: props.peminjaman.expected_return_date || '',
    photo: null,
    remark: props.peminjaman.remark || '',
    createDate: props.peminjaman.created_at || '',
    items: props.peminjaman.items.map((item) => ({
        id: item.id,
        nama: item.nama,
        kategori: item.kategori ?? '',
        type: item.type,
        jumlah: item.jumlah,
        serialNo: item.serial_no,
        inventory_number: item.inventory_number,
        computer_id: item.computer_id,
    })),
});

const handleSubmit = (data: Record<string, any>) => {
    const formData = data as PeminjamanFormPayload;
    form.clearErrors();
    form.docId = formData.docId ?? docId.value;
    form.id = formData.id;
    form.status = formData.status ?? '';
    form.documentType = 'loan';
    form.movementType = formData.movementType ?? 'out';
    form.linkedLoanId = formData.linkedLoanId ?? '';
    form.itDrafter_id = formData.itDrafter_id ?? '';

    form.user_id = formData.user_id ?? '';
    form.group_id = formData.group_id ?? '';
    form.useDate = formData.useDate || '';
    form.expectedReturnDate = formData.expectedReturnDate || '';
    // Ambil foto dari field photo yang sudah di-resolve di PeminjamanForm
    form.photo = formData.photo instanceof File ? formData.photo : null;
    form.remark = formData.remark || '';
    form.createDate = formData.createDate || '';
    form.items = formData.items || [];

    form.transform((payload) => {
        // Hanya kirim File objects baru, jangan kirim existing attachments
        const newPayload = { ...payload };
        if (newPayload.attachments) {
            newPayload.attachments = newPayload.attachments.filter(
                (a: any) => a instanceof File,
            );
        }
        if (!(payload.photo instanceof File)) {
            const { photo, ...rest } = newPayload;
            void photo;
            return rest;
        }
        return newPayload;
    }).post(`/peminjaman/${props.peminjaman.id}`, {
        forceFormData: true,
        preserveScroll: true,
    });
};

const handleCancel = () => {
    window.history.back();
};

const isLoading = computed(() => form.processing);
const errorMessage = computed(() => {
    const errors = form.errors;
    if (!errors || Object.keys(errors).length === 0) return null;
    return Object.values(errors).flat().join(', ');
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="app-page-shell">
            <Head :title="`${flowTitle} ${docId}`" />

            <div
                v-if="errorMessage"
                class="app-form-alert app-form-alert-danger"
            >
                {{ errorMessage }}
            </div>

            <PeminjamanForm
                :initialData="initialData"
                :loan-references="props.loanReferences"
                :isLoading="isLoading"
                page-kicker="Ubah"
                :page-title="flowTitle"
                :page-copy="`Doc ID: ${docId}`"
                @save="handleSubmit"
                @cancel="handleCancel"
            />
        </div>
    </AppLayout>
</template>
