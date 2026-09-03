import { onMounted, onUpdated } from 'vue';

declare global {
    interface Window {
        __APP_RENDER_COUNTS__?: Record<string, number>;
        __APP_RENDER_PROFILER__?: boolean;
    }
}

const isEnabled = () => {
    if (!import.meta.env.DEV || typeof window === 'undefined') {
        return false;
    }

    return (
        window.__APP_RENDER_PROFILER__ === true ||
        new URLSearchParams(window.location.search).has('_renderProfile')
    );
};

const markRender = (name: string, phase: 'mounted' | 'updated') => {
    if (!isEnabled()) {
        return;
    }

    window.__APP_RENDER_COUNTS__ ??= {};
    const nextCount = (window.__APP_RENDER_COUNTS__[name] ?? 0) + 1;
    window.__APP_RENDER_COUNTS__[name] = nextCount;

    console.debug(`[render-profiler] ${name} ${phase} #${nextCount}`);
};

export const useRenderProfiler = (name: string) => {
    onMounted(() => {
        markRender(name, 'mounted');
    });

    onUpdated(() => {
        markRender(name, 'updated');
    });
};
