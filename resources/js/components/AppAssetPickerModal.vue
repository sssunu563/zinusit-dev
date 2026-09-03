<script setup lang="ts">
import { Search, RefreshCw } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { SnipeAsset } from '@/composables/useSnipeDirectory';
import { ASSET_CATEGORIES, CATEGORY_OPTIONS } from '@/constants/categories';

type Category =
    | 'assets' // hardware
    | 'license'
    | 'accessories'
    | 'consumable'
    | 'component';

const CATEGORIES = CATEGORY_OPTIONS;

const props = defineProps<{
    open: boolean;
    assetsByCategory: Record<Category, SnipeAsset[]>;
    loadingByCategory: Record<Category, boolean>;
    dialogTitle: string;
    dialogCopy: string;
    resultsLabel?: string;
    emptyHint?: string;
    getReferenceLabel: (asset: SnipeAsset) => string;
    getReferenceValue: (asset: SnipeAsset) => string | null | undefined;
    showAllStatuses?: boolean;
    hiddenCategories?: Category[];
}>();

const emit = defineEmits<{
    (e: 'select', asset: SnipeAsset): void;
    (e: 'update:open', value: boolean): void;
    (e: 'load-category', category: Category, force?: boolean): void;
}>();

const selectedCategory = ref<Category>('assets');
const searchQuery = ref('');

const visibleCategories = computed(() => {
    if (!props.hiddenCategories) return CATEGORIES;
    return CATEGORIES.filter(
        (cat) => !props.hiddenCategories?.includes(cat.value),
    );
});

watch(
    () => props.open,
    (opened) => {
        if (opened) {
            selectedCategory.value = 'assets';
            searchQuery.value = '';
        }
    },
);

const selectCategory = (cat: Category) => {
    selectedCategory.value = cat;
    searchQuery.value = '';
    emit('load-category', cat);
};

const currentAssets = computed(
    () => props.assetsByCategory[selectedCategory.value] ?? [],
);
const isLoading = computed(
    () => props.loadingByCategory[selectedCategory.value] ?? false,
);

const filteredAssets = computed(() => {
    const q = searchQuery.value.toLowerCase().trim();

    // Filter by category items
    let items = currentAssets.value;

    // Filter by status
    if (selectedCategory.value === 'assets') {
        items = items.filter((asset) => {
            const state = (asset.state_name || '').toLowerCase();

            // If showAllStatuses is true, we show almost everything (except broken/archived usually)
            if (props.showAllStatuses) {
                // Still exclude obviously broken/archived unless explicitly asked
                if (state.includes('broken') || state.includes('archived'))
                    return false;
                return true;
            }

            // Default behavior (STB Picker): Only show stock/ready-to-deploy assets.
            // Keep "Ready to Deploy" valid even though the string contains "deployed".
            const isAvailable =
                state === 'stock' ||
                state.includes('ready to deploy') ||
                state.includes('available') ||
                state.includes('deployable');

            // Exclude truly unusable states, but do not reject stock-like states.
            if (
                state.includes('broken') ||
                state.includes('archived') ||
                (state.includes('deployed') &&
                    !state.includes('ready to deploy')) ||
                state.includes('assigned')
            ) {
                return false;
            }

            return isAvailable;
        });
    }

    // Filter out items with zero stock for other categories
    if (
        ['license', 'accessories', 'consumable', 'component'].includes(
            selectedCategory.value,
        )
    ) {
        items = items.filter((asset) => {
            const rem =
                asset.remaining ?? Number(asset.stock) - Number(asset.used);
            return rem > 0;
        });
    }

    if (!q) return items;

    return items.filter(
        (asset) =>
            asset.name.toLowerCase().includes(q) ||
            (asset.serial || '').toLowerCase().includes(q) ||
            (asset.otherserial || '').toLowerCase().includes(q),
    );
});

const close = () => emit('update:open', false);

// Returns true when a quantity-based asset has no stock left
const isOutOfStock = (asset: SnipeAsset): boolean => {
    const stockCats: string[] = ['accessories', 'consumable', 'component'];
    if (!stockCats.includes(selectedCategory.value)) return false;
    const rem = asset.remaining ?? Number(asset.stock) - Number(asset.used);
    return rem <= 0;
};

const selectAsset = (asset: SnipeAsset) => {
    if (isOutOfStock(asset)) return;
    emit('select', asset);
    close();
};

// Dynamic Column Headers based on category
const currentHeaders = computed(() => {
    switch (selectedCategory.value) {
        case 'assets':
            return [
                { label: 'Nama Barang', width: '25%' },
                { label: 'Model', width: '15%' },
                { label: 'Kategori', width: '10%' },
                { label: 'No. Seri', width: '15%' },
                { label: 'Aset', width: '15%' },
                { label: 'Status', width: '10%' },
            ];
        case 'license':
            return [
                { label: 'Nama Barang', width: '35%' },
                { label: 'Produsen', width: '15%' },
                { label: 'Kunci Produk', width: '25%' },
                { label: 'Sisa Lisensi', width: '10%' },
                { label: 'Status', width: '5%', align: 'center' },
            ];
        case 'accessories':
        case 'consumable':
            return [
                { label: 'Nama Barang', width: '40%' },
                { label: 'Merek/Tipe', width: '20%' },
                { label: 'Terpakai', width: '12%', align: 'right' },
                { label: 'Stok', width: '12%', align: 'right' },
                { label: 'Status', width: '10%', align: 'center' },
            ];
        case 'component':
            return [
                { label: 'Nama Barang', width: '30%' },
                { label: 'Kategori', width: '18%' },
                { label: 'Serial', width: '14%' },
                { label: 'Terpakai', width: '10%', align: 'right' },
                { label: 'Stok', width: '10%', align: 'right' },
                { label: 'Status', width: '10%', align: 'center' },
            ];
        default:
            return [];
    }
});
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent
            class="app-modal-surface flex h-[85vh] !w-[95vw] !max-w-7xl flex-col gap-0 overflow-hidden rounded-[24px] border border-border bg-card p-0 shadow-2xl"
            :show-close-button="false"
        >
            <!-- Header Section -->
            <DialogHeader class="border-b border-border/40 bg-card px-8 py-5">
                <div
                    class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="space-y-0.5">
                        <DialogTitle
                            class="app-page-title !text-xl font-black tracking-tight text-foreground uppercase"
                        >
                            {{ dialogTitle }}
                        </DialogTitle>
                        <p
                            class="text-[11px] leading-relaxed text-muted-foreground"
                        >
                            {{ dialogCopy }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <div
                            class="app-dialog-chip flex items-center gap-2 rounded-lg border border-primary/10 !bg-primary/5 px-3 py-1 font-mono text-[9px] !text-primary"
                        >
                            <span class="font-black">{{
                                filteredAssets.length
                            }}</span>
                            <span
                                class="text-[8px] tracking-widest uppercase opacity-60"
                                >{{ resultsLabel || 'Tersedia' }}</span
                            >
                        </div>
                    </div>
                </div>
            </DialogHeader>

            <!-- Aligned Toolbar -->
            <div class="border-b border-border/30 bg-card px-8 py-4">
                <div
                    class="flex w-full flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
                >
                    <div
                        class="flex flex-wrap items-center gap-1 rounded-xl border border-border/30 bg-background/60 p-1 shadow-sm"
                    >
                        <button
                            v-for="cat in visibleCategories"
                            :key="cat.value"
                            type="button"
                            class="relative flex h-[38px] items-center rounded-lg px-4 text-[10px] font-black tracking-wider uppercase transition-all duration-200"
                            :class="
                                selectedCategory === cat.value
                                    ? 'bg-primary text-primary-foreground shadow-md'
                                    : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground'
                            "
                            @click="selectCategory(cat.value)"
                        >
                            {{ cat.label }}
                        </button>
                    </div>

                    <div class="w-full lg:w-96">
                        <div class="group relative flex items-center gap-2">
                            <div class="relative flex-1">
                                <Search
                                    class="absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-muted-foreground/50 transition-colors group-focus-within:text-primary"
                                />
                                <input
                                    v-model="searchQuery"
                                    type="text"
                                    placeholder="Cari nama, serial, inventory..."
                                    class="app-input-shell !h-[42px] w-full !rounded-xl border border-border/40 bg-background/40 pr-4 pl-10 !text-xs font-bold transition-all outline-none focus:bg-background focus:ring-4 focus:ring-primary/5"
                                />
                            </div>
                            <button
                                type="button"
                                :disabled="isLoading"
                                class="flex size-[42px] shrink-0 items-center justify-center rounded-xl border border-border/40 bg-background/40 text-muted-foreground transition-all hover:bg-muted/50 hover:text-primary active:scale-95 disabled:opacity-40"
                                :title="`Refresh data ${selectedCategory}`"
                                @click="
                                    emit(
                                        'load-category',
                                        selectedCategory,
                                        true,
                                    )
                                "
                            >
                                <RefreshCw
                                    class="h-4 w-4"
                                    :class="{ 'animate-spin': isLoading }"
                                />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- List Content Section (Dynamic Table) -->
            <div
                class="custom-scrollbar min-h-0 flex-1 overflow-x-auto bg-background"
            >
                <table class="w-full min-w-[900px] border-collapse text-left">
                    <thead
                        class="sticky top-0 z-10 border-b border-border/40 bg-background shadow-sm"
                    >
                        <tr>
                            <th
                                v-for="header in currentHeaders"
                                :key="header.label"
                                :style="{ width: header.width }"
                                class="px-6 py-4 text-[10px] font-black tracking-[0.15em] text-muted-foreground/60 uppercase first:px-8"
                                :class="{
                                    'text-center': header.align === 'center',
                                    'text-right': header.align === 'right',
                                }"
                            >
                                {{ header.label }}
                            </th>
                            <th
                                class="w-[10%] px-8 py-4 text-right text-[10px] font-black tracking-[0.15em] text-muted-foreground/60 uppercase"
                            >
                                Aksi
                            </th>
                        </tr>
                    </thead>
                    <tbody v-if="isLoading">
                        <tr>
                            <td
                                :colspan="currentHeaders.length + 1"
                                class="py-20 text-center"
                            >
                                <p
                                    class="animate-pulse text-[11px] font-bold tracking-[0.2em] text-muted-foreground uppercase"
                                >
                                    Memuat Database...
                                </p>
                            </td>
                        </tr>
                    </tbody>
                    <tbody v-else-if="filteredAssets.length === 0">
                        <tr>
                            <td
                                :colspan="currentHeaders.length + 1"
                                class="py-20 text-center"
                            >
                                <p
                                    class="text-[11px] font-bold tracking-widest text-muted-foreground uppercase"
                                >
                                    Data Tidak Ditemukan
                                </p>
                            </td>
                        </tr>
                    </tbody>
                    <tbody v-else class="divide-y divide-border/20">
                        <tr
                            v-for="asset in filteredAssets"
                            :key="asset.id"
                            class="group transition-colors"
                            :class="
                                isOutOfStock(asset)
                                    ? 'cursor-not-allowed opacity-40'
                                    : 'cursor-pointer hover:bg-primary/[0.02]'
                            "
                            @click="selectAsset(asset)"
                        >
                            <td class="px-8 py-4">
                                <p
                                    class="line-clamp-1 text-xs font-bold text-foreground transition-colors group-hover:text-primary"
                                >
                                    {{ asset.name }}
                                </p>
                                <p
                                    v-if="asset.group_name"
                                    class="mt-0.5 text-[9px] font-medium tracking-wide text-muted-foreground/60 uppercase"
                                >
                                    {{ asset.group_name }}
                                </p>
                            </td>

                            <!-- Middle Columns based on category -->
                            <template v-if="selectedCategory === 'assets'">
                                <td class="px-6 py-4">
                                    <span
                                        class="rounded-lg bg-primary/5 px-2 py-1 text-[9px] font-black tracking-widest text-primary uppercase"
                                    >
                                        {{ asset.asset_type_label || '-' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span
                                        class="rounded-lg bg-muted/40 px-2 py-1 text-[8px] font-black tracking-widest text-muted-foreground uppercase"
                                    >
                                        {{ asset.type_name || '-' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <p
                                        class="font-mono text-[10px] font-bold text-foreground/80 uppercase"
                                    >
                                        {{ asset.serial || '-' }}
                                    </p>
                                </td>
                                <td class="px-6 py-4">
                                    <p
                                        class="text-[10px] font-bold text-primary italic"
                                    >
                                        {{ getReferenceValue(asset) || '-' }}
                                    </p>
                                </td>
                                <td class="px-6 py-4">
                                    <span
                                        class="rounded-lg border px-2 py-0.5 text-[8px] font-black tracking-widest uppercase"
                                        :class="[
                                            (asset.state_name || '')
                                                .toLowerCase()
                                                .includes('stock') ||
                                            (asset.state_name || '')
                                                .toLowerCase()
                                                .includes('ready')
                                                ? 'border-emerald-100 bg-emerald-50 text-emerald-600'
                                                : 'border-amber-100 bg-amber-50 text-amber-600',
                                        ]"
                                    >
                                        {{ asset.state_name || '-' }}
                                    </span>
                                </td>
                            </template>

                            <template
                                v-else-if="selectedCategory === 'license'"
                            >
                                <td class="px-6 py-4">
                                    <p
                                        class="text-xs font-medium text-foreground/70"
                                    >
                                        {{ asset.group_name || '-' }}
                                    </p>
                                </td>
                                <td class="px-6 py-4">
                                    <p
                                        class="font-mono text-[9px] font-bold break-all text-foreground/80"
                                    >
                                        {{ asset.otherserial || '-' }}
                                    </p>
                                </td>
                                <td class="px-6 py-4">
                                    <p
                                        class="text-[11px] font-black text-primary"
                                    >
                                        {{ asset.remaining ?? 0 }}
                                        <span
                                            class="text-[8px] font-bold text-muted-foreground uppercase"
                                            >Seats</span
                                        >
                                    </p>
                                </td>
                            </template>

                            <template
                                v-else-if="
                                    selectedCategory === 'accessories' ||
                                    selectedCategory === 'consumable'
                                "
                            >
                                <td class="px-6 py-4">
                                    <p
                                        class="text-xs font-medium text-foreground/70"
                                    >
                                        {{ asset.type_name || '-' }}
                                    </p>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <p
                                        class="text-[11px] font-bold text-muted-foreground/60"
                                    >
                                        {{ asset.used }}
                                    </p>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <p
                                        class="text-[11px] font-black"
                                        :class="
                                            (asset.remaining ?? 0) > 0
                                                ? 'text-emerald-600'
                                                : 'text-red-500'
                                        "
                                    >
                                        {{ asset.remaining ?? 0 }}
                                    </p>
                                </td>
                            </template>

                            <template
                                v-else-if="selectedCategory === 'component'"
                            >
                                <td class="px-6 py-4">
                                    <span
                                        class="rounded-lg bg-muted/40 px-2 py-1 text-[8px] font-black tracking-widest text-muted-foreground uppercase"
                                    >
                                        {{ asset.type_name || '-' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <p
                                        class="font-mono text-[10px] font-bold text-foreground/80 uppercase"
                                    >
                                        {{ asset.serial || '-' }}
                                    </p>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <p
                                        class="text-[11px] font-bold text-muted-foreground/60"
                                    >
                                        {{ asset.used }}
                                    </p>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <p
                                        class="text-[11px] font-black"
                                        :class="
                                            (asset.remaining ?? 0) > 0
                                                ? 'text-emerald-600'
                                                : 'text-red-500'
                                        "
                                    >
                                        {{ asset.remaining ?? 0 }}
                                    </p>
                                </td>
                            </template>
                            <td class="px-6 py-4 text-center">
                                <span
                                    class="inline-flex items-center rounded-lg border border-border/30 bg-muted/20 px-2.5 py-1 text-[9px] font-black text-foreground/60 shadow-sm transition-all group-hover:text-primary"
                                >
                                    {{
                                        asset.state_name ||
                                        asset.asset_type_label
                                    }}
                                </span>
                            </td>

                            <td class="px-8 py-4 text-right">
                                <template v-if="isOutOfStock(asset)">
                                    <span
                                        class="inline-flex h-8 items-center rounded-lg border border-rose-500/20 bg-rose-500/10 px-3 text-[9px] font-black tracking-widest text-rose-400 uppercase"
                                    >
                                        Habis
                                    </span>
                                </template>
                                <template v-else>
                                    <button
                                        type="button"
                                        class="h-8 -translate-x-2 rounded-lg bg-primary px-4 text-[9px] font-black tracking-widest text-white uppercase opacity-0 shadow-md shadow-primary/10 transition-all group-hover:translate-x-0 group-hover:opacity-100"
                                    >
                                        Pilih
                                    </button>
                                </template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Footer Section -->
            <div
                class="flex items-center justify-between border-t border-border/30 bg-card px-8 py-4 backdrop-blur-md"
            >
                <p
                    class="font-mono text-[10px] text-muted-foreground/60 italic"
                >
                    -
                </p>
            </div>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.08);
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.15);
}
</style>
