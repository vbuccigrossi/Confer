<script setup>
import { computed } from 'vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import Checkbox from '@/Components/Checkbox.vue';

const props = defineProps({
    /** Configuration schema from bot definition */
    schema: {
        type: Object,
        default: () => ({ fields: [] }),
    },
    /** Current configuration values */
    modelValue: {
        type: Object,
        default: () => ({}),
    },
    /** Validation errors keyed by field name */
    errors: {
        type: Object,
        default: () => ({}),
    },
    /** Whether the form is disabled */
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue']);

const fields = computed(() => props.schema?.fields || []);

const hasFields = computed(() => fields.value.length > 0);

function getValue(fieldName) {
    return props.modelValue[fieldName] ?? '';
}

function updateValue(fieldName, value) {
    emit('update:modelValue', {
        ...props.modelValue,
        [fieldName]: value,
    });
}

function getInputType(field) {
    switch (field.type) {
        case 'secret':
            return 'password';
        case 'url':
            return 'url';
        case 'number':
            return 'number';
        default:
            return 'text';
    }
}
</script>

<template>
    <div v-if="hasFields" class="space-y-4">
        <div v-for="field in fields" :key="field.name" class="space-y-1">
            <!-- Label -->
            <InputLabel :for="`config-${field.name}`">
                {{ field.label || field.name }}
                <span v-if="field.required" class="text-red-500 ml-0.5">*</span>
            </InputLabel>

            <!-- Description -->
            <p v-if="field.description" class="text-xs text-light-text-secondary dark:text-dark-text-secondary mb-1">
                {{ field.description }}
            </p>

            <!-- String / URL / Secret / Number Input -->
            <TextInput
                v-if="['string', 'url', 'secret', 'number'].includes(field.type)"
                :id="`config-${field.name}`"
                :type="getInputType(field)"
                :model-value="getValue(field.name)"
                :placeholder="field.placeholder"
                :disabled="disabled"
                :min="field.min"
                :max="field.max"
                :step="field.step || 'any'"
                class="block w-full"
                @update:model-value="updateValue(field.name, $event)"
            />

            <!-- Text (multiline) Input -->
            <textarea
                v-else-if="field.type === 'text'"
                :id="`config-${field.name}`"
                :value="getValue(field.name)"
                :placeholder="field.placeholder"
                :disabled="disabled"
                rows="3"
                class="block w-full border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface text-light-text-primary dark:text-dark-text-primary focus:border-light-accent dark:focus:border-dark-accent focus:ring-light-accent dark:focus:ring-dark-accent rounded-md shadow-sm transition-colors"
                @input="updateValue(field.name, $event.target.value)"
            />

            <!-- Boolean Toggle -->
            <div v-else-if="field.type === 'boolean'" class="flex items-center gap-2">
                <Checkbox
                    :id="`config-${field.name}`"
                    :checked="!!getValue(field.name)"
                    :disabled="disabled"
                    @update:checked="updateValue(field.name, $event)"
                />
                <label
                    :for="`config-${field.name}`"
                    class="text-sm text-light-text-primary dark:text-dark-text-primary cursor-pointer"
                >
                    {{ field.checkbox_label || 'Enabled' }}
                </label>
            </div>

            <!-- Select Dropdown -->
            <select
                v-else-if="field.type === 'select'"
                :id="`config-${field.name}`"
                :value="getValue(field.name)"
                :disabled="disabled"
                class="block w-full border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface text-light-text-primary dark:text-dark-text-primary focus:border-light-accent dark:focus:border-dark-accent focus:ring-light-accent dark:focus:ring-dark-accent rounded-md shadow-sm transition-colors"
                @change="updateValue(field.name, $event.target.value)"
            >
                <option value="" disabled>
                    {{ field.placeholder || 'Select an option...' }}
                </option>
                <option
                    v-for="option in field.options"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </option>
            </select>

            <!-- Validation Error -->
            <InputError v-if="errors[field.name]" :message="errors[field.name]" />
        </div>
    </div>

    <!-- No configuration needed message -->
    <div v-else class="text-sm text-light-text-secondary dark:text-dark-text-secondary py-2">
        This bot does not require any configuration.
    </div>
</template>
