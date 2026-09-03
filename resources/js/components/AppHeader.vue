<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed, defineAsyncComponent } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import AppHeaderUtilityLinks from '@/components/header/AppHeaderUtilityLinks.vue';
import { useRenderProfiler } from '@/composables/useRenderProfiler';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

const AppHeaderMobileNav = defineAsyncComponent(
    () => import('@/components/header/AppHeaderMobileNav.vue'),
);
const AppHeaderDesktopNav = defineAsyncComponent(
    () => import('@/components/header/AppHeaderDesktopNav.vue'),
);
const AppHeaderAccountMenu = defineAsyncComponent(
    () => import('@/components/header/AppHeaderAccountMenu.vue'),
);

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

const props = withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage();
const auth = computed(() => page.props.auth);

useRenderProfiler('AppHeader');
</script>

<template>
    <div>
        <div
            class="border-b border-border bg-background/80 text-foreground backdrop-blur-xl"
        >
            <div
                class="mx-auto flex h-18 items-center px-4 md:max-w-7xl md:px-6"
            >
                <AppHeaderMobileNav />

                <Link
                    :href="dashboard()"
                    class="flex items-center gap-x-3 rounded-full border border-border bg-card/60 px-3 py-2 shadow-xl"
                >
                    <AppLogo />
                </Link>

                <AppHeaderDesktopNav />

                <div class="ml-auto flex items-center space-x-2">
                    <AppHeaderUtilityLinks />
                    <AppHeaderAccountMenu :user="auth.user" />
                </div>
            </div>
        </div>

        <div
            v-if="props.breadcrumbs.length > 1"
            class="flex w-full border-b border-border bg-card/40 text-foreground backdrop-blur-xl"
        >
            <div
                class="mx-auto flex h-13 w-full items-center justify-start px-4 text-foreground md:max-w-7xl md:px-6"
            >
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </div>
        </div>
    </div>
</template>
