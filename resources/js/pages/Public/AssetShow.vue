<script setup lang="ts">
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { 
    Monitor, 
    User, 
    MapPin, 
    Hash, 
    Cpu, 
    Calendar, 
    Copy, 
    Check, 
    Share2, 
    ShieldCheck, 
    Building2, 
    Layers, 
    Wrench, 
    Tag, 
    ExternalLink,
    HardDrive
} from 'lucide-vue-next';

interface Component {
    name: string;
    category: string | null;
    qty: number;
}

interface CustomField {
    label: string;
    value: string;
}

interface Asset {
    id: number;
    name: string;
    asset_tag: string;
    serial: string;
    model: string;
    model_number?: string | null;
    category?: string;
    manufacturer?: string | null;
    image: string | null;
    status: string;
    status_type?: string;
    assigned_to: string;
    assigned_email?: string | null;
    location: string;
    company?: string;
    purchase_date?: string | null;
    warranty_months?: number | null;
    notes?: string | null;
    components?: Component[];
    custom_fields?: CustomField[];
}

const props = defineProps<{
    asset: Asset;
}>();

const copiedField = ref<string | null>(null);

const copyText = async (text: string, fieldName: string) => {
    if (!text || text === '-') return;
    try {
        await navigator.clipboard.writeText(text);
        copiedField.value = fieldName;
        setTimeout(() => {
            copiedField.value = null;
        }, 2000);
    } catch (e) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        copiedField.value = fieldName;
        setTimeout(() => {
            copiedField.value = null;
        }, 2000);
    }
};

const handleShare = async () => {
    if (navigator.share) {
        try {
            await navigator.share({
                title: `Asset: ${props.asset.name} (${props.asset.asset_tag})`,
                text: `Detail Asset ${props.asset.name} - Tag: ${props.asset.asset_tag}`,
                url: window.location.href,
            });
        } catch (e) {
            // User cancelled share
        }
    } else {
        copyText(window.location.href, 'share');
    }
};

const getStatusBadge = (status: string, statusType?: string) => {
    const s = (statusType || status || '').toLowerCase();
    if (s.includes('deployable') || s.includes('ready') || s.includes('active')) {
        return {
            bg: 'bg-emerald-500/10 text-emerald-700 border-emerald-500/20',
            dot: 'bg-emerald-500'
        };
    }
    if (s.includes('deployed') || s.includes('assigned') || s.includes('terpakai')) {
        return {
            bg: 'bg-blue-500/10 text-blue-700 border-blue-500/20',
            dot: 'bg-blue-500'
        };
    }
    if (s.includes('repair') || s.includes('maintenance') || s.includes('rusak')) {
        return {
            bg: 'bg-amber-500/10 text-amber-700 border-amber-500/20',
            dot: 'bg-amber-500'
        };
    }
    return {
        bg: 'bg-slate-500/10 text-slate-700 border-slate-500/20',
        dot: 'bg-slate-400'
    };
};
</script>

<template>
    <div class="min-h-screen bg-[#F8FAFC] text-slate-900 flex flex-col antialiased selection:bg-[#003628]/20 selection:text-[#003628]">
        <Head :title="`Asset: ${asset.asset_tag} - ${asset.name}`" />

        <!-- Header Navbar -->
        <header class="bg-white/90 backdrop-blur-md border-b border-slate-200/80 sticky top-0 z-40 shadow-xs">
            <div class="max-w-xl mx-auto px-4 h-16 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <img src="/form-logo.png" alt="ZinusIT" class="h-7 w-auto object-contain" />
                    <div class="h-4 w-[1px] bg-slate-200" />
                    <div class="flex items-center gap-1.5 text-slate-500 text-xs font-bold tracking-tight">
                        <ShieldCheck class="w-4 h-4 text-[#003628]" />
                        <span class="text-[11px] font-black uppercase tracking-wider text-[#003628]">IT Asset Registry</span>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button 
                        @click="handleShare"
                        type="button"
                        class="p-2 rounded-xl text-slate-500 hover:text-slate-800 hover:bg-slate-100 transition-colors cursor-pointer"
                        title="Bagikan Tautan"
                    >
                        <Check v-if="copiedField === 'share'" class="w-4 h-4 text-emerald-600" />
                        <Share2 v-else class="w-4 h-4" />
                    </button>
                    <div 
                        class="px-2.5 py-1 rounded-full border text-[11px] font-bold flex items-center gap-1.5 shadow-xs"
                        :class="getStatusBadge(asset.status, asset.status_type).bg"
                    >
                        <span class="w-1.5 h-1.5 rounded-full" :class="getStatusBadge(asset.status, asset.status_type).dot" />
                        <span>{{ asset.status }}</span>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content (Mobile Optimized) -->
        <main class="flex-1 max-w-xl mx-auto w-full px-4 py-6 space-y-4 pb-16">
            <!-- Hero Card -->
            <div class="relative overflow-hidden rounded-3xl bg-white border border-slate-200/80 p-6 shadow-sm">
                <div class="absolute -right-16 -top-16 w-44 h-44 rounded-full bg-[#003628]/5 blur-2xl pointer-events-none" />

                <div class="relative flex flex-col items-center text-center">
                    <!-- Image or Device Icon -->
                    <div class="w-24 h-24 rounded-2xl bg-gradient-to-br from-slate-50 to-slate-100 border border-slate-200/60 flex items-center justify-center p-2 mb-4 shadow-inner">
                        <img 
                            v-if="asset.image" 
                            :src="asset.image" 
                            :alt="asset.name"
                            class="w-full h-full object-contain" 
                        />
                        <Monitor v-else class="w-10 h-10 text-slate-400" />
                    </div>

                    <!-- Category Pill -->
                    <div v-if="asset.category" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-600 text-[10px] font-black uppercase tracking-wider mb-2">
                        <Tag class="w-3 h-3 text-slate-400" />
                        {{ asset.category }}
                    </div>

                    <!-- Asset Name & Model -->
                    <h1 class="text-lg font-black text-slate-900 tracking-tight leading-snug uppercase max-w-md">
                        {{ asset.name }}
                    </h1>
                    <p class="text-xs font-semibold text-slate-500 mt-0.5">
                        {{ asset.manufacturer ? `${asset.manufacturer} - ` : '' }}{{ asset.model }}
                    </p>

                    <!-- Identifiers Badge (Tag & Serial) -->
                    <div class="grid grid-cols-2 gap-2 w-full mt-5">
                        <div 
                            @click="copyText(asset.asset_tag, 'tag')"
                            class="p-3 rounded-2xl bg-slate-50/80 border border-slate-200/80 flex flex-col items-start cursor-pointer hover:bg-slate-100 transition-colors group relative"
                        >
                            <div class="flex items-center justify-between w-full mb-1">
                                <span class="text-[9px] font-black uppercase tracking-wider text-slate-400">Asset Tag</span>
                                <Check v-if="copiedField === 'tag'" class="w-3.5 h-3.5 text-emerald-600" />
                                <Copy v-else class="w-3.5 h-3.5 text-slate-300 group-hover:text-slate-500 transition-colors" />
                            </div>
                            <span class="text-xs font-black text-[#003628] uppercase tracking-wide truncate w-full text-left">
                                {{ asset.asset_tag }}
                            </span>
                        </div>

                        <div 
                            @click="copyText(asset.serial, 'serial')"
                            class="p-3 rounded-2xl bg-slate-50/80 border border-slate-200/80 flex flex-col items-start cursor-pointer hover:bg-slate-100 transition-colors group relative"
                        >
                            <div class="flex items-center justify-between w-full mb-1">
                                <span class="text-[9px] font-black uppercase tracking-wider text-slate-400">Serial Number</span>
                                <Check v-if="copiedField === 'serial'" class="w-3.5 h-3.5 text-emerald-600" />
                                <Copy v-else class="w-3.5 h-3.5 text-slate-300 group-hover:text-slate-500 transition-colors" />
                            </div>
                            <span class="text-xs font-black text-slate-700 uppercase tracking-wide truncate w-full text-left">
                                {{ asset.serial || '-' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Custodian Hero Card (full width, highlighted) -->
            <div class="rounded-3xl relative overflow-hidden shadow-xl shadow-[#003628]/15"
                :class="asset.assigned_to.toLowerCase().includes('available') 
                    ? 'bg-gradient-to-br from-slate-700 to-slate-800' 
                    : 'bg-gradient-to-br from-[#003628] to-[#004d39]'"
            >
                <!-- Decorative blobs -->
                <div class="absolute -right-10 -top-10 w-40 h-40 rounded-full bg-white/5 blur-2xl pointer-events-none" />
                <div class="absolute -left-10 -bottom-10 w-32 h-32 rounded-full bg-[#d99528]/10 blur-2xl pointer-events-none" />

                <div class="relative p-6">
                    <!-- Label Badge -->
                    <div class="flex items-center gap-2 mb-5">
                        <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full border text-[10px] font-black uppercase tracking-widest"
                            :class="asset.assigned_to.toLowerCase().includes('available')
                                ? 'border-white/10 bg-white/10 text-slate-200'
                                : 'border-[#d99528]/40 bg-[#d99528]/15 text-[#d99528]'"
                        >
                            <div class="w-1.5 h-1.5 rounded-full animate-pulse"
                                :class="asset.assigned_to.toLowerCase().includes('available') ? 'bg-slate-300' : 'bg-[#d99528]'"
                            />
                            {{ asset.assigned_to.toLowerCase().includes('available') ? 'Tidak Terpakai' : 'Pengguna Aktif' }}
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <!-- Avatar Initial -->
                        <div class="shrink-0 w-16 h-16 rounded-2xl flex items-center justify-center text-2xl font-black border-2"
                            :class="asset.assigned_to.toLowerCase().includes('available')
                                ? 'bg-white/10 border-white/10 text-slate-300'
                                : 'bg-[#d99528]/20 border-[#d99528]/30 text-[#d99528]'"
                        >
                            {{ asset.assigned_to.toLowerCase().includes('available') ? '?' : asset.assigned_to.charAt(0).toUpperCase() }}
                        </div>

                        <div class="flex-1 min-w-0">
                            <p class="text-[9px] font-black uppercase tracking-widest text-white/50 mb-1">Sedang Digunakan Oleh</p>
                            <h2 class="text-xl font-black text-white uppercase tracking-tight leading-snug truncate">
                                {{ asset.assigned_to }}
                            </h2>
                            <p v-if="asset.assigned_email" class="text-xs text-emerald-200/70 truncate mt-1">
                                {{ asset.assigned_email }}
                            </p>
                            <div class="flex items-center gap-1.5 mt-2">
                                <MapPin class="w-3.5 h-3.5 text-white/40 shrink-0" />
                                <span class="text-[11px] font-bold text-white/70 truncate">{{ asset.location }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Location Card (standalone) -->
            <div class="rounded-3xl bg-white border border-slate-200/80 p-4 shadow-xs relative overflow-hidden">
                <div class="flex items-center gap-3.5">
                    <div class="w-10 h-10 rounded-2xl bg-[#d99528]/10 flex items-center justify-center shrink-0 border border-[#d99528]/20">
                        <MapPin class="w-5 h-5 text-[#d99528]" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 block">Lokasi Fisik Saat Ini</span>
                        <h3 class="text-sm font-black uppercase tracking-tight text-slate-800 truncate mt-0.5">
                            {{ asset.location }}
                        </h3>
                    </div>
                    <div class="shrink-0 flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-50 border border-slate-100">
                        <Building2 class="w-3.5 h-3.5 text-slate-400" />
                        <span class="text-[10px] font-bold text-slate-500 max-w-[100px] truncate">{{ asset.company || 'Zinus Global Indonesia' }}</span>
                    </div>
                </div>
            </div>

            <!-- Specifications & Info -->
            <div class="rounded-3xl bg-white border border-slate-200/80 p-5 shadow-xs space-y-4">
                <div class="flex items-center gap-2 pb-3 border-b border-slate-100">
                    <Layers class="w-4 h-4 text-[#003628]" />
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-800">Spesifikasi & Informasi</h3>
                </div>

                <div class="grid grid-cols-2 gap-2.5 text-xs">
                    <!-- Always-visible base specs -->
                    <div v-if="asset.manufacturer" class="bg-slate-50/80 p-3 rounded-2xl border border-slate-100">
                        <span class="text-[9px] font-black uppercase tracking-wider text-slate-400 block mb-0.5">Manufacturer</span>
                        <div class="font-bold text-slate-800 truncate">{{ asset.manufacturer }}</div>
                    </div>

                    <div class="bg-slate-50/80 p-3 rounded-2xl border border-slate-100">
                        <span class="text-[9px] font-black uppercase tracking-wider text-slate-400 block mb-0.5">Model</span>
                        <div class="font-bold text-slate-800 truncate" :title="asset.model">{{ asset.model || '-' }}</div>
                    </div>

                    <div v-if="asset.model_number" class="bg-slate-50/80 p-3 rounded-2xl border border-slate-100">
                        <span class="text-[9px] font-black uppercase tracking-wider text-slate-400 block mb-0.5">Model Number</span>
                        <div class="font-bold text-slate-800 truncate">{{ asset.model_number }}</div>
                    </div>

                    <div v-if="asset.category" class="bg-slate-50/80 p-3 rounded-2xl border border-slate-100">
                        <span class="text-[9px] font-black uppercase tracking-wider text-slate-400 block mb-0.5">Kategori</span>
                        <div class="font-bold text-slate-800 truncate">{{ asset.category }}</div>
                    </div>

                    <div v-if="asset.purchase_date" class="bg-slate-50/80 p-3 rounded-2xl border border-slate-100">
                        <span class="text-[9px] font-black uppercase tracking-wider text-slate-400 block mb-0.5">Tanggal Beli</span>
                        <div class="font-bold text-slate-700 flex items-center gap-1.5">
                            <Calendar class="w-3.5 h-3.5 text-slate-400" />
                            {{ asset.purchase_date }}
                        </div>
                    </div>

                    <div v-if="asset.warranty_months" class="bg-slate-50/80 p-3 rounded-2xl border border-slate-100">
                        <span class="text-[9px] font-black uppercase tracking-wider text-slate-400 block mb-0.5">Garansi</span>
                        <div class="font-bold text-slate-700 flex items-center gap-1.5">
                            <ShieldCheck class="w-3.5 h-3.5 text-emerald-600" />
                            {{ asset.warranty_months }} Bulan
                        </div>
                    </div>

                    <!-- Snipe-IT custom fields (e.g. IP, Rack, Batch) -->
                    <div v-for="cf in (asset.custom_fields || [])" :key="cf.label" class="bg-slate-50/80 p-3 rounded-2xl border border-slate-100">
                        <span class="text-[9px] font-black uppercase tracking-wider text-slate-400 block mb-0.5">{{ cf.label }}</span>
                        <div class="font-bold text-slate-800 truncate" :title="cf.value">
                            {{ cf.value }}
                        </div>
                    </div>
                </div>

                <!-- Notes if available -->
                <div v-if="asset.notes" class="p-3 bg-amber-50 rounded-2xl border border-amber-100 text-xs text-amber-800 flex items-start gap-2">
                    <span class="text-amber-500 shrink-0 mt-0.5">📝</span>
                    <span class="italic">{{ asset.notes }}</span>
                </div>
            </div>

            <!-- Installed Components (if any) -->
            <div v-if="asset.components && asset.components.length > 0" class="rounded-3xl bg-white border border-slate-200/80 p-5 shadow-xs space-y-3">
                <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                    <div class="flex items-center gap-2">
                        <Cpu class="w-4 h-4 text-[#003628]" />
                        <h3 class="text-xs font-black uppercase tracking-wider text-slate-800">Komponen Terpasang</h3>
                    </div>
                    <span class="text-[10px] font-black text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full">{{ asset.components.length }}</span>
                </div>

                <div class="space-y-2">
                    <div 
                        v-for="comp in asset.components" 
                        :key="comp.name" 
                        class="p-3 rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-between text-xs"
                    >
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-xl bg-white border border-slate-200/60 flex items-center justify-center text-slate-500">
                                <HardDrive class="w-4 h-4 text-[#003628]" />
                            </div>
                            <div>
                                <span class="font-black text-slate-800 uppercase block leading-tight">{{ comp.name }}</span>
                                <span v-if="comp.category" class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">{{ comp.category }}</span>
                            </div>
                        </div>
                        <span class="text-xs font-black text-slate-600 bg-white px-2.5 py-1 rounded-xl border border-slate-200/80">
                            x{{ comp.qty }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Need Help / Support Banner -->
            <div class="rounded-3xl bg-slate-900 text-white p-6 relative overflow-hidden shadow-xl shadow-slate-900/10">
                <div class="absolute -right-8 -bottom-8 w-32 h-32 rounded-full bg-[#d99528]/20 blur-2xl pointer-events-none" />

                <div class="relative space-y-3">
                    <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-white/10 text-emerald-300 text-[10px] font-black uppercase tracking-wider">
                        <Wrench class="w-3 h-3" />
                        IT Support & Helpdesk
                    </div>
                    <h3 class="text-base font-black uppercase tracking-tight">Menemukan Masalah Pada Aset Ini?</h3>
                    <p class="text-xs text-slate-300 leading-relaxed">
                        Jika perangkat ini membutuhkan perbaikan atau Anda menemukannya tidak pada tempatnya, laporkan segera ke tim IT Support.
                    </p>
                    <div class="pt-2 flex flex-wrap gap-2">
                        <a 
                            href="/kb" 
                            class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-[#003628] hover:bg-[#004d39] text-white text-xs font-black uppercase tracking-wider transition-all shadow-md active:scale-95 cursor-pointer"
                        >
                            Pusat Bantuan IT
                            <ExternalLink class="w-3.5 h-3.5" />
                        </a>
                    </div>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="py-6 border-t border-slate-200/60 bg-white text-center text-slate-400 text-[10px] font-bold uppercase tracking-widest">
            <p>&copy; {{ new Date().getFullYear() }} PT Zinus Global Indonesia &bull; IT Asset Management</p>
        </footer>
    </div>
</template>

<style scoped>
body {
    -webkit-tap-highlight-color: transparent;
}
</style>
