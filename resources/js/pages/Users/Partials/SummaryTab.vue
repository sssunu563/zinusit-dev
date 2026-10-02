<script setup lang="ts">
import {
    Users as UsersIcon,
    Link as LinkIcon,
    ShieldCheck,
    Building2,
    MapPin,
    Briefcase,
    UserCheck,
    ArrowUpRight,
    Clock,
    ChevronRight,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import axios from 'axios';

interface UserItem {
    id: number;
    name: string;
    company_name?: string | null;
    location_name?: string | null;
    department_name?: string | null;
    jobtitle?: string | null;
    snipeit_user_id?: number | null;
    created_at?: string | null;
}

interface LdapUser {
    username: string;
    name: string;
    email: string;
    company_name: string;
    location_name: string;
    department_name?: string;
    title?: string;
    manager?: string;
    created_at?: string;
    modified_at?: string;
    groups?: string[];
}

interface RecentUser {
    id?: number;
    name: string;
    source: 'snipeit' | 'ldap';
    company_name?: string | null;
    department_name?: string | null;
    username?: string;
    created_at?: string | null;
    raw?: UserItem | LdapUser;
}

interface Props {
    users: UserItem[];
    ldapUsers?: LdapUser[];
}

const props = withDefaults(defineProps<Props>(), {
    ldapUsers: () => [],
});

const emit = defineEmits<{
    (e: 'switch-tab', tab: 'snipeit' | 'ldap'): void;
}>();

// ── Top 3 Metrics ────────────────────────
const totalUsers  = computed(() => props.users.length + props.ldapUsers.length);
const linkedUsers = computed(() => props.users.filter(u => u.snipeit_user_id).length);
const ldapCount   = computed(() => props.ldapUsers.length);
const syncPct     = computed(() =>
    totalUsers.value ? Math.round((linkedUsers.value / totalUsers.value) * 1000) / 10 : 0,
);

// ── Entity Distributions with Data Quality Metrics ─
function buildEntityDist(users: UserItem[], getter: (u: UserItem) => string | null | undefined) {
    const map = new Map<string, number>();
    let assigned = 0;
    let unassigned = 0;

    users.forEach(u => {
        const raw = getter(u)?.trim();
        if (raw && raw !== '—' && raw !== '-' && raw.toLowerCase() !== 'null') {
            map.set(raw, (map.get(raw) || 0) + 1);
            assigned++;
        } else {
            unassigned++;
        }
    });

    const total = users.length;
    const items = [...map.entries()]
        .map(([name, count]) => ({
            name,
            count,
            pct: total ? Math.round((count / total) * 1000) / 10 : 0,
            assignedPct: assigned ? Math.round((count / assigned) * 1000) / 10 : 0,
        }))
        .sort((a, b) => b.count - a.count);

    return {
        items,
        totalEntities: items.length,
        assignedCount: assigned,
        unassignedCount: unassigned,
        assignedPct: total ? Math.round((assigned / total) * 1000) / 10 : 0,
        topEntity: items[0]?.name || '—',
    };
}

const companyData = computed(() => buildEntityDist(props.users, u => u.company_name));
const locationData = computed(() => buildEntityDist(props.users, u => u.location_name));
const departmentData = computed(() => buildEntityDist(props.users, u => u.department_name));

// ── Recent Users: merge Snipe-IT and LDAP ─────────────────────
const recentCreated = computed((): RecentUser[] => {
    const snipeitRecent = [...props.users]
        .filter(u => u.created_at)
        .sort((a, b) => new Date(b.created_at!).getTime() - new Date(a.created_at!).getTime())
        .slice(0, 10)
        .map(u => ({
            id: u.id,
            name: u.name,
            source: 'snipeit' as const,
            company_name: u.company_name,
            department_name: u.department_name,
            created_at: u.created_at,
            raw: u,
        }));

    const ldapRecent = [...(props.ldapUsers ?? [])]
        .filter(u => u.created_at)
        .sort((a, b) => new Date(b.created_at!).getTime() - new Date(a.created_at!).getTime())
        .slice(0, 5)
        .map(u => ({
            name: u.name || u.username,
            source: 'ldap' as const,
            company_name: u.company_name,
            department_name: u.department_name,
            username: u.username,
            created_at: u.created_at,
            raw: u,
        }));

    return [...snipeitRecent, ...ldapRecent]
        .sort((a, b) => new Date(b.created_at || 0).getTime() - new Date(a.created_at || 0).getTime())
        .slice(0, 15);
});

// ── Detail Modal ─────────────────────────
const detailModal = ref(false);
const detailLoading = ref(false);
const detailUser = ref<any>(null);
const detailSource = ref<'snipeit' | 'ldap' | null>(null);

async function openUserDetail(user: RecentUser) {
    detailModal.value = true;
    detailLoading.value = true;
    detailSource.value = user.source;
    detailUser.value = null;

    if (user.source === 'snipeit' && user.id) {
        try {
            const res = await axios.get(`/users/${user.id}/edit-data`);
            detailUser.value = res.data;
        } catch {
            detailUser.value = user.raw;
        }
    } else {
        // LDAP user — use the raw data directly
        detailUser.value = user.raw;
    }
    detailLoading.value = false;
}

function closeDetail() {
    detailModal.value = false;
    detailUser.value = null;
}

// ── Helpers ─────────────────────────────
function formatRel(d?: string | null): string {
    if (!d) return '—';
    try {
        const date = new Date(d);
        if (isNaN(date.getTime())) return d;
        const diff = Date.now() - date.getTime();
        const m = Math.floor(diff / 60000);
        if (m < 1) return 'Baru saja';
        if (m < 60) return `${m}m lalu`;
        const h = Math.floor(m / 60);
        if (h < 24) return `${h}j lalu`;
        const dy = Math.floor(h / 24);
        if (dy < 7) return `${dy}h lalu`;
        return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: '2-digit' });
    } catch { return d; }
}

function initials(name: string): string {
    const p = (name || '').trim().split(/\s+/);
    return p.length >= 2 ? (p[0][0] + p[1][0]).toUpperCase() : (name || 'U').slice(0, 2).toUpperCase();
}

const AVATAR_BG = ['bg-slate-100 text-slate-700', 'bg-[#003628]/10 text-[#003628]', 'bg-zinc-100 text-zinc-700'];
function avatarCls(name: string) {
    let h = 0;
    for (let i = 0; i < (name || '').length; i++) h = name.charCodeAt(i) + ((h << 5) - h);
    return AVATAR_BG[Math.abs(h) % AVATAR_BG.length];
}
</script>

<template>
    <div class="flex h-full min-h-0 flex-col gap-3.5">

        <!-- ══════════════════════════════
             1. TOP METRICS STRIP (Simetris 3 Card)
        ══════════════════════════════ -->
        <div class="grid flex-shrink-0 grid-cols-1 sm:grid-cols-3 gap-3.5">

            <!-- Total User -->
            <div
                class="group relative flex flex-col justify-between overflow-hidden rounded-xl border border-t-4 border-slate-200/90 bg-white p-4 shadow-2xs transition-all duration-200 hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-xs"
                style="border-top-color:#003628"
            >
                <div class="flex items-center justify-between">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#003628]/10 text-[#003628]">
                        <UsersIcon class="size-4" />
                    </div>
                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[9px] font-bold text-emerald-700">
                        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500" />
                        Aktif Terpusat
                    </span>
                </div>
                <div class="mt-3">
                    <div class="text-3xl font-black tracking-tight text-slate-900">{{ totalUsers }}</div>
                    <div class="text-[10px] font-bold tracking-wider text-slate-500 uppercase">Total User Terdaftar</div>
                    <div class="mt-0.5 text-[10px] font-medium text-slate-400">Database gabungan Snipe-IT & LDAP Active Directory</div>
                </div>
            </div>

            <!-- Snipe-IT Linked -->
            <div
                class="group relative flex cursor-pointer flex-col justify-between overflow-hidden rounded-xl border border-t-4 border-slate-200/90 bg-white p-4 shadow-2xs transition-all duration-200 hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-xs"
                style="border-top-color:#475569"
                @click="emit('switch-tab', 'snipeit')"
            >
                <div class="flex items-center justify-between">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-700">
                        <LinkIcon class="size-4" />
                    </div>
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-slate-50 text-slate-400 transition-colors group-hover:bg-[#003628]/10 group-hover:text-[#003628]">
                        <ArrowUpRight class="size-3.5" />
                    </span>
                </div>
                <div class="mt-3">
                    <div class="flex items-baseline gap-2">
                        <span class="text-3xl font-black tracking-tight text-slate-900">{{ linkedUsers }}</span>
                        <span class="text-xs font-bold text-slate-500">({{ syncPct }}%)</span>
                    </div>
                    <div class="text-[10px] font-bold tracking-wider text-slate-500 uppercase">Terhubung Snipe-IT</div>
                    <div class="mt-0.5 text-[10px] font-medium text-slate-400">Database Mirror Aset & Lisensi Hardware</div>
                </div>
            </div>

            <!-- LDAP Accounts -->
            <div
                class="group relative flex cursor-pointer flex-col justify-between overflow-hidden rounded-xl border border-t-4 border-slate-200/90 bg-white p-4 shadow-2xs transition-all duration-200 hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-xs"
                style="border-top-color:#64748b"
                @click="emit('switch-tab', 'ldap')"
            >
                <div class="flex items-center justify-between">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-700">
                        <ShieldCheck class="size-4" />
                    </div>
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-slate-50 text-slate-400 transition-colors group-hover:bg-[#003628]/10 group-hover:text-[#003628]">
                        <ArrowUpRight class="size-3.5" />
                    </span>
                </div>
                <div class="mt-3">
                    <div class="text-3xl font-black tracking-tight text-slate-900">{{ ldapCount }}</div>
                    <div class="text-[10px] font-bold tracking-wider text-slate-500 uppercase">LDAP Domain Controller</div>

                </div>
            </div>

        </div>

        <!-- ══════════════════════════════
             2. MAIN LOWER AREA: 3 CARDS KIRI + 1 CARD VERTICAL KANAN
        ══════════════════════════════ -->
        <div class="grid min-h-0 flex-1 grid-cols-1 gap-3.5 xl:grid-cols-12">

            <!-- SISI KIRI (3 Kolom Berdampingan: Perusahaan, Lokasi, Departemen) -->
            <div class="grid min-h-0 flex-1 grid-cols-1 md:grid-cols-3 gap-3.5 xl:col-span-8 2xl:col-span-9">

                <!-- ── Card 1: Perusahaan ── -->
                <div class="flex min-h-0 flex-col overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-2xs">
                    <!-- Head -->
                    <div class="flex flex-shrink-0 items-center justify-between border-b border-slate-100 bg-slate-50/40 px-3.5 py-3">
                        <div class="flex items-center gap-2">
                            <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-[#003628]/10 text-[#003628]">
                                <Building2 class="size-3.5" />
                            </div>
                            <h2 class="text-xs font-bold text-slate-800">Perusahaan</h2>
                        </div>
                        <span class="rounded-md border border-slate-200/80 bg-white px-1.5 py-0.5 text-[10px] font-bold text-slate-600 shadow-2xs">
                            {{ companyData.totalEntities }} PT
                        </span>
                    </div>

                    <!-- List (Scrollable) -->
                    <div class="min-h-0 flex-1 overflow-y-auto p-3 space-y-2.5 custom-scroll">
                        <div
                            v-for="(item, i) in companyData.items"
                            :key="item.name"
                            class="rounded-lg border border-slate-100 bg-slate-50/50 p-2.5 transition-colors hover:bg-slate-50"
                        >
                            <div class="flex items-center justify-between text-[11px]">
                                <div class="flex items-center gap-1.5 truncate">
                                    <span
                                        class="h-2 w-2 flex-shrink-0 rounded-full"
                                        :style="{ background: i === 0 ? '#003628' : '#64748b' }"
                                    />
                                    <span class="truncate font-semibold text-slate-800" :title="item.name">{{ item.name }}</span>
                                </div>
                                <span class="ml-1.5 flex-shrink-0 rounded bg-white px-1.5 py-0.5 text-[10px] font-bold text-slate-700 shadow-2xs border border-slate-100">
                                    {{ item.count }} user
                                </span>
                            </div>
                            <div class="mt-2 flex items-center gap-2">
                                <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-200/70">
                                    <div
                                        class="h-full rounded-full transition-all duration-500"
                                        :style="{
                                            width: `${item.assignedPct}%`,
                                            background: i === 0 ? '#003628' : '#64748b'
                                        }"
                                    />
                                </div>
                                <span class="text-[9px] font-medium text-slate-400 w-9 text-right">{{ item.assignedPct }}%</span>
                            </div>
                        </div>

                        <!-- Profiling Notice if unassigned -->
                        <div
                            v-if="companyData.unassignedCount > 0"
                            class="rounded-lg border border-dashed border-slate-200 bg-slate-50/40 p-2.5 text-[10px] text-slate-500"
                        >
                            <div class="flex items-center justify-between">
                                <span class="text-slate-400">Belum di-assign PT:</span>
                                <span class="font-bold text-slate-600">{{ companyData.unassignedCount }} user</span>
                            </div>
                        </div>
                    </div>

                    <!-- Foot -->
                    <div class="flex flex-shrink-0 items-center justify-between border-t border-slate-100 bg-slate-50/30 px-3.5 py-2">
                        <span class="text-[9px] text-slate-400">Terpetakan</span>
                        <span class="text-[9px] font-bold text-slate-700">{{ companyData.assignedCount }} dari {{ totalUsers }} user</span>
                    </div>
                </div>

                <!-- ── Card 2: Lokasi Site ── -->
                <div class="flex min-h-0 flex-col overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-2xs">
                    <!-- Head -->
                    <div class="flex flex-shrink-0 items-center justify-between border-b border-slate-100 bg-slate-50/40 px-3.5 py-3">
                        <div class="flex items-center gap-2">
                            <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-slate-100 text-slate-700">
                                <MapPin class="size-3.5" />
                            </div>
                            <h2 class="text-xs font-bold text-slate-800">Lokasi Site</h2>
                        </div>
                        <span class="rounded-md border border-slate-200/80 bg-white px-1.5 py-0.5 text-[10px] font-bold text-slate-600 shadow-2xs">
                            {{ locationData.totalEntities }} Site
                        </span>
                    </div>

                    <!-- List (Scrollable) -->
                    <div class="min-h-0 flex-1 overflow-y-auto p-3 space-y-2.5 custom-scroll">
                        <div
                            v-for="(item, i) in locationData.items"
                            :key="item.name"
                            class="rounded-lg border border-slate-100 bg-slate-50/50 p-2.5 transition-colors hover:bg-slate-50"
                        >
                            <div class="flex items-center justify-between text-[11px]">
                                <div class="flex items-center gap-1.5 truncate">
                                    <span
                                        class="h-2 w-2 flex-shrink-0 rounded-full"
                                        :style="{ background: i === 0 ? '#003628' : '#94a3b8' }"
                                    />
                                    <span class="truncate font-semibold text-slate-800" :title="item.name">{{ item.name }}</span>
                                </div>
                                <span class="ml-1.5 flex-shrink-0 rounded bg-white px-1.5 py-0.5 text-[10px] font-bold text-slate-700 shadow-2xs border border-slate-100">
                                    {{ item.count }} user
                                </span>
                            </div>
                            <div class="mt-2 flex items-center gap-2">
                                <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-200/70">
                                    <div
                                        class="h-full rounded-full transition-all duration-500"
                                        :style="{
                                            width: `${item.assignedPct}%`,
                                            background: i === 0 ? '#003628' : '#94a3b8'
                                        }"
                                    />
                                </div>
                                <span class="text-[9px] font-medium text-slate-400 w-9 text-right">{{ item.assignedPct }}%</span>
                            </div>
                        </div>

                        <!-- Profiling Notice if unassigned -->
                        <div
                            v-if="locationData.unassignedCount > 0"
                            class="rounded-lg border border-dashed border-slate-200 bg-slate-50/40 p-2.5 text-[10px] text-slate-500"
                        >
                            <div class="flex items-center justify-between">
                                <span class="text-slate-400">Belum di-assign Site:</span>
                                <span class="font-bold text-slate-600">{{ locationData.unassignedCount }} user</span>
                            </div>
                        </div>
                    </div>

                    <!-- Foot -->
                    <div class="flex flex-shrink-0 items-center justify-between border-t border-slate-100 bg-slate-50/30 px-3.5 py-2">
                        <span class="text-[9px] text-slate-400">Terpetakan</span>
                        <span class="text-[9px] font-bold text-slate-700">{{ locationData.assignedCount }} dari {{ totalUsers }} user</span>
                    </div>
                </div>

                <!-- ── Card 3: Departemen ── -->
                <div class="flex min-h-0 flex-col overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-2xs">
                    <!-- Head -->
                    <div class="flex flex-shrink-0 items-center justify-between border-b border-slate-100 bg-slate-50/40 px-3.5 py-3">
                        <div class="flex items-center gap-2">
                            <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-slate-100 text-slate-700">
                                <Briefcase class="size-3.5" />
                            </div>
                            <div>
                                <h2 class="text-xs font-bold text-slate-800">Departemen</h2>
                                <p class="text-[9px] text-slate-400">Divisi & unit kerja</p>
                            </div>
                        </div>
                        <span class="rounded-md border border-slate-200/80 bg-white px-1.5 py-0.5 text-[10px] font-bold text-slate-600 shadow-2xs">
                            {{ departmentData.totalEntities }} Unit
                        </span>
                    </div>

                    <!-- List (Scrollable) -->
                    <div class="min-h-0 flex-1 overflow-y-auto p-3 space-y-2.5 custom-scroll">
                        <div
                            v-for="(item, i) in departmentData.items"
                            :key="item.name"
                            class="rounded-lg border border-slate-100 bg-slate-50/50 p-2.5 transition-colors hover:bg-slate-50"
                        >
                            <div class="flex items-center justify-between text-[11px]">
                                <div class="flex items-center gap-1.5 truncate">
                                    <span class="flex h-4 w-4 items-center justify-center rounded bg-slate-200/80 text-[8px] font-black text-slate-700">
                                        {{ item.name.charAt(0).toUpperCase() }}
                                    </span>
                                    <span class="truncate font-semibold text-slate-800" :title="item.name">{{ item.name }}</span>
                                </div>
                                <span class="ml-1.5 flex-shrink-0 rounded bg-white px-1.5 py-0.5 text-[10px] font-bold text-slate-700 shadow-2xs border border-slate-100">
                                    {{ item.count }} user
                                </span>
                            </div>
                            <div class="mt-2 flex items-center gap-2">
                                <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-200/70">
                                    <div
                                        class="h-full rounded-full transition-all duration-500"
                                        :style="{
                                            width: `${item.assignedPct}%`,
                                            background: i === 0 ? '#003628' : '#cbd5e1'
                                        }"
                                    />
                                </div>
                                <span class="text-[9px] font-medium text-slate-400 w-9 text-right">{{ item.assignedPct }}%</span>
                            </div>
                        </div>

                        <!-- Profiling Notice if unassigned -->
                        <div
                            v-if="departmentData.unassignedCount > 0"
                            class="rounded-lg border border-dashed border-slate-200 bg-slate-50/40 p-2.5 text-[10px] text-slate-500"
                        >
                            <div class="flex items-center justify-between">
                                <span class="text-slate-400">Belum di-assign Dept:</span>
                                <span class="font-bold text-slate-600">{{ departmentData.unassignedCount }} user</span>
                            </div>
                        </div>
                    </div>

                    <!-- Foot -->
                    <div class="flex flex-shrink-0 items-center justify-between border-t border-slate-100 bg-slate-50/30 px-3.5 py-2">
                        <span class="text-[9px] text-slate-400">Terpetakan</span>
                        <span class="text-[9px] font-bold text-slate-700">{{ departmentData.assignedCount }} dari {{ totalUsers }} user</span>
                    </div>
                </div>

            </div>

            <!-- SISI KANAN (User Terbaru — Vertikal Penuh) -->
            <div class="flex min-h-0 flex-col overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-2xs xl:col-span-4 2xl:col-span-3">
                <!-- Feed Header -->
                <div class="flex flex-shrink-0 items-center justify-between border-b border-slate-100 bg-slate-50/40 px-3.5 py-3">
                    <div class="flex items-center gap-2">
                        <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-[#003628]/10 text-[#003628]">
                            <UserCheck class="size-3.5" />
                        </div>
                        <div>
                            <h2 class="text-xs font-bold text-slate-800">User Terbaru</h2>
                            <p class="text-[9px] text-slate-400">Registrasi & aktivitas terkini</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="inline-flex items-center gap-0.5 rounded-md border border-slate-200/80 bg-white px-2 py-1 text-[10px] font-bold text-slate-600 transition-colors hover:border-[#003628]/40 hover:text-[#003628]"
                        @click="emit('switch-tab', 'snipeit')"
                    >
                        <span>Semua</span>
                        <ChevronRight class="size-3" />
                    </button>
                </div>

                <!-- Feed Scrollable List -->
                <div class="min-h-0 flex-1 overflow-y-auto p-2.5 space-y-1.5 custom-scroll">
                    <div
                        v-for="(user, idx) in recentCreated"
                        :key="user.id ?? (user.username ?? idx)"
                        class="group flex cursor-pointer items-center gap-2.5 rounded-lg border border-slate-100 bg-white p-2 transition-all hover:border-slate-200 hover:bg-slate-50/60"
                        @click="openUserDetail(user)"
                    >
                        <!-- Avatar -->
                        <div
                            class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-lg text-[10px] font-black"
                            :class="avatarCls(user.name)"
                        >
                            {{ initials(user.name) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[11px] font-semibold text-slate-800 group-hover:text-slate-900">
                                {{ user.name }}
                            </p>
                            <p class="truncate text-[9px] text-slate-400">
                                {{ user.company_name || user.department_name || 'Staff' }}
                            </p>
                        </div>
                        <div class="flex flex-shrink-0 flex-col items-end gap-1">
                            <span
                                class="rounded px-1.5 py-0.5 text-[8px] font-bold uppercase"
                                :class="user.source === 'ldap' ? 'bg-slate-100 text-slate-500' : 'bg-[#003628]/10 text-[#003628]'"
                            >
                                {{ user.source === 'ldap' ? 'LDAP' : 'Snipe' }}
                            </span>
                            <div class="flex items-center gap-0.5 text-[8px] text-slate-400">
                                <Clock class="size-2" />
                                {{ formatRel(user.created_at) }}
                            </div>
                        </div>
                    </div>
                    <div v-if="!recentCreated.length" class="py-12 text-center text-[11px] italic text-slate-300">
                        Belum ada data user
                    </div>
                </div>

                <!-- Feed Footer -->
                <div class="flex flex-shrink-0 items-center justify-between border-t border-slate-100 bg-slate-50/40 px-3.5 py-2">
                    <span class="text-[9px] text-slate-400">Sinkronisasi Database</span>
                    <span class="inline-flex items-center gap-1 text-[9px] font-bold text-[#003628]">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse" />
                        Otomatis
                    </span>
                </div>
            </div>

        </div>

        <!-- ── Detail Modal ── -->
        <Teleport to="body">
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="detailModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 backdrop-blur-sm"
                @click.self="closeDetail"
            >
                <div class="relative w-full max-w-lg overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
                    <!-- Header -->
                    <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/60 px-5 py-3.5">
                        <div class="flex items-center gap-2.5">
                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#003628]/10 text-[#003628]">
                                <UserCheck class="size-4" />
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-slate-900">{{ detailUser?.name || detailUser?.username || '…' }}</h3>
                                <p class="text-[10px] text-slate-400">
                                    {{ detailSource === 'ldap' ? 'LDAP Active Directory' : 'Snipe-IT User' }}
                                </p>
                            </div>
                        </div>
                        <button @click="closeDetail" class="flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-400 transition hover:text-slate-700">
                            <X class="size-3.5" />
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="max-h-[70vh] overflow-y-auto p-5">
                        <!-- Loading -->
                        <div v-if="detailLoading" class="flex items-center justify-center py-12">
                            <div class="h-8 w-8 animate-spin rounded-full border-2 border-[#003628]/30 border-t-[#003628]" />
                        </div>

                        <!-- Snipe-IT User Detail -->
                        <template v-else-if="detailSource === 'snipeit' && detailUser">
                            <div class="grid grid-cols-2 gap-3">
                                <div v-if="detailUser.email" class="col-span-2 rounded-lg border border-slate-100 bg-slate-50/60 px-3.5 py-2.5">
                                    <p class="mb-0.5 text-[9px] font-bold uppercase tracking-widest text-slate-400">Email</p>
                                    <p class="text-[12px] font-semibold text-slate-800">{{ detailUser.email }}</p>
                                </div>
                                <div v-if="detailUser.username" class="rounded-lg border border-slate-100 bg-slate-50/60 px-3.5 py-2.5">
                                    <p class="mb-0.5 text-[9px] font-bold uppercase tracking-widest text-slate-400">Username</p>
                                    <p class="text-[12px] font-semibold text-slate-800">{{ detailUser.username }}</p>
                                </div>
                                <div v-if="detailUser.jobtitle" class="rounded-lg border border-slate-100 bg-slate-50/60 px-3.5 py-2.5">
                                    <p class="mb-0.5 text-[9px] font-bold uppercase tracking-widest text-slate-400">Jabatan</p>
                                    <p class="text-[12px] font-semibold text-slate-800">{{ detailUser.jobtitle }}</p>
                                </div>
                                <div v-if="detailUser.company_name" class="rounded-lg border border-slate-100 bg-slate-50/60 px-3.5 py-2.5">
                                    <p class="mb-0.5 text-[9px] font-bold uppercase tracking-widest text-slate-400">Perusahaan</p>
                                    <p class="text-[12px] font-semibold text-slate-800">{{ detailUser.company_name }}</p>
                                </div>
                                <div v-if="detailUser.department_name" class="rounded-lg border border-slate-100 bg-slate-50/60 px-3.5 py-2.5">
                                    <p class="mb-0.5 text-[9px] font-bold uppercase tracking-widest text-slate-400">Departemen</p>
                                    <p class="text-[12px] font-semibold text-slate-800">{{ detailUser.department_name }}</p>
                                </div>
                                <div v-if="detailUser.location_name" class="rounded-lg border border-slate-100 bg-slate-50/60 px-3.5 py-2.5">
                                    <p class="mb-0.5 text-[9px] font-bold uppercase tracking-widest text-slate-400">Lokasi</p>
                                    <p class="text-[12px] font-semibold text-slate-800">{{ detailUser.location_name }}</p>
                                </div>
                                <div v-if="detailUser.phone" class="rounded-lg border border-slate-100 bg-slate-50/60 px-3.5 py-2.5">
                                    <p class="mb-0.5 text-[9px] font-bold uppercase tracking-widest text-slate-400">Telepon</p>
                                    <p class="text-[12px] font-semibold text-slate-800">{{ detailUser.phone }}</p>
                                </div>
                                <div v-if="detailUser.manager_name" class="rounded-lg border border-slate-100 bg-slate-50/60 px-3.5 py-2.5">
                                    <p class="mb-0.5 text-[9px] font-bold uppercase tracking-widest text-slate-400">Manager</p>
                                    <p class="text-[12px] font-semibold text-slate-800">{{ detailUser.manager_name }}</p>
                                </div>
                            </div>
                        </template>

                        <!-- LDAP User Detail -->
                        <template v-else-if="detailSource === 'ldap' && detailUser">
                            <div class="grid grid-cols-2 gap-3">
                                <div v-if="detailUser.email" class="col-span-2 rounded-lg border border-slate-100 bg-slate-50/60 px-3.5 py-2.5">
                                    <p class="mb-0.5 text-[9px] font-bold uppercase tracking-widest text-slate-400">Email</p>
                                    <p class="text-[12px] font-semibold text-slate-800">{{ detailUser.email }}</p>
                                </div>
                                <div v-if="detailUser.username" class="rounded-lg border border-slate-100 bg-slate-50/60 px-3.5 py-2.5">
                                    <p class="mb-0.5 text-[9px] font-bold uppercase tracking-widest text-slate-400">Username</p>
                                    <p class="text-[12px] font-semibold text-slate-800">{{ detailUser.username }}</p>
                                </div>
                                <div v-if="detailUser.title" class="rounded-lg border border-slate-100 bg-slate-50/60 px-3.5 py-2.5">
                                    <p class="mb-0.5 text-[9px] font-bold uppercase tracking-widest text-slate-400">Jabatan</p>
                                    <p class="text-[12px] font-semibold text-slate-800">{{ detailUser.title }}</p>
                                </div>
                                <div v-if="detailUser.company_name" class="rounded-lg border border-slate-100 bg-slate-50/60 px-3.5 py-2.5">
                                    <p class="mb-0.5 text-[9px] font-bold uppercase tracking-widest text-slate-400">Perusahaan</p>
                                    <p class="text-[12px] font-semibold text-slate-800">{{ detailUser.company_name }}</p>
                                </div>
                                <div v-if="detailUser.department_name" class="rounded-lg border border-slate-100 bg-slate-50/60 px-3.5 py-2.5">
                                    <p class="mb-0.5 text-[9px] font-bold uppercase tracking-widest text-slate-400">Departemen</p>
                                    <p class="text-[12px] font-semibold text-slate-800">{{ detailUser.department_name }}</p>
                                </div>
                                <div v-if="detailUser.location_name" class="rounded-lg border border-slate-100 bg-slate-50/60 px-3.5 py-2.5">
                                    <p class="mb-0.5 text-[9px] font-bold uppercase tracking-widest text-slate-400">Lokasi</p>
                                    <p class="text-[12px] font-semibold text-slate-800">{{ detailUser.location_name }}</p>
                                </div>
                                <div v-if="detailUser.phone" class="rounded-lg border border-slate-100 bg-slate-50/60 px-3.5 py-2.5">
                                    <p class="mb-0.5 text-[9px] font-bold uppercase tracking-widest text-slate-400">Telepon</p>
                                    <p class="text-[12px] font-semibold text-slate-800">{{ detailUser.phone }}</p>
                                </div>
                                <div v-if="detailUser.manager" class="rounded-lg border border-slate-100 bg-slate-50/60 px-3.5 py-2.5">
                                    <p class="mb-0.5 text-[9px] font-bold uppercase tracking-widest text-slate-400">Manager</p>
                                    <p class="text-[12px] font-semibold text-slate-800">{{ detailUser.manager }}</p>
                                </div>
                                <div v-if="detailUser.groups?.length" class="col-span-2 rounded-lg border border-slate-100 bg-slate-50/60 px-3.5 py-2.5">
                                    <p class="mb-1 text-[9px] font-bold uppercase tracking-widest text-slate-400">Groups</p>
                                    <div class="flex flex-wrap gap-1">
                                        <span v-for="g in detailUser.groups" :key="g" class="rounded-md border border-slate-200 bg-white px-2 py-0.5 text-[10px] font-medium text-slate-600">
                                            {{ g }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Footer -->
                    <div class="flex items-center justify-end border-t border-slate-100 bg-slate-50/40 px-5 py-3">
                        <button
                            @click="closeDetail"
                            class="rounded-lg border border-slate-200 bg-white px-4 py-1.5 text-xs font-bold text-slate-600 transition hover:bg-slate-50"
                        >
                            Tutup
                        </button>
                    </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<style scoped>
.custom-scroll {
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 transparent;
}
.custom-scroll::-webkit-scrollbar {
    width: 4px;
}
.custom-scroll::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scroll::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 9999px;
}
.custom-scroll::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}
</style>
