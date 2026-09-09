<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight, FileCheck, FileText, Wrench } from 'lucide-vue-next';

export interface PipelineStage {
    label: string;
    sublabel: string;
    count: number;
    tone: 'amber' | 'sky' | 'emerald' | 'purple';
}

export interface ModuleApproval {
    key?: string;
    label: string;
    title?: string;
    description?: string;
    total: number;
    href: string;
    stages?: PipelineStage[];
    // Legacy fallback
    pending?: number;
    approved?: number;
    finalized?: number;
}

defineProps<{
    moduleApprovals: ModuleApproval[];
}>();

const getModuleIcon = (key?: string) => {
    switch (key) {
        case 'peminjaman':
            return FileCheck;
        case 'inspection':
            return Wrench;
        case 'stb':
        default:
            return FileText;
    }
};

const getStageStyle = (tone: string) => {
    switch (tone) {
        case 'amber':
            return {
                box: 'border-amber-200/80 bg-amber-50/70',
                title: 'text-amber-800',
                count: 'text-amber-950',
                sub: 'text-amber-700',
                dot: 'bg-amber-500',
                bar: 'bg-amber-500',
            };
        case 'sky':
            return {
                box: 'border-sky-200/80 bg-sky-50/70',
                title: 'text-sky-800',
                count: 'text-sky-950',
                sub: 'text-sky-700',
                dot: 'bg-sky-500',
                bar: 'bg-sky-500',
            };
        case 'purple':
            return {
                box: 'border-purple-200/80 bg-purple-50/70',
                title: 'text-purple-800',
                count: 'text-purple-950',
                sub: 'text-purple-700',
                dot: 'bg-purple-500',
                bar: 'bg-purple-500',
            };
        case 'emerald':
        default:
            return {
                box: 'border-emerald-200/80 bg-emerald-50/70',
                title: 'text-emerald-800',
                count: 'text-emerald-950',
                sub: 'text-emerald-700',
                dot: 'bg-emerald-500',
                bar: 'bg-emerald-500',
            };
    }
};

const resolveStages = (item: ModuleApproval): PipelineStage[] => {
    if (item.stages && item.stages.length > 0) {
        return item.stages;
    }

    return [
        {
            label: 'Waiting Approval',
            sublabel: 'Butuh Action',
            count: item.pending ?? 0,
            tone: 'amber',
        },
        {
            label: 'Approved',
            sublabel: 'Proses Selesai',
            count: item.approved ?? 0,
            tone: 'sky',
        },
        {
            label: 'Selesai',
            sublabel: 'Arsip Dokumen',
            count: item.finalized ?? 0,
            tone: 'emerald',
        },
    ];
};
</script>

<template>
    <section class="rounded-2xl border border-slate-100 bg-white p-5 shadow-xs">
        <div
            class="mb-4 flex flex-col justify-between gap-2 sm:flex-row sm:items-center"
        >
            <div>
                <div class="flex items-center gap-2">
                    <div
                        class="flex h-6 w-6 items-center justify-center rounded-lg bg-[#003628]/10 text-[#003628]"
                    >
                        <FileCheck class="size-3.5" />
                    </div>
                    <h2 class="text-sm font-bold tracking-tight text-slate-900">
                        Status Dokumen
                    </h2>
                </div>
                <p class="mt-0.5 text-xs text-slate-400">
                    Pantau status STB, peminjaman, dan inspeksi.
                </p>
            </div>
            <div
                class="flex flex-wrap items-center gap-2 text-xs font-semibold text-slate-500"
            >
                <span
                    class="inline-flex items-center gap-1.5 rounded-md border border-amber-200/60 bg-amber-50 px-2 py-0.5 text-amber-700"
                >
                    <span class="h-1.5 w-1.5 rounded-full bg-amber-500" />
                    Menunggu
                </span>
                <span
                    class="inline-flex items-center gap-1.5 rounded-md border border-sky-200/60 bg-sky-50 px-2 py-0.5 text-sky-700"
                >
                    <span class="h-1.5 w-1.5 rounded-full bg-sky-500" />
                    Berjalan
                </span>
                <span
                    class="inline-flex items-center gap-1.5 rounded-md border border-emerald-200/60 bg-emerald-50 px-2 py-0.5 text-emerald-700"
                >
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500" />
                    Selesai
                </span>
            </div>
        </div>

        <!-- Dynamic Grid: 1 col on mobile, 2 cols on tablet, 3 cols on desktop -->
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div
                v-for="item in moduleApprovals"
                :key="item.label"
                class="flex flex-col justify-between rounded-xl border border-slate-100 bg-slate-50/50 p-4 transition-all hover:border-slate-200 hover:bg-white hover:shadow-sm"
            >
                <div>
                    <div
                        class="flex items-center justify-between border-b border-slate-100 pb-3"
                    >
                        <div class="flex items-center gap-2.5">
                            <div
                                class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#003628] text-white"
                            >
                                <component
                                    :is="getModuleIcon(item.key)"
                                    class="size-4"
                                />
                            </div>
                            <div>
                                <h3
                                    class="text-xs font-bold tracking-wider text-slate-900 uppercase"
                                >
                                    {{ item.title || 'DOKUMEN ' + item.label }}
                                </h3>
                                <span class="text-xs text-slate-400">
                                    {{ item.total }} dokumen
                                </span>
                            </div>
                        </div>
                        <Link
                            :href="item.href"
                            class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 shadow-2xs transition-colors hover:border-[#003628] hover:text-[#003628]"
                        >
                            Kelola <ArrowUpRight class="size-3" />
                        </Link>
                    </div>

                    <!-- Pipeline Stages (3 Columns) -->
                    <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                        <div
                            v-for="(stage, sIdx) in resolveStages(item)"
                            :key="sIdx"
                            class="rounded-lg border p-2.5 transition-all"
                            :class="getStageStyle(stage.tone).box"
                        >
                            <div
                                class="flex items-center justify-center gap-1 text-[10px] font-bold tracking-wider uppercase"
                                :class="getStageStyle(stage.tone).title"
                            >
                                <span
                                    class="h-1.5 w-1.5 rounded-full"
                                    :class="getStageStyle(stage.tone).dot"
                                />
                                <span class="truncate">{{ stage.label }}</span>
                            </div>
                            <div
                                class="mt-1 text-xl font-black"
                                :class="getStageStyle(stage.tone).count"
                            >
                                {{ stage.count }}
                            </div>
                            <div
                                class="mt-0.5 truncate text-[10px] font-medium"
                                :class="getStageStyle(stage.tone).sub"
                                :title="stage.sublabel"
                            >
                                {{ stage.sublabel }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Mini Progress Ratio Bar -->
                <div class="mt-3">
                    <div
                        class="flex h-2 w-full overflow-hidden rounded-full bg-slate-200/80"
                    >
                        <div
                            v-for="(stage, sIdx) in resolveStages(item)"
                            :key="sIdx"
                            :class="getStageStyle(stage.tone).bar"
                            class="transition-all duration-500"
                            :style="{
                                width: `${item.total > 0 ? (stage.count / item.total) * 100 : 0}%`,
                            }"
                            :title="`${stage.label}: ${stage.count}`"
                        />
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
