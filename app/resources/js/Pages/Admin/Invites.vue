<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import ConversationLayout from '@/Layouts/ConversationLayout.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    workspace: Object,
    invites: Array,
});

const showCreateModal = ref(false);
const showDeleteModal = ref(false);
const selectedInvite = ref(null);
const createdInvite = ref(null);
const showSuccessModal = ref(false);
const isLoading = ref(false);
const errorMessage = ref('');

// Filter state
const filterType = ref('all');
const filterStatus = ref('all');

// Create form state
const createForm = ref({
    type: 'code',
    email: '',
    role: 'member',
    expires_in: '7d',
    max_uses: null,
});

// Filtered invites
const filteredInvites = computed(() => {
    return props.invites.filter(invite => {
        // Filter by type
        if (filterType.value === 'email' && !invite.is_single_use) return false;
        if (filterType.value === 'code' && invite.is_single_use) return false;

        // Filter by status
        if (filterStatus.value === 'active' && !invite.can_be_used) return false;
        if (filterStatus.value === 'expired' && !invite.is_expired) return false;
        if (filterStatus.value === 'used') {
            if (invite.is_single_use && !invite.accepted_at) return false;
            if (!invite.is_single_use && (!invite.max_uses || invite.use_count < invite.max_uses)) return false;
        }

        return true;
    });
});

// Stats
const stats = computed(() => {
    const active = props.invites.filter(i => i.can_be_used).length;
    const codes = props.invites.filter(i => !i.is_single_use).length;
    const emailInvites = props.invites.filter(i => i.is_single_use).length;
    return { active, codes, emailInvites, total: props.invites.length };
});

const openCreateModal = () => {
    createForm.value = {
        type: 'code',
        email: '',
        role: 'member',
        expires_in: '7d',
        max_uses: null,
    };
    errorMessage.value = '';
    showCreateModal.value = true;
};

const createInvite = async () => {
    isLoading.value = true;
    errorMessage.value = '';

    try {
        const response = await window.axios.post(`/api/admin/workspaces/${props.workspace.id}/invites`, createForm.value);
        createdInvite.value = response.data.invite;
        showCreateModal.value = false;
        showSuccessModal.value = true;
        router.reload({ only: ['invites'] });
    } catch (error) {
        errorMessage.value = error.response?.data?.error || 'Failed to create invite';
    } finally {
        isLoading.value = false;
    }
};

const openDeleteModal = (invite) => {
    selectedInvite.value = invite;
    showDeleteModal.value = true;
};

const deleteInvite = async () => {
    isLoading.value = true;
    try {
        await window.axios.delete(`/api/admin/workspaces/${props.workspace.id}/invites/${selectedInvite.value.id}`);
        showDeleteModal.value = false;
        router.reload({ only: ['invites'] });
    } catch (error) {
        console.error('Failed to delete invite:', error);
        alert('Failed to delete invite');
    } finally {
        isLoading.value = false;
    }
};

const regenerateCode = async (invite) => {
    if (!confirm('Regenerate this invite code? The old code will stop working.')) return;

    try {
        const response = await window.axios.post(`/api/admin/workspaces/${props.workspace.id}/invites/${invite.id}/regenerate`);
        router.reload({ only: ['invites'] });
        alert(`New code: ${response.data.invite_code}`);
    } catch (error) {
        console.error('Failed to regenerate code:', error);
        alert('Failed to regenerate invite code');
    }
};

const copyToClipboard = (text) => {
    navigator.clipboard.writeText(text);
};

const getInviteUrl = (invite) => {
    if (invite.is_single_use && invite.token) {
        return `${window.location.origin}/register?invite=${invite.token}`;
    }
    return invite.invite_code;
};

const formatDate = (dateStr) => {
    if (!dateStr) return 'Never';
    const date = new Date(dateStr);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
};

const getStatusBadge = (invite) => {
    if (invite.is_expired) {
        return { text: 'Expired', class: 'bg-light-danger/20 dark:bg-dark-danger/20 text-light-danger dark:text-dark-danger' };
    }
    if (invite.is_single_use && invite.accepted_at) {
        return { text: 'Used', class: 'bg-light-text-muted/20 dark:bg-dark-text-muted/20 text-light-text-muted dark:text-dark-text-muted' };
    }
    if (!invite.is_single_use && invite.max_uses && invite.use_count >= invite.max_uses) {
        return { text: 'Max Uses Reached', class: 'bg-light-text-muted/20 dark:bg-dark-text-muted/20 text-light-text-muted dark:text-dark-text-muted' };
    }
    // Use green colors for Active status
    return { text: 'Active', class: 'bg-green-500/20 dark:bg-green-400/20 text-green-700 dark:text-green-400' };
};

const getRoleBadgeClass = (role) => {
    if (role === 'admin') {
        return 'bg-light-primary/20 dark:bg-dark-primary/20 text-light-primary dark:text-dark-primary border border-light-primary dark:border-dark-primary';
    }
    return 'bg-light-border dark:bg-dark-border text-light-text-secondary dark:text-dark-text-secondary';
};
</script>

<template>
    <ConversationLayout :title="`Admin - Invites - ${workspace.name}`">
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
                        class="flex items-center px-3 py-2 text-sm font-medium rounded-md bg-light-accent/10 dark:bg-dark-accent/10 text-light-accent dark:text-dark-accent border border-light-accent dark:border-dark-accent"
                    >
                        <svg class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        Invites
                    </Link>
                    <Link
                        :href="`/admin/workspaces/${workspace.id}/bots`"
                        class="flex items-center px-3 py-2 text-sm font-medium rounded-md text-light-text-secondary dark:text-dark-text-secondary hover:bg-light-bg dark:hover:bg-dark-bg hover:text-light-text-primary dark:hover:text-dark-text-primary transition-colors"
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
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    <h1 class="text-xl font-bold text-light-text-primary dark:text-dark-text-primary">
                        Manage Invites
                    </h1>
                </div>
                <PrimaryButton @click="openCreateModal">
                    <svg class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Create Invite
                </PrimaryButton>
            </div>
        </template>

        <!-- Main Content -->
        <div class="h-full overflow-y-auto bg-light-bg dark:bg-dark-bg p-6">
            <!-- Stats Cards -->
            <div class="grid grid-cols-4 gap-4 mb-6">
                <div class="bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded-lg p-4">
                    <div class="text-2xl font-bold text-light-text-primary dark:text-dark-text-primary">{{ stats.active }}</div>
                    <div class="text-sm text-light-text-muted dark:text-dark-text-muted">Active Invites</div>
                </div>
                <div class="bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded-lg p-4">
                    <div class="text-2xl font-bold text-light-text-primary dark:text-dark-text-primary">{{ stats.codes }}</div>
                    <div class="text-sm text-light-text-muted dark:text-dark-text-muted">Invite Codes</div>
                </div>
                <div class="bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded-lg p-4">
                    <div class="text-2xl font-bold text-light-text-primary dark:text-dark-text-primary">{{ stats.emailInvites }}</div>
                    <div class="text-sm text-light-text-muted dark:text-dark-text-muted">Email Invites</div>
                </div>
                <div class="bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded-lg p-4">
                    <div class="text-2xl font-bold text-light-text-primary dark:text-dark-text-primary">{{ stats.total }}</div>
                    <div class="text-sm text-light-text-muted dark:text-dark-text-muted">Total Invites</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="flex space-x-4 mb-4">
                <select
                    v-model="filterType"
                    class="px-3 py-2 bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded-lg text-sm text-light-text-primary dark:text-dark-text-primary focus:border-light-accent dark:focus:border-dark-accent"
                >
                    <option value="all">All Types</option>
                    <option value="code">Invite Codes</option>
                    <option value="email">Email Invites</option>
                </select>
                <select
                    v-model="filterStatus"
                    class="px-3 py-2 bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded-lg text-sm text-light-text-primary dark:text-dark-text-primary focus:border-light-accent dark:focus:border-dark-accent"
                >
                    <option value="all">All Status</option>
                    <option value="active">Active</option>
                    <option value="expired">Expired</option>
                    <option value="used">Used</option>
                </select>
            </div>

            <!-- Invites Table -->
            <div class="bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded-lg shadow-lg">
                <div class="px-6 py-4 border-b border-light-border dark:border-dark-border">
                    <h2 class="text-lg font-semibold text-light-text-primary dark:text-dark-text-primary">
                        Invites ({{ filteredInvites.length }})
                    </h2>
                </div>

                <div v-if="filteredInvites.length === 0" class="p-8 text-center text-light-text-muted dark:text-dark-text-muted">
                    <svg class="h-12 w-12 mx-auto mb-4 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    <p>No invites found</p>
                    <button @click="openCreateModal" class="mt-4 text-light-accent dark:text-dark-accent hover:underline">
                        Create your first invite
                    </button>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-light-border dark:divide-dark-border">
                        <thead class="bg-light-bg dark:bg-dark-bg">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-light-text-muted dark:text-dark-text-muted uppercase tracking-wider">
                                    Type / Code
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-light-text-muted dark:text-dark-text-muted uppercase tracking-wider">
                                    Role
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-light-text-muted dark:text-dark-text-muted uppercase tracking-wider">
                                    Status
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-light-text-muted dark:text-dark-text-muted uppercase tracking-wider">
                                    Uses
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-light-text-muted dark:text-dark-text-muted uppercase tracking-wider">
                                    Expires
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-light-text-muted dark:text-dark-text-muted uppercase tracking-wider">
                                    Created By
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-light-text-muted dark:text-dark-text-muted uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-light-border dark:divide-dark-border">
                            <tr v-for="invite in filteredInvites" :key="invite.id" class="hover:bg-light-bg dark:hover:bg-dark-bg transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div v-if="invite.is_single_use" class="flex items-center">
                                            <svg class="h-5 w-5 text-light-text-muted dark:text-dark-text-muted mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                            </svg>
                                            <div>
                                                <div class="text-sm font-medium text-light-text-primary dark:text-dark-text-primary">
                                                    Email Invite
                                                </div>
                                                <div class="text-sm text-light-text-muted dark:text-dark-text-muted">
                                                    {{ invite.email }}
                                                </div>
                                            </div>
                                        </div>
                                        <div v-else class="flex items-center">
                                            <svg class="h-5 w-5 text-light-accent dark:text-dark-accent mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                            </svg>
                                            <div>
                                                <div class="text-sm font-medium text-light-text-primary dark:text-dark-text-primary font-mono">
                                                    {{ invite.invite_code }}
                                                </div>
                                                <div class="text-xs text-light-text-muted dark:text-dark-text-muted">
                                                    Reusable Code
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span :class="[
                                        'px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full',
                                        getRoleBadgeClass(invite.role)
                                    ]">
                                        {{ invite.role }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span :class="[
                                        'px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full',
                                        getStatusBadge(invite).class
                                    ]">
                                        {{ getStatusBadge(invite).text }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-light-text-secondary dark:text-dark-text-secondary">
                                    <span v-if="invite.is_single_use">
                                        {{ invite.accepted_at ? '1/1' : '0/1' }}
                                    </span>
                                    <span v-else>
                                        {{ invite.use_count }}/{{ invite.max_uses || '∞' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-light-text-secondary dark:text-dark-text-secondary">
                                    {{ formatDate(invite.expires_at) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-light-text-secondary dark:text-dark-text-secondary">
                                    {{ invite.inviter?.name || 'Unknown' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end space-x-2">
                                        <button
                                            v-if="invite.can_be_used"
                                            @click="copyToClipboard(invite.is_single_use ? invite.token : invite.invite_code)"
                                            class="text-light-accent dark:text-dark-accent hover:text-light-accent-hover dark:hover:text-dark-accent-hover transition-colors"
                                            title="Copy to clipboard"
                                        >
                                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                                            </svg>
                                        </button>
                                        <button
                                            v-if="!invite.is_single_use && invite.can_be_used"
                                            @click="regenerateCode(invite)"
                                            class="text-light-primary dark:text-dark-primary hover:text-light-primary-hover dark:hover:text-dark-primary-hover transition-colors"
                                            title="Regenerate code"
                                        >
                                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                            </svg>
                                        </button>
                                        <button
                                            @click="openDeleteModal(invite)"
                                            class="text-light-danger dark:text-dark-danger hover:opacity-75 transition-opacity"
                                            title="Delete invite"
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

    <!-- Create Invite Modal -->
    <Modal :show="showCreateModal" @close="showCreateModal = false" max-width="lg">
        <div class="p-6">
            <h2 class="text-lg font-medium text-light-text-primary dark:text-dark-text-primary mb-4">
                Create New Invite
            </h2>

            <form @submit.prevent="createInvite">
                <!-- Invite Type -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-light-text-primary dark:text-dark-text-primary mb-2">
                        Invite Type
                    </label>
                    <div class="flex space-x-4">
                        <label class="flex items-center">
                            <input
                                type="radio"
                                v-model="createForm.type"
                                value="code"
                                class="form-radio text-light-accent dark:text-dark-accent"
                            />
                            <span class="ml-2 text-sm text-light-text-primary dark:text-dark-text-primary">Reusable Code</span>
                        </label>
                        <label class="flex items-center">
                            <input
                                type="radio"
                                v-model="createForm.type"
                                value="email"
                                class="form-radio text-light-accent dark:text-dark-accent"
                            />
                            <span class="ml-2 text-sm text-light-text-primary dark:text-dark-text-primary">Email Invite</span>
                        </label>
                    </div>
                    <p class="mt-1 text-xs text-light-text-muted dark:text-dark-text-muted">
                        {{ createForm.type === 'code' ? 'Create a code that multiple people can use to join' : 'Send an invite to a specific email address' }}
                    </p>
                </div>

                <!-- Email (only for email invites) -->
                <div v-if="createForm.type === 'email'" class="mb-4">
                    <label class="block text-sm font-medium text-light-text-primary dark:text-dark-text-primary mb-2">
                        Email Address
                    </label>
                    <input
                        type="email"
                        v-model="createForm.email"
                        required
                        class="w-full px-3 py-2 bg-light-bg dark:bg-dark-bg border border-light-border dark:border-dark-border rounded-lg text-sm text-light-text-primary dark:text-dark-text-primary placeholder-light-text-muted dark:placeholder-dark-text-muted focus:border-light-accent dark:focus:border-dark-accent focus:ring-2 focus:ring-light-accent/20 dark:focus:ring-dark-accent/20"
                        placeholder="user@example.com"
                    />
                </div>

                <!-- Max Uses (only for reusable codes) -->
                <div v-if="createForm.type === 'code'" class="mb-4">
                    <label class="block text-sm font-medium text-light-text-primary dark:text-dark-text-primary mb-2">
                        Max Uses (optional)
                    </label>
                    <input
                        type="number"
                        v-model="createForm.max_uses"
                        min="1"
                        max="1000"
                        class="w-full px-3 py-2 bg-light-bg dark:bg-dark-bg border border-light-border dark:border-dark-border rounded-lg text-sm text-light-text-primary dark:text-dark-text-primary placeholder-light-text-muted dark:placeholder-dark-text-muted focus:border-light-accent dark:focus:border-dark-accent focus:ring-2 focus:ring-light-accent/20 dark:focus:ring-dark-accent/20"
                        placeholder="Leave empty for unlimited"
                    />
                </div>

                <!-- Role -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-light-text-primary dark:text-dark-text-primary mb-2">
                        Role
                    </label>
                    <select
                        v-model="createForm.role"
                        class="w-full px-3 py-2 bg-light-bg dark:bg-dark-bg border border-light-border dark:border-dark-border rounded-lg text-sm text-light-text-primary dark:text-dark-text-primary focus:border-light-accent dark:focus:border-dark-accent"
                    >
                        <option value="member">Member</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>

                <!-- Expiration -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-light-text-primary dark:text-dark-text-primary mb-2">
                        Expires In
                    </label>
                    <select
                        v-model="createForm.expires_in"
                        class="w-full px-3 py-2 bg-light-bg dark:bg-dark-bg border border-light-border dark:border-dark-border rounded-lg text-sm text-light-text-primary dark:text-dark-text-primary focus:border-light-accent dark:focus:border-dark-accent"
                    >
                        <option value="1h">1 Hour</option>
                        <option value="24h">24 Hours</option>
                        <option value="7d">7 Days</option>
                        <option value="30d">30 Days</option>
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
                        @click="showCreateModal = false"
                        class="px-4 py-2 bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded-md text-sm font-medium text-light-text-primary dark:text-dark-text-primary hover:bg-light-bg dark:hover:bg-dark-bg transition-colors"
                    >
                        Cancel
                    </button>
                    <PrimaryButton :disabled="isLoading">
                        {{ isLoading ? 'Creating...' : 'Create Invite' }}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </Modal>

    <!-- Success Modal -->
    <Modal :show="showSuccessModal" @close="showSuccessModal = false" max-width="md">
        <div class="p-6">
            <div class="text-center mb-4">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-500/20 dark:bg-green-400/20">
                    <svg class="h-6 w-6 text-green-700 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <h2 class="mt-4 text-lg font-medium text-light-text-primary dark:text-dark-text-primary">
                    Invite Created!
                </h2>
            </div>

            <div v-if="createdInvite" class="bg-light-bg dark:bg-dark-bg rounded-lg p-4 mb-4">
                <div v-if="createdInvite.is_single_use" class="text-center">
                    <p class="text-sm text-light-text-muted dark:text-dark-text-muted mb-2">Share this invite link:</p>
                    <code class="block p-2 bg-light-surface dark:bg-dark-surface rounded text-xs break-all text-light-text-primary dark:text-dark-text-primary">
                        {{ `${window.location.origin}/register?invite=${createdInvite.token}` }}
                    </code>
                </div>
                <div v-else class="text-center">
                    <p class="text-sm text-light-text-muted dark:text-dark-text-muted mb-2">Share this invite code:</p>
                    <code class="block p-4 bg-light-surface dark:bg-dark-surface rounded text-2xl font-mono font-bold text-light-accent dark:text-dark-accent">
                        {{ createdInvite.invite_code }}
                    </code>
                </div>
            </div>

            <div class="flex justify-center space-x-3">
                <button
                    @click="copyToClipboard(createdInvite?.is_single_use ? `${window.location.origin}/register?invite=${createdInvite.token}` : createdInvite?.invite_code)"
                    class="px-4 py-2 bg-light-accent dark:bg-dark-accent text-white rounded-md text-sm font-medium hover:bg-light-accent-hover dark:hover:bg-dark-accent-hover transition-colors"
                >
                    Copy to Clipboard
                </button>
                <button
                    @click="showSuccessModal = false"
                    class="px-4 py-2 bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded-md text-sm font-medium text-light-text-primary dark:text-dark-text-primary hover:bg-light-bg dark:hover:bg-dark-bg transition-colors"
                >
                    Done
                </button>
            </div>
        </div>
    </Modal>

    <!-- Delete Invite Modal -->
    <Modal :show="showDeleteModal" @close="showDeleteModal = false">
        <div class="p-6">
            <h2 class="text-lg font-medium text-light-text-primary dark:text-dark-text-primary mb-4">
                Delete Invite?
            </h2>

            <p class="text-sm text-light-text-secondary dark:text-dark-text-secondary mb-6">
                Are you sure you want to delete this invite?
                <span v-if="selectedInvite?.is_single_use">
                    The invite for <strong>{{ selectedInvite.email }}</strong> will no longer be valid.
                </span>
                <span v-else>
                    The invite code <strong class="font-mono">{{ selectedInvite?.invite_code }}</strong> will no longer work.
                </span>
            </p>

            <div class="flex justify-end space-x-3">
                <button
                    type="button"
                    @click="showDeleteModal = false"
                    class="px-4 py-2 bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded-md text-sm font-medium text-light-text-primary dark:text-dark-text-primary hover:bg-light-bg dark:hover:bg-dark-bg transition-colors"
                >
                    Cancel
                </button>
                <button
                    @click="deleteInvite"
                    :disabled="isLoading"
                    class="px-4 py-2 bg-light-danger dark:bg-dark-danger text-white rounded-md text-sm font-medium hover:opacity-90 transition-opacity disabled:opacity-50"
                >
                    {{ isLoading ? 'Deleting...' : 'Delete Invite' }}
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

/* Number input styling for dark mode */
input[type="number"] {
    color-scheme: light;
}

.dark input[type="number"] {
    color-scheme: dark;
}
</style>
