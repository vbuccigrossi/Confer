<template>
  <div class="composer border-t bg-white p-4">
    <form @submit.prevent="handleSubmit" class="space-y-3">
      <!-- Textarea -->
      <div class="relative">
        <textarea
          v-model="body"
          :placeholder="placeholder"
          class="w-full px-3 py-2 border rounded-lg resize-none focus:outline-none focus:ring-2 focus:ring-blue-500"
          :rows="rows"
          @keydown.meta.enter="handleSubmit"
          @keydown.ctrl.enter="handleSubmit"
        ></textarea>
      </div>

      <!-- Actions Bar -->
      <div class="flex items-center justify-between">
        <div class="flex gap-2">
          <!-- Formatting Hints -->
          <button
            type="button"
            class="text-xs text-gray-500 hover:text-gray-700 px-2 py-1 rounded hover:bg-gray-100"
            title="**bold** _italic_ `code`"
          >
            Markdown
          </button>
        </div>

        <!-- Submit Button -->
        <button
          type="submit"
          :disabled="!body.trim() || submitting"
          class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {{ submitting ? "Sending..." : "Send" }}
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, defineProps, defineEmits } from "vue";

const props = defineProps({
  conversationId: {
    type: Number,
    required: true
  },
  parentMessageId: {
    type: Number,
    default: null
  },
  placeholder: {
    type: String,
    default: "Type a message..."
  },
  rows: {
    type: Number,
    default: 3
  }
});

const emit = defineEmits(["message-sent"]);

const body = ref("");
const submitting = ref(false);

const handleSubmit = async () => {
  if (!body.value.trim() || submitting.value) return;

  submitting.value = true;

  try {
    const response = await fetch(`/api/conversations/${props.conversationId}/messages`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "Accept": "application/json",
        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content
      },
      body: JSON.stringify({
        body_md: body.value,
        parent_message_id: props.parentMessageId
      })
    });

    if (response.ok) {
      const message = await response.json();
      body.value = "";
      emit("message-sent", message);
    } else {
      console.error("Failed to send message:", response.statusText);
    }
  } catch (error) {
    console.error("Error sending message:", error);
  } finally {
    submitting.value = false;
  }
};
</script>

<style scoped>
.composer {
  @apply sticky bottom-0;
}
</style>
