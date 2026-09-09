<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    LucideFileText as FileText,
    LucideUser as UserIcon,
    LucideCalendar as CalendarIcon,
    LucideExternalLink as ExternalLink,
    LucideDownload as Download,
    LucideX as XIcon,
    LucideLayers as Layers,
    LucidePenTool as PenTool,
    LucideEdit3 as EditIcon,
} from 'lucide-vue-next';
import { computed } from 'vue';

interface LogUser {
    id: number;
    name: string;
}

export interface FormLogItem {
    id: number;
    action_type: string;
    action_label: string;
    note?: string | null;
    log_meta?: Record<string, any> | null;
    created_at: string;
    user: LogUser | null;
    form_type: string;
    form_name: string;
    doc_no?: string | null;
    doc_url?: string | null;
    pdf_url?: string | null;
    role?: string | null;
    target_name?: string | null;
    changed?: string | null;
}

const props = defineProps<{
    open: boolean;
    log: FormLogItem | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const close = () => {
    emit('update:open', false);
};

const formatRoleName = (role: string | null | undefined) => {
    if (!role) return '-';
    return role
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (c) => c.toUpperCase());
};

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
    }

    // 4. Fallback parsing from note
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
        'id', 'user_id', 'created_at', 'updated_at',
        'doc_no', 'form_name', 'form_type', 'action', 'action_type', 'role',
    ];
    for (const k of ignoreKeys) {
        delete meta[k];
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
                            <FileText class="size-5" />
                        </div>
                        <div class="min-w-0">
                            <span class="text-[10px] font-black uppercase tracking-widest text-[#003628] block truncate">
                                Detail Log Formulir
                            </span>
                            <h2 class="text-base font-black text-slate-900 leading-tight truncate" :title="log.doc_no || log.form_name">
                                {{ log.doc_no || log.form_name }}
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
                    <!-- Status & Type Banner -->
                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-between gap-3">
                        <div class="space-y-0.5 min-w-0">
                            <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">Jenis Formulir</span>
                            <p class="text-xs font-black text-slate-900 truncate">{{ log.form_name }}</p>
                        </div>
                        <span
                            class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest border shrink-0"
                            :class="{
                                'bg-[#003628]/5 text-[#003628] border-[#003628]/15':
                                    ['created', 'create', 'stb_complete', 'completed'].includes(log.action_type),
                                'bg-violet-50 text-violet-700 border-violet-200':
                                    log.action_type === 'sign' || log.action_type === 'sign_cleared',
                                'bg-amber-50 text-amber-600 border-amber-200':
                                    log.action_type === 'updated' || log.action_type === 'update',
                                'bg-rose-50 text-rose-600 border-rose-200':
                                    ['deleted', 'delete', 'cancelled', 'sync_failed'].includes(log.action_type),
                                'bg-slate-100 text-slate-600 border-slate-200':
                                    !['created', 'create', 'stb_complete', 'completed', 'sign', 'sign_cleared', 'updated', 'update', 'deleted', 'delete', 'cancelled', 'sync_failed'].includes(log.action_type)
                            }"
                        >
                            {{ log.action_label }}
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

                    <!-- Role Signed (if any) -->
                    <div v-if="log.role" class="p-3.5 rounded-xl border border-violet-100 bg-violet-50/50 flex items-center gap-3">
                        <div class="h-8 w-8 rounded-lg bg-violet-100 text-violet-700 flex items-center justify-center shrink-0">
                            <PenTool class="size-4" />
                        </div>
                        <div class="min-w-0">
                            <span class="text-[9px] font-black uppercase tracking-widest text-violet-700 block">Role Penandatangan</span>
                            <p class="text-xs font-black text-slate-900 truncate">{{ formatRoleName(log.role) }}</p>
                        </div>
                    </div>

                    <!-- Note -->
                    <div v-if="log.note && log.note !== '-'" class="space-y-1.5">
                        <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 ml-1">Catatan Log</span>
                        <div class="p-4 rounded-xl border border-slate-100 bg-slate-50 text-xs font-medium text-slate-700 leading-relaxed break-words whitespace-pre-line">
                            {{ log.note }}
                        </div>
                    </div>

                    <!-- Field Changes (Perubahan Nilai / Edit Details) -->
                    <div v-if="parsedChanges.length > 0" class="space-y-1.5">
                        <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 ml-1 flex items-center gap-1.5">
                            <EditIcon class="size-3 text-[#003628]" /> Rincian Perubahan Field
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
                            <Layers class="size-3 text-slate-400" /> Rincian Metadata
                        </span>
                        
                        <div class="divide-y divide-slate-100 rounded-xl border border-slate-100 bg-slate-50/60 overflow-hidden text-xs">
                            <div
                                v-for="(val, key) in filteredMeta"
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
                    <a
                        v-if="log.pdf_url"
                        :href="log.pdf_url"
                        target="_blank"
                        class="flex-1 h-10 px-4 rounded-xl border border-slate-200 bg-white text-slate-700 hover:text-slate-900 hover:bg-slate-50 flex items-center justify-center gap-2 text-xs font-bold transition-all shadow-sm active:scale-95"
                    >
                        <Download class="size-3.5" />
                        <span>Unduh Dokumen PDF</span>
                    </a>

                    <Link
                        v-if="log.doc_url"
                        :href="log.doc_url"
                        class="flex-1 h-10 px-4 rounded-xl bg-[#003628] text-white hover:bg-[#00271d] flex items-center justify-center gap-2 text-xs font-bold transition-all shadow-md shadow-emerald-900/10 active:scale-95"
                    >
                        <ExternalLink class="size-3.5" />
                        <span>Buka Dokumen</span>
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
