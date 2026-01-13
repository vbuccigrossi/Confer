<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import ConversationLayout from '@/Layouts/ConversationLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    availableBots: Array,
});

const page = usePage();

const form = useForm({
    bot_id: null,
});

const startBotDm = () => {
    form.post(route('web.conversations.bot-dm.store'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <ConversationLayout>
        <div class="max-w-2xl mx-auto py-8 px-4">
            <h2 class="text-2xl font-bold text-light-text-primary dark:text-dark-text-primary mb-6">
                Start a Conversation with a Bot
            </h2>

            <div class="bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded-lg p-6">
                <form @submit.prevent="startBotDm">
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-light-text-primary dark:text-dark-text-primary mb-3">
                            Select a Bot
                        </label>
                        <div class="space-y-2">
                            <div
                                v-for="bot in availableBots"
                                :key="bot.id"
                                class="flex items-center"
                            >
                                <input
                                    type="radio"
                                    :id="`bot-${bot.id}`"
                                    v-model="form.bot_id"
                                    :value="bot.id"
                                    class="h-4 w-4 text-light-accent dark:text-dark-accent focus:ring-light-accent dark:focus:ring-dark-accent border-light-border dark:border-dark-border"
                                />
                                <label
                                    :for="`bot-${bot.id}`"
                                    class="ml-3 flex-1 cursor-pointer"
                                >
                                    <div class="flex items-center">
                                        <svg class="h-5 w-5 mr-2 text-light-text-secondary dark:text-dark-text-secondary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                        </svg>
                                        <div>
                                            <div class="text-sm font-medium text-light-text-primary dark:text-dark-text-primary">
                                                {{ bot.name }}
                                            </div>
                                            <div v-if="bot.description" class="text-xs text-light-text-muted dark:text-dark-text-muted">
                                                {{ bot.description }}
                                            </div>
                                        </div>
                                    </div>
                                </label>
                            </div>

                            <div v-if="!availableBots || availableBots.length === 0" class="text-sm text-light-text-muted dark:text-dark-text-muted py-4 text-center">
                                No bots available in this workspace
                            </div>
                        </div>

                        <div v-if="form.errors.bot_id" class="mt-2 text-sm text-light-danger dark:text-dark-danger">
                            {{ form.errors.bot_id }}
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button
                            type="button"
                            @click="$inertia.visit(route('web.conversations.index'))"
                            class="px-4 py-2 text-sm font-medium text-light-text-secondary dark:text-dark-text-secondary hover:text-light-text-primary dark:hover:text-dark-text-primary transition-colors"
                        >
                            Cancel
                        </button>
                        <PrimaryButton :disabled="!form.bot_id || form.processing">
                            Start Conversation
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </ConversationLayout>
</template>
