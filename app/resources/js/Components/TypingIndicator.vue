<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    conversationId: Number,
});

const typingUsers = ref([]);
let typingTimeout = null;

// Computed message
const typingMessage = computed(() => {
    const count = typingUsers.value.length;

    if (count === 0) return null;

    if (count === 1) {
        return `${typingUsers.value[0].user_name} is typing...`;
    }

    if (count === 2) {
        return `${typingUsers.value[0].user_name} and ${typingUsers.value[1].user_name} are typing...`;
    }

    return `${typingUsers.value[0].user_name} and ${count - 1} others are typing...`;
});

// Listen for typing events
const setupTypingListener = () => {
    if (!props.conversationId || !window.Echo) return;

    window.Echo.private(`conversation.${props.conversationId}`)
        .listen('.user.typing', (event) => {
            // Add user to typing list if not already there
            const existingIndex = typingUsers.value.findIndex(u => u.user_id === event.user_id);

            if (existingIndex === -1) {
                typingUsers.value.push({
                    user_id: event.user_id,
                    user_name: event.user_name,
                });
            }

            // Set timeout to remove user after 5 seconds
            clearUserTypingTimeout(event.user_id);
            setUserTypingTimeout(event.user_id);
        })
        .listen('.user.stopped-typing', (event) => {
            // Remove user from typing list
            typingUsers.value = typingUsers.value.filter(u => u.user_id !== event.user_id);
            clearUserTypingTimeout(event.user_id);
        });
};

const userTimeouts = ref({});

const setUserTypingTimeout = (userId) => {
    userTimeouts.value[userId] = setTimeout(() => {
        typingUsers.value = typingUsers.value.filter(u => u.user_id !== userId);
        delete userTimeouts.value[userId];
    }, 5000);
};

const clearUserTypingTimeout = (userId) => {
    if (userTimeouts.value[userId]) {
        clearTimeout(userTimeouts.value[userId]);
        delete userTimeouts.value[userId];
    }
};

// Watch for conversation changes
watch(() => props.conversationId, () => {
    typingUsers.value = [];
    Object.values(userTimeouts.value).forEach(timeout => clearTimeout(timeout));
    userTimeouts.value = {};
    setupTypingListener();
});

onMounted(() => {
    setupTypingListener();
});

onUnmounted(() => {
    Object.values(userTimeouts.value).forEach(timeout => clearTimeout(timeout));
});
</script>

<template>
    <div
        v-if="typingMessage"
        class="px-6 py-2 text-sm text-light-text-muted dark:text-dark-text-muted italic flex items-center"
    >
        <!-- Animated dots -->
        <div class="flex space-x-1 mr-2">
            <div class="w-2 h-2 bg-light-accent dark:bg-dark-accent rounded-full animate-bounce" style="animation-delay: 0ms"></div>
            <div class="w-2 h-2 bg-light-accent dark:bg-dark-accent rounded-full animate-bounce" style="animation-delay: 150ms"></div>
            <div class="w-2 h-2 bg-light-accent dark:bg-dark-accent rounded-full animate-bounce" style="animation-delay: 300ms"></div>
        </div>
        <span>{{ typingMessage }}</span>
    </div>
</template>

<style scoped>
@keyframes bounce {
    0%, 80%, 100% {
        transform: translateY(0);
    }
    40% {
        transform: translateY(-8px);
    }
}

.animate-bounce {
    animation: bounce 1.4s infinite;
}
</style>
