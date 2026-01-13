<script setup>
import { Head, Link } from '@inertiajs/vue3';
import ApplicationMark from '@/Components/ApplicationMark.vue';

defineProps({
    canLogin: {
        type: Boolean,
    },
    canRegister: {
        type: Boolean,
    },
});
</script>

<template>
    <Head title="Welcome to Confer" />

    <div class="min-h-screen bg-light-bg dark:bg-dark-bg transition-colors duration-200">
        <!-- Header -->
        <header class="fixed top-0 left-0 right-0 z-50 bg-light-surface/80 dark:bg-dark-surface/80 backdrop-blur-lg border-b border-light-border dark:border-dark-border">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16">
                <div class="flex justify-between items-center h-full">
                    <!-- Logo and Name -->
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10">
                            <ApplicationMark />
                        </div>
                        <span class="text-xl font-bold text-light-text-primary dark:text-dark-text-primary">
                            Confer
                        </span>
                    </div>

                    <!-- Navigation -->
                    <nav v-if="canLogin" class="flex items-center space-x-4">
                        <Link
                            :href="route('login')"
                            class="px-4 py-2 text-sm font-medium text-light-text-secondary dark:text-dark-text-secondary hover:text-light-accent dark:hover:text-dark-accent transition-colors"
                        >
                            Sign In
                        </Link>

                        <Link
                            v-if="canRegister"
                            :href="route('register')"
                            class="px-6 py-2 text-sm font-semibold text-white bg-light-accent dark:bg-dark-accent hover:bg-light-accent-hover dark:hover:bg-dark-accent-hover rounded-lg transition-all shadow-md hover:shadow-neon"
                        >
                            Get Started
                        </Link>
                    </nav>
                </div>
            </div>
        </header>

        <!-- Hero Section -->
        <div class="pt-32 pb-20 sm:pt-40 sm:pb-32">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center">
                    <!-- Logo -->
                    <div class="w-24 h-24 mx-auto mb-8">
                        <ApplicationMark />
                    </div>

                    <!-- Main Heading -->
                    <h1 class="text-5xl sm:text-6xl lg:text-7xl font-bold text-light-text-primary dark:text-dark-text-primary tracking-tight">
                        Secure Team
                        <span class="block bg-gradient-to-r from-light-accent to-light-primary dark:from-dark-accent dark:to-dark-primary bg-clip-text text-transparent mt-2">
                            Communication
                        </span>
                    </h1>

                    <!-- Subheading -->
                    <p class="mt-6 text-xl sm:text-2xl text-light-text-secondary dark:text-dark-text-secondary max-w-3xl mx-auto">
                        Real-time messaging, organized channels, and secure collaboration
                        for modern teams.
                    </p>

                    <!-- CTA Buttons -->
                    <div class="mt-10 flex flex-col sm:flex-row items-center justify-center gap-4">
                        <Link
                            v-if="canRegister"
                            :href="route('register')"
                            class="w-full sm:w-auto px-8 py-4 text-base font-semibold text-white bg-light-accent dark:bg-dark-accent hover:bg-light-accent-hover dark:hover:bg-dark-accent-hover rounded-lg transition-all shadow-lg hover:shadow-neon-lg"
                        >
                            Start Collaborating →
                        </Link>

                        <Link
                            v-if="canLogin"
                            :href="route('login')"
                            class="w-full sm:w-auto px-8 py-4 text-base font-semibold text-light-accent dark:text-dark-accent bg-transparent border-2 border-light-accent dark:border-dark-accent hover:bg-light-accent/10 dark:hover:bg-dark-accent/10 rounded-lg transition-all"
                        >
                            Sign In
                        </Link>
                    </div>

                    <!-- Security Notice -->
                    <p class="mt-6 text-sm text-light-text-muted dark:text-dark-text-muted flex items-center justify-center">
                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                        </svg>
                        Invite-only registration • Your data stays private
                    </p>
                </div>
            </div>
        </div>

        <!-- Features Grid -->
        <div class="py-20 bg-light-surface dark:bg-dark-surface border-t border-light-border dark:border-dark-border">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-3xl sm:text-4xl font-bold text-light-text-primary dark:text-dark-text-primary">
                        Built for Modern Teams
                    </h2>
                    <p class="mt-4 text-lg text-light-text-secondary dark:text-dark-text-secondary">
                        Everything you need to communicate effectively
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <!-- Feature 1: Real-time Messaging -->
                    <div class="bg-light-bg dark:bg-dark-bg rounded-xl p-6 border border-light-border dark:border-dark-border hover:border-light-accent dark:hover:border-dark-accent transition-all hover:shadow-lg">
                        <div class="w-12 h-12 bg-light-accent/10 dark:bg-dark-accent/10 rounded-lg flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-light-accent dark:text-dark-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-light-text-primary dark:text-dark-text-primary mb-2">
                            Real-time Messaging
                        </h3>
                        <p class="text-light-text-secondary dark:text-dark-text-secondary">
                            Instant communication with WebSocket-powered live updates. No refreshing needed.
                        </p>
                    </div>

                    <!-- Feature 2: Organized Channels -->
                    <div class="bg-light-bg dark:bg-dark-bg rounded-xl p-6 border border-light-border dark:border-dark-border hover:border-light-accent dark:hover:border-dark-accent transition-all hover:shadow-lg">
                        <div class="w-12 h-12 bg-light-accent/10 dark:bg-dark-accent/10 rounded-lg flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-light-accent dark:text-dark-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14" />
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-light-text-primary dark:text-dark-text-primary mb-2">
                            Organized Channels
                        </h3>
                        <p class="text-light-text-secondary dark:text-dark-text-secondary">
                            Public channels, private groups, and direct messages. Keep conversations organized.
                        </p>
                    </div>

                    <!-- Feature 3: Secure & Private -->
                    <div class="bg-light-bg dark:bg-dark-bg rounded-xl p-6 border border-light-border dark:border-dark-border hover:border-light-accent dark:hover:border-dark-accent transition-all hover:shadow-lg">
                        <div class="w-12 h-12 bg-light-accent/10 dark:bg-dark-accent/10 rounded-lg flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-light-accent dark:text-dark-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-light-text-primary dark:text-dark-text-primary mb-2">
                            Secure & Private
                        </h3>
                        <p class="text-light-text-secondary dark:text-dark-text-secondary">
                            Invite-only workspaces with encrypted data storage. Your conversations stay private.
                        </p>
                    </div>

                    <!-- Feature 4: Full-Text Search -->
                    <div class="bg-light-bg dark:bg-dark-bg rounded-xl p-6 border border-light-border dark:border-dark-border hover:border-light-accent dark:hover:border-dark-accent transition-all hover:shadow-lg">
                        <div class="w-12 h-12 bg-light-accent/10 dark:bg-dark-accent/10 rounded-lg flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-light-accent dark:text-dark-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-light-text-primary dark:text-dark-text-primary mb-2">
                            Powerful Search
                        </h3>
                        <p class="text-light-text-secondary dark:text-dark-text-secondary">
                            Find any message or file instantly with full-text search across your workspace.
                        </p>
                    </div>

                    <!-- Feature 5: File Sharing -->
                    <div class="bg-light-bg dark:bg-dark-bg rounded-xl p-6 border border-light-border dark:border-dark-border hover:border-light-accent dark:hover:border-dark-accent transition-all hover:shadow-lg">
                        <div class="w-12 h-12 bg-light-accent/10 dark:bg-dark-accent/10 rounded-lg flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-light-accent dark:text-dark-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-light-text-primary dark:text-dark-text-primary mb-2">
                            File Sharing
                        </h3>
                        <p class="text-light-text-secondary dark:text-dark-text-secondary">
                            Share documents, images, and files securely with image previews and downloads.
                        </p>
                    </div>

                    <!-- Feature 6: Thread Conversations -->
                    <div class="bg-light-bg dark:bg-dark-bg rounded-xl p-6 border border-light-border dark:border-dark-border hover:border-light-accent dark:hover:border-dark-accent transition-all hover:shadow-lg">
                        <div class="w-12 h-12 bg-light-accent/10 dark:bg-dark-accent/10 rounded-lg flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-light-accent dark:text-dark-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-light-text-primary dark:text-dark-text-primary mb-2">
                            Threaded Replies
                        </h3>
                        <p class="text-light-text-secondary dark:text-dark-text-secondary">
                            Keep conversations organized with threaded replies. Focus on what matters.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="bg-light-surface dark:bg-dark-surface border-t border-light-border dark:border-dark-border">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                <div class="flex flex-col items-center">
                    <div class="flex items-center space-x-3 mb-4">
                        <div class="w-8 h-8">
                            <ApplicationMark />
                        </div>
                        <span class="text-lg font-bold text-light-text-primary dark:text-dark-text-primary">
                            Confer
                        </span>
                    </div>
                    <p class="text-light-text-secondary dark:text-dark-text-secondary text-center">
                        Secure team communication platform
                    </p>
                    <p class="mt-2 text-sm text-light-text-muted dark:text-dark-text-muted">
                        &copy; {{ new Date().getFullYear() }} Confer. All rights reserved.
                    </p>
                </div>
            </div>
        </footer>
    </div>
</template>
