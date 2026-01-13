<script setup>
import { ref, computed, watch, nextTick } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    show: Boolean,
    conversations: Array,
});

const emit = defineEmits(['close']);

const searchQuery = ref('');
const selectedIndex = ref(0);
const inputRef = ref(null);

// Filter conversations based on search query
const filteredConversations = computed(() => {
    if (!searchQuery.value) {
        return props.conversations || [];
    }

    const query = searchQuery.value.toLowerCase();
    return (props.conversations || []).filter(conv => {
        const name = conv.display_name || conv.name || '';
        return name.toLowerCase().includes(query);
    });
});

// Reset state when modal opens
watch(() => props.show, async (show) => {
    if (show) {
        searchQuery.value = '';
        selectedIndex.value = 0;
        await nextTick();
        inputRef.value?.focus();
    }
});

// Handle keyboard navigation
const handleKeydown = (event) => {
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        selectedIndex.value = Math.min(selectedIndex.value + 1, filteredConversations.value.length - 1);
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        selectedIndex.value = Math.max(selectedIndex.value - 1, 0);
    } else if (event.key === 'Enter' && filteredConversations.value.length > 0) {
        event.preventDefault();
        selectConversation(filteredConversations.value[selectedIndex.value]);
    } else if (event.key === 'Escape') {
        event.preventDefault();
        emit('close');
    }
};

// Reset selected index when search changes
watch(searchQuery, () => {
    selectedIndex.value = 0;
});

const selectConversation = (conversation) => {
    if (!conversation) return;
    emit('close');
    router.visit(`/conversations/${conversation.id}`);
};

const getConversationIcon = (conversation) => {
    if (conversation.type === 'public_channel') return '#';
    if (conversation.type === 'private_channel') return '🔒';
    if (conversation.type === 'bot_dm') return '🤖';
    return '💬';
};
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="show"
                class="fixed inset-0 z-50 overflow-y-auto"
                @click="emit('close')"
            >
                <!-- Backdrop -->
                <div class="fixed inset-0 bg-black bg-opacity-50"></div>

                <!-- Modal -->
                <div class="flex min-h-screen items-start justify-center p-4 pt-24">
                    <Transition
                        enter-active-class="transition ease-out duration-200"
                        enter-from-class="opacity-0 scale-95"
                        enter-to-class="opacity-100 scale-100"
                        leave-active-class="transition ease-in duration-150"
                        leave-from-class="opacity-100 scale-100"
                        leave-to-class="opacity-0 scale-95"
                    >
                        <div
                            v-if="show"
                            class="relative w-full max-w-2xl bg-light-surface dark:bg-dark-surface rounded-lg shadow-2xl"
                            @click.stop
                        >
                            <!-- Search Input -->
                            <div class="p-4 border-b border-light-border dark:border-dark-border">
                                <div class="relative">
                                    <svg
                                        class="absolute left-3 top-1/2 transform -translate-y-1/2 h-5 w-5 text-light-text-muted dark:text-dark-text-muted"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                    <input
                                        ref="inputRef"
                                        v-model="searchQuery"
                                        type="text"
                                        placeholder="Jump to conversation..."
                                        class="w-full pl-10 pr-4 py-3 bg-light-bg dark:bg-dark-bg border border-light-border dark:border-dark-border rounded-lg text-light-text-primary dark:text-dark-text-primary placeholder-light-text-muted dark:placeholder-dark-text-muted focus:outline-none focus:ring-2 focus:ring-light-accent dark:focus:ring-dark-accent"
                                        @keydown="handleKeydown"
                                    />
                                </div>
                            </div>

                            <!-- Results -->
                            <div class="max-h-96 overflow-y-auto">
                                <div v-if="filteredConversations.length === 0" class="p-8 text-center text-light-text-muted dark:text-dark-text-muted">
                                    <svg class="mx-auto h-12 w-12 mb-3 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                    </svg>
                                    <p>No conversations found</p>
                                </div>

                                <button
                                    v-for="(conversation, index) in filteredConversations"
                                    :key="conversation.id"
                                    @click="selectConversation(conversation)"
                                    :class="[
                                        'w-full text-left px-4 py-3 flex items-center space-x-3 transition-colors',
                                        index === selectedIndex
                                            ? 'bg-light-accent/10 dark:bg-dark-accent/10 border-l-4 border-light-accent dark:border-dark-accent'
                                            : 'border-l-4 border-transparent hover:bg-light-bg dark:hover:bg-dark-bg'
                                    ]"
                                >
                                    <span class="text-lg">{{ getConversationIcon(conversation) }}</span>
                                    <div class="flex-1 min-w-0">
                                        <div class="text-sm font-medium text-light-text-primary dark:text-dark-text-primary truncate">
                                            {{ conversation.display_name || conversation.name }}
                                        </div>
                                        <div class="text-xs text-light-text-muted dark:text-dark-text-muted">
                                            {{ conversation.type === 'public_channel' ? 'Public channel' :
                                               conversation.type === 'private_channel' ? 'Private channel' :
                                               conversation.type === 'bot_dm' ? 'Bot conversation' :
                                               conversation.type === 'group_dm' ? 'Group message' : 'Direct message' }}
                                        </div>
                                    </div>
                                    <span
                                        v-if="conversation.unread_count > 0"
                                        class="px-2 py-1 text-xs font-semibold rounded-full bg-light-accent dark:bg-dark-accent text-white"
                                    >
                                        {{ conversation.unread_count > 99 ? '99+' : conversation.unread_count }}
                                    </span>
                                </button>
                            </div>

                            <!-- Footer -->
                            <div class="px-4 py-3 bg-light-bg dark:bg-dark-bg border-t border-light-border dark:border-dark-border rounded-b-lg">
                                <div class="flex items-center justify-between text-xs text-light-text-muted dark:text-dark-text-muted">
                                    <div class="flex items-center space-x-4">
                                        <span class="flex items-center">
                                            <kbd class="px-2 py-1 bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded mr-1">↑↓</kbd>
                                            Navigate
                                        </span>
                                        <span class="flex items-center">
                                            <kbd class="px-2 py-1 bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded mr-1">Enter</kbd>
                                            Select
                                        </span>
                                        <span class="flex items-center">
                                            <kbd class="px-2 py-1 bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded mr-1">Esc</kbd>
                                            Close
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </Transition>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
