<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    BookOpen,
    Eye,
    FileEdit,
    Save,
    Sparkles,
    Trash2,
} from 'lucide-vue-next';
import { ref } from 'vue';
import AppConfirmDialog from '@/components/AppConfirmDialog.vue';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps<{
    article: any;
    categories: string[];
}>();

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Knowledge Base', href: '/kb' },
    { title: props.article.title, href: `/kb/${props.article.slug}` },
    { title: 'Edit Artikel', href: `/kb/${props.article.slug}/edit` },
];

const activeTab = ref<'write' | 'preview'>('write');
const showDeleteConfirm = ref(false);

const defaultCategories = [
    'Hardware',
    'Network & WiFi',
    'Security',
    'Software & App',
    'Troubleshooting',
    'FAQ',
];

const availableCategories = Array.from(
    new Set([
        ...defaultCategories,
        ...props.categories,
        props.article.category,
    ]),
);

const isCustomCategory = ref(
    !availableCategories.includes(props.article.category),
);

const form = useForm({
    title: props.article.title || '',
    category: props.article.category || 'Hardware',
    custom_category: isCustomCategory.value ? props.article.category : '',
    content: props.article.content || '',
    is_published: Boolean(props.article.is_published),
});

const onCategoryChange = (e: Event) => {
    const val = (e.target as HTMLSelectElement).value;
    if (val === '__custom__') {
        isCustomCategory.value = true;
        form.category = '';
    } else {
        isCustomCategory.value = false;
        form.category = val;
    }
};

const insertFormat = (tag: string) => {
    const textarea = document.getElementById(
        'article-content',
    ) as HTMLTextAreaElement | null;
    if (!textarea) return;

    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selected = form.content.substring(start, end);
    let replacement = '';

    switch (tag) {
        case 'h2':
            replacement = `\n## ${selected || 'Sub Judul'}\n`;
            break;
        case 'h3':
            replacement = `\n### ${selected || 'Bagian'}\n`;
            break;
        case 'bold':
            replacement = `**${selected || 'teks tebal'}**`;
            break;
        case 'italic':
            replacement = `*${selected || 'teks miring'}*`;
            break;
        case 'code':
            replacement = selected.includes('\n')
                ? `\n\`\`\`\n${selected || 'kode atau konfigurasi'}\n\`\`\`\n`
                : `\`${selected || 'kode'}\``;
            break;
        case 'ul':
            replacement = `\n- ${selected || 'Poin pertama'}\n- Poin kedua\n`;
            break;
        case 'ol':
            replacement = `\n1. ${selected || 'Langkah pertama'}\n2. Langkah kedua\n`;
            break;
        case 'note':
            replacement = `\n> **Catatan:** ${selected || 'Tambahkan catatan atau peringatan penting di sini.'}\n`;
            break;
    }

    form.content =
        form.content.substring(0, start) +
        replacement +
        form.content.substring(end);
    setTimeout(() => {
        textarea.focus();
    }, 50);
};

const renderPreview = (content: string) => {
    if (!content) return '<p class="text-slate-400 italic">Belum ada konten untuk ditampilkan.</p>';

    let html = content
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');

    html = html.replace(/^### (.*$)/gim, '<h3 class="text-lg font-bold text-slate-800 mt-6 mb-2">$1</h3>');
    html = html.replace(/^## (.*$)/gim, '<h2 class="text-xl font-black text-slate-900 mt-8 mb-3 border-b border-slate-100 pb-2">$1</h2>');
    html = html.replace(/^# (.*$)/gim, '<h1 class="text-2xl font-black text-slate-900 mt-8 mb-4">$1</h1>');
    html = html.replace(/\*\*(.*?)\*\*/gim, '<strong>$1</strong>');
    html = html.replace(/\*(.*?)\*/gim, '<em>$1</em>');
    html = html.replace(/^\> (.*$)/gim, '<blockquote class="border-l-4 border-emerald-600 bg-emerald-50/60 p-4 rounded-r-xl my-4 text-slate-700">$1</blockquote>');
    html = html.replace(/```([\s\S]*?)```/gim, '<pre class="bg-slate-900 text-slate-100 p-4 rounded-xl my-4 overflow-x-auto text-xs font-mono"><code>$1</code></pre>');
    html = html.replace(/`([^`]+)`/gim, '<code class="bg-slate-100 text-emerald-700 px-1.5 py-0.5 rounded text-xs font-mono">$1</code>');
    html = html.replace(/^\- (.*$)/gim, '<li class="ml-5 list-disc text-slate-700 my-1">$1</li>');
    html = html.replace(/^\d+\. (.*$)/gim, '<li class="ml-5 list-decimal text-slate-700 my-1">$1</li>');
    html = html.replace(/\n\n/gim, '</p><p class="mb-4 text-slate-700 leading-relaxed">');
    html = `<p class="mb-4 text-slate-700 leading-relaxed">${html}</p>`;

    return html;
};

const submit = (asDraft = false) => {
    form.is_published = !asDraft;
    if (isCustomCategory.value && form.custom_category) {
        form.category = form.custom_category;
    }
    form.put(`/kb/${props.article.slug}`);
};

const deleteArticle = () => {
    router.delete(`/kb/${props.article.slug}`, {
        onSuccess: () => {
            showDeleteConfirm.value = false;
        },
    });
};
</script>

<template>
    <Head :title="`Edit: ${article.title} - Knowledge Base`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-[1100px] space-y-6 p-2 sm:p-4 md:p-6">
            <div class="space-y-6">
                <!-- Header -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <Link
                            :href="`/kb/${article.slug}`"
                            class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 hover:text-[#003628] mb-2 transition-colors"
                        >
                            <ArrowLeft class="size-4" /> Kembali ke Artikel
                        </Link>
                        <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight flex items-center gap-3">
                            <BookOpen class="size-7 text-[#003628]" />
                            Edit Artikel
                        </h1>
                        <p class="text-sm text-slate-500 mt-1">
                            Perbarui isi, judul, atau kategori artikel dokumentasi.
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-rose-200 bg-rose-50/50 px-4 py-2.5 text-xs font-bold text-rose-600 hover:bg-rose-100 transition-all"
                            @click="showDeleteConfirm = true"
                        >
                            <Trash2 class="size-4" /> Hapus Artikel
                        </button>
                        <button
                            type="button"
                            :disabled="form.processing"
                            class="inline-flex items-center gap-2 rounded-xl bg-[#003628] px-5 py-2.5 text-xs font-bold text-white shadow-lg shadow-[#003628]/20 hover:bg-[#00281e] transition-all disabled:opacity-50"
                            @click="submit(false)"
                        >
                            <Sparkles class="size-4 text-amber-400" /> Simpan Perubahan
                        </button>
                    </div>
                </div>

                <!-- Main Form Card -->
                <form class="space-y-6" @submit.prevent="submit(false)">
                    <div class="bg-white rounded-3xl border border-slate-100 shadow-xl shadow-slate-200/50 p-6 sm:p-8 space-y-6">
                        <!-- Judul -->
                        <div>
                            <label class="block text-xs font-black tracking-wider text-slate-600 uppercase mb-2">
                                Judul Artikel <span class="text-rose-500">*</span>
                            </label>
                            <input
                                v-model="form.title"
                                type="text"
                                placeholder="Judul artikel..."
                                class="w-full rounded-2xl border-slate-200 px-4 py-3.5 text-base font-semibold text-slate-900 placeholder:text-slate-400 focus:border-[#003628] focus:ring-[#003628]"
                                required
                            />
                            <p v-if="form.errors.title" class="mt-1 text-xs text-rose-500 font-medium">
                                {{ form.errors.title }}
                            </p>
                        </div>

                        <!-- Kategori -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-black tracking-wider text-slate-600 uppercase mb-2">
                                    Kategori <span class="text-rose-500">*</span>
                                </label>
                                <select
                                    class="w-full rounded-2xl border-slate-200 px-4 py-3 text-sm font-medium text-slate-800 focus:border-[#003628] focus:ring-[#003628]"
                                    @change="onCategoryChange"
                                >
                                    <option
                                        v-for="cat in availableCategories"
                                        :key="cat"
                                        :value="cat"
                                        :selected="form.category === cat && !isCustomCategory"
                                    >
                                        {{ cat }}
                                    </option>
                                    <option value="__custom__" :selected="isCustomCategory">+ Kategori Baru...</option>
                                </select>
                            </div>

                            <div v-if="isCustomCategory">
                                <label class="block text-xs font-black tracking-wider text-slate-600 uppercase mb-2">
                                    Nama Kategori Baru <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    v-model="form.custom_category"
                                    type="text"
                                    placeholder="Ketik kategori baru..."
                                    class="w-full rounded-2xl border-slate-200 px-4 py-3 text-sm font-medium text-slate-800 focus:border-[#003628] focus:ring-[#003628]"
                                    required
                                />
                            </div>
                        </div>

                        <!-- Editor Toolbar & Tabs -->
                        <div class="space-y-3">
                            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-3">
                                <!-- Format Buttons -->
                                <div class="flex flex-wrap items-center gap-1">
                                    <button
                                        type="button"
                                        title="Heading 2"
                                        class="rounded-lg px-2.5 py-1.5 text-xs font-bold text-slate-600 hover:bg-slate-100 transition-colors"
                                        @click="insertFormat('h2')"
                                    >
                                        H2
                                    </button>
                                    <button
                                        type="button"
                                        title="Heading 3"
                                        class="rounded-lg px-2.5 py-1.5 text-xs font-bold text-slate-600 hover:bg-slate-100 transition-colors"
                                        @click="insertFormat('h3')"
                                    >
                                        H3
                                    </button>
                                    <span class="h-4 w-px bg-slate-200 mx-1"></span>
                                    <button
                                        type="button"
                                        title="Tebal"
                                        class="rounded-lg px-2.5 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-100 transition-colors"
                                        @click="insertFormat('bold')"
                                    >
                                        B
                                    </button>
                                    <button
                                        type="button"
                                        title="Miring"
                                        class="rounded-lg px-2.5 py-1.5 text-xs italic text-slate-700 hover:bg-slate-100 transition-colors"
                                        @click="insertFormat('italic')"
                                    >
                                        I
                                    </button>
                                    <span class="h-4 w-px bg-slate-200 mx-1"></span>
                                    <button
                                        type="button"
                                        title="Daftar Poin"
                                        class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition-colors"
                                        @click="insertFormat('ul')"
                                    >
                                        • List
                                    </button>
                                    <button
                                        type="button"
                                        title="Daftar Nomor"
                                        class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition-colors"
                                        @click="insertFormat('ol')"
                                    >
                                        1. List
                                    </button>
                                    <button
                                        type="button"
                                        title="Blok Kode"
                                        class="rounded-lg px-2.5 py-1.5 text-xs font-mono font-semibold text-slate-700 hover:bg-slate-100 transition-colors"
                                        @click="insertFormat('code')"
                                    >
                                        &lt;/&gt;
                                    </button>
                                    <button
                                        type="button"
                                        title="Kotak Catatan"
                                        class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-50 transition-colors"
                                        @click="insertFormat('note')"
                                    >
                                        Note
                                    </button>
                                </div>

                                <!-- View Toggle -->
                                <div class="flex items-center rounded-xl bg-slate-100 p-0.5 text-xs font-bold">
                                    <button
                                        type="button"
                                        :class="[
                                            'flex items-center gap-1.5 rounded-lg px-3 py-1.5 transition-all',
                                            activeTab === 'write'
                                                ? 'bg-white text-slate-800 shadow-sm'
                                                : 'text-slate-500 hover:text-slate-700',
                                        ]"
                                        @click="activeTab = 'write'"
                                    >
                                        <FileEdit class="size-3.5" /> Editor
                                    </button>
                                    <button
                                        type="button"
                                        :class="[
                                            'flex items-center gap-1.5 rounded-lg px-3 py-1.5 transition-all',
                                            activeTab === 'preview'
                                                ? 'bg-white text-slate-800 shadow-sm'
                                                : 'text-slate-500 hover:text-slate-700',
                                        ]"
                                        @click="activeTab = 'preview'"
                                    >
                                        <Eye class="size-3.5" /> Pratinjau
                                    </button>
                                </div>
                            </div>

                            <!-- Editor Textarea -->
                            <div v-show="activeTab === 'write'">
                                <textarea
                                    id="article-content"
                                    v-model="form.content"
                                    rows="16"
                                    placeholder="Tuliskan isi artikel..."
                                    class="w-full font-mono text-sm leading-relaxed rounded-2xl border-slate-200 p-4 text-slate-800 placeholder:text-slate-400 focus:border-[#003628] focus:ring-[#003628]"
                                    required
                                ></textarea>
                                <p v-if="form.errors.content" class="mt-1 text-xs text-rose-500 font-medium">
                                    {{ form.errors.content }}
                                </p>
                            </div>

                            <!-- Live Preview Area -->
                            <div
                                v-show="activeTab === 'preview'"
                                class="min-h-[400px] rounded-2xl border border-slate-200 bg-white p-6 overflow-y-auto"
                            >
                                <div
                                    class="prose prose-slate max-w-none text-sm"
                                    v-html="renderPreview(form.content)"
                                ></div>
                            </div>
                        </div>

                        <!-- Status Publikasi -->
                        <div class="flex items-center justify-between border-t border-slate-100 pt-4">
                            <div>
                                <span class="text-sm font-bold text-slate-800">Status Publikasi</span>
                                <p class="text-xs text-slate-400">Jika dinonaktifkan, artikel hanya dapat dilihat oleh pembuat atau admin.</p>
                            </div>
                            <label class="relative inline-flex cursor-pointer items-center">
                                <input
                                    v-model="form.is_published"
                                    type="checkbox"
                                    class="peer sr-only"
                                />
                                <div class="peer h-6 w-11 rounded-full bg-slate-200 after:absolute after:top-[2px] after:left-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-[#003628] peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none"></div>
                            </label>
                        </div>
                    </div>

                    <!-- Bottom Bar -->
                    <div class="flex items-center justify-between pt-2">
                        <Link
                            :href="`/kb/${article.slug}`"
                            class="rounded-xl px-5 py-2.5 text-xs font-bold text-slate-500 hover:bg-slate-100 transition-colors"
                        >
                            Batal
                        </Link>
                        <div class="flex items-center gap-3">
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="rounded-xl bg-[#003628] px-6 py-2.5 text-xs font-bold text-white shadow-lg shadow-[#003628]/20 hover:bg-[#00281e] transition-all disabled:opacity-50"
                            >
                                Simpan Perubahan
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Dialog Konfirmasi Hapus -->
        <AppConfirmDialog
            :open="showDeleteConfirm"
            title="Hapus Artikel Knowledge Base"
            :description="`Apakah Anda yakin ingin menghapus artikel '${article.title}'? Tindakan ini tidak dapat dibatalkan.`"
            confirm-text="Ya, Hapus Artikel"
            cancel-text="Batal"
            variant="destructive"
            @confirm="deleteArticle"
            @cancel="showDeleteConfirm = false"
        />
    </AppLayout>
</template>
