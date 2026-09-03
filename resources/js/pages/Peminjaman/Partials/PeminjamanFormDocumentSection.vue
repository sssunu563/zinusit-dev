<script setup lang="ts">
/* eslint-disable vue/no-mutating-props */
import { 
    LucideMapPin as MapPin,
    LucideHash as Hash,
    LucideCalendar as Calendar,
    LucideLayers as Layers,
    LucideFileText as FileText,
    LucideFileCheck as FileCheck,
    LucideClock as Clock
} from 'lucide-vue-next';
import type {
    LoanReferenceOption,
} from '@/pages/Peminjaman/types';
interface PeminjamanFormData {
    useDate: string;
    expectedReturnDate: string;
    movementType: string;
}



interface FormErrors {
    movementType?: string;
    linkedLoanId?: string;
    user_id?: string;
    group_id?: string;
}

defineProps<{
    docIdDisplay: string;
    formData: PeminjamanFormData;
    users: Array<{ id: number; name: string }>;
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
        <!-- 1. DOCUMENT INFORMATION -->
        <div class="space-y-6">
            <div class="flex items-center gap-3 mb-2">
                <div class="h-px flex-1 bg-slate-100" />
                <span class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">Document Information</span>
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

                <!-- ROW 2: Loan Date -->
                <div class="space-y-2">
                    <label class="text-[11px] font-black uppercase tracking-widest text-slate-400">Loan Date</label>
                    <div class="relative group">
                        <input
                            v-model="formData.useDate"
                            type="date"
                            :readonly="formData.movementType === 'return'"
                            :disabled="formData.movementType === 'return'"
                            :class="[
                                'w-full h-10 px-4 pl-10 rounded-lg border text-[13px] font-bold outline-none shadow-sm',
                                formData.movementType === 'return' 
                                    ? 'border-slate-200 bg-slate-50 text-slate-400 cursor-not-allowed'
                                    : 'border-slate-200 bg-white text-slate-900 focus:border-[#003628]'
                            ]"
                        />
                        <Calendar class="absolute left-3.5 top-1/2 -translate-y-1/2 size-3.5 text-slate-300 group-focus-within:text-[#003628] transition-colors" />
                    </div>
                    <p v-if="formData.movementType === 'return'" class="text-[10px] text-slate-400 italic">
                        Loan date dari dokumen peminjaman asal (read-only)
                    </p>
                </div>

                <!-- ROW 3: Expected Return Date -->
                <div class="space-y-2">
                    <label 
                        :class="[
                            'text-[11px] font-black uppercase tracking-widest',
                            formData.movementType === 'return' ? 'text-blue-600' : 'text-emerald-600'
                        ]"
                    >
                        {{ formData.movementType === 'return' ? 'Return Date' : 'Expected Return' }}
                    </label>
                    <div class="relative group">
                        <input
                            v-model="formData.expectedReturnDate"
                            type="date"
                            :class="[
                                'w-full h-10 px-4 pl-10 rounded-lg border text-[13px] font-bold transition-all outline-none shadow-sm',
                                formData.movementType === 'return'
                                    ? 'border-blue-100 bg-blue-50/30 text-blue-900 focus:border-blue-500'
                                    : 'border-emerald-100 bg-emerald-50/30 text-emerald-900 focus:border-emerald-500'
                            ]"
                        />
                        <Clock 
                            :class="[
                                'absolute left-3.5 top-1/2 -translate-y-1/2 size-3.5 transition-colors',
                                formData.movementType === 'return'
                                    ? 'text-blue-300 group-focus-within:text-blue-500'
                                    : 'text-emerald-300 group-focus-within:text-emerald-500'
                            ]"
                        />
                    </div>
                    <p 
                        v-if="formData.movementType === 'return'" 
                        class="text-[10px] text-blue-600 font-medium"
                    >
                        Tanggal asset dikembalikan
                    </p>
                    <p 
                        v-else 
                        class="text-[10px] text-emerald-600 italic"
                    >
                        Perkiraan tanggal pengembalian (opsional)
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>
</template>
