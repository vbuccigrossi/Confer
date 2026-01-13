<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import ConversationLayout from '@/Layouts/ConversationLayout.vue';

const props = defineProps({
    workspace: Object,
    stats: Object,
    recentMembers: Array,
});
</script>

<template>
    <ConversationLayout :title="`Admin Panel - ${workspace.name}`">
        <template #sidebar>
            <div class="py-4 px-4">
                <h3 class="text-xs font-semibold text-light-text-muted dark:text-dark-text-muted uppercase tracking-wider mb-3">
                    Admin Panel
                </h3>
                <nav class="space-y-1">
                    <Link
                        :href="`/admin/workspaces/${workspace.id}/overview`"
                        class="flex items-center px-3 py-2 text-sm font-medium rounded-md bg-light-accent/10 dark:bg-dark-accent/10 text-light-accent dark:text-dark-accent border border-light-accent dark:border-dark-accent"
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
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
                <h1 class="text-xl font-bold text-light-text-primary dark:text-dark-text-primary">
                    Admin Overview
                </h1>
            </div>
        </template>

        <!-- Main Content -->
        <div class="h-full overflow-y-auto bg-light-bg dark:bg-dark-bg p-6">
            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total Members -->
                <div class="bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded-lg p-6 shadow-lg">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-8 w-8 text-light-accent dark:text-dark-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-light-text-muted dark:text-dark-text-muted truncate">
                                    Total Members
                                </dt>
                                <dd class="text-3xl font-semibold text-light-text-primary dark:text-dark-text-primary">
                                    {{ stats.total_members }}
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>

                <!-- Total Channels -->
                <div class="bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded-lg p-6 shadow-lg">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-8 w-8 text-light-primary dark:text-dark-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14" />
                            </svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-light-text-muted dark:text-dark-text-muted truncate">
                                    Total Channels
                                </dt>
                                <dd class="text-3xl font-semibold text-light-text-primary dark:text-dark-text-primary">
                                    {{ stats.total_channels }}
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>

                <!-- Total Messages -->
                <div class="bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded-lg p-6 shadow-lg">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-8 w-8 text-light-accent dark:text-dark-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                            </svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-light-text-muted dark:text-dark-text-muted truncate">
                                    Total Messages
                                </dt>
                                <dd class="text-3xl font-semibold text-light-text-primary dark:text-dark-text-primary">
                                    {{ stats.total_messages }}
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>

                <!-- Total DMs -->
                <div class="bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded-lg p-6 shadow-lg">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-8 w-8 text-light-primary dark:text-dark-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z" />
                            </svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-light-text-muted dark:text-dark-text-muted truncate">
                                    Direct Messages
                                </dt>
                                <dd class="text-3xl font-semibold text-light-text-primary dark:text-dark-text-primary">
                                    {{ stats.total_dms }}
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Members -->
            <div class="bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded-lg shadow-lg p-6">
                <h2 class="text-lg font-semibold text-light-text-primary dark:text-dark-text-primary mb-4">
                    Recent Members
                </h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-light-border dark:divide-dark-border">
                        <thead>
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
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-light-border dark:divide-dark-border">
                            <tr v-for="member in recentMembers" :key="member.id" class="hover:bg-light-bg dark:hover:bg-dark-bg transition-colors">
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
                                        member.role === 'owner' ? 'bg-light-accent/20 dark:bg-dark-accent/20 text-light-accent dark:text-dark-accent border border-light-accent dark:border-dark-accent' :
                                        member.role === 'admin' ? 'bg-light-primary/20 dark:bg-dark-primary/20 text-light-primary dark:text-dark-primary border border-light-primary dark:border-dark-primary' :
                                        'bg-light-border dark:bg-dark-border text-light-text-secondary dark:text-dark-text-secondary'
                                    ]">
                                        {{ member.role }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-light-text-secondary dark:text-dark-text-secondary">
                                    {{ new Date(member.joined_at).toLocaleDateString() }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </ConversationLayout>
</template>
