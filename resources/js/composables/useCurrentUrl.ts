import type { InertiaLinkProps } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import type { ComputedRef, DeepReadonly } from 'vue';
import { computed, readonly } from 'vue';
import { toUrl } from '@/lib/utils';

export type UseCurrentUrlReturn = {
    currentUrl: DeepReadonly<ComputedRef<string>>;
    isCurrentUrl: (
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        currentUrl?: string,
        startsWith?: boolean,
    ) => boolean;
    isCurrentOrParentUrl: (
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        currentUrl?: string,
    ) => boolean;
    whenCurrentUrl: <T, F = null>(
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        ifTrue: T,
        ifFalse?: F,
    ) => T | F;
};

const page = usePage();
const currentUrlReactive = computed(() => {
    const currentLocation = new URL(page.url, window?.location.origin);

    return `${currentLocation.pathname}${currentLocation.search}`;
});

type ParsedUrl = {
    pathname: string;
    searchParams: URLSearchParams;
};

function normalizeUrl(
    urlToCheck: NonNullable<InertiaLinkProps['href']>,
): ParsedUrl | null {
    const urlString = toUrl(urlToCheck);

    if (!urlString) {
        return null;
    }

    try {
        const normalized = new URL(urlString, window?.location.origin);

        return {
            pathname: normalized.pathname,
            searchParams: normalized.searchParams,
        };
    } catch {
        return null;
    }
}

function parseCurrentUrl(currentUrl?: string): ParsedUrl | null {
    const urlString = currentUrl ?? currentUrlReactive.value;

    try {
        const normalized = new URL(urlString, window?.location.origin);

        return {
            pathname: normalized.pathname,
            searchParams: normalized.searchParams,
        };
    } catch {
        return null;
    }
}

function hasMatchingSearchParams(
    currentUrl: ParsedUrl,
    targetUrl: ParsedUrl,
): boolean {
    const keys = [...new Set(targetUrl.searchParams.keys())];

    return keys.every((key) => {
        const currentValues = currentUrl.searchParams.getAll(key);
        const targetValues = targetUrl.searchParams.getAll(key);

        if (currentValues.length !== targetValues.length) {
            return false;
        }

        return targetValues.every(
            (value, index) => currentValues[index] === value,
        );
    });
}

export function useCurrentUrl(): UseCurrentUrlReturn {
    function isCurrentUrl(
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        currentUrl?: string,
        startsWith: boolean = false,
    ) {
        const currentLocation = parseCurrentUrl(currentUrl);
        const compareTarget = normalizeUrl(urlToCheck);

        if (!currentLocation || !compareTarget) {
            return false;
        }

        const isMatchingPath = startsWith
            ? currentLocation.pathname.startsWith(compareTarget.pathname)
            : compareTarget.pathname === currentLocation.pathname;

        if (!isMatchingPath) {
            return false;
        }

        if (![...compareTarget.searchParams.keys()].length) {
            return true;
        }

        return hasMatchingSearchParams(currentLocation, compareTarget);
    }

    function isCurrentOrParentUrl(
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        currentUrl?: string,
    ) {
        return isCurrentUrl(urlToCheck, currentUrl, true);
    }

    function whenCurrentUrl(
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        ifTrue: any,
        ifFalse: any = null,
    ) {
        return isCurrentUrl(urlToCheck) ? ifTrue : ifFalse;
    }

    return {
        currentUrl: readonly(currentUrlReactive),
        isCurrentUrl,
        isCurrentOrParentUrl,
        whenCurrentUrl,
    };
}
