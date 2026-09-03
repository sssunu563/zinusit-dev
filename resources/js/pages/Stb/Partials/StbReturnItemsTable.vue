<script setup lang="ts">
import { Archive, CheckSquare, Square, Loader2 } from 'lucide-vue-next';
import { computed } from 'vue';

interface ReturnItem {
    nama: string;
    kategori: string;
    type: string;     // Untuk hardware: nama model. Untuk non-hw: kategori Snipe-IT
    model: string;    // NEW: editable model/brand field untuk non-hardware
    jumlah: number | null;
    serialNo: string;
    computer_id: number | null;
    snipeit_asset_id: number | null;
    inventory_number: string;
    is_selected: boolean;
}

const props = defineProps<{
    items: ReturnItem[];
    isLoading: boolean;
    userSelected: boolean;
    formError?: string;
}>();

const emit = defineEmits<{
    (e: 'update:items', val: ReturnItem[]): void;
}>();

const isHardware = (item: ReturnItem) =>
    ['assets', 'hardware'].includes((item.kategori || '').toLowerCase());

const toggleItem = (index: number) => {
    const updated = [...props.items];
    updated[index] = { ...updated[index], is_selected: !updated[index].is_selected };
    emit('update:items', updated);
};

const toggleAll = () => {
    const allSelected = props.items.every(i => i.is_selected);
    emit('update:items', props.items.map(i => ({ ...i, is_selected: !allSelected })));
};

const updateField = (index: number, field: keyof ReturnItem, value: any) => {
    const updated = [...props.items];
    updated[index] = { ...updated[index], [field]: value };
    emit('update:items', updated);
};

const selectedCount = computed(() => props.items.filter(i => i.is_selected).length);
const allSelected = computed(() => props.items.length > 0 && props.items.every(i => i.is_selected));
const someSelected = computed(() => selectedCount.value > 0 && !allSelected.value);

const CATEGORY_META: Record<string, { label: string; classes: string }> = {
    assets:      { label: 'Hardware',    classes: 'bg-blue-50 text-blue-700 border-blue-200' },
    license:     { label: 'License',     classes: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
    accessories: { label: 'Accessories', classes: 'bg-amber-50 text-amber-700 border-amber-200' },
    component:   { label: 'Component',   classes: 'bg-violet-50 text-violet-700 border-violet-200' },
};

const getCategoryMeta = (cat: string) =>
    CATEGORY_META[cat?.toLowerCase()] ?? { label: cat || 'Asset', classes: 'bg-slate-50 text-slate-600 border-slate-200' };
</script>

<template>
    <section class="space-y-3 animate-in fade-in duration-300">
        <!-- Header bar -->
        <div class="flex items-center justify-between px-1">
            <div class="flex items-center gap-3">
                <div class="h-4 w-1 rounded-full bg-[#003628]" />
                <h3 class="text-[10px] font-black uppercase tracking-[0.2em] text-[#003628]">Returned Assets List</h3>
            </div>
            <p v-if="items.length > 0" class="text-[10px] font-bold text-slate-500">
                <span class="text-[#003628] font-black">{{ selectedCount }}</span> / {{ items.length }} selected
            </p>
        </div>

        <!-- Error -->
        <div v-if="formError" class="flex items-center gap-2 px-4 py-3 bg-red-50 border border-red-100 rounded-xl text-red-600">
            <span class="text-[10px] font-black uppercase tracking-widest">{{ formError }}</span>
        </div>

        <!-- Table -->
        <div class="rounded-xl border border-slate-100 overflow-hidden overflow-x-auto">
            <table class="w-full text-left border-collapse" style="min-width: 780px">
                <thead class="bg-slate-50 border-b border-slate-100">
                    <tr>
                        <th class="w-10 px-3 py-3 text-center">
                            <button
                                v-if="items.length > 0"
                                type="button"
                                @click="toggleAll"
                                class="text-slate-400 hover:text-[#003628] transition-colors"
                                :title="allSelected ? 'Deselect all' : 'Select all'"
                            >
                                <CheckSquare v-if="allSelected" class="size-4 text-[#003628]" />
                                <CheckSquare v-else-if="someSelected" class="size-4 text-slate-400 opacity-50" />
                                <Square v-else class="size-4" />
                            </button>
                        </th>
                        <th class="px-4 py-3 text-[9px] font-black uppercase tracking-widest text-slate-400 w-[200px]">Asset Name</th>
                        <th class="px-3 py-3 text-[9px] font-black uppercase tracking-widest text-slate-400 w-[150px]">Type</th>
                        <th class="px-3 py-3 text-[9px] font-black uppercase tracking-widest text-slate-400 w-12 text-center">Qty</th>
                        <th class="px-3 py-3 text-[9px] font-black uppercase tracking-widest text-slate-400 w-[120px]">Condition</th>
                        <th class="px-3 py-3 text-[9px] font-black uppercase tracking-widest text-slate-400 w-[160px]">Serial No</th>
                        <th class="px-3 py-3 text-[9px] font-black uppercase tracking-widest text-slate-400">Asset</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <!-- Loading -->
                    <tr v-if="isLoading">
                        <td colspan="6" class="py-16 text-center">
                            <div class="flex flex-col items-center gap-3">
                                <Loader2 class="size-7 text-[#003628] animate-spin" />
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Fetching assets from Snipe-IT...</p>
                            </div>
                        </td>
                    </tr>

                    <!-- No user -->
                    <tr v-else-if="!userSelected">
                        <td colspan="6" class="py-16 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <Archive class="size-8 text-slate-200" />
                                <p class="text-[11px] font-black text-slate-400 uppercase tracking-widest">Select a user first</p>
                                <p class="text-[10px] text-slate-300 font-bold italic">Assets owned by the user will appear automatically.</p>
                            </div>
                        </td>
                    </tr>

                    <!-- Empty -->
                    <tr v-else-if="items.length === 0">
                        <td colspan="6" class="py-16 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <Archive class="size-8 text-slate-200" />
                                <p class="text-[11px] font-black text-slate-400 uppercase tracking-widest leading-none">User has no assets</p>
                                <p class="text-[10px] text-slate-300 font-bold italic">No Hardware, License, Accessories, or Components in Snipe-IT.</p>
                            </div>
                        </td>
                    </tr>

                    <!-- Rows -->
                    <tr
                        v-else
                        v-for="(item, idx) in items"
                        :key="idx"
                        :class="[
                            'transition-all',
                            item.is_selected
                                ? 'bg-emerald-50/40'
                                : 'opacity-55 hover:opacity-75'
                        ]"
                    >
                        <!-- Checkbox — click to toggle -->
                        <td class="px-3 py-3 text-center cursor-pointer" @click="toggleItem(idx)">
                            <div class="flex items-center justify-center">
                                <div :class="[
                                    'size-5 rounded-md border-2 flex items-center justify-center transition-all',
                                    item.is_selected ? 'bg-[#003628] border-[#003628]' : 'border-slate-300 bg-white'
                                ]">
                                    <svg v-if="item.is_selected" xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-white" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                            </div>
                        </td>

                        <!-- Nama Barang + Kategori -->
                        <td class="px-4 py-2.5">
                            <p class="text-[12px] font-semibold text-slate-800 leading-snug">{{ item.nama || '-' }}</p>
                            <span :class="[
                                'inline-flex items-center px-1.5 py-0.5 rounded border text-[8px] font-black uppercase tracking-wider mt-1',
                                getCategoryMeta(item.kategori).classes
                            ]">
                                {{ getCategoryMeta(item.kategori).label }}
                            </span>
                        </td>

                        <!-- Type -->
                        <td class="px-3 py-2.5" @click.stop>
                            <input
                                :value="item.model || item.type || ''"
                                @input="updateField(idx, 'model', ($event.target as HTMLInputElement).value)"
                                :placeholder="getCategoryMeta(item.kategori).label + ' model...'"
                                class="w-full h-8 px-2 rounded-md border border-slate-200 text-[11px] font-medium text-slate-700 bg-white focus:border-[#003628] focus:ring-2 focus:ring-[#003628]/10 outline-none transition-all placeholder:text-slate-300"
                            />
                        </td>

                        <!-- Qty -->
                        <td class="px-3 py-2.5 text-center" @click.stop>
                            <span v-if="isHardware(item)" class="text-[11px] font-bold text-slate-400">1</span>
                            <input
                                v-else
                                type="number"
                                min="1"
                                :value="item.jumlah ?? 1"
                                @input="updateField(idx, 'jumlah', Number(($event.target as HTMLInputElement).value))"
                                class="w-12 h-8 px-1 rounded-md border border-slate-200 text-[11px] font-bold text-slate-700 text-center bg-white focus:border-[#003628] focus:ring-2 focus:ring-[#003628]/10 outline-none transition-all"
                            />
                        </td>

                        <!-- Condition -->
                        <td class="px-3 py-2.5" @click.stop>
                            <select
                                :value="item.condition || 'Good'"
                                @change="updateField(idx, 'condition', ($event.target as HTMLSelectElement).value)"
                                class="w-full h-8 px-2 rounded-md border border-slate-200 text-[11px] font-bold text-slate-700 bg-white focus:border-[#003628] focus:ring-2 focus:ring-[#003628]/10 outline-none transition-all appearance-none"
                            >
                                <option value="Good">Good</option>
                                <option value="Broken">Broken</option>
                                <option value="Missing">Missing</option>
                            </select>
                        </td>

                        <!-- Serial No -->
                        <td class="px-3 py-2.5" @click.stop>
                            <span v-if="isHardware(item)" class="text-[11px] font-mono text-slate-500">
                                {{ item.serialNo || '-' }}
                            </span>
                            <input
                                v-else
                                :value="item.serialNo || ''"
                                @input="updateField(idx, 'serialNo', ($event.target as HTMLInputElement).value)"
                                placeholder="Serial / License No..."
                                class="w-full h-8 px-2 rounded-md border border-slate-200 text-[11px] font-mono text-slate-700 bg-white focus:border-[#003628] focus:ring-2 focus:ring-[#003628]/10 outline-none transition-all placeholder:text-slate-300"
                            />
                        </td>

                        <!-- Asset -->
                        <td class="px-3 py-2.5">
                            <span class="text-[11px] font-mono text-slate-500">{{ item.inventory_number || '-' }}</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Footer -->
        <div v-if="items.length > 0 && !isLoading" class="flex items-center justify-between px-1 pt-1">
            <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest">
                {{ items.length }} total assets · hardware readonly, non-hardware editable
            </p>
            <p class="text-[9px] text-slate-500 font-bold uppercase tracking-widest">
                <span :class="selectedCount > 0 ? 'text-[#003628]' : 'text-slate-300'">{{ selectedCount }} selected</span>
                &nbsp;·&nbsp; {{ items.length - selectedCount }} not returned
            </p>
        </div>
    </section>
</template>

<style scoped>
input[type=number]::-webkit-outer-spin-button,
input[type=number]::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
input[type=number] { -moz-appearance: textfield; }
</style>
