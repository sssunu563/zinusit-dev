<script setup lang="ts">
import { Head, useForm, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import { 
    LucideAtSign as AtSign, 
    LucideBadgeCheck as BadgeCheck, 
    LucideCamera as Camera, 
    LucideChevronRight as ChevronRight, 
    LucideDownload as Download, 
    LucideExternalLink as ExternalLink, 
    LucideFile as FileIcon, 
    LucideGlobe as Globe, 
    LucideHardDrive as HardDrive, 
    LucideHash as Hash, 
    LucideKey as Key, 
    LucideLoader2 as Loader2, 
    LucideMail as Mail, 
    LucideMapPin as MapPin, 
    LucidePencil as Pencil, 
    LucidePhone as Phone, 
    LucideRotateCcw as RotateCcw, 
    LucideShieldCheck as ShieldCheck, 
    LucideTrash2 as Trash2, 
    LucideUserCog as UserCog, 
    LucideX as X,
    LucideCheck as Check,
    LucideHistory as HistoryIcon,
    LucideInfo as InfoIcon,
    LucidePackage as PackageIcon,
    LucideActivity as ActivityIcon,
    LucideBuilding as Building,
    LucideFileText as FileText,
    LucideEye as Eye,
    LucidePlus as Plus,
    LucideUsers as UsersIcon
} from 'lucide-vue-next';
import { computed, ref, onMounted } from 'vue';
import { getCategoryLabel, getCategoryStyle } from '@/constants/categories';
import AppLayout from '@/layouts/AppLayout.vue';
import HandoverModal from '@/pages/Asset/Partials/HandoverModal.vue';
import UserForm from '@/pages/Users/Partials/UserForm.vue';
import AssetDetailSheet from '@/pages/Asset/Partials/AssetDetailSheet.vue';
import type { BreadcrumbItem } from '@/types';

interface ProfileUser {
    id: number;
    name: string;
    first_name?: string;
    last_name?: string;
    username: string;
    email: string;
    jobtitle?: string;
    employee_num?: string;
    avatar?: string;
    phone?: string;
    mobile?: string;
    website?: string;
    notes?: string;
    activated: boolean;
    company_name?: string;
    department_name?: string;
    location_name?: string;
    manager_name?: string;
    created_at: string;
    updated_at: string;
}

interface UserAsset {
    id: number;
    asset_tag: string;
    name: string;
    model_name?: string;
    category_name?: string;
    serial?: string;
    inventory_number?: string;
    status_name: string;
    status_type: string;
    type: 'hardware' | 'license' | 'accessory';
    image?: string;
    checkout_at?: string;
    stb_id?: number;
}

interface UserFile {
    id: number;
    filename: string;
    url: string;
    notes?: string;
    created_at: string;
}

interface UserHistory {
    id: number;
    action_type: string;
    note?: string;
    created_at: string;
    created_by: string;
    item_name?: string;
    target_name?: string;
    file_url?: string;
}

interface LocalDoc {
    id: number;
    type: string;
    movement: string;
    status: string;
    deliver_date?: string;
    items: string;
    url: string;
}

interface Props {
    user: ProfileUser;
    assets: UserAsset[];
    consumables: any[];
    eulas: any[];
    managed_users: any[];
    managed_locations: any[];
    files: UserFile[];
    history: UserHistory[];
    local_docs: LocalDoc[];
    options: Record<string, any>;
    metadata: any;
    status?: string;
    error?: string;
}

const props = defineProps<Props>();

const isHandoverModalOpen = ref(false);
const handoverType = ref('assets');

const openHandover = (type: string) => {
    handoverType.value = type;
    isHandoverModalOpen.value = true;
};

const handleHandoverSuccess = () => {
    isHandoverModalOpen.value = false;
    router.reload({ preserveScroll: true });
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Direktori Identitas', href: '/users' },
    { title: props.user.name, href: `/users/${props.user.id}` },
];

const tabs = [
    { id: 'info', label: 'Info', icon: InfoIcon },
    { id: 'assets', label: 'Asset', icon: PackageIcon },
    { id: 'consumables', label: 'Habis Pakai', icon: ActivityIcon },
    { id: 'file_uploads', label: 'File Upload', icon: FileIcon },
    { id: 'history', label: 'Riwayat', icon: HistoryIcon },
];

const activeTab = ref('info');
const isEditModalOpen = ref(false);
const isUploadModalOpen = ref(false);
const isPasswordModalOpen = ref(false);
const isUploadingAvatar = ref(false);
const isFetchingEditData = ref(false);

const selectedAssetId = ref<number | null>(null);
const selectedAssetType = ref<string | null>(null);
const isAssetDetailOpen = ref(false);

const showAssetDetail = (asset: any) => {
    selectedAssetId.value = asset.id;
    selectedAssetType.value = asset.type || 'hardware';
    isAssetDetailOpen.value = true;
};
const avatarInput = ref<HTMLInputElement | null>(null);
const historyVisibleCount = ref(50);

const paginatedHistory = computed(() => props.history.slice(0, historyVisibleCount.value));
const historyHasMore = computed(() => historyVisibleCount.value < props.history.length);
const loadMoreHistory = () => { historyVisibleCount.value += 50; };

const fullName = computed(() => props.user.name);
const shortName = computed(() => props.user.name.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase());

const uploadForm = useForm({
    file: null as File | null,
    notes: '',
});

const passwordForm = useForm({
    password: '',
    password_confirmation: '',
});

const editInitialValues = ref<Record<string, any>>({});

const hasAssignments = computed(() => 
    props.assets.length > 0 || 
    props.consumables.length > 0
);

const getTabCount = (tabId: string): number | undefined => {
    switch (tabId) {
        case 'assets': return props.assets.length;
        case 'consumables': return props.consumables.length;
        case 'file_uploads': return props.files.length;
        case 'history': return props.history.length;
        default: return undefined;
    }
};

const getStatusClass = (status: string) => {
    switch(status.toLowerCase()) {
        case 'ready': return 'bg-primary/5 text-primary border-primary/10';
        case 'pending': return 'bg-amber-50 text-amber-500 border-amber-100';
        case 'deployed': return 'bg-blue-50 text-blue-500 border-blue-100';
        default: return 'bg-slate-50 text-slate-500 border-slate-100';
    }
};

const getHistoryActionClass = (action: string) => {
    switch(action.toLowerCase()) {
        case 'created': return 'bg-primary/5 text-primary border-primary/10';
        case 'deleted': return 'bg-red-50 text-red-500 border-red-100';
        case 'updated': return 'bg-amber-50 text-amber-500 border-amber-100';
        case 'stb_complete': return 'bg-indigo-50 text-indigo-500 border-indigo-100';
        default: return 'bg-slate-50 text-slate-500 border-slate-100';
    }
};

const getHistoryActionLabel = (action: string) => {
    switch(action.toLowerCase()) {
        case 'created': return 'Dibuat';
        case 'deleted': return 'Dihapus';
        case 'updated': return 'Diperbarui';
        case 'stb_complete': return 'STB Selesai';
        default: return action;
    }
};

const getRelativeDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric' 
    });
};

const triggerAvatarPicker = () => avatarInput.value?.click();

const handleAvatarChange = async (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file) return;

    isUploadingAvatar.value = true;
    const formData = new FormData();
    formData.append('avatar', file);
    formData.append('_method', 'POST');

    try {
        await axios.post(`/users/${props.user.id}/avatar`, formData);
        window.location.reload();
    } catch (err) {
        console.error('Avatar update failed:', err);
        isUploadingAvatar.value = false;
    }
};

const openEditModal = async () => {
    isFetchingEditData.value = true;
    try {
        const res = await axios.get(`/users/${props.user.id}/edit`);
        editInitialValues.value = res.data.user;
        isEditModalOpen.value = true;
    } catch (err) {
        console.error('Fetch failed:', err);
    } finally {
        isFetchingEditData.value = false;
    }
};

const handleEditSuccess = () => {
    isEditModalOpen.value = false;
    window.location.reload();
};

const handleUpload = () => {
    uploadForm.post(`/users/${props.user.id}/upload`, {
        onSuccess: () => {
            isUploadModalOpen.value = false;
            uploadForm.reset();
        }
    });
};

const handlePasswordSubmit = () => {
    passwordForm.post(`/users/${props.user.id}/password`, {
        onSuccess: () => {
            isPasswordModalOpen.value = false;
            passwordForm.reset();
        }
    });
};

const handleCheckin = (type: string, id: number, name: string) => {
    if (confirm(`Recall ${name}? This action will return the entity to central stock.`)) {
        axios.post(`/users/${props.user.id}/checkin/${type}/${id}`)
            .then(() => window.location.reload());
    }
};

const downloadFile = (url: string) => window.open(url, '_blank');

const confirmDelete = () => {
    if (confirm(`PERMANENTLY PURGE ${props.user.name}? This action cannot be undone.`)) {
        axios.delete(`/users/${props.user.id}`)
            .then(() => window.location.href = '/users');
    }
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="user.name" />

        <!-- Main Application Shell -->
        <div class="app-page-shell">
            <div class="bg-white rounded-[32px] border border-slate-200 shadow-xl shadow-slate-200/40 overflow-hidden h-[800px]">
                <div class="grid grid-cols-1 xl:grid-cols-12 h-full">
                    
                    <!-- LEFT SIDEBAR: PROFILE CARD -->
                    <div class="xl:col-span-3 lg:col-span-4 flex flex-col p-8 border-b xl:border-b-0 xl:border-r border-slate-100 bg-slate-50/30 relative overflow-y-auto h-full">
                        <div class="absolute top-0 right-0 -mr-16 -mt-16 h-64 w-64 rounded-full bg-primary/5 blur-[100px] pointer-events-none" />
                            
                            <div class="relative z-10 flex flex-col items-center text-center gap-6">
                                <!-- Avatar Shield -->
                                <div class="relative group">
                                    <div class="size-32 rounded-3xl overflow-hidden border-4 border-slate-50 bg-slate-100 shadow-xl ring-1 ring-slate-200 group-hover:ring-[#003628]/30 transition-all duration-500 shrink-0">
                                        <img v-if="user.avatar" :src="user.avatar" class="h-full w-full object-cover transition-transform group-hover:scale-110" />
                                        <div v-else class="h-full w-full flex items-center justify-center text-4xl font-black text-[#003628] bg-[#003628]/5 italic tabular-nums">
                                            {{ shortName }}
                                        </div>
                                        <div v-if="isUploadingAvatar" class="absolute inset-0 bg-white/60 flex items-center justify-center backdrop-blur-sm">
                                            <Loader2 class="size-6 animate-spin text-[#003628]" />
                                        </div>
                                    </div>

                                    <button @click="triggerAvatarPicker" class="absolute -bottom-2 -right-2 size-10 rounded-xl bg-[#003628] border border-white flex items-center justify-center text-white shadow-lg shadow-[#003628]/30 hover:brightness-105 transition-all active:scale-90">
                                        <Camera class="size-5" />
                                    </button>
                                    <input type="file" ref="avatarInput" class="hidden" accept="image/*" @change="handleAvatarChange" />
                                </div>

                                <div class="space-y-1">
                                    <h1 class="text-2xl font-black tracking-tight text-slate-900 leading-tight">
                                        {{ fullName }}
                                    </h1>
                                    <p class="text-sm font-black text-[#003628] italic tracking-tight">{{ [user.department_name, user.location_name].filter(Boolean).join(' - ') || '-' }}</p>
                                </div>

                                <div class="flex flex-col gap-3 w-full">
                                    <div class="flex items-center gap-3 px-4 py-2.5 rounded-2xl border border-slate-100 bg-slate-50/50 w-full group">
                                        <AtSign class="size-4 text-slate-400 shrink-0 group-hover:text-[#003628] transition-colors" />
                                        <span class="text-[13px] font-bold text-slate-600 truncate">{{ user.username }}</span>
                                    </div>
                                    <div class="flex items-center gap-3 px-4 py-2.5 rounded-2xl border border-slate-100 bg-slate-50/50 w-full group">
                                        <Mail class="size-4 text-slate-400 shrink-0 group-hover:text-[#003628] transition-colors" />
                                        <a :href="`mailto:${user.email}`" class="text-[13px] font-bold text-slate-600 truncate hover:text-[#003628] hover:underline">{{ user.email }}</a>
                                    </div>
                                    <div class="flex items-center gap-3 px-4 py-2.5 rounded-2xl border border-slate-100 bg-slate-50/50 w-full group">
                                        <Building class="size-4 text-slate-400 shrink-0 group-hover:text-[#003628] transition-colors" />
                                        <span class="text-[13px] font-bold text-slate-500 truncate">{{ user.company_name || '-' }}</span>
                                    </div>
                                </div>

                                <!-- Status Flags -->
                                <div class="flex flex-wrap justify-center gap-2 w-full pt-4 border-t border-slate-100">
                                     <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl border text-[10px] font-black uppercase tracking-widest shadow-sm" :class="user.activated ? 'bg-[#003628]/5 text-[#003628] border-[#003628]/20' : 'bg-red-50 text-red-500 border-red-200/50'">
                                        <ShieldCheck v-if="user.activated" class="size-3" /> 
                                        <X v-else class="size-3" />
                                        {{ user.activated ? 'Aktif' : 'Nonaktif' }}
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="flex flex-col gap-2 w-full pt-2">
                                    <button 
                                        @click="openEditModal" 
                                        class="h-11 rounded-2xl bg-slate-50 border border-slate-200 flex items-center justify-center gap-2 text-slate-700 text-[13px] font-bold hover:bg-slate-100 hover:border-slate-300 transition-all active:scale-95 w-full shadow-sm"
                                        :disabled="isFetchingEditData"
                                    >
                                        <Pencil class="size-4 text-amber-600" /> 
                                        <span>Modifikasi Profil</span>
                                    </button>
                                    <div class="grid grid-cols-2 gap-2">
                                        <button 
                                            @click="isPasswordModalOpen = true" 
                                            class="h-11 rounded-2xl bg-slate-50 border border-slate-200 flex items-center justify-center text-slate-500 hover:text-slate-900 hover:bg-slate-100 hover:border-slate-300 transition-all active:scale-95 shadow-sm"
                                            title="Reset Kredensial"
                                        >
                                            <Key class="size-4" /> <span class="text-[12px] font-bold ml-2">Reset</span>
                                        </button>
                                        <button 
                                            @click="confirmDelete" 
                                            class="h-11 rounded-2xl bg-red-50 border border-red-100 flex items-center justify-center gap-2 text-red-500 text-[12px] font-bold hover:bg-red-500 hover:text-white transition-all active:scale-95 disabled:opacity-50 shadow-sm" 
                                            :disabled="hasAssignments"
                                            :title="hasAssignments ? 'Kembalikan semua item terlebih dahulu' : 'Hapus Identitas'"
                                        >
                                            <Trash2 class="size-4" /> <span>Hapus</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                    </div>

                    <!-- RIGHT MAIN CONTENT (Unified Container) -->
                    <div class="xl:col-span-9 lg:col-span-8 flex flex-col h-full overflow-hidden">
                        
                        <!-- Notifications -->
                        <div class="px-8 pt-8 pb-0 flex flex-col gap-4">
                            <div v-if="status" class="p-4 rounded-2xl border border-[#003628]/20 bg-[#003628]/5 flex items-center justify-center gap-2 text-[#003628] text-[11px] font-bold uppercase tracking-widest shadow-sm">
                                <BadgeCheck class="size-4" /> {{ status }}
                            </div>
                            <div v-if="error" class="p-4 rounded-2xl border border-red-500/20 bg-red-500/5 flex items-center justify-center gap-2 text-red-500 text-[11px] font-bold uppercase tracking-widest shadow-sm">
                                <X class="size-4" /> {{ error }}
                            </div>
                        </div>

                        <!-- Main Unified Console -->
                        <div class="flex flex-col flex-1 overflow-hidden">
                            <!-- Tab Navigation (Internal) -->
                            <div class="px-8 pt-8 pb-4 border-b border-slate-50">
                                <nav class="flex items-center gap-1 overflow-x-auto no-scrollbar pb-2">
                                    <button 
                                        v-for="t in tabs" 
                                        :key="t.id"
                                        @click="activeTab = t.id"
                                        class="h-10 px-6 rounded-xl transition-all flex items-center gap-2 shrink-0"
                                        :class="activeTab === t.id 
                                            ? 'bg-slate-900 text-white shadow-lg' 
                                            : 'bg-transparent text-slate-400 hover:bg-slate-50 hover:text-slate-900'"
                                    >
                                        <component :is="t.icon" class="size-3.5" />
                                        <span class="text-[11px] font-black uppercase tracking-widest">{{ t.label }}</span>
                                        <span v-if="getTabCount(t.id) !== undefined" class="ml-1 rounded-full px-1.5 py-0.5 text-[9px] font-black" :class="activeTab === t.id ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-400'">{{ getTabCount(t.id) }}</span>
                                    </button>
                                </nav>
                            </div>

                            <!-- Tab Content (Internal) -->
                            <div class="p-8 flex-1 relative overflow-y-auto">
                                <!-- INFO PANELS -->
                                <div v-if="activeTab === 'info'" class="animate-in fade-in slide-in-from-bottom-4 duration-500 max-w-4xl space-y-12">
                                    <!-- Identity Verification -->
                                    <section>
                                        <h3 class="text-[10px] font-black uppercase tracking-[0.2em] text-primary mb-6 flex items-center gap-2">
                                            <BadgeCheck class="size-3" /> Identity Verification
                                        </h3>
                                        <div class="divide-y divide-slate-50 border-t border-slate-50">
                                            <div class="flex items-center justify-between py-4 group">
                                                <div class="flex items-center gap-3">
                                                    <BadgeCheck class="size-3.5 text-slate-200 group-hover:text-primary transition-colors" />
                                                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">Nama Lengkap</span>
                                                </div>
                                                <span class="text-sm font-black text-slate-800">{{ user.name }}</span>
                                            </div>
                                            <div class="flex items-center justify-between py-4 group">
                                                <div class="flex items-center gap-3">
                                                    <AtSign class="size-3.5 text-slate-200 group-hover:text-primary transition-colors" />
                                                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">Username</span>
                                                </div>
                                                <span class="text-sm font-black text-slate-700 font-mono">@{{ user.username }}</span>
                                            </div>
                                            <div class="flex items-center justify-between py-4 group">
                                                <div class="flex items-center gap-3">
                                                    <Globe class="size-3.5 text-slate-200 group-hover:text-primary transition-colors" />
                                                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">Email Address</span>
                                                </div>
                                                <a :href="`mailto:${user.email}`" class="text-sm font-black text-primary hover:underline italic">{{ user.email }}</a>
                                            </div>
                                            <div v-if="user.employee_num" class="flex items-center justify-between py-4 group">
                                                <div class="flex items-center gap-3">
                                                    <Hash class="size-3.5 text-slate-200 group-hover:text-primary transition-colors" />
                                                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">Employee Number</span>
                                                </div>
                                                <span class="text-sm font-black text-slate-800 tabular-nums">{{ user.employee_num }}</span>
                                            </div>
                                        </div>
                                    </section>

                                    <!-- Organization Context -->
                                    <section>
                                        <h3 class="text-[10px] font-black uppercase tracking-[0.2em] text-amber-600 mb-6 flex items-center gap-2">
                                            <Building class="size-3" /> Organization Context
                                        </h3>
                                        <div class="divide-y divide-slate-50 border-t border-slate-50">
                                            <div class="flex items-center justify-between py-4 group">
                                                <div class="flex items-center gap-3">
                                                    <Building class="size-3.5 text-slate-200 group-hover:text-amber-500 transition-colors" />
                                                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">Perusahaan</span>
                                                </div>
                                                <span class="text-sm font-black text-slate-800">{{ user.company_name || '-' }}</span>
                                            </div>
                                            <div class="flex items-center justify-between py-4 group">
                                                <div class="flex items-center gap-3">
                                                    <Briefcase class="size-3.5 text-slate-200 group-hover:text-amber-500 transition-colors" />
                                                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">Departemen</span>
                                                </div>
                                                <span class="text-sm font-black text-slate-800">{{ user.department_name || '-' }}</span>
                                            </div>
                                            <div class="flex items-center justify-between py-4 group">
                                                <div class="flex items-center gap-3">
                                                    <MapPin class="size-3.5 text-slate-200 group-hover:text-amber-500 transition-colors" />
                                                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">Lokasi Utama</span>
                                                </div>
                                                <span class="text-sm font-black text-slate-800">{{ user.location_name || '-' }}</span>
                                            </div>
                                            <div class="flex items-center justify-between py-4 group">
                                                <div class="flex items-center gap-3">
                                                    <UserCog class="size-3.5 text-slate-200 group-hover:text-amber-500 transition-colors" />
                                                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">Jabatan / Role</span>
                                                </div>
                                                <span class="text-sm font-black text-slate-800">{{ user.jobtitle || '-' }}</span>
                                            </div>
                                        </div>
                                    </section>

                                    <!-- Account Status Indicators -->
                                    <section>
                                        <h3 class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 mb-6 flex items-center gap-2">
                                            <ShieldCheck class="size-3" /> Account Configuration
                                        </h3>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            <div class="p-4 rounded-2xl border border-slate-100 bg-slate-50/50 flex flex-col gap-1">
                                                <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">LDAP Sync</span>
                                                <div class="flex items-center gap-2">
                                                    <Check v-if="user.ldap_import" class="size-3.5 text-emerald-600" />
                                                    <X v-else class="size-3.5 text-slate-300" />
                                                    <span class="text-xs font-black">{{ user.ldap_import ? 'AKTIF' : 'NONAKTIF' }}</span>
                                                </div>
                                            </div>
                                            <div class="p-4 rounded-2xl border border-slate-100 bg-slate-50/50 flex flex-col gap-1">
                                                <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">Login State</span>
                                                <div class="flex items-center gap-2">
                                                    <Check v-if="user.activated" class="size-3.5 text-emerald-600" />
                                                    <X v-else class="size-3.5 text-slate-300" />
                                                    <span class="text-xs font-black">{{ user.activated ? 'ENABLED' : 'DISABLED' }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </section>
                                </div>

                        <!-- UNIFIED ASSETS TABLE -->
                        <div v-if="activeTab === 'assets'" class="animate-in fade-in slide-in-from-bottom-4 duration-500">
                             <div class="flex items-center justify-between mb-4 px-1">
                                <div class="flex items-center gap-3">
                                    <div class="h-4 w-1 rounded-full bg-primary" />
                                    <h3 class="text-[10px] font-black uppercase tracking-[0.2em] text-primary">Daftar Inventaris & Aset Digital</h3>
                                </div>
                                <span class="px-3 py-1 rounded-full bg-slate-900 text-white text-[9px] font-black tabular-nums">{{ assets.length }} REGISTERED</span>
                             </div>

                             <div class="rounded-xl border border-slate-100 overflow-hidden overflow-x-auto">
                                <table class="w-full text-left border-collapse" style="min-width: 720px">
                                    <thead class="bg-slate-50 border-b border-slate-100">
                                        <tr>
                                            <th class="px-3 py-2 text-[9px] font-black uppercase tracking-widest text-slate-400 w-[180px]">Asset Name</th>
                                            <th class="px-2 py-2 text-[9px] font-black uppercase tracking-widest text-slate-400 w-[130px]">Type</th>
                                            <th class="px-2 py-2 text-[9px] font-black uppercase tracking-widest text-slate-400 w-10 text-center">QTY</th>
                                            <th class="px-2 py-2 text-[9px] font-black uppercase tracking-widest text-slate-400 w-[90px]">Status</th>
                                            <th class="px-2 py-2 text-[9px] font-black uppercase tracking-widest text-slate-400 w-[130px]">Serial No</th>
                                            <th class="px-2 py-2 text-[9px] font-black uppercase tracking-widest text-slate-400 w-[100px]">Asset</th>
                                            <th class="px-3 py-2 text-[9px] font-black uppercase tracking-widest text-slate-400 text-right w-12">Detail</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-50">
                                        <tr v-if="assets.length === 0">
                                            <td colspan="7" class="py-12 text-center">
                                                <div class="flex flex-col items-center gap-2">
                                                    <PackageIcon class="size-7 text-slate-200" />
                                                    <p class="text-[11px] font-black text-slate-400 uppercase tracking-widest">Tidak ada aset terdaftar</p>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr
                                            v-else
                                            v-for="a in assets"
                                            :key="`${a.type}-${a.id}`"
                                            class="hover:bg-slate-50/60 transition-colors"
                                        >
                                            <!-- Asset Name + Category Badge -->
                                            <td class="px-3 py-2">
                                                <p class="text-[11px] font-bold text-slate-800 leading-tight truncate max-w-[170px]">{{ a.model_name || a.name || '-' }}</p>
                                                <span :class="[
                                                    'inline-flex items-center px-1 py-0.5 rounded border text-[7px] font-black uppercase tracking-widest mt-0.5',
                                                    getCategoryStyle(a.type)
                                                ]">
                                                    {{ getCategoryLabel(a.type) }}
                                                </span>
                                            </td>

                                            <!-- Type (model for hardware, category for others) -->
                                            <td class="px-2 py-2">
                                                <span class="text-[10px] font-medium text-slate-600 truncate block max-w-[120px]">{{ a.name || a.category_name || '-' }}</span>
                                            </td>

                                            <!-- QTY -->
                                            <td class="px-2 py-2 text-center">
                                                <span class="text-[10px] font-bold text-slate-400">1</span>
                                            </td>

                                            <!-- Status -->
                                            <td class="px-2 py-2">
                                                <div class="flex items-center gap-1.5">
                                                    <div class="size-1 rounded-full shrink-0" :class="a.status_type === 'deployable' ? 'bg-emerald-500' : 'bg-amber-500'"></div>
                                                    <span class="text-[9px] font-black uppercase tracking-widest text-slate-500">{{ a.status_name || '-' }}</span>
                                                </div>
                                            </td>

                                            <!-- Serial No -->
                                            <td class="px-2 py-2">
                                                <span class="text-[10px] font-mono text-slate-500 truncate block max-w-[120px]">{{ a.serial || '-' }}</span>
                                            </td>

                                            <!-- Asset Tag -->
                                            <td class="px-2 py-2">
                                                <span class="text-[10px] font-mono text-slate-400">{{ a.asset_tag || '-' }}</span>
                                            </td>

                                            <!-- Detail Button -->
                                            <td class="px-3 py-2 text-right">
                                                <button @click="showAssetDetail(a)" class="h-6 w-6 rounded border border-slate-200 bg-white flex items-center justify-center text-slate-400 hover:text-primary hover:border-primary/30 hover:shadow-sm transition-all inline-flex">
                                                    <ChevronRight class="size-3" />
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                             </div>

                             <p v-if="assets.length > 0" class="text-[9px] text-slate-400 font-bold uppercase tracking-widest px-1 pt-2">
                                {{ assets.length }} total · Hardware, License & Accessories
                             </p>
                        </div>


                        <!-- CONSUMABLES -->
                        <div v-if="activeTab === 'consumables'" class="animate-in fade-in slide-in-from-bottom-4 duration-500">
                             <div class="flex items-center justify-between mb-4 px-2">
                                <h3 class="text-xs font-black uppercase tracking-widest text-slate-400">Log Pengambilan Consumables</h3>
                             </div>

                             <div class="border border-slate-100 rounded-[24px] overflow-hidden">
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left border-collapse">
                                        <thead>
                                            <tr class="bg-slate-50/50 border-b border-slate-100">
                                                <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-slate-400">Item</th>
                                                <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-slate-400 text-center">Qty</th>
                                                <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-slate-400">Tanggal</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-50">
                                            <tr v-for="c in consumables" :key="c.id" class="group hover:bg-slate-50/50 transition-colors">
                                                <td class="px-6 py-4">
                                                    <div class="flex items-center gap-3">
                                                        <div class="size-8 rounded-lg bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-400">
                                                            <ActivityIcon class="size-4" />
                                                        </div>
                                                        <span class="text-[13px] font-black text-slate-900">{{ c.name }}</span>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 text-center">
                                                    <span class="text-[13px] font-black text-primary">{{ c.qty }}</span>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <span class="text-[11px] font-bold text-slate-600">{{ c.checkout_at }}</span>
                                                </td>
                                            </tr>
                                            <tr v-if="consumables.length === 0">
                                                <td colspan="3" class="px-6 py-20 text-center">
                                                    <div class="flex flex-col items-center gap-3">
                                                        <div class="size-12 rounded-2xl bg-slate-50 flex items-center justify-center text-slate-300">
                                                            <ActivityIcon class="size-6" />
                                                        </div>
                                                        <p class="text-[11px] font-black uppercase tracking-widest text-slate-400">Tidak ada riwayat consumables</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                             </div>
                        </div>
                        <!-- FILE UPLOADS & OFFICIAL DOCUMENTS -->
                        <div v-if="activeTab === 'file_uploads'" class="animate-in fade-in slide-in-from-bottom-4 duration-500">
                             <div class="flex items-center justify-between mb-4 px-2">
                                <div class="flex items-center gap-3">
                                    <div class="h-4 w-1 rounded-full bg-blue-500" />
                                    <h3 class="text-xs font-black uppercase tracking-widest text-slate-400">Dokumen Digital & Arsip STB</h3>
                                </div>
                                <button @click="isUploadModalOpen = true" class="h-9 px-4 rounded-xl bg-slate-900 text-white text-[10px] font-black uppercase tracking-widest hover:bg-slate-800 transition-all flex items-center gap-2 shadow-lg shadow-slate-900/10">
                                    <Plus class="size-3.5" /> Upload Lampiran
                                </button>
                             </div>

                             <div class="border border-slate-100 rounded-[24px] overflow-hidden">
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left border-collapse">
                                        <thead>
                                            <tr class="bg-slate-50/50 border-b border-slate-100">
                                                <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-slate-400">Informasi Dokumen</th>
                                                <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-slate-400">Jenis / Detail</th>
                                                <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-slate-400">Status</th>
                                                <th class="px-6 py-4 text-right text-[10px] font-black uppercase tracking-widest text-slate-400">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-50">
                                            <!-- Official Local Docs (STB) -->
                                            <tr v-for="d in local_docs" :key="`local-${d.id}`" class="group hover:bg-primary/5 transition-colors">
                                                <td class="px-6 py-4">
                                                    <div class="flex items-center gap-3">
                                                        <div class="size-9 rounded-xl bg-primary/10 border border-primary/20 flex items-center justify-center text-primary">
                                                            <FileText class="size-4" />
                                                        </div>
                                                        <div class="flex flex-col">
                                                            <span class="text-[13px] font-black text-slate-900 truncate max-w-[250px]">{{ d.doc_no }}</span>
                                                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-tighter">{{ d.type === 'loan' ? 'Peminjaman' : 'STB' }} &bull; {{ d.deliver_date || '-' }}</span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <span class="text-[11px] font-bold text-slate-600 truncate max-w-[200px] block">{{ d.items }}</span>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <span :class="[
                                                        'px-2 py-0.5 rounded-lg text-[9px] font-black uppercase tracking-widest border',
                                                        d.status === 'completed' ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 
                                                        d.status === 'cancelled' ? 'bg-red-50 text-red-500 border-red-100' : 
                                                        'bg-amber-50 text-amber-600 border-amber-100'
                                                    ]">
                                                        {{ d.status }}
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4">
                                                     <div class="flex justify-end gap-2">
                                                        <a :href="d.type === 'loan' ? `/peminjaman/${d.id}/print` : `/stb/${d.id}/print`" target="_blank" class="h-8 w-8 rounded-lg border border-primary/20 bg-white flex items-center justify-center text-primary hover:bg-primary hover:text-white transition-all shadow-sm" title="Show Print Page">
                                                            <Eye class="size-3.5" />
                                                        </a>
                                                     </div>
                                                </td>
                                            </tr>

                                            <!-- General Files (Snipe-IT) -->
                                            <tr v-for="f in files" :key="`file-${f.id}`" class="group hover:bg-slate-50/50 transition-colors">
                                                <td class="px-6 py-4">
                                                    <div class="flex items-center gap-3">
                                                        <div class="size-9 rounded-xl bg-blue-50 border border-blue-100 flex items-center justify-center text-blue-500 group-hover:bg-blue-500 group-hover:text-white transition-all">
                                                            <FileIcon class="size-4" />
                                                        </div>
                                                        <div class="flex flex-col">
                                                            <span class="text-[13px] font-black text-slate-900 truncate max-w-[250px]">{{ f.filename }}</span>
                                                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-tighter">{{ f.created_at }}</span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <span class="text-[11px] text-slate-500 italic truncate max-w-[200px] block">{{ f.notes || f.name || '-' }}</span>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <span class="px-2 py-0.5 rounded-lg bg-slate-100 text-slate-400 text-[9px] font-black uppercase tracking-widest border border-slate-200">ATTACHMENT</span>
                                                </td>
                                                <td class="px-6 py-4">
                                                     <div class="flex justify-end gap-2">
                                                        <a :href="f.url" target="_blank" class="h-8 w-8 rounded-lg border border-slate-100 bg-white flex items-center justify-center text-slate-400 hover:text-primary hover:border-primary/20 transition-all shadow-sm" title="Show File">
                                                            <Eye class="size-3.5" />
                                                        </a>
                                                     </div>
                                                </td>
                                            </tr>

                                            <tr v-if="files.length === 0 && local_docs.length === 0">
                                                <td colspan="4" class="px-6 py-20 text-center">
                                                    <div class="flex flex-col items-center gap-3">
                                                        <div class="size-12 rounded-2xl bg-slate-50 flex items-center justify-center text-slate-300">
                                                            <FileText class="size-6" />
                                                        </div>
                                                        <p class="text-[11px] font-black uppercase tracking-widest text-slate-400">Belum ada dokumen atau STB</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                             </div>
                        </div>>

                        <!-- HISTORY TABLE -->
                        <div v-if="activeTab === 'history'" class="animate-in fade-in zoom-in-95 duration-300">
                            <div class="flex items-center gap-3 mb-4 px-2">
                                <div class="h-4 w-1 rounded-full bg-slate-900" />
                                <h3 class="text-xs font-black uppercase tracking-widest text-slate-400">Audit Trail & Log Aktivitas</h3>
                            </div>
                            <div class="overflow-hidden rounded-[24px] border border-slate-100">
                                <table class="w-full border-collapse">
                                <thead>
                                    <tr class="border-b border-slate-100 bg-slate-50/50">
                                        <th class="px-6 py-4 text-left text-[11px] font-black uppercase tracking-widest text-slate-400">Timestamp</th>
                                        <th class="px-6 py-4 text-left text-[11px] font-black uppercase tracking-widest text-slate-400">Operation</th>
                                        <th class="px-6 py-4 text-left text-[11px] font-black uppercase tracking-widest text-slate-400">Context</th>
                                        <th class="px-6 py-4 text-right text-[11px] font-black uppercase tracking-widest text-slate-400">Authorized By</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50">
                                    <tr v-for="h in paginatedHistory" :key="h.id" class="group hover:bg-slate-50/30 transition-colors">
                                        <td class="px-6 py-4 text-[11px] font-black font-mono tabular-nums text-slate-400 leading-none">
                                            {{ h.created_at.split(' ')[0] }}<br/>
                                            <span class="text-[9px] font-bold mt-1 block">{{ h.created_at.split(' ')[1] }}</span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="inline-flex px-2.5 py-1 rounded-lg text-[9px] font-black uppercase tracking-widest border" :class="getHistoryActionClass(h.action_type)">
                                                {{ getHistoryActionLabel(h.action_type) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex flex-col">
                                                <span class="text-[13px] font-bold text-slate-900 tracking-tight">{{ h.item_name || h.target_name || 'System Event' }}</span>
                                                <span v-if="h.note" class="text-[10px] font-bold italic text-slate-400 truncate max-w-[200px]">"{{ h.note }}"</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <span class="text-[11px] font-black uppercase tracking-widest text-slate-400">{{ h.admin_name }}</span>
                                        </td>
                                    </tr>
                                    <tr v-if="history.length === 0">
                                        <td colspan="4" class="px-6 py-20 text-center text-slate-400 italic text-sm">Audit trail clear for this profile.</td>
                                    </tr>
                                </tbody>
                            </table>
                            <!-- Load More footer -->
                            <div v-if="historyHasMore" class="px-6 py-4 border-t border-slate-50 flex items-center justify-between">
                                <span class="text-[11px] font-black uppercase tracking-widest text-slate-400">
                                    Showing {{ paginatedHistory.length }} of {{ history.length }}
                                </span>
                                <button
                                    @click="loadMoreHistory"
                                    class="h-9 px-5 rounded-xl border border-slate-200 bg-white text-[10px] font-black uppercase tracking-widest text-slate-500 hover:text-primary hover:border-primary/30 transition-all active:scale-95 shadow-sm"
                                >
                                    Load 50 More
                                </button>
                            </div>
                            <div v-else-if="history.length > 50" class="px-6 py-3 border-t border-slate-50">
                                <span class="text-[11px] font-black uppercase tracking-widest text-slate-400">
                                    All {{ history.length }} records shown
                                </span>
                            </div>
                        </div>
                        
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

        <AssetDetailSheet 
            :asset-id="selectedAssetId"
            :asset-type="selectedAssetType"
            :asset-type-label="selectedAssetType"
            :open="isAssetDetailOpen"
            @update:open="isAssetDetailOpen = $event"
        />

    <!-- MODALS -->
        <!-- Edit User Modal -->
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="isEditModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 backdrop-blur-md bg-white/60" @click.self="isEditModalOpen = false">
                <Transition
                    enter-active-class="transition duration-300 ease-out"
                    enter-from-class="opacity-0 translate-y-8 scale-95"
                    enter-to-class="opacity-100 translate-y-0 scale-100"
                    leave-active-class="transition duration-200 ease-in"
                    leave-from-class="opacity-100 translate-y-0 scale-100"
                    leave-to-class="opacity-0 translate-y-8 scale-95"
                >
                    <div v-if="isEditModalOpen" class="relative w-full max-w-[940px] max-h-[90vh] overflow-y-auto rounded-[32px] border border-slate-200 bg-white shadow-2xl no-scrollbar">
                         <UserForm
                            :is-modal="true"
                            :title="`Modify Identity: ${user.name}`"
                            :submit-label="'Commit Profile Changes'"
                            :submit-url="`/users/${user.id}`"
                            :method="'put'"
                            :user-id="user.id"
                            :options="options as any"
                            :initial-values="editInitialValues as any"
                            @success="handleEditSuccess"
                        />
                    </div>
                </Transition>
            </div>
        </Transition>

        <!-- Document Upload Modal -->
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="isUploadModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 backdrop-blur-md bg-white/60" @click.self="isUploadModalOpen = false">
                <div class="w-full max-w-md rounded-[32px] border border-slate-200 bg-white p-8 shadow-2xl space-y-8">
                    <header class="flex items-center justify-between">
                         <div class="space-y-1">
                            <h3 class="text-xl font-black text-slate-900 italic tracking-tight">Compliance Archive</h3>
                            <p class="text-[10px] font-black uppercase tracking-widest text-primary">Submit Profile Document</p>
                        </div>
                        <button @click="isUploadModalOpen = false" class="text-slate-400 hover:text-slate-900 transition-colors"><X class="size-5" /></button>
                    </header>

                    <form @submit.prevent="handleUpload" class="space-y-6">
                        <div class="space-y-2">
                            <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 block ml-1">Archive Source</label>
                            <input type="file" @input="uploadForm.file = ($event.target as HTMLInputElement).files?.[0] || null" class="w-full bg-slate-50 border border-slate-100 rounded-2xl p-4 text-xs text-slate-600 file:bg-primary file:border-none file:px-4 file:py-1 file:rounded-lg file:text-white file:font-bold file:text-[10px] file:uppercase file:mr-4 shadow-sm" />
                        </div>
                        <div class="space-y-2">
                             <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 block ml-1">Annotation</label>
                             <textarea v-model="uploadForm.notes" class="w-full p-4 rounded-2xl border border-slate-200 bg-slate-50 text-sm text-slate-900 placeholder:text-slate-400 outline-none focus:border-primary/50 focus:bg-white transition-all resize-none h-24 shadow-sm" placeholder="Provide context for this document..." />
                        </div>
                        <button type="submit" :disabled="uploadForm.processing" class="w-full h-12 rounded-2xl bg-primary text-white text-sm font-bold shadow-lg shadow-primary/20 hover:bg-primary-dark transition-all active:scale-95 disabled:opacity-50">
                            {{ uploadForm.processing ? 'Transmitting...' : 'Commit Upload' }}
                        </button>
                    </form>
                </div>
            </div>
        </Transition>

        <!-- Credentials Reset Modal -->
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="isPasswordModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 backdrop-blur-md bg-white/60" @click.self="isPasswordModalOpen = false">
                <div class="w-full max-w-md rounded-[32px] border border-slate-200 bg-white p-8 shadow-2xl space-y-8">
                    <header class="flex items-center justify-between">
                         <div class="space-y-1">
                            <h3 class="text-xl font-black text-slate-900 italic tracking-tight">Security Protocol</h3>
                            <p class="text-[10px] font-black uppercase tracking-widest text-primary">Override Credentials</p>
                        </div>
                        <button @click="isPasswordModalOpen = false" class="text-slate-400 hover:text-slate-900 transition-colors"><X class="size-5" /></button>
                    </header>

                    <form @submit.prevent="handlePasswordSubmit" class="space-y-6">
                        <div class="space-y-4">
                            <div class="space-y-2">
                                <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 block ml-1">New Passcode</label>
                                <input v-model="passwordForm.password" type="password" class="w-full h-12 px-4 rounded-2xl border border-slate-200 bg-slate-50 text-sm text-slate-900 outline-none focus:border-primary/50 focus:bg-white transition-all shadow-sm" />
                            </div>
                            <div class="space-y-2">
                                <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 block ml-1">Verify Passcode</label>
                                <input v-model="passwordForm.password_confirmation" type="password" class="w-full h-12 px-4 rounded-2xl border border-slate-200 bg-slate-50 text-sm text-slate-900 outline-none focus:border-primary/50 focus:bg-white transition-all shadow-sm" />
                            </div>
                        </div>
                        <button type="submit" :disabled="passwordForm.processing" class="w-full h-12 rounded-2xl bg-amber-500 text-white text-sm font-bold shadow-lg shadow-amber-500/20 hover:bg-amber-600 transition-all active:scale-95 disabled:opacity-50">
                            {{ passwordForm.processing ? 'Syncing...' : 'Override Credentials' }}
                        </button>
                    </form>
                </div>
            </div>
        </Transition>

        <!-- Initial Fetching UI -->
        <div v-if="isFetchingEditData" class="fixed inset-0 z-[60] flex items-center justify-center bg-white/40 backdrop-blur-sm">
            <div class="flex flex-col items-center gap-4 p-8 rounded-[32px] bg-white border border-slate-200 shadow-2xl">
                <Loader2 class="size-8 animate-spin text-primary" />
                <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">Querying identity vault...</span>
            </div>
        </div>

        <!-- Handover Modal -->
        <HandoverModal 
            :show="isHandoverModalOpen"
            :selectedItems="[]"
            :assetType="handoverType"
            :metadata="metadata"
            :initialRecipientId="user.id"
            @close="isHandoverModalOpen = false"
            @success="handleHandoverSuccess"
        />

    </AppLayout>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
  display: none;
}
.no-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>
