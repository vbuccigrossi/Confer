# Building Confer Mobile App for iOS and Android

This guide will walk you through building the Confer mobile app for iOS and Android devices.

## Prerequisites

### For Both Platforms
- Node.js 16+ and npm installed
- The mobile app dependencies installed (`npm install` in the `mobile-app` directory)

### For iOS Development
- **macOS only** (iOS development requires macOS)
- Xcode 13+ installed from the Mac App Store
- Xcode Command Line Tools: `xcode-select --install`
- CocoaPods: `sudo gem install cocoapods`
- An Apple Developer account (free or paid)

### For Android Development
- Android Studio installed (works on macOS, Windows, Linux)
- Android SDK installed via Android Studio
- Java Development Kit (JDK) 17+

---

## Initial Setup

### 1. Firebase Setup for Push Notifications

Push notifications require Firebase Cloud Messaging (FCM) configuration.

#### Create Firebase Project

1. Go to [Firebase Console](https://console.firebase.google.com/)
2. Click **Add Project**
3. Enter project name (e.g., "Confer")
4. Follow the wizard to create the project

#### Configure iOS App

1. In Firebase Console, click **Add App** → **iOS**
2. Register bundle ID: `io.ionic.starter` (or your custom bundle ID from Xcode)
3. Download `GoogleService-Info.plist`
4. Copy it to `mobile-app/ios/App/App/` directory
5. In Xcode, right-click the `App` folder → **Add Files to "App"**
6. Select `GoogleService-Info.plist` and check **Copy items if needed**

#### Configure Android App

1. In Firebase Console, click **Add App** → **Android**
2. Register package name: `io.ionic.starter` (from `android/app/build.gradle`)
3. Download `google-services.json`
4. Copy it to `mobile-app/android/app/` directory

#### Configure Backend (Laravel)

1. In Firebase Console, go to **Project Settings** → **Service Accounts**
2. Click **Generate New Private Key**
3. Save the JSON file securely
4. On your server, set environment variable:
   ```bash
   FIREBASE_CREDENTIALS=/path/to/firebase-credentials.json
   ```

   Or copy the JSON content directly to `.env`:
   ```bash
   FIREBASE_CREDENTIALS='{"type":"service_account","project_id":"...","private_key":"..."}'
   ```

5. Restart Laravel queue workers:
   ```bash
   php artisan queue:restart
   ```

#### Testing Push Notifications

After setup:
1. Build and run the mobile app
2. Login with a user account
3. Send a message from another device/browser
4. You should receive a push notification!

**Note:** Push notifications only work on **real devices**, not simulators/emulators.

---

### 2. Configure API URL

Edit the `.env` file in the `mobile-app` directory:

```bash
# For testing on a real device, use your computer's local IP address
VITE_API_URL=http://192.168.1.100/api

# For production deployment
# VITE_API_URL=https://your-domain.com/api
```

**Finding your local IP:**
- macOS/Linux: `ifconfig | grep "inet "`
- Windows: `ipconfig`

Make sure your mobile device is on the **same Wi-Fi network** as your development machine.

---

## Building for iOS

### Step 1: Add iOS Platform

```bash
cd mobile-app
npx cap add ios
```

This creates the `ios/` directory with the native iOS project.

### Step 2: Build Web Assets

```bash
npm run build
```

This creates the `dist/` directory with compiled web assets.

### Step 3: Sync to iOS Project

```bash
npx cap sync ios
```

This copies the web assets to the iOS project and updates native dependencies.

### Step 4: Open in Xcode

```bash
npx cap open ios
```

This opens the project in Xcode.

### Step 5: Configure Signing

In Xcode:
1. Select the **App** target in the left sidebar
2. Go to **Signing & Capabilities** tab
3. Select your **Team** (Apple Developer account)
4. Xcode will automatically create a provisioning profile

### Step 6: Build and Run

1. Select a simulator or connected iOS device from the device dropdown (top toolbar)
2. Click the **Play** button (▶) or press `Cmd + R`
3. The app will build and launch on the device/simulator

### Troubleshooting iOS

**Build fails with signing errors:**
- Make sure you selected a valid Team in Signing & Capabilities
- Try "Clean Build Folder": `Product` → `Clean Build Folder` in Xcode

**App crashes on launch:**
- Check the Console in Xcode for errors
- Verify the API_URL in your `.env` is accessible from the device

---

## Building for Android

### Step 1: Add Android Platform

```bash
cd mobile-app
npx cap add android
```

This creates the `android/` directory with the native Android project.

### Step 2: Build Web Assets

```bash
npm run build
```

### Step 3: Sync to Android Project

```bash
npx cap sync android
```

### Step 4: Open in Android Studio

```bash
npx cap open android
```

This opens the project in Android Studio.

### Step 5: Configure Android SDK

In Android Studio:
1. Wait for Gradle sync to complete (status bar at bottom)
2. If prompted, accept Android SDK licenses
3. Let Android Studio download any missing SDK components

### Step 6: Build and Run

1. Create or start an **Android Emulator** (AVD):
   - `Tools` → `Device Manager`
   - Click **Create Device**
   - Choose a device (e.g., Pixel 5)
   - Choose a system image (e.g., Android 13/Tiramisu)
   - Click **Finish** and start the emulator

   **OR** connect a real Android device via USB with USB debugging enabled

2. Click the **Run** button (▶) or press `Shift + F10`
3. Select your device/emulator
4. The app will build and install

### Troubleshooting Android

**Build fails with Gradle errors:**
```bash
cd android
./gradlew clean
cd ..
npx cap sync android
```

**App can't connect to API:**
- If using an emulator, use `10.0.2.2` instead of `localhost`
- For real devices, use your computer's local IP (e.g., `192.168.1.100`)
- Make sure your firewall allows connections on port 80/443

**USB debugging not working:**
- Enable Developer Options on Android: `Settings` → `About Phone` → tap **Build Number** 7 times
- Enable USB Debugging: `Settings` → `Developer Options` → `USB Debugging`

---

## Making Changes and Rebuilding

Whenever you make changes to the Vue code:

```bash
# 1. Rebuild web assets
npm run build

# 2. Sync to native projects
npx cap sync

# 3. Rerun from Xcode/Android Studio
# OR use these commands:
npx cap run ios      # For iOS
npx cap run android  # For Android
```

---

## Live Reload During Development

For faster development with live reload:

```bash
# iOS
ionic cap run ios -l --external

# Android
ionic cap run android -l --external
```

This allows the app to reload automatically when you save changes, without rebuilding.

---

## Building Release Versions

### iOS Release Build

1. In Xcode, change scheme to **Any iOS Device (arm64)**
2. `Product` → `Archive`
3. When archive completes, click **Distribute App**
4. Choose distribution method (App Store, Ad Hoc, etc.)
5. Follow the wizard to sign and export

### Android Release Build

1. **Generate a signing key:**
   ```bash
   cd android/app
   keytool -genkey -v -keystore confer-release.keystore -alias confer -keyalg RSA -keysize 2048 -validity 10000
   ```

2. **Configure signing in `android/app/build.gradle`:**
   ```gradle
   android {
       signingConfigs {
           release {
               storeFile file('confer-release.keystore')
               storePassword 'your_password'
               keyAlias 'confer'
               keyPassword 'your_password'
           }
       }
       buildTypes {
           release {
               signingConfig signingConfigs.release
           }
       }
   }
   ```

3. **Build release APK:**
   ```bash
   cd android
   ./gradlew assembleRelease
   ```

   APK location: `android/app/build/outputs/apk/release/app-release.apk`

4. **Build release AAB (for Play Store):**
   ```bash
   cd android
   ./gradlew bundleRelease
   ```

   AAB location: `android/app/build/outputs/bundle/release/app-release.aab`

---

## Quick Reference

```bash
# Setup (one time per platform)
npx cap add ios
npx cap add android

# Development workflow
npm run build          # Build web assets
npx cap sync          # Copy to native projects
npx cap open ios      # Open in Xcode
npx cap open android  # Open in Android Studio

# Run on device
npx cap run ios
npx cap run android

# Live reload development
ionic cap run ios -l --external
ionic cap run android -l --external

# Check Capacitor status
npx cap doctor
```

---

## Getting Help

- **Capacitor Docs:** https://capacitorjs.com/docs
- **Ionic Docs:** https://ionicframework.com/docs
- **Common issues:** Run `npx cap doctor` to diagnose problems
