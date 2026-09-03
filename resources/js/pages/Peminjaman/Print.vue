<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import { PrinterIcon } from "lucide-vue-next";
import { computed, ref } from "vue";
import { usePrintPreview } from "@/composables/usePrintPreview";
import PeminjamanPrintableDocument from "@/pages/Peminjaman/Partials/PeminjamanPrintableDocument.vue";
import type { PeminjamanItem } from "@/pages/Peminjaman/types";
import {
    formatPeminjamanDocId,
    isPeminjamanLoanOut,
    isPeminjamanReturnDocument,
    resolvePeminjamanDocumentLabel,
    resolvePeminjamanRequesterRoleLabels,
} from "@/pages/Peminjaman/utils/peminjaman";

interface PeminjamanPrintRecord {
    id: number;
    status: number | null;
    document_type?: string | null;
    movement_type?: string | null;
    returned_at?: string | null;
    it_drafter_id: number | null;
    user_id: number | null;
    group_id: number | null;
    use_date: string | null;
    expected_return_date?: string | null;
    photo: string | null;
    return_photo_path?: string | null;
    remark: string | null;
    it_drafter_signature_path: string | null;
    it_drafter_signed_at: string | null;
    requester_received_signature_path: string | null;
    requester_received_signed_at: string | null;
    created_at: string;
    updated_at: string;
    items: PeminjamanItem[];
    attachments?: Array<{ id: number; file_path: string }>;
    // Stored fields from DB
    user_name?: string | null;
    user_company?: string | null;
    user_dept?: string | null;
    user_title?: string | null;
    user_phone?: string | null;
    user_email?: string | null;
    location_name?: string | null;
    po_doc_no?: string | null;
}

interface Props {
    peminjaman: PeminjamanPrintRecord;
    shareUrl: string;
}

const props = defineProps<Props>();
const { peminjaman } = props;
const printRoot = ref<HTMLElement | null>(null);

const formatDate = (date?: string | null) => {
    if (!date) return "-";
    return new Date(date).toLocaleDateString("id-ID", {
        year: "numeric",
        month: "long",
        day: "numeric",
    });
};

const formatDateTime = (date?: string | null) => {
    if (!date) return "";
    return new Date(date).toLocaleString("id-ID", {
        year: "numeric",
        month: "long",
        day: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    });
};

// Use stored data from DB — no Snipe-IT directory needed
const userName    = computed(() => peminjaman.user_name    || "-");
const phoneNumber = computed(() => peminjaman.user_phone   || "-");
const email       = computed(() => peminjaman.user_email   || "-");
const position    = computed(() => peminjaman.user_title   || "-");
const deptHead    = computed(() => "-");
const itDrafterName = computed(() => "-");

const groupParts = computed(() => ({
    location:   peminjaman.location_name  || "-",
    company:    peminjaman.user_company   || "-",
    department: peminjaman.user_dept      || "-",
}));

const docId = computed(
    () => formatPeminjamanDocId({
        id: peminjaman.id,
        locationName: peminjaman.location_name,
        date: peminjaman.created_at,
    }) || peminjaman.id.toString()
);

const statusLabel = computed(() => resolvePeminjamanDocumentLabel(peminjaman));
const requesterRoleLabels = computed(() => resolvePeminjamanRequesterRoleLabels(peminjaman));

const getAssetLabel = (item: PeminjamanItem) =>
    (item as any).inventory_number || (item as any).serial_no || "-";

const openSignatureFallback = (_role: string) => { void _role; };

const handlePrint = () => { window.print(); };

usePrintPreview(printRoot, async () => {});
</script>

<template>
    <Head :title="`Cetak ${docId}`" />

    <div ref="printRoot" class="print-stage">
        <div class="mx-auto max-w-[210mm] bg-white p-14 shadow-sm print:p-0 print:shadow-none">
            <PeminjamanPrintableDocument
                :peminjaman="peminjaman"
                :doc-id="docId"
                :group-parts="groupParts"
                :user-name="userName"
                :phone-number="phoneNumber"
                :email="email"
                :position="position"
                :dept-head="deptHead"
                :it-drafter-name="itDrafterName"
                :status-label="statusLabel"
                :is-completed="true"
                :is-cancelled="false"
                :shared-mode="true"
                :format-date="formatDate"
                :format-date-time="formatDateTime"
                :get-asset-label="getAssetLabel"
                :open-signature-modal="openSignatureFallback"
                :open-clear-confirm="openSignatureFallback"
            >
                <template #header>
                    <div class="print:hidden">
                        <div class="flex items-center justify-between w-full">
                            <div class="flex items-center gap-3">
                                <h2 class="app-section-title text-xl tracking-tight">
                                    {{ isPeminjamanLoanOut(peminjaman) ? "Form Peminjaman" : "Form Pengembalian" }}
                                </h2>
                                <span class="inline-flex items-center rounded-full bg-green-50 px-2 py-0.5 text-[10px] font-bold tracking-wider text-green-700 uppercase">
                                    {{ statusLabel }}
                                </span>
                            </div>
                            <button
                                type="button"
                                class="h-10 px-5 rounded-xl bg-[#003628] text-white flex items-center gap-2 text-xs font-black uppercase tracking-widest shadow-lg shadow-primary/20 hover:brightness-110 transition-all active:scale-95"
                                @click="handlePrint"
                            >
                                <PrinterIcon class="size-4" />
                                <span>Print Document</span>
                            </button>
                        </div>
                        <p class="mt-1 flex items-center gap-2 text-sm font-medium text-muted-foreground">
                            <span>{{ docId }}</span>
                            <span class="text-border">•</span>
                            <span>{{ peminjaman.items.length }} Items</span>
                        </p>
                    </div>
                </template>
            </PeminjamanPrintableDocument>
        </div>
    </div>
</template>

<style scoped>
* {
    box-sizing: border-box;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}

.print-stage {
    min-height: 100vh;
    padding: 20px 15px;
    background: #f4f6f5;
}

@media print {
    .print-stage {
        min-height: auto;
        padding: 0;
        background: transparent;
    }
}
</style>
