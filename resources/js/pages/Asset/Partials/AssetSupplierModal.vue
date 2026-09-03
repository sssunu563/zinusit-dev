<template>
    <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4 backdrop-blur-[2px]"
    >
        <div class="bg-white w-full max-w-md rounded-[20px] shadow-2xl overflow-hidden border border-slate-200 animate-in zoom-in-95 duration-200">
            <!-- Modal Header -->
            <div class="px-6 py-3 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                <div>
                    <h3 class="text-base font-black text-slate-800 tracking-tight">Create Supplier</h3>
                    <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-0.5">Vendor details</p>
                </div>
                <button @click="emit('close')" class="size-7 flex items-center justify-center rounded-full hover:bg-slate-200/50 text-slate-400 hover:text-slate-600 transition-all">
                    <X class="size-4" />
                </button>
            </div>

            <!-- Modal Body -->
            <div class="p-5 space-y-3.5 max-h-[80vh] overflow-y-auto">
                <!-- Supplier Name -->
                <div class="flex items-center gap-4">
                    <label class="w-1/3 text-right text-[11px] font-black text-slate-400 uppercase tracking-wider">Supplier Name:</label>
                    <div class="flex-1 relative">
                        <input
                            v-model="form.name"
                            type="text"
                            class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-xs px-3 h-9 rounded-lg focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all pr-4"
                            placeholder="e.g. Dell Inc."
                        />
                        <div class="absolute right-0 top-1.5 bottom-1.5 w-1 bg-[#f39c12] rounded-l-full" />
                    </div>
                </div>

                <!-- Contact Name -->
                <div class="flex items-center gap-4">
                    <label class="w-1/3 text-right text-[11px] font-black text-slate-400 uppercase tracking-wider">Contact Name:</label>
                    <div class="flex-1">
                        <input
                            v-model="form.contact_name"
                            type="text"
                            class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-xs px-3 h-9 rounded-lg focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all"
                        />
                    </div>
                </div>

                <!-- URL -->
                <div class="flex items-center gap-4">
                    <label class="w-1/3 text-right text-[11px] font-black text-slate-400 uppercase tracking-wider">URL:</label>
                    <div class="flex-1">
                        <input
                            v-model="form.url"
                            type="text"
                            class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-xs px-3 h-9 rounded-lg focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all"
                            placeholder="https://..."
                        />
                    </div>
                </div>

                <!-- Phone -->
                <div class="flex items-center gap-4">
                    <label class="w-1/3 text-right text-[11px] font-black text-slate-400 uppercase tracking-wider">Phone:</label>
                    <div class="flex-1">
                        <input
                            v-model="form.phone"
                            type="text"
                            class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-xs px-3 h-9 rounded-lg focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all"
                        />
                    </div>
                </div>

                <!-- Fax -->
                <div class="flex items-center gap-4">
                    <label class="w-1/3 text-right text-[11px] font-black text-slate-400 uppercase tracking-wider">Fax:</label>
                    <div class="flex-1">
                        <input
                            v-model="form.fax"
                            type="text"
                            class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-xs px-3 h-9 rounded-lg focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all"
                        />
                    </div>
                </div>

                <!-- Email -->
                <div class="flex items-center gap-4">
                    <label class="w-1/3 text-right text-[11px] font-black text-slate-400 uppercase tracking-wider">Email:</label>
                    <div class="flex-1">
                        <input
                            v-model="form.email"
                            type="email"
                            class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-xs px-3 h-9 rounded-lg focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all"
                        />
                    </div>
                </div>

                <!-- Notes -->
                <div class="flex items-start gap-4">
                    <label class="w-1/3 text-right text-[11px] font-black text-slate-400 uppercase tracking-wider pt-2">Notes:</label>
                    <div class="flex-1">
                        <textarea
                            v-model="form.notes"
                            rows="3"
                            class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-xs p-3 rounded-lg focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all resize-none"
                        />
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
                    class="flex items-center gap-2 rounded-lg bg-[#3c8dbc] px-5 h-9 text-[11px] font-black text-white shadow-lg shadow-[#3c8dbc]/20 transition-all hover:brightness-110 active:scale-95 disabled:opacity-50"
                    :disabled="adding"
                    @click="emit('save')"
                >
                    <Save v-if="!adding" class="size-3.5" />
                    <span>{{ adding ? '...' : 'Save' }}</span>
                </button>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
 
import { X, Save } from 'lucide-vue-next';

interface SupplierForm {
    name: string;
    contact_name: string;
    url: string;
    phone: string;
    fax: string;
    email: string;
    notes: string;
}

defineProps<{
    open: boolean;
    form: SupplierForm;
    error: string;
    adding: boolean;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'save'): void;
}>();
</script>
