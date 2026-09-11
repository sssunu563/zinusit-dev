<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import { Html5Qrcode } from 'html5-qrcode';
import {
    AlertCircle,
    ArrowRight,
    BookOpen,
    Briefcase,
    Building2,
    Camera,
    Check,
    ChevronRight,
    Clock,
    ExternalLink,
    FileText,
    FlaskConical,
    Hash,
    HelpCircle,
    Loader2,
    Mail,
    MapPin,
    Monitor,
    Package,
    Phone,
    QrCode,
    RefreshCw,
    Search,
    ShieldCheck,
    Sparkles,
    Tag,
    User,
    Wrench,
    X,
    XCircle,
    Zap,
} from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import AssetDetailCard, { type AssetDetail } from './Partials/AssetDetailCard.vue';

interface UserAsset {
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

const props = defineProps<{
    initialTag?: string | null;
    initialAsset?: AssetDetail | null;
    initialError?: string | null;
}>();

const searchMode = ref<'qr' | 'email'>('qr');

// Unit Asset Search state
const assetTagInput = ref(props.initialTag || '');
const currentAsset = ref<AssetDetail | null>(props.initialAsset || null);
const assetLoading = ref(false);
const assetError = ref(props.initialError || '');

// Email Search state
const email = ref('');
const emailLoading = ref(false);
const emailError = ref('');
const userData = ref<UserData | null>(null);
const userAssets = ref<UserAsset[]>([]);
const emailFilter = ref<string>('all');

// Camera state
const isCameraOpen = ref(false);
const html5QrCode = ref<Html5Qrcode | null>(null);
const cameraError = ref('');
const isCameraStarting = ref(false);
const hasTorch = ref(false);
const isTorchOn = ref(false);

const playBeep = () => {
    try {
        const AudioCtx = window.AudioContext || (window as any).webkitAudioContext;
        if (!AudioCtx) return;
        const ctx = new AudioCtx();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(880, ctx.currentTime);
        gain.gain.setValueAtTime(0.2, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.15);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start();
        osc.stop(ctx.currentTime + 0.15);
    } catch {}
    if (typeof navigator !== 'undefined' && 'vibrate' in navigator) {
        navigator.vibrate(80);
    }
};

const startCamera = async () => {
    isCameraOpen.value = true;
    cameraError.value = '';
    isCameraStarting.value = true;
    await nextTick();
    try {
        if (!html5QrCode.value) {
            html5QrCode.value = new Html5Qrcode('public-qr-reader');
        }
        if (html5QrCode.value.isScanning) {
            await html5QrCode.value.stop();
        }
        await html5QrCode.value.start(
            { facingMode: 'environment' },
            {
                fps: 15,
                qrbox: (viewfinderWidth, viewfinderHeight) => {
                    const minDim = Math.min(viewfinderWidth, viewfinderHeight);
                    const size = Math.floor(minDim * 0.75);
                    return { width: size, height: size };
                },
                aspectRatio: 1.0,
            },
            (decodedText) => { handleQrScanResult(decodedText); },
            () => {},
        );
        try {
            const track = (html5QrCode.value as any)?.getRunningTrackCameraCapabilities?.();
            hasTorch.value = Boolean(track?.torchFeature?.()?.isSupported?.());
        } catch {
            hasTorch.value = false;
        }
    } catch {
        cameraError.value = 'Tidak dapat mengakses kamera. Pastikan izin kamera telah diizinkan di browser Anda.';
    } finally {
        isCameraStarting.value = false;
    }
};

const stopCamera = async () => {
    if (html5QrCode.value && html5QrCode.value.isScanning) {
        try { await html5QrCode.value.stop(); } catch {}
    }
    isCameraOpen.value = false;
    isTorchOn.value = false;
};

const toggleTorch = async () => {
    if (!html5QrCode.value || !hasTorch.value) return;
    try {
        isTorchOn.value = !isTorchOn.value;
        await (html5QrCode.value as any)?.applyVideoConstraints({
            advanced: [{ torch: isTorchOn.value }],
        });
    } catch {}
};

// Asset lookup function
const lookupAssetData = async (query: string) => {
    const trimmed = query.trim();
    if (!trimmed) return;
    assetLoading.value = true;
    assetError.value = '';

    // Update browser URL query so it can be copied / refreshed
    const newUrl = `/check-assets?tag=${encodeURIComponent(trimmed)}`;
    window.history.replaceState({}, '', newUrl);

    try {
        const res = await axios.get('/check-assets/lookup', {
            params: { tag: trimmed },
        });
        currentAsset.value = res.data.asset;
        assetTagInput.value = trimmed;
    } catch (err: any) {
        currentAsset.value = null;
        assetError.value = err.response?.data?.message || `Aset '${trimmed}' tidak ditemukan.`;
    } finally {
        assetLoading.value = false;
    }
};

const handleQrScanResult = (scannedText: string) => {
    playBeep();
    stopCamera();
    const raw = scannedText.trim();
    const urlMatch = raw.match(/\/a\/([^/?#\s]+)/) || raw.match(/[?&]tag=([^&#\s]+)/);
    const targetRef = urlMatch ? decodeURIComponent(urlMatch[1]) : raw;
    if (targetRef) {
        assetTagInput.value = targetRef;
        lookupAssetData(targetRef);
    }
};

const handleManualTagSearch = () => {
    const raw = assetTagInput.value.trim();
    if (!raw) return;
    const urlMatch = raw.match(/\/a\/([^/?#\s]+)/) || raw.match(/[?&]tag=([^&#\s]+)/);
    const targetRef = urlMatch ? decodeURIComponent(urlMatch[1]) : raw;
    assetTagInput.value = targetRef;
    lookupAssetData(targetRef);
};

const clearAssetSearch = () => {
    assetTagInput.value = '';
    currentAsset.value = null;
    assetError.value = '';
    window.history.replaceState({}, '', '/check-assets');
};

// Switch to email search tab and autofill email
const onViewUserAssets = (userEmail: string) => {
    searchMode.value = 'email';
    email.value = userEmail;
    handleEmailSearch();
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

// Switch from email table row to asset view
const viewAssetDetail = (tag: string) => {
    if (!tag || tag === '-') return;
    searchMode.value = 'qr';
    assetTagInput.value = tag;
    lookupAssetData(tag);
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

// Email search handler
const handleEmailSearch = async () => {
    if (!email.value.trim()) return;
    emailLoading.value = true;
    emailError.value = '';
    userData.value = null;
    userAssets.value = [];
    emailFilter.value = 'all';
    try {
        const res = await axios.post('/check-assets', { email: email.value.trim() });
        userData.value = res.data.user;
        userAssets.value = res.data.assets;
    } catch (err: any) {
        emailError.value = err.response?.data?.message || 'Gagal mencari aset. Pastikan email Anda benar.';
    } finally {
        emailLoading.value = false;
    }
};

onUnmounted(() => {
    stopCamera();
});

const typeConfig: Record<string, { color: string; bg: string; icon: any; label: string }> = {
    Hardware: { color: 'text-emerald-700', bg: 'bg-emerald-50', icon: Monitor, label: 'Hardware' },
    License: { color: 'text-sky-600', bg: 'bg-sky-50', icon: ShieldCheck, label: 'License' },
    Accessory: { color: 'text-violet-600', bg: 'bg-violet-50', icon: Package, label: 'Accessory' },
    Consumable: { color: 'text-amber-600', bg: 'bg-amber-50', icon: FlaskConical, label: 'Consumable' },
};

const getConf = (type: string) =>
    typeConfig[type] ?? { color: 'text-slate-500', bg: 'bg-slate-50', icon: Tag, label: type };

const tabDefs = ['all', 'Hardware', 'License', 'Accessory', 'Consumable'] as const;

const counts = computed(() => {
    const c: Record<string, number> = { all: userAssets.value.length };
    for (const t of ['Hardware', 'License', 'Accessory', 'Consumable']) {
        c[t] = userAssets.value.filter((a) => a.type === t).length;
    }
    return c;
});

const visibleTabs = computed(() =>
    tabDefs.filter((t) => t === 'all' || counts.value[t] > 0),
);

const filteredUserAssets = computed(() =>
    emailFilter.value === 'all'
        ? userAssets.value
        : userAssets.value.filter((a) => a.type === emailFilter.value),
);
</script>

<template>
    <Head :title="currentAsset ? `Aset: ${currentAsset.asset_tag} - ${currentAsset.name}` : 'Cek Aset Saya - Zinus IT'" />

    <div class="min-h-screen bg-[#F8FAFC] flex flex-col antialiased text-slate-900 selection:bg-[#003628]/20 selection:text-[#003628]">
        <!-- Header -->
        <header class="bg-white/90 backdrop-blur-md border-b border-slate-200/80 sticky top-0 z-40 shadow-xs">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 h-14 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <img src="/form-logo.png" class="h-6 w-auto object-contain" alt="Zinus IT" />
                    <span class="text-xs text-slate-200">|</span>
                    <span class="text-xs font-bold text-slate-600 tracking-tight">Portal Aset Mandiri</span>
                </div>
            </div>
        </header>

        <!-- Main Content Container -->
        <main class="max-w-5xl mx-auto w-full px-4 sm:px-6 lg:px-8 pt-6 sm:pt-8 pb-16 flex-1 space-y-6">
            <!-- Title Header -->
            <div>
                <div class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-[10px] font-black uppercase tracking-wider mb-2">
                    <ShieldCheck class="size-3" />
                    Layanan Mandiri
                </div>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Cek Aset Saya</h1>
                <p class="text-xs sm:text-sm text-slate-500 mt-1">
                    Pindai QR Code, masukkan nomor tag / serial, atau cari seluruh aset berdasarkan email karyawan.
                </p>
            </div>

            <!-- Mode Switcher Tabs -->
            <div class="flex gap-2 p-1.5 rounded-2xl bg-slate-200/60 mb-6">
                <button
                    type="button"
                    :class="[
                        'flex-1 flex items-center justify-center gap-2 py-2.5 px-3 rounded-xl text-xs font-bold transition-all cursor-pointer',
                        searchMode === 'qr'
                            ? 'bg-white text-slate-900 shadow-sm'
                            : 'text-slate-600 hover:text-slate-900 hover:bg-white/50',
                    ]"
                    @click="searchMode = 'qr'"
                >
                    <QrCode class="size-4 text-[#003628]" />
                    <span>Cari Unit Aset (QR / Tag)</span>
                </button>
                <button
                    type="button"
                    :class="[
                        'flex-1 flex items-center justify-center gap-2 py-2.5 px-3 rounded-xl text-xs font-bold transition-all cursor-pointer',
                        searchMode === 'email'
                            ? 'bg-white text-slate-900 shadow-sm'
                            : 'text-slate-600 hover:text-slate-900 hover:bg-white/50',
                    ]"
                    @click="searchMode = 'email'"
                >
                    <Mail class="size-4 text-emerald-600" />
                    <span>Cari via Email Karyawan</span>
                </button>
            </div>

            <!-- TAB 1: Single Asset (QR / Tag / SN) -->
            <div v-if="searchMode === 'qr'" class="space-y-5">
                <!-- Search Box Card -->
                <div class="bg-white rounded-3xl border border-slate-200/80 p-4 shadow-sm space-y-3">
                    <div class="flex gap-2">
                        <div class="relative flex-1">
                            <Hash class="absolute left-3.5 top-1/2 -translate-y-1/2 size-4 text-slate-400 pointer-events-none" />
                            <input
                                v-model="assetTagInput"
                                type="text"
                                placeholder="Nomor tag atau serial (contoh: ZGI-NB-001)"
                                class="w-full h-11 pl-10 pr-9 rounded-2xl border border-slate-200 bg-slate-50/50 text-xs font-medium text-slate-800 placeholder:text-slate-400 focus:border-[#003628] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#003628]/10 transition-all uppercase"
                                @keydown.enter="handleManualTagSearch"
                            />
                            <button
                                v-if="assetTagInput"
                                type="button"
                                class="absolute right-3 top-1/2 -translate-y-1/2 p-1 text-slate-400 hover:text-slate-600 transition-colors"
                                @click="clearAssetSearch"
                            >
                                <X class="size-3.5" />
                            </button>
                        </div>
                        <button
                            type="button"
                            :disabled="assetLoading || !assetTagInput.trim()"
                            class="h-11 px-5 rounded-2xl bg-[#003628] hover:bg-[#004d39] text-white text-xs font-black uppercase tracking-wider flex items-center gap-1.5 shadow-md shadow-[#003628]/15 disabled:opacity-40 transition-all cursor-pointer active:scale-95"
                            @click="handleManualTagSearch"
                        >
                            <Loader2 v-if="assetLoading" class="size-4 animate-spin" />
                            <span v-else>Cari</span>
                        </button>
                    </div>

                    <!-- Open Camera Banner -->
                    <button
                        type="button"
                        class="w-full flex items-center justify-center gap-3 py-3 px-4 rounded-2xl border border-dashed border-emerald-300 bg-emerald-50/50 hover:bg-emerald-50 text-emerald-800 text-xs font-bold transition-all cursor-pointer group active:scale-98"
                        @click="startCamera"
                    >
                        <div class="size-8 rounded-xl bg-white border border-emerald-200 flex items-center justify-center shadow-2xs group-hover:scale-105 transition-transform">
                            <Camera class="size-4 text-emerald-700" />
                        </div>
                        <div class="text-left">
                            <span class="block leading-tight">Pindai dengan Kamera</span>
                            <span class="text-[10px] text-emerald-600 font-normal">Arahkan kamera ke label stiker QR aset</span>
                        </div>
                    </button>
                </div>

                <!-- Error Notice -->
                <div
                    v-if="assetError"
                    class="flex items-start gap-3 p-4 rounded-2xl bg-red-50 border border-red-200/80 text-red-700 text-xs animate-in fade-in"
                >
                    <AlertCircle class="size-4 shrink-0 mt-0.5 text-red-500" />
                    <div class="flex-1 min-w-0">
                        <p class="font-bold">Aset Tidak Ditemukan</p>
                        <p class="mt-0.5 text-red-600">{{ assetError }}</p>
                        <p class="text-[11px] text-red-500 mt-1">Pastikan nomor tag atau serial yang Anda masukkan sudah terdaftar di sistem inventaris.</p>
                    </div>
                </div>

                <!-- Asset Loading Skeleton -->
                <div v-if="assetLoading" class="space-y-4 animate-pulse">
                    <div class="h-12 bg-white rounded-2xl border border-slate-200/60" />
                    <div class="h-64 bg-white rounded-3xl border border-slate-200/60" />
                    <div class="h-32 bg-white rounded-3xl border border-slate-200/60" />
                </div>

                <!-- Asset Detail Card (When Found) -->
                <AssetDetailCard
                    v-else-if="currentAsset"
                    :asset="currentAsset"
                    @view-user-assets="onViewUserAssets"
                />

                <!-- Empty State Helper (When no search performed yet) -->
                <div
                    v-else-if="!assetError"
                    class="bg-white rounded-3xl border border-slate-200/80 p-8 text-center space-y-3"
                >
                    <div class="size-14 rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-center mx-auto text-slate-400">
                        <QrCode class="size-7" />
                    </div>
                    <h3 class="text-sm font-black uppercase tracking-tight text-slate-800">Mulai Pengecekan Aset</h3>
                    <p class="text-xs text-slate-400 max-w-sm mx-auto leading-relaxed">
                        Masukkan kode stiker aset (contoh: <span class="font-mono font-bold text-slate-600">ZGI-NB-001</span>) atau klik tombol kamera untuk memindai QR fisik perangkat.
                    </p>
                </div>
            </div>

            <!-- TAB 2: Search via Email -->
            <div v-else class="space-y-5">
                <!-- Search Box Card -->
                <div class="bg-white rounded-3xl border border-slate-200/80 p-4 shadow-sm space-y-3">
                    <div class="flex gap-2">
                        <div class="relative flex-1">
                            <Mail class="absolute left-3.5 top-1/2 -translate-y-1/2 size-4 text-slate-400 pointer-events-none" />
                            <input
                                v-model="email"
                                type="email"
                                placeholder="Email karyawan (nama@zinus.com)"
                                class="w-full h-11 pl-10 pr-4 rounded-2xl border border-slate-200 bg-slate-50/50 text-xs font-medium text-slate-800 placeholder:text-slate-400 focus:border-[#003628] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#003628]/10 transition-all"
                                @keydown.enter="handleEmailSearch"
                            />
                        </div>
                        <button
                            type="button"
                            :disabled="emailLoading || !email.trim()"
                            class="h-11 px-5 rounded-2xl bg-[#003628] hover:bg-[#004d39] text-white text-xs font-black uppercase tracking-wider flex items-center gap-1.5 shadow-md shadow-[#003628]/15 disabled:opacity-40 transition-all cursor-pointer active:scale-95"
                            @click="handleEmailSearch"
                        >
                            <Loader2 v-if="emailLoading" class="size-4 animate-spin" />
                            <span v-else>Cari</span>
                        </button>
                    </div>
                </div>

                <!-- Error Notice -->
                <div
                    v-if="emailError"
                    class="flex items-center gap-2.5 p-4 rounded-2xl bg-red-50 border border-red-200/80 text-red-700 text-xs"
                >
                    <AlertCircle class="size-4 shrink-0 text-red-500" />
                    <span>{{ emailError }}</span>
                </div>

                <!-- Email Loading Skeleton -->
                <div v-if="emailLoading" class="space-y-4 animate-pulse">
                    <div class="h-20 bg-white rounded-2xl border border-slate-200/60" />
                    <div class="h-8 bg-white rounded-xl border border-slate-200/60 w-1/2" />
                    <div class="h-48 bg-white rounded-2xl border border-slate-200/60" />
                </div>

                <!-- User Profile and Asset List -->
                <div v-if="userData && !emailLoading" class="space-y-4">
                    <!-- User Profile Card -->
                    <div class="bg-white rounded-3xl border border-slate-200/80 p-5 flex items-center gap-4 shadow-sm">
                        <div class="shrink-0">
                            <img
                                v-if="userData.avatar"
                                :src="userData.avatar"
                                class="h-12 w-12 rounded-2xl object-cover border border-slate-200"
                                alt="Avatar"
                            />
                            <div v-else class="h-12 w-12 rounded-2xl bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-500 font-black text-lg">
                                {{ userData.name ? userData.name.charAt(0).toUpperCase() : 'U' }}
                            </div>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-sm font-black text-slate-900 truncate uppercase">{{ userData.name }}</h3>
                            <div class="flex flex-wrap gap-x-3 gap-y-1 mt-1">
                                <span class="flex items-center gap-1 text-xs text-slate-500">
                                    <Mail class="size-3 text-slate-400" /> {{ userData.email }}
                                </span>
                                <span v-if="userData.department" class="flex items-center gap-1 text-xs text-slate-500">
                                    <Building2 class="size-3 text-slate-400" /> {{ userData.department }}
                                </span>
                                <span v-if="userData.jobtitle" class="flex items-center gap-1 text-xs text-slate-500">
                                    <Briefcase class="size-3 text-slate-400" /> {{ userData.jobtitle }}
                                </span>
                            </div>
                        </div>
                        <div class="shrink-0 text-right pl-2 border-l border-slate-100">
                            <p class="text-xl font-black text-[#003628]">{{ userAssets.length }}</p>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Aset</p>
                        </div>
                    </div>

                    <!-- Filter Tabs -->
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <button
                            v-for="tab in visibleTabs"
                            :key="tab"
                            type="button"
                            class="h-8 px-3.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer"
                            :class="
                                emailFilter === tab
                                    ? 'bg-[#003628] text-white shadow-xs'
                                    : 'bg-white border border-slate-200 text-slate-600 hover:border-slate-300'
                            "
                            @click="emailFilter = tab"
                        >
                            <component
                                v-if="tab !== 'all'"
                                :is="getConf(tab).icon"
                                class="size-3.5"
                                :class="emailFilter === tab ? 'text-white' : getConf(tab).color"
                            />
                            {{ tab === 'all' ? 'Semua Tipe' : tab }}
                            <span class="opacity-60 text-[10px]">({{ counts[tab] }})</span>
                        </button>
                    </div>

                    <!-- User Assets Table / Cards -->
                    <div v-if="filteredUserAssets.length" class="bg-white rounded-3xl border border-slate-200/80 overflow-hidden shadow-xs">
                        <div class="hidden md:grid grid-cols-[2fr_1fr_1fr_1fr] px-5 py-3 bg-slate-50/80 border-b border-slate-100 text-[10px] font-black uppercase tracking-wider text-slate-400">
                            <span>Nama Aset</span>
                            <span>Tipe</span>
                            <span>Tag / Serial</span>
                            <span>Lokasi</span>
                        </div>

                        <div class="divide-y divide-slate-100">
                            <div
                                v-for="(item, idx) in filteredUserAssets"
                                :key="idx"
                                class="px-5 py-3.5 hover:bg-slate-50/80 transition-colors group cursor-pointer"
                                @click="item.tag !== '-' ? viewAssetDetail(item.tag) : null"
                            >
                                <!-- Desktop layout -->
                                <div class="hidden md:grid grid-cols-[2fr_1fr_1fr_1fr] items-center gap-3">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="size-8 rounded-xl flex items-center justify-center shrink-0" :class="getConf(item.type).bg">
                                            <component :is="getConf(item.type).icon" class="size-4" :class="getConf(item.type).color" />
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-xs font-black text-slate-800 truncate group-hover:text-[#003628] transition-colors uppercase">
                                                {{ item.name }}
                                            </p>
                                            <p class="text-[10px] text-slate-400 truncate">{{ item.category }}</p>
                                        </div>
                                    </div>
                                    <div>
                                        <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold" :class="[getConf(item.type).bg, getConf(item.type).color]">
                                            {{ item.type }}
                                        </span>
                                    </div>
                                    <div class="font-mono text-xs text-slate-700 font-bold flex items-center gap-1">
                                        <span>{{ item.tag !== '-' ? item.tag : item.serial }}</span>
                                        <ChevronRight v-if="item.tag !== '-'" class="size-3 text-slate-300 group-hover:text-slate-600 transition-colors" />
                                    </div>
                                    <div class="text-xs text-slate-500 truncate">
                                        {{ item.location }}
                                    </div>
                                </div>

                                <!-- Mobile layout -->
                                <div class="md:hidden space-y-2">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="flex items-center gap-2.5 min-w-0">
                                            <div class="size-8 rounded-xl flex items-center justify-center shrink-0" :class="getConf(item.type).bg">
                                                <component :is="getConf(item.type).icon" class="size-4" :class="getConf(item.type).color" />
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-xs font-black text-slate-800 truncate group-hover:text-[#003628] uppercase">
                                                    {{ item.name }}
                                                </p>
                                                <p class="text-[10px] text-slate-400 truncate">{{ item.category }}</p>
                                            </div>
                                        </div>
                                        <span class="px-2 py-0.5 rounded-full text-[9px] font-bold shrink-0" :class="[getConf(item.type).bg, getConf(item.type).color]">
                                            {{ item.type }}
                                        </span>
                                    </div>
                                    <div class="grid grid-cols-3 gap-2 pt-1 text-[11px]">
                                        <div>
                                            <span class="text-[9px] font-black uppercase text-slate-400 block">Tag</span>
                                            <span class="font-mono font-bold text-slate-700">{{ item.tag }}</span>
                                        </div>
                                        <div>
                                            <span class="text-[9px] font-black uppercase text-slate-400 block">Serial</span>
                                            <span class="font-mono text-slate-600 truncate block">{{ item.serial }}</span>
                                        </div>
                                        <div>
                                            <span class="text-[9px] font-black uppercase text-slate-400 block">Lokasi</span>
                                            <span class="text-slate-600 truncate block">{{ item.location }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Empty Filter State -->
                    <div v-else class="bg-white rounded-3xl border border-slate-200/80 p-8 text-center text-slate-400 text-xs">
                        Tidak ada aset dengan kategori {{ emailFilter }}.
                    </div>
                </div>

                <!-- Empty State for Email Tab -->
                <div
                    v-else-if="!emailError && !userData"
                    class="bg-white rounded-3xl border border-slate-200/80 p-8 text-center space-y-3"
                >
                    <div class="size-14 rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-center mx-auto text-slate-400">
                        <Mail class="size-7" />
                    </div>
                    <h3 class="text-sm font-black uppercase tracking-tight text-slate-800">Cari Aset Berdasarkan Email</h3>
                    <p class="text-xs text-slate-400 max-w-sm mx-auto leading-relaxed">
                        Ketikkan alamat email karyawan perusahaan Anda untuk melihat semua perangkat hardware, lisensi, dan aksesoris yang dipinjamkan.
                    </p>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="py-6 border-t border-slate-200/60 bg-white text-center text-slate-400 text-[10px] font-bold uppercase tracking-widest">
            <p>&copy; {{ new Date().getFullYear() }} PT Zinus Global Indonesia &bull; IT Asset Management Portal</p>
        </footer>

        <!-- Camera Modal -->
        <teleport to="body">
            <div v-if="isCameraOpen" class="fixed inset-0 z-[100] flex flex-col bg-black text-white">
                <!-- Top Bar -->
                <div class="flex items-center justify-between px-5 py-4 bg-black/80 backdrop-blur-sm border-b border-white/10">
                    <div class="flex items-center gap-3">
                        <Camera class="size-5 text-emerald-400" />
                        <div>
                            <p class="text-sm font-medium">Scan QR Aset</p>
                            <p class="text-xs text-white/40">Arahkan ke stiker label QR</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            v-if="hasTorch"
                            type="button"
                            class="size-9 rounded-xl bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-colors cursor-pointer"
                            :class="{ 'bg-amber-400 text-slate-950': isTorchOn }"
                            @click="toggleTorch"
                        >
                            <Zap class="size-4" />
                        </button>
                        <button
                            type="button"
                            class="size-9 rounded-xl bg-white/10 hover:bg-white/20 flex items-center justify-center transition-colors cursor-pointer"
                            @click="stopCamera"
                        >
                            <XCircle class="size-5 text-red-400" />
                        </button>
                    </div>
                </div>

                <!-- Camera View -->
                <div class="relative flex-1 flex flex-col items-center justify-center overflow-hidden bg-black p-6">
                    <div id="public-qr-reader" class="w-full max-w-sm h-full max-h-[60vh] overflow-hidden rounded-2xl border border-white/10"></div>

                    <div v-if="isCameraStarting" class="absolute inset-0 flex flex-col items-center justify-center bg-black/85 gap-3 z-10">
                        <RefreshCw class="size-7 animate-spin text-emerald-400" />
                        <p class="text-xs text-white/50">Menghubungkan kamera...</p>
                    </div>

                    <div v-if="cameraError" class="absolute inset-x-6 top-1/2 -translate-y-1/2 bg-gray-900 border border-white/10 p-6 rounded-2xl text-center space-y-4 z-20">
                        <AlertCircle class="size-8 text-red-400 mx-auto" />
                        <p class="text-sm text-white/70 leading-relaxed">{{ cameraError }}</p>
                        <div class="flex gap-2 justify-center">
                            <button type="button" class="rounded-lg bg-white px-4 py-1.5 text-xs font-medium text-gray-800 cursor-pointer" @click="startCamera">Coba Lagi</button>
                            <button type="button" class="rounded-lg border border-white/20 bg-white/10 px-4 py-1.5 text-xs text-white cursor-pointer" @click="stopCamera">Tutup</button>
                        </div>
                    </div>

                    <div v-if="!isCameraStarting && !cameraError" class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center z-10">
                        <div class="relative w-60 h-60 rounded-2xl overflow-hidden shadow-[0_0_0_9999px_rgba(0,0,0,0.5)]">
                            <div class="laser-scanner-line" />
                            <div class="absolute top-2.5 left-2.5 size-5 border-t-2 border-l-2 border-emerald-400 rounded-tl-lg" />
                            <div class="absolute top-2.5 right-2.5 size-5 border-t-2 border-r-2 border-emerald-400 rounded-tr-lg" />
                            <div class="absolute bottom-2.5 left-2.5 size-5 border-b-2 border-l-2 border-emerald-400 rounded-bl-lg" />
                            <div class="absolute bottom-2.5 right-2.5 size-5 border-b-2 border-r-2 border-emerald-400 rounded-br-lg" />
                        </div>
                        <p class="mt-5 text-xs text-white/50">Posisikan QR Code di dalam kotak</p>
                    </div>
                </div>

                <!-- Bottom Bar -->
                <div class="px-5 py-4 bg-black/80 backdrop-blur-sm border-t border-white/10 flex items-center justify-between">
                    <span class="text-xs text-white/30">Menggunakan kamera belakang</span>
                    <button type="button" class="rounded-lg border border-white/15 bg-white/8 px-4 py-1.5 text-xs text-white/70 hover:bg-white/15 cursor-pointer transition-colors" @click="stopCamera">
                        Batal
                    </button>
                </div>
            </div>
        </teleport>
    </div>
</template>

<style scoped>
.laser-scanner-line {
    position: absolute;
    left: 0;
    right: 0;
    height: 1.5px;
    background: linear-gradient(90deg, transparent, #34d399, transparent);
    box-shadow: 0 0 8px 1px #34d399;
    animation: scanLine 2s ease-in-out infinite;
}

@keyframes scanLine {
    0%, 100% { top: 6%; }
    50% { top: 92%; }
}
</style>
