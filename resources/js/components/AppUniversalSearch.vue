<script setup>
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    Search,
    Command,
    User,
    FileText,
    HelpCircle,
    Laptop,
    Package,
    FileKey,
    ClipboardCheck,
    Loader2,
    ArrowRight,
} from 'lucide-vue-next';
import { ref, onMounted, onUnmounted, watch } from 'vue';
import { universalSearch } from '@/routes';

// Ensure axios sends cookies with requests for session-based auth
axios.defaults.withCredentials = true;

const isOpen = ref(false);
const query = ref('');
const results = ref([]);
const isLoading = ref(false);
const selectedIndex = ref(0);

const toggleSearch = () => {
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
        query.value = '';
        results.value = [];
        selectedIndex.value = 0;
    }
};

const handleKeydown = (e) => {
    if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
        e.preventDefault();
        toggleSearch();
    }

    if (!isOpen.value) return;

    if (e.key === 'Escape') {
        isOpen.value = false;
    }

    if (e.key === 'ArrowDown' && results.value.length > 0) {
        e.preventDefault();
        selectedIndex.value = (selectedIndex.value + 1) % results.value.length;
    }

    if (e.key === 'ArrowUp' && results.value.length > 0) {
        e.preventDefault();
        selectedIndex.value =
            (selectedIndex.value - 1 + results.value.length) %
            results.value.length;
    }

    if (e.key === 'Enter' && results.value[selectedIndex.value]) {
        e.preventDefault();
        navigateTo(results.value[selectedIndex.value]);
    }
};

const performSearch = async () => {
    if (query.value.length < 2) {
        results.value = [];
        return;
    }

    isLoading.value = true;
    try {
        const url = universalSearch.url();
        console.log('Search URL:', url);
        console.log('Search query:', query.value);
        
        const response = await axios.get(url, {
            params: { q: query.value },
        });
        
        console.log('Search response:', response.data);
        results.value = response.data.results || [];
        selectedIndex.value = 0;
    } catch (error) {
        console.error('Search error:', error);
        console.error('Response status:', error.response?.status);
        console.error('Response data:', error.response?.data);
        results.value = [];
    } finally {
        isLoading.value = false;
    }
};

let debounceTimer;
watch(query, () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(performSearch, 300);
});

const navigateTo = (result) => {
    isOpen.value = false;
    router.visit(result.href);
};

const getIcon = (iconName) => {
    switch (iconName) {
        case 'User':
            return User;
        case 'FileText':
            return FileText;
        case 'HelpCircle':
            return HelpCircle;
        case 'Laptop':
            return Laptop;
        case 'Package':
            return Package;
        case 'FileKey':
            return FileKey;
        case 'ClipboardCheck':
            return ClipboardCheck;
        default:
            return Search;
    }
};

onMounted(() => window.addEventListener('keydown', handleKeydown));
onUnmounted(() => window.removeEventListener('keydown', handleKeydown));

defineExpose({ toggleSearch });
</script>

<template>
    <div
        v-if="isOpen"
        class="fixed inset-0 z-[100] flex items-start justify-center px-4 pt-24 sm:px-6"
    >
        <!-- Backdrop -->
        <div
            class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity"
            @click="isOpen = false"
        ></div>

        <!-- Search Panel -->
        <div
            class="relative w-full max-w-2xl transform animate-in overflow-hidden rounded-3xl bg-white shadow-2xl ring-1 ring-slate-200 transition-all duration-200 fade-in zoom-in"
        >
            <!-- Search Header -->
            <div
                class="relative flex items-center border-b border-slate-100 p-5"
            >
                <Search v-if="!isLoading" class="mr-4 h-6 w-6 text-slate-400" />
                <Loader2
                    v-else
                    class="mr-4 h-6 w-6 animate-spin text-[#003628]"
                />

                <input
                    v-model="query"
                    type="text"
                    class="flex-1 border-none bg-transparent text-lg text-slate-800 placeholder-slate-400 focus:ring-0"
                    placeholder="Ketik apa saja untuk mencari..."
                    autofocus
                    @keydown.down="
                        selectedIndex = (selectedIndex + 1) % results.length
                    "
                    @keydown.up="
                        selectedIndex =
                            (selectedIndex - 1 + results.length) %
                            results.length
                    "
                />

                <div
                    class="ml-4 flex items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 px-2 py-1"
                >
                    <span
                        class="text-[10px] font-bold tracking-tighter text-slate-400 uppercase"
                        >ESC</span
                    >
                </div>
            </div>

            <!-- Search Content -->
            <div class="max-h-[60vh] overflow-y-auto p-2">
                <!-- Results List -->
                <div v-if="results.length > 0" class="space-y-1">
                    <div
                        v-for="(result, index) in results"
                        :key="result.type + '-' + result.id"
                        class="group flex cursor-pointer items-center rounded-2xl p-3 transition-all duration-200"
                        :class="[
                            selectedIndex === index
                                ? 'bg-[#003628]/5 ring-1 ring-[#003628]/10'
                                : 'hover:bg-slate-50',
                        ]"
                        @mouseenter="selectedIndex = index"
                        @click="navigateTo(result)"
                    >
                        <div
                            class="mr-4 flex h-10 w-10 items-center justify-center rounded-xl transition-colors"
                            :class="[
                                selectedIndex === index
                                    ? 'bg-[#003628] text-white'
                                    : 'bg-slate-100 text-slate-400 group-hover:bg-slate-200 group-hover:text-slate-600',
                            ]"
                        >
                            <component
                                :is="getIcon(result.icon)"
                                class="h-5 w-5"
                            />
                        </div>

                        <div class="min-w-0 flex-1">
                            <h4
                                class="truncate text-sm font-bold text-slate-800"
                            >
                                {{ result.title }}
                            </h4>
                            <p
                                class="truncate text-[11px] font-medium text-slate-500"
                            >
                                {{ result.subtitle }}
                            </p>
                        </div>

                        <div
                            v-if="selectedIndex === index"
                            class="ml-4 animate-in duration-300 slide-in-from-left-2"
                        >
                            <ArrowRight class="h-4 w-4 text-[#003628]" />
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div
                    v-else-if="query.length >= 2 && !isLoading"
                    class="py-12 text-center"
                >
                    <div
                        class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-slate-50"
                    >
                        <Search class="h-8 w-8 text-slate-300" />
                    </div>
                    <p class="font-medium text-slate-500">
                        Tidak ada hasil ditemukan untuk "{{ query }}"
                    </p>
                </div>

                <!-- Tips -->
                <div v-else-if="!isLoading" class="p-4">
                    <p
                        class="mb-4 text-[10px] font-bold tracking-widest text-slate-400 uppercase"
                    >
                        Pencarian Cepat
                    </p>
                    <div class="grid grid-cols-3 gap-3">
                        <div
                            class="rounded-2xl border border-slate-100 bg-slate-50/50 p-3"
                        >
                            <h5 class="mb-1 text-xs font-bold text-slate-700">
                                Aset &amp; Perangkat
                            </h5>
                            <p class="text-[10px] font-medium text-slate-500">
                                Cari SN laptop atau tag aset
                            </p>
                        </div>
                        <div
                            class="rounded-2xl border border-slate-100 bg-slate-50/50 p-3"
                        >
                            <h5 class="mb-1 text-xs font-bold text-slate-700">
                                Form &amp; Dokumen
                            </h5>
                            <p class="text-[10px] font-medium text-slate-500">
                                Cari STB, peminjaman, atau inspection
                            </p>
                        </div>
                        <div
                            class="rounded-2xl border border-slate-100 bg-slate-50/50 p-3"
                        >
                            <h5 class="mb-1 text-xs font-bold text-slate-700">
                                User &amp; Karyawan
                            </h5>
                            <p class="text-[10px] font-medium text-slate-500">
                                Cari nama atau NIK
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div
                class="flex items-center justify-between border-t border-slate-100 bg-slate-50 p-4"
            >
                <div class="flex items-center gap-4">
                    <div
                        class="flex items-center gap-1.5 text-[10px] font-medium text-slate-400"
                    >
                        <div
                            class="rounded border border-slate-200 bg-white px-1 py-0.5 text-slate-600 shadow-sm"
                        >
                            Enter
                        </div>
                        Pilih
                    </div>
                    <div
                        class="flex items-center gap-1.5 text-[10px] font-medium text-slate-400"
                    >
                        <div
                            class="rounded border border-slate-200 bg-white px-1 py-0.5 text-slate-600 shadow-sm"
                        >
                            ↑↓
                        </div>
                        Navigasi
                    </div>
                </div>
                <div
                    class="flex items-center gap-1.5 text-[10px] font-bold tracking-tighter text-slate-400 uppercase"
                >
                    <Command class="h-3 w-3" />
                    Spotlight Search
                </div>
            </div>
        </div>
    </div>
</template>
