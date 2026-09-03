<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';
import { Search } from 'lucide-vue-next';
import AppNotificationDrawer from '@/components/AppNotificationDrawer.vue';

withDefaults(defineProps<{ breadcrumbs?: BreadcrumbItem[] }>(), {
    breadcrumbs: () => [],
});

defineEmits(['toggle-search']);
</script>

<template>
    <header
        class="sticky top-0 z-20 flex h-14 shrink-0 items-center justify-between gap-2 border-b border-border bg-background px-4 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12"
    >
        <SidebarTrigger
            class="-ml-1 h-8 w-8 rounded-lg text-muted-foreground hover:bg-accent hover:text-accent-foreground transition-all duration-300"
        />

        <!-- Separator dot -->
        <div class="h-4 w-px bg-border" />

        <div v-if="breadcrumbs && breadcrumbs.length > 0" class="min-w-0 flex-1">
            <Breadcrumbs :breadcrumbs="breadcrumbs" />
        </div>

        <div class="flex items-center gap-3 ml-auto">
            <!-- Search Trigger -->
            <button 
                @click="$emit('toggle-search')"
                class="hidden md:flex items-center gap-4 pl-3 pr-1.5 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-400 hover:border-slate-300 transition-all group"
            >
                <div class="flex items-center gap-2">
                    <Search class="w-4 h-4 group-hover:text-slate-600 transition-colors" />
                    <span class="text-xs font-medium">Cari...</span>
                </div>
                <div class="flex items-center gap-1 px-1.5 py-0.5 bg-white border border-slate-200 rounded shadow-sm">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-tighter">Ctrl K</span>
                </div>
            </button>

            <button 
                @click="$emit('toggle-search')"
                class="md:hidden p-2 rounded-xl text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-all"
            >
                <Search class="w-5 h-5" />
            </button>

            <!-- Notification Drawer -->
            <AppNotificationDrawer />
        </div>
    </header>
</template>
