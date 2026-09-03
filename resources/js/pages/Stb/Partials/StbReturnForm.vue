<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { RefreshCw } from 'lucide-vue-next';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRenderProfiler } from '@/composables/useRenderProfiler';
import {
    getStbDeptHeadLabel,
    getStbGroupParts,
    getStbUserEmail,
    getStbUserLabel,
    getStbUserPhone,
    getStbUserPosition,
    normalizeStbAssetCategory,
    useStbDirectory,
    getStbAssetReferenceValue,
} from '@/utils/stbDirectory';
import { formatStbDocId } from '@/utils/stb';
import StbFormAttachmentSection from './StbFormAttachmentSection.vue';
import StbFormDocumentSection from './StbFormDocumentSection.vue';
import StbFormApprovalSection from './StbFormRecipientSection.vue';
import StbReturnItemsTable from './StbReturnItemsTable.vue';

useRenderProfiler('StbReturnForm');

const page = usePage();
const rememberTeam = ref(localStorage.getItem('stb_remember_team') === 'true');

interface ReturnItem {
    id?: number;
    nama: string;
    kategori: string;
    type: string; // Snipe-IT type name / model name for hardware
    model: string; // Editable model/category for non-hardware (license, accessories, component)
    jumlah: number | null;
    serialNo: string;
    computer_id: number | null;
    snipeit_asset_id: number | null;
    inventory_number: string;
    is_selected: boolean;
    condition: string;
}

const props = withDefaults(
    defineProps<{
        initialData?: Record<string, any>;
        isLoading?: boolean;
        pageTitle?: string;
        pageCopy?: string;
    }>(),
    {
        initialData: () => ({}),
        isLoading: false,
    },
);

const emit = defineEmits<{
    (e: 'save', data: Record<string, any>): void;
    (e: 'cancel'): void;
}>();

const formErrors = ref<{ user_id?: string; items?: string }>({});
const userAssignedAssetsLoading = ref(false);
const isLoadingProp = computed(() => props.isLoading);

const getCurrentDate = () => {
    const now = new Date();
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
};

const getCurrentDateTime = () => {
    const now = new Date();
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}T${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
};

const toDateTimeLocal = (v?: string | null) => {
    if (!v) return getCurrentDateTime();
    const d = new Date(v);
    return isNaN(d.getTime())
        ? getCurrentDateTime()
        : d.toISOString().slice(0, 16);
};

const normalizeInteger = (v: any): number | null =>
    v === '' || v === null || v === undefined
        ? null
        : isNaN(Number(v))
          ? null
          : Number(v);

const formData = ref({
    id: props.initialData?.id ?? props.initialData?.previewId ?? null,
    status: 2,
    documentType: props.initialData?.documentType || 'handover',
    movementType: 'return' as const,
    linkedStbId: props.initialData?.linkedStbId ?? '',
    user_id: props.initialData?.user_id ?? ('' as string | number),
    user_email: props.initialData?.user_email || '',
    user_phone: props.initialData?.user_phone || '',
    group_id: props.initialData?.group_id ?? ('' as string | number),
    deliverDate: props.initialData?.deliverDate || getCurrentDate(),
    building: props.initialData?.building || '',
    useDate: props.initialData?.useDate || getCurrentDate(),
    batchNo: props.initialData?.batchNo || '',
    reqDocNo: props.initialData?.reqDocNo || '',
    poDocNo: props.initialData?.poDocNo || '',
    itDrafter_id: props.initialData?.itDrafter_id ?? ('' as string | number),
    itChecker_id: props.initialData?.itChecker_id ?? ('' as string | number),
    itApproved_id: props.initialData?.itApproved_id ?? ('' as string | number),
    photo: null as File | null,
    remark: props.initialData?.remark || '',
    createDate: toDateTimeLocal(props.initialData?.createDate),
});

// If editing an existing STB, load saved items (all selected)
const items = ref<ReturnItem[]>(
    props.initialData?.items?.length
        ? props.initialData.items.map((it: any) => ({
              id: it.id,
              nama: it.nama || '',
              kategori: it.kategori || 'assets',
              type: it.type || '',
              model: it.model || it.type || '',
              jumlah: it.jumlah ?? 1,
              serialNo: it.serialNo || it.serial_no || '',
              computer_id: it.computer_id ?? null,
              snipeit_asset_id: it.snipeit_asset_id ?? null,
              inventory_number: it.inventory_number || '',
              is_selected: it.is_selected ?? true,
              condition: it.condition || 'Good',
          }))
        : [],
);

const directory = reactive(useStbDirectory());
const selectedUserId = computed(() => normalizeInteger(formData.value.user_id));
const userSelected = computed(
    () => selectedUserId.value !== null && selectedUserId.value > 0,
);

const groupParts = computed(() =>
    getStbGroupParts(
        normalizeInteger(formData.value.group_id),
        selectedUserId.value,
    ),
);
const itUsers = computed(() =>
    directory.users.filter((u) =>
        (u.department_name || '').toUpperCase().includes('IT'),
    ),
);
const resolvedName = computed(() => getStbUserLabel(selectedUserId.value));
const deptHead = computed(() => getStbDeptHeadLabel(selectedUserId.value));

const applyRememberedItTeam = () => {
    if (!rememberTeam.value) return;

    const savedChecker = localStorage.getItem('stb_it_checker_id');
    const savedApproved = localStorage.getItem('stb_it_approved_id');

    if (savedChecker) formData.value.itChecker_id = savedChecker;
    if (savedApproved) formData.value.itApproved_id = savedApproved;
};

const fetchAssetsForUser = async (userId: number) => {
    if (userId <= 0) return;

    userAssignedAssetsLoading.value = true;
    formErrors.value.items = undefined;

    try {
        // Auto-link last out STB
        axios
            .get(`/stb/last-out/${userId}`)
            .then((res) => {
                if (res.data?.stb?.id)
                    formData.value.linkedStbId = res.data.stb.id;
            })
            .catch(() => {});

        const types = ['assets', 'license', 'accessories', 'component'];
        const results = await Promise.all(
            types.map((type) =>
                directory
                    .fetchUserAssets(userId, type)
                    .then((a) => ({ type, a })),
            ),
        );

        const newItems: ReturnItem[] = [];
        results.forEach(({ type, a: assets }) => {
            assets.forEach((asset) => {
                const isHw =
                    asset.asset_type === 'assets' ||
                    asset.asset_type === 'hardware';
                const typeName =
                    asset.asset_type_label || asset.type_name || '-';
                newItems.push({
                    nama: asset.name || '-',
                    kategori: normalizeStbAssetCategory(asset.asset_type),
                    type: typeName,
                    // Hardware: model = nama model dari Snipe-IT (e.g. "Dell Latitude 5510")
                    // Non-hardware: model = kategori Snipe-IT (e.g. "Microsoft Office", "Logitech")
                    model: typeName,
                    jumlah: 1,
                    serialNo: asset.serial || '',
                    computer_id: isHw ? asset.id : null,
                    snipeit_asset_id: asset.id,
                    inventory_number:
                        asset.inventory_number ||
                        getStbAssetReferenceValue(asset) ||
                        '',
                    is_selected: false,
                    condition: 'Good',
                });
            });
        });

        const quickSelectedIds = new Set(
            (props.initialData?.items ?? [])
                .map((item: any) =>
                    Number(item.snipeit_asset_id ?? item.computer_id ?? 0),
                )
                .filter((id) => id > 0),
        );

        // Only auto-populate if creating new (not editing).
        // Preserve any route-based quick selection so the user immediately sees the
        // assets that were selected from the list and those rows remain checked.
        if (!props.initialData?.id) {
            items.value = newItems.map((item) => {
                const itemId = Number(
                    item.snipeit_asset_id ?? item.computer_id ?? 0,
                );
                return quickSelectedIds.has(itemId)
                    ? { ...item, is_selected: true }
                    : item;
            });
        }
    } catch (e) {
        console.error('[StbReturnForm] fetchAssetsForUser error:', e);
    } finally {
        userAssignedAssetsLoading.value = false;
    }
};

// Single watcher — triggers on user_id change
watch(
    selectedUserId,
    (newId) => {
        if (newId && newId > 0) {
            // Auto-fill group_id from user
            const usr = directory.users.find((u) => u.id === newId);
            if (usr?.location_id) formData.value.group_id = usr.location_id;
            formData.value.user_email = getStbUserEmail(usr as any);
            formData.value.user_phone = getStbUserPhone(usr as any);

            if (rememberTeam.value) {
                applyRememberedItTeam();
            }

            fetchAssetsForUser(newId);
        } else {
            if (!props.initialData?.id) items.value = [];
        }
    },
    { immediate: true },
);

onMounted(async () => {
    await directory.ensureDirectoryLoaded();
    if (!formData.value.id) {
        const snipeId = (page.props.auth.user as any)?.snipeit_user_id;
        if (snipeId) formData.value.itDrafter_id = snipeId;

        // Auto-fill dates for new documents
        if (!formData.value.deliverDate)
            formData.value.deliverDate = getCurrentDate();
        if (!formData.value.useDate) formData.value.useDate = getCurrentDate();

        applyRememberedItTeam();
    }

    if (selectedUserId.value && selectedUserId.value > 0) {
        const usr = directory.users.find((u) => u.id === selectedUserId.value);
        if (usr?.location_id) formData.value.group_id = usr.location_id;
        formData.value.user_email = getStbUserEmail(usr as any);
        formData.value.user_phone = getStbUserPhone(usr as any);
    }

    // If edit mode, still trigger a fetch for freshness but don't overwrite items
    if (props.initialData?.user_id) {
        fetchAssetsForUser(Number(props.initialData.user_id));
    }
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
    (v) =>
        rememberTeam.value &&
        v &&
        localStorage.setItem('stb_it_checker_id', String(v)),
);
watch(
    () => formData.value.itApproved_id,
    (v) =>
        rememberTeam.value &&
        v &&
        localStorage.setItem('stb_it_approved_id', String(v)),
);

const photoLoadFailed = ref(false);
const existingPhotoPreview = computed(() => {
    const p = props.initialData?.photo;
    if (!p || photoLoadFailed.value) return null;
    const s = String(p).trim();
    if (!s) return null;

    if (
        s.startsWith('http://') ||
        s.startsWith('https://') ||
        s.startsWith('data:')
    ) {
        return s;
    }

    if (s.startsWith('/storage/')) {
        return s;
    }

    if (s.startsWith('storage/')) {
        return `/${s}`;
    }

    if (s.startsWith('/')) {
        return `/storage${s}`;
    }

    if (s.startsWith('public/')) {
        return `/storage/${s.replace(/^public\//, '')}`;
    }

    return `/storage/${s.replace(/^\/+/, '')}`;
});
const photoPreview = computed(() =>
    formData.value.photo
        ? window.URL.createObjectURL(formData.value.photo)
        : existingPhotoPreview.value,
);

const docIdDisplay = computed(() => {
    if (!formData.value.id) return '';
    return (
        formatStbDocId({
            id: formData.value.id,
            locationName: groupParts.value.location,
            date: formData.value.createDate,
        }) || ''
    );
});

const handleSubmit = () => {
    formErrors.value = {};
    const selected = items.value.filter((i) => i.is_selected);
    if (selected.length === 0) {
        formErrors.value.items = 'Pilih minimal satu item yang dikembalikan';
        return;
    }
    emit('save', {
        ...formData.value,
        docId: docIdDisplay.value || null,
        id: normalizeInteger(formData.value.id),
        status: 2,
        movementType: 'return',
        documentType: formData.value.documentType || 'handover',
        user_id: normalizeInteger(formData.value.user_id),
        group_id: normalizeInteger(formData.value.group_id),
        itDrafter_id: normalizeInteger(formData.value.itDrafter_id),
        itChecker_id: normalizeInteger(formData.value.itChecker_id),
        itApproved_id: normalizeInteger(formData.value.itApproved_id),
        linkedStbId: normalizeInteger(formData.value.linkedStbId),
        items: selected.map((item) => ({
            nama: item.nama.trim(),
            kategori: item.kategori,
            // Use model field for non-hardware, type for hardware
            type: item.model || item.type,
            jumlah: normalizeInteger(item.jumlah) ?? 1,
            serialNo: item.serialNo || '',
            inventory_number: item.inventory_number || '',
            computer_id: normalizeInteger(item.computer_id),
            snipeit_asset_id: normalizeInteger(item.snipeit_asset_id),
            condition: item.condition || 'Good',
        })),
    });
};
</script>

<template>
    <form
        class="min-h-screen bg-[#f8fafc] pb-20"
        @submit.prevent="handleSubmit"
    >
        <div class="mx-auto max-w-5xl space-y-6 px-6 pt-10">
            <div
                class="overflow-hidden rounded-2xl bg-white shadow-[0_8px_30px_rgb(0,0,0,0.04)]"
            >
                <!-- Header -->
                <div
                    class="flex items-center justify-between border-b border-slate-100 px-8 py-7"
                >
                    <div>
                        <h1
                            class="text-2xl font-black tracking-tight text-[#003628] uppercase"
                        >
                            {{ props.pageKicker }}
                        </h1>
                        <p
                            class="mt-1 text-[11px] font-black tracking-[0.25em] text-[#d99528] uppercase"
                        >
                            Pengembalian
                        </p>
                        <p
                            class="mt-1.5 text-[10px] font-bold text-slate-400 italic"
                        >
                            Select user → check returned items → Save
                        </p>
                    </div>
                    <button
                        type="button"
                        :disabled="userAssignedAssetsLoading || !userSelected"
                        class="flex h-9 items-center gap-1.5 rounded-lg bg-slate-100 px-4 text-[10px] font-black tracking-widest text-slate-600 uppercase transition-all hover:bg-slate-200 disabled:opacity-40"
                        @click="
                            selectedUserId && fetchAssetsForUser(selectedUserId)
                        "
                    >
                        <RefreshCw
                            :class="[
                                'size-3',
                                userAssignedAssetsLoading && 'animate-spin',
                            ]"
                        />
                        Refresh Assets
                    </button>
                </div>

                <!-- Section 1: Document Info -->
                <div class="border-b border-slate-50 px-8 py-4">
                    <StbFormDocumentSection
                        :doc-id-display="docIdDisplay"
                        :form-data="formData"
                        :users="directory.users"
                        :document-type-options="[
                            { value: 'handover', label: 'Hand Over' },
                        ]"
                        :movement-options="[
                            { value: 'return', label: 'Return' },
                        ]"
                        :loan-references="[]"
                        :resolved-location-label="groupParts.location || '-'"
                        :form-errors="formErrors"
                        :lock-document-flow="true"
                        document-flow-label="RETURN"
                        document-date-label="Return Date"
                        selected-loan-reference-label=""
                    />
                </div>

                <!-- Section 2: User / Approval -->
                <div class="border-b border-slate-50 px-8 py-4">
                    <StbFormApprovalSection
                        v-model:remember-team="rememberTeam"
                        :form-data="formData"
                        :users="directory.users"
                        :it-users="itUsers"
                        :resolved-name="resolvedName"
                        :dept-head="deptHead"
                        :group-parts="groupParts"
                        :phone-number="getStbUserPhone(selectedUserId)"
                        :email="getStbUserEmail(selectedUserId)"
                        :position="getStbUserPosition(selectedUserId)"
                        :requester-received="resolvedName"
                        :requester-dept-head="deptHead"
                        recipient-kicker="Return"
                        recipient-title="Recipient Profile"
                        recipient-copy="User data for the returner."
                        requester-kicker="Validation"
                        requester-title="User & Manager"
                        requester-copy="Confirmation of the returner."
                        requester-received-label="Receiver"
                        requester-dept-head-label="Dept Head"
                        :it-drafter-name="
                            getStbUserLabel(formData.itDrafter_id)
                        "
                    />
                </div>

                <!-- Section 3: Return Items (checkbox) -->
                <div class="border-b border-slate-50 px-8 py-6">
                    <StbReturnItemsTable
                        :items="items"
                        :is-loading="userAssignedAssetsLoading"
                        :user-selected="userSelected"
                        :form-error="formErrors.items"
                        @update:items="items = $event"
                    />
                </div>

                <!-- Section 4: Attachment -->
                <div class="border-b border-slate-50 px-8 py-4">
                    <StbFormAttachmentSection
                        :form-data="formData"
                        :photo-preview="photoPreview"
                        :is-compressing-photo="false"
                        :handle-photo-change="
                            (e: any) => {
                                if (e.target.files?.[0])
                                    formData.photo = e.target.files[0];
                            }
                        "
                        :handle-photo-preview-error="
                            () => {
                                photoLoadFailed = true;
                            }
                        "
                        section-kicker="Condition"
                        section-title="Photo & Return Notes"
                        section-copy="Attach photos of the asset condition upon return and verification notes."
                        photo-label="Return Photo"
                        remark-label="Condition Notes"
                        preview-alt="Return Photo"
                        empty-photo-label="Photo will appear here"
                    />
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-between px-8 py-6">
                    <p class="text-[10px] font-bold text-slate-400">
                        <span
                            v-if="items.filter((i) => i.is_selected).length > 0"
                            class="text-[#003628]"
                        >
                            {{ items.filter((i) => i.is_selected).length }}
                            assets
                        </span>
                        <span v-else class="text-slate-300"
                            >No assets selected yet</span
                        >
                        &nbsp;will be saved as a return.
                    </p>
                    <div class="flex gap-3">
                        <button
                            type="button"
                            @click="emit('cancel')"
                            class="h-10 rounded-lg border border-slate-200 bg-white px-6 text-[11px] font-black tracking-widest text-slate-500 uppercase transition-all hover:bg-slate-50"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="isLoadingProp"
                            class="h-10 rounded-lg bg-[#003628] px-8 text-[11px] font-black tracking-widest text-white uppercase shadow-md transition-all hover:brightness-110 active:scale-95 disabled:opacity-50"
                        >
                            {{ isLoadingProp ? 'Saving...' : 'Save Return' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</template>
