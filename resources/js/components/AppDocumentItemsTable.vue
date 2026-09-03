<script setup lang="ts">
import { computed } from 'vue';

interface DocumentItem {
    id: number;
    nama: string;
    type: string;
    jumlah: number;
    serial_no: string;
}

const props = withDefaults(
    defineProps<{
        items: DocumentItem[];
        totalQty: number;
        getAssetLabel: (item: DocumentItem) => string;
        sectionKicker?: string;
        sectionTitle: string;
        sectionCopy?: string;
        previewLimit?: number;
    }>(),
    {
        sectionKicker: 'Item',
        sectionCopy:
            'Daftar item berikut merangkum aset utama yang tercatat pada dokumen ini.',
        previewLimit: 5,
    },
);

const displayedItems = computed(() => props.items.slice(0, props.previewLimit));
const fillerRowCount = computed(() =>
    Math.max(0, props.previewLimit - displayedItems.value.length),
);
const hiddenCount = computed(() =>
    Math.max(0, props.items.length - displayedItems.value.length),
);
</script>

<template>
    <section class="app-table-shell">
        <div
            class="app-table-header-surface flex flex-col gap-2.5 px-4 py-3 md:flex-row md:items-center md:justify-between"
        >
            <div>
                <p class="app-section-kicker">{{ sectionKicker }}</p>
                <h2 class="app-section-title">{{ sectionTitle }}</h2>
                <p class="app-note-text mt-1 max-w-2xl">{{ sectionCopy }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <div class="app-badge app-badge-positive">
                    {{ items.length }} item
                </div>
                <div class="app-badge app-badge-neutral">
                    Total {{ totalQty }} unit
                </div>
            </div>
        </div>

        <div class="p-4">
            <div
                class="overflow-x-auto rounded-[14px] border border-border bg-card shadow-sm"
            >
                <table class="app-table min-w-full">
                    <thead class="app-table-head-surface">
                        <tr>
                            <th class="app-table-head w-14">No</th>
                            <th class="app-table-head">Nama Barang</th>
                            <th class="app-table-head">Tipe</th>
                            <th class="app-table-head w-24">Jumlah</th>
                            <th class="app-table-head">No. Serial</th>
                            <th class="app-table-head">Referensi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(item, index) in displayedItems"
                            :key="item.id"
                            class="app-table-row"
                        >
                            <td class="app-table-cell text-center">
                                {{ index + 1 }}
                            </td>
                            <td class="app-table-cell">
                                <div class="flex flex-col gap-1">
                                    <span class="app-table-emphasis">{{
                                        item.nama
                                    }}</span>
                                    <span class="app-table-meta"
                                        >Asset utama pada dokumen</span
                                    >
                                </div>
                            </td>
                            <td class="app-table-cell">{{ item.type }}</td>
                            <td class="app-table-cell text-center">
                                {{ item.jumlah }}
                            </td>
                            <td class="app-table-cell">{{ item.serial_no }}</td>
                            <td class="app-table-cell">
                                {{ getAssetLabel(item) }}
                            </td>
                        </tr>
                        <tr
                            v-for="n in fillerRowCount"
                            :key="`empty-${n}`"
                            class="app-table-row"
                        >
                            <td class="app-table-cell text-center">&nbsp;</td>
                            <td class="app-table-cell"></td>
                            <td class="app-table-cell"></td>
                            <td class="app-table-cell"></td>
                            <td class="app-table-cell"></td>
                            <td class="app-table-cell"></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div
            class="app-table-note flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between"
        >
            <span>
                Tabel detail dipertahankan ringkas untuk pembacaan cepat oleh
                operator.
            </span>
            <span v-if="hiddenCount > 0">
                {{ hiddenCount }} item tambahan tetap tersimpan di dokumen
                lengkap.
            </span>
        </div>
    </section>
</template>
