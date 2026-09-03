<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import InspectionForm from '@/pages/Inspection/Partials/InspectionForm.vue';
import type { BreadcrumbItem } from '@/types';

interface Props {
    nextSequence?: number;
    initialData?: Record<string, any>;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard',   href: '/dashboard' },
    { title: 'Inspection',  href: '/inspection-menu' },
    { title: 'Inspections', href: '/inspection' },
    { title: 'Create',      href: '/inspection/create' },
];

let form: any = null;

const handleSubmit = (formData: any) => {
    form = useForm({ ...formData });

    form.post('/inspection', {
        forceFormData: true,
        onError: (errors: any) => {
            console.error('Form errors:', errors);
        },
    });
};

const handleCancel = () => window.history.back();

const isLoading   = computed(() => form?.processing ?? false);
const errorMessage = computed(() => {
    const errors = form?.errors ?? {};
    if (!errors || Object.keys(errors).length === 0) return null;
    return Object.values(errors).flat().join(', ');
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="app-page-shell">
            <Head title="Buat Inspection" />
            <div v-if="errorMessage" class="app-form-alert app-form-alert-danger">{{ errorMessage }}</div>
            <InspectionForm
                :initial-data="props.initialData ?? {}"
                :next-sequence="nextSequence ?? 1"
                :is-loading="isLoading"
                @submit="handleSubmit"
                @cancel="handleCancel"
            />
        </div>
    </AppLayout>
</template>
