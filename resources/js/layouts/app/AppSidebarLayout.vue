<script setup lang="ts">
import { ref } from 'vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import AppUniversalSearch from '@/components/AppUniversalSearch.vue';
import FlashAlert from '@/Components/FlashAlert.vue';
import { SidebarInset, SidebarProvider } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItem[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const searchRef = ref();
</script>

<template>
    <SidebarProvider>
        <AppSidebar />
        <SidebarInset class="bg-[#f8fafc]">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" @toggle-search="searchRef?.toggleSearch()" />
            <main class="flex-1 overflow-y-auto overflow-x-hidden p-2 md:p-3 lg:p-4 w-full min-w-0 max-w-full">
                <slot />
            </main>
        </SidebarInset>
        <FlashAlert />
        <AppUniversalSearch ref="searchRef" />
    </SidebarProvider>
</template>
