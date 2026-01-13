<script setup>
import { ref, computed, onMounted } from 'vue';
import { useForm } from '@inertiajs/vue3';
import ActionMessage from '@/Components/ActionMessage.vue';
import ActionSection from '@/Components/ActionSection.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { playNotificationSound } from '@/Utils/notificationSound';

const props = defineProps({
    user: Object,
});

const form = useForm({
    sound_notifications: props.user?.sound_notifications ?? true,
    default_notify_level: props.user?.default_notify_level ?? 'all',
    notification_keywords: props.user?.notification_keywords ?? [],
    quiet_hours_start: props.user?.quiet_hours_start ?? null,
    quiet_hours_end: props.user?.quiet_hours_end ?? null,
});

const dndActive = ref(false);
const dndUntil = ref(null);
const newKeyword = ref('');
const showDndOptions = ref(false);

// Check if DND is currently active
onMounted(async () => {
    try {
        const response = await window.axios.get('/api/users/notification-settings');
        if (response.data.do_not_disturb_until) {
            const until = new Date(response.data.do_not_disturb_until);
            if (until > new Date()) {
                dndActive.value = true;
                dndUntil.value = until;
            }
        }
    } catch (error) {
        console.error('Failed to fetch DND status:', error);
    }
});

const dndTimeRemaining = computed(() => {
    if (!dndUntil.value) return '';

    const now = new Date();
    const diff = dndUntil.value - now;
    const minutes = Math.floor(diff / 60000);
    const hours = Math.floor(minutes / 60);

    if (hours > 24) {
        return `${Math.floor(hours / 24)} days`;
    } else if (hours > 0) {
        return `${hours}h ${minutes % 60}m`;
    } else {
        return `${minutes}m`;
    }
});

const updateNotificationPreferences = async () => {
    try {
        await window.axios.put('/api/users/notification-settings', {
            default_notify_level: form.default_notify_level,
            notification_keywords: form.notification_keywords,
            quiet_hours_start: form.quiet_hours_start,
            quiet_hours_end: form.quiet_hours_end,
        });

        form.put(route('user.notification-preferences.update'), {
            errorBag: 'updateNotificationPreferences',
            preserveScroll: true,
        });
    } catch (error) {
        console.error('Failed to update preferences:', error);
    }
};

const enableDnd = async (duration) => {
    try {
        const response = await window.axios.post('/api/users/dnd', { duration });
        dndActive.value = true;
        dndUntil.value = new Date(response.data.do_not_disturb_until);
        showDndOptions.value = false;
    } catch (error) {
        console.error('Failed to enable DND:', error);
    }
};

const disableDnd = async () => {
    try {
        await window.axios.delete('/api/users/dnd');
        dndActive.value = false;
        dndUntil.value = null;
    } catch (error) {
        console.error('Failed to disable DND:', error);
    }
};

const addKeyword = () => {
    const keyword = newKeyword.value.trim();
    if (keyword && !form.notification_keywords.includes(keyword)) {
        form.notification_keywords.push(keyword);
        newKeyword.value = '';
    }
};

const removeKeyword = (keyword) => {
    form.notification_keywords = form.notification_keywords.filter(k => k !== keyword);
};

const testSound = () => {
    playNotificationSound();
};
</script>

<template>
    <ActionSection>
        <template #title>
            Notification Preferences
        </template>

        <template #description>
            Manage how you receive notifications for new messages, set quiet hours, and configure Do Not Disturb mode.
        </template>

        <template #content>
            <div class="max-w-xl text-sm text-light-text-secondary dark:text-dark-text-secondary">
                <p class="mb-3">
                    Configure your notification settings to customize how you're alerted to new messages.
                </p>
            </div>

            <div class="mt-5 space-y-6">
                <!-- Do Not Disturb -->
                <div class="border-b border-light-border dark:border-dark-border pb-6">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <div class="text-sm font-medium text-light-text-primary dark:text-dark-text-primary">
                                Do Not Disturb
                            </div>
                            <div class="text-xs text-light-text-secondary dark:text-dark-text-secondary mt-1">
                                <span v-if="dndActive">
                                    Active for {{ dndTimeRemaining }}
                                </span>
                                <span v-else>
                                    Pause all notifications temporarily
                                </span>
                            </div>
                        </div>
                        <div class="flex items-center space-x-3">
                            <button
                                v-if="!dndActive"
                                type="button"
                                @click="showDndOptions = !showDndOptions"
                                class="px-4 py-2 bg-light-accent dark:bg-dark-accent text-white rounded-lg hover:bg-light-accent-hover dark:hover:bg-dark-accent-hover transition-colors text-xs font-medium"
                            >
                                Enable DND
                            </button>
                            <button
                                v-else
                                type="button"
                                @click="disableDnd"
                                class="px-4 py-2 bg-light-bg dark:bg-dark-bg text-light-text-primary dark:text-dark-text-primary rounded-lg hover:bg-light-surface dark:hover:bg-dark-surface transition-colors text-xs font-medium border border-light-border dark:border-dark-border"
                            >
                                Disable DND
                            </button>
                        </div>
                    </div>

                    <!-- DND Duration Options -->
                    <div v-if="showDndOptions" class="mt-4 grid grid-cols-3 gap-2">
                        <button
                            @click="enableDnd('30m')"
                            class="px-3 py-2 bg-light-bg dark:bg-dark-bg border border-light-border dark:border-dark-border rounded-lg hover:border-light-accent dark:hover:border-dark-accent text-xs font-medium text-light-text-primary dark:text-dark-text-primary transition-colors"
                        >
                            30 minutes
                        </button>
                        <button
                            @click="enableDnd('1h')"
                            class="px-3 py-2 bg-light-bg dark:bg-dark-bg border border-light-border dark:border-dark-border rounded-lg hover:border-light-accent dark:hover:border-dark-accent text-xs font-medium text-light-text-primary dark:text-dark-text-primary transition-colors"
                        >
                            1 hour
                        </button>
                        <button
                            @click="enableDnd('4h')"
                            class="px-3 py-2 bg-light-bg dark:bg-dark-bg border border-light-border dark:border-dark-border rounded-lg hover:border-light-accent dark:hover:border-dark-accent text-xs font-medium text-light-text-primary dark:text-dark-text-primary transition-colors"
                        >
                            4 hours
                        </button>
                        <button
                            @click="enableDnd('24h')"
                            class="px-3 py-2 bg-light-bg dark:bg-dark-bg border border-light-border dark:border-dark-border rounded-lg hover:border-light-accent dark:hover:border-dark-accent text-xs font-medium text-light-text-primary dark:text-dark-text-primary transition-colors col-span-3"
                        >
                            24 hours
                        </button>
                    </div>
                </div>

                <!-- Default Notification Level -->
                <div class="border-b border-light-border dark:border-dark-border pb-6">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <div class="text-sm font-medium text-light-text-primary dark:text-dark-text-primary">
                                Default Notification Level
                            </div>
                            <div class="text-xs text-light-text-secondary dark:text-dark-text-secondary mt-1">
                                Default notification behavior for new conversations
                            </div>
                        </div>
                        <select
                            v-model="form.default_notify_level"
                            @change="updateNotificationPreferences"
                            class="px-3 py-2 bg-light-bg dark:bg-dark-bg border border-light-border dark:border-dark-border rounded-lg text-sm text-light-text-primary dark:text-dark-text-primary focus:border-light-accent dark:focus:border-dark-accent focus:ring-2 focus:ring-light-accent/20 dark:focus:ring-dark-accent/20"
                        >
                            <option value="all">All messages</option>
                            <option value="mentions">@Mentions only</option>
                            <option value="nothing">Nothing</option>
                        </select>
                    </div>
                </div>

                <!-- Quiet Hours -->
                <div class="border-b border-light-border dark:border-dark-border pb-6">
                    <div>
                        <div class="text-sm font-medium text-light-text-primary dark:text-dark-text-primary mb-3">
                            Quiet Hours
                        </div>
                        <div class="text-xs text-light-text-secondary dark:text-dark-text-secondary mb-3">
                            Automatically pause notifications during specific hours
                        </div>
                        <div class="flex items-center space-x-3">
                            <div>
                                <label class="block text-xs text-light-text-secondary dark:text-dark-text-secondary mb-1">Start</label>
                                <input
                                    type="time"
                                    v-model="form.quiet_hours_start"
                                    class="px-3 py-2 bg-light-bg dark:bg-dark-bg border border-light-border dark:border-dark-border rounded-lg text-sm text-light-text-primary dark:text-dark-text-primary focus:border-light-accent dark:focus:border-dark-accent focus:ring-2 focus:ring-light-accent/20 dark:focus:ring-dark-accent/20"
                                />
                            </div>
                            <div class="pt-6">
                                <svg class="h-5 w-5 text-light-text-muted dark:text-dark-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>
                            </div>
                            <div>
                                <label class="block text-xs text-light-text-secondary dark:text-dark-text-secondary mb-1">End</label>
                                <input
                                    type="time"
                                    v-model="form.quiet_hours_end"
                                    class="px-3 py-2 bg-light-bg dark:bg-dark-bg border border-light-border dark:border-dark-border rounded-lg text-sm text-light-text-primary dark:text-dark-text-primary focus:border-light-accent dark:focus:border-dark-accent focus:ring-2 focus:ring-light-accent/20 dark:focus:ring-dark-accent/20"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notification Keywords -->
                <div class="border-b border-light-border dark:border-dark-border pb-6">
                    <div>
                        <div class="text-sm font-medium text-light-text-primary dark:text-dark-text-primary mb-3">
                            Notification Keywords
                        </div>
                        <div class="text-xs text-light-text-secondary dark:text-dark-text-secondary mb-3">
                            Get notified when these words appear in any message
                        </div>

                        <!-- Keyword Tags -->
                        <div v-if="form.notification_keywords.length > 0" class="flex flex-wrap gap-2 mb-3">
                            <span
                                v-for="keyword in form.notification_keywords"
                                :key="keyword"
                                class="inline-flex items-center px-3 py-1 bg-light-accent/10 dark:bg-dark-accent/10 border border-light-accent dark:border-dark-accent text-light-accent dark:text-dark-accent rounded-full text-xs font-medium"
                            >
                                {{ keyword }}
                                <button
                                    type="button"
                                    @click="removeKeyword(keyword)"
                                    class="ml-2 hover:text-light-accent-hover dark:hover:text-dark-accent-hover"
                                >
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </span>
                        </div>

                        <!-- Add Keyword Input -->
                        <div class="flex space-x-2">
                            <input
                                type="text"
                                v-model="newKeyword"
                                @keydown.enter.prevent="addKeyword"
                                placeholder="Add a keyword..."
                                class="flex-1 px-3 py-2 bg-light-bg dark:bg-dark-bg border border-light-border dark:border-dark-border rounded-lg text-sm text-light-text-primary dark:text-dark-text-primary placeholder-light-text-muted dark:placeholder-dark-text-muted focus:border-light-accent dark:focus:border-dark-accent focus:ring-2 focus:ring-light-accent/20 dark:focus:ring-dark-accent/20"
                            />
                            <button
                                type="button"
                                @click="addKeyword"
                                class="px-4 py-2 bg-light-accent dark:bg-dark-accent text-white rounded-lg hover:bg-light-accent-hover dark:hover:bg-dark-accent-hover transition-colors text-xs font-medium"
                            >
                                Add
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Sound Notifications Toggle -->
                <div class="flex items-center justify-between py-3">
                    <div class="flex-1">
                        <div class="text-sm font-medium text-light-text-primary dark:text-dark-text-primary">
                            Sound Notifications
                        </div>
                        <div class="text-xs text-light-text-secondary dark:text-dark-text-secondary mt-1">
                            Play a sound when you receive new messages
                        </div>
                    </div>
                    <div class="flex items-center space-x-3">
                        <button
                            type="button"
                            @click="testSound"
                            class="text-xs text-light-accent dark:text-dark-accent hover:underline"
                        >
                            Test Sound
                        </button>
                        <button
                            type="button"
                            @click="form.sound_notifications = !form.sound_notifications; updateNotificationPreferences();"
                            :class="[
                                'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-light-accent dark:focus:ring-dark-accent focus:ring-offset-2',
                                form.sound_notifications ? 'bg-light-accent dark:bg-dark-accent' : 'bg-light-bg dark:bg-dark-bg border border-light-border dark:border-dark-border'
                            ]"
                        >
                            <span
                                :class="[
                                    'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                                    form.sound_notifications ? 'translate-x-5' : 'translate-x-0'
                                ]"
                            />
                        </button>
                    </div>
                </div>

                <!-- Save Button -->
                <div class="flex items-center justify-end">
                    <PrimaryButton @click="updateNotificationPreferences" :disabled="form.processing">
                        Save Preferences
                    </PrimaryButton>
                </div>
            </div>

            <ActionMessage :on="form.recentlySuccessful" class="mt-3">
                Saved.
            </ActionMessage>
        </template>
    </ActionSection>
</template>

<style scoped>
/* Fix time input icons and internal styling for dark mode */
:deep(input[type="time"]) {
    color-scheme: light;
}

.dark :deep(input[type="time"]) {
    color-scheme: dark;
}

/* Fix select dropdown options for dark mode */
:deep(select option) {
    background-color: var(--light-surface);
    color: var(--light-text-primary);
}

.dark :deep(select option) {
    background-color: var(--dark-surface);
    color: var(--dark-text-primary);
}
</style>
