<script setup lang="ts">
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps<{
    initialValue?: string;
    initialImage?: string;
    height?: number | string;
    class?: string;
    disabled?: boolean;
    readonly?: boolean;
}>();

const isLocked = computed(() => !!(props.disabled || props.readonly));

const containerHeight = computed(() => {
    if (typeof props.height === 'number') {
        return `${props.height}px`;
    }
    return props.height || '160px';
});

const containerRef = ref<HTMLDivElement | null>(null);
const canvasRef = ref<HTMLCanvasElement | null>(null);
let isDrawing = false;
let strokes: { x: number; y: number; time: number; type: string }[] = [];
let lastX = 0, lastY = 0;

let resizeObserver: ResizeObserver | null = null;

const getCtx = () => {
    if (!canvasRef.value) return null;
    return canvasRef.value.getContext('2d', { alpha: true });
};

const setupCanvas = () => {
    const canvas = canvasRef.value;
    if (!canvas) return;

    const dpr = window.devicePixelRatio || 1;
    const rect = canvas.getBoundingClientRect();
    
    // Fallback height to props or 160
    const targetHeight = typeof props.height === 'number' 
        ? props.height 
        : (parseInt(String(props.height || '160'), 10) || 160);

    const parentWidth = containerRef.value?.clientWidth || canvas.parentElement?.clientWidth || 0;
    const width = rect.width > 20 ? rect.width : (parentWidth > 20 ? parentWidth : 420);
    const height = rect.height > 20 ? rect.height : targetHeight;

    const newPixelWidth = Math.round(width * dpr);
    const newPixelHeight = Math.round(height * dpr);

    const sizeChanged = canvas.width !== newPixelWidth || canvas.height !== newPixelHeight;
    if (sizeChanged) {
        canvas.width = newPixelWidth;
        canvas.height = newPixelHeight;
    }

    const ctx = getCtx();
    if (ctx) {
        // Use setTransform to prevent compounding scale transformations
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        ctx.strokeStyle = '#003628';
        ctx.lineWidth = 2.5;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';

        if (strokes.length > 0) {
            redraw();
        } else {
            const initVal = props.initialValue || props.initialImage;
            if (initVal) {
                if (initVal.startsWith('[')) {
                    try {
                        strokes = JSON.parse(initVal);
                        redraw();
                    } catch (e) {
                        console.error('Failed to parse strokes', e);
                    }
                } else if (initVal.startsWith('data:') || initVal.startsWith('http') || initVal.startsWith('/')) {
                    const img = new Image();
                    img.crossOrigin = 'anonymous';
                    img.onload = () => {
                        const currentCtx = getCtx();
                        if (currentCtx) {
                            currentCtx.setTransform(dpr, 0, 0, dpr, 0, 0);
                            currentCtx.drawImage(img, 0, 0, width, height);
                        }
                    };
                    img.src = initVal;
                }
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
    
    ctx.strokeStyle = '#003628';
    ctx.lineWidth = 2.5;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';

    if (strokes.length === 0) return;

    ctx.beginPath();
    let currentPath = false;

    for (let i = 0; i < strokes.length; i++) {
        const p = strokes[i];
        if (p.type === 'start') {
            if (currentPath) ctx.stroke();
            ctx.beginPath();
            ctx.moveTo(p.x, p.y);
            ctx.arc(p.x, p.y, 1.25, 0, Math.PI * 2);
            ctx.fillStyle = '#003628';
            ctx.fill();
            ctx.beginPath();
            ctx.moveTo(p.x, p.y);
            currentPath = true;
        } else if (p.type === 'move') {
            if (!currentPath) {
                ctx.beginPath();
                ctx.moveTo(p.x, p.y);
                currentPath = true;
            } else {
                ctx.lineTo(p.x, p.y);
            }
        }
    }
    if (currentPath) {
        ctx.stroke();
    }
};

onMounted(() => {
    setupCanvas();

    if (containerRef.value && typeof ResizeObserver !== 'undefined') {
        resizeObserver = new ResizeObserver(() => {
            setupCanvas();
        });
        resizeObserver.observe(containerRef.value);
    }

    window.addEventListener('resize', setupCanvas);
});

onBeforeUnmount(() => {
    if (resizeObserver) {
        resizeObserver.disconnect();
        resizeObserver = null;
    }
    window.removeEventListener('resize', setupCanvas);
});

// Watch for initial value or image updates (e.g. after async fetch)
watch(
    () => [props.initialImage, props.initialValue],
    ([newImg, newVal]) => {
        if ((newImg || newVal) && strokes.length === 0) {
            setupCanvas();
        }
    }
);

const getPos = (e: MouseEvent | TouchEvent | PointerEvent) => {
    const canvas = canvasRef.value!;
    const rect = canvas.getBoundingClientRect();
    if (typeof TouchEvent !== 'undefined' && e instanceof TouchEvent && e.touches.length > 0) {
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

const start = (e: PointerEvent) => {
    if (isLocked.value) return;
    e.preventDefault();
    const canvas = canvasRef.value;
    if (!canvas) return;
    try {
        canvas.setPointerCapture(e.pointerId);
    } catch (_) {}

    isDrawing = true;
    const p = getPos(e);
    lastX = p.x;
    lastY = p.y;
    strokes.push({ ...p, time: Date.now(), type: 'start' });

    const ctx = getCtx();
    if (ctx) {
        ctx.strokeStyle = '#003628';
        ctx.lineWidth = 2.5;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.beginPath();
        ctx.arc(p.x, p.y, 1.25, 0, Math.PI * 2);
        ctx.fillStyle = '#003628';
        ctx.fill();
    }
};

const draw = (e: PointerEvent) => {
    if (!isDrawing || isLocked.value) return;
    e.preventDefault();
    const ctx = getCtx();
    if (!ctx) return;

    ctx.strokeStyle = '#003628';
    ctx.lineWidth = 2.5;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';

    const p = getPos(e);
    const dist = Math.sqrt(Math.pow(p.x - lastX, 2) + Math.pow(p.y - lastY, 2));
    if (dist < 1) return;

    ctx.beginPath();
    ctx.moveTo(lastX, lastY);
    ctx.lineTo(p.x, p.y);
    ctx.stroke();

    lastX = p.x;
    lastY = p.y;
    strokes.push({ ...p, time: Date.now(), type: 'move' });
};

const stop = (e?: PointerEvent) => {
    if (isDrawing && e && canvasRef.value) {
        try {
            canvasRef.value.releasePointerCapture(e.pointerId);
        } catch (_) {}
    }
    isDrawing = false;
};

const clear = () => {
    if (isLocked.value) return;
    const canvas = canvasRef.value!;
    const ctx = getCtx();
    const rect = canvas.getBoundingClientRect();
    if (ctx) {
        ctx.clearRect(0, 0, rect.width, rect.height);
    }
    strokes = [];
};

const isEmpty = () => {
    return strokes.length === 0 && !props.initialImage && !props.initialValue;
};

const getSignature = () => {
    return strokes.length > 0 ? JSON.stringify(strokes) : (props.initialImage || '');
};

const toDataURL = (type = 'image/png') => {
    const canvas = canvasRef.value;
    if (!canvas || isEmpty()) return null;
    return canvas.toDataURL(type);
};

const saveSignature = () => {
    if (strokes.length === 0 && props.initialImage) {
        return props.initialImage;
    }
    return toDataURL() || getSignature();
};

const clearSignature = () => {
    clear();
};

defineExpose({ clear, clearSignature, getSignature, isEmpty, saveSignature, toDataURL, setupCanvas });
</script>

<template>
    <div 
        ref="containerRef"
        class="relative w-full overflow-hidden rounded-xl border transition-colors"
        :class="[
            isLocked 
                ? 'bg-slate-50 border-slate-200 cursor-not-allowed select-none' 
                : 'bg-white border-slate-200 hover:border-slate-300'
        ]"
        :style="{ height: containerHeight, minHeight: containerHeight }"
    >
        <canvas
            ref="canvasRef"
            :class="[
                $props.class, 
                isLocked ? 'cursor-not-allowed pointer-events-none' : 'cursor-crosshair',
                'w-full h-full block select-none'
            ]"
            style="touch-action: none;"
            :style="{ height: containerHeight, minHeight: containerHeight, width: '100%' }"
            @pointerdown="start"
            @pointermove="draw"
            @pointerup="stop"
            @pointerleave="stop"
            @pointercancel="stop"
        />
        <button
            v-if="!isLocked && strokes.length > 0"
            type="button"
            @click="clear"
            class="absolute top-2 right-2 rounded-lg px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-400 hover:text-red-500 hover:bg-red-50 transition-all active:scale-95 z-10 cursor-pointer"
        >
            Hapus
        </button>
        <div
            v-if="isLocked && isEmpty()"
            class="absolute inset-0 flex items-center justify-center text-xs text-slate-400 italic pointer-events-none"
        >
            (Tidak ada tanda tangan)
        </div>
    </div>
</template>
