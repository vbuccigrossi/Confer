import { Preferences } from '@capacitor/preferences';

class AudioNotificationService {
  private audio: HTMLAudioElement | null = null;

  constructor() {
    // Create audio element with a simple notification sound
    // Using a data URI for a simple beep sound
    this.audio = new Audio();
    // Simple notification beep (440Hz for 200ms)
    const audioContext = new (window.AudioContext || (window as any).webkitAudioContext)();
    const oscillator = audioContext.createOscillator();
    const gainNode = audioContext.createGain();

    oscillator.connect(gainNode);
    gainNode.connect(audioContext.destination);

    oscillator.frequency.value = 440; // A4 note
    oscillator.type = 'sine';

    gainNode.gain.setValueAtTime(0, audioContext.currentTime);
    gainNode.gain.linearRampToValueAtTime(0.3, audioContext.currentTime + 0.01);
    gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.2);

    // Store for later use - we'll use Web Audio API instead
  }

  async playNotificationSound(): Promise<void> {
    try {
      console.log('[AUDIO] playNotificationSound called');

      // Check if sound notifications are enabled
      const soundPref = await Preferences.get({ key: 'soundNotifications' });
      console.log('[AUDIO] Sound preference:', soundPref.value);
      const soundEnabled = soundPref.value !== 'false'; // Default to true if not set

      if (!soundEnabled) {
        console.log('[AUDIO] Sound notifications are disabled');
        return;
      }

      console.log('[AUDIO] Creating audio context...');
      // Create a simple beep using Web Audio API
      const audioContext = new (window.AudioContext || (window as any).webkitAudioContext)();
      const oscillator = audioContext.createOscillator();
      const gainNode = audioContext.createGain();

      oscillator.connect(gainNode);
      gainNode.connect(audioContext.destination);

      // Create a pleasant notification sound
      oscillator.frequency.value = 800; // Higher pitch for notification
      oscillator.type = 'sine';

      // Envelope for the sound
      const now = audioContext.currentTime;
      gainNode.gain.setValueAtTime(0, now);
      gainNode.gain.linearRampToValueAtTime(0.3, now + 0.01);
      gainNode.gain.exponentialRampToValueAtTime(0.01, now + 0.15);

      oscillator.start(now);
      oscillator.stop(now + 0.15);

      console.log('[AUDIO] Notification sound played successfully');
    } catch (error) {
      console.error('[AUDIO] Error playing notification sound:', error);
    }
  }
}

export const audioNotificationService = new AudioNotificationService();
export default audioNotificationService;
