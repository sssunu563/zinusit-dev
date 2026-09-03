<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import PeminjamanForm from '@/pages/Peminjaman/Partials/PeminjamanForm.vue';
import type { LoanReferenceOption } from '@/pages/Peminjaman/types';
import { resolvePeminjamanFlowTitle } from '@/pages/Peminjaman/utils/peminjaman';
import type { BreadcrumbItem } from '@/types';

interface Props {
    nextPeminjamanId: number;
    initialData?: Record<string, any>;
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
    photo: File | null;
    remark: string;
    createDate: string;
    items: PeminjamanItemPayload[];
}

const props = defineProps<Props>();

const flowLabel = computed(() => {
    if (props.initialData?.movementType === 'return') {
        return 'Pengembalian Peminjaman';
    }

    return resolvePeminjamanFlowTitle(
        {
            document_type: 'loan',
            movement_type: props.initialData?.movementType,
        },
        'Peminjaman Asset',
    );
});

const flowDescription = computed(() => {
    if (props.initialData?.movementType === 'return') {
        return 'Lengkapi data pengembalian asset.';
    }

    return '';
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Peminjaman', href: '/peminjaman' },
    { title: 'Create', href: '/peminjaman/create' },
];

const form = useForm<PeminjamanFormPayload>({
    docId: null,
    id: null,
    status: '',
    documentType: 'loan',
    movementType: 'out',
    linkedLoanId: '',
    itDrafter_id: '',
    user_id: '',
    group_id: '',
    useDate: '',
    expectedReturnDate: '',
    photo: null,
    remark: '',
    createDate: '',
    items: [],
});

const handleSubmit = (data: Record<string, any>) => {
    const formData = data as PeminjamanFormPayload;
    form.clearErrors();
    form.docId = formData.docId ?? null;
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
    form.photo = formData.photo;
    form.remark = formData.remark || '';
    form.createDate = formData.createDate || '';
    form.items = formData.items || [];

    form.post('/peminjaman', {
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
    
    // If items error is an array, display as bullet list
    if (errors.items && Array.isArray(errors.items)) {
        return errors.items.join('\n• ');
    }
    
    return Object.values(errors).flat().join(', ');
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="app-page-shell">
            <Head :title="`Buat ${flowLabel}`" />

            <div
                v-if="errorMessage"
                class="app-form-alert app-form-alert-danger"
                style="white-space: pre-line;"
            >
                {{ errorMessage }}
            </div>

            <PeminjamanForm
                :initialData="{
                    previewId: props.nextPeminjamanId,
                    ...props.initialData,
                }"
                :loan-references="props.loanReferences"
                :isLoading="isLoading"
                page-kicker="Buat"
                :page-title="flowLabel"
                :page-copy="flowDescription"
                @save="handleSubmit"
                @cancel="handleCancel"
            />
        </div>
    </AppLayout>
</template>
