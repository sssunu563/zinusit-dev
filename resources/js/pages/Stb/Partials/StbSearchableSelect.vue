<script setup lang="ts">
import { LucideChevronDown as ChevronDown, LucideSearch as Search } from 'lucide-vue-next';
import { computed, ref, onMounted, onUnmounted } from 'vue';

interface Option {
    id: number | string;
    name: string;
    subtext?: string;
}

const props = defineProps<{
    modelValue: number | string | null;
    options: Option[];
    placeholder?: string;
    label?: string;
    error?: string;
    leftPadding?: boolean;
}>();

const emit = defineEmits(['update:modelValue', 'change']);

const isOpen = ref(false);
const searchQuery = ref('');
const containerRef = ref<HTMLElement | null>(null);

const selectedOption = computed(() => {
    return props.options.find(opt => opt.id === props.modelValue) || null;
});

const filteredOptions = computed(() => {
    if (!searchQuery.value) return props.options;
    const query = searchQuery.value.toLowerCase();
    return props.options.filter(opt => 
        opt.name.toLowerCase().includes(query) || 
        (opt.subtext && opt.subtext.toLowerCase().includes(query))
    );
});

const toggleDropdown = () => {
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
        searchQuery.value = '';
    }
};

const selectOption = (option: Option) => {
    emit('update:modelValue', option.id);
    emit('change', option.id);
    isOpen.value = false;
    searchQuery.value = '';
};

const handleClickOutside = (event: MouseEvent) => {
    if (containerRef.value && !containerRef.value.contains(event.target as Node)) {
        isOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener('mousedown', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('mousedown', handleClickOutside);
});
</script>

<template>
    <div ref="containerRef" class="relative w-full">
        <div 
            class="group relative cursor-pointer"
            @click="toggleDropdown"
        >
            <div 
                :class="[
                    'w-full h-10 rounded-lg border bg-white flex items-center justify-between transition-all shadow-sm',
                    leftPadding ? 'pl-10 pr-4' : 'px-4',
                    isOpen ? 'border-[#003628] ring-4 ring-[#003628]/5' : 'border-slate-200 hover:border-slate-300',
                    error ? 'border-red-300' : ''
                ]"
            >
                <span 
                    :class="[
                        'text-[13px] font-bold truncate',
                        selectedOption ? 'text-slate-900' : 'text-slate-400'
                    ]"
                >
                    {{ selectedOption ? selectedOption.name : placeholder || 'Pilih opsi...' }}
                </span>
                <ChevronDown 
                    :class="[
                        'size-3.5 text-slate-400 transition-transform duration-200',
                        isOpen ? 'rotate-180 text-[#003628]' : ''
                    ]" 
                />
            </div>
        </div>

        <Transition
            enter-active-class="transition duration-100 ease-out"
            enter-from-class="opacity-0 scale-95 translate-y-[-10px]"
            enter-to-class="opacity-100 scale-100 translate-y-0"
            leave-active-class="transition duration-75 ease-in"
            leave-from-class="opacity-100 scale-100 translate-y-0"
            leave-to-class="opacity-0 scale-95 translate-y-[-10px]"
        >
            <div 
                v-if="isOpen"
                class="absolute z-[9999] mt-2 w-full min-w-[280px] bg-white rounded-xl shadow-[0_20px_50px_rgba(0,0,0,0.2)] border border-slate-100 overflow-hidden left-0"
            >
                <!-- SEARCH INPUT -->
                <div class="p-2 border-b border-slate-50 flex items-center gap-2 bg-slate-50/50">
                    <Search class="size-3.5 text-slate-400 ml-2" />
                    <input 
                        v-model="searchQuery"
                        type="text"
                        class="w-full bg-transparent border-none p-2 text-[12px] font-bold text-slate-900 focus:ring-0 placeholder:text-slate-400"
                        :placeholder="`Cari ${label || 'opsi'}...`"
                        autoFocus
                        @click.stop
                    />
                </div>

                <!-- OPTIONS LIST -->
                <div class="max-h-[240px] overflow-y-auto py-1">
                    <div 
                        v-for="option in filteredOptions" 
                        :key="option.id"
                        class="px-4 py-2.5 hover:bg-slate-50 cursor-pointer transition-colors group/item"
                        @click="selectOption(option)"
                    >
                        <div class="flex flex-col">
                            <span 
                                :class="[
                                    'text-[12px] font-bold transition-colors',
                                    option.id === modelValue ? 'text-[#003628]' : 'text-slate-700 group-hover/item:text-slate-900'
                                ]"
                            >
                                {{ option.name }}
                            </span>
                            <span v-if="option.subtext" class="text-[10px] font-medium text-slate-400">
                                {{ option.subtext }}
                            </span>
                        </div>
                    </div>

                    <!-- EMPTY STATE -->
                    <div v-if="filteredOptions.length === 0" class="px-4 py-8 text-center">
                        <p class="text-[11px] font-black text-slate-300 uppercase tracking-widest">Data tidak ditemukan</p>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
/* Custom scrollbar for dropdown */
.max-h-\[240px\]::-webkit-scrollbar {
    width: 4px;
}
.max-h-\[240px\]::-webkit-scrollbar-track {
    background: transparent;
}
.max-h-\[240px\]::-webkit-scrollbar-thumb {
    background: #f1f5f9;
    border-radius: 10px;
}
.max-h-\[240px\]:hover::-webkit-scrollbar-thumb {
    background: #e2e8f0;
}
</style>
