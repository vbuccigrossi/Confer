<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    href: String,
    as: String,
});
</script>

<template>
    <div>
        <button v-if="as == 'button'" type="submit" class="block w-full px-4 py-2 text-start text-sm leading-5 text-light-text-primary dark:text-dark-text-primary hover:bg-light-bg dark:hover:bg-dark-bg focus:outline-none focus:bg-light-bg dark:focus:bg-dark-bg transition duration-150 ease-in-out">
            <slot />
        </button>

        <a v-else-if="as =='a'" :href="href" class="block px-4 py-2 text-sm leading-5 text-light-text-primary dark:text-dark-text-primary hover:bg-light-bg dark:hover:bg-dark-bg focus:outline-none focus:bg-light-bg dark:focus:bg-dark-bg transition duration-150 ease-in-out">
            <slot />
        </a>

        <Link v-else :href="href" class="block px-4 py-2 text-sm leading-5 text-light-text-primary dark:text-dark-text-primary hover:bg-light-bg dark:hover:bg-dark-bg focus:outline-none focus:bg-light-bg dark:focus:bg-dark-bg transition duration-150 ease-in-out">
            <slot />
        </Link>
    </div>
</template>
