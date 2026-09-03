<script setup lang="ts">
/**
 * AssetDetailSheet — slide-out panel showing basic asset info from Snipe-IT.
 * Used by the Action Log page to preview an asset without navigating away.
 */
import { ref, watch, computed } from 'vue';
import axios from 'axios';
import { X, Package, Hash, Tag, MapPin, User, Loader2, ExternalLink } from 'lucide-vue-next';

interface Props {
    open: boolean;
    assetId: number | null;
    assetType?: string;
}

const props = withDefaults(defineProps<Props>(), {
    assetType: 'assets',
});

const emit = defineEmits<{
    (e: 'update:open', val: boolean): void;
}>();

const asset   = ref<any>(null);
const loading = ref(false);
const error   = ref<string | null>(null);

const close = () => emit('update:open', false);

const assetUrl = computed(() => {
    if (!props.assetId) return null;
    const type = props.assetType === 'laptop' ? 'assets' : props.assetType;
    return `/asset/item/${props.assetId}?type=${type}`;
});

watch(() => [props.open, props.assetId], async ([open, id]) => {
    if (!open || !id) { asset.value = null; return; }
    loading.value = true;
    error.value   = null;
    try {
        const queryType = props.assetType === 'laptop' ? 'assets' : props.assetType;
        const res = await axios.get(`/asset/api/${id}`, { params: { type: queryType } });
        asset.value = res.data;
    } catch (e: any) {
        error.value = e?.response?.data?.message ?? 'Gagal memuat data asset.';
    } finally {
        loading.value = false;
    }
}, { immediate: true });
</script>

<template>
    <!-- Backdrop -->
    <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0"
        enter-to-class="opacity-100" leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100" leave-to-class="opacity-0">
        <div v-if="open" class="fixed inset-0 z-40 bg-black/20 backdrop-blur-sm" @click="close"/>
    </Transition>

    <!-- Sheet -->
    <Transition enter-active-class="transition duration-300 ease-out" enter-from-class="translate-x-full opacity-0"
        enter-to-class="translate-x-0 opacity-100" leave-active-class="transition duration-200 ease-in"
        leave-from-class="translate-x-0 opacity-100" leave-to-class="translate-x-full opacity-0">
        <aside v-if="open"
            class="fixed right-0 top-0 bottom-0 z-50 w-full max-w-md bg-white shadow-2xl flex flex-col overflow-hidden">

            <!-- Header -->
            <div class="flex items-center justify-between px-6 py-5 border-b border-slate-100">
                <div class="flex items-center gap-3">
                    <div class="h-9 w-9 rounded-xl bg-[#003628]/5 flex items-center justify-center">
                        <Package class="size-4 text-[#003628]"/>
                    </div>
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Asset Detail</p>
                        <p class="text-[12px] font-black text-slate-900 mt-0.5">{{ assetType?.toUpperCase() }}</p>
                    </div>
                </div>
                <button type="button" @click="close"
                    class="h-8 w-8 rounded-xl border border-slate-200 flex items-center justify-center text-slate-400 hover:text-slate-900 hover:bg-slate-50 transition-all">
                    <X class="size-4"/>
                </button>
            </div>

            <!-- Body -->
            <div class="flex-1 overflow-y-auto p-6">

                <!-- Loading -->
                <div v-if="loading" class="flex flex-col items-center justify-center py-16 gap-3">
                    <Loader2 class="size-6 text-[#003628] animate-spin"/>
                    <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">Memuat data...</p>
                </div>

                <!-- Error -->
                <div v-else-if="error" class="rounded-2xl bg-rose-50 border border-rose-100 p-5 text-center">
                    <p class="text-[12px] font-bold text-rose-600">{{ error }}</p>
                </div>

                <!-- Asset data -->
                <div v-else-if="asset" class="space-y-4">
                    <!-- Name + tag -->
                    <div class="rounded-2xl border border-slate-100 bg-slate-50/60 p-5">
                        <p class="text-lg font-black text-slate-900 leading-tight">{{ asset.name ?? '-' }}</p>
                        <p class="text-[11px] font-bold text-slate-400 mt-1">{{ asset.asset_tag ?? asset.serial ?? '-' }}</p>
                    </div>

                    <!-- Info grid -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="rounded-2xl border border-slate-100 bg-white p-4 space-y-1">
                            <div class="flex items-center gap-1.5 text-[9px] font-black uppercase tracking-widest text-slate-400">
                                <Hash class="size-3"/> Serial
                            </div>
                            <p class="text-[12px] font-black text-slate-800 truncate">{{ asset.serial ?? '-' }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-100 bg-white p-4 space-y-1">
                            <div class="flex items-center gap-1.5 text-[9px] font-black uppercase tracking-widest text-slate-400">
                                <Tag class="size-3"/> Status
                            </div>
                            <p class="text-[12px] font-black text-slate-800 truncate">
                                {{ asset.status_label?.name ?? asset.status_name ?? '-' }}
                            </p>
                        </div>
                        <div class="rounded-2xl border border-slate-100 bg-white p-4 space-y-1">
                            <div class="flex items-center gap-1.5 text-[9px] font-black uppercase tracking-widest text-slate-400">
                                <MapPin class="size-3"/> Lokasi
                            </div>
                            <p class="text-[12px] font-black text-slate-800 truncate">
                                {{ asset.location?.name ?? asset.location_name ?? '-' }}
                            </p>
                        </div>
                        <div class="rounded-2xl border border-slate-100 bg-white p-4 space-y-1">
                            <div class="flex items-center gap-1.5 text-[9px] font-black uppercase tracking-widest text-slate-400">
                                <User class="size-3"/> Assigned To
                            </div>
                            <p class="text-[12px] font-black text-slate-800 truncate">
                                {{ asset.assigned_to?.name ?? '-' }}
                            </p>
                        </div>
                    </div>

                    <!-- Category / Model -->
                    <div class="rounded-2xl border border-slate-100 bg-white p-4 space-y-2">
                        <div class="flex justify-between items-center">
                            <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">Model</span>
                            <span class="text-[11px] font-bold text-slate-700">{{ asset.model?.name ?? '-' }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">Kategori</span>
                            <span class="text-[11px] font-bold text-slate-700">{{ asset.category?.name ?? '-' }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">Manufacturer</span>
                            <span class="text-[11px] font-bold text-slate-700">{{ asset.manufacturer?.name ?? '-' }}</span>
                        </div>
                    </div>

                    <!-- Link to full detail -->
                    <a v-if="assetUrl" :href="assetUrl"
                        class="flex items-center justify-center gap-2 w-full h-10 rounded-xl bg-[#003628] text-white text-[11px] font-black uppercase tracking-widest hover:brightness-110 transition-all active:scale-95">
                        <ExternalLink class="size-3.5"/> Lihat Detail Lengkap
                    </a>
                </div>

                <!-- Empty -->
                <div v-else class="flex flex-col items-center justify-center py-16 gap-3">
                    <Package class="size-8 text-slate-200"/>
                    <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">Tidak ada data</p>
                </div>
            </div>
        </aside>
    </Transition>
</template>
