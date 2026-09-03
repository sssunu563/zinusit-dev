import { onBeforeUnmount, ref } from 'vue';

interface ActionNotice {
    type: 'success' | 'error';
    message: string;
}

export function useActionNotice(timeout = 3200) {
    const actionNotice = ref<ActionNotice | null>(null);
    let actionNoticeTimer: number | null = null;

    const clearActionNoticeTimer = () => {
        if (actionNoticeTimer) {
            window.clearTimeout(actionNoticeTimer);
            actionNoticeTimer = null;
        }
    };

    const setActionNotice = (
        message: string,
        type: 'success' | 'error' = 'success',
    ) => {
        actionNotice.value = { message, type };
        clearActionNoticeTimer();

        actionNoticeTimer = window.setTimeout(() => {
            actionNotice.value = null;
            actionNoticeTimer = null;
        }, timeout);
    };

    onBeforeUnmount(() => {
        clearActionNoticeTimer();
    });

    return {
        actionNotice,
        setActionNotice,
        clearActionNoticeTimer,
    };
}
