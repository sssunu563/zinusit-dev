<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Bookmark,
    Check,
    Clock,
    Copy,
    Cpu,
    Edit3,
    Eye,
    FileText,
    Globe,
    HelpCircle,
    ShieldCheck,
    ThumbsUp,
    User,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps<{
    article: {
        id: number;
        title: string;
        slug: string;
        category: string;
        content: string;
        view_count: number;
        created_at: string;
        author_id?: number;
        author?: {
            id: number;
            name: string;
        };
    };
    related: Array<{
        id: number;
        title: string;
        slug: string;
        category: string;
        created_at: string;
    }>;
}>();

const page = usePage();
const currentUser = computed(() => (page.props.auth as any)?.user);
const canEdit = computed(() => {
    if (!currentUser.value) return false;
    return (
        currentUser.value.id === props.article.author_id ||
        Boolean(currentUser.value.is_admin)
    );
});

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Knowledge Base', href: '/kb' },
    { title: props.article.title, href: `/kb/${props.article.slug}` },
];

const copied = ref(false);
const helpful = ref<boolean | null>(null);

const copyLink = () => {
    navigator.clipboard.writeText(window.location.href);
    copied.value = true;
    setTimeout(() => {
        copied.value = false;
    }, 2000);
};

const getCategoryIcon = (category: string) => {
    const c = (category || '').toLowerCase();
    if (c.includes('hardware') || c.includes('laptop') || c.includes('pc')) return Cpu;
    if (c.includes('security') || c.includes('keamanan')) return ShieldCheck;
    if (c.includes('network') || c.includes('wifi') || c.includes('vpn') || c.includes('jaringan')) return Globe;
    if (c.includes('faq') || c.includes('tanya')) return HelpCircle;
    return FileText;
};

const formatContent = (content: string) => {
    if (!content) return '';
    if (/<[a-z][\s\S]*>/i.test(content)) {
        return content;
    }
    let html = content
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');

    html = html.replace(/^### (.*$)/gim, '<h3 class="text-lg font-bold text-slate-900 mt-6 mb-2">$1</h3>');
    html = html.replace(/^## (.*$)/gim, '<h2 class="text-xl font-black text-slate-900 mt-8 mb-3 border-b border-slate-100 pb-2">$1</h2>');
    html = html.replace(/^# (.*$)/gim, '<h1 class="text-2xl font-black text-slate-900 mt-8 mb-4">$1</h1>');
    html = html.replace(/\*\*(.*?)\*\*/gim, '<strong>$1</strong>');
    html = html.replace(/\*(.*?)\*/gim, '<em>$1</em>');
    html = html.replace(/^\> (.*$)/gim, '<blockquote class="border-l-4 border-[#003628] bg-emerald-50/70 p-4 rounded-r-2xl my-4 text-slate-700 text-sm">$1</blockquote>');
    html = html.replace(/```([\s\S]*?)```/gim, '<pre class="bg-slate-900 text-slate-100 p-4 rounded-2xl my-4 overflow-x-auto text-xs font-mono leading-relaxed"><code>$1</code></pre>');
    html = html.replace(/`([^`]+)`/gim, '<code class="bg-slate-100 text-emerald-800 px-2 py-0.5 rounded text-xs font-mono">$1</code>');
    html = html.replace(/^\- (.*$)/gim, '<li class="ml-5 list-disc text-slate-700 my-1 text-sm">$1</li>');
    html = html.replace(/^\d+\. (.*$)/gim, '<li class="ml-5 list-decimal text-slate-700 my-1 text-sm">$1</li>');
    html = html.replace(/\n\n/gim, '</p><p class="mb-4 text-slate-700 leading-relaxed text-sm sm:text-base">');
    return `<p class="mb-4 text-slate-700 leading-relaxed text-sm sm:text-base">${html}</p>`;
};

const formatDate = (dateStr: string) => {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
};
</script>

<template>
    <Head :title="`${article.title} - Knowledge Base`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-[1100px] space-y-6 p-2 sm:p-4 md:p-6">
            <!-- Top Navigation & Action Bar -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Link
                    href="/kb"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-600 hover:text-[#003628] hover:border-emerald-300 transition-all shadow-2xs"
                >
                    <ArrowLeft class="size-4" /> Kembali ke Indeks
                </Link>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50 transition-all shadow-2xs"
                        @click="copyLink"
                    >
                        <component :is="copied ? Check : Copy" class="size-4 text-emerald-600" />
                        {{ copied ? 'Tautan Disalin!' : 'Bagikan' }}
                    </button>

                    <Link
                        v-if="canEdit"
                        :href="`/kb/${article.slug}/edit`"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-[#003628] px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-[#00281e] transition-all"
                    >
                        <Edit3 class="size-3.5 text-amber-400" />
                        Edit Panduan
                    </Link>
                </div>
            </div>

            <!-- Main Article Card -->
            <article
                class="overflow-hidden rounded-3xl border border-slate-100 bg-white shadow-xs"
            >
                <!-- Hero Header -->
                <div class="border-b border-slate-100 p-6 sm:p-10">
                    <div class="mb-4 flex flex-wrap items-center gap-2">
                        <span
                            class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-50 px-3.5 py-1 text-xs font-black uppercase tracking-wider text-emerald-800"
                        >
                            <component :is="getCategoryIcon(article.category)" class="size-3.5" />
                            {{ article.category }}
                        </span>
                    </div>

                    <h1
                        class="text-2xl sm:text-4xl font-black text-slate-900 tracking-tight leading-snug mb-6"
                    >
                        {{ article.title }}
                    </h1>

                    <div
                        class="flex flex-wrap items-center gap-y-2 gap-x-6 text-xs text-slate-500 font-medium"
                    >
                        <div class="flex items-center gap-2">
                            <div
                                class="flex size-7 items-center justify-center rounded-full bg-emerald-50 text-emerald-700 font-bold text-xs"
                            >
                                <User class="size-3.5" />
                            </div>
                            <span class="font-semibold text-slate-800">
                                {{ article.author?.name || 'Tim IT Zinus' }}
                            </span>
                        </div>

                        <div class="flex items-center gap-1.5">
                            <Clock class="size-4 text-slate-400" />
                            <span>{{ formatDate(article.created_at) }}</span>
                        </div>

                        <div class="flex items-center gap-1.5">
                            <Eye class="size-4 text-slate-400" />
                            <span>{{ article.view_count }} kali dilihat</span>
                        </div>
                    </div>
                </div>

                <!-- Article Body Content -->
                <div class="p-6 sm:p-10">
                    <div
                        class="prose prose-slate max-w-none text-slate-700"
                        v-html="formatContent(article.content)"
                    ></div>
                </div>

                <!-- Article Feedback Bar -->
                <div
                    class="flex flex-col sm:flex-row items-center justify-between gap-4 border-t border-slate-100 bg-slate-50/50 p-6 sm:p-8"
                >
                    <div>
                        <p class="text-sm font-black text-slate-900">
                            Apakah panduan ini membantu Anda?
                        </p>
                        <p class="text-xs text-slate-400 mt-0.5">
                            Masukan Anda membantu kami menyempurnakan dokumentasi IT.
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            :class="[
                                'inline-flex items-center gap-1.5 rounded-xl border px-4 py-2 text-xs font-bold transition-all',
                                helpful === true
                                    ? 'border-emerald-600 bg-emerald-50 text-emerald-800 shadow-xs'
                                    : 'border-slate-200 bg-white text-slate-700 hover:border-emerald-300',
                            ]"
                            @click="helpful = true"
                        >
                            <ThumbsUp class="size-4" /> Ya, Membantu
                        </button>
                        <button
                            type="button"
                            :class="[
                                'inline-flex items-center gap-1.5 rounded-xl border px-4 py-2 text-xs font-bold transition-all',
                                helpful === false
                                    ? 'border-rose-600 bg-rose-50 text-rose-800 shadow-xs'
                                    : 'border-slate-200 bg-white text-slate-700 hover:border-rose-300',
                            ]"
                            @click="helpful = false"
                        >
                            Kurang Membantu
                        </button>
                    </div>
                </div>
            </article>

            <!-- Related Articles -->
            <div v-if="related && related.length > 0" class="space-y-4 pt-4">
                <div class="flex items-center gap-2">
                    <Bookmark class="size-4 text-[#003628]" />
                    <h2 class="text-base font-black text-slate-900 tracking-tight">
                        Panduan Terkait di Kategori Ini
                    </h2>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                    <Link
                        v-for="item in related"
                        :key="item.id"
                        :href="`/kb/${item.slug}`"
                        class="group flex flex-col justify-between rounded-2xl border border-slate-100 bg-white p-5 shadow-xs transition-all hover:-translate-y-0.5 hover:border-emerald-200 hover:shadow-md"
                    >
                        <h3
                            class="line-clamp-2 text-sm font-black text-slate-900 group-hover:text-[#003628] transition-colors mb-4"
                        >
                            {{ item.title }}
                        </h3>
                        <div class="flex items-center justify-between text-[11px] text-slate-400 font-medium">
                            <span>{{ formatDate(item.created_at) }}</span>
                            <span class="font-bold text-[#003628]">Baca →</span>
                        </div>
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
