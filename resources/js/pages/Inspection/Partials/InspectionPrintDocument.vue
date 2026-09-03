<script setup lang="ts">
import SignatureRenderer from '@/Components/SignatureRenderer.vue';

interface Inspection {
    id: number;
    report_id: string;
    location: string;
    user: string;
    email: string | null;
    leader: string | null;
    company: string;
    department: string;
    report_type: string;
    date: string;
    device_category: string;
    device_name: string;
    asset_snapshot: string | null;
    checked_by: string;
    approve_by: string | null;
    checked_date: string;
    issue_description: string;
    solution: string;
    remarks: string | null;
    photo: string | null;
    it_signature: string | null;
    user_signature: string | null;
    leader_signature: string | null;
    signature_date: string | null;
}

const props = defineProps<{
    inspection: Inspection;
    formatDate: (d?: string | null) => string;
}>();

// Device category mapping for the asset grid
const category = props.inspection.device_category;
</script>

<template>
    <div class="document-preview shared-print doc-canvas">
        
        <!-- ── HEADER ── -->
        <table class="shared-header-table">
            <tbody>
                <tr>
                    <td class="shared-logo-cell">
                        <img src="/form-logo.png" class="shared-logo" alt="Zinus" />
                    </td>
                    <td class="shared-title-cell">
                        <div class="shared-title-main">INSPECTION REPORT</div>
                        <div class="shared-title-sub">PT. {{ inspection.company || 'ZINUS GLOBAL INDONESIA' }}</div>
                    </td>
                    <td class="shared-meta-cell">
                        <div class="font-semibold">IT Dept.</div>
                        <div class="text-[9px]">Doc. No. IT/INSP/{{ new Date(inspection.date).getFullYear().toString().slice(-2) }}/{{ String(new Date(inspection.date).getMonth() + 1).padStart(2, '0') }}</div>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- ── ASSET CATEGORIES GRID ── -->
        <table class="shared-info-table mt-4">
            <tbody>
                <tr>
                    <td class="shared-signature-head w-1/3">PC</td>
                    <td class="shared-signature-head w-1/3">Laptop</td>
                    <td class="shared-signature-head w-1/3">Printer</td>
                </tr>
                <tr class="h-8">
                    <td>{{ category === 'pc' ? inspection.device_name : '' }}</td>
                    <td>{{ category === 'laptop' ? inspection.device_name : '' }}</td>
                    <td>{{ category === 'printer' ? inspection.device_name : '' }}</td>
                </tr>
                <tr>
                    <td class="shared-signature-head">Monitor</td>
                    <td class="shared-signature-head">Other</td>
                    <td class="shared-signature-head">Network Device</td>
                </tr>
                <tr class="h-8">
                    <td>{{ category === 'monitor' ? inspection.device_name : '' }}</td>
                    <td>{{ category === 'other' ? inspection.device_name : '' }}</td>
                    <td>{{ category === 'network' ? inspection.device_name : '' }}</td>
                </tr>
            </tbody>
        </table>

        <!-- ── CASE INFO TABLE ── -->
        <table class="shared-info-table mt-4">
            <tbody>
                <tr>
                    <td class="shared-label w-[18%]">Case ID</td>
                    <td class="w-[32%]">{{ inspection.report_id }}</td>
                    <td class="shared-label w-[18%]">Location</td>
                    <td class="w-[32%]">{{ inspection.location }}</td>
                </tr>
                <tr>
                    <td class="shared-label">Departemen</td>
                    <td>{{ inspection.department }}</td>
                    <td class="shared-label">User</td>
                    <td>{{ inspection.user }}</td>
                </tr>
                <tr>
                    <td class="shared-label">Email</td>
                    <td>{{ inspection.email || '-' }}</td>
                    <td class="shared-label">Checked By</td>
                    <td>{{ inspection.checked_by }}</td>
                </tr>
                <tr>
                    <td class="shared-label">Checked Date</td>
                    <td>{{ formatDate(inspection.checked_date) }}</td>
                    <td class="shared-label">Report Date</td>
                    <td>{{ formatDate(inspection.date) }}</td>
                </tr>
            </tbody>
        </table>

        <div class="shared-recipient-note">
            <span>Detail Temuan dan Dokumentasi:</span>
            <span>ID Dokumen: {{ inspection.report_id }}</span>
        </div>

        <!-- ── ISSUE & PHOTO ── -->
        <table class="shared-info-table mt-1">
            <tbody>
                <tr>
                    <td class="shared-signature-head w-1/2">Problem / Issue</td>
                    <td class="shared-signature-head w-1/2">Photo Documentation</td>
                </tr>
                <tr>
                    <td class="align-top p-3 min-h-[120px] whitespace-pre-wrap leading-relaxed">
                        {{ inspection.issue_description }}
                    </td>
                    <td class="text-center align-middle p-2 bg-[#f8faf9]">
                        <img v-if="inspection.photo" :src="`/storage/${inspection.photo}`"
                            class="max-w-full max-h-[160px] mx-auto object-contain" alt="Inspection Photo" />
                        <span v-else class="text-slate-400 text-[10px] font-medium italic">No photo attached</span>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- ── SOLUTION & REMARKS ── -->
        <div class="shared-agreement-box !p-0 mt-4">
            <div class="border-b border-[#d1d8d4] bg-[#f8faf9] px-3 py-1.5 font-bold text-[#111827]">
                Solution / Resolution
            </div>
            <div class="px-3 py-2.5 whitespace-pre-wrap leading-relaxed min-h-[50px]">
                {{ inspection.solution }}
            </div>
        </div>

        <div class="shared-agreement-box !p-0 mt-2">
            <div class="border-b border-[#d1d8d4] bg-[#f8faf9] px-3 py-1.5 font-bold text-[#111827]">
                Note / Remarks
            </div>
            <div class="px-3 py-2.5 whitespace-pre-wrap leading-relaxed">
                {{ inspection.remarks || '-' }}
            </div>
        </div>

        <!-- ── SIGNATURES ── -->
        <div class="shared-signature-grid mt-auto pt-6 shrink-0" style="grid-template-columns: repeat(4, 1fr) !important">
            <table class="shared-signature-table">
                <tbody>
                    <tr><td class="shared-signature-head">IT</td></tr>
                    <tr>
                        <td class="shared-signature-body">
                            <div class="shared-signature-stack">
                                <div class="shared-signature-image-box">
                                    <SignatureRenderer :data="inspection.it_signature" class="h-full w-full" />
                                </div>
                                <div class="shared-signature-name mt-auto border-t border-[#d1d8d4] pt-1">
                                    {{ inspection.checked_by }}
                                </div>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            <table class="shared-signature-table">
                <tbody>
                    <tr><td class="shared-signature-head">Checked</td></tr>
                    <tr>
                        <td class="shared-signature-body">
                            <div class="shared-signature-stack">
                                <div class="shared-signature-image-box"></div>
                                <div class="shared-signature-name mt-auto border-t border-[#d1d8d4] pt-1">
                                    {{ inspection.approve_by || '________________' }}
                                </div>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            <table class="shared-signature-table">
                <tbody>
                    <tr><td class="shared-signature-head">User</td></tr>
                    <tr>
                        <td class="shared-signature-body">
                            <div class="shared-signature-stack">
                                <div class="shared-signature-image-box">
                                    <SignatureRenderer :data="inspection.user_signature" class="h-full w-full" />
                                </div>
                                <div class="shared-signature-name mt-auto border-t border-[#d1d8d4] pt-1">
                                    {{ inspection.user }}
                                </div>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            <table class="shared-signature-table">
                <tbody>
                    <tr><td class="shared-signature-head">Leader / Head Dept.</td></tr>
                    <tr>
                        <td class="shared-signature-body">
                            <div class="shared-signature-stack">
                                <div class="shared-signature-image-box">
                                    <SignatureRenderer :data="inspection.leader_signature" class="h-full w-full" />
                                </div>
                                <div class="shared-signature-name mt-auto border-t border-[#d1d8d4] pt-1">
                                    {{ inspection.leader || '-' }}
                                </div>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

    </div>
</template>

<style scoped>
/* Scoped styles kept minimal, relying on global shared-* classes */
</style>
