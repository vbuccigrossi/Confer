<script setup>
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const page = usePage();
const currentWorkspace = computed(() => page.props.currentWorkspace);

const createWorkspace = () => {
    router.visit('/workspaces/create');
};
</script>

<template>
    <AppLayout title="Dashboard">
        <template #header>
            <h2 class="font-semibold text-xl text-light-text-primary dark:text-dark-text-primary leading-tight">
                Dashboard
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- No workspace state -->
                <div v-if="!currentWorkspace" class="bg-light-surface dark:bg-dark-surface overflow-hidden shadow-xl sm:rounded-lg p-12 text-center">
                    <div class="max-w-md mx-auto">
                        <svg class="mx-auto h-16 w-16 text-light-text-muted dark:text-dark-text-muted mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                        <h3 class="text-lg font-medium text-light-text-primary dark:text-dark-text-primary mb-2">
                            Welcome to Latch!
                        </h3>
                        <p class="text-light-text-secondary dark:text-dark-text-secondary mb-6">
                            Get started by creating your first workspace. Workspaces help you organize your team's conversations and collaboration.
                        </p>
                        <button
                            @click="createWorkspace"
                            class="inline-flex items-center px-4 py-2 bg-light-accent dark:bg-dark-accent border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-light-accent-hover dark:hover:bg-dark-accent-hover active:bg-light-accent-muted dark:active:bg-dark-accent-muted focus:outline-none focus:ring focus:ring-light-accent/30 dark:focus:ring-dark-accent/30 disabled:opacity-25 transition"
                        >
                            Create Your First Workspace
                        </button>
                    </div>
                </div>

                <!-- Has workspace state -->
                <div v-else class="bg-light-surface dark:bg-dark-surface overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h3 class="text-2xl font-bold text-light-text-primary dark:text-dark-text-primary">
                                {{ currentWorkspace.name }}
                            </h3>
                            <p class="text-sm text-light-text-muted dark:text-dark-text-muted mt-1">
                                Welcome back! Your workspace is ready.
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="bg-light-bg dark:bg-dark-bg p-6 rounded-lg">
                            <div class="flex items-center">
                                <svg class="h-8 w-8 text-light-accent dark:text-dark-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                                </svg>
                                <div class="ml-4">
                                    <h4 class="text-lg font-semibold text-light-text-primary dark:text-dark-text-primary">Channels</h4>
                                    <p class="text-sm text-light-text-secondary dark:text-dark-text-secondary">Coming in Phase 2</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-light-bg dark:bg-dark-bg p-6 rounded-lg">
                            <div class="flex items-center">
                                <svg class="h-8 w-8 text-light-accent dark:text-dark-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z" />
                                </svg>
                                <div class="ml-4">
                                    <h4 class="text-lg font-semibold text-light-text-primary dark:text-dark-text-primary">Messages</h4>
                                    <p class="text-sm text-light-text-secondary dark:text-dark-text-secondary">Coming in Phase 3</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-light-bg dark:bg-dark-bg p-6 rounded-lg">
                            <div class="flex items-center">
                                <svg class="h-8 w-8 text-light-accent dark:text-dark-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                                <div class="ml-4">
                                    <h4 class="text-lg font-semibold text-light-text-primary dark:text-dark-text-primary">Team</h4>
                                    <p class="text-sm text-light-text-secondary dark:text-dark-text-secondary">Manage workspace</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 border-t border-light-border dark:border-dark-border pt-6">
                        <h4 class="text-sm font-medium text-light-text-primary dark:text-dark-text-primary mb-4">Quick Actions</h4>
                        <div class="flex space-x-4">
                            <a
                                href="/workspaces/settings"
                                class="inline-flex items-center px-4 py-2 bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded-md font-semibold text-xs text-light-text-secondary dark:text-dark-text-secondary uppercase tracking-widest shadow-sm hover:bg-light-bg dark:hover:bg-dark-bg focus:outline-none focus:ring-2 focus:ring-light-accent dark:focus:ring-dark-accent focus:ring-offset-2 disabled:opacity-25 transition"
                            >
                                Workspace Settings
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
