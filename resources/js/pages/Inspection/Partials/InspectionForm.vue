<script setup lang="ts">
import {
    computed,
    defineAsyncComponent,
    onMounted,
    reactive,
    ref,
    watch,
} from 'vue';
import {
    Calendar,
    Camera,
    ChevronDown,
    ClipboardCheck,
    Hash,
    Loader2,
    Mail,
    MapPin,
    Tag,
    User,
    Wrench,
    Building2,
    Users,
    FileText,
    CheckCircle2,
    Monitor,
    Package,
    Cpu,
} from 'lucide-vue-next';
import {
    ensureSnipeDirectoryLoaded,
    fetchUserAssets,
    useSnipeDirectory,
} from '@/composables/useSnipeDirectory';
import StbSearchableSelect from '@/pages/Stb/Partials/StbSearchableSelect.vue';

const InspectionItemPickerModal = defineAsyncComponent(
    () => import('@/pages/Inspection/Partials/InspectionItemPickerModal.vue'),
);

interface Props {
    initialData?: any;
    nextSequence?: number;
    isLoading?: boolean;
}
const props = withDefaults(defineProps<Props>(), {
    initialData: () => ({}),
    nextSequence: 1,
    isLoading: false,
});
const emit = defineEmits<{
    (e: 'submit', data: any): void;
    (e: 'cancel'): void;
}>();

const reportTypeOptions = [
    'Inspection Hardware',
    'Inspection Part / Component',
    'Inspection Accessories / Other',
];
const deviceCategoryOptions = [
    { value: 'pc', label: 'PC' },
    { value: 'laptop', label: 'Laptop' },
    { value: 'printer', label: 'Printer' },
    { value: 'monitor', label: 'Monitor' },
    { value: 'network', label: 'Network Device' },
    { value: 'other', label: 'Other' },
];
const today = new Date().toISOString().slice(0, 10);

// Normalize a date value to YYYY-MM-DD string (handles ISO datetime strings)
const toDateStr = (v: any): string => {
    if (!v) return today;
    if (typeof v === 'string' && v.length >= 10) return v.slice(0, 10);
    return today;
};

const generateReportId = (
    company: string,
    date: string,
    seq: number,
): string => {
    if (!company || !date) return '';
    const abbr = company
        .toUpperCase()
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 3)
        .map((w: string) => w[0])
        .join('');
    const d = new Date(date);
    const yymm =
        String(d.getFullYear()).slice(2) +
        String(d.getMonth() + 1).padStart(2, '0');
    return `IR-${abbr}-${yymm}-${String(seq).padStart(5, '0')}`;
};

const directory = reactive(useSnipeDirectory());
const loadingDirectory = ref(false);

onMounted(async () => {
    loadingDirectory.value = true;
    await ensureSnipeDirectoryLoaded();
    loadingDirectory.value = false;

    // -- Resolve missing IDs for edit mode (old records without stored IDs) --
    if (props.initialData?.id) {
        // Resolve user_id by name if missing
        if (!formData.value.user_id && formData.value.user) {
            const match = directory.users.find(
                (u) =>
                    u.name?.toLowerCase() === formData.value.user.toLowerCase(),
            );
            if (match) {
                // Set without triggering the watch's auto-fill side effects
                _skipUserWatch.value = true;
                formData.value.user_id = match.id;
            }
        }
        // Resolve it_staff_id by name if missing
        if (!formData.value.it_staff_id && formData.value.it_staff) {
            const match = directory.users.find(
                (u) =>
                    u.name?.toLowerCase() ===
                    formData.value.it_staff.toLowerCase(),
            );
            if (match) formData.value.it_staff_id = match.id;
        }
        // Resolve checked_by_id by name if missing
        if (!formData.value.checked_by_id && formData.value.checked_by) {
            const match = directory.users.find(
                (u) =>
                    u.name?.toLowerCase() ===
                    formData.value.checked_by.toLowerCase(),
            );
            if (match) formData.value.checked_by_id = match.id;
        }
    }
});

const allUsers = computed(() => directory.users);
const itUsers = computed(() =>
    directory.users.filter((u) => {
        const dept = (u.department_name || '').toUpperCase();
        return dept === 'IT' || dept.includes('INFORMATION TECHNOLOGY');
    }),
);

const formData = ref({
    date: toDateStr(props.initialData?.date),
    report_id: props.initialData?.report_id || '',
    report_type: props.initialData?.report_type || '',
    // User fields
    user_id: props.initialData?.user_id || (null as number | null),
    user: props.initialData?.user || '',
    email: props.initialData?.email || '',
    company: props.initialData?.company || '',
    department: props.initialData?.department || '',
    dept_head: props.initialData?.dept_head || '',
    location: props.initialData?.location || '',
    // IT fields
    it_staff_id: props.initialData?.it_staff_id || (null as number | null),
    it_staff: props.initialData?.it_staff || '',
    checked_by_id: props.initialData?.checked_by_id || (null as number | null),
    checked_by: props.initialData?.checked_by || '',
    checked_date: toDateStr(props.initialData?.checked_date),
    // Asset
    device_category: props.initialData?.device_category || '',
    device_name: props.initialData?.device_name || '',
    asset_tag: props.initialData?.asset_tag || '',
    serial_number: props.initialData?.serial_number || '',
    asset_snapshot: props.initialData?.asset_snapshot || '',
    snipeit_asset_id:
        props.initialData?.snipeit_asset_id || (null as number | null),
    // Issue
    issue_description: props.initialData?.issue_description || '',
    solution: props.initialData?.solution || '',
    remarks: props.initialData?.remarks || '',
    photo: null as File | null,
});

// Asset is locked when coming from asset detail page OR when editing an existing record with an asset
const assetLocked = computed(() => !!props.initialData?.snipeit_asset_id);

// Auto-generate report ID when company or date changes
watch(
    () => [formData.value.company, formData.value.date],
    ([company, date]) => {
        if (!props.initialData?.id) {
            formData.value.report_id = generateReportId(
                String(company),
                String(date),
                props.nextSequence,
            );
        }
    },
    { immediate: true },
);

const normalizeInteger = (v: any) => {
    if (v === '' || v === null || v === undefined) return null;
    const n = Number(v);
    return isNaN(n) ? null : n;
};

const selectedUserId = computed(() => normalizeInteger(formData.value.user_id));

const inspectionAssetCategory = computed(() => {
    switch (formData.value.report_type) {
        case 'Inspection Hardware':
            return 'assets';
        case 'Inspection Part / Component':
            return 'component';
        case 'Inspection Accessories / Other':
            return 'accessories';
        default:
            return null;
    }
});

const filterInspectionAssets = (assets: any[]) => {
    const category = inspectionAssetCategory.value;
    if (!category) return assets;

    return assets.filter(
        (asset) => String(asset.asset_type || '').toLowerCase() === category,
    );
};

// Flag to skip auto-fill when resolving IDs for existing records
const _skipUserWatch = ref(false);

// User's assigned assets (hardware, accessories, component ? no license/consumable)
const userAssets = ref<any[]>([]);
const loadingUserAssets = ref(false);

const refreshUserAssets = async (userId: number, force = false) => {
    if (!userId || assetLocked.value) {
        userAssets.value = [];
        return;
    }

    loadingUserAssets.value = true;
    try {
        const [hw, acc, comp] = await Promise.all([
            fetchUserAssets(userId, 'assets', force),
            fetchUserAssets(userId, 'accessories', force),
            fetchUserAssets(userId, 'component', force),
        ]);
        userAssets.value = filterInspectionAssets(
            [...hw, ...acc, ...comp].filter((a) => a && a.id),
        );
    } catch {
        userAssets.value = [];
    } finally {
        loadingUserAssets.value = false;
    }
};

watch(selectedUserId, async (userId) => {
    if (!userId) {
        userAssets.value = [];
        return;
    }

    // Skip auto-fill side effects when resolving IDs for existing records.
    if (_skipUserWatch.value) {
        _skipUserWatch.value = false;
        await refreshUserAssets(userId);
        return;
    }

    const user = directory.users.find((u) => u.id === userId);
    if (!user) return;
    formData.value.user = user.name;
    formData.value.email = user.email || '';
    formData.value.company = user.company_name || '';
    formData.value.department = user.department_name || '';
    formData.value.dept_head = user.manager_name || '';
    formData.value.location = user.location_name || '';
    if (!assetLocked.value) clearAsset();
    await refreshUserAssets(userId, true);
});

// IT Staff selection
const selectedItStaffId = computed(() =>
    normalizeInteger(formData.value.it_staff_id),
);
watch(selectedItStaffId, (id) => {
    if (!id) return;
    const user = directory.users.find((u) => u.id === id);
    if (user) formData.value.it_staff = user.name;
});

// Checked By selection
const selectedCheckedById = computed(() =>
    normalizeInteger(formData.value.checked_by_id),
);
watch(selectedCheckedById, (id) => {
    if (!id) return;
    const user = directory.users.find((u) => u.id === id);
    if (user) formData.value.checked_by = user.name;
});

const pickerOpen = ref(false);

const openAssetModal = async () => {
    if (assetLocked.value) return;
    await directory.ensureAssetsLoaded('assets');
    pickerOpen.value = true;
};

const selectUserAsset = (asset: any) => {
    formData.value.snipeit_asset_id = asset.id;
    formData.value.device_name = asset.name || '';
    formData.value.asset_tag = asset.otherserial || asset.serial || '';
    formData.value.serial_number = asset.serial || '';
    // Map asset_type to device_category
    const cat = (asset.asset_type || '').toLowerCase();
    if (
        cat.includes('computer') ||
        cat === 'assets' ||
        cat.includes('hardware')
    )
        formData.value.device_category = 'pc';
    else if (cat.includes('laptop') || cat.includes('notebook'))
        formData.value.device_category = 'laptop';
    else if (cat.includes('printer'))
        formData.value.device_category = 'printer';
    else if (cat.includes('monitor') || cat.includes('display'))
        formData.value.device_category = 'monitor';
    else if (
        cat.includes('network') ||
        cat.includes('switch') ||
        cat.includes('router')
    )
        formData.value.device_category = 'network';
    else if (cat.includes('accessories') || cat.includes('accessory'))
        formData.value.device_category = 'other';
    else if (cat.includes('component'))
        formData.value.device_category = 'other';
    else formData.value.device_category = 'other';
    formData.value.asset_snapshot = JSON.stringify({
        id: asset.id,
        name: asset.name,
        asset_tag: asset.otherserial || asset.serial,
        serial: asset.serial,
        category: asset.type_name || asset.asset_type_label,
        asset_type: asset.asset_type,
    });
};

watch(
    () => formData.value.report_type,
    async () => {
        if (selectedUserId.value) {
            await refreshUserAssets(selectedUserId.value, true);
        }
        if (!formData.value.snipeit_asset_id) return;

        const selectedAsset = userAssets.value.find(
            (asset) => asset.id === formData.value.snipeit_asset_id,
        );
        if (!selectedAsset) {
            clearAsset();
        }
    },
);

const handleAssetSelect = (asset: any) => {
    selectUserAsset(asset);
    pickerOpen.value = false;
};

const clearAsset = () => {
    formData.value.snipeit_asset_id = null;
    formData.value.device_name = '';
    formData.value.asset_tag = '';
    formData.value.serial_number = '';
    formData.value.device_category = '';
    formData.value.asset_snapshot = '';
};

const photoPreview = ref<string | null>(null);
const isCompressingPhoto = ref(false);
const photoLoadFailed = ref(false);
const existingPhotoUrl = computed(() =>
    props.initialData?.photo ? `/storage/${props.initialData.photo}` : null,
);

const readFileAsDataUrl = (file: File) =>
    new Promise<string>((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(String(reader.result));
        reader.onerror = () => reject(new Error('Failed to read image file'));
        reader.readAsDataURL(file);
    });

const loadImage = (src: string) =>
    new Promise<HTMLImageElement>((resolve, reject) => {
        const image = new Image();
        image.onload = () => resolve(image);
        image.onerror = () => reject(new Error('Failed to load image'));
        image.src = src;
    });

const canvasToBlob = (
    canvas: HTMLCanvasElement,
    type: string,
    quality?: number,
) =>
    new Promise<Blob>((resolve, reject) => {
        canvas.toBlob(
            (blob) => {
                if (blob) {
                    resolve(blob);
                    return;
                }

                reject(new Error('Failed to compress image'));
            },
            type,
            quality,
        );
    });

const compressImage = async (file: File): Promise<File> => {
    const source = await readFileAsDataUrl(file);
    const image = await loadImage(source);
    const canvas = document.createElement('canvas');
    const context = canvas.getContext('2d');

    if (!context) {
        return file;
    }

    const outputType = 'image/jpeg';
    const maxDimension = 1200;
    let scale = Math.min(
        1,
        maxDimension / image.width,
        maxDimension / image.height,
    );
    let quality = 0.76;
    let blob: Blob | null = null;

    while (scale > 0.3) {
        const width = Math.max(1, Math.round(image.width * scale));
        const height = Math.max(1, Math.round(image.height * scale));
        canvas.width = width;
        canvas.height = height;
        context.clearRect(0, 0, width, height);
        context.drawImage(image, 0, 0, width, height);

        let currentQuality = quality;
        blob = await canvasToBlob(canvas, outputType, currentQuality);

        while (blob.size > 50 * 1024 && currentQuality > 0.2) {
            currentQuality = Number((currentQuality - 0.07).toFixed(2));
            blob = await canvasToBlob(canvas, outputType, currentQuality);
        }

        if (blob.size <= 1024 * 1024) {
            break;
        }

        scale = Number((scale - 0.1).toFixed(2));
        quality = Math.max(0.2, Number((currentQuality - 0.03).toFixed(2)));
    }

    if (!blob || blob.size >= file.size) {
        return file;
    }

    const baseName = file.name.replace(/\.[^.]+$/, '');

    return new File([blob], `${baseName}.jpg`, {
        type: outputType,
        lastModified: Date.now(),
    });
};

const handlePhotoChange = async (event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;

    if (!file.type.startsWith('image/')) {
        alert('File yang dipilih harus berupa gambar.');
        input.value = '';
        return;
    }

    if (file.size > 5 * 1024 * 1024) {
        alert('Ukuran foto maksimal 5MB.');
        input.value = '';
        return;
    }

    try {
        isCompressingPhoto.value = true;
        photoLoadFailed.value = false;

        if (photoPreview.value && photoPreview.value.startsWith('blob:')) {
            URL.revokeObjectURL(photoPreview.value);
        }

        const compressed = await compressImage(file);
        formData.value.photo = compressed;
        photoPreview.value = URL.createObjectURL(compressed);
    } catch (error) {
        console.error('Failed to process image upload:', error);
        formData.value.photo = file;
        photoPreview.value = URL.createObjectURL(file);
    } finally {
        isCompressingPhoto.value = false;
        input.value = '';
    }
};

const formErrors = ref<Record<string, string>>({});
const handleSubmit = () => {
    formErrors.value = {};
    const errs: Record<string, string> = {};
    if (!formData.value.report_type)
        errs.report_type = 'Report type wajib dipilih';
    if (!formData.value.user_id) errs.user_id = 'User wajib dipilih';
    if (!formData.value.location) errs.location = 'Location wajib diisi';
    if (!formData.value.checked_by_id)
        errs.checked_by = 'Checked by wajib dipilih';
    if (!formData.value.issue_description)
        errs.issue_description = 'Issue description wajib diisi';
    if (!formData.value.solution) errs.solution = 'Solution wajib diisi';
    if (Object.keys(errs).length) {
        formErrors.value = errs;
        return;
    }
    emit('submit', { ...formData.value });
};
</script>

<template>
    <form
        class="min-h-screen animate-in bg-[#f8fafc] pb-20 duration-500 fade-in"
        @submit.prevent="handleSubmit"
    >
        <div class="mx-auto max-w-5xl space-y-8 px-6 pt-10">
            <div
                class="overflow-hidden rounded-2xl bg-white shadow-[0_8px_30px_rgb(0,0,0,0.02)]"
            >
                <!-- HEADER -->
                <div class="border-b border-slate-100 bg-white px-8 py-8">
                    <h1
                        class="text-2xl font-bold tracking-tight text-slate-900"
                    >
                        Form Inspection
                    </h1>
                    <p
                        class="mt-1 text-[11px] font-bold tracking-widest text-slate-400 uppercase"
                    >
                        Lengkapi data laporan inspection.
                    </p>
                </div>

                <!-- SECTION 1: DOCUMENT -->
                <div class="border-b border-slate-50 px-8 py-6">
                    <div class="mx-auto max-w-4xl space-y-6">
                        <div class="flex items-center gap-3">
                            <div class="h-px flex-1 bg-slate-100" />
                            <span
                                class="text-[10px] font-black tracking-[0.2em] text-slate-400 uppercase"
                                >Document Information</span
                            >
                            <div class="h-px flex-1 bg-slate-100" />
                        </div>
                        <!-- 3 cols in 1 row -->
                        <div class="grid grid-cols-3 gap-x-8 gap-y-6">
                            <!-- Report ID -->
                            <div class="space-y-2">
                                <label
                                    class="text-[11px] font-black tracking-widest text-slate-400 uppercase"
                                    >Report ID</label
                                >
                                <div class="relative">
                                    <div
                                        class="flex h-10 items-center rounded-lg border border-slate-200 bg-slate-50 pr-4 pl-10 text-[13px] font-black text-slate-400 italic shadow-inner"
                                    >
                                        {{
                                            formData.report_id ||
                                            'AUTO GENERATE'
                                        }}
                                    </div>
                                    <Hash
                                        class="pointer-events-none absolute top-1/2 left-3.5 size-3.5 -translate-y-1/2 text-slate-300"
                                    />
                                </div>
                            </div>
                            <!-- Report Type -->
                            <div class="space-y-2">
                                <label
                                    class="text-[11px] font-black tracking-widest text-slate-400 uppercase"
                                    >Report Type
                                    <span class="text-rose-400">*</span></label
                                >
                                <div class="relative">
                                    <select
                                        v-model="formData.report_type"
                                        class="h-10 w-full appearance-none rounded-lg border border-slate-200 bg-white pr-8 pl-10 text-[13px] font-bold text-slate-900 shadow-sm outline-none focus:border-[#003628]"
                                        :class="
                                            formErrors.report_type
                                                ? 'border-rose-300'
                                                : ''
                                        "
                                    >
                                        <option value="">Pilih tipe...</option>
                                        <option
                                            v-for="opt in reportTypeOptions"
                                            :key="opt"
                                            :value="opt"
                                        >
                                            {{ opt }}
                                        </option>
                                    </select>
                                    <ClipboardCheck
                                        class="pointer-events-none absolute top-1/2 left-3.5 size-3.5 -translate-y-1/2 text-slate-300"
                                    />
                                    <ChevronDown
                                        class="pointer-events-none absolute top-1/2 right-3 size-3.5 -translate-y-1/2 text-slate-300"
                                    />
                                </div>
                                <p
                                    v-if="formErrors.report_type"
                                    class="text-[10px] font-bold text-rose-500"
                                >
                                    {{ formErrors.report_type }}
                                </p>
                            </div>
                            <!-- Date -->
                            <div class="space-y-2">
                                <label
                                    class="text-[11px] font-black tracking-widest text-slate-400 uppercase"
                                    >Date</label
                                >
                                <div class="relative">
                                    <input
                                        v-model="formData.date"
                                        type="date"
                                        class="h-10 w-full rounded-lg border border-slate-200 bg-white pr-4 pl-10 text-[13px] font-bold text-slate-900 shadow-sm outline-none focus:border-[#003628]"
                                    />
                                    <Calendar
                                        class="pointer-events-none absolute top-1/2 left-3.5 size-3.5 -translate-y-1/2 text-slate-300"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECTION 2: USER -->
                <div class="border-b border-slate-50 px-8 py-6">
                    <div class="mx-auto max-w-4xl space-y-6">
                        <div class="flex items-center gap-3">
                            <div class="h-px flex-1 bg-slate-100" />
                            <span
                                class="text-[10px] font-black tracking-[0.2em] text-slate-400 uppercase"
                                >User Information</span
                            >
                            <div class="h-px flex-1 bg-slate-100" />
                        </div>
                        <div class="grid grid-cols-2 gap-x-12 gap-y-6">
                            <!-- User searchable -->
                            <div class="space-y-2">
                                <label
                                    class="text-[11px] font-black tracking-widest text-slate-400 uppercase"
                                    >User
                                    <span class="text-rose-400">*</span></label
                                >
                                <div class="relative">
                                    <StbSearchableSelect
                                        v-model="formData.user_id"
                                        :options="
                                            allUsers.map((u) => ({
                                                id: u.id,
                                                name: u.name,
                                                subtext:
                                                    u.department_name +
                                                    (u.company_name
                                                        ? ' - ' + u.company_name
                                                        : ''),
                                            }))
                                        "
                                        placeholder="Cari user..."
                                        :left-padding="true"
                                    />
                                    <User
                                        class="pointer-events-none absolute top-1/2 left-3.5 z-10 size-3.5 -translate-y-1/2 text-slate-300"
                                    />
                                    <Loader2
                                        v-if="loadingDirectory"
                                        class="pointer-events-none absolute top-1/2 right-8 z-10 size-3.5 -translate-y-1/2 animate-spin text-slate-300"
                                    />
                                </div>
                                <p
                                    v-if="formErrors.user_id"
                                    class="text-[10px] font-bold text-rose-500"
                                >
                                    {{ formErrors.user_id }}
                                </p>
                            </div>
                            <!-- Company (auto) -->
                            <div class="space-y-2">
                                <label
                                    class="text-[11px] font-black tracking-widest text-slate-400 uppercase"
                                    >Company</label
                                >
                                <div class="relative">
                                    <div
                                        class="flex h-10 items-center rounded-lg border border-slate-200 bg-slate-50 pr-4 pl-10 text-[13px] font-black text-slate-400 italic shadow-inner"
                                    >
                                        {{ formData.company || 'AUTOMATIC' }}
                                    </div>
                                    <Building2
                                        class="absolute top-1/2 left-3.5 size-3.5 -translate-y-1/2 text-slate-300"
                                    />
                                </div>
                            </div>
                            <!-- Department (auto) -->
                            <div class="space-y-2">
                                <label
                                    class="text-[11px] font-black tracking-widest text-slate-400 uppercase"
                                    >Department</label
                                >
                                <div class="relative">
                                    <div
                                        class="flex h-10 items-center rounded-lg border border-slate-200 bg-slate-50 pr-4 pl-10 text-[13px] font-black text-slate-400 italic shadow-inner"
                                    >
                                        {{ formData.department || 'AUTOMATIC' }}
                                    </div>
                                    <Users
                                        class="absolute top-1/2 left-3.5 size-3.5 -translate-y-1/2 text-slate-300"
                                    />
                                </div>
                            </div>
                            <!-- Dept Head (auto) -->
                            <div class="space-y-2">
                                <label
                                    class="text-[11px] font-black tracking-widest text-slate-400 uppercase"
                                    >Dept Head</label
                                >
                                <div class="relative">
                                    <div
                                        class="flex h-10 items-center rounded-lg border border-slate-200 bg-slate-50 pr-4 pl-10 text-[13px] font-black text-slate-400 italic shadow-inner"
                                    >
                                        {{ formData.dept_head || 'AUTOMATIC' }}
                                    </div>
                                    <User
                                        class="absolute top-1/2 left-3.5 size-3.5 -translate-y-1/2 text-slate-300"
                                    />
                                </div>
                            </div>
                            <!-- Email (auto) -->
                            <div class="space-y-2">
                                <label
                                    class="text-[11px] font-black tracking-widest text-slate-400 uppercase"
                                    >Email</label
                                >
                                <div class="relative">
                                    <div
                                        class="flex h-10 items-center truncate rounded-lg border border-slate-200 bg-slate-50 pr-4 pl-10 text-[13px] font-black text-slate-400 italic shadow-inner"
                                    >
                                        {{ formData.email || 'AUTOMATIC' }}
                                    </div>
                                    <Mail
                                        class="absolute top-1/2 left-3.5 size-3.5 -translate-y-1/2 text-slate-300"
                                    />
                                </div>
                            </div>
                            <!-- Location (auto) -->
                            <div class="space-y-2">
                                <label
                                    class="text-[11px] font-black tracking-widest text-slate-400 uppercase"
                                    >Location
                                    <span class="text-rose-400">*</span></label
                                >
                                <div class="relative">
                                    <div
                                        class="flex h-10 items-center rounded-lg border border-slate-200 bg-slate-50 pr-4 pl-10 text-[13px] font-black text-slate-400 italic shadow-inner"
                                    >
                                        {{ formData.location || 'AUTOMATIC' }}
                                    </div>
                                    <MapPin
                                        class="absolute top-1/2 left-3.5 size-3.5 -translate-y-1/2 text-slate-300"
                                    />
                                </div>
                                <p
                                    v-if="formErrors.location"
                                    class="text-[10px] font-bold text-rose-500"
                                >
                                    {{ formErrors.location }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECTION 3: ASSET -->
                <div class="border-b border-slate-50 px-8 py-6">
                    <div class="mx-auto max-w-4xl space-y-6">
                        <div class="flex items-center gap-3">
                            <div class="h-px flex-1 bg-slate-100" />
                            <span
                                class="text-[10px] font-black tracking-[0.2em] text-slate-400 uppercase"
                                >Asset</span
                            >
                            <div class="h-px flex-1 bg-slate-100" />
                        </div>

                        <!-- Asset locked notice -->
                        <div
                            v-if="assetLocked"
                            class="flex items-center gap-3 rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-3"
                        >
                            <span
                                class="text-[10px] font-black tracking-widest text-emerald-600 uppercase"
                                >? Asset sudah terpilih dari halaman detail
                                asset</span
                            >
                        </div>

                        <!-- User asset list (shown when user selected and not locked) -->
                        <template v-else-if="selectedUserId">
                            <!-- Loading state -->
                            <div
                                v-if="loadingUserAssets"
                                class="flex items-center justify-center gap-3 py-6"
                            >
                                <Loader2
                                    class="size-5 animate-spin text-slate-300"
                                />
                                <span
                                    class="text-[11px] font-bold text-slate-400"
                                    >Memuat asset user...</span
                                >
                            </div>

                            <!-- User has assets -->
                            <template v-else-if="userAssets.length">
                                <p class="text-[11px] font-bold text-slate-500">
                                    Pilih asset yang bermasalah dari daftar
                                    milik user ini:
                                </p>
                                <div
                                    class="grid max-h-72 grid-cols-1 gap-2 overflow-y-auto pr-1"
                                >
                                    <button
                                        v-for="asset in userAssets"
                                        :key="asset.id"
                                        type="button"
                                        class="group w-full rounded-xl border px-4 py-3.5 text-left transition-all"
                                        :class="
                                            formData.snipeit_asset_id ===
                                            asset.id
                                                ? 'border-[#003628] bg-[#003628]/5 shadow-sm ring-2 ring-[#003628]/10'
                                                : 'border-slate-200 bg-white hover:border-[#003628]/30 hover:bg-slate-50 hover:shadow-sm'
                                        "
                                        @click="selectUserAsset(asset)"
                                    >
                                        <div class="flex items-center gap-3">
                                            <!-- Category icon badge -->
                                            <div
                                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg"
                                                :class="{
                                                    'bg-blue-50 text-blue-500':
                                                        (asset.asset_type || '')
                                                            .toLowerCase()
                                                            .includes(
                                                                'asset',
                                                            ) ||
                                                        (
                                                            asset.asset_type ||
                                                            ''
                                                        ).toLowerCase() ===
                                                            'assets',
                                                    'bg-violet-50 text-violet-500':
                                                        (asset.asset_type || '')
                                                            .toLowerCase()
                                                            .includes(
                                                                'accessor',
                                                            ),
                                                    'bg-amber-50 text-amber-500':
                                                        (asset.asset_type || '')
                                                            .toLowerCase()
                                                            .includes(
                                                                'component',
                                                            ),
                                                    'bg-slate-50 text-slate-400':
                                                        !(
                                                            asset.asset_type ||
                                                            ''
                                                        )
                                                            .toLowerCase()
                                                            .includes(
                                                                'asset',
                                                            ) &&
                                                        !(
                                                            asset.asset_type ||
                                                            ''
                                                        )
                                                            .toLowerCase()
                                                            .includes(
                                                                'accessor',
                                                            ) &&
                                                        !(
                                                            asset.asset_type ||
                                                            ''
                                                        )
                                                            .toLowerCase()
                                                            .includes(
                                                                'component',
                                                            ),
                                                }"
                                            >
                                                <Monitor
                                                    v-if="
                                                        (
                                                            asset.asset_type ||
                                                            ''
                                                        ).toLowerCase() ===
                                                            'assets' ||
                                                        (asset.asset_type || '')
                                                            .toLowerCase()
                                                            .includes(
                                                                'hardware',
                                                            )
                                                    "
                                                    class="size-4"
                                                />
                                                <Package
                                                    v-else-if="
                                                        (asset.asset_type || '')
                                                            .toLowerCase()
                                                            .includes(
                                                                'accessor',
                                                            )
                                                    "
                                                    class="size-4"
                                                />
                                                <Cpu
                                                    v-else-if="
                                                        (asset.asset_type || '')
                                                            .toLowerCase()
                                                            .includes(
                                                                'component',
                                                            )
                                                    "
                                                    class="size-4"
                                                />
                                                <Wrench v-else class="size-4" />
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <div
                                                    class="flex items-center gap-2"
                                                >
                                                    <p
                                                        class="truncate text-[13px] font-black text-slate-900"
                                                    >
                                                        {{ asset.name }}
                                                    </p>
                                                    <!-- Category pill -->
                                                    <span
                                                        class="inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-[9px] font-black tracking-widest uppercase"
                                                        :class="{
                                                            'bg-blue-100 text-blue-600':
                                                                (
                                                                    asset.asset_type ||
                                                                    ''
                                                                ).toLowerCase() ===
                                                                    'assets' ||
                                                                (
                                                                    asset.asset_type ||
                                                                    ''
                                                                )
                                                                    .toLowerCase()
                                                                    .includes(
                                                                        'hardware',
                                                                    ),
                                                            'bg-violet-100 text-violet-600':
                                                                (
                                                                    asset.asset_type ||
                                                                    ''
                                                                )
                                                                    .toLowerCase()
                                                                    .includes(
                                                                        'accessor',
                                                                    ),
                                                            'bg-amber-100 text-amber-600':
                                                                (
                                                                    asset.asset_type ||
                                                                    ''
                                                                )
                                                                    .toLowerCase()
                                                                    .includes(
                                                                        'component',
                                                                    ),
                                                            'bg-slate-100 text-slate-500':
                                                                !(
                                                                    asset.asset_type ||
                                                                    ''
                                                                )
                                                                    .toLowerCase()
                                                                    .includes(
                                                                        'asset',
                                                                    ) &&
                                                                !(
                                                                    asset.asset_type ||
                                                                    ''
                                                                )
                                                                    .toLowerCase()
                                                                    .includes(
                                                                        'accessor',
                                                                    ) &&
                                                                !(
                                                                    asset.asset_type ||
                                                                    ''
                                                                )
                                                                    .toLowerCase()
                                                                    .includes(
                                                                        'component',
                                                                    ),
                                                        }"
                                                    >
                                                        {{
                                                            asset.asset_type_label ||
                                                            asset.type_name ||
                                                            asset.asset_type ||
                                                            'Asset'
                                                        }}
                                                    </span>
                                                </div>
                                                <p
                                                    class="mt-0.5 flex items-center gap-1.5 text-[10px] text-slate-400"
                                                >
                                                    <span
                                                        v-if="asset.serial"
                                                        class="flex items-center gap-1"
                                                        ><Hash
                                                            class="size-2.5"
                                                        />{{
                                                            asset.serial
                                                        }}</span
                                                    >
                                                    <span
                                                        v-if="
                                                            asset.serial &&
                                                            asset.otherserial
                                                        "
                                                        class="text-slate-200"
                                                        >?</span
                                                    >
                                                    <span
                                                        v-if="asset.otherserial"
                                                        class="flex items-center gap-1"
                                                        ><Tag
                                                            class="size-2.5"
                                                        />{{
                                                            asset.otherserial
                                                        }}</span
                                                    >
                                                </p>
                                            </div>
                                            <!-- Selected checkmark -->
                                            <div
                                                v-if="
                                                    formData.snipeit_asset_id ===
                                                    asset.id
                                                "
                                                class="ml-1 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-[#003628]"
                                            >
                                                <svg
                                                    class="size-3 text-white"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                    stroke="currentColor"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        stroke-width="3"
                                                        d="M5 13l4 4L19 7"
                                                    />
                                                </svg>
                                            </div>
                                        </div>
                                    </button>
                                </div>
                                <!-- Selected asset summary -->
                                <div
                                    v-if="formData.snipeit_asset_id"
                                    class="flex items-center gap-3 rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-3"
                                >
                                    <CheckCircle2
                                        class="size-4 shrink-0 text-emerald-500"
                                    />
                                    <span
                                        class="flex-1 text-[10px] font-black tracking-widest text-emerald-600 uppercase"
                                        >{{
                                            formData.device_name
                                        }}
                                        dipilih</span
                                    >
                                    <button
                                        type="button"
                                        class="text-[9px] font-black tracking-widest text-slate-400 uppercase transition-colors hover:text-rose-500"
                                        @click="clearAsset"
                                    >
                                        Batal
                                    </button>
                                </div>
                            </template>

                            <!-- No assets -->
                            <div
                                v-else
                                class="flex flex-col items-center gap-3 py-6 text-center"
                            >
                                <div
                                    class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-50 text-slate-200"
                                >
                                    <Wrench class="size-5" />
                                </div>
                                <p class="text-[11px] font-bold text-slate-400">
                                    User ini tidak memiliki asset yang terdaftar
                                    di Snipe-IT
                                </p>
                            </div>
                        </template>

                        <!-- No user selected yet -->
                        <div
                            v-else
                            class="flex flex-col items-center gap-3 py-6 text-center"
                        >
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-50 text-slate-200"
                            >
                                <User class="size-5" />
                            </div>
                            <p class="text-[11px] font-bold text-slate-400">
                                Pilih user terlebih dahulu untuk melihat asset
                                yang dimiliki
                            </p>
                        </div>

                        <!-- Asset locked: show read-only fields -->
                        <div
                            v-if="assetLocked"
                            class="grid grid-cols-2 gap-x-12 gap-y-4"
                        >
                            <div class="space-y-2">
                                <label
                                    class="text-[11px] font-black tracking-widest text-slate-400 uppercase"
                                    >Asset Name</label
                                >
                                <div class="relative">
                                    <div
                                        class="flex h-10 items-center rounded-lg border border-slate-200 bg-slate-50 pr-4 pl-10 text-[13px] font-bold text-slate-500 italic shadow-inner"
                                    >
                                        {{ formData.device_name || '-' }}
                                    </div>
                                    <Tag
                                        class="absolute top-1/2 left-3.5 size-3.5 -translate-y-1/2 text-slate-300"
                                    />
                                </div>
                            </div>
                            <div class="space-y-2">
                                <label
                                    class="text-[11px] font-black tracking-widest text-slate-400 uppercase"
                                    >Asset Tag</label
                                >
                                <div class="relative">
                                    <div
                                        class="flex h-10 items-center rounded-lg border border-slate-200 bg-slate-50 pr-4 pl-10 text-[13px] font-bold text-slate-500 italic shadow-inner"
                                    >
                                        {{ formData.asset_tag || '-' }}
                                    </div>
                                    <Hash
                                        class="absolute top-1/2 left-3.5 size-3.5 -translate-y-1/2 text-slate-300"
                                    />
                                </div>
                            </div>
                            <div class="space-y-2">
                                <label
                                    class="text-[11px] font-black tracking-widest text-slate-400 uppercase"
                                    >Kategori</label
                                >
                                <div class="relative">
                                    <div
                                        class="flex h-10 items-center rounded-lg border border-slate-200 bg-slate-50 pr-4 pl-10 text-[13px] font-bold text-slate-500 italic shadow-inner"
                                    >
                                        {{
                                            deviceCategoryOptions.find(
                                                (o) =>
                                                    o.value ===
                                                    formData.device_category,
                                            )?.label ||
                                            formData.device_category ||
                                            '-'
                                        }}
                                    </div>
                                    <Wrench
                                        class="absolute top-1/2 left-3.5 size-3.5 -translate-y-1/2 text-slate-300"
                                    />
                                </div>
                            </div>
                            <div class="space-y-2">
                                <label
                                    class="text-[11px] font-black tracking-widest text-slate-400 uppercase"
                                    >Serial Number</label
                                >
                                <div class="relative">
                                    <div
                                        class="flex h-10 items-center rounded-lg border border-slate-200 bg-slate-50 pr-4 pl-10 text-[13px] font-bold text-slate-500 italic shadow-inner"
                                    >
                                        {{ formData.serial_number || '-' }}
                                    </div>
                                    <Tag
                                        class="absolute top-1/2 left-3.5 size-3.5 -translate-y-1/2 text-slate-300"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECTION 4: VERIFICATION (1 row: IT | Checked | Checked Date) -->
                <div class="border-b border-slate-50 px-8 py-6">
                    <div class="mx-auto max-w-4xl space-y-6">
                        <div class="flex items-center gap-3">
                            <div class="h-px flex-1 bg-slate-100" />
                            <span
                                class="text-[10px] font-black tracking-[0.2em] text-slate-400 uppercase"
                                >Verification</span
                            >
                            <div class="h-px flex-1 bg-slate-100" />
                        </div>
                        <div class="grid grid-cols-3 gap-x-8 gap-y-6">
                            <!-- IT (only IT dept) -->
                            <div class="space-y-2">
                                <label
                                    class="text-[11px] font-black tracking-widest text-slate-400 uppercase"
                                    >IT
                                    <span
                                        class="text-[9px] font-normal text-slate-300 normal-case"
                                        >(Dept IT)</span
                                    ></label
                                >
                                <div class="relative">
                                    <StbSearchableSelect
                                        v-model="formData.it_staff_id"
                                        :options="
                                            itUsers.map((u) => ({
                                                id: u.id,
                                                name: u.name,
                                                subtext: u.department_name,
                                            }))
                                        "
                                        placeholder="Cari IT staff..."
                                        :left-padding="true"
                                    />
                                    <User
                                        class="pointer-events-none absolute top-1/2 left-3.5 z-10 size-3.5 -translate-y-1/2 text-slate-300"
                                    />
                                </div>
                            </div>
                            <!-- Checked By (only IT dept) -->
                            <div class="space-y-2">
                                <label
                                    class="text-[11px] font-black tracking-widest text-slate-400 uppercase"
                                    >Checked
                                    <span class="text-rose-400">*</span>
                                    <span
                                        class="text-[9px] font-normal text-slate-300 normal-case"
                                        >(Dept IT)</span
                                    ></label
                                >
                                <div class="relative">
                                    <StbSearchableSelect
                                        v-model="formData.checked_by_id"
                                        :options="
                                            itUsers.map((u) => ({
                                                id: u.id,
                                                name: u.name,
                                                subtext: u.department_name,
                                            }))
                                        "
                                        placeholder="Cari IT staff..."
                                        :left-padding="true"
                                    />
                                    <ClipboardCheck
                                        class="pointer-events-none absolute top-1/2 left-3.5 z-10 size-3.5 -translate-y-1/2 text-slate-300"
                                    />
                                </div>
                                <p
                                    v-if="formErrors.checked_by"
                                    class="text-[10px] font-bold text-rose-500"
                                >
                                    {{ formErrors.checked_by }}
                                </p>
                            </div>
                            <!-- Checked Date -->
                            <div class="space-y-2">
                                <label
                                    class="text-[11px] font-black tracking-widest text-slate-400 uppercase"
                                    >Checked Date</label
                                >
                                <div class="relative">
                                    <input
                                        v-model="formData.checked_date"
                                        type="date"
                                        class="h-10 w-full rounded-lg border border-slate-200 bg-white pr-4 pl-10 text-[13px] font-bold text-slate-900 shadow-sm outline-none focus:border-[#003628]"
                                    /><Calendar
                                        class="absolute top-1/2 left-3.5 size-3.5 -translate-y-1/2 text-slate-300"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECTION 5: ISSUE & SOLUTION + PHOTO & REMARKS -->
                <div class="border-b border-slate-50 px-8 py-6">
                    <div class="mx-auto max-w-4xl space-y-6">
                        <div class="flex items-center gap-3">
                            <div class="h-px flex-1 bg-slate-100" />
                            <span
                                class="text-[10px] font-black tracking-[0.2em] text-slate-400 uppercase"
                                >Issue & Solution</span
                            >
                            <div class="h-px flex-1 bg-slate-100" />
                        </div>
                        <!-- Row 1: Issue | Solution -->
                        <div class="grid grid-cols-2 items-start gap-8">
                            <div class="space-y-2">
                                <label
                                    class="text-[11px] font-black tracking-widest text-slate-400 uppercase"
                                    >Issue (Description)
                                    <span class="text-rose-400">*</span></label
                                >
                                <div class="relative">
                                    <textarea
                                        v-model="formData.issue_description"
                                        rows="5"
                                        placeholder="Deskripsi masalah yang ditemukan..."
                                        class="w-full resize-none rounded-2xl border border-slate-200 bg-white px-4 py-3 text-[13px] font-medium text-slate-900 shadow-sm transition-all outline-none placeholder:text-slate-300 focus:border-[#003628] focus:ring-4 focus:ring-[#003628]/5"
                                        :class="
                                            formErrors.issue_description
                                                ? 'border-rose-300'
                                                : ''
                                        "
                                    ></textarea>
                                </div>
                                <p
                                    v-if="formErrors.issue_description"
                                    class="text-[10px] font-bold text-rose-500"
                                >
                                    {{ formErrors.issue_description }}
                                </p>
                            </div>
                            <div class="space-y-2">
                                <label
                                    class="text-[11px] font-black tracking-widest text-slate-400 uppercase"
                                    >Solution
                                    <span class="text-rose-400">*</span></label
                                >
                                <div class="relative">
                                    <textarea
                                        v-model="formData.solution"
                                        rows="5"
                                        placeholder="Solusi yang diberikan..."
                                        class="w-full resize-none rounded-2xl border border-slate-200 bg-white px-4 py-3 text-[13px] font-medium text-slate-900 shadow-sm transition-all outline-none placeholder:text-slate-300 focus:border-[#003628] focus:ring-4 focus:ring-[#003628]/5"
                                        :class="
                                            formErrors.solution
                                                ? 'border-rose-300'
                                                : ''
                                        "
                                    ></textarea>
                                </div>
                                <p
                                    v-if="formErrors.solution"
                                    class="text-[10px] font-bold text-rose-500"
                                >
                                    {{ formErrors.solution }}
                                </p>
                            </div>
                        </div>

                        <!-- Row 2: Photo | Remarks (STB style) -->
                        <div class="mt-2 flex items-center gap-3">
                            <div class="h-px flex-1 bg-slate-100" />
                            <span
                                class="text-[10px] font-black tracking-[0.2em] text-slate-400 uppercase"
                                >Lampiran & Catatan</span
                            >
                            <div class="h-px flex-1 bg-slate-100" />
                        </div>
                        <div class="grid grid-cols-2 items-start gap-8">
                            <!-- Photo ? STB style -->
                            <div class="space-y-3">
                                <label
                                    class="text-[11px] font-black tracking-widest text-slate-400 uppercase"
                                    >Foto Aset</label
                                >
                                <label
                                    class="group relative block cursor-pointer transition-all active:scale-[0.99]"
                                >
                                    <div
                                        class="overflow-hidden rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50/50 shadow-sm transition-all hover:border-[#003628]/30 hover:bg-white"
                                    >
                                        <div
                                            class="relative flex min-h-[160px] items-center justify-center p-2"
                                        >
                                            <img
                                                v-if="
                                                    photoPreview ||
                                                    existingPhotoUrl
                                                "
                                                :src="
                                                    photoPreview ||
                                                    existingPhotoUrl ||
                                                    ''
                                                "
                                                class="max-h-[250px] w-full rounded-xl object-contain shadow-xl transition-transform duration-500 group-hover:scale-[1.02]"
                                                alt="Inspection Photo"
                                            />
                                            <div
                                                v-else
                                                class="flex flex-col items-center justify-center space-y-3 p-6 text-center"
                                            >
                                                <div
                                                    class="flex size-12 items-center justify-center rounded-full border border-slate-100 bg-white text-slate-300 shadow-sm transition-all group-hover:scale-110 group-hover:text-[#003628]"
                                                >
                                                    <Camera class="size-6" />
                                                </div>
                                                <div class="space-y-0.5">
                                                    <span
                                                        class="block text-[10px] font-black tracking-widest text-slate-500 uppercase"
                                                        >Ambil Foto</span
                                                    >
                                                    <span
                                                        class="block text-[9px] font-bold text-slate-400 uppercase"
                                                        >JPEG/PNG Maks 1MB</span
                                                    >
                                                </div>
                                            </div>
                                            <!-- Hover overlay -->
                                            <div
                                                class="absolute inset-0 flex items-center justify-center bg-[#003628]/60 opacity-0 backdrop-blur-sm transition-opacity group-hover:opacity-100"
                                            >
                                                <div
                                                    class="flex scale-90 items-center gap-2 rounded-full bg-white px-4 py-2 shadow-2xl transition-transform group-hover:scale-100"
                                                >
                                                    <Camera
                                                        class="size-3 text-[#003628]"
                                                    />
                                                    <span
                                                        class="text-[9px] font-black tracking-widest text-[#003628] uppercase"
                                                        >{{
                                                            photoPreview ||
                                                            existingPhotoUrl
                                                                ? 'Ubah'
                                                                : 'Unggah'
                                                        }}</span
                                                    >
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Status bar -->
                                        <div
                                            v-if="photoPreview"
                                            class="flex items-center gap-1.5 border-t border-slate-100 bg-white/80 px-4 py-2"
                                        >
                                            <CheckCircle2
                                                class="size-3.5 text-emerald-500"
                                            />
                                            <span
                                                class="text-[9px] font-black tracking-widest text-slate-600 uppercase"
                                                >Foto terpilih</span
                                            >
                                        </div>
                                    </div>
                                    <input
                                        type="file"
                                        class="hidden"
                                        accept="image/*"
                                        @change="handlePhotoChange"
                                    />
                                </label>
                            </div>

                            <!-- Remarks ? STB style -->
                            <div class="space-y-3">
                                <label
                                    class="text-[11px] font-black tracking-widest text-slate-400 uppercase"
                                    >Catatan Tambahan</label
                                >
                                <div class="relative h-full min-h-[160px]">
                                    <textarea
                                        v-model="formData.remarks"
                                        class="h-full min-h-[160px] w-full resize-none rounded-2xl border border-slate-200 bg-white px-4 py-3 text-[13px] font-medium text-slate-900 shadow-sm transition-all outline-none placeholder:text-slate-300 focus:border-[#003628] focus:ring-4 focus:ring-[#003628]/5"
                                        placeholder="Masukkan catatan khusus atau kondisi aset..."
                                    ></textarea>
                                    <div
                                        class="pointer-events-none absolute right-4 bottom-4 opacity-10"
                                    >
                                        <FileText
                                            class="size-8 text-[#003628]"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ACTIONS -->
                <div class="flex items-center justify-end gap-3 px-8 py-8">
                    <button
                        type="button"
                        class="h-10 rounded-lg border border-slate-200 bg-white px-6 text-[11px] font-black tracking-widest text-slate-500 uppercase shadow-sm transition-all hover:bg-slate-50 active:scale-95"
                        @click="emit('cancel')"
                    >
                        Discard
                    </button>
                    <button
                        type="submit"
                        :disabled="isLoading"
                        class="flex h-10 items-center gap-2 rounded-lg bg-[#003628] px-8 text-[11px] font-black tracking-widest text-white uppercase shadow-md transition-all hover:brightness-110 active:scale-95 disabled:opacity-50"
                    >
                        <Loader2 v-if="isLoading" class="size-4 animate-spin" />
                        <span>{{ isLoading ? 'Menyimpan...' : 'Simpan' }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Inspection Item Picker Modal (no license, no consumable) -->
        <InspectionItemPickerModal
            :open="pickerOpen"
            :assets-by-category="directory.assets"
            :loading-by-category="directory.assetLoading"
            @select="handleAssetSelect"
            @update:open="pickerOpen = $event"
            @load-category="(cat) => directory.ensureAssetsLoaded(cat)"
        />
    </form>
</template>
