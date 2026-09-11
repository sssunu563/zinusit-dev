<script setup lang="ts">
/* eslint-disable vue/no-mutating-props */
import {
    ChevronDown,
    Check,
    Save,
    Plus,
    Loader2,
    AlertCircle,
} from 'lucide-vue-next';
import { ref } from 'vue';

interface FormErrors {
    asset_tag?: string;
    model_id?: string;
    image?: string;
    name?: string;
    seats?: string;
    category_id?: string;
    qty?: string;
    po_number?: string;
    purchase_date?: string;
    stock_document?: string;
    serial?: string;
    license_email?: string;
    purchase_cost?: string;
    order_number?: string;
    min_qty?: string;
    supplier_id?: string;
    manufacturer_id?: string;
    location_id?: string;
    model_number?: string;
    item_no?: string;
    expiration_date?: string;
    termination_date?: string;
    depreciation_id?: string;
}

interface FormState {
    company_id: string;
    asset_tag: string;
    serial: string;
    model_id: string;
    status_id: string;
    location_id: string;
    notes: string;
    requestable: boolean;
    warranty_months: string;
    expected_checkin: string;
    next_audit_date: string;
    byod: boolean;
    order_number: string;
    purchase_date: string;
    asset_eol_date: string;
    supplier_id: string;
    purchase_cost: string;
    name: string;
    category_id: string;
    manufacturer_id: string;
    qty: number;
    seats: number;
    po_number: string;
    license_name: string;
    license_email: string;
    reassignable: boolean;
    expiration_date: string;
    termination_date: string;
    min_qty: string | number;
    depreciation_id: string;
    maintained: boolean;
    model_number: string;
    item_no: string;
    image: File | null;
    stock_document: File | null;
    create_stb: boolean;
    stb_user_id: string;
    processing: boolean;
    errors: FormErrors;
    custom_fields: Record<string, string>;
}

interface OptionItem {
    id: number;
    name: string;
}

interface ModelItem {
    id: number;
    name: string;
    label: string;
    manufacturer_name: string;
    require_serial: boolean;
}

defineProps<{
    form: FormState;
    currentType: string;
    currentTypeLabel: string;
    models: ModelItem[];
    categories: OptionItem[];
    companies: OptionItem[];
    locations: OptionItem[];
    manufacturers: OptionItem[];
    suppliers: OptionItem[];
    depreciations?: OptionItem[];
    users: OptionItem[];
    isStockType: boolean;
    statuses: OptionItem[];
    selectedModel: ModelItem | null;
    customFields: any[];
    openAddModelModal: () => void;
    openAddCategoryModal: () => void;
    openAddManufacturerModal: () => void;
    openAddSupplierModal: () => void;
    openAddLocationModal: () => void;
    openAddStatusModal: () => void;
    handleStockDocumentChange: (event: Event) => void;
    handleImageChange: (event: Event) => void;
    serialError?: string;
    checkingSerial?: boolean;
    isEditMode: boolean;
    auditMode?: boolean;
    auditReadOnly?: boolean;
}>();

import AssetCustomFieldsSection from './AssetCustomFieldsSection.vue';

const optionalOpen = ref(false);
const orderOpen = ref(false);

const emit = defineEmits(['submit']);
</script>

<template>
    <div
        class="app-form-classic-naked"
        :class="{ 'audit-read-only': auditReadOnly }"
    >
        <!-- ─── MODERN HEADER ─── -->
        <div
            v-if="!auditMode"
            class="border-b border-slate-100 bg-white px-8 py-6"
        >
            <h2 class="text-2xl font-black tracking-tight text-slate-900">
                {{
                    auditMode
                        ? 'Stock Opname'
                        : (isEditMode ? 'Edit' : 'Create') +
                          ' ' +
                          currentTypeLabel
                }}
            </h2>
            <p
                class="mt-1 text-[10px] font-black tracking-[0.15em] text-slate-400 uppercase"
            >
                {{
                    auditMode
                        ? 'Match mengunci form. Pilih Mismatch untuk mengedit data sesuai kondisi lapangan.'
                        : 'Registration details for inventory management'
                }}
            </p>
        </div>

        <div class="py-2">
            <!-- BRANCH 1: HARDWARE (Original Layout Preserved) -->
            <template v-if="currentType === 'assets'">
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label">Company</label>
                    <div class="app-form-classic-input-group max-w-2xl">
                        <select
                            v-model="form.company_id"
                            class="app-select-shell app-select-compact"
                            :disabled="form.processing"
                        >
                            <option value="">Select Company</option>
                            <option
                                v-for="co in companies"
                                :key="co.id"
                                :value="String(co.id)"
                            >
                                {{ co.name }}
                            </option>
                        </select>
                        <p v-if="form.errors.company_id" class="app-form-error">
                            {{ form.errors.company_id }}
                        </p>
                    </div>
                </div>

                <!-- Asset Tag -->
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label"
                        >Asset Tag
                        <span class="app-form-label-required">*</span></label
                    >
                    <div class="app-form-classic-input-group max-w-2xl">
                        <div class="app-input-classic-wrapper">
                            <input
                                v-model="form.asset_tag"
                                type="text"
                                class="app-input-shell app-input-compact"
                                placeholder="AST-..."
                                :disabled="form.processing"
                            />
                            <div class="app-input-indicator" />
                        </div>
                        <p v-if="form.errors.asset_tag" class="app-form-error">
                            {{ form.errors.asset_tag }}
                        </p>
                    </div>
                </div>

                <!-- Serial -->
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label">Serial</label>
                    <div class="app-form-classic-input-group max-w-2xl">
                        <div class="relative">
                            <input
                                v-model="form.serial"
                                type="text"
                                class="app-input-shell app-input-compact"
                                :class="{
                                    'border-red-300 ring-1 ring-red-100':
                                        serialError,
                                }"
                                placeholder="Serial Number"
                                :disabled="form.processing"
                            />
                            <div
                                v-if="checkingSerial"
                                class="absolute top-1/2 right-3 -translate-y-1/2"
                            >
                                <Loader2
                                    class="size-3.5 animate-spin text-slate-400"
                                />
                            </div>
                        </div>
                        <div
                            v-if="serialError"
                            class="mt-1.5 flex animate-in items-center gap-1.5 text-red-500 duration-200 fade-in slide-in-from-top-1"
                        >
                            <AlertCircle class="size-3" />
                            <p
                                class="text-[10px] font-bold tracking-tight uppercase"
                            >
                                {{ serialError }}
                            </p>
                        </div>
                        <p v-if="form.errors.serial" class="app-form-error">
                            {{ form.errors.serial }}
                        </p>
                    </div>
                </div>

                <!-- Model -->
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label"
                        >Model
                        <span class="app-form-label-required">*</span></label
                    >
                    <div class="app-form-classic-input-group max-w-2xl">
                        <div class="app-input-classic-wrapper">
                            <select
                                v-model="form.model_id"
                                class="app-select-shell app-select-compact"
                                :disabled="form.processing"
                            >
                                <option value="">Select a Model</option>
                                <option
                                    v-for="model in models"
                                    :key="model.id"
                                    :value="String(model.id)"
                                >
                                    {{ model.label || model.name }}
                                </option>
                            </select>
                            <div class="app-input-indicator" />
                        </div>
                        <p v-if="form.errors.model_id" class="app-form-error">
                            {{ form.errors.model_id }}
                        </p>
                    </div>
                    <div class="hidden lg:block lg:w-32">
                        <button
                            type="button"
                            class="h-7 rounded-md border border-slate-200 bg-white px-2 text-[9px] font-black tracking-widest text-slate-500 uppercase shadow-sm transition-all hover:border-primary/30 hover:text-primary"
                            @click="openAddModelModal"
                        >
                            New
                        </button>
                    </div>
                </div>

                <!-- Status -->
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label"
                        >Status
                        <span class="app-form-label-required">*</span></label
                    >
                    <div class="app-form-classic-input-group max-w-2xl">
                        <div class="app-input-classic-wrapper">
                            <select
                                v-model="form.status_id"
                                class="app-select-shell app-select-compact"
                                :disabled="form.processing"
                            >
                                <option value="">Select Status</option>
                                <option
                                    v-for="st in statuses"
                                    :key="st.id"
                                    :value="String(st.id)"
                                >
                                    {{ st.name }}
                                </option>
                            </select>
                            <div class="app-input-indicator" />
                        </div>
                        <p v-if="form.errors.status_id" class="app-form-error">
                            {{ form.errors.status_id }}
                        </p>
                    </div>
                </div>

                <!-- Default Location -->
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label"
                        >Default Location</label
                    >
                    <div class="app-form-classic-input-group max-w-2xl">
                        <select
                            v-model="form.location_id"
                            class="app-select-shell app-select-compact"
                            :disabled="form.processing"
                        >
                            <option value="">Select a Location</option>
                            <option
                                v-for="loc in locations"
                                :key="loc.id"
                                :value="String(loc.id)"
                            >
                                {{ loc.name }}
                            </option>
                        </select>
                        <p
                            v-if="form.errors.location_id"
                            class="app-form-error"
                        >
                            {{ form.errors.location_id }}
                        </p>
                    </div>
                </div>

                <!-- Hardware Specifc: Requestable -->
                <div class="app-form-classic-row">
                    <div class="md:w-56" />
                    <label class="group flex cursor-pointer items-center gap-3">
                        <input
                            type="checkbox"
                            v-model="form.requestable"
                            class="size-4 rounded border-border bg-background accent-primary"
                            :disabled="form.processing"
                        />
                        <span
                            class="text-xs font-bold text-muted-foreground transition-colors group-hover:text-foreground"
                            >Requestable</span
                        >
                    </label>
                </div>
            </template>

            <!-- BRANCH 2: LICENSE -->
            <template v-else-if="currentType === 'license'">
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label"
                        >Software Name
                        <span class="app-form-label-required">*</span></label
                    >
                    <div class="app-form-classic-input-group max-w-2xl">
                        <div class="app-input-classic-wrapper">
                            <input
                                v-model="form.name"
                                type="text"
                                class="app-input-shell app-input-compact"
                                :disabled="form.processing"
                            />
                            <div class="app-input-indicator" />
                        </div>
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label"
                        >Category Name
                        <span class="app-form-label-required">*</span></label
                    >
                    <div class="app-form-classic-input-group max-w-2xl">
                        <div class="app-input-classic-wrapper">
                            <select
                                v-model="form.category_id"
                                class="app-select-shell app-select-compact"
                                :disabled="form.processing"
                            >
                                <option value="">Select a Category</option>
                                <option
                                    v-for="cat in categories"
                                    :key="cat.id"
                                    :value="String(cat.id)"
                                >
                                    {{ cat.name }}
                                </option>
                            </select>
                            <div class="app-input-indicator" />
                        </div>
                    </div>
                    <div class="hidden lg:block lg:w-32">
                        <button
                            type="button"
                            class="h-7 rounded-md border border-slate-200 bg-white px-2 text-[9px] font-black tracking-widest text-slate-500 uppercase shadow-sm transition-all hover:border-primary/30 hover:text-primary"
                            @click="openAddCategoryModal"
                        >
                            New
                        </button>
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label"
                        >Seats
                        <span class="app-form-label-required">*</span></label
                    >
                    <div class="app-form-classic-input-group max-w-2xl">
                        <div class="app-input-classic-wrapper max-w-[150px]">
                            <input
                                v-model="form.seats"
                                type="number"
                                class="app-input-shell app-input-compact"
                                min="1"
                                :disabled="form.processing"
                            />
                            <div class="app-input-indicator" />
                        </div>
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label">Min. QTY</label>
                    <div class="app-form-classic-input-group max-w-2xl">
                        <div class="flex items-center gap-3">
                            <input
                                v-model="form.min_qty"
                                type="number"
                                class="app-input-shell app-input-compact max-w-[150px]"
                                :disabled="form.processing"
                            />
                        </div>
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label">Product Key</label>
                    <div class="app-form-classic-input-group max-w-2xl">
                        <textarea
                            v-model="form.serial"
                            rows="4"
                            class="app-textarea-shell bg-white text-xs"
                            :disabled="form.processing"
                        />
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label">Company</label>
                    <div class="app-form-classic-input-group max-w-2xl">
                        <select
                            v-model="form.company_id"
                            class="app-select-shell app-select-compact"
                            :disabled="form.processing"
                        >
                            <option value="">Select Company</option>
                            <option
                                v-for="co in companies"
                                :key="co.id"
                                :value="String(co.id)"
                            >
                                {{ co.name }}
                            </option>
                        </select>
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label">Manufacturer</label>
                    <div class="app-form-classic-input-group max-w-2xl">
                        <select
                            v-model="form.manufacturer_id"
                            class="app-select-shell app-select-compact"
                            :disabled="form.processing"
                        >
                            <option value="">Select a Manufacturer</option>
                            <option
                                v-for="m in manufacturers"
                                :key="m.id"
                                :value="String(m.id)"
                            >
                                {{ m.name }}
                            </option>
                        </select>
                    </div>
                    <div class="hidden lg:block lg:w-32">
                        <button
                            type="button"
                            class="h-7 rounded-md border border-slate-200 bg-white px-2 text-[9px] font-black tracking-widest text-slate-500 uppercase shadow-sm transition-all hover:border-primary/30 hover:text-primary"
                            @click="openAddManufacturerModal"
                        >
                            New
                        </button>
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label">Licensed To</label>
                    <div class="app-form-classic-input-group max-w-2xl">
                        <input
                            v-model="form.license_name"
                            type="text"
                            class="app-input-shell app-input-compact"
                            :disabled="form.processing"
                        />
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label"
                        >Licensed to Email</label
                    >
                    <div class="app-form-classic-input-group max-w-2xl">
                        <input
                            v-model="form.license_email"
                            type="email"
                            class="app-input-shell app-input-compact"
                            :disabled="form.processing"
                        />
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <div class="md:w-56" />
                    <label class="flex cursor-pointer items-center gap-2">
                        <input
                            type="checkbox"
                            v-model="form.reassignable"
                            class="size-4 rounded border-border"
                            :disabled="form.processing"
                        />
                        <span class="text-xs font-bold text-slate-700"
                            >Reassignable</span
                        >
                    </label>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label">Supplier</label>
                    <div class="app-form-classic-input-group max-w-2xl">
                        <select
                            v-model="form.supplier_id"
                            class="app-select-shell app-select-compact"
                            :disabled="form.processing"
                        >
                            <option value="">Select a Supplier</option>
                            <option
                                v-for="s in suppliers"
                                :key="s.id"
                                :value="String(s.id)"
                            >
                                {{ s.name }}
                            </option>
                        </select>
                    </div>
                    <div class="hidden lg:block lg:w-32">
                        <button
                            type="button"
                            class="h-7 rounded-md border border-slate-200 bg-white px-2 text-[9px] font-black tracking-widest text-slate-500 uppercase shadow-sm transition-all hover:border-primary/30 hover:text-primary"
                            @click="openAddSupplierModal"
                        >
                            New
                        </button>
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label">Order Number</label>
                    <div class="app-form-classic-input-group max-w-2xl">
                        <input
                            v-model="form.order_number"
                            type="text"
                            class="app-input-shell app-input-compact"
                            :disabled="form.processing"
                        />
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label">Purchase Cost</label>
                    <div
                        class="app-form-classic-input-group flex max-w-2xl items-center"
                    >
                        <input
                            v-model="form.purchase_cost"
                            type="number"
                            step="0.01"
                            class="app-input-shell app-input-compact !rounded-r-none"
                            :disabled="form.processing"
                        />
                        <span
                            class="flex h-[38px] items-center rounded-r-lg border border-l-0 border-slate-200 bg-slate-50 px-4 text-xs font-bold text-slate-500"
                            >USD</span
                        >
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label">Purchase Date</label>
                    <div class="app-form-classic-input-group max-w-2xl">
                        <input
                            v-model="form.purchase_date"
                            type="date"
                            class="app-input-shell app-input-compact"
                            :disabled="form.processing"
                        />
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label"
                        >Expiration Date</label
                    >
                    <div class="app-form-classic-input-group max-w-2xl">
                        <input
                            v-model="form.expiration_date"
                            type="date"
                            class="app-input-shell app-input-compact"
                            :disabled="form.processing"
                        />
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label"
                        >Termination Date</label
                    >
                    <div class="app-form-classic-input-group max-w-2xl">
                        <input
                            v-model="form.termination_date"
                            type="date"
                            class="app-input-shell app-input-compact"
                            :disabled="form.processing"
                        />
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label"
                        >Purchase Order Number</label
                    >
                    <div class="app-form-classic-input-group max-w-2xl">
                        <input
                            v-model="form.po_number"
                            type="text"
                            class="app-input-shell app-input-compact"
                            :disabled="form.processing"
                        />
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label">Depreciation</label>
                    <div class="app-form-classic-input-group max-w-2xl">
                        <select
                            v-model="form.depreciation_id"
                            class="app-select-shell app-select-compact"
                            :disabled="form.processing"
                        >
                            <option value="">Do Not Depreciate</option>
                            <option
                                v-for="d in depreciations"
                                :key="d.id"
                                :value="String(d.id)"
                            >
                                {{ d.name }}
                            </option>
                        </select>
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <div class="md:w-56" />
                    <label class="flex cursor-pointer items-center gap-2">
                        <input
                            type="checkbox"
                            v-model="form.maintained"
                            class="size-4 rounded border-border"
                            :disabled="form.processing"
                        />
                        <span class="text-xs font-bold text-slate-700"
                            >Maintained</span
                        >
                    </label>
                </div>
            </template>

            <!-- BRANCH 3: ACCESSORIES -->
            <template v-else-if="currentType === 'accessories'">
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label">Company</label>
                    <div class="app-form-classic-input-group max-w-2xl">
                        <select
                            v-model="form.company_id"
                            class="app-select-shell app-select-compact"
                            :disabled="form.processing"
                        >
                            <option value="">Select Company</option>
                            <option
                                v-for="co in companies"
                                :key="co.id"
                                :value="String(co.id)"
                            >
                                {{ co.name }}
                            </option>
                        </select>
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label"
                        >Accessory Name
                        <span class="app-form-label-required">*</span></label
                    >
                    <div class="app-form-classic-input-group max-w-2xl">
                        <div class="app-input-classic-wrapper">
                            <input
                                v-model="form.name"
                                type="text"
                                class="app-input-shell app-input-compact"
                                :disabled="form.processing"
                            />
                            <div class="app-input-indicator" />
                        </div>
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label"
                        >Category
                        <span class="app-form-label-required">*</span></label
                    >
                    <div class="app-form-classic-input-group max-w-2xl">
                        <div class="app-input-classic-wrapper">
                            <select
                                v-model="form.category_id"
                                class="app-select-shell app-select-compact"
                                :disabled="form.processing"
                            >
                                <option value="">Select a Category</option>
                                <option
                                    v-for="cat in categories"
                                    :key="cat.id"
                                    :value="String(cat.id)"
                                >
                                    {{ cat.name }}
                                </option>
                            </select>
                            <div class="app-input-indicator" />
                        </div>
                    </div>
                    <div class="hidden lg:block lg:w-32">
                        <button
                            type="button"
                            class="h-7 rounded-md border border-slate-200 bg-white px-2 text-[9px] font-black tracking-widest text-slate-500 uppercase shadow-sm transition-all hover:border-primary/30 hover:text-primary"
                            @click="openAddCategoryModal"
                        >
                            New
                        </button>
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label">Supplier</label>
                    <div class="app-form-classic-input-group max-w-2xl">
                        <select
                            v-model="form.supplier_id"
                            class="app-select-shell app-select-compact"
                            :disabled="form.processing"
                        >
                            <option value="">Select a Supplier</option>
                            <option
                                v-for="s in suppliers"
                                :key="s.id"
                                :value="String(s.id)"
                            >
                                {{ s.name }}
                            </option>
                        </select>
                    </div>
                    <div class="hidden lg:block lg:w-32">
                        <button
                            type="button"
                            class="h-7 rounded-md border border-slate-200 bg-white px-2 text-[9px] font-black tracking-widest text-slate-500 uppercase shadow-sm transition-all hover:border-primary/30 hover:text-primary"
                            @click="openAddSupplierModal"
                        >
                            New
                        </button>
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label">Manufacturer</label>
                    <div class="app-form-classic-input-group max-w-2xl">
                        <select
                            v-model="form.manufacturer_id"
                            class="app-select-shell app-select-compact"
                            :disabled="form.processing"
                        >
                            <option value="">Select a Manufacturer</option>
                            <option
                                v-for="m in manufacturers"
                                :key="m.id"
                                :value="String(m.id)"
                            >
                                {{ m.name }}
                            </option>
                        </select>
                    </div>
                    <div class="hidden lg:block lg:w-32">
                        <button
                            type="button"
                            class="h-7 rounded-md border border-slate-200 bg-white px-2 text-[9px] font-black tracking-widest text-slate-500 uppercase shadow-sm transition-all hover:border-primary/30 hover:text-primary"
                            @click="openAddManufacturerModal"
                        >
                            New
                        </button>
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label">Location</label>
                    <div class="app-form-classic-input-group max-w-2xl">
                        <select
                            v-model="form.location_id"
                            class="app-select-shell app-select-compact"
                            :disabled="form.processing"
                        >
                            <option value="">Select a Location</option>
                            <option
                                v-for="loc in locations"
                                :key="loc.id"
                                :value="String(loc.id)"
                            >
                                {{ loc.name }}
                            </option>
                        </select>
                    </div>
                    <div class="hidden lg:block lg:w-32">
                        <button
                            type="button"
                            class="h-7 rounded-md border border-slate-200 bg-white px-2 text-[9px] font-black tracking-widest text-slate-500 uppercase shadow-sm transition-all hover:border-primary/30 hover:text-primary"
                            @click="openAddLocationModal"
                        >
                            New
                        </button>
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label">Model No.</label>
                    <div class="app-form-classic-input-group max-w-2xl">
                        <input
                            v-model="form.model_number"
                            type="text"
                            class="app-input-shell app-input-compact"
                            :disabled="form.processing"
                        />
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label">Order Number</label>
                    <div class="app-form-classic-input-group max-w-2xl">
                        <input
                            v-model="form.order_number"
                            type="text"
                            class="app-input-shell app-input-compact"
                            :disabled="form.processing"
                        />
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label">Purchase Date</label>
                    <div class="app-form-classic-input-group max-w-2xl">
                        <input
                            v-model="form.purchase_date"
                            type="date"
                            class="app-input-shell app-input-compact"
                            :disabled="form.processing"
                        />
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label">Unit Cost</label>
                    <div
                        class="app-form-classic-input-group flex max-w-2xl items-center"
                    >
                        <input
                            v-model="form.purchase_cost"
                            type="number"
                            step="0.01"
                            class="app-input-shell app-input-compact !rounded-r-none"
                            :disabled="form.processing"
                        />
                        <span
                            class="flex h-[38px] items-center rounded-r-lg border border-l-0 border-slate-200 bg-slate-50 px-4 text-xs font-bold text-slate-500"
                            >USD</span
                        >
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label"
                        >Quantity
                        <span class="app-form-label-required">*</span></label
                    >
                    <div class="app-form-classic-input-group max-w-2xl">
                        <div class="app-input-classic-wrapper max-w-[150px]">
                            <input
                                v-model="form.qty"
                                type="number"
                                class="app-input-shell app-input-compact"
                                min="1"
                                :disabled="form.processing"
                            />
                            <div class="app-input-indicator" />
                        </div>
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label">Min. QTY</label>
                    <div class="app-form-classic-input-group max-w-2xl">
                        <input
                            v-model="form.min_qty"
                            type="number"
                            class="app-input-shell app-input-compact max-w-[150px]"
                            :disabled="form.processing"
                        />
                    </div>
                </div>
            </template>

            <!-- BRANCH 4: CONSUMABLE -->
            <template v-else-if="currentType === 'consumable'">
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label">Company</label>
                    <div class="app-form-classic-input-group max-w-2xl">
                        <select
                            v-model="form.company_id"
                            class="app-select-shell app-select-compact"
                            :disabled="form.processing"
                        >
                            <option value="">Select Company</option>
                            <option
                                v-for="co in companies"
                                :key="co.id"
                                :value="String(co.id)"
                            >
                                {{ co.name }}
                            </option>
                        </select>
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label"
                        >Name
                        <span class="app-form-label-required">*</span></label
                    >
                    <div class="app-form-classic-input-group max-w-2xl">
                        <div class="app-input-classic-wrapper">
                            <input
                                v-model="form.name"
                                type="text"
                                class="app-input-shell app-input-compact"
                                :disabled="form.processing"
                            />
                            <div class="app-input-indicator" />
                        </div>
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label"
                        >Category
                        <span class="app-form-label-required">*</span></label
                    >
                    <div class="app-form-classic-input-group max-w-2xl">
                        <div class="app-input-classic-wrapper">
                            <select
                                v-model="form.category_id"
                                class="app-select-shell app-select-compact"
                                :disabled="form.processing"
                            >
                                <option value="">Select a Category</option>
                                <option
                                    v-for="cat in categories"
                                    :key="cat.id"
                                    :value="String(cat.id)"
                                >
                                    {{ cat.name }}
                                </option>
                            </select>
                            <div class="app-input-indicator" />
                        </div>
                    </div>
                    <div class="hidden lg:block lg:w-32">
                        <button
                            type="button"
                            class="h-7 rounded-md border border-slate-200 bg-white px-2 text-[9px] font-black tracking-widest text-slate-500 uppercase shadow-sm transition-all hover:border-primary/30 hover:text-primary"
                            @click="openAddCategoryModal"
                        >
                            New
                        </button>
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label"
                        >Quantity
                        <span class="app-form-label-required">*</span></label
                    >
                    <div class="app-form-classic-input-group max-w-2xl">
                        <div class="app-input-classic-wrapper max-w-[150px]">
                            <input
                                v-model="form.qty"
                                type="number"
                                class="app-input-shell app-input-compact"
                                min="1"
                                :disabled="form.processing"
                            />
                            <div class="app-input-indicator" />
                        </div>
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label">Min. QTY</label>
                    <div class="app-form-classic-input-group max-w-2xl">
                        <input
                            v-model="form.min_qty"
                            type="number"
                            class="app-input-shell app-input-compact max-w-[150px]"
                            :disabled="form.processing"
                        />
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label">Supplier</label>
                    <div class="app-form-classic-input-group max-w-2xl">
                        <select
                            v-model="form.supplier_id"
                            class="app-select-shell app-select-compact"
                            :disabled="form.processing"
                        >
                            <option value="">Select a Supplier</option>
                            <option
                                v-for="s in suppliers"
                                :key="s.id"
                                :value="String(s.id)"
                            >
                                {{ s.name }}
                            </option>
                        </select>
                    </div>
                    <div class="hidden lg:block lg:w-32">
                        <button
                            type="button"
                            class="h-7 rounded-md border border-slate-200 bg-white px-2 text-[9px] font-black tracking-widest text-slate-500 uppercase shadow-sm transition-all hover:border-primary/30 hover:text-primary"
                            @click="openAddSupplierModal"
                        >
                            New
                        </button>
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label">Manufacturer</label>
                    <div class="app-form-classic-input-group max-w-2xl">
                        <select
                            v-model="form.manufacturer_id"
                            class="app-select-shell app-select-compact"
                            :disabled="form.processing"
                        >
                            <option value="">Select a Manufacturer</option>
                            <option
                                v-for="m in manufacturers"
                                :key="m.id"
                                :value="String(m.id)"
                            >
                                {{ m.name }}
                            </option>
                        </select>
                    </div>
                    <div class="hidden lg:block lg:w-32">
                        <button
                            type="button"
                            class="h-7 rounded-md border border-slate-200 bg-white px-2 text-[9px] font-black tracking-widest text-slate-500 uppercase shadow-sm transition-all hover:border-primary/30 hover:text-primary"
                            @click="openAddManufacturerModal"
                        >
                            New
                        </button>
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label">Location</label>
                    <div class="app-form-classic-input-group max-w-2xl">
                        <select
                            v-model="form.location_id"
                            class="app-select-shell app-select-compact"
                            :disabled="form.processing"
                        >
                            <option value="">Select a Location</option>
                            <option
                                v-for="loc in locations"
                                :key="loc.id"
                                :value="String(loc.id)"
                            >
                                {{ loc.name }}
                            </option>
                        </select>
                    </div>
                    <div class="hidden lg:block lg:w-32">
                        <button
                            type="button"
                            class="h-7 rounded-md border border-slate-200 bg-white px-2 text-[9px] font-black tracking-widest text-slate-500 uppercase shadow-sm transition-all hover:border-primary/30 hover:text-primary"
                            @click="openAddLocationModal"
                        >
                            New
                        </button>
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label">Model No.</label>
                    <div class="app-form-classic-input-group max-w-2xl">
                        <input
                            v-model="form.model_number"
                            type="text"
                            class="app-input-shell app-input-compact"
                            :disabled="form.processing"
                        />
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label">Item No.</label>
                    <div class="app-form-classic-input-group max-w-2xl">
                        <input
                            v-model="form.item_no"
                            type="text"
                            class="app-input-shell app-input-compact"
                            :disabled="form.processing"
                        />
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label">Order Number</label>
                    <div class="app-form-classic-input-group max-w-2xl">
                        <input
                            v-model="form.order_number"
                            type="text"
                            class="app-input-shell app-input-compact"
                            :disabled="form.processing"
                        />
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label">Purchase Date</label>
                    <div class="app-form-classic-input-group max-w-2xl">
                        <input
                            v-model="form.purchase_date"
                            type="date"
                            class="app-input-shell app-input-compact"
                            :disabled="form.processing"
                        />
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label">Unit Cost</label>
                    <div
                        class="app-form-classic-input-group flex max-w-2xl items-center"
                    >
                        <input
                            v-model="form.purchase_cost"
                            type="number"
                            step="0.01"
                            class="app-input-shell app-input-compact !rounded-r-none"
                            :disabled="form.processing"
                        />
                        <span
                            class="flex h-[38px] items-center rounded-r-lg border border-l-0 border-slate-200 bg-slate-50 px-4 text-xs font-bold text-slate-500"
                            >USD</span
                        >
                    </div>
                </div>
            </template>

            <!-- BRANCH 5: COMPONENT -->
            <template v-else-if="currentType === 'component'">
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label"
                        >Component Name
                        <span class="app-form-label-required">*</span></label
                    >
                    <div class="app-form-classic-input-group max-w-2xl">
                        <div class="app-input-classic-wrapper">
                            <input
                                v-model="form.name"
                                type="text"
                                class="app-input-shell app-input-compact"
                                :disabled="form.processing"
                            />
                            <div class="app-input-indicator" />
                        </div>
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label"
                        >Category
                        <span class="app-form-label-required">*</span></label
                    >
                    <div class="app-form-classic-input-group max-w-2xl">
                        <div class="app-input-classic-wrapper">
                            <select
                                v-model="form.category_id"
                                class="app-select-shell app-select-compact"
                                :disabled="form.processing"
                            >
                                <option value="">Select a Category</option>
                                <option
                                    v-for="cat in categories"
                                    :key="cat.id"
                                    :value="String(cat.id)"
                                >
                                    {{ cat.name }}
                                </option>
                            </select>
                            <div class="app-input-indicator" />
                        </div>
                    </div>
                    <div class="hidden lg:block lg:w-32">
                        <button
                            type="button"
                            class="h-7 rounded-md border border-slate-200 bg-white px-2 text-[9px] font-black tracking-widest text-slate-500 uppercase shadow-sm transition-all hover:border-primary/30 hover:text-primary"
                            @click="openAddCategoryModal"
                        >
                            New
                        </button>
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label"
                        >Quantity
                        <span class="app-form-label-required">*</span></label
                    >
                    <div class="app-form-classic-input-group max-w-2xl">
                        <div class="app-input-classic-wrapper max-w-[150px]">
                            <input
                                v-model="form.qty"
                                type="number"
                                class="app-input-shell app-input-compact"
                                min="1"
                                :disabled="form.processing"
                            />
                            <div class="app-input-indicator" />
                        </div>
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label">Min. QTY</label>
                    <div class="app-form-classic-input-group max-w-2xl">
                        <input
                            v-model="form.min_qty"
                            type="number"
                            class="app-input-shell app-input-compact max-w-[150px]"
                            :disabled="form.processing"
                        />
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label">Serial</label>
                    <div class="app-form-classic-input-group max-w-2xl">
                        <input
                            v-model="form.serial"
                            type="text"
                            class="app-input-shell app-input-compact"
                            :disabled="form.processing"
                        />
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label">Manufacturer</label>
                    <div class="app-form-classic-input-group max-w-2xl">
                        <select
                            v-model="form.manufacturer_id"
                            class="app-select-shell app-select-compact"
                            :disabled="form.processing"
                        >
                            <option value="">Select a Manufacturer</option>
                            <option
                                v-for="m in manufacturers"
                                :key="m.id"
                                :value="String(m.id)"
                            >
                                {{ m.name }}
                            </option>
                        </select>
                    </div>
                    <div class="hidden lg:block lg:w-32">
                        <button
                            type="button"
                            class="h-7 rounded-md border border-slate-200 bg-white px-2 text-[9px] font-black tracking-widest text-slate-500 uppercase shadow-sm transition-all hover:border-primary/30 hover:text-primary"
                            @click="openAddManufacturerModal"
                        >
                            New
                        </button>
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label">Model No.</label>
                    <div class="app-form-classic-input-group max-w-2xl">
                        <input
                            v-model="form.model_number"
                            type="text"
                            class="app-input-shell app-input-compact"
                            :disabled="form.processing"
                        />
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label">Company</label>
                    <div class="app-form-classic-input-group max-w-2xl">
                        <select
                            v-model="form.company_id"
                            class="app-select-shell app-select-compact"
                            :disabled="form.processing"
                        >
                            <option value="">Select Company</option>
                            <option
                                v-for="co in companies"
                                :key="co.id"
                                :value="String(co.id)"
                            >
                                {{ co.name }}
                            </option>
                        </select>
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label">Location</label>
                    <div class="app-form-classic-input-group max-w-2xl">
                        <select
                            v-model="form.location_id"
                            class="app-select-shell app-select-compact"
                            :disabled="form.processing"
                        >
                            <option value="">Select a Location</option>
                            <option
                                v-for="loc in locations"
                                :key="loc.id"
                                :value="String(loc.id)"
                            >
                                {{ loc.name }}
                            </option>
                        </select>
                    </div>
                    <div class="hidden lg:block lg:w-32">
                        <button
                            type="button"
                            class="h-7 rounded-md border border-slate-200 bg-white px-2 text-[9px] font-black tracking-widest text-slate-500 uppercase shadow-sm transition-all hover:border-primary/30 hover:text-primary"
                            @click="openAddLocationModal"
                        >
                            New
                        </button>
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label">Supplier</label>
                    <div class="app-form-classic-input-group max-w-2xl">
                        <select
                            v-model="form.supplier_id"
                            class="app-select-shell app-select-compact"
                            :disabled="form.processing"
                        >
                            <option value="">Select a Supplier</option>
                            <option
                                v-for="s in suppliers"
                                :key="s.id"
                                :value="String(s.id)"
                            >
                                {{ s.name }}
                            </option>
                        </select>
                    </div>
                    <div class="hidden lg:block lg:w-32">
                        <button
                            type="button"
                            class="h-7 rounded-md border border-slate-200 bg-white px-2 text-[9px] font-black tracking-widest text-slate-500 uppercase shadow-sm transition-all hover:border-primary/30 hover:text-primary"
                            @click="openAddSupplierModal"
                        >
                            New
                        </button>
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label">Order Number</label>
                    <div class="app-form-classic-input-group max-w-2xl">
                        <input
                            v-model="form.order_number"
                            type="text"
                            class="app-input-shell app-input-compact"
                            :disabled="form.processing"
                        />
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label">Purchase Date</label>
                    <div class="app-form-classic-input-group max-w-2xl">
                        <input
                            v-model="form.purchase_date"
                            type="date"
                            class="app-input-shell app-input-compact"
                            :disabled="form.processing"
                        />
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label">Unit Cost</label>
                    <div
                        class="app-form-classic-input-group flex max-w-2xl items-center"
                    >
                        <input
                            v-model="form.purchase_cost"
                            type="number"
                            step="0.01"
                            class="app-input-shell app-input-compact !rounded-r-none"
                            :disabled="form.processing"
                        />
                        <span
                            class="flex h-[38px] items-center rounded-r-lg border border-l-0 border-slate-200 bg-slate-50 px-4 text-xs font-bold text-slate-500"
                            >USD</span
                        >
                    </div>
                </div>
            </template>

            <!-- COMMON: Notes -->
            <div class="app-form-classic-row">
                <label class="app-form-classic-label">Notes</label>
                <div class="app-form-classic-input-group max-w-2xl">
                    <textarea
                        v-model="form.notes"
                        rows="3"
                        class="app-textarea-shell bg-white text-xs"
                        :disabled="form.processing"
                    />
                </div>
            </div>

            <!-- COMMON: Upload Image -->
            <div class="app-form-classic-row" v-if="currentType !== 'license'">
                <label class="app-form-classic-label">Upload Image</label>
                <div class="app-form-classic-input-group max-w-2xl">
                    <div class="flex items-center gap-4">
                        <label
                            class="inline-flex h-8 cursor-pointer items-center rounded-lg bg-primary px-4 text-[10px] font-black tracking-widest text-white uppercase shadow-sm transition-all hover:bg-primary/90"
                        >
                            Select File...
                            <input
                                type="file"
                                class="hidden"
                                @change="handleImageChange"
                                accept="image/*"
                            />
                        </label>
                        <span
                            v-if="form.image"
                            class="text-[10px] font-black tracking-tighter text-primary uppercase"
                            >✓ File Ready</span
                        >
                    </div>
                    <p class="mt-1.5 text-[11px] text-muted-foreground">
                        Accepted filetypes are jpg, webp, png, gif, svg, and
                        avif. Max size 2M.
                    </p>
                </div>
            </div>
        </div>

        <template v-if="currentType === 'assets'">
            <button
                type="button"
                class="app-form-classic-accordion border-t border-slate-50"
                @click="optionalOpen = !optionalOpen"
            >
                <ChevronDown
                    class="size-4 text-slate-300 transition-transform"
                    :class="{ '-rotate-90': !optionalOpen }"
                />
                <span
                    class="text-[10px] font-black tracking-[0.2em] text-slate-400 uppercase"
                    >Optional Information</span
                >
            </button>
            <div v-show="optionalOpen" class="py-2">
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label">Asset Name</label>
                    <div class="app-form-classic-input-group max-w-2xl">
                        <input
                            v-model="form.name"
                            type="text"
                            class="app-input-shell app-input-compact"
                            :disabled="form.processing"
                        />
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label">Warranty</label>
                    <div
                        class="app-form-classic-input-group flex max-w-[200px] items-center gap-0 overflow-hidden rounded-lg"
                    >
                        <input
                            v-model="form.warranty_months"
                            type="number"
                            class="app-input-shell app-input-compact rounded-r-none border-r-0"
                            :disabled="form.processing"
                        />
                        <span
                            class="flex h-8 items-center border border-l-0 border-border bg-muted px-3 text-[11px] font-bold text-muted-foreground"
                            >months</span
                        >
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label"
                        >Expected Checkin</label
                    >
                    <div class="app-form-classic-input-group max-w-2xl">
                        <input
                            v-model="form.expected_checkin"
                            type="date"
                            class="app-input-shell app-input-compact"
                            :disabled="form.processing"
                        />
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label"
                        >Next Audit Date</label
                    >
                    <div class="app-form-classic-input-group max-w-2xl">
                        <input
                            v-model="form.next_audit_date"
                            type="date"
                            class="app-input-shell app-input-compact"
                            :disabled="form.processing"
                        />
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <div class="md:w-56" />
                    <label class="group flex cursor-pointer items-center gap-3">
                        <input
                            type="checkbox"
                            v-model="form.byod"
                            class="size-4 rounded border-border bg-background accent-primary"
                            :disabled="form.processing"
                        />
                        <span
                            class="text-xs font-bold text-muted-foreground transition-colors group-hover:text-foreground"
                            >BYOD (Bring Your Own Device)</span
                        >
                    </label>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label">Asset EOL Date</label>
                    <div class="app-form-classic-input-group max-w-2xl">
                        <input
                            v-model="form.asset_eol_date"
                            type="date"
                            class="app-input-shell app-input-compact"
                            :disabled="form.processing"
                        />
                    </div>
                </div>
            </div>

            <!-- HARDWARE ORDER INFO -->
            <button
                type="button"
                class="app-form-classic-accordion border-t border-slate-50"
                @click="orderOpen = !orderOpen"
            >
                <ChevronDown
                    class="size-4 text-slate-300 transition-transform"
                    :class="{ '-rotate-90': !orderOpen }"
                />
                <span
                    class="text-[10px] font-black tracking-[0.2em] text-slate-400 uppercase"
                    >Order Information</span
                >
            </button>
            <div v-show="orderOpen" class="py-2">
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label">Supplier</label>
                    <div class="app-form-classic-input-group max-w-2xl">
                        <select
                            v-model="form.supplier_id"
                            class="app-select-shell app-select-compact"
                            :disabled="form.processing"
                        >
                            <option value="">Select Supplier</option>
                            <option
                                v-for="s in suppliers"
                                :key="s.id"
                                :value="String(s.id)"
                            >
                                {{ s.name }}
                            </option>
                        </select>
                    </div>
                    <div class="hidden lg:block lg:w-32">
                        <button
                            type="button"
                            class="h-7 rounded-md border border-slate-200 bg-white px-2 text-[9px] font-black tracking-widest text-slate-500 uppercase shadow-sm transition-all hover:border-primary/30 hover:text-primary"
                            @click="openAddSupplierModal"
                        >
                            New
                        </button>
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label">Order Number</label>
                    <div class="app-form-classic-input-group max-w-2xl">
                        <input
                            v-model="form.order_number"
                            type="text"
                            class="app-input-shell app-input-compact"
                            :disabled="form.processing"
                        />
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label">Purchase Cost</label>
                    <div class="app-form-classic-input-group max-w-2xl">
                        <input
                            v-model="form.purchase_cost"
                            type="number"
                            step="0.01"
                            class="app-input-shell app-input-compact"
                            :disabled="form.processing"
                        />
                    </div>
                </div>
                <div class="app-form-classic-row">
                    <label class="app-form-classic-label">Purchase Date</label>
                    <div class="app-form-classic-input-group max-w-2xl">
                        <input
                            v-model="form.purchase_date"
                            type="date"
                            class="app-input-shell app-input-compact"
                            :disabled="form.processing"
                        />
                    </div>
                </div>
            </div>
        </template>

        <AssetCustomFieldsSection
            v-if="customFields.length > 0 && currentType === 'assets'"
            :fields="customFields"
            :values="form.custom_fields"
            @update:values="form.custom_fields = $event"
        />
    </div>
</template>

<style>
.audit-read-only input,
.audit-read-only select,
.audit-read-only textarea {
    pointer-events: none;
    cursor: not-allowed;
    background-color: rgb(248 250 252);
    color: rgb(100 116 139);
}
</style>
