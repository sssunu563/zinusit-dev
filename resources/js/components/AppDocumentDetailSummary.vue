<script setup lang="ts">
import { 
    LucideActivity as ActivityIcon, 
    LucideCalendar as CalendarIcon, 
    LucideShieldCheck as ShieldCheck,
    LucideMapPin as MapPinIcon,
    LucideBuilding as BuildingIcon,
    LucideHash as HashIcon,
    LucideFileText as FileTextIcon,
    LucideClock as ClockIcon,
    LucideInfo as InfoIcon
} from 'lucide-vue-next';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        documentLabel: string;
        detailDateLabel: string;
        deliverDate: string;
        useDate: string;
        location: string;
        department: string;
        building: string;
        batchNo: string;
        reqDocNo: string;
        poDocNo: string;
        updatedAt: string;
        completedAt: string;
        relatedReturnedAt: string;
        isCompleted: boolean;
        isCancelled: boolean;
        isReturnFlow: boolean;
        isLoanOut: boolean;
        sectionKicker?: string;
        sectionTitle?: string;
        sectionCopy?: string;
    }>(),
    {
        sectionKicker: 'Operational Intelligence',
        sectionTitle: 'Summary of Document Lifecycle',
        sectionCopy:
            'A unified perspective on status, location, and key references optimized for rapid verification and audit compliance.',
    },
);

const finalStatusLabel = computed(() => {
    if (props.isCancelled) return 'Dibatalkan';
    if (props.isCompleted) return 'Finalized';
    return 'In-Progress';
});

const lifecycleLabel = computed(() => {
    if (props.isLoanOut) return 'Recalled At';
    if (props.isReturnFlow) return 'Verified At';
    return 'Locked At';
});

const lifecycleValue = computed(() => {
    if (props.isLoanOut) return props.relatedReturnedAt;
    return props.completedAt;
});

const lifecycleVisible = computed(
    () =>
        props.isCompleted ||
        (props.isLoanOut && props.relatedReturnedAt !== '-') ||
        (props.isReturnFlow && props.completedAt !== '-'),
);

const primaryCards = computed(() => [
    {
        label: 'Flow Logic',
        value: props.documentLabel,
        meta: props.isReturnFlow
            ? 'Validated asset return sequence.'
            : 'Operational document flow.',
        icon: ActivityIcon,
        color: 'text-primary'
    },
    {
        label: props.detailDateLabel,
        value: props.deliverDate,
        meta: 'Primary temporal reference.',
        icon: CalendarIcon,
        color: 'text-slate-600'
    },
    {
        label: 'Security Status',
        value: finalStatusLabel.value,
        meta: props.isCancelled
            ? 'Accountability chain terminated.'
            : props.isCompleted
              ? 'Arched record secured.'
              : 'Awaiting authorization.',
        icon: ShieldCheck,
        tone: finalStatusLabel.value
    },
]);

const detailCards = computed(() => [
    { label: 'Operational Base', value: props.location, icon: MapPinIcon },
    { label: 'Division Unit', value: props.department, icon: BuildingIcon },
    { label: 'Enterprise Building', value: props.building, icon: BuildingIcon },
    { label: 'Deployment Date', value: props.useDate, icon: CalendarIcon },
    { label: 'Control Batch', value: props.batchNo, icon: HashIcon },
    { label: 'Compliance Ref', value: props.reqDocNo, icon: FileTextIcon },
    { label: 'Procurement PO', value: props.poDocNo, icon: FileTextIcon },
    { label: 'Last Sync', value: props.updatedAt, icon: ClockIcon },
]);
</script>

<template>
    <section class="space-y-6 animate-in fade-in slide-in-from-bottom-4 duration-500">
        <!-- Header / Intro -->
        <div class="px-2">
            <div class="flex items-center gap-3 mb-2">
                <div class="h-6 w-1 rounded-full bg-primary" />
                <h3 class="text-[10px] font-black uppercase tracking-widest text-primary">{{ sectionKicker }}</h3>
            </div>
            <h2 class="text-2xl font-black text-slate-900 tracking-tight">{{ sectionTitle }}</h2>
            <p class="text-sm text-slate-500 mt-1 max-w-2xl leading-relaxed">{{ sectionCopy }}</p>
        </div>

        <!-- Primary High-Impact Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div 
                v-for="card in primaryCards" 
                :key="card.label"
                class="overflow-hidden rounded-[28px] border border-slate-200 bg-white p-6 shadow-xl shadow-slate-200/40 relative group transition-all hover:scale-[1.02]"
            >
                <div class="flex flex-col h-full justify-between gap-4">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2 mb-2">
                            <component :is="card.icon" class="size-3.5 text-slate-300 group-hover:text-primary transition-colors" />
                            <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">{{ card.label }}</span>
                        </div>
                        <h4 
                            class="text-xl font-black italic tracking-tight"
                            :class="[
                                card.color ? card.color : '',
                                card.tone === 'Finalized' ? 'text-primary' : '',
                                card.tone === 'Dibatalkan' ? 'text-red-500' : '',
                                card.tone === 'In-Progress' ? 'text-slate-900' : ''
                            ]"
                        >
                            {{ card.value }}
                        </h4>
                    </div>
                    <p class="text-[11px] text-slate-400 font-medium leading-relaxed group-hover:text-slate-500 transition-colors">{{ card.meta }}</p>
                </div>
            </div>
        </div>

        <!-- Detailed Reference Grid -->
        <div class="rounded-[32px] border border-slate-200 bg-white p-8 shadow-xl shadow-slate-200/30">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-y-10 gap-x-8">
                <div v-for="item in detailCards" :key="item.label" class="space-y-2 group">
                    <div class="flex items-center gap-2">
                         <component :is="item.icon" class="size-3 text-slate-300 group-hover:text-primary transition-colors" />
                         <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">{{ item.label }}</span>
                    </div>
                    <p class="text-[13px] font-bold text-slate-900 group-hover:text-primary transition-colors truncate">{{ item.value || '-' }}</p>
                </div>
                <!-- Lifecycle Bonus Card -->
                <div v-if="lifecycleVisible" class="space-y-2 group p-3 rounded-2xl bg-slate-50 border border-slate-100 transform -translate-y-1">
                    <div class="flex items-center gap-2">
                         <InfoIcon class="size-3 text-primary" />
                         <span class="text-[9px] font-black uppercase tracking-widest text-primary">{{ lifecycleLabel }}</span>
                    </div>
                    <p class="text-[13px] font-black text-primary tabular-nums">{{ lifecycleValue }}</p>
                </div>
            </div>
        </div>
    </section>
</template>
