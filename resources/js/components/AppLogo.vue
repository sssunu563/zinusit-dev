<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
</script>

<template>
    <div class="flex items-center gap-3 ml-4 group-data-[collapsible=icon]:ml-0 group-data-[collapsible=icon]:justify-center">
        <div class="flex shrink-0 items-center justify-center">
            <AppLogoIcon class="size-9 text-primary" />
        </div>

        <div class="min-w-0 flex-1 group-data-[collapsible=icon]:hidden">
            <span class="block truncate text-[16px] font-bold tracking-tight text-foreground">
                Zinus IT
            </span>
        </div>
    </div>
</template>
