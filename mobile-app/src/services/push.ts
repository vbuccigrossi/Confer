import { PushNotifications } from '@capacitor/push-notifications';
import type { Token } from '@capacitor/push-notifications';
import type { PluginListenerHandle } from '@capacitor/core';
import api from './api';
import audioNotificationService from './audio';

class PushNotificationService {
  private isRegistered = false;
  private isInitialized = false;
  private listenerHandles: PluginListenerHandle[] = [];

  async initialize() {
    // Prevent double initialization
    if (this.isInitialized) {
      console.log('[Push] Already initialized');
      return;
    }

    try {
      const permStatus = await PushNotifications.requestPermissions();

      if (permStatus.receive === 'granted') {
        await PushNotifications.register();
      }

      // Store listener handles for cleanup
      const registrationHandle = await PushNotifications.addListener('registration', async (token: Token) => {
        this.isRegistered = true;
        await this.sendTokenToBackend(token.value);
      });
      this.listenerHandles.push(registrationHandle);

      const errorHandle = await PushNotifications.addListener('registrationError', (error: any) => {
        console.error('Push registration error:', error);
      });
      this.listenerHandles.push(errorHandle);

      const receivedHandle = await PushNotifications.addListener(
        'pushNotificationReceived',
        async (notification) => {
          await audioNotificationService.playNotificationSound();
        }
      );
      this.listenerHandles.push(receivedHandle);

      const actionHandle = await PushNotifications.addListener(
        'pushNotificationActionPerformed',
        (notification) => {
          const data = notification.notification?.data;
          if (data?.conversation_id) {
            // TODO: Navigate to conversation
          }
        }
      );
      this.listenerHandles.push(actionHandle);

      this.isInitialized = true;
    } catch (error) {
      console.error('Push service error:', error);
    }
  }

  private async sendTokenToBackend(token: string) {
    try {
      await api.getClient().post('/device-tokens', {
        token,
        platform: this.getPlatform(),
      });
      console.log('Device token sent to backend');
    } catch (error) {
      console.error('Failed to send device token to backend:', error);
    }
  }

  private getPlatform(): string {
    // Detect platform
    const userAgent = navigator.userAgent || navigator.vendor;

    if (/android/i.test(userAgent)) {
      return 'android';
    }

    if (/iPad|iPhone|iPod/.test(userAgent)) {
      return 'ios';
    }

    return 'web';
  }

  async removeToken() {
    try {
      // Remove all individual listener handles
      for (const handle of this.listenerHandles) {
        await handle.remove();
      }
      this.listenerHandles = [];

      // Also call removeAllListeners for safety
      if (this.isRegistered) {
        await PushNotifications.removeAllListeners();
      }

      this.isRegistered = false;
      this.isInitialized = false;

      // Optionally tell backend to remove token
      // await api.getClient().delete('/device-tokens');
    } catch (error) {
      console.error('Failed to remove push token:', error);
    }
  }

  // Get list of delivered notifications (iOS only)
  async getDeliveredNotifications() {
    const notificationList = await PushNotifications.getDeliveredNotifications();
    console.log('Delivered notifications:', notificationList);
    return notificationList;
  }
}

export const pushService = new PushNotificationService();
export default pushService;
