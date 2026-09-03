<template>
    <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4 backdrop-blur-[2px]"
    >
        <div class="bg-white w-full max-w-md rounded-[20px] shadow-2xl overflow-hidden border border-slate-200 animate-in zoom-in-95 duration-200">
            <!-- Modal Header -->
            <div class="px-6 py-3 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                <div>
                    <h3 class="text-base font-black text-slate-800 tracking-tight">Create Asset Model</h3>
                    <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-0.5">Specifications</p>
                </div>
                <button @click="emit('close')" class="size-7 flex items-center justify-center rounded-full hover:bg-slate-200/50 text-slate-400 hover:text-slate-600 transition-all">
                    <X class="size-4" />
                </button>
            </div>

            <!-- Modal Body -->
            <div class="p-5 space-y-4 max-h-[70vh] overflow-y-auto">
                <!-- Name -->
                <div class="flex items-center gap-4">
                    <label class="w-1/3 text-right text-[11px] font-black text-slate-400 uppercase tracking-wider">Name:</label>
                    <div class="flex-1 relative">
                        <input
                            v-model="form.name"
                            type="text"
                            class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-xs px-3 h-9 rounded-lg focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all pr-4"
                            placeholder="e.g. MacBook Pro"
                        />
                        <div class="absolute right-0 top-1.5 bottom-1.5 w-1 bg-[#f39c12] rounded-l-full" />
                    </div>
                </div>

                <!-- Category -->
                <div class="flex flex-col gap-1.5">
                    <div class="flex items-center gap-4">
                        <label class="w-1/3 text-right text-[11px] font-black text-slate-400 uppercase tracking-wider">Category:</label>
                        <div class="flex-1 flex gap-1.5">
                            <div class="flex-1 relative">
                                <select
                                    v-model="form.category_id"
                                    class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-xs px-3 h-9 rounded-lg focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none appearance-none transition-all pr-8"
                                >
                                    <option value="">Select Category</option>
                                    <option
                                        v-for="category in allCategories"
                                        :key="category.id"
                                        :value="String(category.id)"
                                    >
                                        {{ category.name }}
                                    </option>
                                </select>
                                <div class="absolute right-0 top-1.5 bottom-1.5 w-1 bg-[#f39c12] rounded-l-full" />
                                <ChevronDown class="absolute right-3 top-1/2 -translate-y-1/2 size-3 text-slate-400 pointer-events-none" />
                            </div>
                            <button @click="openNewCat" type="button" class="h-9 w-9 bg-slate-50 border border-slate-200 text-slate-400 hover:text-primary hover:border-primary/30 rounded-lg flex items-center justify-center transition-all shadow-sm">
                                <Plus class="size-4" />
                            </button>
                        </div>
                    </div>
                    <!-- Inline new category form -->
                    <div v-if="showNewCat" class="flex items-center gap-4 animate-in slide-in-from-top-1 duration-200">
                        <div class="w-1/3" />
                        <div class="flex-1 bg-amber-50/50 p-3 rounded-xl border border-amber-100 shadow-inner">
                            <label class="text-[9px] font-black uppercase tracking-[0.2em] text-amber-600 mb-1.5 block">Quick Add Category</label>
                            <div class="flex gap-1.5">
                                <input
                                    v-model="newCatName"
                                    type="text"
                                    class="flex-1 bg-white border border-amber-200 text-slate-900 text-[11px] px-3 h-8 rounded-lg outline-none focus:ring-2 focus:ring-amber-500/20"
                                    placeholder="Name"
                                    @keyup.enter="confirmNewCat"
                                />
                                <button @click="confirmNewCat" type="button" class="h-8 px-3 bg-amber-500 text-white text-[9px] font-black uppercase tracking-widest rounded-lg hover:bg-amber-600 shadow-sm disabled:opacity-50 transition-colors" :disabled="addingCat">
                                    {{ addingCat ? '...' : 'Add' }}
                                </button>
                                <button @click="cancelNewCat" type="button" class="h-8 w-8 flex items-center justify-center text-amber-400 hover:text-amber-600 transition-colors">
                                    <X class="size-3" />
                                </button>
                            </div>
                            <p v-if="catError" class="mt-1.5 text-[9px] text-red-500 font-bold uppercase">{{ catError }}</p>
                        </div>
                    </div>
                </div>

                <!-- Manufacturer -->
                <div class="flex flex-col gap-1.5">
                    <div class="flex items-center gap-4">
                        <label class="w-1/3 text-right text-[11px] font-black text-slate-400 uppercase tracking-wider">Manufacturer:</label>
                        <div class="flex-1 flex gap-1.5">
                            <div class="flex-1 relative">
                                <select
                                    v-model="form.manufacturer_id"
                                    class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-xs px-3 h-9 rounded-lg focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none appearance-none transition-all pr-8"
                                >
                                    <option value="">Select Manufacturer</option>
                                    <option
                                        v-for="manufacturer in allManufacturers"
                                        :key="manufacturer.id"
                                        :value="String(manufacturer.id)"
                                    >
                                        {{ manufacturer.name }}
                                    </option>
                                </select>
                                <ChevronDown class="absolute right-3 top-1/2 -translate-y-1/2 size-3 text-slate-400 pointer-events-none" />
                            </div>
                            <button @click="openNewMan" type="button" class="h-9 w-9 bg-slate-50 border border-slate-200 text-slate-400 hover:text-primary hover:border-primary/30 rounded-lg flex items-center justify-center transition-all shadow-sm">
                                <Plus class="size-4" />
                            </button>
                        </div>
                    </div>
                    <!-- Inline new manufacturer form -->
                    <div v-if="showNewMan" class="flex items-center gap-4 animate-in slide-in-from-top-1 duration-200">
                        <div class="w-1/3" />
                        <div class="flex-1 bg-sky-50/50 p-3 rounded-xl border border-sky-100 shadow-inner">
                            <label class="text-[9px] font-black uppercase tracking-[0.2em] text-sky-600 mb-1.5 block">Quick Add Manufacturer</label>
                            <div class="flex gap-1.5">
                                <input
                                    v-model="newManName"
                                    type="text"
                                    class="flex-1 bg-white border border-sky-200 text-slate-900 text-[11px] px-3 h-8 rounded-lg outline-none focus:ring-2 focus:ring-sky-500/20"
                                    placeholder="Name"
                                    @keyup.enter="confirmNewMan"
                                />
                                <button @click="confirmNewMan" type="button" class="h-8 px-3 bg-sky-600 text-white text-[9px] font-black uppercase tracking-widest rounded-lg hover:bg-sky-700 shadow-sm disabled:opacity-50 transition-colors" :disabled="addingMan">
                                    {{ addingMan ? '...' : 'Add' }}
                                </button>
                                <button @click="cancelNewMan" type="button" class="h-8 w-8 flex items-center justify-center text-sky-400 hover:text-sky-600 transition-colors">
                                    <X class="size-3" />
                                </button>
                            </div>
                            <p v-if="manError" class="mt-1.5 text-[9px] text-red-500 font-bold uppercase">{{ manError }}</p>
                        </div>
                    </div>
                </div>

                <!-- Model No -->
                <div class="flex items-center gap-4">
                    <label class="w-1/3 text-right text-[11px] font-black text-slate-400 uppercase tracking-wider">Model No.:</label>
                    <div class="flex-1">
                        <input
                            v-model="form.model_number"
                            type="text"
                            class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-xs px-3 h-9 rounded-lg focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all"
                            placeholder="e.g. MP-2024"
                        />
                    </div>
                </div>

                <!-- Fieldset -->
                <div class="flex items-center gap-4">
                    <label class="w-1/3 text-right text-[11px] font-black text-slate-400 uppercase tracking-wider">Fieldset:</label>
                    <div class="flex-1 relative">
                        <select
                            v-model="form.fieldset_id"
                            class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-xs px-3 h-9 rounded-lg focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none appearance-none transition-all pr-8"
                        >
                            <option value="">No custom fields</option>
                            <option
                                v-for="fieldset in fieldsets"
                                :key="fieldset.id"
                                :value="String(fieldset.id)"
                            >
                                {{ fieldset.name }}
                            </option>
                        </select>
                        <ChevronDown class="absolute right-3 top-1/2 -translate-y-1/2 size-3 text-slate-400 pointer-events-none" />
                    </div>
                </div>

                <!-- Error Message -->
                <div v-if="error" class="flex gap-4">
                    <div class="w-1/3" />
                    <p class="flex-1 text-[10px] font-black text-red-500 uppercase tracking-tighter bg-red-50 p-2 rounded-lg border border-red-100">
                        {{ error }}
                    </p>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-3 bg-slate-50/50 flex items-center justify-between border-t border-slate-100">
                <button
                    type="button"
                    class="text-[11px] font-black text-slate-400 hover:text-slate-600 uppercase tracking-widest transition-colors"
                    @click="emit('close')"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    class="flex items-center gap-2 rounded-lg bg-primary px-5 h-9 text-[11px] font-black text-white shadow-lg shadow-primary/20 transition-all hover:brightness-110 active:scale-95 disabled:opacity-50"
                    :disabled="adding"
                    @click="emit('save')"
                >
                    <Save v-if="!adding" class="size-3.5" />
                    <span>{{ adding ? '...' : 'Save Model' }}</span>
                </button>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
/* eslint-disable vue/no-mutating-props */
import axios from 'axios';
import { X, ChevronDown, Plus, Save } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface OptionItem {
    id: number;
    name: string;
}

interface ModelForm {
    name: string;
    model_number: string;
    category_id: string;
    manufacturer_id: string;
    fieldset_id: string;
}

const props = defineProps<{
    open: boolean;
    form: ModelForm;
    error: string;
    adding: boolean;
    categories: OptionItem[];
    manufacturers: OptionItem[];
    fieldsets: OptionItem[];
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'save'): void;
    (e: 'category-created', category: OptionItem): void;
    (e: 'manufacturer-created', manufacturer: OptionItem): void;
}>();

const extraCats = ref<OptionItem[]>([]);
const showNewCat = ref(false);
const newCatName = ref('');
const addingCat = ref(false);
const catError = ref('');

const allCategories = computed<OptionItem[]>(() => [
    ...props.categories,
    ...extraCats.value,
]);

const openNewCat = () => {
    showNewCat.value = true;
    catError.value = '';
    newCatName.value = '';
};

const cancelNewCat = () => {
    showNewCat.value = false;
    catError.value = '';
};

const confirmNewCat = async () => {
    catError.value = '';
    if (!newCatName.value.trim()) {
        catError.value = 'Category name is required.';
        return;
    }
    addingCat.value = true;
    try {
        const res = await axios.post('/api/snipeit/categories', {
            name: newCatName.value.trim(),
            category_type: 'Asset',
        });
        const created = res.data?.category as OptionItem | undefined;
        if (created?.id) {
            extraCats.value.unshift(created);
            props.form.category_id = String(created.id);
            emit('category-created', created);
            showNewCat.value = false;
        }
    } catch (err: unknown) {
        catError.value = axios.isAxiosError(err)
            ? String(err.response?.data?.message ?? 'Failed to create category.')
            : 'Failed to create category.';
    } finally {
        addingCat.value = false;
    }
};

const extraMans = ref<OptionItem[]>([]);
const showNewMan = ref(false);
const newManName = ref('');
const addingMan = ref(false);
const manError = ref('');

const allManufacturers = computed<OptionItem[]>(() => [
    ...props.manufacturers,
    ...extraMans.value,
]);

const openNewMan = () => {
    showNewMan.value = true;
    manError.value = '';
    newManName.value = '';
};

const cancelNewMan = () => {
    showNewMan.value = false;
    manError.value = '';
};

const confirmNewMan = async () => {
    manError.value = '';
    if (!newManName.value.trim()) {
        manError.value = 'Manufacturer name is required.';
        return;
    }
    addingMan.value = true;
    try {
        const res = await axios.post('/api/snipeit/manufacturers', {
            name: newManName.value.trim()
        });
        const created = res.data?.manufacturer as OptionItem | undefined;
        if (created?.id) {
            extraMans.value.unshift(created);
            props.form.manufacturer_id = String(created.id);
            emit('manufacturer-created', created);
            showNewMan.value = false;
        }
    } catch (err: unknown) {
        manError.value = axios.isAxiosError(err)
            ? String(err.response?.data?.message ?? 'Failed to create manufacturer.')
            : 'Failed to create manufacturer.';
    } finally {
        addingMan.value = false;
    }
};
</script>
