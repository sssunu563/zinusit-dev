<script setup lang="ts">
import { computed } from 'vue';

type SignatureRole =
    | 'it_drafter'
    | 'it_checker'
    | 'it_approved'
    | 'requester_received'
    | 'requester_dept_head';

interface ApprovalCardItem {
    role: SignatureRole;
    title: string;
    name: string;
    signaturePath: string | null;
    signedAt: string | null;
    badge: string;
}

const props = defineProps<{
    approvalCards: ApprovalCardItem[];
    formatDateTime: (date?: string | null) => string;
    openSignatureModal: (role: SignatureRole) => void;
    canSign: boolean;
    sectionKicker: string;
    sectionTitle: string;
    sectionCopy: string;
    emptyStateCopy: string;
    statementKicker: string;
    statementTitle: string;
    statementIntro: string;
    statementBody: string;
    violationTitle: string;
    violationBody: string;
}>();

const approvalRows = computed(() => [
    props.approvalCards.slice(0, 3),
    props.approvalCards.slice(3),
]);

const getApprovalBadgeClass = (card: ApprovalCardItem) =>
    card.signaturePath ? 'app-badge-positive' : 'app-badge-warning';
</script>

<template>
    <div class="grid gap-4 xl:grid-cols-[1fr,1fr]">
        <section class="app-soft-panel-elevated p-4">
            <div class="app-section-head">
                <div>
                    <p class="app-section-kicker">{{ sectionKicker }}</p>
                    <h2 class="app-section-title">{{ sectionTitle }}</h2>
                </div>
                <p class="app-section-copy">{{ sectionCopy }}</p>
            </div>

            <div
                class="mb-3 flex flex-wrap items-center justify-between gap-2 rounded-[14px] border border-[#ece6df] bg-[#faf7f2] px-3 py-2.5"
            >
                <div class="flex flex-wrap items-center gap-2">
                    <span class="app-note-chip">
                        {{
                            approvalCards.filter((card) => card.signaturePath)
                                .length
                        }}/{{ approvalCards.length }} signed
                    </span>
                    <span class="app-table-meta"
                        >Status tanda tangan realtime untuk dokumen ini.</span
                    >
                </div>
            </div>

            <div class="grid gap-3 md:grid-cols-3">
                <div
                    v-for="card in approvalRows[0]"
                    :key="card.role"
                    class="approval-card"
                >
                    <div class="flex items-start justify-between gap-2">
                        <span class="approval-role">{{ card.title }}</span>
                        <span
                            class="app-badge text-[9px] tracking-[0.04em] uppercase"
                            :class="getApprovalBadgeClass(card)"
                        >
                            {{ card.badge }}
                        </span>
                    </div>
                    <span class="approval-name">{{ card.name }}</span>
                    <div class="signature-preview-box">
                        <img
                            v-if="card.signaturePath"
                            :src="`/storage/${card.signaturePath}`"
                            class="signature-preview-image"
                            :alt="`${card.title} signature`"
                        />
                        <span v-else class="signature-preview-empty">
                            {{ emptyStateCopy }}
                        </span>
                    </div>
                    <div class="approval-meta-row">
                        <span class="approval-signed-at">
                            {{
                                card.signedAt
                                    ? formatDateTime(card.signedAt)
                                    : 'Not signed'
                            }}
                        </span>
                        <button
                            v-if="canSign"
                            type="button"
                            class="app-button-primary app-button-compact shrink-0"
                            @click="openSignatureModal(card.role)"
                        >
                            {{
                                card.signaturePath
                                    ? 'Resign'
                                    : 'Sign'
                            }}
                        </button>
                    </div>
                </div>
            </div>

            <div class="mt-3 grid gap-3 md:grid-cols-2">
                <div
                    v-for="card in approvalRows[1]"
                    :key="card.role"
                    class="approval-card"
                >
                    <div class="flex items-start justify-between gap-2">
                        <span class="approval-role">{{ card.title }}</span>
                        <span
                            class="app-badge text-[9px] tracking-[0.04em] uppercase"
                            :class="getApprovalBadgeClass(card)"
                        >
                            {{ card.badge }}
                        </span>
                    </div>
                    <span class="approval-name">{{ card.name }}</span>
                    <div class="signature-preview-box">
                        <img
                            v-if="card.signaturePath"
                            :src="`/storage/${card.signaturePath}`"
                            class="signature-preview-image"
                            :alt="`${card.title} signature`"
                        />
                        <span v-else class="signature-preview-empty">
                            {{ emptyStateCopy }}
                        </span>
                    </div>
                    <div class="approval-meta-row">
                        <span class="approval-signed-at">
                            {{
                                card.signedAt
                                    ? formatDateTime(card.signedAt)
                                    : 'Not signed'
                            }}
                        </span>
                        <button
                            v-if="canSign"
                            type="button"
                            class="app-button-primary app-button-compact shrink-0"
                            @click="openSignatureModal(card.role)"
                        >
                            {{
                                card.signaturePath
                                    ? 'Resign'
                                    : 'Sign'
                            }}
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <section class="app-soft-panel-elevated p-4">
            <div class="app-section-head">
                <div>
                    <p class="app-section-kicker">{{ statementKicker }}</p>
                    <h2 class="app-section-title">{{ statementTitle }}</h2>
                </div>
            </div>
            <div class="app-copy-panel">
                <p class="font-semibold text-[#17342a]">
                    {{ statementIntro }}
                </p>
                <p class="mt-2 whitespace-pre-line">
                    {{ statementBody }}
                </p>
                <p class="mt-4 font-semibold text-[#17342a]">
                    {{ violationTitle }}
                </p>
                <p class="mt-2 whitespace-pre-line">
                    {{ violationBody }}
                </p>
            </div>
        </section>
    </div>
</template>

<style scoped>
.approval-card {
    display: flex;
    min-height: 198px;
    flex-direction: column;
    justify-content: space-between;
    border: 1px solid #ece6df;
    border-radius: 12px;
    background: linear-gradient(180deg, #ffffff 0%, #faf7f2 100%);
    padding: 12px;
}

.approval-role {
    font-size: 9px;
    font-weight: 800;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: #7a817b;
}

.approval-name {
    padding-top: 10px;
    border-top: 1px dashed #e7e1da;
    font-size: 12px;
    font-weight: 700;
    line-height: 1.25;
    color: #22343d;
}

.signature-preview-box {
    display: flex;
    min-height: 76px;
    align-items: center;
    justify-content: center;
    margin-top: 8px;
    border: 1px dashed #e7e1da;
    border-radius: 10px;
    background: linear-gradient(180deg, #ffffff 0%, #faf7f2 100%);
    padding: 6px;
}

.signature-preview-image {
    max-width: 100%;
    max-height: 58px;
    object-fit: contain;
}

.signature-preview-empty {
    font-size: 11px;
    text-align: center;
    color: #9aa39d;
}

.approval-meta-row {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 8px;
    margin-top: 8px;
}

.approval-signed-at {
    font-size: 11px;
    line-height: 1.3;
    color: #6f867c;
}

@media (max-width: 640px) {
    .approval-meta-row {
        align-items: flex-start;
        flex-direction: column;
    }
}
</style>
