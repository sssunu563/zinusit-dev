<script setup lang="ts">
import AppAssetPickerModal from '@/components/AppAssetPickerModal.vue';
import type { SnipeAsset } from '@/composables/useSnipeDirectory';
import {
    getStbAssetReferenceLabel,
    getStbAssetReferenceValue,
} from '@/utils/stbDirectory';

type Category =
    | 'assets'
    | 'license'
    | 'accessories'
    | 'consumable'
    | 'component';

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

const handleLoadCategory = (category: Category, force = false) =>
    emit('load-category', category, force);
</script>

<template>
    <AppAssetPickerModal
        :open="open"
        :assets-by-category="assetsByCategory"
        :loading-by-category="loadingByCategory"
        dialog-title="Pilih Item STB"
        dialog-copy="Cari item dari direktori master. Hanya menampilkan aset yang berstatus stok tersedia (Ready to Deploy)."
        :get-reference-label="getReferenceLabel"
        :get-reference-value="getReferenceValue"
        @select="emit('select', $event)"
        @update:open="emit('update:open', $event)"
        @load-category="handleLoadCategory"
    />
</template>
