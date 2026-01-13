import { createRouter, createWebHistory } from '@ionic/vue-router';
import { RouteRecordRaw } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const routes: Array<RouteRecordRaw> = [
  {
    path: '/',
    redirect: '/login'
  },
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/views/auth/LoginPage.vue'),
    meta: { requiresGuest: true }
  },
  {
    path: '/register',
    name: 'Register',
    component: () => import('@/views/auth/RegisterPage.vue'),
    meta: { requiresGuest: true }
  },
  {
    path: '/workspaces',
    name: 'Workspaces',
    component: () => import('@/views/WorkspacesPage.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/workspace/:workspaceId',
    name: 'Chat',
    component: () => import('@/views/ChatPage.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/settings',
    name: 'Settings',
    component: () => import('@/views/SettingsPage.vue'),
    meta: { requiresAuth: true }
  }
];

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes
});

// Track if initial auth check has been done
let initialAuthCheckDone = false;

// Navigation guards
router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore();

  console.log('[Router] Navigation:', from.path, '->', to.path, 'isAuthenticated:', authStore.isAuthenticated);

  // Skip auth check if coming from login/register (we just authenticated)
  const comingFromAuth = from.path === '/login' || from.path === '/register';

  // Only check auth once on app startup, not on every navigation
  if (!initialAuthCheckDone && !authStore.isAuthenticated && !comingFromAuth) {
    initialAuthCheckDone = true;
    console.log('[Router] Running initial auth check...');
    try {
      await authStore.checkAuth();
      console.log('[Router] Auth check result:', authStore.isAuthenticated);
    } catch (e) {
      console.error('[Router] Initial auth check failed:', e);
    }
  }

  // Mark as done if we just came from auth pages
  if (comingFromAuth) {
    initialAuthCheckDone = true;
  }

  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    // Not authenticated, redirect to login
    console.log('[Router] Requires auth but not authenticated, redirecting to login');
    next('/login');
  } else if (to.meta.requiresGuest && authStore.isAuthenticated) {
    // Already authenticated, redirect to workspace
    console.log('[Router] Guest page but authenticated, redirecting to workspace');
    next('/workspace/1');
  } else {
    console.log('[Router] Allowing navigation');
    next();
  }
});

export default router;
