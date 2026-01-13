<template>
  <ion-modal :is-open="isOpen" @didDismiss="$emit('close')">
    <ion-header>
      <ion-toolbar>
        <ion-buttons slot="start">
          <ion-button @click="$emit('close')">
            <ion-icon :icon="closeOutline"></ion-icon>
          </ion-button>
        </ion-buttons>
        <ion-title>Profile</ion-title>
      </ion-toolbar>
    </ion-header>

    <ion-content class="ion-padding">
      <div v-if="user" class="profile-container">
        <!-- Avatar -->
        <div class="profile-avatar">
          <div class="avatar-circle" :style="{ backgroundColor: getUserColor(user.name) }">
            {{ getInitials(user.name) }}
          </div>
          <div v-if="isOnline" class="online-badge"></div>
        </div>

        <!-- User Info -->
        <div class="user-info">
          <h2 class="user-name">{{ user.name }}</h2>
          <p class="user-email">{{ user.email }}</p>

          <!-- Status -->
          <div v-if="user.status" class="user-status">
            <ion-icon :icon="informationCircleOutline" class="status-icon"></ion-icon>
            <span>{{ user.status }}</span>
          </div>
        </div>

        <!-- Stats -->
        <div class="user-stats">
          <div class="stat-item">
            <div class="stat-value">{{ messageCount || '0' }}</div>
            <div class="stat-label">Messages</div>
          </div>
          <div class="stat-item">
            <div class="stat-value">{{ user.workspace_count || '0' }}</div>
            <div class="stat-label">Workspaces</div>
          </div>
        </div>

        <!-- Actions -->
        <div class="profile-actions">
          <ion-button expand="block" @click="$emit('start-dm')">
            <ion-icon :icon="chatbubbleOutline" slot="start"></ion-icon>
            Send Message
          </ion-button>

          <ion-button v-if="!isOwnProfile" expand="block" fill="outline" @click="viewInWorkspace">
            <ion-icon :icon="personOutline" slot="start"></ion-icon>
            View in Workspace
          </ion-button>
        </div>

        <!-- Additional Info -->
        <ion-list class="info-list">
          <ion-item>
            <ion-icon :icon="timeOutline" slot="start"></ion-icon>
            <ion-label>
              <p>Member since</p>
              <h3>{{ formatDate(user.created_at) }}</h3>
            </ion-label>
          </ion-item>

          <ion-item v-if="user.last_seen_at">
            <ion-icon :icon="eyeOutline" slot="start"></ion-icon>
            <ion-label>
              <p>Last seen</p>
              <h3>{{ formatLastSeen(user.last_seen_at) }}</h3>
            </ion-label>
          </ion-item>

          <ion-item v-if="user.timezone">
            <ion-icon :icon="globeOutline" slot="start"></ion-icon>
            <ion-label>
              <p>Timezone</p>
              <h3>{{ user.timezone }}</h3>
            </ion-label>
          </ion-item>
        </ion-list>
      </div>
    </ion-content>
  </ion-modal>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import {
  IonModal,
  IonHeader,
  IonToolbar,
  IonTitle,
  IonButtons,
  IonButton,
  IonContent,
  IonIcon,
  IonList,
  IonItem,
  IonLabel,
} from '@ionic/vue';
import {
  closeOutline,
  chatbubbleOutline,
  personOutline,
  informationCircleOutline,
  timeOutline,
  eyeOutline,
  globeOutline,
} from 'ionicons/icons';

interface Props {
  isOpen: boolean;
  user: any | null;
  currentUserId?: number;
  isOnline?: boolean;
  messageCount?: number;
}

const props = defineProps<Props>();
const emit = defineEmits(['close', 'start-dm']);

const isOwnProfile = computed(() => props.user?.id === props.currentUserId);

function getUserColor(name?: string): string {
  if (!name) return '#94a3b8';

  const colors = [
    '#38bdf8', '#00ffc8', '#a78bfa', '#fb923c',
    '#ec4899', '#10b981', '#f59e0b', '#8b5cf6',
  ];

  let hash = 0;
  for (let i = 0; i < name.length; i++) {
    hash = name.charCodeAt(i) + ((hash << 5) - hash);
  }

  return colors[Math.abs(hash) % colors.length];
}

function getInitials(name?: string): string {
  if (!name) return '?';
  return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
}

function formatDate(timestamp?: string): string {
  if (!timestamp) return 'Unknown';
  const date = new Date(timestamp);
  return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
}

function formatLastSeen(timestamp?: string): string {
  if (!timestamp) return 'Unknown';

  const date = new Date(timestamp);
  const now = new Date();
  const diffMs = now.getTime() - date.getTime();
  const diffMins = Math.floor(diffMs / 60000);
  const diffHours = Math.floor(diffMins / 60);
  const diffDays = Math.floor(diffHours / 24);

  if (diffMins < 5) return 'Just now';
  if (diffMins < 60) return `${diffMins} minutes ago`;
  if (diffHours < 24) return `${diffHours} hours ago`;
  if (diffDays < 7) return `${diffDays} days ago`;

  return formatDate(timestamp);
}

function viewInWorkspace() {
  // TODO: Navigate to user in workspace
  console.log('View user in workspace');
}
</script>

<style scoped>
.profile-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 24px;
}

.profile-avatar {
  position: relative;
  margin-bottom: 24px;
}

.avatar-circle {
  width: 120px;
  height: 120px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: 700;
  font-size: 48px;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
}

.online-badge {
  position: absolute;
  bottom: 8px;
  right: 8px;
  width: 24px;
  height: 24px;
  background: #22c55e;
  border: 4px solid var(--ion-background-color);
  border-radius: 50%;
}

.user-info {
  text-align: center;
  margin-bottom: 24px;
}

.user-name {
  font-size: 28px;
  font-weight: 700;
  color: var(--ion-color-dark);
  margin: 0 0 8px 0;
}

.user-email {
  font-size: 16px;
  color: var(--ion-color-medium);
  margin: 0 0 12px 0;
}

.user-status {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 8px 16px;
  background: var(--ion-color-light);
  border-radius: 20px;
  font-size: 14px;
  color: var(--ion-color-step-600);
  margin-top: 12px;
}

.status-icon {
  font-size: 18px;
}

.user-stats {
  display: flex;
  gap: 32px;
  margin-bottom: 32px;
  padding: 20px;
  background: var(--ion-color-light);
  border-radius: 12px;
  width: 100%;
  max-width: 300px;
}

.stat-item {
  flex: 1;
  text-align: center;
}

.stat-value {
  font-size: 28px;
  font-weight: 700;
  color: var(--ion-color-primary);
  margin-bottom: 4px;
}

.stat-label {
  font-size: 13px;
  color: var(--ion-color-medium);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.profile-actions {
  width: 100%;
  max-width: 400px;
  margin-bottom: 32px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.info-list {
  width: 100%;
  max-width: 400px;
}

.info-list ion-item {
  --padding-start: 0;
  --inner-padding-end: 0;
}

@media (prefers-color-scheme: dark) {
  .avatar-circle {
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
  }

  .user-stats,
  .user-status {
    background: var(--ion-color-step-100);
  }
}
</style>
