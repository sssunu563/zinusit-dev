<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import {
    ArrowRight,
    BookOpen,
    Building2,
    Calendar,
    ChevronRight,
    Clock,
    Cpu,
    ExternalLink,
    Eye,
    FileText,
    Globe,
    HelpCircle,
    Laptop,
    Mail,
    MapPin,
    QrCode,
    Search,
    ShieldCheck,
    Sparkles,
    Tag,
    User,
    Wrench,
    X,
} from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface ArticleItem {
    id: number;
    title: string;
    slug: string;
    category: string;
    content: string;
    view_count: number;
    created_at: string;
    author?: {
        id: number;
        name: string;
    };
}

const props = defineProps<{
    articles: {
        data: ArticleItem[];
        total: number;
        links?: Array<{
            url: string | null;
            label: string;
            active: boolean;
        }>;
    };
    popularArticles?: ArticleItem[];
    categories: string[];
    filters: {
        search?: string;
        category?: string;
    };
    canManage?: boolean;
}>();

const search = ref(props.filters.search || '');
const selectedCategory = ref(props.filters.category || '');

const defaultTopicChips = [
    'Hardware & Perangkat',
    'Network & WiFi',
    'Software & Email',
    'Keamanan & Akun',
];

const allCategories = Array.from(
    new Set([...defaultTopicChips, ...(props.categories || [])]),
);

const executeSearch = useDebounceFn(() => {
    router.get(
        '/help',
        {
            search: search.value || undefined,
            category: selectedCategory.value || undefined,
        },
        {
            preserveState: true,
            replace: true,
            only: ['articles', 'filters'],
        },
    );
}, 300);

watch(search, () => {
    executeSearch();
});

const selectCategory = (cat: string) => {
    selectedCategory.value = selectedCategory.value === cat ? '' : cat;
    router.get(
        '/help',
        {
            search: search.value || undefined,
            category: selectedCategory.value || undefined,
        },
        {
            preserveState: true,
            replace: true,
            only: ['articles', 'filters'],
        },
    );
};

const clearFilters = () => {
    search.value = '';
    selectedCategory.value = '';
    router.get(
        '/help',
        {},
        {
            preserveState: true,
            replace: true,
            only: ['articles', 'filters'],
        },
    );
};

const getCategoryIcon = (category: string) => {
    const c = (category || '').toLowerCase();
    if (c.includes('hardware') || c.includes('laptop') || c.includes('perangkat')) return Laptop;
    if (c.includes('keamanan') || c.includes('security') || c.includes('akun')) return ShieldCheck;
    if (c.includes('network') || c.includes('wifi') || c.includes('jaringan')) return Globe;
    if (c.includes('software') || c.includes('email') || c.includes('outlook')) return Mail;
    return FileText;
};

const stripHtml = (html: string) => {
    if (!html) return '';
    return html
        .replace(/<[^>]*>?/gm, '')
        .replace(/[#*`_~]/g, '')
        .trim()
        .substring(0, 130);
};

const formatDate = (dateStr: string) => {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
};
</script>

<template>
    <Head title="Pusat Bantuan & Panduan IT - Zinus IT" />

    <div class="min-h-screen bg-[#F8FAFC] flex flex-col antialiased text-slate-900 selection:bg-[#003628]/20 selection:text-[#003628]">
        <!-- Top Navigation Header -->
        <header class="bg-white/90 backdrop-blur-md border-b border-slate-200/80 sticky top-0 z-40 shadow-xs">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-14 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Link href="/help" class="flex items-center gap-2">
                        <img src="/form-logo.png" class="h-6 w-auto object-contain" alt="Zinus IT" />
                        <span class="text-xs text-slate-200">|</span>
                        <span class="text-xs font-bold text-slate-600 tracking-tight">Pusat Bantuan & Panduan IT</span>
                    </Link>
                </div>
            </div>
        </header>

        <!-- Main Content Container -->
        <main class="max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 pt-6 pb-16 flex-1 space-y-6">
            <!-- Hero Search Section -->
            <div class="relative overflow-hidden rounded-3xl bg-white border border-slate-200/80 p-6 sm:p-8 shadow-xs text-center">
                <div class="absolute -top-16 -right-16 w-56 h-56 rounded-full bg-emerald-500/5 blur-3xl pointer-events-none" />
                <div class="absolute -bottom-16 -left-16 w-56 h-56 rounded-full bg-amber-500/5 blur-3xl pointer-events-none" />

                <div class="relative max-w-3xl mx-auto space-y-3.5">
                    <div class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-[11px] font-semibold">
                        <BookOpen class="size-3" />
                        Basis Pengetahuan & Dokumentasi IT
                    </div>

                    <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-slate-900">
                        Bagaimana kami bisa membantu Anda?
                    </h1>

                    <p class="text-xs sm:text-sm text-slate-500 max-w-xl mx-auto hidden sm:block">
                        Cari panduan langkah-demi-langkah seputar Wi-Fi kantor, setup printer jaringan, email Outlook, hingga prosedur pelaporan kendala perangkat.
                    </p>

                    <!-- Search Input -->
                    <div class="relative max-w-xl mx-auto pt-1">
                        <Search class="absolute left-3.5 top-1/2 -translate-y-1/2 size-4 text-slate-400 pointer-events-none" />
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Ketik kata kunci (Wi-Fi, Printer, Outlook, Laptop...)"
                            class="w-full h-11 pl-10 pr-10 rounded-2xl border border-slate-200 bg-slate-50/70 text-sm font-medium text-slate-800 placeholder:text-slate-400 focus:border-[#003628] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#003628]/10 transition-all shadow-xs"
                        />
                        <button
                            v-if="search"
                            type="button"
                            class="absolute right-3 top-1/2 -translate-y-1/2 p-1 text-slate-400 hover:text-slate-600 transition-colors"
                            @click="search = ''"
                        >
                            <X class="size-3.5" />
                        </button>
                    </div>

                    <!-- Category Chips -->
                    <div class="flex flex-wrap items-center justify-center gap-1.5 pt-1">
                        <button
                            type="button"
                            class="px-3 py-1 rounded-xl text-xs font-semibold transition-all cursor-pointer"
                            :class="
                                !selectedCategory
                                    ? 'bg-[#003628] text-white shadow-xs'
                                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                            "
                            @click="selectCategory('')"
                        >
                            Semua Kategori
                        </button>
                        <button
                            v-for="cat in allCategories"
                            :key="cat"
                            type="button"
                            class="px-3 py-1 rounded-xl text-xs font-semibold transition-all cursor-pointer"
                            :class="
                                selectedCategory === cat
                                    ? 'bg-[#003628] text-white shadow-xs'
                                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                            "
                            @click="selectCategory(cat)"
                        >
                            {{ cat }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Quick Topic Cards (Hidden to avoid duplication with category chips above) -->
            <!-- 
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
                <div
                    class="bg-white rounded-2xl border border-gray-100 p-4 shadow-xs hover:border-sky-200 hover:shadow-sm transition-all group flex items-start gap-3.5 cursor-pointer"
                    @click="selectCategory('Network & WiFi')"
                >
                    <div class="size-10 rounded-xl bg-sky-50 text-sky-700 flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
                        <Globe class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-bold text-slate-800 group-hover:text-sky-700 transition-colors">Koneksi Jaringan & Wi-Fi</p>
                        <p class="text-[11px] text-slate-400 mt-0.5 leading-snug">Panduan SSID kantor, setup VPN, dan troubleshooting.</p>
                    </div>
                </div>

                <div
                    class="bg-white rounded-2xl border border-gray-100 p-4 shadow-xs hover:border-amber-200 hover:shadow-sm transition-all group flex items-start gap-3.5 cursor-pointer"
                    @click="selectCategory('Hardware & Perangkat')"
                >
                    <div class="size-10 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
                        <Wrench class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-bold text-slate-800 group-hover:text-amber-700 transition-colors">Bantuan Perangkat Keras</p>
                        <p class="text-[11px] text-slate-400 mt-0.5 leading-snug">Prosedur printer kantor, laptop rusak, dan unit cadangan.</p>
                    </div>
                </div>

                <div
                    class="bg-white rounded-2xl border border-gray-100 p-4 shadow-xs hover:border-emerald-200 hover:shadow-sm transition-all group flex items-start gap-3.5 cursor-pointer"
                    @click="selectCategory('Software & Email')"
                >
                    <div class="size-10 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
                        <Mail class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-bold text-slate-800 group-hover:text-emerald-700 transition-colors">Software & Email Outlook</p>
                        <p class="text-[11px] text-slate-400 mt-0.5 leading-snug">Konfigurasi email @zinus.com, lisensi, dan aplikasi kerja.</p>
                    </div>
                </div>

                <div
                    class="bg-white rounded-2xl border border-gray-100 p-4 shadow-xs hover:border-purple-200 hover:shadow-sm transition-all group flex items-start gap-3.5 cursor-pointer"
                    @click="selectCategory('Keamanan & Akun')"
                >
                    <div class="size-10 rounded-xl bg-purple-50 text-purple-700 flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
                        <ShieldCheck class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-bold text-slate-800 group-hover:text-purple-700 transition-colors">Keamanan & Akun IT</p>
                        <p class="text-[11px] text-slate-400 mt-0.5 leading-snug">Reset password domain, keamanan data, dan phishing.</p>
                    </div>
                </div>
            </div>
            -->

            <!-- Articles Section (Full Widescreen 4-Column Grid) -->
            <div class="space-y-4 pt-2">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <h2 class="text-base font-bold text-slate-900">
                            {{ selectedCategory ? `Kategori: ${selectedCategory}` : 'Semua Panduan & Solusi IT' }}
                        </h2>
                        <span class="text-xs font-semibold px-2 py-0.5 rounded-md bg-slate-200/80 text-slate-600">
                            {{ articles.total }} artikel
                        </span>
                    </div>

                    <button
                        v-if="selectedCategory || search"
                        type="button"
                        class="text-xs font-semibold text-emerald-700 hover:underline cursor-pointer"
                        @click="clearFilters"
                    >
                        Tampilkan Semua Panduan
                    </button>
                </div>

                <!-- Articles Grid (1 col mobile, 2 col sm, 3 col lg, 4 col xl) -->
                <div v-if="articles.data.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    <Link
                        v-for="item in articles.data"
                        :key="item.id"
                        :href="`/help/${item.slug}`"
                        class="bg-white rounded-2xl border border-gray-100 p-4.5 shadow-xs hover:border-emerald-200 hover:shadow-md transition-all flex flex-col justify-between group"
                    >
                        <div class="space-y-2.5">
                            <div class="flex items-center justify-between gap-2">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-800 text-[10px] font-semibold">
                                    <component :is="getCategoryIcon(item.category)" class="size-2.5 text-emerald-600" />
                                    {{ item.category }}
                                </span>

                                <span class="text-[10px] text-slate-400 flex items-center gap-0.5">
                                    <Eye class="size-2.5" />
                                    {{ item.view_count }}
                                </span>
                            </div>

                            <h3 class="text-sm font-bold text-slate-900 group-hover:text-[#003628] transition-colors leading-snug line-clamp-2">
                                {{ item.title }}
                            </h3>

                            <p class="text-[11px] text-slate-500 line-clamp-3 leading-relaxed">
                                {{ stripHtml(item.content) }}...
                            </p>
                        </div>

                        <div class="pt-3.5 mt-3.5 border-t border-slate-50 flex items-center justify-between text-[11px] text-slate-400">
                            <span>{{ formatDate(item.created_at) }}</span>
                            <span class="font-bold text-[#003628] flex items-center gap-0.5 group-hover:translate-x-0.5 transition-transform">
                                Baca Panduan <ArrowRight class="size-3" />
                            </span>
                        </div>
                    </Link>
                </div>

                <!-- Empty State -->
                <div
                    v-else
                    class="bg-white rounded-3xl border border-slate-200/80 p-12 text-center space-y-3"
                >
                    <div class="size-12 rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-center mx-auto text-slate-400">
                        <HelpCircle class="size-6" />
                    </div>
                    <h3 class="text-sm font-bold text-slate-800">Panduan Tidak Ditemukan</h3>
                    <p class="text-xs text-slate-400 max-w-sm mx-auto">
                        Tidak ada artikel yang cocok dengan kata kunci <span class="font-semibold text-slate-600">"{{ search }}"</span>.
                    </p>
                    <button
                        type="button"
                        class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-xs font-semibold text-slate-700 transition-colors cursor-pointer"
                        @click="clearFilters"
                    >
                        Reset Pencarian
                    </button>
                </div>

                <!-- Pagination -->
                <div v-if="articles.links && articles.links.length > 3" class="flex items-center justify-center gap-1 pt-4">
                    <template v-for="(link, idx) in articles.links" :key="idx">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors"
                            :class="
                                link.active
                                    ? 'bg-[#003628] text-white font-bold'
                                    : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50'
                            "
                            v-html="link.label"
                        />
                    </template>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="py-6 border-t border-slate-200/60 bg-white text-center text-slate-400 text-[10px] font-bold uppercase tracking-widest">
            <p>&copy; {{ new Date().getFullYear() }} PT Zinus Global Indonesia &bull; IT Support & Knowledge Base</p>
        </footer>
    </div>
</template>
