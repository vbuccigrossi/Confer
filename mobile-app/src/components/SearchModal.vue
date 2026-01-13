<template>
  <ion-modal :is-open="isOpen" @didDismiss="$emit('close')">
    <ion-header>
      <ion-toolbar>
        <ion-buttons slot="start">
          <ion-button @click="$emit('close')">
            <ion-icon :icon="arrowBackOutline"></ion-icon>
          </ion-button>
        </ion-buttons>
        <ion-searchbar
          v-model="searchQuery"
          placeholder="Search messages..."
          :debounce="300"
          @ionInput="handleSearch"
          animated
        ></ion-searchbar>
      </ion-toolbar>
    </ion-header>

    <ion-content>
      <!-- Search Results -->
      <ion-list v-if="searchResults.length > 0" role="listbox" aria-label="Search results">
        <ion-item
          v-for="result in searchResults"
          :key="result.id || Math.random()"
          button
          role="option"
          @click="$emit('select-message', result)"
        >
          <div class="search-result">
            <div class="result-header">
              <span class="result-user">{{ result.user?.name || 'Unknown' }}</span>
              <span class="result-time">{{ formatTime(result.created_at) }}</span>
            </div>
            <div class="result-content" v-html="highlightMatch(result.body_md)"></div>
          </div>
        </ion-item>
      </ion-list>

      <!-- Results count for screen readers -->
      <div v-if="searchResults.length > 0" role="status" aria-live="polite" class="sr-only">
        {{ searchResults.length }} results found
      </div>

      <!-- No Results -->
      <div v-else-if="searchQuery && !isSearching" class="no-results">
        <ion-icon :icon="searchOutline" class="no-results-icon"></ion-icon>
        <p>No messages found for "{{ searchQuery }}"</p>
      </div>

      <!-- Search Prompt -->
      <div v-else-if="!searchQuery" class="search-prompt">
        <ion-icon :icon="searchOutline" class="prompt-icon"></ion-icon>
        <p>Search for messages in this conversation</p>
      </div>

      <!-- Loading -->
      <div v-if="isSearching" class="ion-padding ion-text-center">
        <ion-spinner></ion-spinner>
      </div>
    </ion-content>
  </ion-modal>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import {
  IonModal,
  IonHeader,
  IonToolbar,
  IonButtons,
  IonButton,
  IonSearchbar,
  IonContent,
  IonList,
  IonItem,
  IonIcon,
  IonSpinner,
} from '@ionic/vue';
import { arrowBackOutline, searchOutline } from 'ionicons/icons';
import DOMPurify from 'dompurify';

interface Props {
  isOpen: boolean;
}

const props = defineProps<Props>();
const emit = defineEmits(['close', 'search', 'select-message']);

const searchQuery = ref('');
const searchResults = ref<any[]>([]);
const isSearching = ref(false);

// Reset when modal closes
watch(() => props.isOpen, (newVal) => {
  if (!newVal) {
    searchQuery.value = '';
    searchResults.value = [];
  }
});

function handleSearch() {
  if (!searchQuery.value.trim()) {
    searchResults.value = [];
    return;
  }

  isSearching.value = true;
  emit('search', searchQuery.value);
}

// Exposed method for parent to set results
defineExpose({
  setResults: (results: any[]) => {
    searchResults.value = results;
    isSearching.value = false;
  },
  setLoading: (loading: boolean) => {
    isSearching.value = loading;
  }
});

function formatTime(timestamp: string): string {
  if (!timestamp) return '';
  const date = new Date(timestamp);
  const now = new Date();
  const diffMs = now.getTime() - date.getTime();
  const diffMins = Math.floor(diffMs / 60000);
  const diffHours = Math.floor(diffMins / 60);
  const diffDays = Math.floor(diffHours / 24);

  if (diffMins < 1) return 'Just now';
  if (diffMins < 60) return `${diffMins}m ago`;
  if (diffHours < 24) return `${diffHours}h ago`;
  if (diffDays < 7) return `${diffDays}d ago`;

  return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

// Escape special regex characters to prevent regex injection
function escapeRegex(str: string): string {
  return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function highlightMatch(text: string): string {
  if (!searchQuery.value || !text) return DOMPurify.sanitize(text || '');

  // Escape the search query to prevent regex injection
  const escapedQuery = escapeRegex(searchQuery.value);
  const regex = new RegExp(`(${escapedQuery})`, 'gi');
  // First sanitize the text, then highlight matches
  const sanitized = DOMPurify.sanitize(text);
  return sanitized.replace(regex, '<mark>$1</mark>');
}
</script>

<style scoped>
.search-result {
  width: 100%;
  padding: 8px 0;
}

.result-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 4px;
}

.result-user {
  font-weight: 600;
  font-size: 14px;
  color: var(--ion-color-dark);
}

.result-time {
  font-size: 12px;
  color: var(--ion-color-medium);
}

.result-content {
  font-size: 14px;
  color: var(--ion-color-step-600);
  line-height: 1.4;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.result-content :deep(mark) {
  background: var(--ion-color-primary);
  color: white;
  padding: 2px 4px;
  border-radius: 3px;
  font-weight: 600;
}

.no-results,
.search-prompt {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 64px 24px;
  text-align: center;
  color: var(--ion-color-medium);
}

.no-results-icon,
.prompt-icon {
  font-size: 80px;
  margin-bottom: 16px;
  opacity: 0.3;
}

.no-results p,
.search-prompt p {
  font-size: 16px;
  margin: 0;
}

/* Screen reader only */
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}
</style>
