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
                class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#003628] via-[#004735] to-[#012d22] p-6 sm:p-10 text-white shadow-xl shadow-emerald-950/10"
            >
                <!-- Subtle decorative background shapes -->
                <div class="absolute inset-0 pointer-events-none opacity-10">
                    <div class="absolute -top-12 -right-12 h-64 w-64 rounded-full bg-emerald-400 blur-3xl" />
                    <div class="absolute -bottom-12 -left-12 h-64 w-64 rounded-full bg-amber-400 blur-3xl" />
                </div>

                <div class="relative z-10 mx-auto max-w-3xl text-center space-y-4">
                    <h2 class="text-2xl sm:text-3xl font-black tracking-tight">
                        Ada kendala atau butuh panduan teknis?
                    </h2>
                    <p class="text-xs sm:text-sm text-emerald-100/80 font-medium max-w-xl mx-auto">
                        Cari solusi cepat untuk masalah printer, setup VPN, email, akun sistem, hingga konfigurasi hardware.
                    </p>

                    <!-- Search Input Box -->
                    <div class="relative mx-auto max-w-2xl pt-2">
                        <Search
                            class="absolute top-1/2 left-4 size-5 -translate-y-1/2 text-slate-400"
                        />
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Ketik kata kunci pencarian (misal: VPN, Printer, Zoom, Password)..."
                            class="h-13 w-full rounded-2xl border-none bg-white pr-10 pl-12 text-sm sm:text-base font-semibold text-slate-800 shadow-xl placeholder:text-slate-400 placeholder:font-normal focus:ring-4 focus:ring-emerald-400/30 transition-all"
                        />
                        <button
                            v-if="search"
                            type="button"
                            class="absolute top-1/2 right-3 -translate-y-1/2 rounded-full p-1 text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"
                            @click="search = ''"
                        >
                            <X class="size-4" />
                        </button>
                    </div>

                    <!-- Category Chips Filter -->
                    <div class="flex flex-wrap items-center justify-center gap-2 pt-3">
                        <button
                            type="button"
                            :class="[
                                'inline-flex items-center gap-1.5 rounded-xl px-3.5 py-1.5 text-xs font-bold transition-all',
                                !selectedCategory
                                    ? 'bg-[#d99528] text-slate-900 shadow-sm'
                                    : 'bg-white/10 text-emerald-100 hover:bg-white/20',
                            ]"
                            @click="selectCategory('')"
                        >
                            Semua Kategori
                        </button>
                        <button
                            v-for="cat in allCategories"
                            :key="cat"
                            type="button"
                            :class="[
                                'inline-flex items-center gap-1.5 rounded-xl px-3.5 py-1.5 text-xs font-bold transition-all',
                                selectedCategory === cat
                                    ? 'bg-[#d99528] text-slate-900 shadow-sm'
                                    : 'bg-white/10 text-emerald-100 hover:bg-white/20',
                            ]"
                            @click="selectCategory(cat)"
                        >
                            <component :is="getCategoryIcon(cat)" class="size-3.5" />
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
                    class="grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3"
                >
                    <Link
                        v-for="article in articles.data"
                        :key="article.id"
                        :href="`/kb/${article.slug}`"
                        class="group flex flex-col justify-between rounded-3xl border border-slate-100 bg-white p-6 shadow-xs transition-all duration-200 hover:-translate-y-1 hover:border-emerald-200 hover:shadow-xl hover:shadow-emerald-950/5"
                    >
                        <div>
                            <!-- Header: Category & View Count -->
                            <div class="mb-4 flex items-center justify-between gap-2">
                                <span
                                    class="inline-flex items-center gap-1 rounded-xl bg-emerald-50 px-3 py-1 text-[10px] font-black uppercase tracking-wider text-emerald-800"
                                >
                                    <component :is="getCategoryIcon(article.category)" class="size-3" />
                                    {{ article.category }}
                                </span>
                                <span class="flex items-center gap-1 text-[11px] font-semibold text-slate-400">
                                    <Eye class="size-3.5" />
                                    {{ article.view_count }}
                                </span>
                            </div>

                            <!-- Title -->
                            <h3
                                class="mb-2 line-clamp-2 text-lg font-black tracking-tight text-slate-900 transition-colors group-hover:text-[#003628]"
                            >
                                {{ article.title }}
                            </h3>

                            <!-- Excerpt -->
                            <p class="mb-6 line-clamp-3 text-xs leading-relaxed text-slate-500">
                                {{ stripHtml(article.content) }}...
                            </p>
                        </div>

                        <!-- Footer -->
                        <div class="border-t border-slate-100 pt-4 flex items-center justify-between text-xs">
                            <div class="flex items-center gap-2 text-[11px] font-medium text-slate-400">
                                <Clock class="size-3.5" />
                                <span>{{ formatDate(article.created_at) }}</span>
                            </div>

                            <span
                                class="inline-flex items-center gap-1 font-bold text-[#003628] transition-transform group-hover:translate-x-1"
                            >
                                Baca Panduan
                                <ChevronRight class="size-4" />
                            </span>
                        </div>
                    </Link>
                </div>

                <!-- Empty State -->
                <div
                    v-else
                    class="rounded-3xl border border-dashed border-slate-200 bg-white p-12 text-center"
                >
                    <div
                        class="mx-auto mb-4 flex size-16 items-center justify-center rounded-2xl bg-slate-50 text-slate-400"
                    >
                        <Search class="size-8 text-slate-300" />
                    </div>
                    <h3 class="text-lg font-black text-slate-900 uppercase">
                        Tidak Ada Artikel Ditemukan
                    </h3>
                    <p class="mt-1 text-xs sm:text-sm text-slate-500 max-w-sm mx-auto">
                        Coba gunakan kata kunci pencarian yang berbeda atau pilih kategori lain.
                    </p>
                    <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                        <button
                            v-if="search || selectedCategory"
                            type="button"
                            class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 transition-colors"
                            @click="clearFilters"
                        >
                            Reset Pencarian
                        </button>
                        <Link
                            href="/kb/create"
                            class="rounded-xl bg-[#003628] px-4 py-2 text-xs font-bold text-white hover:bg-[#00281e] transition-colors"
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
                class="flex flex-col sm:flex-row items-center justify-between gap-4 rounded-3xl border border-emerald-100 bg-gradient-to-r from-emerald-50/80 to-white p-6"
            >
                <div class="flex items-center gap-4">
                    <div
                        class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-[#003628] text-white shadow-md shadow-[#003628]/15"
                    >
                        <LifeBuoy class="size-6" />
                    </div>
                    <div>
                        <h4 class="text-sm font-black text-slate-900 uppercase">
                            Butuh bantuan lebih lanjut?
                        </h4>
                        <p class="text-xs text-slate-500 mt-0.5">
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
