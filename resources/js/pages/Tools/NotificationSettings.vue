<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Bell, CheckCircle2, Send, Webhook } from 'lucide-vue-next';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type Props = {
    settings: {
        enabled: boolean;
        provider: 'custom' | 'teams' | 'slack' | 'discord';
        hasWebhookUrl: boolean;
    };
    templates: Array<{
        event: string;
        enabled: boolean;
        title: string;
        message: string;
    }>;
    availableVariables: string[];
    status?: string;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Alat', href: '#' },
    { title: 'Notification Settings', href: '/notification-settings' },
];

const form = useForm({
    enabled: props.settings.enabled,
    provider: props.settings.provider,
    webhook_url: '',
    templates: props.templates.map((template) => ({ ...template })),
});

const selectedEvent = ref(props.templates[0]?.event ?? 'approval_requested');

const eventLabel = (event: string) =>
    event
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());

const testLocal = () => {
    form.post('/notification-settings/test-local', { preserveScroll: true });
};

const testWebhook = () => {
    router.post(
        '/notification-settings/test-webhook',
        { event: selectedEvent.value },
        { preserveScroll: true },
    );
};
</script>

<template>
    <Head title="Notification Settings" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="app-page-shell">
            <div
                class="rounded-[28px] border border-slate-200/70 bg-white p-6 shadow-xl shadow-slate-200/40 lg:p-8"
            >
                <div
                    class="mb-8 flex items-start justify-between border-b border-slate-100 pb-6"
                >
                    <div>
                        <p
                            class="mb-1 text-[10px] font-black tracking-[0.18em] text-[#003628] uppercase"
                        >
                            Tools
                        </p>
                        <h1
                            class="text-2xl font-black tracking-tight text-slate-900"
                        >
                            Notification Settings
                        </h1>
                        <p class="mt-1 text-sm text-slate-400">
                            Atur webhook dan lakukan testing notifikasi
                            personal.
                        </p>
                    </div>
                    <Webhook class="size-6 text-[#003628]" />
                </div>

                <div
                    v-if="status"
                    class="mb-6 flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs font-bold text-emerald-700"
                >
                    <CheckCircle2 class="size-4" /> {{ status }}
                </div>

                <form
                    class="space-y-6"
                    @submit.prevent="
                        form.put('/notification-settings', {
                            preserveScroll: true,
                        })
                    "
                >
                    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                        <label
                            class="space-y-2 text-xs font-bold text-slate-600"
                        >
                            Provider
                            <select
                                v-model="form.provider"
                                class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 outline-none focus:border-[#003628]"
                            >
                                <option value="custom">Custom Webhook</option>
                                <option value="teams">Microsoft Teams</option>
                                <option value="slack">Slack</option>
                                <option value="discord">Discord</option>
                            </select>
                        </label>
                        <label
                            class="flex items-center gap-3 self-end rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs font-bold text-slate-700"
                        >
                            <input
                                v-model="form.enabled"
                                type="checkbox"
                                class="size-4 accent-[#003628]"
                            />
                            Aktifkan webhook notification
                        </label>
                    </div>

                    <label
                        class="block space-y-2 text-xs font-bold text-slate-600"
                    >
                        Webhook URL
                        <input
                            v-model="form.webhook_url"
                            type="url"
                            :placeholder="
                                props.settings.hasWebhookUrl
                                    ? 'URL tersimpan. Isi untuk mengganti.'
                                    : 'https://...'
                            "
                            class="h-11 w-full rounded-xl border border-slate-200 bg-white px-4 outline-none focus:border-[#003628]"
                        />
                        <span
                            v-if="props.settings.hasWebhookUrl"
                            class="block text-[11px] font-medium text-slate-400"
                            >URL tersimpan di server dan disembunyikan untuk
                            keamanan. Isi field hanya jika ingin menggantinya.
                        </span>
                        >
                        <span
                            v-if="form.errors.webhook_url"
                            class="block text-[11px] font-bold text-rose-600"
                            >{{ form.errors.webhook_url }}</span
                        >
                    </label>

                    <div class="space-y-4 border-t border-slate-100 pt-6">
                        <div class="flex items-end justify-between gap-4">
                            <div>
                                <h2 class="text-sm font-black text-slate-900">
                                    Template per event
                                </h2>
                                <p class="mt-1 text-xs text-slate-400">
                                    Atur judul dan pesan webhook untuk setiap
                                    event.
                                </p>
                            </div>
                            <div class="hidden flex-wrap gap-1.5 md:flex">
                                <span
                                    v-for="variable in availableVariables"
                                    :key="variable"
                                    class="rounded-md bg-slate-100 px-2 py-1 font-mono text-[10px] text-slate-500"
                                    >{{ variable }}</span
                                >
                            </div>
                        </div>

                        <div
                            class="overflow-x-auto rounded-xl border border-slate-200"
                        >
                            <table
                                class="w-full min-w-[760px] border-collapse text-left"
                            >
                                <thead
                                    class="border-b border-slate-100 bg-slate-50"
                                >
                                    <tr>
                                        <th
                                            class="w-48 px-4 py-3 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                        >
                                            Event
                                        </th>
                                        <th
                                            class="w-16 px-4 py-3 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                        >
                                            Aktif
                                        </th>
                                        <th
                                            class="px-4 py-3 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                        >
                                            Title
                                        </th>
                                        <th
                                            class="px-4 py-3 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                        >
                                            Message
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr
                                        v-for="template in form.templates"
                                        :key="template.event"
                                    >
                                        <td
                                            class="px-4 py-3 font-mono text-xs font-bold text-slate-600"
                                        >
                                            {{ eventLabel(template.event) }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <input
                                                v-model="template.enabled"
                                                type="checkbox"
                                                class="size-4 accent-[#003628]"
                                            />
                                        </td>
                                        <td class="px-4 py-3">
                                            <input
                                                v-model="template.title"
                                                class="h-9 w-full rounded-lg border border-slate-200 px-3 text-xs outline-none focus:border-[#003628]"
                                            />
                                        </td>
                                        <td class="px-4 py-3">
                                            <textarea
                                                v-model="template.message"
                                                rows="2"
                                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-xs outline-none focus:border-[#003628]"
                                            />
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div
                        class="flex flex-wrap justify-end gap-3 border-t border-slate-100 pt-5"
                    >
                        <button
                            type="button"
                            :disabled="form.processing"
                            class="flex h-10 items-center gap-2 rounded-xl border border-slate-200 px-4 text-xs font-bold text-slate-600 hover:bg-slate-50 disabled:opacity-50"
                            @click="testLocal"
                        >
                            <Bell class="size-4" /> Test Local Notification
                        </button>
                        <button
                            type="button"
                            :disabled="form.processing"
                            class="flex h-10 items-center gap-2 rounded-xl border border-slate-200 px-4 text-xs font-bold text-slate-600 hover:bg-slate-50 disabled:opacity-50"
                            @click="testWebhook"
                        >
                            <select
                                v-model="selectedEvent"
                                class="h-7 rounded-lg border-0 bg-transparent text-[10px] font-bold outline-none"
                            >
                                <option
                                    v-for="template in form.templates"
                                    :key="template.event"
                                    :value="template.event"
                                >
                                    {{ eventLabel(template.event) }}
                                </option>
                            </select>
                            <Send class="size-4" /> Test Webhook
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="flex h-10 items-center gap-2 rounded-xl bg-[#003628] px-5 text-xs font-bold text-white disabled:opacity-50"
                        >
                            Simpan Pengaturan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
