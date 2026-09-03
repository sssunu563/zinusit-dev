<script setup lang="ts">
import AppDocumentItemsTable from '@/components/AppDocumentItemsTable.vue';
import type { PeminjamanItem } from '@/pages/Peminjaman/types';

defineProps<{
    items: PeminjamanItem[];
    totalQty: number;
    getAssetLabel: (item: PeminjamanItem) => string;
}>();
</script>

<template>
    <AppDocumentItemsTable
        v-bind="$props"
        section-kicker="Item"
        section-title="Aset yang dipinjamkan"
        section-copy="Ringkasan item peminjaman ini dirancang untuk verifikasi cepat tanpa harus masuk ke layout cetak dokumen."
    />
</template>
