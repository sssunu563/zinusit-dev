<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Search,
    BookOpen,
    ChevronRight,
    Clock,
    Eye,
    FileText,
    HelpCircle,
    Cpu,
    ShieldCheck,
    Globe,
} from 'lucide-vue-next';
import { ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps<{
    articles: any;
    categories: string[];
    filters: {
        search: string;
        category: string;
    };
}>();

const search = ref(props.filters.search || '');
const category = ref(props.filters.category || '');
const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Knowledge Base', href: '/kb' },
];

const applyFilters = () => {
    router.get(
        '/kb',
        {
            search: search.value || undefined,
            category: category.value || undefined,
        },
        {
            preserveState: true,
            replace: true,
            only: ['articles', 'filters'],
        },
    );
};

watch(search, applyFilters);
watch(category, applyFilters);

const getCategoryIcon = (category: string) => {
    const c = category.toLowerCase();
    if (c.includes('hardware')) return Cpu;
    if (c.includes('security')) return ShieldCheck;
    if (c.includes('network')) return Globe;
    if (c.includes('faq')) return HelpCircle;
    return FileText;
};

const stripHtml = (html: string) => {
    return html.replace(/<[^>]*>?/gm, '').substring(0, 120);
};
</script>

<template>
    <Head title="Knowledge Base" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="min-h-screen bg-slate-50/50">
            <!-- Hero Search Section -->
            <div
                class="relative overflow-hidden bg-[#003628] px-6 pt-12 pb-24 md:pt-16 md:pb-28"
            >
                <div class="absolute inset-0 opacity-10">
                    <div
                        class="absolute top-0 left-0 h-96 w-96 -translate-x-1/2 -translate-y-1/2 rounded-full bg-emerald-400 blur-[100px]"
                    />
                    <div
                        class="absolute right-0 bottom-0 h-96 w-96 translate-x-1/2 translate-y-1/2 rounded-full bg-emerald-500 blur-[100px]"
                    />
                </div>

                <div class="relative z-10 mx-auto max-w-4xl text-center">
                    <h1
                        class="mb-4 text-3xl font-black tracking-tight text-white md:text-5xl"
                    >
                        How can we
                        <span class="text-[#d99528] italic">help you?</span>
                    </h1>
                    <p
                        class="mb-8 text-sm font-medium text-emerald-100/70 md:text-base"
                    >
                        Cari panduan, dokumentasi teknis, dan solusi masalah IT
                        di sini.
                    </p>

                    <div class="group relative mx-auto max-w-2xl">
                        <Search
                            class="absolute top-1/2 left-6 size-6 -translate-y-1/2 text-emerald-800/40 transition-colors group-focus-within:text-[#003628]"
                        />
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Cari artikel (misal: VPN, Printer, Password)..."
                            class="h-14 w-full rounded-2xl border-none bg-white pr-8 pl-16 text-base font-medium text-slate-800 shadow-2xl shadow-emerald-950/20 transition-all placeholder:text-slate-400 focus:ring-4 focus:ring-emerald-500/20 md:h-16 md:text-lg"
                        />
                    </div>
                </div>
            </div>

            <div
                class="relative z-20 mx-auto -mt-8 max-w-7xl px-6 pb-20 md:-mt-12"
            >
                <!-- Quick Category Access -->
                <div
                    v-if="categories.length"
                    class="mb-12 grid grid-cols-2 gap-4 md:mb-14 md:grid-cols-4 md:gap-6"
                >
                    <button
                        v-for="cat in categories.slice(0, 4)"
                        :key="cat"
                        type="button"
                        class="group cursor-pointer rounded-2xl border border-white bg-white p-6 text-center shadow-xl shadow-[#003628]/5 transition-all hover:border-emerald-100 md:p-8"
                        :class="
                            category === cat ? 'ring-2 ring-emerald-200' : ''
                        "
                        @click="category = category === cat ? '' : cat"
                    >
                        <div
                            class="mx-auto mb-6 flex size-16 items-center justify-center rounded-3xl bg-emerald-50 transition-all group-hover:bg-[#003628] group-hover:text-white"
                        >
                            <component
                                :is="getCategoryIcon(cat)"
                                class="size-8"
                            />
                        </div>
                        <h3
                            class="text-sm font-black tracking-widest text-slate-800 uppercase"
                        >
                            {{ cat }}
                        </h3>
                    </button>
                </div>

                <div class="flex flex-col gap-12 lg:flex-row">
                    <!-- Articles List -->
                    <div class="flex-1 space-y-8">
                        <div class="flex items-center justify-between">
                            <h2
                                class="flex items-center gap-3 text-2xl font-black tracking-tight text-slate-800"
                            >
                                <BookOpen class="size-6 text-[#003628]" />
                                Dokumentasi Terbaru
                            </h2>
                            <span class="text-xs font-semibold text-slate-400"
                                >{{ articles.total }} artikel</span
                            >
                        </div>

                        <div class="grid grid-cols-1 gap-8 md:grid-cols-2">
                            <Link
                                v-for="article in articles.data"
                                :key="article.id"
                                :href="`/kb/${article.slug}`"
                                class="group rounded-2xl border border-slate-100 bg-white p-8 shadow-xl shadow-[#003628]/5 transition-all hover:-translate-y-1 hover:shadow-2xl hover:shadow-[#003628]/10"
                            >
                                <div class="mb-6 flex items-center gap-3">
                                    <span
                                        class="rounded-full bg-emerald-50 px-4 py-1.5 text-[10px] font-black tracking-widest text-emerald-700 uppercase"
                                        >{{ article.category }}</span
                                    >
                                </div>
                                <h3
                                    class="mb-4 text-xl leading-tight font-black text-slate-800 transition-colors group-hover:text-[#003628]"
                                >
                                    {{ article.title }}
                                </h3>
                                <p
                                    class="mb-8 line-clamp-2 text-sm leading-relaxed text-slate-500"
                                >
                                    {{ stripHtml(article.content) }}...
                                </p>

                                <div
                                    class="flex items-center justify-between border-t border-slate-50 pt-6"
                                >
                                    <div
                                        class="flex items-center gap-4 text-[11px] font-bold text-slate-400"
                                    >
                                        <div class="flex items-center gap-1.5">
                                            <Clock class="size-3.5" />
                                            {{
                                                new Date(
                                                    article.created_at,
                                                ).toLocaleDateString('id-ID', {
                                                    day: 'numeric',
                                                    month: 'short',
                                                })
                                            }}
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <Eye class="size-3.5" />
                                            {{ article.view_count }}
                                        </div>
                                    </div>
                                    <ChevronRight
                                        class="size-5 text-slate-300 transition-all group-hover:translate-x-1 group-hover:text-[#003628]"
                                    />
                                </div>
                            </Link>
                        </div>

                        <nav
                            v-if="articles.links?.length > 3"
                            class="flex flex-wrap items-center justify-center gap-2 pt-2"
                            aria-label="Navigasi artikel"
                        >
                            <Link
                                v-for="link in articles.links"
                                :key="link.label"
                                :href="link.url || '#'"
                                :class="[
                                    'min-w-9 rounded-lg border px-3 py-2 text-center text-xs font-bold transition-colors',
                                    link.active
                                        ? 'border-[#003628] bg-[#003628] text-white'
                                        : link.url
                                          ? 'border-slate-200 bg-white text-slate-600 hover:border-emerald-200 hover:text-[#003628]'
                                          : 'cursor-not-allowed border-slate-100 bg-slate-50 text-slate-300',
                                ]"
                                :aria-current="link.active ? 'page' : undefined"
                                :aria-disabled="!link.url"
                                v-html="link.label"
                            />
                        </nav>

                        <!-- Pagination (Simple) -->
                        <div
                            v-if="articles.data.length === 0"
                            class="rounded-2xl border border-dashed border-slate-200 bg-white px-6 py-16 text-center"
                        >
                            <div
                                class="mx-auto mb-6 flex size-20 items-center justify-center rounded-full bg-slate-50 text-slate-300"
                            >
                                <Search class="size-10" />
                            </div>
                            <h3
                                class="text-xl font-black text-slate-800 uppercase"
                            >
                                No articles found
                            </h3>
                            <p class="mt-2 text-slate-400">
                                Coba kata kunci lain atau kategori berbeda.
                            </p>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <aside class="w-full space-y-6 lg:w-80">
                        <div
                            v-if="categories.length"
                            class="rounded-[40px] border border-slate-100 bg-white p-8 shadow-xl shadow-[#003628]/5"
                        >
                            <h4
                                class="mb-6 text-xs font-black tracking-[0.2em] text-slate-400 uppercase"
                            >
                                Popular Topics
                            </h4>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="cat in categories"
                                    :key="cat"
                                    type="button"
                                    class="rounded-2xl border border-slate-100 bg-slate-50 px-5 py-2.5 text-[11px] font-black tracking-widest text-slate-600 uppercase transition-all hover:bg-[#003628] hover:text-white"
                                    :class="
                                        category === cat
                                            ? 'bg-[#003628] text-white'
                                            : ''
                                    "
                                    @click="
                                        category = category === cat ? '' : cat
                                    "
                                >
                                    {{ cat }}
                                </button>
                            </div>
                        </div>

                        <div
                            class="relative overflow-hidden rounded-2xl bg-[#d99528] p-8 text-white shadow-xl shadow-amber-900/20"
                        >
                            <div class="relative z-10">
                                <h4
                                    class="mb-2 text-xl font-black tracking-tight"
                                >
                                    Butuh Bantuan?
                                </h4>
                                <p
                                    class="mb-6 text-sm leading-relaxed text-amber-50"
                                >
                                    Jika tidak menemukan solusi di dokumentasi,
                                    teknisi kami siap membantu.
                                </p>
                                <Link
                                    href="/helpdesk"
                                    class="inline-flex h-12 items-center rounded-2xl bg-[#003628] px-8 text-[11px] font-black tracking-widest text-white uppercase shadow-lg shadow-black/20 transition-all hover:-translate-y-1"
                                >
                                    Buka Tiket Baru
                                </Link>
                            </div>
                            <HelpCircle
                                class="absolute -right-6 -bottom-6 size-32 text-amber-600/20"
                            />
                        </div>
                    </aside>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
