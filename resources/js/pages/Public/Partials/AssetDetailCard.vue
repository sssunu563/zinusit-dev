<script setup lang="ts">
import { computed, ref } from 'vue';
import {
    Building2,
    Calendar,
    Check,
    Copy,
    Cpu,
    ExternalLink,
    HardDrive,
    HelpCircle,
    Info,
    Laptop,
    Layers,
    Mail,
    MapPin,
    Monitor,
    Package,
    Share2,
    ShieldCheck,
    Smartphone,
    Tag,
    User,
    Users,
    Wrench,
} from 'lucide-vue-next';

export interface ComponentItem {
    name: string;
    category: string | null;
    qty: number;
}

export interface CustomFieldItem {
    label: string;
    value: string;
}

export interface AssetDetail {
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
    components?: ComponentItem[];
    custom_fields?: CustomFieldItem[];
}

const props = defineProps<{
    asset: AssetDetail;
}>();

const emit = defineEmits<{
    (e: 'view-user-assets', email: string): void;
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
    } catch {
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
    const url = window.location.href;
    if (navigator.share) {
        try {
            await navigator.share({
                title: `Aset: ${props.asset.name} (${props.asset.asset_tag})`,
                text: `Detail Aset ${props.asset.name} - Tag: ${props.asset.asset_tag}`,
                url,
            });
        } catch {
            // Cancelled
        }
    } else {
        copyText(url, 'share');
    }
};

const getStatusBadge = (status: string, statusType?: string) => {
    const s = (statusType || status || '').toLowerCase();
    if (s.includes('deployable') || s.includes('ready') || s.includes('active')) {
        return {
            bg: 'bg-emerald-50 text-emerald-700 border-emerald-200/80',
            dot: 'bg-emerald-500',
        };
    }
    if (s.includes('deployed') || s.includes('assigned') || s.includes('terpakai')) {
        return {
            bg: 'bg-blue-50 text-blue-700 border-blue-200/80',
            dot: 'bg-blue-500',
        };
    }
    if (s.includes('repair') || s.includes('maintenance') || s.includes('rusak')) {
        return {
            bg: 'bg-amber-50 text-amber-700 border-amber-200/80',
            dot: 'bg-amber-500',
        };
    }
    return {
        bg: 'bg-gray-100 text-gray-700 border-gray-200',
        dot: 'bg-gray-400',
    };
};

const isAvailable = computed(() => {
    const a = (props.asset.assigned_to || '').toLowerCase();
    return a.includes('available') || a.includes('in stock') || a.includes('gudang') || !props.asset.assigned_to;
});

const getDeviceIcon = computed(() => {
    const cat = (props.asset.category || '').toLowerCase();
    if (cat.includes('laptop') || cat.includes('notebook')) return Laptop;
    if (cat.includes('phone') || cat.includes('mobile')) return Smartphone;
    if (cat.includes('component')) return Cpu;
    if (cat.includes('accessory')) return Package;
    return Monitor;
});
</script>

<template>
    <div class="space-y-4">
        <!-- 1. MAIN ASSET OVERVIEW CARD -->
        <div class="bg-white rounded-2xl border border-gray-100 p-5 shadow-xs space-y-4">
            <!-- Top Sub-Bar: Tags, Status & Share -->
            <div class="flex items-center justify-between gap-3 pb-3.5 border-b border-gray-100">
                <div class="flex items-center gap-2 flex-wrap">
                    <span
                        v-if="asset.category"
                        class="px-2.5 py-0.5 rounded-md bg-gray-100 text-gray-600 text-[11px] font-semibold uppercase tracking-wider"
                    >
                        {{ asset.category }}
                    </span>
                    <div
                        class="px-2.5 py-0.5 rounded-full border text-xs font-medium flex items-center gap-1.5"
                        :class="getStatusBadge(asset.status, asset.status_type).bg"
                    >
                        <span class="w-1.5 h-1.5 rounded-full" :class="getStatusBadge(asset.status, asset.status_type).dot" />
                        <span>{{ asset.status }}</span>
                    </div>
                </div>

                <button
                    type="button"
                    class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs text-gray-500 hover:text-gray-800 hover:bg-gray-50 border border-gray-200/60 transition-colors cursor-pointer"
                    title="Bagikan Tautan Aset"
                    @click="handleShare"
                >
                    <Check v-if="copiedField === 'share'" class="size-3.5 text-emerald-600" />
                    <Share2 v-else class="size-3.5" />
                    <span class="text-xs">{{ copiedField === 'share' ? 'Tersalin!' : 'Bagikan' }}</span>
                </button>
            </div>

            <!-- Asset Identity Section -->
            <div class="flex items-start gap-4">
                <!-- Device Image or Icon -->
                <div class="size-16 rounded-xl bg-gray-50 border border-gray-100 flex items-center justify-center shrink-0 p-1.5">
                    <img
                        v-if="asset.image"
                        :src="asset.image"
                        :alt="asset.name"
                        class="w-full h-full object-contain"
                    />
                    <component :is="getDeviceIcon" v-else class="size-8 text-gray-400" />
                </div>

                <!-- Asset Title & Identifiers -->
                <div class="min-w-0 flex-1">
                    <h2 class="text-base font-semibold text-gray-800 leading-snug">
                        {{ asset.model || asset.name }}
                    </h2>
                    <p class="text-xs text-gray-400 mt-0.5">
                        {{ asset.manufacturer ? `${asset.manufacturer} • ` : '' }}{{ asset.name !== asset.model ? asset.name : 'Hardware Asset' }}
                    </p>

                    <!-- Identifiers Row (Tag & Serial) -->
                    <div class="flex items-center gap-2 flex-wrap mt-3">
                        <!-- Asset Tag -->
                        <div
                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-gray-50 border border-gray-200/80 text-xs font-mono text-gray-700 hover:bg-gray-100 cursor-pointer transition-colors group"
                            title="Klik untuk salin tag"
                            @click="copyText(asset.asset_tag, 'tag')"
                        >
                            <span class="text-[10px] uppercase font-sans font-medium text-gray-400">Tag:</span>
                            <span class="font-bold text-gray-900">{{ asset.asset_tag }}</span>
                            <Check v-if="copiedField === 'tag'" class="size-3 text-emerald-600" />
                            <Copy v-else class="size-3 text-gray-400 group-hover:text-gray-600" />
                        </div>

                        <!-- Serial Number -->
                        <div
                            v-if="asset.serial && asset.serial !== '-'"
                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-gray-50 border border-gray-200/80 text-xs font-mono text-gray-700 hover:bg-gray-100 cursor-pointer transition-colors group"
                            title="Klik untuk salin serial"
                            @click="copyText(asset.serial, 'serial')"
                        >
                            <span class="text-[10px] uppercase font-sans font-medium text-gray-400">SN:</span>
                            <span class="font-bold text-gray-900">{{ asset.serial }}</span>
                            <Check v-if="copiedField === 'serial'" class="size-3 text-emerald-600" />
                            <Copy v-else class="size-3 text-gray-400 group-hover:text-gray-600" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. USER / CUSTODIAN CARD (MATCHING EMAIL SEARCH USER CARD) -->
        <div class="bg-white rounded-2xl border border-gray-100 p-4 shadow-xs">
            <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-3">
                Penanggung Jawab / Pemegang Aset
            </p>

            <div v-if="!isAvailable" class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-3.5 min-w-0">
                    <!-- User Avatar -->
                    <div class="size-11 rounded-xl bg-emerald-50 border border-emerald-100 flex items-center justify-center text-emerald-800 font-bold text-base shrink-0">
                        {{ asset.assigned_to ? asset.assigned_to.charAt(0).toUpperCase() : 'U' }}
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-gray-800 truncate">
                            {{ asset.assigned_to }}
                        </p>
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-0.5 mt-0.5">
                            <span v-if="asset.assigned_email" class="flex items-center gap-1 text-xs text-gray-400">
                                <Mail class="size-3" /> {{ asset.assigned_email }}
                            </span>
                            <span v-if="asset.location" class="flex items-center gap-1 text-xs text-gray-400">
                                <MapPin class="size-3" /> {{ asset.location }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Cross-Link Action Button -->
                <button
                    v-if="asset.assigned_email"
                    type="button"
                    class="h-9 px-3.5 rounded-xl bg-gray-50 hover:bg-emerald-50 text-gray-700 hover:text-emerald-700 border border-gray-200/80 hover:border-emerald-200 text-xs font-medium flex items-center justify-center gap-1.5 transition-all cursor-pointer shrink-0"
                    @click="emit('view-user-assets', asset.assigned_email!)"
                >
                    <Users class="size-3.5 text-emerald-600" />
                    <span>Lihat Semua Aset Karyawan</span>
                    <span class="text-xs">→</span>
                </button>
            </div>

            <!-- If Unassigned / Warehouse -->
            <div v-else class="flex items-center gap-3 py-1 text-gray-500">
                <div class="size-10 rounded-xl bg-gray-50 border border-gray-100 flex items-center justify-center text-gray-400 shrink-0">
                    <Building2 class="size-5" />
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-700">Tersedia di Gudang (In Stock)</p>
                    <p class="text-xs text-gray-400">Aset saat ini belum dipinjamkan ke karyawan.</p>
                </div>
            </div>
        </div>

        <!-- 3. SPECIFICATIONS & DETAILS GRID -->
        <div class="bg-white rounded-2xl border border-gray-100 p-5 shadow-xs space-y-3">
            <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider">
                Spesifikasi & Informasi Perangkat
            </p>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                <!-- Lokasi -->
                <div class="p-3 rounded-xl bg-gray-50/70 border border-gray-100">
                    <span class="text-[10px] text-gray-400 block mb-0.5">Lokasi Fisik</span>
                    <p class="text-xs font-semibold text-gray-700 truncate">{{ asset.location || '-' }}</p>
                </div>

                <!-- Perusahaan -->
                <div class="p-3 rounded-xl bg-gray-50/70 border border-gray-100">
                    <span class="text-[10px] text-gray-400 block mb-0.5">Perusahaan</span>
                    <p class="text-xs font-semibold text-gray-700 truncate">{{ asset.company || 'Zinus Global Indonesia' }}</p>
                </div>

                <!-- Manufacturer -->
                <div v-if="asset.manufacturer" class="p-3 rounded-xl bg-gray-50/70 border border-gray-100">
                    <span class="text-[10px] text-gray-400 block mb-0.5">Manufacturer</span>
                    <p class="text-xs font-semibold text-gray-700 truncate">{{ asset.manufacturer }}</p>
                </div>

                <!-- Model -->
                <div class="p-3 rounded-xl bg-gray-50/70 border border-gray-100">
                    <span class="text-[10px] text-gray-400 block mb-0.5">Model</span>
                    <p class="text-xs font-semibold text-gray-700 truncate" :title="asset.model">{{ asset.model || '-' }}</p>
                </div>

                <!-- Model Number -->
                <div v-if="asset.model_number" class="p-3 rounded-xl bg-gray-50/70 border border-gray-100">
                    <span class="text-[10px] text-gray-400 block mb-0.5">Model Number</span>
                    <p class="text-xs font-semibold text-gray-700 truncate">{{ asset.model_number }}</p>
                </div>

                <!-- Tanggal Beli -->
                <div v-if="asset.purchase_date" class="p-3 rounded-xl bg-gray-50/70 border border-gray-100">
                    <span class="text-[10px] text-gray-400 block mb-0.5">Tanggal Beli</span>
                    <p class="text-xs font-semibold text-gray-700 flex items-center gap-1">
                        <Calendar class="size-3 text-gray-400" />
                        <span>{{ asset.purchase_date }}</span>
                    </p>
                </div>

                <!-- Garansi -->
                <div v-if="asset.warranty_months" class="p-3 rounded-xl bg-gray-50/70 border border-gray-100">
                    <span class="text-[10px] text-gray-400 block mb-0.5">Garansi</span>
                    <p class="text-xs font-semibold text-gray-700 flex items-center gap-1">
                        <ShieldCheck class="size-3 text-emerald-600" />
                        <span>{{ asset.warranty_months }} Bulan</span>
                    </p>
                </div>

                <!-- Custom Snipe-IT Fields -->
                <div
                    v-for="cf in (asset.custom_fields || [])"
                    :key="cf.label"
                    class="p-3 rounded-xl bg-gray-50/70 border border-gray-100"
                >
                    <span class="text-[10px] text-gray-400 block mb-0.5">{{ cf.label }}</span>
                    <p class="text-xs font-semibold text-gray-700 truncate" :title="cf.value">{{ cf.value }}</p>
                </div>
            </div>

            <!-- Notes Callout -->
            <div v-if="asset.notes" class="p-3 bg-amber-50/60 rounded-xl border border-amber-200/60 text-xs text-amber-800 flex items-start gap-2">
                <Info class="size-4 text-amber-500 shrink-0 mt-0.5" />
                <span class="italic">{{ asset.notes }}</span>
            </div>
        </div>

        <!-- 4. COMPONENTS (IF ANY) -->
        <div v-if="asset.components && asset.components.length > 0" class="bg-white rounded-2xl border border-gray-100 p-5 shadow-xs space-y-3">
            <div class="flex items-center justify-between">
                <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider">
                    Komponen Terpasang
                </p>
                <span class="text-xs text-gray-400 font-mono">({{ asset.components.length }})</span>
            </div>

            <div class="divide-y divide-gray-50">
                <div
                    v-for="comp in asset.components"
                    :key="comp.name"
                    class="py-2.5 flex items-center justify-between text-xs"
                >
                    <div class="flex items-center gap-2.5">
                        <Cpu class="size-4 text-gray-400" />
                        <div>
                            <p class="font-medium text-gray-700">{{ comp.name }}</p>
                            <span v-if="comp.category" class="text-[10px] text-gray-400">{{ comp.category }}</span>
                        </div>
                    </div>
                    <span class="text-xs font-semibold text-gray-600 bg-gray-50 px-2 py-0.5 rounded-md border border-gray-100">
                        x{{ comp.qty }}
                    </span>
                </div>
            </div>
        </div>

        <!-- 5. HELPDESK & SUPPORT (CLEAN & SUBTLE) -->
        <div class="bg-white rounded-2xl border border-gray-100 p-4 shadow-xs flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="size-10 rounded-xl bg-gray-50 border border-gray-100 flex items-center justify-center text-gray-500 shrink-0">
                    <Wrench class="size-5 text-gray-400" />
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-800">Butuh bantuan atau perbaikan?</p>
                    <p class="text-[11px] text-gray-400">Laporkan kendala perangkat ke tim IT Support.</p>
                </div>
            </div>

            <a
                href="/help"
                class="h-9 px-3.5 rounded-xl bg-gray-800 hover:bg-gray-700 text-white text-xs font-medium flex items-center gap-1.5 transition-colors cursor-pointer shrink-0"
            >
                <span>Bantuan IT</span>
                <ExternalLink class="size-3" />
            </a>
        </div>
    </div>
</template>
