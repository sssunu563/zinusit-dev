<script setup lang="ts">
import { Clock, User, ArrowRight, FileText } from 'lucide-vue-next';
import { computed } from 'vue';

interface HistoryItem {
    id: number;
    serial_no: string;
    user_label: string;
    movement_type: 'out' | 'return';
    completed_at: string | null;
    remark: string | null;
}

interface Props {
    history: Record<string, HistoryItem[]>;
}

const props = defineProps<Props>();

const hasHistory = computed(() => Object.keys(props.history).length > 0);

const formatDate = (date?: string | null) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};
</script>

<template>
    <div class="space-y-8">
        <div v-if="!hasHistory" class="flex flex-col items-center justify-center py-12 text-zinc-400">
            <Clock class="w-12 h-12 mb-4 opacity-20" />
            <p>Tidak ada riwayat peminjaman sebelumnya untuk asset ini.</p>
        </div>

        <div v-for="(items, serial) in history" :key="serial" class="space-y-4">
            <div class="flex items-center gap-2">
                <div class="px-2 py-0.5 rounded bg-zinc-100 dark:bg-zinc-800 text-[10px] font-black uppercase tracking-widest text-zinc-500">
                    Serial Number
                </div>
                <h4 class="font-mono font-bold text-zinc-900 dark:text-zinc-100">{{ serial }}</h4>
            </div>

            <div class="relative pl-8 space-y-6 before:absolute before:left-3 before:top-2 before:bottom-2 before:w-0.5 before:bg-zinc-100 dark:before:bg-zinc-800">
                <div v-for="item in items" :key="item.id" class="relative group">
                    <!-- Dot -->
                    <div class="absolute -left-[25px] top-1.5 size-2.5 rounded-full border-2 border-white dark:border-zinc-950 shadow-sm"
                        :class="item.movement_type === 'out' ? 'bg-amber-500' : 'bg-emerald-500'">
                    </div>

                    <a :href="`/peminjaman/${item.id}`" class="block p-4 transition-all bg-white border rounded-2xl dark:bg-zinc-900 border-zinc-200 dark:border-zinc-800 hover:border-primary group-hover:shadow-md">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] font-black uppercase tracking-tighter" 
                                        :class="item.movement_type === 'out' ? 'text-amber-600' : 'text-emerald-600'">
                                        {{ item.movement_type === 'out' ? 'Loan Issued' : 'Loan Returned' }}
                                    </span>
                                    <span class="text-xs text-zinc-400">•</span>
                                    <span class="text-xs text-zinc-500">{{ formatDate(item.completed_at) }}</span>
                                </div>
                                <div class="flex items-center gap-2 text-sm font-bold text-zinc-900 dark:text-zinc-100">
                                    <FileText class="w-4 h-4 text-zinc-400" />
                                    Doc #{{ item.id }}
                                </div>
                            </div>
                            
                            <div class="flex items-center gap-2 text-xs font-medium text-zinc-500">
                                <User class="w-3.5 h-3.5" />
                                {{ item.user_label }}
                            </div>
                        </div>

                        <div v-if="item.remark" class="mt-3 text-xs italic leading-relaxed text-zinc-400 line-clamp-2">
                            "{{ item.remark }}"
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</template>
