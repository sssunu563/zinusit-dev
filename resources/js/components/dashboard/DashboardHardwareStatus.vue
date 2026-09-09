<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight, Monitor } from 'lucide-vue-next';

interface HardwareStatus {
    label: string;
    count: number;
    share: number;
    href: string;
}

interface AssetHighlight {
    label: string;
    value: number;
    detail: string;
    tone: string;
}

defineProps<{
    hardwareStatuses: HardwareStatus[];
    highlights?: AssetHighlight[];
}>();

const getStatusColor = (label: string) => {
    const l = label.toLowerCase();
    if (
        l.includes('ready') ||
        l.includes('available') ||
        l.includes('deployable')
    ) {
        return {
            bar: 'bg-emerald-500',
            badge: 'text-emerald-700 bg-emerald-50',
        };
    }
    if (
        l.includes('deployed') ||
        l.includes('terpakai') ||
        l.includes('assigned')
    ) {
        return { bar: 'bg-sky-500', badge: 'text-sky-700 bg-sky-50' };
    }
    if (
        l.includes('repair') ||
        l.includes('rusak') ||
        l.includes('maintenance')
    ) {
        return { bar: 'bg-amber-500', badge: 'text-amber-700 bg-amber-50' };
    }
    if (l.includes('archived') || l.includes('scrap') || l.includes('lost')) {
        return { bar: 'bg-rose-500', badge: 'text-rose-700 bg-rose-50' };
    }
    return { bar: 'bg-slate-500', badge: 'text-slate-700 bg-slate-100' };
};
</script>

<template>
    <section class="rounded-2xl border border-slate-100 bg-white p-5 shadow-xs">
        <div class="mb-4 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div
                    class="flex h-6 w-6 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700"
                >
                    <Monitor class="size-3.5" />
                </div>
                <div>
                    <h2 class="text-sm font-bold tracking-tight text-slate-900">
                        Status Perangkat
                    </h2>
                    <p class="text-xs text-slate-400">
                        Kondisi dan penggunaan perangkat.
                    </p>
                </div>
            </div>
            <Link
                href="/asset?type=assets"
                class="inline-flex items-center gap-1 text-xs font-semibold text-[#003628] hover:underline"
            >
                Lihat Semua <ArrowUpRight class="size-3" />
            </Link>
        </div>

        <!-- Highlights Cards Row if present -->
        <div
            v-if="highlights && highlights.length"
            class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-4"
        >
            <div
                v-for="h in highlights"
                :key="h.label"
                class="rounded-xl border border-slate-100 bg-slate-50/40 p-3"
            >
                <div
                    class="text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                >
                    {{ h.label }}
                </div>
                <div class="mt-1 text-xl font-black text-slate-900">
                    {{ h.value }}
                </div>
                <div
                    class="mt-1 line-clamp-1 text-[11px] text-slate-500"
                    :title="h.detail"
                >
                    {{ h.detail }}
                </div>
            </div>
        </div>

        <!-- Status Bars List -->
        <div class="space-y-3">
            <div
                v-for="item in hardwareStatuses"
                :key="item.label"
                class="group rounded-xl p-2 transition-colors hover:bg-slate-50/80"
            >
                <div
                    class="mb-1.5 flex items-center justify-between text-xs font-semibold"
                >
                    <div class="flex items-center gap-2">
                        <span
                            class="rounded-md px-2 py-0.5 text-[11px] font-bold"
                            :class="getStatusColor(item.label).badge"
                        >
                            {{ item.label }}
                        </span>
                        <span class="font-normal text-slate-400"
                            >({{ item.share }}%)</span
                        >
                    </div>
                    <span class="font-bold text-slate-900">
                        {{ item.count }} unit
                    </span>
                </div>
                <!-- Progress Bar -->
                <div
                    class="h-2 w-full overflow-hidden rounded-full bg-slate-100"
                >
                    <div
                        class="h-full rounded-full transition-all duration-700"
                        :class="getStatusColor(item.label).bar"
                        :style="{ width: `${Math.max(item.share, 2)}%` }"
                    />
                </div>
            </div>

            <div
                v-if="!hardwareStatuses.length"
                class="py-6 text-center text-xs font-medium text-slate-400"
            >
                Belum ada data status perangkat
            </div>
        </div>
    </section>
</template>
