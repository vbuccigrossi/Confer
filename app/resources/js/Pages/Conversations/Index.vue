<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { router, usePage, useForm } from '@inertiajs/vue3';
import ConversationLayout from '@/Layouts/ConversationLayout.vue';
import ConversationSidebar from '@/Components/ConversationSidebar.vue';
import MessageFeed from '@/Components/MessageFeed.vue';
import MessageComposer from '@/Components/MessageComposer.vue';
import ThreadPanel from '@/Components/ThreadPanel.vue';
import TypingIndicator from '@/Components/TypingIndicator.vue';
import QuickSwitcher from '@/Components/QuickSwitcher.vue';
import KeyboardShortcutsModal from '@/Components/KeyboardShortcutsModal.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { useKeyboardShortcuts } from '@/Composables/useKeyboardShortcuts';

const props = defineProps({
    conversations: Array,
    conversation: Object,
    messages: Array,
    availableBots: Array,
});

// Debug logging
console.log('=== Index.vue Props ===');
console.log('Conversations:', props.conversations);
console.log('Current conversation:', props.conversation);
console.log('Messages:', props.messages);

const page = usePage();
const currentWorkspace = computed(() => page.props.currentWorkspace);

console.log('Current workspace:', currentWorkspace.value);

// Local messages state (reactive)
const localMessages = ref([...props.messages || []]);

// Watch for prop changes and update local state
watch(() => props.messages, (newMessages) => {
    localMessages.value = [...newMessages || []];
}, { deep: true });

// Modals
const showWorkspaceModal = ref(false);
const showChannelModal = ref(false);
const showDmModal = ref(false);
const showQuickSwitcher = ref(false);
const showKeyboardShortcuts = ref(false);

// Keyboard shortcuts
useKeyboardShortcuts({
    // Quick switcher
    'ctrl+k': {
        description: 'Quick switcher',
        action: () => {
            showQuickSwitcher.value = true;
        },
        allowInInput: false,
    },
    // Help modal
    'ctrl+/': {
        description: 'Show keyboard shortcuts',
        action: () => {
            showKeyboardShortcuts.value = !showKeyboardShortcuts.value;
        },
        allowInInput: false,
    },
    '?': {
        description: 'Show keyboard shortcuts (alternative)',
        action: () => {
            showKeyboardShortcuts.value = !showKeyboardShortcuts.value;
        },
        allowInInput: false,
    },
    // Navigation
    'ctrl+shift+]': {
        description: 'Next conversation',
        action: () => {
            navigateConversation(1);
        },
        allowInInput: false,
    },
    'ctrl+shift+[': {
        description: 'Previous conversation',
        action: () => {
            navigateConversation(-1);
        },
        allowInInput: false,
    },
    'escape': {
        description: 'Close panel/modal',
        action: () => {
            if (showQuickSwitcher.value) {
                showQuickSwitcher.value = false;
            } else if (showKeyboardShortcuts.value) {
                showKeyboardShortcuts.value = false;
            } else if (activeThread.value) {
                closeThread();
            } else if (showChannelModal.value) {
                showChannelModal.value = false;
            } else if (showDmModal.value) {
                showDmModal.value = false;
            }
        },
        allowInInput: false,
    },
});

// Navigate between conversations
const navigateConversation = (direction) => {
    if (!props.conversations || props.conversations.length === 0) return;

    const currentIndex = props.conversations.findIndex(c => c.id === props.conversation?.id);
    let nextIndex;

    if (currentIndex === -1) {
        // No conversation selected, go to first
        nextIndex = 0;
    } else {
        nextIndex = currentIndex + direction;
        // Wrap around
        if (nextIndex < 0) nextIndex = props.conversations.length - 1;
        if (nextIndex >= props.conversations.length) nextIndex = 0;
    }

    const nextConversation = props.conversations[nextIndex];
    if (nextConversation) {
        router.visit(`/conversations/${nextConversation.id}`);
    }
};

// WebSocket channel reference
let conversationChannel = null;

// Subscribe to conversation channel
const subscribeToConversation = (conversationId) => {
    if (!conversationId || !window.Echo) return;
    
    // Unsubscribe from previous channel
    if (conversationChannel) {
        window.Echo.leave(conversationChannel);
    }
    
    const channelName = `private-conversation.${conversationId}`;
    conversationChannel = channelName;
    
    console.log('Subscribing to channel:', channelName);
    
    window.Echo.private(`conversation.${conversationId}`)
        .listen('.message.created', (event) => {
            console.log('New message received:', event);

            // Add new message to local messages if not already present
            if (!localMessages.value.find(m => m.id === event.message.id)) {
                localMessages.value.push(event.message);
            }
        })
        .listen('.message.updated', (event) => {
            console.log('Message updated:', event);

            // Update message in local state
            const index = localMessages.value.findIndex(m => m.id === event.message.id);
            if (index !== -1) {
                localMessages.value[index] = event.message;
            }
        })
        .listen('.message.deleted', (event) => {
            console.log('Message deleted:', event);

            // Remove message from local state
            localMessages.value = localMessages.value.filter(m => m.id !== event.messageId);
        })
        .listen('.reaction.added', (event) => {
            console.log('Reaction added:', event);

            // Find the message and add the reaction
            const message = localMessages.value.find(m => m.id === event.reaction.message_id);
            if (message) {
                if (!message.reactions) {
                    message.reactions = [];
                }
                // Add reaction if not already present
                if (!message.reactions.find(r => r.id === event.reaction.id)) {
                    message.reactions.push(event.reaction);
                }
            }
        })
        .listen('.reaction.removed', (event) => {
            console.log('Reaction removed:', event);

            // Find the message and remove the reaction
            const message = localMessages.value.find(m => m.id === event.messageId);
            if (message && message.reactions) {
                message.reactions = message.reactions.filter(r => r.id !== event.reactionId);
            }
        });
};

// Watch conversation changes
watch(() => props.conversation?.id, (newId) => {
    if (newId) {
        subscribeToConversation(newId);
    }
}, { immediate: true });

// Cleanup on unmount
onUnmounted(() => {
    if (conversationChannel && window.Echo) {
        window.Echo.leave(conversationChannel);
    }
});

// Check if we need to show workspace creation modal
onMounted(() => {
    if (!currentWorkspace.value) {
        showWorkspaceModal.value = true;
    }
});

// Workspace creation form
const workspaceForm = useForm({
    name: '',
    slug: '',
});

const createWorkspace = () => {
    workspaceForm.post(route('workspaces.store'), {
        preserveScroll: true,
        onSuccess: () => {
            workspaceForm.reset();
            showWorkspaceModal.value = false;
        },
    });
};

// Channel creation form
const channelForm = useForm({
    type: 'public_channel',
    name: '',
    topic: '',
    description: '',
});

const createChannel = () => {
    channelForm.post(route('web.conversations.store'), {
        preserveScroll: false, // Allow redirect to new channel
        onSuccess: () => {
            channelForm.reset();
            showChannelModal.value = false;
            // No reload needed - Inertia will handle the redirect
        },
    });
};

// DM creation
const dmForm = useForm({
    user_ids: [],
});

const currentUserId = computed(() => page.props.auth.user.id);

// Get workspace members directly from the currentWorkspace prop
const availableMembers = computed(() => {
    const members = currentWorkspace.value?.members || [];
    return members.filter(member => member.user_id !== currentUserId.value);
});

const loadAvailableUsers = () => {
    // No longer needed - we get members from props
    console.log('Current workspace members:', currentWorkspace.value?.members);
    console.log('Available members:', availableMembers.value);
};

const toggleUser = (userId) => {
    const index = dmForm.user_ids.indexOf(userId);
    if (index > -1) {
        dmForm.user_ids.splice(index, 1);
    } else {
        dmForm.user_ids.push(userId);
    }
};

const createDm = () => {
    dmForm.post(route('web.conversations.dm.store'), {
        preserveScroll: false,
        onSuccess: () => {
            dmForm.reset();
            showDmModal.value = false;
        },
    });
};

// Thread state
const activeThread = ref(null);
const threadReplies = ref([]);
const editingMessage = ref(null);

const openThread = async (message) => {
    activeThread.value = message;
    // Load thread replies
    try {
        const response = await window.axios.get(`/api/messages/${message.id}/replies`);
        threadReplies.value = response.data.replies || [];
    } catch (error) {
        console.error('Failed to load thread replies:', error);
        threadReplies.value = [];
    }
};

const closeThread = () => {
    activeThread.value = null;
    threadReplies.value = [];
};

const startEdit = (message) => {
    editingMessage.value = message;
    closeThread();
};

const cancelEdit = () => {
    editingMessage.value = null;
};

const deleteMessage = async (message) => {
    if (!confirm('Are you sure you want to delete this message?')) return;

    try {
        await window.axios.delete(`/api/messages/${message.id}`);
        // Remove from local messages (WebSocket will also handle this)
        localMessages.value = localMessages.value.filter(m => m.id !== message.id);
    } catch (error) {
        console.error('Message delete failed:', error);
        alert('Failed to delete message');
    }
};

const loadMore = () => {
    console.log('Load more messages');
};

// Expose methods to sidebar
const openChannelModal = () => {
    showChannelModal.value = true;
};

const openDmModal = () => {
    showDmModal.value = true;
    loadAvailableUsers();
};

const openBotDmModal = () => {
    router.visit(route('web.conversations.bot-dm.start'));
};
</script>
<template>
    <ConversationLayout :title="conversation?.name || currentWorkspace?.name || 'Confer'">
        <template #sidebar>
            <ConversationSidebar
                :conversations="conversations || []"
                :current-conversation-id="conversation?.id"
                :available-bots="availableBots || []"
                @create-channel="openChannelModal"
                @create-dm="openDmModal"
                @create-bot-dm="openBotDmModal"
            />
        </template>

        <template #header>
            <div v-if="conversation" class="flex items-center">
                <span v-if="conversation.type === 'private_channel'" class="mr-2">
                    <svg class="h-5 w-5 text-light-text-primary dark:text-dark-text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </span>
                <h1 class="text-xl font-bold text-light-text-primary dark:text-dark-text-primary">
                    {{ conversation.name || conversation.display_name }}
                </h1>
                <span v-if="conversation.topic" class="ml-4 text-sm text-light-text-secondary dark:text-dark-text-secondary">
                    {{ conversation.topic }}
                </span>
            </div>
            <div v-else class="flex items-center">
                <h1 class="text-xl font-bold text-light-text-primary dark:text-dark-text-primary">
                    {{ currentWorkspace?.name || 'Confer' }}
                </h1>
            </div>
        </template>

        <!-- Main conversation area -->
        <div class="flex h-full">
            <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
                <div v-if="!conversation" class="flex-1 flex items-center justify-center bg-light-surface dark:bg-dark-surface">
                    <div class="text-center max-w-md">
                        <svg class="mx-auto h-16 w-16 text-light-text-muted dark:text-dark-text-muted mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                        </svg>
                        <h3 class="text-lg font-medium text-light-text-primary dark:text-dark-text-primary mb-2">
                            Welcome to {{ currentWorkspace?.name || 'Confer' }}
                        </h3>
                        <p class="text-light-text-secondary dark:text-dark-text-secondary mb-6">
                            Select a channel from the sidebar or create a new one to get started.
                        </p>
                    </div>
                </div>

                <div v-else class="flex-1 flex flex-col bg-light-surface dark:bg-dark-surface overflow-hidden">
                    <!-- Message Feed (using local messages) -->
                    <MessageFeed
                        :conversation-id="conversation.id"
                        :messages="localMessages"
                        @load-more="loadMore"
                        @reply-thread="openThread"
                        @edit-message="startEdit"
                        @delete-message="deleteMessage"
                    />

                    <!-- Typing Indicator -->
                    <TypingIndicator :conversation-id="conversation.id" />

                    <!-- Message Composer -->
                    <MessageComposer
                        :conversation-id="conversation.id"
                        :editing-message="editingMessage"
                        @sent="cancelEdit"
                        @cancel="cancelEdit"
                    />
                </div>
            </div>

            <!-- Thread Panel -->
            <ThreadPanel
                v-if="activeThread"
                :parent-message="activeThread"
                :thread-replies="threadReplies"
                :conversation-id="conversation?.id"
                @close="closeThread"
            />
        </div>
    </ConversationLayout>

    <!-- Workspace Creation Modal -->
    <Modal :show="showWorkspaceModal" @close="() => {}" :closeable="false">
        <div class="p-6">
            <h2 class="text-lg font-medium text-light-text-primary dark:text-dark-text-primary mb-4">
                Create Your Workspace
            </h2>
            <p class="text-sm text-light-text-secondary dark:text-dark-text-secondary mb-6">
                Workspaces are shared environments where you and your team can work together.
            </p>

            <form @submit.prevent="createWorkspace">
                <div class="mb-4">
                    <InputLabel for="workspace_name" value="Workspace Name" />
                    <TextInput
                        id="workspace_name"
                        v-model="workspaceForm.name"
                        type="text"
                        class="mt-1 block w-full"
                        required
                        autofocus
                        placeholder="e.g. Acme Inc"
                    />
                    <InputError :message="workspaceForm.errors.name" class="mt-2" />
                </div>

                <div class="mb-4">
                    <InputLabel for="workspace_slug" value="Workspace URL (optional)" />
                    <TextInput
                        id="workspace_slug"
                        v-model="workspaceForm.slug"
                        type="text"
                        class="mt-1 block w-full"
                        placeholder="acme-inc"
                    />
                    <InputError :message="workspaceForm.errors.slug" class="mt-2" />
                    <p class="mt-1 text-sm text-light-text-secondary dark:text-dark-text-secondary">
                        Leave blank to auto-generate from name
                    </p>
                </div>

                <div class="flex justify-end">
                    <PrimaryButton :class="{ 'opacity-25': workspaceForm.processing }" :disabled="workspaceForm.processing">
                        Create Workspace
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </Modal>

    <!-- Channel Creation Modal -->
    <Modal :show="showChannelModal" @close="showChannelModal = false">
        <div class="p-6">
            <h2 class="text-lg font-medium text-light-text-primary dark:text-dark-text-primary mb-4">
                Create a Channel
            </h2>

            <form @submit.prevent="createChannel">
                <div class="mb-4">
                    <InputLabel value="Channel Type" />
                    <div class="mt-2 space-y-2">
                        <label class="flex items-center cursor-pointer">
                            <input
                                v-model="channelForm.type"
                                type="radio"
                                value="public_channel"
                                class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-light-border dark:border-dark-border"
                            />
                            <span class="ml-2 text-sm text-light-text-primary dark:text-dark-text-primary"># Public - Anyone can join</span>
                        </label>
                        <label class="flex items-center cursor-pointer">
                            <input
                                v-model="channelForm.type"
                                type="radio"
                                value="private_channel"
                                class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-light-border dark:border-dark-border"
                            />
                            <span class="ml-2 text-sm text-light-text-primary dark:text-dark-text-primary">🔒 Private - Invite only</span>
                        </label>
                    </div>
                </div>

                <div class="mb-4">
                    <InputLabel for="channel_name" value="Channel Name" />
                    <div class="mt-1 flex rounded-md shadow-sm">
                        <span class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-light-border dark:border-dark-border bg-light-bg dark:bg-dark-bg text-light-text-muted dark:text-dark-text-muted text-sm">
                            #
                        </span>
                        <TextInput
                            id="channel_name"
                            v-model="channelForm.name"
                            type="text"
                            class="flex-1 rounded-none rounded-r-md"
                            required
                            placeholder="e.g. general"
                        />
                    </div>
                    <InputError :message="channelForm.errors.name" class="mt-2" />
                </div>

                <div class="mb-4">
                    <InputLabel for="topic" value="Topic (optional)" />
                    <TextInput
                        id="topic"
                        v-model="channelForm.topic"
                        type="text"
                        class="mt-1 block w-full"
                        placeholder="What's this channel about?"
                    />
                </div>

                <div class="flex justify-end space-x-3">
                    <button
                        type="button"
                        @click="showChannelModal = false"
                        class="px-4 py-2 bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded-md text-sm font-medium text-light-text-secondary dark:text-dark-text-secondary hover:bg-light-bg dark:hover:bg-dark-bg"
                    >
                        Cancel
                    </button>
                    <PrimaryButton :class="{ 'opacity-25': channelForm.processing }" :disabled="channelForm.processing">
                        Create
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </Modal>

    <!-- DM Creation Modal -->
    <Modal :show="showDmModal" @close="showDmModal = false">
        <div class="p-6">
            <h2 class="text-lg font-medium text-light-text-primary dark:text-dark-text-primary mb-4">
                Start a Direct Message
            </h2>

            <form @submit.prevent="createDm">
                <div class="mb-4">
                    <InputLabel value="Select People" />

                    <!-- Debug info -->
                    <div class="mt-2 text-xs text-light-text-muted dark:text-dark-text-muted">
                        Total workspace members: {{ currentWorkspace?.members?.length || 0 }} |
                        Available to DM: {{ availableMembers.length }} |
                        Current user ID: {{ currentUserId }}
                    </div>

                    <div class="mt-3 space-y-2 max-h-96 overflow-y-auto">
                        <div v-if="availableMembers.length === 0" class="text-center py-8 text-light-text-muted dark:text-dark-text-muted">
                            No other users available in this workspace.
                        </div>
                        <label
                            v-for="member in availableMembers"
                            :key="member.user_id"
                            class="flex items-center p-3 rounded-md border border-light-border dark:border-dark-border hover:bg-light-bg dark:hover:bg-dark-bg cursor-pointer"
                        >
                            <input
                                type="checkbox"
                                :value="member.user_id"
                                :checked="dmForm.user_ids.includes(member.user_id)"
                                @change="toggleUser(member.user_id)"
                                class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-light-border dark:border-dark-border rounded"
                            />
                            <div class="ml-3 flex items-center">
                                <div class="h-8 w-8 rounded-full bg-indigo-600 flex items-center justify-center text-white font-semibold mr-3">
                                    {{ member.user.name.charAt(0).toUpperCase() }}
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-light-text-primary dark:text-dark-text-primary">
                                        {{ member.user.name }}
                                    </div>
                                    <div class="text-xs text-light-text-secondary dark:text-dark-text-secondary">
                                        {{ member.user.email }}
                                    </div>
                                </div>
                            </div>
                        </label>
                    </div>
                    <InputError :message="dmForm.errors.user_ids" class="mt-2" />
                    <p class="mt-2 text-sm text-light-text-secondary dark:text-dark-text-secondary">
                        Select one person for a direct message, or multiple people for a group DM.
                    </p>
                </div>

                <div class="flex justify-end space-x-3">
                    <button
                        type="button"
                        @click="showDmModal = false"
                        class="px-4 py-2 bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded-md text-sm font-medium text-light-text-secondary dark:text-dark-text-secondary hover:bg-light-bg dark:hover:bg-dark-bg"
                    >
                        Cancel
                    </button>
                    <PrimaryButton
                        :class="{ 'opacity-25': dmForm.processing || dmForm.user_ids.length === 0 }"
                        :disabled="dmForm.processing || dmForm.user_ids.length === 0"
                    >
                        Start Conversation
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </Modal>

    <!-- Quick Switcher -->
    <QuickSwitcher
        :show="showQuickSwitcher"
        :conversations="conversations"
        @close="showQuickSwitcher = false"
    />

    <!-- Keyboard Shortcuts Help -->
    <KeyboardShortcutsModal
        :show="showKeyboardShortcuts"
        @close="showKeyboardShortcuts = false"
    />
</template>
