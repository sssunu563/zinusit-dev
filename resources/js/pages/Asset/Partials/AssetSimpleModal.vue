<script setup lang="ts">
/* eslint-disable vue/no-mutating-props */
interface SimpleForm {
    name: string;
    type?: string;
}

defineProps<{
    open: boolean;
    title: string;
    description: string;
    form: SimpleForm;
    error: string;
    adding: boolean;
    showType?: boolean;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'save'): void;
}>();

const statusTypes = [
    { id: 'deployable', name: 'Deployable' },
    { id: 'pending', name: 'Pending' },
    { id: 'archived', name: 'Archived' },
    { id: 'undeployable', name: 'Undeployable' },
];
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm"
            @mousedown.self="emit('close')"
        >
            <div class="bg-white w-full max-w-md rounded-3xl border border-slate-200 shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-200">
                <!-- Header -->
                <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/50">
                    <h3 class="text-sm font-black uppercase tracking-widest text-slate-900">{{ title }}</h3>
                    <p class="text-[10px] text-slate-500 mt-1 font-bold">{{ description }}</p>
                </div>

                <!-- Body -->
                <div class="p-6 space-y-4">
                    <div class="space-y-1.5">
                        <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 px-1">
                            Name <span class="text-red-500">*</span>
                        </label>
                        <input
                            v-model="form.name"
                            type="text"
                            class="w-full h-10 px-4 rounded-xl bg-slate-50 border border-slate-200 text-sm font-bold focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none"
                            placeholder="Enter name..."
                            @keyup.enter="emit('save')"
                        />
                    </div>

                    <div v-if="showType" class="space-y-1.5">
                        <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 px-1">
                            Status Type <span class="text-red-500">*</span>
                        </label>
                        <select
                            v-model="form.type"
                            class="w-full h-10 px-4 rounded-xl bg-slate-50 border border-slate-200 text-sm font-bold focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none"
                        >
                            <option v-for="type in statusTypes" :key="type.id" :value="type.id">
                                {{ type.name }}
                            </option>
                        </select>
                    </div>

                    <p v-if="error" class="text-[10px] text-red-500 font-bold uppercase px-1">
                        {{ error }}
                    </p>
                </div>

                <!-- Footer -->
                <div class="px-6 py-5 border-t border-slate-100 bg-slate-50/50 flex items-center justify-end gap-3">
                    <button
                        type="button"
                        class="px-4 h-9 text-[11px] font-bold text-slate-500 hover:text-slate-800 transition-colors"
                        @click="emit('close')"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="px-6 h-9 bg-primary rounded-xl text-[11px] font-black text-white uppercase tracking-widest shadow-lg shadow-primary/20 hover:shadow-xl active:scale-95 disabled:opacity-50 transition-all"
                        :disabled="adding || !form.name.trim()"
                        @click="emit('save')"
                    >
                        {{ adding ? 'Saving...' : 'Save' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
