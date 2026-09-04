<script setup lang="ts">
import {
    Tag,
    Hash,
    MapPin,
    Calendar,
    Wallet,
    User,
    ShieldCheck,
    FileText,
    Plus,
    QrCode,
    Monitor,
    Package,
    PackageOpen,
    Building2,
    Layers,
    Factory,
    Clock,
    Printer,
} from 'lucide-vue-next';
import QrcodeVue from 'qrcode.vue';
import { computed } from 'vue';

interface AssetDetail {
    id: number;
    name?: string;
    asset_tag?: string;
    serial?: string;
    model?: string;
    model_number?: string;
    category?: string;
    manufacturer?: string;
    location?: string;
    rtd_location?: string;
    company?: string;
    supplier?: string;
    status?: string;
    status_type?: string;
    qty?: number;
    remaining_qty?: number;
    checked_out?: number;
    requestable?: boolean;
    image?: string;
    created_by?: string;
    assigned_to?: string;
    assigned_to_type?: string;
    assigned_to_username?: string;
    assigned_to_email?: string;
    assigned_to_jobtitle?: string;
    notes?: string;
    purchase_date?: string;
    purchase_cost?: string;
    order_number?: string;
    warranty_months?: string;
    warranty_expires?: string;
    asset_eol_date?: string;
    book_value?: string;
    last_audit_date?: string;
    next_audit_date?: string;
    last_checkout?: string;
    last_checkin?: string;
    expected_checkin?: string;
    checkin_counter?: number;
    checkout_counter?: number;
    byod?: boolean;
    custom_fields?: Array<{ name: string; value: string; format: string }>;
    created_at?: string;
    updated_at?: string;
}

const props = defineProps<{
    asset: AssetDetail;
    assetType: string;
    assetTypeLabel: string;
}>();

const emit = defineEmits<{ 'add-stock': [] }>();

const isStockType = computed(() =>
    ['accessories', 'consumable', 'component'].includes(props.assetType),
);
const isHardware = computed(() => props.assetType === 'assets');
const isLicense = computed(() => props.assetType === 'license');

const statusConfig = computed(() => {
    const t = props.asset.status_type ?? '';
    if (t === 'deployed')
        return {
            color: 'bg-emerald-400',
            text: 'text-emerald-600',
            bg: 'bg-emerald-50',
            label: 'Deployed',
        };
    if (t === 'deployable')
        return {
            color: 'bg-sky-400',
            text: 'text-sky-600',
            bg: 'bg-sky-50',
            label: props.asset.status ?? 'Ready',
        };
    if (t === 'archived')
        return {
            color: 'bg-slate-400',
            text: 'text-slate-500',
            bg: 'bg-slate-50',
            label: 'Archived',
        };
    if (t === 'undeployable')
        return {
            color: 'bg-rose-400',
            text: 'text-rose-600',
            bg: 'bg-rose-50',
            label: props.asset.status ?? 'Unavailable',
        };
    return {
        color: 'bg-amber-400',
        text: 'text-amber-600',
        bg: 'bg-amber-50',
        label: props.asset.status ?? '-',
    };
});

const remainingPct = computed(() => {
    const total = props.asset.qty ?? 0;
    const rem = props.asset.remaining_qty ?? 0;
    if (!total) return 0;
    return Math.round((rem / total) * 100);
});

const remainingColor = computed(() => {
    const p = remainingPct.value;
    if (p <= 0) return 'text-rose-600';
    if (p <= 25) return 'text-amber-600';
    return 'text-emerald-600';
});

const remainingBarColor = computed(() => {
    const p = remainingPct.value;
    if (p <= 0) return 'bg-rose-400';
    if (p <= 25) return 'bg-amber-400';
    return 'bg-emerald-400';
});

const publicLabelUrl = computed(() => {
    const base = window.location.origin;
    const ref = props.asset.serial || props.asset.asset_tag;
    return ref ? `${base}/a/${ref}` : '';
});

function printLabel() {
    const tag = props.asset.asset_tag || props.asset.serial;
    if (!tag) return;
    window.open(`/asset/label/${encodeURIComponent(tag)}`, '_blank');
}
</script>

<template>
    <div class="space-y-3">
        <div
            class="relative overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm"
        >
            <!-- Decorative blob -->
            <div
                class="pointer-events-none absolute top-0 right-0 -mt-8 -mr-8 h-32 w-32 rounded-full bg-[#003628]/5 blur-3xl"
            />

            <div class="relative z-10 p-5">
                <!-- Image + name -->
                <div class="mb-5 flex items-start gap-4">
                    <div class="relative shrink-0">
                        <div
                            v-if="asset.image"
                            class="h-16 w-16 overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm"
                        >
                            <img
                                :src="asset.image"
                                :alt="asset.name"
                                class="h-full w-full object-contain"
                            />
                        </div>
                        <div
                            v-else
                            class="flex h-16 w-16 items-center justify-center rounded-2xl border border-slate-100 bg-slate-50"
                        >
                            <Monitor class="size-7 text-slate-200" />
                        </div>
                        <!-- Status dot -->
                        <div
                            class="absolute -right-1 -bottom-1 h-4 w-4 rounded-full border-2 border-white shadow-sm"
                            :class="statusConfig.color"
                        />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p
                            class="text-[10px] font-black tracking-widest text-slate-400 uppercase"
                        >
                            {{ assetTypeLabel }}
                        </p>
                        <h3
                            class="mt-0.5 truncate text-[14px] leading-tight font-black text-slate-900"
                        >
                            {{ asset.name || '-' }}
                        </h3>
                        <p
                            class="mt-0.5 truncate text-[11px] font-bold text-slate-400"
                        >
                            {{ asset.model || '-' }}
                        </p>
                    </div>
                </div>

                <!-- Status badge -->
                <div class="mb-4 flex items-center gap-2">
                    <span
                        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[9px] font-black tracking-widest uppercase"
                        :class="[statusConfig.bg, statusConfig.text]"
                    >
                        <span
                            class="h-1.5 w-1.5 rounded-full"
                            :class="statusConfig.color"
                        />
                        {{ statusConfig.label }}
                    </span>
                    <span
                        v-if="asset.byod"
                        class="inline-flex items-center rounded-full bg-violet-50 px-2 py-0.5 text-[9px] font-black tracking-widest text-violet-600 uppercase"
                    >
                        BYOD
                    </span>
                </div>

                <!-- Key fields -->
                <div class="space-y-2">
                    <div
                        v-if="asset.asset_tag"
                        class="flex items-center justify-between"
                    >
                        <span
                            class="flex items-center gap-1.5 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                        >
                            <Tag class="size-3" /> Asset Tag
                        </span>
                        <span
                            class="rounded-lg border border-slate-100 bg-slate-50 px-2 py-0.5 font-mono text-[11px] font-black text-slate-700"
                        >
                            {{ asset.asset_tag }}
                        </span>
                    </div>
                    <div
                        v-if="asset.serial"
                        class="flex items-center justify-between"
                    >
                        <span
                            class="flex items-center gap-1.5 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                        >
                            <Hash class="size-3" /> Serial
                        </span>
                        <span
                            class="max-w-[130px] truncate font-mono text-[11px] font-bold text-slate-600"
                            >{{ asset.serial }}</span
                        >
                    </div>
                    <div
                        v-if="asset.location"
                        class="flex items-center justify-between"
                    >
                        <span
                            class="flex items-center gap-1.5 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                        >
                            <MapPin class="size-3" /> Lokasi
                        </span>
                        <span
                            class="max-w-[130px] truncate text-[11px] font-bold text-slate-700"
                            >{{ asset.location }}</span
                        >
                    </div>
                    <div
                        v-if="asset.company"
                        class="flex items-center justify-between"
                    >
                        <span
                            class="flex items-center gap-1.5 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                        >
                            <Building2 class="size-3" /> Company
                        </span>
                        <span
                            class="max-w-[130px] truncate text-[11px] font-bold text-slate-700"
                            >{{ asset.company }}</span
                        >
                    </div>
                </div>
            </div>
        </div>

        <div
            class="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm"
        >
            <div class="space-y-4 p-5">
                <!-- Stock type: qty bar -->
                <template v-if="isStockType">
                    <div>
                        <div class="mb-2 flex items-center justify-between">
                            <span
                                class="text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                >Stok</span
                            >
                            <span
                                class="text-[11px] font-black"
                                :class="remainingColor"
                            >
                                {{ asset.remaining_qty ?? 0 }} /
                                {{ asset.qty ?? 0 }}
                            </span>
                        </div>
                        <div
                            class="h-1.5 overflow-hidden rounded-full bg-slate-100"
                        >
                            <div
                                class="h-full rounded-full transition-all duration-500"
                                :class="remainingBarColor"
                                :style="{ width: remainingPct + '%' }"
                            />
                        </div>
                        <div class="mt-1 flex justify-between">
                            <span class="text-[9px] font-bold text-slate-400"
                                >Tersisa</span
                            >
                            <span
                                class="text-[9px] font-black"
                                :class="remainingColor"
                                >{{ remainingPct }}%</span
                            >
                        </div>
                    </div>
                    <div
                        class="flex items-center justify-between border-t border-slate-50 pt-1"
                    >
                        <span
                            class="flex items-center gap-1 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                        >
                            <Package class="size-3" /> Checked Out
                        </span>
                        <span class="text-[12px] font-black text-slate-800">{{
                            asset.checked_out ?? 0
                        }}</span>
                    </div>
                </template>

                <!-- License: seats -->
                <template v-else-if="isLicense">
                    <div class="flex items-center justify-between">
                        <span
                            class="text-[9px] font-black tracking-widest text-slate-400 uppercase"
                            >Total Seats</span
                        >
                        <span class="text-[12px] font-black text-slate-800">{{
                            asset.qty ?? '-'
                        }}</span>
                    </div>
                    <div
                        class="flex items-center justify-between border-t border-slate-50 pt-3"
                    >
                        <span
                            class="text-[9px] font-black tracking-widest text-slate-400 uppercase"
                            >Tersedia</span
                        >
                        <span
                            class="text-[12px] font-black"
                            :class="remainingColor"
                            >{{ asset.remaining_qty ?? 0 }}</span
                        >
                    </div>
                </template>

                <!-- Hardware: assigned to -->
                <template v-else>
                    <div>
                        <p
                            class="mb-2 flex items-center gap-1 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                        >
                            <User class="size-3" /> Dipinjamkan Ke
                        </p>
                        <div
                            v-if="asset.assigned_to"
                            class="flex items-center gap-3 rounded-2xl border border-emerald-100 bg-emerald-50 p-3"
                        >
                            <div
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-[#003628] text-[11px] font-black text-white"
                            >
                                {{ asset.assigned_to.charAt(0).toUpperCase() }}
                            </div>
                            <div class="min-w-0">
                                <p
                                    class="truncate text-[11px] font-black text-slate-900"
                                >
                                    {{ asset.assigned_to }}
                                </p>
                                <p
                                    v-if="asset.assigned_to_jobtitle"
                                    class="truncate text-[9px] font-bold text-slate-400"
                                >
                                    {{ asset.assigned_to_jobtitle }}
                                </p>
                                <p
                                    class="mt-0.5 text-[9px] font-black tracking-widest text-emerald-600 uppercase"
                                >
                                    Aktif
                                </p>
                            </div>
                        </div>
                        <div
                            v-else
                            class="flex items-center gap-2 rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-3"
                        >
                            <div
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-slate-100"
                            >
                                <User class="size-4 text-slate-300" />
                            </div>
                            <p class="text-[10px] font-bold text-slate-400">
                                Belum dipinjamkan
                            </p>
                        </div>
                    </div>
                    <!-- Checkout/checkin counters -->
                    <div
                        v-if="
                            asset.checkout_counter !== undefined ||
                            asset.checkin_counter !== undefined
                        "
                        class="grid grid-cols-2 gap-2 border-t border-slate-50 pt-1"
                    >
                        <div class="text-center">
                            <p
                                class="text-[9px] font-black tracking-widest text-slate-400 uppercase"
                            >
                                Checkout
                            </p>
                            <p class="text-[14px] font-black text-slate-800">
                                {{ asset.checkout_counter ?? 0 }}
                            </p>
                        </div>
                        <div class="text-center">
                            <p
                                class="text-[9px] font-black tracking-widest text-slate-400 uppercase"
                            >
                                Checkin
                            </p>
                            <p class="text-[14px] font-black text-slate-800">
                                {{ asset.checkin_counter ?? 0 }}
                            </p>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <div
            class="space-y-2 rounded-[24px] border border-slate-200 bg-white p-4 shadow-sm"
        >
            <!-- QR Label Card (inline, no navigation) -->
            <div
                v-if="isHardware && publicLabelUrl"
                class="overflow-hidden rounded-xl border border-slate-100 bg-slate-50"
            >
                <!-- Card header -->
                <div class="flex items-center justify-between px-3 pt-3 pb-2">
                    <span
                        class="flex items-center gap-1.5 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                    >
                        <QrCode class="size-3" /> QR Label
                    </span>
                    <button
                        type="button"
                        class="flex h-7 items-center gap-1.5 rounded-lg bg-[#003628] px-3 text-[9px] font-black tracking-widest text-white uppercase shadow-sm shadow-[#003628]/20 transition-all hover:brightness-110 active:scale-95"
                        @click="printLabel"
                    >
                        <Printer class="size-3" />
                        Print
                    </button>
                </div>

                <!-- Screen preview (decorative, not printed) -->
                <div class="flex items-center gap-3 px-3 pb-3 print:hidden">
                    <div
                        class="shrink-0 rounded-lg border border-slate-100 bg-white p-1.5 shadow-sm"
                    >
                        <QrcodeVue
                            :value="publicLabelUrl"
                            :size="52"
                            level="M"
                            render-as="svg"
                        />
                    </div>
                    <div class="min-w-0">
                        <p
                            class="truncate text-[10px] font-black text-slate-700"
                        >
                            {{ asset.name || asset.model || '-' }}
                        </p>
                        <p
                            v-if="asset.asset_tag"
                            class="truncate font-mono text-[9px] font-bold text-slate-400"
                        >
                            {{ asset.asset_tag }}
                        </p>
                        <p
                            v-if="asset.serial"
                            class="truncate font-mono text-[9px] font-bold text-slate-300"
                        >
                            {{ asset.serial }}
                        </p>
                    </div>
                </div>

                <!-- Printable label — hidden on screen, shown only when printing -->
                <div
                    id="asset-detail-qr-label"
                    class="hidden flex-col items-center justify-center bg-white print:flex"
                    style="
                        width: 40mm;
                        height: 30mm;
                        padding: 1mm;
                        font-family: Inter, sans-serif;
                    "
                >
                    <QrcodeVue
                        :value="publicLabelUrl"
                        :size="68"
                        level="M"
                        render-as="svg"
                    />
                    <div
                        class="mt-0.5 flex w-full flex-col items-center text-center text-black"
                    >
                        <p
                            class="w-full truncate leading-none font-black tracking-tighter uppercase"
                            style="font-size: 7pt"
                        >
                            {{ asset.name || asset.model || '-' }}
                        </p>
                        <div
                            class="flex flex-wrap justify-center gap-x-1"
                            style="margin-top: 1px"
                        >
                            <p
                                v-if="asset.asset_tag"
                                class="font-bold"
                                style="font-size: 6pt"
                            >
                                {{ asset.asset_tag }}
                            </p>
                            <p
                                v-if="asset.serial"
                                class="font-bold italic opacity-60"
                                style="font-size: 5.5pt"
                            >
                                {{ asset.serial }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Add Stock -->
            <button
                v-if="isStockType"
                type="button"
                class="flex h-10 w-full items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 text-[10px] font-black tracking-widest text-emerald-700 uppercase transition-all hover:bg-emerald-100 active:scale-95"
                @click="emit('add-stock')"
            >
                <Plus class="size-4 shrink-0" />
                <span class="flex-1">Tambah Stok</span>
            </button>
        </div>

        <div
            class="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm"
        >
            <div class="px-5 pt-4 pb-1">
                <p
                    class="text-[9px] font-black tracking-[0.2em] text-slate-400 uppercase"
                >
                    Informasi Lainnya
                </p>
            </div>
            <div class="divide-y divide-slate-50">
                <div
                    v-if="asset.manufacturer"
                    class="flex items-center justify-between px-5 py-2.5"
                >
                    <span
                        class="flex items-center gap-1.5 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                        ><Factory class="size-3" /> Manufacturer</span
                    >
                    <span
                        class="max-w-[130px] truncate text-[11px] font-bold text-slate-700"
                        >{{ asset.manufacturer }}</span
                    >
                </div>
                <div
                    v-if="asset.purchase_cost"
                    class="flex items-center justify-between px-5 py-2.5"
                >
                    <span
                        class="flex items-center gap-1.5 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                        ><Wallet class="size-3" /> Harga Beli</span
                    >
                    <span class="text-[11px] font-bold text-slate-700">{{
                        asset.purchase_cost
                    }}</span>
                </div>
                <div
                    v-if="asset.purchase_date"
                    class="flex items-center justify-between px-5 py-2.5"
                >
                    <span
                        class="flex items-center gap-1.5 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                        ><Calendar class="size-3" /> Tgl Beli</span
                    >
                    <span class="text-[11px] font-bold text-slate-700">{{
                        asset.purchase_date
                    }}</span>
                </div>
                <div
                    v-if="asset.warranty_expires"
                    class="flex items-center justify-between px-5 py-2.5"
                >
                    <span
                        class="flex items-center gap-1.5 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                        ><ShieldCheck class="size-3" /> Garansi</span
                    >
                    <span class="text-[11px] font-bold text-slate-700">{{
                        asset.warranty_expires
                    }}</span>
                </div>
                <div
                    v-if="asset.order_number"
                    class="flex items-center justify-between px-5 py-2.5"
                >
                    <span
                        class="flex items-center gap-1.5 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                        ><Hash class="size-3" /> Order No</span
                    >
                    <span
                        class="max-w-[130px] truncate text-[11px] font-bold text-slate-700"
                        >{{ asset.order_number }}</span
                    >
                </div>
                <div
                    v-if="asset.last_checkout"
                    class="flex items-center justify-between px-5 py-2.5"
                >
                    <span
                        class="flex items-center gap-1.5 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                        ><Clock class="size-3" /> Last Checkout</span
                    >
                    <span class="text-[11px] font-bold text-slate-700">{{
                        asset.last_checkout
                    }}</span>
                </div>
                <div v-if="asset.notes" class="px-5 py-2.5">
                    <span
                        class="mb-1 flex items-center gap-1.5 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                        ><FileText class="size-3" /> Notes</span
                    >
                    <p
                        class="text-[10px] leading-relaxed font-medium text-slate-600"
                    >
                        {{ asset.notes }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
