<template>
  <ion-item-sliding>
    <ion-item
      button
      @click="$emit('select')"
      :class="{ 'has-unread': conversation.unread_count > 0 }"
      lines="none"
      class="conversation-item"
      role="listitem"
      :aria-label="conversationAriaLabel"
    >
      <!-- Avatar/Icon -->
      <div slot="start" class="conversation-avatar">
        <!-- Channel icon -->
        <div v-if="isChannel" class="channel-icon">
          <ion-icon :icon="lockClosedOutline" v-if="conversation.type === 'private_channel'"></ion-icon>
          <span v-else>#</span>
        </div>

        <!-- DM avatar -->
        <div v-else-if="isDM" class="dm-avatar">
          <div
            class="avatar-circle"
            :style="{ backgroundColor: getUserColor(conversation.display_name) }"
          >
            {{ getInitials(conversation.display_name) }}
          </div>
          <!-- Online status indicator -->
          <div v-if="isOnline" class="online-indicator"></div>
        </div>

        <!-- Bot icon -->
        <div v-else-if="isBot" class="bot-icon">
          <ion-icon :icon="chatbubbleEllipsesOutline"></ion-icon>
        </div>
      </div>

      <!-- Content -->
      <ion-label class="conversation-label">
        <div class="conversation-header">
          <h3 class="conversation-name">
            <span v-if="isChannel">#</span>
            {{ conversation.display_name || conversation.name || 'Unknown' }}
          </h3>
          <span v-if="lastMessageTime" class="last-message-time">
            {{ lastMessageTime }}
          </span>
        </div>
        <p v-if="lastMessagePreview" class="last-message">
          {{ lastMessagePreview }}
        </p>
        <p v-else-if="!hasMessages" class="no-messages">No messages yet</p>
      </ion-label>

      <!-- Unread badge -->
      <div
        v-if="conversation.unread_count > 0"
        slot="end"
        class="unread-badge"
        role="status"
        :aria-label="`${conversation.unread_count} unread messages`"
      >
        {{ conversation.unread_count > 99 ? '99+' : conversation.unread_count }}
      </div>

      <!-- Muted indicator -->
      <ion-icon
        v-else-if="conversation.is_muted"
        slot="end"
        :icon="volumeMuteOutline"
        class="muted-icon"
        aria-label="Muted"
      ></ion-icon>
    </ion-item>

    <!-- Swipe actions -->
    <ion-item-options side="end">
      <ion-item-option
        @click="$emit('toggle-mute')"
        :color="conversation.is_muted ? 'primary' : 'medium'"
      >
        <ion-icon
          slot="icon-only"
          :icon="conversation.is_muted ? volumeHighOutline : volumeMuteOutline"
        ></ion-icon>
      </ion-item-option>
      <ion-item-option @click="$emit('mark-read')" color="success" v-if="conversation.unread_count > 0">
        <ion-icon slot="icon-only" :icon="checkmarkDoneOutline"></ion-icon>
      </ion-item-option>
      <ion-item-option @click="$emit('show-options')" color="light">
        <ion-icon slot="icon-only" :icon="ellipsisHorizontalOutline"></ion-icon>
      </ion-item-option>
    </ion-item-options>
  </ion-item-sliding>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import {
  IonItem,
  IonLabel,
  IonIcon,
  IonItemSliding,
  IonItemOptions,
  IonItemOption,
} from '@ionic/vue';
import {
  lockClosedOutline,
  chatbubbleEllipsesOutline,
  volumeMuteOutline,
  volumeHighOutline,
  checkmarkDoneOutline,
  ellipsisHorizontalOutline,
} from 'ionicons/icons';

interface Props {
  conversation: any;
  isOnline?: boolean;
}

const props = defineProps<Props>();

const emit = defineEmits(['select', 'toggle-mute', 'mark-read', 'show-options']);

const isChannel = computed(() =>
  props.conversation.type === 'public_channel' || props.conversation.type === 'private_channel'
);

const isDM = computed(() =>
  props.conversation.type === 'dm' || props.conversation.type === 'group_dm'
);

const isBot = computed(() => props.conversation.type === 'bot_dm');

// Accessibility label for the conversation
const conversationAriaLabel = computed(() => {
  const name = props.conversation.display_name || props.conversation.name || 'Unknown';
  const type = isChannel.value ? 'Channel' : isDM.value ? 'Direct message with' : isBot.value ? 'Bot conversation with' : '';
  const unread = props.conversation.unread_count > 0 ? `, ${props.conversation.unread_count} unread` : '';
  const online = props.isOnline ? ', online' : '';
  return `${type} ${name}${unread}${online}`;
});

// Check if conversation has any messages (even if last_message body isn't available)
const hasMessages = computed(() => {
  return !!(props.conversation.last_message_at || props.conversation.last_message);
});

const lastMessageTime = computed(() => {
  if (!props.conversation.last_message_at) return '';

  const date = new Date(props.conversation.last_message_at);
  const now = new Date();
  const diffMs = now.getTime() - date.getTime();
  const diffMins = Math.floor(diffMs / 60000);
  const diffHours = Math.floor(diffMins / 60);
  const diffDays = Math.floor(diffHours / 24);

  if (diffMins < 1) return 'now';
  if (diffMins < 60) return `${diffMins}m`;
  if (diffHours < 24) return `${diffHours}h`;
  if (diffDays < 7) return `${diffDays}d`;

  return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
});

const lastMessagePreview = computed(() => {
  if (!props.conversation.last_message) return '';

  let text = props.conversation.last_message.body_md || '';

  // Remove markdown formatting for preview
  text = text.replace(/[#*_~`\[\]()]/g, '').trim();

  // If text is empty after removing markdown, return empty
  if (!text) return '';

  // Truncate to 60 characters
  if (text.length > 60) {
    text = text.substring(0, 60) + '...';
  }

  // Add sender name for group chats
  if (props.conversation.type === 'group_dm' && props.conversation.last_message.user) {
    const senderName = props.conversation.last_message.user.name?.split(' ')[0] || 'Someone';
    text = `${senderName}: ${text}`;
  }

  return text;
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
  return name
    .split(' ')
    .map((n) => n[0])
    .join('')
    .toUpperCase()
    .slice(0, 2);
}
</script>

<style scoped>
.conversation-item {
  --padding-start: 16px;
  --padding-end: 16px;
  --inner-padding-end: 0;
  --min-height: 72px;
  --background: transparent;
  margin-bottom: 2px;
  transition: all 0.2s ease;
}

.conversation-item:hover {
  --background: rgba(var(--ion-color-primary-rgb), 0.05);
}

.conversation-item.has-unread {
  --background: rgba(var(--ion-color-primary-rgb), 0.08);
}

.conversation-avatar {
  margin-right: 12px;
  flex-shrink: 0;
}

.channel-icon {
  width: 40px;
  height: 40px;
  border-radius: 8px;
  background: var(--ion-color-primary);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  font-weight: 700;
}

.dm-avatar {
  position: relative;
  width: 44px;
  height: 44px;
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
  font-size: 16px;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
}

.online-indicator {
  position: absolute;
  bottom: 0;
  right: 0;
  width: 14px;
  height: 14px;
  background: #22c55e;
  border: 2px solid var(--ion-background-color);
  border-radius: 50%;
}

.bot-icon {
  width: 40px;
  height: 40px;
  border-radius: 8px;
  background: var(--ion-color-secondary);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
}

.conversation-label {
  margin: 0;
}

.conversation-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 4px;
}

.conversation-name {
  font-size: 16px;
  font-weight: 600;
  color: var(--ion-color-dark);
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  flex: 1;
}

.has-unread .conversation-name {
  font-weight: 700;
  color: var(--ion-color-primary);
}

.last-message-time {
  font-size: 12px;
  color: var(--ion-color-medium);
  flex-shrink: 0;
  margin-left: 8px;
}

.has-unread .last-message-time {
  color: var(--ion-color-primary);
  font-weight: 600;
}

.last-message {
  font-size: 14px;
  color: var(--ion-color-medium);
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.has-unread .last-message {
  font-weight: 500;
  color: var(--ion-color-dark);
}

.no-messages {
  font-size: 14px;
  color: var(--ion-color-step-400);
  font-style: italic;
  margin: 0;
}

.unread-badge {
  background: var(--ion-color-primary);
  color: white;
  border-radius: 12px;
  min-width: 24px;
  height: 24px;
  padding: 0 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 700;
  margin-left: 8px;
}

.muted-icon {
  color: var(--ion-color-medium);
  font-size: 20px;
  margin-left: 8px;
}
</style>
