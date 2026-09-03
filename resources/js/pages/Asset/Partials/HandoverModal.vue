<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { bulkCheckout } from '@/routes/asset';
import {
    X,
    Check,
    Building,
    Briefcase,
    Loader2,
    AlertCircle,
    Smartphone,
    HardDrive,
    Cpu,
    CreditCard,
    Package,
    Search,
    MapPin,
} from 'lucide-vue-next';
import { ref, computed, watch, onMounted } from 'vue';
import { notify } from '@/utils/flash';

interface AssetItem {
    id: number | string;
    name: string;
    asset_tag?: string;
    serial?: string;
    otherserial?: string;
    category?: string;
    type_name?: string;
    remaining?: number | string;
}

const props = defineProps<{
    show: boolean;
    selectedItems: AssetItem[];
    assetType: string;
    metadata: any;
    initialRecipientId?: string | number;
}>();

const emit = defineEmits(['close', 'success']);

const page = usePage();
const rememberTeam = ref(localStorage.getItem('stb_remember_team') === 'true');

const localSelectedItems = ref<AssetItem[]>([]);
const assetSearchQuery = ref('');
const assetSearchResults = ref<AssetItem[]>([]);
const searchingAssets = ref(false);

const form = useForm({
    ids: [] as (number | string)[],
    type: props.assetType,
    recipient_id: '',
    recipient_name: '',
    stb_no: '',
    deliver_date: new Date().toISOString().split('T')[0],
    use_date: '',
    building: '',
    floor: '',
    room: '',
    checker_id: '',
    approved_id: '',
    remark: '',
    items: [] as any[],
    send_notification: false,
});

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

const searchAssets = async () => {
    if (assetSearchQuery.value.length < 2) {
        assetSearchResults.value = [];
        return;
    }
    searchingAssets.value = true;
    try {
        const { data } = await axios.get('/asset/check-serial', {
            params: {
                serial: assetSearchQuery.value,
                type: props.assetType,
            },
        });
        assetSearchResults.value = Array.isArray(data)
            ? data
            : data.id
              ? [data]
              : [];
    } catch (e) {
        assetSearchResults.value = [];
    } finally {
        searchingAssets.value = false;
    }
};

const addItem = (item: AssetItem) => {
    if (props.assetType !== 'assets' && Number(item.remaining ?? 0) <= 0) {
        notify(
            'error',
            `Tolong cek kembali ketersediaan ${item.name}, saat ini stocknya 0.`,
        );
        return;
    }

    if (!localSelectedItems.value.find((i) => i.id === item.id)) {
        localSelectedItems.value.push(item);
    }
    assetSearchQuery.value = '';
    assetSearchResults.value = [];
};

const removeItem = (id: number | string) => {
    localSelectedItems.value = localSelectedItems.value.filter(
        (i) => i.id !== id,
    );
};

onMounted(() => {
    if (rememberTeam.value) {
        form.checker_id = localStorage.getItem('stb_it_checker_id') || '';
        form.approved_id = localStorage.getItem('stb_it_approved_id') || '';
    }
    fetchNextStbId();
});

const selectedStbUser = computed(() => {
    if (!form.recipient_id) return null;
    return props.metadata.users.find(
        (u: any) => String(u.id) === String(form.recipient_id),
    );
});

const estimatedStbNumber = computed(() => {
    if (!nextStbId.value) return '...';
    const companyName = String(selectedStbUser.value?.company ?? '');
    const companyCode =
        companyName
            .split(' ')
            .map((word: string) => word.charAt(0).toUpperCase())
            .join('') || 'IT';
    const now = new Date();
    const yearMonth =
        now.getFullYear().toString().slice(-2) +
        (now.getMonth() + 1).toString().padStart(2, '0');
    const paddedId = String(nextStbId.value).padStart(5, '0');
    return `STB-${companyCode}-${yearMonth}-${paddedId}`;
});

watch(
    () => props.show,
    (val) => {
        if (val) {
            localSelectedItems.value = [...props.selectedItems];
            form.type = props.assetType;

            if (props.initialRecipientId) {
                form.recipient_id = String(props.initialRecipientId);
            }

            const currentUserSnipeId = page.props.auth.user.snipeit_user_id;
            // No drafter field in form but we use it in backend
            fetchNextStbId();
        }
    },
);

watch(
    () => form.recipient_id,
    async (newUserId) => {
        if (newUserId) {
            const user = props.metadata.users.find(
                (u: any) => String(u.id) === String(newUserId),
            );
            if (user) {
                form.recipient_name = user.name;
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
        } else {
            userAssetsHistory.value = [];
            form.recipient_name = '';
        }
    },
);

const submit = () => {
    if (rememberTeam.value) {
        localStorage.setItem('stb_it_checker_id', String(form.checker_id));
        localStorage.setItem('stb_it_approved_id', String(form.approved_id));
    }

    form.ids = localSelectedItems.value.map((i) => i.id);
    form.items = localSelectedItems.value.map((i) => ({
        id: i.id,
        name: i.name,
        asset_tag: i.asset_tag,
        serial: i.serial || i.otherserial,
        model: i.type_name || i.category,
        qty: 1, // Default to 1
    }));
    form.stb_no = estimatedStbNumber.value;

    form.post(bulkCheckout.url(), {
        onSuccess: () => {
            emit('success');
            emit('close');
        },
        onError: (errors) => {
            const message = Object.values(errors)[0];
            if (message) {
                notify('error', String(message));
            }
        },
    });
};

const getIcon = (type: string) => {
    if (type === 'license') return CreditCard;
    if (type === 'accessories') return Smartphone;
    if (type === 'consumable') return Package;
    if (type === 'component') return Cpu;
    return HardDrive;
};
</script>

<template>
    <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div
            v-if="show"
            class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm"
        >
            <div
                class="flex max-h-[90vh] w-full max-w-4xl animate-in flex-col overflow-hidden rounded-[32px] bg-white shadow-2xl duration-300 zoom-in-95"
            >
                <!-- Header -->
                <div
                    class="sticky top-0 z-10 flex items-center justify-between border-b border-slate-100 bg-white px-8 py-6"
                >
                    <div>
                        <h2
                            class="text-xl font-black tracking-tight text-slate-900 uppercase"
                        >
                            Bulk Handover
                        </h2>
                        <div class="mt-0.5 flex items-center gap-2">
                            <p
                                class="text-[10px] font-bold tracking-widest text-slate-400 uppercase"
                            >
                                Penyerahan {{ selectedItems.length }} item ke
                                user
                            </p>
                            <span
                                class="rounded-full border border-[#003628]/10 bg-[#003628]/5 px-2 py-0.5 text-[10px] font-black text-[#003628]"
                            >
                                {{ estimatedStbNumber }}
                            </span>
                        </div>
                    </div>
                    <button
                        @click="emit('close')"
                        class="flex size-10 items-center justify-center rounded-2xl text-slate-400 transition-all hover:bg-slate-50 hover:text-slate-900"
                    >
                        <X class="size-5" />
                    </button>
                </div>

                <!-- Content -->
                <div class="custom-scrollbar flex-1 overflow-y-auto p-8">
                    <form @submit.prevent="submit" class="space-y-8">
                        <!-- Global Errors (e.g. Stock Availability) -->
                        <div
                            v-if="form.errors.stock"
                            class="flex animate-in items-start gap-3 rounded-2xl border border-red-100 bg-red-50 p-4 slide-in-from-top-2"
                        >
                            <AlertCircle
                                class="mt-0.5 size-5 shrink-0 text-red-500"
                            />
                            <div class="flex-1">
                                <p
                                    class="text-sm leading-tight font-bold text-red-700"
                                >
                                    {{ form.errors.stock }}
                                </p>
                                <p
                                    class="mt-1 text-[10px] font-medium tracking-widest text-red-500 uppercase"
                                >
                                    Stok Tidak Cukup
                                </p>
                            </div>
                        </div>

                        <!-- Step 1: Items to Handover -->
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <h3
                                    class="flex items-center gap-2 text-[11px] font-black tracking-widest text-slate-900 uppercase"
                                >
                                    <span
                                        class="flex size-5 items-center justify-center rounded-full bg-[#003628] text-[9px] text-white"
                                        >1</span
                                    >
                                    Item yang Diserahkan
                                </h3>

                                <div class="relative w-64">
                                    <input
                                        v-model="assetSearchQuery"
                                        @input="searchAssets"
                                        type="text"
                                        class="h-9 w-full rounded-xl border-slate-200 pr-4 pl-9 text-[11px] font-bold placeholder:text-slate-400 focus:ring-[#003628]/20"
                                        placeholder="Tambah via Serial/Tag..."
                                    />
                                    <Search
                                        class="absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-slate-400"
                                    />

                                    <!-- Search Results Dropdown -->
                                    <div
                                        v-if="assetSearchResults.length > 0"
                                        class="custom-scrollbar absolute top-full right-0 left-0 z-50 mt-2 max-h-60 overflow-hidden overflow-y-auto rounded-2xl border border-slate-200 bg-white shadow-2xl"
                                    >
                                        <div
                                            v-for="res in assetSearchResults"
                                            :key="res.id"
                                            @click="addItem(res)"
                                            class="group flex cursor-pointer items-center gap-3 border-b border-slate-50 p-3 last:border-none hover:bg-slate-50"
                                        >
                                            <div
                                                class="flex size-8 items-center justify-center rounded-lg bg-slate-100 text-slate-400"
                                            >
                                                <component
                                                    :is="getIcon(assetType)"
                                                    class="size-4"
                                                />
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <div
                                                    class="flex items-center justify-between gap-2"
                                                >
                                                    <p
                                                        class="truncate text-[11px] font-bold text-slate-900 transition-colors group-hover:text-[#003628]"
                                                    >
                                                        {{ res.name }}
                                                    </p>
                                                    <span
                                                        v-if="
                                                            assetType !==
                                                            'assets'
                                                        "
                                                        class="shrink-0 rounded-md bg-slate-100 px-1.5 py-0.5 text-[9px] font-black text-slate-500"
                                                        :class="{
                                                            'bg-red-50 text-red-500':
                                                                Number(
                                                                    res.remaining,
                                                                ) === 0,
                                                        }"
                                                    >
                                                        Stok:
                                                        {{ res.remaining }}
                                                    </span>
                                                </div>
                                                <p
                                                    class="mt-0.5 text-[9px] font-medium tracking-tight text-slate-400 tabular-nums"
                                                >
                                                    <span
                                                        v-if="res.asset_tag"
                                                        >{{
                                                            res.asset_tag
                                                        }}</span
                                                    >
                                                    <span
                                                        v-if="
                                                            res.asset_tag &&
                                                            (res.serial ||
                                                                res.otherserial)
                                                        "
                                                        class="mx-1.5 opacity-30"
                                                        >•</span
                                                    >
                                                    <span v-if="res.serial">{{
                                                        res.serial
                                                    }}</span>
                                                    <span
                                                        v-if="
                                                            res.serial &&
                                                            res.otherserial
                                                        "
                                                        class="mx-1.5 opacity-30"
                                                        >•</span
                                                    >
                                                    <span
                                                        v-if="res.otherserial"
                                                        >{{
                                                            res.otherserial
                                                        }}</span
                                                    >
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <div
                                        v-else-if="searchingAssets"
                                        class="absolute top-full right-0 left-0 z-50 mt-2 flex items-center justify-center rounded-2xl border border-slate-200 bg-white p-4 shadow-2xl"
                                    >
                                        <Loader2
                                            class="size-4 animate-spin text-[#003628]"
                                        />
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                <div
                                    v-for="item in localSelectedItems"
                                    :key="item.id"
                                    class="group/item flex items-center gap-3 rounded-2xl border border-slate-200/60 bg-slate-50 p-3"
                                >
                                    <div
                                        class="flex size-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-400 shadow-sm"
                                    >
                                        <component
                                            :is="getIcon(assetType)"
                                            class="size-5"
                                        />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div
                                            class="flex items-center justify-between gap-2"
                                        >
                                            <p
                                                class="truncate text-xs font-black text-slate-900"
                                            >
                                                {{ item.name }}
                                            </p>
                                            <span
                                                v-if="assetType !== 'assets'"
                                                class="shrink-0 rounded-md border border-slate-200 bg-white px-1 py-0.5 text-[8px] font-black text-slate-400"
                                            >
                                                Stok:
                                                {{
                                                    (item as any).remaining ??
                                                    '?'
                                                }}
                                            </span>
                                        </div>
                                        <div
                                            class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5"
                                        >
                                            <span
                                                class="text-[9px] font-bold tracking-tighter text-slate-400 uppercase tabular-nums"
                                            >
                                                {{ item.asset_tag || '-' }} •
                                                {{
                                                    item.serial ||
                                                    item.otherserial ||
                                                    '-'
                                                }}
                                            </span>
                                            <span
                                                class="size-1 rounded-full bg-slate-200"
                                            ></span>
                                            <span
                                                class="text-[9px] font-black tracking-widest text-[#003628]/60 uppercase"
                                            >
                                                {{
                                                    item.type_name ||
                                                    item.category ||
                                                    'N/A'
                                                }}
                                            </span>
                                        </div>
                                    </div>
                                    <button
                                        @click="removeItem(item.id)"
                                        class="flex size-8 items-center justify-center rounded-lg text-slate-300 opacity-0 transition-all group-hover/item:opacity-100 hover:bg-red-50 hover:text-red-500"
                                    >
                                        <X class="size-4" />
                                    </button>
                                </div>
                                <div
                                    v-if="localSelectedItems.length === 0"
                                    class="flex flex-col items-center justify-center gap-3 rounded-[32px] border-2 border-dashed border-slate-100 p-12 md:col-span-2"
                                >
                                    <div
                                        class="flex size-12 items-center justify-center rounded-full bg-slate-50 text-slate-200"
                                    >
                                        <Package class="size-6" />
                                    </div>
                                    <p
                                        class="text-sm font-bold text-slate-400 italic"
                                    >
                                        Belum ada item yang dipilih.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Step 2: Recipient -->
                        <div class="space-y-4 border-t border-slate-50 pt-4">
                            <h3
                                class="flex items-center gap-2 text-[11px] font-black tracking-widest text-slate-900 uppercase"
                            >
                                <span
                                    class="flex size-5 items-center justify-center rounded-full bg-[#003628] text-[9px] text-white"
                                    >2</span
                                >
                                Detail Penerima
                            </h3>

                            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                                <div class="space-y-2">
                                    <label
                                        class="ml-1 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                        >Penerima
                                        <span class="text-red-500"
                                            >*</span
                                        ></label
                                    >
                                    <select
                                        v-model="form.recipient_id"
                                        class="app-select-shell app-select-compact w-full"
                                        required
                                    >
                                        <option value="">Pilih Penerima</option>
                                        <option
                                            v-for="user in metadata.users"
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
                                            class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-slate-50 px-2 py-1 text-[10px] font-bold tracking-tight text-slate-500 uppercase"
                                        >
                                            <Building
                                                class="size-3 text-[#003628]"
                                            />
                                            {{ selectedStbUser.department }}
                                        </div>
                                        <div
                                            v-if="selectedStbUser.location"
                                            class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-slate-50 px-2 py-1 text-[10px] font-bold tracking-tight text-slate-500 uppercase"
                                        >
                                            <MapPin
                                                class="size-3 text-[#003628]"
                                            />
                                            {{ selectedStbUser.location }}
                                        </div>
                                        <div
                                            v-if="selectedStbUser.company"
                                            class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-slate-50 px-2 py-1 text-[10px] font-bold tracking-tight text-slate-500 uppercase"
                                        >
                                            <Briefcase
                                                class="size-3 text-[#003628]"
                                            />
                                            {{ selectedStbUser.company }}
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <label
                                        class="ml-1 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                        >Tanggal Penyerahan</label
                                    >
                                    <input
                                        v-model="form.deliver_date"
                                        type="date"
                                        class="app-input-shell app-input-compact w-full"
                                    />
                                </div>
                            </div>

                            <!-- Recipient Asset History -->
                            <div
                                v-if="userAssetsHistory.length > 0"
                                class="mt-2 space-y-2"
                            >
                                <div
                                    class="custom-scrollbar flex max-h-24 flex-wrap gap-1.5 overflow-y-auto pr-2"
                                >
                                    <div
                                        v-for="asset in userAssetsHistory"
                                        :key="asset.id"
                                        class="group flex items-center gap-2 rounded-lg border border-slate-100 bg-slate-50 px-2 py-1 shadow-sm transition-all hover:border-[#003628]/20"
                                    >
                                        <div
                                            class="size-1.5 rounded-full bg-[#003628]/40 transition-colors group-hover:bg-[#003628]"
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
                            </div>
                        </div>

                        <!-- Step 3: Signature Team -->
                        <div class="space-y-4 border-t border-slate-50 pt-4">
                            <div class="flex items-center justify-between">
                                <h3
                                    class="flex items-center gap-2 text-[11px] font-black tracking-widest text-slate-900 uppercase"
                                >
                                    <span
                                        class="flex size-5 items-center justify-center rounded-full bg-[#003628] text-[9px] text-white"
                                        >3</span
                                    >
                                    Rantai Tanda Tangan
                                </h3>
                            </div>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <label
                                        class="ml-1 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                        >Pemeriksa (IT Checker)</label
                                    >
                                    <select
                                        v-model="form.checker_id"
                                        class="app-select-shell app-select-compact w-full"
                                        required
                                    >
                                        <option value="">
                                            Pilih Pemeriksa
                                        </option>
                                        <option
                                            v-for="user in metadata.users"
                                            :key="user.id"
                                            :value="String(user.id)"
                                        >
                                            {{ user.name }}
                                        </option>
                                    </select>
                                </div>
                                <div class="space-y-2">
                                    <label
                                        class="ml-1 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                        >Penyetuju (IT Approved)</label
                                    >
                                    <select
                                        v-model="form.approved_id"
                                        class="app-select-shell app-select-compact w-full"
                                        required
                                    >
                                        <option value="">
                                            Pilih Penyetuju
                                        </option>
                                        <option
                                            v-for="user in metadata.users"
                                            :key="user.id"
                                            :value="String(user.id)"
                                        >
                                            {{ user.name }}
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <div
                                class="mt-2 flex items-center justify-between px-1"
                            >
                                <label
                                    class="group flex cursor-pointer items-center gap-2"
                                >
                                    <input
                                        type="checkbox"
                                        v-model="rememberTeam"
                                        class="h-4 w-4 rounded border-slate-300 accent-[#003628]"
                                    />
                                    <span
                                        class="text-[10px] font-black tracking-widest text-slate-400 uppercase transition-colors group-hover:text-[#003628]"
                                        >Ingat Konfigurasi Tim Saya</span
                                    >
                                </label>
                                <label
                                    class="group flex cursor-pointer items-center gap-2"
                                >
                                    <span
                                        class="text-[10px] font-black tracking-widest text-slate-400 uppercase transition-colors group-hover:text-[#003628]"
                                        >Kirim Notifikasi Email</span
                                    >
                                    <input
                                        type="checkbox"
                                        v-model="form.send_notification"
                                        class="h-4 w-4 rounded border-slate-300 accent-[#003628]"
                                    />
                                </label>
                            </div>
                        </div>

                        <!-- Step 4: Metadata -->
                        <div class="space-y-4 border-t border-slate-50 pt-4">
                            <h3
                                class="flex items-center gap-2 text-[11px] font-black tracking-widest text-slate-900 uppercase"
                            >
                                <span
                                    class="flex size-5 items-center justify-center rounded-full bg-[#003628] text-[9px] text-white"
                                    >4</span
                                >
                                Metadata Tambahan
                            </h3>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div class="space-y-2">
                                    <label
                                        class="ml-1 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                        >Gedung / Building</label
                                    >
                                    <input
                                        v-model="form.building"
                                        type="text"
                                        class="app-input-shell app-input-compact w-full"
                                        placeholder="Contoh: WH 1, Office..."
                                    />
                                </div>
                                <div class="space-y-2">
                                    <label
                                        class="ml-1 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                        >Lantai / Floor</label
                                    >
                                    <input
                                        v-model="form.floor"
                                        type="text"
                                        class="app-input-shell app-input-compact w-full"
                                        placeholder="Contoh: 1, 2, Mezzanine..."
                                    />
                                </div>
                                <div class="space-y-2">
                                    <label
                                        class="ml-1 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                        >Ruangan / Room</label
                                    >
                                    <input
                                        v-model="form.room"
                                        type="text"
                                        class="app-input-shell app-input-compact w-full"
                                        placeholder="Contoh: Meeting Room, IT..."
                                    />
                                </div>
                                <div class="space-y-2 md:col-span-3">
                                    <label
                                        class="ml-1 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                        >Keterangan / Remark</label
                                    >
                                    <textarea
                                        v-model="form.remark"
                                        class="app-input-shell app-input-compact h-20 w-full resize-none py-2"
                                        placeholder="Masukkan catatan tambahan..."
                                    ></textarea>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Footer -->
                <div
                    class="flex items-center justify-end border-t border-slate-100 bg-slate-50/50 px-8 py-6"
                >
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            @click="emit('close')"
                            class="h-11 rounded-2xl border border-slate-200 bg-white px-6 text-[11px] font-black tracking-widest text-slate-500 uppercase transition-all hover:bg-slate-50"
                        >
                            Batal
                        </button>
                        <button
                            type="button"
                            @click="submit"
                            :disabled="
                                form.processing ||
                                !form.recipient_id ||
                                localSelectedItems.length === 0
                            "
                            class="h-11 rounded-2xl bg-[#003628] px-8 text-[11px] font-black tracking-widest text-white uppercase shadow-xl shadow-emerald-900/20 transition-all hover:scale-[1.02] hover:bg-[#003628]/90 disabled:scale-100 disabled:opacity-50"
                        >
                            <div
                                v-if="form.processing"
                                class="flex items-center gap-2"
                            >
                                <Loader2 class="size-3.5 animate-spin" />
                                Memproses...
                            </div>
                            <span v-else>Proses Penyerahan</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #e2e8f0;
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #cbd5e1;
}
</style>
