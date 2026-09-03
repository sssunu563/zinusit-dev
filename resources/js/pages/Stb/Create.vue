<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import StbHandoverForm from '@/pages/Stb/Partials/StbHandoverForm.vue';
import StbReturnForm from '@/pages/Stb/Partials/StbReturnForm.vue';
import type { LoanReferenceOption } from '@/pages/Stb/types';
import type { BreadcrumbItem } from '@/types';
import { resolveStbFlowTitle } from '@/utils/stb';

interface Props {
    nextStbId: number;
    initialData?: Record<string, any>;
    loanReferences: LoanReferenceOption[];
}

interface StbItemPayload {
    id?: number | null;
    nama: string;
    kategori: string;
    type: string;
    jumlah: number | null;
    serialNo: string;
    inventory_number?: string | null;
    computer_id: number | null;
}

interface StbFormPayload {
    docId?: string | null;
    id: number | null;
    deliverDate: string;
    status: number | string | null;
    documentType: string | null;
    movementType: string | null;
    linkedStbId: number | string | null;
    itDrafter_id: number | string | null;
    itChecker_id: number | string | null;
    itApproved_id: number | string | null;
    reqDocNo: string;
    poDocNo: string;
    user_id: number | string | null;
    group_id: number | string | null;
    building: string;
    useDate: string;
    batchNo: string;
    photo: File | null;
    remark: string;
    createDate: string;
    items: StbItemPayload[];
}

const props = defineProps<Props>();

const flowLabel = computed(() =>
    resolveStbFlowTitle(
        {
            document_type: props.initialData?.documentType,
            movement_type: props.initialData?.movementType,
        },
        'Surat Tanda Bukti (STB)',
    ),
);

const flowDescription = computed(() => {
    if (props.initialData?.documentType === 'handover') {
        return 'Lengkapi data serah terima sesuai jenis alur yang dipilih dari menu create.';
    }

    return 'Pilih alur dokumen dari menu create, lalu lengkapi data penerima, asset, dan approval.';
});
const isReturn = computed(() => props.initialData?.movementType === 'return');

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'STB', href: '/stb' },
    { title: 'Create', href: '/stb/create' },
];

const form = useForm<StbFormPayload>({
    docId: null,
    id: null,
    deliverDate: '',
    status: '',
    documentType: '',
    movementType: '',
    linkedStbId: '',
    itDrafter_id: '',
    itChecker_id: '',
    itApproved_id: '',
    reqDocNo: '',
    poDocNo: '',
    user_id: '',
    group_id: '',
    building: '',
    useDate: '',
    batchNo: '',
    photo: null,
    remark: '',
    createDate: '',
    items: [],
});

const handleSubmit = (data: Record<string, any>) => {
    const formData = data as StbFormPayload;
    form.clearErrors();
    form.docId = formData.docId ?? null;
    form.id = formData.id;
    form.deliverDate = formData.deliverDate || '';
    form.status = formData.status ?? '';
    form.documentType = formData.documentType ?? '';
    form.movementType = formData.movementType ?? '';
    form.linkedStbId = formData.linkedStbId ?? '';
    form.itDrafter_id = formData.itDrafter_id ?? '';
    form.itChecker_id = formData.itChecker_id ?? '';
    form.itApproved_id = formData.itApproved_id ?? '';
    form.reqDocNo = formData.reqDocNo || '';
    form.poDocNo = formData.poDocNo || '';
    form.user_id = formData.user_id ?? '';
    form.group_id = formData.group_id ?? '';
    form.building = formData.building || '';
    form.useDate = formData.useDate || '';
    form.batchNo = formData.batchNo || '';
    form.photo = formData.photo;
    form.remark = formData.remark || '';
    form.createDate = formData.createDate || '';
    form.items = formData.items || [];

    form.post('/stb', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

const handleCancel = () => {
    router.get('/stb');
};

const isLoading = computed(() => form.processing);
const formErrors = computed(() => form.errors);
const errorMessage = computed(() => {
    const errors = formErrors.value;
    if (!errors || Object.keys(errors).length === 0) return null;
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
            >
                {{ errorMessage }}
            </div>

            <div>
                <component
                    :is="isReturn ? StbReturnForm : StbHandoverForm"
                    :initialData="{
                        previewId: props.nextStbId,
                        ...props.initialData,
                        loanReferences: props.loanReferences,
                    }"
                    :isLoading="isLoading"
                    :allowed-document-types="[
                        { value: 'handover', label: 'Serah Terima' },
                    ]"
                    page-kicker="FORM SERAH TERIMA BARANG"
                    :page-title="flowLabel"
                    page-copy="Lengkapi data penerima, daftar aset, dan persetujuan untuk menerbitkan dokumen."
                    @save="handleSubmit"
                    @cancel="handleCancel"
                />
            </div>
        </div>
    </AppLayout>
</template>
