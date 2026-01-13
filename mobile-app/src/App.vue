<template>
  <ion-app>
    <ion-router-outlet />
  </ion-app>
</template>

<script setup lang="ts">
import { onMounted } from 'vue';
import { IonApp, IonRouterOutlet } from '@ionic/vue';
import { useAuthStore } from '@/stores/auth';
import pushService from '@/services/push';
import { Capacitor } from '@capacitor/core';
import { useTheme } from '@/composables/useTheme';

const authStore = useAuthStore();
const { loadTheme } = useTheme();

onMounted(async () => {
  // Load theme preference on app start
  await loadTheme();

  // Check authentication status first (loads token from storage)
  await authStore.checkAuth();

  // Only initialize push notifications on native platforms when authenticated
  if (Capacitor.isNativePlatform() && authStore.isAuthenticated) {
    await pushService.initialize();
  }
});
</script>
