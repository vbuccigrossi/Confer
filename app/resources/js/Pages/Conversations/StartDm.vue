<script setup>
import { ref, computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import FormSection from '@/Components/FormSection.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    workspaceMembers: Array,
});

const page = usePage();
const currentUserId = computed(() => page.props.auth.user.id);

const form = useForm({
    user_ids: [],
});

const availableMembers = computed(() => {
    return props.workspaceMembers?.filter(member => member.user_id !== currentUserId.value) || [];
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
    form.post(route('conversations.dm.store'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout title="Start a Direct Message">
        <template #header>
            <h2 class="font-semibold text-xl text-light-text-primary dark:text-dark-text-primary leading-tight">
                Start a Direct Message
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <FormSection @submitted="submit">
                    <template #title>
                        Select Members
                    </template>

                    <template #description>
                        Choose one or more people to start a conversation with.
                    </template>

                    <template #form>
                        <div class="col-span-6">
                            <InputLabel value="Workspace Members" />
                            <div class="mt-3 space-y-2 max-h-96 overflow-y-auto">
                                <label
                                    v-for="member in availableMembers"
                                    :key="member.user_id"
                                    class="flex items-center p-3 rounded-md border border-light-border dark:border-dark-border hover:bg-light-bg dark:hover:bg-dark-bg cursor-pointer"
                                >
                                    <input
                                        type="checkbox"
                                        :value="member.user_id"
                                        :checked="form.user_ids.includes(member.user_id)"
                                        @change="toggleUser(member.user_id)"
                                        class="h-4 w-4 text-light-accent dark:text-dark-accent focus:ring-light-accent dark:focus:ring-dark-accent border-light-border dark:border-dark-border rounded"
                                    />
                                    <div class="ml-3 flex items-center">
                                        <div class="h-8 w-8 rounded-full bg-light-bg dark:bg-dark-bg flex items-center justify-center text-light-text-secondary dark:text-dark-text-secondary font-semibold mr-3">
                                            {{ member.user.name.charAt(0).toUpperCase() }}
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-light-text-primary dark:text-dark-text-primary">
                                                {{ member.user.name }}
                                            </div>
                                            <div class="text-xs text-light-text-muted dark:text-dark-text-muted">
                                                {{ member.user.email }}
                                            </div>
                                        </div>
                                    </div>
                                </label>
                            </div>
                            <InputError :message="form.errors.user_ids" class="mt-2" />
                            <p class="mt-2 text-sm text-light-text-muted dark:text-dark-text-muted">
                                Select one person for a direct message, or multiple people for a group DM.
                            </p>
                        </div>
                    </template>

                    <template #actions>
                        <PrimaryButton :class="{ 'opacity-25': form.processing || form.user_ids.length === 0 }" :disabled="form.processing || form.user_ids.length === 0">
                            Start Conversation
                        </PrimaryButton>
                    </template>
                </FormSection>
            </div>
        </div>
    </AppLayout>
</template>
