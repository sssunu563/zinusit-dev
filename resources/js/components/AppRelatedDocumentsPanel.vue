<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

interface RelatedDocumentItem {
    id: number;
    docId: string;
    href: string;
    relationLabel: string;
    documentLabel: string;
    userName: string;
    deliverDate: string | null;
    completedAt: string | null;
    returnedAt?: string | null;
}

withDefaults(
    defineProps<{
        linkedDocument?: RelatedDocumentItem | null;
        relatedDocuments: RelatedDocumentItem[];
        loanReturnHref?: string | null;
        relationCompletedLabel: string;
        formatDate: (date?: string | null) => string;
        sectionKicker?: string;
        sectionTitle?: string;
        followUpKicker?: string;
        followUpTitle?: string;
        followUpCopy?: string;
        followUpButtonLabel?: string;
    }>(),
    {
        linkedDocument: null,
        loanReturnHref: null,
        sectionKicker: 'Relasi',
        sectionTitle: 'Dokumen Terkait',
        followUpKicker: 'Tindak Lanjut',
        followUpTitle: 'Pengembalian asset pinjaman siap dibuat.',
        followUpCopy:
            'Gunakan dokumen pengembalian untuk foto pengembalian dan tanda tangan pengembalian.',
        followUpButtonLabel: 'Pengembalian',
    },
);
</script>

<template>
    <section
        class="app-soft-panel p-4 shadow-xl border border-border bg-card/60 backdrop-blur-sm"
    >
        <div class="app-section-head">
            <div>
                <p class="app-section-kicker">{{ sectionKicker }}</p>
                <h2 class="app-section-title">{{ sectionTitle }}</h2>
            </div>
        </div>

        <div class="grid gap-3 md:grid-cols-2">
            <div
                v-if="linkedDocument"
                class="rounded-[18px] border border-border bg-card p-4 shadow-sm"
            >
                <p
                    class="text-[11px] font-semibold tracking-[0.18em] text-muted-foreground/60 uppercase"
                >
                    {{ linkedDocument.relationLabel }}
                </p>
                <Link
                    :href="linkedDocument.href"
                    class="mt-2 block text-sm font-semibold text-foreground hover:text-primary"
                >
                    {{ linkedDocument.docId }}
                </Link>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ linkedDocument.userName }}
                </p>
                <p class="mt-2 text-xs text-muted-foreground/60">
                    {{ linkedDocument.documentLabel }}
                    <span v-if="linkedDocument.deliverDate">
                        · {{ formatDate(linkedDocument.deliverDate) }}
                    </span>
                </p>
            </div>

            <div
                v-for="document in relatedDocuments"
                :key="document.id"
                class="rounded-[18px] border border-border bg-card p-4 shadow-sm"
            >
                <p
                    class="text-[11px] font-semibold tracking-[0.18em] text-muted-foreground/60 uppercase"
                >
                    {{ document.relationLabel }}
                </p>
                <Link
                    :href="document.href"
                    class="mt-2 block text-sm font-semibold text-foreground hover:text-primary"
                >
                    {{ document.docId }}
                </Link>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ document.userName }}
                </p>
                <p class="mt-2 text-xs text-muted-foreground/60">
                    {{ document.documentLabel }}
                    <span v-if="document.completedAt">
                        · {{ relationCompletedLabel }}
                        {{ formatDate(document.completedAt) }}
                    </span>
                    <span v-else-if="document.deliverDate">
                        · {{ formatDate(document.deliverDate) }}
                    </span>
                </p>
            </div>

            <div
                v-if="loanReturnHref"
                class="rounded-[18px] border border-dashed border-border bg-muted/10 p-4"
            >
                <p
                    class="text-[11px] font-semibold tracking-[0.18em] text-muted-foreground/60 uppercase"
                >
                    {{ followUpKicker }}
                </p>
                <p class="mt-2 text-sm font-semibold text-foreground">
                    {{ followUpTitle }}
                </p>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ followUpCopy }}
                </p>
                <Link
                    :href="loanReturnHref"
                    class="app-button-primary app-button-compact mt-3 inline-flex"
                >
                    {{ followUpButtonLabel }}
                </Link>
            </div>
        </div>
    </section>
</template>
