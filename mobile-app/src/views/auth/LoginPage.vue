<template>
  <ion-page>
    <ion-header>
      <ion-toolbar>
        <ion-title>Confer</ion-title>
      </ion-toolbar>
    </ion-header>

    <ion-content :fullscreen="true" class="ion-padding">
      <div class="login-container">
        <div class="logo-container">
          <h1>Confer</h1>
          <p>Team collaboration made simple</p>
        </div>

        <ion-list>
          <ion-item>
            <ion-label position="stacked">Email</ion-label>
            <ion-input
              v-model="email"
              type="email"
              placeholder="your@email.com"
              autocomplete="email"
              @keyup.enter="handleLogin"
            ></ion-input>
          </ion-item>

          <ion-item>
            <ion-label position="stacked">Password</ion-label>
            <ion-input
              v-model="password"
              type="password"
              placeholder="••••••••"
              autocomplete="current-password"
              @keyup.enter="handleLogin"
            ></ion-input>
          </ion-item>
        </ion-list>

        <div v-if="authStore.error" class="error-message">
          <ion-text color="danger">
            {{ authStore.error }}
          </ion-text>
        </div>

        <ion-button
          expand="block"
          @click="handleLogin"
          :disabled="authStore.loading || !isFormValid"
          class="ion-margin-top"
        >
          <ion-spinner v-if="authStore.loading" name="crescent"></ion-spinner>
          <span v-else>Sign In</span>
        </ion-button>

        <div class="register-link">
          <ion-text>
            Don't have an account?
            <router-link to="/register">
              <strong>Sign Up</strong>
            </router-link>
          </ion-text>
        </div>
      </div>
    </ion-content>
  </ion-page>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
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
  IonInput,
  IonButton,
  IonSpinner,
  IonText,
} from '@ionic/vue';
import { useAuthStore } from '@/stores/auth';
import pushService from '@/services/push';
import { Capacitor } from '@capacitor/core';

const router = useRouter();
const authStore = useAuthStore();

const email = ref('');
const password = ref('');

const isFormValid = computed(() => {
  return email.value.length > 0 && password.value.length > 0;
});

async function initializePushNotifications() {
  try {
    if (Capacitor.isNativePlatform()) {
      await pushService.initialize();
    }
  } catch (error) {
    // Don't block login if push notifications fail
    console.error('Push notification init error:', error);
  }
}

async function handleLogin() {
  if (!isFormValid.value) return;

  console.log('[Login] Starting login...');
  const success = await authStore.login(email.value, password.value);
  console.log('[Login] Login result:', success, 'isAuthenticated:', authStore.isAuthenticated);

  if (success) {
    // Initialize push notifications in background (don't await)
    initializePushNotifications();

    console.log('[Login] Navigating to /workspace/1...');
    // Navigate to workspace
    router.replace('/workspace/1').then(() => {
      console.log('[Login] Navigation complete');
    }).catch((err) => {
      console.error('[Login] Navigation failed:', err);
    });
  } else {
    console.log('[Login] Login failed, error:', authStore.error);
  }
}
</script>

<style scoped>
.login-container {
  max-width: 400px;
  margin: 0 auto;
  padding-top: 60px;
}

.logo-container {
  text-align: center;
  margin-bottom: 40px;
}

.logo-container h1 {
  font-size: 36px;
  font-weight: bold;
  margin-bottom: 8px;
  color: var(--ion-color-primary);
}

.logo-container p {
  color: var(--ion-color-medium);
  font-size: 14px;
}

.error-message {
  margin-top: 16px;
  padding: 12px;
  background: var(--ion-color-danger-tint);
  border-radius: 8px;
  text-align: center;
}

.register-link {
  text-align: center;
  margin-top: 24px;
}

ion-list {
  background: transparent;
}

ion-item {
  --background: transparent;
  --border-color: var(--ion-color-light-shade);
  margin-bottom: 16px;
}
</style>
