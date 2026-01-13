<script setup>
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/vue';
import { ChevronDownIcon, PlusIcon } from '@heroicons/vue/20/solid';

const page = usePage();
const currentWorkspace = computed(() => page.props.currentWorkspace);
const workspaces = computed(() => page.props.workspaces || []);

const switchWorkspace = (workspaceId) => {
    router.post(`/workspaces/${workspaceId}/switch`);
};

const createWorkspace = () => {
    router.visit('/workspaces/create');
};
</script>

<template>
    <Menu as="div" class="relative inline-block text-left">
        <div>
            <MenuButton class="inline-flex w-full justify-center items-center gap-x-1.5 rounded-md bg-light-surface dark:bg-dark-surface px-3 py-2 text-sm font-semibold text-light-text-primary dark:text-dark-text-primary shadow-sm ring-1 ring-inset ring-light-border dark:ring-dark-border hover:bg-light-bg dark:hover:bg-dark-bg">
                <span v-if="currentWorkspace">{{ currentWorkspace.name }}</span>
                <span v-else>Select Workspace</span>
                <ChevronDownIcon class="-mr-1 h-5 w-5 text-light-text-muted dark:text-dark-text-muted" aria-hidden="true" />
            </MenuButton>
        </div>

        <transition enter-active-class="transition ease-out duration-100" enter-from-class="transform opacity-0 scale-95" enter-to-class="transform opacity-100 scale-100" leave-active-class="transition ease-in duration-75" leave-from-class="transform opacity-100 scale-100" leave-to-class="transform opacity-0 scale-95">
            <MenuItems class="absolute left-0 z-10 mt-2 w-56 origin-top-left rounded-md bg-light-surface dark:bg-dark-surface shadow-lg ring-1 ring-light-border dark:ring-dark-border focus:outline-none">
                <div class="py-1">
                    <MenuItem v-for="workspace in workspaces" :key="workspace.id" v-slot="{ active }">
                        <button
                            @click="switchWorkspace(workspace.id)"
                            :class="[
                                active ? 'bg-light-bg dark:bg-dark-bg text-light-text-primary dark:text-dark-text-primary' : 'text-light-text-secondary dark:text-dark-text-secondary',
                                currentWorkspace?.id === workspace.id ? 'font-semibold' : '',
                                'block w-full text-left px-4 py-2 text-sm'
                            ]"
                        >
                            {{ workspace.name }}
                            <span v-if="currentWorkspace?.id === workspace.id" class="ml-2 text-xs text-light-text-muted dark:text-dark-text-muted">(current)</span>
                        </button>
                    </MenuItem>

                    <div class="border-t border-light-border dark:border-dark-border"></div>

                    <MenuItem v-slot="{ active }">
                        <button
                            @click="createWorkspace"
                            :class="[
                                active ? 'bg-light-bg dark:bg-dark-bg text-light-text-primary dark:text-dark-text-primary' : 'text-light-text-secondary dark:text-dark-text-secondary',
                                'flex w-full items-center px-4 py-2 text-sm'
                            ]"
                        >
                            <PlusIcon class="mr-2 h-4 w-4" aria-hidden="true" />
                            Create Workspace
                        </button>
                    </MenuItem>
                </div>
            </MenuItems>
        </transition>
    </Menu>
</template>
