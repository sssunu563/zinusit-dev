<script setup lang="ts">
interface StockHistoryItem {
    id: number;
    qty: number;
    po_number: string;
    purchase_date: string | null;
    document_url: string | null;
    document_name: string | null;
    notes: string | null;
}

defineProps<{
    stockHistory: StockHistoryItem[];
}>();
</script>

<template>
    <div class="app-table-shell">
        <div class="app-table-header-surface px-4 py-3 md:px-5">
            <h2 class="app-section-title">Stock History</h2>
            <p class="app-upload-meta">
                Riwayat penambahan stock (PO, tanggal pembelian, dokumen).
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="app-table min-w-[720px]">
                <thead class="app-table-head-surface">
                    <tr>
                        <th class="app-table-head md:px-5">Tanggal</th>
                        <th class="app-table-head md:px-5">PO</th>
                        <th class="app-table-head text-right md:px-5">Qty</th>
                        <th class="app-table-head md:px-5">Dokumen</th>
                        <th class="app-table-head md:px-5">Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="item in stockHistory"
                        :key="item.id"
                        class="app-table-row"
                    >
                        <td class="app-table-cell md:px-5">
                            {{ item.purchase_date || '-' }}
                        </td>
                        <td class="app-table-cell md:px-5">
                            {{ item.po_number || '-' }}
                        </td>
                        <td class="app-table-cell text-right md:px-5">
                            {{ item.qty }}
                        </td>
                        <td class="app-table-cell md:px-5">
                            <a
                                v-if="item.document_url"
                                :href="item.document_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="app-table-link"
                            >
                                {{ item.document_name || 'Buka file' }}
                            </a>
                            <span v-else>-</span>
                        </td>
                        <td class="app-table-cell md:px-5">
                            {{ item.notes || '-' }}
                        </td>
                    </tr>
                    <tr v-if="!stockHistory.length">
                        <td colspan="5" class="app-table-empty-cell">
                            Belum ada history penambahan stock.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
