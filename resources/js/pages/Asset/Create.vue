<script setup lang="ts">
import { useForm, Link, Head, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import {
    Check,
    Building,
    Briefcase,
    Loader2,
    AlertCircle,
    MapPin,
} from 'lucide-vue-next';
import { ref, computed, watch, onMounted } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import AssetCategoryModal from './Partials/AssetCategoryModal.vue';
import AssetCreateMainFields from './Partials/AssetCreateMainFields.vue';
import AssetCustomFieldsSection from './Partials/AssetCustomFieldsSection.vue';
import AssetModelModal from './Partials/AssetModelModal.vue';
import AssetSimpleModal from './Partials/AssetSimpleModal.vue';
import AssetSupplierModal from './Partials/AssetSupplierModal.vue';

type AssetType =
    | 'assets'
    | 'license'
    | 'accessories'
    | 'consumable'
    | 'component';

interface OptionItem {
    id: number;
    name: string;
    type?: string;
    category_type?: string;
}

interface InitialData {
    type?: string;
    name?: string;
    asset_tag?: string;
    serial?: string;
    model_id?: number | string | null;
    status_id?: number | string | null;
    category_id?: number | string | null;
    company_id?: number | string | null;
    location_id?: number | string | null;
    manufacturer_id?: number | string | null;
    supplier_id?: number | string | null;
    qty?: number;
    seats?: number;
    notes?: string;
    requestable?: boolean;
    warranty_months?: string;
    expected_checkin?: string;
    next_audit_date?: string;
    byod?: boolean;
    order_number?: string;
    purchase_date?: string;
    asset_eol_date?: string;
    purchase_cost?: string;
    po_number?: string;
    // License-specific
    license_name?: string;
    license_email?: string;
    reassignable?: boolean;
    expiration_date?: string;
    termination_date?: string;
    min_qty?: number | string;
    depreciation_id?: number | string | null;
    maintained?: boolean;
    // Stock-specific
    model_number?: string;
    item_no?: string;
    custom_fields?: Record<string, string>;
}

interface TypeItem {
    key: AssetType;
    endpoint: AssetType;
    label: string;
}

interface ModelField {
    name: string;
    db_column_name: string;
    default_value: string | null;
    format: string;
    type: string;
    field_values: string | null;
    required: boolean;
}

interface ModelItem {
    id: number;
    name: string;
    label: string;
    manufacturer_name: string;
    category_id: number;
    category_name: string;
    fieldset_name: string;
    require_serial: boolean;
    has_details?: boolean;
    default_fields: ModelField[];
}

interface TypeMetadata {
    categories?: OptionItem[];
    companies?: OptionItem[];
    locations?: OptionItem[];
    manufacturers?: OptionItem[];
    fieldsets?: OptionItem[];
    statuses?: OptionItem[];
    models?: ModelItem[];
    suppliers?: OptionItem[];
    depreciations?: OptionItem[];
}

interface AssetMetadata {
    users: OptionItem[];
    assets: TypeMetadata;
}

interface Props {
    mode?: 'create' | 'edit';
    assetId?: number | null;
    initialType: AssetType;
    types: TypeItem[];
    metadata: Record<AssetType, TypeMetadata> & AssetMetadata;
    initialData?: InitialData;
    initialModelDetail?: ModelItem;
}

const props = defineProps<Props>();
const extraCategories = ref<Record<string, OptionItem[]>>({});

// Pre-populate modelOptions; if PHP supplied full model detail (edit mode), inject it
// so the first render already has correct type/field_values without an async API call.
const initialModels = [...(props.metadata.assets?.models || [])];
if (props.initialModelDetail?.id) {
    const idx = initialModels.findIndex(
        (m) => m.id === props.initialModelDetail!.id,
    );
    if (idx >= 0) {
        initialModels[idx] = props.initialModelDetail;
    } else {
        initialModels.unshift(props.initialModelDetail);
    }
}
const modelOptions = ref<ModelItem[]>(initialModels);
const showAddModel = ref(false);
const addingModel = ref(false);
const addModelError = ref('');
const addModelForm = ref({
    name: '',
    model_number: '',
    category_id: '',
    manufacturer_id: '',
    fieldset_id: '',
});

const showAddCategory = ref(false);
const addingCategory = ref(false);
const addCategoryError = ref('');
const addCategoryForm = ref({ name: '' });

const showAddManufacturer = ref(false);
const addingManufacturer = ref(false);
const addManufacturerError = ref('');
const addManufacturerForm = ref({ name: '' });

const showAddSupplier = ref(false);
const addingSupplier = ref(false);
const addSupplierError = ref('');
const addSupplierForm = ref({
    name: '',
    contact_name: '',
    url: '',
    phone: '',
    fax: '',
    email: '',
    notes: '',
});

const showAddLocation = ref(false);
const addingLocation = ref(false);
const addLocationError = ref('');
const addLocationForm = ref({ name: '' });

const showAddStatus = ref(false);
const addingStatus = ref(false);
const addStatusError = ref('');
const addStatusForm = ref({ name: '', type: 'deployable' });

const form = useForm({
    type:
        (props.initialData?.type as AssetType | undefined) || props.initialType,
    name: String(props.initialData?.name || ''),
    asset_tag: String(props.initialData?.asset_tag || ''),
    serial: String(props.initialData?.serial || ''),
    model_id: String(props.initialData?.model_id || ''),
    status_id: String(props.initialData?.status_id || ''),
    category_id: String(props.initialData?.category_id || ''),
    company_id: String(props.initialData?.company_id || ''),
    location_id: String(props.initialData?.location_id || ''),
    manufacturer_id: String(props.initialData?.manufacturer_id || ''),
    qty: Number(props.initialData?.qty || 1),
    seats: Number(props.initialData?.seats || 1),
    notes: String(props.initialData?.notes || ''),
    requestable: Boolean(props.initialData?.requestable || false),
    // Optional Information
    warranty_months: String(props.initialData?.warranty_months || ''),
    expected_checkin: String(props.initialData?.expected_checkin || ''),
    next_audit_date: String(props.initialData?.next_audit_date || ''),
    byod: Boolean(props.initialData?.byod || false),
    // Order Related Information
    order_number: String(props.initialData?.order_number || ''),
    purchase_date: String(props.initialData?.purchase_date || ''),
    asset_eol_date: String(props.initialData?.asset_eol_date || ''),
    supplier_id: String(props.initialData?.supplier_id || ''),
    purchase_cost: String(props.initialData?.purchase_cost || ''),
    // Image
    image: null as File | null,
    // Stock (for non-asset types)
    po_number: String(props.initialData?.po_number || ''),
    stock_document: null as File | null,
    // License-specific
    license_name: String(props.initialData?.license_name || ''),
    license_email: String(props.initialData?.license_email || ''),
    reassignable: Boolean(props.initialData?.reassignable ?? false),
    expiration_date: String(props.initialData?.expiration_date || ''),
    termination_date: String(props.initialData?.termination_date || ''),
    min_qty: String(props.initialData?.min_qty ?? ''),
    depreciation_id: String(props.initialData?.depreciation_id || ''),
    maintained: Boolean(props.initialData?.maintained ?? false),
    // Stock-specific
    model_number: String(props.initialData?.model_number || ''),
    item_no: String(props.initialData?.item_no || ''),
    custom_fields: props.initialData?.custom_fields ?? {},
    create_stb: false,
    stb_user_id: '',
    stb_building: '',
    stb_use_date: '',
    stb_batch_no: '',
    stb_req_doc_no: '',
    stb_po_doc_no: '',
    stb_it_drafter_id: '',
    stb_it_checker_id: '',
    stb_it_approved_id: '',
    stb_location_id: '',
    stb_remark: '',
    stb_send_notification: false,
});

const isEditMode = computed(
    () => props.mode === 'edit' && Boolean(props.assetId),
);

const currentType = computed<AssetType>(() => form.type as AssetType);
const currentMetadata = computed<TypeMetadata>(
    () => props.metadata[currentType.value] || {},
);
const models = computed(() => {
    return currentType.value === 'assets'
        ? modelOptions.value
        : currentMetadata.value.models || [];
});
const categories = computed(() => [
    ...(currentMetadata.value.categories || []),
    ...(extraCategories.value[currentType.value] || []),
]);
const companies = computed(() => currentMetadata.value.companies || []);
const locations = computed(() => currentMetadata.value.locations || []);
const manufacturers = computed(() => currentMetadata.value.manufacturers || []);
const suppliers = computed(
    () =>
        currentMetadata.value.suppliers ||
        props.metadata.assets?.suppliers ||
        [],
);
const assetCategories = ref<OptionItem[]>([
    ...(props.metadata.assets?.categories || []),
]);
const assetManufacturers = computed(
    () => props.metadata.assets?.manufacturers || [],
);
const assetFieldsets = computed(() => props.metadata.assets?.fieldsets || []);
const statuses = computed(() => currentMetadata.value.statuses || []);
const depreciations = computed(
    () =>
        currentMetadata.value.depreciations ||
        props.metadata.assets?.depreciations ||
        [],
);
const selectedModel = computed<ModelItem | null>(() => {
    const found =
        models.value.find(
            (item) => Number(item.id) === Number(form.model_id),
        ) || null;

    return found;
});
const customFields = computed(() => selectedModel.value?.default_fields || []);

watch(customFields, (val) => {}, { immediate: true });

const currentTypeLabel = computed(
    () =>
        props.types.find((item) => item.key === currentType.value)?.label ||
        currentType.value,
);
const isStockType = computed(
    () =>
        currentType.value === 'accessories' ||
        currentType.value === 'consumable' ||
        currentType.value === 'component',
);

watch(
    () => form.type,
    (nextType) => {
        form.clearErrors();
        form.type = nextType as AssetType;
        form.model_id = '';
        form.status_id = '';
        form.category_id = '';
        form.manufacturer_id = '';
        form.serial = '';
        form.asset_tag = '';
        form.qty = 1;
        form.seats = 1;
        form.po_number = '';
        form.purchase_date = '';
        form.stock_document = null;
        form.custom_fields = {};
        form.create_stb = false;
        form.stb_user_id = '';
        form.stb_building = '';
        form.stb_use_date = '';
        form.stb_batch_no = '';
        form.stb_req_doc_no = '';
        form.stb_po_doc_no = '';
        form.stb_remark = '';
    },
);

watch(
    () => form.model_id,
    async (newModelId, prevModelId) => {
        // Don't reset when first mounting in edit mode (prevModelId is undefined)
        if (prevModelId !== undefined) {
            form.custom_fields = {};
        }

        if (!newModelId) {
            // Only wipe category_id if user actively cleared the model (not on initial mount)
            if (prevModelId !== undefined) {
                form.category_id = '';
            }
            return;
        }

        let model =
            modelOptions.value.find((m) => m.id === Number(newModelId)) ?? null;

        if (
            !model ||
            !model.default_fields ||
            model.default_fields.length === 0 ||
            (!model.has_details && !!model.fieldset_name)
        ) {
            model = (await fetchModelDetail(newModelId)) ?? model;
        }

        if (model) {
            form.category_id = String(model.category_id || '');
            // In edit mode initial load: merge field definitions with already-loaded values
            const isInitialEditLoad =
                prevModelId === undefined &&
                Object.keys(form.custom_fields).length > 0;
            if (!isInitialEditLoad) {
                form.custom_fields = Object.fromEntries(
                    (model.default_fields ?? []).map((field) => [
                        field.db_column_name,
                        String(field.default_value ?? ''),
                    ]),
                );
            }
        }
    },
    { immediate: true },
);

const rememberTeam = ref(localStorage.getItem('stb_remember_team') === 'true');

onMounted(() => {
    if (rememberTeam.value) {
        form.stb_it_checker_id =
            localStorage.getItem('stb_it_checker_id') || '';
        form.stb_it_approved_id =
            localStorage.getItem('stb_it_approved_id') || '';
    }
    fetchNextStbId();
});

watch(rememberTeam, (val) => {
    localStorage.setItem('stb_remember_team', String(val));
    if (val) {
        localStorage.setItem(
            'stb_it_checker_id',
            String(form.stb_it_checker_id),
        );
        localStorage.setItem(
            'stb_it_approved_id',
            String(form.stb_it_approved_id),
        );
    }
});

watch([() => form.stb_it_checker_id, () => form.stb_it_approved_id], () => {
    if (rememberTeam.value) {
        localStorage.setItem(
            'stb_it_checker_id',
            String(form.stb_it_checker_id),
        );
        localStorage.setItem(
            'stb_it_approved_id',
            String(form.stb_it_approved_id),
        );
    }
});

const serialError = ref('');
const checkingSerial = ref(false);
let serialDebounce: any = null;

watch(
    () => form.serial,
    (newSerial) => {
        serialError.value = '';
        if (!newSerial || newSerial.length < 3) return;

        if (serialDebounce) clearTimeout(serialDebounce);
        serialDebounce = setTimeout(async () => {
            checkingSerial.value = true;
            try {
                const { data } = await axios.get(
                    `/asset/check-serial/${encodeURIComponent(newSerial)}`,
                );
                if (data.exists) {
                    serialError.value =
                        'Nomor seri ini sudah terpakai di Snipe-IT!';
                }
            } catch (e) {
                console.error('Failed to check serial', e);
            } finally {
                checkingSerial.value = false;
            }
        }, 500);
    },
);

const userAssetsHistory = ref([]);
const loadingHistory = ref(false);
const nextStbId = ref<number | null>(null);

const fetchNextStbId = async () => {
    try {
        const { data } = await axios.get('/stb-next-id');
        nextStbId.value = data.next_id;
    } catch (e) {
        console.error('Failed to fetch next STB ID', e);
    }
};

const estimatedStbNumber = computed(() => {
    if (!nextStbId.value) return '...';

    const companyName = String(selectedStbUser.value?.company ?? '');
    const companyCode =
        companyName
            .split(' ')
            .map((word) => word.charAt(0).toUpperCase())
            .join('') || 'IT';

    const now = new Date();
    const yearMonth =
        now.getFullYear().toString().slice(-2) +
        (now.getMonth() + 1).toString().padStart(2, '0');

    const paddedId = String(nextStbId.value).padStart(5, '0');

    return `STB-${companyCode}-${yearMonth}-${paddedId}`;
});

// Sync Location and fetch History when recipient changes
watch(
    () => form.stb_user_id,
    async (newUserId) => {
        if (form.create_stb && newUserId) {
            const user = props.metadata.users.find(
                (u) => String(u.id) === String(newUserId),
            );
            if (user && user.location_id) {
                form.location_id = user.location_id;
            }

            loadingHistory.value = true;
            userAssetsHistory.value = [];
            try {
                const { data } = await axios.get(
                    `/api/snipeit/users/${newUserId}/assets`,
                );
                userAssetsHistory.value = data;
            } catch (e) {
                console.error('Failed to fetch user assets', e);
            } finally {
                loadingHistory.value = false;
            }
        } else if (!newUserId) {
            userAssetsHistory.value = [];
        }
    },
);

// Breadcrumbs disabled as per request
const breadcrumbs = computed<BreadcrumbItem[]>(() => []);

const page = usePage();

const selectedStbUser = computed(() => {
    if (!form.stb_user_id) return null;
    return props.metadata.users.find(
        (u) => String(u.id) === String(form.stb_user_id),
    );
});

// Watch for STB toggle to auto-select Drafter and Sync Status
watch(
    () => form.create_stb,
    (enabled) => {
        if (enabled) {
            // Auto-select current user as Drafter if available
            const currentUserSnipeId = page.props.auth.user.snipeit_user_id;
            if (currentUserSnipeId && !form.stb_it_drafter_id) {
                form.stb_it_drafter_id = String(currentUserSnipeId);
            }

            // Auto-set status to Ready to Deploy if it's currently empty or not RTD
            if (currentType.value === 'assets') {
                const rtdStatus = props.metadata.assets.statuses.find(
                    (s) => s.type === 'rtd',
                );
                if (
                    rtdStatus &&
                    (!form.status_id || form.status_id != rtdStatus.id)
                ) {
                    form.status_id = rtdStatus.id;
                }
            }
        }
    },
);

const submit = () => {
    if (isEditMode.value && props.assetId) {
        form.put(`/asset/${props.assetId}`, {
            preserveScroll: true,
            forceFormData: true,
        });
        return;
    }

    form.post('/asset', {
        preserveScroll: true,
        forceFormData: true,
    });
};

const handleStockDocumentChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    form.stock_document = target.files?.[0] || null;
};

/** Draw image onto canvas at given dimensions, return a Blob. */
const renderToBlob = (
    img: HTMLImageElement,
    w: number,
    h: number,
    quality: number,
): Promise<Blob | null> => {
    return new Promise((resolve) => {
        const canvas = document.createElement('canvas');
        canvas.width = w;
        canvas.height = h;
        canvas.getContext('2d')!.drawImage(img, 0, 0, w, h);
        canvas.toBlob((blob) => resolve(blob), 'image/jpeg', quality);
    });
};

/**
 * Compress an image file to under 100 KB.
 * Iterates through decreasing resolution + quality levels until the target is met.
 * Falls back to the smallest result if the target cannot be reached.
 */
const compressImage = (file: File): Promise<File> => {
    return new Promise((resolve) => {
        const TARGET_BYTES = 100 * 1024; // 100 KB
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
            resolve(file);
            return;
        }
        const reader = new FileReader();
        reader.onload = async (e) => {
            const img = new Image();
            img.onload = async () => {
                // Steps: [maxPx, quality] — progressively smaller
                const steps: [number, number][] = [
                    [1280, 0.8],
                    [1024, 0.7],
                    [800, 0.65],
                    [640, 0.55],
                    [512, 0.45],
                    [400, 0.4],
                ];

                let bestBlob: Blob | null = null;

                for (const [maxPx, q] of steps) {
                    let w = img.width;
                    let h = img.height;
                    if (w > maxPx || h > maxPx) {
                        if (w >= h) {
                            h = Math.round((h * maxPx) / w);
                            w = maxPx;
                        } else {
                            w = Math.round((w * maxPx) / h);
                            h = maxPx;
                        }
                    } else {
                        // Image already smaller than this step's max — just try quality
                        w = img.width;
                        h = img.height;
                    }
                    const blob = await renderToBlob(img, w, h, q);
                    if (!blob) continue;
                    if (!bestBlob || blob.size < bestBlob.size) bestBlob = blob;
                    if (blob.size <= TARGET_BYTES) break; // target reached
                }

                if (!bestBlob || bestBlob.size >= file.size) {
                    resolve(file); // nothing helped — use original
                } else {
                    resolve(
                        new File([bestBlob], file.name, {
                            type: 'image/jpeg',
                            lastModified: Date.now(),
                        }),
                    );
                }
            };
            img.src = e.target!.result as string;
        };
        reader.readAsDataURL(file);
    });
};

const handleImageChange = async (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (!file) {
        form.image = null;
        return;
    }
    form.image = await compressImage(file);
};

const extractApiError = (error: unknown, fallback: string): string => {
    if (axios.isAxiosError(error)) {
        return String(
            error.response?.data?.errors?.name?.[0] ??
                error.response?.data?.message ??
                error.message ??
                fallback,
        );
    }
    if (error instanceof Error) return error.message;
    return fallback;
};

const fetchModelDetail = async (
    modelId: string | number,
): Promise<ModelItem | null> => {
    try {
        const res = await axios.get(`/api/snipeit/models/${modelId}`);
        const fullModel = res.data?.model as ModelItem | undefined;

        if (fullModel?.id) {
            const idx = modelOptions.value.findIndex(
                (m) => m.id === fullModel.id,
            );
            if (idx >= 0) {
                modelOptions.value.splice(idx, 1, fullModel);
            } else {
                modelOptions.value.unshift(fullModel);
            }
            return fullModel;
        }
        return null;
    } catch (e) {
        console.error('[DEBUG] fetchModelDetail failed:', e);
        return null;
    }
};

const resetAddModelForm = () => {
    addModelError.value = '';
    addModelForm.value = {
        name: '',
        model_number: '',
        category_id: '',
        manufacturer_id: '',
        fieldset_id: '',
    };
};

const openAddModelModal = () => {
    addModelError.value = '';
    if (form.category_id) {
        addModelForm.value.category_id = form.category_id;
    }
    showAddModel.value = true;
};

const closeAddModelModal = () => {
    showAddModel.value = false;
    addModelError.value = '';
};

const categoryTypeMap: Record<string, string> = {
    assets: 'Asset',
    license: 'License',
    accessories: 'Accessory',
    consumable: 'Consumable',
    component: 'Component',
};

const openAddCategoryModal = () => {
    addCategoryError.value = '';
    addCategoryForm.value = { name: '' };
    showAddCategory.value = true;
};

const closeAddCategoryModal = () => {
    showAddCategory.value = false;
    addCategoryError.value = '';
};

const submitAddCategory = async () => {
    addCategoryError.value = '';

    if (!addCategoryForm.value.name.trim()) {
        addCategoryError.value = 'Nama kategori wajib diisi.';
        return;
    }

    addingCategory.value = true;
    try {
        const response = await axios.post('/api/snipeit/categories', {
            name: addCategoryForm.value.name.trim(),
            category_type: categoryTypeMap[currentType.value] || 'Asset',
        });

        const created = response.data?.category as OptionItem | undefined;
        if (!created?.id) {
            addCategoryError.value =
                'Kategori dibuat tapi respons tidak valid.';
            return;
        }

        if (!extraCategories.value[currentType.value]) {
            extraCategories.value[currentType.value] = [];
        }
        extraCategories.value[currentType.value].unshift(created);
        form.category_id = String(created.id);
        showAddCategory.value = false;
    } catch (error: unknown) {
        addCategoryError.value = extractApiError(
            error,
            'Gagal membuat kategori.',
        );
    } finally {
        addingCategory.value = false;
    }
};

const submitAddModel = async () => {
    addModelError.value = '';

    if (!addModelForm.value.name.trim()) {
        addModelError.value = 'Nama model wajib diisi.';
        return;
    }

    if (!addModelForm.value.category_id) {
        addModelError.value = 'Kategori wajib dipilih.';
        return;
    }

    addingModel.value = true;

    try {
        const response = await axios.post('/api/snipeit/models', {
            name: addModelForm.value.name,
            model_number: addModelForm.value.model_number || null,
            category_id: Number(addModelForm.value.category_id),
            manufacturer_id: addModelForm.value.manufacturer_id
                ? Number(addModelForm.value.manufacturer_id)
                : null,
            fieldset_id: addModelForm.value.fieldset_id
                ? Number(addModelForm.value.fieldset_id)
                : null,
        });

        const createdModel = response.data?.model as ModelItem | undefined;

        if (!createdModel?.id) {
            addModelError.value =
                'Model berhasil dibuat, tetapi respons tidak valid.';
            return;
        }

        const existingIndex = modelOptions.value.findIndex(
            (item) => item.id === createdModel.id,
        );

        if (existingIndex >= 0) {
            modelOptions.value[existingIndex] = createdModel;
        } else {
            modelOptions.value.unshift(createdModel);
        }

        form.model_id = String(createdModel.id);
        showAddModel.value = false;
        resetAddModelForm();
    } catch (error: unknown) {
        addModelError.value = extractApiError(error, 'Gagal membuat model.');
    } finally {
        addingModel.value = false;
    }
};
const openAddManufacturerModal = () => {
    addManufacturerError.value = '';
    addManufacturerForm.value = { name: '' };
    showAddManufacturer.value = true;
};
const closeAddManufacturerModal = () => (showAddManufacturer.value = false);

const submitAddManufacturer = async () => {
    if (!addManufacturerForm.value.name.trim()) return;
    addingManufacturer.value = true;
    try {
        const res = await axios.post('/api/snipeit/manufacturers', {
            name: addManufacturerForm.value.name.trim(),
        });
        const created = res.data?.manufacturer;
        if (created?.id) {
            props.metadata.assets.manufacturers.unshift(created);
            form.manufacturer_id = String(created.id);
            showAddManufacturer.value = false;
        }
    } catch (err) {
        addManufacturerError.value = extractApiError(
            err,
            'Failed to add manufacturer',
        );
    } finally {
        addingManufacturer.value = false;
    }
};

const openAddSupplierModal = () => {
    addSupplierError.value = '';
    addSupplierForm.value = {
        name: '',
        contact_name: '',
        url: '',
        phone: '',
        fax: '',
        email: '',
        notes: '',
    };
    showAddSupplier.value = true;
};
const closeAddSupplierModal = () => (showAddSupplier.value = false);

const submitAddSupplier = async () => {
    if (!addSupplierForm.value.name.trim()) return;
    addingSupplier.value = true;
    try {
        const res = await axios.post('/api/snipeit/suppliers', {
            name: addSupplierForm.value.name.trim(),
            contact_name: addSupplierForm.value.contact_name,
            url: addSupplierForm.value.url,
            phone: addSupplierForm.value.phone,
            fax: addSupplierForm.value.fax,
            email: addSupplierForm.value.email,
            notes: addSupplierForm.value.notes,
        });
        const created = res.data?.supplier;
        if (created?.id) {
            props.metadata.assets.suppliers.unshift(created);
            form.supplier_id = String(created.id);
            showAddSupplier.value = false;
        }
    } catch (err) {
        addSupplierError.value = extractApiError(err, 'Failed to add supplier');
    } finally {
        addingSupplier.value = false;
    }
};

const openAddLocationModal = () => {
    addLocationError.value = '';
    addLocationForm.value = { name: '' };
    showAddLocation.value = true;
};
const closeAddLocationModal = () => (showAddLocation.value = false);

const submitAddLocation = async () => {
    if (!addLocationForm.value.name.trim()) return;
    addingLocation.value = true;
    try {
        const res = await axios.post('/api/snipeit/locations', {
            name: addLocationForm.value.name.trim(),
        });
        const created = res.data?.location;
        if (created?.id) {
            props.metadata.assets.locations.unshift(created);
            form.location_id = String(created.id);
            showAddLocation.value = false;
        }
    } catch (err) {
        addLocationError.value = extractApiError(err, 'Failed to add location');
    } finally {
        addingLocation.value = false;
    }
};

const openAddStatusModal = () => {
    addStatusError.value = '';
    addStatusForm.value = { name: '', type: 'deployable' };
    showAddStatus.value = true;
};
const closeAddStatusModal = () => (showAddStatus.value = false);

const submitAddStatus = async () => {
    if (!addStatusForm.value.name.trim()) return;
    addingStatus.value = true;
    try {
        const res = await axios.post('/api/snipeit/statuslabels', {
            name: addStatusForm.value.name.trim(),
            type: addStatusForm.value.type,
        });
        const created = res.data?.statuslabel;
        if (created?.id) {
            props.metadata.assets.statuses.unshift(created);
            form.status_id = String(created.id);
            showAddStatus.value = false;
        }
    } catch (err) {
        addStatusError.value = extractApiError(err, 'Failed to add status');
    } finally {
        addingStatus.value = false;
    }
};
</script>

<template>
    <Head :title="`${isEditMode ? 'Edit' : 'Buat'} ${currentTypeLabel}`" />

    <AppLayout :breadcrumbs="[]">
        <div class="app-page-shell">
            <div class="mx-auto w-full max-w-5xl px-4 py-4 md:px-8">
                <!-- MASTER CARD WRAPPER -->
                <div
                    class="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-xl"
                >
                    <AssetCreateMainFields
                        :form="form"
                        :current-type="currentType"
                        :current-type-label="currentTypeLabel"
                        :models="models"
                        :statuses="statuses"
                        :depreciations="depreciations"
                        :is-edit-mode="isEditMode"
                        :categories="categories"
                        :companies="companies"
                        :locations="locations"
                        :manufacturers="
                            metadata[form.type]?.manufacturers || []
                        "
                        :suppliers="metadata[form.type]?.suppliers || []"
                        :users="metadata.users || []"
                        :is-stock-type="isStockType"
                        :selected-model="selectedModel"
                        :open-add-model-modal="openAddModelModal"
                        :open-add-category-modal="openAddCategoryModal"
                        :open-add-manufacturer-modal="openAddManufacturerModal"
                        :open-add-supplier-modal="openAddSupplierModal"
                        :open-add-location-modal="openAddLocationModal"
                        :open-add-status-modal="openAddStatusModal"
                        :handle-stock-document-change="
                            handleStockDocumentChange
                        "
                        :handle-image-change="handleImageChange"
                        :serial-error="serialError"
                        :checking-serial="checkingSerial"
                        :custom-fields="customFields"
                        @submit="submit"
                    />

                    <div
                        v-if="!isEditMode && currentType === 'assets'"
                        class="mt-4 border-t border-slate-100 bg-slate-50/50 p-6"
                    >
                        <div class="mb-4 flex items-center justify-between">
                            <div>
                                <h3
                                    class="text-sm font-black tracking-widest text-slate-800 uppercase"
                                >
                                    Quick Handover
                                </h3>
                                <div class="mt-0.5 flex items-center gap-2">
                                    <p
                                        class="text-[10px] font-bold text-slate-400"
                                    >
                                        Automatically create STB & checkout
                                        asset
                                    </p>
                                    <span
                                        v-if="form.create_stb"
                                        class="animate-in rounded-full border border-primary/10 bg-primary/5 px-2 py-0.5 text-[10px] font-black text-primary duration-300 fade-in zoom-in"
                                    >
                                        {{ estimatedStbNumber }}
                                    </span>
                                </div>
                            </div>
                            <label
                                class="group relative inline-flex cursor-pointer items-center"
                            >
                                <input
                                    type="checkbox"
                                    v-model="form.create_stb"
                                    class="peer sr-only"
                                />
                                <div
                                    class="peer h-6 w-11 rounded-full bg-slate-200 shadow-inner peer-checked:bg-primary peer-focus:outline-none after:absolute after:top-[2px] after:left-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-full peer-checked:after:border-white"
                                ></div>
                            </label>
                        </div>

                        <div
                            v-show="form.create_stb"
                            class="animate-in space-y-4 duration-300 slide-in-from-top-2"
                        >
                            <div class="app-form-classic-row !border-none !p-0">
                                <label class="app-form-classic-label !w-48"
                                    >Recipient User
                                    <span class="app-form-label-required"
                                        >*</span
                                    ></label
                                >
                                <div
                                    class="app-form-classic-input-group flex-1"
                                >
                                    <select
                                        v-model="form.stb_user_id"
                                        class="app-select-shell app-select-compact"
                                        :disabled="form.processing"
                                    >
                                        <option value="">
                                            Select Recipient
                                        </option>
                                        <option
                                            v-for="user in props.metadata
                                                .users || []"
                                            :key="user.id"
                                            :value="String(user.id)"
                                        >
                                            {{ user.name }}
                                        </option>
                                    </select>

                                    <div
                                        v-if="selectedStbUser"
                                        class="mt-2 flex animate-in flex-wrap gap-2 duration-300 fade-in slide-in-from-left-2"
                                    >
                                        <div
                                            v-if="selectedStbUser.department"
                                            class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-2 py-1 text-[10px] font-bold tracking-tight text-slate-500 uppercase"
                                        >
                                            <Building
                                                class="size-3 text-primary"
                                            />
                                            {{ selectedStbUser.department }}
                                        </div>
                                        <div
                                            v-if="selectedStbUser.location"
                                            class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-2 py-1 text-[10px] font-bold tracking-tight text-slate-500 uppercase"
                                        >
                                            <MapPin
                                                class="size-3 text-primary"
                                            />
                                            {{ selectedStbUser.location }}
                                        </div>
                                        <div
                                            v-if="selectedStbUser.company"
                                            class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-2 py-1 text-[10px] font-bold tracking-tight text-slate-500 uppercase"
                                        >
                                            <Briefcase
                                                class="size-3 text-primary"
                                            />
                                            {{ selectedStbUser.company }}
                                        </div>
                                    </div>

                                    <!-- Simplified Recipient Asset History -->
                                    <div
                                        v-if="userAssetsHistory.length > 0"
                                        class="mt-2 flex flex-wrap gap-1.5"
                                    >
                                        <div
                                            v-for="asset in userAssetsHistory"
                                            :key="asset.id"
                                            class="group flex items-center gap-2 rounded-lg border border-slate-100 bg-white px-2 py-1 shadow-sm transition-all hover:border-primary/20"
                                        >
                                            <div
                                                class="size-1.5 rounded-full bg-primary/40 transition-colors group-hover:bg-primary"
                                            />
                                            <span
                                                class="text-[10px] font-bold text-slate-600"
                                                >{{ asset.name }}</span
                                            >
                                            <span
                                                class="text-[9px] font-black tracking-tighter text-slate-300 uppercase"
                                                >{{ asset.asset_tag }}</span
                                            >
                                        </div>
                                    </div>

                                    <p
                                        v-if="form.errors.stb_user_id"
                                        class="app-form-error"
                                    >
                                        {{ form.errors.stb_user_id }}
                                    </p>
                                </div>
                            </div>

                            <div
                                v-if="loadingHistory"
                                class="ml-48 flex items-center gap-2 py-2"
                            >
                                <Loader2
                                    class="size-3 animate-spin text-primary"
                                />
                                <span
                                    class="text-[10px] font-bold text-slate-400 italic"
                                    >Syncing asset history...</span
                                >
                            </div>

                            <!-- Additional Handover Metadata Grid -->
                            <div
                                class="grid grid-cols-1 gap-x-8 gap-y-4 rounded-2xl border border-slate-200/60 bg-slate-100/50 p-4 md:grid-cols-2"
                            >
                                <div
                                    class="app-form-classic-row !border-none !p-0"
                                >
                                    <label
                                        class="app-form-classic-label !w-32 !text-[11px]"
                                        >Default Location</label
                                    >
                                    <select
                                        v-model="form.stb_location_id"
                                        class="app-select-shell app-select-compact flex-1"
                                    >
                                        <option value="">
                                            User's Current Location
                                        </option>
                                        <option
                                            v-for="loc in props.metadata.assets
                                                ?.locations || []"
                                            :key="loc.id"
                                            :value="String(loc.id)"
                                        >
                                            {{ loc.name }}
                                        </option>
                                    </select>
                                </div>
                                <div
                                    class="app-form-classic-row !border-none !p-0"
                                >
                                    <label
                                        class="app-form-classic-label !w-32 !text-[11px]"
                                        >Gedung / Building</label
                                    >
                                    <input
                                        v-model="form.stb_building"
                                        type="text"
                                        class="app-input-shell app-input-compact flex-1"
                                        placeholder="e.g. Factory 1"
                                    />
                                </div>
                                <div
                                    class="app-form-classic-row !border-none !p-0"
                                >
                                    <label
                                        class="app-form-classic-label !w-32 !text-[11px]"
                                        >Batch No.</label
                                    >
                                    <input
                                        v-model="form.stb_batch_no"
                                        type="text"
                                        class="app-input-shell app-input-compact flex-1"
                                        placeholder="e.g. BATCH-001"
                                    />
                                </div>
                                <div
                                    class="app-form-classic-row !border-none !p-0"
                                >
                                    <label
                                        class="app-form-classic-label !w-32 !text-[11px]"
                                        >Tanggal Pakai</label
                                    >
                                    <input
                                        v-model="form.stb_use_date"
                                        type="date"
                                        class="app-input-shell app-input-compact flex-1"
                                    />
                                </div>
                                <div
                                    class="app-form-classic-row !border-none !p-0"
                                >
                                    <label
                                        class="app-form-classic-label !w-32 !text-[11px]"
                                        >Request Doc No.</label
                                    >
                                    <input
                                        v-model="form.stb_req_doc_no"
                                        type="text"
                                        class="app-input-shell app-input-compact flex-1"
                                        placeholder="e.g. REQ-2024-001"
                                    />
                                </div>
                                <div
                                    class="app-form-classic-row !border-none !p-0"
                                >
                                    <label
                                        class="app-form-classic-label !w-32 !text-[11px]"
                                        >PO Doc No.</label
                                    >
                                    <input
                                        v-model="form.stb_po_doc_no"
                                        type="text"
                                        class="app-input-shell app-input-compact flex-1"
                                        placeholder="e.g. PO-2024-001"
                                    />
                                </div>
                                <div
                                    class="app-form-classic-row !border-none !p-0 md:col-span-2"
                                >
                                    <label
                                        class="app-form-classic-label !w-32 !text-[11px]"
                                        >Remark</label
                                    >
                                    <textarea
                                        v-model="form.stb_remark"
                                        class="app-input-shell app-input-compact h-20 flex-1 resize-none py-2"
                                        placeholder="Enter any additional notes..."
                                    ></textarea>
                                </div>
                            </div>

                            <div
                                class="grid grid-cols-1 gap-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm md:grid-cols-3"
                            >
                                <div class="space-y-2">
                                    <label
                                        class="text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                        >IT Drafter
                                        <span class="text-red-500"
                                            >*</span
                                        ></label
                                    >
                                    <select
                                        v-model="form.stb_it_drafter_id"
                                        class="app-select-shell app-select-compact w-full"
                                        :disabled="form.processing"
                                    >
                                        <option value="">Select Drafter</option>
                                        <option
                                            v-for="user in props.metadata
                                                .users || []"
                                            :key="`drafter-${user.id}`"
                                            :value="String(user.id)"
                                        >
                                            {{ user.name }}
                                        </option>
                                    </select>
                                    <p
                                        v-if="form.errors.stb_it_drafter_id"
                                        class="app-form-error"
                                    >
                                        {{ form.errors.stb_it_drafter_id }}
                                    </p>
                                </div>
                                <div class="space-y-2">
                                    <label
                                        class="text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                        >IT Checker
                                        <span class="text-red-500"
                                            >*</span
                                        ></label
                                    >
                                    <select
                                        v-model="form.stb_it_checker_id"
                                        class="app-select-shell app-select-compact w-full"
                                        :disabled="form.processing"
                                    >
                                        <option value="">Select Checker</option>
                                        <option
                                            v-for="user in props.metadata
                                                .users || []"
                                            :key="`checker-${user.id}`"
                                            :value="String(user.id)"
                                        >
                                            {{ user.name }}
                                        </option>
                                    </select>
                                    <p
                                        v-if="form.errors.stb_it_checker_id"
                                        class="app-form-error"
                                    >
                                        {{ form.errors.stb_it_checker_id }}
                                    </p>
                                </div>
                                <div class="space-y-2">
                                    <label
                                        class="text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                        >IT Approver
                                        <span class="text-red-500"
                                            >*</span
                                        ></label
                                    >
                                    <select
                                        v-model="form.stb_it_approved_id"
                                        class="app-select-shell app-select-compact w-full"
                                        :disabled="form.processing"
                                    >
                                        <option value="">
                                            Select Approver
                                        </option>
                                        <option
                                            v-for="user in props.metadata
                                                .users || []"
                                            :key="`approved-${user.id}`"
                                            :value="String(user.id)"
                                        >
                                            {{ user.name }}
                                        </option>
                                    </select>
                                    <p
                                        v-if="form.errors.stb_it_approved_id"
                                        class="app-form-error"
                                    >
                                        {{ form.errors.stb_it_approved_id }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center justify-between px-1">
                                <label
                                    class="group flex cursor-pointer items-center gap-2"
                                >
                                    <input
                                        type="checkbox"
                                        v-model="rememberTeam"
                                        class="h-4 w-4 rounded border-slate-300 accent-primary"
                                    />
                                    <span
                                        class="text-[10px] font-black tracking-widest text-slate-400 uppercase transition-colors group-hover:text-primary"
                                        >Remember My Team Configuration</span
                                    >
                                </label>
                                <label
                                    class="group flex cursor-pointer items-center gap-2"
                                >
                                    <span
                                        class="text-[10px] font-black tracking-widest text-slate-400 uppercase transition-colors group-hover:text-primary"
                                        >Send Email Notification</span
                                    >
                                    <input
                                        type="checkbox"
                                        v-model="form.stb_send_notification"
                                        class="h-4 w-4 rounded border-slate-300 accent-primary"
                                    />
                                </label>
                            </div>

                            <div
                                class="flex items-center gap-2 rounded-xl border border-blue-100 bg-blue-50 p-3"
                            >
                                <div
                                    class="flex size-6 shrink-0 items-center justify-center rounded-full bg-blue-500 text-white"
                                >
                                    <Check class="size-3.5" />
                                </div>
                                <p
                                    class="text-[11px] leading-tight font-bold text-blue-700"
                                >
                                    Barang akan otomatis di-checkout ke user ini
                                    dan dokumen STB akan langsung dibuat setelah
                                    Anda menekan tombol Save.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Card Footer: Unified Action Bar -->
                    <div
                        class="flex items-center justify-end gap-3 border-t border-slate-100 bg-slate-50/50 px-8 py-5"
                    >
                        <Link
                            :href="`/asset?type=${encodeURIComponent(currentType)}`"
                            class="text-[13px] font-bold text-slate-500 transition-colors hover:text-slate-800"
                        >
                            Cancel
                        </Link>
                        <button
                            type="button"
                            class="flex h-10 items-center gap-2 rounded-lg bg-primary px-5 text-[13px] font-bold text-white shadow-lg shadow-primary/20 transition-all hover:brightness-110 active:scale-95 disabled:opacity-50"
                            :disabled="form.processing"
                            @click="submit"
                        >
                            <Check v-if="!form.processing" class="size-4" />
                            <span>{{
                                form.processing ? 'Saving...' : 'Save'
                            }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <AssetCategoryModal
            :open="showAddCategory"
            :form="addCategoryForm"
            :error="addCategoryError"
            :adding="addingCategory"
            :current-type="currentType"
            @close="closeAddCategoryModal"
            @save="submitAddCategory"
        />

        <AssetModelModal
            :open="currentType === 'assets' && showAddModel"
            :form="addModelForm"
            :error="addModelError"
            :adding="addingModel"
            :categories="assetCategories"
            :manufacturers="assetManufacturers"
            :fieldsets="assetFieldsets"
            @close="closeAddModelModal"
            @save="submitAddModel"
            @category-created="(cat) => assetCategories.unshift(cat)"
            @manufacturer-created="
                (man) => props.metadata.assets.manufacturers.unshift(man)
            "
        />

        <AssetSimpleModal
            :open="showAddManufacturer"
            title="Add Manufacturer"
            description="Create a new device manufacturer."
            :form="addManufacturerForm"
            :error="addManufacturerError"
            :adding="addingManufacturer"
            @close="closeAddManufacturerModal"
            @save="submitAddManufacturer"
        />

        <AssetSupplierModal
            :open="showAddSupplier"
            :form="addSupplierForm"
            :error="addSupplierError"
            :adding="addingSupplier"
            @close="closeAddSupplierModal"
            @save="submitAddSupplier"
        />

        <AssetSimpleModal
            :open="showAddLocation"
            title="Add Location"
            description="Create a new storage or office location."
            :form="addLocationForm"
            :error="addLocationError"
            :adding="addingLocation"
            @close="closeAddLocationModal"
            @save="submitAddLocation"
        />

        <AssetSimpleModal
            :open="showAddStatus"
            title="Add Status Label"
            description="Define a new status label for assets."
            :form="addStatusForm"
            :error="addStatusError"
            :adding="addingStatus"
            :show-type="true"
            @close="closeAddStatusModal"
            @save="submitAddStatus"
        />
    </AppLayout>
</template>
