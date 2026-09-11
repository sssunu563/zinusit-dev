<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    BookOpen,
    Building2,
    Calendar,
    Check,
    ChevronRight,
    Clock,
    Copy,
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
    Share2,
    ShieldCheck,
    ThumbsDown,
    ThumbsUp,
    User,
    Wrench,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface ArticleDetail {
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
}

interface RelatedItem {
    id: number;
    title: string;
    slug: string;
    category: string;
    created_at: string;
}

const props = defineProps<{
    article: ArticleDetail;
    related: RelatedItem[];
    canEdit?: boolean;
}>();

const copied = ref(false);
const helpful = ref<boolean | null>(null);

const copyLink = () => {
    navigator.clipboard.writeText(window.location.href);
    copied.value = true;
    setTimeout(() => {
        copied.value = false;
    }, 2000);
};

const handleShare = async () => {
    const url = window.location.href;
    if (navigator.share) {
        try {
            await navigator.share({
                title: props.article.title,
                text: `Panduan IT: ${props.article.title}`,
                url,
            });
        } catch {}
    } else {
        copyLink();
    }
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

    html = html.replace(/^### (.*$)/gim, '<h3 class="text-base sm:text-lg font-bold text-slate-900 mt-6 mb-2">$1</h3>');
    html = html.replace(/^## (.*$)/gim, '<h2 class="text-lg sm:text-xl font-black text-slate-900 mt-7 mb-3 border-b border-slate-100 pb-2">$1</h2>');
    html = html.replace(/^# (.*$)/gim, '<h1 class="text-xl sm:text-2xl font-black text-slate-900 mt-8 mb-4">$1</h1>');

    // Images (Markdown: ![alt](url))
    html = html.replace(
        /!\[(.*?)\]\((.*?)\)/gim,
        '<figure class="my-6 text-center"><img src="$2" alt="$1" class="rounded-2xl max-w-full h-auto border border-slate-200/80 shadow-xs mx-auto object-contain max-h-[540px]" loading="lazy" /><figcaption class="text-center text-xs text-slate-400 mt-2 italic">$1</figcaption></figure>',
    );

    html = html.replace(/\*\*(.*?)\*\*/gim, '<strong>$1</strong>');
    html = html.replace(/\*(.*?)\*/gim, '<em>$1</em>');
    html = html.replace(/^\> (.*$)/gim, '<blockquote class="border-l-4 border-[#003628] bg-emerald-50/70 p-4 rounded-r-2xl my-4 text-slate-700 text-xs sm:text-sm leading-relaxed">$1</blockquote>');
    html = html.replace(/```([\s\S]*?)```/gim, '<pre class="bg-slate-900 text-slate-100 p-4 rounded-2xl my-4 overflow-x-auto text-xs font-mono leading-relaxed"><code>$1</code></pre>');
    html = html.replace(/`([^`]+)`/gim, '<code class="bg-slate-100 text-emerald-800 px-1.5 py-0.5 rounded text-xs font-mono">$1</code>');
    html = html.replace(/^\- (.*$)/gim, '<li class="ml-5 list-disc text-slate-700 my-1 text-xs sm:text-sm">$1</li>');
    html = html.replace(/^\d+\. (.*$)/gim, '<li class="ml-5 list-decimal text-slate-700 my-1 text-xs sm:text-sm">$1</li>');
    html = html.replace(/\n\n/gim, '</p><p class="mb-3.5 text-slate-700 leading-relaxed text-xs sm:text-sm">');
    return `<p class="mb-3.5 text-slate-700 leading-relaxed text-xs sm:text-sm">${html}</p>`;
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
    <Head :title="`${article.title} - Pusat Bantuan IT Zinus`" />

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
        <main class="max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 pt-6 pb-16 flex-1">
            <div class="lg:grid lg:grid-cols-12 lg:gap-8 items-start">
                <!-- Left Column: Article Body -->
                <div class="lg:col-span-8 xl:col-span-9 space-y-6">
                    <!-- Breadcrumbs and Back Navigation -->
                    <div class="flex items-center justify-between gap-4">
                        <Link
                            href="/help"
                            class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-slate-900 transition-colors"
                        >
                            <ArrowLeft class="size-3.5" />
                            <span>Kembali ke Semua Panduan</span>
                        </Link>

                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-medium text-slate-600 bg-white border border-slate-200/80 hover:bg-slate-50 transition-colors cursor-pointer"
                                @click="copyLink"
                            >
                                <Check v-if="copied" class="size-3.5 text-emerald-600" />
                                <Copy v-else class="size-3.5 text-slate-400" />
                                <span>{{ copied ? 'Tersalin!' : 'Salin Tautan' }}</span>
                            </button>
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-medium text-slate-600 bg-white border border-slate-200/80 hover:bg-slate-50 transition-colors cursor-pointer"
                                @click="handleShare"
                            >
                                <Share2 class="size-3.5 text-slate-400" />
                                <span>Bagikan</span>
                            </button>
                        </div>
                    </div>

                    <!-- Article Body Card -->
                    <article class="bg-white rounded-3xl border border-slate-200/80 p-6 sm:p-10 shadow-xs space-y-6">
                        <!-- Article Meta Header -->
                        <div class="space-y-3 pb-6 border-b border-slate-100">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="px-2.5 py-0.5 rounded-md bg-emerald-50 text-emerald-700 text-xs font-semibold uppercase tracking-wider">
                                    {{ article.category }}
                                </span>
                                <span class="text-slate-300">·</span>
                                <span class="text-xs text-slate-400">
                                    Diperbarui {{ formatDate(article.created_at) }}
                                </span>
                                <span class="text-slate-300">·</span>
                                <span class="text-xs text-slate-400 flex items-center gap-1">
                                    <Eye class="size-3.5" />
                                    {{ article.view_count }} kali dibaca
                                </span>
                            </div>

                            <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight leading-tight">
                                {{ article.title }}
                            </h1>

                            <div v-if="article.author" class="flex items-center gap-2 pt-1 text-xs text-slate-500 font-medium">
                                <div class="size-6 rounded-full bg-slate-100 flex items-center justify-center text-slate-600 font-bold text-[10px]">
                                    {{ article.author.name.charAt(0).toUpperCase() }}
                                </div>
                                <span>Ditulis oleh {{ article.author.name }} (Tim IT Zinus)</span>
                            </div>
                        </div>

                        <!-- Formatted HTML / Markdown Content -->
                        <div
                            class="prose prose-slate max-w-none text-slate-700 text-sm sm:text-base leading-relaxed"
                            v-html="formatContent(article.content)"
                        />

                        <!-- Feedback Section -->
                        <div class="pt-8 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-4">
                            <div>
                                <p class="text-xs font-bold text-slate-800">Apakah panduan ini membantu Anda?</p>
                                <p class="text-[11px] text-slate-400">Masukan Anda membantu kami meningkatkan kualitas dokumentasi IT.</p>
                            </div>

                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    :disabled="helpful !== null"
                                    class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl border text-xs font-medium transition-all cursor-pointer disabled:opacity-70"
                                    :class="helpful === true ? 'bg-emerald-50 border-emerald-300 text-emerald-700 font-bold' : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'"
                                    @click="helpful = true"
                                >
                                    <ThumbsUp class="size-3.5" />
                                    <span>{{ helpful === true ? 'Terima Kasih!' : 'Ya, Membantu' }}</span>
                                </button>
                                <button
                                    type="button"
                                    :disabled="helpful !== null"
                                    class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl border text-xs font-medium transition-all cursor-pointer disabled:opacity-70"
                                    :class="helpful === false ? 'bg-red-50 border-red-300 text-red-700 font-bold' : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'"
                                    @click="helpful = false"
                                >
                                    <ThumbsDown class="size-3.5" />
                                    <span>{{ helpful === false ? 'Akan Kami Tingkatkan' : 'Kurang Membantu' }}</span>
                                </button>
                            </div>
                        </div>
                    </article>

                    <!-- Need More Help Banner (Disabled - Belum Siap) -->
                    <!-- 
                    <div class="bg-white rounded-3xl border border-slate-200/80 p-5 sm:p-6 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="flex items-start sm:items-center gap-4">
                            <div class="size-11 rounded-2xl bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0">
                                <Wrench class="size-5.5" />
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-slate-800">Masih Mengalami Kendala Teknis?</h3>
                                <p class="text-xs text-slate-400 mt-0.5 leading-snug">
                                    Tim IT Support Zinus siap membantu pengecekan perangkat keras, reset akun, dan perbaikan langsung di lokasi.
                                </p>
                            </div>
                        </div>

                        <a
                            href="mailto:it.support@zinus.com"
                            class="h-10 px-4 rounded-xl bg-gray-800 hover:bg-gray-700 text-white text-xs font-semibold inline-flex items-center justify-center gap-1.5 transition-colors shrink-0"
                        >
                            <Mail class="size-3.5" />
                            <span>Hubungi IT Helpdesk</span>
                        </a>
                    </div>
                    -->
                </div>

                <!-- Right Sidebar Column -->
                <div class="mt-8 lg:mt-0 lg:col-span-4 xl:col-span-3 space-y-5">
                    <!-- Related Articles in Category -->
                    <div v-if="related && related.length > 0" class="bg-white rounded-3xl border border-slate-200/80 p-5 shadow-xs space-y-3.5">
                        <div class="flex items-center gap-2">
                            <div class="size-8 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center">
                                <BookOpen class="size-4" />
                            </div>
                            <div>
                                <h3 class="text-xs font-bold text-slate-900">Panduan Terkait</h3>
                                <p class="text-[10px] text-slate-400">Dalam kategori {{ article.category }}</p>
                            </div>
                        </div>

                        <div class="space-y-2 pt-0.5">
                            <Link
                                v-for="item in related"
                                :key="item.id"
                                :href="`/help/${item.slug}`"
                                class="p-2.5 rounded-xl border border-slate-100 hover:border-emerald-200 hover:bg-slate-50/70 transition-all flex items-center justify-between gap-3 group"
                            >
                                <div class="min-w-0">
                                    <p class="text-xs font-semibold text-slate-800 group-hover:text-emerald-800 line-clamp-1">
                                        {{ item.title }}
                                    </p>
                                    <p class="text-[10px] text-slate-400 mt-0.5">
                                        {{ formatDate(item.created_at) }}
                                    </p>
                                </div>
                                <ChevronRight class="size-3.5 text-slate-300 group-hover:text-slate-600 shrink-0" />
                            </Link>
                        </div>
                    </div>

                    <!-- IT Helpdesk Service Info (Hidden) -->
                    <!-- 
                    <div class="bg-white rounded-3xl border border-slate-200/80 p-5 shadow-xs space-y-3.5">
                        <div class="flex items-center gap-2">
                            <div class="size-8 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center">
                                <HelpCircle class="size-4" />
                            </div>
                            <div>
                                <h3 class="text-xs font-bold text-slate-900">Layanan IT Support</h3>
                                <p class="text-[10px] text-slate-400">Bantuan teknis langsung</p>
                            </div>
                        </div>

                        <div class="space-y-2.5 text-xs text-slate-600">
                            <div class="flex items-start gap-2.5 p-2.5 rounded-xl bg-slate-50 border border-slate-100">
                                <MapPin class="size-3.5 text-emerald-600 shrink-0 mt-0.5" />
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Lokasi</p>
                                    <p class="text-xs font-semibold text-slate-700">Ruang IT Support &bull; Lantai 2 / Plant Area</p>
                                </div>
                            </div>

                            <div class="flex items-start gap-2.5 p-2.5 rounded-xl bg-slate-50 border border-slate-100">
                                <Clock class="size-3.5 text-emerald-600 shrink-0 mt-0.5" />
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Jam Operasional</p>
                                    <p class="text-xs font-semibold text-slate-700">Senin &ndash; Jumat &bull; 08:00 &ndash; 17:00 WIB</p>
                                </div>
                            </div>

                            <div class="flex items-start gap-2.5 p-2.5 rounded-xl bg-slate-50 border border-slate-100">
                                <Mail class="size-3.5 text-emerald-600 shrink-0 mt-0.5" />
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Email Helpdesk</p>
                                    <a href="mailto:it.support@zinus.com" class="text-xs font-bold text-[#003628] hover:underline">
                                        it.support@zinus.com
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    -->
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="py-6 border-t border-slate-200/60 bg-white text-center text-slate-400 text-[10px] font-bold uppercase tracking-widest">
            <p>&copy; {{ new Date().getFullYear() }} PT Zinus Global Indonesia &bull; IT Support & Knowledge Base</p>
        </footer>
    </div>
</template>
