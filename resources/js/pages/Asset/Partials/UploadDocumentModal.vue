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
    notes: '',
    document: null as File | null,
});

function handleFile(e: Event) {
    const target = e.target as HTMLInputElement;
    form.document = target.files?.[0] ?? null;
}

function submit() {
    form.post(`/asset/item/${props.assetId}/document`, {
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
        <div
            v-if="show"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-[2px] p-4"
            @click.self="emit('close')"
        >
            <div class="app-modal-surface w-full max-w-md rounded-[20px]">
                <!-- Header -->
                <div
                    class="flex items-center justify-between rounded-t-[20px] border-b border-border bg-muted/30 px-5 py-4"
                >
                    <div>
                        <p class="app-section-title">Upload Dokumen</p>
                        <p class="app-upload-meta">
                            File akan di-upload ke Snipe-IT sebagai dokumen aset
                        </p>
                    </div>
                    <button
                        type="button"
                        class="app-button-ghost app-button-compact"
                        @click="emit('close')"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <!-- Body -->
                <form class="flex flex-col gap-4 p-5" @submit.prevent="submit">
                    <div>
                        <label class="app-form-label"
                            >File (PDF / Gambar / Excel) *</label
                        >
                        <input
                            type="file"
                            class="app-file-shell w-full"
                            accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx"
                            @change="handleFile"
                        />
                        <p
                            v-if="form.errors.document"
                            class="app-form-error-sm"
                        >
                            {{ form.errors.document }}
                        </p>
                        <p class="app-upload-meta mt-1">Maks 10 MB.</p>
                    </div>

                    <div>
                        <label class="app-form-label">Catatan</label>
                        <input
                            v-model="form.notes"
                            type="text"
                            class="app-input-shell w-full"
                            placeholder="Opsional"
                        />
                        <p v-if="form.errors.notes" class="app-form-error-sm">
                            {{ form.errors.notes }}
                        </p>
                    </div>

                    <div class="flex justify-end gap-3">
                        <button
                            type="button"
                            class="app-button-secondary"
                            @click="emit('close')"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            class="app-button-primary"
                            :disabled="form.processing"
                        >
                            {{ form.processing ? 'Mengupload...' : 'Upload' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>
