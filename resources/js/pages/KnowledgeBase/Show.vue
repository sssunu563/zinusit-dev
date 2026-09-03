<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { 
    ChevronLeft, 
    Clock, 
    Eye, 
    User,
    Share2,
    MessageSquare,
    ThumbsUp,
    Bookmark
} from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps<{
    article: any;
    related: any[];
}>();

const breadcrumbs = [
    { title: 'Knowledge Base', href: '/kb' },
    { title: props.article.title, href: `/kb/${props.article.slug}` },
];
</script>

<template>
    <Head :title="article.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="min-h-screen bg-slate-50/50 pb-20">
            <div class="max-w-5xl mx-auto px-6 pt-12">
                <!-- Header -->
                <Link href="/kb" class="inline-flex items-center gap-2 text-xs font-black uppercase tracking-widest text-slate-400 hover:text-[#003628] transition-all mb-8">
                    <ChevronLeft class="size-4" /> Kembali ke Indeks
                </Link>

                <div class="bg-white rounded-[40px] border border-slate-100 shadow-2xl shadow-[#003628]/5 overflow-hidden">
                    <!-- Hero Content -->
                    <div class="p-8 md:p-16 border-b border-slate-50">
                        <div class="flex items-center gap-4 mb-8">
                            <span class="px-5 py-2 rounded-2xl bg-[#003628] text-white text-[11px] font-black uppercase tracking-widest shadow-lg shadow-emerald-950/20">
                                {{ article.category }}
                            </span>
                        </div>
                        
                        <h1 class="text-3xl md:text-5xl font-black text-slate-800 tracking-tight leading-[1.1] mb-8">
                            {{ article.title }}
                        </h1>

                        <div class="flex flex-wrap items-center gap-8 text-[11px] font-black text-slate-400 uppercase tracking-widest">
                            <div class="flex items-center gap-3">
                                <div class="size-8 rounded-xl bg-slate-100 flex items-center justify-center text-slate-400">
                                    <User class="size-4" />
                                </div>
                                <span class="text-slate-600">{{ article.author?.name }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <Clock class="size-4" /> {{ new Date(article.created_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) }}
                            </div>
                            <div class="flex items-center gap-2 text-emerald-600 bg-emerald-50 px-3 py-1 rounded-lg">
                                <Eye class="size-4" /> {{ article.view_count }} Views
                            </div>
                        </div>
                    </div>

                    <!-- Article Content -->
                    <div class="p-8 md:p-16 prose prose-slate max-w-none prose-headings:font-black prose-headings:tracking-tight prose-a:text-[#003628] prose-img:rounded-3xl">
                        <div v-html="article.content"></div>
                    </div>

                    <!-- Footer / Interaction -->
                    <div class="p-8 md:p-16 bg-slate-50/50 border-t border-slate-100 flex flex-col md:flex-row md:items-center justify-between gap-8">
                        <div class="space-y-1">
                            <p class="text-sm font-black text-slate-800 tracking-tight">Apakah artikel ini membantu?</p>
                            <p class="text-[11px] font-medium text-slate-400">Terima kasih atas masukan Anda!</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <button class="h-12 px-6 rounded-2xl bg-white border border-slate-200 text-slate-600 hover:text-emerald-600 hover:border-emerald-200 transition-all flex items-center gap-2 text-[11px] font-black uppercase tracking-widest shadow-sm">
                                <ThumbsUp class="size-4" /> Ya
                            </button>
                            <button class="h-12 px-6 rounded-2xl bg-white border border-slate-200 text-slate-600 hover:text-rose-600 hover:border-rose-200 transition-all flex items-center gap-2 text-[11px] font-black uppercase tracking-widest shadow-sm">
                                <MessageSquare class="size-4" /> Tidak
                            </button>
                            <button class="size-12 rounded-2xl bg-white border border-slate-200 text-slate-400 hover:text-[#003628] transition-all flex items-center justify-center shadow-sm">
                                <Share2 class="size-5" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Related Content -->
                <div v-if="related.length > 0" class="mt-20">
                    <div class="flex items-center gap-4 mb-10">
                        <Bookmark class="size-6 text-[#d99528]" />
                        <h2 class="text-2xl font-black text-slate-800 tracking-tight">Artikel Terkait</h2>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                        <Link 
                            v-for="item in related" 
                            :key="item.id"
                            :href="`/kb/${item.slug}`"
                            class="bg-white p-8 rounded-[40px] border border-slate-100 shadow-xl shadow-[#003628]/5 hover:-translate-y-1 transition-all group"
                        >
                            <h3 class="text-lg font-black text-slate-800 group-hover:text-[#003628] transition-colors leading-tight mb-4">
                                {{ item.title }}
                            </h3>
                            <div class="flex items-center gap-2 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                <Clock class="size-3.5" /> {{ new Date(item.created_at).toLocaleDateString() }}
                            </div>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style>
/* Base typography for v-html content */
.prose {
    font-size: 1.125rem;
    line-height: 1.8;
}
.prose p {
    margin-bottom: 2rem;
}
.prose h2 {
    margin-top: 4rem;
    margin-bottom: 2rem;
}
.prose ul {
    margin-bottom: 2.5rem;
}
.prose li {
    margin-bottom: 1rem;
}
</style>
