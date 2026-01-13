<template>
  <ion-modal :is-open="isOpen" @didDismiss="$emit('close')">
    <ion-header>
      <ion-toolbar>
        <ion-buttons slot="start">
          <ion-button @click="$emit('close')">
            <ion-icon :icon="closeOutline"></ion-icon>
          </ion-button>
        </ion-buttons>
        <ion-title>{{ attachment?.file_name || 'Attachment' }}</ion-title>
        <ion-buttons slot="end">
          <ion-button @click="downloadAttachment">
            <ion-icon :icon="downloadOutline"></ion-icon>
          </ion-button>
        </ion-buttons>
      </ion-toolbar>
    </ion-header>

    <ion-content class="ion-padding">
      <div v-if="attachment" class="attachment-preview">
        <!-- Image Preview -->
        <div v-if="isImage" class="image-preview">
          <img :src="attachment.url" :alt="attachment.file_name" />
        </div>

        <!-- PDF Preview -->
        <div v-else-if="isPDF" class="pdf-preview">
          <ion-icon :icon="documentTextOutline" class="file-icon"></ion-icon>
          <p class="file-name">{{ attachment.file_name }}</p>
          <p class="file-size">{{ formatFileSize(attachment.size) }}</p>
          <ion-button @click="downloadAttachment" expand="block">
            <ion-icon :icon="downloadOutline" slot="start"></ion-icon>
            Download PDF
          </ion-button>
        </div>

        <!-- Generic File Preview -->
        <div v-else class="generic-preview">
          <ion-icon :icon="documentOutline" class="file-icon"></ion-icon>
          <p class="file-name">{{ attachment.file_name }}</p>
          <p class="file-size">{{ formatFileSize(attachment.size) }}</p>
          <ion-button @click="downloadAttachment" expand="block">
            <ion-icon :icon="downloadOutline" slot="start"></ion-icon>
            Download File
          </ion-button>
        </div>
      </div>
    </ion-content>
  </ion-modal>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import {
  IonModal,
  IonHeader,
  IonToolbar,
  IonTitle,
  IonButtons,
  IonButton,
  IonContent,
  IonIcon,
} from '@ionic/vue';
import {
  closeOutline,
  downloadOutline,
  documentOutline,
  documentTextOutline,
} from 'ionicons/icons';

interface Props {
  isOpen: boolean;
  attachment: any | null;
}

const props = defineProps<Props>();
const emit = defineEmits(['close', 'download']);

const isImage = computed(() => {
  if (!props.attachment?.file_name) return false;
  const ext = props.attachment.file_name.split('.').pop()?.toLowerCase();
  return ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'].includes(ext || '');
});

const isPDF = computed(() => {
  if (!props.attachment?.file_name) return false;
  return props.attachment.file_name.toLowerCase().endsWith('.pdf');
});

function formatFileSize(bytes?: number): string {
  if (!bytes) return '0 B';
  const sizes = ['B', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(1024));
  return `${(bytes / Math.pow(1024, i)).toFixed(2)} ${sizes[i]}`;
}

function downloadAttachment() {
  if (props.attachment) {
    emit('download', props.attachment);
  }
}
</script>

<style scoped>
.attachment-preview {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 100%;
  padding: 24px;
}

.image-preview {
  width: 100%;
  max-width: 100%;
}

.image-preview img {
  width: 100%;
  height: auto;
  border-radius: 12px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.pdf-preview,
.generic-preview {
  text-align: center;
  max-width: 400px;
}

.file-icon {
  font-size: 120px;
  color: var(--ion-color-primary);
  margin-bottom: 24px;
  opacity: 0.8;
}

.file-name {
  font-size: 18px;
  font-weight: 600;
  color: var(--ion-color-dark);
  margin-bottom: 8px;
  word-break: break-word;
}

.file-size {
  font-size: 14px;
  color: var(--ion-color-medium);
  margin-bottom: 24px;
}

ion-button {
  margin-top: 16px;
}

@media (prefers-color-scheme: dark) {
  .image-preview img {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
  }
}
</style>
