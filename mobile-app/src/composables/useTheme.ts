import { ref } from 'vue';
import { Preferences } from '@capacitor/preferences';

const currentTheme = ref<string>('auto');

export function useTheme() {
  async function loadTheme() {
    const themePref = await Preferences.get({ key: 'theme' });
    const theme = themePref.value || 'auto';
    currentTheme.value = theme;
    await applyTheme(theme);
  }

  async function setTheme(theme: string) {
    currentTheme.value = theme;
    await Preferences.set({ key: 'theme', value: theme });
    await applyTheme(theme);
  }

  async function applyTheme(themeValue: string) {
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)');
    const html = document.documentElement;

    // Remove both classes first to start fresh
    html.classList.remove('ion-palette-dark');
    html.classList.remove('ion-palette-light');

    if (themeValue === 'auto') {
      // Follow system preference
      if (prefersDark.matches) {
        html.classList.add('ion-palette-dark');
      }
      // Light mode is default, no class needed
    } else if (themeValue === 'dark') {
      // Force dark mode
      html.classList.add('ion-palette-dark');
    } else {
      // Force light mode - add light class to override system dark preference
      html.classList.add('ion-palette-light');
    }
  }

  return {
    currentTheme,
    loadTheme,
    setTheme,
    applyTheme,
  };
}
