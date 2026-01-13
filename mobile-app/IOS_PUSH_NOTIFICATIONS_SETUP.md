# iOS Push Notifications Setup Guide

This guide provides complete instructions for setting up push notifications on iOS for the Confer mobile app. The Android version is already fully configured and working.

---

## Table of Contents

1. [Overview](#overview)
2. [Prerequisites](#prerequisites)
3. [Current Project State](#current-project-state)
4. [Step 1: Apple Developer Account Setup](#step-1-apple-developer-account-setup)
5. [Step 2: Create App ID with Push Notifications](#step-2-create-app-id-with-push-notifications)
6. [Step 3: Create APNs Authentication Key](#step-3-create-apns-authentication-key)
7. [Step 4: Firebase Console Setup for iOS](#step-4-firebase-console-setup-for-ios)
8. [Step 5: Add iOS Platform to Capacitor](#step-5-add-ios-platform-to-capacitor)
9. [Step 6: Configure Xcode Project](#step-6-configure-xcode-project)
10. [Step 7: Add Push Notification Capability](#step-7-add-push-notification-capability)
11. [Step 8: Test Push Notifications](#step-8-test-push-notifications)
12. [Troubleshooting](#troubleshooting)
13. [Architecture Reference](#architecture-reference)

---

## Overview

The Confer mobile app uses:
- **Capacitor** (v7.4.4) as the native bridge
- **Firebase Cloud Messaging (FCM)** for push notifications on both iOS and Android
- **APNs (Apple Push Notification service)** via FCM for iOS delivery

Firebase acts as a unified push notification service - the backend sends notifications to FCM, and FCM handles delivery to both Android (directly) and iOS (via APNs).

### How It Works

```
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│  Laravel Backend │────▶│  Firebase FCM   │────▶│  Android Device │
│                 │     │                 │     └─────────────────┘
│  Sends FCM      │     │  Routes to      │
│  notification   │     │  appropriate    │     ┌─────────────────┐
│                 │     │  platform       │────▶│   iOS Device    │
└─────────────────┘     └─────────────────┘     │   (via APNs)    │
                                                └─────────────────┘
```

---

## Prerequisites

### Required Accounts
- [ ] **Apple Developer Account** ($99/year) - https://developer.apple.com
- [ ] **Firebase Account** (free) - Already set up, project: `confer-54f37`

### Required Software (for iOS developer)
- [ ] **macOS** (Monterey 12.0 or later recommended)
- [ ] **Xcode 14+** from Mac App Store
- [ ] **Xcode Command Line Tools**: `xcode-select --install`
- [ ] **CocoaPods**: `sudo gem install cocoapods`
- [ ] **Node.js 18+** and npm

### Firebase Project Information
- **Project ID**: `confer-54f37`
- **Project Number**: `136110471138`
- **Current Android App ID**: `1:136110471138:android:2d7d703cdf1870e919ebbb`

---

## Current Project State

### What's Already Done ✅

1. **Push notification service** implemented in `src/services/push.ts`
2. **Capacitor Push Notifications plugin** installed (`@capacitor/push-notifications@7.0.3`)
3. **Backend API** for device token registration (`POST /api/device-tokens`)
4. **FCM integration** in Laravel using `laravel-notification-channels/fcm`
5. **Android configuration** complete with `google-services.json`
6. **Notification payload** structure defined with conversation navigation data

### What Needs to Be Done 📋

1. Add iOS platform to Capacitor project
2. Create iOS app in Firebase Console
3. Configure APNs authentication in Firebase
4. Add `GoogleService-Info.plist` to iOS project
5. Configure Xcode signing and capabilities
6. Change bundle ID from `io.ionic.starter` to proper identifier

---

## Step 1: Apple Developer Account Setup

### 1.1 Enroll in Apple Developer Program

1. Go to https://developer.apple.com/programs/enroll/
2. Sign in with your Apple ID (or create one)
3. Complete enrollment ($99/year fee)
4. Wait for enrollment approval (usually 24-48 hours)

### 1.2 Access Apple Developer Portal

Once enrolled:
1. Go to https://developer.apple.com/account
2. You should see access to Certificates, Identifiers & Profiles

---

## Step 2: Create App ID with Push Notifications

### 2.1 Register a New App ID

1. Go to **Certificates, Identifiers & Profiles**
2. Click **Identifiers** in the left sidebar
3. Click the **+** button to register a new identifier
4. Select **App IDs** and click **Continue**
5. Select **App** type and click **Continue**

### 2.2 Configure the App ID

Fill in the following:

- **Description**: `Confer Mobile App`
- **Bundle ID**: Choose **Explicit** and enter: `com.yourcompany.confer`

  > ⚠️ **Important**: Replace `yourcompany` with your actual company/organization identifier. This must be unique and cannot be changed later.

### 2.3 Enable Push Notifications Capability

In the **Capabilities** section, scroll down and check:
- [x] **Push Notifications**

Click **Continue**, then **Register**.

---

## Step 3: Create APNs Authentication Key

APNs Authentication Keys are the recommended way to authenticate with APNs (instead of certificates).

### 3.1 Create the Key

1. Go to **Certificates, Identifiers & Profiles**
2. Click **Keys** in the left sidebar
3. Click the **+** button to create a new key
4. Enter a **Key Name**: `Confer APNs Key`
5. Check **Apple Push Notifications service (APNs)**
6. Click **Continue**, then **Register**

### 3.2 Download and Save the Key

1. Click **Download** to get the `.p8` file
2. **Save this file securely** - you can only download it once!
3. Note the **Key ID** displayed (10-character string like `ABC123DEFG`)
4. Note your **Team ID** (found in Membership details or top-right of the portal)

> 📁 **Save these three pieces of information**:
> - The `.p8` key file
> - Key ID
> - Team ID

---

## Step 4: Firebase Console Setup for iOS

### 4.1 Add iOS App to Firebase Project

1. Go to [Firebase Console](https://console.firebase.google.com/)
2. Select the **confer-54f37** project
3. Click the **gear icon** → **Project settings**
4. In the **Your apps** section, click **Add app** → **iOS**

### 4.2 Register the iOS App

Fill in:
- **Apple bundle ID**: `com.yourcompany.confer` (must match Step 2.2 exactly)
- **App nickname** (optional): `Confer iOS`
- **App Store ID** (optional): Leave blank for now

Click **Register app**.

### 4.3 Download GoogleService-Info.plist

1. Click **Download GoogleService-Info.plist**
2. Save this file - you'll add it to the Xcode project later
3. Click **Next** through the remaining steps (we'll configure manually)

### 4.4 Configure APNs Authentication in Firebase

1. In Firebase Console, go to **Project settings** → **Cloud Messaging** tab
2. Scroll to **Apple app configuration**
3. Under **APNs authentication key**, click **Upload**
4. Upload your `.p8` file from Step 3
5. Enter your **Key ID** (from Step 3)
6. Enter your **Team ID** (from Step 3)
7. Click **Upload**

✅ Firebase is now configured to send push notifications to iOS devices via APNs.

---

## Step 5: Add iOS Platform to Capacitor

### 5.1 Update Bundle ID in Capacitor Config

Edit `capacitor.config.ts`:

```typescript
import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'com.yourcompany.confer',  // ← Change this to match your App ID
  appName: 'Confer',
  webDir: 'dist',
  server: {
    androidScheme: 'https',
    iosScheme: 'ionic'
  },
  plugins: {
    SplashScreen: {
      launchShowDuration: 0
    },
    PushNotifications: {
      presentationOptions: ['badge', 'sound', 'alert']
    }
  }
};

export default config;
```

### 5.2 Build Web Assets

```bash
cd mobile-app
npm install
npm run build
```

### 5.3 Add iOS Platform

```bash
npx cap add ios
```

This creates the `ios/` directory with the native Xcode project.

### 5.4 Sync Capacitor

```bash
npx cap sync ios
```

---

## Step 6: Configure Xcode Project

### 6.1 Open in Xcode

```bash
npx cap open ios
```

### 6.2 Add GoogleService-Info.plist

1. In Xcode, right-click on the **App** folder (under App → App)
2. Select **Add Files to "App"...**
3. Navigate to and select your `GoogleService-Info.plist`
4. Ensure **Copy items if needed** is checked
5. Click **Add**

### 6.3 Configure Signing

1. Select the **App** project in the navigator (top item)
2. Select the **App** target
3. Go to **Signing & Capabilities** tab
4. Check **Automatically manage signing**
5. Select your **Team** (your Apple Developer account)
6. Xcode will create a provisioning profile automatically

### 6.4 Verify Bundle Identifier

In the same **Signing & Capabilities** tab:
- Ensure **Bundle Identifier** matches: `com.yourcompany.confer`

---

## Step 7: Add Push Notification Capability

### 7.1 Add Capability in Xcode

1. With the **App** target selected, stay in **Signing & Capabilities**
2. Click **+ Capability**
3. Search for and add **Push Notifications**
4. Also add **Background Modes** capability
5. In Background Modes, check:
   - [x] **Remote notifications**

### 7.2 Verify Entitlements

Xcode should automatically create/update `App.entitlements` with:
```xml
<key>aps-environment</key>
<string>development</string>
```

For production builds, this will automatically change to `production`.

---

## Step 8: Test Push Notifications

### 8.1 Build and Run on Physical Device

> ⚠️ **Push notifications only work on physical iOS devices, not simulators!**

1. Connect an iOS device via USB
2. Select your device in Xcode's device dropdown
3. Click **Run** (▶) or press `Cmd + R`
4. Accept the push notification permission prompt on the device

### 8.2 Verify Token Registration

1. Open the app and log in
2. Check Xcode console for: `Device token sent to backend`
3. Or check browser DevTools console if using Safari remote debugging

### 8.3 Test Sending a Notification

From another device or the web app:
1. Log in as a different user
2. Send a message to a conversation the iOS user is in
3. The iOS device should receive a push notification

### 8.4 Backend Verification

Check the Laravel logs or database to verify the device token was registered:

```bash
# On the server
docker exec -it latch-app php artisan tinker
>>> App\Models\DeviceToken::where('platform', 'ios')->get()
```

---

## Troubleshooting

### Push notifications not arriving

1. **Check APNs configuration in Firebase**
   - Verify the `.p8` key was uploaded correctly
   - Verify Key ID and Team ID are correct

2. **Check device token registration**
   - Look in Xcode console for registration errors
   - Verify token appears in `device_tokens` database table

3. **Check notification permissions**
   - Go to iOS Settings → Notifications → Confer
   - Ensure notifications are enabled

4. **Check backend logs**
   ```bash
   docker logs latch-queue
   ```

### "No valid 'aps-environment' entitlement" error

- Ensure Push Notifications capability is added in Xcode
- Clean build: Product → Clean Build Folder
- Delete app from device and reinstall

### Token registration fails

- Ensure the device has internet connectivity
- Check that the API URL in `.env` is correct and accessible
- Verify SSL certificate is valid (or use `http` for local testing)

### Notifications arrive but app doesn't open to correct conversation

The notification payload includes `conversation_id`. Check `src/services/push.ts`:

```typescript
await PushNotifications.addListener(
  'pushNotificationActionPerformed',
  (notification) => {
    const data = notification.notification.data;
    if (data.conversation_id) {
      // Navigate to conversation
      // router.push(`/conversations/${data.conversation_id}`);
    }
  }
);
```

---

## Architecture Reference

### Mobile App Files

| File | Purpose |
|------|---------|
| `src/services/push.ts` | Push notification service - handles registration, listeners |
| `src/services/api.ts` | API client - sends device token to backend |
| `capacitor.config.ts` | Capacitor configuration including push settings |
| `ios/App/App/GoogleService-Info.plist` | Firebase iOS configuration (you add this) |
| `google-services.json` | Firebase Android configuration (already present) |

### Backend Files

| File | Purpose |
|------|---------|
| `app/Http/Controllers/Api/DeviceTokenController.php` | Stores device tokens |
| `app/Models/DeviceToken.php` | Device token model (user_id, token, platform) |
| `app/Notifications/NewMessageNotification.php` | FCM notification payload |
| `app/Services/NotificationService.php` | Decides when to send notifications |

### API Endpoints

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `POST /api/device-tokens` | POST | Register device token |
| `DELETE /api/device-tokens` | DELETE | Remove device token (logout) |

### Device Token Registration Payload

```json
{
  "token": "fcm_device_token_string",
  "platform": "ios"
}
```

### Push Notification Payload (sent by backend)

```json
{
  "notification": {
    "title": "John Doe in #general",
    "body": "Hey everyone, check this out..."
  },
  "data": {
    "conversation_id": "123",
    "workspace_id": "1",
    "message_id": "456",
    "type": "new_message"
  }
}
```

---

## Environment Configuration

### Mobile App `.env`

```bash
# Production API URL
VITE_API_URL=https://groundstatesystems.work/api
```

### Backend Environment Variables (already configured)

The backend should have Firebase credentials configured:

```bash
FIREBASE_CREDENTIALS=/path/to/firebase-credentials.json
# OR
FIREBASE_CREDENTIALS='{"type":"service_account",...}'
```

---

## Checklist Summary

### Apple Developer Portal
- [ ] Enrolled in Apple Developer Program
- [ ] Created App ID with Push Notifications enabled
- [ ] Created APNs Authentication Key (.p8 file)
- [ ] Noted Key ID and Team ID

### Firebase Console
- [ ] Added iOS app to confer-54f37 project
- [ ] Downloaded GoogleService-Info.plist
- [ ] Uploaded APNs Authentication Key
- [ ] Entered Key ID and Team ID

### Xcode Project
- [ ] Updated bundle ID in capacitor.config.ts
- [ ] Added iOS platform: `npx cap add ios`
- [ ] Added GoogleService-Info.plist to Xcode project
- [ ] Configured signing with Apple Developer account
- [ ] Added Push Notifications capability
- [ ] Added Background Modes → Remote notifications

### Testing
- [ ] Built and ran on physical iOS device
- [ ] Accepted notification permission prompt
- [ ] Verified device token in backend database
- [ ] Received test push notification

---

## Support

For issues with:
- **Firebase/FCM**: https://firebase.google.com/docs/cloud-messaging/ios/client
- **Capacitor Push Notifications**: https://capacitorjs.com/docs/apis/push-notifications
- **APNs**: https://developer.apple.com/documentation/usernotifications

---

*Document Version: 1.0*
*Last Updated: November 2025*
*For: Confer Mobile App iOS Push Notifications Setup*
