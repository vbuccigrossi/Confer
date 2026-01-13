<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import ConversationLayout from '@/Layouts/ConversationLayout.vue';

const props = defineProps({
    workspace: Object,
});

const settingsForm = useForm({
    name: props.workspace.name,
    description: props.workspace.description || '',
});

const updateSettings = () => {
    settingsForm.put(`/workspaces/${props.workspace.id}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <ConversationLayout title="Admin Settings">
        <template #sidebar>
            <div class="p-4">
                <h3 class="text-xs font-semibold text-light-text-muted dark:text-dark-text-muted uppercase tracking-wider mb-2">
                    Admin Panel
                </h3>
                <div class="space-y-1">
                    <a :href="`/admin/workspaces/${workspace.id}/overview`"
                       class="flex items-center px-3 py-2 text-sm font-medium rounded-md text-light-text-secondary dark:text-dark-text-secondary hover:bg-light-bg dark:hover:bg-dark-bg hover:text-light-text-primary dark:hover:text-dark-accent transition-colors">
                        <svg class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                        Overview
                    </a>
                    <a :href="`/admin/workspaces/${workspace.id}/members`"
                       class="flex items-center px-3 py-2 text-sm font-medium rounded-md text-light-text-secondary dark:text-dark-text-secondary hover:bg-light-bg dark:hover:bg-dark-bg hover:text-light-text-primary dark:hover:text-dark-accent transition-colors">
                        <svg class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        Members
                    </a>
                    <a :href="`/admin/workspaces/${workspace.id}/invites`"
                       class="flex items-center px-3 py-2 text-sm font-medium rounded-md text-light-text-secondary dark:text-dark-text-secondary hover:bg-light-bg dark:hover:bg-dark-bg hover:text-light-text-primary dark:hover:text-dark-accent transition-colors">
                        <svg class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        Invites
                    </a>
                    <a :href="`/admin/workspaces/${workspace.id}/bots`"
                       class="flex items-center px-3 py-2 text-sm font-medium rounded-md text-light-text-secondary dark:text-dark-text-secondary hover:bg-light-bg dark:hover:bg-dark-bg hover:text-light-text-primary dark:hover:text-dark-accent transition-colors">
                        <svg class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        Bots
                    </a>
                    <a :href="`/admin/workspaces/${workspace.id}/email`"
                       class="flex items-center px-3 py-2 text-sm font-medium rounded-md text-light-text-secondary dark:text-dark-text-secondary hover:bg-light-bg dark:hover:bg-dark-bg hover:text-light-text-primary dark:hover:text-dark-accent transition-colors">
                        <svg class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        Email
                    </a>
                    <a :href="`/admin/workspaces/${workspace.id}/settings`"
                       class="flex items-center px-3 py-2 text-sm font-medium rounded-md bg-light-accent/10 dark:bg-dark-accent/10 text-light-accent dark:text-dark-accent border-l-2 border-light-accent dark:border-dark-accent transition-colors">
                        <svg class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Settings
                    </a>
                </div>
            </div>
        </template>

        <template #header>
            <div class="flex items-center">
                <h2 class="font-semibold text-xl text-light-text-primary dark:text-dark-text-primary leading-tight">
                    Workspace Settings
                </h2>
            </div>
        </template>

        <div class="py-12 px-4 sm:px-6 lg:px-8 overflow-y-auto h-full">
            <div class="max-w-4xl mx-auto">
                <!-- Workspace Information Card -->
                <div class="bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded-lg shadow-lg mb-8">
                    <div class="px-6 py-5 border-b border-light-border dark:border-dark-border">
                        <h3 class="text-lg font-medium text-light-text-primary dark:text-dark-text-primary">
                            Workspace Information
                        </h3>
                        <p class="mt-1 text-sm text-light-text-muted dark:text-dark-text-muted">
                            Update your workspace's name and description.
                        </p>
                    </div>
                    <div class="px-6 py-5">
                        <form @submit.prevent="updateSettings" class="space-y-6">
                            <div>
                                <label for="name" class="block text-sm font-medium text-light-text-secondary dark:text-dark-text-secondary mb-2">
                                    Workspace Name
                                </label>
                                <input
                                    id="name"
                                    v-model="settingsForm.name"
                                    type="text"
                                    class="w-full px-4 py-2 bg-light-bg dark:bg-dark-bg border border-light-border dark:border-dark-border rounded-md text-light-text-primary dark:text-dark-text-primary focus:border-light-accent dark:focus:border-dark-accent focus:ring-2 focus:ring-light-accent/20 dark:focus:ring-dark-accent/20 focus:outline-none transition-colors"
                                    required
                                />
                                <div v-if="settingsForm.errors.name" class="mt-2 text-sm text-red-600 dark:text-red-400">
                                    {{ settingsForm.errors.name }}
                                </div>
                            </div>

                            <div>
                                <label for="description" class="block text-sm font-medium text-light-text-secondary dark:text-dark-text-secondary mb-2">
                                    Description
                                </label>
                                <textarea
                                    id="description"
                                    v-model="settingsForm.description"
                                    rows="3"
                                    class="w-full px-4 py-2 bg-light-bg dark:bg-dark-bg border border-light-border dark:border-dark-border rounded-md text-light-text-primary dark:text-dark-text-primary focus:border-light-accent dark:focus:border-dark-accent focus:ring-2 focus:ring-light-accent/20 dark:focus:ring-dark-accent/20 focus:outline-none transition-colors"
                                    placeholder="A brief description of your workspace..."
                                ></textarea>
                                <div v-if="settingsForm.errors.description" class="mt-2 text-sm text-red-600 dark:text-red-400">
                                    {{ settingsForm.errors.description }}
                                </div>
                            </div>

                            <div class="flex items-center justify-end">
                                <button
                                    type="submit"
                                    :disabled="settingsForm.processing"
                                    class="px-4 py-2 bg-light-accent dark:bg-dark-accent text-white font-semibold rounded-md shadow-neon hover:shadow-neon-lg transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    <span v-if="settingsForm.processing">Saving...</span>
                                    <span v-else>Save Changes</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Workspace Owner Card -->
                <div class="bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded-lg shadow-lg">
                    <div class="px-6 py-5 border-b border-light-border dark:border-dark-border">
                        <h3 class="text-lg font-medium text-light-text-primary dark:text-dark-text-primary">
                            Workspace Owner
                        </h3>
                        <p class="mt-1 text-sm text-light-text-muted dark:text-dark-text-muted">
                            Information about the workspace owner.
                        </p>
                    </div>
                    <div class="px-6 py-5">
                        <div v-if="workspace.owner" class="flex items-center space-x-4">
                            <div v-if="workspace.owner.profile_photo_url" class="h-12 w-12 rounded-full overflow-hidden">
                                <img :src="workspace.owner.profile_photo_url" :alt="workspace.owner.name" class="w-full h-full object-cover">
                            </div>
                            <div v-else class="h-12 w-12 rounded-full bg-light-accent dark:bg-dark-accent flex items-center justify-center text-white text-lg font-semibold shadow-neon">
                                {{ workspace.owner.name.charAt(0).toUpperCase() }}
                            </div>
                            <div>
                                <div class="text-base font-medium text-light-text-primary dark:text-dark-text-primary">
                                    {{ workspace.owner.name }}
                                </div>
                                <div class="text-sm text-light-text-muted dark:text-dark-text-muted">
                                    {{ workspace.owner.email }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Workspace ID Card (for reference) -->
                <div class="mt-8 bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded-lg shadow-lg">
                    <div class="px-6 py-5 border-b border-light-border dark:border-dark-border">
                        <h3 class="text-lg font-medium text-light-text-primary dark:text-dark-text-primary">
                            Workspace Details
                        </h3>
                        <p class="mt-1 text-sm text-light-text-muted dark:text-dark-text-muted">
                            Technical information about this workspace.
                        </p>
                    </div>
                    <div class="px-6 py-5">
                        <dl class="grid grid-cols-1 gap-4">
                            <div>
                                <dt class="text-sm font-medium text-light-text-secondary dark:text-dark-text-secondary">
                                    Workspace ID
                                </dt>
                                <dd class="mt-1 text-sm text-light-text-primary dark:text-dark-text-primary font-mono bg-light-bg dark:bg-dark-bg px-3 py-2 rounded border border-light-border dark:border-dark-border">
                                    {{ workspace.id }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-light-text-secondary dark:text-dark-text-secondary">
                                    Created
                                </dt>
                                <dd class="mt-1 text-sm text-light-text-primary dark:text-dark-text-primary">
                                    {{ new Date(workspace.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </ConversationLayout>
</template>
