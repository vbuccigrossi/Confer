<script setup>
import { ref, computed, onMounted, nextTick, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AttachmentList from './AttachmentList.vue';
import LinkPreview from './LinkPreview.vue';
import EmojiPicker from './EmojiPicker.vue';
import hljs from 'highlight.js';

const props = defineProps({
    message: Object,
});

const emit = defineEmits(['reply-thread', 'edit', 'delete']);

const page = usePage();
const currentUserId = computed(() => page.props.auth.user.id);
const isOwn = computed(() => props.message.user_id === currentUserId.value);
const showActions = ref(false);
const showEmojiPicker = ref(false);
const messageBodyRef = ref(null);
const emojiButtonRef = ref(null);

const canEdit = computed(() => {
    if (!isOwn.value) return false;
    const editWindow = 15 * 60 * 1000; // 15 minutes
    const messageAge = new Date() - new Date(props.message.created_at);
    return messageAge < editWindow;
});

const formatTime = (timestamp) => {
    const date = new Date(timestamp);
    const now = new Date();
    const isToday = date.toDateString() === now.toDateString();

    if (isToday) {
        return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
    }
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) + ' at ' +
           date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
};

const addReaction = async (emoji) => {
    try {
        await window.axios.post(`/api/messages/${props.message.id}/reactions`, {
            emoji: emoji
        });
        // Reload messages to show the new reaction
        router.reload({ only: ['messages'] });
    } catch (error) {
        console.error('Failed to add reaction:', error);
    }
};

const removeReaction = async (reactionId) => {
    try {
        await window.axios.delete(`/api/reactions/${reactionId}`);
        // Reload messages to show updated reactions
        router.reload({ only: ['messages'] });
    } catch (error) {
        console.error('Failed to remove reaction:', error);
    }
};

const toggleReaction = (emoji) => {
    const existingReaction = props.message.reactions?.find(
        r => r.emoji === emoji && r.user_id === currentUserId.value
    );

    if (existingReaction) {
        removeReaction(existingReaction.id);
    } else {
        addReaction(emoji);
    }
};

const groupedReactions = computed(() => {
    const groups = {};
    props.message.reactions?.forEach(reaction => {
        if (!groups[reaction.emoji]) {
            groups[reaction.emoji] = {
                emoji: reaction.emoji,
                count: 0,
                userIds: [],
                hasCurrentUser: false,
            };
        }
        groups[reaction.emoji].count++;
        groups[reaction.emoji].userIds.push(reaction.user_id);
        if (reaction.user_id === currentUserId.value) {
            groups[reaction.emoji].hasCurrentUser = true;
            groups[reaction.emoji].currentUserReactionId = reaction.id;
        }
    });
    return Object.values(groups);
});

// Apply syntax highlighting to code blocks
const highlightCode = () => {
    if (messageBodyRef.value) {
        const codeBlocks = messageBodyRef.value.querySelectorAll('pre code');
        codeBlocks.forEach((block) => {
            // Skip if already highlighted
            if (!block.classList.contains('hljs')) {
                hljs.highlightElement(block);
            }
        });
    }
};

// Apply highlighting when component mounts
onMounted(() => {
    nextTick(() => {
        highlightCode();
    });
});

// Re-apply highlighting when message content changes
watch(() => props.message.body_html, () => {
    nextTick(() => {
        highlightCode();
    });
});
</script>

<template>
    <div
        class="group hover:bg-light-bg dark:hover:bg-dark-bg -mx-6 px-6 py-2 transition-colors"
        @mouseenter="showActions = true"
        @mouseleave="showActions = false"
    >
        <div class="flex items-start space-x-3">
            <!-- Avatar -->
            <div class="flex-shrink-0">
                <img
                    v-if="message.user?.profile_photo_url"
                    :src="message.user.profile_photo_url"
                    :alt="message.user.name"
                    class="h-10 w-10 rounded-full object-cover"
                />
                <div v-else class="h-10 w-10 rounded-full bg-light-accent dark:bg-dark-accent flex items-center justify-center text-white font-semibold">
                    {{ message.user?.name?.charAt(0).toUpperCase() || '?' }}
                </div>
            </div>

            <!-- Message Content -->
            <div class="flex-1 min-w-0">
                <!-- Header -->
                <div class="flex items-baseline space-x-2">
                    <span class="font-semibold text-light-text-primary dark:text-dark-text-primary">
                        {{ message.user?.name || 'Unknown User' }}
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
                    v-if="message.body_md"
                    ref="messageBodyRef"
                    class="mt-1 text-sm text-light-text-primary dark:text-dark-text-primary prose prose-sm max-w-none dark:prose-invert"
                    v-html="message.body_html"
                ></div>

                <!-- Attachments -->
                <AttachmentList :attachments="message.attachments" />

                <!-- Link Previews -->
                <div v-if="message.link_previews && message.link_previews.length > 0">
                    <LinkPreview
                        v-for="preview in message.link_previews"
                        :key="preview.id"
                        :preview="preview"
                    />
                </div>

                <!-- Reactions -->
                <div v-if="groupedReactions.length > 0" class="mt-2 flex flex-wrap gap-1">
                    <button
                        v-for="reaction in groupedReactions"
                        :key="reaction.emoji"
                        @click="toggleReaction(reaction.emoji)"
                        :class="[
                            'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border transition-all',
                            reaction.hasCurrentUser
                                ? 'bg-light-accent/10 dark:bg-dark-accent/10 border-light-accent dark:border-dark-accent text-light-accent dark:text-dark-accent shadow-neon'
                                : 'bg-light-bg dark:bg-dark-bg border-light-border dark:border-dark-border text-light-text-secondary dark:text-dark-text-secondary hover:border-light-accent dark:hover:border-dark-accent'
                        ]"
                    >
                        <span>{{ reaction.emoji }}</span>
                        <span class="ml-1">{{ reaction.count }}</span>
                    </button>
                </div>

                <!-- Thread Reply Count with Last Reply Info -->
                <button
                    v-if="message.reply_count > 0"
                    @click="emit('reply-thread', message)"
                    class="mt-2 inline-flex items-center space-x-2 text-xs text-light-accent dark:text-dark-accent hover:text-light-accent-hover dark:hover:text-dark-accent-hover font-medium transition-colors"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                    </svg>
                    <span>{{ message.reply_count }} {{ message.reply_count === 1 ? 'reply' : 'replies' }}</span>
                    <span v-if="message.last_reply_user" class="text-light-text-muted dark:text-dark-text-muted">
                        • Last from {{ message.last_reply_user.name }}
                    </span>
                    <span v-if="message.last_reply_at" class="text-light-text-muted dark:text-dark-text-muted">
                        {{ formatTime(message.last_reply_at) }}
                    </span>
                </button>
            </div>

            <!-- Actions (shown on hover) -->
            <div v-if="showActions" class="flex items-center space-x-1 opacity-0 group-hover:opacity-100 transition-opacity">
                <!-- Quick reactions -->
                <button
                    @click="toggleReaction('👍')"
                    class="p-1 rounded hover:bg-light-border dark:hover:bg-dark-border transition-colors"
                    title="👍"
                >
                    👍
                </button>
                <button
                    @click="toggleReaction('❤️')"
                    class="p-1 rounded hover:bg-light-border dark:hover:bg-dark-border transition-colors"
                    title="❤️"
                >
                    ❤️
                </button>
                <button
                    @click="toggleReaction('😄')"
                    class="p-1 rounded hover:bg-light-border dark:hover:bg-dark-border transition-colors"
                    title="😄"
                >
                    😄
                </button>

                <!-- More emoji reactions -->
                <button
                    ref="emojiButtonRef"
                    @click.stop="showEmojiPicker = !showEmojiPicker"
                    class="p-1 rounded hover:bg-light-border dark:hover:bg-dark-border transition-colors"
                    title="Add reaction"
                >
                    <svg class="h-4 w-4 text-light-text-secondary dark:text-dark-text-secondary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </button>

                <!-- Emoji Picker (using Teleport for fixed positioning) -->
                <Teleport to="body">
                    <EmojiPicker
                        :show="showEmojiPicker"
                        :button-ref="emojiButtonRef"
                        @select="(emoji) => { toggleReaction(emoji); showEmojiPicker = false; }"
                        @close="showEmojiPicker = false"
                    />
                </Teleport>

                <!-- Thread reply -->
                <button
                    v-if="!message.parent_message_id"
                    @click="emit('reply-thread', message)"
                    class="p-1 rounded hover:bg-light-border dark:hover:bg-dark-border transition-colors"
                    title="Reply in thread"
                >
                    <svg class="h-4 w-4 text-light-text-secondary dark:text-dark-text-secondary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                    </svg>
                </button>

                <!-- Edit -->
                <button
                    v-if="canEdit"
                    @click="emit('edit', message)"
                    class="p-1 rounded hover:bg-light-border dark:hover:bg-dark-border transition-colors"
                    title="Edit message"
                >
                    <svg class="h-4 w-4 text-light-text-secondary dark:text-dark-text-secondary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                </button>

                <!-- Delete -->
                <button
                    v-if="isOwn"
                    @click="emit('delete', message)"
                    class="p-1 rounded hover:bg-light-border dark:hover:bg-dark-border transition-colors"
                    title="Delete message"
                >
                    <svg class="h-4 w-4 text-light-text-secondary dark:text-dark-text-secondary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                </button>
            </div>
        </div>
    </div>
</template>
