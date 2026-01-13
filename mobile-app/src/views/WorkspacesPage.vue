<template>
  <ion-page>
    <ion-header>
      <ion-toolbar>
        <ion-title>Workspaces</ion-title>
        <ion-buttons slot="end">
          <ion-button @click="goToSettings">
            <ion-icon :icon="settingsOutline"></ion-icon>
          </ion-button>
        </ion-buttons>
      </ion-toolbar>
    </ion-header>

    <ion-content :fullscreen="true">
      <ion-refresher slot="fixed" @ionRefresh="handleRefresh">
        <ion-refresher-content></ion-refresher-content>
      </ion-refresher>

      <div v-if="loading && workspaces.length === 0" class="ion-padding ion-text-center">
        <ion-spinner></ion-spinner>
        <p>Loading workspaces...</p>
      </div>

      <ion-list v-else-if="workspaces.length > 0">
        <ion-item
          v-for="workspace in workspaces"
          :key="workspace.id"
          button
          @click="selectWorkspace(workspace)"
        >
          <ion-label>
            <h2>{{ workspace.name }}</h2>
            <p v-if="workspace.description">{{ workspace.description }}</p>
          </ion-label>
          <ion-icon :icon="chevronForwardOutline" slot="end"></ion-icon>
        </ion-item>
      </ion-list>

      <div v-else class="ion-padding ion-text-center">
        <ion-icon :icon="businessOutline" size="large" color="medium"></ion-icon>
        <p>No workspaces found</p>
        <ion-button @click="loadWorkspaces">Refresh</ion-button>
      </div>
    </ion-content>
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
  IonItem,
  IonLabel,
  IonButton,
  IonButtons,
  IonIcon,
  IonSpinner,
  IonRefresher,
  IonRefresherContent,
} from '@ionic/vue';
import { settingsOutline, chevronForwardOutline, businessOutline } from 'ionicons/icons';
import { useAuthStore } from '@/stores/auth';
import api from '@/services/api';

const router = useRouter();
const authStore = useAuthStore();

const workspaces = ref<any[]>([]);
const loading = ref(false);

async function loadWorkspaces() {
  loading.value = true;
  try {
    workspaces.value = await api.getWorkspaces();
  } catch (error) {
    console.error('Error loading workspaces:', error);
  } finally {
    loading.value = false;
  }
}

function selectWorkspace(workspace: any) {
  router.push(`/workspace/${workspace.id}`);
}

function goToSettings() {
  router.push('/settings');
}

async function handleRefresh(event: any) {
  await loadWorkspaces();
  event.target.complete();
}

onMounted(() => {
  loadWorkspaces();
});
</script>

<style scoped>
ion-icon[size="large"] {
  font-size: 64px;
  margin-bottom: 16px;
}
</style>
