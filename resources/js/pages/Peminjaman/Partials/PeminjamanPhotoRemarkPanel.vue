<script setup lang="ts">
import AppDocumentPhotoRemarkPanel from '@/components/AppDocumentPhotoRemarkPanel.vue';

defineProps<{
    photo: string | null;
    remark: string | null;
    photoKicker: string;
    photoTitle: string;
    photoAlt: string;
    emptyPhotoCopy: string;
    remarkKicker: string;
    remarkTitle: string;
}>();
</script>

<template>
    <AppDocumentPhotoRemarkPanel v-bind="$props" />
</template>
