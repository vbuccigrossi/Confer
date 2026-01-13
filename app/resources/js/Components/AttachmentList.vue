<script setup>
import { computed } from 'vue';

const props = defineProps({
    attachments: Array,
});

const formatFileSize = (bytes) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
};

const getFileIcon = (mimeType) => {
    if (mimeType.startsWith('image/')) return '🖼️';
    if (mimeType.startsWith('video/')) return '🎥';
    if (mimeType.startsWith('audio/')) return '🎵';
    if (mimeType.includes('pdf')) return '📄';
    if (mimeType.includes('word') || mimeType.includes('document')) return '📝';
    if (mimeType.includes('sheet') || mimeType.includes('excel')) return '📊';
    if (mimeType.includes('presentation') || mimeType.includes('powerpoint')) return '📽️';
    if (mimeType.includes('zip') || mimeType.includes('rar') || mimeType.includes('tar')) return '📦';
    return '📎';
};

const imageAttachments = computed(() => {
    return props.attachments?.filter(a => a.mime_type.startsWith('image/')) || [];
});

const videoAttachments = computed(() => {
    return props.attachments?.filter(a => a.mime_type.startsWith('video/')) || [];
});

const pdfAttachments = computed(() => {
    return props.attachments?.filter(a => a.mime_type === 'application/pdf') || [];
});

const otherAttachments = computed(() => {
    return props.attachments?.filter(a =>
        !a.mime_type.startsWith('image/') &&
        !a.mime_type.startsWith('video/') &&
        a.mime_type !== 'application/pdf'
    ) || [];
});
</script>

<template>
    <div v-if="attachments && attachments.length > 0" class="mt-2 space-y-3">
        <!-- Image Attachments (inline display like Slack) -->
        <div v-if="imageAttachments.length > 0" class="space-y-2">
            <a
                v-for="attachment in imageAttachments"
                :key="attachment.id"
                :href="attachment.url"
                target="_blank"
                class="block group"
            >
                <div class="relative inline-block max-w-full rounded-lg overflow-hidden border border-light-border dark:border-dark-border hover:border-light-accent dark:hover:border-dark-accent transition-colors">
                    <img
                        :src="attachment.url"
                        :alt="attachment.file_name"
                        class="max-w-full max-h-96 object-contain bg-light-bg dark:bg-dark-bg"
                        :style="attachment.image_width && attachment.image_height ? `aspect-ratio: ${attachment.image_width}/${attachment.image_height}` : ''"
                    />
                    <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-5 transition-opacity"></div>
                </div>
                <p class="text-xs text-light-text-muted dark:text-dark-text-muted mt-1">
                    {{ attachment.file_name }} · {{ attachment.size_human || formatFileSize(attachment.size_bytes) }}
                </p>
            </a>
        </div>

        <!-- Video Attachments (with thumbnail) -->
        <div v-if="videoAttachments.length > 0" class="space-y-2">
            <a
                v-for="attachment in videoAttachments"
                :key="attachment.id"
                :href="attachment.url"
                target="_blank"
                class="block group"
            >
                <div class="relative inline-block max-w-full rounded-lg overflow-hidden border border-light-border dark:border-dark-border hover:border-light-accent dark:hover:border-dark-accent transition-colors">
                    <!-- Video Thumbnail -->
                    <div class="relative">
                        <img
                            v-if="attachment.thumbnail_url"
                            :src="attachment.thumbnail_url"
                            :alt="attachment.file_name"
                            class="max-w-full max-h-96 object-contain bg-black"
                        />
                        <div v-else class="flex items-center justify-center bg-dark-surface h-48 w-full">
                            <span class="text-6xl">🎥</span>
                        </div>

                        <!-- Play Button Overlay -->
                        <div class="absolute inset-0 flex items-center justify-center">
                            <div class="bg-black bg-opacity-60 rounded-full p-4 group-hover:bg-opacity-80 transition-all">
                                <svg class="h-12 w-12 text-white" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M8 5v14l11-7z"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
                <p class="text-xs text-light-text-muted dark:text-dark-text-muted mt-1">
                    🎥 {{ attachment.file_name }} · {{ attachment.size_human || formatFileSize(attachment.size_bytes) }}
                </p>
            </a>
        </div>

        <!-- PDF Attachments (with thumbnail) -->
        <div v-if="pdfAttachments.length > 0" class="space-y-2">
            <a
                v-for="attachment in pdfAttachments"
                :key="attachment.id"
                :href="attachment.url"
                target="_blank"
                class="flex items-start p-3 border border-light-border dark:border-dark-border rounded-lg hover:bg-light-bg dark:hover:bg-dark-bg hover:border-light-accent dark:hover:border-dark-accent transition-colors"
            >
                <!-- PDF Thumbnail or Icon -->
                <div class="flex-shrink-0 mr-3">
                    <img
                        v-if="attachment.thumbnail_url"
                        :src="attachment.thumbnail_url"
                        :alt="attachment.file_name"
                        class="h-20 w-16 object-cover rounded border border-light-border dark:border-dark-border"
                    />
                    <div v-else class="h-20 w-16 flex items-center justify-center bg-red-100 dark:bg-red-900/20 rounded border border-light-border dark:border-dark-border">
                        <span class="text-3xl">📄</span>
                    </div>
                </div>

                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-light-text-primary dark:text-dark-text-primary truncate">
                        {{ attachment.file_name }}
                    </p>
                    <p class="text-xs text-light-text-muted dark:text-dark-text-muted mt-1">
                        PDF · {{ attachment.size_human || formatFileSize(attachment.size_bytes) }}
                    </p>
                    <p class="text-xs text-light-accent dark:text-dark-accent mt-2">
                        Click to open in new tab
                    </p>
                </div>

                <svg class="h-5 w-5 text-light-text-muted dark:text-dark-text-muted flex-shrink-0 mt-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                </svg>
            </a>
        </div>

        <!-- Other File Attachments -->
        <div v-if="otherAttachments.length > 0" class="space-y-1">
            <a
                v-for="attachment in otherAttachments"
                :key="attachment.id"
                :href="attachment.url"
                target="_blank"
                class="flex items-center p-3 border border-light-border dark:border-dark-border rounded-lg hover:bg-light-bg dark:hover:bg-dark-bg hover:border-light-accent dark:hover:border-dark-accent transition-colors"
            >
                <span class="text-2xl mr-3">{{ getFileIcon(attachment.mime_type) }}</span>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-light-text-primary dark:text-dark-text-primary truncate">
                        {{ attachment.file_name }}
                    </p>
                    <p class="text-xs text-light-text-muted dark:text-dark-text-muted">
                        {{ attachment.size_human || formatFileSize(attachment.size_bytes) }}
                    </p>
                </div>
                <svg class="h-5 w-5 text-light-text-muted dark:text-dark-text-muted flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
            </a>
        </div>
    </div>
</template>
