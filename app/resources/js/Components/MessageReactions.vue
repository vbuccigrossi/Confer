<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    reactions: Array,
    messageId: Number,
});

const emit = defineEmits(['add-reaction', 'remove-reaction']);

const page = usePage();
const currentUserId = computed(() => page.props.auth.user.id);

// Group reactions by emoji
const groupedReactions = computed(() => {
    if (!props.reactions || props.reactions.length === 0) return [];

    const groups = {};

    props.reactions.forEach(reaction => {
        if (!groups[reaction.emoji]) {
            groups[reaction.emoji] = {
                emoji: reaction.emoji,
                count: 0,
                users: [],
                hasUserReacted: false,
            };
        }

        groups[reaction.emoji].count++;
        groups[reaction.emoji].users.push(reaction.user);

        if (reaction.user.id === currentUserId.value) {
            groups[reaction.emoji].hasUserReacted = true;
        }
    });

    return Object.values(groups);
});

// Format user names for tooltip
const formatUsers = (users) => {
    if (users.length === 0) return '';
    if (users.length === 1) return users[0].name;
    if (users.length === 2) return `${users[0].name} and ${users[1].name}`;
    return `${users[0].name}, ${users[1].name}, and ${users.length - 2} other${users.length - 2 > 1 ? 's' : ''}`;
};

const toggleReaction = (group) => {
    if (group.hasUserReacted) {
        emit('remove-reaction', group.emoji);
    } else {
        emit('add-reaction', group.emoji);
    }
};
</script>

<template>
    <div v-if="groupedReactions.length > 0" class="flex flex-wrap gap-1 mt-2">
        <button
            v-for="group in groupedReactions"
            :key="group.emoji"
            @click="toggleReaction(group)"
            :class="[
                'inline-flex items-center px-2 py-1 rounded-full text-sm transition-all',
                group.hasUserReacted
                    ? 'bg-light-accent/20 dark:bg-dark-accent/20 border-2 border-light-accent dark:border-dark-accent'
                    : 'bg-light-bg dark:bg-dark-bg border border-light-border dark:border-dark-border hover:border-light-accent dark:hover:border-dark-accent'
            ]"
            :title="formatUsers(group.users)"
        >
            <span class="text-base mr-1">{{ group.emoji }}</span>
            <span
                :class="[
                    'text-xs font-medium',
                    group.hasUserReacted
                        ? 'text-light-accent dark:text-dark-accent'
                        : 'text-light-text-secondary dark:text-dark-text-secondary'
                ]"
            >
                {{ group.count }}
            </span>
        </button>
    </div>
</template>
