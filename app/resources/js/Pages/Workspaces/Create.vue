<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import FormSection from '@/Components/FormSection.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const form = useForm({
    name: '',
    slug: '',
});

const submit = () => {
    form.post(route('workspaces.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};
</script>

<template>
    <AppLayout title="Create Workspace">
        <template #header>
            <h2 class="font-semibold text-xl text-light-text-primary dark:text-dark-text-primary leading-tight">
                Create Workspace
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <FormSection @submitted="submit">
                    <template #title>
                        Workspace Details
                    </template>

                    <template #description>
                        Create a new workspace to organize your team's conversations and collaboration.
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
                                autofocus
                            />
                            <InputError :message="form.errors.name" class="mt-2" />
                        </div>

                        <div class="col-span-6 sm:col-span-4">
                            <InputLabel for="slug" value="Workspace Slug (optional)" />
                            <TextInput
                                id="slug"
                                v-model="form.slug"
                                type="text"
                                class="mt-1 block w-full"
                                placeholder="Leave blank to auto-generate from name"
                            />
                            <InputError :message="form.errors.slug" class="mt-2" />
                            <p class="mt-2 text-sm text-light-text-muted dark:text-dark-text-muted">
                                A URL-friendly identifier for your workspace. Will be auto-generated if left blank.
                            </p>
                        </div>
                    </template>

                    <template #actions>
                        <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                            Create Workspace
                        </PrimaryButton>
                    </template>
                </FormSection>
            </div>
        </div>
    </AppLayout>
</template>
