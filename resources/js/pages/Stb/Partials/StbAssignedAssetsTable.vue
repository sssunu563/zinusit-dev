<script setup lang="ts">
import AppDocumentItemsTable from '@/components/AppDocumentItemsTable.vue';
import type { StbItem } from '@/pages/Stb/types';

defineProps<{
    items: StbItem[];
    totalQty: number;
    getAssetLabel: (item: StbItem) => string;
}>();
</script>

<template>
    <AppDocumentItemsTable
        v-bind="$props"
        section-kicker="Item"
        section-title="Aset yang diserahkan"
        section-copy="Ringkasan item pada dokumen STB ini membantu pengecekan cepat sebelum membuka versi cetak lengkap."
    />
</template>
