<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import ApplicationMark from '@/Components/ApplicationMark.vue';
import Banner from '@/Components/Banner.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';

defineProps({
    title: String,
});

const page = usePage();
const showingNavigationDropdown = ref(false);
const currentWorkspace = computed(() => page.props.currentWorkspace);

const isAdmin = computed(() => {
    const workspace = page.props.currentWorkspace;
    if (!workspace) return false;
    const member = workspace.members?.find(m => m.user_id === page.props.auth.user.id);
    return member?.role === 'owner' || member?.role === 'admin';
});

const logout = () => {
    router.post(route('logout'));
};
</script>

<template>
    <div>
        <Head :title="title" />

        <Banner />

        <div class="min-h-screen bg-light-bg dark:bg-dark-bg transition-colors duration-200">
            <nav class="bg-light-surface dark:bg-dark-surface border-b border-light-border dark:border-dark-border">
                <!-- Primary Navigation Menu -->
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between h-16">
                        <div class="flex">
                            <!-- Logo -->
                            <div class="shrink-0 flex items-center">
                                <Link :href="route('dashboard')">
                                    <ApplicationMark class="block h-9 w-auto" />
                                </Link>
                            </div>

                            <!-- Navigation Links -->
                            <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                                <NavLink :href="route('dashboard')" :active="route().current('dashboard')">
                                    Dashboard
                                </NavLink>
                                <NavLink :href="route('analytics.index')" :active="route().current('analytics.index')">
                                    Analytics
                                </NavLink>
                            </div>
                        </div>

                        <div class="hidden sm:flex sm:items-center sm:ms-6">
                            <!-- Theme Toggle -->
                            <ThemeToggle />

                            <!-- Settings Dropdown -->
                            <div class="ms-3 relative">
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
                                        <!-- Account Management -->
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

                                        <DropdownLink v-if="$page.props.jetstream.hasApiFeatures" :href="route('api-tokens.index')">
                                            <div class="flex items-center">
                                                <svg class="h-4 w-4 mr-2 text-light-accent dark:text-dark-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                                </svg>
                                                API Tokens
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

                                        <!-- Authentication -->
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

                        <!-- Hamburger -->
                        <div class="-me-2 flex items-center sm:hidden">
                            <button class="inline-flex items-center justify-center p-2 rounded-md text-light-text-muted dark:text-dark-text-muted hover:text-light-text-primary dark:hover:text-dark-text-primary hover:bg-light-bg dark:hover:bg-dark-bg focus:outline-none focus:bg-light-bg dark:focus:bg-dark-bg focus:text-light-text-primary dark:focus:text-dark-text-primary transition duration-150 ease-in-out" @click="showingNavigationDropdown = ! showingNavigationDropdown">
                                <svg
                                    class="size-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        :class="{'hidden': showingNavigationDropdown, 'inline-flex': ! showingNavigationDropdown }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{'hidden': ! showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Responsive Navigation Menu -->
                <div :class="{'block': showingNavigationDropdown, 'hidden': ! showingNavigationDropdown}" class="sm:hidden">
                    <div class="pt-2 pb-3 space-y-1">
                        <ResponsiveNavLink :href="route('dashboard')" :active="route().current('dashboard')">
                            Dashboard
                        </ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('analytics.index')" :active="route().current('analytics.index')">
                            Analytics
                        </ResponsiveNavLink>
                    </div>

                    <!-- Responsive Settings Options -->
                    <div class="pt-4 pb-1 border-t border-light-border dark:border-dark-border">
                        <div class="flex items-center px-4">
                            <div class="shrink-0 me-3">
                                <div v-if="$page.props.auth.user.profile_photo_url" class="h-10 w-10 rounded-full overflow-hidden">
                                    <img :src="$page.props.auth.user.profile_photo_url" :alt="$page.props.auth.user.name" class="w-full h-full object-cover">
                                </div>
                                <div v-else class="h-10 w-10 rounded-full bg-light-accent dark:bg-dark-accent flex items-center justify-center text-white font-semibold">
                                    {{ $page.props.auth.user.name.charAt(0).toUpperCase() }}
                                </div>
                            </div>

                            <div>
                                <div class="font-medium text-base text-light-text-primary dark:text-dark-text-primary">
                                    {{ $page.props.auth.user.name }}
                                </div>
                                <div class="font-medium text-sm text-light-text-secondary dark:text-dark-text-secondary">
                                    {{ $page.props.auth.user.email }}
                                </div>
                            </div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <!-- Theme Toggle for Mobile -->
                            <div class="px-4 py-2">
                                <ThemeToggle />
                            </div>

                            <ResponsiveNavLink :href="route('profile.show')" :active="route().current('profile.show')">
                                Profile
                            </ResponsiveNavLink>

                            <ResponsiveNavLink v-if="$page.props.jetstream.hasApiFeatures" :href="route('api-tokens.index')" :active="route().current('api-tokens.index')">
                                API Tokens
                            </ResponsiveNavLink>

                            <ResponsiveNavLink :href="route('analytics.index')" :active="route().current('analytics.index')">
                                Analytics
                            </ResponsiveNavLink>

                            <ResponsiveNavLink v-if="isAdmin" :href="`/admin/workspaces/${currentWorkspace?.id}/overview`">
                                Admin Panel
                            </ResponsiveNavLink>

                            <!-- Authentication -->
                            <form method="POST" @submit.prevent="logout">
                                <ResponsiveNavLink as="button">
                                    Log Out
                                </ResponsiveNavLink>
                            </form>

                        </div>
                    </div>
                </div>
            </nav>

            <!-- Page Heading -->
            <header v-if="$slots.header" class="bg-light-surface dark:bg-dark-surface shadow">
                <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main>
                <slot />
            </main>
        </div>
    </div>
</template>
