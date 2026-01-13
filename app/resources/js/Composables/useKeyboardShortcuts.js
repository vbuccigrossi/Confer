import { onMounted, onUnmounted } from 'vue';

export function useKeyboardShortcuts(shortcuts = {}) {
    const isMac = navigator.platform.toUpperCase().indexOf('MAC') >= 0;

    const handleKeydown = (event) => {
        const key = event.key.toLowerCase();
        const ctrl = event.ctrlKey || event.metaKey; // Cmd on Mac, Ctrl on Windows/Linux
        const shift = event.shiftKey;
        const alt = event.altKey;

        // Build shortcut signature
        let signature = '';
        if (ctrl) signature += 'ctrl+';
        if (shift) signature += 'shift+';
        if (alt) signature += 'alt+';
        signature += key;

        // Check if we have a handler for this shortcut
        if (shortcuts[signature]) {
            // Don't trigger if user is in an input/textarea (unless explicitly allowed)
            const target = event.target;
            const isInput = target.tagName === 'INPUT' ||
                          target.tagName === 'TEXTAREA' ||
                          target.isContentEditable;

            const handler = shortcuts[signature];
            const allowInInput = handler.allowInInput || false;

            if (!isInput || allowInInput) {
                event.preventDefault();
                handler.action(event);
            }
        }
    };

    onMounted(() => {
        window.addEventListener('keydown', handleKeydown);
    });

    onUnmounted(() => {
        window.removeEventListener('keydown', handleKeydown);
    });

    return {
        isMac,
    };
}

// Keyboard shortcut registry - global shortcuts
export const globalShortcuts = {
    // Quick switcher
    'ctrl+k': {
        description: 'Quick switcher',
        action: null, // Will be set by the component
        allowInInput: false,
    },
    // Help modal
    'ctrl+/': {
        description: 'Show keyboard shortcuts',
        action: null,
        allowInInput: false,
    },
    // Navigation
    'ctrl+shift+]': {
        description: 'Next conversation',
        action: null,
        allowInInput: false,
    },
    'ctrl+shift+[': {
        description: 'Previous conversation',
        action: null,
        allowInInput: false,
    },
    'escape': {
        description: 'Close panel/modal',
        action: null,
        allowInInput: false,
    },
};

// Text formatting shortcuts (for use in message composer)
export const formattingShortcuts = {
    'ctrl+b': {
        description: 'Bold text',
        action: null,
        allowInInput: true,
    },
    'ctrl+i': {
        description: 'Italic text',
        action: null,
        allowInInput: true,
    },
    'ctrl+shift+x': {
        description: 'Strikethrough text',
        action: null,
        allowInInput: true,
    },
    'ctrl+shift+c': {
        description: 'Inline code',
        action: null,
        allowInInput: true,
    },
    'ctrl+shift+7': {
        description: 'Numbered list',
        action: null,
        allowInInput: true,
    },
    'ctrl+shift+8': {
        description: 'Bullet list',
        action: null,
        allowInInput: true,
    },
    'ctrl+shift+9': {
        description: 'Code block',
        action: null,
        allowInInput: true,
    },
    'ctrl+shift+>': {
        description: 'Quote',
        action: null,
        allowInInput: true,
    },
};
