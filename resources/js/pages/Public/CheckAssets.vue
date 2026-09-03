<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import {
    Search, Mail, Monitor, Tag, Hash, Info,
    Package, ShieldCheck, User, Loader2,
    MapPin, Building2, Briefcase, FlaskConical,
} from 'lucide-vue-next';
import { ref, computed } from 'vue';

interface Asset {
    name: string;
    tag: string;
    serial: string;
    type: string;
    category: string;
    status: string;
    location: string;
    model: string;
    image: string | null;
    checkout_at: string | null;
}

interface UserData {
    name: string;
    email: string;
    avatar: string | null;
    department: string | null;
    jobtitle: string | null;
    location: string | null;
}

const email    = ref('');
const loading  = ref(false);
const error    = ref('');
const userData = ref<UserData | null>(null);
const assets   = ref<Asset[]>([]);
const filter   = ref<string>('all');

const handleSearch = async () => {
    if (!email.value.trim()) return;
    loading.value  = true;
    error.value    = '';
    userData.value = null;
    assets.value   = [];
    filter.value   = 'all';
    try {
        const res  = await axios.post('/check-assets', { email: email.value.trim() });
        userData.value = res.data.user;
        assets.value   = res.data.assets;
    } catch (err: any) {
        error.value = err.response?.data?.message || 'Gagal mencari aset. Pastikan email Anda benar.';
    } finally {
        loading.value = false;
    }
};

// Type config — icon + colour per type
const typeConfig: Record<string, { color: string; bg: string; border: string; icon: any; label: string }> = {
    Hardware:   { color: 'text-[#003628]',  bg: 'bg-[#003628]/5',  border: 'border-[#003628]/10', icon: Monitor,      label: 'Hardware'   },
    License:    { color: 'text-sky-600',    bg: 'bg-sky-50',       border: 'border-sky-100',      icon: ShieldCheck,  label: 'License'    },
    Accessory:  { color: 'text-violet-600', bg: 'bg-violet-50',    border: 'border-violet-100',   icon: Package,      label: 'Accessory'  },
    Consumable: { color: 'text-amber-600',  bg: 'bg-amber-50',     border: 'border-amber-100',    icon: FlaskConical, label: 'Consumable' },
};

const getConf = (type: string) =>
    typeConfig[type] ?? { color: 'text-slate-500', bg: 'bg-slate-50', border: 'border-slate-100', icon: Tag, label: type };

// Tab definitions
const tabDefs = ['all', 'Hardware', 'License', 'Accessory', 'Consumable'] as const;

const counts = computed(() => {
    const c: Record<string, number> = { all: assets.value.length };
    for (const t of ['Hardware', 'License', 'Accessory', 'Consumable']) {
        c[t] = assets.value.filter(a => a.type === t).length;
    }
    return c;
});

// Only show tabs that have items (plus "all")
const visibleTabs = computed(() =>
    tabDefs.filter(t => t === 'all' || counts.value[t] > 0)
);

const filtered = computed(() =>
    filter.value === 'all' ? assets.value : assets.value.filter(a => a.type === filter.value)
);
</script>

<template>
    <Head title="Cek Aset Saya" />

    <div class="min-h-screen bg-slate-50 flex flex-col">

        <header class="bg-white border-b border-slate-100 sticky top-0 z-20">
            <div class="max-w-4xl mx-auto px-4 h-14 flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <img src="/form-logo.png" class="h-7 w-auto" alt="Logo"/>
                    <div class="h-4 w-px bg-slate-200"/>
                    <span class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">Portal Aset Karyawan</span>
                </div>
                <a href="/login" class="text-[10px] font-black uppercase tracking-widest text-slate-400 hover:text-[#003628] transition-colors">
                    Login →
                </a>
            </div>
        </header>

        <div class="bg-white border-b border-slate-100">
            <div class="max-w-4xl mx-auto px-4 py-8">
                <h1 class="text-xl font-black text-slate-900 tracking-tight mb-0.5">Cek Aset Saya</h1>
                <p class="text-[11px] font-bold text-slate-400 mb-5">Masukkan email terdaftar untuk melihat daftar perangkat yang Anda pegang.</p>

                <div class="flex items-center gap-2 max-w-lg">
                    <div class="relative flex-1">
                        <Mail class="absolute left-3.5 top-1/2 -translate-y-1/2 size-4 text-slate-300 pointer-events-none"/>
                        <input
                            v-model="email"
                            type="email"
                            placeholder="email@perusahaan.com"
                            class="w-full h-11 pl-10 pr-4 rounded-xl border border-slate-200 bg-white text-[13px] font-bold text-slate-900 placeholder:text-slate-300 focus:border-[#003628]/40 focus:ring-4 focus:ring-[#003628]/5 outline-none transition-all"
                            @keydown.enter="handleSearch"
                        />
                    </div>
                    <button
                        type="button"
                        :disabled="loading || !email.trim()"
                        class="h-11 px-5 rounded-xl bg-[#003628] text-white text-[10px] font-black uppercase tracking-widest flex items-center gap-2 hover:brightness-110 transition-all active:scale-95 disabled:opacity-40 shrink-0"
                        @click="handleSearch"
                    >
                        <Loader2 v-if="loading" class="size-3.5 animate-spin"/>
                        <Search v-else class="size-3.5"/>
                        Cari
                    </button>
                </div>

                <div v-if="error" class="mt-3 flex items-center gap-2 px-4 py-2.5 rounded-xl bg-rose-50 border border-rose-100 text-rose-600 text-[11px] font-bold max-w-lg">
                    <Info class="size-3.5 shrink-0"/>
                    {{ error }}
                </div>
            </div>
        </div>

        <div class="flex-1 max-w-4xl mx-auto w-full px-4 py-5 space-y-4">

            <!-- Loading skeleton -->
            <template v-if="loading">
                <div class="h-20 bg-white rounded-2xl border border-slate-100 animate-pulse"/>
                <div class="h-10 bg-white rounded-xl border border-slate-100 animate-pulse"/>
                <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden">
                    <div v-for="n in 4" :key="n" class="h-14 border-b border-slate-50 animate-pulse" :style="{ opacity: 1 - n * 0.18 }"/>
                </div>
            </template>

            <!-- Results -->
            <Transition enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0 translate-y-2" enter-to-class="opacity-100 translate-y-0">
                <div v-if="userData && !loading" class="space-y-4">

                    <div class="bg-white rounded-2xl border border-slate-200 px-5 py-4 flex items-center gap-4">
                        <div class="shrink-0">
                            <img v-if="userData.avatar" :src="userData.avatar"
                                class="h-11 w-11 rounded-xl object-cover border border-slate-100"/>
                            <div v-else class="h-11 w-11 rounded-xl bg-[#003628]/5 flex items-center justify-center">
                                <User class="size-5 text-[#003628]"/>
                            </div>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[13px] font-black text-slate-900 truncate">{{ userData.name }}</p>
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-0.5 mt-0.5">
                                <span class="flex items-center gap-1 text-[10px] font-bold text-slate-400">
                                    <Mail class="size-3"/> {{ userData.email }}
                                </span>
                                <span v-if="userData.department" class="flex items-center gap-1 text-[10px] font-bold text-slate-400">
                                    <Building2 class="size-3"/> {{ userData.department }}
                                </span>
                                <span v-if="userData.jobtitle" class="flex items-center gap-1 text-[10px] font-bold text-slate-400">
                                    <Briefcase class="size-3"/> {{ userData.jobtitle }}
                                </span>
                                <span v-if="userData.location" class="flex items-center gap-1 text-[10px] font-bold text-slate-400">
                                    <MapPin class="size-3"/> {{ userData.location }}
                                </span>
                            </div>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="text-2xl font-black text-slate-900 tabular-nums">{{ assets.length }}</p>
                            <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Total Aset</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-1.5 flex-wrap">
                        <button
                            v-for="tab in visibleTabs"
                            :key="tab"
                            type="button"
                            class="h-8 px-3.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all flex items-center gap-1.5"
                            :class="filter === tab
                                ? 'bg-[#003628] text-white shadow-sm'
                                : 'bg-white border border-slate-200 text-slate-500 hover:border-slate-300 hover:text-slate-700'"
                            @click="filter = tab"
                        >
                            <component
                                v-if="tab !== 'all'"
                                :is="getConf(tab).icon"
                                class="size-3"
                                :class="filter === tab ? 'text-white' : getConf(tab).color"
                            />
                            {{ tab === 'all' ? 'Semua' : tab }}
                            <span class="tabular-nums opacity-70">{{ counts[tab] }}</span>
                        </button>
                    </div>

                    <div v-if="filtered.length" class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                        <!-- Header -->
                        <div class="hidden md:grid grid-cols-[2fr_1fr_1fr_1fr_1fr] px-5 py-3 bg-slate-50/80 border-b border-slate-100">
                            <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">Nama Aset</span>
                            <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">Tipe</span>
                            <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">Asset Tag</span>
                            <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">Serial</span>
                            <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">Lokasi</span>
                        </div>

                        <!-- Rows -->
                        <div class="divide-y divide-slate-50">
                            <div
                                v-for="(asset, i) in filtered"
                                :key="i"
                                class="grid grid-cols-1 md:grid-cols-[2fr_1fr_1fr_1fr_1fr] gap-2 md:gap-0 px-5 py-3.5 hover:bg-slate-50/50 transition-colors"
                            >
                                <!-- Name -->
                                <div class="flex items-center gap-3 min-w-0 md:pr-4">
                                    <div class="shrink-0 h-8 w-8 rounded-xl overflow-hidden border border-slate-100 bg-slate-50 flex items-center justify-center">
                                        <img v-if="asset.image" :src="asset.image" class="h-full w-full object-contain"/>
                                        <component v-else :is="getConf(asset.type).icon" class="size-3.5" :class="getConf(asset.type).color"/>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-[12px] font-bold text-slate-800 truncate">{{ asset.name }}</p>
                                        <p class="text-[9px] font-bold text-slate-400 truncate md:hidden">{{ asset.category }}</p>
                                    </div>
                                </div>

                                <!-- Type badge -->
                                <div class="flex items-center">
                                    <span
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-widest border"
                                        :class="[getConf(asset.type).bg, getConf(asset.type).color, getConf(asset.type).border]"
                                    >
                                        <component :is="getConf(asset.type).icon" class="size-2.5"/>
                                        {{ asset.type }}
                                    </span>
                                </div>

                                <!-- Tag -->
                                <div class="flex items-center">
                                    <span v-if="asset.tag && asset.tag !== '-'"
                                        class="text-[10px] font-mono font-bold text-slate-600 bg-slate-50 px-2 py-0.5 rounded-lg border border-slate-100">
                                        {{ asset.tag }}
                                    </span>
                                    <span v-else class="text-slate-300 text-sm font-bold">—</span>
                                </div>

                                <!-- Serial -->
                                <div class="flex items-center">
                                    <span v-if="asset.serial && asset.serial !== '-'"
                                        class="text-[10px] font-mono text-slate-500 truncate max-w-[110px]">
                                        {{ asset.serial }}
                                    </span>
                                    <span v-else class="text-slate-300 text-sm font-bold">—</span>
                                </div>

                                <!-- Location -->
                                <div class="flex items-center">
                                    <span v-if="asset.location && asset.location !== '-'"
                                        class="flex items-center gap-1 text-[10px] font-bold text-slate-500 truncate">
                                        <MapPin class="size-3 shrink-0 text-slate-300"/>
                                        {{ asset.location }}
                                    </span>
                                    <span v-else class="text-slate-300 text-sm font-bold">—</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Empty filtered state -->
                    <div v-else class="bg-white rounded-2xl border border-dashed border-slate-200 py-14 text-center">
                        <Package class="size-7 text-slate-200 mx-auto mb-2"/>
                        <p class="text-[11px] font-black text-slate-400 uppercase tracking-widest">Tidak ada aset</p>
                    </div>

                </div>
            </Transition>

            <!-- Initial empty state -->
            <div v-if="!userData && !loading" class="flex flex-col items-center justify-center py-20 text-center">
                <div class="h-14 w-14 rounded-2xl bg-white border border-slate-200 flex items-center justify-center mb-4 shadow-sm">
                    <Search class="size-6 text-slate-200"/>
                </div>
                <p class="text-[11px] font-black text-slate-400 uppercase tracking-widest">Masukkan email untuk mulai</p>
                <p class="text-[10px] text-slate-300 mt-1 font-medium">Data diambil langsung dari sistem manajemen aset</p>
            </div>
        </div>

        <!-- Footer -->
        <footer class="border-t border-slate-100 py-5 text-center">
            <p class="text-[10px] font-bold text-slate-300 uppercase tracking-widest">
                © {{ new Date().getFullYear() }} Zinus IT Asset Management
            </p>
        </footer>
    </div>
</template>
