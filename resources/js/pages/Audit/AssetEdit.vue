<script setup lang="ts">
import AssetEditForm from '@/pages/Asset/Create.vue';

const props = defineProps({
    mode: { type: String, default: 'edit' },
    assetId: { type: Number, required: true },
    initialType: { type: String, required: true },
    types: { type: Array, required: true },
    metadata: { type: Object, required: true },
    initialData: { type: Object, default: undefined },
    initialModelDetail: { type: Object, default: undefined },
    auditContext: { type: Object, required: true },
});
</script>

<template>
    <AssetEditForm v-bind="props" :audit-page="true" />
</template>
