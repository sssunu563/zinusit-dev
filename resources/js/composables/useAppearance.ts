import type { ComputedRef, Ref } from 'vue';
import { computed, onMounted, ref } from 'vue';
import type { Appearance, ResolvedAppearance } from '@/types';

export type { Appearance, ResolvedAppearance };

export type UseAppearanceReturn = {
    appearance: Ref<Appearance>;
    resolvedAppearance: ComputedRef<ResolvedAppearance>;
    updateAppearance: (value: Appearance) => void;
};

export function updateTheme(value: Appearance): void {
    if (typeof window === 'undefined') {
        return;
    }

    document.documentElement.dataset.appearance = value;
    document.documentElement.classList.remove('dark');
}

const setCookie = (name: string, value: string, days = 365) => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;

    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

export function initializeTheme(): void {
    // No-op since dark mode is disabled. We still expose the function so that
    // existing callers don't break, but it no longer changes the document
    // class or installs listeners.
}

// We default to light and ignore any stored or user-selected value. Dark
// mode is disabled, so the only meaningful appearance is 'light'.
const appearance = ref<Appearance>('light');

export function useAppearance(): UseAppearanceReturn {
    onMounted(() => {
        appearance.value = 'light';
        updateTheme('light');
    });

    // Always return light; system preference and stored values are ignored.
    const resolvedAppearance = computed<ResolvedAppearance>(() => 'light');

    function updateAppearance(value: Appearance) {
        appearance.value = value === 'light' ? 'light' : 'light';

        localStorage.setItem('appearance', 'light');
        setCookie('appearance', 'light');

        updateTheme('light');
    }

    return {
        appearance,
        resolvedAppearance,
        updateAppearance,
    };
}
