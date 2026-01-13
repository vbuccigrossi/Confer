<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import FormSection from '@/Components/FormSection.vue';
import ActionSection from '@/Components/ActionSection.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    workspace: Object,
});

const form = useForm({
    name: props.workspace.name,
});

const inviteForm = useForm({
    email: '',
    role: 'member',
});

const updateWorkspace = () => {
    form.put(route('workspaces.update', props.workspace.id), {
        preserveScroll: true,
    });
};

const sendInvite = () => {
    inviteForm.post(route('api.workspaces.invites.store', props.workspace.id), {
        preserveScroll: true,
        onSuccess: () => inviteForm.reset(),
    });
};
</script>

<template>
    <AppLayout title="Workspace Settings">
        <template #header>
            <h2 class="font-semibold text-xl text-light-text-primary dark:text-dark-text-primary leading-tight">
                Workspace Settings
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- Workspace Details -->
                <FormSection @submitted="updateWorkspace">
                    <template #title>
                        Workspace Information
                    </template>

                    <template #description>
                        Update your workspace's name and other details.
                    </template>

                    <template #form>
                        <div class="col-span-6 sm:col-span-4">
                            <InputLabel for="name" value="Workspace Name" />
                            <TextInput
                                id="name"
                                v-model="form.name"
                                type="text"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError :message="form.errors.name" class="mt-2" />
                        </div>

                        <div class="col-span-6 sm:col-span-4">
                            <InputLabel value="Workspace Slug" />
                            <p class="mt-1 text-sm text-light-text-secondary dark:text-dark-text-secondary">
                                {{ workspace.slug }}
                            </p>
                        </div>
                    </template>

                    <template #actions>
                        <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                            Save Changes
                        </PrimaryButton>
                    </template>
                </FormSection>

                <!-- Team Members -->
                <ActionSection>
                    <template #title>
                        Team Members
                    </template>

                    <template #description>
                        All of the people that are part of this workspace.
                    </template>

                    <template #content>
                        <div class="space-y-6">
                            <div v-for="member in workspace.members" :key="member.id" class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="h-10 w-10 rounded-full bg-light-bg dark:bg-dark-bg flex items-center justify-center text-light-text-secondary dark:text-dark-text-secondary font-semibold">
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
                                <div class="text-sm text-light-text-muted dark:text-dark-text-muted">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-light-accent/10 dark:bg-dark-accent/10 text-light-accent dark:text-dark-accent">
                                        {{ member.role }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </template>
                </ActionSection>

                <!-- Pending Invitations -->
                <FormSection @submitted="sendInvite">
                    <template #title>
                        Invite Team Members
                    </template>

                    <template #description>
                        Send email invitations to add new members to your workspace.
                    </template>

                    <template #form>
                        <div class="col-span-6 sm:col-span-4">
                            <InputLabel for="email" value="Email Address" />
                            <TextInput
                                id="email"
                                v-model="inviteForm.email"
                                type="email"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError :message="inviteForm.errors.email" class="mt-2" />
                        </div>

                        <div class="col-span-6 sm:col-span-4">
                            <InputLabel for="role" value="Role" />
                            <select
                                id="role"
                                v-model="inviteForm.role"
                                class="mt-1 block w-full border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface text-light-text-primary dark:text-dark-text-primary focus:border-light-accent dark:focus:border-dark-accent focus:ring-light-accent dark:focus:ring-dark-accent rounded-md shadow-sm"
                            >
                                <option value="member">Member</option>
                                <option value="admin">Admin</option>
                            </select>
                            <InputError :message="inviteForm.errors.role" class="mt-2" />
                        </div>

                        <!-- Show pending invites -->
                        <div v-if="workspace.invites && workspace.invites.length > 0" class="col-span-6">
                            <h4 class="text-sm font-medium text-light-text-primary dark:text-dark-text-primary mb-4">Pending Invitations</h4>
                            <div class="space-y-3">
                                <div v-for="invite in workspace.invites" :key="invite.id" class="flex items-center justify-between p-3 bg-light-bg dark:bg-dark-bg rounded-md">
                                    <div>
                                        <div class="text-sm font-medium text-light-text-primary dark:text-dark-text-primary">
                                            {{ invite.email }}
                                        </div>
                                        <div class="text-xs text-light-text-muted dark:text-dark-text-muted">
                                            Invited by {{ invite.inviter.name }} &middot; Expires {{ new Date(invite.expires_at).toLocaleDateString() }}
                                        </div>
                                    </div>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-200">
                                        {{ invite.role }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </template>

                    <template #actions>
                        <PrimaryButton :class="{ 'opacity-25': inviteForm.processing }" :disabled="inviteForm.processing">
                            Send Invitation
                        </PrimaryButton>
                    </template>
                </FormSection>
            </div>
        </div>
    </AppLayout>
</template>
