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

const maxPurchaseDate = (() => {
    const today = new Date();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');

    return `${today.getFullYear()}-${month}-${day}`;
})();

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
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4 backdrop-blur-sm"
                @click.self="emit('close')"
            >
                <div
                    class="w-full max-w-lg overflow-hidden rounded-[32px] border border-slate-200 bg-white shadow-2xl"
                >
                    <!-- Header -->
                    <div
                        class="flex items-center justify-between border-b border-slate-100 bg-slate-50/50 px-8 py-6"
                    >
                        <div>
                            <h3
                                class="text-sm font-black tracking-widest text-slate-900 uppercase"
                            >
                                Replenish Stock
                            </h3>
                            <p
                                class="mt-1 text-[11px] font-medium tracking-tight text-slate-400 uppercase"
                            >
                                Transmit purchase details & PO identity to
                                Snipe-IT
                            </p>
                        </div>
                        <button
                            type="button"
                            class="flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-400 shadow-sm transition-all hover:border-primary/50 hover:text-primary"
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
                            <label
                                class="ml-1 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                >Quantity (Qty) *</label
                            >
                            <input
                                v-model.number="form.qty"
                                type="number"
                                min="1"
                                class="h-11 w-full rounded-xl border border-slate-100 bg-slate-50 px-4 text-[13px] font-bold text-slate-900 transition-all outline-none focus:border-primary/50"
                            />
                            <p
                                v-if="form.errors.qty"
                                class="mt-1 ml-1 text-[10px] font-bold text-rose-500 uppercase"
                            >
                                {{ form.errors.qty }}
                            </p>
                        </div>

                        <!-- PO Number -->
                        <div class="space-y-2">
                            <label
                                class="ml-1 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                >PO Ident / Reference</label
                            >
                            <input
                                v-model="form.po_number"
                                type="text"
                                class="h-11 w-full rounded-xl border border-slate-100 bg-slate-50 px-4 text-[13px] font-bold text-slate-900 transition-all outline-none focus:border-primary/50"
                                placeholder="PO-2026-X"
                            />
                            <p
                                v-if="form.errors.po_number"
                                class="mt-1 ml-1 text-[10px] font-bold text-rose-500 uppercase"
                            >
                                {{ form.errors.po_number }}
                            </p>
                        </div>

                        <!-- Purchase Date -->
                        <div class="space-y-2">
                            <label
                                class="ml-1 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                >Transaction Date</label
                            >
                            <input
                                v-model="form.purchase_date"
                                type="date"
                                :max="maxPurchaseDate"
                                class="h-11 w-full rounded-xl border border-slate-100 bg-slate-50 px-4 text-[13px] font-bold text-slate-900 transition-all outline-none focus:border-primary/50"
                            />
                            <p
                                v-if="form.errors.purchase_date"
                                class="mt-1 ml-1 text-[10px] font-bold text-rose-500 uppercase"
                            >
                                {{ form.errors.purchase_date }}
                            </p>
                        </div>

                        <!-- Notes -->
                        <div class="space-y-2">
                            <label
                                class="ml-1 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                >Contextual Notes</label
                            >
                            <input
                                v-model="form.notes"
                                type="text"
                                class="h-11 w-full rounded-xl border border-slate-100 bg-slate-50 px-4 text-[13px] font-bold text-slate-900 transition-all outline-none focus:border-primary/50"
                                placeholder="Optional details"
                            />
                            <p
                                v-if="form.errors.notes"
                                class="mt-1 ml-1 text-[10px] font-bold text-rose-500 uppercase"
                            >
                                {{ form.errors.notes }}
                            </p>
                        </div>

                        <!-- Document upload -->
                        <div class="space-y-2 sm:col-span-2">
                            <label
                                class="ml-1 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                >Documentation (PDF / Image / Sheet)</label
                            >
                            <input
                                type="file"
                                class="w-full cursor-pointer text-xs font-bold text-slate-500 transition-all file:mr-4 file:rounded-xl file:border-0 file:bg-primary/10 file:px-6 file:py-2.5 file:text-[10px] file:font-black file:text-primary file:uppercase hover:file:bg-primary/20"
                                accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx"
                                @change="handleFile"
                            />
                            <p
                                v-if="form.errors.document"
                                class="mt-1 ml-1 text-[10px] font-bold text-rose-500 uppercase"
                            >
                                {{ form.errors.document }}
                            </p>
                            <p
                                class="mt-2 text-[9px] font-bold tracking-wide text-slate-400 uppercase opacity-60"
                            >
                                Maximum 10 MB. Metadata will be mirrored to the
                                Snipe-IT vault.
                            </p>
                        </div>

                        <!-- Actions -->
                        <div
                            class="mt-4 flex items-center justify-end gap-3 sm:col-span-2"
                        >
                            <button
                                type="button"
                                class="h-11 rounded-2xl bg-slate-100 px-8 text-[11px] font-black tracking-widest text-slate-600 uppercase transition-all hover:bg-slate-200 active:scale-[0.98]"
                                @click="emit('close')"
                            >
                                Abort
                            </button>
                            <button
                                type="submit"
                                class="hover:bg-primary-dark h-11 rounded-2xl bg-primary px-8 text-[11px] font-black tracking-widest text-white uppercase shadow-xl shadow-primary/20 transition-all hover:shadow-2xl active:scale-[0.98]"
                                :disabled="form.processing"
                            >
                                {{
                                    form.processing
                                        ? 'Syncing...'
                                        : 'Commit Transaction'
                                }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
