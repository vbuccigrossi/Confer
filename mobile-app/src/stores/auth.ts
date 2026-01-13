import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api from '@/services/api';

export const useAuthStore = defineStore('auth', () => {
  const user = ref<any>(null);
  const token = ref<string | null>(null);
  const loading = ref(false);
  const error = ref<string | null>(null);

  const isAuthenticated = computed(() => !!token.value);

  async function login(email: string, password: string) {
    loading.value = true;
    error.value = null;

    try {
      const response = await api.login(email, password);
      console.log('[AuthStore] Login response:', JSON.stringify(response));
      console.log('[AuthStore] Token from response:', response.token);
      user.value = response.user;
      token.value = response.token || null;
      console.log('[AuthStore] Token set to:', token.value);
      return true;
    } catch (err: any) {
      console.error('[AuthStore] Login error:', err);
      error.value = err.response?.data?.message || 'Login failed';
      return false;
    } finally {
      loading.value = false;
    }
  }

  async function register(name: string, email: string, password: string) {
    loading.value = true;
    error.value = null;

    try {
      const response = await api.register(name, email, password);
      user.value = response.user;
      token.value = response.token || null;
      return true;
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Registration failed';
      return false;
    } finally {
      loading.value = false;
    }
  }

  async function logout() {
    loading.value = true;
    error.value = null;

    try {
      await api.logout();
    } catch (err: any) {
      console.error('Logout error:', err);
    } finally {
      user.value = null;
      token.value = null;
      loading.value = false;
    }
  }

  async function checkAuth() {
    if (api.isAuthenticated()) {
      token.value = api.getToken();
      user.value = api.getUser();

      try {
        // Verify token is still valid
        const profile = await api.getProfile();
        user.value = profile;
        return true;
      } catch (err) {
        // Token is invalid, clear it
        await logout();
        return false;
      }
    }
    return false;
  }

  return {
    user,
    token,
    loading,
    error,
    isAuthenticated,
    login,
    register,
    logout,
    checkAuth,
  };
});
