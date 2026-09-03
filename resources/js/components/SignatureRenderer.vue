<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    data: string | null;
    class?: string;
}>();

const svgPath = computed(() => {
    if (!props.data || !props.data.startsWith('[')) return null;

    try {
        const strokes = JSON.parse(props.data);
        if (!strokes || strokes.length === 0) return null;

        let path = '';
        strokes.forEach((p: any) => {
            if (p.type === 'start') {
                path += `M ${p.x} ${p.y} `;
            } else {
                path += `L ${p.x} ${p.y} `;
            }
        });

        // Find bounds for viewBox
        let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
        strokes.forEach((p: any) => {
            minX = Math.min(minX, p.x);
            minY = Math.min(minY, p.y);
            maxX = Math.max(maxX, p.x);
            maxY = Math.max(maxY, p.y);
        });

        const width = (maxX - minX) || 1;
        const height = (maxY - minY) || 1;
        
        return {
            path,
            viewBox: `${minX - 5} ${minY - 5} ${width + 10} ${height + 10}`
        };
    } catch (e) {
        console.error('Failed to parse strokes in SignatureRenderer', e);
        return null;
    }
});
</script>

<template>
    <div :class="['relative flex items-center justify-center overflow-hidden', $props.class]">
        <template v-if="data">
            <svg 
                v-if="svgPath" 
                :viewBox="svgPath.viewBox" 
                preserveAspectRatio="xMidYMid meet"
                class="w-full h-full"
            >
                <path 
                    :d="svgPath.path" 
                    fill="none" 
                    stroke="#003628" 
                    stroke-width="2.5" 
                    stroke-linecap="round" 
                    stroke-linejoin="round" 
                />
            </svg>
            <img 
                v-else 
                :src="data.startsWith('data:') ? data : `/storage/${data}`" 
                class="max-h-full max-w-full object-contain"
            />
        </template>
        <div v-else class="text-slate-300 italic text-[10px] uppercase font-black tracking-widest">
            Belum TTD
        </div>
    </div>
</template>
