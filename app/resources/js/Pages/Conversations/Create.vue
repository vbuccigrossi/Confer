<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import FormSection from '@/Components/FormSection.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const form = useForm({
    type: 'public_channel',
    name: '',
    slug: '',
    topic: '',
    description: '',
});

const submit = () => {
    form.post(route('conversations.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};
</script>

<template>
    <AppLayout title="Create Channel">
        <template #header>
            <h2 class="font-semibold text-xl text-light-text-primary dark:text-dark-text-primary leading-tight">
                Create a Channel
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <FormSection @submitted="submit">
                    <template #title>
                        Channel Details
                    </template>

                    <template #description>
                        Channels are where your team communicates. They're best organized around a topic — #marketing, for example.
                    </template>

                    <template #form>
                        <!-- Channel Type -->
                        <div class="col-span-6">
                            <InputLabel value="Channel Type" />
                            <div class="mt-2 space-y-3">
                                <label class="flex items-start cursor-pointer">
                                    <input
                                        v-model="form.type"
                                        type="radio"
                                        value="public_channel"
                                        class="mt-1 h-4 w-4 text-light-accent dark:text-dark-accent focus:ring-light-accent dark:focus:ring-dark-accent border-light-border dark:border-dark-border"
                                    />
                                    <div class="ml-3">
                                        <div class="text-sm font-medium text-light-text-primary dark:text-dark-text-primary flex items-center">
                                            <span class="mr-2">#</span> Public
                                        </div>
                                        <p class="text-sm text-light-text-muted dark:text-dark-text-muted">
                                            Anyone in the workspace can find and join
                                        </p>
                                    </div>
                                </label>

                                <label class="flex items-start cursor-pointer">
                                    <input
                                        v-model="form.type"
                                        type="radio"
                                        value="private_channel"
                                        class="mt-1 h-4 w-4 text-light-accent dark:text-dark-accent focus:ring-light-accent dark:focus:ring-dark-accent border-light-border dark:border-dark-border"
                                    />
                                    <div class="ml-3">
                                        <div class="text-sm font-medium text-light-text-primary dark:text-dark-text-primary flex items-center">
                                            <svg class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                            </svg>
                                            Private
                                        </div>
                                        <p class="text-sm text-light-text-muted dark:text-dark-text-muted">
                                            Only invited members can access
                                        </p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Channel Name -->
                        <div class="col-span-6 sm:col-span-4">
                            <InputLabel for="name" value="Channel Name" />
                            <div class="mt-1 flex rounded-md shadow-sm">
                                <span class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-light-border dark:border-dark-border bg-light-bg dark:bg-dark-bg text-light-text-muted dark:text-dark-text-muted text-sm">
                                    #
                                </span>
                                <TextInput
                                    id="name"
                                    v-model="form.name"
                                    type="text"
                                    class="flex-1 rounded-none rounded-r-md"
                                    required
                                    autofocus
                                    placeholder="e.g. marketing"
                                />
                            </div>
                            <InputError :message="form.errors.name" class="mt-2" />
                            <p class="mt-2 text-sm text-light-text-muted dark:text-dark-text-muted">
                                Names must be lowercase, without spaces or periods, and can't be longer than 80 characters.
                            </p>
                        </div>

                        <!-- Topic -->
                        <div class="col-span-6 sm:col-span-4">
                            <InputLabel for="topic" value="Topic (optional)" />
                            <TextInput
                                id="topic"
                                v-model="form.topic"
                                type="text"
                                class="mt-1 block w-full"
                                placeholder="What's this channel about?"
                                maxlength="250"
                            />
                            <InputError :message="form.errors.topic" class="mt-2" />
                        </div>

                        <!-- Description -->
                        <div class="col-span-6">
                            <InputLabel for="description" value="Description (optional)" />
                            <textarea
                                id="description"
                                v-model="form.description"
                                rows="3"
                                class="mt-1 block w-full border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface text-light-text-primary dark:text-dark-text-primary focus:border-light-accent dark:focus:border-dark-accent focus:ring-light-accent dark:focus:ring-dark-accent rounded-md shadow-sm"
                                placeholder="What's the purpose of this channel?"
                            ></textarea>
                            <InputError :message="form.errors.description" class="mt-2" />
                        </div>
                    </template>

                    <template #actions>
                        <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                            Create Channel
                        </PrimaryButton>
                    </template>
                </FormSection>
            </div>
        </div>
    </AppLayout>
</template>
