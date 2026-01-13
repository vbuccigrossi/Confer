<template>
  <div class="attachment-chip flex items-center gap-2 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg hover:bg-gray-100 transition-colors">
    <!-- File Icon -->
    <div class="flex-shrink-0">
      <svg v-if="isImage" class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
      </svg>
      <svg v-else class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
      </svg>
    </div>

    <!-- File Info -->
    <div class="flex-1 min-w-0">
      <p class="text-sm font-medium text-gray-900 truncate">
        {{ attachment.file_name }}
      </p>
      <p class="text-xs text-gray-500">
        {{ attachment.size_human }}
      </p>
    </div>

    <!-- Download Button -->
    <a
      :href="attachment.url"
      target="_blank"
      class="flex-shrink-0 text-blue-600 hover:text-blue-800"
      title="Download"
    >
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
      </svg>
    </a>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  attachment: {
    type: Object,
    required: true
  }
});

const isImage = computed(() => {
  return props.attachment.mime_type?.startsWith('image/');
});
</script>

<style scoped>
.attachment-chip {
  max-width: 100%;
}
</style>
