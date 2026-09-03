<script setup lang="ts">
import AppPrintToolbar from '@/components/AppPrintToolbar.vue';

defineProps<{
    docId: string;
    statusLabel: string;
    userName: string;
    itemCount: number;
    totalQty: number;
    toolbarKicker: string;
    toolbarCopy: string;
    subjectLabel: string;
    assetLabel: string;
}>();
</script>

<template>
    <AppPrintToolbar v-bind="$props" />
</template>
