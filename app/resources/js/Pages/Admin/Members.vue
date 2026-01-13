<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import ConversationLayout from '@/Layouts/ConversationLayout.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    workspace: Object,
    members: Array,
});

const showRoleModal = ref(false);
const showDeleteModal = ref(false);
const showPasswordResetModal = ref(false);
const selectedMember = ref(null);

const roleForm = useForm({
    role: '',
});

const openRoleModal = (member) => {
    selectedMember.value = member;
    roleForm.role = member.role;
    showRoleModal.value = true;
};

const updateRole = () => {
    roleForm.patch(`/api/admin/workspaces/${props.workspace.id}/members/${selectedMember.value.user_id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showRoleModal.value = false;
            roleForm.reset();
        },
    });
};

const openDeleteModal = (member) => {
    selectedMember.value = member;
    showDeleteModal.value = true;
};

const deleteMember = () => {
    router.delete(`/api/admin/workspaces/${props.workspace.id}/members/${selectedMember.value.user_id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false;
        },
    });
};

const openPasswordResetModal = (member) => {
    selectedMember.value = member;
    showPasswordResetModal.value = true;
};

const sendPasswordReset = async () => {
    try {
        await window.axios.post(`/api/admin/workspaces/${props.workspace.id}/members/${selectedMember.value.user_id}/reset-password`);
        showPasswordResetModal.value = false;
        alert('Password reset email sent successfully!');
    } catch (error) {
        console.error('Failed to send password reset:', error);
        alert('Failed to send password reset email');
    }
};

const getRoleBadgeClass = (role) => {
    if (role === 'owner') {
        return 'bg-light-accent/20 dark:bg-dark-accent/20 text-light-accent dark:text-dark-accent border border-light-accent dark:border-dark-accent shadow-neon';
    } else if (role === 'admin') {
        return 'bg-light-primary/20 dark:bg-dark-primary/20 text-light-primary dark:text-dark-primary border border-light-primary dark:border-dark-primary';
    }
    return 'bg-light-border dark:bg-dark-border text-light-text-secondary dark:text-dark-text-secondary';
};
</script>

<template>
    <ConversationLayout :title="`Admin - Members - ${workspace.name}`">
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
                        class="flex items-center px-3 py-2 text-sm font-medium rounded-md bg-light-accent/10 dark:bg-dark-accent/10 text-light-accent dark:text-dark-accent border border-light-accent dark:border-dark-accent"
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
            <div class="flex items-center">
                <svg class="h-6 w-6 text-light-accent dark:text-dark-accent mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                <h1 class="text-xl font-bold text-light-text-primary dark:text-dark-text-primary">
                    Manage Members
                </h1>
            </div>
        </template>

        <!-- Main Content -->
        <div class="h-full overflow-y-auto bg-light-bg dark:bg-dark-bg p-6">
            <div class="bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded-lg shadow-lg">
                <div class="px-6 py-4 border-b border-light-border dark:border-dark-border">
                    <h2 class="text-lg font-semibold text-light-text-primary dark:text-dark-text-primary">
                        Workspace Members ({{ members.length }})
                    </h2>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-light-border dark:divide-dark-border">
                        <thead class="bg-light-bg dark:bg-dark-bg">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-light-text-muted dark:text-dark-text-muted uppercase tracking-wider">
                                    User
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-light-text-muted dark:text-dark-text-muted uppercase tracking-wider">
                                    Role
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-light-text-muted dark:text-dark-text-muted uppercase tracking-wider">
                                    Joined
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-light-text-muted dark:text-dark-text-muted uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-light-border dark:divide-dark-border">
                            <tr v-for="member in members" :key="member.id" class="hover:bg-light-bg dark:hover:bg-dark-bg transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="h-10 w-10 rounded-full bg-light-accent dark:bg-dark-accent flex items-center justify-center text-white font-semibold">
                                            {{ member.user.name.charAt(0).toUpperCase() }}
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-light-text-primary dark:text-dark-text-primary">
                                                {{ member.user.name }}
                                            </div>
                                            <div class="text-sm text-light-text-muted dark:text-dark-text-muted">
                                                {{ member.user.email }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span :class="[
                                        'px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full',
                                        getRoleBadgeClass(member.role)
                                    ]">
                                        {{ member.role }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-light-text-secondary dark:text-dark-text-secondary">
                                    {{ new Date(member.joined_at).toLocaleDateString() }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end space-x-2">
                                        <button
                                            @click="openRoleModal(member)"
                                            class="text-light-primary dark:text-dark-primary hover:text-light-primary-hover dark:hover:text-dark-primary-hover transition-colors"
                                            title="Change role"
                                        >
                                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                            </svg>
                                        </button>
                                        <button
                                            @click="openPasswordResetModal(member)"
                                            class="text-light-accent dark:text-dark-accent hover:text-light-accent-hover dark:hover:text-dark-accent-hover transition-colors"
                                            title="Reset password"
                                        >
                                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                            </svg>
                                        </button>
                                        <button
                                            v-if="member.role !== 'owner'"
                                            @click="openDeleteModal(member)"
                                            class="text-light-danger dark:text-dark-danger hover:opacity-75 transition-opacity"
                                            title="Remove member"
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

    <!-- Change Role Modal -->
    <Modal :show="showRoleModal" @close="showRoleModal = false">
        <div class="p-6">
            <h2 class="text-lg font-medium text-light-text-primary dark:text-dark-text-primary mb-4">
                Change Role for {{ selectedMember?.user.name }}
            </h2>
            
            <form @submit.prevent="updateRole">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-light-text-primary dark:text-dark-text-primary mb-2">
                        Role
                    </label>
                    <select
                        v-model="roleForm.role"
                        class="w-full border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface text-light-text-primary dark:text-dark-text-primary rounded-md shadow-sm focus:border-light-accent dark:focus:border-dark-accent focus:ring-light-accent dark:focus:ring-dark-accent"
                        required
                    >
                        <option value="member">Member</option>
                        <option value="admin">Admin</option>
                        <option value="owner">Owner</option>
                    </select>
                </div>

                <div class="flex justify-end space-x-3">
                    <button
                        type="button"
                        @click="showRoleModal = false"
                        class="px-4 py-2 bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded-md text-sm font-medium text-light-text-primary dark:text-dark-text-primary hover:bg-light-bg dark:hover:bg-dark-bg transition-colors"
                    >
                        Cancel
                    </button>
                    <PrimaryButton :class="{ 'opacity-25': roleForm.processing }" :disabled="roleForm.processing">
                        Update Role
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </Modal>

    <!-- Password Reset Modal -->
    <Modal :show="showPasswordResetModal" @close="showPasswordResetModal = false">
        <div class="p-6">
            <h2 class="text-lg font-medium text-light-text-primary dark:text-dark-text-primary mb-4">
                Reset Password for {{ selectedMember?.user.name }}
            </h2>
            
            <p class="text-sm text-light-text-secondary dark:text-dark-text-secondary mb-6">
                This will send a password reset email to <strong>{{ selectedMember?.user.email }}</strong>.
            </p>

            <div class="flex justify-end space-x-3">
                <button
                    type="button"
                    @click="showPasswordResetModal = false"
                    class="px-4 py-2 bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded-md text-sm font-medium text-light-text-primary dark:text-dark-text-primary hover:bg-light-bg dark:hover:bg-dark-bg transition-colors"
                >
                    Cancel
                </button>
                <PrimaryButton @click="sendPasswordReset">
                    Send Reset Email
                </PrimaryButton>
            </div>
        </div>
    </Modal>

    <!-- Delete Member Modal -->
    <Modal :show="showDeleteModal" @close="showDeleteModal = false">
        <div class="p-6">
            <h2 class="text-lg font-medium text-light-text-primary dark:text-dark-text-primary mb-4">
                Remove {{ selectedMember?.user.name }}?
            </h2>
            
            <p class="text-sm text-light-text-secondary dark:text-dark-text-secondary mb-6">
                Are you sure you want to remove this member from the workspace? This action cannot be undone.
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
                    @click="deleteMember"
                    class="px-4 py-2 bg-light-danger dark:bg-dark-danger text-white rounded-md text-sm font-medium hover:opacity-90 transition-opacity"
                >
                    Remove Member
                </button>
            </div>
        </div>
    </Modal>
</template>
