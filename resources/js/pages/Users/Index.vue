<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { Users, Database, LayoutDashboard, Shield } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import SummaryTab from './Partials/SummaryTab.vue';
import SnipeItUsersTab from './Partials/SnipeItUsersTab.vue';
import LdapUsersTab from './Partials/LdapUsersTab.vue';

interface UserItem {
    id: number;
    name: string;
    first_name?: string | null;
    last_name?: string | null;
    username?: string | null;
    email: string;
    phone?: string | null;
    jobtitle?: string | null;
    manager_id?: number | null;
    manager_name?: string | null;
    location_id?: number | null;
    location_name?: string | null;
    department_id?: number | null;
    department_name?: string | null;
    company_id?: number | null;
    company_name?: string | null;
    email_verified_at?: string | null;
    snipeit_user_id?: number | null;
    snipeit_username?: string | null;
    snipeit_synced_at?: string | null;
    created_at?: string | null;
}

interface LdapUser {
    username: string;
    name: string;
    first_name: string;
    last_name: string;
    email: string;
    phone: string;
    company_name: string;
    department_name: string;
    location_name: string;
    manager: string;
    title: string;
    uuid: string;
    created_at: string;
    modified_at: string;
    password_modified_at: string;
    groups: string[];
}

interface Props {
    users: UserItem[];
    ldapUsers?: LdapUser[];
    filterOptions?: {
        companies: Array<{ name: string; count: number }>;
        locations: Array<{ name: string; count: number }>;
        departments: Array<{ name: string; count: number }>;
    };
    options: {
        managers: any[];
        locations: any[];
        departments: any[];
        companies: any[];
    };
    status?: string;
}

const props = withDefaults(defineProps<Props>(), {
    ldapUsers: () => [],
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Pengguna', href: '/users' },
];

type TabKey = 'summary' | 'snipeit' | 'ldap';
const activeTab = ref<TabKey>('summary');

const tabs = computed(() => [
    {
        key: 'summary' as TabKey,
        label: 'Ringkasan',
        icon: LayoutDashboard,
        description: 'Statistik & Overview',
        badge: null,
    },
    {
        key: 'snipeit' as TabKey,
        label: 'Snipe-IT Users',
        icon: Users,
        description: 'Database Mirror Snipe-IT',
        badge: props.users.length,
    },
    {
        key: 'ldap' as TabKey,
        label: 'LDAP Users',
        icon: Database,
        description: 'Active Directory',
        badge: props.ldapUsers?.length ?? 0,
    },
]);

const currentTab = computed(() => tabs.value.find((t) => t.key === activeTab.value));
const setActiveTab = (key: TabKey) => { activeTab.value = key; };

const totalCount = computed(() => props.users.length + (props.ldapUsers?.length ?? 0));
</script>

<template>
    <Head title="Manajemen Pengguna" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <!-- Full-height, no outer scroll — unified pure white main card -->
        <div class="flex flex-col overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-xs" style="height: calc(100vh - 96px)">

            <!-- Status toast -->
            <div
                v-if="status"
                class="m-3 flex-shrink-0 rounded-xl border border-[#003628]/15 bg-[#003628]/5 px-4 py-2 text-center text-[10px] font-bold tracking-widest text-[#003628] uppercase"
            >
                {{ status }}
            </div>

            <!-- ╔═══════════════════════════════════════╗
                 HEADER — clean integrated white header
                 ╚═══════════════════════════════════════╝ -->
            <header class="flex flex-shrink-0 items-center justify-between gap-4 border-b border-slate-100 bg-white px-6 py-3.5">
                <!-- Left: identity -->
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-[#003628] border border-slate-200/60 shadow-2xs">
                        <Users class="size-4.5" />
                    </div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-[15px] font-black tracking-tight text-slate-900">
                            Pengguna Sistem
                        </h1>
                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700">
                            <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500" />
                            {{ totalCount }} akun aktif
                        </span>
                    </div>
                </div>

                <!-- Right: tab switcher (same style as dashboard) -->
                <div class="inline-flex items-center gap-1 rounded-xl border border-slate-200/60 bg-slate-100/90 p-1 shadow-2xs">
                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-[11px] font-bold transition-all duration-150"
                        :class="activeTab === tab.key
                            ? 'bg-white text-slate-900 shadow-xs'
                            : 'text-slate-500 hover:text-slate-800'"
                        @click="setActiveTab(tab.key)"
                    >
                        <component
                            :is="tab.icon"
                            class="size-3.5"
                            :class="activeTab === tab.key ? 'text-[#003628]' : 'text-slate-400'"
                        />
                        <span>{{ tab.label }}</span>
                        <span
                            v-if="tab.badge !== null"
                            class="rounded-md px-1.5 py-0.5 text-[9px] font-black"
                            :class="activeTab === tab.key
                                ? 'bg-[#003628]/10 text-[#003628]'
                                : 'bg-slate-200/80 text-slate-500'"
                        >
                            {{ tab.badge }}
                        </span>
                    </button>
                </div>
            </header>

            <!-- ╔═══════════════════════════════════════╗
                 CONTENT — pure white background, fills height
                 ╚═══════════════════════════════════════╝ -->
            <div class="flex min-h-0 flex-1 flex-col overflow-hidden bg-white p-4">
                <Transition
                    enter-active-class="transition duration-150 ease-out"
                    enter-from-class="opacity-0 translate-y-1"
                    enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="transition duration-100 ease-in"
                    leave-from-class="opacity-100"
                    leave-to-class="opacity-0"
                    mode="out-in"
                >
                    <SummaryTab
                        v-if="activeTab === 'summary'"
                        key="summary"
                        :users="users"
                        :ldap-users="ldapUsers"
                        @switch-tab="setActiveTab"
                    />
                    <SnipeItUsersTab
                        v-else-if="activeTab === 'snipeit'"
                        key="snipeit"
                        :users="users"
                        :filter-options="filterOptions"
                        :options="options"
                    />
                    <LdapUsersTab
                        v-else-if="activeTab === 'ldap'"
                        key="ldap"
                        :users="ldapUsers"
                    />
                </Transition>
            </div>

        </div>
    </AppLayout>
</template>
