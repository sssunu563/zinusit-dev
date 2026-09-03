<script setup lang="ts">
import { ref, onMounted, watch, computed } from 'vue';
import {
    Search,
    Loader2,
    Table2,
    MessageSquare,
    User,
    MapPin,
    Tag,
} from 'lucide-vue-next';

const props = defineProps<{
    filterFrom: string;
    filterTo: string;
    applyTrigger: number;
}>();

const loading = ref(true);
const tickets = ref<any[]>([]);
const search = ref('');

async function loadData() {
    loading.value = true;
    try {
        const res = await fetch(
            `/support-operation/data?from=${props.filterFrom}&to=${props.filterTo}&search=${search.value}`,
        );
        if (res.ok) tickets.value = await res.json();
    } finally {
        loading.value = false;
    }
}

onMounted(() => loadData());
watch(
    () => props.applyTrigger,
    () => loadData(),
);

const filteredTickets = computed(() => {
    if (!search.value) return tickets.value;
    const q = search.value.toLowerCase();
    return tickets.value.filter(
        (t) =>
            (t.issue_description ?? '').toLowerCase().includes(q) ||
            (t.requester ?? '').toLowerCase().includes(q) ||
            (t.location ?? '').toLowerCase().includes(q) ||
            t.id.toString().includes(q),
    );
});

function getStatusBadge(status: string) {
    const s = status.toLowerCase();
    if (s === 'closed' || s === 'resolved')
        return 'bg-emerald-50 text-emerald-600 border-emerald-100';
    if (s === 'open' || s === 'new')
        return 'bg-blue-50 text-blue-600 border-blue-100';
    if (s === 'pending') return 'bg-amber-50 text-amber-600 border-amber-100';
    return 'bg-slate-50 text-slate-600 border-slate-100';
}

function formatDate(dateStr: string) {
    const d = new Date(dateStr);
    return d.toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <div class="w-full min-w-0 pb-10">
        <div
            class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm"
        >
            <!-- Header -->
            <div
                class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4"
            >
                <div class="flex min-w-0 items-center gap-3">
                    <div
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#003628]/5"
                    >
                        <Table2 class="size-4 text-[#003628]" />
                    </div>
                    <div class="min-w-0">
                        <p
                            class="text-sm font-black tracking-tight text-slate-800"
                        >
                            Support Ticket Ledger
                        </p>
                        <p
                            class="mt-0.5 text-[9px] font-bold tracking-widest text-slate-400 uppercase"
                        >
                            {{ filteredTickets.length }} records
                        </p>
                    </div>
                </div>
                <div class="relative shrink-0">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-2.5 size-3 -translate-y-1/2 text-slate-400"
                    />
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Cari ticket, requester..."
                        class="h-8 w-56 rounded-lg border border-slate-200 bg-white pr-3 pl-8 text-[10px] font-bold text-slate-700 focus:ring-2 focus:ring-[#003628]/20 focus:outline-none"
                    />
                </div>
            </div>

            <!-- Loading -->
            <div v-if="loading" class="flex items-center justify-center py-32">
                <Loader2 class="size-8 animate-spin text-[#003628]" />
            </div>

            <!-- Table -->
            <div v-else class="w-full overflow-x-auto">
                <table class="w-full border-collapse text-left">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50">
                            <th
                                class="px-5 py-3 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                            >
                                Ticket
                            </th>
                            <th
                                class="px-4 py-3 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                            >
                                Requester
                            </th>
                            <th
                                class="px-4 py-3 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                            >
                                Kategori & Lokasi
                            </th>
                            <th
                                class="px-4 py-3 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                            >
                                Status
                            </th>
                            <th
                                class="px-4 py-3 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                            >
                                Solution
                            </th>
                            <th
                                class="px-4 py-3 text-right text-[9px] font-black tracking-widest text-slate-400 uppercase"
                            >
                                Selesai
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr
                            v-for="t in filteredTickets"
                            :key="t.id"
                            class="group transition-colors hover:bg-slate-50/50"
                        >
                            <!-- Ticket -->
                            <td class="px-5 py-3 align-top">
                                <div class="flex max-w-sm flex-col gap-1">
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="rounded-lg bg-[#003628]/5 px-2 py-0.5 text-[9px] font-black text-[#003628] tabular-nums"
                                            >#{{ t.id }}</span
                                        >
                                        <span
                                            class="text-[9px] font-bold text-slate-400 tabular-nums"
                                            >{{
                                                formatDate(t.created_at)
                                            }}</span
                                        >
                                    </div>
                                    <p
                                        class="text-[11px] leading-tight font-bold text-slate-800"
                                    >
                                        {{ t.issue_description }}
                                    </p>
                                </div>
                            </td>
                            <!-- Requester -->
                            <td class="px-4 py-3 align-top">
                                <div
                                    class="flex items-center gap-1.5 text-slate-700"
                                >
                                    <User
                                        class="size-3 shrink-0 text-slate-400"
                                    />
                                    <span class="text-[11px] font-bold">{{
                                        t.requester
                                    }}</span>
                                </div>
                                <p
                                    class="mt-0.5 ml-4 text-[9px] font-bold text-slate-400 uppercase"
                                >
                                    {{ t.department }}
                                </p>
                            </td>
                            <!-- Category & Location -->
                            <td class="px-4 py-3 align-top">
                                <div
                                    class="mb-1 flex items-center gap-1.5 text-slate-600"
                                >
                                    <Tag
                                        class="size-3 shrink-0 text-slate-400"
                                    />
                                    <span
                                        class="text-[10px] font-black tracking-tight uppercase"
                                        >{{ t.category }}</span
                                    >
                                </div>
                                <div
                                    class="flex items-center gap-1.5 text-slate-500"
                                >
                                    <MapPin
                                        class="size-3 shrink-0 text-slate-400"
                                    />
                                    <span class="text-[10px] font-bold">{{
                                        t.location
                                    }}</span>
                                </div>
                            </td>
                            <!-- Status -->
                            <td class="px-4 py-3 align-top">
                                <span
                                    class="inline-flex items-center rounded-lg border px-2 py-1 text-[9px] font-black tracking-widest uppercase"
                                    :class="getStatusBadge(t.status)"
                                >
                                    {{ t.status }}
                                </span>
                            </td>
                            <!-- Solution -->
                            <td class="px-4 py-3 align-top">
                                <p
                                    class="max-w-[200px] truncate text-[10px] text-slate-500"
                                    :title="t.action_taken"
                                >
                                    {{ t.action_taken ?? '—' }}
                                </p>
                            </td>
                            <!-- Closed -->
                            <td class="px-4 py-3 text-right align-top">
                                <p
                                    class="text-[10px] font-black text-slate-700 tabular-nums"
                                >
                                    {{
                                        t.date_closed
                                            ? t.date_closed.slice(0, 10)
                                            : '—'
                                    }}
                                </p>
                                <p
                                    v-if="t.technician"
                                    class="mt-0.5 text-[9px] text-slate-400"
                                >
                                    {{ t.technician }}
                                </p>
                            </td>
                        </tr>
                        <tr v-if="!filteredTickets.length">
                            <td colspan="5" class="px-5 py-20 text-center">
                                <MessageSquare
                                    class="mx-auto mb-3 size-10 text-slate-200"
                                />
                                <p
                                    class="text-[11px] font-black tracking-widest text-slate-300 uppercase"
                                >
                                    Tidak ada ticket ditemukan
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
