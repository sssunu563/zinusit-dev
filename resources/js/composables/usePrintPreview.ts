import { nextTick, onMounted  } from 'vue';
import type {Ref} from 'vue';

export function usePrintPreview(
    printRoot: Ref<HTMLElement | null>,
    prepare: () => Promise<void>,
) {
    onMounted(async () => {
        await prepare();
        await nextTick();

        const images = Array.from(
            printRoot.value?.querySelectorAll('img') ?? [],
        );

        await Promise.all(
            images.map(
                (image) =>
                    new Promise<void>((resolve) => {
                        if (image.complete) {
                            resolve();
                            return;
                        }

                        image.addEventListener('load', () => resolve(), {
                            once: true,
                        });
                        image.addEventListener('error', () => resolve(), {
                            once: true,
                        });
                    }),
            ),
        );

        window.setTimeout(() => {
            window.print();
        }, 500);
    });
}
