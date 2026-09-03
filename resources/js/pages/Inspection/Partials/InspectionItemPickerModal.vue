<script setup lang="ts">
import AppAssetPickerModal from '@/components/AppAssetPickerModal.vue';
import type { SnipeAsset } from '@/composables/useSnipeDirectory';
import {
    getStbAssetReferenceLabel,
    getStbAssetReferenceValue,
} from '@/utils/stbDirectory';

// Inspection only allows: hardware, accessories, component — NOT license or consumable
type Category = 'assets' | 'license' | 'accessories' | 'consumable' | 'component';

const HIDDEN: Category[] = ['license', 'consumable'];

defineProps<{
    open: boolean;
    assetsByCategory: Record<Category, SnipeAsset[]>;
    loadingByCategory: Record<Category, boolean>;
}>();

const emit = defineEmits<{
    (e: 'select', asset: SnipeAsset): void;
    (e: 'update:open', value: boolean): void;
    (e: 'load-category', category: Category, force?: boolean): void;
}>();

const getReferenceLabel = (asset: SnipeAsset) =>
    getStbAssetReferenceLabel(asset.asset_type);

const getReferenceValue = (asset: SnipeAsset) =>
    getStbAssetReferenceValue(asset);
</script>

<template>
    <AppAssetPickerModal
        :open="open"
        :assets-by-category="assetsByCategory"
        :loading-by-category="loadingByCategory"
        :hidden-categories="HIDDEN"
        :show-all-statuses="true"
        dialog-title="Pilih Asset Inspection"
        dialog-copy="Cari asset dari direktori master. Hardware, Aksesori, dan Komponen tersedia untuk inspection."
        :get-reference-label="getReferenceLabel"
        :get-reference-value="getReferenceValue"
        @select="emit('select', $event)"
        @update:open="emit('update:open', $event)"
        @load-category="emit('load-category', $event)"
    />
</template>
