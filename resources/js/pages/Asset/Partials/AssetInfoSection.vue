<script setup lang="ts">
import { computed, ref } from 'vue';
import {
    Hash,
    Tag,
    Layers,
    Factory,
    Folder,
    Building2,
    MapPin,
    Truck,
    Package,
    Wallet,
    Calendar,
    Receipt,
    FileText,
    Check,
    Ban,
    Copy,
    CheckCheck,
    ShieldCheck,
    Mail,
    User,
    Clock,
} from 'lucide-vue-next';

export interface AssetDetail {
    id: number;
    name?: string;
    asset_tag?: string;
    serial?: string;
    model?: string;
    model_number?: string;
    item_no?: string;
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
    min_qty?: number | string;
    notes?: string;
    purchase_date?: string;
    purchase_cost?: string;
    order_number?: string;
    po_number?: string;
    warranty_months?: string;
    warranty_expires?: string;
    assigned_to?: string;
    assigned_to_username?: string;
    assigned_to_email?: string;
    license_name?: string;
    license_email?: string;
    expiration_date?: string;
    termination_date?: string;
    reassignable?: boolean;
    maintained?: boolean;
    seats?: number;
    free_seats?: number;
    updated_at?: string;
    custom_fields?: Array<{ name: string; value: string; format: string }>;
}

interface Props {
    asset: AssetDetail;
    assetType: string;
    assetTypeLabel: string;
}

const props = defineProps<Props>();

const copiedField = ref<string | null>(null);

function copyToClipboard(text: string | undefined, fieldKey: string) {
    if (!text || text === '—' || text === '-') return;
    navigator.clipboard.writeText(text);
    copiedField.value = fieldKey;
    setTimeout(() => {
        if (copiedField.value === fieldKey) {
            copiedField.value = null;
        }
    }, 1800);
}

const isLicense = computed(() => props.assetType === 'license');
</script>

<template>
    <div class="space-y-6">
        <!-- 1. SPESIFIKASI & IDENTITAS -->
        <div
            class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs transition hover:border-slate-300/80"
        >
            <div
                class="mb-4 flex items-center justify-between border-b border-slate-100 pb-3"
            >
                <div class="flex items-center gap-2.5">
                    <div
                        class="flex size-7 items-center justify-center rounded-lg bg-[#003628]/10 text-[#003628]"
                    >
                        <Layers class="size-4" />
                    </div>
                    <div>
                        <h4
                            class="text-xs font-black tracking-widest text-slate-800 uppercase"
                        >
                            Spesifikasi & Identitas Item
                        </h4>
                        <p class="text-[11px] text-slate-400">
                            Rincian nomor identifikasi, nomor model, dan
                            klasifikasi item.
                        </p>
                    </div>
                </div>
                <span
                    class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[10px] font-bold text-slate-600 uppercase"
                >
                    {{ assetTypeLabel }}
                </span>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <!-- Tag Aset (Asset Tag) -->
                <div
                    v-if="asset.asset_tag"
                    class="group relative flex flex-col justify-between rounded-xl border border-slate-100 bg-slate-50/50 p-3.5 transition hover:border-slate-200"
                >
                    <div class="flex items-center justify-between">
                        <span
                            class="flex items-center gap-1.5 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                        >
                            <Tag class="size-3 text-[#003628]" />
                            Tag Aset
                        </span>
                        <button
                            v-if="asset.asset_tag && asset.asset_tag !== '—'"
                            type="button"
                            class="text-slate-400 transition hover:text-slate-700"
                            title="Salin Tag Aset"
                            @click="
                                copyToClipboard(asset.asset_tag, 'asset_tag')
                            "
                        >
                            <CheckCheck
                                v-if="copiedField === 'asset_tag'"
                                class="size-3 text-emerald-600"
                            />
                            <Copy
                                v-else
                                class="size-3 opacity-0 transition group-hover:opacity-100"
                            />
                        </button>
                    </div>
                    <p
                        class="mt-2 font-mono text-xs font-bold text-slate-900 select-all"
                    >
                        {{ asset.asset_tag }}
                    </p>
                </div>

                <!-- Serial Number -->
                <div
                    class="group relative flex flex-col justify-between rounded-xl border border-slate-100 bg-slate-50/50 p-3.5 transition hover:border-slate-200"
                >
                    <div class="flex items-center justify-between">
                        <span
                            class="flex items-center gap-1.5 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                        >
                            <Hash class="size-3 text-[#003628]" />
                            Nomor Seri
                        </span>
                        <button
                            v-if="asset.serial && asset.serial !== '—'"
                            type="button"
                            class="text-slate-400 transition hover:text-slate-700"
                            title="Salin Nomor Seri"
                            @click="copyToClipboard(asset.serial, 'serial')"
                        >
                            <CheckCheck
                                v-if="copiedField === 'serial'"
                                class="size-3 text-emerald-600"
                            />
                            <Copy
                                v-else
                                class="size-3 opacity-0 transition group-hover:opacity-100"
                            />
                        </button>
                    </div>
                    <p
                        class="mt-2 font-mono text-xs font-bold text-slate-900 select-all"
                    >
                        {{ asset.serial || '—' }}
                    </p>
                </div>

                <!-- Model Number / Item No -->
                <div
                    class="group relative flex flex-col justify-between rounded-xl border border-slate-100 bg-slate-50/50 p-3.5 transition hover:border-slate-200"
                >
                    <div class="flex items-center justify-between">
                        <span
                            class="flex items-center gap-1.5 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                        >
                            <Tag class="size-3 text-slate-400" />
                            {{
                                isLicense
                                    ? 'Lisensi Ke'
                                    : asset.item_no
                                      ? 'Nomor Item'
                                      : 'Nomor Model'
                            }}
                        </span>
                    </div>
                    <p
                        class="mt-2 font-mono text-xs font-bold text-slate-900 select-all"
                    >
                        {{
                            asset.model_number ||
                            asset.item_no ||
                            asset.license_name ||
                            '—'
                        }}
                    </p>
                </div>

                <!-- Category -->
                <div
                    class="flex flex-col justify-between rounded-xl border border-slate-100 bg-slate-50/50 p-3.5"
                >
                    <span
                        class="flex items-center gap-1.5 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                    >
                        <Folder class="size-3 text-slate-400" />
                        Kategori
                    </span>
                    <p class="mt-2 text-xs font-bold text-slate-800">
                        {{ asset.category || '—' }}
                    </p>
                </div>

                <!-- Manufacturer -->
                <div
                    class="flex flex-col justify-between rounded-xl border border-slate-100 bg-slate-50/50 p-3.5"
                >
                    <span
                        class="flex items-center gap-1.5 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                    >
                        <Factory class="size-3 text-slate-400" />
                        Manufaktur / Merk
                    </span>
                    <p class="mt-2 text-xs font-bold text-slate-800">
                        {{ asset.manufacturer || '—' }}
                    </p>
                </div>

                <!-- Model / Type -->
                <div
                    class="flex flex-col justify-between rounded-xl border border-slate-100 bg-slate-50/50 p-3.5"
                >
                    <span
                        class="flex items-center gap-1.5 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                    >
                        <Layers class="size-3 text-slate-400" />
                        Model Barang
                    </span>
                    <p class="mt-2 text-xs font-bold text-slate-800">
                        {{ asset.model || asset.name || '—' }}
                    </p>
                </div>

                <!-- Status Asset -->
                <div
                    v-if="asset.status"
                    class="flex flex-col justify-between rounded-xl border border-slate-100 bg-slate-50/50 p-3.5"
                >
                    <span
                        class="flex items-center gap-1.5 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                    >
                        <ShieldCheck class="size-3 text-slate-400" />
                        Status Aset
                    </span>
                    <p class="mt-2 text-xs font-bold text-slate-800">
                        {{ asset.status }}
                    </p>
                </div>

                <!-- Minimum Buffer Stock (if applicable) -->
                <div
                    v-if="
                        asset.min_qty !== undefined &&
                        asset.min_qty !== null &&
                        asset.min_qty !== ''
                    "
                    class="flex flex-col justify-between rounded-xl border border-slate-100 bg-slate-50/50 p-3.5"
                >
                    <span
                        class="flex items-center gap-1.5 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                    >
                        <Package class="size-3 text-slate-400" />
                        Ambang Batas (Min. Qty)
                    </span>
                    <p class="mt-2 font-mono text-xs font-bold text-slate-800">
                        {{ asset.min_qty }}
                    </p>
                </div>

                <!-- Special License Fields -->
                <template v-if="isLicense">
                    <div
                        v-if="asset.license_email"
                        class="flex flex-col justify-between rounded-xl border border-slate-100 bg-slate-50/50 p-3.5"
                    >
                        <span
                            class="flex items-center gap-1.5 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                        >
                            <Mail class="size-3 text-slate-400" />
                            License Email
                        </span>
                        <p
                            class="mt-2 truncate text-xs font-bold text-slate-800"
                        >
                            {{ asset.license_email }}
                        </p>
                    </div>

                    <div
                        class="flex flex-col justify-between rounded-xl border border-slate-100 bg-slate-50/50 p-3.5"
                    >
                        <span
                            class="flex items-center gap-1.5 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                        >
                            <ShieldCheck class="size-3 text-slate-400" />
                            Dapat Dialihkan (Reassignable)
                        </span>
                        <div class="mt-2">
                            <span
                                class="inline-flex items-center gap-1 text-xs font-bold"
                                :class="
                                    asset.reassignable
                                        ? 'text-emerald-700'
                                        : 'text-slate-500'
                                "
                            >
                                <Check
                                    v-if="asset.reassignable"
                                    class="size-3.5 stroke-[2.5]"
                                />
                                <Ban v-else class="size-3.5" />
                                {{
                                    asset.reassignable
                                        ? 'Ya (Dapat dialihkan)'
                                        : 'Tidak (Permanen)'
                                }}
                            </span>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- 2. LOKASI & KEPEMILIKAN (ORGANISASI) -->
        <div
            class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs transition hover:border-slate-300/80"
        >
            <div
                class="mb-4 flex items-center justify-between border-b border-slate-100 pb-3"
            >
                <div class="flex items-center gap-2.5">
                    <div
                        class="flex size-7 items-center justify-center rounded-lg bg-sky-50 text-sky-700"
                    >
                        <MapPin class="size-4" />
                    </div>
                    <div>
                        <h4
                            class="text-xs font-black tracking-widest text-slate-800 uppercase"
                        >
                            Penempatan & Kepemilikan
                        </h4>
                        <p class="text-[11px] text-slate-400">
                            Lokasi fisik penyimpanan dan entitas perusahaan
                            penanggung jawab.
                        </p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <!-- Assigned To User (if assigned) -->
                <div
                    v-if="asset.assigned_to"
                    class="flex items-start gap-3 rounded-xl border border-slate-100 bg-slate-50/50 p-4"
                >
                    <div
                        class="flex size-8 shrink-0 items-center justify-center rounded-lg border border-slate-200/60 bg-white text-slate-600 shadow-2xs"
                    >
                        <User class="size-4 text-[#003628]" />
                    </div>
                    <div class="min-w-0">
                        <p
                            class="text-[10px] font-black tracking-widest text-slate-400 uppercase"
                        >
                            Pemegang Saat Ini
                        </p>
                        <p
                            class="mt-1 truncate text-xs leading-snug font-bold text-slate-900"
                        >
                            {{ asset.assigned_to }}
                        </p>
                        <p
                            v-if="
                                asset.assigned_to_email ||
                                asset.assigned_to_username
                            "
                            class="truncate text-[10px] text-slate-400"
                        >
                            {{
                                asset.assigned_to_email ||
                                asset.assigned_to_username
                            }}
                        </p>
                    </div>
                </div>

                <!-- Location -->
                <div
                    class="flex items-start gap-3 rounded-xl border border-slate-100 bg-slate-50/50 p-4"
                >
                    <div
                        class="flex size-8 shrink-0 items-center justify-center rounded-lg border border-slate-200/60 bg-white text-slate-600 shadow-2xs"
                    >
                        <MapPin class="size-4 text-emerald-700" />
                    </div>
                    <div class="min-w-0">
                        <p
                            class="text-[10px] font-black tracking-widest text-slate-400 uppercase"
                        >
                            Lokasi Penyimpanan
                        </p>
                        <p
                            class="mt-1 text-xs leading-snug font-bold text-slate-900"
                        >
                            {{ asset.location || '—' }}
                        </p>
                    </div>
                </div>

                <!-- Default RTD Location (if different) -->
                <div
                    v-if="
                        asset.rtd_location &&
                        asset.rtd_location !== asset.location
                    "
                    class="flex items-start gap-3 rounded-xl border border-slate-100 bg-slate-50/50 p-4"
                >
                    <div
                        class="flex size-8 shrink-0 items-center justify-center rounded-lg border border-slate-200/60 bg-white text-slate-600 shadow-2xs"
                    >
                        <MapPin class="size-4 text-sky-700" />
                    </div>
                    <div class="min-w-0">
                        <p
                            class="text-[10px] font-black tracking-widest text-slate-400 uppercase"
                        >
                            Lokasi Asal (Default)
                        </p>
                        <p
                            class="mt-1 text-xs leading-snug font-bold text-slate-900"
                        >
                            {{ asset.rtd_location }}
                        </p>
                    </div>
                </div>

                <!-- Company -->
                <div
                    class="flex items-start gap-3 rounded-xl border border-slate-100 bg-slate-50/50 p-4"
                >
                    <div
                        class="flex size-8 shrink-0 items-center justify-center rounded-lg border border-slate-200/60 bg-white text-slate-600 shadow-2xs"
                    >
                        <Building2 class="size-4 text-sky-700" />
                    </div>
                    <div class="min-w-0">
                        <p
                            class="text-[10px] font-black tracking-widest text-slate-400 uppercase"
                        >
                            Perusahaan / Entity
                        </p>
                        <p
                            class="mt-1 text-xs leading-snug font-bold text-slate-900"
                        >
                            {{ asset.company || '—' }}
                        </p>
                    </div>
                </div>

                <!-- Supplier -->
                <div
                    class="flex items-start gap-3 rounded-xl border border-slate-100 bg-slate-50/50 p-4"
                >
                    <div
                        class="flex size-8 shrink-0 items-center justify-center rounded-lg border border-slate-200/60 bg-white text-slate-600 shadow-2xs"
                    >
                        <Truck class="size-4 text-amber-700" />
                    </div>
                    <div class="min-w-0">
                        <p
                            class="text-[10px] font-black tracking-widest text-slate-400 uppercase"
                        >
                            Pemasok / Vendor
                        </p>
                        <p
                            class="mt-1 text-xs leading-snug font-bold text-slate-900"
                        >
                            {{ asset.supplier || '—' }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. PENGADAAN & FINANSIAL -->
        <div
            class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs transition hover:border-slate-300/80"
        >
            <div
                class="mb-4 flex items-center justify-between border-b border-slate-100 pb-3"
            >
                <div class="flex items-center gap-2.5">
                    <div
                        class="flex size-7 items-center justify-center rounded-lg bg-amber-50 text-amber-700"
                    >
                        <Wallet class="size-4" />
                    </div>
                    <div>
                        <h4
                            class="text-xs font-black tracking-widest text-slate-800 uppercase"
                        >
                            Pengadaan & Finansial
                        </h4>
                        <p class="text-[11px] text-slate-400">
                            Nomor referensi pembelian, tanggal faktur, garansi,
                            dan harga aset.
                        </p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <!-- Order Number / PO -->
                <div
                    class="flex flex-col justify-between rounded-xl border border-slate-100 bg-slate-50/50 p-3.5"
                >
                    <span
                        class="flex items-center gap-1.5 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                    >
                        <Receipt class="size-3 text-slate-400" />
                        No. Order / Kontrak
                    </span>
                    <p class="mt-2 font-mono text-xs font-bold text-slate-800">
                        {{ asset.order_number || asset.po_number || '—' }}
                    </p>
                </div>

                <!-- Purchase Date -->
                <div
                    class="flex flex-col justify-between rounded-xl border border-slate-100 bg-slate-50/50 p-3.5"
                >
                    <span
                        class="flex items-center gap-1.5 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                    >
                        <Calendar class="size-3 text-slate-400" />
                        Tanggal Pembelian
                    </span>
                    <p class="mt-2 text-xs font-bold text-slate-800">
                        {{ asset.purchase_date || '—' }}
                    </p>
                </div>

                <!-- Purchase Cost -->
                <div
                    class="flex flex-col justify-between rounded-xl border border-slate-100 bg-slate-50/50 p-3.5"
                >
                    <span
                        class="flex items-center gap-1.5 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                    >
                        <Wallet class="size-3 text-slate-400" />
                        Harga Satuan / Nilai
                    </span>
                    <p class="mt-2 text-xs font-black text-slate-900">
                        {{ asset.purchase_cost || '—' }}
                    </p>
                </div>

                <!-- Warranty (if applicable) -->
                <div
                    v-if="asset.warranty_months || asset.warranty_expires"
                    class="flex flex-col justify-between rounded-xl border border-slate-100 bg-slate-50/50 p-3.5"
                >
                    <span
                        class="flex items-center gap-1.5 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                    >
                        <Clock class="size-3 text-slate-400" />
                        Masa Garansi
                    </span>
                    <div class="mt-2">
                        <p class="text-xs font-bold text-slate-900">
                            {{
                                asset.warranty_months
                                    ? asset.warranty_months + ' Bulan'
                                    : 'Garansi Tercatat'
                            }}
                        </p>
                        <p
                            v-if="asset.warranty_expires"
                            class="mt-0.5 text-[10px] font-medium text-slate-400"
                        >
                            Hingga {{ asset.warranty_expires }}
                        </p>
                    </div>
                </div>

                <!-- License Expiration (if License) -->
                <div
                    v-if="isLicense && asset.expiration_date"
                    class="flex flex-col justify-between rounded-xl border border-slate-100 bg-slate-50/50 p-3.5"
                >
                    <span
                        class="flex items-center gap-1.5 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                    >
                        <Clock class="size-3 text-slate-400" />
                        Masa Berlaku Berakhir
                    </span>
                    <p class="mt-2 text-xs font-bold text-rose-700">
                        {{ asset.expiration_date }}
                    </p>
                </div>
            </div>
        </div>

        <!-- 4. CATATAN ADMINISTRATOR (IF ANY) -->
        <div
            v-if="asset.notes"
            class="rounded-2xl border border-amber-200/70 bg-gradient-to-br from-amber-50/70 to-amber-50/20 p-5 shadow-xs"
        >
            <div
                class="mb-2 flex items-center gap-2 text-[11px] font-black tracking-widest text-amber-800 uppercase"
            >
                <FileText class="size-4 text-amber-600" />
                <span>Catatan Administrator</span>
            </div>
            <p
                class="text-xs leading-relaxed whitespace-pre-line text-slate-700"
            >
                {{ asset.notes }}
            </p>
        </div>

        <!-- 5. CUSTOM FIELDS (IF ANY) -->
        <div
            v-if="asset.custom_fields && asset.custom_fields.length > 0"
            class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs"
        >
            <div
                class="mb-4 flex items-center justify-between border-b border-slate-100 pb-3"
            >
                <div class="flex items-center gap-2.5">
                    <div
                        class="flex size-7 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700"
                    >
                        <Tag class="size-4" />
                    </div>
                    <div>
                        <h4
                            class="text-xs font-black tracking-widest text-slate-800 uppercase"
                        >
                            Informasi Kustom Tambahan
                        </h4>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="field in asset.custom_fields"
                    :key="field.name"
                    class="flex flex-col justify-between rounded-xl border border-slate-100 bg-slate-50/50 p-3.5"
                >
                    <span
                        class="truncate text-[10px] font-black tracking-widest text-slate-400 uppercase"
                    >
                        {{ field.name }}
                    </span>
                    <p
                        class="mt-1.5 text-xs font-bold break-words text-slate-800"
                    >
                        {{ field.value || '—' }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
