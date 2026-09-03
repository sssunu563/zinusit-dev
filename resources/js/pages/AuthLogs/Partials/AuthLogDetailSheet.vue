<script setup lang="ts">
import {
    LucideShieldCheck as ShieldCheck,
    LucideUser as UserIcon,
    LucideCalendar as CalendarIcon,
    LucideCheckCircle2 as CheckCircle,
    LucideXCircle as XCircle,
    LucideX as XIcon,
    LucideGlobe as Globe,
    LucideLaptop as Laptop,
    LucideKeyRound as KeyIcon,
    LucideLogOut as LogOut,
    LucideRefreshCw as RefreshCw,
    LucideInfo as InfoIcon,
    LucideCode as CodeIcon,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

export interface AuthLogUser {
    id: number;
    name: string;
    email: string;
    username?: string | null;
    avatar?: string | null;
    department?: string | null;
    company?: string | null;
}

export interface AuthLogItem {
    id: number;
    event: string;
    event_label?: string;
    status: string;
    status_label?: string;
    identifier?: string | null;
    ip_address?: string | null;
    user_agent?: string | null;
    meta?: Record<string, unknown> | null;
    created_at?: string | null;
    user?: AuthLogUser | null;
}

const props = defineProps<{
    open: boolean;
    log: AuthLogItem | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const showRawJson = ref(false);

const close = () => {
    emit('update:open', false);
};

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

const parseBrowser = (userAgent?: string | null) => {
    if (!userAgent) return 'Perangkat Tidak Dikenal';
    if (userAgent.includes('Edg/')) return 'Microsoft Edge';
    if (userAgent.includes('Chrome/')) return 'Google Chrome';
    if (userAgent.includes('Firefox/')) return 'Mozilla Firefox';
    if (userAgent.includes('Safari/') && !userAgent.includes('Chrome/')) return 'Apple Safari';
    if (userAgent.includes('Postman')) return 'Postman API Client';
    if (userAgent.includes('PHPUnit')) return 'PHPUnit Test Runner';
    return 'Web Browser / Client';
};

const parseOs = (userAgent?: string | null) => {
    if (!userAgent) return '-';
    if (userAgent.includes('Windows NT 10.0')) return 'Windows 10 / 11';
    if (userAgent.includes('Windows NT')) return 'Windows OS';
    if (userAgent.includes('Mac OS X')) return 'macOS';
    if (userAgent.includes('Android')) return 'Android';
    if (userAgent.includes('iPhone') || userAgent.includes('iPad')) return 'iOS';
    if (userAgent.includes('Linux')) return 'Linux OS';
    return 'OS Tidak Dikenal';
};

const isSuccess = computed(() => {
    return ['success', 'matched', 'created', 'updated'].includes(props.log?.status || '');
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
                    <div class="flex items-center gap-3">
                        <div
                            class="h-10 w-10 rounded-xl flex items-center justify-center"
                            :class="isSuccess ? 'bg-[#003628]/10 text-[#003628]' : 'bg-red-50 text-red-600'"
                        >
                            <component :is="getEventIcon(log.event)" class="size-5" />
                        </div>
                        <div>
                            <span class="text-[10px] font-black uppercase tracking-widest text-[#003628]">Detail Audit Autentikasi</span>
                            <h2 class="text-base font-black text-slate-900 leading-tight">
                                {{ log.user?.name || log.identifier || 'Pengguna Anonim' }}
                            </h2>
                        </div>
                    </div>
                    <button
                        @click="close"
                        class="h-8 w-8 rounded-lg border border-slate-200 bg-white text-slate-400 hover:text-slate-700 hover:bg-slate-50 flex items-center justify-center transition-colors"
                        title="Tutup"
                    >
                        <XIcon class="size-4" />
                    </button>
                </div>

                <!-- Body -->
                <div class="flex-1 overflow-y-auto p-6 space-y-6">
                    <!-- Event & Status Banner -->
                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-between">
                        <div class="space-y-0.5">
                            <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">Kejadian Keamanan</span>
                            <p class="text-xs font-black text-slate-900 flex items-center gap-1.5">
                                <component :is="getEventIcon(log.event)" class="size-3.5 text-[#003628]" />
                                {{ log.event_label || log.event }}
                            </p>
                        </div>
                        <span
                            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest border"
                            :class="isSuccess ? 'bg-[#003628]/5 text-[#003628] border-[#003628]/10' : 'bg-red-50 text-red-600 border-red-100'"
                        >
                            <component :is="isSuccess ? CheckCircle : XCircle" class="size-3" />
                            {{ log.status_label || log.status }}
                        </span>
                    </div>

                    <!-- Meta Information Grid -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="p-3.5 rounded-xl border border-slate-100 bg-white shadow-xs space-y-1">
                            <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 flex items-center gap-1.5">
                                <CalendarIcon class="size-3 text-[#003628]" />
                                Waktu Kejadian
                            </span>
                            <p class="text-xs font-black text-slate-800 tabular-nums">
                                {{ log.created_at || '—' }}
                            </p>
                        </div>

                        <div class="p-3.5 rounded-xl border border-slate-100 bg-white shadow-xs space-y-1">
                            <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 flex items-center gap-1.5">
                                <Globe class="size-3 text-[#003628]" />
                                IP Address
                            </span>
                            <p class="text-xs font-black font-mono text-slate-800">
                                {{ log.ip_address || 'Internal' }}
                            </p>
                        </div>
                    </div>

                    <!-- User Identity Card -->
                    <div class="p-4 rounded-2xl border border-slate-100 bg-slate-50/50 space-y-3">
                        <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 flex items-center gap-1.5">
                            <UserIcon class="size-3 text-[#003628]" />
                            Identitas Akun Terkait
                        </span>

                        <div v-if="log.user" class="flex items-start gap-3 pt-1">
                            <div class="size-10 rounded-xl bg-[#003628]/10 text-[#003628] font-black text-sm flex items-center justify-center shrink-0 border border-[#003628]/20 overflow-hidden">
                                <img v-if="log.user.avatar" :src="log.user.avatar" :alt="log.user.name" class="size-full object-cover" />
                                <span v-else>{{ log.user.name.charAt(0).toUpperCase() }}</span>
                            </div>
                            <div class="min-w-0 flex-1 space-y-0.5">
                                <p class="text-xs font-black text-slate-900 truncate">{{ log.user.name }}</p>
                                <p class="text-[11px] font-medium text-slate-500 truncate">{{ log.user.email }}</p>
                                <div class="flex items-center gap-2 pt-1 text-[10px] text-slate-400 font-medium">
                                    <span v-if="log.user.department" class="px-2 py-0.5 rounded-md bg-white border border-slate-200">
                                        {{ log.user.department }}
                                    </span>
                                    <span v-if="log.user.company" class="px-2 py-0.5 rounded-md bg-white border border-slate-200">
                                        {{ log.user.company }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div v-else class="space-y-1 pt-1">
                            <p class="text-xs font-bold text-slate-700">Identifier Percobaan:</p>
                            <span class="inline-block font-mono text-xs font-bold text-[#003628] px-2.5 py-1 bg-white rounded-lg border border-slate-200">
                                {{ log.identifier || '—' }}
                            </span>
                            <p class="text-[10px] text-slate-400 italic pt-1">User tidak terdaftar dalam database lokal saat aksi berlangsung.</p>
                        </div>
                    </div>

                    <!-- Client & Device Info Card -->
                    <div class="p-4 rounded-2xl border border-slate-100 bg-white shadow-xs space-y-3">
                        <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 flex items-center gap-1.5">
                            <Laptop class="size-3 text-[#003628]" />
                            Klien &amp; Lingkungan Perangkat
                        </span>

                        <div class="grid grid-cols-2 gap-3 pt-1">
                            <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-100 space-y-0.5">
                                <span class="text-[9px] font-bold text-slate-400 uppercase">Browser / Aplikasi</span>
                                <p class="text-xs font-black text-slate-800">{{ parseBrowser(log.user_agent) }}</p>
                            </div>
                            <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-100 space-y-0.5">
                                <span class="text-[9px] font-bold text-slate-400 uppercase">Sistem Operasi</span>
                                <p class="text-xs font-black text-slate-800">{{ parseOs(log.user_agent) }}</p>
                            </div>
                        </div>

                        <div v-if="log.user_agent" class="pt-1">
                            <span class="text-[9px] font-bold text-slate-400 uppercase block mb-1">User Agent Lengkap</span>
                            <p class="text-[11px] font-mono text-slate-600 bg-slate-50 p-2.5 rounded-xl border border-slate-100 break-all leading-relaxed">
                                {{ log.user_agent }}
                            </p>
                        </div>
                    </div>

                    <!-- Additional Metadata -->
                    <div v-if="log.meta && Object.keys(log.meta).length > 0" class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 flex items-center gap-1.5">
                                <InfoIcon class="size-3 text-[#003628]" />
                                Informasi Meta Keamanan
                            </span>
                            <button
                                @click="showRawJson = !showRawJson"
                                class="text-[10px] font-bold text-[#003628] hover:underline flex items-center gap-1 cursor-pointer"
                            >
                                <CodeIcon class="size-3" />
                                {{ showRawJson ? 'Tampilan Ringkas' : 'Lihat JSON Mentah' }}
                            </button>
                        </div>

                        <!-- Formatted Key Value Pairs -->
                        <div v-if="!showRawJson" class="space-y-2">
                            <div
                                v-for="(val, key) in log.meta"
                                :key="key"
                                class="p-3 rounded-xl bg-slate-50 border border-slate-100 flex items-center justify-between text-xs"
                            >
                                <span class="font-bold text-slate-500 uppercase text-[10px] tracking-wider">{{ key }}</span>
                                <span class="font-mono font-bold text-slate-800 max-w-[65%] truncate text-right">
                                    {{ typeof val === 'object' ? JSON.stringify(val) : String(val) }}
                                </span>
                            </div>
                        </div>

                        <!-- Raw JSON view -->
                        <div v-else class="rounded-xl bg-slate-900 p-3.5 text-[11px] font-mono text-slate-100 overflow-x-auto">
                            <pre>{{ JSON.stringify(log.meta, null, 2) }}</pre>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="p-4 border-t border-slate-100 bg-slate-50/50 flex justify-end">
                    <button
                        @click="close"
                        class="h-9 px-4 rounded-xl border border-slate-200 bg-white text-xs font-bold text-slate-700 hover:bg-slate-50 hover:text-slate-900 transition-colors shadow-xs cursor-pointer"
                    >
                        Tutup Panel
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
