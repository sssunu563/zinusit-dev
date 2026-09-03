<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { 
    LucideCheckCircle2 as CheckCircle2, 
    LucideEdit2 as Edit2, 
    LucidePrinter as PrinterIcon, 
    LucideTrash2 as Trash2,
    LucideShieldCheck as ShieldCheck,
    LucideRotateCcw as RotateCcw,
    LucideUser as UserIcon,
    LucideBuilding2 as BuildingIcon,
    LucideMapPin as MapPinIcon,
    LucideCalendar as CalendarIcon,
    LucideBox as BoxIcon,
    LucideLayers as LayersIcon
} from 'lucide-vue-next';

interface GroupParts {
    location?: string | null;
    department?: string | null;
}

interface ActionNotice {
    type: 'success' | 'error';
    message: string;
}

defineProps<{
    docId: string;
    userName: string;
    statusLabel: string;
    updatedLabel: string;
    position: string;
    groupParts: GroupParts;
    itemCount: number;
    totalQty: number;
    building: string | null;
    signedCount: number;
    totalApprovals: number;
    isCompleted: boolean;
    isCancelled: boolean;
    hasAllSignatures: boolean;
    canComplete: boolean;
    completeProcessing: boolean;
    loanReturnHref: string | null;
    editHref: string;
    actionNotice: ActionNotice | null;
    openCompleteConfirm: () => void;
    printDocument: () => void;
    openDeleteConfirm: () => void;
    detailKicker: string;
    progressLabel: string;
    progressMeta: string;
    subjectLabel: string;
    assetLabel: string;
    updatedMetaPrefix: string;
}>();
</script>

<template>
    <section class="overflow-hidden rounded-[32px] border border-slate-200 bg-white p-6 md:p-10 shadow-xl shadow-slate-200/40 relative">
        <!-- Decorative background -->
        <div class="absolute top-0 right-0 -mr-24 -mt-24 h-96 w-96 rounded-full bg-primary/5 blur-[120px] pointer-events-none" />
        
        <div class="relative z-10 space-y-8">
            <div class="flex flex-col xl:flex-row xl:items-start justify-between gap-8">
                <div class="space-y-4 max-w-3xl">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="inline-flex items-center gap-2 rounded-full border border-primary/10 bg-primary/5 px-3 py-1 text-[10px] font-black tracking-widest text-primary uppercase">
                            <ShieldCheck class="size-3" />
                            {{ detailKicker }}
                        </div>
                        <div 
                            class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-[10px] font-black tracking-widest uppercase border"
                            :class="isCancelled 
                                ? 'bg-red-50 text-red-500 border-red-100' 
                                : isCompleted 
                                    ? 'bg-primary/10 text-primary border-primary/20' 
                                    : 'bg-amber-50 text-amber-600 border-amber-100'"
                        >
                            {{ statusLabel }}
                        </div>
                    </div>

                    <div class="space-y-1">
                        <h1 class="text-4xl font-black tracking-tight text-slate-900 lg:text-5xl italic decoration-primary/10 underline-offset-8">
                            {{ docId }}
                        </h1>
                        <p class="text-lg font-black text-slate-400 italic tracking-tight uppercase tracking-[0.05em] pt-1">
                            {{ progressLabel }} <span class="text-primary">— {{ progressMeta }}</span>
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-4 pt-2">
                        <div class="flex items-center gap-2.5 px-4 py-2 rounded-2xl border border-slate-100 bg-slate-50/50 shadow-sm transition-all hover:bg-white hover:shadow-md">
                            <UserIcon class="size-4 text-primary" />
                            <div class="flex flex-col">
                                <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 leading-none mb-1">{{ subjectLabel }}</span>
                                <span class="text-[13px] font-bold text-slate-900 leading-none">{{ userName }}</span>
                            </div>
                        </div>
                        <div v-if="groupParts.department" class="flex items-center gap-2.5 px-4 py-2 rounded-2xl border border-slate-100 bg-slate-50/50 shadow-sm transition-all hover:bg-white hover:shadow-md">
                            <BuildingIcon class="size-4 text-primary" />
                            <div class="flex flex-col">
                                <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 leading-none mb-1">Divisi</span>
                                <span class="text-[13px] font-bold text-slate-900 leading-none">{{ groupParts.department }}</span>
                            </div>
                        </div>
                        <div v-if="groupParts.location" class="flex items-center gap-2.5 px-4 py-2 rounded-2xl border border-slate-100 bg-slate-50/50 shadow-sm transition-all hover:bg-white hover:shadow-md">
                            <MapPinIcon class="size-4 text-primary" />
                            <div class="flex flex-col">
                                <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 leading-none mb-1">Lokasi Operasional</span>
                                <span class="text-[13px] font-bold text-slate-900 leading-none">{{ groupParts.location }} {{ building ? `(${building})` : '' }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3 xl:pt-4">
                    
                    <button
                        type="button"
                        class="h-12 px-6 rounded-2xl bg-white border border-slate-200 flex items-center gap-2.5 text-slate-600 text-sm font-bold hover:bg-slate-50 hover:text-slate-900 transition-all active:scale-95 shadow-sm"
                        @click="printDocument"
                    >
                        <PrinterIcon class="size-4 text-slate-400" />
                        <span>Cetak Dokumen</span>
                    </button>

                    <button
                        v-if="canComplete"
                        type="button"
                        class="h-12 px-8 rounded-2xl bg-primary text-white flex items-center gap-2.5 text-sm font-bold hover:brightness-110 transition-all active:scale-95 shadow-lg shadow-primary/30"
                        :disabled="completeProcessing"
                        @click="openCompleteConfirm"
                    >
                        <CheckCircle2 class="size-4" />
                        <span>{{ completeProcessing ? 'Memproses...' : 'Selesaikan Dokumen' }}</span>
                    </button>

                    <Link
                        v-if="loanReturnHref"
                        :href="loanReturnHref"
                        class="h-12 px-8 rounded-2xl bg-primary text-white flex items-center gap-2.5 text-sm font-bold hover:brightness-110 transition-all active:scale-95 shadow-lg shadow-primary/30"
                    >
                        <RotateCcw class="size-4" />
                        <span>Proses Pengembalian</span>
                    </Link>

                    <div v-if="!hasAllSignatures && !isCompleted && !isCancelled" class="flex items-center gap-2 ml-auto lg:ml-0">
                        <Link
                            :href="editHref"
                            class="h-12 w-12 rounded-2xl border border-slate-200 bg-white flex items-center justify-center text-amber-600 hover:bg-amber-50 hover:border-amber-100 transition-all active:scale-90 shadow-sm"
                            title="Ubah Data"
                        >
                            <Edit2 class="size-4" />
                        </Link>
                        <button
                            type="button"
                            class="h-12 w-12 rounded-2xl border border-slate-200 bg-white flex items-center justify-center text-red-500 hover:bg-red-50 hover:border-red-100 transition-all active:scale-90 shadow-sm"
                            title="Hapus Data"
                            @click="openDeleteConfirm"
                        >
                            <Trash2 class="size-4" />
                        </button>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 pt-6 border-t border-slate-100">
                <div class="flex items-center gap-4">
                    <div class="size-10 rounded-xl bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-400">
                        <BoxIcon class="size-4" />
                    </div>
                    <div class="flex flex-col">
                        <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 leading-none mb-1">Total Item</span>
                        <span class="text-[13px] font-bold text-slate-900 leading-none">{{ itemCount }} Item <span class="text-slate-400 font-medium">({{ totalQty }} unit)</span></span>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <div class="size-10 rounded-xl bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-400">
                        <ShieldCheck class="size-4 text-primary" />
                    </div>
                    <div class="flex flex-col">
                        <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 leading-none mb-1">Status Persetujuan</span>
                        <span class="text-[13px] font-bold text-slate-900 leading-none">
                            {{ signedCount }} dari {{ totalApprovals }} <span class="text-slate-400 font-medium">(Ditandatangani)</span>
                        </span>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <div class="size-10 rounded-xl bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-400">
                        <CalendarIcon class="size-4" />
                    </div>
                    <div class="flex flex-col">
                        <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 leading-none mb-1">{{ updatedMetaPrefix }}</span>
                        <span class="text-[13px] font-bold text-slate-900 leading-none lowercase tabular-nums first-letter:uppercase">{{ updatedLabel }}</span>
                    </div>
                </div>
            </div>

            <div
                v-if="actionNotice"
                class="p-4 rounded-2xl border text-sm font-bold flex items-center gap-3 animate-in fade-in slide-in-from-top-2 duration-300"
                :class="actionNotice.type === 'success' ? 'bg-primary/5 border-primary/20 text-primary' : 'bg-red-50 border-red-200 text-red-600'"
            >
                <div class="size-2 rounded-full animate-pulse" :class="actionNotice.type === 'success' ? 'bg-primary' : 'bg-red-500'" />
                {{ actionNotice.message }}
            </div>
        </div>
    </section>
</template>
