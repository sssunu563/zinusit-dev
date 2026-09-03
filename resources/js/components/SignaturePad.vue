<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, defineExpose } from 'vue';

const props = defineProps<{
    initialValue?: string;
    height?: number | string;
    class?: string;
}>();

const canvasRef = ref<HTMLCanvasElement | null>(null);
let isDrawing = false;
let strokes: { x: number; y: number; time: number; type: string }[] = [];
let lastX = 0, lastY = 0;

const getCtx = () => {
    if (!canvasRef.value) return null;
    return canvasRef.value.getContext('2d', { alpha: true });
};

const setupCanvas = () => {
    const canvas = canvasRef.value;
    if (!canvas) return;

    const dpr = window.devicePixelRatio || 1;
    const rect = canvas.getBoundingClientRect();

    canvas.width = rect.width * dpr;
    canvas.height = rect.height * dpr;

    const ctx = getCtx();
    if (ctx) {
        ctx.scale(dpr, dpr);
        ctx.strokeStyle = '#003628';
        ctx.lineWidth = 2.5;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';

        if (props.initialValue) {
            if (props.initialValue.startsWith('[')) {
                try {
                    strokes = JSON.parse(props.initialValue);
                    redraw();
                } catch (e) {
                    console.error('Failed to parse strokes', e);
                }
            } else if (props.initialValue.startsWith('data:')) {
                const img = new Image();
                img.onload = () => ctx.drawImage(img, 0, 0, rect.width, rect.height);
                img.src = props.initialValue;
            }
        }
    }
};

const redraw = () => {
    const ctx = getCtx();
    if (!ctx) return;
    
    const canvas = canvasRef.value!;
    const rect = canvas.getBoundingClientRect();
    ctx.clearRect(0, 0, rect.width, rect.height);
    
    ctx.beginPath();
    strokes.forEach((p) => {
        if (p.type === 'start') {
            ctx.moveTo(p.x, p.y);
        } else {
            ctx.lineTo(p.x, p.y);
            ctx.stroke();
            ctx.beginPath();
            ctx.moveTo(p.x, p.y);
        }
    });
};

onMounted(() => {
    setupCanvas();
    window.addEventListener('resize', setupCanvas);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', setupCanvas);
});

const getPos = (e: MouseEvent | TouchEvent) => {
    const canvas = canvasRef.value!;
    const rect = canvas.getBoundingClientRect();
    if (e instanceof TouchEvent) {
        return {
            x: e.touches[0].clientX - rect.left,
            y: e.touches[0].clientY - rect.top,
        };
    }
    return {
        x: (e as MouseEvent).clientX - rect.left,
        y: (e as MouseEvent).clientY - rect.top,
    };
};

const start = (e: MouseEvent | TouchEvent) => {
    e.preventDefault();
    isDrawing = true;
    const p = getPos(e);
    lastX = p.x;
    lastY = p.y;
    strokes.push({ ...p, time: Date.now(), type: 'start' });
};

const draw = (e: MouseEvent | TouchEvent) => {
    if (!isDrawing) return;
    e.preventDefault();
    const ctx = getCtx();
    if (!ctx) return;

    const p = getPos(e);
    
    // Minimal movement threshold to reduce redundant data points
    const dist = Math.sqrt(Math.pow(p.x - lastX, 2) + Math.pow(p.y - lastY, 2));
    if (dist < 1.2) return;

    ctx.beginPath();
    ctx.moveTo(lastX, lastY);
    ctx.lineTo(p.x, p.y);
    ctx.stroke();

    lastX = p.x;
    lastY = p.y;
    strokes.push({ ...p, time: Date.now(), type: 'move' });
};

const stop = () => {
    isDrawing = false;
};

const clear = () => {
    const canvas = canvasRef.value!;
    const ctx = getCtx();
    const rect = canvas.getBoundingClientRect();
    if (ctx) {
        ctx.clearRect(0, 0, rect.width, rect.height);
    }
    strokes = [];
};

const isEmpty = () => strokes.length === 0;

const getSignature = () => {
    if (isEmpty()) return null;
    return JSON.stringify(strokes);
};

defineExpose({ clear, getSignature, isEmpty });
</script>

<template>
    <div class="relative w-full overflow-hidden bg-white rounded-xl border border-slate-200">
        <canvas
            ref="canvasRef"
            :class="[$props.class, 'touch-none cursor-crosshair w-full block']"
            :style="{ height: typeof height === 'number' ? height + 'px' : height || '180px' }"
            @mousedown="start"
            @mousemove="draw"
            @mouseup="stop"
            @mouseleave="stop"
            @touchstart="start"
            @touchmove="draw"
            @touchend="stop"
        />
        <button
            v-if="strokes.length > 0"
            type="button"
            @click="clear"
            class="absolute top-2 right-2 rounded-lg px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-400 hover:text-red-500 hover:bg-red-50 transition-all active:scale-95"
        >
            Hapus
        </button>
    </div>
</template>
