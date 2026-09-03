<script setup lang="ts">
import { computed } from 'vue';

interface DataPoint {
    label: string;
    stb: number;
    peminjaman: number;
    tickets: number;
}

const props = defineProps<{
    data: DataPoint[];
    height?: number;
}>();

const height = props.height || 200;
const width = 800; // Reference width for SVG coordinate system
const padding = { top: 16, right: 16, bottom: 28, left: 40 };

const chartWidth = width - padding.left - padding.right;
const chartHeight = height - padding.top - padding.bottom;

const maxValue = computed(() => {
    return Math.max(
        ...props.data.flatMap((d) => [d.stb, d.peminjaman, d.tickets]),
        5,
    );
});

const getX = (index: number) => {
    return padding.left + index * (chartWidth / (props.data.length - 1));
};

const getY = (value: number) => {
    return padding.top + (chartHeight - (value / maxValue.value) * chartHeight);
};

const createPath = (
    key: keyof Pick<DataPoint, 'stb' | 'peminjaman' | 'tickets'>,
) => {
    if (props.data.length < 2) return '';

    const points = props.data.map((d, i) => ({
        x: getX(i),
        y: getY(d[key] as number),
    }));

    // Create smooth curve path
    let path = `M ${points[0].x} ${points[0].y}`;

    for (let i = 0; i < points.length - 1; i++) {
        const p0 = points[i];
        const p1 = points[i + 1];
        const cp1x = p0.x + (p1.x - p0.x) / 2;
        path += ` C ${cp1x} ${p0.y}, ${cp1x} ${p1.y}, ${p1.x} ${p1.y}`;
    }

    return path;
};

const createAreaPath = (
    key: keyof Pick<DataPoint, 'stb' | 'peminjaman' | 'tickets'>,
) => {
    const linePath = createPath(key);
    if (!linePath) return '';

    const lastX = getX(props.data.length - 1);
    const firstX = getX(0);
    const bottomY = padding.top + chartHeight;

    return `${linePath} L ${lastX} ${bottomY} L ${firstX} ${bottomY} Z`;
};

const series = [
    { key: 'tickets' as const, color: '#10b981', label: 'Tickets' },
    { key: 'stb' as const, color: '#0ea5e9', label: 'STB' },
    { key: 'peminjaman' as const, color: '#f59e0b', label: 'Peminjaman' },
];
</script>

<template>
    <div class="w-full">
        <svg :viewBox="`0 0 ${width} ${height}`" class="overflow-visible">
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
                    <stop
                        offset="0%"
                        :style="{ stopColor: s.color, stopOpacity: 0.2 }"
                    />
                    <stop
                        offset="100%"
                        :style="{ stopColor: s.color, stopOpacity: 0 }"
                    />
                </linearGradient>
            </defs>

            <!-- Grid Lines -->
            <line
                v-for="i in 4"
                :key="i"
                :x1="padding.left"
                :y1="padding.top + (chartHeight / 4) * i"
                :x2="width - padding.right"
                :y2="padding.top + (chartHeight / 4) * i"
                stroke="#e2e8f0"
                stroke-width="1"
                stroke-dasharray="6 4"
            />

            <!-- Y Axis Labels -->
            <text
                v-for="i in 4"
                :key="`y-${i}`"
                :x="padding.left - 8"
                :y="padding.top + (chartHeight / 4) * (i - 1) + 4"
                text-anchor="end"
                fill="#94a3b8"
                font-size="9"
                font-weight="bold"
            >
                {{ Math.round(maxValue - (maxValue / 4) * (i - 1)) }}
            </text>

            <!-- Areas -->
            <path
                v-for="s in series"
                :key="`area-${s.key}`"
                :d="createAreaPath(s.key)"
                :fill="`url(#grad-${s.key})`"
                class="transition-all duration-1000 ease-in-out"
            />

            <!-- Lines -->
            <path
                v-for="s in series"
                :key="`line-${s.key}`"
                :d="createPath(s.key)"
                fill="transparent"
                :stroke="s.color"
                stroke-width="3"
                stroke-linecap="round"
                stroke-linejoin="round"
                class="transition-all duration-1000 ease-in-out"
            />

            <!-- X Axis Labels -->
            <text
                v-for="(point, i) in data"
                :key="`label-${i}`"
                :x="getX(i)"
                :y="height"
                text-anchor="middle"
                fill="#64748b"
                font-size="10"
                font-weight="bold"
                class="tracking-tighter uppercase"
            >
                {{ point.label }}
            </text>
        </svg>
    </div>
</template>
