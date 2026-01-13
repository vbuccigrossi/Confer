<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import ApplicationMark from '@/Components/ApplicationMark.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import StatusSelector from '@/Components/StatusSelector.vue';

defineProps({
    title: String,
});

const page = usePage();
const showingNavigationDropdown = ref(false);
const showStatusSelector = ref(false);
const currentWorkspace = computed(() => page.props.currentWorkspace);
const currentUser = computed(() => page.props.auth.user);

const isAdmin = computed(() => {
    const workspace = page.props.currentWorkspace;
    if (!workspace) return false;
    const member = workspace.members?.find(m => m.user_id === page.props.auth.user.id);
    return member?.role === 'owner' || member?.role === 'admin';
});

const statusIcon = computed(() => {
    if (currentUser.value.status_emoji) {
        return currentUser.value.status_emoji;
    }

    const icons = {
        active: '🟢',
        away: '🟡',
        dnd: '🔴',
        invisible: '⚫',
    };
    return icons[currentUser.value.status] || '🟢';
});

const statusText = computed(() => {
    if (currentUser.value.status_message) {
        return currentUser.value.status_message;
    }

    const labels = {
        active: 'Active',
        away: 'Away',
        dnd: 'Do Not Disturb',
        invisible: 'Invisible',
    };
    return labels[currentUser.value.status] || 'Active';
});

const logout = () => {
    router.post(route('logout'));
};

// Listen for status changes via WebSocket
onMounted(() => {
    if (currentWorkspace.value) {
        window.Echo.private(`workspace.${currentWorkspace.value.id}`)
            .listen('.user.status.changed', (event) => {
                // Update the current user's status if it matches
                if (event.userId === currentUser.value.id) {
                    currentUser.value.status = event.status;
                    currentUser.value.status_message = event.statusMessage;
                    currentUser.value.status_emoji = event.statusEmoji;
                    currentUser.value.is_dnd = event.isDnd;
                    currentUser.value.is_online = event.isOnline;
                }
            });
    }
});

onUnmounted(() => {
    if (currentWorkspace.value) {
        window.Echo.leave(`workspace.${currentWorkspace.value.id}`);
    }
});
</script>

<template>
    <div>
        <Head :title="title" />

        <div class="h-screen bg-light-bg dark:bg-dark-bg flex transition-colors duration-200">
            <!-- Sidebar -->
            <div class="w-64 bg-light-surface dark:bg-dark-surface flex-shrink-0 flex flex-col border-r border-light-border dark:border-dark-border">
                <!-- Header -->
                <div class="h-16 flex items-center px-4 border-b border-light-border dark:border-dark-border">
                    <Link :href="route('web.conversations.index')" class="flex items-center space-x-2 text-light-text-primary dark:text-dark-text-primary font-bold text-lg hover:text-light-accent dark:hover:text-dark-accent transition-colors">
                        <div class="w-8 h-8 flex-shrink-0">
                            <ApplicationMark />
                        </div>
                        <span>Confer</span>
                    </Link>
                </div>

                <!-- Sidebar Content -->
                <div class="flex-1 overflow-y-auto">
                    <slot name="sidebar" />
                </div>

                <!-- User Profile Footer -->
                <button
                    @click="showStatusSelector = true"
                    class="h-14 border-t border-light-border dark:border-dark-border px-4 flex items-center w-full hover:bg-light-bg dark:hover:bg-dark-bg transition-colors cursor-pointer"
                >
                    <div class="flex items-center space-x-3 flex-1">
                        <div class="flex-shrink-0">
                            <div v-if="$page.props.auth.user.profile_photo_url" class="h-8 w-8 rounded overflow-hidden">
                                <img :src="$page.props.auth.user.profile_photo_url" :alt="$page.props.auth.user.name" class="w-full h-full object-cover">
                            </div>
                            <div v-else class="h-8 w-8 rounded bg-light-accent dark:bg-dark-accent flex items-center justify-center text-white text-sm font-semibold">
                                {{ $page.props.auth.user.name.charAt(0).toUpperCase() }}
                            </div>
                        </div>
                        <div class="flex-1 min-w-0 text-left">
                            <p class="text-sm font-medium text-light-text-primary dark:text-dark-text-primary truncate text-left">
                                {{ $page.props.auth.user.name }}
                            </p>
                            <div class="flex items-center space-x-1.5">
                                <span class="text-xs leading-none">{{ statusIcon }}</span>
                                <span class="text-xs text-light-text-muted dark:text-dark-text-muted truncate">
                                    {{ statusText }}
                                </span>
                            </div>
                        </div>
                    </div>
                </button>

                <!-- Status Selector Modal -->
                <StatusSelector
                    :show="showStatusSelector"
                    @close="showStatusSelector = false"
                />
            </div>

            <!-- Main Content -->
            <div class="flex-1 flex flex-col min-w-0">
                <!-- Header -->
                <header class="h-16 bg-light-surface dark:bg-dark-surface border-b border-light-border dark:border-dark-border flex-shrink-0">
                    <div class="h-full px-4 flex items-center justify-between">
                        <div class="flex items-center flex-1">
                            <slot name="header" />
                        </div>

                        <!-- Top Right Navigation -->
                        <div class="flex items-center space-x-4">
                            <!-- Theme Toggle -->
                            <ThemeToggle />

                            <!-- Profile Dropdown -->
                            <Dropdown align="right" width="48">
                                <template #trigger>
                                    <button class="flex items-center text-sm font-medium text-light-text-secondary dark:text-dark-text-secondary hover:text-light-text-primary dark:hover:text-dark-accent focus:outline-none focus:text-light-text-primary dark:focus:text-dark-accent transition duration-150 ease-in-out">
                                        <div v-if="$page.props.auth.user.profile_photo_url" class="h-8 w-8 rounded-full overflow-hidden mr-2">
                                            <img :src="$page.props.auth.user.profile_photo_url" :alt="$page.props.auth.user.name" class="w-full h-full object-cover">
                                        </div>
                                        <div v-else class="h-8 w-8 rounded-full bg-light-accent dark:bg-dark-accent flex items-center justify-center text-white font-semibold mr-2">
                                            {{ $page.props.auth.user.name.charAt(0).toUpperCase() }}
                                        </div>
                                        <span>{{ $page.props.auth.user.name }}</span>
                                        <svg class="ml-2 -mr-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                        </svg>
                                    </button>
                                </template>

                                <template #content>
                                    <div class="block px-4 py-2 text-xs text-light-text-muted dark:text-dark-text-muted">
                                        Manage Account
                                    </div>

                                    <DropdownLink :href="route('profile.show')">
                                        <div class="flex items-center">
                                            <svg class="h-4 w-4 mr-2 text-light-accent dark:text-dark-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                            </svg>
                                            Profile
                                        </div>
                                    </DropdownLink>

                                    <DropdownLink :href="route('analytics.index')">
                                        <div class="flex items-center">
                                            <svg class="h-4 w-4 mr-2 text-light-accent dark:text-dark-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                            </svg>
                                            Analytics
                                        </div>
                                    </DropdownLink>

                                    <DropdownLink v-if="isAdmin" :href="`/admin/workspaces/${currentWorkspace?.id}/overview`">
                                        <div class="flex items-center">
                                            <svg class="h-4 w-4 mr-2 text-light-accent dark:text-dark-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                            </svg>
                                            Admin Panel
                                        </div>
                                    </DropdownLink>

                                    <div class="border-t border-light-border dark:border-dark-border" />

                                    <form @submit.prevent="logout">
                                        <DropdownLink as="button">
                                            <div class="flex items-center">
                                                <svg class="h-4 w-4 mr-2 text-light-accent dark:text-dark-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                                </svg>
                                                Log Out
                                            </div>
                                        </DropdownLink>
                                    </form>
                                </template>
                            </Dropdown>
                        </div>
                    </div>
                </header>

                <!-- Page Content -->
                <main class="flex-1 overflow-hidden">
                    <slot />
                </main>
            </div>
        </div>
    </div>
</template>
