# Confer Mobile App

Ionic Vue mobile application for Confer - cross-platform chat for iOS and Android.

## Features

- 📱 Cross-platform: Single codebase for iOS and Android
- 🔐 Authentication with Laravel Sanctum
- 💬 Real-time messaging via polling (3-second intervals)
- 📝 Channels and Direct Messages
- 🎨 Native mobile UI with Ionic components
- 🌙 Dark mode support

## Development Setup

### Prerequisites

- Node.js 16+ and npm
- For iOS development: macOS with Xcode
- For Android development: Android Studio

### Installation

```bash
cd mobile-app
npm install
```

### Configuration

1. Edit `.env` file to set your API URL:

```bash
# For development on a real device, use your computer's IP
VITE_API_URL=http://192.168.1.100/api

# For production
VITE_API_URL=https://your-domain.com/api
```

### Run in Browser

```bash
npm run dev
```

Then open http://localhost:5173 in your browser.

### Build for Production

```bash
npm run build
```

## Mobile Deployment

### Add iOS Platform

```bash
npx cap add ios
npm run build
npx cap sync ios
npx cap open ios
```

Then build and run from Xcode.

### Add Android Platform

```bash
npx cap add android
npm run build
npx cap sync android
npx cap open android
```

Then build and run from Android Studio.

### Update Native Projects

After making changes to the web code:

```bash
npm run build
npx cap sync
```

## Project Structure

```
mobile-app/
├── src/
│   ├── views/
│   │   ├── auth/          # Login/Register screens
│   │   ├── WorkspacesPage.vue
│   │   └── ChatPage.vue   # Main chat interface
│   ├── services/
│   │   └── api.ts         # API client (similar to TUI)
│   ├── stores/
│   │   └── auth.ts        # Pinia auth store
│   ├── router/
│   │   └── index.ts       # Vue Router config
│   └── main.ts
├── android/               # Android project (generated)
├── ios/                   # iOS project (generated)
├── capacitor.config.ts    # Capacitor configuration
└── .env                   # Environment variables

```

## API Integration

The app uses the same Laravel API as the web and TUI clients:

- Authentication: `/api/auth/login`, `/api/auth/register`
- Workspaces: `/api/workspaces`
- Conversations: `/api/conversations`
- Messages: `/api/conversations/{id}/messages`

## Development Tips

### Testing on Real Devices

1. **Find your computer's local IP:**
   ```bash
   # macOS/Linux
   ifconfig | grep "inet "

   # Windows
   ipconfig
   ```

2. **Update `.env` with your IP:**
   ```
   VITE_API_URL=http://192.168.1.100/api
   ```

3. **Ensure your device is on the same network**

4. **Build and sync:**
   ```bash
   npm run build
   npx cap sync
   ```

### Debugging

- **iOS:** Use Safari Web Inspector (Safari > Develop > Your Device)
- **Android:** Use Chrome DevTools (chrome://inspect)

### Live Reload

For live reload during development:

```bash
ionic cap run android -l --external
# or
ionic cap run ios -l --external
```

## Known Issues

- Push notifications not yet implemented
- Offline mode not yet implemented
- File uploads not yet implemented

## Future Enhancements

- [ ] Push notifications using Firebase Cloud Messaging
- [ ] Offline message caching
- [ ] File/image sharing
- [ ] Voice/video calls
- [ ] Biometric authentication
