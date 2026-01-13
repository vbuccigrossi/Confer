<script setup>
import { onMounted, ref } from 'vue';

defineProps({
    modelValue: String,
});

defineEmits(['update:modelValue']);

const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <input
        ref="input"
        class="border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface text-light-text-primary dark:text-dark-text-primary focus:border-light-accent dark:focus:border-dark-accent focus:ring-light-accent dark:focus:ring-dark-accent rounded-md shadow-sm transition-colors"
        :value="modelValue"
        @input="$emit('update:modelValue', $event.target.value)"
    >
</template>
