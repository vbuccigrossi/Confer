<script setup>
import { ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const props = defineProps({
    messageId: Number,
    conversationId: Number,
});

const emit = defineEmits(['uploaded']);

const page = usePage();
const fileInput = ref(null);
const uploading = ref(false);
const uploadProgress = ref(0);

const triggerUpload = () => {
    fileInput.value?.click();
};

const handleFileSelect = async (event) => {
    const files = event.target.files;
    if (!files || files.length === 0) return;

    uploading.value = true;
    uploadProgress.value = 0;

    for (let i = 0; i < files.length; i++) {
        const file = files[i];
        const formData = new FormData();
        formData.append('file', file);
        if (props.messageId) {
            formData.append('message_id', props.messageId);
        }

        try {
            const attachment = await uploadFile(formData);
            uploadProgress.value = ((i + 1) / files.length) * 100;

            // If not attaching to an existing message, create a new message with the attachment
            if (!props.messageId && props.conversationId) {
                await createMessageWithAttachment(attachment.id);
            }
        } catch (error) {
            console.error('Upload failed:', error);
            alert(`Failed to upload ${file.name}`);
        }
    }

    uploading.value = false;
    uploadProgress.value = 0;
    fileInput.value.value = '';
    emit('uploaded');
};

const uploadFile = async (formData) => {
    const response = await window.axios.post('/api/files', formData, {
        headers: {
            'Content-Type': 'multipart/form-data',
        },
    });
    return response.data;
};

const createMessageWithAttachment = async (attachmentId) => {
    // Create a message with the attachment using the web route
    router.post(route('web.conversations.messages.store', props.conversationId), {
        body_md: '', // Empty body, just the attachment
        attachment_ids: [attachmentId],
    }, {
        preserveScroll: true,
        onError: (errors) => {
            console.error('Failed to create message with attachment:', errors);
        }
    });
};
</script>

<template>
    <div>
        <input
            ref="fileInput"
            type="file"
            multiple
            class="hidden"
            @change="handleFileSelect"
        />

        <button
            type="button"
            @click="triggerUpload"
            :disabled="uploading"
            class="p-2 text-light-text-muted dark:text-dark-text-muted hover:text-light-text-primary dark:hover:text-dark-text-primary disabled:opacity-50"
            title="Upload file"
        >
            <svg v-if="!uploading" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
            </svg>
            <svg v-else class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        </button>

        <div v-if="uploading" class="text-xs text-light-text-muted dark:text-dark-text-muted mt-1">
            Uploading... {{ Math.round(uploadProgress) }}%
        </div>
    </div>
</template>
