# GuidePaw Mobile Development Notes

## Current mobile strategy

GuidePaw is currently a PHP/PostgreSQL web app.

## Installed Android tooling

Android Studio:
Installed through Snap.

Check:
snap list android-studio

Java/JDK:
OpenJDK 17

Check:
java -version
javac -version

ADB:
Android Debug Bridge installed.

Check:
adb version

KVM:
User is in the kvm group for Android Emulator acceleration.

Check:
groups

Node/npm:
Installed for future Capacitor or React Native tooling.

Check:
node -v
npm -v

Capacitor CLI:
Installed globally.

Check:
cap --version

Gradle:
Installed, but system Gradle may be old. Prefer project gradle wrapper later.

Check:
gradle -v

Helper tools:
scrcpy, ffmpeg, ImageMagick

## Useful Android commands

List connected Android devices:

adb devices

Mirror/control Android phone:

scrcpy

Start Android Studio:

android-studio

## Apple/iOS notes

Final iOS app builds require Xcode on macOS.

This Linux laptop can still be used for:
- Planning app structure
- Preparing assets
- Writing shared web/mobile code
- Managing GitHub source
- Building Android
- Preparing Capacitor configuration

iOS options later:
- Physical Mac
- Borrowed Mac
- Cloud Mac build service
- GitHub Actions macOS runner, if project is structured for it

## Future GuidePaw mobile tasks

1. Decide app approach:
   - PWA only
   - Capacitor wrapper
   - React Native app

2. Prepare mobile-safe URLs:
   - Production API base URL
   - Auth/session strategy
   - Offline behavior

3. Add app assets:
   - App icon
   - Splash screen
   - Store screenshots

4. Android build:
   - Initialize Capacitor
   - Add Android platform
   - Open in Android Studio
   - Build debug APK

5. iOS build:
   - Add iOS platform on Mac
   - Open in Xcode
   - Configure signing
   - Test on iPhone
