<script setup lang="ts">
/* eslint-disable vue/no-mutating-props */
import { 
    LucideChevronDown as ChevronDown, 
    LucideMapPin as MapPin,
    LucideHash as Hash,
    LucideCalendar as Calendar,
    LucideLayers as Layers,
    LucideFileText as FileText,
    LucideFileCheck as FileCheck
} from 'lucide-vue-next';
import type {
    LoanReferenceOption,
    StbDocumentType,
    StbMovementType,
} from '@/pages/Stb/types';

interface StbFormData {
    user_id: number | string;
    documentType: StbDocumentType | string;
    movementType: StbMovementType | string;
    linkedStbId: number | string;
    deliverDate: string;
    building: string;
    useDate: string;
    batchNo: string;
    reqDocNo: string;
    poDocNo: string;
}

interface FormErrors {
    documentType?: string;
    movementType?: string;
    linkedStbId?: string;
    user_id?: string;
    group_id?: string;
}

defineProps<{
    docIdDisplay: string;
    formData: StbFormData;
    users: Array<{ id: number; name: string }>;
    documentTypeOptions: Array<{ value: string; label: string }>;
    movementOptions: Array<{ value: string; label: string }>;
    loanReferences: LoanReferenceOption[];
    resolvedLocationLabel: string;
    formErrors: FormErrors;
    lockDocumentFlow: boolean;
    documentFlowLabel: string;
    documentDateLabel: string;
    selectedLoanReferenceLabel: string;
}>();
</script>

<template>
<section class="space-y-8 animate-in fade-in slide-in-from-bottom-2 duration-300">
    <div class="space-y-10 max-w-4xl mx-auto">
        <!-- 1. STB INFORMATION -->
        <div class="space-y-6">
            <div class="flex items-center gap-3 mb-2">
                <div class="h-px flex-1 bg-slate-100" />
                <span class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">STB Information</span>
                <div class="h-px flex-1 bg-slate-100" />
            </div>
 
            <div class="grid grid-cols-2 gap-x-12 gap-y-6">
                <!-- ROW 1 -->
                <div class="space-y-2">
                    <label class="text-[11px] font-black uppercase tracking-widest text-slate-400">Doc ID</label>
                    <div class="relative group">
                        <div class="flex h-10 items-center rounded-lg border border-slate-200 bg-slate-50 pl-10 pr-4 text-[13px] font-black text-slate-400 tabular-nums tracking-widest italic shadow-inner">
                            {{ docIdDisplay || 'AUTOMATIC' }}
                        </div>
                        <Hash class="absolute left-3.5 top-1/2 -translate-y-1/2 size-3.5 text-slate-300" />
                    </div>
                </div>
                <div class="space-y-2">
                    <label class="text-[11px] font-black uppercase tracking-widest text-slate-400">Location</label>
                    <div class="relative group">
                        <div class="flex h-10 items-center rounded-lg border border-slate-200 bg-slate-50 pl-10 pr-4 text-[13px] font-black text-slate-400 shadow-inner italic">
                            {{ resolvedLocationLabel || 'AUTOMATIC' }}
                        </div>
                        <MapPin class="absolute left-3.5 top-1/2 -translate-y-1/2 size-3.5 text-slate-300" />
                    </div>
                </div>
 
                <!-- ROW 2 -->
                <div class="space-y-2">
                    <label class="text-[11px] font-black uppercase tracking-widest text-slate-400">{{ documentDateLabel }}</label>
                    <div class="relative group">
                        <input
                            v-model="formData.deliverDate"
                            type="date"
                            class="w-full h-10 px-4 pl-10 rounded-lg border border-slate-200 bg-white text-[13px] font-bold text-slate-900 focus:border-[#003628] transition-all outline-none shadow-sm"
                        />
                        <Calendar class="absolute left-3.5 top-1/2 -translate-y-1/2 size-3.5 text-slate-300 group-focus-within:text-[#003628] transition-colors" />
                    </div>
                </div>
                <div class="space-y-2">
                    <label class="text-[11px] font-black uppercase tracking-widest text-slate-400">Building</label>
                    <div class="group relative">
                        <input
                            v-model="formData.building"
                            class="w-full h-10 px-4 pl-10 rounded-lg border border-slate-200 bg-white text-[13px] font-bold text-slate-900 focus:border-[#003628] transition-all outline-none shadow-sm"
                            placeholder="Example: Building A"
                        />
                        <MapPin class="absolute left-3.5 top-1/2 -translate-y-1/2 size-3.5 text-slate-300 group-focus-within:text-[#003628] transition-colors" />
                    </div>
                </div>
 
                <!-- ROW 3 -->
                <div class="space-y-2">
                    <label class="text-[11px] font-black uppercase tracking-widest text-slate-400">Use Date</label>
                    <div class="relative group">
                        <input
                            v-model="formData.useDate"
                            type="date"
                            class="w-full h-10 px-4 pl-10 rounded-lg border border-slate-200 bg-white text-[13px] font-bold text-slate-900 focus:border-[#003628] transition-all outline-none shadow-sm"
                        />
                        <Calendar class="absolute left-3.5 top-1/2 -translate-y-1/2 size-3.5 text-slate-300 group-focus-within:text-[#003628] transition-colors" />
                    </div>
                </div>
                <div class="space-y-2">
                    <label class="text-[11px] font-black uppercase tracking-widest text-slate-400">Batch No</label>
                    <div class="relative group">
                        <input
                            v-model="formData.batchNo"
                            class="w-full h-10 px-4 pl-10 rounded-lg border border-slate-200 bg-white text-[13px] font-bold text-slate-900 focus:border-[#003628] outline-none shadow-sm"
                            placeholder="Batch Number"
                        />
                        <Layers class="absolute left-3.5 top-1/2 -translate-y-1/2 size-3.5 text-slate-300 group-focus-within:text-[#003628] transition-colors" />
                    </div>
                </div>
 
                <!-- ROW 4 -->
                <div class="space-y-2">
                    <label class="text-[11px] font-black uppercase tracking-widest text-slate-400">Request Doc No</label>
                    <div class="relative group">
                        <input
                            v-model="formData.reqDocNo"
                            class="w-full h-10 px-4 pl-10 rounded-lg border border-slate-200 bg-white text-[13px] font-bold text-slate-900 focus:border-[#003628] outline-none shadow-sm"
                            placeholder="Request Number"
                        />
                        <FileText class="absolute left-3.5 top-1/2 -translate-y-1/2 size-3.5 text-slate-300 group-focus-within:text-[#003628] transition-colors" />
                    </div>
                </div>
                <div class="space-y-2">
                    <label class="text-[11px] font-black uppercase tracking-widest text-slate-400">PO Doc No</label>
                    <div class="relative group">
                        <input
                            v-model="formData.poDocNo"
                            class="w-full h-10 px-4 pl-10 rounded-lg border border-slate-200 bg-white text-[13px] font-bold text-slate-900 focus:border-[#003628] outline-none shadow-sm"
                            placeholder="PO Reference"
                        />
                        <FileCheck class="absolute left-3.5 top-1/2 -translate-y-1/2 size-3.5 text-slate-300 group-focus-within:text-[#003628] transition-colors" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
</template>
