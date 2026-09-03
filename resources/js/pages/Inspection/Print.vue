<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import { PrinterIcon } from "lucide-vue-next";
import { ref } from "vue";
import { usePrintPreview } from "@/composables/usePrintPreview";
import InspectionDocument from "@/pages/Inspection/Partials/InspectionDocument.vue";

interface Props {
    inspection: any;
    shareUrl?: string;
}

const props = defineProps<Props>();
const printRoot = ref<HTMLElement | null>(null);
const noop = (_role: string) => {};

usePrintPreview(printRoot, async () => {});
</script>

<template>
    <Head :title="`Cetak: ${props.inspection.report_id}`" />

    <div ref="printRoot" class="print-stage">
        <div class="mx-auto max-w-[210mm] bg-white shadow-sm print:shadow-none">

            <!-- Toolbar (hidden on print) -->
            <div class="print:hidden flex items-center justify-between px-6 py-4 border-b border-slate-200 bg-white sticky top-0 z-10">
                <div>
                    <h2 class="text-lg font-black text-slate-900">{{ props.inspection.report_id }}</h2>
                    <p class="text-[11px] text-slate-400 font-bold uppercase tracking-widest">Inspection Report</p>
                </div>
                <button
                    type="button"
                    class="h-10 px-5 rounded-xl bg-[#003628] text-white flex items-center gap-2 text-xs font-black uppercase tracking-widest shadow-lg hover:brightness-110 transition-all active:scale-95"
                    @click="() => window.print()"
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
* { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
.print-stage { min-height: 100vh; background: #f4f6f5; padding: 20px 15px; }
@media print {
    .print-stage { min-height: auto; padding: 0; background: transparent; }
}
</style>
