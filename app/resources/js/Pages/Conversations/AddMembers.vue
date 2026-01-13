<script setup>
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import FormSection from '@/Components/FormSection.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    conversation: Object,
    availableMembers: Array,
});

const form = useForm({
    user_ids: [],
});

const toggleUser = (userId) => {
    const index = form.user_ids.indexOf(userId);
    if (index > -1) {
        form.user_ids.splice(index, 1);
    } else {
        form.user_ids.push(userId);
    }
};

const submit = () => {
    form.post(route('web.conversations.members.store', props.conversation.id), {
        preserveScroll: true,
    });
};

const cancel = () => {
    router.visit(route('web.conversations.show', props.conversation.id));
};
</script>

<template>
    <AppLayout :title="`Add Members to ${conversation.name}`">
        <template #header>
            <h2 class="font-semibold text-xl text-light-text-primary dark:text-dark-text-primary leading-tight">
                Add Members to {{ conversation.name }}
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <FormSection @submitted="submit">
                    <template #title>
                        Select Members
                    </template>

                    <template #description>
                        Choose one or more people to add to this {{ conversation.type === 'private_channel' ? 'private' : 'public' }} channel.
                    </template>

                    <template #form>
                        <div class="col-span-6">
                            <InputLabel value="Available Members" />
                            <div v-if="availableMembers.length === 0" class="mt-3 text-sm text-light-text-secondary dark:text-dark-text-secondary">
                                All workspace members are already in this channel.
                            </div>
                            <div v-else class="mt-3 space-y-2 max-h-96 overflow-y-auto">
                                <label
                                    v-for="member in availableMembers"
                                    :key="member.id"
                                    class="flex items-center p-3 rounded-md border border-light-border dark:border-dark-border hover:bg-light-bg dark:hover:bg-dark-bg cursor-pointer"
                                >
                                    <input
                                        type="checkbox"
                                        :value="member.id"
                                        :checked="form.user_ids.includes(member.id)"
                                        @change="toggleUser(member.id)"
                                        class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-light-border dark:border-dark-border rounded"
                                    />
                                    <div class="ml-3 flex items-center">
                                        <div class="h-8 w-8 rounded-full bg-indigo-600 flex items-center justify-center text-white font-semibold mr-3">
                                            {{ member.name.charAt(0).toUpperCase() }}
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-light-text-primary dark:text-dark-text-primary">
                                                {{ member.name }}
                                            </div>
                                            <div class="text-xs text-light-text-secondary dark:text-dark-text-secondary">
                                                {{ member.email }}
                                            </div>
                                        </div>
                                    </div>
                                </label>
                            </div>
                            <InputError :message="form.errors.user_ids" class="mt-2" />
                        </div>
                    </template>

                    <template #actions>
                        <SecondaryButton @click="cancel" type="button" class="mr-3">
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton :class="{ 'opacity-25': form.processing || form.user_ids.length === 0 }" :disabled="form.processing || form.user_ids.length === 0">
                            Add Members
                        </PrimaryButton>
                    </template>
                </FormSection>
            </div>
        </div>
    </AppLayout>
</template>
