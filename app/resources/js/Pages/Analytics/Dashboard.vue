<script setup>
import { ref, computed, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';

const loading = ref(true);
const stats = ref(null);
const dateRange = ref({
    start: new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
    end: new Date().toISOString().split('T')[0],
});

const fetchAnalytics = async () => {
    loading.value = true;
    try {
        const response = await window.axios.get('/api/analytics/dashboard', {
            params: {
                start_date: dateRange.value.start,
                end_date: dateRange.value.end,
            },
        });
        console.log('Analytics response:', response.data);
        console.log('Client usage data:', response.data.client_usage);
        console.log('Client usage by_client:', response.data.client_usage?.by_client);
        console.log('Client usage by_client length:', response.data.client_usage?.by_client?.length);
        stats.value = response.data;
    } catch (error) {
        console.error('Failed to fetch analytics:', error);
        console.error('Error details:', error.response?.data);
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchAnalytics();
});

const formatNumber = (num) => {
    return new Intl.NumberFormat().format(num || 0);
};

const formatBytes = (bytes) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
};

const formatDuration = (seconds) => {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    if (hours > 0) {
        return `${hours}h ${minutes}m`;
    }
    return `${minutes}m`;
};

const updateDateRange = () => {
    fetchAnalytics();
};
</script>

<template>
    <AppLayout title="Analytics Dashboard">
        <Head title="Analytics Dashboard" />

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-light-text-primary dark:text-dark-text-primary">
                    Analytics Dashboard
                </h1>
                <p class="mt-2 text-light-text-secondary dark:text-dark-text-secondary">
                    Comprehensive insights into workspace usage and activity
                </p>
            </div>

            <!-- Date Range Picker -->
            <div class="mb-8 bg-light-surface dark:bg-dark-surface rounded-lg shadow-md p-4">
                <div class="flex items-center space-x-4">
                    <div>
                        <label class="block text-sm font-medium text-light-text-secondary dark:text-dark-text-secondary mb-1">
                            Start Date
                        </label>
                        <input
                            type="date"
                            v-model="dateRange.start"
                            @change="updateDateRange"
                            class="px-3 py-2 rounded-md border border-light-border dark:border-dark-border bg-light-bg dark:bg-dark-bg text-light-text-primary dark:text-dark-text-primary shadow-sm focus:border-light-accent dark:focus:border-dark-accent focus:ring-2 focus:ring-light-accent/20 dark:focus:ring-dark-accent/20 focus:outline-none"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-light-text-secondary dark:text-dark-text-secondary mb-1">
                            End Date
                        </label>
                        <input
                            type="date"
                            v-model="dateRange.end"
                            @change="updateDateRange"
                            class="px-3 py-2 rounded-md border border-light-border dark:border-dark-border bg-light-bg dark:bg-dark-bg text-light-text-primary dark:text-dark-text-primary shadow-sm focus:border-light-accent dark:focus:border-dark-accent focus:ring-2 focus:ring-light-accent/20 dark:focus:ring-dark-accent/20 focus:outline-none"
                        />
                    </div>
                    <button
                        @click="updateDateRange"
                        class="mt-6 px-4 py-2 bg-light-accent dark:bg-dark-accent text-white rounded-md hover:bg-light-accent-hover dark:hover:bg-dark-accent-hover transition-colors"
                    >
                        Refresh
                    </button>
                </div>
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="flex justify-center items-center py-20">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-light-accent dark:border-dark-accent"></div>
            </div>

            <!-- Error State -->
            <div v-else-if="!stats" class="bg-light-surface dark:bg-dark-surface rounded-lg shadow-md p-12 text-center">
                <svg class="mx-auto h-16 w-16 text-light-text-muted dark:text-dark-text-muted mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                <h3 class="text-lg font-medium text-light-text-primary dark:text-dark-text-primary mb-2">
                    Failed to Load Analytics
                </h3>
                <p class="text-light-text-secondary dark:text-dark-text-secondary mb-4">
                    Unable to fetch analytics data. Please check the browser console for details.
                </p>
                <button
                    @click="fetchAnalytics"
                    class="px-4 py-2 bg-light-accent dark:bg-dark-accent text-white rounded-md hover:bg-light-accent-hover dark:hover:bg-dark-accent-hover transition-colors"
                >
                    Retry
                </button>
            </div>

            <!-- Analytics Content -->
            <div v-else class="space-y-8">
                <!-- Overview Cards -->
                <div>
                    <h2 class="text-2xl font-semibold text-light-text-primary dark:text-dark-text-primary mb-4">
                        Overview
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                        <div class="bg-light-surface dark:bg-dark-surface rounded-lg shadow-md p-6">
                            <div class="text-light-text-muted dark:text-dark-text-muted text-sm font-medium">Total Users</div>
                            <div class="mt-2 text-3xl font-bold text-light-text-primary dark:text-dark-text-primary">
                                {{ formatNumber(stats.overview.total_users) }}
                            </div>
                        </div>
                        <div class="bg-light-surface dark:bg-dark-surface rounded-lg shadow-md p-6">
                            <div class="text-light-text-muted dark:text-dark-text-muted text-sm font-medium">Total Messages</div>
                            <div class="mt-2 text-3xl font-bold text-light-text-primary dark:text-dark-text-primary">
                                {{ formatNumber(stats.overview.total_messages) }}
                            </div>
                        </div>
                        <div class="bg-light-surface dark:bg-dark-surface rounded-lg shadow-md p-6">
                            <div class="text-light-text-muted dark:text-dark-text-muted text-sm font-medium">Conversations</div>
                            <div class="mt-2 text-3xl font-bold text-light-text-primary dark:text-dark-text-primary">
                                {{ formatNumber(stats.overview.total_conversations) }}
                            </div>
                        </div>
                        <div class="bg-light-surface dark:bg-dark-surface rounded-lg shadow-md p-6">
                            <div class="text-light-text-muted dark:text-dark-text-muted text-sm font-medium">Files Uploaded</div>
                            <div class="mt-2 text-3xl font-bold text-light-text-primary dark:text-dark-text-primary">
                                {{ formatNumber(stats.overview.total_files) }}
                            </div>
                        </div>
                        <div class="bg-light-surface dark:bg-dark-surface rounded-lg shadow-md p-6">
                            <div class="text-light-text-muted dark:text-dark-text-muted text-sm font-medium">Active Today</div>
                            <div class="mt-2 text-3xl font-bold text-light-accent dark:text-dark-accent">
                                {{ formatNumber(stats.overview.active_users_today) }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- User Statistics -->
                <div>
                    <h2 class="text-2xl font-semibold text-light-text-primary dark:text-dark-text-primary mb-4">
                        User Activity
                    </h2>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Average Session Duration -->
                        <div class="bg-light-surface dark:bg-dark-surface rounded-lg shadow-md p-6">
                            <h3 class="text-lg font-semibold text-light-text-primary dark:text-dark-text-primary mb-2">
                                Average Session Duration
                            </h3>
                            <div class="text-4xl font-bold text-light-accent dark:text-dark-accent">
                                {{ formatDuration(stats.users.avg_session_duration) }}
                            </div>
                        </div>

                        <!-- Most Active Users -->
                        <div class="bg-light-surface dark:bg-dark-surface rounded-lg shadow-md p-6">
                            <h3 class="text-lg font-semibold text-light-text-primary dark:text-dark-text-primary mb-4">
                                Most Active Users
                            </h3>
                            <div class="space-y-2">
                                <div v-for="user in stats.users.most_active_users.slice(0, 5)" :key="user.user_id" class="flex justify-between items-center">
                                    <span class="text-light-text-primary dark:text-dark-text-primary">{{ user.user?.name || 'Unknown' }}</span>
                                    <span class="text-light-text-muted dark:text-dark-text-muted">{{ formatNumber(user.message_count) }} messages</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Message Statistics -->
                <div>
                    <h2 class="text-2xl font-semibold text-light-text-primary dark:text-dark-text-primary mb-4">
                        Message Activity
                    </h2>
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <div class="bg-light-surface dark:bg-dark-surface rounded-lg shadow-md p-6">
                            <h3 class="text-sm font-medium text-light-text-muted dark:text-dark-text-muted">Total Messages</h3>
                            <div class="mt-2 text-3xl font-bold text-light-text-primary dark:text-dark-text-primary">
                                {{ formatNumber(stats.messages.total_messages) }}
                            </div>
                            <p class="mt-1 text-sm text-light-text-muted dark:text-dark-text-muted">in selected period</p>
                        </div>
                        <div class="bg-light-surface dark:bg-dark-surface rounded-lg shadow-md p-6">
                            <h3 class="text-sm font-medium text-light-text-muted dark:text-dark-text-muted">Avg per Day</h3>
                            <div class="mt-2 text-3xl font-bold text-light-text-primary dark:text-dark-text-primary">
                                {{ formatNumber(stats.messages.avg_messages_per_day) }}
                            </div>
                            <p class="mt-1 text-sm text-light-text-muted dark:text-dark-text-muted">messages/day</p>
                        </div>
                        <div class="bg-light-surface dark:bg-dark-surface rounded-lg shadow-md p-6">
                            <h3 class="text-sm font-medium text-light-text-muted dark:text-dark-text-muted">Peak Hour</h3>
                            <div class="mt-2 text-3xl font-bold text-light-text-primary dark:text-dark-text-primary">
                                {{ stats.messages.messages_by_hour.reduce((max, h) => h.count > max.count ? h : max, {count: 0, hour: 0}).hour }}:00
                            </div>
                            <p class="mt-1 text-sm text-light-text-muted dark:text-dark-text-muted">most active time</p>
                        </div>
                    </div>
                </div>

                <!-- Top Channels -->
                <div>
                    <h2 class="text-2xl font-semibold text-light-text-primary dark:text-dark-text-primary mb-4">
                        Top Channels
                    </h2>
                    <div class="bg-light-surface dark:bg-dark-surface rounded-lg shadow-md p-6">
                        <div v-if="stats.conversations.top_channels && stats.conversations.top_channels.length > 0" class="space-y-3">
                            <div v-for="channel in stats.conversations.top_channels" :key="channel.id" class="flex justify-between items-center pb-3 border-b border-light-border dark:border-dark-border last:border-0">
                                <div class="flex items-center space-x-3">
                                    <span class="text-2xl">#</span>
                                    <span class="font-medium text-light-text-primary dark:text-dark-text-primary">{{ channel.name }}</span>
                                </div>
                                <span class="text-light-text-muted dark:text-dark-text-muted">
                                    {{ formatNumber(channel.messages_count) }} messages
                                </span>
                            </div>
                        </div>
                        <div v-else class="text-center py-4 text-light-text-muted dark:text-dark-text-muted">
                            No channel activity in selected period
                        </div>
                    </div>
                </div>

                <!-- DM Statistics -->
                <div>
                    <h2 class="text-2xl font-semibold text-light-text-primary dark:text-dark-text-primary mb-4">
                        Direct Messages
                    </h2>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="bg-light-surface dark:bg-dark-surface rounded-lg shadow-md p-6">
                            <h3 class="text-sm font-medium text-light-text-muted dark:text-dark-text-muted">Total DMs</h3>
                            <div class="mt-2 text-3xl font-bold text-light-text-primary dark:text-dark-text-primary">
                                {{ formatNumber(stats.conversations.total_dms) }}
                            </div>
                            <p class="mt-1 text-sm text-light-text-muted dark:text-dark-text-muted">
                                {{ formatNumber(stats.conversations.dm_message_count) }} messages sent
                            </p>
                        </div>
                        <div class="bg-light-surface dark:bg-dark-surface rounded-lg shadow-md p-6">
                            <h3 class="text-lg font-semibold text-light-text-primary dark:text-dark-text-primary mb-4">
                                Most DMs
                            </h3>
                            <div class="space-y-2">
                                <div v-for="user in stats.conversations.users_by_dm_count.slice(0, 5)" :key="user.id" class="flex justify-between items-center">
                                    <span class="text-light-text-primary dark:text-dark-text-primary">{{ user.name }}</span>
                                    <span class="text-light-text-muted dark:text-dark-text-muted">{{ user.dm_count }} DMs</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- File Statistics -->
                <div>
                    <h2 class="text-2xl font-semibold text-light-text-primary dark:text-dark-text-primary mb-4">
                        File Activity
                    </h2>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="bg-light-surface dark:bg-dark-surface rounded-lg shadow-md p-6">
                            <h3 class="text-sm font-medium text-light-text-muted dark:text-dark-text-muted mb-4">File Stats</h3>
                            <div class="space-y-3">
                                <div class="flex justify-between">
                                    <span class="text-light-text-primary dark:text-dark-text-primary">Total Files</span>
                                    <span class="font-semibold text-light-text-primary dark:text-dark-text-primary">{{ formatNumber(stats.files.total_files) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-light-text-primary dark:text-dark-text-primary">Total Storage</span>
                                    <span class="font-semibold text-light-text-primary dark:text-dark-text-primary">{{ stats.files.total_size_mb }} MB</span>
                                </div>
                            </div>
                        </div>
                        <div class="bg-light-surface dark:bg-dark-surface rounded-lg shadow-md p-6">
                            <h3 class="text-sm font-medium text-light-text-muted dark:text-dark-text-muted mb-4">By Type</h3>
                            <div class="space-y-2">
                                <div v-for="type in stats.files.files_by_type" :key="type.type" class="flex justify-between items-center">
                                    <span class="text-light-text-primary dark:text-dark-text-primary">{{ type.type }}</span>
                                    <span class="text-light-text-muted dark:text-dark-text-muted">{{ formatNumber(type.count) }} ({{ formatBytes(type.total_size) }})</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Client Usage -->
                <div>
                    <h2 class="text-2xl font-semibold text-light-text-primary dark:text-dark-text-primary mb-4">
                        Client Usage
                    </h2>
                    <div class="bg-light-surface dark:bg-dark-surface rounded-lg shadow-md p-6">
                        <div v-if="stats.client_usage.by_client && stats.client_usage.by_client.length > 0" class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div v-for="client in stats.client_usage.by_client" :key="client.client_type" class="text-center">
                                <div class="text-4xl mb-2">
                                    <span v-if="client.client_type === 'web'">🌐</span>
                                    <span v-else-if="client.client_type === 'mobile'">📱</span>
                                    <span v-else-if="client.client_type === 'tui'">💻</span>
                                    <span v-else>❓</span>
                                </div>
                                <div class="text-2xl font-bold text-light-text-primary dark:text-dark-text-primary">
                                    {{ formatNumber(client.unique_users) }}
                                </div>
                                <div class="text-sm text-light-text-muted dark:text-dark-text-muted capitalize">
                                    {{ client.client_type || 'Unknown' }} Users
                                </div>
                                <div class="text-xs text-light-text-muted dark:text-dark-text-muted mt-1">
                                    {{ formatNumber(client.session_count) }} sessions
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-4 text-light-text-muted dark:text-dark-text-muted">
                            No client usage data available
                        </div>
                    </div>
                </div>

                <!-- System Stats -->
                <div>
                    <h2 class="text-2xl font-semibold text-light-text-primary dark:text-dark-text-primary mb-4">
                        System Information
                    </h2>
                    <div class="bg-light-surface dark:bg-dark-surface rounded-lg shadow-md p-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <div class="text-sm text-light-text-muted dark:text-dark-text-muted">System Age</div>
                                <div class="mt-1 text-xl font-semibold text-light-text-primary dark:text-dark-text-primary">
                                    {{ stats.system.system_age }}
                                </div>
                            </div>
                            <div>
                                <div class="text-sm text-light-text-muted dark:text-dark-text-muted">Database Size</div>
                                <div class="mt-1 text-xl font-semibold text-light-text-primary dark:text-dark-text-primary">
                                    {{ stats.system.database_size }}
                                </div>
                            </div>
                            <div>
                                <div class="text-sm text-light-text-muted dark:text-dark-text-muted">Cache Status</div>
                                <div class="mt-1 text-xl font-semibold text-light-text-primary dark:text-dark-text-primary">
                                    {{ stats.system.cache_enabled ? '✅ Enabled' : '❌ Disabled' }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
