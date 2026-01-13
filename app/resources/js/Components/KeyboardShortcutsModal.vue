<script setup>
import { computed } from 'vue';

const props = defineProps({
    show: Boolean,
});

const emit = defineEmits(['close']);

const isMac = navigator.platform.toUpperCase().indexOf('MAC') >= 0;
const modKey = isMac ? '⌘' : 'Ctrl';

const shortcutSections = [
    {
        title: 'Navigation',
        shortcuts: [
            { keys: [`${modKey}`, 'K'], description: 'Quick switcher - Jump to any conversation' },
            { keys: [`${modKey}`, '⇧', ']'], description: 'Next conversation' },
            { keys: [`${modKey}`, '⇧', '['], description: 'Previous conversation' },
            { keys: ['Esc'], description: 'Close panel or modal' },
        ],
    },
    {
        title: 'Messages',
        shortcuts: [
            { keys: [`${modKey}`, 'Enter'], description: 'Send message' },
            { keys: [`${modKey}`, '⇧', 'Enter'], description: 'New line in message' },
            { keys: ['↑'], description: 'Edit last message' },
        ],
    },
    {
        title: 'Formatting',
        shortcuts: [
            { keys: [`${modKey}`, 'B'], description: 'Bold text' },
            { keys: [`${modKey}`, 'I'], description: 'Italic text' },
            { keys: [`${modKey}`, '⇧', 'X'], description: 'Strikethrough text' },
            { keys: [`${modKey}`, '⇧', 'C'], description: 'Inline code' },
            { keys: [`${modKey}`, '⇧', '7'], description: 'Numbered list' },
            { keys: [`${modKey}`, '⇧', '8'], description: 'Bullet list' },
            { keys: [`${modKey}`, '⇧', '9'], description: 'Code block' },
            { keys: [`${modKey}`, '⇧', '>'], description: 'Quote' },
        ],
    },
    {
        title: 'General',
        shortcuts: [
            { keys: [`${modKey}`, '/'], description: 'Show keyboard shortcuts (this dialog)' },
            { keys: ['?'], description: 'Show keyboard shortcuts (alternative)' },
        ],
    },
];
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
                <div class="flex min-h-screen items-center justify-center p-4">
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
                            class="relative w-full max-w-4xl bg-light-surface dark:bg-dark-surface rounded-lg shadow-2xl"
                            @click.stop
                        >
                            <!-- Header -->
                            <div class="px-6 py-4 border-b border-light-border dark:border-dark-border">
                                <div class="flex items-center justify-between">
                                    <h2 class="text-xl font-bold text-light-text-primary dark:text-dark-text-primary">
                                        Keyboard Shortcuts
                                    </h2>
                                    <button
                                        @click="emit('close')"
                                        class="text-light-text-muted dark:text-dark-text-muted hover:text-light-text-primary dark:hover:text-dark-text-primary transition-colors"
                                    >
                                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Content -->
                            <div class="p-6 max-h-[70vh] overflow-y-auto">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                    <div
                                        v-for="section in shortcutSections"
                                        :key="section.title"
                                        class="space-y-3"
                                    >
                                        <h3 class="text-sm font-semibold text-light-text-secondary dark:text-dark-text-secondary uppercase tracking-wider">
                                            {{ section.title }}
                                        </h3>
                                        <div class="space-y-2">
                                            <div
                                                v-for="(shortcut, index) in section.shortcuts"
                                                :key="index"
                                                class="flex items-center justify-between py-2"
                                            >
                                                <span class="text-sm text-light-text-primary dark:text-dark-text-primary">
                                                    {{ shortcut.description }}
                                                </span>
                                                <div class="flex items-center space-x-1">
                                                    <kbd
                                                        v-for="(key, keyIndex) in shortcut.keys"
                                                        :key="keyIndex"
                                                        class="px-2 py-1 text-xs font-semibold bg-light-bg dark:bg-dark-bg border border-light-border dark:border-dark-border rounded shadow-sm"
                                                    >
                                                        {{ key }}
                                                    </kbd>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Footer -->
                            <div class="px-6 py-4 bg-light-bg dark:bg-dark-bg border-t border-light-border dark:border-dark-border rounded-b-lg">
                                <p class="text-xs text-light-text-muted dark:text-dark-text-muted text-center">
                                    Press <kbd class="px-2 py-1 bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded">Esc</kbd> or
                                    <kbd class="px-2 py-1 bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded">{{ modKey }}</kbd>
                                    <kbd class="px-2 py-1 bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded">/</kbd>
                                    to close this dialog
                                </p>
                            </div>
                        </div>
                    </Transition>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
