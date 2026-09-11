<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import {
    BookOpen,
    ChevronRight,
    Clock,
    Cpu,
    Eye,
    FileText,
    Globe,
    HelpCircle,
    LifeBuoy,
    Plus,
    Search,
    ShieldCheck,
    Tag,
    X,
} from 'lucide-vue-next';
import { ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps<{
    articles: {
        data: Array<{
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
        }>;
        total: number;
        links?: Array<{
            url: string | null;
            label: string;
            active: boolean;
        }>;
    };
    categories: string[];
    filters: {
        search?: string;
        category?: string;
    };
}>();

const search = ref(props.filters.search || '');
const selectedCategory = ref(props.filters.category || '');

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Knowledge Base', href: '/kb' },
];

const defaultTopicChips = [
    'Hardware',
    'Network & WiFi',
    'Security',
    'Software & App',
    'FAQ',
];

const allCategories = Array.from(
    new Set([...defaultTopicChips, ...(props.categories || [])]),
);

const executeSearch = useDebounceFn(() => {
    router.get(
        '/kb',
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
}, 350);

watch(search, () => {
    executeSearch();
});

const selectCategory = (cat: string) => {
    selectedCategory.value = selectedCategory.value === cat ? '' : cat;
    router.get(
        '/kb',
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
        '/kb',
        {},
        {
            preserveState: true,
            replace: true,
            only: ['articles', 'filters'],
        },
    );
};

const getCategoryIcon = (category: string) => {
    const c = category.toLowerCase();
    if (c.includes('hardware') || c.includes('laptop') || c.includes('pc')) return Cpu;
    if (c.includes('security') || c.includes('keamanan')) return ShieldCheck;
    if (c.includes('network') || c.includes('wifi') || c.includes('vpn') || c.includes('jaringan')) return Globe;
    if (c.includes('faq') || c.includes('tanya')) return HelpCircle;
    return FileText;
};

const stripHtml = (html: string) => {
    if (!html) return '';
    return html
        .replace(/<[^>]*>?/gm, '')
        .replace(/[#*`_~]/g, '')
        .trim()
        .substring(0, 140);
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
    <Head title="Knowledge Base - Panduan & Solusi IT" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-[1600px] space-y-6 p-2 sm:p-4 md:p-6">
            <!-- Command Header -->
            <div
                class="flex flex-col justify-between gap-4 rounded-3xl border border-slate-100 bg-white p-5 sm:p-6 shadow-xs sm:flex-row sm:items-center"
            >
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-bold text-emerald-800"
                        >
                            <BookOpen class="size-3.5 text-emerald-600" />
                            Pusat Dokumentasi IT
                        </span>
                        <span class="text-xs text-slate-300">·</span>
                        <span class="text-xs font-semibold text-slate-500">
                            {{ articles.total }} Dokumen Panduan
                        </span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-slate-900">
                        Knowledge Base
                    </h1>
                    <p class="text-xs sm:text-sm font-medium text-slate-500 mt-1">
                        Panduan operasional, troubleshooting teknis, dan informasi sistem IT Zinus.
                    </p>
                </div>

                <div class="flex items-center gap-3 shrink-0">
                    <Link
                        href="/kb/create"
                        class="inline-flex h-11 items-center gap-2 rounded-2xl bg-[#003628] px-5 text-xs font-black tracking-wider text-white uppercase shadow-lg shadow-[#003628]/20 transition-all hover:bg-[#00281e] active:scale-95"
                    >
                        <Plus class="size-4 text-amber-400" />
                        Tulis Artikel Baru
                    </Link>
                </div>
            </div>

            <!-- Search Banner -->
            <div
                class="relative overflow-hidden rounded-3xl bg-white border border-slate-200/80 p-6 sm:p-10 shadow-xs text-center"
            >
                <!-- Subtle decorative background shapes -->
                <div class="absolute -top-20 -right-20 w-60 h-60 rounded-full bg-emerald-500/5 blur-3xl pointer-events-none" />
                <div class="absolute -bottom-20 -left-20 w-60 h-60 rounded-full bg-amber-500/5 blur-3xl pointer-events-none" />

                <div class="relative max-w-2xl mx-auto space-y-3">
                    <div class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 text-xs font-semibold">
                        <BookOpen class="size-3.5" />
                        Basis Pengetahuan & Dokumentasi IT
                    </div>

                    <h2 class="text-2xl sm:text-3xl font-black tracking-tight text-slate-900">
                        Ada kendala atau butuh panduan teknis?
                    </h2>
                    <p class="text-xs sm:text-sm text-slate-500 max-w-lg mx-auto">
                        Cari solusi cepat untuk masalah printer, setup VPN, email, akun sistem, hingga konfigurasi hardware.
                    </p>

                    <!-- Search Input Box -->
                    <div class="relative mx-auto max-w-xl pt-3">
                        <Search
                            class="absolute top-1/2 left-4 size-4.5 -translate-y-1/2 text-slate-400 pointer-events-none"
                        />
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Ketik kata kunci pencarian (misal: VPN, Printer, Zoom, Password)..."
                            class="w-full h-12 pl-11 pr-10 rounded-2xl border border-slate-200 bg-slate-50/70 text-sm font-medium text-slate-800 placeholder:text-slate-400 focus:border-[#003628] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#003628]/10 transition-all shadow-xs"
                        />
                        <button
                            v-if="search"
                            type="button"
                            class="absolute top-1/2 right-3.5 -translate-y-1/2 p-1 text-slate-400 hover:text-slate-600 transition-colors"
                            @click="search = ''"
                        >
                            <X class="size-4" />
                        </button>
                    </div>

                    <!-- Category Chips Filter -->
                    <div class="flex flex-wrap items-center justify-center gap-1.5 pt-2">
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

            <!-- Active Filter Indicator -->
            <div
                v-if="search || selectedCategory"
                class="flex items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white px-5 py-3 text-xs shadow-2xs"
            >
                <div class="flex items-center gap-2 text-slate-600 font-medium">
                    <Tag class="size-4 text-[#003628]" />
                    <span>Filter aktif:</span>
                    <span v-if="search" class="font-bold text-slate-900">
                        Pencarian "{{ search }}"
                    </span>
                    <span v-if="search && selectedCategory">·</span>
                    <span v-if="selectedCategory" class="font-bold text-emerald-800">
                        Kategori: {{ selectedCategory }}
                    </span>
                    <span class="text-slate-400">({{ articles.total }} hasil ditemukan)</span>
                </div>
                <button
                    type="button"
                    class="font-bold text-rose-600 hover:text-rose-700 underline transition-colors"
                    @click="clearFilters"
                >
                    Reset Filter
                </button>
            </div>

            <!-- Articles Section -->
            <div class="space-y-6">
                <!-- Grid of Articles -->
                <div
                    v-if="articles.data.length > 0"
                    class="grid grid-cols-1 gap-4 sm:grid-cols-2"
                >
                    <Link
                        v-for="article in articles.data"
                        :key="article.id"
                        :href="`/kb/${article.slug}`"
                        class="bg-white rounded-2xl border border-gray-100 p-5 shadow-xs hover:border-emerald-200 hover:shadow-md transition-all flex flex-col justify-between group"
                    >
                        <div class="space-y-2.5">
                            <!-- Header: Category & View Count -->
                            <div class="flex items-center justify-between gap-2">
                                <span
                                    class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md bg-emerald-50 text-emerald-800 text-[11px] font-semibold"
                                >
                                    <component :is="getCategoryIcon(article.category)" class="size-3 text-emerald-600" />
                                    {{ article.category }}
                                </span>
                                <span class="text-[11px] text-slate-400 flex items-center gap-1">
                                    <Eye class="size-3" />
                                    {{ article.view_count }}
                                </span>
                            </div>

                            <!-- Title -->
                            <h3 class="text-sm font-bold text-slate-900 group-hover:text-[#003628] transition-colors leading-snug">
                                {{ article.title }}
                            </h3>

                            <!-- Excerpt -->
                            <p class="text-xs text-slate-500 line-clamp-2 leading-relaxed">
                                {{ stripHtml(article.content) }}...
                            </p>
                        </div>

                        <!-- Footer -->
                        <div class="pt-4 mt-3 border-t border-slate-50 flex items-center justify-between text-[11px] text-slate-400">
                            <div class="flex items-center gap-2 font-medium">
                                <Clock class="size-3.5" />
                                <span>{{ formatDate(article.created_at) }}</span>
                            </div>
                            <span class="font-bold text-[#003628] flex items-center gap-1 group-hover:translate-x-0.5 transition-transform">
                                Baca Panduan <ChevronRight class="size-3" />
                            </span>
                        </div>
                    </Link>
                </div>

                <!-- Empty State -->
                <div
                    v-else
                    class="bg-white rounded-3xl border border-slate-200/80 p-10 text-center space-y-3"
                >
                    <div class="size-12 rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-center mx-auto text-slate-400">
                        <HelpCircle class="size-6" />
                    </div>
                    <h3 class="text-sm font-bold text-slate-800">Artikel Tidak Ditemukan</h3>
                    <p class="text-xs text-slate-400 max-w-sm mx-auto">
                        Coba gunakan kata kunci yang berbeda atau pilih kategori lain.
                    </p>
                    <div class="flex items-center justify-center gap-2 pt-2">
                        <button
                            v-if="search || selectedCategory"
                            type="button"
                            class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-xs font-semibold text-slate-700 transition-colors cursor-pointer"
                            @click="clearFilters"
                        >
                            Reset Pencarian
                        </button>
                        <Link
                            href="/kb/create"
                            class="px-4 py-2 rounded-xl bg-[#003628] text-white text-xs font-bold hover:bg-[#00281e] transition-colors"
                        >
                            Tulis Artikel Baru
                        </Link>
                    </div>
                </div>

                <!-- Pagination Links -->
                <nav
                    v-if="articles.links && articles.links.length > 3"
                    class="flex flex-wrap items-center justify-center gap-1.5 pt-4"
                    aria-label="Navigasi halaman"
                >
                    <Link
                        v-for="link in articles.links"
                        :key="link.label"
                        :href="link.url || '#'"
                        :class="[
                            'min-w-9 rounded-xl border px-3 py-1.5 text-center text-xs font-bold transition-all',
                            link.active
                                ? 'border-[#003628] bg-[#003628] text-white shadow-xs'
                                : link.url
                                  ? 'border-slate-200 bg-white text-slate-600 hover:border-emerald-300 hover:text-[#003628]'
                                  : 'cursor-not-allowed border-slate-100 bg-slate-50 text-slate-300',
                        ]"
                        :aria-current="link.active ? 'page' : undefined"
                        :aria-disabled="!link.url"
                        v-html="link.label"
                    />
                </nav>
            </div>

            <!-- Helpdesk Banner at the bottom -->
            <div
                class="flex flex-col sm:flex-row items-center justify-between gap-4 rounded-3xl border border-slate-200/80 bg-white p-5 sm:p-6 shadow-xs"
            >
                <div class="flex items-center gap-4">
                    <div
                        class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-700"
                    >
                        <LifeBuoy class="size-5.5" />
                    </div>
                    <div>
                        <h4 class="text-sm font-black text-slate-900">
                            Butuh bantuan lebih lanjut?
                        </h4>
                        <p class="text-xs text-slate-400 mt-0.5">
                            Jika panduan di atas belum menyelesaikan masalah Anda, buat tiket ke tim IT Helpdesk kami.
                        </p>
                    </div>
                </div>

                <Link
                    href="/helpdesk"
                    class="inline-flex h-10 shrink-0 items-center gap-2 rounded-xl bg-[#003628] px-5 text-xs font-bold text-white shadow-xs hover:bg-[#00281e] transition-all"
                >
                    Buka Tiket Helpdesk
                    <ChevronRight class="size-4" />
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
