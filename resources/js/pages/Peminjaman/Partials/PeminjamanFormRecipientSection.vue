<script setup lang="ts">
/* eslint-disable vue/no-mutating-props */
import { 
    LucideUser as User, 
    LucideBuilding as Building, 
    LucideBriefcase as Briefcase,
    LucideMail as Mail,
    LucidePhone as Phone,
    LucideUsers as Users
} from 'lucide-vue-next';
import PeminjamanSearchableSelect from './PeminjamanSearchableSelect.vue';

interface PeminjamanFormData {
    user_id: number | string;
    user_phone?: string;
    user_email?: string;
    itDrafter_id: number | string;
}


interface GroupParts {
    company?: string | null;
    department?: string | null;
}

const props = defineProps<{
    formData: PeminjamanFormData;
    users: Array<{ id: number; name: string; department_name?: string }>;
    itUsers: Array<{ id: number; name: string; department_name?: string }>;
    resolvedName: string;
    groupParts: GroupParts;
    phoneNumber: string;
    email: string;
    position: string;
    requesterReceived: string;
    requesterReceivedLabel: string;
    requesterDeptHeadLabel: string;
    itDrafterName: string;
    rememberTeam: boolean;
}
>();


const emit = defineEmits(['update:rememberTeam', 'user-change']);
</script>

<template>
<section class="space-y-6 animate-in fade-in slide-in-from-bottom-2 duration-400">
    <div class="space-y-10 max-w-4xl mx-auto">
        <!-- 1. RECIPIENT INFORMATION -->
        <div class="space-y-6">
            <div class="flex items-center gap-3 mb-2">
                <div class="h-px flex-1 bg-slate-100" />
                <span class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">Recipient Information</span>
                <div class="h-px flex-1 bg-slate-100" />
            </div>

            <div class="grid grid-cols-2 gap-x-12 gap-y-6">
                <!-- ROW 1: Name | Company -->
                <div class="space-y-2">
                    <label class="text-[11px] font-black uppercase tracking-widest text-slate-400">Name</label>
                    <div class="relative group">
                        <PeminjamanSearchableSelect
                            v-model="formData.user_id"
                            :options="users.map(u => ({ id: u.id, name: u.name, subtext: u.department_name }))"
                            placeholder="Type name or department..."
                            label="User"
                            has-icon
                            @change="emit('user-change', $event)"
                        />

                        <User class="absolute left-3 top-1/2 -translate-y-1/2 size-3.5 text-slate-300 group-focus-within:text-[#003628] transition-colors z-10" />
                    </div>
                </div>
                <div class="space-y-2">
                    <label class="text-[11px] font-black uppercase tracking-widest text-slate-400">Company</label>
                    <div class="relative group">
                        <div class="flex h-10 items-center rounded-lg border border-slate-200 bg-slate-50 pl-10 pr-4 text-[13px] font-black text-slate-400 shadow-inner italic">
                            {{ groupParts.company || 'AUTOMATIC' }}
                        </div>
                        <Building class="absolute left-3.5 top-1/2 -translate-y-1/2 size-3.5 text-slate-300" />
                    </div>
                </div>

                <!-- ROW 2: Phone | Department -->
                <div class="space-y-2">
                    <label class="text-[11px] font-black uppercase tracking-widest text-slate-400">Phone Number</label>
                    <div class="relative group">
                        <input
                            v-model="formData.user_phone"
                            class="w-full h-10 px-4 pl-10 rounded-lg border border-slate-200 bg-white text-[13px] font-bold text-slate-900 focus:border-[#003628] transition-all outline-none shadow-sm"
                            placeholder="Phone number..."
                        />
                        <Phone class="absolute left-3.5 top-1/2 -translate-y-1/2 size-3.5 text-slate-300 group-focus-within:text-[#003628] transition-colors" />
                    </div>
                </div>
                <div class="space-y-2">
                    <label class="text-[11px] font-black uppercase tracking-widest text-slate-400">Department</label>
                    <div class="relative group">
                        <div class="flex h-10 items-center rounded-lg border border-slate-200 bg-slate-50 pl-10 pr-4 text-[13px] font-black text-slate-400 shadow-inner italic">
                            {{ groupParts.department || 'AUTOMATIC' }}
                        </div>
                        <Users class="absolute left-3.5 top-1/2 -translate-y-1/2 size-3.5 text-slate-300" />
                    </div>
                </div>

                <!-- ROW 3: Email | Position -->
                <div class="space-y-2">
                    <label class="text-[11px] font-black uppercase tracking-widest text-slate-400">Email</label>
                    <div class="relative group">
                        <input
                            v-model="formData.user_email"
                            class="w-full h-10 px-4 pl-10 rounded-lg border border-slate-200 bg-white text-[13px] font-bold text-slate-900 focus:border-[#003628] transition-all outline-none shadow-sm"
                            placeholder="Email address..."
                        />
                        <Mail class="absolute left-3.5 top-1/2 -translate-y-1/2 size-3.5 text-slate-300 group-focus-within:text-[#003628] transition-colors" />
                    </div>
                </div>
                <div class="space-y-2">
                    <label class="text-[11px] font-black uppercase tracking-widest text-slate-400">Position</label>
                    <div class="relative group">
                        <div class="flex h-10 items-center rounded-lg border border-slate-200 bg-slate-50 pl-10 pr-4 text-[13px] font-black text-slate-400 shadow-inner italic">
                            {{ position || 'AUTOMATIC' }}
                        </div>
                        <Briefcase class="absolute left-3.5 top-1/2 -translate-y-1/2 size-3.5 text-slate-300" />
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. IT DRAFTER | BORROWER FIELDS -->
        <div class="space-y-6">
            <div class="grid grid-cols-2 gap-x-12 gap-y-6">
                <!-- IT Drafter (Read-only) -->
                <div class="space-y-2">
                    <label class="text-[11px] font-black uppercase tracking-widest text-slate-400">IT Drafter</label>
                    <div class="relative group">
                        <div class="flex h-10 items-center rounded-lg border border-slate-200 bg-slate-50 pl-10 pr-4 text-[13px] font-black text-slate-400 shadow-inner italic">
                            {{ itDrafterName || 'AUTOMATIC' }}
                        </div>
                        <User class="absolute left-3.5 top-1/2 -translate-y-1/2 size-3.5 text-slate-300" />
                    </div>
                </div>


                <!-- Borrower Validation -->
                <div class="space-y-2">
                    <label class="text-[11px] font-black uppercase tracking-widest text-slate-400">{{ requesterReceivedLabel }}</label>
                    <div class="relative group">
                        <div class="flex h-10 items-center rounded-lg border border-slate-200 bg-slate-50 pl-10 pr-4 text-[13px] font-black text-slate-400 shadow-inner italic">
                            {{ requesterReceived || 'AUTOMATIC' }}
                        </div>
                        <User class="absolute left-3.5 top-1/2 -translate-y-1/2 size-3.5 text-slate-300" />
                    </div>
                </div>

                <!-- Remember IT Team Checkbox -->
                <div class="flex items-center gap-2 mt-auto pb-2 col-span-2">
                    <input
                        type="checkbox"
                        :checked="rememberTeam"
                        class="size-3.5 rounded border-slate-300 text-[#003628] focus:ring-[#003628]"
                        @change="$emit('update:rememberTeam', ($event.target as HTMLInputElement).checked)"
                    />
                    <span class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Remember my IT Team</span>
                </div>
            </div>
        </div>
    </div>
</section>
</template>
