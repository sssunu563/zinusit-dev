<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Home } from 'lucide-vue-next';

defineProps<{
    isEditMode: boolean;
    currentType: string;
    currentTypeLabel: string;
}>();
</script>

<template>
    <div class="app-breadcrumb-classic">
        <Link href="/dashboard" class="app-breadcrumb-classic-icon">
            <Home class="size-4" />
        </Link>
        <span class="app-breadcrumb-classic-sep">></span>
        <Link href="/asset" class="hover:underline">Assets</Link>
        <span class="app-breadcrumb-classic-sep">></span>
        <span class="text-white/90">{{ isEditMode ? 'Edit' : 'Create New' }}</span>
    </div>
</template>
