<script setup>
import { ref, computed, watch } from 'vue';
import { router, usePage, Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const page = usePage();
const searchQuery = ref('');
const results = ref([]);
const loading = ref(false);
const nextCursor = ref(null);
const hasMore = ref(false);
const activeFilters = ref([]);

// Debounce timer
let searchTimeout = null;

// Parse active filters from query
const parseFilters = (query) => {
    const filters = [];
    const filterRegex = /(in|from|has|since|until|before|after|on):([^\s]+)/gi;
    let match;

    while ((match = filterRegex.exec(query)) !== null) {
        filters.push({
            type: match[1].toLowerCase(),
            value: match[2],
            full: match[0]
        });
    }

    return filters;
};

// Perform search
const performSearch = async (cursor = null) => {
    if (!searchQuery.value.trim()) {
        results.value = [];
        activeFilters.value = [];
        return;
    }

    loading.value = true;

    try {
        const params = {
            q: searchQuery.value,
            limit: 20
        };

        if (cursor) {
            params.cursor = cursor;
        }

        const response = await window.axios.get('/api/search', { params });

        if (cursor) {
            // Append to existing results
            results.value = [...results.value, ...response.data.results];
        } else {
            // New search
            results.value = response.data.results;
            activeFilters.value = parseFilters(searchQuery.value);
        }

        nextCursor.value = response.data.next_cursor;
        hasMore.value = response.data.has_more;
    } catch (error) {
        console.error('Search failed:', error);
        results.value = [];
    } finally {
        loading.value = false;
    }
};

// Watch for query changes with debounce
watch(searchQuery, (newQuery) => {
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }

    searchTimeout = setTimeout(() => {
        performSearch();
    }, 300);
});

// Load more results
const loadMore = () => {
    if (hasMore.value && nextCursor.value && !loading.value) {
        performSearch(nextCursor.value);
    }
};

// Add filter to query
const addFilter = (filterType, filterValue) => {
    const filterString = `${filterType}:${filterValue}`;

    if (!searchQuery.value.includes(filterString)) {
        searchQuery.value = searchQuery.value.trim() + ' ' + filterString;
    }
};

// Remove filter from query
const removeFilter = (filter) => {
    searchQuery.value = searchQuery.value.replace(filter.full, '').trim();
};

// Navigate to message
const goToMessage = (result) => {
    router.visit(route('web.conversations.show', result.conversation_id), {
        data: { message_id: result.id }
    });
};

// Format timestamp
const formatTime = (timestamp) => {
    const date = new Date(timestamp);
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit'
    });
};

// Highlight search terms in text
const highlightText = (text, maxLength = 200) => {
    if (!text) return '';

    // Truncate if needed
    let truncated = text.length > maxLength
        ? text.substring(0, maxLength) + '...'
        : text;

    return truncated;
};

// Quick filter buttons
const quickFilters = [
    { label: 'Has file', value: 'has:file' },
    { label: 'Has link', value: 'has:link' },
    { label: 'Has code', value: 'has:code' },
    { label: 'Today', value: `on:${new Date().toISOString().split('T')[0]}` },
    { label: 'This week', value: `since:${new Date(Date.now() - 7*24*60*60*1000).toISOString().split('T')[0]}` },
];
</script>

<template>
    <AppLayout title="Search">
        <Head title="Search" />

        <div class="h-screen flex flex-col">
            <!-- Search Header -->
            <div class="bg-light-surface dark:bg-dark-surface border-b border-light-border dark:border-dark-border px-6 py-4">
                <div class="max-w-4xl mx-auto">
                    <h1 class="text-2xl font-bold text-light-text-primary dark:text-dark-text-primary mb-4">
                        Search Messages
                    </h1>

                    <!-- Search Input -->
                    <div class="relative">
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Search messages... (e.g., bug fix from:@john in:#engineering has:file)"
                            class="w-full pl-10 pr-4 py-3 bg-light-bg dark:bg-dark-bg border-light-border dark:border-dark-border text-light-text-primary dark:text-dark-text-primary rounded-lg shadow-sm focus:border-light-accent dark:focus:border-dark-accent focus:ring-2 focus:ring-light-accent/20 dark:focus:ring-dark-accent/20 transition-colors"
                        />
                        <svg class="absolute left-3 top-3.5 h-5 w-5 text-light-text-muted dark:text-dark-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <div v-if="loading" class="absolute right-3 top-3.5">
                            <svg class="animate-spin h-5 w-5 text-light-accent dark:text-dark-accent" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </div>
                    </div>

                    <!-- Quick Filters -->
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button
                            v-for="filter in quickFilters"
                            :key="filter.value"
                            @click="addFilter(filter.value.split(':')[0], filter.value.split(':')[1])"
                            class="px-3 py-1 text-xs font-medium bg-light-bg dark:bg-dark-bg border border-light-border dark:border-dark-border rounded-full hover:border-light-accent dark:hover:border-dark-accent text-light-text-secondary dark:text-dark-text-secondary hover:text-light-accent dark:hover:text-dark-accent transition-colors"
                        >
                            {{ filter.label }}
                        </button>
                    </div>

                    <!-- Active Filters -->
                    <div v-if="activeFilters.length > 0" class="mt-3 flex flex-wrap gap-2">
                        <span class="text-xs text-light-text-muted dark:text-dark-text-muted">Active filters:</span>
                        <button
                            v-for="filter in activeFilters"
                            :key="filter.full"
                            @click="removeFilter(filter)"
                            class="inline-flex items-center px-2 py-1 text-xs font-medium bg-light-accent/10 dark:bg-dark-accent/10 border border-light-accent dark:border-dark-accent text-light-accent dark:text-dark-accent rounded-full hover:bg-light-accent/20 dark:hover:bg-dark-accent/20 transition-colors"
                        >
                            <span class="font-semibold">{{ filter.type }}:</span>
                            <span class="ml-1">{{ filter.value }}</span>
                            <svg class="ml-1 h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Search Help -->
                    <div class="mt-3 text-xs text-light-text-muted dark:text-dark-text-muted">
                        <strong>Search tips:</strong>
                        Use <code class="px-1 py-0.5 bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded">in:#channel</code>
                        <code class="px-1 py-0.5 bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded">from:@user</code>
                        <code class="px-1 py-0.5 bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded">has:file</code>
                        <code class="px-1 py-0.5 bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded">before:2024-12-01</code>
                        <code class="px-1 py-0.5 bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded">"exact phrase"</code>
                    </div>
                </div>
            </div>

            <!-- Search Results -->
            <div class="flex-1 overflow-y-auto bg-light-bg dark:bg-dark-bg">
                <div class="max-w-4xl mx-auto px-6 py-6">
                    <!-- No query state -->
                    <div v-if="!searchQuery.trim()" class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-light-text-muted dark:text-dark-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <h3 class="mt-4 text-lg font-medium text-light-text-primary dark:text-dark-text-primary">
                            Search your messages
                        </h3>
                        <p class="mt-2 text-sm text-light-text-muted dark:text-dark-text-muted">
                            Use the search bar above to find messages across all your conversations
                        </p>
                    </div>

                    <!-- No results state -->
                    <div v-else-if="!loading && results.length === 0 && searchQuery.trim()" class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-light-text-muted dark:text-dark-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <h3 class="mt-4 text-lg font-medium text-light-text-primary dark:text-dark-text-primary">
                            No results found
                        </h3>
                        <p class="mt-2 text-sm text-light-text-muted dark:text-dark-text-muted">
                            Try different keywords or remove some filters
                        </p>
                    </div>

                    <!-- Results List -->
                    <div v-else class="space-y-4">
                        <div
                            v-for="result in results"
                            :key="result.id"
                            @click="goToMessage(result)"
                            class="bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded-lg p-4 hover:border-light-accent dark:hover:border-dark-accent hover:shadow-neon cursor-pointer transition-all"
                        >
                            <!-- Header -->
                            <div class="flex items-start justify-between mb-2">
                                <div class="flex items-center space-x-2">
                                    <span class="text-sm font-semibold text-light-text-primary dark:text-dark-text-primary">
                                        {{ result.user.name }}
                                    </span>
                                    <span class="text-xs text-light-text-muted dark:text-dark-text-muted">
                                        in
                                    </span>
                                    <span class="text-sm font-medium text-light-accent dark:text-dark-accent">
                                        #{{ result.conversation_name }}
                                    </span>
                                </div>
                                <span class="text-xs text-light-text-muted dark:text-dark-text-muted">
                                    {{ formatTime(result.created_at) }}
                                </span>
                            </div>

                            <!-- Message Content -->
                            <div class="text-sm text-light-text-primary dark:text-dark-text-primary">
                                {{ highlightText(result.body_md) }}
                            </div>

                            <!-- Snippet (if available from PostgreSQL) -->
                            <div v-if="result.snippet" class="mt-2 text-sm text-light-text-secondary dark:text-dark-text-secondary italic border-l-2 border-light-accent dark:border-dark-accent pl-3">
                                <span v-html="result.snippet"></span>
                            </div>

                            <!-- Edited indicator -->
                            <div v-if="result.edited_at" class="mt-2">
                                <span class="text-xs text-light-text-muted dark:text-dark-text-muted">
                                    (edited {{ formatTime(result.edited_at) }})
                                </span>
                            </div>
                        </div>

                        <!-- Load More Button -->
                        <div v-if="hasMore" class="text-center py-4">
                            <button
                                @click="loadMore"
                                :disabled="loading"
                                class="px-6 py-2 bg-light-accent dark:bg-dark-accent text-white rounded-lg hover:bg-light-accent-hover dark:hover:bg-dark-accent-hover shadow-neon hover:shadow-neon-lg disabled:opacity-50 transition-all font-medium"
                            >
                                {{ loading ? 'Loading...' : 'Load More' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
