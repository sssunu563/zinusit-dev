<script setup lang="ts">
/* eslint-disable vue/no-mutating-props */
import { Link } from '@inertiajs/vue3';
import { onClickOutside } from '@vueuse/core';
import {
    LucideDownload as Download,
    LucideSearch as Search,
    LucideSlidersHorizontal as SlidersHorizontal,
    LucideFileEdit as EditIcon, 
    LucideSettings as SettingsIcon, 
    LucidePackage as PackageIcon, 
    LucideUser as UserIcon, 
    LucideHardDrive as AssetIcon,
    LucideServer as ServerIcon,
    LucideWifi as NetworkIcon,
    LucideCamera as CctvIcon,
    LucideRefreshCw as RefreshCw,
    LucideEye as EyeIcon,
    LucideExternalLink as ExternalLink,
} from 'lucide-vue-next';
import { ref } from 'vue';
import type { ActionLogItem } from '@/pages/Logs/Partials/LogDetailSheet.vue';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface CategoryOption {
    key: string;
    label: string;
}

interface FilterOptions {
    admins: string[];
    actions: string[];
    items: string[];
    categories: CategoryOption[];
}

const props = defineProps<{
    logs: {
        data: ActionLogItem[];
        links: PaginationLink[];
    };
    filterForm: {
        search: string;
        filter_category: string;
        filter_admin: string;
        filter_action: string;
        filter_item: string;
        from_date: string;
        to_date: string;
    };
    filterOptions: FilterOptions;
    summaryText: string;
    exportUrl: string;
    applyDatePreset: (preset: 'today' | 'last7Days' | 'thisMonth') => void;
    clearDateFilters: () => void;
    isPresetActive: (preset: 'today' | 'last7Days' | 'thisMonth') => boolean;
    activeFilterCount: number;
}>();

const emit = defineEmits<{
    (e: 'open-asset', id: number, type: string): void;
    (e: 'open-detail', log: ActionLogItem): void;
}>();

const showFilters = ref(false);
const filterPanelRef = ref<HTMLElement | null>(null);

onClickOutside(filterPanelRef, () => {
    showFilters.value = false;
});

const getModelIcon = (type: string) => {
    const t = (type || '').toLowerCase();
    if (t.includes('user')) return UserIcon;
    if (t.includes('server')) return ServerIcon;
    if (t.includes('cctv')) return CctvIcon;
    if (t.includes('network') || t.includes('bandwidth') || t.includes('uptime')) return NetworkIcon;
    if (t.includes('asset') || t.includes('hardware') || t.includes('license') || t.includes('accessory') || t.includes('consumable') || t.includes('component')) return AssetIcon;
    return EditIcon;
};

const getActionIcon = (type: string) => {
    switch(type) {
        case 'created':
        case 'create':
            return PackageIcon;
        case 'updated':
        case 'update':
            return EditIcon;
        case 'deleted':
        case 'delete':
            return Search;
        case 'login':
        case 'logout':
            return UserIcon;
        default:
            return RefreshCw;
    }
};
</script>

<template>
    <div class="space-y-4">
        <!-- TABLE CARD -->
        <div class="bg-white rounded-[32px] border border-slate-200/60 shadow-xl shadow-slate-200/50 p-6 lg:p-8">
            
            <!-- Toolbar Section -->
            <div class="mb-8 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <div class="relative w-full lg:max-w-md">
                    <Search class="absolute left-4 top-1/2 -translate-y-1/2 size-4 text-slate-400" />
                    <input
                        v-model="filterForm.search"
                        type="text"
                        placeholder="Cari jejak audit, item, user, catatan..."
                        class="w-full h-10 pl-11 pr-4 rounded-xl border border-slate-200 bg-slate-50/30 text-xs text-slate-900 placeholder:text-slate-400 focus:border-[#003628]/50 focus:bg-white transition-all outline-none shadow-sm"
                    />
                </div>

                <div class="flex items-center gap-2">
                    <!-- Quick Category Selector -->
                    <select
                        v-model="filterForm.filter_category"
                        class="h-10 px-3 rounded-xl border border-slate-200 bg-white text-xs font-bold text-slate-700 outline-none focus:border-[#003628]/50 shadow-sm transition-all"
                    >
                        <option value="">Semua Kategori</option>
                        <option v-for="cat in filterOptions.categories" :key="cat.key" :value="cat.key">
                            {{ cat.label }}
                        </option>
                    </select>

                    <a
                        :href="exportUrl"
                        class="h-10 px-3 rounded-xl border border-slate-200 bg-white flex items-center justify-center text-slate-500 hover:text-slate-900 hover:bg-slate-50 transition-all active:scale-95 shadow-sm"
                        title="Ekspor CSV"
                    >
                        <Download class="size-4" />
                    </a>

                    <div ref="filterPanelRef" class="relative">
                        <button
                            type="button"
                            class="h-10 px-4 rounded-xl border border-slate-200 bg-white flex items-center gap-2.5 text-xs font-bold text-slate-600 hover:text-slate-900 hover:bg-slate-50 transition-all active:scale-95 shadow-sm"
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
                            enter-active-class="transition duration-200 ease-out"
                            enter-from-class="opacity-0 translate-y-2 scale-95"
                            enter-to-class="opacity-100 translate-y-0 scale-100"
                            leave-active-class="transition duration-150 ease-in"
                            leave-from-class="opacity-100 translate-y-0 scale-100"
                            leave-to-class="opacity-0 translate-y-2 scale-95"
                        >
                            <div
                                v-if="showFilters"
                                class="absolute top-full right-0 z-50 mt-3 w-84 rounded-[32px] border border-slate-200 bg-white p-6 shadow-2xl backdrop-blur-xl overflow-hidden"
                            >
                                <div class="flex items-center justify-between mb-6">
                                    <h3 class="text-[10px] font-black uppercase tracking-widest text-slate-400">Filter Log Aktivitas</h3>
                                    <button 
                                        @click="filterForm.search = ''; filterForm.filter_category = ''; filterForm.filter_action = ''; filterForm.filter_admin = ''; filterForm.filter_item = ''; clearDateFilters(); showFilters = false;"
                                        class="text-[10px] font-black uppercase tracking-widest text-[#003628] hover:opacity-70 transition-colors flex items-center gap-1.5"
                                    >
                                        <RefreshCw class="size-3" /> Reset
                                    </button>
                                </div>

                                <div class="space-y-4">
                                    <div class="space-y-1.5">
                                        <label class="text-[9px] font-black uppercase tracking-widest text-slate-500 ml-1">Kategori Entitas</label>
                                        <select v-model="filterForm.filter_category" class="w-full h-9 px-3 rounded-xl border border-slate-200 bg-slate-50 text-[11px] font-medium text-slate-700 outline-none focus:border-[#003628]/50 focus:bg-white appearance-none">
                                            <option value="">Semua Kategori</option>
                                            <option v-for="cat in filterOptions.categories" :key="cat.key" :value="cat.key">{{ cat.label }}</option>
                                        </select>
                                    </div>

                                    <div class="space-y-1.5">
                                        <label class="text-[9px] font-black uppercase tracking-widest text-slate-500 ml-1">Otorisasi Oleh</label>
                                        <select v-model="filterForm.filter_admin" class="w-full h-9 px-3 rounded-xl border border-slate-200 bg-slate-50 text-[11px] font-medium text-slate-700 outline-none focus:border-[#003628]/50 focus:bg-white appearance-none">
                                            <option value="">Semua Admin / User</option>
                                            <option v-for="admin in filterOptions.admins" :key="admin" :value="admin">{{ admin }}</option>
                                        </select>
                                    </div>

                                    <div class="space-y-1.5">
                                        <label class="text-[9px] font-black uppercase tracking-widest text-slate-500 ml-1">Jenis Operasi</label>
                                        <select v-model="filterForm.filter_action" class="w-full h-9 px-3 rounded-xl border border-slate-200 bg-slate-50 text-[11px] font-medium text-slate-700 outline-none focus:border-[#003628]/50 focus:bg-white appearance-none capitalize">
                                            <option value="">Semua Operasi</option>
                                            <option v-for="action in filterOptions.actions" :key="action" :value="action">{{ action }}</option>
                                        </select>
                                    </div>

                                    <div class="grid grid-cols-2 gap-3 pt-3 border-t border-slate-100">
                                        <div class="space-y-1.5">
                                            <label class="text-[9px] font-black uppercase tracking-widest text-slate-400">Dari</label>
                                            <input v-model="filterForm.from_date" type="date" class="w-full h-9 px-2 rounded-xl border border-slate-200 bg-slate-50 text-[10px] font-medium text-slate-700 outline-none focus:border-[#003628]/50 focus:bg-white" />
                                        </div>
                                        <div class="space-y-1.5">
                                            <label class="text-[9px] font-black uppercase tracking-widest text-slate-400">Hingga</label>
                                            <input v-model="filterForm.to_date" type="date" class="w-full h-9 px-2 rounded-xl border border-slate-200 bg-slate-50 text-[10px] font-medium text-slate-700 outline-none focus:border-[#003628]/50 focus:bg-white" />
                                        </div>
                                    </div>

                                    <!-- Quick Date Presets -->
                                    <div class="flex items-center gap-1.5 pt-2">
                                        <button
                                            type="button"
                                            @click="applyDatePreset('today')"
                                            class="flex-1 h-7 rounded-lg text-[10px] font-black transition-colors"
                                            :class="isPresetActive('today') ? 'bg-[#003628] text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                        >
                                            Hari Ini
                                        </button>
                                        <button
                                            type="button"
                                            @click="applyDatePreset('last7Days')"
                                            class="flex-1 h-7 rounded-lg text-[10px] font-black transition-colors"
                                            :class="isPresetActive('last7Days') ? 'bg-[#003628] text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                        >
                                            7 Hari
                                        </button>
                                        <button
                                            type="button"
                                            @click="applyDatePreset('thisMonth')"
                                            class="flex-1 h-7 rounded-lg text-[10px] font-black transition-colors"
                                            :class="isPresetActive('thisMonth') ? 'bg-[#003628] text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                        >
                                            Bulan Ini
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </Transition>
                    </div>
                </div>
            </div>

            <!-- Desktop Table -->
            <div class="hidden md:block overflow-hidden rounded-xl border border-slate-200/50">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/50">
                            <th class="px-6 py-3 text-left text-[10px] font-black uppercase tracking-widest text-slate-400">Timeline</th>
                            <th class="px-6 py-3 text-left text-[10px] font-black uppercase tracking-widest text-slate-400">Otorisasi Oleh</th>
                            <th class="px-6 py-3 text-left text-[10px] font-black uppercase tracking-widest text-slate-400">Entitas & Kategori</th>
                            <th class="px-6 py-3 text-left text-[10px] font-black uppercase tracking-widest text-slate-400">Operasi</th>
                            <th class="px-6 py-3 text-left text-[10px] font-black uppercase tracking-widest text-slate-400">Catatan</th>
                            <th class="px-4 py-3 text-center text-[10px] font-black uppercase tracking-widest text-slate-400">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-for="log in logs.data" :key="log.id" class="group hover:bg-slate-50/50 transition-colors">
                            <!-- Timeline -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-2.5">
                                    <component :is="getActionIcon(log.action_type)" class="size-3.5 text-slate-300 group-hover:text-[#003628] transition-colors" />
                                    <span class="text-[11px] font-black text-slate-900 tabular-nums font-mono tracking-tighter leading-none">{{ log.created_at }}</span>
                                </div>
                            </td>

                            <!-- Otorisasi Oleh -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-2.5">
                                    <div class="h-7 w-7 rounded-lg bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-400 group-hover:text-[#003628] transition-colors">
                                        <UserIcon class="size-3.5" />
                                    </div>
                                    <Link v-if="log.user" :href="`/users/${log.user.id}`" class="text-[12px] font-black text-slate-900 hover:text-[#003628] hover:underline transition-all">
                                        {{ log.user.name }}
                                    </Link>
                                    <span v-else class="text-[12px] font-black text-slate-400">Sistem</span>
                                </div>
                            </td>

                            <!-- Entity / Item Name -->
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <div class="h-7 w-7 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center shrink-0">
                                        <component :is="getModelIcon(log.item_type)" class="size-3.5" />
                                    </div>
                                    <div class="space-y-0.5">
                                        <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">{{ log.category || log.item_type }}</span>
                                        <button 
                                            v-if="log.item_id && ['assets', 'hardware', 'laptop', 'license', 'accessories', 'consumable', 'component'].includes((log.item_type || '').toLowerCase())" 
                                            @click="emit('open-asset', log.item_id, log.item_type)"
                                            class="text-[12px] font-black text-[#003628] hover:underline transition-all text-left cursor-pointer active:scale-95 truncate max-w-xs block"
                                        >
                                            {{ log.item_name || 'Lihat Detail' }}
                                        </button>
                                        <Link
                                            v-else-if="log.item_url"
                                            :href="log.item_url"
                                            class="text-[12px] font-black text-slate-800 hover:text-[#003628] hover:underline transition-all truncate max-w-xs block"
                                        >
                                            {{ log.item_name || 'Buka Halaman' }}
                                        </Link>
                                        <span v-else class="text-[12px] font-black text-slate-700 truncate max-w-xs block">
                                            {{ log.item_name || '—' }}
                                        </span>
                                    </div>
                                </div>
                            </td>

                            <!-- Operasi -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span
                                    class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-widest border"
                                    :class="{
                                        'bg-[#003628]/5 text-[#003628] border-[#003628]/10': ['created', 'create', 'login', 'add_stock'].includes(log.action_type),
                                        'bg-blue-50 text-blue-700 border-blue-100': ['checkin'].includes(log.action_type),
                                        'bg-amber-50 text-amber-600 border-amber-100': ['updated', 'update', 'checkout'].includes(log.action_type),
                                        'bg-red-50 text-red-500 border-red-100': ['deleted', 'delete'].includes(log.action_type),
                                        'bg-slate-100 text-slate-500 border-slate-200': !['created', 'create', 'updated', 'update', 'deleted', 'delete', 'login', 'checkout', 'checkin', 'add_stock'].includes(log.action_type)
                                    }"
                                >
                                    {{ log.action_label || log.action_type }}
                                </span>
                            </td>

                            <!-- Catatan -->
                            <td class="px-6 py-4">
                                <p class="text-[12px] font-medium text-slate-600 line-clamp-2 max-w-sm" :title="log.note || ''">
                                    {{ log.note || '—' }}
                                </p>
                            </td>

                            <!-- Aksi -->
                            <td class="px-4 py-4 text-center whitespace-nowrap">
                                <button
                                    type="button"
                                    @click="emit('open-detail', log)"
                                    class="h-8 px-3 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 hover:text-slate-900 text-xs font-bold transition-all active:scale-95 shadow-xs inline-flex items-center gap-1.5"
                                    title="Lihat Detail Log"
                                >
                                    <EyeIcon class="size-3.5" />
                                    <span>Detail</span>
                                </button>
                            </td>
                        </tr>
                        <tr v-if="logs.data.length === 0">
                            <td colspan="6" class="px-6 py-20 text-center text-slate-400 italic text-sm">
                                Tidak ada catatan jejak audit yang sesuai dengan filter Anda.
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
                            <component :is="getModelIcon(log.item_type)" class="size-4 text-[#003628]" />
                            <span class="text-xs font-black text-slate-900">{{ log.category || log.item_type }}</span>
                        </div>
                        <span
                            class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-widest border"
                            :class="{
                                'bg-[#003628]/5 text-[#003628] border-[#003628]/10': ['created', 'create', 'login', 'add_stock'].includes(log.action_type),
                                'bg-blue-50 text-blue-700 border-blue-100': ['checkin'].includes(log.action_type),
                                'bg-amber-50 text-amber-600 border-amber-100': ['updated', 'update', 'checkout'].includes(log.action_type),
                                'bg-red-50 text-red-500 border-red-100': ['deleted', 'delete'].includes(log.action_type),
                                'bg-slate-100 text-slate-500 border-slate-200': !['created', 'create', 'updated', 'update', 'deleted', 'delete', 'login', 'checkout', 'checkin', 'add_stock'].includes(log.action_type)
                            }"
                        >
                            {{ log.action_label || log.action_type }}
                        </span>
                    </div>

                    <div class="space-y-1">
                        <p v-if="log.item_name" class="text-xs font-bold text-[#003628]">{{ log.item_name }}</p>
                        <p v-if="log.note" class="text-xs text-slate-600">{{ log.note }}</p>
                    </div>

                    <div class="flex items-center justify-between text-[10px] text-slate-400 font-mono pt-2 border-t border-slate-200/60">
                        <span>{{ log.created_at }}</span>
                        <button
                            type="button"
                            @click="emit('open-detail', log)"
                            class="text-[#003628] font-bold underline"
                        >
                            Lihat Detail
                        </button>
                    </div>
                </div>
            </div>

            <!-- Table Footer -->
            <div class="px-6 py-4 flex flex-col sm:flex-row items-center justify-between gap-4 border-t border-slate-100 bg-white mt-4">
                 <span class="text-[11px] font-black uppercase tracking-widest text-slate-400">{{ summaryText }}</span>
                 <nav v-if="logs.links.length > 3" class="flex items-center gap-1.5">
                    <Link
                        v-for="(link, i) in logs.links"
                        :key="i"
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
