<script setup>
defineProps({
    type: {
        type: String,
        default: 'submit',
    },
});
</script>

<template>
    <button :type="type" class="inline-flex items-center px-4 py-2 bg-light-accent dark:bg-dark-accent border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-light-accent-hover dark:hover:bg-dark-accent-hover focus:bg-light-accent-hover dark:focus:bg-dark-accent-hover active:bg-light-accent dark:active:bg-dark-accent focus:outline-none focus:ring-2 focus:ring-light-accent dark:focus:ring-dark-accent focus:ring-offset-2 disabled:opacity-50 transition ease-in-out duration-150 shadow-neon">
        <slot />
    </button>
</template>
