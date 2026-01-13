#!/bin/bash
# Simple Android build script - just run this instead of remembering all the steps
set -e

echo "========================================="
echo "   Confer Mobile - Android Build"
echo "========================================="
echo ""

cd "$(dirname "$0")"

# Build web assets
echo "1/4 Building web assets..."
npm run build

# Sync to Android
echo ""
echo "2/4 Syncing to Android project..."
npx cap sync android

# Build APK with proper Java home
echo ""
echo "3/4 Building Android APK..."
cd android
export JAVA_HOME=/usr/lib/jvm/java-21-openjdk-amd64
./gradlew --stop > /dev/null 2>&1 || true
./gradlew clean assembleDebug

# Copy to desktop
echo ""
echo "4/4 Copying APK to desktop..."
cp app/build/outputs/apk/debug/app-debug.apk ~/Desktop/confer-mobile.apk

echo ""
echo "========================================="
echo "   Build Complete!"
echo "========================================="
echo ""
echo "APK location: ~/Desktop/confer-mobile.apk"
ls -lh ~/Desktop/confer-mobile.apk
echo ""
