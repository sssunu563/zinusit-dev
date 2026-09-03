<script setup lang="ts">
/**
 * InspectionDocument — reusable document body used by Show, Print, and Share pages.
 * Renders the full inspection document with consistent layout and signature support.
 */
import { computed } from 'vue';
import SignatureRenderer from '@/components/SignatureRenderer.vue';

interface Props {
    inspection: any;
    isCompleted?: boolean;
    sharedMode?: boolean;
    openSignatureModal?: (role: string) => void;
    openClearConfirm?: (role: string) => void;
}

const props = withDefaults(defineProps<Props>(), {
    isCompleted: false,
    sharedMode: false,
    openSignatureModal: () => {},
    openClearConfirm: () => {},
});

const formatDate = (d?: string | null) => {
    if (!d) return '-';
    return new Date(d).toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};

const catLabels: Record<string, string> = {
    pc: 'PC',
    laptop: 'Laptop',
    printer: 'Printer',
    monitor: 'Monitor',
    other: 'Other',
    network: 'Network Device',
};

const catLabel = computed(
    () =>
        catLabels[props.inspection.device_category] ||
        props.inspection.device_category ||
        '-',
);

const signatures = computed(() => [
    {
        role: 'it',
        label: 'IT',
        name: props.inspection.it_staff || '-',
        data: props.inspection.it_signature,
    },
    {
        role: 'checked',
        label: 'Checked',
        name: props.inspection.checked_by || '-',
        data: props.inspection.checked_signature,
    },
    {
        role: 'user',
        label: 'User',
        name: props.inspection.user || '-',
        data: props.inspection.user_signature,
    },
    {
        role: 'leader',
        label: 'Leader / Head Dept.',
        name: props.inspection.dept_head || '-',
        data: props.inspection.leader_signature,
    },
]);

// Resolve signaturePath from raw data (same as createApprovalSignatureState)
const sigPath = (data: any) => (data ? data : null);
</script>

<template>
    <div class="insp-doc">
        <!-- 1. HEADER -->
        <table class="insp-header">
            <tbody>
                <tr>
                    <td class="h-logo">
                        <img src="/form-logo.png" alt="Zinus" />
                    </td>
                    <td class="h-title">
                        <div class="t-main">Inspection Report</div>
                        <div class="t-sub">PT. ZINUS GLOBAL INDONESIA</div>
                    </td>
                    <td class="h-meta">
                        <strong>IT Dept.</strong><br />
                        Doc. No. IT/INSP/II/25/01
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- 2. DEVICE INFO -->
        <table
            style="
                width: 100%;
                border-collapse: collapse;
                margin-top: 6px;
                table-layout: fixed;
            "
        >
            <thead>
                <tr>
                    <th
                        style="
                            background: #f1f5f9;
                            font-weight: 700;
                            font-size: 10px;
                            color: #1e293b;
                            padding: 6px 10px;
                            border: 1px solid #94a3b8;
                            text-align: left;
                            width: 33.33%;
                        "
                    >
                        Asset Tag
                    </th>
                    <th
                        style="
                            background: #f1f5f9;
                            font-weight: 700;
                            font-size: 10px;
                            color: #1e293b;
                            padding: 6px 10px;
                            border: 1px solid #94a3b8;
                            text-align: left;
                            width: 33.33%;
                        "
                    >
                        Kategori
                    </th>
                    <th
                        style="
                            background: #f1f5f9;
                            font-weight: 700;
                            font-size: 10px;
                            color: #1e293b;
                            padding: 6px 10px;
                            border: 1px solid #94a3b8;
                            text-align: left;
                            width: 33.33%;
                        "
                    >
                        Serial Number
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td
                        style="
                            font-size: 10.5px;
                            font-weight: 600;
                            padding: 6px 10px;
                            border: 1px solid #94a3b8;
                        "
                    >
                        {{ inspection.asset_tag || '-' }}
                    </td>
                    <td
                        style="
                            font-size: 10.5px;
                            font-weight: 600;
                            padding: 6px 10px;
                            border: 1px solid #94a3b8;
                        "
                    >
                        {{ catLabel }}
                    </td>
                    <td
                        style="
                            font-size: 10.5px;
                            font-weight: 600;
                            padding: 6px 10px;
                            border: 1px solid #94a3b8;
                        "
                    >
                        {{ inspection.serial_number || '-' }}
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- 3. INFO GRID -->
        <table
            style="
                width: 100%;
                border-collapse: collapse;
                margin-top: 6px;
                table-layout: fixed;
            "
        >
            <tbody>
                <tr>
                    <td
                        style="
                            background: #f1f5f9;
                            font-weight: 700;
                            color: #334155;
                            font-size: 9.5px;
                            padding: 5px 8px;
                            border: 1px solid #94a3b8;
                            width: 25%;
                        "
                    >
                        Case ID
                    </td>
                    <td
                        style="
                            background: #f1f5f9;
                            font-weight: 700;
                            color: #334155;
                            font-size: 9.5px;
                            padding: 5px 8px;
                            border: 1px solid #94a3b8;
                            width: 25%;
                        "
                    >
                        Location
                    </td>
                    <td
                        style="
                            background: #f1f5f9;
                            font-weight: 700;
                            color: #334155;
                            font-size: 9.5px;
                            padding: 5px 8px;
                            border: 1px solid #94a3b8;
                            width: 25%;
                        "
                    >
                        Checked By
                    </td>
                    <td
                        style="
                            background: #f1f5f9;
                            font-weight: 700;
                            color: #334155;
                            font-size: 9.5px;
                            padding: 5px 8px;
                            border: 1px solid #94a3b8;
                            width: 25%;
                        "
                    >
                        Checked Date
                    </td>
                </tr>
                <tr>
                    <td
                        style="
                            font-size: 10px;
                            padding: 5px 8px;
                            border: 1px solid #94a3b8;
                        "
                    >
                        {{ inspection.report_id }}
                    </td>
                    <td
                        style="
                            font-size: 10px;
                            padding: 5px 8px;
                            border: 1px solid #94a3b8;
                        "
                    >
                        {{ inspection.location || '-' }}
                    </td>
                    <td
                        style="
                            font-size: 10px;
                            padding: 5px 8px;
                            border: 1px solid #94a3b8;
                        "
                    >
                        {{ inspection.checked_by || '-' }}
                    </td>
                    <td
                        style="
                            font-size: 10px;
                            padding: 5px 8px;
                            border: 1px solid #94a3b8;
                            white-space: nowrap;
                        "
                    >
                        {{ formatDate(inspection.checked_date) }}
                    </td>
                </tr>
                <tr>
                    <td
                        style="
                            background: #f1f5f9;
                            font-weight: 700;
                            color: #334155;
                            font-size: 9.5px;
                            padding: 5px 8px;
                            border: 1px solid #94a3b8;
                        "
                    >
                        Departement
                    </td>
                    <td
                        style="
                            background: #f1f5f9;
                            font-weight: 700;
                            color: #334155;
                            font-size: 9.5px;
                            padding: 5px 8px;
                            border: 1px solid #94a3b8;
                        "
                    >
                        User
                    </td>
                    <td
                        style="
                            background: #f1f5f9;
                            font-weight: 700;
                            color: #334155;
                            font-size: 9.5px;
                            padding: 5px 8px;
                            border: 1px solid #94a3b8;
                        "
                    >
                        Email
                    </td>
                    <td
                        style="
                            background: #f1f5f9;
                            font-weight: 700;
                            color: #334155;
                            font-size: 9.5px;
                            padding: 5px 8px;
                            border: 1px solid #94a3b8;
                        "
                    >
                        Date
                    </td>
                </tr>
                <tr>
                    <td
                        style="
                            font-size: 10px;
                            padding: 5px 8px;
                            border: 1px solid #94a3b8;
                        "
                    >
                        {{ inspection.department || '-' }}
                    </td>
                    <td
                        style="
                            font-size: 10px;
                            padding: 5px 8px;
                            border: 1px solid #94a3b8;
                        "
                    >
                        {{ inspection.user || '-' }}
                    </td>
                    <td
                        style="
                            font-size: 10px;
                            padding: 5px 8px;
                            border: 1px solid #94a3b8;
                            word-break: break-all;
                        "
                    >
                        {{ inspection.email || '-' }}
                    </td>
                    <td
                        style="
                            font-size: 10px;
                            padding: 5px 8px;
                            border: 1px solid #94a3b8;
                            white-space: nowrap;
                        "
                    >
                        {{ formatDate(inspection.date) }}
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- 4. ISSUE + PHOTO -->
        <table class="insp-issue-photo">
            <thead>
                <tr>
                    <th class="th-gray" style="width: 40%">Issue</th>
                    <th class="th-gray" style="width: 60%">Photo</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="td-issue">
                        {{ inspection.issue_description || '-' }}
                    </td>
                    <td class="td-photo">
                        <img
                            v-if="inspection.photo"
                            :src="`/storage/${inspection.photo}`"
                            alt="Inspection Photo"
                        />
                        <span
                            v-else
                            style="
                                font-size: 10px;
                                color: #94a3b8;
                                display: block;
                                padding: 30px 0;
                            "
                            >Tidak ada foto</span
                        >
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- 5. SOLUTION -->
        <table class="insp-section">
            <thead>
                <tr>
                    <th class="th-gray">Solution</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="td-body">{{ inspection.solution || '-' }}</td>
                </tr>
            </tbody>
        </table>

        <!-- 6. NOTE -->
        <table class="insp-section">
            <thead>
                <tr>
                    <th class="th-gray">Note</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="td-body">{{ inspection.remarks || '-' }}</td>
                </tr>
            </tbody>
        </table>

        <!-- 7. CONFIRMATION / SIGNATURES -->
        <table class="insp-confirm">
            <thead>
                <tr>
                    <th class="th-gray" colspan="4">Confirmation</th>
                </tr>
                <tr>
                    <th class="th-role">IT</th>
                    <th class="th-role">Checked</th>
                    <th class="th-role">User</th>
                    <th class="th-role">Leader / Head Dept.</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td
                        v-for="sig in signatures"
                        :key="sig.role"
                        class="td-sig"
                    >
                        <div class="sig-box">
                            <SignatureRenderer
                                :data="sigPath(sig.data)"
                                style="width: 100%; height: 100%"
                            />
                        </div>
                        <div class="sig-name">{{ sig.name }}</div>
                        <div class="sig-status">
                            {{
                                sig.data
                                    ? 'Ditandatangani'
                                    : 'Belum ditandatangani'
                            }}
                        </div>
                        <div v-if="!isCompleted" class="sig-actions">
                            <button
                                v-if="!sig.data"
                                type="button"
                                class="app-button-primary app-button-compact btn-sign"
                                @click="openSignatureModal(sig.role)"
                            >
                                Sign
                            </button>
                            <button
                                v-else-if="!sharedMode"
                                type="button"
                                class="app-button-secondary app-button-compact btn-clear"
                                @click="openClearConfirm(sig.role)"
                            >
                                Clear
                            </button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
