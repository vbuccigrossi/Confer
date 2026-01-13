<template>
  <ion-modal :is-open="isOpen" @didDismiss="$emit('close')" :initial-breakpoint="0.5" :breakpoints="[0, 0.5, 0.75]">
    <ion-header>
      <ion-toolbar>
        <ion-title>Add Reaction</ion-title>
        <ion-buttons slot="end">
          <ion-button @click="$emit('close')">Close</ion-button>
        </ion-buttons>
      </ion-toolbar>
    </ion-header>
    <ion-content class="ion-padding">
      <!-- Quick reactions -->
      <div class="quick-reactions">
        <ion-chip
          v-for="emoji in quickEmojis"
          :key="emoji"
          @click="selectEmoji(emoji)"
          class="emoji-chip"
        >
          <span class="emoji-large">{{ emoji }}</span>
        </ion-chip>
      </div>

      <!-- Emoji categories -->
      <div class="emoji-categories">
        <ion-segment v-model="selectedCategory" scrollable>
          <ion-segment-button value="smileys">
            <ion-label>😊</ion-label>
          </ion-segment-button>
          <ion-segment-button value="gestures">
            <ion-label>👍</ion-label>
          </ion-segment-button>
          <ion-segment-button value="objects">
            <ion-label>🎉</ion-label>
          </ion-segment-button>
          <ion-segment-button value="symbols">
            <ion-label>❤️</ion-label>
          </ion-segment-button>
        </ion-segment>
      </div>

      <!-- Emoji grid -->
      <div class="emoji-grid">
        <div
          v-for="emoji in currentCategoryEmojis"
          :key="emoji"
          @click="selectEmoji(emoji)"
          class="emoji-item"
        >
          {{ emoji }}
        </div>
      </div>
    </ion-content>
  </ion-modal>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import {
  IonModal,
  IonHeader,
  IonToolbar,
  IonTitle,
  IonButtons,
  IonButton,
  IonContent,
  IonSegment,
  IonSegmentButton,
  IonLabel,
  IonChip,
} from '@ionic/vue';

interface Props {
  isOpen: boolean;
}

const props = defineProps<Props>();
const emit = defineEmits(['close', 'select']);

const selectedCategory = ref('smileys');

// Quick access emojis
const quickEmojis = ['👍', '❤️', '😂', '😮', '😢', '🔥', '🎉', '✅'];

// Emoji collections by category
const emojisByCategory = {
  smileys: [
    '😀', '😃', '😄', '😁', '😆', '😅', '🤣', '😂',
    '🙂', '🙃', '😉', '😊', '😇', '🥰', '😍', '🤩',
    '😘', '😗', '😚', '😙', '🥲', '😋', '😛', '😜',
    '🤪', '😝', '🤑', '🤗', '🤭', '🤫', '🤔', '🤐',
  ],
  gestures: [
    '👍', '👎', '👊', '✊', '🤛', '🤜', '🤞', '✌️',
    '🤟', '🤘', '👌', '🤌', '🤏', '👈', '👉', '👆',
    '👇', '☝️', '👋', '🤚', '🖐️', '✋', '🖖', '👏',
    '🙌', '👐', '🤲', '🤝', '🙏', '✍️', '💪', '🦾',
  ],
  objects: [
    '🎉', '🎊', '🎈', '🎁', '🏆', '🥇', '🥈', '🥉',
    '⚽', '🏀', '🏈', '⚾', '🎾', '🏐', '🏉', '🎱',
    '🎮', '🎯', '🎲', '🎰', '🎳', '🎪', '🎭', '🎨',
    '🎬', '🎤', '🎧', '🎼', '🎹', '🥁', '🎷', '🎺',
  ],
  symbols: [
    '❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍',
    '🤎', '💔', '❣️', '💕', '💞', '💓', '💗', '💖',
    '💘', '💝', '💟', '☮️', '✝️', '☪️', '🕉️', '☸️',
    '✡️', '🔯', '🕎', '☯️', '☦️', '🛐', '⛎', '♈',
  ],
};

const currentCategoryEmojis = computed(() => {
  return emojisByCategory[selectedCategory.value as keyof typeof emojisByCategory] || [];
});

function selectEmoji(emoji: string) {
  emit('select', emoji);
  emit('close');
}
</script>

<style scoped>
.quick-reactions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 24px;
  padding: 16px 0;
  border-bottom: 1px solid var(--ion-color-step-150);
}

.emoji-chip {
  --background: var(--ion-color-light);
  margin: 0;
  cursor: pointer;
  transition: all 0.2s ease;
  height: 56px;
  min-width: 56px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.emoji-chip:hover {
  transform: scale(1.1);
  --background: var(--ion-color-step-100);
}

.emoji-large {
  font-size: 32px;
}

.emoji-categories {
  margin-bottom: 16px;
}

.emoji-grid {
  display: grid;
  grid-template-columns: repeat(8, 1fr);
  gap: 8px;
  margin-top: 16px;
}

.emoji-item {
  font-size: 32px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 8px;
  border-radius: 8px;
  transition: all 0.2s ease;
  aspect-ratio: 1;
}

.emoji-item:hover,
.emoji-item:active {
  background: var(--ion-color-light);
  transform: scale(1.2);
}

@media (prefers-color-scheme: dark) {
  .emoji-chip {
    --background: var(--ion-color-step-100);
  }

  .emoji-item:hover,
  .emoji-item:active {
    background: var(--ion-color-step-150);
  }
}

@media (max-width: 768px) {
  .emoji-grid {
    grid-template-columns: repeat(6, 1fr);
  }

  .emoji-item {
    font-size: 28px;
  }
}
</style>
