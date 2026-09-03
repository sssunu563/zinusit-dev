<script setup lang="ts">
/* eslint-disable vue/no-mutating-props */
interface CategoryForm {
    name: string;
}

defineProps<{
    open: boolean;
    form: CategoryForm;
    error: string;
    adding: boolean;
    currentType: string;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'save'): void;
}>();
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            @mousedown.self="emit('close')"
        >
            <div class="app-form-panel w-full max-w-md border border-white/[0.05] shadow-2xl">
                <!-- Header -->
                <div class="flex items-center justify-between px-5 py-4 border-b border-white/[0.04] bg-white/5">
                    <div>
                        <h3 class="text-xs font-black uppercase tracking-widest text-white">Add New Category</h3>
                        <p class="text-[10px] text-slate-500 mt-0.5">Asset types for grouping and policy.</p>
                    </div>
                    <button
                        type="button"
                        class="app-button-ghost app-button-compact"
                        @click="emit('close')"
                    >
                        ✕
                    </button>
                </div>

                <!-- Body -->
                <div class="p-5">
                    <div class="app-form-field">
                        <label class="app-form-label">
                            Category Name <span class="app-required-mark">*</span>
                        </label>
                        <input
                            v-model="form.name"
                            type="text"
                            class="app-input-shell app-input-compact"
                            placeholder="e.g. Laptops"
                            @keyup.enter="emit('save')"
                        />
                    </div>
                    <p v-if="error" class="mt-2 text-[10px] text-red-500 font-bold uppercase">
                        {{ error }}
                    </p>
                </div>

                <!-- Footer -->
                <div class="p-5 border-t border-white/[0.04] flex items-center justify-end gap-3">
                    <button
                        type="button"
                        class="app-button-secondary app-button-compact"
                        @click="emit('close')"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="app-button-primary app-button-compact min-w-[100px]"
                        :disabled="adding"
                        @click="emit('save')"
                    >
                        {{ adding ? 'Adding...' : 'Add Category' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
