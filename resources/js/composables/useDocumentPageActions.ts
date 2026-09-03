import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { ref, toValue  } from 'vue';
import type {MaybeRefOrGetter} from 'vue';

interface UseDocumentPageActionsOptions {
    shareUrl: MaybeRefOrGetter<string | null | undefined>;
    completedPdfUrl: MaybeRefOrGetter<string | null | undefined>;
    printUrl: MaybeRefOrGetter<string>;
    completeUrl: MaybeRefOrGetter<string>;
    canComplete: MaybeRefOrGetter<boolean>;
    reloadOnly: string[];
    completeErrorMessage: string;
    shareSuccessMessage?: string;
    shareErrorMessage?: string;
    closeCompleteConfirm: () => void;
    setActionNotice: (message: string, type?: 'success' | 'error') => void;
}

export function useDocumentPageActions(options: UseDocumentPageActionsOptions) {
    const completeProcessing = ref(false);

    const printDocument = () => {
        const link = document.createElement('a');
        link.href =
            toValue(options.completedPdfUrl) || toValue(options.printUrl);
        link.target = '_blank';
        link.rel = 'noopener';
        document.body.appendChild(link);
        link.click();
        link.remove();
    };

    const copyShareLink = async () => {
        const shareUrl = toValue(options.shareUrl);

        if (!shareUrl) {
            return;
        }

        try {
            await navigator.clipboard.writeText(shareUrl);
            options.setActionNotice(
                options.shareSuccessMessage || 'Share link berhasil disalin.',
            );
        } catch {
            const fallbackInput = document.createElement('textarea');
            fallbackInput.value = shareUrl;
            fallbackInput.setAttribute('readonly', 'true');
            fallbackInput.style.position = 'fixed';
            fallbackInput.style.opacity = '0';
            document.body.appendChild(fallbackInput);
            fallbackInput.select();

            try {
                document.execCommand('copy');
                options.setActionNotice(
                    options.shareSuccessMessage ||
                        'Share link berhasil disalin.',
                );
            } catch {
                options.setActionNotice(
                    options.shareErrorMessage ||
                        'Share link tidak bisa disalin otomatis di browser ini.',
                    'error',
                );
            } finally {
                document.body.removeChild(fallbackInput);
            }
        }
    };

    const completeDocument = () => {
        if (!toValue(options.canComplete)) {
            return;
        }

        completeProcessing.value = true;

        axios
            .post(toValue(options.completeUrl))
            .then(() => {
                options.closeCompleteConfirm();
                router.visit(window.location.href, {
                    only: options.reloadOnly,
                    preserveScroll: true,
                    preserveState: true,
                });
            })
            .catch((error) => {
                options.setActionNotice(
                    error?.response?.data?.message ||
                        options.completeErrorMessage,
                    'error',
                );
            })
            .finally(() => {
                completeProcessing.value = false;
            });
    };

    return {
        completeProcessing,
        printDocument,
        copyShareLink,
        completeDocument,
    };
}
