<template>
    <div class="md:col-span-1 flex justify-between">
        <div class="px-4 sm:px-0">
            <h3 class="text-lg font-medium text-light-text-primary dark:text-dark-text-primary">
                <slot name="title" />
            </h3>

            <p class="mt-1 text-sm text-light-text-secondary dark:text-dark-text-secondary">
                <slot name="description" />
            </p>
        </div>

        <div class="px-4 sm:px-0">
            <slot name="aside" />
        </div>
    </div>
</template>
