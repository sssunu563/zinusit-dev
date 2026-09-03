<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { edit as editProfile } from '@/routes/profile';
import { edit as editPassword } from '@/routes/user-password';
import { User, Shield, ChevronRight, LogOut, Package, Info, Fingerprint } from 'lucide-vue-next';
import { computed } from 'vue';

interface NavItem {
    id: string;
    label: string;
    icon: any;
    title?: string;
}

const props = defineProps<{
    activeTab?: string;
    tabs?: NavItem[];
}>();

const emit = defineEmits(['update:activeTab']);

const page = usePage();
const user = computed(() => (page.props.auth as any).user);
const fullName = computed(() => user.value?.name || 'User');

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div class="flex w-full h-full overflow-hidden">
        <!-- Sidebar: Premium Slate Soft -->
        <aside class="w-[320px] border-r border-slate-100 flex flex-col shrink-0 bg-slate-50/50 backdrop-blur-xl h-full overflow-hidden">
            <!-- Profile Header Section (Fixed) -->
            <div class="p-10 pb-8 flex flex-col items-center border-b border-slate-100 bg-white/20 shrink-0">
                <div class="relative">
                    <div class="size-24 rounded-full border-4 border-white p-1 shadow-2xl overflow-hidden bg-white">
                        <img :src="user?.avatar || `https://ui-avatars.com/api/?name=${fullName}&background=f1f5f9&color=94a3b8`" class="size-full rounded-full object-cover" />
                    </div>
                    <div class="absolute -bottom-1 left-1/2 -translate-x-1/2 px-3 py-1 bg-[#003628] text-white text-[8px] font-black uppercase rounded-full shadow-lg border-2 border-white tracking-[0.2em]">Verified</div>
                </div>
                
                <div class="mt-6 text-center px-6">
                    <h2 class="text-base font-black text-slate-800 tracking-tight leading-tight">{{ fullName }}</h2>
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-1.5">{{ user?.username }}</p>
                </div>
            </div>

            <!-- Scrollable Navigation -->
            <nav class="flex-1 p-6 space-y-1.5 overflow-y-auto scrollbar-hide">
                <p class="px-5 text-[9px] font-black text-slate-400 uppercase tracking-[0.4em] mb-5 mt-2">Personal Settings</p>
                
                <div class="grid grid-cols-1 gap-2.5">
                    <button 
                        v-for="item in tabs" 
                        :key="item.id"
                        @click="emit('update:activeTab', item.id)"
                        :class="[
                            'relative flex items-center gap-4 px-5 py-4 rounded-[28px] border transition-all duration-500 group',
                            activeTab === item.id 
                                ? 'bg-white border-slate-200 shadow-[0_20px_40px_-12px_rgba(0,0,0,0.08)] text-[#003628]' 
                                : 'bg-transparent border-transparent text-slate-500 hover:bg-white hover:text-slate-800 hover:border-slate-100'
                        ]"
                    >
                        <div :class="[
                            'size-10 rounded-2xl flex items-center justify-center transition-all duration-500',
                            activeTab === item.id ? 'bg-[#003628] text-white shadow-xl shadow-[#003628]/30 rotate-6' : 'bg-slate-200/50 text-slate-400 group-hover:bg-slate-100 group-hover:text-slate-600'
                        ]">
                            <component :is="item.icon" class="size-4.5" />
                        </div>
                        <div class="flex-1 text-left">
                            <p class="text-[11px] font-black tracking-tight">{{ item.label }}</p>
                            <p class="text-[9px] font-bold opacity-40 mt-0.5 leading-none">{{ item.title || 'Manajemen Data' }}</p>
                        </div>
                    </button>
                </div>
            </nav>

            <!-- Footer Section: System Status (With Safe Zone) -->
            <div class="p-8 pb-12 border-t border-slate-100 bg-white/20 space-y-4 shrink-0">
                <div class="flex flex-col gap-2.5">
                    <div class="flex items-center gap-3 px-4 py-2.5 rounded-2xl bg-[#003628]/5 border border-[#003628]/10">
                        <div class="size-2 rounded-full bg-emerald-500 animate-pulse shadow-[0_0_8px_rgba(16,185,129,0.5)]"></div>
                        <span class="text-[9px] font-black text-[#003628] uppercase tracking-widest">LDAP Integrated</span>
                    </div>
                    <div class="flex items-center gap-3 px-4 py-2.5 rounded-2xl bg-blue-50 border border-blue-100">
                        <div class="size-2 rounded-full bg-blue-500 shadow-[0_0_8px_rgba(59,130,246,0.5)]"></div>
                        <span class="text-[9px] font-black text-blue-600 uppercase tracking-widest">Snipe-IT Synced</span>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Content Area: Refined Alignment -->
        <main class="flex-1 flex flex-col min-w-0 bg-white h-full overflow-hidden">
            <header class="h-24 border-b border-slate-50 px-12 flex items-center justify-between shrink-0 bg-white/50 backdrop-blur-sm">
                <div class="flex items-center gap-4">
                    <div class="h-8 w-1.5 rounded-full bg-slate-900/10" />
                    <h1 class="text-xs font-black text-slate-900 tracking-[0.3em] uppercase opacity-40 italic">{{ tabs?.find(t => t.id === activeTab)?.label || 'Settings' }}</h1>
                </div>
            </header>
            <div class="flex-1 p-12 lg:p-16 overflow-y-auto scrollbar-hide bg-white">
                <slot />
            </div>
        </main>
    </div>
</template>
