<script setup lang="ts">
import { computed } from 'vue';
import AppAssetPickerModal from '@/components/AppAssetPickerModal.vue';
import type { SnipeAsset } from '@/composables/useSnipeDirectory';
import {
    getPeminjamanAssetReferenceLabel,
    getPeminjamanAssetReferenceValue,
} from '@/pages/Peminjaman/utils/peminjamanDirectory';

type Category = 'assets' | 'license' | 'accessories' | 'consumable';

const props = defineProps<{
    open: boolean;
    assetsByCategory: Record<Category, SnipeAsset[]>;
    loadingByCategory: Record<Category, boolean>;
    movementType?: string; // Add movement type prop
}>();

const emit = defineEmits<{
    (e: 'select', asset: SnipeAsset): void;
    (e: 'update:open', value: boolean): void;
    (e: 'load-category', category: Category, force?: boolean): void;
}>();

const getReferenceLabel = (asset: SnipeAsset) =>
    getPeminjamanAssetReferenceLabel(asset.asset_type);

const getReferenceValue = (asset: SnipeAsset) =>
    getPeminjamanAssetReferenceValue(asset);

const handleLoadCategory = (category: Category, force = false) =>
    emit('load-category', category, force);

// Dynamic dialog copy based on movement type
const dialogCopy = computed(() => {
    if (props.movementType === 'return') {
        return 'Cari item dari direktori master. Hanya menampilkan aset yang berstatus Borrowed (sedang dipinjam).';
    }
    return 'Cari item dari direktori master. Hanya menampilkan aset yang berstatus Ready to Deploy (tersedia untuk dipinjam).';
});
</script>

<template>
    <AppAssetPickerModal
        :open="open"
        :assets-by-category="assetsByCategory"
        :loading-by-category="loadingByCategory"
        dialog-title="Pilih Aset Peminjaman"
        :dialog-copy="dialogCopy"
        :get-reference-label="getReferenceLabel"
        :get-reference-value="getReferenceValue"
        :hidden-categories="['license', 'consumable', 'component']"
        @select="emit('select', $event)"
        @update:open="emit('update:open', $event)"
        @load-category="handleLoadCategory"
    />
</template>
