<template>
  <div class="image-preview">
    <!-- Thumbnail -->
    <div
      class="relative cursor-pointer group"
      @click="openLightbox"
    >
      <img
        :src="src"
        :alt="alt"
        class="rounded-lg max-w-full h-auto"
        :class="{ 'opacity-0': loading, 'opacity-100': !loading }"
        @load="loading = false"
        @error="handleError"
      />

      <!-- Loading State -->
      <div v-if="loading" class="absolute inset-0 flex items-center justify-center bg-gray-100 rounded-lg">
        <svg class="animate-spin h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
      </div>

      <!-- Error State -->
      <div v-if="error" class="flex items-center justify-center bg-gray-100 rounded-lg p-4">
        <div class="text-center">
          <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <p class="mt-2 text-sm text-gray-500">Failed to load image</p>
        </div>
      </div>

      <!-- Zoom Overlay -->
      <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-10 transition-opacity rounded-lg flex items-center justify-center">
        <svg class="w-8 h-8 text-white opacity-0 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7" />
        </svg>
      </div>
    </div>

    <!-- Lightbox -->
    <Teleport to="body">
      <div
        v-if="showLightbox"
        class="fixed inset-0 z-50 bg-black bg-opacity-90 flex items-center justify-center p-4"
        @click="closeLightbox"
      >
        <button
          class="absolute top-4 right-4 text-white hover:text-gray-300"
          @click.stop="closeLightbox"
        >
          <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>

        <img
          :src="fullSrc"
          :alt="alt"
          class="max-w-full max-h-full object-contain"
          @click.stop
        />
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
  src: {
    type: String,
    required: true
  },
  fullSrc: {
    type: String,
    required: true
  },
  alt: {
    type: String,
    default: 'Image preview'
  }
});

const loading = ref(true);
const error = ref(false);
const showLightbox = ref(false);

const handleError = () => {
  loading.value = false;
  error.value = true;
};

const openLightbox = () => {
  if (!error.value) {
    showLightbox.value = true;
  }
};

const closeLightbox = () => {
  showLightbox.value = false;
};
</script>
