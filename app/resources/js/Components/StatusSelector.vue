<script setup>
import { ref, computed, onMounted } from 'vue';
import { usePage, router } from '@inertiajs/vue3';

const props = defineProps({
    show: Boolean,
});

const emit = defineEmits(['close', 'status-updated']);

const page = usePage();
const currentUser = computed(() => page.props.auth.user);
const loading = ref(false);
const presets = ref([]);
const customStatus = ref({
    message: '',
    emoji: '',
    duration: null,
});

onMounted(async () => {
    try {
        const response = await window.axios.get('/api/status/presets');
        presets.value = response.data.presets;
    } catch (error) {
        console.error('Failed to load status presets:', error);
    }
});

const refreshPage = () => {
    // Reload the current page to get fresh user data
    router.reload({ only: ['auth'] });
};

const setStatus = async (preset) => {
    loading.value = true;
    try {
        const response = await window.axios.put('/api/status', {
            status: preset.status,
            message: preset.message,
            emoji: preset.emoji,
            expires_in: preset.duration || null,
        });
        // Update the user data in Inertia's page props
        if (response.data.user) {
            page.props.auth.user.status = response.data.user.status;
            page.props.auth.user.status_message = response.data.user.status_message;
            page.props.auth.user.status_emoji = response.data.user.status_emoji;
        }
        emit('status-updated');
        emit('close');
    } catch (error) {
        console.error('Failed to set status:', error);
    } finally {
        loading.value = false;
    }
};

const setCustomStatus = async () => {
    if (!customStatus.value.message) return;

    loading.value = true;
    try {
        const response = await window.axios.put('/api/status', {
            status: 'active',
            message: customStatus.value.message,
            emoji: customStatus.value.emoji || null,
            expires_in: customStatus.value.duration || null,
        });
        // Update the user data in Inertia's page props
        if (response.data.user) {
            page.props.auth.user.status = response.data.user.status;
            page.props.auth.user.status_message = response.data.user.status_message;
            page.props.auth.user.status_emoji = response.data.user.status_emoji;
        }
        customStatus.value = { message: '', emoji: '', duration: null };
        emit('status-updated');
        emit('close');
    } catch (error) {
        console.error('Failed to set custom status:', error);
    } finally {
        loading.value = false;
    }
};

const clearStatus = async () => {
    loading.value = true;
    try {
        await window.axios.delete('/api/status');
        // Clear the status in Inertia's page props
        page.props.auth.user.status = 'active';
        page.props.auth.user.status_message = null;
        page.props.auth.user.status_emoji = null;
        emit('status-updated');
        emit('close');
    } catch (error) {
        console.error('Failed to clear status:', error);
    } finally {
        loading.value = false;
    }
};

const getStatusIcon = (status) => {
    const icons = {
        active: '🟢',
        away: '🟡',
        dnd: '🔴',
        invisible: '⚫',
    };
    return icons[status] || '🟢';
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
                @click.self="emit('close')"
            >
                <div class="flex min-h-screen items-center justify-center p-4">
                    <div class="fixed inset-0 bg-black/50" @click="emit('close')"></div>

                    <div class="relative bg-light-surface dark:bg-dark-surface rounded-lg shadow-2xl max-w-md w-full p-6 z-10">
                        <!-- Header -->
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-xl font-bold text-light-text-primary dark:text-dark-text-primary">
                                Set a status
                            </h2>
                            <button
                                @click="emit('close')"
                                class="text-light-text-muted dark:text-dark-text-muted hover:text-light-text-primary dark:hover:text-dark-text-primary"
                            >
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <!-- Current Status -->
                        <div v-if="currentUser.status_message" class="mb-4 p-3 bg-light-bg dark:bg-dark-bg rounded-lg">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-2">
                                    <span class="text-2xl">{{ currentUser.status_emoji || getStatusIcon(currentUser.status) }}</span>
                                    <span class="text-sm text-light-text-primary dark:text-dark-text-primary">{{ currentUser.status_message }}</span>
                                </div>
                                <button
                                    @click="clearStatus"
                                    class="text-xs text-light-accent dark:text-dark-accent hover:underline"
                                    :disabled="loading"
                                >
                                    Clear
                                </button>
                            </div>
                        </div>

                        <!-- Presets -->
                        <div class="space-y-1 mb-4">
                            <button
                                v-for="preset in presets"
                                :key="preset.label"
                                @click="setStatus(preset)"
                                :disabled="loading"
                                class="w-full flex items-center space-x-3 p-3 rounded-lg hover:bg-light-bg dark:hover:bg-dark-bg transition-colors text-left disabled:opacity-50"
                            >
                                <span class="text-2xl">{{ preset.emoji || getStatusIcon(preset.status) }}</span>
                                <span class="text-sm text-light-text-primary dark:text-dark-text-primary">{{ preset.label }}</span>
                            </button>
                        </div>

                        <!-- Custom Status -->
                        <div class="border-t border-light-border dark:border-dark-border pt-4">
                            <h3 class="text-sm font-semibold text-light-text-secondary dark:text-dark-text-secondary mb-3">
                                Custom status
                            </h3>
                            <div class="space-y-3">
                                <div class="flex space-x-2">
                                    <input
                                        v-model="customStatus.emoji"
                                        type="text"
                                        placeholder="😀"
                                        maxlength="2"
                                        class="w-16 text-center text-2xl bg-light-bg dark:bg-dark-bg border-light-border dark:border-dark-border rounded-md"
                                    />
                                    <input
                                        v-model="customStatus.message"
                                        type="text"
                                        placeholder="What's your status?"
                                        maxlength="100"
                                        class="flex-1 bg-light-bg dark:bg-dark-bg border-light-border dark:border-dark-border text-light-text-primary dark:text-dark-text-primary rounded-md px-3 py-2"
                                        @keydown.enter="setCustomStatus"
                                    />
                                </div>
                                <div>
                                    <label class="block text-xs text-light-text-muted dark:text-dark-text-muted mb-1">
                                        Clear after
                                    </label>
                                    <select
                                        v-model="customStatus.duration"
                                        class="w-full bg-light-bg dark:bg-dark-bg border-light-border dark:border-dark-border text-light-text-primary dark:text-dark-text-primary rounded-md px-3 py-2"
                                    >
                                        <option :value="null">Don't clear</option>
                                        <option :value="30">30 minutes</option>
                                        <option :value="60">1 hour</option>
                                        <option :value="240">4 hours</option>
                                        <option :value="1440">Today</option>
                                    </select>
                                </div>
                                <button
                                    @click="setCustomStatus"
                                    :disabled="!customStatus.message || loading"
                                    class="w-full px-4 py-2 bg-light-accent dark:bg-dark-accent text-white rounded-md hover:bg-light-accent-hover dark:hover:bg-dark-accent-hover disabled:opacity-50 transition-colors"
                                >
                                    Set Status
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
