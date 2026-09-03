<script setup lang="ts">
import { LucideCheckCircle2 as CheckCircle2, LucideClock as Clock, LucideShieldCheck as ShieldCheck, LucideUserCheck as UserCheck } from 'lucide-vue-next';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { getStbUserLabel } from '@/utils/stbDirectory';

interface STB {
    id: number;
    user_id: number | null;
    it_drafter_id: number | null;
    it_checker_id: number | null;
    it_approved_id: number | null;
    it_drafter_signature_path?: string | null;
    it_checker_signature_path?: string | null;
    it_approved_signature_path?: string | null;
    requester_received_signature_path?: string | null;
    requester_dept_head_signature_path?: string | null;
}

const props = defineProps<{
    open: boolean;
    docId: string;
    stb: STB;
}>();

defineEmits<{
    (e: 'close'): void;
}>();

const roles = [
    {
        id: 'it_drafter',
        label: 'IT Pembuat',
        pathField: 'it_drafter_signature_path',
        userField: 'it_drafter_id',
    },
    {
        id: 'it_checker',
        label: 'IT Pemeriksa',
        pathField: 'it_checker_signature_path',
        userField: 'it_checker_id',
    },
    {
        id: 'it_approved',
        label: 'IT Penyetuju',
        pathField: 'it_approved_signature_path',
        userField: 'it_approved_id',
    },
    {
        id: 'requester_received',
        label: 'Penerima',
        pathField: 'requester_received_signature_path',
        userField: 'user_id',
    },
    {
        id: 'requester_dept_head',
        label: 'Atasan Dept',
        pathField: 'requester_dept_head_signature_path',
        userField: null,
    },
];

const isSigned = (role: any) => {
    return !!props.stb[role.pathField as keyof STB];
};

const getSignerName = (role: any) => {
    if (role.userField) {
        const userId = props.stb[role.userField as keyof STB];
        return getStbUserLabel(typeof userId === 'number' ? userId : null);
    }
    return '-';
};
</script>

<template>
    <Dialog :open="open" @update:open="(val) => !val && $emit('close')">
        <DialogContent class="max-w-md p-8 rounded-[24px] border-none shadow-2xl bg-white">
            <DialogHeader class="mb-6">
                <div class="flex items-center gap-3">
                    <div class="size-10 rounded-xl bg-slate-50 flex items-center justify-center text-[#003628]">
                        <ShieldCheck class="size-5" />
                    </div>
                    <div>
                        <DialogTitle class="text-lg font-black text-slate-900 leading-none">Status Validasi</DialogTitle>
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mt-1">{{ docId }}</p>
                    </div>
                </div>
            </DialogHeader>

            <div class="space-y-3">
                <div
                    v-for="role in roles"
                    :key="role.id"
                    class="flex items-center justify-between rounded-xl border p-3.5 transition-all"
                    :class="
                        isSigned(role)
                            ? 'bg-emerald-50/30 border-emerald-100'
                            : 'bg-white border-slate-100'
                    "
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-lg"
                            :class="
                                isSigned(role)
                                    ? 'bg-emerald-500 text-white'
                                    : 'bg-slate-50 text-slate-300'
                            "
                        >
                            <CheckCircle2
                                v-if="isSigned(role)"
                                class="h-4 w-4"
                            />
                            <Clock v-else class="h-4 w-4" />
                        </div>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">
                                {{ role.label }}
                            </p>
                            <p
                                class="text-[12px] font-bold text-slate-700 mt-0.5"
                            >
                                {{ role.userField ? getSignerName(role) : (isSigned(role) ? 'Terverifikasi' : 'Menunggu...') }}
                            </p>
                        </div>
                    </div>
                    
                    <div v-if="isSigned(role)">
                         <UserCheck class="size-3.5 text-emerald-500" />
                    </div>
                </div>
            </div>

            <div class="mt-8 flex justify-end">
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
