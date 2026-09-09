<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { PrinterIcon } from 'lucide-vue-next';
import { ref } from 'vue';
import { usePrintPreview } from '@/composables/usePrintPreview';
import InspectionDocument from '@/pages/Inspection/Partials/InspectionDocument.vue';

interface Props {
    inspection: any;
    shareUrl?: string;
}

const props = defineProps<Props>();
const printRoot = ref<HTMLElement | null>(null);
const noop = (_role: string) => {};
const printDocument = () => window.print();

usePrintPreview(printRoot, async () => {});
</script>

<template>
    <Head :title="`Cetak: ${props.inspection.report_id}`" />

    <div ref="printRoot" class="print-stage">
        <div class="mx-auto max-w-[210mm] bg-white shadow-sm print:shadow-none">
            <!-- Toolbar (hidden on print) -->
            <div
                class="sticky top-0 z-10 flex items-center justify-between border-b border-slate-200 bg-white px-6 py-4 print:hidden"
            >
                <div>
                    <h2 class="text-lg font-black text-slate-900">
                        {{ props.inspection.report_id }}
                    </h2>
                    <p
                        class="text-[11px] font-bold tracking-widest text-slate-400 uppercase"
                    >
                        Inspection Report
                    </p>
                </div>
                <button
                    type="button"
                    class="flex h-10 items-center gap-2 rounded-xl bg-[#003628] px-5 text-xs font-black tracking-widest text-white uppercase shadow-lg transition-all hover:brightness-110 active:scale-95"
                    @click="printDocument"
                >
                    <PrinterIcon class="size-4" />
                    Print Document
                </button>
            </div>

            <!-- Document (read-only, no sign/clear buttons) -->
            <div class="p-8 print:p-0">
                <InspectionDocument
                    :inspection="props.inspection"
                    :is-completed="true"
                    :open-signature-modal="noop"
                    :open-clear-confirm="noop"
                />
            </div>
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
    background: #f4f6f5;
    padding: 20px 15px;
}
@media print {
    .print-stage {
        min-height: auto;
        padding: 0;
        background: transparent;
    }
}
</style>
