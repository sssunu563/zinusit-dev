<script setup lang="ts">
import { computed } from 'vue';

interface DataItem {
    label: string;
    count: number;
    tone: string;
}

const props = defineProps<{
    data: DataItem[];
    total: number;
    size?: number;
    strokeWidth?: number;
}>();

const size = props.size || 200;
const strokeWidth = props.strokeWidth || 24;
const radius = (size - strokeWidth) / 2;
const center = size / 2;
const circumference = 2 * Math.PI * radius;

const segments = computed(() => {
    let currentOffset = 0;

    return props.data
        .filter((item) => item.count > 0)
        .map((item) => {
            const percentage =
                props.total > 0 ? (item.count / props.total) * 100 : 0;
            const dashOffset = (percentage / 100) * circumference;
            const startOffset = currentOffset;
            currentOffset += dashOffset;

            return {
                ...item,
                percentage,
                strokeDasharray: `${dashOffset} ${circumference}`,
                strokeDashoffset: -startOffset,
            };
        });
});

const toneColors: Record<string, string> = {
    emerald: '#10b981',
    amber: '#f59e0b',
    sky: '#0ea5e9',
    slate: '#64748b',
    rose: '#f43f5e',
    purple: '#a855f7',
};
</script>

<template>
    <div
        class="relative flex items-center justify-center"
        :style="{ width: `${size}px`, height: `${size}px` }"
    >
        <svg :width="size" :height="size" class="-rotate-90 transform">
            <!-- Background track -->
            <circle
                :cx="center"
                :cy="center"
                :r="radius"
                fill="transparent"
                stroke="currentColor"
                class="text-slate-100"
                :stroke-width="strokeWidth"
            />

            <!-- Segments -->
            <circle
                v-for="segment in segments"
                :key="segment.label"
                :cx="center"
                :cy="center"
                :r="radius"
                fill="transparent"
                :stroke="toneColors[segment.tone] || segment.tone"
                :stroke-width="strokeWidth"
                :stroke-dasharray="segment.strokeDasharray"
                :stroke-dashoffset="segment.strokeDashoffset"
                stroke-linecap="round"
                class="transition-all duration-1000 ease-out hover:opacity-80"
            />
        </svg>

        <!-- Center content -->
        <div
            class="absolute inset-0 flex flex-col items-center justify-center text-center"
        >
            <span class="text-3xl font-black tracking-tighter text-slate-900">{{
                total
            }}</span>
            <span
                class="text-[10px] font-black tracking-[0.2em] text-slate-400 uppercase"
                >Total Assets</span
            >
        </div>
    </div>
</template>
