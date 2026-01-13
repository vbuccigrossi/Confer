<template>
  <div class="message-list">
    <div
      v-for="message in messages"
      :key="message.id"
      class="message-item p-4 hover:bg-light-bg dark:hover:bg-dark-bg border-b border-light-border dark:border-dark-border"
    >
      <!-- User Avatar & Info -->
      <div class="flex items-start gap-3">
        <div class="w-10 h-10 rounded bg-light-accent dark:bg-dark-accent flex-shrink-0"></div>

        <div class="flex-1 min-w-0">
          <!-- Header -->
          <div class="flex items-baseline gap-2 mb-1">
            <span class="font-semibold text-light-text-primary dark:text-dark-text-primary">
              {{ message.user?.name || "Unknown User" }}
            </span>
            <span class="text-xs text-light-text-muted dark:text-dark-text-muted">
              {{ formatTime(message.created_at) }}
            </span>
            <span v-if="message.edited_at" class="text-xs text-light-text-muted dark:text-dark-text-muted">
              (edited)
            </span>
          </div>

          <!-- Message Body -->
          <div
            class="prose prose-sm dark:prose-invert max-w-none text-light-text-primary dark:text-dark-text-primary"
            v-html="message.body_html"
          ></div>

          <!-- Reactions -->
          <div v-if="message.reactions?.length" class="flex gap-1 mt-2">
            <button
              v-for="reaction in message.reactions"
              :key="reaction.id"
              class="px-2 py-1 text-xs rounded border border-light-border dark:border-dark-border hover:bg-light-bg dark:hover:bg-dark-bg"
              @click="$emit('toggle-reaction', message.id, reaction.emoji)"
            >
              {{ reaction.emoji }} {{ reaction.count || 1 }}
            </button>
          </div>

          <!-- Thread Reply Count -->
          <div v-if="message.reply_count" class="mt-2">
            <button
              class="text-sm text-light-accent dark:text-dark-accent hover:underline"
              @click="$emit('open-thread', message.id)"
            >
              {{ message.reply_count }} {{ message.reply_count === 1 ? "reply" : "replies" }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="p-4 text-center text-light-text-muted dark:text-dark-text-muted">
      Loading messages...
    </div>

    <!-- Empty State -->
    <div v-if="!loading && !messages.length" class="p-8 text-center text-light-text-muted dark:text-dark-text-muted">
      No messages yet. Start the conversation!
    </div>
  </div>
</template>

<script setup>
import { defineProps, defineEmits } from "vue";

defineProps({
  messages: {
    type: Array,
    default: () => []
  },
  loading: {
    type: Boolean,
    default: false
  }
});

defineEmits(["toggle-reaction", "open-thread"]);

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
</script>

<style scoped>
.message-list {
  @apply flex flex-col;
}

.message-item:last-child {
  @apply border-b-0;
}
</style>
