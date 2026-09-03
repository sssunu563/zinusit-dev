<script setup lang="ts">
import { computed } from 'vue';
import { SidebarInset } from '@/components/ui/sidebar';

type Props = {
    variant?: 'header' | 'sidebar';
    class?: string;
};

const props = defineProps<Props>();
const className = computed(() => props.class);
</script>

<template>
    <!-- Sidebar variant: takes full inset area -->
    <SidebarInset v-if="props.variant === 'sidebar'" :class="className">
        <div class="app-page-enter flex flex-1 flex-col overflow-y-auto">
            <div
                class="app-page-stack flex min-h-full w-full max-w-none flex-col gap-5 px-4 py-5 sm:px-5 sm:py-5 md:px-6 md:py-6"
            >
                <slot />
            </div>
        </div>
    </SidebarInset>

    <!-- Header variant: full-page layout -->
    <main
        v-else
        class="app-page-enter app-page-stack flex h-full w-full flex-1 flex-col gap-4 px-4 py-4 sm:px-5 sm:py-5"
        :class="className"
    >
        <slot />
    </main>
</template>
