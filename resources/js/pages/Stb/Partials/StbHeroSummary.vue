<script setup lang="ts">
import AppDocumentHeroSummary from '@/components/AppDocumentHeroSummary.vue';

interface GroupParts {
    location?: string | null;
    department?: string | null;
}

interface ActionNotice {
    type: 'success' | 'error';
    message: string;
}

const props = defineProps<{
    docId: string;
    userName: string;
    statusLabel: string;
    updatedLabel: string;
    position: string;
    groupParts: GroupParts;
    itemCount: number;
    totalQty: number;
    building: string | null;
    signedCount: number;
    totalApprovals: number;
    isCompleted: boolean;
    isCancelled: boolean;
    hasAllSignatures: boolean;
    canComplete: boolean;
    completeProcessing: boolean;
    loanReturnHref: string | null;
    shareUrl: string | null;
    stbId: number;
    actionNotice: ActionNotice | null;
    copyShareLink: () => void | Promise<void>;
    openCompleteConfirm: () => void;
    printStb: () => void;
    openDeleteConfirm: () => void;
    detailKicker: string;
    progressLabel: string;
    progressMeta: string;
    subjectLabel: string;
    assetLabel: string;
    updatedMetaPrefix: string;
}>();
</script>

<template>
    <AppDocumentHeroSummary
        :doc-id="props.docId"
        :user-name="props.userName"
        :status-label="props.statusLabel"
        :updated-label="props.updatedLabel"
        :position="props.position"
        :group-parts="props.groupParts"
        :item-count="props.itemCount"
        :total-qty="props.totalQty"
        :building="props.building"
        :signed-count="props.signedCount"
        :total-approvals="props.totalApprovals"
        :is-completed="props.isCompleted"
        :is-cancelled="props.isCancelled"
        :has-all-signatures="props.hasAllSignatures"
        :can-complete="props.canComplete"
        :complete-processing="props.completeProcessing"
        :loan-return-href="props.loanReturnHref"
        :share-url="props.shareUrl"
        :edit-href="`/stb/${props.stbId}/edit`"
        :action-notice="props.actionNotice"
        :copy-share-link="props.copyShareLink"
        :open-complete-confirm="props.openCompleteConfirm"
        :print-document="props.printStb"
        :open-delete-confirm="props.openDeleteConfirm"
        :detail-kicker="props.detailKicker"
        :progress-label="props.progressLabel"
        :progress-meta="props.progressMeta"
        :subject-label="props.subjectLabel"
        :asset-label="props.assetLabel"
        :updated-meta-prefix="props.updatedMetaPrefix"
    />
</template>
