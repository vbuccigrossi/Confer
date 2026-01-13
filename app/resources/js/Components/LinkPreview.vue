<script setup>
const props = defineProps({
    preview: Object,
});
</script>

<template>
    <a
        :href="preview.url"
        target="_blank"
        rel="noopener noreferrer"
        class="block mt-2 border border-light-border dark:border-dark-border rounded-lg overflow-hidden hover:border-light-accent dark:hover:border-dark-accent transition-colors bg-light-surface dark:bg-dark-surface max-w-lg"
    >
        <!-- Image -->
        <div v-if="preview.image_url" class="w-full bg-light-bg dark:bg-dark-bg">
            <img
                :src="preview.image_url"
                :alt="preview.title || preview.url"
                class="w-full h-48 object-cover"
                @error="$event.target.style.display = 'none'"
            />
        </div>

        <!-- Content -->
        <div class="p-3">
            <!-- Site name -->
            <div v-if="preview.site_name" class="text-xs text-light-text-muted dark:text-dark-text-muted mb-1">
                {{ preview.site_name }}
            </div>

            <!-- Title -->
            <div v-if="preview.title" class="font-semibold text-sm text-light-text-primary dark:text-dark-text-primary line-clamp-2 mb-1">
                {{ preview.title }}
            </div>

            <!-- Description -->
            <div v-if="preview.description" class="text-xs text-light-text-secondary dark:text-dark-text-secondary line-clamp-2 mb-1">
                {{ preview.description }}
            </div>

            <!-- URL -->
            <div class="text-xs text-light-text-muted dark:text-dark-text-muted truncate">
                {{ preview.url }}
            </div>
        </div>
    </a>
</template>

<style scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
