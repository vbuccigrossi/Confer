import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'io.ionic.starter',
  appName: 'Confer',
  webDir: 'dist',
  server: {
    // Allow cleartext traffic for development (localhost)
    // Remove this in production
    androidScheme: 'https',
    iosScheme: 'ionic'
  },
  plugins: {
    SplashScreen: {
      launchShowDuration: 0
    }
  }
};

export default config;
