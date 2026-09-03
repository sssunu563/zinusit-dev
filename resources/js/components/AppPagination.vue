<script setup lang="ts">
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    currentPage: number;
    totalPages: number;
    itemsPerPage: number;
    totalItems: number;
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    disabled: false,
});

const emit = defineEmits<{
    'update:currentPage': [page: number];
}>();

const canGoPrevious = computed(() => props.currentPage > 1);
const canGoNext = computed(() => props.currentPage < props.totalPages);

const pageStart = computed(() => {
    if (props.totalItems === 0) return 0;
    return (props.currentPage - 1) * props.itemsPerPage + 1;
});

const pageEnd = computed(() => {
    const end = props.currentPage * props.itemsPerPage;
    return end > props.totalItems ? props.totalItems : end;
});

function goToPage(page: number) {
    if (!props.disabled && page >= 1 && page <= props.totalPages) {
        emit('update:currentPage', page);
    }
}

function previousPage() {
    if (canGoPrevious.value) {
        goToPage(props.currentPage - 1);
    }
}

function nextPage() {
    if (canGoNext.value) {
        goToPage(props.currentPage + 1);
    }
}
</script>

<template>
    <div
        class="flex flex-wrap items-center justify-between gap-4 rounded-lg bg-slate-50 px-4 py-3"
    >
        <!-- Item count info -->
        <div class="text-[12px] font-semibold text-slate-600">
            <span v-if="totalItems > 0">
                Showing
                <span class="font-bold text-slate-900">{{ pageStart }}-{{ pageEnd }}</span>
                of
                <span class="font-bold text-slate-900">{{ totalItems }}</span>
            </span>
            <span v-else class="text-slate-500">No items to display</span>
        </div>

        <!-- Pagination controls -->
        <div
            v-if="totalPages > 1"
            class="flex items-center gap-2"
        >
            <!-- Previous button -->
            <button
                type="button"
                :disabled="!canGoPrevious || disabled"
                class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-600 transition-all hover:enabled:border-slate-300 hover:enabled:bg-white disabled:cursor-not-allowed disabled:opacity-50"
                @click="previousPage"
            >
                <ChevronLeft class="size-4" />
            </button>

            <!-- Page numbers -->
            <div class="flex items-center gap-1">
                <template v-for="page in totalPages" :key="page">
                    <!-- Show first page, last page, current page +/- 1 -->
                    <template
                        v-if="
                            page === 1 ||
                            page === totalPages ||
                            (page >= currentPage - 1 && page <= currentPage + 1)
                        "
                    >
                        <button
                            type="button"
                            :disabled="disabled"
                            class="flex h-8 w-8 items-center justify-center rounded-lg text-[11px] font-bold transition-all"
                            :class="
                                page === currentPage
                                    ? 'bg-[#003628] text-white shadow-md shadow-[#003628]/20'
                                    : 'border border-slate-200 text-slate-600 hover:enabled:border-slate-300 hover:enabled:bg-white disabled:cursor-not-allowed disabled:opacity-50'
                            "
                            @click="goToPage(page)"
                        >
                            {{ page }}
                        </button>
                    </template>

                    <!-- Ellipsis for gap between page ranges -->
                    <template v-else-if="page === 2 && currentPage > 3">
                        <span class="text-slate-400">…</span>
                    </template>
                    <template v-else-if="page === totalPages - 1 && currentPage < totalPages - 2">
                        <span class="text-slate-400">…</span>
                    </template>
                </template>
            </div>

            <!-- Next button -->
            <button
                type="button"
                :disabled="!canGoNext || disabled"
                class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-600 transition-all hover:enabled:border-slate-300 hover:enabled:bg-white disabled:cursor-not-allowed disabled:opacity-50"
                @click="nextPage"
            >
                <ChevronRight class="size-4" />
            </button>
        </div>
    </div>
</template>
