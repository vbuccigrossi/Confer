<script setup>
import { ref, watch, computed } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import ConversationLayout from '@/Layouts/ConversationLayout.vue';

const props = defineProps({
    workspace: Object,
    settings: Object,
    presets: Object,
});

const page = usePage();
const successMessage = computed(() => page.props.flash?.success);

const selectedPreset = ref('custom');

const emailForm = useForm({
    mail_driver: props.settings.mail_driver || 'smtp',
    mail_host: props.settings.mail_host || '',
    mail_port: props.settings.mail_port || '587',
    mail_username: props.settings.mail_username || '',
    mail_password: '',
    mail_encryption: props.settings.mail_encryption || 'tls',
    mail_from_address: props.settings.mail_from_address || '',
    mail_from_name: props.settings.mail_from_name || '',
});

const testForm = useForm({
    test_email: '',
});

// Apply preset when selected
const applyPreset = (presetKey) => {
    selectedPreset.value = presetKey;
    const preset = props.presets[presetKey];
    if (preset) {
        emailForm.mail_host = preset.host;
        emailForm.mail_port = preset.port;
        emailForm.mail_encryption = preset.encryption;
    }
};

// Detect current preset on load
const detectCurrentPreset = () => {
    for (const [key, preset] of Object.entries(props.presets)) {
        if (preset.host === props.settings.mail_host &&
            String(preset.port) === String(props.settings.mail_port) &&
            preset.encryption === props.settings.mail_encryption) {
            selectedPreset.value = key;
            return;
        }
    }
    selectedPreset.value = 'custom';
};

detectCurrentPreset();

const updateSettings = () => {
    emailForm.put(`/admin/workspaces/${props.workspace.id}/email`, {
        preserveScroll: true,
        onSuccess: () => {
            emailForm.mail_password = '';
        },
    });
};

const sendTestEmail = () => {
    testForm.post(`/admin/workspaces/${props.workspace.id}/email/test`, {
        preserveScroll: true,
    });
};

const currentPresetNote = computed(() => {
    return props.presets[selectedPreset.value]?.note || '';
});
</script>

<template>
    <ConversationLayout title="Email Settings">
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
                       class="flex items-center px-3 py-2 text-sm font-medium rounded-md bg-light-accent/10 dark:bg-dark-accent/10 text-light-accent dark:text-dark-accent border-l-2 border-light-accent dark:border-dark-accent transition-colors">
                        <svg class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        Email
                    </a>
                    <a :href="`/admin/workspaces/${workspace.id}/settings`"
                       class="flex items-center px-3 py-2 text-sm font-medium rounded-md text-light-text-secondary dark:text-dark-text-secondary hover:bg-light-bg dark:hover:bg-dark-bg hover:text-light-text-primary dark:hover:text-dark-accent transition-colors">
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
                    Email Configuration
                </h2>
            </div>
        </template>

        <div class="py-12 px-4 sm:px-6 lg:px-8 overflow-y-auto h-full">
            <div class="max-w-4xl mx-auto">
                <!-- Success Message -->
                <div v-if="successMessage" class="mb-6 p-4 bg-green-100 dark:bg-green-900/30 border border-green-200 dark:border-green-800 rounded-lg">
                    <div class="flex items-center">
                        <svg class="h-5 w-5 text-green-500 dark:text-green-400 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span class="text-green-700 dark:text-green-300">{{ successMessage }}</span>
                    </div>
                </div>

                <!-- Email Provider Presets -->
                <div class="bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded-lg shadow-lg mb-8">
                    <div class="px-6 py-5 border-b border-light-border dark:border-dark-border">
                        <h3 class="text-lg font-medium text-light-text-primary dark:text-dark-text-primary">
                            Email Provider
                        </h3>
                        <p class="mt-1 text-sm text-light-text-muted dark:text-dark-text-muted">
                            Select a preset or configure a custom SMTP server.
                        </p>
                    </div>
                    <div class="px-6 py-5">
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                            <button
                                v-for="(preset, key) in presets"
                                :key="key"
                                @click="applyPreset(key)"
                                :class="[
                                    'px-4 py-3 rounded-lg border-2 text-left transition-all',
                                    selectedPreset === key
                                        ? 'border-light-accent dark:border-dark-accent bg-light-accent/10 dark:bg-dark-accent/10'
                                        : 'border-light-border dark:border-dark-border hover:border-light-accent/50 dark:hover:border-dark-accent/50'
                                ]"
                            >
                                <div class="font-medium text-light-text-primary dark:text-dark-text-primary text-sm">
                                    {{ preset.name }}
                                </div>
                            </button>
                        </div>
                        <div v-if="currentPresetNote" class="mt-4 p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
                            <div class="flex items-start">
                                <svg class="h-5 w-5 text-amber-500 dark:text-amber-400 mr-2 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span class="text-sm text-amber-700 dark:text-amber-300">{{ currentPresetNote }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SMTP Configuration Card -->
                <div class="bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded-lg shadow-lg mb-8">
                    <div class="px-6 py-5 border-b border-light-border dark:border-dark-border">
                        <h3 class="text-lg font-medium text-light-text-primary dark:text-dark-text-primary">
                            SMTP Settings
                        </h3>
                        <p class="mt-1 text-sm text-light-text-muted dark:text-dark-text-muted">
                            Configure your outgoing mail server.
                        </p>
                    </div>
                    <div class="px-6 py-5">
                        <form @submit.prevent="updateSettings" class="space-y-6">
                            <!-- Mail Driver -->
                            <div>
                                <label for="mail_driver" class="block text-sm font-medium text-light-text-secondary dark:text-dark-text-secondary mb-2">
                                    Mail Driver
                                </label>
                                <select
                                    id="mail_driver"
                                    v-model="emailForm.mail_driver"
                                    class="w-full px-4 py-2 bg-light-bg dark:bg-dark-bg border border-light-border dark:border-dark-border rounded-md text-light-text-primary dark:text-dark-text-primary focus:border-light-accent dark:focus:border-dark-accent focus:ring-2 focus:ring-light-accent/20 dark:focus:ring-dark-accent/20 focus:outline-none transition-colors"
                                >
                                    <option value="smtp">SMTP</option>
                                    <option value="sendmail">Sendmail</option>
                                    <option value="log">Log (for testing)</option>
                                </select>
                                <div v-if="emailForm.errors.mail_driver" class="mt-2 text-sm text-red-600 dark:text-red-400">
                                    {{ emailForm.errors.mail_driver }}
                                </div>
                            </div>

                            <div v-if="emailForm.mail_driver === 'smtp'" class="space-y-6">
                                <!-- Host and Port -->
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div class="md:col-span-2">
                                        <label for="mail_host" class="block text-sm font-medium text-light-text-secondary dark:text-dark-text-secondary mb-2">
                                            SMTP Host
                                        </label>
                                        <input
                                            id="mail_host"
                                            v-model="emailForm.mail_host"
                                            type="text"
                                            placeholder="smtp.example.com"
                                            class="w-full px-4 py-2 bg-light-bg dark:bg-dark-bg border border-light-border dark:border-dark-border rounded-md text-light-text-primary dark:text-dark-text-primary focus:border-light-accent dark:focus:border-dark-accent focus:ring-2 focus:ring-light-accent/20 dark:focus:ring-dark-accent/20 focus:outline-none transition-colors"
                                        />
                                        <div v-if="emailForm.errors.mail_host" class="mt-2 text-sm text-red-600 dark:text-red-400">
                                            {{ emailForm.errors.mail_host }}
                                        </div>
                                    </div>
                                    <div>
                                        <label for="mail_port" class="block text-sm font-medium text-light-text-secondary dark:text-dark-text-secondary mb-2">
                                            Port
                                        </label>
                                        <input
                                            id="mail_port"
                                            v-model="emailForm.mail_port"
                                            type="number"
                                            placeholder="587"
                                            class="w-full px-4 py-2 bg-light-bg dark:bg-dark-bg border border-light-border dark:border-dark-border rounded-md text-light-text-primary dark:text-dark-text-primary focus:border-light-accent dark:focus:border-dark-accent focus:ring-2 focus:ring-light-accent/20 dark:focus:ring-dark-accent/20 focus:outline-none transition-colors"
                                        />
                                        <div v-if="emailForm.errors.mail_port" class="mt-2 text-sm text-red-600 dark:text-red-400">
                                            {{ emailForm.errors.mail_port }}
                                        </div>
                                    </div>
                                </div>

                                <!-- Encryption -->
                                <div>
                                    <label for="mail_encryption" class="block text-sm font-medium text-light-text-secondary dark:text-dark-text-secondary mb-2">
                                        Encryption
                                    </label>
                                    <select
                                        id="mail_encryption"
                                        v-model="emailForm.mail_encryption"
                                        class="w-full px-4 py-2 bg-light-bg dark:bg-dark-bg border border-light-border dark:border-dark-border rounded-md text-light-text-primary dark:text-dark-text-primary focus:border-light-accent dark:focus:border-dark-accent focus:ring-2 focus:ring-light-accent/20 dark:focus:ring-dark-accent/20 focus:outline-none transition-colors"
                                    >
                                        <option value="tls">TLS (Recommended)</option>
                                        <option value="ssl">SSL</option>
                                        <option value="null">None</option>
                                    </select>
                                    <div v-if="emailForm.errors.mail_encryption" class="mt-2 text-sm text-red-600 dark:text-red-400">
                                        {{ emailForm.errors.mail_encryption }}
                                    </div>
                                </div>

                                <!-- Username and Password -->
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="mail_username" class="block text-sm font-medium text-light-text-secondary dark:text-dark-text-secondary mb-2">
                                            Username
                                        </label>
                                        <input
                                            id="mail_username"
                                            v-model="emailForm.mail_username"
                                            type="text"
                                            placeholder="your-email@example.com"
                                            class="w-full px-4 py-2 bg-light-bg dark:bg-dark-bg border border-light-border dark:border-dark-border rounded-md text-light-text-primary dark:text-dark-text-primary focus:border-light-accent dark:focus:border-dark-accent focus:ring-2 focus:ring-light-accent/20 dark:focus:ring-dark-accent/20 focus:outline-none transition-colors"
                                        />
                                        <div v-if="emailForm.errors.mail_username" class="mt-2 text-sm text-red-600 dark:text-red-400">
                                            {{ emailForm.errors.mail_username }}
                                        </div>
                                    </div>
                                    <div>
                                        <label for="mail_password" class="block text-sm font-medium text-light-text-secondary dark:text-dark-text-secondary mb-2">
                                            Password
                                        </label>
                                        <input
                                            id="mail_password"
                                            v-model="emailForm.mail_password"
                                            type="password"
                                            :placeholder="settings.mail_password_set ? '••••••••' : 'Enter password'"
                                            class="w-full px-4 py-2 bg-light-bg dark:bg-dark-bg border border-light-border dark:border-dark-border rounded-md text-light-text-primary dark:text-dark-text-primary focus:border-light-accent dark:focus:border-dark-accent focus:ring-2 focus:ring-light-accent/20 dark:focus:ring-dark-accent/20 focus:outline-none transition-colors"
                                        />
                                        <p v-if="settings.mail_password_set" class="mt-1 text-xs text-light-text-muted dark:text-dark-text-muted">
                                            Leave blank to keep current password
                                        </p>
                                        <div v-if="emailForm.errors.mail_password" class="mt-2 text-sm text-red-600 dark:text-red-400">
                                            {{ emailForm.errors.mail_password }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- From Address and Name -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-4 border-t border-light-border dark:border-dark-border">
                                <div>
                                    <label for="mail_from_address" class="block text-sm font-medium text-light-text-secondary dark:text-dark-text-secondary mb-2">
                                        From Address
                                    </label>
                                    <input
                                        id="mail_from_address"
                                        v-model="emailForm.mail_from_address"
                                        type="email"
                                        placeholder="noreply@example.com"
                                        class="w-full px-4 py-2 bg-light-bg dark:bg-dark-bg border border-light-border dark:border-dark-border rounded-md text-light-text-primary dark:text-dark-text-primary focus:border-light-accent dark:focus:border-dark-accent focus:ring-2 focus:ring-light-accent/20 dark:focus:ring-dark-accent/20 focus:outline-none transition-colors"
                                        required
                                    />
                                    <div v-if="emailForm.errors.mail_from_address" class="mt-2 text-sm text-red-600 dark:text-red-400">
                                        {{ emailForm.errors.mail_from_address }}
                                    </div>
                                </div>
                                <div>
                                    <label for="mail_from_name" class="block text-sm font-medium text-light-text-secondary dark:text-dark-text-secondary mb-2">
                                        From Name
                                    </label>
                                    <input
                                        id="mail_from_name"
                                        v-model="emailForm.mail_from_name"
                                        type="text"
                                        placeholder="Latch"
                                        class="w-full px-4 py-2 bg-light-bg dark:bg-dark-bg border border-light-border dark:border-dark-border rounded-md text-light-text-primary dark:text-dark-text-primary focus:border-light-accent dark:focus:border-dark-accent focus:ring-2 focus:ring-light-accent/20 dark:focus:ring-dark-accent/20 focus:outline-none transition-colors"
                                        required
                                    />
                                    <div v-if="emailForm.errors.mail_from_name" class="mt-2 text-sm text-red-600 dark:text-red-400">
                                        {{ emailForm.errors.mail_from_name }}
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-end pt-4">
                                <button
                                    type="submit"
                                    :disabled="emailForm.processing"
                                    class="px-4 py-2 bg-light-accent dark:bg-dark-accent text-white font-semibold rounded-md shadow-neon hover:shadow-neon-lg transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    <span v-if="emailForm.processing">Saving...</span>
                                    <span v-else>Save Settings</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Test Email Card -->
                <div class="bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded-lg shadow-lg">
                    <div class="px-6 py-5 border-b border-light-border dark:border-dark-border">
                        <h3 class="text-lg font-medium text-light-text-primary dark:text-dark-text-primary">
                            Test Configuration
                        </h3>
                        <p class="mt-1 text-sm text-light-text-muted dark:text-dark-text-muted">
                            Send a test email to verify your settings are working correctly.
                        </p>
                    </div>
                    <div class="px-6 py-5">
                        <form @submit.prevent="sendTestEmail" class="space-y-4">
                            <div>
                                <label for="test_email" class="block text-sm font-medium text-light-text-secondary dark:text-dark-text-secondary mb-2">
                                    Recipient Email
                                </label>
                                <div class="flex gap-3">
                                    <input
                                        id="test_email"
                                        v-model="testForm.test_email"
                                        type="email"
                                        placeholder="test@example.com"
                                        class="flex-1 px-4 py-2 bg-light-bg dark:bg-dark-bg border border-light-border dark:border-dark-border rounded-md text-light-text-primary dark:text-dark-text-primary focus:border-light-accent dark:focus:border-dark-accent focus:ring-2 focus:ring-light-accent/20 dark:focus:ring-dark-accent/20 focus:outline-none transition-colors"
                                        required
                                    />
                                    <button
                                        type="submit"
                                        :disabled="testForm.processing || !testForm.test_email"
                                        class="px-4 py-2 bg-light-bg dark:bg-dark-bg border border-light-border dark:border-dark-border text-light-text-primary dark:text-dark-text-primary font-medium rounded-md hover:bg-light-accent/10 dark:hover:bg-dark-accent/10 hover:border-light-accent dark:hover:border-dark-accent transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        <span v-if="testForm.processing" class="flex items-center">
                                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            Sending...
                                        </span>
                                        <span v-else>Send Test Email</span>
                                    </button>
                                </div>
                                <div v-if="testForm.errors.test_email" class="mt-2 text-sm text-red-600 dark:text-red-400">
                                    {{ testForm.errors.test_email }}
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Information Card -->
                <div class="mt-8 bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded-lg shadow-lg">
                    <div class="px-6 py-5 border-b border-light-border dark:border-dark-border">
                        <h3 class="text-lg font-medium text-light-text-primary dark:text-dark-text-primary">
                            About Email Configuration
                        </h3>
                    </div>
                    <div class="px-6 py-5 text-sm text-light-text-secondary dark:text-dark-text-secondary space-y-3">
                        <p>
                            Email configuration is used for sending system emails including:
                        </p>
                        <ul class="list-disc list-inside space-y-1 ml-2">
                            <li>Password reset emails</li>
                            <li>Two-factor authentication codes</li>
                            <li>Email verification links</li>
                            <li>Workspace invitations</li>
                            <li>Notification digests</li>
                        </ul>
                        <p class="pt-2">
                            <strong class="text-light-text-primary dark:text-dark-text-primary">Security Note:</strong>
                            Your SMTP password is encrypted before being stored in the database.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </ConversationLayout>
</template>
