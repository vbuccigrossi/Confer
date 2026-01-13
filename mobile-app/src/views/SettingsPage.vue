<template>
  <ion-page>
    <ion-header>
      <ion-toolbar>
        <ion-buttons slot="start">
          <ion-back-button default-href="/workspaces"></ion-back-button>
        </ion-buttons>
        <ion-title>Settings</ion-title>
      </ion-toolbar>
    </ion-header>

    <ion-content :fullscreen="true">
      <ion-list>
        <ion-list-header>
          <ion-label>Account</ion-label>
        </ion-list-header>

        <ion-item button @click="editProfile">
          <ion-label>
            <h2>{{ authStore.user?.name }}</h2>
            <p>{{ authStore.user?.email }}</p>
          </ion-label>
          <ion-icon :icon="personCircleOutline" slot="start"></ion-icon>
          <ion-icon :icon="chevronForwardOutline" slot="end"></ion-icon>
        </ion-item>

        <ion-item button @click="showStatusSelector = true">
          <ion-icon :icon="happyOutline" slot="start" :color="currentStatus ? 'primary' : undefined"></ion-icon>
          <ion-label>
            <h3>Set Status</h3>
            <p v-if="currentStatus?.message">{{ currentStatus.emoji }} {{ currentStatus.message }}</p>
            <p v-else-if="currentStatus?.status">{{ getStatusLabel(currentStatus.status) }}</p>
            <p v-else>Set your availability</p>
          </ion-label>
          <ion-icon :icon="chevronForwardOutline" slot="end"></ion-icon>
        </ion-item>

        <ion-list-header class="ion-margin-top">
          <ion-label>Preferences</ion-label>
        </ion-list-header>

        <ion-item>
          <ion-label>Sound Notifications</ion-label>
          <ion-toggle
            v-model="soundNotifications"
            @ionChange="updateSoundNotifications"
          ></ion-toggle>
        </ion-item>

        <ion-item button>
          <ion-label>Theme</ion-label>
          <ion-select v-model="theme" interface="action-sheet" @ionChange="handleThemeChange">
            <ion-select-option value="auto">Auto</ion-select-option>
            <ion-select-option value="light">Light</ion-select-option>
            <ion-select-option value="dark">Dark</ion-select-option>
          </ion-select>
        </ion-item>

        <ion-list-header class="ion-margin-top">
          <ion-label>About</ion-label>
        </ion-list-header>

        <ion-item>
          <ion-label>
            <h3>Version</h3>
            <p>1.0.0</p>
          </ion-label>
          <ion-icon :icon="informationCircleOutline" slot="start"></ion-icon>
        </ion-item>

        <ion-item button>
          <ion-label>
            <h3>Privacy Policy</h3>
          </ion-label>
          <ion-icon :icon="documentTextOutline" slot="start"></ion-icon>
          <ion-icon :icon="chevronForwardOutline" slot="end"></ion-icon>
        </ion-item>

        <ion-item button>
          <ion-label>
            <h3>Terms of Service</h3>
          </ion-label>
          <ion-icon :icon="documentTextOutline" slot="start"></ion-icon>
          <ion-icon :icon="chevronForwardOutline" slot="end"></ion-icon>
        </ion-item>

        <ion-list-header class="ion-margin-top">
          <ion-label>Actions</ion-label>
        </ion-list-header>

        <ion-item button @click="logout">
          <ion-label color="danger">
            <h3>Logout</h3>
          </ion-label>
          <ion-icon :icon="logOutOutline" slot="start" color="danger"></ion-icon>
        </ion-item>
      </ion-list>
    </ion-content>

    <!-- Status Selector Modal -->
    <StatusSelector
      :is-open="showStatusSelector"
      @close="showStatusSelector = false"
      @status-changed="handleStatusChanged"
    />
  </ion-page>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import {
  IonPage,
  IonHeader,
  IonToolbar,
  IonTitle,
  IonContent,
  IonList,
  IonListHeader,
  IonItem,
  IonLabel,
  IonButtons,
  IonBackButton,
  IonIcon,
  IonToggle,
  IonSelect,
  IonSelectOption,
  alertController,
} from '@ionic/vue';
import {
  personCircleOutline,
  chevronForwardOutline,
  informationCircleOutline,
  documentTextOutline,
  logOutOutline,
  happyOutline,
} from 'ionicons/icons';
import { Preferences } from '@capacitor/preferences';
import { useAuthStore } from '@/stores/auth';
import { useTheme } from '@/composables/useTheme';
import StatusSelector from '@/components/StatusSelector.vue';
import api from '@/services/api';

const router = useRouter();
const authStore = useAuthStore();
const { currentTheme, setTheme } = useTheme();

const soundNotifications = ref(true);
const theme = currentTheme;
const showStatusSelector = ref(false);
const currentStatus = ref<any>(null);

function getStatusLabel(status: string): string {
  const labels: Record<string, string> = {
    active: 'Active',
    away: 'Away',
    dnd: 'Do Not Disturb',
    invisible: 'Invisible',
  };
  return labels[status] || status;
}

function handleStatusChanged(status: any) {
  currentStatus.value = status;
}

async function loadCurrentStatus() {
  try {
    currentStatus.value = await api.getCurrentStatus();
  } catch (error) {
    console.error('Error loading status:', error);
  }
}

async function editProfile() {
  const alert = await alertController.create({
    header: 'Edit Profile',
    inputs: [
      {
        name: 'name',
        type: 'text',
        placeholder: 'Name',
        value: authStore.user?.name || '',
      },
      {
        name: 'email',
        type: 'email',
        placeholder: 'Email',
        value: authStore.user?.email || '',
        attributes: {
          disabled: true, // Email usually can't be changed
        },
      },
    ],
    buttons: [
      {
        text: 'Cancel',
        role: 'cancel',
      },
      {
        text: 'Save',
        handler: async (data) => {
          // TODO: Implement profile update API call
          console.log('Update profile:', data);
          return true;
        },
      },
    ],
  });
  await alert.present();
}

async function updateSoundNotifications() {
  await Preferences.set({
    key: 'soundNotifications',
    value: soundNotifications.value.toString(),
  });
  console.log('Sound notifications:', soundNotifications.value);
}

async function handleThemeChange() {
  await setTheme(theme.value);
}

async function logout() {
  await authStore.logout();
  router.replace('/login');
}

onMounted(async () => {
  // Load sound notifications preference
  const soundPref = await Preferences.get({ key: 'soundNotifications' });
  soundNotifications.value = soundPref.value === 'true';

  // Load current status
  await loadCurrentStatus();
});
</script>

<style scoped>
ion-list {
  padding-bottom: 32px;
}
</style>
