<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
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
    PeminjamanDocumentType,
    PeminjamanMovementType,
} from '@/pages/Peminjaman/types';
import {
    resolvePeminjamanDateLabel,
    formatPeminjamanDocId,
    resolvePeminjamanFlowTitle,
    resolvePeminjamanPhotoLabel,
    resolvePeminjamanRemarkLabel,
    resolvePeminjamanRequesterRoleLabels,
} from '@/pages/Peminjaman/utils/peminjaman';
import {
    getPeminjamanAssetById,
    getPeminjamanAssetLabel,
    getPeminjamanAssetReferenceLabel,
    getPeminjamanAssetReferenceValue,
    getPeminjamanDeptHeadLabel,
    getPeminjamanGroupParts,
    getPeminjamanUserEmail,
    getPeminjamanUserLabel,
    getPeminjamanUserPhone,
    getPeminjamanUserPosition,
    normalizePeminjamanAssetCategory,
    usePeminjamanDirectory,
} from '@/pages/Peminjaman/utils/peminjamanDirectory';

import PeminjamanFormAttachmentSection from './PeminjamanFormAttachmentSection.vue';
import PeminjamanFormDocumentSection from './PeminjamanFormDocumentSection.vue';
import PeminjamanFormItemsSection from './PeminjamanFormItemsSection.vue';
import PeminjamanFormApprovalSection from './PeminjamanFormRecipientSection.vue';

const PeminjamanItemPickerModal = defineAsyncComponent(
    () => import('@/pages/Peminjaman/Partials/PeminjamanItemPickerModal.vue'),
);

useRenderProfiler('PeminjamanForm');

const page = usePage();
const rememberTeam = ref(
    localStorage.getItem('peminjaman_remember_team') === 'true',
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
    condition: 'Good' | 'Broken' | 'Missing';
    is_selected?: boolean;
}

interface PeminjamanFormData {
    id: number | null;
    status: number | string | null;
    documentType: PeminjamanDocumentType | string;
    movementType: PeminjamanMovementType | string;
    linkedLoanId: number | string;
    user_id: number | string;
    user_email?: string;
    user_phone?: string;
    group_id: number | string;
    useDate: string;
    itDrafter_id: number | string;
    photo: File | null;
    remark: string;
    expectedReturnDate: string;
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
    linkedLoanId?: string;
    user_id?: string;
    group_id?: string;
    items?: string;
}

interface Props {
    initialData?: Record<string, any>;
    isLoading?: boolean;
    pageKicker?: string;
    pageTitle?: string;
    pageCopy?: string;
    loanReferences?: LoanReferenceOption[];
}

interface Emits {
    (e: 'save', data: Record<string, any>): void;
    (e: 'cancel'): void;
}

const props = withDefaults(defineProps<Props>(), {
    initialData: () => ({}),
    isLoading: false,
    pageKicker: 'Peminjaman',
    pageTitle: 'Form Peminjaman',
    pageCopy: '',

    loanReferences: () => [],
});

const emit = defineEmits<Emits>();

const isLoading = computed(() => props.isLoading);
const formErrors = ref<FormErrors>({});
const itemErrors = ref<ItemFieldErrors[]>([]);

const deriveMovementType = (initialData: Record<string, any>) => {
    if (initialData.movementType) return initialData.movementType;
    if (initialData.movement_type) return initialData.movement_type;
    return initialData.status === 2 ? 'return' : 'out';
};

const toDateOnly = (value?: string | null): string => {
    if (!value) return '';
    // Handle ISO datetime strings like "2026-04-30T00:00:00.000000Z"
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
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

const formData = ref<PeminjamanFormData>({
    id: props.initialData?.id ?? props.initialData?.previewId ?? null,
    status: props.initialData?.status ?? null,
    documentType: 'loan',
    movementType: deriveMovementType(props.initialData),
    linkedLoanId:
        props.initialData?.linkedLoanId ??
        props.initialData?.linked_stb_id ??
        '',
    user_id: props.initialData?.user_id ?? '',
    user_email: props.initialData?.user_email || '',
    user_phone: props.initialData?.user_phone || '',
    group_id: props.initialData?.group_id ?? '',
    useDate:
        toDateOnly(props.initialData?.useDate || props.initialData?.use_date) ||
        getCurrentDate(),
    itDrafter_id:
        props.initialData?.itDrafter_id ??
        props.initialData?.it_drafter_id ??
        '',
    photo: null,
    remark: props.initialData?.remark || '',
    expectedReturnDate:
        toDateOnly(
            props.initialData?.expectedReturnDate ||
                props.initialData?.expected_return_date,
        ) || '',
    createDate: toDateTimeLocal(
        props.initialData?.createDate || props.initialData?.created_at,
    ),
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

const isDocumentFlowPreset = computed(() =>
    Boolean(props.initialData?.documentType && props.initialData?.movementType),
);

const documentFlowLabel = computed(() =>
    resolvePeminjamanFlowTitle(
        {
            document_type: 'loan',
            movement_type: String(formData.value.movementType || ''),
        },
        '-',
    ),
);
const documentDateLabel = computed(() =>
    resolvePeminjamanDateLabel({
        document_type: 'loan',
        movement_type: String(formData.value.movementType || ''),
    }),
);

const isReturnMode = computed(() => formData.value.movementType === 'return');

const selectedUserId = computed(() => normalizeInteger(formData.value.user_id));
const selectedGroupId = computed(() =>
    normalizeInteger(formData.value.group_id),
);
const directory = reactive(usePeminjamanDirectory());
const userAssignedAssets = reactive<Record<string, SnipeAsset[]>>({
    assets: [],
    license: [],
    accessories: [],
    consumable: [],
    component: [],
});
const userAssignedAssetsLoading = ref(false);

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

            if (
                newIsReturnMode &&
                !props.initialData?.id &&
                !props.initialData?.items?.length
            ) {
                const returnableItems: ItemBarang[] = [];

                axios
                    .get(`/peminjaman/last-out/${id}`)
                    .then((res) => {
                        if (
                            res.data?.peminjaman?.id &&
                            !formData.value.linkedLoanId
                        ) {
                            formData.value.linkedLoanId =
                                res.data.peminjaman.id;
                        }
                    })
                    .catch(() => {});

                results.forEach(({ type, assets }) => {
                    if (type === 'consumable') return;
                    assets.forEach((asset) => {
                        returnableItems.push({
                            nama: asset.name || '',
                            kategori: normalizePeminjamanAssetCategory(
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
                                getPeminjamanAssetReferenceValue(asset) || '',
                            condition: 'Good',
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
    getPeminjamanGroupParts(selectedGroupId.value, selectedUserId.value),
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
    return selectedLocation
        ? selectedLocation.name
        : groupParts.value.location || '-';
});

const resolvedName = computed(() =>
    getPeminjamanUserLabel(selectedUserId.value),
);
const phoneNumber = computed(() =>
    getPeminjamanUserPhone(selectedUserId.value),
);
const email = computed(() => getPeminjamanUserEmail(selectedUserId.value));
const position = computed(() =>
    getPeminjamanUserPosition(selectedUserId.value),
);
const deptHead = computed(() =>
    getPeminjamanDeptHeadLabel(selectedUserId.value),
);
const requesterReceived = computed(() => resolvedName.value);
const requesterDeptHead = computed(() => deptHead.value);

const requesterRoleLabels = computed(() =>
    resolvePeminjamanRequesterRoleLabels({
        document_type: 'loan',
        movement_type: String(formData.value.movementType || ''),
    }),
);

const selectedLoanReference = computed(() => {
    const linkedId = normalizeInteger(formData.value.linkedLoanId);
    if (!linkedId) return null;
    return props.loanReferences?.find((r) => r.id === linkedId) ?? null;
});

// Filter assets by status based on movement type
const filteredAssetsByCategory = computed(() => {
    const movementType = formData.value.movementType;

    // If movement type is 'return', only show borrowed assets
    // Otherwise show all assets (for 'out' movement)
    if (movementType === 'return') {
        return {
            assets: directory.assets.assets.filter((asset) => {
                const status = (asset.state_name || '').toLowerCase();
                // Check if asset is borrowed/on loan
                return (
                    status.includes('borrow') ||
                    status.includes('borrowed') ||
                    status.includes('on loan') ||
                    status.includes('loaner') ||
                    status.includes('dipinjam') ||
                    status.includes('peminjaman')
                );
            }),
            license: directory.assets.license,
            accessories: directory.assets.accessories,
            consumable: directory.assets.consumable,
            component: directory.assets.component,
        };
    }

    // For 'out' movement, show available/stock assets
    return {
        assets: directory.assets.assets.filter((asset) => {
            const status = (asset.state_name || '').toLowerCase();
            // Check if asset is available/ready to deploy
            return (
                status.includes('ready to deploy') ||
                status.includes('stock') ||
                status.includes('available') ||
                status.includes('deployable')
            );
        }),
        license: directory.assets.license,
        accessories: directory.assets.accessories,
        consumable: directory.assets.consumable,
        component: directory.assets.component,
    };
});

const itemPickerOpen = ref(false);
const itemPickerIndex = ref(-1);

const openItemPicker = (index: number) => {
    itemPickerIndex.value = index;
    void directory.ensureAssetsLoaded('assets');
    itemPickerOpen.value = true;
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

    const category = normalizePeminjamanAssetCategory(asset.asset_type);
    const mappedReference =
        getPeminjamanAssetReferenceValue(asset) || item.inventory_number;

    item.computer_id = null;
    item.snipeit_asset_id = asset.id;
    item.kategori = category;
    if (category === 'assets') item.computer_id = asset.id;

    item.inventory_number = category === 'assets' ? mappedReference : '';

    if (!item.serialNo || item.serialNo.trim() === '') {
        item.serialNo = asset.serial || item.serialNo;
    }
    if (!item.nama || item.nama.trim() === '') {
        item.nama = asset.name || item.nama;
    }
    if (!item.type || item.type.trim() === '') {
        item.type = asset.asset_type_label || asset.type_name || item.type;
    }

    if (category !== 'assets' && item.nama === 'Asset Name') {
        item.nama = asset.name || item.nama;
    }
};

const handleUserChange = () => {
    const userId = selectedUserId.value;
    if (!userId) {
        formData.value.group_id = '';
        return;
    }
    const selectedUser = directory.users.find((user) => user.id === userId);
    if (selectedUser?.location_id)
        formData.value.group_id = selectedUser.location_id;

    formData.value.user_email = getPeminjamanUserEmail(userId);
    formData.value.user_phone = getPeminjamanUserPhone(userId);
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
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (!file) return;

    if (!file.type.startsWith('image/')) {
        alert('File yang dipilih harus berupa gambar.');
        target.value = '';
        return;
    }

    if (file.size > 5 * 1024 * 1024) {
        alert('Ukuran foto maksimal 5MB.');
        target.value = '';
        return;
    }

    try {
        const compressed = await compressImage(file);
        formData.value.photo = compressed;
    } catch (error) {
        console.error('Failed to process loan photo upload:', error);
        formData.value.photo = file;
    } finally {
        target.value = '';
    }
};

onMounted(async () => {
    console.log('=== PeminjamanForm mounted ===');
    console.log('props.initialData:', props.initialData);
    console.log('props.initialData?.photo:', props.initialData?.photo);
    console.log('photoPreview value:', photoPreview.value);

    await directory.ensureDirectoryLoaded();
    handleUserChange();

    if (!formData.value.id) {
        const currentUserSnipeId = (page.props.auth.user as any)
            ?.snipeit_user_id;
        if (currentUserSnipeId)
            formData.value.itDrafter_id = currentUserSnipeId;

        // Apply remembered IT team if enabled (but allow user to change)
        if (rememberTeam.value) {
            const rememberedDrafter = localStorage.getItem(
                'peminjaman_it_drafter_id',
            );
            if (rememberedDrafter) {
                formData.value.itDrafter_id = rememberedDrafter;
            }
        }
    }

    await Promise.all(
        ['assets', 'license', 'accessories', 'consumable', 'component'].map(
            (cat) => directory.ensureAssetsLoaded(cat as any),
        ),
    );
});

watch(rememberTeam, (val) => {
    localStorage.setItem('peminjaman_remember_team', String(val));

    if (!val) {
        // Clear stored values when unchecked
        localStorage.removeItem('peminjaman_it_drafter_id');
        return;
    }

    // Save current value when checked
    if (formData.value.itDrafter_id) {
        localStorage.setItem(
            'peminjaman_it_drafter_id',
            String(formData.value.itDrafter_id),
        );
    }
});

// Watch IT Drafter changes and save if remember is enabled
watch(
    () => formData.value.itDrafter_id,
    (val) => {
        if (rememberTeam.value && val) {
            localStorage.setItem('peminjaman_it_drafter_id', String(val));
        }
    },
);

watch(selectedUserId, () => handleUserChange());

const validateForm = () => {
    formErrors.value = {};
    let isValid = true;

    if (!formData.value.user_id) {
        formErrors.value.user_id = 'Peminjam wajib dipilih';
        isValid = false;
    }

    if (!formData.value.movementType) {
        formErrors.value.movementType = 'Aksi dokumen wajib dipilih';
        isValid = false;
    }

    if (
        formData.value.movementType === 'return' &&
        !normalizeInteger(formData.value.linkedLoanId)
    ) {
        formErrors.value.linkedLoanId = 'Dokumen pinjaman asal wajib dipilih';
        isValid = false;
    }

    if (!formData.value.group_id) {
        formErrors.value.group_id = 'Lokasi wajib terisi dari user';
        isValid = false;
    }

    if (isReturnMode.value) {
        if (items.value.filter((i) => i.is_selected).length === 0) {
            formErrors.value.items =
                'Pilih minimal satu item yang dikembalikan';
            isValid = false;
        }
    } else if (items.value.length === 0) {
        formErrors.value.items = 'Minimal harus ada satu item';
        isValid = false;
    }

    items.value.forEach((item, index) => {
        if (isReturnMode.value && !item.is_selected) {
            itemErrors.value[index] = {};
            return;
        }
        const nextErrors: ItemFieldErrors = {};
        if (!item.nama) {
            nextErrors.nama = 'Nama barang wajib diisi';
            isValid = false;
        }
        if (!item.type) {
            nextErrors.type = 'Tipe wajib diisi';
            isValid = false;
        }
        if (!item.jumlah || item.jumlah < 1) {
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
        formatPeminjamanDocId({
            id: formData.value.id,
            locationName: resolvedLocationLabel.value,
            date: formData.value.createDate,
        }) || ''
    );
});

const photoLoadFailed = ref(false);
const photoObjectUrl = ref<string | null>(null);

watch(
    () => formData.value.photo,
    (file, oldFile) => {
        // Revoke old object URL to free memory
        if (photoObjectUrl.value) {
            URL.revokeObjectURL(photoObjectUrl.value);
            photoObjectUrl.value = null;
        }
        if (file instanceof File) {
            photoObjectUrl.value = URL.createObjectURL(file);
        }
    },
);

const normalizePhotoSource = (value: string | null | undefined): string => {
    if (!value) return '';
    const source = String(value).trim();
    if (
        source.startsWith('blob:') ||
        source.startsWith('data:') ||
        source.startsWith('http://') ||
        source.startsWith('https://')
    )
        return source;
    if (source.startsWith('/storage/')) return source;
    if (source.startsWith('storage/')) return `/${source}`;
    if (source.startsWith('/')) return `/storage${source}`;
    if (source.startsWith('public/'))
        return `/storage/${source.replace(/^public\//, '')}`;
    return `/storage/${source.replace(/^\/+/, '')}`;
};

const existingPhotoPreview = computed(() => {
    if (!props.initialData?.photo || photoLoadFailed.value) return null;
    const rawPath = String(props.initialData.photo).trim();
    if (!rawPath) return null;
    return normalizePhotoSource(rawPath);
});

const handlePhotoPreviewError = () => {
    photoLoadFailed.value = true;
    if (photoObjectUrl.value) {
        URL.revokeObjectURL(photoObjectUrl.value);
        photoObjectUrl.value = null;
    }
};

const photoPreview = computed(() => {
    if (formData.value.photo && photoObjectUrl.value) {
        return photoObjectUrl.value;
    }
    return existingPhotoPreview.value;
});

const handleSubmit = () => {
    if (!validateForm()) return;

    emit('save', {
        ...formData.value,
        id: normalizeInteger(formData.value.id),
        status: isReturnMode.value ? 2 : 3,
        linkedStbId: normalizeInteger(formData.value.linkedLoanId),
        user_id: normalizeInteger(formData.value.user_id),
        group_id: normalizeInteger(formData.value.group_id),
        itDrafter_id: normalizeInteger(formData.value.itDrafter_id),
        items: (isReturnMode.value
            ? items.value.filter((i) => i.is_selected)
            : items.value
        ).map((item) => ({
            ...item,
            jumlah: Number(item.jumlah),
            computer_id: normalizeInteger(item.computer_id),
            snipeit_asset_id: normalizeInteger(item.snipeit_asset_id),
        })),
    });
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
                <!-- HEADER (INLINE AS IN STB) -->
                <div
                    class="flex flex-col justify-between gap-4 border-b border-slate-100 bg-white px-8 py-8 md:flex-row md:items-center"
                >
                    <div class="space-y-1">
                        <h1
                            class="text-2xl font-bold tracking-tight text-slate-900"
                        >
                            {{ props.pageTitle || documentFlowLabel }}
                        </h1>
                        <p
                            class="text-[11px] font-bold tracking-widest text-slate-400 uppercase"
                        >
                            {{
                                props.pageCopy ||
                                'Lengkapi data peminjaman asset.'
                            }}
                        </p>
                    </div>
                </div>

                <!-- SECTION 1: DOCUMENT INFORMATION -->
                <div class="px-8 py-3">
                    <PeminjamanFormDocumentSection
                        :doc-id-display="docIdDisplay"
                        :form-data="formData"
                        :users="directory.users"
                        :movement-options="[
                            { value: 'out', label: 'Dipinjamkan' },
                            { value: 'return', label: 'Dikembalikan' },
                        ]"
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

                <!-- SECTION 2: RECIPIENT INFORMATION -->
                <div class="px-8 py-3">
                    <PeminjamanFormApprovalSection
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
                        :requester-received-label="requesterRoleLabels.receiver"
                        :requester-dept-head-label="
                            requesterRoleLabels.approver
                        "
                        :it-drafter-name="
                            getPeminjamanUserLabel(formData.itDrafter_id)
                        "
                        @user-change="handleUserChange"
                    />
                </div>

                <!-- SECTION 3: ATTACHMENTS -->
                <div class="px-8 py-3">
                    <PeminjamanFormAttachmentSection
                        :form-data="formData"
                        :photo-preview="photoPreview"
                        :handle-photo-change="handlePhotoChange"
                        :handle-photo-preview-error="handlePhotoPreviewError"
                        :section-kicker="'Lampiran'"
                        :section-title="'Foto & Dokumen Pendukung'"
                        :section-copy="'Lampirkan bukti fisik atau dokumen pendukung untuk kebutuhan verifikasi.'"
                        :remark-label="'Catatan'"
                    />
                </div>

                <!-- SECTION 4: ITEMS SELECTION -->
                <div class="px-8 py-3">
                    <PeminjamanFormItemsSection
                        :items="items"
                        :item-errors="itemErrors"
                        :form-errors="formErrors"
                        :user-assigned-assets="userAssignedAssets"
                        :all-hardware-assets="directory.assets"
                        :is-return-mode="isReturnMode"
                        :is-loading="userAssignedAssetsLoading"
                        :get-item-reference-label="
                            getPeminjamanAssetReferenceLabel
                        "
                        :get-stb-asset-label="getPeminjamanAssetLabel"
                        :add-item="
                            () => {
                                items.push(createEmptyItem());
                                itemErrors.push(createEmptyItemErrors());
                            }
                        "
                        :remove-item="
                            (idx) => {
                                items.splice(idx, 1);
                                itemErrors.splice(idx, 1);
                            }
                        "
                        :open-item-picker="openItemPicker"
                        :ensure-assets-loaded="directory.ensureAssetsLoaded"
                        :section-title="
                            isReturnMode
                                ? 'Daftar aset yang dikembalikan'
                                : 'Daftar aset yang dipinjamkan'
                        "
                        :section-copy="'Pilih aset dari direktori atau isi manual bila item belum tersedia.'"
                        :skipped-items="props.initialData?.skippedItems ?? []"
                    />
                </div>

                <!-- FORM ACTIONS -->
                <div
                    class="flex items-center justify-end gap-3 border-t border-slate-100 bg-white px-8 py-8"
                >
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
                        class="h-10 rounded-lg bg-[#003628] px-8 text-[11px] font-black tracking-widest text-white uppercase shadow-md transition-all hover:brightness-110 active:scale-95 disabled:opacity-50"
                    >
                        {{ isLoading ? 'Saving...' : 'Save' }}
                    </button>
                </div>
            </div>
        </div>

        <PeminjamanItemPickerModal
            v-model:open="itemPickerOpen"
            :assets-by-category="filteredAssetsByCategory"
            :loading-by-category="directory.assetLoading"
            :movement-type="formData.movementType"
            @select="handlePickerSelect"
            @load-category="
                (cat, force) => directory.ensureAssetsLoaded(cat, force)
            "
        />
    </form>
</template>
