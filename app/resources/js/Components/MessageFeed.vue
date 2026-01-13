<script setup>
import { ref, onMounted, nextTick, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import MessageItem from './MessageItem.vue';
import { playNotificationSound } from '@/Utils/notificationSound';

const props = defineProps({
    conversationId: Number,
    messages: Array,
});

const emit = defineEmits(['load-more', 'reply-thread', 'edit-message', 'delete-message']);

const messagesContainer = ref(null);
const isAtBottom = ref(true);
const previousMessageIds = ref(new Set());

const page = usePage();
const currentUserId = page.props.auth.user.id;
const soundNotificationsEnabled = page.props.auth.user.sound_notifications;

const scrollToBottom = () => {
    if (messagesContainer.value) {
        messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
    }
};

// Ensure scroll after images load
const scrollAfterImagesLoad = () => {
    if (!messagesContainer.value) return;

    const images = messagesContainer.value.querySelectorAll('img');
    if (images.length === 0) {
        scrollToBottom();
        return;
    }

    let loadedCount = 0;
    const totalImages = images.length;

    const checkAllLoaded = () => {
        loadedCount++;
        if (loadedCount === totalImages) {
            scrollToBottom();
        }
    };

    images.forEach(img => {
        if (img.complete) {
            checkAllLoaded();
        } else {
            img.addEventListener('load', checkAllLoaded);
            img.addEventListener('error', checkAllLoaded); // Also handle errors
        }
    });
};

const handleScroll = () => {
    if (!messagesContainer.value) return;

    const { scrollTop, scrollHeight, clientHeight } = messagesContainer.value;
    isAtBottom.value = scrollHeight - scrollTop - clientHeight < 50;

    // Load more when scrolled to top
    if (scrollTop < 100) {
        emit('load-more');
    }
};

onMounted(() => {
    // Initialize previousMessageIds with current messages
    if (props.messages) {
        previousMessageIds.value = new Set(props.messages.map(m => m.id));
    }

    // Scroll to bottom after DOM is ready and images are loaded
    nextTick(() => {
        scrollAfterImagesLoad();
    });
});

// Watch for new messages and play sound
watch(() => props.messages, (newMessages, oldMessages) => {
    if (!newMessages || !soundNotificationsEnabled) return;

    // Check if there are new messages
    const currentIds = new Set(newMessages.map(m => m.id));
    const hasNewMessages = newMessages.some(msg => 
        !previousMessageIds.value.has(msg.id) && msg.user_id !== currentUserId
    );

    if (hasNewMessages) {
        playNotificationSound();
    }

    // Update previousMessageIds
    previousMessageIds.value = currentIds;

    // Scroll to bottom if needed
    if (isAtBottom.value) {
        nextTick(() => scrollAfterImagesLoad());
    }
}, { deep: true });
</script>

<template>
    <div
        ref="messagesContainer"
        class="flex-1 overflow-y-auto px-6 py-4"
        @scroll="handleScroll"
    >
        <div v-if="!messages || messages.length === 0" class="flex items-center justify-center h-full">
            <div class="text-center text-light-text-muted dark:text-dark-text-muted">
                <svg class="mx-auto h-12 w-12 text-light-text-muted dark:text-dark-text-muted mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                </svg>
                <p class="text-sm">No messages yet. Start the conversation!</p>
            </div>
        </div>

        <div v-else class="space-y-4">
            <MessageItem
                v-for="message in messages"
                :key="message.id"
                :message="message"
                @reply-thread="emit('reply-thread', $event)"
                @edit="emit('edit-message', $event)"
                @delete="emit('delete-message', $event)"
            />
        </div>
    </div>
</template>
