<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { X } from 'lucide-vue-next';

const props = defineProps<{
    show: boolean;
    assetId: number;
    assetType: string;
}>();

const emit = defineEmits<{ close: [] }>();

const form = useForm({
    type: props.assetType,
    qty: 1 as number,
    po_number: '',
    purchase_date: '',
    notes: '',
    document: null as File | null,
});

function handleFile(e: Event) {
    const target = e.target as HTMLInputElement;
    form.document = target.files?.[0] ?? null;
}

function submit() {
    form.post(`/asset/item/${props.assetId}/stock`, {
        forceFormData: true,
        onSuccess: () => {
            emit('close');
            form.reset();
        },
    });
}
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="show"
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4"
                @click.self="emit('close')"
            >
                <div class="bg-white w-full max-w-lg rounded-[32px] shadow-2xl overflow-hidden border border-slate-200">
                    <!-- Header -->
                    <div class="px-8 py-6 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-black uppercase tracking-widest text-slate-900">Replenish Stock</h3>
                            <p class="text-[11px] font-medium text-slate-400 mt-1 uppercase tracking-tight">
                                Transmit purchase details & PO identity to Snipe-IT
                            </p>
                        </div>
                        <button
                            type="button"
                            class="h-8 w-8 rounded-full border border-slate-200 bg-white flex items-center justify-center text-slate-400 hover:text-primary hover:border-primary/50 transition-all shadow-sm"
                            @click="emit('close')"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>

                    <!-- Body -->
                    <form
                        class="grid gap-6 p-8 sm:grid-cols-2"
                        @submit.prevent="submit"
                    >
                        <!-- Qty -->
                        <div class="space-y-2">
                            <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Quantity (Qty) *</label>
                            <input
                                v-model.number="form.qty"
                                type="number"
                                min="1"
                                class="w-full h-11 px-4 rounded-xl border border-slate-100 bg-slate-50 text-[13px] font-bold text-slate-900 outline-none focus:border-primary/50 transition-all"
                            />
                            <p v-if="form.errors.qty" class="text-[10px] font-bold text-rose-500 mt-1 ml-1 uppercase">
                                {{ form.errors.qty }}
                            </p>
                        </div>

                        <!-- PO Number -->
                        <div class="space-y-2">
                            <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">PO Ident / Reference</label>
                            <input
                                v-model="form.po_number"
                                type="text"
                                class="w-full h-11 px-4 rounded-xl border border-slate-100 bg-slate-50 text-[13px] font-bold text-slate-900 outline-none focus:border-primary/50 transition-all"
                                placeholder="PO-2026-X"
                            />
                            <p v-if="form.errors.po_number" class="text-[10px] font-bold text-rose-500 mt-1 ml-1 uppercase">
                                {{ form.errors.po_number }}
                            </p>
                        </div>

                        <!-- Purchase Date -->
                        <div class="space-y-2">
                            <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Transaction Date</label>
                            <input
                                v-model="form.purchase_date"
                                type="date"
                                class="w-full h-11 px-4 rounded-xl border border-slate-100 bg-slate-50 text-[13px] font-bold text-slate-900 outline-none focus:border-primary/50 transition-all"
                            />
                            <p v-if="form.errors.purchase_date" class="text-[10px] font-bold text-rose-500 mt-1 ml-1 uppercase">
                                {{ form.errors.purchase_date }}
                            </p>
                        </div>

                        <!-- Notes -->
                        <div class="space-y-2">
                            <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Contextual Notes</label>
                            <input
                                v-model="form.notes"
                                type="text"
                                class="w-full h-11 px-4 rounded-xl border border-slate-100 bg-slate-50 text-[13px] font-bold text-slate-900 outline-none focus:border-primary/50 transition-all"
                                placeholder="Optional details"
                            />
                            <p v-if="form.errors.notes" class="text-[10px] font-bold text-rose-500 mt-1 ml-1 uppercase">
                                {{ form.errors.notes }}
                            </p>
                        </div>

                        <!-- Document upload -->
                        <div class="sm:col-span-2 space-y-2">
                            <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Documentation (PDF / Image / Sheet)</label>
                            <input
                                type="file"
                                class="w-full text-xs font-bold text-slate-500 file:mr-4 file:py-2.5 file:px-6 file:rounded-xl file:border-0 file:text-[10px] file:font-black file:uppercase file:bg-primary/10 file:text-primary hover:file:bg-primary/20 transition-all cursor-pointer"
                                accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx"
                                @change="handleFile"
                            />
                            <p v-if="form.errors.document" class="text-[10px] font-bold text-rose-500 mt-1 ml-1 uppercase">
                                {{ form.errors.document }}
                            </p>
                            <p class="text-[9px] font-bold text-slate-400 mt-2 uppercase tracking-wide opacity-60">
                                Maximum 10 MB. Metadata will be mirrored to the Snipe-IT vault.
                            </p>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center justify-end gap-3 mt-4 sm:col-span-2">
                            <button
                                type="button"
                                class="h-11 px-8 rounded-2xl bg-slate-100 text-[11px] font-black uppercase tracking-widest text-slate-600 hover:bg-slate-200 transition-all active:scale-[0.98]"
                                @click="emit('close')"
                            >
                                Abort
                            </button>
                            <button
                                type="submit"
                                class="h-11 px-8 rounded-2xl bg-primary text-[11px] font-black uppercase tracking-widest text-white shadow-xl shadow-primary/20 hover:bg-primary-dark hover:shadow-2xl transition-all active:scale-[0.98]"
                                :disabled="form.processing"
                            >
                                {{ form.processing ? 'Syncing...' : 'Commit Transaction' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
