<script setup lang="ts">
import { 
    Trash2, 
    Search, 
    PlusCircle, 
    AlertCircle, 
    Plus,
    Archive
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import type { SnipeAsset } from '@/composables/useSnipeDirectory';
import { LABELS } from '@/constants/labels';
import StbLicenseAssetPickerModal from './StbLicenseAssetPickerModal.vue';
import StbSearchableSelect from './StbSearchableSelect.vue';

interface ItemBarang {
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
}>();

const pickerModalOpen = ref(false);
const activeItemIndex = ref<number | null>(null);
const pickerCategoryValue = ref('assets');

const openAssetPicker = (index: number) => {
    const item = props.items[index];
    if (!item) return;

    activeItemIndex.value = index;
    
    // For all items (License, Component, Accessories, Consumable, etc), 
    // we want to pick a HARDWARE asset to attach it to.
    const pickerCategory = 'assets';

    pickerCategoryValue.value = pickerCategory;
    
    // Ensure the relevant category is loaded in the directory
    void props.ensureAssetsLoaded(pickerCategory);
    
    pickerModalOpen.value = true;
};

const handleAssetSelect = (selection: { value: string; id: number | null }) => {
    if (activeItemIndex.value !== null) {
        props.items[activeItemIndex.value].inventory_number = selection.value;
        props.items[activeItemIndex.value].computer_id = selection.id;
    }
};

const activeCategory = computed(() => {
    return pickerCategoryValue.value;
});

const currentUserAssets = computed(() => {
    return props.userAssignedAssets[activeCategory.value] || [];
});

const currentAllAssets = computed(() => {
    return props.allHardwareAssets[activeCategory.value] || [];
});

const isAllSelected = computed({
    get: () => props.items.length > 0 && props.items.every(item => item.is_selected),
    set: (val: boolean) => {
        props.items.forEach(item => {
            item.is_selected = val;
        });
    }
});
</script>

<template>
<section class="space-y-4 animate-in fade-in slide-in-from-bottom-2 duration-500">
    <!-- 1. TABLE CONTROL BAR -->
    <div class="flex items-center justify-between px-1">
        <div class="flex items-center gap-3">
            <div class="h-4 w-1 rounded-full bg-[#003628]" />
            <h3 class="text-[10px] font-black uppercase tracking-[0.2em] text-[#003628]">Asset Distribution List</h3>
        </div>
        
        <div v-if="!props.isReturnMode" class="flex items-center gap-2">
            <button
                type="button"
                @click="addItem"
                class="h-9 px-4 rounded-lg border border-slate-200 text-[10px] font-black uppercase tracking-widest text-slate-500 hover:bg-slate-50 hover:text-[#003628] transition-all flex items-center gap-2"
            >
                <PlusCircle class="size-3.5" />
                <span>{{ LABELS.BUTTON.ADD_MANUAL }}</span>
            </button>
            <button
                type="button"
                @click="openItemPicker(-1)"
                class="h-9 px-5 rounded-lg bg-[#003628] text-white text-[10px] font-black uppercase tracking-widest shadow-md hover:brightness-110 transition-all active:scale-95 flex items-center gap-2"
            >
                <Plus class="size-3.5" />
                <span>{{ LABELS.BUTTON.PICK_FROM_MASTER }}</span>
            </button>
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
            class="flex items-center gap-3 p-4 bg-red-50 border border-red-100 rounded-xl text-red-600"
        >
            <AlertCircle class="h-4 w-4 shrink-0" />
            <p class="text-[10px] font-black uppercase tracking-widest leading-none">{{ formErrors.items }}</p>
        </div>
    </Transition>

    <!-- 2.5 COMPONENT ASSIGNMENT HELPER -->
    <div class="flex items-start gap-3 p-3 bg-blue-50 border border-blue-100 rounded-lg text-blue-700">
        <AlertCircle class="h-4 w-4 shrink-0 mt-0.5 flex-none" />
        <div class="space-y-1">
            <p class="text-[10px] font-black uppercase tracking-widest leading-tight">Komponen harus ditambahkan ke Hardware</p>
            <p class="text-[9px] font-semibold leading-snug">Untuk item yang merupakan komponen/aksesori (Mouse, Keyboard, Monitor, dll), silakan tentukan hardware utama (PC/Laptop) yang menggunakan komponen tersebut di kolom "Asset".</p>
        </div>
    </div>

    <!-- 3. PREMIUM DYNAMIC TABLE -->
    <div class="relative">
        <div class="overflow-x-visible">
            <table class="w-full text-left border-collapse min-w-[800px]">
                <thead>
                    <tr class="border-b-2 border-slate-100">
                        <th class="w-12 px-2 py-3 text-center text-[9px] font-black uppercase tracking-[0.15em] text-slate-400">No</th>
                        <th class="w-1/4 px-4 py-3 text-[9px] font-black uppercase tracking-[0.15em] text-slate-400">Asset Name</th>
                        <th class="w-1/6 px-4 py-3 text-[9px] font-black uppercase tracking-[0.15em] text-slate-400">Type</th>
                        <th class="w-16 px-2 py-3 text-center text-[9px] font-black uppercase tracking-[0.15em] text-slate-400">Qty</th>
                        <th class="w-1/5 px-4 py-3 text-[9px] font-black uppercase tracking-[0.15em] text-slate-400">Serial No</th>
                        <th class="w-1/5 px-4 py-3 text-[9px] font-black uppercase tracking-[0.15em] text-slate-400">Asset</th>
                        <th class="w-12 px-2 py-3 text-center text-[9px] font-black uppercase tracking-[0.15em] text-slate-400">
                            <div v-if="props.isReturnMode" class="flex items-center justify-center">
                                <label class="relative flex items-center cursor-pointer">
                                    <input
                                        type="checkbox"
                                        v-model="isAllSelected"
                                        class="peer h-4 w-4 cursor-pointer appearance-none rounded border border-slate-300 transition-all checked:bg-[#003628] checked:border-[#003628] focus:outline-none"
                                    />
                                    <span class="absolute text-white opacity-0 peer-checked:opacity-100 top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 pointer-events-none">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor" stroke="currentColor" stroke-width="1">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                        </svg>
                                    </span>
                                </label>
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <!-- ROW DATA -->
                    <tr
                        v-for="(item, index) in items"
                        :key="index"
                        class="group transition-all hover:bg-slate-50/50"
                    >
                        <td class="px-2 py-4 text-center">
                            <span class="text-[10px] font-black text-slate-300 tabular-nums">{{ String(index + 1).padStart(2, '0') }}</span>
                        </td>
                        <td class="px-3 py-4">
                            <input
                                v-model="item.nama"
                                :readonly="item.kategori === 'assets'"
                                :class="[
                                    'w-full h-9 px-3 rounded-lg border border-slate-200 text-[12px] font-bold transition-all outline-none focus:ring-4 focus:ring-[#003628]/5',
                                    item.kategori === 'assets' ? 'text-slate-400 bg-slate-50 cursor-not-allowed italic' : 'text-slate-900 bg-white focus:border-[#003628]'
                                ]"
                                placeholder="Asset Name"
                            />
                        </td>
                        <td class="px-3 py-4">
                            <input
                                v-model="item.type"
                                readonly
                                class="w-full h-9 px-3 rounded-lg border border-slate-200 text-[12px] font-bold text-slate-400 italic bg-slate-50 cursor-not-allowed outline-none"
                                placeholder="Type"
                            />
                        </td>
                        <td class="px-2 py-4 text-center">
                            <input
                                v-model.number="item.jumlah"
                                type="number"
                                min="1"
                                :readonly="item.kategori === 'assets'"
                                :class="[
                                    'w-14 h-9 rounded-lg border border-slate-200 text-[12px] font-black text-center transition-all outline-none focus:ring-4 focus:ring-[#003628]/5',
                                    item.kategori === 'assets' ? 'text-slate-400 bg-slate-50 cursor-not-allowed' : 'text-slate-900 bg-white focus:border-[#003628]'
                                ]"
                            />
                        </td>
                        <td class="px-3 py-4">
                            <div class="flex items-center gap-2">
                                <input
                                    v-model="item.serialNo"
                                    :readonly="item.kategori === 'assets'"
                                    :class="[
                                        'flex-1 h-9 px-3 rounded-lg border border-slate-300 text-[11px] font-bold transition-all outline-none focus:border-[#003628] focus:ring-4 focus:ring-[#003628]/5 placeholder:text-slate-300',
                                        item.kategori === 'assets' ? 'text-slate-400 italic bg-slate-50 border-slate-100 cursor-not-allowed' : 'text-slate-900 bg-white',
                                        item.kategori === 'license' && !item.serialNo ? 'placeholder:text-red-300' : ''
                                    ]"
                                    :placeholder="item.kategori === 'license' ? 'Required...' : 'Serial'"
                                />
                            </div>
                        </td>
                        <td class="px-3 py-4">
                            <div class="flex items-center gap-2 group/aset">
                                <div 
                                    :class="[
                                        'flex-1 h-9 px-3 rounded-lg border flex items-center shadow-inner select-none transition-all',
                                        props.isReturnMode || item.kategori === 'assets'
                                            ? 'bg-slate-50 border-slate-100 text-slate-400 italic cursor-not-allowed' 
                                            : 'bg-white border-slate-200 text-slate-700 font-mono hover:border-[#003628] cursor-pointer'
                                    ]"
                                    @click="!props.isReturnMode && item.kategori !== 'assets' && openAssetPicker(index)"
                                >
                                    <span class="text-[11px] font-bold truncate">
                                        {{ item.inventory_number || (item.kategori === 'assets' ? '[USER]' : '-') }}
                                    </span>
                                </div>
                                <button 
                                    v-if="!props.isReturnMode && item.kategori !== 'assets'"
                                    type="button"
                                    @click="openAssetPicker(index)"
                                    class="p-0.5 text-slate-300 hover:text-[#003628] transition-all"
                                >
                                    <Search class="size-3.5" />
                                </button>
                            </div>
                        </td>
                        <td class="px-2 py-4 text-center">
                            <div v-if="props.isReturnMode" class="flex items-center justify-center">
                                <label class="relative flex items-center cursor-pointer">
                                    <input
                                        type="checkbox"
                                        v-model="item.is_selected"
                                        class="peer h-5 w-5 cursor-pointer appearance-none rounded-md border border-slate-300 transition-all checked:bg-[#003628] checked:border-[#003628] focus:outline-none"
                                    />
                                    <span class="absolute text-white opacity-0 peer-checked:opacity-100 top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 pointer-events-none">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" stroke="currentColor" stroke-width="1">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                        </svg>
                                    </span>
                                </label>
                            </div>
                            <button
                                v-else
                                type="button"
                                class="size-7 mx-auto flex items-center justify-center rounded-lg text-slate-300 hover:text-red-500 hover:bg-red-50 transition-all"
                                @click="props.removeItem(index)"
                            >
                                <Trash2 class="size-3.5" />
                            </button>
                        </td>
                    </tr>

                    <!-- LOADING STATE ROW -->
                    <tr v-if="props.isLoading">
                        <td colspan="7" class="py-20">
                            <div class="flex flex-col items-center justify-center gap-4">
                                <div class="size-8 border-4 border-slate-100 border-t-[#003628] rounded-full animate-spin" />
                                <div class="space-y-1 text-center">
                                    <p class="text-[11px] font-black text-slate-400 uppercase tracking-widest leading-none">Fetching asset data...</p>
                                    <p class="text-[10px] text-slate-300 font-bold italic">Please wait.</p>
                                </div>
                            </div>
                        </td>
                    </tr>

                    <!-- EMPTY STATE ROW -->
                    <tr v-if="!props.isLoading && items.length === 0">
                        <td colspan="7" class="px-6 py-12 text-center bg-slate-50/20">
                            <div class="flex flex-col items-center justify-center gap-3">
                                <div class="size-12 rounded-xl bg-white border border-slate-100 flex items-center justify-center text-slate-200">
                                    <Archive class="size-6" />
                                </div>
                                <div class="space-y-1">
                                    <p class="text-[11px] font-black text-slate-400 uppercase tracking-widest leading-none">Distribution list is empty</p>
                                    <p class="text-[10px] text-slate-300 font-bold italic">Click the button above to add assets.</p>
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
        <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">
            Total Items: <span class="text-slate-900 tabular-nums">{{ items.length }}</span>
        </p>
    </div>
    <StbLicenseAssetPickerModal
        v-model:open="pickerModalOpen"
        :user-assets="currentUserAssets"
        :all-assets="currentAllAssets"
        :category="activeCategory"
        :selected-value="activeItemIndex !== null ? items[activeItemIndex].inventory_number : ''"
        @select="handleAssetSelect"
    />
</section>
</template>

<style scoped>
/* Remove number input arrows */
input::-webkit-outer-spin-button,
input::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}
input[type=number] {
  -moz-appearance: textfield;
}

/* Hidden scrollbar but allow horizontal scroll if table is wide */
.overflow-x-visible {
    overflow-x: auto;
    overflow-y: visible;
}
.overflow-x-visible::-webkit-scrollbar {
    height: 4px;
}
.overflow-x-auto::-webkit-scrollbar-track {
    background: transparent;
}
.overflow-x-auto::-webkit-scrollbar-thumb {
    background: #e2e8f0;
    border-radius: 10px;
}
</style>


