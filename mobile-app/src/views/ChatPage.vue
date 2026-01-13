<template>
  <ion-page>
    <ion-header>
      <ion-toolbar>
        <ion-buttons slot="start">
          <ion-button v-if="currentConversation" @click="backToConversationList">
            <ion-icon :icon="arrowBackOutline"></ion-icon>
          </ion-button>
          <ion-button v-else @click="router.push('/settings')">
            <ion-icon :icon="settingsOutline"></ion-icon>
          </ion-button>
        </ion-buttons>
        <ion-title>{{ headerTitle }}</ion-title>
        <ion-buttons slot="end">
          <ion-button v-if="currentConversation" @click="showSearch">
            <ion-icon :icon="searchOutline"></ion-icon>
          </ion-button>
        </ion-buttons>
      </ion-toolbar>
    </ion-header>

    <ion-content :fullscreen="true">
        <!-- Main Content -->
        <div id="main-content" class="full-width">
          <!-- Conversation List View -->
          <div v-if="!currentConversation" class="conversations-view">
            <ion-list class="conversation-list">
              <!-- Notes to Self - always at top -->
              <ion-item
                button
                @click="openNotesToSelf"
                class="notes-to-self-item"
              >
                <ion-icon :icon="documentTextOutline" slot="start" color="primary"></ion-icon>
                <ion-label>
                  <h2>Notes to Self</h2>
                  <p v-if="selfConversation?.last_message">{{ selfConversation.last_message.body_md?.substring(0, 50) }}...</p>
                  <p v-else>Your private notepad</p>
                </ion-label>
                <ion-badge v-if="selfConversation?.unread_count" color="primary" slot="end">
                  {{ selfConversation.unread_count }}
                </ion-badge>
              </ion-item>

              <ion-list-header>
                <ion-label class="section-header">Channels</ion-label>
                <ion-button fill="clear" size="small" @click="createChannel">
                  <ion-icon :icon="addOutline"></ion-icon>
                </ion-button>
              </ion-list-header>
              <ConversationListItem
                v-for="conv in channels"
                :key="conv.id"
                :conversation="conv"
                @select="selectConversation(conv)"
                @toggle-mute="toggleMute(conv)"
                @mark-read="markAsRead(conv)"
                @show-options="showConversationOptions(conv)"
              />

              <ion-list-header class="ion-margin-top">
                <ion-label class="section-header">Direct Messages</ion-label>
                <ion-button fill="clear" size="small" @click="createDM">
                  <ion-icon :icon="addOutline"></ion-icon>
                </ion-button>
              </ion-list-header>
              <ConversationListItem
                v-for="conv in dms"
                :key="conv.id"
                :conversation="conv"
                :is-online="isDmOnline(conv)"
                @select="selectConversation(conv)"
                @toggle-mute="toggleMute(conv)"
                @mark-read="markAsRead(conv)"
                @show-options="showConversationOptions(conv)"
              />

              <ion-list-header class="ion-margin-top">
                <ion-label class="section-header">Bots</ion-label>
                <ion-button fill="clear" size="small" @click="createBotDM">
                  <ion-icon :icon="addOutline"></ion-icon>
                </ion-button>
              </ion-list-header>
              <ConversationListItem
                v-for="conv in bots"
                :key="conv.id"
                :conversation="conv"
                @select="selectConversation(conv)"
                @toggle-mute="toggleMute(conv)"
                @mark-read="markAsRead(conv)"
                @show-options="showConversationOptions(conv)"
              />
            </ion-list>
          </div>

          <!-- Chat View -->
          <div v-else class="chat-container" role="main" aria-label="Chat conversation">
            <!-- Messages -->
            <div ref="messagesContainer" class="messages-list" role="log" aria-live="polite" aria-label="Messages">
              <ion-refresher slot="fixed" @ionRefresh="handleRefresh">
                <ion-refresher-content></ion-refresher-content>
              </ion-refresher>

              <div v-if="loadingMessages && messages.length === 0" class="ion-padding ion-text-center">
                <ion-spinner></ion-spinner>
              </div>

              <MessageBubble
                v-for="message in messages"
                :key="message.id"
                :message="message"
                :current-user-id="currentUserId"
                @toggle-reaction="(emoji) => toggleReaction(message, emoji)"
                @add-reaction="showReactionPicker(message)"
                @view-thread="viewThread(message)"
                @show-options="showMessageOptions(message)"
                @view-attachment="viewAttachment"
              />

              <!-- Typing Indicator -->
              <TypingIndicator :typing-users="typingUsers" />
            </div>

            <!-- Message Input -->
            <div class="message-input-container" role="form" aria-label="Send message">
              <!-- Mention Autocomplete -->
              <MentionAutocomplete
                ref="mentionAutocompleteRef"
                :text="newMessage"
                :cursor-position="cursorPosition"
                :conversation-id="currentConversation?.id || 0"
                @select="handleMentionSelect"
              />

              <!-- File Upload Progress -->
              <div v-if="uploadingFiles.length > 0" class="upload-progress">
                <div v-for="upload in uploadingFiles" :key="upload.name" class="upload-item">
                  <ion-icon :icon="documentOutline"></ion-icon>
                  <span class="upload-name">{{ upload.name }}</span>
                  <ion-progress-bar :value="upload.progress / 100"></ion-progress-bar>
                </div>
              </div>

              <!-- Pending Attachments -->
              <div v-if="pendingAttachments.length > 0" class="pending-attachments">
                <ion-chip v-for="(att, index) in pendingAttachments" :key="att.id" @click="removePendingAttachment(index)">
                  <ion-icon :icon="documentOutline"></ion-icon>
                  <ion-label>{{ att.file_name }}</ion-label>
                  <ion-icon :icon="closeOutline"></ion-icon>
                </ion-chip>
              </div>

              <ion-button v-if="replyingTo" fill="clear" @click="cancelReply" size="small">
                <ion-icon :icon="closeOutline"></ion-icon>
                Cancel reply to {{ replyingTo.user?.name }}
              </ion-button>

              <ion-item lines="none">
                <ion-button slot="start" fill="clear" @click="selectFile" aria-label="Attach file">
                  <ion-icon :icon="attachOutline"></ion-icon>
                </ion-button>
                <ion-textarea
                  ref="messageInputRef"
                  v-model="newMessage"
                  placeholder="Type a message... (@ to mention)"
                  :auto-grow="true"
                  :rows="1"
                  @keydown="handleInputKeyDown"
                  @ionInput="handleInputChange"
                  aria-label="Message text"
                ></ion-textarea>
                <ion-button
                  slot="end"
                  :disabled="!canSendMessage"
                  @click="sendMessage"
                  aria-label="Send message"
                >
                  <ion-icon :icon="sendOutline"></ion-icon>
                </ion-button>
              </ion-item>
              <input
                ref="fileInput"
                type="file"
                style="display: none"
                @change="handleFileSelect"
                multiple
              />
            </div>
          </div>
        </div>
    </ion-content>

    <!-- Reaction Picker Modal -->
    <ReactionPicker
      :is-open="showReactionPickerModal"
      @close="showReactionPickerModal = false"
      @select="handleReactionSelect"
    />

    <!-- Thread View Modal -->
    <ThreadView
      :is-open="showThreadModal"
      :parent-message="threadParentMessage"
      :replies="threadReplies"
      :current-user-id="currentUserId"
      @close="showThreadModal = false"
      @send-reply="sendThreadReply"
      @toggle-reaction="(emoji) => threadParentMessage && toggleReaction(threadParentMessage, emoji)"
      @add-reaction="showReactionPicker"
    />

    <!-- Attachment Preview Modal -->
    <AttachmentPreview
      :is-open="showAttachmentPreview"
      :attachment="previewAttachment"
      @close="showAttachmentPreview = false"
      @download="downloadAttachment"
    />

    <!-- Search Modal -->
    <SearchModal
      ref="searchModalRef"
      :is-open="showSearchModal"
      @close="showSearchModal = false"
      @search="handleSearch"
      @select-message="selectSearchResult"
    />
  </ion-page>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import {
  IonPage,
  IonHeader,
  IonToolbar,
  IonTitle,
  IonContent,
  IonButtons,
  IonButton,
  IonBackButton,
  IonIcon,
  IonList,
  IonListHeader,
  IonItem,
  IonLabel,
  IonTextarea,
  IonSpinner,
  IonRefresher,
  IonRefresherContent,
  IonChip,
  IonBadge,
  IonProgressBar,
  actionSheetController,
  alertController,
  modalController,
  toastController,
} from '@ionic/vue';
import {
  chatbubblesOutline,
  chatbubbleEllipsesOutline,
  sendOutline,
  searchOutline,
  addOutline,
  ellipsisVerticalOutline,
  ellipsisHorizontalOutline,
  happyOutline,
  chatboxOutline,
  closeOutline,
  attachOutline,
  documentOutline,
  documentTextOutline,
  settingsOutline,
  arrowBackOutline,
} from 'ionicons/icons';

// Note: happyOutline is used for the "Add Reaction" button in message options
import { marked } from 'marked';
import DOMPurify from 'dompurify';
import api from '@/services/api';
import { useAuthStore } from '@/stores/auth';
import MessageBubble from '@/components/MessageBubble.vue';
import ConversationListItem from '@/components/ConversationListItem.vue';
import ReactionPicker from '@/components/ReactionPicker.vue';
import ThreadView from '@/components/ThreadView.vue';
import TypingIndicator from '@/components/TypingIndicator.vue';
import AttachmentPreview from '@/components/AttachmentPreview.vue';
import SearchModal from '@/components/SearchModal.vue';
import MentionAutocomplete from '@/components/MentionAutocomplete.vue';
import audioNotificationService from '@/services/audio';
import websocketService from '@/services/websocket';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const workspaceId = computed(() => parseInt(route.params.workspaceId as string));
const currentWorkspaceName = ref('GSS');
const conversations = ref<any[]>([]);
const currentConversation = ref<any | null>(null);

const headerTitle = computed(() => {
  if (currentConversation.value) {
    // Show conversation name or display name for DMs
    if (currentConversation.value.name) {
      // Only add # if the name doesn't already start with it
      const name = currentConversation.value.name;
      return name.startsWith('#') ? name : `# ${name}`;
    }
    return currentConversation.value.display_name || 'Conversation';
  }
  return 'GSS';
});
const messages = ref<any[]>([]);
const newMessage = ref('');
const loadingMessages = ref(false);
const messagesContainer = ref<HTMLElement | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const replyingTo = ref<any | null>(null);
const showReactionPickerModal = ref(false);
const reactionPickerMessage = ref<any | null>(null);
const showThreadModal = ref(false);
const threadParentMessage = ref<any | null>(null);
const threadReplies = ref<any[]>([]);
const typingUsers = ref<string[]>([]);
const showAttachmentPreview = ref(false);
const previewAttachment = ref<any | null>(null);
const selectedFiles = ref<File[]>([]);
const showSearchModal = ref(false);
const searchModalRef = ref<any | null>(null);

// New refs for @mentions, file upload, and real-time
const mentionAutocompleteRef = ref<any | null>(null);
const messageInputRef = ref<any | null>(null);
const cursorPosition = ref(0);
const uploadingFiles = ref<{ name: string; progress: number }[]>([]);
const pendingAttachments = ref<any[]>([]);
const useWebSocket = ref(false); // Set to true to enable WebSocket instead of polling

const currentUserId = computed(() => authStore.user?.id);
const canSendMessage = computed(() => newMessage.value.trim() || pendingAttachments.value.length > 0);

let pollInterval: number | null = null;
let heartbeatInterval: number | null = null;
let isLoadingMessages = false; // Prevent concurrent loadMessages calls
const typingTimeouts = new Map<string, ReturnType<typeof setTimeout>>(); // Track typing indicator timeouts

// Store WebSocket event handler references for cleanup
const wsHandlers = {
  messageCreated: null as ((data: any) => void) | null,
  messageUpdated: null as ((data: any) => void) | null,
  messageDeleted: null as ((data: any) => void) | null,
  reactionAdded: null as ((data: any) => void) | null,
  reactionRemoved: null as ((data: any) => void) | null,
  userTyping: null as ((data: any) => void) | null,
};

const channels = computed(() =>
  conversations.value.filter((c) => c.type.includes('channel'))
);

const dms = computed(() =>
  conversations.value.filter((c) => c.type === 'dm' || c.type === 'group_dm')
);

const bots = computed(() =>
  conversations.value.filter((c) => c.type === 'bot_dm')
);

const selfConversation = computed(() =>
  conversations.value.find((c) => c.type === 'self')
);

function renderMarkdown(text: string): string {
  if (!text) return '';
  const html = marked.parse(text, { async: false }) as string;
  return DOMPurify.sanitize(html);
}

function isDmOnline(dm: any): boolean {
  if (!dm.members || dm.members.length === 0) return false;
  const otherMembers = dm.members.filter((m: any) => m.user && m.user.id !== currentUserId.value);
  return otherMembers.some((m: any) => m.user?.is_online === true);
}

function groupReactions(reactions: any[]): any[] {
  const grouped: any = {};
  reactions.forEach((r) => {
    if (!grouped[r.emoji]) {
      grouped[r.emoji] = { emoji: r.emoji, count: 0, hasMyReaction: false };
    }
    grouped[r.emoji].count++;
    if (r.user_id === currentUserId.value) {
      grouped[r.emoji].hasMyReaction = true;
    }
  });
  return Object.values(grouped);
}

async function loadConversations() {
  try {
    console.log('[CHAT] Route params:', route.params);
    console.log('[CHAT] Workspace ID (raw):', route.params.workspaceId);
    console.log('[CHAT] Workspace ID (parsed):', workspaceId.value);
    console.log('[CHAT] Workspace ID is NaN?:', isNaN(workspaceId.value));

    if (!workspaceId.value || isNaN(workspaceId.value)) {
      console.error('[CHAT] Invalid workspace ID!');
      return;
    }

    console.log('[CHAT] Loading conversations for workspace:', workspaceId.value);
    conversations.value = await api.getConversations(workspaceId.value);
    console.log('[CHAT] Loaded conversations:', conversations.value);
    console.log('[CHAT] Conversations count:', conversations.value.length);
    console.log('[CHAT] Channels:', channels.value);
    console.log('[CHAT] DMs:', dms.value);
    console.log('[CHAT] Bots:', bots.value);

    if (currentConversation.value) {
      const updated = conversations.value.find(c => c.id === currentConversation.value.id);
      if (updated) {
        currentConversation.value = updated;
      }
    }
  } catch (error) {
    console.error('Error loading conversations:', error);
    console.error('Error details:', error);
  }
}

async function loadMessages(forceScroll = false) {
  if (!currentConversation.value) return;

  // Prevent concurrent calls
  if (isLoadingMessages) return;
  isLoadingMessages = true;

  const previousMessageCount = messages.value.length;
  loadingMessages.value = true;
  try {
    // Load all messages (set high limit to get everything)
    const response = await api.getMessages(currentConversation.value.id, 10000);
    const newMessages = response.messages || [];

    // API returns newest first, we need oldest first for chronological display
    const reversedMessages = newMessages.reverse();

    // Check if we have new messages (and it's not the initial load)
    const hasNewMessages = previousMessageCount > 0 && reversedMessages.length > previousMessageCount;

    if (hasNewMessages) {
      console.log('[CHAT] New messages detected!', { prev: previousMessageCount, now: reversedMessages.length });
      const latestMessage = reversedMessages[reversedMessages.length - 1];
      console.log('[CHAT] Latest message:', latestMessage);
      // Only play sound if the new message is from someone else
      if (latestMessage && latestMessage.user_id !== currentUserId.value) {
        console.log('[CHAT] Playing notification sound...');
        await audioNotificationService.playNotificationSound();
      } else {
        console.log('[CHAT] Not playing sound - message is from current user');
      }
    }

    messages.value = reversedMessages;
    await nextTick();

    // Mark the latest message as read if there are any messages
    if (reversedMessages.length > 0) {
      const latestMessage = reversedMessages[reversedMessages.length - 1];
      try {
        await api.markAsRead(latestMessage.id);
        // Reload conversations to update unread counts in the sidebar
        await loadConversations();
      } catch (error) {
        console.error('Error marking message as read:', error);
      }
    }

    // Auto-scroll when opening a conversation, sending a message, or new messages arrive
    if (forceScroll || hasNewMessages) {
      scrollToBottom();
    }
  } catch (error) {
    console.error('Error loading messages:', error);
    const toast = await toastController.create({
      message: 'Failed to load messages',
      duration: 2000,
      color: 'danger',
      position: 'bottom',
    });
    await toast.present();
  } finally {
    loadingMessages.value = false;
    isLoadingMessages = false;
  }
}


async function selectConversation(conv: any) {
  console.log('Selecting conversation:', conv.name || conv.display_name);
  currentConversation.value = conv;
  replyingTo.value = null;
  await loadMessages(true); // Force scroll to bottom when opening
  startPolling();
}

function backToConversationList() {
  stopPolling();
  currentConversation.value = null;
  messages.value = [];
}

async function sendMessage() {
  if (!canSendMessage.value || !currentConversation.value) return;

  const messageText = newMessage.value;
  const attachmentIds = pendingAttachments.value.map((a) => a.id);

  newMessage.value = '';
  pendingAttachments.value = [];

  try {
    if (attachmentIds.length > 0) {
      // Send message with attachments
      await api.sendMessageWithAttachments(
        currentConversation.value.id,
        messageText || '',
        attachmentIds,
        replyingTo.value?.id
      );
    } else {
      // Send regular message
      await api.sendMessage(
        currentConversation.value.id,
        messageText,
        replyingTo.value?.id
      );
    }
    replyingTo.value = null;
    await loadMessages(true); // Force scroll after sending
  } catch (error) {
    console.error('Error sending message:', error);
    newMessage.value = messageText;
    // Note: Can't restore attachments as they were already uploaded - they are lost
    const toast = await toastController.create({
      message: 'Failed to send message',
      duration: 2000,
      color: 'danger',
      position: 'bottom',
    });
    await toast.present();
  }
}

// Input handlers for @mentions
function handleInputKeyDown(event: KeyboardEvent) {
  // Check if mention autocomplete should handle this
  if (mentionAutocompleteRef.value?.isOpen() && mentionAutocompleteRef.value?.handleKeyDown(event)) {
    return;
  }

  // Handle Enter key for sending
  if (event.key === 'Enter' && !event.shiftKey) {
    event.preventDefault();
    sendMessage();
  }
}

function handleInputChange(event: any) {
  // Update cursor position for mention detection
  const textarea = event.target;
  cursorPosition.value = textarea.selectionStart || newMessage.value.length;
}

function handleMentionSelect(data: { user: any; mentionStart: number; mentionEnd: number }) {
  // Insert the @mention into the message
  const before = newMessage.value.substring(0, data.mentionStart);
  const after = newMessage.value.substring(data.mentionEnd);
  const mention = `@${data.user.name.replace(/\s+/g, '')} `; // Remove spaces from name for mention

  newMessage.value = before + mention + after;

  // Update cursor position after the mention
  nextTick(() => {
    cursorPosition.value = data.mentionStart + mention.length;
  });
}

function scrollToBottom() {
  if (messagesContainer.value) {
    messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
  }
}

function formatTime(timestamp: string) {
  const date = new Date(timestamp);
  return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

async function handleRefresh(event: any) {
  await loadMessages();
  event.target.complete();
}

async function showCreateMenu() {
  const actionSheet = await actionSheetController.create({
    header: 'Create New',
    buttons: [
      {
        text: 'Channel',
        handler: () => createChannel(),
      },
      {
        text: 'Direct Message',
        handler: () => createDM(),
      },
      {
        text: 'Cancel',
        role: 'cancel',
      },
    ],
  });
  await actionSheet.present();
}

async function createChannel() {
  const alert = await alertController.create({
    header: 'Create Channel',
    inputs: [
      {
        name: 'name',
        type: 'text',
        placeholder: 'Channel name',
      },
      {
        name: 'topic',
        type: 'text',
        placeholder: 'Topic (optional)',
      },
    ],
    buttons: [
      {
        text: 'Cancel',
        role: 'cancel',
      },
      {
        text: 'Create',
        handler: async (data) => {
          if (!data.name) return false;
          try {
            const conv = await api.createChannel(workspaceId.value, data.name, 'public_channel', data.topic);
            await loadConversations();
            await selectConversation(conv);
          } catch (error) {
            console.error('Error creating channel:', error);
          }
          return true;
        },
      },
    ],
  });
  await alert.present();
}

async function createDM() {
  try {
    const members = await api.getWorkspaceMembers(workspaceId.value);
    const otherMembers = members.filter((m: any) => m.user_id !== currentUserId.value);

    const inputs = otherMembers.map((m: any) => ({
      name: `user_${m.user_id}`,
      type: 'checkbox',
      label: m.user?.name || 'Unknown',
      value: m.user_id,
    }));

    const alert = await alertController.create({
      header: 'Start Direct Message',
      inputs: inputs as any,
      buttons: [
        {
          text: 'Cancel',
          role: 'cancel',
        },
        {
          text: 'Start',
          handler: async (selectedIds) => {
            if (!selectedIds || selectedIds.length === 0) return false;
            try {
              const conv = await api.createDM(workspaceId.value, selectedIds);
              await loadConversations();
              await selectConversation(conv);
            } catch (error) {
              console.error('Error creating DM:', error);
            }
            return true;
          },
        },
      ],
    });
    await alert.present();
  } catch (error) {
    console.error('Error loading members:', error);
  }
}

async function openNotesToSelf() {
  try {
    const conv = await api.getOrCreateSelfConversation(workspaceId.value);
    await loadConversations();
    await selectConversation(conv);
  } catch (error) {
    console.error('Error opening Notes to Self:', error);
    const toast = await toastController.create({
      message: 'Failed to open Notes to Self',
      duration: 2000,
      color: 'danger',
      position: 'bottom',
    });
    await toast.present();
  }
}

async function createBotDM() {
  try {
    // Search for bot users
    const users = await api.searchUsers(workspaceId.value, 'bot');
    const botUsers = users.filter((u: any) => u.email?.endsWith('@bots.local'));

    if (botUsers.length === 0) {
      const alert = await alertController.create({
        header: 'No Bots Available',
        message: 'There are no bots available in this workspace.',
        buttons: ['OK'],
      });
      await alert.present();
      return;
    }

    const inputs = botUsers.map((bot: any) => ({
      name: `bot_${bot.id}`,
      type: 'radio',
      label: `🤖 ${bot.name}`,
      value: bot.id,
    }));

    const alert = await alertController.create({
      header: 'Start Bot Conversation',
      inputs: inputs as any,
      buttons: [
        {
          text: 'Cancel',
          role: 'cancel',
        },
        {
          text: 'Start',
          handler: async (selectedBotId) => {
            if (!selectedBotId) return false;
            try {
              const conv = await api.createBotDM(workspaceId.value, selectedBotId);
              await loadConversations();
              await selectConversation(conv);
            } catch (error) {
              console.error('Error creating bot DM:', error);
            }
            return true;
          },
        },
      ],
    });
    await alert.present();
  } catch (error) {
    console.error('Error loading bots:', error);
  }
}

async function showConversationOptions(conv: any) {
  const isChannel = conv.type.includes('channel');
  const isDM = conv.type === 'dm' || conv.type === 'group_dm';
  const isBot = conv.type === 'bot_dm';

  const buttons: any[] = [];

  // Only channels can be renamed
  if (isChannel) {
    buttons.push({
      text: 'Rename',
      handler: () => renameConversation(conv),
    });
  }

  // All conversation types can be deleted
  buttons.push({
    text: 'Delete',
    role: 'destructive',
    handler: () => deleteConversation(conv),
  });

  buttons.push({
    text: 'Cancel',
    role: 'cancel',
  });

  const actionSheet = await actionSheetController.create({
    header: conv.name || conv.display_name || 'Options',
    buttons,
  });
  await actionSheet.present();
}

async function toggleMute(conv: any) {
  try {
    // TODO: Implement mute/unmute API call
    console.log('Toggle mute for conversation:', conv.id);
    // Placeholder for now - would call API to toggle mute
    // await api.toggleConversationMute(conv.id);
    // await loadConversations();
  } catch (error) {
    console.error('Error toggling mute:', error);
  }
}

async function markAsRead(conv: any) {
  try {
    // Mark the conversation's last message as read
    if (conv.last_message?.id) {
      await api.markAsRead(conv.last_message.id);
      await loadConversations();
    }
  } catch (error) {
    console.error('Error marking as read:', error);
  }
}

async function renameConversation(conv: any) {
  const alert = await alertController.create({
    header: 'Rename Channel',
    inputs: [
      {
        name: 'name',
        type: 'text',
        value: conv.name,
        placeholder: 'Channel name',
      },
    ],
    buttons: [
      {
        text: 'Cancel',
        role: 'cancel',
      },
      {
        text: 'Rename',
        handler: async (data) => {
          if (!data.name || !data.name.trim()) return false;
          try {
            await api.updateConversation(conv.id, { name: data.name.trim() });
            await loadConversations();
          } catch (error) {
            console.error('Error renaming conversation:', error);
          }
          return true;
        },
      },
    ],
  });
  await alert.present();
}

async function deleteConversation(conv: any) {
  const alert = await alertController.create({
    header: 'Delete Conversation',
    message: `Are you sure you want to delete "${conv.name || conv.display_name}"? This cannot be undone.`,
    buttons: [
      {
        text: 'Cancel',
        role: 'cancel',
      },
      {
        text: 'Delete',
        role: 'destructive',
        handler: async () => {
          try {
            await api.deleteConversation(conv.id);

            // If we're currently viewing this conversation, go back to list
            if (currentConversation.value?.id === conv.id) {
              backToConversationList();
            }

            await loadConversations();
          } catch (error) {
            console.error('Error deleting conversation:', error);
          }
        },
      },
    ],
  });
  await alert.present();
}

async function showMessageOptions(message: any) {
  const isOwn = message.user_id === currentUserId.value;

  const buttons: any[] = [
    {
      text: 'Add Reaction',
      icon: happyOutline,
      handler: () => showReactionPicker(message),
    },
    {
      text: 'Reply in Thread',
      icon: chatboxOutline,
      handler: () => replyInThread(message),
    },
  ];

  // Only show edit/delete for own messages
  if (isOwn) {
    buttons.push({
      text: 'Edit',
      handler: () => editMessage(message),
    });
    buttons.push({
      text: 'Delete',
      role: 'destructive',
      handler: () => deleteMessage(message),
    });
  }

  buttons.push({
    text: 'Cancel',
    role: 'cancel',
  });

  const actionSheet = await actionSheetController.create({
    header: 'Message Options',
    buttons,
  });
  await actionSheet.present();
}

async function editMessage(message: any) {
  const alert = await alertController.create({
    header: 'Edit Message',
    inputs: [
      {
        name: 'text',
        type: 'textarea',
        value: message.body_md,
      },
    ],
    buttons: [
      {
        text: 'Cancel',
        role: 'cancel',
      },
      {
        text: 'Save',
        handler: async (data) => {
          if (!data.text.trim()) return false;
          try {
            await api.editMessage(message.id, data.text);
            await loadMessages();
          } catch (error) {
            console.error('Error editing message:', error);
          }
          return true;
        },
      },
    ],
  });
  await alert.present();
}

async function deleteMessage(message: any) {
  const alert = await alertController.create({
    header: 'Delete Message',
    message: 'Are you sure you want to delete this message? This cannot be undone.',
    buttons: [
      {
        text: 'Cancel',
        role: 'cancel',
      },
      {
        text: 'Delete',
        role: 'destructive',
        handler: async () => {
          try {
            await api.deleteMessage(message.id);
            await loadMessages();
          } catch (error) {
            console.error('Error deleting message:', error);
          }
        },
      },
    ],
  });
  await alert.present();
}

function replyInThread(message: any) {
  replyingTo.value = message;
}

function cancelReply() {
  replyingTo.value = null;
}

function showReactionPicker(message: any) {
  reactionPickerMessage.value = message;
  showReactionPickerModal.value = true;
}

function handleReactionSelect(emoji: string) {
  if (reactionPickerMessage.value) {
    toggleReaction(reactionPickerMessage.value, emoji);
  }
}

async function toggleReaction(message: any, emoji: string) {
  try {
    const myReaction = message.reactions?.find(
      (r: any) => r.emoji === emoji && r.user_id === currentUserId.value
    );

    if (myReaction) {
      await api.removeReaction(message.id, emoji);
    } else {
      await api.addReaction(message.id, emoji);
    }
    await loadMessages();
  } catch (error) {
    console.error('Error toggling reaction:', error);
  }
}

async function viewThread(message: any) {
  threadParentMessage.value = message;

  if (!currentConversation.value) return;

  // Load thread replies
  try {
    // Use dedicated thread endpoint
    const result = await api.getThreadReplies(currentConversation.value.id, message.id);
    threadReplies.value = Array.isArray(result) ? result : [];
    showThreadModal.value = true;
  } catch (error) {
    console.error('Error loading thread:', error);
    // Fallback: filter from current messages
    threadReplies.value = messages.value.filter(
      (m: any) => m.parent_message_id === message.id
    );
    showThreadModal.value = true;
  }
}

async function sendThreadReply(data: { parentMessageId: number; text: string }) {
  if (!currentConversation.value) return;

  try {
    const newReply = await api.sendMessage(
      currentConversation.value.id,
      data.text,
      data.parentMessageId
    );

    // Add to thread replies
    threadReplies.value.push(newReply);

    // Reload messages to update thread count
    await loadMessages();
  } catch (error) {
    console.error('Error sending thread reply:', error);
  }
}

function selectFile() {
  fileInput.value?.click();
}

async function handleFileSelect(event: Event) {
  const target = event.target as HTMLInputElement;
  const files = target.files;
  if (!files || files.length === 0 || !currentConversation.value) return;

  // Start uploading immediately
  await uploadFiles(Array.from(files));

  // Reset input
  target.value = '';
}

async function uploadFiles(files: File[]) {
  if (!currentConversation.value) return;

  for (const file of files) {
    // Add to uploading list
    const uploadEntry = { name: file.name, progress: 0 };
    uploadingFiles.value.push(uploadEntry);

    try {
      const result = await api.uploadFile(
        file,
        currentConversation.value.id,
        undefined, // No message ID yet - will attach when sending
        (progress) => {
          // Update progress
          const index = uploadingFiles.value.findIndex((u) => u.name === file.name);
          if (index !== -1) {
            uploadingFiles.value[index].progress = progress;
          }
        }
      );

      // Remove from uploading, add to pending
      uploadingFiles.value = uploadingFiles.value.filter((u) => u.name !== file.name);
      pendingAttachments.value.push(result);

      const toast = await toastController.create({
        message: `${file.name} uploaded`,
        duration: 1500,
        color: 'success',
        position: 'bottom',
      });
      await toast.present();
    } catch (error) {
      console.error('Error uploading file:', error);
      uploadingFiles.value = uploadingFiles.value.filter((u) => u.name !== file.name);

      const toast = await toastController.create({
        message: `Failed to upload ${file.name}`,
        duration: 3000,
        color: 'danger',
        position: 'bottom',
      });
      await toast.present();
    }
  }

  selectedFiles.value = [];
}

function removePendingAttachment(index: number) {
  pendingAttachments.value.splice(index, 1);
}

function viewAttachment(attachment: any) {
  previewAttachment.value = attachment;
  showAttachmentPreview.value = true;
}

async function downloadAttachment(attachment: any) {
  try {
    // Open attachment URL in browser
    window.open(attachment.url, '_blank');
  } catch (error) {
    console.error('Error downloading attachment:', error);
  }
}

function showSearch() {
  showSearchModal.value = true;
}

async function handleSearch(query: string) {
  if (!currentConversation.value || !query.trim()) return;

  try {
    searchModalRef.value?.setLoading(true);
    const result = await api.searchMessages(
      query,
      workspaceId.value,
      currentConversation.value.id
    );

    // API returns { messages: [...] }
    const messages = result.messages || result || [];
    searchModalRef.value?.setResults(messages);
  } catch (error) {
    console.error('Error searching messages:', error);
    searchModalRef.value?.setResults([]);
  }
}

function selectSearchResult(message: any) {
  // Close search and scroll to message
  showSearchModal.value = false;

  // Find the message in the current messages list
  const messageElement = document.getElementById(`message-${message.id}`);
  if (messageElement) {
    messageElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
    // Add a highlight animation
    messageElement.classList.add('highlight-message');
    setTimeout(() => {
      messageElement.classList.remove('highlight-message');
    }, 2000);
  }
}

function startPolling() {
  stopPolling();
  pollInterval = window.setInterval(() => {
    if (currentConversation.value) {
      loadMessages();
    }
  }, 3000);
}

function stopPolling() {
  if (pollInterval) {
    clearInterval(pollInterval);
    pollInterval = null;
  }
}

function startHeartbeat() {
  stopHeartbeat();
  // Send initial heartbeat
  api.sendHeartbeat();
  // Send heartbeat every 60 seconds to update presence
  heartbeatInterval = window.setInterval(() => {
    api.sendHeartbeat();
    // Also reload conversations to update online status indicators
    loadConversations();
  }, 60000); // 60 seconds
}

function stopHeartbeat() {
  if (heartbeatInterval) {
    clearInterval(heartbeatInterval);
    heartbeatInterval = null;
  }
}

watch(() => currentConversation.value, (newVal, oldVal) => {
  if (newVal) {
    if (useWebSocket.value) {
      // Subscribe to conversation channel for real-time updates
      websocketService.subscribeToConversation(newVal.id);
    } else {
      startPolling();
    }
  } else {
    if (useWebSocket.value && oldVal) {
      websocketService.unsubscribeFromConversation(oldVal.id);
    } else {
      stopPolling();
    }
  }
});

// WebSocket event handlers
function setupWebSocketListeners() {
  // Create handler functions and store references for cleanup
  wsHandlers.messageCreated = (data: any) => {
    if (currentConversation.value && data.conversation_id === currentConversation.value.id) {
      // Add message to list if not already present
      if (!messages.value.find((m) => m.id === data.id)) {
        messages.value.push(data);
        nextTick(() => scrollToBottom());

        // Play notification sound if from another user
        if (data.user_id !== currentUserId.value) {
          audioNotificationService.playNotificationSound();
        }
      }
    }
    // Reload conversations to update unread counts
    loadConversations();
  };

  wsHandlers.messageUpdated = (data: any) => {
    const index = messages.value.findIndex((m) => m.id === data.id);
    if (index !== -1) {
      messages.value[index] = { ...messages.value[index], ...data };
    }
  };

  wsHandlers.messageDeleted = (data: any) => {
    messages.value = messages.value.filter((m) => m.id !== data.id);
  };

  wsHandlers.reactionAdded = (data: any) => {
    const message = messages.value.find((m) => m.id === data.message_id);
    if (message) {
      if (!message.reactions) message.reactions = [];
      message.reactions.push(data);
    }
  };

  wsHandlers.reactionRemoved = (data: any) => {
    const message = messages.value.find((m) => m.id === data.message_id);
    if (message && message.reactions) {
      message.reactions = message.reactions.filter(
        (r: any) => !(r.emoji === data.emoji && r.user_id === data.user_id)
      );
    }
  };

  wsHandlers.userTyping = (data: any) => {
    if (currentConversation.value && data.conversation_id === currentConversation.value.id) {
      const userName = data.user_name;
      if (!userName) return;

      // Clear existing timeout for this user if any
      const existingTimeout = typingTimeouts.get(userName);
      if (existingTimeout) {
        clearTimeout(existingTimeout);
      }

      // Add to typing users if not already there
      if (!typingUsers.value.includes(userName)) {
        typingUsers.value.push(userName);
      }

      // Set timeout to remove after 3 seconds
      const timeoutId = setTimeout(() => {
        typingUsers.value = typingUsers.value.filter((u) => u !== userName);
        typingTimeouts.delete(userName);
      }, 3000);
      typingTimeouts.set(userName, timeoutId);
    }
  };

  // Register all handlers
  websocketService.on('message.created', wsHandlers.messageCreated);
  websocketService.on('message.updated', wsHandlers.messageUpdated);
  websocketService.on('message.deleted', wsHandlers.messageDeleted);
  websocketService.on('reaction.added', wsHandlers.reactionAdded);
  websocketService.on('reaction.removed', wsHandlers.reactionRemoved);
  websocketService.on('user.typing', wsHandlers.userTyping);
}

// Clean up WebSocket listeners
function cleanupWebSocketListeners() {
  if (wsHandlers.messageCreated) {
    websocketService.off('message.created', wsHandlers.messageCreated);
    wsHandlers.messageCreated = null;
  }
  if (wsHandlers.messageUpdated) {
    websocketService.off('message.updated', wsHandlers.messageUpdated);
    wsHandlers.messageUpdated = null;
  }
  if (wsHandlers.messageDeleted) {
    websocketService.off('message.deleted', wsHandlers.messageDeleted);
    wsHandlers.messageDeleted = null;
  }
  if (wsHandlers.reactionAdded) {
    websocketService.off('reaction.added', wsHandlers.reactionAdded);
    wsHandlers.reactionAdded = null;
  }
  if (wsHandlers.reactionRemoved) {
    websocketService.off('reaction.removed', wsHandlers.reactionRemoved);
    wsHandlers.reactionRemoved = null;
  }
  if (wsHandlers.userTyping) {
    websocketService.off('user.typing', wsHandlers.userTyping);
    wsHandlers.userTyping = null;
  }

  // Clear all typing timeouts
  typingTimeouts.forEach((timeoutId) => clearTimeout(timeoutId));
  typingTimeouts.clear();
}

async function initializeWebSocket() {
  const token = api.getToken();
  const baseUrl = import.meta.env.VITE_API_URL || 'http://localhost/api';

  if (token) {
    try {
      await websocketService.connect(token, baseUrl);
      setupWebSocketListeners();

      // Subscribe to workspace for presence updates
      if (workspaceId.value) {
        websocketService.subscribeToWorkspace(workspaceId.value);
      }
    } catch (error) {
      console.error('WebSocket connection failed, falling back to polling:', error);
      useWebSocket.value = false;
    }
  }
}

onMounted(async () => {
  // Explicitly ensure no conversation is selected on mount
  currentConversation.value = null;
  messages.value = [];
  await loadConversations();
  // Start heartbeat to update user's online status
  startHeartbeat();

  // Initialize WebSocket if enabled
  if (useWebSocket.value) {
    await initializeWebSocket();
  }
  // Don't auto-select a conversation - let user choose from the list
});

onUnmounted(() => {
  stopPolling();
  stopHeartbeat();

  // Clean up WebSocket listeners and disconnect
  if (useWebSocket.value) {
    cleanupWebSocketListeners();
    websocketService.disconnect();
  }
});
</script>

<style scoped>
.full-width {
  width: 100%;
  height: 100%;
}

.conversations-view {
  height: 100%;
  overflow-y: auto;
  background: var(--ion-background-color);
}

.conversation-list {
  background: transparent;
}

.conversation-list ion-list-header {
  padding: 16px 16px 8px;
}

.section-header {
  font-size: 14px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: var(--ion-color-medium);
}

.chat-container {
  display: flex;
  flex-direction: column;
  height: 100%;
  width: 100%;
  position: relative;
  background: var(--ion-background-color);
}

.messages-list {
  flex: 1;
  overflow-y: auto;
  padding: 16px;
  background: var(--ion-background-color);
}

.message {
  margin-bottom: 16px;
  position: relative;
}

.message-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 4px;
}

.message-header strong {
  color: var(--ion-color-primary);
}

.message-time {
  font-size: 12px;
  color: var(--ion-color-medium);
}

.message-body {
  padding: 8px 12px;
  background: var(--ion-color-light);
  border-radius: 8px;
}

.message-body :deep(p) {
  margin: 0;
}

.message-body :deep(code) {
  background: var(--ion-color-medium);
  padding: 2px 4px;
  border-radius: 4px;
}

.reactions {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  margin-top: 4px;
}

.reactions ion-chip {
  font-size: 12px;
  height: 24px;
}

.reactions ion-chip.my-reaction {
  background: var(--ion-color-primary-tint);
}

.add-reaction-btn {
  margin-top: 4px;
  height: 24px;
  font-size: 12px;
}

.thread-info {
  margin-top: 4px;
}

.link-preview {
  margin-top: 8px;
  border: 1px solid var(--ion-color-light-shade);
  border-radius: 8px;
  overflow: hidden;
}

.link-preview img {
  width: 100%;
  height: auto;
}

.link-preview-content {
  padding: 8px;
}

.link-preview-content h4 {
  margin: 0 0 4px 0;
  font-size: 14px;
}

.link-preview-content p {
  margin: 0;
  font-size: 12px;
  color: var(--ion-color-medium);
}

.attachments {
  margin-top: 8px;
}

.attachment {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px;
  background: var(--ion-color-light);
  border-radius: 4px;
  margin-bottom: 4px;
}

.message-input-container {
  border-top: 1px solid var(--ion-color-step-150);
  padding: 12px 16px;
  width: 100%;
  min-width: 100%;
  box-sizing: border-box;
  position: sticky;
  bottom: 0;
  background: var(--ion-background-color);
  box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.05);
}

/* Dark mode - media query (respects light mode override) */
@media (prefers-color-scheme: dark) {
  :root:not(.ion-palette-light) .message-input-container {
    box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.3);
  }
}

/* Dark mode - class-based toggle */
:root.ion-palette-dark .message-input-container {
  box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.3);
}

.message-input-container ion-item {
  --background: transparent;
  --border-radius: 24px;
  --padding-start: 0;
  --padding-end: 0;
  --inner-padding-end: 0;
  width: 100%;
  min-width: 100%;
}

.message-input-container ion-textarea {
  --background: var(--ion-color-light);
  --padding-start: 16px;
  --padding-end: 16px;
  --padding-top: 10px;
  --padding-bottom: 10px;
  border-radius: 24px;
  font-size: 15px;
  max-height: 120px;
}

/* Dark mode for textarea - media query (respects light mode override) */
@media (prefers-color-scheme: dark) {
  :root:not(.ion-palette-light) .message-input-container ion-textarea {
    --background: var(--ion-color-step-100);
  }
}

/* Dark mode for textarea - class-based toggle */
:root.ion-palette-dark .message-input-container ion-textarea {
  --background: var(--ion-color-step-100);
}

.message-input-container ion-button {
  --border-radius: 50%;
  margin: 0 4px;
}

.no-conversation {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100%;
}

.no-conversation ion-icon {
  font-size: 64px;
  margin-bottom: 16px;
}

.selected {
  --background: var(--ion-color-primary-tint);
}

.unread-badge {
  color: var(--ion-color-primary);
  font-weight: bold;
  font-size: 12px;
}

.status-indicator {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--ion-color-medium);
  margin-right: 8px;
}

.status-indicator.online {
  background: var(--ion-color-success);
  box-shadow: 0 0 6px var(--ion-color-success);
}

.conversations-view {
  width: 100%;
  height: 100%;
  overflow-y: auto;
}

.notes-to-self-item {
  --background: var(--ion-color-primary-tint);
  margin: 8px 16px;
  border-radius: 12px;
}

.notes-to-self-item h2 {
  font-weight: 600;
}

.notes-to-self-item p {
  color: var(--ion-color-medium);
  font-size: 13px;
}

/* Dark mode for notes to self */
@media (prefers-color-scheme: dark) {
  :root:not(.ion-palette-light) .notes-to-self-item {
    --background: var(--ion-color-step-100);
  }
}

:root.ion-palette-dark .notes-to-self-item {
  --background: var(--ion-color-step-100);
}

/* Upload progress styles */
.upload-progress {
  padding: 8px 12px;
  background: var(--ion-color-light);
  border-radius: 8px;
  margin-bottom: 8px;
}

.upload-item {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 4px;
}

.upload-item:last-child {
  margin-bottom: 0;
}

.upload-item ion-icon {
  color: var(--ion-color-primary);
  font-size: 18px;
}

.upload-name {
  flex: 1;
  font-size: 13px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.upload-item ion-progress-bar {
  width: 80px;
  height: 4px;
  border-radius: 2px;
}

/* Pending attachments styles */
.pending-attachments {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  padding: 8px 0;
}

.pending-attachments ion-chip {
  --background: var(--ion-color-light);
  font-size: 12px;
  height: 28px;
  cursor: pointer;
}

.pending-attachments ion-chip ion-icon:last-child {
  color: var(--ion-color-danger);
  margin-left: 4px;
}

/* Dark mode for upload/attachment areas */
@media (prefers-color-scheme: dark) {
  :root:not(.ion-palette-light) .upload-progress,
  :root:not(.ion-palette-light) .pending-attachments ion-chip {
    --background: var(--ion-color-step-100);
  }
}

:root.ion-palette-dark .upload-progress,
:root.ion-palette-dark .pending-attachments ion-chip {
  --background: var(--ion-color-step-100);
}

/* Message highlight animation for search results */
@keyframes highlight-pulse {
  0%, 100% {
    background: transparent;
  }
  50% {
    background: var(--ion-color-warning-tint);
  }
}

.highlight-message {
  animation: highlight-pulse 0.5s ease-in-out 2;
  border-radius: 8px;
}
</style>
