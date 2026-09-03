<script setup lang="ts">
import { Sun } from 'lucide-vue-next';
import { useAppearance } from '@/composables/useAppearance';

const { appearance, updateAppearance } = useAppearance();

// The system is now 100% Soft Light.
const tabs = [{ value: 'light', Icon: Sun, label: 'Soft Light' }] as const;
</script>

<template>
    <div
        class="inline-flex gap-1 rounded-xl bg-primary/5 p-1 border border-primary/10 shadow-sm"
    >
        <button
            v-for="{ value, Icon, label } in tabs"
            :key="value"
            @click="updateAppearance(value)"
            class="flex items-center rounded-lg px-3.5 py-1.5 transition-colors bg-white text-primary shadow-md"
        >
            <component :is="Icon" class="-ml-1 h-3.5 w-3.5" />
            <span class="ml-2 text-[10px] font-black uppercase tracking-widest">{{ label }}</span>
        </button>
    </div>
</template>
