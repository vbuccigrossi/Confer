<template>
  <div v-if="typingUsers.length > 0" class="typing-indicator-container">
    <div class="typing-bubble">
      <div class="typing-dots">
        <span class="dot"></span>
        <span class="dot"></span>
        <span class="dot"></span>
      </div>
    </div>
    <div class="typing-text">
      {{ typingMessage }}
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';

interface Props {
  typingUsers: string[];
}

const props = defineProps<Props>();

const typingMessage = computed(() => {
  const count = props.typingUsers.length;
  if (count === 0) return '';
  if (count === 1) return `${props.typingUsers[0]} is typing...`;
  if (count === 2) return `${props.typingUsers[0]} and ${props.typingUsers[1]} are typing...`;
  return `${props.typingUsers[0]} and ${count - 1} others are typing...`;
});
</script>

<style scoped>
.typing-indicator-container {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  margin-bottom: 8px;
  animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.typing-bubble {
  background: var(--ion-color-light);
  border-radius: 16px;
  padding: 10px 14px;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
}

@media (prefers-color-scheme: dark) {
  .typing-bubble {
    background: var(--ion-color-step-100);
  }
}

.typing-dots {
  display: flex;
  gap: 4px;
  align-items: center;
  height: 18px;
}

.dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--ion-color-medium);
  animation: typingAnimation 1.4s infinite ease-in-out;
}

.dot:nth-child(1) {
  animation-delay: 0s;
}

.dot:nth-child(2) {
  animation-delay: 0.2s;
}

.dot:nth-child(3) {
  animation-delay: 0.4s;
}

@keyframes typingAnimation {
  0%, 60%, 100% {
    transform: translateY(0);
    opacity: 0.7;
  }
  30% {
    transform: translateY(-10px);
    opacity: 1;
  }
}

.typing-text {
  font-size: 13px;
  color: var(--ion-color-medium);
  font-style: italic;
}
</style>
