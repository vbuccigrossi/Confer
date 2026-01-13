<template>
  <ion-modal :is-open="isOpen" @didDismiss="$emit('close')">
    <ion-header>
      <ion-toolbar>
        <ion-buttons slot="start">
          <ion-button @click="$emit('close')">
            <ion-icon :icon="arrowBackOutline"></ion-icon>
          </ion-button>
        </ion-buttons>
        <ion-title>Thread</ion-title>
      </ion-toolbar>
    </ion-header>

    <ion-content class="ion-padding">
      <!-- Parent Message -->
      <div v-if="parentMessage" class="parent-message">
        <div class="parent-label">Thread started by</div>
        <MessageBubble
          :message="parentMessage"
          :current-user-id="currentUserId"
          @toggle-reaction="$emit('toggle-reaction', $event)"
          @add-reaction="$emit('add-reaction', parentMessage)"
        />
      </div>

      <ion-list-header v-if="replies.length > 0" class="replies-header">
        <ion-label>{{ replies.length }} {{ replies.length === 1 ? 'Reply' : 'Replies' }}</ion-label>
      </ion-list-header>

      <!-- Thread Replies -->
      <div class="thread-replies">
        <MessageBubble
          v-for="reply in replies"
          :key="reply.id"
          :message="reply"
          :current-user-id="currentUserId"
          @toggle-reaction="$emit('toggle-reaction', $event)"
          @add-reaction="$emit('add-reaction', reply)"
        />
      </div>

      <!-- No replies yet -->
      <div v-if="replies.length === 0" class="no-replies">
        <ion-icon :icon="chatbubbleOutline" class="no-replies-icon"></ion-icon>
        <p>No replies yet. Be the first to reply!</p>
      </div>
    </ion-content>

    <!-- Reply Input -->
    <ion-footer class="thread-footer">
      <div class="reply-input-container">
        <ion-textarea
          v-model="replyText"
          placeholder="Reply to thread..."
          :auto-grow="true"
          :rows="1"
          @keydown.enter.exact.prevent="sendReply"
        ></ion-textarea>
        <ion-button
          @click="sendReply"
          :disabled="!replyText.trim()"
          fill="clear"
          class="send-button"
        >
          <ion-icon :icon="sendOutline" slot="icon-only"></ion-icon>
        </ion-button>
      </div>
    </ion-footer>
  </ion-modal>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import {
  IonModal,
  IonHeader,
  IonToolbar,
  IonTitle,
  IonButtons,
  IonButton,
  IonContent,
  IonFooter,
  IonTextarea,
  IonIcon,
  IonListHeader,
  IonLabel,
} from '@ionic/vue';
import { arrowBackOutline, sendOutline, chatbubbleOutline } from 'ionicons/icons';
import MessageBubble from './MessageBubble.vue';

interface Props {
  isOpen: boolean;
  parentMessage: any | null;
  replies: any[];
  currentUserId: number;
}

const props = defineProps<Props>();
const emit = defineEmits(['close', 'send-reply', 'toggle-reaction', 'add-reaction']);

const replyText = ref('');

// Reset reply text when modal closes
watch(() => props.isOpen, (newVal) => {
  if (!newVal) {
    replyText.value = '';
  }
});

function sendReply() {
  if (replyText.value.trim() && props.parentMessage) {
    emit('send-reply', {
      parentMessageId: props.parentMessage.id,
      text: replyText.value.trim(),
    });
    replyText.value = '';
  }
}
</script>

<style scoped>
.parent-message {
  background: var(--ion-color-light);
  border-radius: 12px;
  padding: 16px;
  margin-bottom: 24px;
  border-left: 4px solid var(--ion-color-primary);
}

.parent-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--ion-color-medium);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 12px;
}

.replies-header {
  margin: 16px 0 8px 0;
  padding: 0 4px;
}

.replies-header ion-label {
  font-weight: 600;
  font-size: 14px;
  color: var(--ion-color-dark);
}

.thread-replies {
  padding-bottom: 16px;
}

.no-replies {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 48px 24px;
  text-align: center;
  color: var(--ion-color-medium);
}

.no-replies-icon {
  font-size: 64px;
  margin-bottom: 16px;
  opacity: 0.3;
}

.no-replies p {
  font-size: 15px;
  margin: 0;
}

.thread-footer {
  border-top: 1px solid var(--ion-color-step-150);
  background: var(--ion-background-color);
}

.reply-input-container {
  display: flex;
  align-items: flex-end;
  gap: 8px;
  padding: 12px 16px;
}

.reply-input-container ion-textarea {
  flex: 1;
  --background: var(--ion-color-light);
  --padding-start: 16px;
  --padding-end: 16px;
  --padding-top: 10px;
  --padding-bottom: 10px;
  border-radius: 24px;
  font-size: 15px;
  max-height: 120px;
}

.send-button {
  --color: var(--ion-color-primary);
  --padding-start: 8px;
  --padding-end: 8px;
  margin: 0;
  flex-shrink: 0;
}

.send-button ion-icon {
  font-size: 24px;
}

@media (prefers-color-scheme: dark) {
  .parent-message {
    background: var(--ion-color-step-100);
  }

  .reply-input-container ion-textarea {
    --background: var(--ion-color-step-100);
  }
}
</style>
