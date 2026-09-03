<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { getCategoryLabel, getCategoryStyle } from '@/constants/categories';
import { 
    User, 
    Shield, 
    Monitor, 
    BadgeCheck, 
    Camera,
    HardDrive,
    Key,
    Plug,
    Package,
    Loader2,
    CheckCircle2,
    ChevronRight,
    AtSign,
    Phone,
    Briefcase,
    Building2,
    MapPin,
    ArrowUpRight,
    Globe,
    Cpu,
    Hash,
    KeyRound,
    ShoppingCart,
    FileText,
    Activity,
    Download,
    Eye,
    RefreshCw,
    Layers,
    Search,
    Filter,
    Clock,
    Tag,
    MoreVertical
} from 'lucide-vue-next';

interface Asset {
    id: number;
    name: string;
    asset_tag: string;
    serial: string;
    type: 'hardware' | 'license' | 'accessory' | 'consumable';
    image?: string;
    notes?: string;
    category?: string;
    manufacturer?: string;
}

const props = defineProps<{
    profile: any;
    assets: Asset[];
    consumables: any[];
    files: any[];
    history: any[];
    status?: string;
}>();

const activeTab = ref('general');

const tabs = [
    { 
        id: 'general', 
        label: 'Profil Identitas', 
        icon: User,
        title: 'Manajemen Profil'
    },
    { 
        id: 'inventory', 
        label: 'Inventaris Aset', 
        icon: Package,
        title: 'Daftar Aset Digital'
    },
    { 
        id: 'consumables', 
        label: 'Habis Pakai', 
        icon: ShoppingCart,
        title: 'Riwayat Barang'
    },
    { 
        id: 'files', 
        label: 'Dokumen File', 
        icon: FileText,
        title: 'Lampiran System'
    },
    { 
        id: 'history', 
        label: 'Log Aktivitas', 
        icon: Activity,
        title: 'Riwayat System'
    },
    { 
        id: 'security', 
        label: 'Akses Keamanan', 
        icon: Shield,
        title: 'Sandi & Keamanan'
    }
];

// Stats Calculation
const stats = computed(() => {
    return {
        totalAssets: props.assets.filter(a => a.type === 'hardware').length,
        totalLicenses: props.assets.filter(a => a.type === 'license').length,
        totalFiles: props.files.length,
        totalConsumables: props.consumables.length
    };
});

// File Pagination
const filesPerPage = 6;
const currentFilePage = ref(1);
const totalFilePages = computed(() => Math.ceil(props.files.length / filesPerPage));
const paginatedFiles = computed(() => {
    const start = (currentFilePage.value - 1) * filesPerPage;
    return props.files.slice(start, start + filesPerPage);
});

const submitPassword = () => {
    passwordForm.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
    });
};

const handleAvatarChange = (e: any) => {
    const file = e.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = (event: any) => {
        avatarForm.image = event.target.result;
        avatarForm.post(route('profile.avatar.upload'), {
            preserveScroll: true,
        });
    };
    reader.readAsDataURL(file);
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Profil Pengguna" />

        <SettingsLayout v-model:activeTab="activeTab" :tabs="tabs">
            <!-- Content Container -->
            <div class="relative min-h-[500px]">
                <transition
                    enter-active-class="transition ease-out duration-300"
                    enter-from-class="opacity-0 translate-y-4"
                    enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="transition ease-in duration-200"
                    leave-from-class="opacity-100 translate-y-0"
                    leave-to-class="opacity-0 -translate-y-4"
                >
                    <div :key="activeTab">
                        <!-- Tab: Profil Umum -->
                        <div v-if="activeTab === 'general'" class="max-w-4xl space-y-12 animate-in fade-in duration-500">
                            <section class="space-y-1">
                                <h3 class="text-[10px] font-black uppercase tracking-[0.2em] text-[#003628] mb-6">Identity Verification</h3>
                                <div class="divide-y divide-slate-50">
                                    <div class="flex items-center justify-between py-4 group">
                                        <div class="flex items-center gap-3">
                                            <BadgeCheck class="size-3.5 text-slate-200" />
                                            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">Nama Lengkap</span>
                                        </div>
                                        <span class="text-sm font-bold text-slate-800">{{ fullName }}</span>
                                    </div>
                                    <div class="flex items-center justify-between py-4 group">
                                        <div class="flex items-center gap-3">
                                            <AtSign class="size-3.5 text-slate-200" />
                                            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">System Identifier</span>
                                        </div>
                                        <span class="text-sm font-bold text-slate-800 font-mono">@{{ profile.username }}</span>
                                    </div>
                                    <div class="flex items-center justify-between py-4 group">
                                        <div class="flex items-center gap-3">
                                            <Globe class="size-3.5 text-slate-200" />
                                            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">Email Address</span>
                                        </div>
                                        <span class="text-sm font-bold text-slate-800 italic">{{ profile.email }}</span>
                                    </div>
                                    <div class="flex items-center justify-between py-4 group">
                                        <div class="flex items-center gap-3">
                                            <Phone class="size-3.5 text-slate-200" />
                                            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">Phone Number</span>
                                        </div>
                                        <span class="text-sm font-bold text-slate-800 tabular-nums">{{ profile.mobile || profile.phone || '-' }}</span>
                                    </div>
                                </div>
                            </section>

                            <section class="space-y-1">
                                <h3 class="text-[10px] font-black uppercase tracking-[0.2em] text-[#d99528] mb-6">Organization Context</h3>
                                <div class="divide-y divide-slate-50">
                                    <div class="flex items-center justify-between py-4 group">
                                        <div class="flex items-center gap-3">
                                            <Building2 class="size-3.5 text-slate-200" />
                                            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">Company</span>
                                        </div>
                                        <span class="text-sm font-bold text-slate-700">{{ profile.company_name || '-' }}</span>
                                    </div>
                                    <div class="flex items-center justify-between py-4 group">
                                        <div class="flex items-center gap-3">
                                            <Briefcase class="size-3.5 text-slate-200" />
                                            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">Department</span>
                                        </div>
                                        <span class="text-sm font-bold text-slate-700">{{ profile.department_name || '-' }}</span>
                                    </div>
                                    <div class="flex items-center justify-between py-4 group">
                                        <div class="flex items-center gap-3">
                                            <MapPin class="size-3.5 text-slate-200" />
                                            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">Primary Office</span>
                                        </div>
                                        <span class="text-sm font-bold text-slate-700">{{ profile.location_name || '-' }}</span>
                                    </div>
                                </div>
                            </section>
                        </div>

                        <!-- Tab: Inventaris (Modern Table Clone) -->
                        <div v-if="activeTab === 'inventory'" class="space-y-6 animate-in fade-in duration-500">
                            <div class="flex items-center justify-between px-1">
                                <div class="flex items-center gap-3">
                                    <div class="h-4 w-1 rounded-full bg-[#003628]" />
                                    <h3 class="text-[10px] font-black uppercase tracking-[0.2em] text-[#003628]">Digital Assets Inventory</h3>
                                </div>
                                <span class="px-3 py-1 rounded-full bg-slate-900 text-white text-[9px] font-black tabular-nums shadow-lg shadow-slate-900/10">{{ assets.length }} REGISTERED</span>
                            </div>

                            <div class="rounded-2xl border border-slate-100 overflow-hidden bg-white shadow-sm overflow-x-auto">
                                <table class="w-full text-left border-collapse" style="min-width: 800px">
                                    <thead class="bg-slate-50/50 border-b border-slate-100">
                                        <tr>
                                            <th class="px-6 py-4 text-[9px] font-black uppercase tracking-widest text-slate-400 w-[250px]">Asset Name</th>
                                            <th class="px-4 py-4 text-[9px] font-black uppercase tracking-widest text-slate-400 w-[180px]">Type</th>
                                            <th class="px-4 py-4 text-[9px] font-black uppercase tracking-widest text-slate-400 w-16 text-center">Qty</th>
                                            <th class="px-4 py-4 text-[9px] font-black uppercase tracking-widest text-slate-400 w-[120px]">Condition</th>
                                            <th class="px-4 py-4 text-[9px] font-black uppercase tracking-widest text-slate-400 w-[180px]">Serial No</th>
                                            <th class="px-4 py-4 text-[9px] font-black uppercase tracking-widest text-slate-400">Asset</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-50">
                                        <tr v-for="asset in assets" :key="`${asset.type}-${asset.id}`" class="hover:bg-slate-50/50 transition-colors group">
                                            <!-- Asset Name + Badge -->
                                            <td class="px-6 py-4">
                                                <div class="flex items-center gap-3">
                                                    <div class="size-10 rounded-xl bg-slate-50 border border-slate-100 flex items-center justify-center shrink-0 group-hover:bg-white transition-colors overflow-hidden">
                                                        <img v-if="asset.image" :src="asset.image" class="size-full object-cover" />
                                                        <component v-else :is="asset.type === 'hardware' ? HardDrive : (asset.type === 'license' ? Key : Plug)" class="size-4 text-slate-300 group-hover:text-[#003628] transition-colors" />
                                                    </div>
                                                    <div>
                                                        <p class="text-sm font-bold text-slate-900 leading-tight">{{ asset.name }}</p>
                                                        <div class="flex items-center gap-1.5 mt-1.5">
                                                            <span :class="[
                                                                'px-1.5 py-0.5 rounded text-[8px] font-black uppercase tracking-widest border',
                                                                getCategoryStyle(asset.type)
                                                            ]">
                                                                {{ getCategoryLabel(asset.type) }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Type / Category -->
                                            <td class="px-4 py-4">
                                                <div class="flex flex-col">
                                                    <span class="text-[11px] font-bold text-slate-600 truncate max-w-[160px]">{{ asset.category || '-' }}</span>
                                                    <span class="text-[9px] font-medium text-slate-300 uppercase tracking-tighter">{{ asset.manufacturer || '' }}</span>
                                                </div>
                                            </td>

                                            <!-- Qty -->
                                            <td class="px-4 py-4 text-center">
                                                <span class="text-[11px] font-black text-slate-400 tabular-nums">1</span>
                                            </td>

                                            <!-- Condition -->
                                            <td class="px-4 py-4">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-slate-50 text-slate-500 text-[10px] font-bold border border-slate-100">
                                                    Good
                                                </span>
                                            </td>

                                            <!-- Serial No -->
                                            <td class="px-4 py-4">
                                                <div class="flex flex-col gap-1">
                                                    <span class="text-[11px] font-bold text-slate-900 font-mono tracking-tight flex items-center gap-1.5">
                                                        <KeyRound class="size-3 opacity-30 shrink-0" /> {{ asset.serial }}
                                                    </span>
                                                </div>
                                            </td>

                                            <!-- Asset Tag -->
                                            <td class="px-4 py-4">
                                                <span class="text-[11px] font-bold text-slate-500 font-mono flex items-center gap-1.5">
                                                    <Hash class="size-3 opacity-30" /> {{ asset.asset_tag }}
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                
                                <div v-if="assets.length === 0" class="py-32 text-center bg-slate-50/30">
                                    <Package class="size-16 text-slate-100 mx-auto mb-4" />
                                    <p class="text-[10px] font-black text-slate-300 uppercase tracking-[0.2em]">No assets discovered</p>
                                </div>
                            </div>
                            
                            <div v-if="assets.length > 0" class="flex items-center justify-between px-1">
                                <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest">
                                    {{ assets.length }} total items · sync with snipe-it production
                                </p>
                            </div>
                        </div>

                        <!-- Tab: Habis Pakai -->
                        <div v-if="activeTab === 'consumables'" class="space-y-6 animate-in fade-in duration-500">
                            <div class="flex items-center justify-between px-1">
                                <div class="flex items-center gap-3">
                                    <div class="h-4 w-1 rounded-full bg-amber-500" />
                                    <h3 class="text-[10px] font-black uppercase tracking-[0.2em] text-amber-600">Consumables Records</h3>
                                </div>
                                <span class="px-3 py-1 rounded-full bg-slate-900 text-white text-[9px] font-black tabular-nums shadow-lg shadow-slate-900/10">{{ consumables.length }} ITEMS GIVEN</span>
                            </div>

                            <div class="rounded-2xl border border-slate-100 overflow-hidden bg-white shadow-sm overflow-x-auto">
                                <table class="w-full text-left border-collapse" style="min-width: 800px">
                                    <thead class="bg-slate-50/50 border-b border-slate-100">
                                        <tr>
                                            <th class="px-6 py-4 text-[9px] font-black uppercase tracking-widest text-slate-400 w-[300px]">Item Name</th>
                                            <th class="px-4 py-4 text-[9px] font-black uppercase tracking-widest text-slate-400">Category</th>
                                            <th class="px-4 py-4 text-[9px] font-black uppercase tracking-widest text-slate-400 text-center w-24">Date Given</th>
                                            <th class="px-4 py-4 text-[9px] font-black uppercase tracking-widest text-slate-400 w-48">Reference</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-50">
                                        <tr v-for="item in consumables" :key="item.id" class="hover:bg-slate-50/50 transition-colors group">
                                            <td class="px-6 py-4">
                                                <div class="flex items-center gap-3">
                                                    <div class="size-10 rounded-xl bg-slate-50 border border-slate-100 flex items-center justify-center shrink-0">
                                                        <Package class="size-4 text-slate-300" />
                                                    </div>
                                                    <div>
                                                        <p class="text-sm font-bold text-slate-900 leading-tight">{{ item.name }}</p>
                                                        <p class="text-[9px] font-medium text-slate-400 uppercase tracking-tighter mt-1">{{ item.manufacturer || 'System Consumable' }}</p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-4">
                                                <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-600 text-[9px] font-bold uppercase tracking-widest">{{ item.category || '-' }}</span>
                                            </td>
                                            <td class="px-4 py-4 text-center">
                                                <span class="text-[11px] font-bold text-slate-500 tabular-nums">{{ item.created_at || '-' }}</span>
                                            </td>
                                            <td class="px-4 py-4">
                                                <span class="text-[11px] font-medium text-slate-400 italic truncate max-w-[180px]">{{ item.notes || '-' }}</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                <div v-if="consumables.length === 0" class="py-24 text-center">
                                    <ShoppingCart class="size-12 text-slate-100 mx-auto mb-4" />
                                    <p class="text-[10px] font-black text-slate-300 uppercase tracking-[0.2em]">No consumables recorded</p>
                                </div>
                            </div>
                        </div>

                        <!-- Tab: Dokumen File -->
                        <div v-if="activeTab === 'files'" class="space-y-6 animate-in fade-in duration-500">
                            <div class="flex items-center justify-between px-1">
                                <div class="flex items-center gap-3">
                                    <div class="h-4 w-1 rounded-full bg-blue-500" />
                                    <h3 class="text-[10px] font-black uppercase tracking-[0.2em] text-blue-600">Uploaded Documents</h3>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div v-for="file in files" :key="file.id" class="p-4 rounded-[24px] border border-slate-100 bg-white hover:shadow-xl hover:shadow-slate-200/40 transition-all group flex items-center gap-4">
                                    <div class="size-12 rounded-2xl bg-blue-50 text-blue-500 flex items-center justify-center group-hover:bg-blue-500 group-hover:text-white transition-colors shrink-0">
                                        <FileText class="size-5" />
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-bold text-slate-900 truncate">{{ file.filename }}</p>
                                        <p class="text-[10px] text-slate-400 font-medium mt-0.5">{{ file.created_at }} · {{ file.filesize || 'Unknown size' }}</p>
                                    </div>
                                    <a :href="file.file_url" target="_blank" class="size-10 rounded-xl bg-slate-50 text-slate-400 flex items-center justify-center hover:bg-slate-900 hover:text-white transition-all">
                                        <Download class="size-4" />
                                    </a>
                                </div>

                                <div v-if="files.length === 0" class="col-span-full py-24 text-center rounded-[32px] border border-dashed border-slate-100 bg-slate-50/20">
                                    <Layers class="size-12 text-slate-100 mx-auto mb-4" />
                                    <p class="text-[10px] font-black text-slate-300 uppercase tracking-[0.2em]">No documents found</p>
                                </div>
                            </div>
                        </div>

                        <!-- Tab: Log Aktivitas -->
                        <div v-if="activeTab === 'history'" class="space-y-6 animate-in fade-in duration-500">
                            <div class="flex items-center justify-between px-1">
                                <div class="flex items-center gap-3">
                                    <div class="h-4 w-1 rounded-full bg-slate-900" />
                                    <h3 class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-900">Comprehensive Activity Log</h3>
                                </div>
                            </div>

                            <div class="rounded-2xl border border-slate-100 overflow-hidden bg-white shadow-sm overflow-x-auto">
                                <table class="w-full text-left border-collapse" style="min-width: 800px">
                                    <thead class="bg-slate-50/50 border-b border-slate-100">
                                        <tr>
                                            <th class="px-6 py-4 text-[9px] font-black uppercase tracking-widest text-slate-400 w-48">Timestamp</th>
                                            <th class="px-4 py-4 text-[9px] font-black uppercase tracking-widest text-slate-400 w-32">Action</th>
                                            <th class="px-4 py-4 text-[9px] font-black uppercase tracking-widest text-slate-400">Target Item</th>
                                            <th class="px-4 py-4 text-[9px] font-black uppercase tracking-widest text-slate-400">Note / Reference</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-50">
                                        <tr v-for="log in history" :key="log.id" class="hover:bg-slate-50/50 transition-colors group">
                                            <td class="px-6 py-4">
                                                <div class="flex items-center gap-2">
                                                    <Clock class="size-3 text-slate-300" />
                                                    <span class="text-[11px] font-bold text-slate-500 tabular-nums">{{ log.created_at?.formatted || log.created_at }}</span>
                                                </div>
                                            </td>
                                            <td class="px-4 py-4">
                                                <span :class="[
                                                    'px-2 py-0.5 rounded-full text-[8px] font-black uppercase tracking-widest border',
                                                    log.action_type === 'checkout' ? 'bg-emerald-50 text-emerald-700 border-emerald-100' :
                                                    log.action_type === 'checkin' ? 'bg-blue-50 text-blue-700 border-blue-100' :
                                                    'bg-slate-100 text-slate-600 border-slate-200'
                                                ]">
                                                    {{ log.action_type }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-4">
                                                <p class="text-[11px] font-bold text-slate-800">{{ log.item?.name || 'System Object' }}</p>
                                                <p class="text-[9px] font-medium text-slate-400 uppercase tracking-tighter mt-0.5">{{ log.item?.type || '' }}</p>
                                            </td>
                                            <td class="px-4 py-4">
                                                <span class="text-[11px] font-medium text-slate-400 italic">{{ log.note || '-' }}</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                <div v-if="history.length === 0" class="py-24 text-center">
                                    <Activity class="size-12 text-slate-100 mx-auto mb-4" />
                                    <p class="text-[10px] font-black text-slate-300 uppercase tracking-[0.2em]">No activity logs found</p>
                                </div>
                            </div>
                        </div>

                        <!-- Tab: Keamanan -->
                        <div v-if="activeTab === 'security'" class="max-w-xl animate-in fade-in duration-500">
                            <div class="mb-10 flex items-center gap-4">
                                <div class="size-12 rounded-2xl bg-[#003628]/5 flex items-center justify-center text-[#003628]">
                                    <Shield class="size-6" />
                                </div>
                                <h3 class="text-base font-bold text-slate-900">Security Control</h3>
                            </div>

                            <form @submit.prevent="submitPassword" class="space-y-6">
                                <div class="space-y-4">
                                    <div class="space-y-1.5">
                                        <label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest ml-1">Current Password</label>
                                        <input v-model="passwordForm.current_password" type="password" class="w-full h-11 px-4 rounded-xl border border-slate-100 bg-slate-50/50 text-sm font-bold focus:bg-white focus:border-[#003628] focus:ring-4 focus:ring-[#003628]/5 outline-none transition-all" required />
                                        <p v-if="passwordForm.errors.current_password" class="text-[9px] font-bold text-red-500 uppercase ml-1 mt-1">{{ passwordForm.errors.current_password }}</p>
                                    </div>
                                    <div class="grid grid-cols-2 gap-6">
                                        <div class="space-y-1.5">
                                            <label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest ml-1">New Password</label>
                                            <input v-model="passwordForm.password" type="password" class="w-full h-11 px-4 rounded-xl border border-slate-100 bg-slate-50/50 text-sm font-bold focus:bg-white focus:border-[#003628] focus:ring-4 focus:ring-[#003628]/5 outline-none transition-all" required />
                                        </div>
                                        <div class="space-y-1.5">
                                            <label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest ml-1">Verify Password</label>
                                            <input v-model="passwordForm.password_confirmation" type="password" class="w-full h-11 px-4 rounded-xl border border-slate-100 bg-slate-50/50 text-sm font-bold focus:bg-white focus:border-[#003628] focus:ring-4 focus:ring-[#003628]/5 outline-none transition-all" required />
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" :disabled="passwordForm.processing" class="h-11 px-8 rounded-xl bg-[#003628] text-white text-[10px] font-black uppercase tracking-widest shadow-lg shadow-[#003628]/10 hover:brightness-110 active:scale-95 transition-all">
                                    Sync Passphrase
                                </button>
                            </form>
                        </div>
                    </div>
                </transition>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>

<style scoped>
:deep(.max-w-7xl) {
    max-width: none !important;
    padding: 0 !important;
}

.scrollbar-hide::-webkit-scrollbar {
    display: none;
}
.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
