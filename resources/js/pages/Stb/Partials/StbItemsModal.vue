<script setup lang="ts">
import { LucideBox as Box, LucideArchive as Archive } from 'lucide-vue-next';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

interface StbItem {
    id: number;
    nama: string;
    kategori: string | null;
    type: string;
    jumlah: number;
    serial_no: string | null;
    inventory_number: string | null;
}

defineProps<{
    open: boolean;
    docId: string;
    items: StbItem[];
}>();

defineEmits<{
    (e: 'close'): void;
}>();
</script>

<template>
    <Dialog :open="open" @update:open="(val) => !val && $emit('close')">
        <DialogContent class="sm:max-w-6xl p-8 rounded-[24px] border-none shadow-2xl bg-white">
            <DialogHeader class="mb-6">
                <div class="flex items-center justify-between w-full">
                    <div class="flex items-center gap-3">
                        <div class="size-10 rounded-xl bg-slate-50 flex items-center justify-center text-[#003628]">
                            <Box class="size-5" />
                        </div>
                        <div>
                            <DialogTitle class="text-lg font-black text-[#003628] leading-none uppercase tracking-tight">Daftar Item</DialogTitle>
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mt-1.5">{{ docId }}</p>
                        </div>
                    </div>
                </div>
            </DialogHeader>

            <div class="rounded-xl border border-slate-100 overflow-hidden bg-white">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b-2 border-slate-50 bg-slate-50/30">
                            <th class="w-12 px-4 py-3 text-center text-[9px] font-black uppercase tracking-[0.15em] text-slate-400">No</th>
                            <th class="px-4 py-3 text-[9px] font-black uppercase tracking-[0.15em] text-slate-400">Nama Barang</th>
                            <th class="px-4 py-3 text-[9px] font-black uppercase tracking-[0.15em] text-slate-400">Tipe</th>
                            <th class="w-20 px-4 py-3 text-center text-[9px] font-black uppercase tracking-[0.15em] text-slate-400">Jumlah</th>
                            <th class="px-4 py-3 text-[9px] font-black uppercase tracking-[0.15em] text-slate-400">No. Seri</th>
                            <th class="px-4 py-3 text-[9px] font-black uppercase tracking-[0.15em] text-slate-400">Aset</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-for="(item, index) in items" :key="item.id" class="group transition-all hover:bg-slate-50/50">
                            <td class="px-4 py-4 text-center">
                                <span class="text-[10px] font-black text-slate-300 tabular-nums">{{ String(index + 1).padStart(2, '0') }}</span>
                            </td>
                            <td class="px-4 py-4">
                                <p class="text-[12px] font-bold text-slate-900">{{ item.nama }}</p>
                            </td>
                            <td class="px-4 py-4">
                                <p class="text-[12px] font-medium text-slate-600">{{ item.type }}</p>
                            </td>
                            <td class="px-4 py-4 text-center">
                                <p class="text-[12px] font-black text-slate-900">{{ item.jumlah }}</p>
                            </td>
                            <td class="px-4 py-4">
                                <p class="text-[11px] font-black uppercase tracking-widest text-slate-900">{{ item.serial_no || '-' }}</p>
                            </td>
                            <td class="px-4 py-4">
                                <p class="text-[11px] font-black uppercase tracking-widest text-slate-900">{{ item.inventory_number || '-' }}</p>
                            </td>
                        </tr>
                        <tr v-if="!items.length">
                            <td colspan="6" class="px-6 py-12 text-center bg-slate-50/20">
                                <div class="flex flex-col items-center justify-center gap-3">
                                    <Archive class="size-6 text-slate-200" />
                                    <p class="text-[11px] font-black text-slate-400 uppercase tracking-widest leading-none">Daftar distribusi kosong</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-8 flex items-center justify-between">
                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">
                    Total Item: <span class="text-slate-900 tabular-nums">{{ items.length }}</span>
                </p>
                <button
                    type="button"
                    class="h-10 px-8 rounded-xl bg-[#003628] text-white text-[10px] font-black uppercase tracking-widest shadow-lg shadow-emerald-900/20 hover:brightness-110 active:scale-95 transition-all"
                    @click="$emit('close')"
                >
                    Tutup
                </button>
            </div>
        </DialogContent>
    </Dialog>
</template>
