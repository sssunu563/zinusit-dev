<script setup lang="ts">
import AppDocumentDetailSummary from '@/components/AppDocumentDetailSummary.vue';

defineProps<{
    documentLabel: string;
    detailDateLabel: string;
    deliverDate: string;
    useDate: string;
    location: string;
    department: string;
    building: string;
    batchNo: string;
    reqDocNo: string;
    poDocNo: string;
    updatedAt: string;
    completedAt: string;
    relatedReturnedAt: string;
    isCompleted: boolean;
    isCancelled: boolean;
    isReturnFlow: boolean;
    isLoanOut: boolean;
}>();
</script>

<template>
    <AppDocumentDetailSummary
        v-bind="$props"
        section-kicker="Ringkasan"
        section-title="Ringkasan operasional STB"
        section-copy="Lokasi, tanggal, asset, dan status final diringkas dalam satu panel untuk membantu verifikasi dokumen serah terima."
    />
</template>
