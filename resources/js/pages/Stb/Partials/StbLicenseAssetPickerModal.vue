<script setup lang="ts">
import { 
    LucideSearch as Search, 
    LucideMonitor as Monitor, 
    LucideCheck as Check,
    LucidePlus as Plus,
    LucideChevronRight as ChevronRight
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { 
    Dialog, 
    DialogContent, 
    DialogHeader, 
    DialogTitle,
    DialogDescription
} from '@/components/ui/dialog';
import type { SnipeAsset } from '@/composables/useSnipeDirectory';

const props = defineProps<{
    open: boolean;
    userAssets: SnipeAsset[];
    allAssets: SnipeAsset[];
    selectedValue?: string;
    category?: string;
}>();

const emit = defineEmits(['update:open', 'select']);

const searchQuery = ref('');
const activeTab = ref<'user' | 'all'>('user');

const filteredUserAssets = computed(() => {
    if (!searchQuery.value) return props.userAssets;
    const q = searchQuery.value.toLowerCase();
    return props.userAssets.filter(a => 
        (a.name || '').toLowerCase().includes(q) || 
        (a.otherserial || '').toLowerCase().includes(q) ||
        (a.serial || '').toLowerCase().includes(q)
    );
});

const filteredAllAssets = computed(() => {
    const q = searchQuery.value.toLowerCase();
    const userAssetIds = new Set(props.userAssets.map(a => a.id));
    
    return props.allAssets.filter(a => {
        // 1. Exclude items already in the 'User Owned' tab
        if (userAssetIds.has(a.id)) return false;

        // 2. For Hardware (assets category), show all statuses as requested for "Select Asset" function.
        // We only exclude broken/archived if we want to be safe, but user said "semua status lengkap".
        if (props.category === 'assets' || !props.category) {
            const state = (a.state_name || '').toLowerCase();
            // Exclude only the absolutely unusable ones if necessary, but "semua status" usually implies everything.
            // Let's keep it broad.
            if (state.includes('archived')) return false;
        }

        // 3. For other categories (License, Accessory, etc.), only show items with remaining stock
        if (['license', 'accessories', 'consumable', 'component'].includes(props.category || '')) {
            const remaining = Number(a.remaining ?? 0);
            if (remaining <= 0) return false;
        }
        
        if (!searchQuery.value) return true;
        return (a.name || '').toLowerCase().includes(q) || 
               (a.otherserial || '').toLowerCase().includes(q) ||
               (a.serial || '').toLowerCase().includes(q);
    });
});

const handleSelect = (asset: SnipeAsset) => {
    // For hardware assets, we usually want the Asset Tag (otherserial) or Serial
    const value = asset.otherserial || asset.serial || asset.name;
    emit('select', { value, id: asset.id });
    emit('update:open', false);
    searchQuery.value = '';
};

const handleUserCheckout = () => {
    emit('select', { value: '[USER]', id: null });
    emit('update:open', false);
    searchQuery.value = '';
};

const handleCustomSelect = () => {
    if (!searchQuery.value.trim()) return;
    emit('select', { value: searchQuery.value.trim(), id: null });
    emit('update:open', false);
    searchQuery.value = '';
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-[650px] p-0 overflow-hidden bg-white border-none shadow-2xl">
            <!-- TOP SPACING WITH TITLE -->
            <div class="pt-5 pb-1 px-6 bg-white">
                <span class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-300">Pilih {{ props.category || 'Aset' }}</span>
            </div>

            <!-- ULTRA COMPACT SEARCH BAR & TABS -->
            <div class="px-6 pt-1 bg-white border-b border-slate-100">
                <div class="relative group mb-4">
                    <Search class="absolute left-4 top-1/2 -translate-y-1/2 size-4 text-slate-400 group-focus-within:text-[#003628] transition-all" />
                    <input 
                        v-model="searchQuery"
                        type="text"
                        placeholder="Cari Asset Tag, Serial, atau Nama Barang..."
                        class="w-full h-11 pl-11 pr-4 rounded-xl border border-slate-200 bg-white text-[12px] font-bold text-slate-900 shadow-sm focus:border-[#003628] focus:ring-4 focus:ring-[#003628]/5 transition-all outline-none placeholder:text-slate-300"
                    />
                </div>

                <!-- TABS -->
                <div class="flex gap-6">
                    <button 
                        @click="activeTab = 'user'"
                        :class="[
                            'pb-2 text-[10px] font-black uppercase tracking-[0.2em] transition-all relative',
                            activeTab === 'user' ? 'text-[#003628]' : 'text-slate-300 hover:text-slate-400'
                        ]"
                    >
                        Milik User ({{ props.userAssets.length }})
                        <div v-if="activeTab === 'user'" class="absolute bottom-0 left-0 w-full h-0.5 bg-[#003628] rounded-full"></div>
                    </button>
                    <button 
                        @click="activeTab = 'all'"
                        :class="[
                            'pb-2 text-[10px] font-black uppercase tracking-[0.2em] transition-all relative',
                            activeTab === 'all' ? 'text-[#003628]' : 'text-slate-300 hover:text-slate-400'
                        ]"
                    >
                        Semua Aset
                        <div v-if="activeTab === 'all'" class="absolute bottom-0 left-0 w-full h-0.5 bg-[#003628] rounded-full"></div>
                    </button>
                </div>
            </div>

            <!-- ASSET LIST TABLE (FIXED HEIGHT FOR CONSISTENCY) -->
            <div class="h-[450px] overflow-y-auto bg-slate-50/20">
                <table class="w-full text-left border-collapse">
                    <thead class="sticky top-0 z-10 bg-white border-b border-slate-100 shadow-sm">
                        <tr>
                            <th class="px-6 py-2 text-[8px] font-black uppercase tracking-[0.2em] text-slate-300">Aset</th>
                            <th class="px-6 py-2 text-[8px] font-black uppercase tracking-[0.2em] text-slate-300">Nama Barang</th>
                            <th class="px-6 py-2 text-[8px] font-black uppercase tracking-[0.2em] text-slate-300">Kategori</th>
                            <th class="w-12 px-6 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <template v-if="activeTab === 'user'">
                            <tr v-for="asset in filteredUserAssets" :key="asset.id" 
                                class="group cursor-pointer hover:bg-[#003628]/5 transition-all"
                                @click="handleSelect(asset)"
                            >
                                <td class="px-6 py-2.5">
                                    <div class="flex flex-col">
                                        <span class="text-[11px] font-black text-[#003628] leading-tight">{{ asset.otherserial || asset.serial }}</span>
                                        <span class="text-[8px] font-black text-white bg-[#003628] px-1.5 py-0.5 rounded-sm uppercase tracking-widest inline-block w-fit mt-0.5">Milik User</span>
                                    </div>
                                </td>
                                <td class="px-6 py-2.5">
                                    <span class="text-[10px] font-black text-slate-700 uppercase">{{ asset.name }}</span>
                                </td>
                                <td class="px-6 py-2.5">
                                    <span class="text-[8px] font-black text-slate-400 uppercase tracking-widest">{{ asset.type_name || 'Hardware' }}</span>
                                </td>
                                <td class="px-6 py-2.5 text-right">
                                    <Check v-if="selectedValue === (asset.otherserial || asset.serial)" class="size-3 text-[#003628] ml-auto" />
                                </td>
                            </tr>
                            <tr v-if="filteredUserAssets.length === 0">
                                <td colspan="4" class="py-20 text-center text-slate-300 text-[10px] uppercase font-black tracking-[0.2em]">Tidak ada aset milik user</td>
                            </tr>
                        </template>

                        <!-- SECTION: ALL ASSETS -->
                        <template v-if="activeTab === 'all'">
                            <!-- CUSTOM ASSET TAG OPTION -->
                            <tr v-if="searchQuery" 
                                class="group cursor-pointer bg-slate-50/50 hover:bg-slate-100 transition-all border-b border-slate-100"
                                @click="handleCustomSelect"
                            >
                                <td class="px-6 py-3">
                                    <div class="flex items-center gap-2">
                                        <Plus class="size-3 text-[#003628]" />
                                        <span class="text-[11px] font-bold text-[#003628] italic underline decoration-dotted decoration-[#003628]/30 underline-offset-4">
                                            {{ searchQuery }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-3" colspan="2">
                                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest italic">Gunakan sebagai custom tag</span>
                                </td>
                                <td class="px-6 py-3 text-right">
                                    <ChevronRight class="size-3 text-slate-300 group-hover:text-[#003628] transition-all ml-auto" />
                                </td>
                            </tr>

                            <tr v-for="asset in filteredAllAssets" :key="asset.id" 
                                class="group cursor-pointer hover:bg-slate-50 transition-all"
                                @click="handleSelect(asset)"
                            >
                                <td class="px-6 py-2.5">
                                    <span class="text-[11px] font-bold text-slate-700">{{ asset.otherserial || asset.serial }}</span>
                                </td>
                                <td class="px-6 py-2.5">
                                    <span class="text-[10px] font-medium text-slate-400 uppercase">{{ asset.name }}</span>
                                </td>
                                <td class="px-6 py-2.5">
                                    <span class="text-[8px] font-bold text-slate-200 uppercase tracking-widest">{{ asset.type_name || 'Hardware' }}</span>
                                </td>
                                <td class="px-6 py-2.5 text-right">
                                    <Check v-if="selectedValue === (asset.otherserial || asset.serial)" class="size-3 text-[#003628] ml-auto" />
                                </td>
                            </tr>
                            <tr v-if="filteredAllAssets.length === 0 && !searchQuery">
                                <td colspan="4" class="py-20 text-center text-slate-300 text-[10px] uppercase font-black tracking-[0.2em]">Aset tidak ditemukan</td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </DialogContent>
    </Dialog>
</template>
