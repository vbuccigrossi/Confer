<template>
  <ion-modal :is-open="isOpen" @didDismiss="$emit('close')">
    <ion-header>
      <ion-toolbar>
        <ion-title>Set Status</ion-title>
        <ion-buttons slot="end">
          <ion-button @click="$emit('close')">Close</ion-button>
        </ion-buttons>
      </ion-toolbar>
    </ion-header>

    <ion-content class="ion-padding">
      <!-- Current Status Display -->
      <div v-if="currentStatus" class="current-status">
        <div class="status-badge" :class="currentStatus.status">
          <span v-if="currentStatus.emoji" class="status-emoji">{{ currentStatus.emoji }}</span>
          <span v-else class="status-dot"></span>
          <span class="status-text">{{ currentStatus.message || getStatusLabel(currentStatus.status) }}</span>
        </div>
        <ion-button fill="clear" size="small" @click="clearCurrentStatus">
          Clear
        </ion-button>
      </div>

      <!-- Quick Status Options -->
      <ion-list-header>
        <ion-label>Quick Status</ion-label>
      </ion-list-header>

      <ion-list lines="none" class="status-list">
        <ion-item
          v-for="status in quickStatuses"
          :key="status.value"
          button
          @click="setQuickStatus(status.value)"
          :class="{ 'active': currentStatus?.status === status.value }"
        >
          <span class="status-indicator" :class="status.value" slot="start"></span>
          <ion-label>{{ status.label }}</ion-label>
        </ion-item>
      </ion-list>

      <!-- Preset Statuses -->
      <ion-list-header class="ion-margin-top">
        <ion-label>Preset Status</ion-label>
      </ion-list-header>

      <ion-list lines="none" class="status-list">
        <ion-item
          v-for="preset in presets"
          :key="preset.message"
          button
          @click="setPresetStatus(preset)"
        >
          <span class="preset-emoji" slot="start">{{ preset.emoji }}</span>
          <ion-label>{{ preset.message }}</ion-label>
        </ion-item>
      </ion-list>

      <!-- Custom Status -->
      <ion-list-header class="ion-margin-top">
        <ion-label>Custom Status</ion-label>
      </ion-list-header>

      <div class="custom-status-form">
        <ion-item lines="none" class="emoji-input">
          <ion-button fill="clear" @click="showEmojiPicker = true">
            <span class="emoji-button">{{ customEmoji || '😀' }}</span>
          </ion-button>
          <ion-input
            v-model="customMessage"
            placeholder="What's your status?"
            :maxlength="100"
          ></ion-input>
        </ion-item>

        <ion-item lines="none">
          <ion-label>Clear after</ion-label>
          <ion-select v-model="customExpiration" interface="action-sheet">
            <ion-select-option :value="null">Don't clear</ion-select-option>
            <ion-select-option :value="30">30 minutes</ion-select-option>
            <ion-select-option :value="60">1 hour</ion-select-option>
            <ion-select-option :value="240">4 hours</ion-select-option>
            <ion-select-option :value="1440">24 hours</ion-select-option>
          </ion-select>
        </ion-item>

        <ion-button
          expand="block"
          class="ion-margin-top"
          :disabled="!customMessage"
          @click="setCustomStatus"
        >
          Set Custom Status
        </ion-button>
      </div>

      <!-- Do Not Disturb -->
      <ion-list-header class="ion-margin-top">
        <ion-label>Do Not Disturb</ion-label>
      </ion-list-header>

      <ion-list lines="none" class="status-list">
        <ion-item>
          <ion-icon :icon="notificationsOffOutline" slot="start" color="danger"></ion-icon>
          <ion-label>
            <h3>Pause Notifications</h3>
            <p>You won't receive any notifications</p>
          </ion-label>
          <ion-toggle
            :checked="isDND"
            @ionChange="toggleDND"
            color="danger"
          ></ion-toggle>
        </ion-item>

        <ion-item v-if="isDND">
          <ion-label>Turn off after</ion-label>
          <ion-select v-model="dndExpiration" interface="action-sheet" @ionChange="updateDNDExpiration">
            <ion-select-option :value="null">Until I turn it off</ion-select-option>
            <ion-select-option :value="30">30 minutes</ion-select-option>
            <ion-select-option :value="60">1 hour</ion-select-option>
            <ion-select-option :value="240">4 hours</ion-select-option>
            <ion-select-option :value="1440">24 hours</ion-select-option>
          </ion-select>
        </ion-item>
      </ion-list>
    </ion-content>

    <!-- Emoji Picker Modal -->
    <ion-modal :is-open="showEmojiPicker" @didDismiss="showEmojiPicker = false" :initial-breakpoint="0.5" :breakpoints="[0, 0.5]">
      <ion-header>
        <ion-toolbar>
          <ion-title>Choose Emoji</ion-title>
          <ion-buttons slot="end">
            <ion-button @click="showEmojiPicker = false">Done</ion-button>
          </ion-buttons>
        </ion-toolbar>
      </ion-header>
      <ion-content class="ion-padding">
        <div class="emoji-grid">
          <div
            v-for="emoji in statusEmojis"
            :key="emoji"
            @click="selectEmoji(emoji)"
            class="emoji-item"
            :class="{ 'selected': customEmoji === emoji }"
          >
            {{ emoji }}
          </div>
        </div>
      </ion-content>
    </ion-modal>
  </ion-modal>
</template>

<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import {
  IonModal,
  IonHeader,
  IonToolbar,
  IonTitle,
  IonButtons,
  IonButton,
  IonContent,
  IonList,
  IonListHeader,
  IonItem,
  IonLabel,
  IonInput,
  IonSelect,
  IonSelectOption,
  IonToggle,
  IonIcon,
  toastController,
} from '@ionic/vue';
import { notificationsOffOutline } from 'ionicons/icons';
import api from '@/services/api';

interface Props {
  isOpen: boolean;
}

const props = defineProps<Props>();
const emit = defineEmits(['close', 'statusChanged']);

const currentStatus = ref<any>(null);
const presets = ref<any[]>([]);
const isDND = ref(false);
const dndExpiration = ref<number | null>(null);
const customMessage = ref('');
const customEmoji = ref('');
const customExpiration = ref<number | null>(null);
const showEmojiPicker = ref(false);

const quickStatuses = [
  { value: 'active', label: 'Active' },
  { value: 'away', label: 'Away' },
  { value: 'dnd', label: 'Do Not Disturb' },
  { value: 'invisible', label: 'Invisible' },
];

const statusEmojis = [
  '😀', '😊', '🙂', '😎', '🤔', '😴', '🤒', '🏠',
  '🚗', '✈️', '🏖️', '📅', '💻', '📞', '🍽️', '☕',
  '🎉', '🎮', '📚', '🏃', '🧘', '🎵', '🔒', '⏰',
];

// Watch for modal open to refresh data
watch(() => props.isOpen, async (isOpen) => {
  if (isOpen) {
    await loadCurrentStatus();
    await loadPresets();
  }
});

async function loadCurrentStatus() {
  try {
    const status = await api.getCurrentStatus();
    currentStatus.value = status;
    isDND.value = status?.do_not_disturb || false;
  } catch (error) {
    console.error('Error loading status:', error);
  }
}

async function loadPresets() {
  try {
    presets.value = await api.getStatusPresets();
  } catch (error) {
    console.error('Error loading presets:', error);
    // Default presets if API fails
    presets.value = [
      { emoji: '📅', message: 'In a meeting' },
      { emoji: '🚗', message: 'Commuting' },
      { emoji: '🤒', message: 'Out sick' },
      { emoji: '🏖️', message: 'On vacation' },
      { emoji: '🏠', message: 'Working from home' },
      { emoji: '🍽️', message: 'At lunch' },
    ];
  }
}

async function setQuickStatus(status: string) {
  try {
    await api.setStatus({ status });
    await loadCurrentStatus();
    emit('statusChanged', currentStatus.value);
    showToast(`Status set to ${getStatusLabel(status)}`);
  } catch (error) {
    console.error('Error setting status:', error);
    showToast('Failed to set status', 'danger');
  }
}

async function setPresetStatus(preset: any) {
  try {
    await api.setStatus({
      message: preset.message,
      emoji: preset.emoji,
    });
    await loadCurrentStatus();
    emit('statusChanged', currentStatus.value);
    showToast(`Status: ${preset.emoji} ${preset.message}`);
  } catch (error) {
    console.error('Error setting preset status:', error);
    showToast('Failed to set status', 'danger');
  }
}

async function setCustomStatus() {
  if (!customMessage.value) return;

  try {
    await api.setStatus({
      message: customMessage.value,
      emoji: customEmoji.value || undefined,
      expires_in: customExpiration.value,
    });
    await loadCurrentStatus();
    emit('statusChanged', currentStatus.value);
    showToast('Custom status set');

    // Clear form
    customMessage.value = '';
    customEmoji.value = '';
    customExpiration.value = null;
  } catch (error) {
    console.error('Error setting custom status:', error);
    showToast('Failed to set status', 'danger');
  }
}

async function clearCurrentStatus() {
  try {
    await api.clearStatus();
    currentStatus.value = null;
    emit('statusChanged', null);
    showToast('Status cleared');
  } catch (error) {
    console.error('Error clearing status:', error);
    showToast('Failed to clear status', 'danger');
  }
}

async function toggleDND(event: any) {
  const enabled = event.detail.checked;

  try {
    if (enabled) {
      await api.enableDND(dndExpiration.value || undefined);
      isDND.value = true;
      showToast('Do Not Disturb enabled');
    } else {
      await api.disableDND();
      isDND.value = false;
      dndExpiration.value = null;
      showToast('Do Not Disturb disabled');
    }
    await loadCurrentStatus();
    emit('statusChanged', currentStatus.value);
  } catch (error) {
    console.error('Error toggling DND:', error);
    showToast('Failed to update Do Not Disturb', 'danger');
    // Revert toggle
    isDND.value = !enabled;
  }
}

async function updateDNDExpiration() {
  if (isDND.value) {
    try {
      await api.enableDND(dndExpiration.value || undefined);
    } catch (error) {
      console.error('Error updating DND expiration:', error);
    }
  }
}

function selectEmoji(emoji: string) {
  customEmoji.value = emoji;
  showEmojiPicker.value = false;
}

function getStatusLabel(status: string): string {
  const labels: Record<string, string> = {
    active: 'Active',
    away: 'Away',
    dnd: 'Do Not Disturb',
    invisible: 'Invisible',
  };
  return labels[status] || status;
}

async function showToast(message: string, color = 'success') {
  const toast = await toastController.create({
    message,
    duration: 2000,
    color,
    position: 'bottom',
  });
  await toast.present();
}
</script>

<style scoped>
.current-status {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px;
  background: var(--ion-color-light);
  border-radius: 12px;
  margin-bottom: 16px;
}

.status-badge {
  display: flex;
  align-items: center;
  gap: 8px;
}

.status-emoji {
  font-size: 24px;
}

.status-dot {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  background: var(--ion-color-success);
}

.status-badge.away .status-dot {
  background: var(--ion-color-warning);
}

.status-badge.dnd .status-dot {
  background: var(--ion-color-danger);
}

.status-badge.invisible .status-dot {
  background: var(--ion-color-medium);
}

.status-text {
  font-weight: 500;
}

.status-list ion-item {
  --background: transparent;
  --padding-start: 12px;
  --padding-end: 12px;
  --min-height: 48px;
  border-radius: 8px;
  margin: 4px 0;
}

.status-list ion-item.active {
  --background: var(--ion-color-primary-tint);
}

.status-indicator {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  margin-right: 8px;
}

.status-indicator.active {
  background: var(--ion-color-success);
  box-shadow: 0 0 8px var(--ion-color-success);
}

.status-indicator.away {
  background: var(--ion-color-warning);
}

.status-indicator.dnd {
  background: var(--ion-color-danger);
}

.status-indicator.invisible {
  background: var(--ion-color-medium);
  border: 2px dashed var(--ion-color-step-300);
}

.preset-emoji {
  font-size: 24px;
  margin-right: 8px;
}

.custom-status-form {
  padding: 12px;
  background: var(--ion-color-light);
  border-radius: 12px;
}

.emoji-input {
  --background: var(--ion-background-color);
  border-radius: 8px;
}

.emoji-button {
  font-size: 28px;
}

.emoji-grid {
  display: grid;
  grid-template-columns: repeat(8, 1fr);
  gap: 8px;
}

.emoji-item {
  font-size: 28px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 8px;
  border-radius: 8px;
  transition: all 0.2s ease;
}

.emoji-item:hover,
.emoji-item:active {
  background: var(--ion-color-light);
  transform: scale(1.2);
}

.emoji-item.selected {
  background: var(--ion-color-primary-tint);
}

@media (prefers-color-scheme: dark) {
  .current-status,
  .custom-status-form {
    background: var(--ion-color-step-100);
  }
}
</style>
