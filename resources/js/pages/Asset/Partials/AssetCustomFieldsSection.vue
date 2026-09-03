<script setup lang="ts">
import { ChevronDown } from 'lucide-vue-next';
import { ref, onMounted, onBeforeUnmount } from 'vue';

interface ModelField {
    name: string;
    db_column_name: string;
    format: string;
    type: string; // "text" | "listbox" | "checkbox" | "textarea"
    field_values: string | null; // newline-separated option list
    required: boolean;
}

const props = defineProps<{
    fields: ModelField[];
    values: Record<string, string>;
}>();

const emit = defineEmits<{
    (e: 'update:values', value: Record<string, string>): void;
}>();

const open = ref(true);

const updateValue = (key: string, value: string) => {
    emit('update:values', { ...props.values, [key]: value });
};

/**
 * UI type decision:
 *  - type = checkbox                → multi-checkbox group (value = comma-separated)
 *  - type = listbox/radio + field_values → select dropdown
 *  - field_values present (fallback) → select dropdown
 *  - type = textarea                → textarea
 *  - format = BOOLEAN               → yes/no select
 *  - format = DATE                  → date input
 *  - else                           → text/email/url input
 */
const getFieldUiType = (
    type: string,
    fieldValues: string | null,
    format: string,
): 'checkbox' | 'select' | 'textarea' | 'boolean' | 'date' | 'text' => {
    if (type === 'checkbox') return 'checkbox';
    if (type === 'listbox' || type === 'radio') return 'select';
    if (fieldValues) return 'select'; // fallback: has options → dropdown
    if (type === 'textarea') return 'textarea';
    if (format === 'BOOLEAN') return 'boolean';
    if (format === 'DATE') return 'date';
    return 'text';
};

/** Split newline- or pipe-separated field_values string into option list. */
const getListOptions = (fieldValues: string | null): string[] => {
    const value = typeof fieldValues === 'string' ? fieldValues : '';
    if (!value) return [];
    const sep = value.includes('\n') ? '\n' : '|';
    return value
        .split(sep)
        .map((v) => v.trim())
        .filter(Boolean);
};

/** Get current checked values as an array from comma-separated string. */
const getCheckedValues = (key: string): string[] => {
    const raw = String(props.values[key] ?? '');
    return raw
        ? raw
              .split(',')
              .map((v) => v.trim())
              .filter(Boolean)
        : [];
};

/** Toggle a single checkbox option in the comma-separated value string. */
const toggleCheckbox = (key: string, option: string, checked: boolean) => {
    const current = getCheckedValues(key);
    const next = checked
        ? [...current.filter((v) => v !== option), option]
        : current.filter((v) => v !== option);
    updateValue(key, next.join(','));
};

const getInputType = (format: string): string => {
    if (format === 'EMAIL') return 'email';
    if (format === 'URL') return 'url';
    return 'text';
};

/** Track which checkbox-dropdown panels are open. */
const openDropdowns = ref<Record<string, boolean>>({});

const toggleDropdown = (key: string) => {
    const wasOpen = openDropdowns.value[key];
    openDropdowns.value = {};
    if (!wasOpen) openDropdowns.value[key] = true;
};

const closeAllDropdowns = () => {
    openDropdowns.value = {};
};

onMounted(() => {
    document.addEventListener('click', closeAllDropdowns);
});
onBeforeUnmount(() => document.removeEventListener('click', closeAllDropdowns));
</script>

<template>
    <div class="app-form-classic-naked border-t border-slate-50">
        <!-- Section Header -->
        <button
            type="button"
            class="app-form-classic-accordion w-full"
            @click="open = !open"
        >
            <ChevronDown
                class="size-4 text-slate-300 transition-transform"
                :class="{ '-rotate-90': !open }"
            />
            <span
                class="text-[10px] font-black tracking-[0.2em] text-slate-400 uppercase"
            >
                Custom Fields
            </span>
        </button>

        <!-- Fields Rows -->
        <div v-show="open" class="py-2">
            <div
                v-if="!fields.length"
                class="px-8 py-4 text-[10px] font-bold tracking-widest text-slate-400 uppercase italic"
            >
                No custom fields defined for this model.
            </div>
            <div
                v-for="field in fields"
                :key="field.db_column_name"
                class="app-form-classic-row"
            >
                <label class="app-form-classic-label">
                    {{ field.name }}
                    <span v-if="field.required" class="app-form-label-required"
                        >*</span
                    >
                </label>

                <div class="app-form-classic-input-group max-w-2xl">
                    <!-- CHECKBOX DROPDOWN -->
                    <div
                        v-if="
                            getFieldUiType(
                                field.type,
                                field.field_values,
                                field.format,
                            ) === 'checkbox'
                        "
                        class="relative"
                        @click.stop
                    >
                        <button
                            type="button"
                            class="app-input-shell app-input-compact flex w-full items-center justify-between gap-2 text-left"
                            @click="toggleDropdown(field.db_column_name)"
                        >
                            <span
                                class="truncate"
                                :class="
                                    getCheckedValues(field.db_column_name)
                                        .length
                                        ? 'font-bold text-slate-900'
                                        : 'text-slate-400'
                                "
                            >
                                {{
                                    getCheckedValues(field.db_column_name)
                                        .length
                                        ? getCheckedValues(
                                              field.db_column_name,
                                          ).join(', ')
                                        : '— select multiple —'
                                }}
                            </span>
                            <ChevronDown
                                class="size-3 shrink-0 text-slate-500 transition-transform duration-150"
                                :class="{
                                    'rotate-180':
                                        openDropdowns[field.db_column_name],
                                }"
                            />
                        </button>

                        <div
                            v-if="openDropdowns[field.db_column_name]"
                            class="absolute top-full right-0 left-0 z-30 mt-1 max-h-60 overflow-y-auto rounded-xl border border-slate-200 bg-white p-2 shadow-2xl"
                        >
                            <label
                                v-for="opt in getListOptions(
                                    field.field_values,
                                )"
                                :key="opt"
                                class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2 py-1.5 text-[11px] text-slate-700 transition-colors hover:bg-primary/5"
                            >
                                <input
                                    type="checkbox"
                                    class="h-4 w-4 shrink-0 rounded border-slate-300 bg-white accent-primary"
                                    :checked="
                                        getCheckedValues(
                                            field.db_column_name,
                                        ).includes(opt)
                                    "
                                    @change="
                                        toggleCheckbox(
                                            field.db_column_name,
                                            opt,
                                            ($event.target as HTMLInputElement)
                                                .checked,
                                        )
                                    "
                                />
                                <span>{{ opt }}</span>
                            </label>
                        </div>
                    </div>

                    <!-- SELECT -->
                    <div
                        v-else-if="
                            getFieldUiType(
                                field.type,
                                field.field_values,
                                field.format,
                            ) === 'select'
                        "
                        class="app-input-classic-wrapper"
                    >
                        <select
                            :value="values[field.db_column_name] || ''"
                            class="app-select-shell app-select-compact"
                            @change="
                                updateValue(
                                    field.db_column_name,
                                    ($event.target as HTMLSelectElement).value,
                                )
                            "
                        >
                            <option value="">— select —</option>
                            <option
                                v-for="opt in getListOptions(
                                    field.field_values,
                                )"
                                :key="opt"
                                :value="opt"
                            >
                                {{ opt }}
                            </option>
                        </select>
                        <div
                            v-if="field.required"
                            class="app-input-indicator"
                        />
                    </div>

                    <!-- TEXTAREA -->
                    <textarea
                        v-else-if="
                            getFieldUiType(
                                field.type,
                                field.field_values,
                                field.format,
                            ) === 'textarea'
                        "
                        :value="values[field.db_column_name] || ''"
                        rows="2"
                        class="app-textarea-shell bg-white text-xs"
                        :placeholder="field.name"
                        @input="
                            updateValue(
                                field.db_column_name,
                                ($event.target as HTMLTextAreaElement).value,
                            )
                        "
                    />

                    <!-- BOOLEAN -->
                    <div
                        v-else-if="
                            getFieldUiType(
                                field.type,
                                field.field_values,
                                field.format,
                            ) === 'boolean'
                        "
                        class="app-input-classic-wrapper"
                    >
                        <select
                            :value="values[field.db_column_name] ?? ''"
                            class="app-select-shell app-select-compact"
                            @change="
                                updateValue(
                                    field.db_column_name,
                                    ($event.target as HTMLSelectElement).value,
                                )
                            "
                        >
                            <option value="">— select —</option>
                            <option value="1">Yes</option>
                            <option value="0">No</option>
                        </select>
                        <div
                            v-if="field.required"
                            class="app-input-indicator"
                        />
                    </div>

                    <!-- DATE -->
                    <div
                        v-else-if="
                            getFieldUiType(
                                field.type,
                                field.field_values,
                                field.format,
                            ) === 'date'
                        "
                        class="app-input-classic-wrapper"
                    >
                        <input
                            :value="values[field.db_column_name] || ''"
                            type="date"
                            class="app-input-shell app-input-compact"
                            @input="
                                updateValue(
                                    field.db_column_name,
                                    ($event.target as HTMLInputElement).value,
                                )
                            "
                        />
                        <div
                            v-if="field.required"
                            class="app-input-indicator"
                        />
                    </div>

                    <!-- TEXT (default) -->
                    <div v-else class="app-input-classic-wrapper">
                        <input
                            :value="values[field.db_column_name] || ''"
                            :type="getInputType(field.format)"
                            class="app-input-shell app-input-compact"
                            :placeholder="
                                field.format !== 'ANY'
                                    ? field.format
                                    : field.name
                            "
                            @input="
                                updateValue(
                                    field.db_column_name,
                                    ($event.target as HTMLInputElement).value,
                                )
                            "
                        />
                        <div
                            v-if="field.required"
                            class="app-input-indicator"
                        />
                    </div>

                    <p
                        v-if="
                            getFieldUiType(
                                field.type,
                                field.field_values,
                                field.format,
                            ) === 'text' && field.format !== 'ANY'
                        "
                        class="mt-0.5 text-[10px] text-slate-500 italic"
                    >
                        Format: {{ field.format }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
