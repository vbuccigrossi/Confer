<template>
  <div v-if="showSuggestions && suggestions.length > 0" class="mention-autocomplete">
    <ion-list lines="none">
      <ion-item
        v-for="(user, index) in suggestions"
        :key="user.id"
        button
        @click="selectUser(user)"
        :class="{ 'selected': index === selectedIndex }"
      >
        <div class="user-avatar" slot="start" :style="{ backgroundColor: getUserColor(user.name) }">
          {{ getInitials(user.name) }}
        </div>
        <ion-label>
          <h3>{{ user.name }}</h3>
          <p>{{ user.email }}</p>
        </ion-label>
      </ion-item>
    </ion-list>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, onUnmounted } from 'vue';
import { IonList, IonItem, IonLabel } from '@ionic/vue';
import api from '@/services/api';

interface User {
  id: number;
  name: string;
  email: string;
}

interface Props {
  text: string;
  cursorPosition: number;
  conversationId: number;
}

const props = defineProps<Props>();
const emit = defineEmits(['select', 'close']);

const suggestions = ref<User[]>([]);
const showSuggestions = ref(false);
const selectedIndex = ref(0);
const mentionStart = ref(-1);
let searchTimeout: ReturnType<typeof setTimeout> | null = null;
let currentSearchId = 0; // Track search requests to handle race conditions

// Clean up timeout on unmount
onUnmounted(() => {
  if (searchTimeout) {
    clearTimeout(searchTimeout);
    searchTimeout = null;
  }
});

// Watch for text changes to detect @mentions
watch(() => props.text, async (newText) => {
  if (!newText) {
    closeSuggestions();
    return;
  }

  const position = props.cursorPosition;

  // Find if we're in a mention context
  const textBeforeCursor = newText.substring(0, position);
  const mentionMatch = textBeforeCursor.match(/@(\w*)$/);

  if (mentionMatch) {
    mentionStart.value = textBeforeCursor.lastIndexOf('@');
    const query = mentionMatch[1];

    // Debounce the search
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => searchMembers(query), 150);
  } else {
    closeSuggestions();
  }
});

async function searchMembers(query: string) {
  if (!props.conversationId || props.conversationId <= 0) return;

  // Increment search ID to track this request
  const thisSearchId = ++currentSearchId;

  try {
    const results = await api.searchConversationMembers(props.conversationId, query);

    // Only update if this is still the most recent search
    if (thisSearchId !== currentSearchId) return;

    // Validate results is an array
    if (!Array.isArray(results)) {
      closeSuggestions();
      return;
    }

    suggestions.value = results.slice(0, 8); // Limit to 8 suggestions
    showSuggestions.value = suggestions.value.length > 0;
    selectedIndex.value = 0;
  } catch (error) {
    console.error('[MentionAutocomplete] Error searching members:', error);
    // Only close if this is still the current search
    if (thisSearchId === currentSearchId) {
      closeSuggestions();
    }
  }
}

function selectUser(user: User) {
  emit('select', {
    user,
    mentionStart: mentionStart.value,
    mentionEnd: props.cursorPosition,
  });
  closeSuggestions();
}

function closeSuggestions() {
  showSuggestions.value = false;
  suggestions.value = [];
  mentionStart.value = -1;
}

function handleKeyDown(event: KeyboardEvent) {
  if (!showSuggestions.value) return false;

  switch (event.key) {
    case 'ArrowDown':
      event.preventDefault();
      selectedIndex.value = Math.min(selectedIndex.value + 1, suggestions.value.length - 1);
      return true;
    case 'ArrowUp':
      event.preventDefault();
      selectedIndex.value = Math.max(selectedIndex.value - 1, 0);
      return true;
    case 'Enter':
    case 'Tab':
      if (suggestions.value[selectedIndex.value]) {
        event.preventDefault();
        selectUser(suggestions.value[selectedIndex.value]);
        return true;
      }
      break;
    case 'Escape':
      event.preventDefault();
      closeSuggestions();
      return true;
  }
  return false;
}

function getUserColor(name: string | undefined | null): string {
  const colors = [
    '#38bdf8', '#00ffc8', '#a78bfa', '#fb923c',
    '#ec4899', '#10b981', '#f59e0b', '#8b5cf6',
  ];
  if (!name) return colors[0];
  let hash = 0;
  for (let i = 0; i < name.length; i++) {
    hash = name.charCodeAt(i) + ((hash << 5) - hash);
  }
  return colors[Math.abs(hash) % colors.length];
}

function getInitials(name: string | undefined | null): string {
  if (!name) return '?';
  return name.split(' ').map(n => n?.[0] || '').join('').toUpperCase().slice(0, 2) || '?';
}

// Expose methods for parent component
defineExpose({
  handleKeyDown,
  isOpen: () => showSuggestions.value,
});
</script>

<style scoped>
.mention-autocomplete {
  position: absolute;
  bottom: 100%;
  left: 0;
  right: 0;
  max-height: 300px;
  overflow-y: auto;
  background: var(--ion-background-color);
  border: 1px solid var(--ion-color-step-200);
  border-radius: 12px;
  margin-bottom: 8px;
  box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.15);
  z-index: 1000;
}

.mention-autocomplete ion-list {
  padding: 4px;
  background: transparent;
}

.mention-autocomplete ion-item {
  --background: transparent;
  --padding-start: 12px;
  --padding-end: 12px;
  --min-height: 48px;
  border-radius: 8px;
  margin: 2px 0;
}

.mention-autocomplete ion-item.selected,
.mention-autocomplete ion-item:hover {
  --background: var(--ion-color-primary-tint);
}

.user-avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: 600;
  font-size: 12px;
}

.mention-autocomplete h3 {
  font-size: 14px;
  font-weight: 600;
  margin: 0;
}

.mention-autocomplete p {
  font-size: 12px;
  color: var(--ion-color-medium);
  margin: 2px 0 0 0;
}

@media (prefers-color-scheme: dark) {
  .mention-autocomplete {
    border-color: var(--ion-color-step-150);
    box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.4);
  }
}
</style>
