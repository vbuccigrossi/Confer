<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import ConversationLayout from '@/Layouts/ConversationLayout.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import BotConfigForm from '@/Components/BotConfigForm.vue';

const props = defineProps({
    workspace: Object,
    installations: Array,
    availableBots: Array,
});

// Modal states
const showInstallModal = ref(false);
const showConfigModal = ref(false);
const showTokenModal = ref(false);
const showUninstallModal = ref(false);
const showTokenSuccessModal = ref(false);

// Selected items
const selectedInstallation = ref(null);
const selectedBot = ref(null);
const createdToken = ref(null);

// Loading states
const isLoading = ref(false);
const errorMessage = ref('');
const configErrors = ref({});

// Install form state
const installForm = ref({
    method: 'registry', // 'registry' or 'manifest'
    bot_id: null,
    manifest_url: '',
    config: {},
});

// Token form state
const tokenForm = ref({
    name: '',
    scopes: ['chat:write', 'channels:read'],
    expires_in: 'never',
});

// Config form state
const configForm = ref({
    is_active: true,
    config: {},
});

// Stats
const stats = computed(() => {
    const active = props.installations.filter(i => i.is_active).length;
    const inactive = props.installations.length - active;
    const withTokens = props.installations.filter(i => i.has_active_token).length;
    return { total: props.installations.length, active, inactive, withTokens };
});

// Available scopes
const availableScopes = [
    { value: 'chat:write', label: 'Send messages', description: 'Post messages to channels' },
    { value: 'chat:read', label: 'Read messages', description: 'Read messages in channels' },
    { value: 'channels:read', label: 'Read channels', description: 'List and view channels' },
    { value: 'channels:write', label: 'Manage channels', description: 'Create and modify channels' },
    { value: 'users:read', label: 'Read users', description: 'View user profiles' },
    { value: 'files:write', label: 'Upload files', description: 'Upload and share files' },
    { value: 'reactions:write', label: 'Add reactions', description: 'Add emoji reactions' },
];

const openInstallModal = () => {
    installForm.value = {
        method: 'registry',
        bot_id: null,
        manifest_url: '',
        config: {},
    };
    errorMessage.value = '';
    configErrors.value = {};
    showInstallModal.value = true;
};

const installBot = async () => {
    isLoading.value = true;
    errorMessage.value = '';
    configErrors.value = {};

    try {
        let response;
        if (installForm.value.method === 'registry') {
            response = await window.axios.post(
                `/api/admin/workspaces/${props.workspace.id}/bots/install`,
                { bot_id: installForm.value.bot_id, config: installForm.value.config }
            );
        } else {
            response = await window.axios.post(
                `/api/admin/workspaces/${props.workspace.id}/bots/install-from-manifest`,
                { manifest_url: installForm.value.manifest_url, config: installForm.value.config }
            );
        }

        createdToken.value = response.data.token;
        showInstallModal.value = false;
        showTokenSuccessModal.value = true;
        router.reload({ only: ['installations', 'availableBots'] });
    } catch (error) {
        errorMessage.value = error.response?.data?.error || 'Failed to install bot';
        // Handle config validation errors
        if (error.response?.data?.config_errors) {
            configErrors.value = error.response.data.config_errors;
        }
    } finally {
        isLoading.value = false;
    }
};

const openConfigModal = (installation) => {
    selectedInstallation.value = installation;
    configForm.value = {
        is_active: installation.is_active,
        config: { ...installation.config },
    };
    errorMessage.value = '';
    configErrors.value = {};
    showConfigModal.value = true;
};

const saveConfig = async () => {
    isLoading.value = true;
    errorMessage.value = '';
    configErrors.value = {};

    try {
        await window.axios.patch(
            `/api/admin/workspaces/${props.workspace.id}/bots/${selectedInstallation.value.id}`,
            configForm.value
        );
        showConfigModal.value = false;
        router.reload({ only: ['installations'] });
    } catch (error) {
        errorMessage.value = error.response?.data?.error || 'Failed to update configuration';
        // Handle config validation errors
        if (error.response?.data?.config_errors) {
            configErrors.value = error.response.data.config_errors;
        }
    } finally {
        isLoading.value = false;
    }
};

const openTokenModal = (installation) => {
    selectedInstallation.value = installation;
    tokenForm.value = {
        name: '',
        scopes: ['chat:write', 'channels:read'],
        expires_in: 'never',
    };
    errorMessage.value = '';
    showTokenModal.value = true;
};

const generateToken = async () => {
    isLoading.value = true;
    errorMessage.value = '';

    try {
        const response = await window.axios.post(
            `/api/admin/workspaces/${props.workspace.id}/bots/${selectedInstallation.value.id}/tokens`,
            tokenForm.value
        );
        createdToken.value = response.data.token.plain_token;
        showTokenModal.value = false;
        showTokenSuccessModal.value = true;
        router.reload({ only: ['installations'] });
    } catch (error) {
        errorMessage.value = error.response?.data?.error || 'Failed to generate token';
    } finally {
        isLoading.value = false;
    }
};

const openUninstallModal = (installation) => {
    selectedInstallation.value = installation;
    showUninstallModal.value = true;
};

const uninstallBot = async () => {
    isLoading.value = true;
    try {
        await window.axios.delete(
            `/api/admin/workspaces/${props.workspace.id}/bots/${selectedInstallation.value.id}`
        );
        showUninstallModal.value = false;
        router.reload({ only: ['installations', 'availableBots'] });
    } catch (error) {
        console.error('Failed to uninstall bot:', error);
        alert('Failed to uninstall bot');
    } finally {
        isLoading.value = false;
    }
};

const toggleBotStatus = async (installation) => {
    try {
        await window.axios.patch(
            `/api/admin/workspaces/${props.workspace.id}/bots/${installation.id}`,
            { is_active: !installation.is_active }
        );
        router.reload({ only: ['installations'] });
    } catch (error) {
        console.error('Failed to toggle status:', error);
        alert('Failed to update bot status');
    }
};

const copyToClipboard = (text) => {
    navigator.clipboard.writeText(text);
};

const formatDate = (dateStr) => {
    if (!dateStr) return 'Never';
    const date = new Date(dateStr);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
};

const getStatusBadge = (installation) => {
    if (installation.is_active) {
        return { text: 'Active', class: 'bg-green-500/20 dark:bg-green-400/20 text-green-700 dark:text-green-400' };
    }
    return { text: 'Inactive', class: 'bg-light-text-muted/20 dark:bg-dark-text-muted/20 text-light-text-muted dark:text-dark-text-muted' };
};

// Get selected bot for install modal (from registry)
const selectedBotForInstall = computed(() => {
    if (!installForm.value.bot_id) return null;
    return props.availableBots.find(b => b.id === installForm.value.bot_id);
});

// Watch for bot selection changes to initialize config with defaults
watch(() => installForm.value.bot_id, (newBotId) => {
    if (newBotId) {
        const bot = props.availableBots.find(b => b.id === newBotId);
        if (bot?.config_schema?.fields) {
            // Initialize config with defaults from schema
            const defaults = {};
            for (const field of bot.config_schema.fields) {
                if (field.default !== undefined) {
                    defaults[field.name] = field.default;
                }
            }
            installForm.value.config = defaults;
        } else {
            installForm.value.config = {};
        }
    }
    configErrors.value = {};
});
</script>

<template>
    <ConversationLayout :title="`Admin - Bots - ${workspace.name}`">
        <template #sidebar>
            <div class="py-4 px-4">
                <h3 class="text-xs font-semibold text-light-text-muted dark:text-dark-text-muted uppercase tracking-wider mb-3">
                    Admin Panel
                </h3>
                <nav class="space-y-1">
                    <Link
                        :href="`/admin/workspaces/${workspace.id}/overview`"
                        class="flex items-center px-3 py-2 text-sm font-medium rounded-md text-light-text-secondary dark:text-dark-text-secondary hover:bg-light-bg dark:hover:bg-dark-bg hover:text-light-text-primary dark:hover:text-dark-text-primary transition-colors"
                    >
                        <svg class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                        Overview
                    </Link>
                    <Link
                        :href="`/admin/workspaces/${workspace.id}/members`"
                        class="flex items-center px-3 py-2 text-sm font-medium rounded-md text-light-text-secondary dark:text-dark-text-secondary hover:bg-light-bg dark:hover:bg-dark-bg hover:text-light-text-primary dark:hover:text-dark-text-primary transition-colors"
                    >
                        <svg class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        Members
                    </Link>
                    <Link
                        :href="`/admin/workspaces/${workspace.id}/invites`"
                        class="flex items-center px-3 py-2 text-sm font-medium rounded-md text-light-text-secondary dark:text-dark-text-secondary hover:bg-light-bg dark:hover:bg-dark-bg hover:text-light-text-primary dark:hover:text-dark-text-primary transition-colors"
                    >
                        <svg class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        Invites
                    </Link>
                    <Link
                        :href="`/admin/workspaces/${workspace.id}/bots`"
                        class="flex items-center px-3 py-2 text-sm font-medium rounded-md bg-light-accent/10 dark:bg-dark-accent/10 text-light-accent dark:text-dark-accent border border-light-accent dark:border-dark-accent"
                    >
                        <svg class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        Bots
                    </Link>
                    <Link
                        :href="`/admin/workspaces/${workspace.id}/email`"
                        class="flex items-center px-3 py-2 text-sm font-medium rounded-md text-light-text-secondary dark:text-dark-text-secondary hover:bg-light-bg dark:hover:bg-dark-bg hover:text-light-text-primary dark:hover:text-dark-text-primary transition-colors"
                    >
                        <svg class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        Email
                    </Link>
                    <Link
                        :href="`/admin/workspaces/${workspace.id}/settings`"
                        class="flex items-center px-3 py-2 text-sm font-medium rounded-md text-light-text-secondary dark:text-dark-text-secondary hover:bg-light-bg dark:hover:bg-dark-bg hover:text-light-text-primary dark:hover:text-dark-text-primary transition-colors"
                    >
                        <svg class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Settings
                    </Link>
                </nav>

                <div class="mt-8">
                    <Link
                        :href="`/conversations`"
                        class="flex items-center px-3 py-2 text-sm font-medium rounded-md text-light-text-muted dark:text-dark-text-muted hover:text-light-accent dark:hover:text-dark-accent transition-colors"
                    >
                        <svg class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        Back to Conversations
                    </Link>
                </div>
            </div>
        </template>

        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <svg class="h-6 w-6 text-light-accent dark:text-dark-accent mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    <h1 class="text-xl font-bold text-light-text-primary dark:text-dark-text-primary">
                        Manage Bots
                    </h1>
                </div>
                <PrimaryButton @click="openInstallModal">
                    <svg class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Install Bot
                </PrimaryButton>
            </div>
        </template>

        <!-- Main Content -->
        <div class="h-full overflow-y-auto bg-light-bg dark:bg-dark-bg p-6">
            <!-- Stats Cards -->
            <div class="grid grid-cols-4 gap-4 mb-6">
                <div class="bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded-lg p-4">
                    <div class="text-2xl font-bold text-light-text-primary dark:text-dark-text-primary">{{ stats.total }}</div>
                    <div class="text-sm text-light-text-muted dark:text-dark-text-muted">Installed Bots</div>
                </div>
                <div class="bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded-lg p-4">
                    <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ stats.active }}</div>
                    <div class="text-sm text-light-text-muted dark:text-dark-text-muted">Active</div>
                </div>
                <div class="bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded-lg p-4">
                    <div class="text-2xl font-bold text-light-text-primary dark:text-dark-text-primary">{{ stats.withTokens }}</div>
                    <div class="text-sm text-light-text-muted dark:text-dark-text-muted">With Active Tokens</div>
                </div>
                <div class="bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded-lg p-4">
                    <div class="text-2xl font-bold text-light-text-primary dark:text-dark-text-primary">{{ availableBots.length }}</div>
                    <div class="text-sm text-light-text-muted dark:text-dark-text-muted">Available to Install</div>
                </div>
            </div>

            <!-- Installed Bots Table -->
            <div class="bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded-lg shadow-lg">
                <div class="px-6 py-4 border-b border-light-border dark:border-dark-border">
                    <h2 class="text-lg font-semibold text-light-text-primary dark:text-dark-text-primary">
                        Installed Bots ({{ installations.length }})
                    </h2>
                </div>

                <div v-if="installations.length === 0" class="p-8 text-center text-light-text-muted dark:text-dark-text-muted">
                    <svg class="h-12 w-12 mx-auto mb-4 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    <p>No bots installed</p>
                    <button @click="openInstallModal" class="mt-4 text-light-accent dark:text-dark-accent hover:underline">
                        Install your first bot
                    </button>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-light-border dark:divide-dark-border">
                        <thead class="bg-light-bg dark:bg-dark-bg">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-light-text-muted dark:text-dark-text-muted uppercase tracking-wider">
                                    Bot
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-light-text-muted dark:text-dark-text-muted uppercase tracking-wider">
                                    Status
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-light-text-muted dark:text-dark-text-muted uppercase tracking-wider">
                                    Tokens
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-light-text-muted dark:text-dark-text-muted uppercase tracking-wider">
                                    Commands
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-light-text-muted dark:text-dark-text-muted uppercase tracking-wider">
                                    Installed
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-light-text-muted dark:text-dark-text-muted uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-light-border dark:divide-dark-border">
                            <tr v-for="installation in installations" :key="installation.id" class="hover:bg-light-bg dark:hover:bg-dark-bg transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="h-10 w-10 rounded-full bg-light-accent/20 dark:bg-dark-accent/20 flex items-center justify-center mr-3">
                                            <img
                                                v-if="installation.bot.avatar_url"
                                                :src="installation.bot.avatar_url"
                                                class="h-10 w-10 rounded-full"
                                                :alt="installation.bot.name"
                                            />
                                            <svg v-else class="h-5 w-5 text-light-accent dark:text-dark-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-light-text-primary dark:text-dark-text-primary">
                                                {{ installation.bot.name }}
                                            </div>
                                            <div class="text-xs text-light-text-muted dark:text-dark-text-muted">
                                                {{ installation.bot.description || 'No description' }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <button
                                        @click="toggleBotStatus(installation)"
                                        :class="[
                                            'px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full cursor-pointer hover:opacity-80 transition-opacity',
                                            getStatusBadge(installation).class
                                        ]"
                                    >
                                        {{ getStatusBadge(installation).text }}
                                    </button>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-light-text-secondary dark:text-dark-text-secondary">
                                    <span :class="{ 'text-green-600 dark:text-green-400': installation.has_active_token, 'text-light-text-muted dark:text-dark-text-muted': !installation.has_active_token }">
                                        {{ installation.tokens_count }} token{{ installation.tokens_count !== 1 ? 's' : '' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-light-text-secondary dark:text-dark-text-secondary">
                                    {{ installation.slash_commands.length }} command{{ installation.slash_commands.length !== 1 ? 's' : '' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-light-text-secondary dark:text-dark-text-secondary">
                                    <div>{{ formatDate(installation.installed_at) }}</div>
                                    <div class="text-xs text-light-text-muted dark:text-dark-text-muted">
                                        by {{ installation.installer?.name || 'Unknown' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end space-x-2">
                                        <button
                                            @click="openTokenModal(installation)"
                                            class="text-light-accent dark:text-dark-accent hover:text-light-accent-hover dark:hover:text-dark-accent-hover transition-colors"
                                            title="Generate token"
                                        >
                                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                            </svg>
                                        </button>
                                        <button
                                            @click="openConfigModal(installation)"
                                            class="text-light-primary dark:text-dark-primary hover:text-light-primary-hover dark:hover:text-dark-primary-hover transition-colors"
                                            title="Configure"
                                        >
                                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                        </button>
                                        <button
                                            @click="openUninstallModal(installation)"
                                            class="text-light-danger dark:text-dark-danger hover:opacity-75 transition-opacity"
                                            title="Uninstall"
                                        >
                                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </ConversationLayout>

    <!-- Install Bot Modal -->
    <Modal :show="showInstallModal" @close="showInstallModal = false" max-width="lg">
        <div class="p-6">
            <h2 class="text-lg font-medium text-light-text-primary dark:text-dark-text-primary mb-4">
                Install Bot
            </h2>

            <form @submit.prevent="installBot">
                <!-- Install Method -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-light-text-primary dark:text-dark-text-primary mb-2">
                        Installation Method
                    </label>
                    <div class="flex space-x-4">
                        <label class="flex items-center">
                            <input
                                type="radio"
                                v-model="installForm.method"
                                value="registry"
                                class="form-radio text-light-accent dark:text-dark-accent"
                            />
                            <span class="ml-2 text-sm text-light-text-primary dark:text-dark-text-primary">From Registry</span>
                        </label>
                        <label class="flex items-center">
                            <input
                                type="radio"
                                v-model="installForm.method"
                                value="manifest"
                                class="form-radio text-light-accent dark:text-dark-accent"
                            />
                            <span class="ml-2 text-sm text-light-text-primary dark:text-dark-text-primary">From Manifest URL</span>
                        </label>
                    </div>
                    <p class="mt-1 text-xs text-light-text-muted dark:text-dark-text-muted">
                        {{ installForm.method === 'registry' ? 'Choose from pre-registered bots' : 'Install a third-party bot via manifest URL' }}
                    </p>
                </div>

                <!-- Bot Selection (for registry) -->
                <div v-if="installForm.method === 'registry'" class="mb-4">
                    <label class="block text-sm font-medium text-light-text-primary dark:text-dark-text-primary mb-2">
                        Select Bot
                    </label>
                    <div v-if="availableBots.length === 0" class="p-4 bg-light-bg dark:bg-dark-bg rounded-lg text-center text-light-text-muted dark:text-dark-text-muted">
                        No bots available to install. All bots are already installed or none are registered.
                    </div>
                    <div v-else class="space-y-2 max-h-60 overflow-y-auto">
                        <label
                            v-for="bot in availableBots"
                            :key="bot.id"
                            :class="[
                                'flex items-center p-3 border rounded-lg cursor-pointer transition-colors',
                                installForm.bot_id === bot.id
                                    ? 'border-light-accent dark:border-dark-accent bg-light-accent/10 dark:bg-dark-accent/10'
                                    : 'border-light-border dark:border-dark-border hover:bg-light-bg dark:hover:bg-dark-bg'
                            ]"
                        >
                            <input
                                type="radio"
                                v-model="installForm.bot_id"
                                :value="bot.id"
                                class="form-radio text-light-accent dark:text-dark-accent"
                            />
                            <div class="ml-3 flex-1">
                                <div class="flex items-center">
                                    <span class="text-sm font-medium text-light-text-primary dark:text-dark-text-primary">
                                        {{ bot.name }}
                                    </span>
                                    <span
                                        v-if="bot.requires_configuration"
                                        class="ml-2 px-1.5 py-0.5 text-xs rounded bg-amber-500/20 text-amber-700 dark:text-amber-400"
                                    >
                                        Requires config
                                    </span>
                                </div>
                                <div class="text-xs text-light-text-muted dark:text-dark-text-muted">
                                    {{ bot.description || 'No description' }}
                                </div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Configuration for selected bot (registry) -->
                <div v-if="installForm.method === 'registry' && selectedBotForInstall?.config_schema?.fields?.length" class="mb-4">
                    <label class="block text-sm font-medium text-light-text-primary dark:text-dark-text-primary mb-2">
                        Configuration
                    </label>
                    <div class="p-4 bg-light-bg dark:bg-dark-bg rounded-lg">
                        <BotConfigForm
                            :schema="selectedBotForInstall.config_schema"
                            v-model="installForm.config"
                            :errors="configErrors"
                            :disabled="isLoading"
                        />
                    </div>
                </div>

                <!-- Manifest URL (for manifest) -->
                <div v-if="installForm.method === 'manifest'" class="mb-4">
                    <label class="block text-sm font-medium text-light-text-primary dark:text-dark-text-primary mb-2">
                        Manifest URL
                    </label>
                    <input
                        type="url"
                        v-model="installForm.manifest_url"
                        required
                        class="w-full px-3 py-2 bg-light-bg dark:bg-dark-bg border border-light-border dark:border-dark-border rounded-lg text-sm text-light-text-primary dark:text-dark-text-primary placeholder-light-text-muted dark:placeholder-dark-text-muted focus:border-light-accent dark:focus:border-dark-accent focus:ring-2 focus:ring-light-accent/20 dark:focus:ring-dark-accent/20"
                        placeholder="https://example.com/bot-manifest.json"
                    />
                    <p class="mt-1 text-xs text-light-text-muted dark:text-dark-text-muted">
                        Enter the URL to a bot manifest JSON file
                    </p>
                </div>

                <!-- Error Message -->
                <div v-if="errorMessage" class="mb-4 p-3 bg-light-danger/10 dark:bg-dark-danger/10 border border-light-danger dark:border-dark-danger rounded-lg">
                    <p class="text-sm text-light-danger dark:text-dark-danger">{{ errorMessage }}</p>
                </div>

                <div class="flex justify-end space-x-3">
                    <button
                        type="button"
                        @click="showInstallModal = false"
                        class="px-4 py-2 bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded-md text-sm font-medium text-light-text-primary dark:text-dark-text-primary hover:bg-light-bg dark:hover:bg-dark-bg transition-colors"
                    >
                        Cancel
                    </button>
                    <PrimaryButton
                        :disabled="isLoading || (installForm.method === 'registry' && !installForm.bot_id) || (installForm.method === 'manifest' && !installForm.manifest_url)"
                    >
                        {{ isLoading ? 'Installing...' : 'Install Bot' }}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </Modal>

    <!-- Generate Token Modal -->
    <Modal :show="showTokenModal" @close="showTokenModal = false" max-width="lg">
        <div class="p-6">
            <h2 class="text-lg font-medium text-light-text-primary dark:text-dark-text-primary mb-4">
                Generate Bot Token
            </h2>
            <p class="text-sm text-light-text-muted dark:text-dark-text-muted mb-4">
                Generate a new API token for <strong class="text-light-text-primary dark:text-dark-text-primary">{{ selectedInstallation?.bot?.name }}</strong>
            </p>

            <form @submit.prevent="generateToken">
                <!-- Token Name -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-light-text-primary dark:text-dark-text-primary mb-2">
                        Token Name
                    </label>
                    <input
                        type="text"
                        v-model="tokenForm.name"
                        required
                        class="w-full px-3 py-2 bg-light-bg dark:bg-dark-bg border border-light-border dark:border-dark-border rounded-lg text-sm text-light-text-primary dark:text-dark-text-primary placeholder-light-text-muted dark:placeholder-dark-text-muted focus:border-light-accent dark:focus:border-dark-accent focus:ring-2 focus:ring-light-accent/20 dark:focus:ring-dark-accent/20"
                        placeholder="Production Token"
                    />
                </div>

                <!-- Scopes -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-light-text-primary dark:text-dark-text-primary mb-2">
                        Permissions (Scopes)
                    </label>
                    <div class="space-y-2 max-h-48 overflow-y-auto">
                        <label
                            v-for="scope in availableScopes"
                            :key="scope.value"
                            class="flex items-start p-2 hover:bg-light-bg dark:hover:bg-dark-bg rounded cursor-pointer"
                        >
                            <input
                                type="checkbox"
                                v-model="tokenForm.scopes"
                                :value="scope.value"
                                class="form-checkbox text-light-accent dark:text-dark-accent mt-0.5"
                            />
                            <div class="ml-3">
                                <div class="text-sm font-medium text-light-text-primary dark:text-dark-text-primary">
                                    {{ scope.label }}
                                </div>
                                <div class="text-xs text-light-text-muted dark:text-dark-text-muted">
                                    {{ scope.description }}
                                </div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Expiration -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-light-text-primary dark:text-dark-text-primary mb-2">
                        Expires In
                    </label>
                    <select
                        v-model="tokenForm.expires_in"
                        class="w-full px-3 py-2 bg-light-bg dark:bg-dark-bg border border-light-border dark:border-dark-border rounded-lg text-sm text-light-text-primary dark:text-dark-text-primary focus:border-light-accent dark:focus:border-dark-accent"
                    >
                        <option value="7d">7 Days</option>
                        <option value="30d">30 Days</option>
                        <option value="90d">90 Days</option>
                        <option value="1y">1 Year</option>
                        <option value="never">Never</option>
                    </select>
                </div>

                <!-- Error Message -->
                <div v-if="errorMessage" class="mb-4 p-3 bg-light-danger/10 dark:bg-dark-danger/10 border border-light-danger dark:border-dark-danger rounded-lg">
                    <p class="text-sm text-light-danger dark:text-dark-danger">{{ errorMessage }}</p>
                </div>

                <div class="flex justify-end space-x-3">
                    <button
                        type="button"
                        @click="showTokenModal = false"
                        class="px-4 py-2 bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded-md text-sm font-medium text-light-text-primary dark:text-dark-text-primary hover:bg-light-bg dark:hover:bg-dark-bg transition-colors"
                    >
                        Cancel
                    </button>
                    <PrimaryButton :disabled="isLoading || !tokenForm.name || tokenForm.scopes.length === 0">
                        {{ isLoading ? 'Generating...' : 'Generate Token' }}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </Modal>

    <!-- Token Success Modal -->
    <Modal :show="showTokenSuccessModal" @close="showTokenSuccessModal = false" max-width="md">
        <div class="p-6">
            <div class="text-center mb-4">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-500/20 dark:bg-green-400/20">
                    <svg class="h-6 w-6 text-green-700 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <h2 class="mt-4 text-lg font-medium text-light-text-primary dark:text-dark-text-primary">
                    Token Generated!
                </h2>
            </div>

            <div class="bg-light-bg dark:bg-dark-bg rounded-lg p-4 mb-4">
                <p class="text-sm text-light-text-muted dark:text-dark-text-muted mb-2 text-center">
                    Copy this token now. You won't be able to see it again!
                </p>
                <code class="block p-3 bg-light-surface dark:bg-dark-surface rounded text-xs break-all text-light-text-primary dark:text-dark-text-primary font-mono">
                    {{ createdToken }}
                </code>
            </div>

            <div class="p-3 bg-amber-500/10 dark:bg-amber-400/10 border border-amber-500 dark:border-amber-400 rounded-lg mb-4">
                <p class="text-sm text-amber-700 dark:text-amber-400">
                    <strong>Important:</strong> Store this token securely. It provides API access to your workspace.
                </p>
            </div>

            <div class="flex justify-center space-x-3">
                <button
                    @click="copyToClipboard(createdToken)"
                    class="px-4 py-2 bg-light-accent dark:bg-dark-accent text-white rounded-md text-sm font-medium hover:bg-light-accent-hover dark:hover:bg-dark-accent-hover transition-colors"
                >
                    Copy to Clipboard
                </button>
                <button
                    @click="showTokenSuccessModal = false"
                    class="px-4 py-2 bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded-md text-sm font-medium text-light-text-primary dark:text-dark-text-primary hover:bg-light-bg dark:hover:bg-dark-bg transition-colors"
                >
                    Done
                </button>
            </div>
        </div>
    </Modal>

    <!-- Configure Bot Modal -->
    <Modal :show="showConfigModal" @close="showConfigModal = false" max-width="md">
        <div class="p-6">
            <h2 class="text-lg font-medium text-light-text-primary dark:text-dark-text-primary mb-4">
                Configure Bot
            </h2>
            <p class="text-sm text-light-text-muted dark:text-dark-text-muted mb-4">
                Configure settings for <strong class="text-light-text-primary dark:text-dark-text-primary">{{ selectedInstallation?.bot?.name }}</strong>
            </p>

            <form @submit.prevent="saveConfig">
                <!-- Active Status -->
                <div class="mb-4">
                    <label class="flex items-center justify-between p-3 bg-light-bg dark:bg-dark-bg rounded-lg">
                        <div>
                            <div class="text-sm font-medium text-light-text-primary dark:text-dark-text-primary">
                                Active
                            </div>
                            <div class="text-xs text-light-text-muted dark:text-dark-text-muted">
                                Enable or disable this bot
                            </div>
                        </div>
                        <button
                            type="button"
                            @click="configForm.is_active = !configForm.is_active"
                            :class="[
                                'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none',
                                configForm.is_active ? 'bg-light-accent dark:bg-dark-accent' : 'bg-light-border dark:bg-dark-border'
                            ]"
                        >
                            <span
                                :class="[
                                    'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                                    configForm.is_active ? 'translate-x-5' : 'translate-x-0'
                                ]"
                            />
                        </button>
                    </label>
                </div>

                <!-- Bot Configuration -->
                <div v-if="selectedInstallation?.bot?.config_schema?.fields?.length" class="mb-4">
                    <label class="block text-sm font-medium text-light-text-primary dark:text-dark-text-primary mb-2">
                        Bot Settings
                    </label>
                    <div class="p-4 bg-light-bg dark:bg-dark-bg rounded-lg">
                        <BotConfigForm
                            :schema="selectedInstallation.bot.config_schema"
                            v-model="configForm.config"
                            :errors="configErrors"
                            :disabled="isLoading"
                        />
                    </div>
                </div>

                <!-- Slash Commands (read-only) -->
                <div v-if="selectedInstallation?.slash_commands?.length" class="mb-4">
                    <label class="block text-sm font-medium text-light-text-primary dark:text-dark-text-primary mb-2">
                        Slash Commands
                    </label>
                    <div class="space-y-2">
                        <div
                            v-for="cmd in selectedInstallation.slash_commands"
                            :key="cmd.id"
                            class="flex items-center justify-between p-2 bg-light-bg dark:bg-dark-bg rounded"
                        >
                            <div>
                                <code class="text-sm text-light-accent dark:text-dark-accent">/{{ cmd.command }}</code>
                                <span class="ml-2 text-xs text-light-text-muted dark:text-dark-text-muted">{{ cmd.description }}</span>
                            </div>
                            <span :class="[
                                'px-2 py-0.5 text-xs rounded',
                                cmd.is_active
                                    ? 'bg-green-500/20 text-green-700 dark:text-green-400'
                                    : 'bg-light-text-muted/20 text-light-text-muted dark:text-dark-text-muted'
                            ]">
                                {{ cmd.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Error Message -->
                <div v-if="errorMessage" class="mb-4 p-3 bg-light-danger/10 dark:bg-dark-danger/10 border border-light-danger dark:border-dark-danger rounded-lg">
                    <p class="text-sm text-light-danger dark:text-dark-danger">{{ errorMessage }}</p>
                </div>

                <div class="flex justify-end space-x-3">
                    <button
                        type="button"
                        @click="showConfigModal = false"
                        class="px-4 py-2 bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded-md text-sm font-medium text-light-text-primary dark:text-dark-text-primary hover:bg-light-bg dark:hover:bg-dark-bg transition-colors"
                    >
                        Cancel
                    </button>
                    <PrimaryButton :disabled="isLoading">
                        {{ isLoading ? 'Saving...' : 'Save Changes' }}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </Modal>

    <!-- Uninstall Bot Modal -->
    <Modal :show="showUninstallModal" @close="showUninstallModal = false">
        <div class="p-6">
            <h2 class="text-lg font-medium text-light-text-primary dark:text-dark-text-primary mb-4">
                Uninstall Bot?
            </h2>

            <p class="text-sm text-light-text-secondary dark:text-dark-text-secondary mb-4">
                Are you sure you want to uninstall <strong>{{ selectedInstallation?.bot?.name }}</strong>?
            </p>

            <div class="p-3 bg-light-danger/10 dark:bg-dark-danger/10 border border-light-danger dark:border-dark-danger rounded-lg mb-6">
                <p class="text-sm text-light-danger dark:text-dark-danger">
                    This will revoke all tokens and remove all slash commands for this bot. This action cannot be undone.
                </p>
            </div>

            <div class="flex justify-end space-x-3">
                <button
                    type="button"
                    @click="showUninstallModal = false"
                    class="px-4 py-2 bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded-md text-sm font-medium text-light-text-primary dark:text-dark-text-primary hover:bg-light-bg dark:hover:bg-dark-bg transition-colors"
                >
                    Cancel
                </button>
                <button
                    @click="uninstallBot"
                    :disabled="isLoading"
                    class="px-4 py-2 bg-light-danger dark:bg-dark-danger text-white rounded-md text-sm font-medium hover:opacity-90 transition-opacity disabled:opacity-50"
                >
                    {{ isLoading ? 'Uninstalling...' : 'Uninstall Bot' }}
                </button>
            </div>
        </div>
    </Modal>
</template>

<style scoped>
:deep(select option) {
    background-color: var(--light-surface);
    color: var(--light-text-primary);
}

.dark :deep(select option) {
    background-color: var(--dark-surface);
    color: var(--dark-text-primary);
}

/* Radio button styling for dark mode */
input[type="radio"] {
    accent-color: var(--light-accent);
}

.dark input[type="radio"] {
    accent-color: var(--dark-accent);
}

/* Checkbox styling for dark mode */
input[type="checkbox"] {
    accent-color: var(--light-accent);
}

.dark input[type="checkbox"] {
    accent-color: var(--dark-accent);
}
</style>
