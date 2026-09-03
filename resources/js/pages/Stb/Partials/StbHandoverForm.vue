<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { RefreshCw } from 'lucide-vue-next';
import {
    computed,
    defineAsyncComponent,
    onMounted,
    reactive,
    ref,
    watch,
} from 'vue';
import { useRenderProfiler } from '@/composables/useRenderProfiler';
import type { SnipeAsset } from '@/composables/useSnipeDirectory';
import type {
    LoanReferenceOption,
    StbDocumentType,
    StbMovementType,
} from '@/pages/Stb/types';
import {
    resolveStbDateLabel,
    formatStbDocId,
    resolveStbFlowTitle,
    resolveStbPhotoLabel,
    resolveStbRemarkLabel,
    resolveStbRequesterRoleLabels,
} from '@/utils/stb';
import {
    getStbAssetById,
    getStbAssetLabel,
    getStbAssetReferenceLabel,
    getStbAssetReferenceValue,
    getStbDeptHeadLabel,
    getStbGroupParts,
    getStbUserEmail,
    getStbUserLabel,
    getStbUserPhone,
    getStbUserPosition,
    normalizeStbAssetCategory,
    useStbDirectory,
} from '@/utils/stbDirectory';
import StbFormAttachmentSection from './StbFormAttachmentSection.vue';
import StbFormDocumentSection from './StbFormDocumentSection.vue';
import StbFormItemsSection from './StbFormItemsSection.vue';
import StbFormApprovalSection from './StbFormRecipientSection.vue';

const StbItemPickerModal = defineAsyncComponent(
    () => import('@/pages/Stb/Partials/StbItemPickerModal.vue'),
);

useRenderProfiler('StbHandoverForm');

const page = usePage();
const rememberTeam = ref(localStorage.getItem('stb_remember_team') === 'true');
const currentUserSnipeId = computed(() =>
    Number(
        (page.props.auth.user as any)?.snipeit_user_id ??
            (page.props.auth.user as any)?.snipeitId ??
            0,
    ),
);
const currentUserName = computed(
    () => (page.props.auth.user as any)?.name || 'AUTOMATIC',
);

interface ItemBarang {
    id?: number;
    nama: string;
    kategori: string;
    type: string;
    jumlah: number | null;
    serialNo: string;
    computer_id: number | null;
    snipeit_asset_id: number | null;
    inventory_number: string;
    condition: string;
    is_selected?: boolean;
}

interface StbFormData {
    id: number | null;
    status: number | string | null;
    documentType: StbDocumentType | string;
    movementType: StbMovementType | string;
    linkedStbId: number | string;
    user_id: number | string;
    user_email: string;
    user_phone: string;
    group_id: number | string;
    deliverDate: string;
    building: string;
    useDate: string;
    batchNo: string;
    reqDocNo: string;
    poDocNo: string;
    itDrafter_id: number | string;
    itChecker_id: number | string;
    itApproved_id: number | string;
    photo: File | null;
    remark: string;
    createDate: string;
}

interface ItemFieldErrors {
    nama?: string;
    kategori?: string;
    type?: string;
    jumlah?: string;
    computer_id?: string;
}

interface FormErrors {
    documentType?: string;
    movementType?: string;
    linkedStbId?: string;
    user_id?: string;
    group_id?: string;
    items?: string;
}

interface PhotoSelectionStats {
    originalBytes: number;
    finalBytes: number;
    wasCompressed: boolean;
}

interface Props {
    initialData?: Record<string, any>;
    isLoading?: boolean;
    pageKicker?: string;
    pageTitle?: string;
    pageCopy?: string;
    allowedDocumentTypes?: Array<{
        value: string;
        label: string;
    }>;
}

interface Emits {
    (e: 'save', data: Record<string, any>): void;
    (e: 'cancel'): void;
}

const DOCUMENT_TYPE_OPTIONS = [
    { value: 'handover', label: 'Hand Over' },
    { value: 'loan', label: 'Loan' },
    { value: 'service', label: 'Service' },
];

const PICKER_CATEGORIES = [
    'assets',
    'license',
    'accessories',
    'consumable',
    'component',
] as const;
type PickerCategory = (typeof PICKER_CATEGORIES)[number];

const props = withDefaults(defineProps<Props>(), {
    initialData: () => ({}),
    isLoading: false,
    pageKicker: 'SURAT TANDA BUKTI',
    pageTitle: '',
    pageCopy: '',
});

const emit = defineEmits<Emits>();

const isLoading = computed(() => props.isLoading);
const isCompressingPhoto = ref(false);
const formErrors = ref<FormErrors>({});
const itemErrors = ref<ItemFieldErrors[]>([]);
const photoSelectionStats = ref<PhotoSelectionStats | null>(null);
const formNotice = ref<{ type: 'warning' | 'info'; message: string } | null>(
    null,
);

const PHOTO_MAX_WIDTH = 1280;
const PHOTO_MAX_HEIGHT = 1280;
const PHOTO_TARGET_BYTES = 70 * 1024;
const PHOTO_HARD_LIMIT_BYTES = 100 * 1024;
const PHOTO_MIN_QUALITY = 0.35;

const loanReferences = computed<LoanReferenceOption[]>(
    () => props.initialData?.loanReferences ?? [],
);

const deriveLegacyStatus = (documentType: string, movementType: string) => {
    if (documentType === 'handover' && movementType === 'handover') return 1;
    if (documentType === 'loan' && movementType === 'out') return 3;
    if (documentType === 'service' && movementType === 'out') return 4;
    return null;
};

const getCurrentDate = () => {
    const now = new Date();
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
};

const getCurrentDateTime = () => {
    const now = new Date();
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}T${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
};

const toDateTimeLocal = (value?: string | null) => {
    if (!value) return getCurrentDateTime();
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return getCurrentDateTime();
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}T${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
};

const formatFileSize = (bytes: number) => {
    if (bytes < 1024) return `${bytes} B`;
    const units = ['KB', 'MB', 'GB'];
    let value = bytes / 1024;
    let unitIndex = 0;
    while (value >= 1024 && unitIndex < units.length - 1) {
        value /= 1024;
        unitIndex += 1;
    }
    return `${value >= 10 ? value.toFixed(0) : value.toFixed(1)} ${units[unitIndex]}`;
};

const createEmptyItem = (): ItemBarang => ({
    nama: '',
    kategori: 'manual',
    type: '',
    jumlah: 1,
    serialNo: '',
    computer_id: null,
    snipeit_asset_id: null,
    inventory_number: '',
    condition: 'Good',
});

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

const createEmptyItemErrors = (): ItemFieldErrors => ({});

const mapInitialItem = (item: any): ItemBarang => ({
    id: item.id,
    nama: item.nama || '',
    kategori: item.kategori || 'assets',
    type: item.type || '',
    jumlah: item.jumlah ?? 1,
    serialNo: item.serialNo || item.serial_no || '',
    computer_id: item.computer_id ?? null,
    snipeit_asset_id: item.snipeit_asset_id ?? null,
    inventory_number: item.inventory_number || '',
    condition: item.condition || 'Good',
});

const formData = ref<StbFormData>({
    id: props.initialData?.id ?? props.initialData?.previewId ?? null,
    status: props.initialData?.status ?? null,
    documentType: props.initialData?.documentType || 'handover',
    movementType:
        props.initialData?.movementType ||
        (props.initialData?.documentType === 'handover' ? 'out' : 'handover'),
    linkedStbId: props.initialData?.linkedStbId ?? '',
    user_id: props.initialData?.user_id ?? '',
    user_email: props.initialData?.user_email || '',
    user_phone: props.initialData?.user_phone || '',
    group_id: props.initialData?.group_id ?? '',
    deliverDate: props.initialData?.deliverDate || getCurrentDate(),
    building: props.initialData?.building || '',
    useDate: props.initialData?.useDate || getCurrentDate(),
    batchNo: props.initialData?.batchNo || '',
    reqDocNo: props.initialData?.reqDocNo || '',
    poDocNo: props.initialData?.poDocNo || '',
    itDrafter_id: props.initialData?.itDrafter_id ?? '',
    itChecker_id: props.initialData?.itChecker_id ?? '',
    itApproved_id: props.initialData?.itApproved_id ?? '',
    photo: null,
    remark: props.initialData?.remark || '',
    createDate: toDateTimeLocal(props.initialData?.createDate),
});

const items = ref<ItemBarang[]>(
    props.initialData?.items?.length
        ? props.initialData.items.map(mapInitialItem)
        : [],
);

itemErrors.value = items.value.map(() => createEmptyItemErrors());

const normalizeInteger = (value: number | string | null) => {
    if (value === '' || value === null || typeof value === 'undefined')
        return null;
    const parsed = Number(value);
    return Number.isNaN(parsed) ? null : parsed;
};

const documentTypeOptions = computed(() =>
    props.allowedDocumentTypes?.length
        ? props.allowedDocumentTypes
        : DOCUMENT_TYPE_OPTIONS,
);

const isDocumentFlowPreset = computed(() =>
    Boolean(props.initialData?.documentType && props.initialData?.movementType),
);

const documentFlowLabel = computed(() =>
    resolveStbFlowTitle(
        {
            document_type: String(formData.value.documentType || ''),
            movement_type: String(formData.value.movementType || ''),
        },
        '-',
    ),
);
const documentDateLabel = computed(() =>
    resolveStbDateLabel({
        document_type: String(formData.value.documentType || ''),
        movement_type: String(formData.value.movementType || ''),
    }),
);

const attachmentSectionKicker = ref('Lampiran');
const attachmentSectionTitle = computed(() => {
    if (formData.value.documentType === 'loan')
        return 'Foto & catatan peminjaman';
    return 'Foto & catatan tambahan';
});
const attachmentSectionCopy = computed(() => {
    if (formData.value.documentType === 'loan')
        return 'Lampirkan foto asset yang dipinjamkan dan catatan tambahan.';
    return 'Lampirkan foto pendukung dan catatan tambahan.';
});

const attachmentPhotoLabel = computed(() =>
    resolveStbPhotoLabel({
        document_type: String(formData.value.documentType || ''),
        movement_type: String(formData.value.movementType || ''),
    }),
);
const attachmentRemarkLabel = computed(() =>
    resolveStbRemarkLabel({
        document_type: String(formData.value.documentType || ''),
        movement_type: String(formData.value.movementType || ''),
    }),
);

const attachmentPreviewAlt = computed(
    () => `${attachmentPhotoLabel.value} STB`,
);
const attachmentEmptyLabel = computed(
    () => `${attachmentPhotoLabel.value} akan tampil di sini.`,
);

const movementOptions = computed(() => {
    if (formData.value.documentType === 'loan')
        return [{ value: 'out', label: 'Loaned' }];
    if (formData.value.documentType === 'service')
        return [{ value: 'out', label: 'Service' }];
    return [{ value: 'out', label: 'Hand Over' }];
});

const isBlank = (value: unknown) =>
    value === null ||
    value === undefined ||
    (typeof value === 'string' && value.trim() === '');

const selectedUserId = computed(() => normalizeInteger(formData.value.user_id));
const selectedGroupId = computed(() =>
    normalizeInteger(formData.value.group_id),
);
const directory = reactive(useStbDirectory());

const groupParts = computed(() =>
    getStbGroupParts(selectedGroupId.value, selectedUserId.value),
);
const itUsers = computed(() => {
    return directory.users.filter((user) => {
        const dept = (user.department_name || '').toUpperCase();
        return dept === 'IT' || dept.includes('INFORMATION TECHNOLOGY');
    });
});

const resolvedLocationLabel = computed(() => {
    const selectedLocation = directory.groups.find(
        (g) => g.id === Number(selectedGroupId.value),
    );
    return selectedLocation
        ? selectedLocation.name
        : groupParts.value.location || '-';
});

const resolvedName = computed(() => getStbUserLabel(selectedUserId.value));
const phoneNumber = computed(() => getStbUserPhone(selectedUserId.value));
const email = computed(() => getStbUserEmail(selectedUserId.value));
const position = computed(() => getStbUserPosition(selectedUserId.value));
const deptHead = computed(() => getStbDeptHeadLabel(selectedUserId.value));
const requesterReceived = computed(() => resolvedName.value);
const requesterDeptHead = computed(() => deptHead.value);

const requesterRoleLabels = computed(() =>
    resolveStbRequesterRoleLabels({
        document_type: String(formData.value.documentType || ''),
        movement_type: String(formData.value.movementType || ''),
    }),
);

const drafterDisplayName = computed(() => {
    const drafterId = normalizeInteger(formData.value.itDrafter_id);
    if (drafterId && drafterId > 0) {
        return getStbUserLabel(drafterId) || currentUserName.value;
    }

    return currentUserName.value;
});

const recipientSectionKicker = computed(
    () => requesterRoleLabels.value.section,
);
const recipientSectionTitle = computed(() =>
    formData.value.documentType === 'loan'
        ? 'Profil peminjam'
        : 'Profil penerima',
);
const recipientSectionCopy = computed(() =>
    formData.value.documentType === 'loan'
        ? 'Data peminjam otomatis mengikuti user.'
        : 'Data penerima otomatis mengikuti user.',
);

const requesterSectionKicker = ref('Pemohon');
const requesterSectionTitle = computed(() =>
    formData.value.documentType === 'loan'
        ? 'Peminjam & atasan'
        : 'Pihak penerima & dept head',
);
const requesterSectionCopy = computed(
    () =>
        'Nama pemohon dan kepala departemen mengikuti struktur user yang aktif.',
);

const itemsSectionTitle = computed(() => {
    if (formData.value.documentType === 'loan')
        return 'Daftar Aset Dipinjamkan';
    if (formData.value.documentType === 'service')
        return 'Daftar Aset Diperbaiki';
    return 'Daftar Aset Diserahkan';
});
const itemsSectionCopy = computed(
    () => 'Pilih aset dari direktori atau isi manual bila item belum tersedia.',
);

const selectedLoanReference = computed(() => {
    const linkedStbId = normalizeInteger(formData.value.linkedStbId);
    if (!linkedStbId) return null;
    return loanReferences.value.find((ref) => ref.id === linkedStbId) ?? null;
});

const topHeroTitle = computed(
    () => props.pageTitle || 'FORM SERAH TERIMA BARANG',
);
const topHeroCopy = computed(
    () =>
        props.pageCopy ||
        'Lengkapi data serah terima aset sesuai dengan peruntukannya.',
);

const getItemReferenceLabel = (category?: string | null) =>
    getStbAssetReferenceLabel(category || 'assets');

// --- Item Picker Modal ---
const itemPickerOpen = ref(false);
const itemPickerIndex = ref(-1);

const pickerAssetsByCategory = computed(() =>
    Object.fromEntries(
        PICKER_CATEGORIES.map((cat) => [cat, directory.assets[cat] ?? []]),
    ),
);
const pickerLoadingByCategory = computed(() =>
    Object.fromEntries(
        PICKER_CATEGORIES.map((cat) => [
            cat,
            directory.assetLoading[cat] ?? false,
        ]),
    ),
);

const openItemPicker = (index: number) => {
    itemPickerIndex.value = index;
    void directory.ensureAssetsLoaded('assets');
    itemPickerOpen.value = true;
};

const userAssignedAssets = ref<Record<string, SnipeAsset[]>>({
    assets: [],
    license: [],
    accessories: [],
    component: [],
});

const fetchUserAssignedAssets = async (userId: number) => {
    if (!userId || userId <= 0) {
        userAssignedAssets.value = {
            assets: [],
            license: [],
            accessories: [],
            component: [],
        };
        return;
    }
    try {
        const types = [
            'assets',
            'license',
            'accessories',
            'component',
        ] as const;
        const results = await Promise.all(
            types.map((type) => directory.fetchUserAssets(userId, type)),
        );
        types.forEach((type, i) => {
            userAssignedAssets.value[type] = results[i];
        });
    } catch (e) {
        console.warn('[StbHandoverForm] fetchUserAssignedAssets error:', e);
    }
};

const handlePickerLoadCategory = (cat: PickerCategory, force = false) =>
    void directory.ensureAssetsLoaded(cat, force);

const handlePickerSelect = (asset: SnipeAsset) => {
    let item: ItemBarang;
    if (itemPickerIndex.value === -1) {
        item = createEmptyItem();
        items.value.push(item);
        itemErrors.value.push(createEmptyItemErrors());
    } else {
        item = items.value[itemPickerIndex.value];
    }
    if (!item) return;

    item.computer_id = null;
    item.snipeit_asset_id = asset.id;
    const category = normalizeStbAssetCategory(asset.asset_type);
    item.kategori = category;
    if (category === 'assets') {
        item.computer_id = asset.id;
    }

    item.nama = asset.name || item.nama;
    item.type = asset.asset_type_label || asset.type_name || item.type;
    item.serialNo = asset.serial || item.serialNo;
    item.inventory_number =
        getStbAssetReferenceValue(asset) || item.inventory_number;
};

const applyAssetSelection = (item: ItemBarang) => {
    if (item.kategori !== 'assets') return;
    const assetId = item.snipeit_asset_id || item.computer_id;
    const asset = getStbAssetById(assetId, item.kategori);
    if (!asset) return;
    item.nama = asset.name || item.nama;
    item.type = asset.asset_type_label || item.type;
    item.serialNo = asset.serial || item.serialNo;
    item.inventory_number =
        getStbAssetReferenceValue(asset) || item.inventory_number;
};

const handleUserChange = () => {
    const userId = selectedUserId.value;
    if (!userId) {
        formData.value.group_id = '';
        return;
    }
    const selectedUser = directory.users.find((u) => u.id === userId);
    if (selectedUser?.location_id)
        formData.value.group_id = selectedUser.location_id;
    formData.value.user_email = getStbUserEmail(selectedUser);
    formData.value.user_phone = getStbUserPhone(selectedUser);
};

onMounted(async () => {
    await directory.ensureDirectoryLoaded();
    handleUserChange();
    if (!formData.value.id) {
        if (currentUserSnipeId.value > 0) {
            formData.value.itDrafter_id = currentUserSnipeId.value;
        }

        // Auto-fill dates for new documents
        if (!formData.value.deliverDate)
            formData.value.deliverDate = getCurrentDate();
        if (!formData.value.useDate) formData.value.useDate = getCurrentDate();

        if (rememberTeam.value) {
            formData.value.itChecker_id =
                localStorage.getItem('stb_it_checker_id') || '';
            formData.value.itApproved_id =
                localStorage.getItem('stb_it_approved_id') || '';
        }
    }
    await Promise.all(
        PICKER_CATEGORIES.map((cat) => directory.ensureAssetsLoaded(cat)),
    );
    items.value.forEach((item) => applyAssetSelection(item));
    // Fetch user owned assets if a user is already set (edit mode)
    if (selectedUserId.value && selectedUserId.value > 0) {
        await fetchUserAssignedAssets(selectedUserId.value);
    }
});

watch(rememberTeam, (val) => {
    localStorage.setItem('stb_remember_team', String(val));
    if (val) {
        if (formData.value.itChecker_id)
            localStorage.setItem(
                'stb_it_checker_id',
                String(formData.value.itChecker_id),
            );
        if (formData.value.itApproved_id)
            localStorage.setItem(
                'stb_it_approved_id',
                String(formData.value.itApproved_id),
            );
    }
});

watch(
    () => formData.value.itChecker_id,
    (val) => {
        if (rememberTeam.value && val)
            localStorage.setItem('stb_it_checker_id', String(val));
    },
);
watch(
    () => formData.value.itApproved_id,
    (val) => {
        if (rememberTeam.value && val)
            localStorage.setItem('stb_it_approved_id', String(val));
    },
);
watch(selectedUserId, (newId) => {
    handleUserChange();
    if (newId && newId > 0) fetchUserAssignedAssets(newId);
    else
        userAssignedAssets.value = {
            assets: [],
            license: [],
            accessories: [],
            component: [],
        };
});

const addItem = () => {
    items.value.push(createEmptyItem());
    itemErrors.value.push(createEmptyItemErrors());
};
const removeItem = (index: number) => {
    items.value.splice(index, 1);
    itemErrors.value.splice(index, 1);
};

// --- Image Logic ---
const compressImage = async (file: File) => {
    const source = await readFileAsDataUrl(file);
    const image = await loadImage(source);
    const canvas = document.createElement('canvas');
    const context = canvas.getContext('2d');

    if (!context) {
        throw new Error('Canvas is not supported in this browser');
    }

    const outputType = 'image/jpeg';
    let scale = Math.min(
        1,
        PHOTO_MAX_WIDTH / image.width,
        PHOTO_MAX_HEIGHT / image.height,
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

        while (
            blob.size > PHOTO_TARGET_BYTES &&
            currentQuality > PHOTO_MIN_QUALITY
        ) {
            currentQuality = Number((currentQuality - 0.07).toFixed(2));
            blob = await canvasToBlob(canvas, outputType, currentQuality);
        }

        if (blob.size <= PHOTO_HARD_LIMIT_BYTES) {
            break;
        }

        scale = Number((scale - 0.1).toFixed(2));
        quality = Math.max(
            PHOTO_MIN_QUALITY,
            Number((currentQuality - 0.03).toFixed(2)),
        );
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
    const target = event.target as HTMLInputElement;
    const files = target.files;

    if (!files || files.length === 0) {
        return;
    }

    isCompressingPhoto.value = true;
    photoLoadFailed.value = false;

    try {
        const originalFile = files[0];
        const nextPhoto = await compressImage(originalFile);

        formData.value.photo = nextPhoto;
        photoSelectionStats.value = {
            originalBytes: originalFile.size,
            finalBytes: nextPhoto.size,
            wasCompressed: nextPhoto.size < originalFile.size,
        };
    } catch (error) {
        console.error(error);
        formData.value.photo = files[0];
        photoSelectionStats.value = {
            originalBytes: files[0].size,
            finalBytes: files[0].size,
            wasCompressed: false,
        };
        formNotice.value = {
            type: 'warning',
            message:
                'Kompresi foto gagal. File asli tetap akan digunakan saat disimpan.',
        };
    } finally {
        isCompressingPhoto.value = false;
        target.value = '';
    }
};

const photoLoadFailed = ref(false);

const normalizeStoredPhotoSource = (value: unknown) => {
    if (value === null || value === undefined) {
        return null;
    }

    const rawPath = String(value).trim();
    if (!rawPath) {
        return null;
    }

    if (
        rawPath.startsWith('http://') ||
        rawPath.startsWith('https://') ||
        rawPath.startsWith('data:')
    ) {
        return rawPath;
    }

    if (rawPath.startsWith('/storage/')) {
        return rawPath;
    }

    if (rawPath.startsWith('storage/')) {
        return `/${rawPath}`;
    }

    if (rawPath.startsWith('/')) {
        return `/storage${rawPath}`;
    }

    if (rawPath.startsWith('public/')) {
        return `/storage/${rawPath.replace(/^public\//, '')}`;
    }

    return `/storage/${rawPath.replace(/^\/+/, '')}`;
};

const existingPhotoPreview = computed(() => {
    if (!props.initialData?.photo) {
        return null;
    }

    return normalizeStoredPhotoSource(props.initialData.photo);
});

const photoPreview = computed(() => {
    if (formData.value.photo) {
        return window.URL.createObjectURL(formData.value.photo);
    }

    return existingPhotoPreview.value;
});

const photoSummary = computed(() => {
    if (formData.value.photo) {
        return {
            title: formData.value.photo.name,
            helper: `Ukuran file ${formatFileSize(formData.value.photo.size)}. Foto siap untuk disimpan.`,
        };
    }

    if (props.initialData?.photo && existingPhotoPreview.value) {
        return {
            title: 'Foto tersimpan',
            helper: 'Foto yang sudah tersimpan akan tetap digunakan selama Anda belum menggantinya.',
        };
    }

    return null;
});

const handlePhotoPreviewError = () => {
    if (formData.value.photo) {
        photoLoadFailed.value = true;
        return;
    }

    // Do not blank out the existing photo during edit mode just because the browser
    // rejected a transient image load; keep the saved photo path available for retry.
    photoLoadFailed.value = false;
};

const validateForm = () => {
    formErrors.value = {};
    let isValid = true;
    if (!formData.value.user_id) {
        formErrors.value.user_id = 'User wajib dipilih';
        isValid = false;
    }
    if (items.value.length === 0) {
        formErrors.value.items = 'Minimal satu item';
        isValid = false;
    }
    items.value.forEach((item, index) => {
        const errs: ItemFieldErrors = {};
        if (isBlank(item.nama)) {
            errs.nama = 'Nama wajib diisi';
            isValid = false;
        }
        itemErrors.value[index] = errs;
    });
    return isValid;
};

const docIdDisplay = computed(() => {
    if (!formData.value.id) return '';
    return (
        formatStbDocId({
            id: formData.value.id,
            locationName: resolvedLocationLabel.value,
            date: formData.value.createDate,
        }) || ''
    );
});

const handleSubmit = () => {
    if (!validateForm()) return;

    if (
        !normalizeInteger(formData.value.itDrafter_id) &&
        currentUserSnipeId.value > 0
    ) {
        formData.value.itDrafter_id = currentUserSnipeId.value;
    }

    emit('save', {
        ...formData.value,
        docId: docIdDisplay.value || null,
        id: normalizeInteger(formData.value.id),
        status: deriveLegacyStatus(
            String(formData.value.documentType),
            String(formData.value.movementType || 'out'),
        ),
        movementType: String(formData.value.movementType || 'out'),
        itDrafter_id: normalizeInteger(formData.value.itDrafter_id) ?? null,
        items: items.value.map((item) => ({
            ...item,
            nama: item.nama.trim(),
            jumlah: Number(item.jumlah),
        })),
    });
};
</script>

<template>
    <form
        class="min-h-screen bg-[#f8fafc] pb-20"
        @submit.prevent="handleSubmit"
    >
        <div class="mx-auto max-w-5xl space-y-8 px-6 pt-10">
            <div
                class="overflow-hidden rounded-2xl bg-white shadow-[0_8px_30px_rgb(0,0,0,0.02)]"
            >
                <div
                    class="flex items-center justify-between border-b border-slate-100 px-8 py-8"
                >
                    <div class="space-y-1">
                        <h1
                            class="text-2xl font-black tracking-tight text-[#003628] uppercase"
                        >
                            {{ props.pageKicker }}
                        </h1>
                        <p
                            class="text-[11px] font-black tracking-[0.2em] text-[#d99528] uppercase"
                        >
                            {{ topHeroTitle }}
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            :disabled="directory.directoryLoading"
                            class="flex h-9 items-center gap-1.5 rounded-lg bg-slate-100 px-4 text-[10px] font-black tracking-widest text-slate-600 uppercase transition-all hover:bg-slate-200 disabled:opacity-40"
                            @click="directory.ensureDirectoryLoaded(true)"
                        >
                            <RefreshCw
                                :class="[
                                    'size-3',
                                    directory.directoryLoading &&
                                        'animate-spin',
                                ]"
                            />
                            Refresh Directory
                        </button>
                    </div>
                </div>

                <div class="px-8 py-3">
                    <StbFormDocumentSection
                        :doc-id-display="docIdDisplay"
                        :form-data="formData"
                        :users="directory.users"
                        :document-type-options="documentTypeOptions"
                        :movement-options="movementOptions"
                        :loan-references="loanReferences"
                        :resolved-location-label="resolvedLocationLabel"
                        :form-errors="formErrors"
                        :lock-document-flow="isDocumentFlowPreset"
                        :document-flow-label="documentFlowLabel"
                        :document-date-label="documentDateLabel"
                        :selected-loan-reference-label="
                            selectedLoanReference?.label || ''
                        "
                    />
                </div>

                <div class="px-8 py-3">
                    <StbFormApprovalSection
                        v-model:remember-team="rememberTeam"
                        :form-data="formData"
                        :users="directory.users"
                        :it-users="itUsers"
                        :resolved-name="resolvedName"
                        :dept-head="deptHead"
                        :group-parts="groupParts"
                        :phone-number="phoneNumber"
                        :email="email"
                        :position="position"
                        :requester-received="requesterReceived"
                        :requester-dept-head="requesterDeptHead"
                        :recipient-kicker="recipientSectionKicker"
                        :recipient-title="recipientSectionTitle"
                        :recipient-copy="recipientSectionCopy"
                        :requester-kicker="requesterKicker"
                        :requester-title="requesterTitle"
                        :requester-copy="requesterCopy"
                        :requester-received-label="requesterRoleLabels.receiver"
                        :requester-dept-head-label="
                            requesterRoleLabels.approver
                        "
                        :it-drafter-name="drafterDisplayName"
                    />
                </div>

                <div class="px-8 py-3">
                    <StbFormAttachmentSection
                        :form-data="formData"
                        :photo-preview="photoPreview"
                        :photo-summary="photoSummary"
                        :is-compressing-photo="false"
                        :handle-photo-change="handlePhotoChange"
                        :handle-photo-preview-error="handlePhotoPreviewError"
                        :section-kicker="attachmentSectionKicker"
                        :section-title="attachmentSectionTitle"
                        :section-copy="attachmentSectionCopy"
                        :photo-label="attachmentPhotoLabel"
                        :remark-label="attachmentRemarkLabel"
                        :preview-alt="attachmentPreviewAlt"
                        :empty-photo-label="attachmentEmptyLabel"
                    />
                </div>

                <div class="px-8 py-3">
                    <StbFormItemsSection
                        :items="items"
                        :item-errors="itemErrors"
                        :form-errors="formErrors"
                        :user-assigned-assets="userAssignedAssets"
                        :all-hardware-assets="directory.assets"
                        :is-return-mode="false"
                        :get-item-reference-label="getItemReferenceLabel"
                        :get-stb-asset-label="getStbAssetLabel"
                        :add-item="addItem"
                        :remove-item="removeItem"
                        :open-item-picker="openItemPicker"
                        :ensure-assets-loaded="directory.ensureAssetsLoaded"
                        :section-title="itemsSectionTitle"
                        :section-copy="itemsSectionCopy"
                    />
                </div>

                <div
                    class="flex justify-end gap-3 border-t border-slate-100 px-8 py-8"
                >
                    <button
                        type="button"
                        @click="emit('cancel')"
                        class="h-10 rounded-lg border border-slate-200 px-6 text-[11px] font-black tracking-widest text-slate-500 uppercase transition-all hover:bg-slate-50"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="isLoading"
                        class="h-10 rounded-lg bg-[#003628] px-8 text-[11px] font-black tracking-widest text-white uppercase shadow-md transition-all hover:brightness-110 active:scale-95 disabled:opacity-50"
                    >
                        {{ isLoading ? 'Saving...' : 'Save Document' }}
                    </button>
                </div>
            </div>
        </div>

        <StbItemPickerModal
            v-model:open="itemPickerOpen"
            :assets-by-category="pickerAssetsByCategory"
            :loading-by-category="pickerLoadingByCategory"
            @select="handlePickerSelect"
            @load-category="handlePickerLoadCategory"
        />
    </form>
</template>
