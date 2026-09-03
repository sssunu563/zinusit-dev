<script setup lang="ts">
import { 
    Clock, 
    FileSignature, 
    Truck, 
    RotateCcw, 
    CheckCircle2, 
    AlertCircle, 
    CalendarClock
} from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    status: 'draft' | 'signed' | 'completed' | 'returned' | 'cancelled';
    isReturn?: boolean;
    createdAt: string;
    completedAt?: string | null;
    returnedAt?: string | null;
    cancelledAt?: string | null;
    expectedReturnDate?: string | null;
}

const props = defineProps<Props>();

const steps = computed(() => {
    const baseSteps = [
        {
            id: 'draft',
            label: 'Form Created',
            date: props.createdAt,
            icon: Clock,
            color: 'text-slate-400',
            bg: 'bg-slate-50',
            active: true
        },
        {
            id: 'signed',
            label: 'Fully Signed',
            date: null,
            icon: FileSignature,
            color: 'text-blue-500',
            bg: 'bg-blue-50',
            active: props.status !== 'draft'
        },
        {
            id: 'completed',
            label: props.isReturn ? 'Return Validated' : 'Asset Issued',
            date: props.completedAt,
            icon: props.isReturn ? RotateCcw : Truck,
            color: 'text-emerald-500',
            bg: 'bg-emerald-50',
            active: ['completed', 'returned'].includes(props.status)
        }
    ];

    if (!props.isReturn && props.status === 'returned') {
        baseSteps.push({
            id: 'returned',
            label: 'Fully Returned',
            date: props.returnedAt,
            icon: CheckCircle2,
            color: 'text-indigo-500',
            bg: 'bg-indigo-50',
            active: true
        });
    }

    if (props.status === 'cancelled') {
        baseSteps.push({
            id: 'cancelled',
            label: 'Document Cancelled',
            date: props.cancelledAt,
            icon: AlertCircle,
            color: 'text-red-500',
            bg: 'bg-red-50',
            active: true
        });
    }

    return baseSteps;
});

const overdue = computed(() => {
    if (!props.expectedReturnDate || props.status === 'returned' || props.isReturn) return false;
    return new Date(props.expectedReturnDate) < new Date();
});
</script>

<template>
    <div class="relative w-full rounded-[40px] bg-white border border-slate-200 p-8 shadow-2xl shadow-slate-200/50 overflow-hidden">
        <!-- Background Decoration -->
        <div class="absolute -right-24 -top-24 size-64 rounded-full bg-slate-50 blur-[100px]" />
        
        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-8 relative z-10">
            <div v-for="(step, idx) in steps" :key="step.id" class="flex flex-1 items-center w-full">
                <div class="flex flex-col items-center group">
                    <div 
                        class="size-12 rounded-2xl flex items-center justify-center transition-all duration-500 shadow-sm"
                        :class="[
                            step.active ? step.bg + ' ' + step.color : 'bg-slate-50 text-slate-200',
                            step.active ? 'scale-110 shadow-lg' : 'grayscale opacity-50'
                        ]"
                    >
                        <component :is="step.icon" class="size-6" />
                    </div>
                    <div class="mt-4 text-center">
                        <p class="text-[9px] font-black uppercase tracking-[0.2em] mb-1" :class="step.active ? 'text-slate-900' : 'text-slate-300'">
                            {{ step.label }}
                        </p>
                        <p v-if="step.date" class="text-[10px] font-bold text-slate-400">
                            {{ new Date(step.date).toLocaleDateString('id-ID', { day: '2-digit', month: 'short' }) }}
                        </p>
                        <p v-else-if="step.active" class="text-[9px] font-black text-emerald-500 uppercase tracking-widest animate-pulse">
                            Active
                        </p>
                        <p v-else class="text-[9px] font-black text-slate-200 uppercase tracking-widest">
                            Pending
                        </p>
                    </div>
                </div>

                <!-- Connector -->
                <div v-if="idx < steps.length - 1" class="hidden md:block flex-1 mx-4 h-0.5 rounded-full bg-slate-100 relative overflow-hidden">
                    <div 
                        class="absolute inset-0 bg-gradient-to-r from-transparent via-emerald-400 to-transparent translate-x-[-100%] transition-transform duration-[2000ms]"
                        :class="{ 'translate-x-[100%]': step.active }"
                    />
                </div>
            </div>

            <!-- Overdue Badge -->
            <div v-if="overdue" class="mt-4 md:mt-0 px-6 py-3 rounded-2xl bg-red-50 border border-red-100 flex items-center gap-3 animate-bounce">
                <CalendarClock class="size-5 text-red-500" />
                <div class="flex flex-col">
                    <span class="text-[9px] font-black text-red-900 uppercase tracking-widest">Overdue Alert</span>
                    <span class="text-[11px] font-black text-red-600 uppercase">Estimated Return Was {{ new Date(expectedReturnDate!).toLocaleDateString('id-ID') }}</span>
                </div>
            </div>
        </div>
    </div>
</template>
