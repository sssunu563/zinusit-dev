import { onMounted } from 'vue';

interface CategorizedItem {
    kategori?: string | null;
}

export function useDocumentAssetBootstrap<TItem extends CategorizedItem>(
    items: () => TItem[],
    ensureDirectoryLoaded: () => Promise<unknown>,
    ensureAssetsLoaded: (category: string) => Promise<unknown>,
) {
    onMounted(async () => {
        await ensureDirectoryLoaded();
        await Promise.all(
            Array.from(
                new Set(
                    items()
                        .map((item) => item.kategori)
                        .filter(Boolean),
                ),
            ).map((category) => ensureAssetsLoaded(category as string)),
        );
    });
}
