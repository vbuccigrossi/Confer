<template>
  <ion-page>
    <ion-header>
      <ion-toolbar>
        <ion-buttons slot="start">
          <ion-back-button default-href="/login"></ion-back-button>
        </ion-buttons>
        <ion-title>Sign Up</ion-title>
      </ion-toolbar>
    </ion-header>

    <ion-content :fullscreen="true" class="ion-padding">
      <div class="register-container">
        <div class="logo-container">
          <h2>Create Account</h2>
          <p>Join Confer today</p>
        </div>

        <ion-list>
          <ion-item>
            <ion-label position="stacked">Name</ion-label>
            <ion-input
              v-model="name"
              type="text"
              placeholder="John Doe"
              autocomplete="name"
            ></ion-input>
          </ion-item>

          <ion-item>
            <ion-label position="stacked">Email</ion-label>
            <ion-input
              v-model="email"
              type="email"
              placeholder="your@email.com"
              autocomplete="email"
            ></ion-input>
          </ion-item>

          <ion-item>
            <ion-label position="stacked">Password</ion-label>
            <ion-input
              v-model="password"
              type="password"
              placeholder="••••••••"
              autocomplete="new-password"
            ></ion-input>
          </ion-item>

          <ion-item>
            <ion-label position="stacked">Confirm Password</ion-label>
            <ion-input
              v-model="passwordConfirmation"
              type="password"
              placeholder="••••••••"
              autocomplete="new-password"
              @keyup.enter="handleRegister"
            ></ion-input>
          </ion-item>
        </ion-list>

        <div v-if="validationError" class="error-message">
          <ion-text color="danger">
            {{ validationError }}
          </ion-text>
        </div>

        <div v-if="authStore.error" class="error-message">
          <ion-text color="danger">
            {{ authStore.error }}
          </ion-text>
        </div>

        <ion-button
          expand="block"
          @click="handleRegister"
          :disabled="authStore.loading || !isFormValid"
          class="ion-margin-top"
        >
          <ion-spinner v-if="authStore.loading" name="crescent"></ion-spinner>
          <span v-else>Create Account</span>
        </ion-button>

        <div class="login-link">
          <ion-text>
            Already have an account?
            <router-link to="/login">
              <strong>Sign In</strong>
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
  IonButtons,
  IonBackButton,
} from '@ionic/vue';
import { useAuthStore } from '@/stores/auth';
import pushService from '@/services/push';
import { Capacitor } from '@capacitor/core';

const router = useRouter();
const authStore = useAuthStore();

const name = ref('');
const email = ref('');
const password = ref('');
const passwordConfirmation = ref('');

const validationError = computed(() => {
  if (password.value && passwordConfirmation.value && password.value !== passwordConfirmation.value) {
    return 'Passwords do not match';
  }
  if (password.value && password.value.length < 8) {
    return 'Password must be at least 8 characters';
  }
  return null;
});

const isFormValid = computed(() => {
  return (
    name.value.length > 0 &&
    email.value.length > 0 &&
    password.value.length >= 8 &&
    password.value === passwordConfirmation.value &&
    !validationError.value
  );
});

async function initializePushNotifications() {
  try {
    if (Capacitor.isNativePlatform()) {
      await pushService.initialize();
    }
  } catch (error) {
    // Don't block registration if push notifications fail
    console.error('Push notification init error:', error);
  }
}

async function handleRegister() {
  if (!isFormValid.value) return;

  const success = await authStore.register(
    name.value,
    email.value,
    password.value
  );

  if (success) {
    // Initialize push notifications in background (don't await)
    initializePushNotifications();

    router.replace('/workspaces');
  }
}
</script>

<style scoped>
.register-container {
  max-width: 400px;
  margin: 0 auto;
  padding-top: 40px;
}

.logo-container {
  text-align: center;
  margin-bottom: 40px;
}

.logo-container h2 {
  font-size: 28px;
  font-weight: bold;
  margin-bottom: 8px;
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

.login-link {
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
