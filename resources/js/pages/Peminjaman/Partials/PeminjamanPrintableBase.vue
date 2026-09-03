<script setup lang="ts">
import SignatureRenderer from '@/components/SignatureRenderer.vue';

const normalizePhotoSource = (value: string | null | undefined) => {
    if (!value) return null;

    const source = String(value).trim();
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

    if (source.startsWith('storage/')) {
        return `/${source}`;
    }

    if (source.startsWith('/')) {
        return `/storage${source}`;
    }

    if (source.startsWith('public/')) {
        return `/storage/${source.replace(/^public\//, '')}`;
    }

    return `/storage/${source.replace(/^\/+/, '')}`;
};

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
    use_date: string | null;
    created_at: string;
    photo: string | null;
    return_photo_path?: string | null;
    returned_at?: string | null;
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
    signatureSections: SignatureSection[];
}>();
</script>

<template>
    <section :class="sharedMode ? 'bg-white p-0' : 'bg-transparent'">
        <div
            v-if="!sharedMode"
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
            <!-- Professional Header -->
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
                            <div
                                class="shared-title-main tracking-widest uppercase"
                            >
                                {{ headerTitle }}
                            </div>
                            <div
                                class="shared-title-sub text-slate-500 uppercase"
                            >
                                {{ headerSubtitle }}
                            </div>
                        </td>
                        <td class="shared-meta-cell">
                            <div style="font-weight: 700; color: #475569">
                                IT Dept.
                            </div>
                            <div style="margin-top: 2px">{{ headerDocNo }}</div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Document Info Grid -->
            <table class="shared-info-table">
                <tbody>
                    <tr>
                        <td class="shared-label">Doc ID</td>
                        <td class="value font-bold">{{ docId }}</td>
                        <td class="shared-label">Location</td>
                        <td class="value">{{ groupParts.location || '-' }}</td>
                    </tr>
                    <tr>
                        <td class="shared-label">{{ documentDateLabel }}</td>
                        <td class="value">
                            {{ formatDate(document.use_date) }}
                        </td>
                        <td class="shared-label">Est. Kembali</td>
                        <td class="value font-bold text-red-600">
                            {{ formatDate(document.expected_return_date) }}
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

            <!-- Recipient Detail -->
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

            <!-- Asset Table -->
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

            <!-- Footer Section (Signatures & Remark Separated) -->
            <div class="shared-signature-grid mt-8 !gap-4">
                <!-- Signatures -->
                <table class="shared-signature-table h-full w-full">
                    <thead>
                        <tr>
                            <th
                                v-for="col in signatureSections[0].columns"
                                :key="col.role"
                                class="shared-signature-head py-1.5 text-[10px] font-bold"
                            >
                                {{ col.label }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td
                                v-for="col in signatureSections[0].columns"
                                :key="col.role"
                                class="shared-signature-body"
                            >
                                <div class="shared-signature-image-box">
                                    <SignatureRenderer
                                        :data="col.signaturePath"
                                        class="h-full w-full"
                                    />
                                </div>
                                <div class="shared-signature-name">
                                    {{ col.name }}
                                </div>
                                <div class="shared-signature-time">
                                    {{
                                        col.signedAt
                                            ? formatDateTime(col.signedAt)
                                            : 'Belum ditandatangani'
                                    }}
                                </div>
                                <div class="shared-sign-actions">
                                    <button
                                        v-if="
                                            !col.signaturePath &&
                                            !isCompleted &&
                                            !isCancelled
                                        "
                                        type="button"
                                        class="shared-sign-btn"
                                        @click="openSignatureModal(col.role)"
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
                                        @click="openClearConfirm(col.role)"
                                    >
                                        Clear
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Remark -->
                <table class="shared-signature-table h-full w-full">
                    <thead>
                        <tr>
                            <th
                                class="shared-signature-head px-3 py-1.5 text-[10px] font-bold"
                                style="text-align: left !important"
                            >
                                Remark :
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td
                                class="shared-signature-body bg-white p-4 align-top text-[10px] text-slate-700 italic"
                                style="text-align: left !important"
                            >
                                {{ document.remark || '-' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Evidence Gallery -->
            <div class="mt-8 grid grid-cols-2 gap-6">
                <div class="space-y-2">
                    <div class="text-[9px] font-bold text-slate-500">
                        Foto Penyerahan :
                        <span
                            v-if="document.photo"
                            class="ml-1 font-normal text-slate-400 italic"
                            >{{
                                document.created_at_formatted ||
                                document.created_at
                            }}</span
                        >
                    </div>
                    <div
                        class="shared-photo-box flex w-full items-center justify-center rounded-lg border border-slate-100 bg-slate-50/50"
                    >
                        <img
                            v-if="document.photo"
                            :src="normalizePhotoSource(document.photo)"
                            class="shared-photo rounded-lg"
                        />
                        <span
                            v-else
                            class="text-[10px] font-medium text-slate-300 italic"
                            >Foto penyerahan tidak tersedia</span
                        >
                    </div>
                </div>
                <div class="space-y-2">
                    <div class="text-[9px] font-bold text-slate-500">
                        Foto Pengembalian :
                        <span
                            v-if="
                                document.return_photo_path ||
                                document.returned_at
                            "
                            class="ml-1 font-normal text-slate-400 italic"
                            >{{
                                document.returned_at_formatted ||
                                document.returned_at
                            }}</span
                        >
                    </div>
                    <div
                        class="shared-photo-box flex w-full items-center justify-center rounded-lg border border-slate-100 bg-slate-50/50"
                    >
                        <img
                            v-if="document.return_photo_path"
                            :src="
                                normalizePhotoSource(document.return_photo_path)
                            "
                            class="shared-photo rounded-lg"
                        />
                        <span
                            v-else
                            class="text-[10px] font-medium text-slate-300 italic"
                            >Foto pengembalian tidak tersedia</span
                        >
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
