<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { PrinterIcon } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { usePrintPreview } from '@/composables/usePrintPreview';
import StbPrintableDocument from '@/pages/Stb/Partials/StbPrintableDocument.vue';
import type { StbItem } from '@/pages/Stb/types';
import { formatStbDocId, resolveStbDocumentLabel } from '@/utils/stb';
import {
    ensureStbAssetsLoaded,
    ensureStbDirectoryLoaded,
    getStbAssetLabel,
    getStbDeptHeadLabel,
    getStbGroupParts,
    getStbUserEmail,
    getStbUserLabel,
    getStbUserPhone,
    getStbUserPosition,
} from '@/utils/stbDirectory';

interface STB {
    id: number;
    deliver_date: string | null;
    status: number | null;
    document_type?: string | null;
    movement_type?: string | null;
    returned_at?: string | null;
    it_drafter_id: number | null;
    it_checker_id: number | null;
    it_approved_id: number | null;
    req_doc_no: string | null;
    po_doc_no: string | null;
    user_id: number | null;
    group_id: number | null;
    building: string | null;
    use_date: string | null;
    batch_no: string | null;
    photo: string | null;
    remark: string | null;
    it_drafter_signature_path: string | null;
    it_drafter_signed_at: string | null;
    it_checker_signature_path: string | null;
    it_checker_signed_at: string | null;
    it_approved_signature_path: string | null;
    it_approved_signed_at: string | null;
    requester_received_signature_path: string | null;
    requester_received_signed_at: string | null;
    requester_dept_head_signature_path: string | null;
    requester_dept_head_signed_at: string | null;
    created_at: string;
    updated_at: string;
    items: StbItem[];
}

interface Props {
    stb: STB;
    shareUrl: string;
}

const props = defineProps<Props>();
const { stb, shareUrl } = props;
const printRoot = ref<HTMLElement | null>(null);

const formatDate = (date?: string | null) => {
    if (!date) {
        return '-';
    }

    return new Date(date).toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};

const groupParts = computed(() => getStbGroupParts(stb.group_id, stb.user_id));
const userName = computed(() => getStbUserLabel(stb.user_id));
const phoneNumber = computed(() => getStbUserPhone(stb.user_id));
const email = computed(() => getStbUserEmail(stb.user_id));
const position = computed(() => getStbUserPosition(stb.user_id));
const deptHead = computed(() => getStbDeptHeadLabel(stb.user_id));
const itDrafterName = computed(() => getStbUserLabel(stb.it_drafter_id));
const itCheckerName = computed(() => getStbUserLabel(stb.it_checker_id));
const itApprovedName = computed(() => getStbUserLabel(stb.it_approved_id));
const docId = computed(
    () =>
        formatStbDocId({
            id: stb.id,
            locationName: groupParts.value.location,
            date: stb.created_at,
        }) || stb.id.toString(),
);
const statusLabel = computed(() => resolveStbDocumentLabel(stb));
const openSignatureFallback = (_role: string) => {
    void _role;
};

const formatDateTime = (date?: string | null) => {
    if (!date) {
        return '';
    }

    return new Date(date).toLocaleString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const handlePrint = () => {
    window.print();
};

usePrintPreview(printRoot, async () => {
    await ensureStbDirectoryLoaded();
    await Promise.all(
        Array.from(
            new Set(stb.items.map((item) => item.kategori).filter(Boolean)),
        ).map((category) => ensureStbAssetsLoaded(category)),
    );
});
</script>

<template>
    <Head :title="`Cetak ${docId}`" />

    <div ref="printRoot" class="print-stage">
        <div
            class="document-print-surface mx-auto bg-white p-14 shadow-sm print:p-0 print:shadow-none"
        >
            <StbPrintableDocument
                :stb="stb"
                :doc-id="docId"
                :group-parts="groupParts"
                :user-name="userName"
                :phone-number="phoneNumber"
                :email="email"
                :position="position"
                :dept-head="deptHead"
                :it-drafter-name="itDrafterName"
                :it-checker-name="itCheckerName"
                :it-approved-name="itApprovedName"
                :status-label="statusLabel"
                :is-completed="true"
                :is-cancelled="false"
                :shared-mode="true"
                :format-date="formatDate"
                :format-date-time="formatDateTime"
                :get-asset-label="getStbAssetLabel"
                :open-signature-modal="openSignatureFallback"
                :open-clear-confirm="openSignatureFallback"
                :share-url="shareUrl"
            >
                <template #header>
                    <div class="print:hidden">
                        <div class="flex w-full items-center justify-between">
                            <div class="flex items-center gap-3">
                                <h2
                                    class="app-section-title text-xl tracking-tight"
                                >
                                    Detail STB
                                </h2>
                                <span
                                    class="inline-flex items-center rounded-full bg-green-50 px-2 py-0.5 text-[10px] font-bold tracking-wider text-green-700 uppercase"
                                >
                                    {{ statusLabel }}
                                </span>
                            </div>
                            <button
                                type="button"
                                class="flex h-10 items-center gap-2 rounded-xl bg-[#003628] px-5 text-xs font-black tracking-widest text-white uppercase shadow-lg shadow-primary/20 transition-all hover:brightness-110 active:scale-95"
                                @click="handlePrint"
                            >
                                <PrinterIcon class="size-4" />
                                <span>Print Document</span>
                            </button>
                        </div>
                        <p
                            class="mt-1 flex items-center gap-2 text-sm font-medium text-muted-foreground"
                        >
                            <span>{{ docId }}</span>
                            <span class="text-border">•</span>
                            <span>{{ stb.items.length }} Items</span>
                        </p>
                    </div>
                </template>
            </StbPrintableDocument>
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

.document-print-surface {
    width: 100%;
    max-width: 210mm;
    min-height: 297mm;
}

@page {
    size: A4;
    margin: 10mm;
}

@media print {
    :global(body) {
        margin: 0;
        background: #fff;
    }

    .print-stage {
        min-height: auto;
        padding: 0;
        background: transparent;
    }

    .document-print-surface {
        width: 210mm;
        max-width: none;
        min-height: 297mm;
        margin: 0;
    }
}
</style>
