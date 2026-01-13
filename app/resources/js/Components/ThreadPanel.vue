<script setup>
import { ref } from 'vue';
import MessageItem from './MessageItem.vue';
import MessageComposer from './MessageComposer.vue';

const props = defineProps({
    parentMessage: Object,
    threadReplies: Array,
    conversationId: Number,
});

const emit = defineEmits(['close']);
</script>

<template>
    <div class="w-96 border-l border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface flex flex-col h-full">
        <!-- Thread Header -->
        <div class="px-6 py-4 border-b border-light-border dark:border-dark-border flex items-center justify-between">
            <h3 class="text-lg font-semibold text-light-text-primary dark:text-dark-text-primary">Thread</h3>
            <button
                @click="emit('close')"
                class="text-light-text-secondary dark:text-dark-text-secondary hover:text-light-text-primary dark:hover:text-dark-text-primary"
            >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Parent Message -->
        <div class="px-6 py-4 border-b border-light-border dark:border-dark-border bg-light-bg dark:bg-dark-bg">
            <MessageItem
                :message="parentMessage"
                @edit="() => {}"
                @delete="() => {}"
            />
        </div>

        <!-- Thread Replies -->
        <div class="flex-1 overflow-y-auto px-6 py-4">
            <div v-if="!threadReplies || threadReplies.length === 0" class="text-center text-light-text-secondary dark:text-dark-text-secondary py-8">
                <p class="text-sm">No replies yet</p>
            </div>
            <div v-else class="space-y-4">
                <MessageItem
                    v-for="reply in threadReplies"
                    :key="reply.id"
                    :message="reply"
                    @edit="() => {}"
                    @delete="() => {}"
                />
            </div>
        </div>

        <!-- Reply Composer -->
        <MessageComposer
            :conversation-id="conversationId"
            :parent-message-id="parentMessage.id"
            placeholder="Reply to thread..."
            @sent="() => {}"
        />
    </div>
</template>
