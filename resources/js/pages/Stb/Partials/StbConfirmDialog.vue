<script setup lang="ts">
import AppConfirmDialog from '@/components/AppConfirmDialog.vue';

defineProps<{
    open: boolean;
    kicker: string;
    title: string;
    description: string;
    confirmLabel: string;
    confirmVariant?: 'primary' | 'warning' | 'danger';
    processing?: boolean;
    cancelLabel?: string;
    subject?: string | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'confirm'): void;
}>();
</script>

<template>
    <AppConfirmDialog
        v-bind="$props"
        @close="emit('close')"
        @confirm="emit('confirm')"
    />
</template>
