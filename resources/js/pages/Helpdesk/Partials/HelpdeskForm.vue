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
    categoryOptions: string[];
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
    Urgent: 'border-rose-500 bg-rose-500 text-white shadow-lg shadow-rose-200 scale-[1.02]',
    High: 'border-orange-500 bg-orange-500 text-white shadow-lg shadow-orange-200 scale-[1.02]',
    Medium: 'border-[#003628] bg-[#003628] text-white shadow-lg shadow-emerald-900/20 scale-[1.02]',
    Low: 'border-slate-600 bg-slate-600 text-white shadow-lg shadow-slate-200 scale-[1.02]',
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

const normalizedCategoryOptions = computed(() =>
    Array.from(
        new Set(
            (props.categoryOptions || [])
                .map((category) => category.trim())
                .filter((category) => category !== ''),
        ),
    ).sort((left, right) => left.localeCompare(right, 'en')),
);

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

const filteredRequesterOptions = computed(() => {
    const q = requesterSearch.value.toLowerCase().trim();
    if (!q) return sortedRequesterOptions.value;
    return sortedRequesterOptions.value.filter(
        (o) =>
            o.name.toLowerCase().includes(q) ||
            o.department_name.toLowerCase().includes(q) ||
            o.location_name.toLowerCase().includes(q),
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

    props.form.company = requester.company_name || '';
    props.form.location = requester.location_name || '';
    props.form.department = requester.department_name || '';
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
    <form @submit.prevent="$emit('submit')">
        <div
            class="app-form-panel"
            :class="[
                isModal ? 'max-w-none border-none shadow-none md:p-6' : '',
            ]"
        >
            <!-- Decorative background -->
            <div class="absolute top-0 right-0 -mr-24 -mt-24 h-96 w-96 rounded-full bg-primary/5 blur-[120px] pointer-events-none" />
            <!-- ── HEADER ── -->
            <div class="mb-6 flex justify-between items-start">
                <div>
                    <div class="flex items-center gap-3">
                        <div class="h-6 w-1.5 rounded-full bg-[#d99528]" />
                        <h3 class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">Create Ticket</h3>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        :disabled="directory.directoryLoading"
                        class="flex items-center gap-1.5 h-9 px-4 rounded-lg bg-slate-100 text-[10px] font-black uppercase tracking-widest text-slate-600 hover:bg-slate-200 disabled:opacity-40 transition-all"
                        @click="directory.ensureDirectoryLoaded(true)"
                    >
                        <RefreshCw :class="['size-3', directory.directoryLoading && 'animate-spin']" />
                        Refresh Direktori
                    </button>
                </div>
            </div>

            <!-- ── BLOCK 1: IDENTITY (Who & Type) ── -->
            <section class="space-y-3.5">
                <div class="grid gap-5 sm:grid-cols-2">
                    <!-- Requester -->
                    <div ref="requesterDropdownRef" class="app-form-field relative">
                        <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2 block ml-1">
                            Diminta Oleh<span class="app-required-mark">*</span>
                        </span>
                        <button
                            type="button"
                            class="app-select-shell app-select-compact flex w-full items-center justify-between gap-2 text-left bg-white/50 backdrop-blur-sm"
                            :disabled="directory.directoryLoading"
                            @click="requesterDropdownOpen = !requesterDropdownOpen"
                        >
                            <span class="truncate font-medium" :class="form.requester ? 'text-slate-900' : 'text-slate-400'">
                                {{ directory.directoryLoading ? 'Mengambil data user…' : form.requester || 'Pilih Pelapor' }}
                            </span>
                            <ChevronDown class="h-4 w-4 text-slate-400" />
                        </button>

                        <!-- Dropdown Menu -->
                        <div v-if="requesterDropdownOpen" class="absolute top-full left-0 right-0 z-[60] mt-1.5 rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-200/50 overflow-hidden animate-in fade-in slide-in-from-top-2 duration-200">
                            <div class="p-2 border-b border-slate-100 bg-slate-50/50">
                                <input
                                    v-model="requesterSearch"
                                    type="search"
                                    class="h-9 w-full rounded-xl border-none bg-white px-3 text-xs shadow-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-primary focus:outline-none"
                                    placeholder="Cari nama, dept, atau lokasi…"
                                    autofocus
                                />
                            </div>
                            <ul class="max-h-60 overflow-y-auto py-1 custom-scrollbar">
                                <li v-if="filteredRequesterOptions.length === 0" class="px-4 py-3 text-xs text-slate-500 italic">Tidak ada hasil ditemukan</li>
                                <li
                                    v-for="option in filteredRequesterOptions"
                                    :key="option.id"
                                    class="group flex cursor-pointer items-center justify-between gap-3 px-4 py-2.5 text-xs transition-colors hover:bg-slate-50"
                                    @click="selectRequester(option.name)"
                                >
                                    <div class="min-w-0">
                                        <p class="font-semibold" :class="form.requester === option.name ? 'text-[#003628]' : 'text-slate-700'">{{ option.name }}</p>
                                        <p class="truncate text-[10px] text-slate-400 group-hover:text-slate-500">
                                            {{ [option.department_name, option.location_name].filter(Boolean).join(' • ') }}
                                        </p>
                                    </div>
                                    <div v-if="form.requester === option.name" class="w-5 h-5 rounded-full bg-[#d99528]/10 flex items-center justify-center">
                                        <svg class="h-3 w-3 text-[#d99528]" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                </li>
                            </ul>
                        </div>
                        <p v-if="form.errors.requester" class="app-form-error">{{ form.errors.requester }}</p>
                    </div>

                    <!-- Ticket Type -->
                    <div class="app-form-field">
                        <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2 block ml-1">
                            Lingkup Tiket<span class="app-required-mark">*</span>
                        </span>
                        <div class="flex p-1 bg-slate-100/80 rounded-xl gap-1">
                            <button
                                v-for="option in ticketScopeOptions"
                                :key="option.value"
                                type="button"
                                class="flex-1 rounded-lg py-2 text-xs font-bold transition-all duration-200"
                                :class="form.ticket_scope === option.value
                                    ? 'bg-white text-[#003628] shadow-md'
                                    : 'text-slate-500 hover:text-slate-700'"
                                @click="form.ticket_scope = option.value"
                            >
                                {{ option.value === 'non-asset' ? 'Dukungan Umum' : (option.value === 'asset' ? 'Terkait Aset' : option.label) }}
                            </button>
                        </div>
                        <p v-if="form.errors.ticket_scope" class="app-form-error">{{ form.errors.ticket_scope }}</p>
                    </div>
                </div>

                <!-- Metadata Profile Bar -->
                <Transition
                    enter-active-class="transition duration-300 ease-out"
                    enter-from-class="opacity-0 -translate-y-2"
                    enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="transition duration-200 ease-in"
                    leave-from-class="opacity-100 translate-y-0"
                    leave-to-class="opacity-0 -translate-y-2"
                >
                    <div v-if="activeRequester" class="flex flex-wrap items-center gap-5 p-3.5 bg-slate-50/50 border border-slate-100 rounded-2xl">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-lg bg-white shadow-sm flex items-center justify-center text-slate-400">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-7h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                            </div>
                            <div>
                                <p class="text-[9px] text-slate-400 font-bold uppercase">Perusahaan</p>
                                <p class="text-xs font-semibold text-slate-700">{{ form.company || '-' }}</p>
                            </div>
                        </div>
                        <div class="w-px h-6 bg-slate-200 hidden sm:block"></div>
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-lg bg-white shadow-sm flex items-center justify-center text-slate-400">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                            </div>
                            <div>
                                <p class="text-[9px] text-slate-400 font-bold uppercase">Lokasi</p>
                                <p class="text-xs font-semibold text-slate-700">{{ form.location || '-' }}</p>
                            </div>
                        </div>
                        <div class="w-px h-6 bg-slate-200 hidden sm:block"></div>
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-lg bg-white shadow-sm flex items-center justify-center text-slate-400">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                            </div>
                            <div>
                                <p class="text-[9px] text-slate-400 font-bold uppercase">Departemen</p>
                                <p class="text-xs font-semibold text-slate-700">{{ form.department || '-' }}</p>
                            </div>
                        </div>
                    </div>
                </Transition>
            </section>

            <div class="my-5 border-t border-dashed border-slate-200"></div>

            <!-- ── BLOCK 2: CLASSIFICATION (What & How Urgent) ── -->
            <section class="grid gap-5 sm:grid-cols-2">
                <!-- Category -->
                <div ref="categoryDropdownRef" class="app-form-field relative">
                    <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2 block ml-1">
                        Kategori Masalah<span class="app-required-mark">*</span>
                    </span>
                    <div class="relative">
                        <button
                            type="button"
                            class="app-select-shell app-select-compact flex w-full items-center justify-between gap-2 text-left bg-white/50"
                            @click="categoryDropdownOpen = !categoryDropdownOpen"
                        >
                            <span class="truncate font-medium" :class="form.category ? 'text-slate-900' : 'text-slate-400'">
                                {{ form.category || 'Pilih atau ketik kategori baru' }}
                            </span>
                            <ChevronDown class="h-3.5 w-3.5 text-slate-400" />
                        </button>

                        <!-- Dropdown Menu -->
                        <div v-if="categoryDropdownOpen" class="absolute top-full left-0 right-0 z-[60] mt-1 rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-200/50 overflow-hidden animate-in fade-in slide-in-from-top-2 duration-200">
                            <div class="p-1.5 border-b border-slate-100 bg-slate-50/50">
                                <input
                                    v-model="categorySearch"
                                    type="text"
                                    class="h-8 w-full rounded-xl border-none bg-white px-3 text-xs shadow-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-primary focus:outline-none"
                                    placeholder="Cari atau ketik manual..."
                                    @keydown.enter.prevent="selectCategory(categorySearch || form.category)"
                                    autofocus
                                />
                            </div>
                            <ul class="max-h-52 overflow-y-auto py-1 custom-scrollbar">
                                <!-- Option to use custom typed text if not in list -->
                                <li
                                    v-if="categorySearch && !normalizedCategoryOptions.some(opt => opt.toLowerCase() === categorySearch.toLowerCase())"
                                    class="px-4 py-2.5 text-xs cursor-pointer hover:bg-[#d99528]/5 text-[#d99528] font-bold border-b border-slate-50"
                                    @click="selectCategory(categorySearch)"
                                >
                                    <div class="flex items-center gap-2">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                        Gunakan: "{{ categorySearch }}"
                                    </div>
                                </li>

                                <li v-if="filteredCategoryOptions.length === 0 && !categorySearch" class="px-4 py-3 text-xs text-slate-500 italic">No categories found</li>

                                <li
                                    v-for="option in filteredCategoryOptions"
                                    :key="option"
                                    class="group flex cursor-pointer items-center justify-between gap-3 px-4 py-2.5 text-xs transition-colors hover:bg-slate-50"
                                    @click="selectCategory(option)"
                                >
                                    <span class="font-semibold" :class="form.category === option ? 'text-[#003628]' : 'text-slate-700'">{{ option }}</span>
                                    <div v-if="form.category === option" class="w-4 h-4 rounded-full bg-[#d99528]/10 flex items-center justify-center">
                                        <svg class="h-2.5 w-2.5 text-[#d99528]" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <p v-if="form.errors.category" class="app-form-error">{{ form.errors.category }}</p>
                </div>

                <!-- Priority -->
                <div class="app-form-field">
                    <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2 block ml-1">
                        Tingkat Prioritas<span class="app-required-mark">*</span>
                    </span>
                    <div class="grid grid-cols-4 gap-2">
                        <button
                            v-for="option in priorityOptions"
                            :key="option"
                            type="button"
                            class="rounded-xl py-2.5 text-[11px] font-bold transition-all duration-200 border"
                            :class="form.priority === option
                                ? (priorityActiveColors[option] || 'bg-[#003628] border-[#003628] text-white shadow-lg shadow-emerald-900/20 scale-[1.02]')
                                : (priorityColors[option] || 'bg-slate-50 border-slate-100 text-slate-500 hover:bg-slate-100')"
                            @click="form.priority = option"
                        >
                            {{ priorityLabels[option] || option }}
                        </button>
                    </div>
                    <p v-if="form.errors.priority" class="app-form-error">{{ form.errors.priority }}</p>
                </div>
            </section>

            <!-- ── BLOCK 3: TECHNICAL DETAIL (If Asset Ticket) ── -->
            <Transition
                enter-active-class="transition duration-300 ease-out"
                enter-from-class="opacity-0 -translate-y-4"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition duration-200 ease-in"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 -translate-y-4"
            >
                <section v-if="isAssetTicket" class="mt-6 pt-6 border-t border-slate-100">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <!-- Asset Select -->
                        <div class="app-form-field">
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2 block ml-1">
                                Aset Terkait<span class="app-required-mark">*</span>
                            </span>
                            <div ref="assetDropdownRef" class="relative">
                                <button
                                    type="button"
                                    class="app-select-shell app-select-compact flex w-full items-center justify-between gap-2 text-left bg-white/50"
                                    :disabled="loadingHardwareAssets"
                                    @click="assetDropdownOpen = !assetDropdownOpen"
                                >
                                    <span class="truncate font-medium" :class="form.snipeit_asset_id ? 'text-slate-900' : 'text-slate-400'">
                                        {{ loadingHardwareAssets ? 'Memuat aset…' : activeAsset ? activeAsset.name : 'Pilih Aset Perangkat Keras' }}
                                    </span>
                                    <ChevronDown class="h-4 w-4 text-slate-400" />
                                </button>

                                <div v-if="assetDropdownOpen" class="absolute top-full left-0 right-0 z-[60] mt-1.5 rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-200/50 overflow-hidden animate-in fade-in slide-in-from-top-2 duration-200">
                                    <div class="p-2 border-b border-slate-100 bg-slate-50/50">
                                        <input
                                            v-model="assetSearch"
                                            type="search"
                                            class="h-9 w-full rounded-xl border-none bg-white px-3 text-xs shadow-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-primary focus:outline-none"
                                            placeholder="Cari nama, serial, atau lokasi…"
                                            autofocus
                                        />
                                    </div>
                                    <ul class="max-h-60 overflow-y-auto py-1 custom-scrollbar">
                                        <li v-if="filteredAssetOptions.length === 0" class="px-4 py-3 text-xs text-slate-500 italic">Aset tidak ditemukan</li>
                                        <li
                                            v-for="asset in filteredAssetOptions"
                                            :key="asset.id"
                                            class="group flex cursor-pointer items-center justify-between gap-3 px-4 py-2.5 text-xs transition-colors hover:bg-slate-50"
                                            @click="selectAsset(asset.id)"
                                        >
                                            <div class="min-w-0">
                                                <p class="font-semibold" :class="form.snipeit_asset_id === asset.id ? 'text-primary' : 'text-slate-700'">{{ asset.name }}</p>
                                                <p class="truncate text-[10px] text-slate-400 group-hover:text-slate-500">
                                                    {{ [resolveAssetReference(asset), asset.location_name].filter(Boolean).join(' • ') }}
                                                </p>
                                            </div>
                                            <div v-if="form.snipeit_asset_id === asset.id" class="w-5 h-5 rounded-full bg-primary/10 flex items-center justify-center">
                                                <svg class="h-3 w-3 text-primary" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                                                </svg>
                                            </div>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <p v-if="form.errors.snipeit_asset_id" class="app-form-error">{{ form.errors.snipeit_asset_id }}</p>
                        </div>

                        <!-- Maintenance Type -->
                        <div class="app-form-field">
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2 block ml-1">
                                Tipe Pemeliharaan<span class="app-required-mark">*</span>
                            </span>
                            <select v-model="form.maintenance_type" class="app-select-shell app-select-compact w-full bg-white/50">
                                <option v-for="option in maintenanceTypeOptions" :key="option" :value="option">{{ option }}</option>
                            </select>
                            <p v-if="form.errors.maintenance_type" class="app-form-error">{{ form.errors.maintenance_type }}</p>
                        </div>
                    </div>
                </section>
            </Transition>

            <div class="my-5 border-t border-dashed border-slate-200"></div>

            <!-- ── BLOCK 3.5: VENDOR (Optional) ── -->
            <section v-if="isAssetTicket" class="space-y-3.5">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 block ml-1">
                        Vendor / Pihak Ketiga (Opsional)
                    </span>
                    <button 
                        v-if="!showAddVendor"
                        type="button" 
                        class="text-[9px] font-black uppercase tracking-widest text-primary hover:text-primary/80 flex items-center gap-1 transition-all"
                        @click="showAddVendor = true"
                    >
                        <PlusCircle class="w-3 h-3" /> Tambah Vendor Baru
                    </button>
                </div>

                <div v-if="showAddVendor" class="p-4 bg-slate-50 rounded-2xl border border-dashed border-slate-200 animate-in fade-in slide-in-from-top-2">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1.5 block">Nama Vendor</label>
                            <input v-model="newVendor.name" type="text" class="app-input-shell app-input-compact w-full bg-white" placeholder="Contoh: PT. Maju Jaya" />
                        </div>
                        <div>
                            <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1.5 block">Kategori</label>
                            <input v-model="newVendor.category" type="text" class="app-input-shell app-input-compact w-full bg-white" placeholder="Contoh: Hardware, Network" />
                        </div>
                    </div>
                    <div class="mt-3 flex justify-end gap-2">
                        <button type="button" class="text-[10px] font-bold text-slate-500 px-3 py-1.5" @click="showAddVendor = false">Batal</button>
                        <button 
                            type="button" 
                            class="text-[10px] font-black uppercase tracking-widest bg-primary text-white px-4 py-1.5 rounded-lg shadow-lg shadow-primary/10 disabled:opacity-50"
                            :disabled="vendorLoading || !newVendor.name"
                            @click="addNewVendor"
                        >
                            {{ vendorLoading ? 'Menyimpan...' : 'Simpan Vendor' }}
                        </button>
                    </div>
                </div>

                <div v-else class="relative">
                    <div class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
                        <Truck class="w-4 h-4" />
                    </div>
                    <select v-model="form.vendor_id" class="app-select-shell app-select-compact w-full pl-10 bg-white/50">
                        <option :value="null">-- Tidak Ada Vendor (Internal) --</option>
                        <option v-for="vendor in localVendorOptions" :key="vendor.id" :value="vendor.id">{{ vendor.name }}</option>
                    </select>
                </div>
            </section>

            <div class="my-5 border-t border-dashed border-slate-200"></div>

            <!-- ── BLOCK 4: WORK PROCESS (Problem & Solution) ── -->
            <section class="space-y-4">
                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="app-form-field">
                        <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2 block ml-1">
                            Deskripsi Masalah<span class="app-required-mark">*</span>
                        </span>
                        <textarea
                            v-model="form.issue_description"
                            rows="5"
                            class="app-textarea-shell w-full resize-none text-xs leading-relaxed bg-white/50 focus:bg-white transition-colors"
                            placeholder="Jelaskan detail keluhan dari user di sini…"
                        ></textarea>
                        <p v-if="form.errors.issue_description" class="app-form-error">{{ form.errors.issue_description }}</p>
                    </div>

                    <div class="app-form-field">
                        <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2 block ml-1">
                            Tindakan yang Diambil<span class="app-required-mark">*</span>
                        </span>
                        <textarea
                            v-model="form.action_taken"
                            rows="5"
                            class="app-textarea-shell w-full resize-none text-xs leading-relaxed bg-white/50 focus:bg-white transition-colors"
                            placeholder="Apa saja langkah perbaikan yang sudah dilakukan?"
                        ></textarea>
                        <p v-if="form.errors.action_taken" class="app-form-error">{{ form.errors.action_taken }}</p>
                    </div>
                </div>

                <div class="app-form-field">
                    <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2 block ml-1">Catatan Internal Teknisi</span>
                    <textarea
                        v-model="form.note"
                        rows="2"
                        class="app-textarea-shell w-full resize-none text-xs italic bg-slate-50/50"
                        placeholder="Catatan tambahan (hanya untuk internal IT)…"
                    ></textarea>
                    <p v-if="form.errors.note" class="app-form-error">{{ form.errors.note }}</p>
                </div>
            </section>

            <div class="my-5 border-t border-dashed border-slate-200"></div>

            <!-- ── BLOCK 5: CLOSING (Status & Date) ── -->
            <section class="grid gap-5 sm:grid-cols-2 items-end">
                <!-- Status Pills -->
                <div class="app-form-field">
                    <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2 block ml-1">
                        Status Saat Ini<span class="app-required-mark">*</span>
                    </span>
                    <div class="flex gap-2">
                        <button
                            v-for="option in statusOptions"
                            :key="option"
                            type="button"
                            class="flex-1 rounded-xl py-2.5 text-[10px] font-black uppercase tracking-[0.15em] transition-all duration-200 border"
                            :class="form.status === option
                                ? (statusActiveColors[option] || 'bg-[#003628] border-[#003628] text-white shadow-lg shadow-emerald-900/20 scale-[1.02]')
                                : (statusColors[option] || 'bg-slate-50 border-slate-100 text-slate-400 hover:text-slate-600')"
                            @click="form.status = option"
                        >
                            {{ statusLabels[option] || option }}
                        </button>
                    </div>
                    <p v-if="form.errors.status" class="app-form-error">{{ form.errors.status }}</p>
                </div>

                <!-- Date Closed -->
                <div class="app-form-field">
                    <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2 block ml-1">Tanggal Ditutup</span>
                    <div class="relative">
                        <input
                            v-model="form.date_closed"
                            type="date"
                            class="app-input-shell app-input-compact w-full pl-9 bg-white/50 disabled:bg-slate-100/50 disabled:cursor-not-allowed"
                            :disabled="!isClosedStatus"
                        />
                        <div class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
                            <Calendar class="w-3.5 h-3.5" />
                        </div>
                    </div>
                    <p v-if="form.errors.date_closed" class="app-form-error">{{ form.errors.date_closed }}</p>
                </div>
            </section>

            <!-- ── FOOTER & ACTIONS ── -->
            <div class="mt-8 flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between border-t border-slate-100 pt-6">
                <div class="flex flex-col gap-4">
                    <div class="flex items-center gap-3 group">
                        <div class="w-9 h-9 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 group-hover:bg-[#d99528]/10 group-hover:text-[#d99528] transition-colors duration-300">
                            <User2 class="w-4.5 h-4.5" />
                        </div>
                        <div>
                            <p class="text-[9px] text-slate-400 font-bold uppercase tracking-tight">Teknisi yang Ditugaskan</p>
                            <p class="text-xs font-bold text-slate-700 tracking-tight">{{ form.technician || 'Belum Ditugaskan' }}</p>
                        </div>
                    </div>

                </div>

                <div class="flex items-center gap-3">
                    <button
                        v-if="showCancel"
                        type="button"
                        class="px-5 py-2 rounded-xl text-xs font-bold text-slate-500 hover:bg-slate-100 transition-all active:scale-95"
                        @click="handleBack"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        class="px-8 h-12 rounded-2xl text-[11px] font-black uppercase tracking-widest text-white bg-[#003628] shadow-xl shadow-emerald-900/20 hover:brightness-110 transition-all active:scale-95 disabled:opacity-50"
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
