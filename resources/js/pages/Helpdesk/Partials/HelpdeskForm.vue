<script setup lang="ts">
/* eslint-disable vue/no-mutating-props */
import { onClickOutside } from '@vueuse/core';
import axios from 'axios';
import {
    ChevronDown,
    Building2,
    MapPin,
    Users2,
    Search,
    User2,
    Wrench,
    AlertCircle,
    Calendar,
    RefreshCw,
    X,
    Check,
    Truck,
    PlusCircle,
} from 'lucide-vue-next';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import {
    ensureSnipeAssetsLoaded,
    getSnipeAssetReferenceValue,
    useSnipeDirectory,
} from '@/composables/useSnipeDirectory';
import { useStbDirectory } from '@/utils/stbDirectory';
import type {
    SnipeAsset,
    SnipeAssetCategory,
} from '@/composables/useSnipeDirectory';

interface RequesterOption {
    id: number;
    name: string;
    company_name: string;
    location_name: string;
    department_name: string;
}

const props = withDefaults(defineProps<{
    form: {
        company: string;
        location: string;
        category: string;
        ticket_scope: string;
        priority: string;
        requester: string;
        department: string;
        snipeit_asset_id: number | null;
        asset_reference_snapshot: string;
        maintenance_type: string;
        issue_description: string;
        action_taken: string;
        note: string;
        technician: string;
        vendor_id: number | null;
        status: string;
        date_closed: string | null;
        snipeit_maintenance_id?: number | null;
        snipeit_sync_status?: string | null;
        snipeit_sync_message?: string | null;
        processing: boolean;
        errors: Record<string, string | undefined>;
    };
    priorityOptions: string[];
    statusOptions: string[];
    ticketScopeOptions: Array<{ value: string; label: string }>;
    maintenanceTypeOptions: string[];
    categoryOptions: Array<{ name: string; count: number }> | string[];
    requesterOptions: RequesterOption[];
    vendorOptions: Array<{ id: number; name: string }>;
    submitLabel: string;
    showCancel?: boolean;
    isModal?: boolean;
}>(), {
    showCancel: true,
    isModal: false,
});

const emit = defineEmits<{
    (e: 'submit'): void;
    (e: 'cancel'): void;
}>();

const directory = reactive(useStbDirectory());

const isClosedStatus = computed(() => props.form.status === 'Closed');
const isAssetTicket = computed(() => props.form.ticket_scope === 'asset');
const hasMounted = ref(false);

// Default categories merged into searchable list
const defaultCategories = [
    'Hardware',
    'Software',
    'Network',
    'Printer',
    'Email',
    'Akses',
];

const requesterSearch = ref('');
const requesterDropdownOpen = ref(false);
const requesterDropdownRef = ref<HTMLElement | null>(null);
onClickOutside(requesterDropdownRef, () => {
    requesterDropdownOpen.value = false;
});

const selectRequester = (name: string) => {
    props.form.requester = name;
    requesterDropdownOpen.value = false;
    requesterSearch.value = '';
};

const categorySearch = ref('');
const categoryDropdownOpen = ref(false);
const categoryDropdownRef = ref<HTMLElement | null>(null);
onClickOutside(categoryDropdownRef, () => {
    categoryDropdownOpen.value = false;
});

const selectCategory = (val: string) => {
    props.form.category = val;
    categoryDropdownOpen.value = false;
    categorySearch.value = '';
};

const assetSearch = ref('');
const assetDropdownOpen = ref(false);
const assetDropdownRef = ref<HTMLElement | null>(null);
onClickOutside(assetDropdownRef, () => {
    assetDropdownOpen.value = false;
});

const selectAsset = (id: number | null) => {
    props.form.snipeit_asset_id = id;
    if (id === null) {
        props.form.asset_reference_snapshot = '';
    }
    assetDropdownOpen.value = false;
    assetSearch.value = '';
};

const { assets, assetLoading } = useSnipeDirectory();

const priorityLabels: Record<string, string> = {
    Urgent: 'Darurat',
    High: 'Tinggi',
    Medium: 'Sedang',
    Low: 'Rendah',
};
const statusLabels: Record<string, string> = {
    Open: 'Buka',
    'In Progress': 'Diproses',
    Closed: 'Selesai',
};

const priorityColors: Record<string, string> = {
    Urgent: 'border-rose-200 bg-rose-50/50 text-rose-600 hover:bg-rose-50',
    High: 'border-orange-200 bg-orange-50/50 text-orange-600 hover:bg-orange-50',
    Medium: 'border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100',
    Low: 'border-slate-200 bg-slate-50 text-slate-400 hover:bg-slate-100',
};
const priorityActiveColors: Record<string, string> = {
    Urgent: 'bg-rose-600 text-white shadow-sm',
    High: 'bg-amber-500 text-white shadow-sm',
    Medium: 'bg-[#003628] text-white shadow-sm',
    Low: 'bg-slate-600 text-white shadow-sm',
};
const statusColors: Record<string, string> = {
    Open: 'border-slate-200 bg-slate-50 text-slate-400 hover:bg-slate-100',
    'In Progress': 'border-amber-200 bg-amber-50/50 text-amber-600 hover:bg-amber-50',
    Closed: 'border-emerald-200 bg-emerald-50/50 text-emerald-600 hover:bg-emerald-50',
};
const statusActiveColors: Record<string, string> = {
    Open: 'border-slate-600 bg-slate-600 text-white shadow-lg shadow-slate-200 scale-[1.02]',
    'In Progress': 'border-[#d99528] bg-[#d99528] text-white shadow-lg shadow-orange-200 scale-[1.02]',
    Closed: 'border-emerald-600 bg-emerald-600 text-white shadow-lg shadow-emerald-200 scale-[1.02]',
};

const normalizedCategoryOptions = computed(() => {
    const set = new Set<string>();
    defaultCategories.forEach((cat) => set.add(cat));
    (props.categoryOptions || []).forEach((item) => {
        // Handle both old format (string) and new format ({ name, count })
        const category = typeof item === 'string' ? item : item.name;
        const trimmed = category?.trim();
        if (trimmed) set.add(trimmed);
    });
    return Array.from(set).sort((left, right) => left.localeCompare(right, 'id-ID'));
});

const hasCategoryOptions = computed(
    () => normalizedCategoryOptions.value.length > 0,
);

const resolveAssetReference = (asset: SnipeAsset) =>
    getSnipeAssetReferenceValue(asset) || asset.serial || asset.name || '';

const hardwareAssets = computed(() => assets.assets ?? []);
const loadingHardwareAssets = computed(() => assetLoading.assets ?? false);

const assetOptions = computed(() =>
    [
        ...hardwareAssets.value,
        ...(props.form.snipeit_asset_id &&
        !hardwareAssets.value.some(
            (asset) => asset.id === props.form.snipeit_asset_id,
        )
            ? [
                  {
                      id: props.form.snipeit_asset_id,
                      name:
                          props.form.asset_reference_snapshot ||
                          `Asset #${props.form.snipeit_asset_id}`,
                      serial: '',
                      otherserial: props.form.asset_reference_snapshot || '',
                      state_name: 'Unknown',
                      group_name: '',
                      type_name: 'Hardware',
                      stock: '-',
                      used: '-',
                      asset_type: 'assets' as SnipeAssetCategory,
                      asset_type_label: 'Assets',
                      users_id: null,
                      location_name: props.form.location || '',
                  } satisfies SnipeAsset,
              ]
            : []),
    ].sort((left, right) => left.name.localeCompare(right.name, 'id-ID')),
);

const activeAsset = computed(
    () =>
        assetOptions.value.find(
            (asset) => asset.id === props.form.snipeit_asset_id,
        ) ?? null,
);

const sortedRequesterOptions = computed(() =>
    [
        ...directory.users,
        ...(props.form.requester !== '' &&
        !directory.users.some(
            (option) => option.name === props.form.requester,
        )
            ? [
                  {
                      id: -1,
                      name: props.form.requester,
                      company_name: props.form.company,
                      location_name: props.form.location,
                      department_name: props.form.department,
                  },
              ]
            : []),
    ].sort((left, right) => left.name.localeCompare(right.name, 'id-ID')),
);

const activeRequester = computed(
    () =>
        directory.users.find(
            (option) => option.name === props.form.requester,
        ) ?? null,
);

const filteredAssetOptions = computed(() => {
    const q = assetSearch.value.toLowerCase().trim();
    if (!q) return assetOptions.value;
    return assetOptions.value.filter(
        (a) =>
            a.name.toLowerCase().includes(q) ||
            resolveAssetReference(a).toLowerCase().includes(q) ||
            (a.location_name ?? '').toLowerCase().includes(q),
    );
});

const filteredCategoryOptions = computed(() => {
    const q = categorySearch.value.toLowerCase().trim();
    if (!q) return normalizedCategoryOptions.value;
    return normalizedCategoryOptions.value.filter((opt) =>
        opt.toLowerCase().includes(q),
    );
});

const formatRequesterSub = (option: { department_name?: string; company_name?: string; location_name?: string; name?: string }) => {
    const parts: string[] = [];
    const dept = option.department_name && option.department_name !== '-' ? option.department_name : null;
    let comp = option.company_name && option.company_name !== '-' ? option.company_name : null;
    const loc = option.location_name && option.location_name !== '-' ? option.location_name : null;

    if (!comp && option.name) {
        if (option.name.includes('(ZINUS ZGI)')) comp = 'PT Zinus Global Indonesia';
        else if (option.name.includes('(ZINUS ZDI)')) comp = 'PT Zinus Dream Indonesia';
    }

    if (dept) parts.push(`Dept: ${dept}`);
    if (comp) parts.push(comp);
    if (loc) parts.push(loc);

    if (parts.length === 0) {
        return 'Data dept & company belum diatur';
    }
    return parts.join(' • ');
};

const filteredRequesterOptions = computed(() => {
    const q = requesterSearch.value.toLowerCase().trim();
    if (!q) return sortedRequesterOptions.value;
    return sortedRequesterOptions.value.filter(
        (o) =>
            o.name.toLowerCase().includes(q) ||
            (o.department_name && o.department_name.toLowerCase().includes(q)) ||
            (o.location_name && o.location_name.toLowerCase().includes(q)) ||
            (o.company_name && o.company_name.toLowerCase().includes(q)),
    );
});

const fetchAssetOptions = async () => {
    await ensureSnipeAssetsLoaded('assets');
};

const handleBack = () => {
    emit('cancel');
};

onMounted(() => {
    hasMounted.value = true;
    void directory.ensureDirectoryLoaded();
    void fetchAssetOptions();
});


watch(
    () => props.form.status,
    (status) => {
        if (status === 'Closed') {
            if (!props.form.date_closed) {
                props.form.date_closed = new Date().toISOString().slice(0, 10);
            }

            return;
        }

        props.form.date_closed = '';
    },
    { immediate: true },
);

watch(activeRequester, (requester) => {
    if (!hasMounted.value) {
        return;
    }

    if (!requester) {
        props.form.company = '';
        props.form.location = '';
        props.form.department = '';
        return;
    }

    let comp = requester.company_name && requester.company_name !== '-' ? requester.company_name : '';
    if (!comp && requester.name) {
        if (requester.name.includes('(ZINUS ZGI)')) comp = 'PT Zinus Global Indonesia';
        else if (requester.name.includes('(ZINUS ZDI)')) comp = 'PT Zinus Dream Indonesia';
    }

    props.form.company = comp;
    props.form.location = (requester.location_name && requester.location_name !== '-') ? requester.location_name : '';
    props.form.department = (requester.department_name && requester.department_name !== '-') ? requester.department_name : '';
});

watch(activeAsset, (asset) => {
    if (!hasMounted.value) {
        return;
    }

    if (!isAssetTicket.value) {
        return;
    }

    props.form.asset_reference_snapshot = asset
        ? resolveAssetReference(asset)
        : '';
});

watch(
    () => props.form.ticket_scope,
    (scope) => {
        if (!hasMounted.value) {
            return;
        }

        if (scope === 'asset') {
            if (!props.form.maintenance_type) {
                props.form.maintenance_type = 'Pemeliharaan';
            }

            return;
        }

        props.form.snipeit_asset_id = null;
        props.form.asset_reference_snapshot = '';
        props.form.maintenance_type = 'Pemeliharaan';
        props.form.snipeit_maintenance_id = null;
        props.form.snipeit_sync_status = null;
        props.form.snipeit_sync_message = null;
    },
    { immediate: true },
);


// Vendor Logic
const showAddVendor = ref(false);
const newVendor = reactive({
    name: '',
    category: '',
});
const vendorLoading = ref(false);
const localVendorOptions = ref([...(props.vendorOptions || [])]);

const addNewVendor = async () => {
    if (!newVendor.name) return;
    vendorLoading.value = true;
    try {
        const res = await axios.post('/vendors', newVendor);
        const created = res.data;
        localVendorOptions.value.push(created);
        props.form.vendor_id = created.id;
        showAddVendor.value = false;
        newVendor.name = '';
        newVendor.category = '';
    } catch (err) {
        console.error(err);
    } finally {
        vendorLoading.value = false;
    }
};
</script>

<template>
    <form @submit.prevent="$emit('submit')" class="flex flex-col h-full max-h-[92vh] bg-white rounded-2xl overflow-hidden relative">
        <!-- Decorative background -->
        <div class="absolute top-0 right-0 -mr-24 -mt-24 h-96 w-96 rounded-full bg-primary/5 blur-[120px] pointer-events-none" />

        <!-- ── FIXED HEADER ── -->
        <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-white/95 backdrop-blur-sm shrink-0 z-10">
            <div class="flex items-center gap-3">
                <div class="h-5 w-1 rounded-full bg-[#d99528]" />
                <h3 class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">
                    {{ isModal ? (form.snipeit_maintenance_id || (props.submitLabel.toLowerCase().includes('perbarui') ? 'Edit Ticket' : 'Create Ticket')) : 'Workspace Form' }}
                </h3>
            </div>
            <button
                type="button"
                :disabled="directory.directoryLoading"
                class="flex items-center gap-1.5 h-8 px-3 rounded-lg bg-slate-100 text-[9px] font-black uppercase tracking-widest text-slate-600 hover:bg-slate-200 disabled:opacity-40 transition-all cursor-pointer"
                @click="directory.ensureDirectoryLoaded(true)"
            >
                <RefreshCw :class="['size-3', directory.directoryLoading && 'animate-spin']" />
                Refresh
            </button>
        </div>

        <!-- ── SCROLLABLE BODY ── -->
        <div class="flex-1 overflow-y-auto px-6 py-4 space-y-3.5 custom-scrollbar">
            <!-- ══════════════════════════════════════════════════════ -->
            <!-- 👤 SECTION 1: INFORMASI PELAPOR -->
            <!-- ══════════════════════════════════════════════════════ -->
            <section class="rounded-2xl border border-[#003628]/10 bg-[#003628]/[0.03] p-4">
                <div class="flex items-center gap-2 mb-3">
                    <div class="flex h-6 w-6 items-center justify-center rounded-lg bg-[#003628] shadow-sm shadow-[#003628]/20">
                        <User2 class="size-3 text-white" />
                    </div>
                    <h4 class="text-[10px] font-black uppercase tracking-widest text-[#003628]">Informasi Pelapor</h4>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <!-- Requester -->
                    <div ref="requesterDropdownRef" class="app-form-field relative">
                        <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5 block ml-1">
                            Diminta Oleh<span class="app-required-mark">*</span>
                        </span>
                        <button
                            type="button"
                            class="app-select-shell app-select-compact flex w-full items-center justify-between gap-2 text-left bg-white min-h-[42px] py-1.5"
                            :disabled="directory.directoryLoading"
                            @click="requesterDropdownOpen = !requesterDropdownOpen"
                        >
                            <div class="flex items-center gap-2.5 min-w-0">
                                <User2 class="size-4 text-slate-400 shrink-0" />
                                <div class="min-w-0">
                                    <span class="block truncate font-bold text-xs" :class="form.requester ? 'text-slate-900' : 'text-slate-400 font-medium'">
                                        {{ directory.directoryLoading ? 'Mengambil data user…' : form.requester || 'Pilih Pelapor' }}
                                    </span>
                                    <span v-if="form.requester && (form.department || form.company || form.location)" class="block truncate text-[10px] text-slate-500 font-medium">
                                        {{ [form.department ? `Dept: ${form.department}` : '', form.company, form.location].filter(Boolean).join(' • ') }}
                                    </span>
                                </div>
                            </div>
                            <ChevronDown class="h-3.5 w-3.5 text-slate-400 shrink-0 transition-transform duration-200" :class="requesterDropdownOpen && 'rotate-180'" />
                        </button>

                        <!-- Dropdown Menu -->
                        <div v-if="requesterDropdownOpen" class="absolute top-full left-0 right-0 z-[60] mt-1.5 rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-200/50 overflow-hidden animate-in fade-in slide-in-from-top-2 duration-200">
                            <div class="p-2 border-b border-slate-100 bg-slate-50/50">
                                <input
                                    v-model="requesterSearch"
                                    type="search"
                                    class="h-8.5 w-full rounded-xl border-none bg-white px-3 text-xs shadow-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-[#003628] focus:outline-none"
                                    placeholder="Cari nama, dept, atau lokasi…"
                                    autofocus
                                />
                            </div>
                            <ul class="max-h-56 overflow-y-auto py-1 custom-scrollbar">
                                <li v-if="filteredRequesterOptions.length === 0" class="px-4 py-3 text-xs text-slate-500 italic">User tidak ditemukan</li>
                                <li
                                    v-for="option in filteredRequesterOptions"
                                    :key="option.id"
                                    class="group flex cursor-pointer items-center justify-between gap-3 px-4 py-2.5 text-xs transition-colors hover:bg-slate-50 border-b border-slate-50 last:border-none"
                                    @click="selectRequester(option.name)"
                                >
                                    <div class="min-w-0">
                                        <p class="font-semibold text-xs" :class="form.requester === option.name ? 'text-[#003628]' : 'text-slate-800'">{{ option.name }}</p>
                                        <p class="truncate text-[10px] text-slate-400 group-hover:text-slate-600 mt-0.5">
                                            {{ formatRequesterSub(option) }}
                                        </p>
                                    </div>
                                    <div v-if="form.requester === option.name" class="w-4 h-4 rounded-full bg-[#003628]/10 flex items-center justify-center shrink-0">
                                        <Check class="h-2.5 w-2.5 text-[#003628]" />
                                    </div>
                                </li>
                            </ul>
                        </div>
                        <p v-if="form.errors.requester" class="app-form-error">{{ form.errors.requester }}</p>
                    </div>

                    <!-- Ticket Type -->
                    <div class="app-form-field">
                        <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5 block ml-1">
                            Lingkup Tiket<span class="app-required-mark">*</span>
                        </span>
                        <div class="flex p-1 bg-slate-100/90 rounded-xl gap-1 border border-slate-200/60 min-h-[42px] items-center">
                            <button
                                v-for="option in ticketScopeOptions"
                                :key="option.value"
                                type="button"
                                class="flex-1 rounded-lg py-1.5 text-xs font-bold transition-all duration-200 cursor-pointer"
                                :class="form.ticket_scope === option.value
                                    ? 'bg-white text-[#003628] shadow-sm'
                                    : 'text-slate-500 hover:text-slate-700'"
                                @click="form.ticket_scope = option.value"
                            >
                                {{ option.value === 'non-asset' ? 'Dukungan Umum' : (option.value === 'asset' ? 'Terkait Aset' : option.label) }}
                            </button>
                        </div>
                        <p v-if="form.errors.ticket_scope" class="app-form-error">{{ form.errors.ticket_scope }}</p>
                    </div>
                </div>

                <!-- Detail User: Perusahaan, Lokasi, Departemen -->
                <Transition
                    enter-active-class="transition duration-300 ease-out"
                    enter-from-class="opacity-0 -translate-y-2"
                    enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="transition duration-200 ease-in"
                    leave-from-class="opacity-100 translate-y-0"
                    leave-to-class="opacity-0 -translate-y-2"
                >
                    <div v-if="form.requester" class="mt-3 grid gap-3 sm:grid-cols-3 pt-3 border-t border-[#003628]/10">
                        <div class="app-form-field">
                            <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 mb-1 block ml-1 flex items-center gap-1.5">
                                <Building2 class="size-3 text-slate-400" /> Perusahaan<span class="app-required-mark">*</span>
                            </span>
                            <input
                                v-model="form.company"
                                type="text"
                                class="app-input-shell app-input-compact w-full bg-white text-xs font-semibold text-slate-800 h-8.5"
                                placeholder="Nama Perusahaan..."
                            />
                            <p v-if="form.errors.company" class="app-form-error">{{ form.errors.company }}</p>
                        </div>

                        <div class="app-form-field">
                            <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 mb-1 block ml-1 flex items-center gap-1.5">
                                <MapPin class="size-3 text-slate-400" /> Lokasi<span class="app-required-mark">*</span>
                            </span>
                            <input
                                v-model="form.location"
                                type="text"
                                class="app-input-shell app-input-compact w-full bg-white text-xs font-semibold text-slate-800 h-8.5"
                                placeholder="Lokasi / Gedung..."
                            />
                            <p v-if="form.errors.location" class="app-form-error">{{ form.errors.location }}</p>
                        </div>

                        <div class="app-form-field">
                            <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 mb-1 block ml-1 flex items-center gap-1.5">
                                <Users2 class="size-3 text-slate-400" /> Departemen<span class="app-required-mark">*</span>
                            </span>
                            <input
                                v-model="form.department"
                                type="text"
                                class="app-input-shell app-input-compact w-full bg-white text-xs font-semibold text-slate-800 h-8.5"
                                placeholder="Departemen..."
                            />
                            <p v-if="form.errors.department" class="app-form-error">{{ form.errors.department }}</p>
                        </div>
                    </div>
                </Transition>
            </section>

            <!-- ══════════════════════════════════════════════════════ -->
            <!-- 🏷️ SECTION 2: DETAIL MASALAH -->
            <!-- ══════════════════════════════════════════════════════ -->
            <section class="rounded-2xl border border-amber-200/60 bg-amber-50/20 p-4">
                <div class="flex items-center gap-2 mb-3">
                    <div class="flex h-6 w-6 items-center justify-center rounded-lg bg-amber-500 shadow-sm shadow-amber-500/20">
                        <AlertCircle class="size-3 text-white" />
                    </div>
                    <h4 class="text-[10px] font-black uppercase tracking-widest text-amber-700">Detail Masalah</h4>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <!-- Category (Clean Dropdown only, no chips outside) -->
                    <div ref="categoryDropdownRef" class="app-form-field relative">
                        <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5 block ml-1">
                            Kategori Masalah<span class="app-required-mark">*</span>
                        </span>

                        <div class="relative">
                            <button
                                type="button"
                                class="app-select-shell app-select-compact flex w-full items-center justify-between gap-2 text-left bg-white transition-colors h-9"
                                :class="form.category ? 'border-[#003628]/30 ring-1 ring-[#003628]/10' : 'border-slate-200'"
                                @click="categoryDropdownOpen = !categoryDropdownOpen"
                            >
                                <div class="flex items-center gap-2 min-w-0">
                                    <Search class="size-3.5 text-slate-400 shrink-0" />
                                    <span class="truncate text-xs" :class="form.category ? 'font-bold text-slate-900' : 'text-slate-400 font-medium'">
                                        {{ form.category || 'Pilih Kategori Masalah...' }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-1.5 shrink-0">
                                    <button
                                        v-if="form.category"
                                        type="button"
                                        class="text-slate-400 hover:text-rose-500 p-0.5 transition-colors cursor-pointer"
                                        title="Hapus pilihan"
                                        @click.stop="form.category = ''"
                                    >
                                        <X class="size-3.5" />
                                    </button>
                                    <ChevronDown class="h-3.5 w-3.5 text-slate-400 transition-transform duration-200" :class="categoryDropdownOpen && 'rotate-180'" />
                                </div>
                            </button>

                            <!-- Dropdown Menu -->
                            <div v-if="categoryDropdownOpen" class="absolute top-full left-0 right-0 z-[60] mt-1.5 rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-200/50 overflow-hidden animate-in fade-in slide-in-from-top-2 duration-200">
                                <div class="p-2 border-b border-slate-100 bg-slate-50/50">
                                    <input
                                        v-model="categorySearch"
                                        type="text"
                                        class="h-8.5 w-full rounded-xl border-none bg-white px-3 text-xs shadow-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-[#003628] focus:outline-none"
                                        placeholder="Cari atau ketik kategori baru..."
                                        @keydown.enter.prevent="selectCategory(categorySearch || form.category)"
                                        autofocus
                                    />
                                </div>
                                <ul class="max-h-52 overflow-y-auto py-1 custom-scrollbar">
                                    <!-- Option to use custom typed text if not in list -->
                                    <li
                                        v-if="categorySearch && !normalizedCategoryOptions.some(opt => opt.toLowerCase() === categorySearch.toLowerCase())"
                                        class="px-4 py-2 text-xs cursor-pointer hover:bg-amber-50 text-amber-700 font-bold border-b border-slate-50 flex items-center gap-2"
                                        @click="selectCategory(categorySearch)"
                                    >
                                        <PlusCircle class="w-3.5 h-3.5 text-amber-600" />
                                        Gunakan: "{{ categorySearch }}"
                                    </li>

                                    <li v-if="filteredCategoryOptions.length === 0 && !categorySearch" class="px-4 py-3 text-xs text-slate-500 italic">Kategori tidak ditemukan</li>

                                    <li
                                        v-for="option in filteredCategoryOptions"
                                        :key="option"
                                        class="group flex cursor-pointer items-center justify-between gap-3 px-4 py-2 text-xs transition-colors hover:bg-slate-50"
                                        @click="selectCategory(option)"
                                    >
                                        <span class="font-medium" :class="form.category === option ? 'font-bold text-[#003628]' : 'text-slate-700'">{{ option }}</span>
                                        <div v-if="form.category === option" class="w-4 h-4 rounded-full bg-[#003628]/10 flex items-center justify-center">
                                            <Check class="h-2.5 w-2.5 text-[#003628]" />
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <p v-if="form.errors.category" class="app-form-error">{{ form.errors.category }}</p>
                    </div>

                    <!-- Priority — Compact single-row segmented control -->
                    <div class="app-form-field">
                        <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5 block ml-1">
                            Tingkat Prioritas<span class="app-required-mark">*</span>
                        </span>
                        <div class="grid grid-cols-4 gap-1 p-1 bg-slate-100/90 rounded-xl border border-slate-200/60 h-9 items-center">
                            <button
                                v-for="option in priorityOptions"
                                :key="option"
                                type="button"
                                class="rounded-lg py-1 px-1 text-[10px] font-black uppercase tracking-wider transition-all duration-200 text-center cursor-pointer"
                                :class="form.priority === option
                                    ? (priorityActiveColors[option] || 'bg-[#003628] text-white shadow-sm')
                                    : 'text-slate-500 hover:text-slate-800 hover:bg-white/50 bg-transparent'"
                                @click="form.priority = option"
                            >
                                {{ priorityLabels[option] || option }}
                            </button>
                        </div>
                        <p v-if="form.errors.priority" class="app-form-error">{{ form.errors.priority }}</p>
                    </div>
                </div>

                <!-- Deskripsi Masalah (Moved inside Detail Masalah) -->
                <div class="app-form-field mt-3">
                    <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5 block ml-1">
                        Deskripsi Masalah<span class="app-required-mark">*</span>
                    </span>
                    <textarea
                        v-model="form.issue_description"
                        rows="3"
                        class="app-textarea-shell w-full resize-none text-xs leading-relaxed bg-white focus:bg-white transition-colors"
                        placeholder="Jelaskan detail keluhan dari user di sini…"
                    ></textarea>
                    <p v-if="form.errors.issue_description" class="app-form-error">{{ form.errors.issue_description }}</p>
                </div>
            </section>

            <!-- ══════════════════════════════════════════════════════ -->
            <!-- 💻 SECTION 3: INFORMASI ASSET (Conditional) -->
            <!-- ══════════════════════════════════════════════════════ -->
            <Transition
                enter-active-class="transition duration-300 ease-out"
                enter-from-class="opacity-0 -translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition duration-200 ease-in"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 -translate-y-2"
            >
                <section v-if="isAssetTicket" class="rounded-2xl border border-purple-200/60 bg-purple-50/30 p-4 shadow-sm">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="flex h-6 w-6 items-center justify-center rounded-lg bg-purple-500 shadow-sm shadow-purple-500/20">
                            <Building2 class="size-3 text-white" />
                        </div>
                        <h4 class="text-[10px] font-black uppercase tracking-widest text-purple-600">Informasi Asset</h4>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <!-- Asset Select -->
                        <div class="app-form-field">
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5 block ml-1">
                                Aset Terkait<span class="app-required-mark">*</span>
                            </span>
                            <div ref="assetDropdownRef" class="relative">
                                <button
                                    type="button"
                                    class="app-select-shell app-select-compact flex w-full items-center justify-between gap-2 text-left bg-white h-9"
                                    :disabled="loadingHardwareAssets"
                                    @click="assetDropdownOpen = !assetDropdownOpen"
                                >
                                    <span class="truncate font-medium text-xs" :class="form.snipeit_asset_id ? 'text-slate-900' : 'text-slate-400'">
                                        {{ loadingHardwareAssets ? 'Memuat aset…' : activeAsset ? activeAsset.name : 'Pilih Aset Perangkat Keras' }}
                                    </span>
                                    <ChevronDown class="h-3.5 w-3.5 text-slate-400 shrink-0 transition-transform duration-200" :class="assetDropdownOpen && 'rotate-180'" />
                                </button>

                                <div v-if="assetDropdownOpen" class="absolute top-full left-0 right-0 z-[60] mt-1.5 rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-200/50 overflow-hidden animate-in fade-in slide-in-from-top-2 duration-200">
                                    <div class="p-2 border-b border-slate-100 bg-slate-50/50">
                                        <input
                                            v-model="assetSearch"
                                            type="search"
                                            class="h-8.5 w-full rounded-xl border-none bg-white px-3 text-xs shadow-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-primary focus:outline-none"
                                            placeholder="Cari nama, serial, atau lokasi…"
                                            autofocus
                                        />
                                    </div>
                                    <ul class="max-h-52 overflow-y-auto py-1 custom-scrollbar">
                                        <li v-if="filteredAssetOptions.length === 0" class="px-4 py-3 text-xs text-slate-500 italic">Aset tidak ditemukan</li>
                                        <li
                                            v-for="asset in filteredAssetOptions"
                                            :key="asset.id"
                                            class="group flex cursor-pointer items-center justify-between gap-3 px-4 py-2 text-xs transition-colors hover:bg-slate-50"
                                            @click="selectAsset(asset.id)"
                                        >
                                            <div class="min-w-0">
                                                <p class="font-semibold" :class="form.snipeit_asset_id === asset.id ? 'text-primary' : 'text-slate-700'">{{ asset.name }}</p>
                                                <p class="truncate text-[10px] text-slate-400 group-hover:text-slate-500">
                                                    {{ [resolveAssetReference(asset), asset.location_name].filter(Boolean).join(' • ') }}
                                                </p>
                                            </div>
                                            <div v-if="form.snipeit_asset_id === asset.id" class="w-4 h-4 rounded-full bg-primary/10 flex items-center justify-center">
                                                <Check class="h-2.5 w-2.5 text-primary" />
                                            </div>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <p v-if="form.errors.snipeit_asset_id" class="app-form-error">{{ form.errors.snipeit_asset_id }}</p>
                        </div>

                        <!-- Maintenance Type -->
                        <div class="app-form-field">
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5 block ml-1">
                                Tipe Pemeliharaan<span class="app-required-mark">*</span>
                            </span>
                            <select v-model="form.maintenance_type" class="app-select-shell app-select-compact w-full bg-white h-9">
                                <option v-for="option in maintenanceTypeOptions" :key="option" :value="option">{{ option }}</option>
                            </select>
                            <p v-if="form.errors.maintenance_type" class="app-form-error">{{ form.errors.maintenance_type }}</p>
                        </div>
                    </div>
                </section>
            </Transition>

            <!-- ══════════════════════════════════════════════════════ -->
            <!-- 🚚 SECTION 3.5: VENDOR (Conditional) -->
            <!-- ══════════════════════════════════════════════════════ -->
            <section v-if="isAssetTicket" class="rounded-2xl border border-purple-200/60 bg-purple-50/20 p-4">
                <div class="flex items-center justify-between mb-2.5">
                    <div class="flex items-center gap-2">
                        <Truck class="w-3.5 h-3.5 text-purple-400" />
                        <span class="text-[10px] font-black uppercase tracking-widest text-purple-600">
                            Vendor / Pihak Ketiga
                            <span class="text-purple-400 normal-case font-medium">(Opsional)</span>
                        </span>
                    </div>
                    <button
                        v-if="!showAddVendor"
                        type="button"
                        class="text-[9px] font-black uppercase tracking-widest text-primary hover:text-primary/80 flex items-center gap-1 transition-all cursor-pointer"
                        @click="showAddVendor = true"
                    >
                        <PlusCircle class="w-3 h-3" /> Tambah Vendor Baru
                    </button>
                </div>

                <div v-if="showAddVendor" class="p-3 bg-white rounded-xl border border-dashed border-slate-200 animate-in fade-in slide-in-from-top-2">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1 block">Nama Vendor</label>
                            <input v-model="newVendor.name" type="text" class="app-input-shell app-input-compact w-full bg-white h-8 text-xs" placeholder="Contoh: PT. Maju Jaya" />
                        </div>
                        <div>
                            <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1 block">Kategori</label>
                            <input v-model="newVendor.category" type="text" class="app-input-shell app-input-compact w-full bg-white h-8 text-xs" placeholder="Contoh: Hardware, Network" />
                        </div>
                    </div>
                    <div class="mt-2.5 flex justify-end gap-2">
                        <button type="button" class="text-[10px] font-bold text-slate-500 px-2.5 py-1 hover:bg-slate-100 rounded-lg transition-all cursor-pointer" @click="showAddVendor = false">Batal</button>
                        <button
                            type="button"
                            class="text-[10px] font-black uppercase tracking-widest bg-primary text-white px-3.5 py-1 rounded-lg shadow-sm disabled:opacity-50 cursor-pointer"
                            :disabled="vendorLoading || !newVendor.name"
                            @click="addNewVendor"
                        >
                            {{ vendorLoading ? 'Menyimpan...' : 'Simpan Vendor' }}
                        </button>
                    </div>
                </div>

                <div v-else class="relative">
                    <div class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">
                        <Truck class="w-3.5 h-3.5" />
                    </div>
                    <select v-model="form.vendor_id" class="app-select-shell app-select-compact w-full pl-9 bg-white h-9">
                        <option :value="null">-- Tidak Ada Vendor (Internal) --</option>
                        <option v-for="vendor in localVendorOptions" :key="vendor.id" :value="vendor.id">{{ vendor.name }}</option>
                    </select>
                </div>
            </section>

            <!-- ══════════════════════════════════════════════════════ -->
            <!-- ✅ SECTION 4: TINDAKAN & PENYELESAIAN (Compact 2-col) -->
            <!-- ══════════════════════════════════════════════════════ -->
            <section class="rounded-2xl border border-blue-200/60 bg-blue-50/20 p-4 shadow-sm">
                <div class="flex items-center gap-2 mb-3">
                    <div class="flex h-6 w-6 items-center justify-center rounded-lg bg-blue-500 shadow-sm shadow-blue-500/20">
                        <Check class="size-3 text-white" />
                    </div>
                    <h4 class="text-[10px] font-black uppercase tracking-widest text-blue-600">Tindakan & Penyelesaian</h4>
                </div>

                <!-- Tindakan + Catatan Internal side-by-side, compact -->
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="app-form-field">
                        <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5 block ml-1">
                            Tindakan yang Diambil<span class="app-required-mark">*</span>
                        </span>
                        <textarea
                            v-model="form.action_taken"
                            rows="2"
                            class="app-textarea-shell w-full resize-none text-xs leading-relaxed bg-white focus:bg-white transition-colors min-h-[56px]"
                            placeholder="Apa saja langkah perbaikan yang sudah dilakukan?"
                        ></textarea>
                        <p v-if="form.errors.action_taken" class="app-form-error">{{ form.errors.action_taken }}</p>
                    </div>

                    <div class="app-form-field">
                        <div class="flex items-center gap-1.5 mb-1.5 ml-1">
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">Catatan Internal Teknisi</span>
                            <span class="text-[9px] text-slate-300">(hanya internal IT)</span>
                        </div>
                        <textarea
                            v-model="form.note"
                            rows="2"
                            class="app-textarea-shell w-full resize-none text-xs italic bg-white/80 border-blue-100 placeholder:text-slate-300 focus:bg-white transition-colors min-h-[56px]"
                            placeholder="Catatan tambahan (hanya untuk internal IT)…"
                        ></textarea>
                        <p v-if="form.errors.note" class="app-form-error">{{ form.errors.note }}</p>
                    </div>
                </div>
            </section>
        </div>

        <!-- ══════════════════════════════════════════════════════ -->
        <!-- 🔒 LOCKED BOTTOM BAR: STATUS SAAT INI + FOOTER ACTIONS -->
        <!-- ══════════════════════════════════════════════════════ -->
        <div class="border-t border-slate-200/90 bg-white px-6 py-3 shrink-0 z-20 shadow-[0_-4px_25px_rgba(0,0,0,0.06)] space-y-2.5">
            <!-- Row 1: Status Saat Ini + Tanggal Ditutup -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex items-center gap-3 flex-1 min-w-0">
                    <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 shrink-0">
                        Status Saat Ini<span class="app-required-mark">*</span>
                    </span>
                    <div class="flex gap-1.5 flex-1 max-w-sm">
                        <button
                            v-for="option in statusOptions"
                            :key="option"
                            type="button"
                            class="flex-1 rounded-xl py-1.5 px-3 text-[10px] font-black uppercase tracking-wider transition-all duration-200 border cursor-pointer text-center"
                            :class="form.status === option
                                ? (statusActiveColors[option] || 'bg-[#003628] border-[#003628] text-white shadow-sm scale-[1.02]')
                                : (statusColors[option] || 'bg-slate-50 border-slate-200 text-slate-400 hover:text-slate-600 hover:bg-slate-100')"
                            @click="form.status = option"
                        >
                            {{ statusLabels[option] || option }}
                        </button>
                    </div>
                    <p v-if="form.errors.status" class="app-form-error shrink-0">{{ form.errors.status }}</p>
                </div>

                <!-- Tanggal Ditutup -->
                <div class="flex items-center gap-2 shrink-0">
                    <span
                        class="text-[9px] font-black uppercase tracking-widest transition-colors"
                        :class="isClosedStatus ? 'text-slate-500' : 'text-slate-300'"
                    >
                        Tanggal Ditutup:
                    </span>
                    <div class="relative w-36">
                        <input
                            v-model="form.date_closed"
                            type="date"
                            class="app-input-shell app-input-compact w-full pl-8 pr-2 text-xs transition-all h-8.5"
                            :class="isClosedStatus ? 'bg-white text-slate-800 border-slate-300' : 'bg-slate-100/70 text-slate-300 cursor-not-allowed border-slate-200 opacity-50'"
                            :disabled="!isClosedStatus"
                        />
                        <div class="absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none" :class="isClosedStatus ? 'text-slate-500' : 'text-slate-300'">
                            <Calendar class="w-3.5 h-3.5" />
                        </div>
                    </div>
                    <p v-if="form.errors.date_closed" class="app-form-error">{{ form.errors.date_closed }}</p>
                </div>
            </div>

            <!-- Row 2: Teknisi + Actions -->
            <div class="flex items-center justify-between gap-4 pt-2 border-t border-slate-100">
                <div class="flex items-center gap-2.5 group">
                    <div class="w-7 h-7 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 group-hover:bg-[#d99528]/10 group-hover:text-[#d99528] transition-colors shrink-0">
                        <Wrench class="w-3.5 h-3.5" />
                    </div>
                    <div class="leading-tight">
                        <p class="text-[8px] text-slate-400 font-bold uppercase tracking-tight">Teknisi yang Ditugaskan</p>
                        <p class="text-xs font-bold text-slate-700 tracking-tight">{{ form.technician || 'Belum Ditugaskan' }}</p>
                    </div>
                </div>

                <div class="flex items-center gap-2.5">
                    <button
                        v-if="showCancel"
                        type="button"
                        class="px-4 py-2 rounded-xl text-xs font-bold text-slate-500 hover:bg-slate-100 transition-all active:scale-95 cursor-pointer"
                        @click="handleBack"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        class="px-8 h-11 rounded-2xl text-[11px] font-black uppercase tracking-widest text-white bg-[#003628] shadow-xl shadow-emerald-900/20 hover:brightness-110 transition-all active:scale-95 disabled:opacity-50"
                        :disabled="form.processing"
                    >
                        <span v-if="form.processing" class="flex items-center gap-2">
                            <svg class="animate-spin h-3.5 w-3.5" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            Menyinkronkan...
                        </span>
                        <span v-else>{{ submitLabel }}</span>
                    </button>
                </div>
            </div>
        </div>
    </form>
</template>
