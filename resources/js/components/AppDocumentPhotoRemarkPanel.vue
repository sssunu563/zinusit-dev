<script setup lang="ts">
const props = withDefaults(
    defineProps<{
        photo: string | null;
        remark: string | null;
        photoKicker: string;
        photoTitle: string;
        photoAlt: string;
        emptyPhotoCopy: string;
        remarkKicker: string;
        remarkTitle: string;
        remarkEmptyCopy?: string;
    }>(),
    {
        remarkEmptyCopy: 'Belum ada catatan tambahan.',
    },
);

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

    if (source.startsWith('storage/')) {
        return `/${source}`;
    }

    if (source.startsWith('/')) {
        return `/storage${source}`;
    }

    if (source.startsWith('public/')) {
        return `/storage/${source.replace(/^public\//, '')}`;
    }

    return `/storage/${source.replace(/^\/+/, '').replace(/\\/g, '/')}`;
};
</script>

<template>
    <div class="grid gap-4 xl:grid-cols-[0.82fr,1.18fr]">
        <section class="app-soft-panel-elevated p-4">
            <div class="app-section-head">
                <div>
                    <p class="app-section-kicker">{{ photoKicker }}</p>
                    <h2 class="app-section-title">{{ photoTitle }}</h2>
                </div>
            </div>
            <div class="document-media-stage">
                <img
                    v-if="props.photo"
                    :src="resolvePhotoSource(props.photo)"
                    class="document-media-image"
                    :alt="photoAlt"
                />
                <div v-else class="document-media-empty">
                    <span class="app-media-copy">{{ emptyPhotoCopy }}</span>
                </div>
            </div>
        </section>

        <section class="app-soft-panel-elevated p-4">
            <div class="app-section-head">
                <div>
                    <p class="app-section-kicker">{{ remarkKicker }}</p>
                    <h2 class="app-section-title">{{ remarkTitle }}</h2>
                </div>
            </div>
            <div class="document-remark-card">
                {{ remark || remarkEmptyCopy }}
            </div>
        </section>
    </div>
</template>

<style scoped>
.document-media-stage {
    display: flex;
    min-height: 188px;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border: 1px solid #d6dfe4;
    border-radius: 16px;
    background:
        radial-gradient(
            circle at top right,
            rgba(214, 233, 224, 0.32),
            transparent 34%
        ),
        linear-gradient(180deg, #ffffff 0%, #faf7f2 100%);
    padding: 16px;
}

.document-media-image {
    max-height: 180px;
    width: auto;
    max-width: 100%;
    border-radius: 12px;
    object-fit: contain;
}

.document-media-empty {
    display: flex;
    min-height: 156px;
    width: 100%;
    align-items: center;
    justify-content: center;
    border: 1px dashed #e7e1da;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.58);
}

.document-remark-card {
    min-height: 188px;
    border: 1px solid #d6dfe4;
    border-radius: 16px;
    background: linear-gradient(180deg, #ffffff 0%, #faf7f2 100%);
    padding: 16px;
    font-size: 13px;
    line-height: 1.65;
    white-space: pre-wrap;
    color: #436055;
}
</style>
