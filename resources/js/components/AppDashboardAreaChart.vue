<script setup lang="ts">
import { computed, ref } from 'vue';

export interface ChartDataPoint {
    label: string;
    stb: number;
    peminjaman: number;
    tickets: number;
    inspections?: number;
}

const props = withDefaults(
    defineProps<{
        data: ChartDataPoint[];
        height?: number;
    }>(),
    {
        height: 240,
    },
);

const width = 800;
const padding = { top: 20, right: 24, bottom: 32, left: 44 };

const chartWidth = width - padding.left - padding.right;
const chartHeight = props.height - padding.top - padding.bottom;

const activeSeries = ref<string>('all');
const hoveredIndex = ref<number | null>(null);

const series = [
    { key: 'tickets' as const, color: '#10b981', label: 'Tiket Helpdesk', bg: 'bg-emerald-500' },
    { key: 'stb' as const, color: '#0ea5e9', label: 'Serah Terima (STB)', bg: 'bg-sky-500' },
    { key: 'peminjaman' as const, color: '#f59e0b', label: 'Peminjaman', bg: 'bg-amber-500' },
];

const visibleSeries = computed(() => {
    if (activeSeries.value === 'all') return series;
    return series.filter((s) => s.key === activeSeries.value);
});

const maxValue = computed(() => {
    if (!props.data || props.data.length === 0) return 10;
    const values = props.data.flatMap((d) => [d.stb || 0, d.peminjaman || 0, d.tickets || 0]);
    const max = Math.max(...values, 5);
    return Math.ceil(max * 1.15); // Add 15% breathing room at top
});

const getX = (index: number) => {
    if (props.data.length <= 1) return padding.left + chartWidth / 2;
    return padding.left + index * (chartWidth / (props.data.length - 1));
};

const getY = (value: number) => {
    return padding.top + (chartHeight - (value / maxValue.value) * chartHeight);
};

const createPath = (key: keyof Pick<ChartDataPoint, 'stb' | 'peminjaman' | 'tickets'>) => {
    if (!props.data || props.data.length < 2) return '';

    const points = props.data.map((d, i) => ({
        x: getX(i),
        y: getY(Number(d[key]) || 0),
    }));

    let path = `M ${points[0].x} ${points[0].y}`;

    for (let i = 0; i < points.length - 1; i++) {
        const p0 = points[i];
        const p1 = points[i + 1];
        const cp1x = p0.x + (p1.x - p0.x) / 2;
        path += ` C ${cp1x} ${p0.y}, ${cp1x} ${p1.y}, ${p1.x} ${p1.y}`;
    }

    return path;
};

const createAreaPath = (key: keyof Pick<ChartDataPoint, 'stb' | 'peminjaman' | 'tickets'>) => {
    const linePath = createPath(key);
    if (!linePath || !props.data.length) return '';

    const lastX = getX(props.data.length - 1);
    const firstX = getX(0);
    const bottomY = padding.top + chartHeight;

    return `${linePath} L ${lastX} ${bottomY} L ${firstX} ${bottomY} Z`;
};

const handleMouseMove = (event: MouseEvent) => {
    const rect = (event.currentTarget as HTMLElement).getBoundingClientRect();
    const relativeX = event.clientX - rect.left;
    const svgX = (relativeX / rect.width) * width;

    // Find nearest point
    let nearestIndex = 0;
    let minDistance = Infinity;

    props.data.forEach((_, i) => {
        const px = getX(i);
        const dist = Math.abs(px - svgX);
        if (dist < minDistance) {
            minDistance = dist;
            nearestIndex = i;
        }
    });

    hoveredIndex.value = nearestIndex;
};

const handleMouseLeave = () => {
    hoveredIndex.value = null;
};

const hoveredPoint = computed(() => {
    if (hoveredIndex.value === null || !props.data[hoveredIndex.value]) return null;
    return {
        index: hoveredIndex.value,
        data: props.data[hoveredIndex.value],
        x: getX(hoveredIndex.value),
    };
});
</script>

<template>
    <div class="space-y-3">
        <!-- Series Filter Controls -->
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-3">
            <div class="flex items-center gap-1.5">
                <button
                    type="button"
                    @click="activeSeries = 'all'"
                    class="rounded-lg px-2.5 py-1 text-[11px] font-bold transition-all"
                    :class="
                        activeSeries === 'all'
                            ? 'bg-[#003628] text-white shadow-sm'
                            : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                    "
                >
                    Semua
                </button>
                <button
                    v-for="s in series"
                    :key="s.key"
                    type="button"
                    @click="activeSeries = activeSeries === s.key ? 'all' : s.key"
                    class="flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-[11px] font-bold transition-all"
                    :class="
                        activeSeries === s.key
                            ? 'bg-slate-900 text-white shadow-sm'
                            : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                    "
                >
                    <span class="h-2 w-2 rounded-full" :style="{ backgroundColor: s.color }" />
                    <span>{{ s.label }}</span>
                </button>
            </div>

            <div class="text-[11px] font-medium text-slate-400">
                <span class="hidden sm:inline">Sorot grafik untuk melihat rincian bulan</span>
            </div>
        </div>

        <!-- SVG Chart Container -->
        <div
            class="relative w-full cursor-crosshair select-none"
            @mousemove="handleMouseMove"
            @mouseleave="handleMouseLeave"
        >
            <svg :viewBox="`0 0 ${width} ${height}`" class="w-full overflow-visible">
                <defs>
                    <linearGradient
                        v-for="s in series"
                        :key="`grad-${s.key}`"
                        :id="`grad-${s.key}`"
                        x1="0"
                        y1="0"
                        x2="0"
                        y2="1"
                    >
                        <stop offset="0%" :style="{ stopColor: s.color, stopOpacity: 0.22 }" />
                        <stop offset="100%" :style="{ stopColor: s.color, stopOpacity: 0.01 }" />
                    </linearGradient>
                </defs>

                <!-- Horizontal Grid Lines -->
                <line
                    v-for="i in 4"
                    :key="`grid-${i}`"
                    :x1="padding.left"
                    :y1="padding.top + (chartHeight / 4) * i"
                    :x2="width - padding.right"
                    :y2="padding.top + (chartHeight / 4) * i"
                    stroke="#f1f5f9"
                    stroke-width="1.2"
                    stroke-dasharray="4 4"
                />

                <!-- Y Axis Labels -->
                <text
                    v-for="i in 4"
                    :key="`y-axis-${i}`"
                    :x="padding.left - 10"
                    :y="padding.top + (chartHeight / 4) * (i - 1) + 4"
                    text-anchor="end"
                    fill="#94a3b8"
                    font-size="10"
                    font-weight="600"
                >
                    {{ Math.round(maxValue - (maxValue / 4) * (i - 1)) }}
                </text>

                <!-- Area Fills -->
                <path
                    v-for="s in visibleSeries"
                    :key="`area-${s.key}`"
                    :d="createAreaPath(s.key)"
                    :fill="`url(#grad-${s.key})`"
                    class="transition-all duration-300 ease-out"
                />

                <!-- Curved Lines -->
                <path
                    v-for="s in visibleSeries"
                    :key="`line-${s.key}`"
                    :d="createPath(s.key)"
                    fill="transparent"
                    :stroke="s.color"
                    stroke-width="2.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    class="transition-all duration-300 ease-out"
                />

                <!-- Hover Guide Line & Indicators -->
                <g v-if="hoveredPoint">
                    <line
                        :x1="hoveredPoint.x"
                        :y1="padding.top"
                        :x2="hoveredPoint.x"
                        :y2="padding.top + chartHeight"
                        stroke="#0f172a"
                        stroke-opacity="0.25"
                        stroke-width="1.5"
                        stroke-dasharray="3 3"
                    />

                    <!-- Dots on curves -->
                    <circle
                        v-for="s in visibleSeries"
                        :key="`dot-${s.key}`"
                        :cx="hoveredPoint.x"
                        :cy="getY(Number(hoveredPoint.data[s.key]) || 0)"
                        :r="5"
                        :fill="s.color"
                        stroke="#ffffff"
                        stroke-width="2.5"
                        class="shadow-md transition-all duration-150"
                    />
                </g>

                <!-- X Axis Month Labels -->
                <text
                    v-for="(point, i) in data"
                    :key="`x-label-${i}`"
                    :x="getX(i)"
                    :y="height - 6"
                    text-anchor="middle"
                    :fill="hoveredIndex === i ? '#003628' : '#64748b'"
                    :font-weight="hoveredIndex === i ? 'bold' : '600'"
                    font-size="11"
                    class="transition-colors"
                >
                    {{ point.label }}
                </text>
            </svg>

            <!-- Floating Hover Tooltip -->
            <div
                v-if="hoveredPoint"
                class="pointer-events-none absolute top-3 z-30 rounded-xl border border-slate-200/80 bg-white/95 px-3.5 py-2.5 shadow-xl backdrop-blur-md transition-all"
                :style="{
                    left: `${Math.min(Math.max((hoveredPoint.x / width) * 100, 15), 85)}%`,
                    transform: 'translateX(-50%)',
                }"
            >
                <div class="mb-1.5 border-b border-slate-100 pb-1 text-center text-[11px] font-bold text-slate-800">
                    Bulan {{ hoveredPoint.data.label }}
                </div>
                <div class="space-y-1 text-xs">
                    <div
                        v-for="s in series"
                        :key="`tooltip-${s.key}`"
                        class="flex items-center justify-between gap-4 font-semibold"
                        :class="activeSeries !== 'all' && activeSeries !== s.key ? 'opacity-40' : ''"
                    >
                        <span class="flex items-center gap-1.5 text-slate-600">
                            <span class="h-2 w-2 rounded-full" :style="{ backgroundColor: s.color }" />
                            {{ s.label }}
                        </span>
                        <span class="font-bold text-slate-900">
                            {{ hoveredPoint.data[s.key] || 0 }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
