<script setup>
import { ref, onMounted, onUnmounted, watch, nextTick } from 'vue';

const props = defineProps({
    show: Boolean,
    buttonRef: Object,
});

const emit = defineEmits(['select', 'close']);

const pickerRef = ref(null);
const pickerStyle = ref({});

// Common emoji reactions (Slack-style)
const commonEmojis = [
    '👍', '❤️', '😂', '😮', '😢', '😡',
    '🎉', '🙏', '👏', '🔥', '✅', '❌',
    '👀', '💯', '🚀', '⭐', '💪', '🤔',
    '😊', '😎', '🤩', '😍', '🥳', '😴',
    '🤝', '💡', '⚡', '🎯', '📌', '🎨',
];

const selectEmoji = (emoji) => {
    emit('select', emoji);
    emit('close');
};

// Calculate optimal position when shown
const updatePosition = () => {
    if (!props.buttonRef || !pickerRef.value) return;

    const buttonRect = props.buttonRef.getBoundingClientRect();
    const pickerRect = pickerRef.value.getBoundingClientRect();
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;

    const pickerWidth = 280; // min-width from template
    const pickerHeight = pickerRect.height || 300; // approximate

    // Calculate horizontal position
    let left = buttonRect.left;
    let right = 'auto';

    // If it would go off the right edge, align to right edge of button
    if (buttonRect.left + pickerWidth > viewportWidth) {
        left = 'auto';
        right = viewportWidth - buttonRect.right;
    }

    // Calculate vertical position (default below button)
    let top = buttonRect.bottom + 8;
    let bottom = 'auto';

    // If it would go off the bottom, show above button
    if (buttonRect.bottom + pickerHeight > viewportHeight) {
        top = 'auto';
        bottom = viewportHeight - buttonRect.top + 8;
    }

    pickerStyle.value = {
        top: typeof top === 'number' ? `${top}px` : top,
        bottom: typeof bottom === 'number' ? `${bottom}px` : bottom,
        left: typeof left === 'number' ? `${left}px` : left,
        right: typeof right === 'number' ? `${right}px` : right,
    };
};

watch(() => props.show, (isShown) => {
    if (isShown) {
        nextTick(() => {
            updatePosition();
        });
    }
});
</script>

<template>
    <Transition
        enter-active-class="transition ease-out duration-100"
        enter-from-class="opacity-0 scale-95"
        enter-to-class="opacity-100 scale-100"
        leave-active-class="transition ease-in duration-75"
        leave-from-class="opacity-100 scale-100"
        leave-to-class="opacity-0 scale-95"
    >
        <div
            v-if="show"
            ref="pickerRef"
            class="fixed z-50 bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded-lg shadow-2xl p-3"
            :style="{ minWidth: '280px', ...pickerStyle }"
            @click.stop
        >
            <!-- Header -->
            <div class="flex items-center justify-between mb-2 pb-2 border-b border-light-border dark:border-dark-border">
                <span class="text-xs font-semibold text-light-text-secondary dark:text-dark-text-secondary uppercase">
                    Pick a reaction
                </span>
                <button
                    @click="emit('close')"
                    class="text-light-text-muted dark:text-dark-text-muted hover:text-light-text-primary dark:hover:text-dark-text-primary transition-colors"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Emoji Grid -->
            <div class="grid grid-cols-6 gap-1 max-h-64 overflow-y-auto">
                <button
                    v-for="emoji in commonEmojis"
                    :key="emoji"
                    @click="selectEmoji(emoji)"
                    class="text-2xl p-2 rounded hover:bg-light-bg dark:hover:bg-dark-bg transition-colors"
                    :title="emoji"
                >
                    {{ emoji }}
                </button>
            </div>

            <!-- Footer hint -->
            <div class="mt-2 pt-2 border-t border-light-border dark:border-dark-border">
                <p class="text-xs text-light-text-muted dark:text-dark-text-muted text-center">
                    Click an emoji to add your reaction
                </p>
            </div>
        </div>
    </Transition>
</template>
