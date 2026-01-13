<template>
  <div class="thread-pane flex flex-col h-full bg-light-surface dark:bg-dark-surface border-l border-light-border dark:border-dark-border">
    <!-- Header -->
    <div class="flex items-center justify-between p-4 border-b border-light-border dark:border-dark-border">
      <h3 class="font-semibold text-lg text-light-text-primary dark:text-dark-text-primary">Thread</h3>
      <button
        @click="$emit('close')"
        class="text-light-text-muted dark:text-dark-text-muted hover:text-light-text-primary dark:hover:text-dark-text-primary"
        aria-label="Close thread"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>

    <!-- Parent Message -->
    <div v-if="parentMessage" class="p-4 bg-light-bg dark:bg-dark-bg border-b border-light-border dark:border-dark-border">
      <div class="flex items-start gap-3">
        <div class="w-10 h-10 rounded bg-light-accent dark:bg-dark-accent flex-shrink-0"></div>
        <div class="flex-1 min-w-0">
          <div class="flex items-baseline gap-2 mb-1">
            <span class="font-semibold text-light-text-primary dark:text-dark-text-primary">
              {{ parentMessage.user?.name || "Unknown User" }}
            </span>
            <span class="text-xs text-light-text-muted dark:text-dark-text-muted">
              {{ formatTime(parentMessage.created_at) }}
            </span>
          </div>
          <div
            class="prose prose-sm dark:prose-invert max-w-none text-light-text-primary dark:text-dark-text-primary"
            v-html="parentMessage.body_html"
          ></div>
        </div>
      </div>
    </div>

    <!-- Thread Replies -->
    <div class="flex-1 overflow-y-auto p-4 space-y-4">
      <div
        v-for="reply in replies"
        :key="reply.id"
        class="flex items-start gap-3"
      >
        <div class="w-8 h-8 rounded bg-light-accent dark:bg-dark-accent flex-shrink-0"></div>
        <div class="flex-1 min-w-0">
          <div class="flex items-baseline gap-2 mb-1">
            <span class="font-semibold text-sm text-light-text-primary dark:text-dark-text-primary">
              {{ reply.user?.name || "Unknown User" }}
            </span>
            <span class="text-xs text-light-text-muted dark:text-dark-text-muted">
              {{ formatTime(reply.created_at) }}
            </span>
          </div>
          <div
            class="prose prose-sm dark:prose-invert max-w-none text-light-text-primary dark:text-dark-text-primary"
            v-html="reply.body_html"
          ></div>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="text-center text-light-text-muted dark:text-dark-text-muted">
        Loading replies...
      </div>

      <!-- Empty State -->
      <div v-if="!loading && !replies.length" class="text-center text-light-text-muted dark:text-dark-text-muted">
        No replies yet. Start the thread!
      </div>
    </div>

    <!-- Composer -->
    <div class="border-t border-light-border dark:border-dark-border">
      <Composer
        v-if="parentMessage"
        :conversation-id="parentMessage.conversation_id"
        :parent-message-id="parentMessage.id"
        placeholder="Reply to thread..."
        :rows="2"
        @message-sent="handleReplySent"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, defineProps, defineEmits, watch } from "vue";
import Composer from "./Composer.vue";

const props = defineProps({
  messageId: {
    type: Number,
    default: null
  },
  conversationId: {
    type: Number,
    required: true
  }
});

const emit = defineEmits(["close", "reply-sent"]);

const parentMessage = ref(null);
const replies = ref([]);
const loading = ref(false);

const loadThread = async () => {
  if (!props.messageId) return;

  loading.value = true;

  try {
    // Load parent message
    const parentResponse = await fetch(`/api/messages/${props.messageId}`);
    if (parentResponse.ok) {
      parentMessage.value = await parentResponse.json();
    }

    // Load replies
    const repliesResponse = await fetch(
      `/api/conversations/${props.conversationId}/messages?parent_message_id=${props.messageId}`
    );
    if (repliesResponse.ok) {
      const data = await repliesResponse.json();
      replies.value = data.messages || [];
    }
  } catch (error) {
    console.error("Error loading thread:", error);
  } finally {
    loading.value = false;
  }
};

const handleReplySent = (message) => {
  replies.value.push(message);
  emit("reply-sent", message);
};

const formatTime = (timestamp) => {
  if (!timestamp) return "";
  const date = new Date(timestamp);
  const now = new Date();
  const diffMs = now - date;
  const diffMins = Math.floor(diffMs / 60000);

  if (diffMins < 1) return "just now";
  if (diffMins < 60) return `${diffMins}m ago`;

  const diffHours = Math.floor(diffMins / 60);
  if (diffHours < 24) return `${diffHours}h ago`;

  return date.toLocaleDateString();
};

watch(() => props.messageId, loadThread, { immediate: true });
</script>

<style scoped>
.thread-pane {
  @apply w-96;
}
</style>
