<script setup lang="ts">
import {
    LucideTrash2 as Trash2,
    LucideSearch as Search,
    LucidePlusCircle as PlusCircle,
    LucideAlertCircle as AlertCircle,
    LucidePlus as Plus,
    LucideArchive as Archive,
    LucideShieldCheck as ShieldCheck,
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import type { SnipeAsset } from '@/composables/useSnipeDirectory';
import { LABELS } from '@/constants/labels';
import PeminjamanSearchableSelect from './PeminjamanSearchableSelect.vue';

interface ItemBarang {
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

interface ItemFieldErrors {
    nama?: string;
    type?: string;
    jumlah?: string;
    computer_id?: string;
}

interface FormErrors {
    items?: string;
}

const props = defineProps<{
    items: ItemBarang[];
    itemErrors: ItemFieldErrors[];
    formErrors: FormErrors;
    userAssignedAssets: Record<string, SnipeAsset[]>;
    allHardwareAssets: Record<string, SnipeAsset[]>;
    getItemReferenceLabel: (category?: string | null) => string;
    getStbAssetLabel: (item: ItemBarang) => string;
    addItem: () => void;
    removeItem: (index: number) => void;
    openItemPicker: (index: number) => void;
    ensureAssetsLoaded: (type: string) => Promise<void>;
    sectionTitle: string;
    sectionCopy: string;
    isReturnMode?: boolean;
    isLoading?: boolean;
    skippedItems?: Array<{
        nama: string;
        inventory_number?: string;
        serial_no?: string;
    }>;
}>();

const isAllSelected = computed({
    get: () =>
        props.items.length > 0 && props.items.every((item) => item.is_selected),
    set: (val: boolean) => {
        props.items.forEach((item) => {
            item.is_selected = val;
        });
    },
});
</script>

<template>
    <section
        class="animate-in space-y-4 duration-500 fade-in slide-in-from-bottom-2"
    >
        <!-- 1. TABLE CONTROL BAR -->
        <div class="flex items-center justify-between px-1">
            <div class="flex items-center gap-3">
                <div class="h-4 w-1 rounded-full bg-[#003628]" />
                <h3
                    class="text-[10px] font-black tracking-[0.2em] text-[#003628] uppercase"
                >
                    {{ sectionTitle }}
                </h3>
            </div>

            <div v-if="!props.isReturnMode" class="flex items-center gap-2">
                <button
                    type="button"
                    @click="addItem"
                    class="flex h-9 items-center gap-2 rounded-lg border border-slate-200 px-4 text-[10px] font-black tracking-widest text-slate-500 uppercase transition-all hover:bg-slate-50 hover:text-[#003628]"
                >
                    <PlusCircle class="size-3.5" />
                    <span>{{ LABELS.BUTTON.ADD_MANUAL }}</span>
                </button>
                <button
                    type="button"
                    @click="openItemPicker(-1)"
                    class="flex h-9 items-center gap-2 rounded-lg bg-[#003628] px-5 text-[10px] font-black tracking-widest text-white uppercase shadow-md transition-all hover:brightness-110 active:scale-95"
                >
                    <Plus class="size-3.5" />
                    <span>{{ LABELS.BUTTON.PICK_FROM_MASTER }}</span>
                </button>
            </div>
        </div>

        <!-- SKIPPED ITEMS NOTICE (return mode only) -->
        <div
            v-if="isReturnMode && skippedItems && skippedItems.length > 0"
            class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3"
        >
            <AlertCircle class="mt-0.5 size-4 shrink-0 text-amber-500" />
            <div class="min-w-0">
                <p
                    class="text-[11px] font-black tracking-widest text-amber-700 uppercase"
                >
                    {{ skippedItems.length }} item tidak ditampilkan
                </p>
                <p class="mt-1 text-[11px] leading-relaxed text-amber-600">
                    Item berikut sudah dikembalikan atau tidak berstatus
                    <span class="font-bold">Borrowed</span> di Snipe-IT:
                </p>
                <ul class="mt-1.5 space-y-0.5">
                    <li
                        v-for="(s, i) in skippedItems"
                        :key="i"
                        class="text-[11px] font-medium text-amber-700"
                    >
                        • {{ s.nama
                        }}<span
                            v-if="
                                s.inventory_number && s.inventory_number !== '-'
                            "
                        >
                            — {{ s.inventory_number }}</span
                        >
                    </li>
                </ul>
            </div>
        </div>

        <!-- 2. ERROR ALERT -->
        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="opacity-0 -translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
        >
            <div
                v-if="formErrors.items"
                class="flex items-center gap-3 rounded-xl border border-red-100 bg-red-50 p-4 text-red-600"
            >
                <AlertCircle class="h-4 w-4 shrink-0" />
                <p
                    class="text-[10px] leading-none font-black tracking-widest uppercase"
                >
                    {{ formErrors.items }}
                </p>
            </div>
        </Transition>

        <!-- 3. PREMIUM DYNAMIC TABLE -->
        <div
            class="relative overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
        >
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] border-collapse text-left">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/50">
                            <th
                                class="w-12 px-4 py-3 text-center text-[9px] font-black tracking-[0.15em] text-slate-400 uppercase"
                            >
                                #
                            </th>
                            <th
                                class="px-4 py-3 text-[9px] font-black tracking-[0.15em] text-slate-400 uppercase"
                            >
                                Asset Specification
                            </th>
                            <th
                                class="w-32 px-4 py-3 text-[9px] font-black tracking-[0.15em] text-slate-400 uppercase"
                            >
                                Category
                            </th>
                            <th
                                class="w-20 px-4 py-3 text-center text-[9px] font-black tracking-[0.15em] text-slate-400 uppercase"
                            >
                                Qty
                            </th>
                            <th
                                class="w-40 px-4 py-3 text-[9px] font-black tracking-[0.15em] text-slate-400 uppercase"
                            >
                                Condition
                            </th>
                            <th
                                class="w-48 px-4 py-3 text-[9px] font-black tracking-[0.15em] text-slate-400 uppercase"
                            >
                                Inventory Mapping
                            </th>
                            <th
                                class="w-12 px-4 py-3 text-center text-[9px] font-black tracking-[0.15em] text-slate-400 uppercase"
                            >
                                <div
                                    v-if="props.isReturnMode"
                                    class="flex items-center justify-center"
                                >
                                    <input
                                        type="checkbox"
                                        v-model="isAllSelected"
                                        class="h-4 w-4 rounded border-slate-300 text-[#003628] focus:ring-[#003628]"
                                    />
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="(item, index) in items"
                            :key="index"
                            class="group transition-all hover:bg-slate-50/50"
                            :class="{
                                'opacity-50':
                                    props.isReturnMode && !item.is_selected,
                            }"
                        >
                            <td class="px-4 py-4 text-center">
                                <span
                                    class="text-[10px] font-black text-slate-300 tabular-nums"
                                    >{{
                                        String(index + 1).padStart(2, '0')
                                    }}</span
                                >
                            </td>
                            <td class="space-y-1.5 px-4 py-4">
                                <input
                                    v-model="item.nama"
                                    :readonly="
                                        props.isReturnMode ||
                                        item.kategori === 'assets'
                                    "
                                    class="h-9 w-full rounded-lg border border-slate-200 px-3 text-[12px] font-bold text-slate-900 transition-all outline-none focus:border-[#003628]"
                                    :class="{
                                        'cursor-not-allowed bg-slate-50 text-slate-400 italic':
                                            props.isReturnMode ||
                                            item.kategori === 'assets',
                                    }"
                                    placeholder="Asset Name"
                                />
                                <div class="flex items-center gap-2">
                                    <input
                                        v-model="item.type"
                                        readonly
                                        class="h-7 flex-1 rounded-md border border-slate-100 bg-slate-50 px-2 text-[10px] font-bold text-slate-400 italic outline-none"
                                        placeholder="Model/Type"
                                    />
                                    <input
                                        v-model="item.serialNo"
                                        :readonly="props.isReturnMode"
                                        class="h-7 flex-1 rounded-md border border-slate-200 px-2 text-[10px] font-bold text-slate-600 outline-none focus:border-[#003628]"
                                        :class="{
                                            'bg-slate-50 text-slate-400 italic':
                                                props.isReturnMode,
                                        }"
                                        placeholder="Serial Number"
                                    />
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                <span
                                    class="inline-flex items-center rounded-md bg-slate-100 px-2 py-1 text-[9px] font-black tracking-widest text-slate-500 uppercase"
                                >
                                    {{ item.kategori }}
                                </span>
                            </td>
                            <td class="px-4 py-4 text-center">
                                <input
                                    v-model.number="item.jumlah"
                                    type="number"
                                    min="1"
                                    :readonly="
                                        props.isReturnMode ||
                                        item.kategori === 'assets'
                                    "
                                    class="h-9 w-14 rounded-lg border border-slate-200 text-center text-[12px] font-black text-slate-900 outline-none focus:border-[#003628]"
                                    :class="{
                                        'bg-slate-50 text-slate-400':
                                            props.isReturnMode ||
                                            item.kategori === 'assets',
                                    }"
                                />
                            </td>
                            <td class="px-4 py-4">
                                <div class="group/cond relative">
                                    <select
                                        v-model="item.condition"
                                        class="h-9 w-full appearance-none rounded-lg border border-slate-200 pr-3 pl-8 text-[11px] font-black tracking-widest text-slate-700 uppercase transition-all outline-none focus:border-[#003628]"
                                        :class="[
                                            item.condition === 'Good'
                                                ? 'border-emerald-100 bg-emerald-50/30 text-emerald-600'
                                                : 'border-red-100 bg-red-50/30 text-red-600',
                                        ]"
                                    >
                                        <option value="Good">Good</option>
                                        <option value="Broken">Broken</option>
                                        <option value="Missing">Missing</option>
                                    </select>
                                    <ShieldCheck
                                        class="absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 transition-colors"
                                        :class="
                                            item.condition === 'Good'
                                                ? 'text-emerald-500'
                                                : 'text-red-500'
                                        "
                                    />
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                <div class="group/aset flex items-center gap-2">
                                    <div
                                        class="flex h-9 flex-1 items-center rounded-lg border border-slate-200 bg-white px-3 font-mono text-slate-700 shadow-inner transition-all select-none"
                                        :class="{
                                            'cursor-not-allowed border-slate-100 bg-slate-50 text-slate-400 italic':
                                                props.isReturnMode ||
                                                item.kategori !== 'assets',
                                            'cursor-pointer hover:border-[#003628]':
                                                !props.isReturnMode &&
                                                item.kategori === 'assets',
                                        }"
                                        @click="
                                            !props.isReturnMode &&
                                            item.kategori === 'assets' &&
                                            openItemPicker(index)
                                        "
                                    >
                                        <span
                                            class="truncate text-[11px] font-bold"
                                        >
                                            {{
                                                item.kategori === 'assets'
                                                    ? item.inventory_number ||
                                                      '[MASTER]'
                                                    : ''
                                            }}
                                        </span>
                                    </div>
                                    <button
                                        v-if="
                                            !props.isReturnMode &&
                                            item.kategori === 'assets'
                                        "
                                        type="button"
                                        @click="openItemPicker(index)"
                                        class="p-1 text-slate-300 transition-all hover:text-[#003628]"
                                    >
                                        <Search class="size-3.5" />
                                    </button>
                                </div>
                            </td>
                            <td class="px-4 py-4 text-center">
                                <div
                                    v-if="props.isReturnMode"
                                    class="flex items-center justify-center"
                                >
                                    <input
                                        type="checkbox"
                                        v-model="item.is_selected"
                                        class="h-5 w-5 rounded border-slate-300 text-[#003628] focus:ring-[#003628]"
                                    />
                                </div>
                                <button
                                    v-else
                                    type="button"
                                    class="mx-auto flex size-8 items-center justify-center rounded-lg text-slate-300 transition-all hover:bg-red-50 hover:text-red-500"
                                    @click="props.removeItem(index)"
                                >
                                    <Trash2 class="size-4" />
                                </button>
                            </td>
                        </tr>

                        <!-- LOADING STATE -->
                        <tr v-if="props.isLoading">
                            <td colspan="7" class="py-12">
                                <div
                                    class="flex flex-col items-center justify-center gap-3"
                                >
                                    <div
                                        class="size-6 animate-spin rounded-full border-2 border-slate-200 border-t-[#003628]"
                                    />
                                    <p
                                        class="text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                    >
                                        {{ LABELS.STATUS.SCANNING_DIRECTORY }}
                                    </p>
                                </div>
                            </td>
                        </tr>

                        <!-- EMPTY STATE -->
                        <tr v-if="!props.isLoading && items.length === 0">
                            <td colspan="7" class="px-6 py-12 text-center">
                                <div
                                    class="flex flex-col items-center justify-center gap-3 text-slate-300"
                                >
                                    <Archive class="size-8" />
                                    <div class="space-y-1">
                                        <p
                                            class="text-[11px] font-black tracking-widest uppercase"
                                        >
                                            {{ LABELS.STATUS.NO_ASSETS_ASSIGNED }}
                                        </p>
                                        <p class="text-[10px] italic">
                                            {{ LABELS.STATUS.ADD_ITEMS_HELP }}
                                        </p>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 4. FOOTER INFO -->
        <div v-if="items.length > 0" class="flex items-center justify-end px-1">
            <p
                class="text-[9px] font-black tracking-widest text-slate-400 uppercase"
            >
                Total Manifest:
                <span class="text-slate-900 tabular-nums"
                    >{{ items.length }} Records</span
                >
            </p>
        </div>
    </section>
</template>

<style scoped>
input::-webkit-outer-spin-button,
input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
input[type='number'] {
    -moz-appearance: textfield;
}
</style>
