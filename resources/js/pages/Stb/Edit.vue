<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import StbHandoverForm from '@/pages/Stb/Partials/StbHandoverForm.vue';
import StbReturnForm from '@/pages/Stb/Partials/StbReturnForm.vue';
import type { LoanReferenceOption } from '@/pages/Stb/types';
import type { BreadcrumbItem } from '@/types';
import { formatStbDocId, resolveStbFlowTitle } from '@/utils/stb';
import { getStbGroupParts } from '@/utils/stbDirectory';

interface StbItem {
    id: number;
    nama: string;
    kategori: string | null;
    type: string;
    jumlah: number;
    serial_no: string;
    inventory_number: string | null;
    computer_id: number | null;
    snipeit_asset_id?: number | null;
}

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
    created_at: string;
    updated_at: string;
    items: StbItem[];
}

interface Props {
    stb: STB;
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
    _method: 'put';
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
    photo: File | string | null;
    remark: string;
    createDate: string;
    items: StbItemPayload[];
}

const props = defineProps<Props>();

const isReturn = computed(() => props.stb.movement_type === 'return');
const flowTitle = computed(() => `Edit ${resolveStbFlowTitle(props.stb)}`);

const docId = computed(() => {
    return (
        formatStbDocId({
            id: props.stb.id,
            company: getStbGroupParts(props.stb.group_id).company,
            date: props.stb.created_at,
        }) || `${props.stb.id}`
    );
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'STB', href: '/stb' },
    { title: 'Edit', href: `/stb/${props.stb.id}/edit` },
];

const form = useForm<StbFormPayload>({
    _method: 'put',
    docId: docId.value,
    id: props.stb.id,
    deliverDate: props.stb.deliver_date || '',
    status: props.stb.status ?? '',
    documentType: props.stb.document_type ?? '',
    movementType: props.stb.movement_type ?? '',
    linkedStbId: props.stb.linked_stb_id ?? '',
    itDrafter_id: props.stb.it_drafter_id ?? '',
    itChecker_id: props.stb.it_checker_id ?? '',
    itApproved_id: props.stb.it_approved_id ?? '',
    reqDocNo: props.stb.req_doc_no || '',
    poDocNo: props.stb.po_doc_no || '',
    user_id: props.stb.user_id ?? '',
    group_id: props.stb.group_id ?? '',
    building: props.stb.building || '',
    useDate: props.stb.use_date || '',
    batchNo: props.stb.batch_no || '',
    photo: null,
    remark: props.stb.remark || '',
    createDate: props.stb.created_at || '',
    items: props.stb.items.map((item) => ({
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
    const formData = data as StbFormPayload;
    form.clearErrors();
    form.docId = formData.docId ?? docId.value;
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
    form.photo = formData.photo instanceof File ? formData.photo : null;
    form.remark = formData.remark || '';
    form.createDate = formData.createDate || '';
    form.items = formData.items || [];

    form.transform((payload) => {
        // Keep existing photo on backend unless user uploads a new file.
        if (!(payload.photo instanceof File)) {
            const { photo, ...rest } = payload;
            void photo;
            return rest;
        }

        return payload;
    }).post(`/stb/${props.stb.id}`, {
        forceFormData: true,
        preserveScroll: true,
    });
};

const handleCancel = () => {
    router.get(`/stb/${props.stb.id}`);
};

const stbData = ref({
    id: props.stb.id,
    deliverDate: props.stb.deliver_date,
    status: props.stb.status,
    documentType: props.stb.document_type,
    movementType: props.stb.movement_type,
    linkedStbId: props.stb.linked_stb_id,
    itDrafter_id: props.stb.it_drafter_id,
    itChecker_id: props.stb.it_checker_id,
    itApproved_id: props.stb.it_approved_id,
    reqDocNo: props.stb.req_doc_no,
    poDocNo: props.stb.po_doc_no,
    user_id: props.stb.user_id,
    group_id: props.stb.group_id,
    building: props.stb.building,
    useDate: props.stb.use_date,
    batchNo: props.stb.batch_no,
    photo: props.stb.photo,
    remark: props.stb.remark,
    createDate: props.stb.created_at,
    updatedAt: props.stb.updated_at,
    items: props.stb.items.map((item) => ({
        id: item.id,
        nama: item.nama,
        kategori: item.kategori ?? '',
        type: item.type,
        jumlah: item.jumlah,
        serialNo: item.serial_no,
        inventory_number: item.inventory_number,
        computer_id: item.computer_id,
        snipeit_asset_id: item.snipeit_asset_id,
    })),
});

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
            <Head :title="`${flowTitle} ${docId}`" />

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
                        ...stbData,
                        loanReferences: props.loanReferences,
                    }"
                    :isLoading="isLoading"
                    :allowed-document-types="
                        props.stb.document_type === 'service'
                            ? [
                                  { value: 'handover', label: 'Serah Terima' },
                                  {
                                      value: 'service',
                                      label: 'Perbaikan (Legacy)',
                                  },
                              ]
                            : [{ value: 'handover', label: 'Serah Terima' }]
                    "
                    page-kicker="FORM SERAH TERIMA BARANG"
                    :page-title="flowTitle"
                    :page-copy="`Memperbarui rincian dokumen ${docId}`"
                    @save="handleSubmit"
                    @cancel="handleCancel"
                />
            </div>
        </div>
    </AppLayout>
</template>
