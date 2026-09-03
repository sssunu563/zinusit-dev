<script setup lang="ts">
import AppDocumentDetailSummary from '@/components/AppDocumentDetailSummary.vue';

defineProps<{
    documentLabel: string;
    detailDateLabel: string;
    useDate: string;
    location: string;
    department: string;
    poDocNo: string;
    expectedReturnDate: string;
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
        section-title="Ringkasan operasional peminjaman"
        section-copy="Panel ini menyatukan status, tanggal, dan referensi utama agar proses verifikasi peminjaman atau pengembalian lebih cepat dibaca."
    />
</template>
