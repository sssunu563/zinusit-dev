<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    LucideActivity as ActivityIcon,
    LucideUser as UserIcon,
    LucideCalendar as CalendarIcon,
    LucideExternalLink as ExternalLink,
    LucideX as XIcon,
    LucideLayers as Layers,
    LucideHardDrive as AssetIcon,
    LucideSlidersHorizontal as Sliders,
    LucideTag as TagIcon,
} from 'lucide-vue-next';

interface LogUser {
    id: number;
    name: string;
}

export interface ActionLogItem {
    id: number;
    action_type: string;
    action_label: string;
    note?: string | null;
    log_meta?: Record<string, any> | null;
    created_at: string;
    user: LogUser | null;
    item_type: string;
    item_raw_type?: string;
    item_url?: string | null;
    item_id?: number;
    item_name?: string | null;
    target_type: string;
    target_url?: string | null;
    target_name?: string | null;
    category?: string;
}

const props = defineProps<{
    open: boolean;
    log: ActionLogItem | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'open-asset', id: number, type: string): void;
}>();

const close = () => {
    emit('update:open', false);
};

const handleOpenAsset = () => {
    if (!props.log?.item_id || !props.log?.item_type) return;
    close();
    emit('open-asset', props.log.item_id, props.log.item_type);
};
</script>

<template>
    <div v-if="open && log" class="fixed inset-0 z-50 overflow-hidden">
        <!-- Backdrop -->
        <div
            class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity"
            @click="close"
        />

        <div class="fixed inset-y-0 right-0 max-w-full flex pl-10">
            <div class="w-screen max-w-lg bg-white shadow-2xl flex flex-col border-l border-slate-200">
                <!-- Header -->
                <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-xl bg-[#003628]/10 text-[#003628] flex items-center justify-center">
                            <ActivityIcon class="size-5" />
                        </div>
                        <div>
                            <span class="text-[10px] font-black uppercase tracking-widest text-[#003628]">Detail Log Aktivitas</span>
                            <h2 class="text-base font-black text-slate-900 leading-tight">
                                {{ log.item_name || log.category || 'Log Aktivitas' }}
                            </h2>
                        </div>
                    </div>
                    <button
                        @click="close"
                        class="h-8 w-8 rounded-lg border border-slate-200 bg-white text-slate-400 hover:text-slate-700 hover:bg-slate-50 flex items-center justify-center transition-colors"
                    >
                        <XIcon class="size-4" />
                    </button>
                </div>

                <!-- Body -->
                <div class="flex-1 overflow-y-auto p-6 space-y-6">
                    <!-- Status & Category Banner -->
                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-between">
                        <div class="space-y-0.5">
                            <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">Kategori Entitas</span>
                            <p class="text-xs font-black text-slate-900">{{ log.category || log.item_type || 'Umum' }}</p>
                        </div>
                        <span
                            class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-widest border"
                            :class="{
                                'bg-[#003628]/5 text-[#003628] border-[#003628]/10': ['created', 'create', 'login', 'add_stock'].includes(log.action_type),
                                'bg-blue-50 text-blue-700 border-blue-100': ['checkin'].includes(log.action_type),
                                'bg-amber-50 text-amber-600 border-amber-100': ['updated', 'update', 'checkout'].includes(log.action_type),
                                'bg-red-50 text-red-500 border-red-100': ['deleted', 'delete'].includes(log.action_type),
                                'bg-slate-100 text-slate-600 border-slate-200': !['created', 'create', 'updated', 'update', 'deleted', 'delete', 'login', 'checkout', 'checkin', 'add_stock'].includes(log.action_type)
                            }"
                        >
                            {{ log.action_label || log.action_type }}
                        </span>
                    </div>

                    <!-- Meta Information Grid -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="p-3.5 rounded-xl border border-slate-100 bg-white shadow-sm space-y-1">
                            <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 flex items-center gap-1.5">
                                <CalendarIcon class="size-3 text-slate-400" /> Waktu Kejadian
                            </span>
                            <p class="text-[11px] font-mono font-bold text-slate-800">{{ log.created_at }}</p>
                        </div>

                        <div class="p-3.5 rounded-xl border border-slate-100 bg-white shadow-sm space-y-1">
                            <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 flex items-center gap-1.5">
                                <UserIcon class="size-3 text-slate-400" /> Otorisasi Oleh
                            </span>
                            <Link v-if="log.user" :href="`/users/${log.user.id}`" class="text-[11px] font-black text-[#003628] hover:underline block truncate">
                                {{ log.user.name }}
                            </Link>
                            <span v-else class="text-[11px] font-bold text-slate-400">Sistem</span>
                        </div>
                    </div>

                    <!-- Item Affected / Target Details -->
                    <div v-if="log.item_name || log.target_name" class="p-4 rounded-xl border border-slate-100 bg-slate-50 space-y-3">
                        <div v-if="log.item_name" class="flex items-center justify-between">
                            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 flex items-center gap-1.5">
                                <AssetIcon class="size-3.5 text-slate-400" /> Item / Entitas
                            </span>
                            <span class="text-xs font-black text-slate-900">{{ log.item_name }}</span>
                        </div>
                        <div v-if="log.target_name" class="flex items-center justify-between pt-2 border-t border-slate-200/50">
                            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 flex items-center gap-1.5">
                                <UserIcon class="size-3.5 text-slate-400" /> Target
                            </span>
                            <span class="text-xs font-bold text-slate-700">{{ log.target_name }}</span>
                        </div>
                    </div>

                    <!-- Note -->
                    <div v-if="log.note" class="space-y-1.5">
                        <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 ml-1">Catatan Log</span>
                        <div class="p-4 rounded-xl border border-slate-100 bg-slate-50 text-xs font-medium text-slate-700 leading-relaxed">
                            {{ log.note }}
                        </div>
                    </div>

                    <!-- Detailed Meta Payload -->
                    <div v-if="log.log_meta && Object.keys(log.log_meta).length > 0" class="space-y-2">
                        <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 ml-1 flex items-center gap-1.5">
                            <Layers class="size-3 text-slate-400" /> Rincian Metadata
                        </span>
                        
                        <div class="divide-y divide-slate-100 rounded-xl border border-slate-100 bg-slate-50/60 overflow-hidden text-xs">
                            <div
                                v-for="(val, key) in log.log_meta"
                                :key="key"
                                class="px-3.5 py-2.5 flex flex-col sm:flex-row sm:items-start justify-between gap-2"
                            >
                                <span class="font-black text-[10px] uppercase tracking-wider text-slate-400">{{ key }}</span>
                                <span class="font-medium text-slate-800 break-all text-right font-mono text-[11px]">
                                    {{ typeof val === 'object' ? JSON.stringify(val) : val }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer Actions -->
                <div class="p-4 border-t border-slate-100 bg-slate-50/50 flex items-center gap-3">
                    <button
                        v-if="log.item_id && ['assets', 'hardware', 'license', 'accessories', 'consumable', 'component'].includes((log.item_type || '').toLowerCase())"
                        type="button"
                        @click="handleOpenAsset"
                        class="flex-1 h-10 px-4 rounded-xl border border-slate-200 bg-white text-slate-700 hover:text-slate-900 hover:bg-slate-50 flex items-center justify-center gap-2 text-xs font-bold transition-all shadow-sm active:scale-95"
                    >
                        <AssetIcon class="size-3.5" />
                        <span>Lihat Aset (Drawer)</span>
                    </button>

                    <Link
                        v-if="log.item_url"
                        :href="log.item_url"
                        class="flex-1 h-10 px-4 rounded-xl bg-[#003628] text-white hover:bg-[#00271d] flex items-center justify-center gap-2 text-xs font-bold transition-all shadow-md shadow-emerald-900/10 active:scale-95"
                    >
                        <ExternalLink class="size-3.5" />
                        <span>Buka Halaman Item</span>
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
