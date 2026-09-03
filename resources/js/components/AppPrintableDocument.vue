<script setup lang="ts">
import SignatureRenderer from './SignatureRenderer.vue';
interface GroupParts {
    location?: string | null;
    company?: string | null;
    department?: string | null;
}

interface PrintableItem {
    id: number;
    nama: string;
    type: string;
    jumlah: number;
    serial_no: string;
}

interface PrintableDocumentRecord {
    deliver_date: string | null;
    building: string | null;
    use_date: string | null;
    batch_no: string | null;
    req_doc_no: string | null;
    po_doc_no: string | null;
    created_at: string;
    photo: string | null;
    attachments?: Array<{ id: number; file_path: string }>;
    remark: string | null;
    expected_return_date?: string | null;
    items: PrintableItem[];
}

interface SignatureColumn<TRole extends string = string> {
    role: TRole;
    label: string;
    name: string;
    signaturePath: string | null;
    signedAt: string | null;
    imageAlt: string;
}

interface SignatureSection<TRole extends string = string> {
    key: string;
    title: string;
    columns: SignatureColumn<TRole>[];
}

const props = defineProps<{
    document: PrintableDocumentRecord;
    docId: string;
    groupParts: GroupParts;
    userName: string;
    phoneNumber: string;
    email: string;
    position: string;
    statusLabel: string;
    isCompleted: boolean;
    isCancelled: boolean;
    sharedMode: boolean;
    formatDate: (date?: string | null) => string;
    formatDateTime: (date?: string | null) => string;
    getAssetLabel: (item: PrintableItem) => string;
    openSignatureModal: (role: string) => void;
    openClearConfirm: (role: string) => void;
    headerTitle: string;
    headerSubtitle: string;
    headerDocNo: string;
    printDocumentTitle: string;
    printDocumentIntro: string;
    documentDateLabel: string;
    signerIntro: string;
    agreementIntro: string;
    agreementBody: string[];
    violationHeading: string;
    violationBody: string[];
    printPhotoLabel: string;
    printRemarkLabel: string;
    movementType: string;
    signatureSections: SignatureSection[];
}>();

const resolvePhotoSource = (value: string | null) => {
    if (!value) return null;

    const source = value.trim();
    if (!source) return null;

    if (
        source.startsWith('data:') ||
        source.startsWith('http://') ||
        source.startsWith('https://')
    ) {
        return source;
    }

    if (source.startsWith('/storage/')) {
        return source;
    }

    return `/storage/${source.replace(/^\/+/, '').replace(/\\/g, '/')}`;
};

const getSignature = (role: string) => {
    for (const section of props.signatureSections) {
        const col = section.columns.find((c) => c.role === role);
        if (col) return col;
    }
    return {
        role,
        label: '',
        name: '-',
        signaturePath: null,
        signedAt: null,
        imageAlt: '',
    };
};
</script>

<template>
    <section :class="sharedMode ? 'bg-white p-0' : 'bg-transparent'">
        <div
            class="mb-5 flex flex-col gap-4 border-b border-border pb-5 md:flex-row md:items-start md:justify-between print:hidden"
        >
            <div class="flex-1">
                <slot name="header" />
            </div>
            <div class="flex w-full flex-col gap-3 md:w-auto md:items-end">
                <div
                    class="flex w-full flex-wrap items-center justify-end gap-2"
                >
                    <slot name="actions" />
                </div>
            </div>
        </div>
        <div class="document-preview shared-print doc-canvas">
            <table class="shared-header-table">
                <tbody>
                    <tr>
                        <td class="shared-logo-cell">
                            <img
                                src="/form-logo.png"
                                class="shared-logo"
                                alt="Zinus"
                            />
                        </td>
                        <td class="shared-title-cell">
                            <div class="shared-title-main">
                                {{
                                    movementType === 'return'
                                        ? 'FORM PENGEMBALIAN BARANG'
                                        : 'FORM SERAH TERIMA BARANG'
                                }}
                            </div>
                            <div class="shared-title-sub">
                                PT. Zinus Global Indonesia
                            </div>
                        </td>
                        <td class="shared-meta-cell">
                            <div style="font-weight: 700; color: #475569">
                                IT Dept.
                            </div>
                            <div style="margin-top: 2px">
                                Doc. No. IT/STB/XII/24/01
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <table class="shared-info-table">
                <tbody>
                    <tr>
                        <td class="shared-label">Doc ID</td>
                        <td class="value">{{ docId }}</td>
                        <td class="shared-label">Location</td>
                        <td class="value">{{ groupParts.location || '-' }}</td>
                    </tr>
                    <tr>
                        <td class="shared-label">{{ documentDateLabel }}</td>
                        <td class="value">
                            {{ formatDate(document.deliver_date) }}
                        </td>
                        <td class="shared-label">Building</td>
                        <td class="value">{{ document.building || '-' }}</td>
                    </tr>
                    <tr>
                        <td class="shared-label">Use Date</td>
                        <td class="value">
                            {{ formatDate(document.use_date) }}
                        </td>
                        <td class="shared-label">Batch No</td>
                        <td class="value">{{ document.batch_no || '-' }}</td>
                    </tr>
                    <tr>
                        <td class="shared-label">Request Doc No</td>
                        <td class="value">{{ document.req_doc_no || '-' }}</td>
                        <td class="shared-label">
                            {{
                                document.expected_return_date
                                    ? 'Est. Kembali'
                                    : 'PO Doc No'
                            }}
                        </td>
                        <td class="value">
                            {{
                                document.expected_return_date
                                    ? formatDate(document.expected_return_date)
                                    : document.po_doc_no || '-'
                            }}
                        </td>
                    </tr>
                </tbody>
            </table>

            <div class="shared-recipient-note">
                <span>{{ signerIntro }}</span>
                <span style="float: right">{{
                    formatDate(document.created_at)
                }}</span>
            </div>

            <table class="shared-info-table">
                <tbody>
                    <tr>
                        <td class="shared-label">Name</td>
                        <td>{{ userName }}</td>
                        <td class="shared-label">Company</td>
                        <td>{{ groupParts.company || '-' }}</td>
                    </tr>
                    <tr>
                        <td class="shared-label">Phone Number</td>
                        <td>{{ phoneNumber }}</td>
                        <td class="shared-label">Department</td>
                        <td>{{ groupParts.department || '-' }}</td>
                    </tr>
                    <tr>
                        <td class="shared-label">Email</td>
                        <td>{{ email }}</td>
                        <td class="shared-label">Position</td>
                        <td>{{ position }}</td>
                    </tr>
                </tbody>
            </table>

            <table class="shared-items-table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Barang</th>
                        <th>Type</th>
                        <th>Qty</th>
                        <th>Serial No</th>
                        <th>Asset</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(item, idx) in document.items" :key="idx">
                        <td class="text-center">{{ idx + 1 }}</td>
                        <td>{{ item.nama }}</td>
                        <td>{{ item.type }}</td>
                        <td class="text-center">{{ item.jumlah }}</td>
                        <td>{{ item.serial_no }}</td>
                        <td>{{ getAssetLabel(item) }}</td>
                    </tr>
                    <tr
                        v-for="n in Math.max(0, 5 - document.items.length)"
                        :key="`empty-${n}`"
                    >
                        <td>&nbsp;</td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>

            <div v-if="agreementBody.length" class="shared-agreement-box">
                <div class="mb-1 text-[8.5px] leading-tight font-bold">
                    {{ agreementIntro }}
                </div>
                <div
                    v-for="line in agreementBody"
                    :key="line"
                    class="shared-agreement-line"
                >
                    <span class="shared-point-num">{{
                        line.substring(0, 3)
                    }}</span>
                    <span class="shared-point-text">{{
                        line.substring(3)
                    }}</span>
                </div>

                <div
                    v-if="violationHeading"
                    class="mt-2 mb-1 text-[8.5px] leading-tight font-bold"
                >
                    {{ violationHeading }}
                </div>
                <div
                    v-for="line in violationBody"
                    :key="line"
                    class="shared-agreement-line"
                >
                    <span class="shared-point-num">{{
                        line.substring(0, 3)
                    }}</span>
                    <span class="shared-point-text">{{
                        line.substring(3)
                    }}</span>
                </div>
            </div>

            <div class="shared-signature-grid">
                <!-- IT Section (left) -->
                <table class="shared-signature-table">
                    <tbody>
                        <tr>
                            <td colspan="3" class="shared-signature-title">
                                IT
                            </td>
                        </tr>
                        <tr>
                            <td class="shared-signature-head">Drafter</td>
                            <td class="shared-signature-head">Checker</td>
                            <td class="shared-signature-head">Approved</td>
                        </tr>
                        <tr>
                            <td
                                v-for="role in [
                                    'it_drafter',
                                    'it_checker',
                                    'it_approved',
                                ]"
                                :key="role"
                                class="shared-signature-body"
                            >
                                <div class="shared-signature-image-box">
                                    <SignatureRenderer
                                        :data="getSignature(role).signaturePath"
                                        class="h-full w-full"
                                    />
                                </div>
                                <div class="shared-signature-name">
                                    {{ getSignature(role).name }}
                                </div>
                                <div class="shared-signature-time">
                                    {{
                                        getSignature(role).signedAt
                                            ? formatDateTime(
                                                  getSignature(role).signedAt,
                                              )
                                            : 'Belum ditandatangani'
                                    }}
                                </div>
                                <div class="shared-sign-actions">
                                    <button
                                        v-if="
                                            !getSignature(role).signaturePath &&
                                            !isCompleted &&
                                            !isCancelled
                                        "
                                        type="button"
                                        class="shared-sign-btn"
                                        @click="openSignatureModal(role)"
                                    >
                                        Sign
                                    </button>
                                    <button
                                        v-else-if="
                                            !sharedMode &&
                                            !isCompleted &&
                                            !isCancelled
                                        "
                                        type="button"
                                        class="shared-clear-btn"
                                        @click="openClearConfirm(role)"
                                    >
                                        Clear
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Requester (2 cols only — no remark column here) -->
                <table
                    class="shared-signature-table"
                    style="table-layout: fixed"
                >
                    <tbody>
                        <tr>
                            <td colspan="2" class="shared-signature-title">
                                Requester
                            </td>
                        </tr>
                        <tr>
                            <td
                                class="shared-signature-head"
                                style="width: 50%"
                            >
                                {{
                                    movementType === 'return'
                                        ? 'Returned'
                                        : movementType === 'loan'
                                          ? 'Borrower'
                                          : 'Received'
                                }}
                            </td>
                            <td
                                class="shared-signature-head"
                                style="width: 50%"
                            >
                                Dept Head
                            </td>
                        </tr>
                        <tr>
                            <td
                                v-for="role in [
                                    'requester_received',
                                    'requester_dept_head',
                                ]"
                                :key="role"
                                class="shared-signature-body"
                            >
                                <div class="shared-signature-image-box">
                                    <SignatureRenderer
                                        :data="getSignature(role).signaturePath"
                                        class="h-full w-full"
                                    />
                                </div>
                                <div class="shared-signature-name">
                                    {{
                                        role === 'requester_received'
                                            ? userName
                                            : getSignature(role).name
                                    }}
                                </div>
                                <div class="shared-signature-time">
                                    {{
                                        getSignature(role).signedAt
                                            ? formatDateTime(
                                                  getSignature(role).signedAt,
                                              )
                                            : 'Belum ditandatangani'
                                    }}
                                </div>
                                <div class="shared-sign-actions">
                                    <button
                                        v-if="
                                            !getSignature(role).signaturePath &&
                                            !isCompleted &&
                                            !isCancelled
                                        "
                                        type="button"
                                        class="shared-sign-btn"
                                        @click="openSignatureModal(role)"
                                    >
                                        Sign
                                    </button>
                                    <button
                                        v-else-if="
                                            !sharedMode &&
                                            !isCompleted &&
                                            !isCancelled
                                        "
                                        type="button"
                                        class="shared-clear-btn"
                                        @click="openClearConfirm(role)"
                                    >
                                        Clear
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Photo (left) + Remark label (right) -->
            <table class="shared-note-table">
                <tbody>
                    <tr>
                        <td
                            style="
                                width: 32%;
                                vertical-align: top;
                                padding: 4px;
                            "
                        >
                            <div class="shared-photo-box">
                                <img
                                    v-if="document.photo"
                                    :src="resolvePhotoSource(document.photo)"
                                    class="shared-photo"
                                    :alt="printPhotoLabel"
                                />
                                <span v-else class="shared-photo-empty">{{
                                    `${printPhotoLabel} tidak tersedia`
                                }}</span>
                            </div>
                            <!-- Small Gallery in Print -->
                            <div
                                v-if="
                                    document.attachments &&
                                    document.attachments.length > 0
                                "
                                class="mt-2 grid grid-cols-4 gap-1"
                            >
                                <div
                                    v-for="att in document.attachments"
                                    :key="att.id"
                                    class="aspect-square overflow-hidden border border-slate-200"
                                >
                                    <img
                                        :src="`/storage/${att.file_path}`"
                                        class="h-full w-full object-cover"
                                    />
                                </div>
                            </div>
                        </td>
                        <td
                            style="
                                width: 68%;
                                vertical-align: top;
                                padding: 8px 12px;
                            "
                        >
                            <div
                                style="
                                    font-weight: 700;
                                    color: #475569;
                                    font-size: 9px;
                                    margin-bottom: 4px;
                                "
                            >
                                {{ printRemarkLabel }} :
                            </div>
                            <div
                                style="
                                    font-size: 9px;
                                    line-height: 1.5;
                                    color: #1e293b;
                                "
                            >
                                {{ document.remark || '-' }}
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>

<style scoped>
/* Scoped styles kept minimal, relying on global shared-* classes */
</style>
