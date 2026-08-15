#!/usr/bin/env bash
# Plain Android SDK command-line build: aapt2 + javac + d8 + apksigner.
# No Gradle, no Maven, no wrapper jar. Mirrors server/build.php's
# philosophy of "one script, no build framework".
set -euo pipefail

cd "$(dirname "$0")"

APP_ID="com.tayyebi.oncall"
MIN_SDK=24
TARGET_SDK=34
COMPILE_SDK=34
VERSION_CODE=1
VERSION_NAME="1.0"

SDK="${ANDROID_SDK_ROOT:-${ANDROID_HOME:-}}"
if [ -z "$SDK" ]; then
    echo "Set ANDROID_SDK_ROOT (or ANDROID_HOME) to an Android SDK install." >&2
    exit 1
fi

BUILD_TOOLS="$(find "$SDK/build-tools" -maxdepth 1 -mindepth 1 | sort -V | tail -n1)"
PLATFORM="$SDK/platforms/android-$COMPILE_SDK/android.jar"
AAPT2="$BUILD_TOOLS/aapt2"
D8="$BUILD_TOOLS/d8"
ZIPALIGN="$BUILD_TOOLS/zipalign"
APKSIGNER="$BUILD_TOOLS/apksigner"

[ -x "$AAPT2" ] || { echo "aapt2 not found under $BUILD_TOOLS" >&2; exit 1; }
[ -f "$PLATFORM" ] || { echo "android.jar not found: $PLATFORM (install platform android-$COMPILE_SDK)" >&2; exit 1; }

SRC=app/src/main
OUT=build
RES_ZIP="$OUT/res.zip"
GEN="$OUT/gen"
CLASSES="$OUT/classes"
UNSIGNED_APK="$OUT/app-unsigned.apk"
ALIGNED_APK="$OUT/app-aligned.apk"
RELEASE_APK="$OUT/outputs/apk/release/app-release.apk"

rm -rf "$OUT"
mkdir -p "$OUT" "$GEN" "$CLASSES" "$(dirname "$RELEASE_APK")"

echo "compiling resources..."
"$AAPT2" compile --dir "$SRC/res" -o "$RES_ZIP"

echo "linking resources..."
"$AAPT2" link \
    -I "$PLATFORM" \
    --manifest "$SRC/AndroidManifest.xml" \
    -R "$RES_ZIP" \
    --java "$GEN" \
    --min-sdk-version "$MIN_SDK" \
    --target-sdk-version "$TARGET_SDK" \
    --version-code "$VERSION_CODE" \
    --version-name "$VERSION_NAME" \
    --auto-add-overlay \
    -o "$UNSIGNED_APK"

echo "compiling java..."
JAVA_SOURCES="$OUT/sources.txt"
find "$SRC/java" "$GEN" -name '*.java' > "$JAVA_SOURCES"
javac -encoding UTF-8 -source 11 -target 11 -nowarn \
    --system none -bootclasspath "$PLATFORM" \
    -d "$CLASSES" \
    @"$JAVA_SOURCES"

echo "dexing..."
find "$CLASSES" -name '*.class' > "$OUT/classfiles.txt"
"$D8" --release --min-api "$MIN_SDK" --lib "$PLATFORM" \
    --output "$OUT" \
    @"$OUT/classfiles.txt"

echo "packaging..."
cp "$UNSIGNED_APK" "$OUT/app-with-dex.apk"
(cd "$OUT" && zip -q app-with-dex.apk classes.dex)

echo "zipaligning..."
"$ZIPALIGN" -f 4 "$OUT/app-with-dex.apk" "$ALIGNED_APK"

echo "signing..."
KEYSTORE="${ONCALL_KEYSTORE:-$OUT/debug.keystore}"
if [ ! -f "$KEYSTORE" ]; then
    keytool -genkeypair -v \
        -keystore "$KEYSTORE" -storepass android -keypass android \
        -alias androiddebugkey -keyalg RSA -keysize 2048 -validity 10000 \
        -dname "CN=on-call debug,O=on-call" >/dev/null
fi
"$APKSIGNER" sign \
    --ks "$KEYSTORE" --ks-pass pass:"${ONCALL_KEYSTORE_PASS:-android}" \
    --key-pass pass:"${ONCALL_KEY_PASS:-android}" \
    --out "$RELEASE_APK" "$ALIGNED_APK"

echo "built: $RELEASE_APK"
