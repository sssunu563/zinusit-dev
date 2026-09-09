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
    LucideTag as TagIcon,
    LucideEdit3 as EditIcon,
    LucideCpu as CpuIcon,
    LucideHash as HashIcon,
    LucideFileText as FileText,
    LucideLaptop as LaptopIcon,
    LucideServer as ServerIcon,
} from 'lucide-vue-next';
import { computed } from 'vue';

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
    item_raw_type?: string | null;
    item_url?: string | null;
    item_id?: number | null;
    item_name?: string | null;
    target_type: string;
    target_url?: string | null;
    target_name?: string | null;
    category?: string;
    form_name?: string | null;
    form_type?: string | null;
    changed?: string | null;
    asset_tag?: string | null;
    serial?: string | null;
    device_specs?: Array<{ label: string; value: string }> | null;
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

const displayTitle = computed(() => {
    if (!props.log) return 'Detail Log';
    return (
        props.log.item_name ||
        props.log.target_name ||
        props.log.form_name ||
        props.log.action_label ||
        'Log Aktivitas'
    );
});

const displayCategory = computed(() => {
    if (!props.log) return 'Aktivitas Sistem';
    return props.log.form_name || props.log.category || 'Detail Log Aktivitas';
});

const getHeaderIcon = computed(() => {
    const raw = (props.log?.item_type || props.log?.item_raw_type || props.log?.category || '').toLowerCase();
    if (raw.includes('laptop')) return LaptopIcon;
    if (raw.includes('server')) return ServerIcon;
    if (raw.includes('user')) return UserIcon;
    if (raw.includes('asset') || raw.includes('hardware') || raw.includes('consumable') || raw.includes('component')) return AssetIcon;
    if (raw.includes('form') || props.log?.form_name) return FileText;
    return ActivityIcon;
});

const parsedChanges = computed(() => {
    if (!props.log) return [];

    const changes: Array<{
        field: string;
        oldValue: any;
        newValue: any;
    }> = [];

    const addChange = (field: string, oldVal: any, newVal: any) => {
        const cleanField = field.replace(/^(Field|Atribut)\s+/i, '').replace(/_/g, ' ').trim().toUpperCase();
        if (!cleanField) return;

        // Skip non-meaningful changes or passwords
        if (['PASSWORD', 'REMEMBER TOKEN', 'UPDATED AT', 'CREATED AT'].includes(cleanField)) return;

        const oldStr = oldVal !== undefined && oldVal !== null && String(oldVal).trim() !== '' ? String(oldVal).trim() : 'kosong';
        const newStr = newVal !== undefined && newVal !== null && String(newVal).trim() !== '' ? String(newVal).trim() : 'kosong';

        if (oldStr === newStr && oldStr !== 'kosong') return;

        changes.push({
            field: cleanField,
            oldValue: oldStr,
            newValue: newStr,
        });
    };

    // 1. If explicit 'changed' field exists
    const rawChanged = props.log.changed;
    if (typeof rawChanged === 'string' && rawChanged.trim() !== '') {
        const parts = rawChanged.split(/\s+\|\|\s+|\s+\|\s+/);
        for (const part of parts) {
            const arrowIdx = part.indexOf(' -> ');
            if (arrowIdx !== -1) {
                const beforeArrow = part.slice(0, arrowIdx);
                const colonIdx = beforeArrow.indexOf(': ');
                const field = colonIdx !== -1 ? beforeArrow.slice(0, colonIdx) : beforeArrow;
                const oldVal = colonIdx !== -1 ? beforeArrow.slice(colonIdx + 2) : '';
                const newVal = part.slice(arrowIdx + 4);
                addChange(field, oldVal, newVal);
            } else if (part.trim()) {
                changes.push({ field: 'INFORMASI', oldValue: part.trim(), newValue: null });
            }
        }
    }

    // 2. If log_meta contains changed_fields (array of strings or objects)
    const meta = props.log.log_meta;
    if (meta && typeof meta === 'object') {
        if (Array.isArray(meta.changed_fields)) {
            for (const item of meta.changed_fields) {
                if (typeof item === 'string' && item.trim() !== '') {
                    const arrowIdx = item.indexOf(' -> ');
                    if (arrowIdx !== -1) {
                        const beforeArrow = item.slice(0, arrowIdx);
                        const colonIdx = beforeArrow.indexOf(': ');
                        const field = colonIdx !== -1 ? beforeArrow.slice(0, colonIdx) : beforeArrow;
                        const oldVal = colonIdx !== -1 ? beforeArrow.slice(colonIdx + 2) : '';
                        const newVal = item.slice(arrowIdx + 4);
                        addChange(field, oldVal, newVal);
                    } else {
                        changes.push({ field: 'FIELD', oldValue: item.trim(), newValue: null });
                    }
                } else if (typeof item === 'object' && item !== null) {
                    addChange(item.field || item.name || 'FIELD', item.old ?? item.from, item.new ?? item.to);
                }
            }
        }

        // 3. If log_meta has old and new objects (Loggable trait)
        if (meta.old && meta.new && typeof meta.old === 'object' && typeof meta.new === 'object') {
            const keys = Array.from(new Set([...Object.keys(meta.old), ...Object.keys(meta.new)]));
            for (const key of keys) {
                if (['updated_at', 'created_at', 'id', 'password', 'remember_token'].includes(key)) continue;
                const oldVal = meta.old[key];
                const newVal = meta.new[key];
                if (oldVal != newVal) {
                    addChange(key, oldVal, newVal);
                }
            }
        }

        // 4. If log_meta has changes payload (Snipe-IT)
        if (meta.changes && typeof meta.changes === 'object') {
            for (const [field, c] of Object.entries(meta.changes)) {
                if (typeof c === 'object' && c !== null) {
                    addChange(field, (c as any).old ?? (c as any).before, (c as any).new ?? (c as any).after);
                }
            }
        }
    }

    // 5. Fallback parsing from note
    if (changes.length === 0 && props.log.note) {
        const note = props.log.note;
        if (note.includes(' -> ') || note.includes(' diubah: ')) {
            const parts = note.split(/\s*\|\|\s*|\s*,\s*(?=[a-zA-Z0-9_]+:)/);
            for (const part of parts) {
                const arrowIdx = part.indexOf(' -> ');
                if (arrowIdx !== -1) {
                    const colonIdx = part.indexOf(':');
                    const field = colonIdx !== -1 ? part.substring(0, colonIdx) : 'FIELD';
                    const oldVal = colonIdx !== -1 ? part.substring(colonIdx + 1, arrowIdx).trim() : part.substring(0, arrowIdx).trim();
                    const newVal = part.substring(arrowIdx + 4).trim();
                    addChange(field, oldVal, newVal);
                }
            }
        }
    }

    return changes;
});

const filteredMeta = computed(() => {
    if (!props.log?.log_meta) return {};
    const meta = { ...props.log.log_meta };
    const ignoreKeys = [
        'old', 'new', 'changed_fields', 'changes',
        'item_name', 'target_name', 'asset_tag', 'serial', 'serial_number', 'tag', 'name',
        'id', 'item_id', 'target_id', 'user_id', 'created_at', 'updated_at',
        'model', 'model_id', 'order_number', 'purchase_date', 'purchase_cost',
        'action', 'action_type', 'added_qty', 'new_qty', 'recipient',
    ];
    for (const k of ignoreKeys) {
        delete meta[k];
    }
    for (const k of Object.keys(meta)) {
        if (k.startsWith('_snipeit_')) {
            delete meta[k];
        }
    }
    return meta;
});
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
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="h-10 w-10 shrink-0 rounded-xl bg-[#003628]/10 text-[#003628] flex items-center justify-center">
                            <component :is="getHeaderIcon" class="size-5" />
                        </div>
                        <div class="min-w-0">
                            <span class="text-[10px] font-black uppercase tracking-widest text-[#003628] block truncate">
                                {{ displayCategory }}
                            </span>
                            <h2 class="text-base font-black text-slate-900 leading-tight truncate" :title="displayTitle">
                                {{ displayTitle }}
                            </h2>
                        </div>
                    </div>
                    <button
                        @click="close"
                        class="h-8 w-8 shrink-0 rounded-lg border border-slate-200 bg-white text-slate-400 hover:text-slate-700 hover:bg-slate-50 flex items-center justify-center transition-colors"
                    >
                        <XIcon class="size-4" />
                    </button>
                </div>

                <!-- Body -->
                <div class="flex-1 overflow-y-auto p-6 space-y-6">
                    <!-- Status & Modul Banner -->
                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-between gap-3">
                        <div class="space-y-0.5 min-w-0">
                            <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">
                                Jenis Operasi / Modul
                            </span>
                            <p class="text-xs font-black text-slate-900 truncate">
                                {{ log.form_name || log.category || 'Aktivitas Sistem' }}
                            </p>
                        </div>
                        <span
                            class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest border shrink-0"
                            :class="{
                                'bg-[#003628]/5 text-[#003628] border-[#003628]/15':
                                    ['created', 'create', 'stb_complete', 'completed', 'login', 'add_stock'].some((k) =>
                                        (log.action_type || '').toLowerCase().includes(k)
                                    ),
                                'bg-amber-50 text-amber-600 border-amber-200':
                                    ['updated', 'update', 'sign', 'checkout'].some((k) =>
                                        (log.action_type || '').toLowerCase().includes(k)
                                    ),
                                'bg-blue-50 text-blue-700 border-blue-200':
                                    ['checkin', 'mutasi', 'sync'].some((k) =>
                                        (log.action_type || '').toLowerCase().includes(k)
                                    ),
                                'bg-rose-50 text-rose-600 border-rose-200':
                                    ['deleted', 'delete', 'cancelled', 'batal', 'gagal'].some((k) =>
                                        (log.action_type || '').toLowerCase().includes(k)
                                    ),
                                'bg-slate-100 text-slate-600 border-slate-200':
                                    !['created', 'create', 'stb_complete', 'completed', 'login', 'add_stock', 'updated', 'update', 'sign', 'checkout', 'checkin', 'mutasi', 'deleted', 'delete'].some((k) =>
                                        (log.action_type || '').toLowerCase().includes(k)
                                    )
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
                            <span v-else class="text-[11px] font-bold text-slate-800 block truncate">Sistem</span>
                        </div>
                    </div>

                    <!-- Perangkat / Aset Terkait Box (Unified Elegant Specs Card) -->
                    <div
                        v-if="log.item_name || log.asset_tag || log.serial || (log.device_specs && log.device_specs.length > 0)"
                        class="p-4 rounded-2xl border border-emerald-100 bg-emerald-50/40 space-y-3"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <div class="h-8 w-8 rounded-lg bg-[#003628]/10 text-[#003628] flex items-center justify-center shrink-0">
                                    <AssetIcon class="size-4" />
                                </div>
                                <div class="min-w-0">
                                    <span class="text-[9px] font-black uppercase tracking-widest text-emerald-800 block">
                                        Perangkat / Aset Terkait
                                    </span>
                                    <p class="text-xs font-black text-slate-900 truncate" :title="log.item_name || ''">
                                        {{ log.item_name || ('Aset #' + log.item_id) }}
                                    </p>
                                </div>
                            </div>
                            <div v-if="log.asset_tag" class="px-2.5 py-1 rounded-lg bg-emerald-100/80 border border-emerald-200 text-[#003628] font-mono text-[11px] font-black shrink-0 tracking-tight">
                                {{ log.asset_tag }}
                            </div>
                        </div>

                        <!-- Device Specs / Identifiers in clean neat rows -->
                        <div v-if="log.serial || (log.device_specs && log.device_specs.length > 0)" class="pt-2.5 border-t border-emerald-100/80 space-y-1.5">
                            <div v-if="log.serial" class="flex items-center justify-between gap-2 py-0.5 text-xs">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Serial Number</span>
                                <span class="text-[11px] font-mono font-bold text-slate-800 bg-white/80 px-2 py-0.5 rounded border border-emerald-100/80">{{ log.serial }}</span>
                            </div>
                            <div
                                v-for="(spec, idx) in log.device_specs"
                                :key="idx"
                                class="flex items-center justify-between gap-2 py-0.5 text-xs border-t border-emerald-100/40"
                            >
                                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500">{{ spec.label }}</span>
                                <span class="text-[11px] font-mono font-semibold text-slate-800 text-right truncate max-w-[240px]">{{ spec.value }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Target / Pihak Terkait Grid -->
                    <div v-if="log.target_name" class="p-4 rounded-xl border border-slate-100 bg-slate-50 space-y-2.5">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">
                                Target / Pihak Terkait
                            </span>
                            <span class="text-xs font-bold text-slate-800 text-right truncate">
                                {{ log.target_name }}
                            </span>
                        </div>
                    </div>

                    <!-- Catatan Log (Note) -->
                    <div v-if="log.note && log.note !== '-'" class="space-y-1.5">
                        <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 ml-1">
                            Catatan Log
                        </span>
                        <div class="p-4 rounded-xl border border-slate-100 bg-slate-50 text-xs font-medium text-slate-700 leading-relaxed break-words whitespace-pre-line">
                            {{ log.note }}
                        </div>
                    </div>

                    <!-- Field Changes (Rincian Perubahan Field) -->
                    <div v-if="parsedChanges.length > 0" class="space-y-1.5">
                        <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 ml-1 flex items-center gap-1.5">
                            <EditIcon class="size-3 text-[#003628]" />
                            Rincian Perubahan Field
                        </span>
                        <div class="divide-y divide-slate-100 rounded-xl border border-slate-100 bg-slate-50/80 overflow-hidden text-xs">
                            <div
                                v-for="(change, idx) in parsedChanges"
                                :key="idx"
                                class="px-3.5 py-2.5 flex flex-col sm:flex-row sm:items-center justify-between gap-2"
                            >
                                <span v-if="change.field" class="font-black text-[10px] uppercase tracking-wider text-slate-500">
                                    {{ change.field }}
                                </span>
                                <div v-if="change.newValue !== null" class="flex items-center gap-2 text-[11px] font-mono">
                                    <s class="text-rose-400">{{ change.oldValue || 'kosong' }}</s>
                                    <span class="text-slate-400">→</span>
                                    <span class="font-bold text-emerald-700">{{ change.newValue }}</span>
                                </div>
                                <span v-else class="font-medium text-slate-700 text-[11px]">
                                    {{ change.oldValue }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Detailed Meta Payload -->
                    <div v-if="filteredMeta && Object.keys(filteredMeta).length > 0" class="space-y-2">
                        <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 ml-1 flex items-center gap-1.5">
                            <Layers class="size-3 text-slate-400" />
                            Rincian Metadata
                        </span>
                        
                        <div class="divide-y divide-slate-100 rounded-xl border border-slate-100 bg-slate-50/60 overflow-hidden text-xs">
                            <div
                                v-for="(val, key) in filteredMeta"
                                :key="key"
                                class="px-3.5 py-2.5 flex flex-col sm:flex-row sm:items-start justify-between gap-2"
                            >
                                <span class="font-black text-[10px] uppercase tracking-wider text-slate-400">
                                    {{ key }}
                                </span>
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
                        v-if="log.item_id && ['assets', 'hardware', 'laptop', 'license', 'accessories', 'consumable', 'component'].includes((log.item_type || '').toLowerCase())"
                        type="button"
                        @click="handleOpenAsset"
                        class="flex-1 h-10 px-4 rounded-xl border border-slate-200 bg-white text-slate-700 hover:text-slate-900 hover:bg-slate-50 flex items-center justify-center gap-2 text-xs font-bold transition-all shadow-sm active:scale-95"
                    >
                        <AssetIcon class="size-3.5 text-[#003628]" />
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

