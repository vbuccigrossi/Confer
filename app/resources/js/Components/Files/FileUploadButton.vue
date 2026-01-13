<template>
  <div class="file-upload-button">
    <input
      ref="fileInput"
      type="file"
      class="hidden"
      :accept="acceptedTypes"
      @change="handleFileSelect"
    />

    <button
      type="button"
      @click="$refs.fileInput.click()"
      :disabled="uploading"
      class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
    >
      <svg v-if="!uploading" class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
      </svg>
      <svg v-else class="animate-spin w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
      {{ uploading ? 'Uploading...' : 'Attach File' }}
    </button>

    <!-- Error Message -->
    <p v-if="errorMessage" class="mt-2 text-sm text-red-600">
      {{ errorMessage }}
    </p>
  </div>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
  acceptedTypes: {
    type: String,
    default: 'image/*,.pdf,.txt,.md,.zip'
  },
  messageId: {
    type: Number,
    default: null
  }
});

const emit = defineEmits(['upload-success', 'upload-error']);

const fileInput = ref(null);
const uploading = ref(false);
const errorMessage = ref('');

const handleFileSelect = async (event) => {
  const file = event.target.files[0];
  if (!file) return;

  uploading.value = true;
  errorMessage.value = '';

  try {
    const formData = new FormData();
    formData.append('file', file);
    if (props.messageId) {
      formData.append('message_id', props.messageId);
    }

    const response = await fetch('/api/files', {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
      },
      body: formData,
      credentials: 'same-origin'
    });

    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.message || 'Upload failed');
    }

    const attachment = await response.json();
    emit('upload-success', attachment);

    // Reset input
    if (fileInput.value) {
      fileInput.value.value = '';
    }
  } catch (error) {
    console.error('Upload error:', error);
    errorMessage.value = error.message || 'Failed to upload file';
    emit('upload-error', error);
  } finally {
    uploading.value = false;
  }
};
</script>
