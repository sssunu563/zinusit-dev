export type FlashType = 'success' | 'error' | 'info';

export function notify(type: FlashType, message: string) {
    window.dispatchEvent(new CustomEvent('flash-message', {
        detail: { type, message }
    }));
}
