<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import {
    computed,
    defineAsyncComponent,
    onMounted,
    reactive,
    ref,
    toRefs,
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

useRenderProfiler('StbForm');

const page = usePage();
const rememberTeam = ref(localStorage.getItem('stb_remember_team') === 'true');

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
    is_selected?: boolean;
}

interface StbFormData {
    id: number | null;
    status: number | string | null;
    documentType: StbDocumentType | string;
    movementType: StbMovementType | string;
    linkedStbId: number | string;
    user_id: number | string;
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
    { value: 'handover', label: 'Serah Terima' },
    { value: 'loan', label: 'Peminjaman' },
    { value: 'service', label: 'Perbaikan' },
];

const SERVICE_OPTION = { value: 'service', label: 'Perbaikan' };

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
    pageKicker: 'Form STB',
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
    if (documentType === 'handover' && movementType === 'out') return 1;
    if (documentType === 'handover' && movementType === 'return') return 2;
    if (documentType === 'loan' && movementType === 'out') return 3;
    if (documentType === 'service' && movementType === 'out') return 4;

    return null;
};

const deriveDocumentType = (initialData: Record<string, any>) => {
    if (initialData.documentType) return initialData.documentType;
    if (initialData.document_type) return initialData.document_type;

    return initialData.status === 3
        ? 'loan'
        : initialData.status === 4
          ? 'service'
          : 'handover';
};

const deriveMovementType = (initialData: Record<string, any>) => {
    if (initialData.movementType) return initialData.movementType;
    if (initialData.movement_type) return initialData.movement_type;

    return initialData.status === 2 ? 'return' : 'out';
};

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
});

const formData = ref<StbFormData>({
    id: props.initialData?.id ?? props.initialData?.previewId ?? null,
    status: props.initialData?.status ?? null,
    documentType: deriveDocumentType(props.initialData),
    movementType: deriveMovementType(props.initialData),
    linkedStbId:
        props.initialData?.linkedStbId ??
        props.initialData?.linked_stb_id ??
        '',
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
    if (value === '' || value === null || typeof value === 'undefined') {
        return null;
    }

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

const attachmentSectionKicker = computed(() =>
    formData.value.movementType === 'return' ? 'Pengembalian' : 'Lampiran',
);

const attachmentSectionTitle = computed(() => {
    if (formData.value.movementType === 'return') {
        return 'Foto & catatan pengembalian';
    }

    if (formData.value.documentType === 'loan') {
        return 'Foto & catatan peminjaman';
    }

    return 'Foto & catatan tambahan';
});

const attachmentSectionCopy = computed(() => {
    if (formData.value.movementType === 'return') {
        return 'Lampirkan foto kondisi asset saat kembali beserta catatan hasil pengembalian untuk kebutuhan verifikasi dokumen.';
    }

    if (formData.value.documentType === 'loan') {
        return 'Lampirkan foto asset yang dipinjamkan dan catatan tambahan untuk kebutuhan verifikasi dokumen.';
    }

    return 'Lampirkan foto pendukung dan catatan tambahan untuk kebutuhan verifikasi dokumen.';
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
    if (formData.value.documentType === 'loan') {
        return [
            { value: 'out', label: 'Dipinjamkan' },
            { value: 'return', label: 'Dikembalikan' },
        ];
    }

    if (formData.value.documentType === 'service') {
        return [{ value: 'out', label: 'Perbaikan' }];
    }

    return [
        { value: 'out', label: 'Menyerahkan' },
        { value: 'return', label: 'Mengembalikan' },
    ];
});

const isBlank = (value: unknown) =>
    value === null ||
    value === undefined ||
    (typeof value === 'string' && value.trim() === '');

const isReturnMode = computed(() => formData.value.movementType === 'return');

const selectedUserId = computed(() => normalizeInteger(formData.value.user_id));
const selectedGroupId = computed(() =>
    normalizeInteger(formData.value.group_id),
);
const directory = reactive(useStbDirectory());
const userAssignedAssets = reactive<Record<string, SnipeAsset[]>>({
    assets: [],
    license: [],
    accessories: [],
    consumable: [],
    component: [],
});
const userAssignedAssetsLoading = ref(false);

// Watch both user selection and return mode to auto-populate items
watch(
    [selectedUserId, isReturnMode],
    async ([newUserId, newIsReturnMode]) => {
        const id = Number(newUserId);
        if (!id || id <= 0) {
            Object.keys(userAssignedAssets).forEach(
                (key) => (userAssignedAssets[key] = []),
            );
            return;
        }

        userAssignedAssetsLoading.value = true;
        try {
            const types = [
                'assets',
                'license',
                'accessories',
                'consumable',
                'component',
            ];
            const results = await Promise.all(
                types.map(async (type) => {
                    const assets = await directory.fetchUserAssets(id, type);
                    userAssignedAssets[type] = assets;
                    return { type, assets };
                }),
            );

            // If in return mode and creating new document (no saved ID),
            // populate items list with user's current holdings
            if (newIsReturnMode && !props.initialData?.id) {
                const returnableItems: ItemBarang[] = [];

                // Auto-fetch last STB for linking
                axios
                    .get(`/stb/last-out/${id}`)
                    .then((res) => {
                        if (res.data?.stb?.id) {
                            formData.value.linkedStbId = res.data.stb.id;
                        }
                    })
                    .catch(() => {});

                results.forEach(({ type, assets }) => {
                    // Per Rule: "consumable tidak perlu dimunculkan"
                    if (type === 'consumable') return;

                    assets.forEach((asset) => {
                        returnableItems.push({
                            nama: asset.name || '',
                            kategori: normalizeStbAssetCategory(
                                asset.asset_type,
                            ),
                            type:
                                asset.asset_type_label || asset.type_name || '',
                            jumlah: 1,
                            serialNo: asset.serial || '',
                            computer_id:
                                asset.asset_type === 'hardware'
                                    ? asset.id
                                    : null,
                            snipeit_asset_id: asset.id,
                            inventory_number:
                                getStbAssetReferenceValue(asset) || '',
                            is_selected: false,
                        });
                    });
                });
                items.value = returnableItems;
                itemErrors.value = items.value.map(() =>
                    createEmptyItemErrors(),
                );
            } else if (
                !newIsReturnMode &&
                !formData.value.id &&
                items.value.length > 0 &&
                items.value.every((i) => i.is_selected === false)
            ) {
                // If switching back to handover and list is just auto-populated but unselected, clear it
                items.value = [];
                itemErrors.value = [];
            }
        } finally {
            userAssignedAssetsLoading.value = false;
        }
    },
    { immediate: true },
);

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
        (group) => group.id === Number(selectedGroupId.value),
    );

    if (selectedLocation) {
        return selectedLocation.name;
    }

    return groupParts.value.location || '-';
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
const recipientSectionKicker = computed(
    () => requesterRoleLabels.value.section,
);
const recipientSectionTitle = computed(() => {
    if (formData.value.movementType === 'return') {
        return 'Profil penerima pengembalian';
    }

    if (formData.value.documentType === 'loan') {
        return 'Profil peminjam';
    }

    return 'Profil penerima';
});
const recipientSectionCopy = computed(() => {
    if (formData.value.movementType === 'return') {
        return 'Data penerima pengembalian otomatis mengikuti user yang dipilih.';
    }

    if (formData.value.documentType === 'loan') {
        return 'Data peminjam otomatis mengikuti user yang dipilih.';
    }

    return 'Data penerima otomatis mengikuti user yang dipilih.';
});
const requesterSectionKicker = computed(() =>
    formData.value.movementType === 'return'
        ? 'Validasi Pengembalian'
        : 'Pemohon',
);
const requesterSectionTitle = computed(() => {
    if (formData.value.movementType === 'return') {
        return 'Penerima pengembalian & atasan';
    }

    if (formData.value.documentType === 'loan') {
        return 'Peminjam & atasan';
    }

    return 'Pihak penerima & dept head';
});
const requesterSectionCopy = computed(() => {
    if (formData.value.movementType === 'return') {
        return 'Nama penerima pengembalian dan atasan mengikuti struktur user yang aktif.';
    }

    if (formData.value.documentType === 'loan') {
        return 'Nama peminjam dan atasan mengikuti struktur user yang aktif.';
    }

    return 'Nama pemohon dan kepala departemen mengikuti struktur user yang aktif.';
});
const itemsSectionTitle = computed(() => {
    if (formData.value.movementType === 'return') {
        return 'Daftar aset yang dikembalikan';
    }

    if (formData.value.documentType === 'loan') {
        return 'Daftar aset yang dipinjamkan';
    }

    return 'Daftar aset yang diserahkan';
});
const itemsSectionCopy = computed(() => {
    if (formData.value.movementType === 'return') {
        return 'Pilih aset yang kembali dari direktori atau isi manual bila item belum tersedia di daftar asset.';
    }

    if (formData.value.documentType === 'loan') {
        return 'Pilih aset yang dipinjamkan dari direktori atau isi manual bila item belum tersedia di daftar asset.';
    }

    return 'Pilih aset dari direktori atau isi manual bila item belum tersedia di daftar asset.';
});
const selectedLoanReference = computed(() => {
    const linkedStbId = normalizeInteger(formData.value.linkedStbId);

    if (!linkedStbId) {
        return null;
    }

    return (
        loanReferences.value.find(
            (reference) => reference.id === linkedStbId,
        ) ?? null
    );
});
const topHeroTitle = computed(() => props.pageTitle || documentFlowLabel.value);
const topHeroCopy = computed(
    () =>
        props.pageCopy ||
        'Lengkapi data dokumen, penerima, item, dan lampiran.',
);
const userHardwareAssets = computed(() => {
    const userId = Number(selectedUserId.value);
    if (!userId || userId <= 0) return [];

    // Combine relevant categories and filter by the specific user ID
    return (directory.assets.assets || []).filter((asset) => {
        return Number(asset.users_id) === userId;
    });
});
const getItemReferenceLabel = (category?: string | null) =>
    getStbAssetReferenceLabel(category || 'assets');

// --- Item Picker Modal ---
const itemPickerOpen = ref(false);
const itemPickerIndex = ref(-1);

const pickerAssetsByCategory = computed(
    () =>
        Object.fromEntries(
            PICKER_CATEGORIES.map((cat) => [cat, directory.assets[cat] ?? []]),
        ) as Record<PickerCategory, SnipeAsset[]>,
);
const pickerLoadingByCategory = computed(
    () =>
        Object.fromEntries(
            PICKER_CATEGORIES.map((cat) => [
                cat,
                directory.assetLoading[cat] ?? false,
            ]),
        ) as Record<PickerCategory, boolean>,
);

const openItemPicker = (index: number) => {
    itemPickerIndex.value = index;
    void directory.ensureAssetsLoaded('assets');
    itemPickerOpen.value = true;
};

const handlePickerLoadCategory = (cat: PickerCategory) => {
    void directory.ensureAssetsLoaded(cat);
};

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

    // Reset IDs before assigning new ones
    item.computer_id = null;
    item.snipeit_asset_id = asset.id;

    const category = normalizeStbAssetCategory(asset.asset_type);
    item.kategori = category;

    // If it's a Hardware Asset (category 'assets'), also set as computer_id
    if (category === 'assets') {
        item.computer_id = asset.id;
    }

    item.nama = asset.name || item.nama;
    // Map the actual Snipe-IT model/category name to the "Type" field
    item.type = asset.asset_type_label || asset.type_name || item.type;
    item.serialNo = asset.serial || item.serialNo;
    item.inventory_number =
        getStbAssetReferenceValue(asset) || item.inventory_number;
};
// -------------------------

const applyAssetSelection = (item: ItemBarang) => {
    // Only auto-sync from Snipe-IT for the main Hardware category
    // For others (License, etc.), the fields might store linked asset info which shouldn't be overwritten
    if (item.kategori !== 'assets') {
        return;
    }

    const assetId = item.snipeit_asset_id || item.computer_id;
    const asset = getStbAssetById(assetId, item.kategori);

    if (!asset) {
        return;
    }

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

    const selectedUser = directory.users.find((user) => user.id === userId);

    if (selectedUser?.location_id) {
        formData.value.group_id = selectedUser.location_id;
    }

    // Auto-populate email and phone from Snipe-IT
    formData.value.user_email = getStbUserEmail(selectedUser);
    formData.value.user_phone = getStbUserPhone(selectedUser);
};

onMounted(async () => {
    await directory.ensureDirectoryLoaded();

    handleUserChange();

    // Default IT Selection logic
    if (!formData.value.id) {
        const currentUserSnipeId = (page.props.auth.user as any)
            ?.snipeit_user_id;
        if (currentUserSnipeId) {
            formData.value.itDrafter_id = currentUserSnipeId;
        }

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
});

watch(rememberTeam, (val) => {
    localStorage.setItem('stb_remember_team', String(val));

    if (!val) {
        localStorage.removeItem('stb_it_checker_id');
        localStorage.removeItem('stb_it_approved_id');
        return;
    }

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
});

watch(
    () => formData.value.itChecker_id,
    (val) => {
        if (rememberTeam.value) {
            localStorage.setItem('stb_it_checker_id', String(val));
        }
    },
);

watch(
    () => formData.value.itApproved_id,
    (val) => {
        if (rememberTeam.value) {
            localStorage.setItem('stb_it_approved_id', String(val));
        }
    },
);

watch(selectedUserId, () => {
    handleUserChange();
});

watch(
    () => formData.value.documentType,
    (nextDocumentType) => {
        if (nextDocumentType === 'service') {
            formData.value.movementType = 'out';
            formData.value.linkedStbId = '';
            return;
        }

        if (nextDocumentType !== 'loan') {
            formData.value.linkedStbId = '';
        }
    },
);

watch(
    () => formData.value.movementType,
    (nextMovementType) => {
        if (
            !(
                formData.value.documentType === 'loan' &&
                nextMovementType === 'return'
            )
        ) {
            formData.value.linkedStbId = '';
        }
    },
);

const addItem = () => {
    items.value.push(createEmptyItem());
    itemErrors.value.push(createEmptyItemErrors());
};

const removeItem = (index: number) => {
    items.value.splice(index, 1);
    itemErrors.value.splice(index, 1);
};

const compressImage = async (file: File) => {
    const source = await readFileAsDataUrl(file);
    const image = await loadImage(source);
    const canvas = document.createElement('canvas');
    const context = canvas.getContext('2d');

    if (!context) throw new Error('Canvas is not supported in this browser');

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

        if (blob.size <= PHOTO_HARD_LIMIT_BYTES) break;

        scale = Number((scale - 0.1).toFixed(2));
        quality = Math.max(
            PHOTO_MIN_QUALITY,
            Number((currentQuality - 0.03).toFixed(2)),
        );
    }

    if (!blob || blob.size >= file.size) return file;

    const baseName = file.name.replace(/\.[^.]+$/, '');

    return new File([blob], `${baseName}.jpg`, {
        type: outputType,
        lastModified: Date.now(),
    });
};

const handlePhotoChange = async (event: Event) => {
    const target = event.target as HTMLInputElement;
    const files = target.files;

    if (files && files.length > 0) {
        isCompressingPhoto.value = true;
        formNotice.value = null;
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
        }
    }
};

const photoLoadFailed = ref(false);

const existingPhotoPreview = computed(() => {
    if (!props.initialData?.photo || photoLoadFailed.value) return null;

    const rawPath = String(props.initialData.photo).trim();

    if (!rawPath) return null;

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
});

const photoPreview = computed(() => {
    if (formData.value.photo) {
        return window.URL.createObjectURL(formData.value.photo);
    }

    return existingPhotoPreview.value;
});

const photoSummary = computed(() => {
    if (formData.value.photo && photoSelectionStats.value) {
        const stats = photoSelectionStats.value;

        return {
            title: formData.value.photo.name,
            helper: stats.wasCompressed
                ? `Dikompresi otomatis dari ${formatFileSize(stats.originalBytes)} menjadi ${formatFileSize(stats.finalBytes)}.`
                : `Ukuran file ${formatFileSize(stats.finalBytes)}. File sudah sesuai sehingga tidak perlu dikompresi lagi.`,
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
    photoLoadFailed.value = true;
};

const clearErrors = () => {
    formErrors.value = {};
    itemErrors.value = items.value.map(() => createEmptyItemErrors());
};

const validateForm = () => {
    clearErrors();
    let isValid = true;

    if (!formData.value.user_id) {
        formErrors.value.user_id = 'User penerima wajib dipilih';
        isValid = false;
    }

    if (isBlank(formData.value.documentType)) {
        formErrors.value.documentType = 'Jenis dokumen wajib dipilih';
        isValid = false;
    }

    if (isBlank(formData.value.movementType)) {
        formErrors.value.movementType = 'Aksi dokumen wajib dipilih';
        isValid = false;
    }

    if (
        formData.value.documentType === 'loan' &&
        formData.value.movementType === 'return' &&
        !normalizeInteger(formData.value.linkedStbId)
    ) {
        formErrors.value.linkedStbId = 'Dokumen pinjaman asal wajib dipilih';
        isValid = false;
    }

    if (!formData.value.group_id) {
        formErrors.value.group_id = 'Lokasi wajib terisi dari user';
        isValid = false;
    }

    if (isReturnMode.value) {
        const selectedCount = items.value.filter((i) => i.is_selected).length;
        if (selectedCount === 0) {
            formErrors.value.items =
                'Pilih minimal satu item yang dikembalikan';
            isValid = false;
        }
    } else if (items.value.length === 0) {
        formErrors.value.items = 'Minimal harus ada satu item';
        isValid = false;
    }

    items.value.forEach((item, index) => {
        // In return mode, skip validation for unselected items
        if (isReturnMode.value && !item.is_selected) {
            itemErrors.value[index] = {};
            return;
        }

        const nextErrors: ItemFieldErrors = {};

        if (isBlank(item.nama)) {
            nextErrors.nama = 'Nama barang wajib diisi';
            isValid = false;
        }

        if (isBlank(item.type)) {
            nextErrors.type = 'Tipe wajib diisi';
            isValid = false;
        }

        if (
            item.jumlah === null ||
            Number.isNaN(Number(item.jumlah)) ||
            Number(item.jumlah) < 1
        ) {
            nextErrors.jumlah = 'Jumlah minimal 1';
            isValid = false;
        }

        itemErrors.value[index] = nextErrors;
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

    if (isCompressingPhoto.value) {
        formErrors.value.items = 'Tunggu proses foto selesai dulu';
        return;
    }

    emit('save', {
        ...formData.value,
        docId: docIdDisplay.value || null,
        id: normalizeInteger(formData.value.id),
        status: deriveLegacyStatus(
            String(formData.value.documentType || ''),
            String(formData.value.movementType || ''),
        ),
        documentType: formData.value.documentType,
        movementType: formData.value.movementType,
        linkedStbId: normalizeInteger(formData.value.linkedStbId),
        user_id: normalizeInteger(formData.value.user_id),
        group_id: normalizeInteger(formData.value.group_id),
        itDrafter_id: normalizeInteger(formData.value.itDrafter_id),
        itChecker_id: normalizeInteger(formData.value.itChecker_id),
        itApproved_id: normalizeInteger(formData.value.itApproved_id),
        items: (isReturnMode.value
            ? items.value.filter((i) => i.is_selected)
            : items.value
        ).map((item) => ({
            nama: item.nama.trim(),
            kategori: (item.kategori || '').trim(),
            type: item.type.trim(),
            jumlah: Number(item.jumlah),
            serialNo: (item.serialNo || '').trim(),
            inventory_number: (item.inventory_number || '').trim() || null,
            computer_id: normalizeInteger(item.computer_id),
            snipeit_asset_id: normalizeInteger(item.snipeit_asset_id),
        })),
    });
};

const handleCancel = () => emit('cancel');
</script>

<template>
    <form
        class="min-h-screen animate-in bg-[#f8fafc] pb-20 duration-500 fade-in"
        @submit.prevent="handleSubmit"
    >
        <div class="mx-auto max-w-5xl space-y-8 px-6 pt-10">
            <!-- HEADER -->

            <Transition
                enter-active-class="transition duration-500 ease-out"
                enter-from-class="opacity-0 -translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
            >
                <div
                    v-if="formNotice"
                    class="mb-4 rounded-2xl border-l-4 p-4 shadow-lg"
                    :class="[
                        formNotice.type === 'warning'
                            ? 'border-amber-400 bg-white text-amber-900 shadow-amber-900/5'
                            : 'border-emerald-400 bg-white text-emerald-900 shadow-emerald-900/5',
                    ]"
                >
                    <div class="flex items-center gap-3">
                        <Info
                            class="size-4"
                            :class="
                                formNotice.type === 'warning'
                                    ? 'text-amber-500'
                                    : 'text-emerald-500'
                            "
                        />
                        <p class="text-[12px] leading-none font-bold">
                            {{ formNotice.message }}
                        </p>
                    </div>
                </div>
            </Transition>

            <!-- UNIFIED MAIN CONTAINER (HARDWARE STYLE) -->
            <div
                class="overflow-hidden rounded-2xl bg-white shadow-[0_8px_30px_rgb(0,0,0,0.02)]"
            >
                <!-- FORM HEADER -->
                <div
                    class="flex flex-col justify-between gap-4 border-b border-slate-100 bg-white px-8 py-8 md:flex-row md:items-center"
                >
                    <div class="space-y-1">
                        <h1
                            class="text-2xl font-bold tracking-tight text-slate-900"
                        >
                            {{ topHeroTitle }}
                        </h1>
                        <p
                            class="text-[11px] font-bold tracking-widest text-slate-400 uppercase"
                        >
                            {{ topHeroCopy }}
                        </p>
                    </div>
                </div>
                <!-- SECTION 1: STB INFORMATION -->
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

                <!-- SECTION 2: USER INFORMATION -->
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
                        :requester-kicker="requesterSectionKicker"
                        :requester-title="requesterSectionTitle"
                        :requester-copy="requesterSectionCopy"
                        :requester-received-label="requesterRoleLabels.receiver"
                        :requester-dept-head-label="
                            requesterRoleLabels.approver
                        "
                        :it-drafter-name="
                            getStbUserLabel(formData.itDrafter_id)
                        "
                    />
                </div>

                <!-- SECTION 3: ATTACHMENTS -->
                <div class="px-8 py-3">
                    <StbFormAttachmentSection
                        :form-data="formData"
                        :photo-preview="photoPreview"
                        :photo-summary="photoSummary"
                        :is-compressing-photo="isCompressingPhoto"
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

                <!-- SECTION 4: ITEMS SELECTION -->
                <div class="px-8 py-3">
                    <StbFormItemsSection
                        :items="items"
                        :item-errors="itemErrors"
                        :form-errors="formErrors"
                        :user-assigned-assets="userAssignedAssets"
                        :all-hardware-assets="directory.assets"
                        :is-return-mode="isReturnMode"
                        :is-loading="userAssignedAssetsLoading"
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

                <!-- FORM ACTIONS -->
                <div
                    class="flex items-center justify-end gap-3 border-t border-slate-100 bg-white px-8 py-8"
                >
                    <button
                        type="button"
                        class="h-10 rounded-lg border border-slate-200 bg-white px-6 text-[11px] font-black tracking-widest text-slate-500 uppercase shadow-sm transition-all hover:bg-slate-50 active:scale-95"
                        @click="handleCancel"
                    >
                        Discard
                    </button>
                    <button
                        type="submit"
                        :disabled="isLoading || isCompressingPhoto"
                        class="h-10 rounded-lg bg-[#003628] px-8 text-[11px] font-black tracking-widest text-white uppercase shadow-md transition-all hover:brightness-110 active:scale-95 disabled:opacity-50"
                    >
                        {{
                            isCompressingPhoto
                                ? 'Optimizing...'
                                : isLoading
                                  ? 'Syncing...'
                                  : 'Save'
                        }}
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
