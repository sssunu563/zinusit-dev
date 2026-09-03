<script setup lang="ts">
defineProps<{
    docId: string;
    statusLabel: string;
    userName: string;
    itemCount: number;
    totalQty: number;
    toolbarKicker: string;
    toolbarCopy: string;
    subjectLabel: string;
    assetLabel: string;
}>();
</script>

<template>
    <section class="print-toolbar" aria-hidden="true">
        <div>
            <p class="print-kicker">{{ toolbarKicker }}</p>
            <h1 class="print-title">{{ docId }}</h1>
            <p class="print-copy">
                {{ toolbarCopy }}
            </p>
        </div>
        <div class="print-chip-group">
            <div class="print-chip">
                <span class="print-chip-label">Status</span>
                <strong class="print-chip-value">{{ statusLabel }}</strong>
            </div>
            <div class="print-chip">
                <span class="print-chip-label">{{ subjectLabel }}</span>
                <strong class="print-chip-value">{{ userName }}</strong>
            </div>
            <div class="print-chip">
                <span class="print-chip-label">{{ assetLabel }}</span>
                <strong class="print-chip-value">
                    {{ itemCount }} item / {{ totalQty }} qty
                </strong>
            </div>
        </div>
    </section>
</template>

<style scoped>
.print-toolbar {
    width: 194mm;
    max-width: 100%;
    margin: 0 auto 16px;
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 16px;
}

.print-kicker {
    margin: 0;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: #5a766b;
}

.print-title {
    margin: 6px 0 0;
    font-size: 28px;
    line-height: 1.1;
    font-weight: 800;
    color: #17342a;
}

.print-copy {
    margin: 8px 0 0;
    max-width: 460px;
    font-size: 13px;
    line-height: 1.6;
    color: #587267;
}

.print-chip-group {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 10px;
}

.print-chip {
    min-width: 140px;
    border: 1px solid rgba(151, 180, 166, 0.3);
    border-radius: 14px;
    background: linear-gradient(
        180deg,
        rgba(255, 255, 255, 0.96) 0%,
        rgba(244, 249, 246, 0.98) 100%
    );
    padding: 10px 12px;
    box-shadow: 0 18px 36px -32px rgba(0, 54, 40, 0.35);
}

.print-chip-label {
    display: block;
    font-size: 9px;
    font-weight: 800;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: #5a766b;
}

.print-chip-value {
    display: block;
    margin-top: 4px;
    font-size: 13px;
    line-height: 1.35;
    color: #17342a;
}

@media screen and (max-width: 900px) {
    .print-toolbar {
        flex-direction: column;
        align-items: stretch;
    }

    .print-chip-group {
        justify-content: flex-start;
    }
}

@media print {
    .print-toolbar {
        display: none;
    }
}
</style>
