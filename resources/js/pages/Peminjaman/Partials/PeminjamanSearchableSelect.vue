<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Search, ChevronDown, Check } from 'lucide-vue-next';

interface Option {
    id: number | string;
    name: string;
    subtext?: string | null;
}

const props = defineProps<{
    modelValue: number | string;
    options: Option[];
    placeholder?: string;
    label?: string;
    hasIcon?: boolean;
}>();


const emit = defineEmits(['update:modelValue', 'change']);

const isOpen = ref(false);
const searchQuery = ref('');
const containerRef = ref<HTMLElement | null>(null);

const filteredOptions = computed(() => {
    const query = searchQuery.value.toLowerCase();
    if (!query) return props.options;
    return props.options.filter(opt => 
        opt.name.toLowerCase().includes(query) || 
        (opt.subtext && opt.subtext.toLowerCase().includes(query))
    );
});

const selectedOption = computed(() => {
    return props.options.find(opt => opt.id === props.modelValue);
});

const toggleDropdown = () => {
    isOpen.value = !isOpen.value;
    if (isOpen.value) searchQuery.value = '';
};

const selectOption = (option: Option) => {
    emit('update:modelValue', option.id);
    emit('change', option.id);
    isOpen.value = false;
};

const handleClickOutside = (event: MouseEvent) => {
    if (containerRef.value && !containerRef.value.contains(event.target as Node)) {
        isOpen.value = false;
    }
};

onMounted(() => document.addEventListener('mousedown', handleClickOutside));
onUnmounted(() => document.removeEventListener('mousedown', handleClickOutside));
</script>

<template>
<div ref="containerRef" class="relative">
    <button
        type="button"
        @click="toggleDropdown"
        class="w-full h-10 flex items-center justify-between rounded-lg border border-slate-200 bg-white text-[13px] font-bold text-slate-900 focus:border-[#003628] focus:ring-4 focus:ring-[#003628]/5 transition-all outline-none shadow-sm group"
        :class="hasIcon ? 'pl-10 pr-3' : 'px-3'"
    >

        <span v-if="selectedOption" class="truncate">{{ selectedOption.name }}</span>
        <span v-else class="text-slate-300 italic font-medium">{{ placeholder || 'Select option...' }}</span>
        <ChevronDown class="size-3.5 text-slate-300 group-hover:text-slate-400 transition-colors" />
    </button>

    <div
        v-if="isOpen"
        class="absolute z-50 mt-1 w-full rounded-xl border border-slate-200 bg-white shadow-2xl animate-in fade-in zoom-in-95 duration-200"
    >
        <div class="p-2 border-b border-slate-50">
            <div class="relative">
                <input
                    v-model="searchQuery"
                    type="text"
                    class="w-full h-9 pl-9 pr-3 rounded-lg bg-slate-50 border-none text-[12px] font-medium text-slate-900 focus:ring-2 focus:ring-[#003628]/10 outline-none"
                    :placeholder="`Search ${label || ''}...`"
                    @click.stop
                />
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 size-3.5 text-slate-400" />
            </div>
        </div>

        <div class="max-h-60 overflow-y-auto p-1 custom-scrollbar">
            <button
                v-for="opt in filteredOptions"
                :key="opt.id"
                type="button"
                @click="selectOption(opt)"
                class="w-full px-3 py-2 flex flex-col items-start rounded-lg hover:bg-slate-50 transition-colors group"
                :class="{ 'bg-slate-50/50': opt.id === modelValue }"
            >
                <div class="flex items-center justify-between w-full">
                    <span class="text-[12px] font-bold text-slate-900">{{ opt.name }}</span>
                    <Check v-if="opt.id === modelValue" class="size-3 text-[#003628]" />
                </div>
                <span v-if="opt.subtext" class="text-[10px] font-medium text-slate-400 italic">{{ opt.subtext }}</span>
            </button>

            <div v-if="filteredOptions.length === 0" class="px-3 py-6 text-center">
                <p class="text-[11px] font-bold text-slate-400 italic">No matches found</p>
            </div>
        </div>
    </div>
</div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #e2e8f0;
    border-radius: 10px;
}
</style>
