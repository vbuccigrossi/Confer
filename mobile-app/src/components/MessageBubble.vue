<template>
  <div
    class="message-container"
    :class="{ 'own-message': isOwnMessage }"
    :id="`message-${message.id}`"
    role="article"
    :aria-label="`Message from ${message.user?.name || 'Unknown'} at ${formattedTime}`"
  >
    <!-- Avatar (only for other users' messages) -->
    <div v-if="!isOwnMessage" class="avatar" aria-hidden="true">
      <div class="avatar-circle" :style="{ backgroundColor: getUserColor(message.user?.name) }">
        {{ getInitials(message.user?.name) }}
      </div>
    </div>

    <div class="message-content">
      <!-- Sender name and timestamp (only for other users) -->
      <div v-if="!isOwnMessage" class="message-meta">
        <span class="sender-name">{{ message.user?.name || 'Unknown' }}</span>
        <span class="message-time">{{ formattedTime }}</span>
      </div>

      <!-- Message bubble -->
      <div class="message-bubble" :class="{ 'own-bubble': isOwnMessage }">
        <div class="message-body" v-html="renderedContent"></div>

        <!-- Link Preview -->
        <div v-if="message.link_preview" class="link-preview">
          <img
            v-if="message.link_preview.image_url"
            :src="message.link_preview.image_url"
            class="preview-image"
          />
          <div class="preview-content">
            <div class="preview-title">{{ message.link_preview.title }}</div>
            <p class="preview-description">{{ message.link_preview.description }}</p>
          </div>
        </div>

        <!-- Attachments -->
        <div v-if="message.attachments && message.attachments.length > 0" class="attachments">
          <div
            v-for="attachment in message.attachments"
            :key="attachment.id"
            class="attachment-item"
            @click="$emit('view-attachment', attachment)"
          >
            <ion-icon :icon="documentOutline" class="attachment-icon"></ion-icon>
            <span class="attachment-name">{{ attachment.file_name }}</span>
          </div>
        </div>
      </div>

      <!-- Reactions -->
      <div class="reactions" role="group" aria-label="Reactions">
        <ion-chip
          v-for="reaction in groupedReactions"
          :key="reaction.emoji"
          @click="$emit('toggle-reaction', reaction.emoji)"
          :class="{ 'my-reaction': reaction.hasMyReaction }"
          class="reaction-chip"
          role="button"
          :aria-label="`${reaction.emoji} reaction, ${reaction.count} ${reaction.count === 1 ? 'person' : 'people'}${reaction.hasMyReaction ? ', including you' : ''}`"
          :aria-pressed="reaction.hasMyReaction"
        >
          <span class="reaction-emoji" aria-hidden="true">{{ reaction.emoji }}</span>
          <span class="reaction-count">{{ reaction.count }}</span>
        </ion-chip>
        <ion-button
          fill="clear"
          size="small"
          @click="$emit('add-reaction')"
          class="add-reaction-btn"
          aria-label="Add reaction"
        >
          <ion-icon :icon="happyOutline" slot="icon-only"></ion-icon>
        </ion-button>
      </div>

      <!-- Thread replies indicator -->
      <div v-if="message.thread_reply_count > 0" class="thread-indicator">
        <ion-button
          fill="clear"
          size="small"
          @click="$emit('view-thread')"
          class="thread-button"
          :aria-label="`View thread with ${message.thread_reply_count} ${message.thread_reply_count === 1 ? 'reply' : 'replies'}`"
        >
          <ion-icon :icon="chatboxOutline" slot="start" aria-hidden="true"></ion-icon>
          {{ message.thread_reply_count }} {{ message.thread_reply_count === 1 ? 'reply' : 'replies' }}
        </ion-button>
      </div>

      <!-- Timestamp for own messages -->
      <div v-if="isOwnMessage" class="own-message-time">
        {{ formattedTime }}
      </div>
    </div>

    <!-- Actions button (3-dot menu) - always available for all messages -->
    <div class="message-actions">
      <ion-button
        fill="clear"
        size="small"
        @click="$emit('show-options')"
        class="options-btn"
        aria-label="Message options"
      >
        <ion-icon :icon="ellipsisHorizontalOutline" slot="icon-only"></ion-icon>
      </ion-button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { IonIcon, IonButton, IonChip } from '@ionic/vue';
import {
  ellipsisHorizontalOutline,
  documentOutline,
  chatboxOutline,
  addOutline,
  happyOutline
} from 'ionicons/icons';
import { marked } from 'marked';
import DOMPurify from 'dompurify';

interface Props {
  message: any;
  currentUserId: number;
}

const props = defineProps<Props>();

const emit = defineEmits(['toggle-reaction', 'add-reaction', 'view-thread', 'show-options', 'view-attachment']);

const isOwnMessage = computed(() => props.message.user_id === props.currentUserId);

const formattedTime = computed(() => {
  const date = new Date(props.message.created_at);
  const now = new Date();
  const diffMs = now.getTime() - date.getTime();
  const diffMins = Math.floor(diffMs / 60000);
  const diffHours = Math.floor(diffMins / 60);
  const diffDays = Math.floor(diffHours / 24);

  if (diffMins < 1) return 'Just now';
  if (diffMins < 60) return `${diffMins}m ago`;
  if (diffHours < 24) return `${diffHours}h ago`;
  if (diffDays < 7) return `${diffDays}d ago`;

  return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
});

const renderedContent = computed(() => {
  if (!props.message.body_md) return '';
  // Use parseInline for synchronous rendering, or cast to string
  const html = marked.parse(props.message.body_md, { async: false }) as string;
  return DOMPurify.sanitize(html);
});

const groupedReactions = computed(() => {
  if (!props.message.reactions) return [];

  const groups: Record<string, any> = {};

  props.message.reactions.forEach((reaction: any) => {
    if (!groups[reaction.emoji]) {
      groups[reaction.emoji] = {
        emoji: reaction.emoji,
        count: 0,
        hasMyReaction: false,
      };
    }
    groups[reaction.emoji].count++;
    if (reaction.user_id === props.currentUserId) {
      groups[reaction.emoji].hasMyReaction = true;
    }
  });

  return Object.values(groups);
});

function getUserColor(name?: string): string {
  if (!name) return '#94a3b8';

  const colors = [
    '#38bdf8', // cyan
    '#00ffc8', // green
    '#a78bfa', // purple
    '#fb923c', // orange
    '#ec4899', // pink
    '#10b981', // emerald
    '#f59e0b', // amber
    '#8b5cf6', // violet
  ];

  let hash = 0;
  for (let i = 0; i < name.length; i++) {
    hash = name.charCodeAt(i) + ((hash << 5) - hash);
  }

  return colors[Math.abs(hash) % colors.length];
}

function getInitials(name?: string): string {
  if (!name) return '?';
  return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
}
</script>

<style scoped>
.message-container {
  display: flex;
  gap: 12px;
  margin-bottom: 16px;
  align-items: flex-start;
  animation: slideIn 0.2s ease-out;
}

@keyframes slideIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.message-container.own-message {
  flex-direction: row-reverse;
}

.avatar {
  flex-shrink: 0;
  width: 36px;
  height: 36px;
}

.avatar-circle {
  width: 100%;
  height: 100%;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: 600;
  font-size: 14px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.message-content {
  flex: 1;
  min-width: 0;
  max-width: 75%;
}

.message-container.own-message .message-content {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
}

.message-meta {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 4px;
  padding: 0 4px;
}

.sender-name {
  font-weight: 600;
  font-size: 14px;
  color: var(--ion-color-dark);
}

.message-time {
  font-size: 11px;
  color: var(--ion-color-medium);
}

.message-bubble {
  padding: 10px 14px;
  border-radius: 16px;
  background: var(--ion-color-light);
  position: relative;
  word-wrap: break-word;
  overflow-wrap: break-word;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
}

/* Dark mode - media query (respects light mode override) */
@media (prefers-color-scheme: dark) {
  :root:not(.ion-palette-light) .message-bubble:not(.own-bubble) {
    background: var(--ion-color-step-100);
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
  }
}

/* Dark mode - class-based toggle */
:root.ion-palette-dark .message-bubble:not(.own-bubble) {
  background: var(--ion-color-step-100);
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
}

.message-bubble.own-bubble {
  background: linear-gradient(135deg, var(--ion-color-primary) 0%, var(--ion-color-primary-shade) 100%);
  color: white;
}

.message-body {
  line-height: 1.5;
  font-size: 15px;
}

.message-bubble.own-bubble .message-body {
  color: white;
}

.message-body :deep(p) {
  margin: 0;
}

.message-body :deep(p + p) {
  margin-top: 8px;
}

.message-body :deep(code) {
  background: rgba(0, 0, 0, 0.1);
  padding: 2px 6px;
  border-radius: 4px;
  font-family: 'Courier New', monospace;
  font-size: 13px;
}

.message-bubble.own-bubble .message-body :deep(code) {
  background: rgba(255, 255, 255, 0.2);
}

.message-body :deep(pre) {
  background: rgba(0, 0, 0, 0.05);
  padding: 12px;
  border-radius: 8px;
  overflow-x: auto;
  margin: 8px 0;
}

.message-body :deep(a) {
  color: var(--ion-color-primary);
  text-decoration: underline;
}

.message-bubble.own-bubble .message-body :deep(a) {
  color: rgba(255, 255, 255, 0.9);
}

.link-preview {
  margin-top: 8px;
  border-radius: 8px;
  overflow: hidden;
  background: rgba(0, 0, 0, 0.05);
}

.preview-image {
  width: 100%;
  max-height: 200px;
  object-fit: cover;
}

.preview-content {
  padding: 12px;
}

.preview-title {
  font-weight: 600;
  font-size: 14px;
  margin-bottom: 4px;
  color: var(--ion-color-dark);
}

.preview-description {
  font-size: 13px;
  color: var(--ion-color-medium);
  margin: 0;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.attachments {
  margin-top: 8px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.attachment-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 10px;
  background: rgba(0, 0, 0, 0.05);
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.attachment-item:hover {
  background: rgba(0, 0, 0, 0.1);
  transform: scale(1.02);
}

.attachment-icon {
  font-size: 20px;
  color: var(--ion-color-primary);
}

.attachment-name {
  flex: 1;
  font-size: 13px;
  color: var(--ion-color-dark);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.reactions {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 6px;
  padding: 0 4px;
}

.reaction-chip {
  --background: rgba(var(--ion-color-primary-rgb), 0.1);
  --color: var(--ion-color-primary);
  height: 28px;
  font-size: 13px;
  padding: 0 10px;
  margin: 0;
  border-radius: 14px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.reaction-chip:hover {
  --background: rgba(var(--ion-color-primary-rgb), 0.2);
  transform: scale(1.05);
}

.reaction-chip.my-reaction {
  --background: var(--ion-color-primary);
  --color: white;
  font-weight: 600;
}

.reaction-emoji {
  font-size: 14px;
  margin-right: 4px;
}

.reaction-count {
  font-weight: 600;
}

.add-reaction-btn {
  --padding-start: 8px;
  --padding-end: 8px;
  height: 28px;
  margin: 0;
}

.thread-indicator {
  margin-top: 6px;
  padding: 0 4px;
}

.thread-button {
  --color: var(--ion-color-primary);
  font-size: 13px;
  font-weight: 500;
  text-transform: none;
  margin: 0;
  height: 28px;
}

.own-message-time {
  font-size: 11px;
  color: var(--ion-color-medium);
  margin-top: 4px;
  padding: 0 4px;
  text-align: right;
}

.message-actions {
  flex-shrink: 0;
  opacity: 0;
  transition: opacity 0.2s ease;
}

.message-container:hover .message-actions {
  opacity: 1;
}

.options-btn {
  --padding-start: 6px;
  --padding-end: 6px;
  margin: 0;
  height: 28px;
}
</style>
