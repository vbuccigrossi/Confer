<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { useForm, router, usePage } from '@inertiajs/vue3';
import FileUploadButton from './FileUploadButton.vue';

const props = defineProps({
    conversationId: Number,
    parentMessageId: Number,
    editingMessage: Object,
    placeholder: {
        type: String,
        default: 'Type a message...',
    },
});

const emit = defineEmits(['sent', 'cancel']);
const page = usePage();

const form = useForm({
    body_md: '',
    parent_message_id: props.parentMessageId || null,
});

const textareaRef = ref(null);

// Mention autocomplete state
const showMentionDropdown = ref(false);
const mentionSearch = ref('');
const mentionStartPos = ref(0);
const selectedMentionIndex = ref(0);
const conversationMembers = ref([]);
const loadingMembers = ref(false);

// Fetch conversation members when mention search changes
watch(mentionSearch, async (newSearch) => {
    if (!showMentionDropdown.value || !props.conversationId) return;

    loadingMembers.value = true;
    try {
        const response = await window.axios.get(
            `/api/conversations/${props.conversationId}/members/search`,
            { params: { q: newSearch } }
        );
        conversationMembers.value = response.data;
    } catch (error) {
        console.error('Failed to fetch members:', error);
    } finally {
        loadingMembers.value = false;
    }
});

const filteredMembers = computed(() => {
    return conversationMembers.value;
});

watch(() => props.editingMessage, (message) => {
    if (message) {
        form.body_md = message.body_md;
        textareaRef.value?.focus();
    }
}, { immediate: true });

// Typing indicator
const isTyping = ref(false);
let typingTimeout = null;

const sendTypingIndicator = async () => {
    if (!props.conversationId) return;

    try {
        await window.axios.post(`/api/conversations/${props.conversationId}/typing`);
        isTyping.value = true;

        // Clear previous timeout
        if (typingTimeout) {
            clearTimeout(typingTimeout);
        }

        // Stop typing after 3 seconds of inactivity
        typingTimeout = setTimeout(() => {
            isTyping.value = false;
        }, 3000);
    } catch (error) {
        console.error('Failed to send typing indicator:', error);
    }
};

const handleInput = (e) => {
    const textarea = e.target;
    const text = textarea.value;
    const cursorPos = textarea.selectionStart;

    // Send typing indicator
    if (text.trim().length > 0 && !isTyping.value) {
        sendTypingIndicator();
    }

    // Find the last @ before cursor
    const textBeforeCursor = text.substring(0, cursorPos);
    const lastAtIndex = textBeforeCursor.lastIndexOf('@');

    if (lastAtIndex !== -1) {
        // Check if there's a space or newline between @ and cursor
        const textAfterAt = textBeforeCursor.substring(lastAtIndex + 1);

        if (!/\s/.test(textAfterAt)) {
            // Show mention dropdown
            showMentionDropdown.value = true;
            mentionSearch.value = textAfterAt;
            mentionStartPos.value = lastAtIndex;
            selectedMentionIndex.value = 0;
            return;
        }
    }

    // Hide dropdown if no active mention
    showMentionDropdown.value = false;
};

const selectMention = (member) => {
    const textarea = textareaRef.value;
    const text = form.body_md;
    const cursorPos = textarea.selectionStart;

    // Replace from @ to cursor with @username
    const before = text.substring(0, mentionStartPos.value);
    const after = text.substring(cursorPos);

    form.body_md = before + '@' + member.name + ' ' + after;

    // Hide dropdown
    showMentionDropdown.value = false;

    // Set cursor after the mention
    const newCursorPos = before.length + member.name.length + 2;
    setTimeout(() => {
        textarea.focus();
        textarea.setSelectionRange(newCursorPos, newCursorPos);
    }, 0);
};

const submit = async () => {
    if (!form.body_md.trim()) return;

    if (props.editingMessage) {
        // Update existing message using axios (API returns JSON)
        form.processing = true;
        try {
            await window.axios.patch(`/api/messages/${props.editingMessage.id}`, {
                body_md: form.body_md
            });
            form.reset();
            emit('sent');
            // Reload the current page to show updated message
            router.reload({ only: ['messages'] });
        } catch (error) {
            console.error('Message update failed:', error);
            alert('Failed to update message');
        } finally {
            form.processing = false;
        }
    } else {
        // Create new message using Inertia form (web route)
        form.post(route('web.conversations.messages.store', props.conversationId), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                emit('sent');
            },
            onError: (errors) => {
                console.error('Message send failed:', errors);
            },
        });
    }
};

const handleKeydown = (e) => {
    const ctrl = e.ctrlKey || e.metaKey;
    const shift = e.shiftKey;

    // Handle formatting shortcuts
    if (ctrl && !shift && e.key.toLowerCase() === 'b') {
        e.preventDefault();
        wrapSelection('**', '**');
        return;
    }
    if (ctrl && !shift && e.key.toLowerCase() === 'i') {
        e.preventDefault();
        wrapSelection('*', '*');
        return;
    }
    if (ctrl && shift && e.key.toLowerCase() === 'x') {
        e.preventDefault();
        wrapSelection('~~', '~~');
        return;
    }
    if (ctrl && shift && e.key.toLowerCase() === 'c') {
        e.preventDefault();
        wrapSelection('`', '`');
        return;
    }
    if (ctrl && shift && e.key === '7') {
        e.preventDefault();
        insertListPrefix('1. ');
        return;
    }
    if (ctrl && shift && e.key === '8') {
        e.preventDefault();
        insertListPrefix('- ');
        return;
    }
    if (ctrl && shift && e.key === '9') {
        e.preventDefault();
        wrapSelection('```\n', '\n```');
        return;
    }
    if (ctrl && shift && e.key === '>') {
        e.preventDefault();
        insertListPrefix('> ');
        return;
    }

    // Handle mention dropdown navigation
    if (showMentionDropdown.value) {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            selectedMentionIndex.value = Math.min(
                selectedMentionIndex.value + 1,
                filteredMembers.value.length - 1
            );
            return;
        }

        if (e.key === 'ArrowUp') {
            e.preventDefault();
            selectedMentionIndex.value = Math.max(selectedMentionIndex.value - 1, 0);
            return;
        }

        if (e.key === 'Enter' || e.key === 'Tab') {
            e.preventDefault();
            if (filteredMembers.value[selectedMentionIndex.value]) {
                selectMention(filteredMembers.value[selectedMentionIndex.value]);
            }
            return;
        }

        if (e.key === 'Escape') {
            e.preventDefault();
            showMentionDropdown.value = false;
            return;
        }
    }

    // Submit on Enter (without Shift)
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        submit();
    }
};

// Wrap selected text with formatting characters
const wrapSelection = (before, after) => {
    const textarea = textareaRef.value;
    if (!textarea) return;

    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selectedText = form.body_md.substring(start, end);
    const newText = form.body_md.substring(0, start) + before + selectedText + after + form.body_md.substring(end);

    form.body_md = newText;

    // Set cursor position after formatting
    nextTick(() => {
        textarea.focus();
        const newCursorPos = start + before.length + selectedText.length + after.length;
        textarea.setSelectionRange(newCursorPos, newCursorPos);
    });
};

// Insert list prefix at current line
const insertListPrefix = (prefix) => {
    const textarea = textareaRef.value;
    if (!textarea) return;

    const start = textarea.selectionStart;
    const text = form.body_md;

    // Find the start of the current line
    let lineStart = start;
    while (lineStart > 0 && text[lineStart - 1] !== '\n') {
        lineStart--;
    }

    // Insert prefix at line start
    const newText = text.substring(0, lineStart) + prefix + text.substring(lineStart);
    form.body_md = newText;

    // Move cursor after prefix
    nextTick(() => {
        textarea.focus();
        const newCursorPos = start + prefix.length;
        textarea.setSelectionRange(newCursorPos, newCursorPos);
    });
};

const cancel = () => {
    form.reset();
    showMentionDropdown.value = false;
    emit('cancel');
};

const handleFileUploaded = () => {
    // Refresh to show new attachments
    router.reload({ only: ['messages'] });
};

// Close dropdown when clicking outside
const handleClickOutside = (e) => {
    if (showMentionDropdown.value && !e.target.closest('.mention-dropdown')) {
        showMentionDropdown.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <div class="border-t border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface px-6 py-4">
        <!-- Editing indicator -->
        <div v-if="editingMessage" class="mb-2 flex items-center justify-between bg-yellow-100 dark:bg-yellow-900/30 px-3 py-2 rounded border border-yellow-300 dark:border-yellow-700">
            <span class="text-sm text-yellow-900 dark:text-yellow-200">
                <svg class="inline h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                Editing message
            </span>
            <button
                @click="cancel"
                class="text-sm text-yellow-900 dark:text-yellow-200 hover:text-yellow-950 dark:hover:text-yellow-100 font-medium"
            >
                Cancel
            </button>
        </div>

        <!-- Thread reply indicator -->
        <div v-if="parentMessageId && !editingMessage" class="mb-2 flex items-center justify-between bg-light-accent/10 dark:bg-dark-accent/10 px-3 py-2 rounded border border-light-accent/30 dark:border-dark-accent/30">
            <span class="text-sm text-light-accent dark:text-dark-accent">
                <svg class="inline h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                </svg>
                Replying to thread
            </span>
            <button
                @click="cancel"
                class="text-sm text-light-accent dark:text-dark-accent hover:text-light-accent-hover dark:hover:text-dark-accent-hover font-medium"
            >
                Cancel
            </button>
        </div>

        <form @submit.prevent="submit" class="flex items-end space-x-3">
            <!-- File Upload Button -->
            <FileUploadButton :conversation-id="conversationId" @uploaded="handleFileUploaded" />

            <div class="flex-1 relative">
                <!-- Mention Autocomplete Dropdown -->
                <div
                    v-if="showMentionDropdown && filteredMembers.length > 0"
                    class="mention-dropdown absolute bottom-full left-0 mb-2 w-64 bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded-lg shadow-lg max-h-48 overflow-y-auto z-50"
                >
                    <div v-if="loadingMembers" class="px-3 py-2 text-sm text-light-text-muted dark:text-dark-text-muted">
                        Loading...
                    </div>
                    <div
                        v-for="(member, index) in filteredMembers"
                        :key="member.id"
                        @click="selectMention(member)"
                        :class="[
                            'px-3 py-2 cursor-pointer flex items-center space-x-2',
                            index === selectedMentionIndex
                                ? 'bg-light-accent dark:bg-dark-accent text-white'
                                : 'hover:bg-light-bg dark:hover:bg-dark-bg text-light-text-primary dark:text-dark-text-primary'
                        ]"
                    >
                        <img
                            v-if="member.avatar_url"
                            :src="member.avatar_url"
                            :alt="member.name"
                            class="h-6 w-6 rounded-full"
                        />
                        <div v-else class="h-6 w-6 rounded-full bg-indigo-600 flex items-center justify-center text-white text-xs font-semibold">
                            {{ member.name.charAt(0).toUpperCase() }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium truncate">{{ member.name }}</div>
                            <div v-if="member.email" class="text-xs text-light-text-muted dark:text-dark-text-muted truncate">{{ member.email }}</div>
                        </div>
                    </div>
                </div>

                <textarea
                    ref="textareaRef"
                    v-model="form.body_md"
                    :placeholder="placeholder"
                    rows="3"
                    class="w-full bg-light-bg dark:bg-dark-bg border-light-border dark:border-dark-border text-light-text-primary dark:text-dark-text-primary rounded-lg shadow-sm focus:border-light-accent dark:focus:border-dark-accent focus:ring-2 focus:ring-light-accent/20 dark:focus:ring-dark-accent/20 resize-none transition-colors"
                    @keydown="handleKeydown"
                    @input="handleInput"
                ></textarea>
                <div class="mt-1 flex items-center justify-between">
                    <p class="text-xs text-light-text-muted dark:text-dark-text-muted">
                        <strong>Markdown</strong> supported • Type <strong>@</strong> to mention someone
                    </p>
                    <p class="text-xs text-light-text-muted dark:text-dark-text-muted">
                        Press <kbd class="px-1 py-0.5 bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded text-light-text-secondary dark:text-dark-text-secondary">Enter</kbd> to send
                    </p>
                </div>

                <!-- Show form errors -->
                <div v-if="form.errors.body_md" class="mt-2 text-sm text-red-600 dark:text-red-400">
                    {{ form.errors.body_md }}
                </div>
            </div>
            <button
                type="submit"
                :disabled="!form.body_md.trim() || form.processing"
                class="inline-flex items-center px-4 py-2 bg-light-accent dark:bg-dark-accent border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-light-accent-hover dark:hover:bg-dark-accent-hover shadow-neon hover:shadow-neon-lg active:bg-light-accent-muted dark:active:bg-dark-accent-muted focus:outline-none disabled:opacity-50 transition-all"
            >
                {{ editingMessage ? 'Update' : 'Send' }}
            </button>
        </form>
    </div>
</template>
