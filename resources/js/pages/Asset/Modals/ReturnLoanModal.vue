<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Search, X } from 'lucide-vue-next';

interface Peminjaman {
    id: number;
    docId: string;
    user_name: string;
    created_at: string;
}

interface Props {
    show: boolean;
    selectedAssets?: { id: number; name: string }[];
    loanReferences?: any[];
}

interface Emits {
    (e: 'close'): void;
    (e: 'select', peminjaman: Peminjaman): void;
}

const props = withDefaults(defineProps<Props>(), {
    selectedAssets: () => [],
    loanReferences: () => [],
});

const emit = defineEmits<Emits>();

const searchQuery = ref('');
const selectedLoan = ref<Peminjaman | null>(null);

const filteredLoans = computed(() => {
    if (!searchQuery.value.trim()) {
        return props.loanReferences || [];
    }

    const query = searchQuery.value.toLowerCase();
    return (props.loanReferences || []).filter(
        (loan) =>
            loan.docId?.toLowerCase().includes(query) ||
            loan.label?.toLowerCase().includes(query),
    );
});

const handleSelect = () => {
    if (selectedLoan.value) {
        emit('select', selectedLoan.value);
        handleClose();
    }
};

const handleClose = () => {
    searchQuery.value = '';
    selectedLoan.value = null;
    emit('close');
};

watch(
    () => props.show,
    (newVal) => {
        if (newVal) {
            searchQuery.value = '';
            selectedLoan.value = null;
        }
    },
);
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
        @click.self="handleClose"
    >
        <div class="w-full max-w-2xl rounded-lg bg-white shadow-xl" @click.stop>
            <!-- Header -->
            <div
                class="flex items-center justify-between border-b border-slate-200 px-6 py-4"
            >
                <div>
                    <h2 class="text-lg font-bold text-slate-900">
                        Pilih Peminjaman untuk Pengembalian
                    </h2>
                    <p class="text-sm text-slate-500">
                        Pilih dokumen peminjaman yang ingin dikembalikan
                    </p>
                </div>
                <button
                    @click="handleClose"
                    class="rounded-lg p-2 hover:bg-slate-100"
                >
                    <X class="size-5 text-slate-500" />
                </button>
            </div>

            <!-- Search -->
            <div class="border-b border-slate-200 px-6 py-4">
                <div class="relative">
                    <Search
                        class="absolute top-3 left-3 size-5 text-slate-400"
                    />
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Cari dokumen peminjaman..."
                        class="w-full rounded-lg border border-slate-200 bg-white py-2 pr-4 pl-10 text-sm text-slate-900 placeholder-slate-500 focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none"
                    />
                </div>
            </div>

            <!-- List -->
            <div class="max-h-96 overflow-y-auto">
                <div
                    v-if="filteredLoans.length === 0"
                    class="px-6 py-8 text-center"
                >
                    <p class="text-sm text-slate-500">
                        {{
                            searchQuery
                                ? 'Tidak ada peminjaman yang cocok'
                                : 'Tidak ada peminjaman aktif'
                        }}
                    </p>
                </div>

                <div v-else>
                    <button
                        v-for="loan in filteredLoans"
                        :key="loan.id"
                        @click="selectedLoan = loan"
                        :class="[
                            'w-full border-b border-slate-100 px-6 py-4 text-left transition-colors hover:bg-slate-50',
                            selectedLoan?.id === loan.id && 'bg-primary/5',
                        ]"
                    >
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <p class="font-semibold text-slate-900">
                                    {{ loan.docId }}
                                </p>
                                <p class="text-sm text-slate-600">
                                    {{ loan.label }}
                                </p>
                            </div>
                            <div
                                v-if="selectedLoan?.id === loan.id"
                                class="mt-1 ml-4 flex h-5 w-5 items-center justify-center rounded-full bg-primary"
                            >
                                <svg
                                    class="size-3 text-white"
                                    fill="currentColor"
                                    viewBox="0 0 20 20"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                            </div>
                        </div>
                    </button>
                </div>
            </div>

            <!-- Footer -->
            <div class="flex gap-3 border-t border-slate-200 px-6 py-4">
                <button
                    @click="handleClose"
                    class="flex-1 rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                >
                    Batal
                </button>
                <button
                    @click="handleSelect"
                    :disabled="!selectedLoan"
                    class="flex-1 rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:enabled:bg-red-700 disabled:opacity-50"
                >
                    Pilih Peminjaman
                </button>
            </div>
        </div>
    </div>
</template>
