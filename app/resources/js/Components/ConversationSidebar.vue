<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { usePresence } from '@/Composables/usePresence';
import ChannelIcon from '@/Components/ChannelIcon.vue';

const props = defineProps({
    conversations: Array,
    currentConversationId: Number,
    availableBots: Array,
});

const emit = defineEmits(['create-channel', 'create-dm', 'create-bot-dm']);

// Setup presence heartbeat
usePresence();

const selfConversation = computed(() => props.conversations?.find(c => c.type === 'self') || null);
const publicChannels = computed(() => props.conversations?.filter(c => c.type === 'public_channel') || []);
const privateChannels = computed(() => props.conversations?.filter(c => c.type === 'private_channel') || []);
const dms = computed(() => props.conversations?.filter(c => c.type === 'dm') || []);
const groupDms = computed(() => props.conversations?.filter(c => c.type === 'group_dm') || []);
const botDms = computed(() => props.conversations?.filter(c => c.type === 'bot_dm') || []);

// Collapse state
const channelsCollapsed = ref(false);
const dmsCollapsed = ref(false);
const botsCollapsed = ref(false);

// Dropdown menu state
const openMenuId = ref(null);

const toggleMenu = (conversationId, event) => {
    event.stopPropagation(); // Prevent opening the conversation
    openMenuId.value = openMenuId.value === conversationId ? null : conversationId;
};

const closeMenu = () => {
    openMenuId.value = null;
};

const renameChannel = (conversation, event) => {
    event.stopPropagation();
    closeMenu();
    const newName = prompt('Enter new channel name:', conversation.name);
    if (newName && newName !== conversation.name) {
        router.put(route('web.conversations.update', conversation.id), {
            name: newName,
        }, {
            preserveScroll: true,
        });
    }
};

const inviteMembers = (conversation, event) => {
    event.stopPropagation();
    closeMenu();
    router.visit(route('web.conversations.members.add', conversation.id));
};

const deleteChannel = (conversation, event) => {
    event.stopPropagation();
    closeMenu();
    const displayName = conversation.type === 'dm' || conversation.type === 'group_dm'
        ? conversation.display_name
        : `#${conversation.name}`;
    if (confirm(`Are you sure you want to delete ${displayName}?`)) {
        router.delete(route('web.conversations.destroy', conversation.id), {
            onSuccess: () => {
                router.visit(route('web.conversations.index'));
            },
        });
    }
};

// Helper to check if DM user is online
const isDmOnline = (dm) => {
    if (!dm.members || dm.members.length === 0) {
        return false;
    }
    // Get other members (not current user)
    const otherMembers = dm.members.filter(m => m.user);
    if (otherMembers.length === 0) {
        return false;
    }
    // Return true if any member is online
    return otherMembers.some(m => m.user.is_online === true);
};

const openConversation = (conversation) => {
    closeMenu();
    // Clear unread count immediately for better UX
    if (conversation.unread_count > 0) {
        conversation.unread_count = 0;
    }
    router.visit(`/conversations/${conversation.id}`);
};

const openNotesToSelf = async () => {
    try {
        // If we already have a self conversation, just navigate to it
        if (selfConversation.value) {
            openConversation(selfConversation.value);
            return;
        }
        // Otherwise, the backend will create one via the API endpoint
        router.visit(route('web.conversations.self'));
    } catch (error) {
        console.error('Failed to open Notes to Self:', error);
    }
};
</script>

<template>
    <div class="py-4">
        <!-- Notes to Self - Always at top -->
        <div class="px-4 mb-4">
            <button
                @click="openNotesToSelf"
                :class="[
                    'w-full text-left px-3 py-2 rounded-lg text-sm flex items-center transition-all',
                    selfConversation && currentConversationId === selfConversation.id
                        ? 'bg-light-accent dark:bg-dark-accent/20 text-light-surface dark:text-dark-accent border border-light-accent dark:border-dark-accent shadow-neon'
                        : 'bg-light-bg dark:bg-dark-bg text-light-text-primary dark:text-dark-text-primary hover:bg-light-border dark:hover:bg-dark-border'
                ]"
            >
                <svg class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <div class="flex-1 min-w-0">
                    <div class="font-medium">Notes to Self</div>
                    <div v-if="selfConversation?.last_message" class="text-xs text-light-text-muted dark:text-dark-text-muted truncate">
                        {{ selfConversation.last_message.body_md?.substring(0, 40) }}...
                    </div>
                    <div v-else class="text-xs text-light-text-muted dark:text-dark-text-muted">
                        Your private notepad
                    </div>
                </div>
                <span
                    v-if="selfConversation?.unread_count > 0"
                    class="ml-2 px-2 py-0.5 text-xs font-semibold rounded-full bg-light-accent dark:bg-dark-accent text-white"
                >
                    {{ selfConversation.unread_count }}
                </span>
            </button>
        </div>

        <!-- Channels Section -->
        <div class="px-4 mb-4">
            <div class="flex items-center justify-between mb-2">
                <button
                    @click="channelsCollapsed = !channelsCollapsed"
                    class="flex items-center text-light-text-secondary dark:text-dark-text-secondary hover:text-light-text-primary dark:hover:text-dark-accent text-sm font-semibold transition-colors"
                >
                    <svg
                        class="h-4 w-4 mr-1 transition-transform"
                        :class="{ '-rotate-90': channelsCollapsed }"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                    Channels
                </button>
                <button
                    @click="emit('create-channel')"
                    class="text-light-text-muted dark:text-dark-text-muted hover:text-light-accent dark:hover:text-dark-accent transition-colors"
                    title="Create channel"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                </button>
            </div>

            <!-- Channels List -->
            <div v-if="!channelsCollapsed">
                <!-- Public Channels -->
            <div class="space-y-0.5">
                <div
                    v-for="channel in publicChannels"
                    :key="channel.id"
                    class="relative group"
                >
                    <button
                        @click="openConversation(channel)"
                        :class="[
                            'w-full text-left px-2 py-1 rounded text-sm flex items-center justify-between transition-all',
                            currentConversationId === channel.id
                                ? 'bg-light-accent dark:bg-dark-accent/20 text-light-surface dark:text-dark-accent border border-light-accent dark:border-dark-accent shadow-neon'
                                : 'text-light-text-secondary dark:text-dark-text-secondary hover:bg-light-bg dark:hover:bg-dark-bg hover:text-light-text-primary dark:hover:text-dark-text-primary'
                        ]"
                    >
                        <div class="flex items-center flex-1 min-w-0">
                            <div class="w-4 h-4 mr-2 flex-shrink-0">
                                <ChannelIcon />
                            </div>
                            <span class="truncate">{{ channel.name }}</span>
                            <!-- Unread badge -->
                            <span
                                v-if="channel.unread_count > 0"
                                class="ml-2 px-2 py-0.5 text-xs font-semibold rounded-full bg-light-accent dark:bg-dark-accent text-white"
                            >
                                {{ channel.unread_count > 99 ? '99+' : channel.unread_count }}
                            </span>
                        </div>
                        <button
                            @click="toggleMenu(channel.id, $event)"
                            class="opacity-0 group-hover:opacity-100 p-1 hover:bg-light-border dark:hover:bg-dark-border rounded transition-opacity"
                            title="Channel options"
                        >
                            <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 16 16">
                                <circle cx="8" cy="2" r="1.5"/>
                                <circle cx="8" cy="8" r="1.5"/>
                                <circle cx="8" cy="14" r="1.5"/>
                            </svg>
                        </button>
                    </button>

                    <!-- Dropdown menu -->
                    <div
                        v-if="openMenuId === channel.id"
                        class="absolute right-0 mt-1 w-48 bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded-md shadow-lg z-10 py-1"
                    >
                        <button
                            @click="inviteMembers(channel, $event)"
                            class="w-full text-left px-4 py-2 text-sm text-light-text-secondary dark:text-dark-text-secondary hover:bg-light-bg dark:hover:bg-dark-bg transition-colors"
                        >
                            Invite members
                        </button>
                        <button
                            @click="renameChannel(channel, $event)"
                            class="w-full text-left px-4 py-2 text-sm text-light-text-secondary dark:text-dark-text-secondary hover:bg-light-bg dark:hover:bg-dark-bg transition-colors"
                        >
                            Rename channel
                        </button>
                        <button
                            @click="deleteChannel(channel, $event)"
                            class="w-full text-left px-4 py-2 text-sm text-light-danger dark:text-dark-danger hover:bg-light-bg dark:hover:bg-dark-bg transition-colors"
                        >
                            Delete channel
                        </button>
                    </div>
                </div>

                <!-- Private Channels -->
                <div
                    v-for="channel in privateChannels"
                    :key="channel.id"
                    class="relative group"
                >
                    <button
                        @click="openConversation(channel)"
                        :class="[
                            'w-full text-left px-2 py-1 rounded text-sm flex items-center justify-between transition-all',
                            currentConversationId === channel.id
                                ? 'bg-light-accent dark:bg-dark-accent/20 text-light-surface dark:text-dark-accent border border-light-accent dark:border-dark-accent shadow-neon'
                                : 'text-light-text-secondary dark:text-dark-text-secondary hover:bg-light-bg dark:hover:bg-dark-bg hover:text-light-text-primary dark:hover:text-dark-text-primary'
                        ]"
                    >
                        <div class="flex items-center flex-1 min-w-0">
                            <svg class="h-3 w-3 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                            <span class="truncate">{{ channel.name }}</span>
                            <!-- Unread badge -->
                            <span
                                v-if="channel.unread_count > 0"
                                class="ml-2 px-2 py-0.5 text-xs font-semibold rounded-full bg-light-accent dark:bg-dark-accent text-white"
                            >
                                {{ channel.unread_count > 99 ? '99+' : channel.unread_count }}
                            </span>
                        </div>
                        <button
                            @click="toggleMenu(channel.id, $event)"
                            class="opacity-0 group-hover:opacity-100 p-1 hover:bg-light-border dark:hover:bg-dark-border rounded transition-opacity"
                            title="Channel options"
                        >
                            <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 16 16">
                                <circle cx="8" cy="2" r="1.5"/>
                                <circle cx="8" cy="8" r="1.5"/>
                                <circle cx="8" cy="14" r="1.5"/>
                            </svg>
                        </button>
                    </button>

                    <!-- Dropdown menu -->
                    <div
                        v-if="openMenuId === channel.id"
                        class="absolute right-0 mt-1 w-48 bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded-md shadow-lg z-10 py-1"
                    >
                        <button
                            @click="inviteMembers(channel, $event)"
                            class="w-full text-left px-4 py-2 text-sm text-light-text-secondary dark:text-dark-text-secondary hover:bg-light-bg dark:hover:bg-dark-bg transition-colors"
                        >
                            Invite members
                        </button>
                        <button
                            @click="renameChannel(channel, $event)"
                            class="w-full text-left px-4 py-2 text-sm text-light-text-secondary dark:text-dark-text-secondary hover:bg-light-bg dark:hover:bg-dark-bg transition-colors"
                        >
                            Rename channel
                        </button>
                        <button
                            @click="deleteChannel(channel, $event)"
                            class="w-full text-left px-4 py-2 text-sm text-light-danger dark:text-dark-danger hover:bg-light-bg dark:hover:bg-dark-bg transition-colors"
                        >
                            Delete channel
                        </button>
                    </div>
                </div>
            </div>

                <div v-if="publicChannels.length === 0 && privateChannels.length === 0" class="px-2 py-2 text-sm text-light-text-muted dark:text-dark-text-muted">
                    No channels yet
                </div>
            </div>
        </div>

        <!-- Direct Messages Section -->
        <div class="px-4">
            <div class="flex items-center justify-between mb-2">
                <button
                    @click="dmsCollapsed = !dmsCollapsed"
                    class="flex items-center text-light-text-secondary dark:text-dark-text-secondary hover:text-light-text-primary dark:hover:text-dark-accent text-sm font-semibold transition-colors"
                >
                    <svg
                        class="h-4 w-4 mr-1 transition-transform"
                        :class="{ '-rotate-90': dmsCollapsed }"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                    Direct Messages
                </button>
                <button
                    @click="emit('create-dm')"
                    class="text-light-text-muted dark:text-dark-text-muted hover:text-light-accent dark:hover:text-dark-accent transition-colors"
                    title="Start a DM"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                </button>
            </div>

            <!-- DMs List -->
            <div v-if="!dmsCollapsed">
                <!-- DMs and Group DMs -->
                <div class="space-y-0.5">
                <div
                    v-for="dm in [...dms, ...groupDms]"
                    :key="dm.id"
                    class="relative group"
                >
                    <button
                        @click="openConversation(dm)"
                        :class="[
                            'w-full text-left px-2 py-1 rounded text-sm flex items-center justify-between transition-all',
                            currentConversationId === dm.id
                                ? 'bg-light-accent dark:bg-dark-accent/20 text-light-surface dark:text-dark-accent border border-light-accent dark:border-dark-accent shadow-neon'
                                : 'text-light-text-secondary dark:text-dark-text-secondary hover:bg-light-bg dark:hover:bg-dark-bg hover:text-light-text-primary dark:hover:text-dark-text-primary'
                        ]"
                    >
                        <div class="flex items-center flex-1 min-w-0">
                            <div :class="[
                                'h-2 w-2 rounded-full mr-2 transition-colors',
                                isDmOnline(dm) ? 'bg-green-400 shadow-[0_0_8px_rgba(74,222,128,0.6)]' : 'bg-light-text-muted dark:bg-dark-text-muted'
                            ]"></div>
                            <span class="truncate">{{ dm.display_name || dm.name }}</span>
                            <!-- Unread badge -->
                            <span
                                v-if="dm.unread_count > 0"
                                class="ml-2 px-2 py-0.5 text-xs font-semibold rounded-full bg-light-accent dark:bg-dark-accent text-white"
                            >
                                {{ dm.unread_count > 99 ? '99+' : dm.unread_count }}
                            </span>
                        </div>
                        <button
                            @click="toggleMenu(dm.id, $event)"
                            class="opacity-0 group-hover:opacity-100 p-1 hover:bg-light-border dark:hover:bg-dark-border rounded transition-opacity"
                            title="Conversation options"
                        >
                            <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 16 16">
                                <circle cx="8" cy="2" r="1.5"/>
                                <circle cx="8" cy="8" r="1.5"/>
                                <circle cx="8" cy="14" r="1.5"/>
                            </svg>
                        </button>
                    </button>

                    <!-- Dropdown menu (delete only for DMs) -->
                    <div
                        v-if="openMenuId === dm.id"
                        class="absolute right-0 mt-1 w-48 bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded-md shadow-lg z-10 py-1"
                    >
                        <button
                            @click="deleteChannel(dm, $event)"
                            class="w-full text-left px-4 py-2 text-sm text-light-danger dark:text-dark-danger hover:bg-light-bg dark:hover:bg-dark-bg transition-colors"
                        >
                            Delete conversation
                        </button>
                    </div>
                </div>
                </div>

                <div v-if="dms.length === 0 && groupDms.length === 0" class="px-2 py-2 text-sm text-light-text-muted dark:text-dark-text-muted">
                    No direct messages yet
                </div>
            </div>
        </div>

        <!-- Bots Section -->
        <div class="px-4 mt-4">
            <div class="flex items-center justify-between mb-2">
                <button
                    @click="botsCollapsed = !botsCollapsed"
                    class="flex items-center text-light-text-secondary dark:text-dark-text-secondary hover:text-light-text-primary dark:hover:text-dark-accent text-sm font-semibold transition-colors"
                >
                    <svg
                        class="h-4 w-4 mr-1 transition-transform"
                        :class="{ '-rotate-90': botsCollapsed }"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                    Bots
                </button>
                <button
                    @click="emit('create-bot-dm')"
                    class="text-light-text-muted dark:text-dark-text-muted hover:text-light-accent dark:hover:text-dark-accent transition-colors"
                    title="Start conversation with bot"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                </button>
            </div>

            <!-- Bots List -->
            <div v-if="!botsCollapsed">
                <div class="space-y-0.5">
                    <div
                        v-for="botConv in botDms"
                        :key="botConv.id"
                        class="relative group"
                    >
                        <button
                            @click="openConversation(botConv)"
                            :class="[
                                'w-full text-left px-2 py-1 rounded text-sm flex items-center justify-between transition-all',
                                currentConversationId === botConv.id
                                    ? 'bg-light-accent dark:bg-dark-accent/20 text-light-surface dark:text-dark-accent border border-light-accent dark:border-dark-accent shadow-neon'
                                    : 'text-light-text-secondary dark:text-dark-text-secondary hover:bg-light-bg dark:hover:bg-dark-bg hover:text-light-text-primary dark:hover:text-dark-text-primary'
                            ]"
                        >
                            <div class="flex items-center flex-1 min-w-0">
                                <!-- Bot icon -->
                                <svg class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                                <span class="truncate">{{ botConv.display_name || botConv.name }}</span>
                                <!-- Unread badge -->
                                <span
                                    v-if="botConv.unread_count > 0"
                                    class="ml-2 px-2 py-0.5 text-xs font-semibold rounded-full bg-light-accent dark:bg-dark-accent text-white"
                                >
                                    {{ botConv.unread_count > 99 ? '99+' : botConv.unread_count }}
                                </span>
                            </div>
                            <button
                                @click="toggleMenu(botConv.id, $event)"
                                class="opacity-0 group-hover:opacity-100 p-1 hover:bg-light-border dark:hover:bg-dark-border rounded transition-opacity"
                                title="Bot options"
                            >
                                <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 16 16">
                                    <circle cx="8" cy="2" r="1.5"/>
                                    <circle cx="8" cy="8" r="1.5"/>
                                    <circle cx="8" cy="14" r="1.5"/>
                                </svg>
                            </button>
                        </button>

                        <!-- Dropdown menu (delete only for bot DMs) -->
                        <div
                            v-if="openMenuId === botConv.id"
                            class="absolute right-0 mt-1 w-48 bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded-md shadow-lg z-10 py-1"
                        >
                            <button
                                @click="deleteChannel(botConv, $event)"
                                class="w-full text-left px-4 py-2 text-sm text-light-danger dark:text-dark-danger hover:bg-light-bg dark:hover:bg-dark-bg transition-colors"
                            >
                                Delete conversation
                            </button>
                        </div>
                    </div>
                </div>

                <div v-if="botDms.length === 0" class="px-2 py-2 text-sm text-light-text-muted dark:text-dark-text-muted">
                    No bot conversations yet
                </div>
            </div>
        </div>
    </div>
</template>
