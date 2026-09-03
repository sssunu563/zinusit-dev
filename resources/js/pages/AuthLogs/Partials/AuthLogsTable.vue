<script setup lang="ts">
/* eslint-disable vue/no-mutating-props */
import { Link } from '@inertiajs/vue3';
import { onClickOutside } from '@vueuse/core';
import {
    LucideDownload as Download,
    LucideSearch as Search,
    LucideSlidersHorizontal as SlidersHorizontal,
    LucideShieldCheck as ShieldCheck,
    LucideUser as UserIcon,
    LucideRefreshCw as RefreshCw,
    LucideEye as EyeIcon,
    LucideKeyRound as KeyIcon,
    LucideLogOut as LogOut,
    LucideCheckCircle2 as CheckCircle,
    LucideXCircle as XCircle,
    LucideGlobe as Globe,
    LucideLaptop as Laptop,
} from 'lucide-vue-next';
import { ref } from 'vue';
import type { AuthLogItem } from '@/pages/AuthLogs/Partials/AuthLogDetailSheet.vue';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

const props = defineProps<{
    logs: {
        data: AuthLogItem[];
        links: PaginationLink[];
    };
    filterForm: {
        search: string;
        event: string;
        status: string;
        from_date: string;
        to_date: string;
    };
    events: string[];
    statuses: string[];
    summaryText: string;
    exportUrl: string;
    applyDatePreset: (preset: 'today' | 'last7Days' | 'thisMonth') => void;
    clearDateFilters: () => void;
    isPresetActive: (preset: 'today' | 'last7Days' | 'thisMonth') => boolean;
    activeFilterCount: number;
}>();

const emit = defineEmits<{
    (e: 'open-detail', log: AuthLogItem): void;
}>();

const showFilters = ref(false);
const filterPanelRef = ref<HTMLElement | null>(null);

onClickOutside(filterPanelRef, () => {
    showFilters.value = false;
});

const getEventIcon = (event?: string) => {
    switch (event) {
        case 'login':
            return KeyIcon;
        case 'logout':
            return LogOut;
        case 'user_sync':
            return RefreshCw;
        default:
            return ShieldCheck;
    }
};

const formatEventLabel = (event: string) => {
    switch (event) {
        case 'login':
            return 'Masuk (Login)';
        case 'logout':
            return 'Keluar (Logout)';
        case 'user_sync':
            return 'Sinkronisasi Akun';
        default:
            return event.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
    }
};

const formatStatusLabel = (status: string) => {
    switch (status) {
        case 'success':
            return 'Berhasil';
        case 'failed':
            return 'Gagal';
        case 'matched':
            return 'Tervalidasi';
        case 'updated':
            return 'Diperbarui';
        case 'created':
            return 'Dibuat';
        default:
            return status.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
    }
};

const parseBrowser = (userAgent?: string | null) => {
    if (!userAgent) return 'Perangkat Lain';
    if (userAgent.includes('Edg/')) return 'Edge';
    if (userAgent.includes('Chrome/')) return 'Chrome';
    if (userAgent.includes('Firefox/')) return 'Firefox';
    if (userAgent.includes('Safari/') && !userAgent.includes('Chrome/')) return 'Safari';
    if (userAgent.includes('PHPUnit')) return 'PHPUnit';
    return 'Browser';
};

const isSuccess = (status?: string) => {
    return ['success', 'matched', 'created', 'updated'].includes(status || '');
};
</script>

<template>
    <div class="space-y-4">
        <!-- TABLE CARD CONTAINER -->
        <div class="bg-white rounded-[32px] border border-slate-200/60 shadow-xl shadow-slate-200/50 p-6 lg:p-8">
            <!-- Toolbar Section -->
            <div class="mb-8 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <div class="relative w-full lg:max-w-md">
                    <Search class="absolute left-4 top-1/2 -translate-y-1/2 size-4 text-slate-400" />
                    <input
                        v-model="filterForm.search"
                        type="text"
                        placeholder="Cari user, email, username, IP..."
                        class="w-full h-10 pl-11 pr-4 rounded-xl border border-slate-200 bg-slate-50/30 text-xs text-slate-900 placeholder:text-slate-400 focus:border-[#003628]/50 focus:bg-white transition-all outline-none shadow-xs"
                    />
                </div>

                <div class="flex items-center gap-2">
                    <a
                        :href="exportUrl"
                        class="h-10 px-3 rounded-xl border border-slate-200 bg-white flex items-center justify-center text-slate-500 hover:text-slate-900 hover:bg-slate-50 transition-all active:scale-95 shadow-xs"
                        title="Ekspor CSV"
                    >
                        <Download class="size-4" />
                    </a>

                    <!-- Filter Dropdown Button & Popover -->
                    <div ref="filterPanelRef" class="relative">
                        <button
                            type="button"
                            class="h-10 px-4 rounded-xl border border-slate-200 bg-white flex items-center gap-2.5 text-xs font-bold text-slate-600 hover:text-slate-900 hover:bg-slate-50 transition-all active:scale-95 shadow-xs cursor-pointer"
                            @click="showFilters = !showFilters"
                        >
                            <SlidersHorizontal class="size-4 text-slate-400" />
                            <span>Filter</span>
                            <span
                                v-if="activeFilterCount"
                                class="flex h-4 w-4 items-center justify-center rounded-full bg-[#003628] text-[9px] font-black text-white"
                            >
                                {{ activeFilterCount }}
                            </span>
                        </button>

                        <Transition
                            enter-active-class="transition duration-300 ease-out"
                            enter-from-class="opacity-0 translate-y-4 scale-95"
                            enter-to-class="opacity-100 translate-y-0 scale-100"
                            leave-active-class="transition duration-200 ease-in"
                            leave-from-class="opacity-100 translate-y-0 scale-100"
                            leave-to-class="opacity-0 translate-y-4 scale-95"
                        >
                            <div
                                v-if="showFilters"
                                class="absolute top-full right-0 z-50 mt-4 w-88 rounded-[32px] border border-slate-200 bg-white p-6 shadow-2xl backdrop-blur-xl overflow-hidden"
                            >
                                <div class="flex items-center justify-between mb-6">
                                    <h3 class="text-[10px] font-black uppercase tracking-widest text-slate-400">Filter Log Autentikasi</h3>
                                    <button
                                        @click="
                                            filterForm.search = '';
                                            filterForm.event = '';
                                            filterForm.status = '';
                                            clearDateFilters();
                                            showFilters = false;
                                        "
                                        class="text-[10px] font-black uppercase tracking-widest text-[#003628] hover:opacity-70 transition-colors flex items-center gap-1.5 cursor-pointer"
                                    >
                                        <RefreshCw class="size-3" /> Reset
                                    </button>
                                </div>

                                <div class="space-y-4">
                                    <!-- Event Filter -->
                                    <div class="space-y-1.5">
                                        <label class="text-[9px] font-black uppercase tracking-widest text-slate-500 ml-1">Jenis Aktivitas</label>
                                        <select
                                            v-model="filterForm.event"
                                            class="w-full h-9 px-3 rounded-xl border border-slate-200 bg-slate-50 text-[11px] font-medium text-slate-700 outline-none focus:border-[#003628]/50 focus:bg-white"
                                        >
                                            <option value="">Semua Aktivitas</option>
                                            <option v-for="ev in events" :key="ev" :value="ev">{{ formatEventLabel(ev) }}</option>
                                        </select>
                                    </div>

                                    <!-- Status Filter -->
                                    <div class="space-y-1.5">
                                        <label class="text-[9px] font-black uppercase tracking-widest text-slate-500 ml-1">Status / Hasil</label>
                                        <select
                                            v-model="filterForm.status"
                                            class="w-full h-9 px-3 rounded-xl border border-slate-200 bg-slate-50 text-[11px] font-medium text-slate-700 outline-none focus:border-[#003628]/50 focus:bg-white"
                                        >
                                            <option value="">Semua Status</option>
                                            <option v-for="st in statuses" :key="st" :value="st">{{ formatStatusLabel(st) }}</option>
                                        </select>
                                    </div>

                                    <!-- Date Presets -->
                                    <div class="pt-3 border-t border-slate-100 space-y-2">
                                        <label class="text-[9px] font-black uppercase tracking-widest text-slate-500 ml-1">Preset Rentang Tanggal</label>
                                        <div class="grid grid-cols-3 gap-2">
                                            <button
                                                type="button"
                                                @click="applyDatePreset('today')"
                                                class="h-7 px-2 rounded-lg text-[10px] font-bold border transition-all cursor-pointer"
                                                :class="isPresetActive('today') ? 'bg-[#003628] text-white border-[#003628]' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100'"
                                            >
                                                Hari Ini
                                            </button>
                                            <button
                                                type="button"
                                                @click="applyDatePreset('last7Days')"
                                                class="h-7 px-2 rounded-lg text-[10px] font-bold border transition-all cursor-pointer"
                                                :class="isPresetActive('last7Days') ? 'bg-[#003628] text-white border-[#003628]' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100'"
                                            >
                                                7 Hari
                                            </button>
                                            <button
                                                type="button"
                                                @click="applyDatePreset('thisMonth')"
                                                class="h-7 px-2 rounded-lg text-[10px] font-bold border transition-all cursor-pointer"
                                                :class="isPresetActive('thisMonth') ? 'bg-[#003628] text-white border-[#003628]' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100'"
                                            >
                                                Bulan Ini
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Date Inputs -->
                                    <div class="grid grid-cols-2 gap-3 pt-2 border-t border-slate-100">
                                        <div class="space-y-1">
                                            <label class="text-[9px] font-black uppercase tracking-widest text-slate-400">Dari</label>
                                            <input
                                                v-model="filterForm.from_date"
                                                type="date"
                                                class="w-full h-9 px-2 rounded-xl border border-slate-200 bg-slate-50 text-[10px] font-medium text-slate-700 outline-none focus:border-[#003628]/50 focus:bg-white"
                                            />
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-[9px] font-black uppercase tracking-widest text-slate-400">Hingga</label>
                                            <input
                                                v-model="filterForm.to_date"
                                                type="date"
                                                class="w-full h-9 px-2 rounded-xl border border-slate-200 bg-slate-50 text-[10px] font-medium text-slate-700 outline-none focus:border-[#003628]/50 focus:bg-white"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </Transition>
                    </div>
                </div>
            </div>

            <!-- Desktop Table -->
            <div class="hidden md:block overflow-hidden rounded-2xl border border-slate-100">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/50">
                            <th class="px-6 py-3.5 text-[10px] font-black uppercase tracking-widest text-slate-400">Waktu</th>
                            <th class="px-6 py-3.5 text-[10px] font-black uppercase tracking-widest text-slate-400">Pengguna / Akun</th>
                            <th class="px-6 py-3.5 text-[10px] font-black uppercase tracking-widest text-slate-400">Kejadian (Event)</th>
                            <th class="px-6 py-3.5 text-[10px] font-black uppercase tracking-widest text-slate-400">Status</th>
                            <th class="px-6 py-3.5 text-[10px] font-black uppercase tracking-widest text-slate-400 font-mono">IP &amp; Browser</th>
                            <th class="px-6 py-3.5 text-[10px] font-black uppercase tracking-widest text-slate-400 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr
                            v-for="log in logs.data"
                            :key="log.id"
                            class="group hover:bg-slate-50/50 transition-colors"
                        >
                            <!-- Waktu -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex flex-col">
                                    <span class="text-[12px] font-bold text-slate-800 tabular-nums">
                                        {{ (log.created_at || '').split(' ')[0] || log.created_at || '—' }}
                                    </span>
                                    <span class="text-[10px] text-slate-400 font-mono font-medium mt-0.5">
                                        {{ (log.created_at || '').split(' ')[1] || '' }}
                                    </span>
                                </div>
                            </td>

                            <!-- Pengguna / Akun -->
                            <td class="px-6 py-4">
                                <div v-if="log.user" class="flex items-center gap-3">
                                    <div class="size-8 rounded-lg bg-[#003628]/10 text-[#003628] font-black text-xs flex items-center justify-center shrink-0 border border-[#003628]/20 overflow-hidden">
                                        <img v-if="log.user.avatar" :src="log.user.avatar" :alt="log.user.name" class="size-full object-cover" />
                                        <span v-else>{{ log.user.name.charAt(0).toUpperCase() }}</span>
                                    </div>
                                    <div class="flex flex-col min-w-0">
                                        <span class="text-xs font-black text-slate-900 truncate max-w-[200px]">{{ log.user.name }}</span>
                                        <span class="text-[10px] text-slate-400 font-medium truncate max-w-[200px]">{{ log.user.email || log.user.username }}</span>
                                    </div>
                                </div>
                                <div v-else class="flex items-center gap-2">
                                    <div class="size-8 rounded-lg bg-slate-100 text-slate-400 flex items-center justify-center shrink-0 border border-slate-200">
                                        <UserIcon class="size-4" />
                                    </div>
                                    <div class="flex flex-col min-w-0">
                                        <span class="text-xs font-bold text-slate-700 truncate font-mono">{{ log.identifier || 'Pengguna Anonim' }}</span>
                                        <span class="text-[10px] text-slate-400 italic">User Belum Terdaftar</span>
                                    </div>
                                </div>
                            </td>

                            <!-- Kejadian (Event) -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-800">
                                    <component :is="getEventIcon(log.event)" class="size-3.5 text-[#003628]" />
                                    {{ log.event_label || formatEventLabel(log.event) }}
                                </span>
                            </td>

                            <!-- Status -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-widest border"
                                    :class="isSuccess(log.status)
                                        ? 'bg-[#003628]/5 text-[#003628] border-[#003628]/10'
                                        : 'bg-red-50 text-red-600 border-red-100'"
                                >
                                    <component :is="isSuccess(log.status) ? CheckCircle : XCircle" class="size-3" />
                                    {{ log.status_label || formatStatusLabel(log.status) }}
                                </span>
                            </td>

                            <!-- IP & Browser -->
                            <td class="px-6 py-4 font-mono whitespace-nowrap">
                                <div class="space-y-0.5">
                                    <span class="inline-block text-[11px] font-bold text-slate-700 px-2 py-0.5 rounded-md bg-slate-50 border border-slate-200/80">
                                        {{ log.ip_address || 'Internal' }}
                                    </span>
                                    <p class="text-[10px] text-slate-400 font-sans font-medium flex items-center gap-1">
                                        <Laptop class="size-2.5" />
                                        {{ parseBrowser(log.user_agent) }}
                                    </p>
                                </div>
                            </td>

                            <!-- Aksi -->
                            <td class="px-6 py-4 text-center whitespace-nowrap">
                                <button
                                    type="button"
                                    @click="emit('open-detail', log)"
                                    class="h-8 px-3 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 hover:text-slate-900 text-xs font-bold transition-all active:scale-95 shadow-xs inline-flex items-center gap-1.5 cursor-pointer"
                                    title="Lihat Detail Log"
                                >
                                    <EyeIcon class="size-3.5" />
                                    <span>Detail</span>
                                </button>
                            </td>
                        </tr>

                        <!-- Empty State -->
                        <tr v-if="logs.data.length === 0">
                            <td colspan="6" class="px-6 py-20 text-center text-slate-400 italic text-sm">
                                Tidak ada catatan audit autentikasi yang sesuai dengan filter Anda.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Mobile View List -->
            <div class="md:hidden space-y-3">
                <div
                    v-for="log in logs.data"
                    :key="log.id"
                    class="p-4 rounded-2xl border border-slate-200/80 bg-slate-50/50 space-y-3"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <component :is="getEventIcon(log.event)" class="size-4 text-[#003628]" />
                            <span class="text-xs font-black text-slate-900">{{ log.event_label || formatEventLabel(log.event) }}</span>
                        </div>
                        <span
                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-widest border"
                            :class="isSuccess(log.status)
                                ? 'bg-[#003628]/5 text-[#003628] border-[#003628]/10'
                                : 'bg-red-50 text-red-600 border-red-100'"
                        >
                            {{ log.status_label || formatStatusLabel(log.status) }}
                        </span>
                    </div>

                    <div class="space-y-0.5">
                        <p class="text-xs font-bold text-slate-800">
                            {{ log.user?.name || log.identifier || 'Pengguna Anonim' }}
                        </p>
                        <p class="text-[10px] font-mono text-slate-400">
                            IP: {{ log.ip_address || 'Internal' }} · {{ parseBrowser(log.user_agent) }}
                        </p>
                    </div>

                    <div class="flex items-center justify-between text-[10px] text-slate-400 font-mono pt-2 border-t border-slate-200/60">
                        <span>{{ log.created_at }}</span>
                        <button
                            type="button"
                            @click="emit('open-detail', log)"
                            class="font-sans font-bold text-[#003628] hover:underline cursor-pointer"
                        >
                            Lihat Detail →
                        </button>
                    </div>
                </div>

                <div v-if="logs.data.length === 0" class="p-8 text-center text-slate-400 italic text-sm bg-slate-50/50 rounded-2xl border border-slate-200">
                    Tidak ada catatan audit autentikasi yang sesuai dengan filter Anda.
                </div>
            </div>

            <!-- Table Footer -->
            <div class="px-2 py-4 flex flex-col sm:flex-row items-center justify-between gap-4 border-t border-slate-100 mt-4">
                <span class="text-[11px] font-black uppercase tracking-widest text-slate-400">{{ summaryText }}</span>
                <nav v-if="logs.links.length > 3" class="flex items-center gap-1.5">
                    <Link
                        v-for="(link, j) in logs.links"
                        :key="j"
                        :href="link.url || '#'"
                        class="h-8 min-w-[32px] px-2 rounded-lg flex items-center justify-center text-[11px] font-bold transition-all border border-slate-100"
                        :class="link.active ? 'bg-[#003628] text-white shadow-lg shadow-emerald-900/20 border-[#003628]' : 'bg-white text-slate-500 hover:bg-slate-50'"
                        v-html="link.label"
                    />
                </nav>
            </div>
        </div>
    </div>
</template>
