#!/usr/bin/env bash
# Build the signed release APK and publish it to public/downloads/ (mensa.apk + version.json).
# Installed apps read version.json on start and offer the update.
# Usage: scripts/release-android.sh "What changed (shown in the update dialog)"
# Bump versionCode/versionName in android/app/build.gradle.kts first, then commit version.json and run scripts/deploy.sh.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
NOTES="${1:-}"
GRADLE="$ROOT/android/app/build.gradle.kts"
CODE="$(sed -nE 's/^[[:space:]]*versionCode = ([0-9]+).*/\1/p' "$GRADLE")"
NAME="$(sed -nE 's/^[[:space:]]*versionName = "([^"]+)".*/\1/p' "$GRADLE")"
OUT="$ROOT/public/downloads"
APK="$ROOT/android/app/build/outputs/apk/release/app-release.apk"
SDK="$(sed -nE 's/^sdk.dir=(.*)/\1/p' "$ROOT/android/local.properties")"
APKSIGNER="$(ls -d "$SDK"/build-tools/* | sort -V | tail -1)/apksigner"

PUBLISHED="$(sed -nE 's/.*"versionCode": *([0-9]+).*/\1/p' "$OUT/version.json" 2>/dev/null || echo 0)"
if [ "${PUBLISHED:-0}" -ge "$CODE" ]; then
    echo "versionCode $CODE is not newer than the published ${PUBLISHED}. Bump it in android/app/build.gradle.kts." >&2
    exit 1
fi

echo "→ Building $NAME ($CODE)"
(cd "$ROOT/android" && ./gradlew -q assembleRelease)
"$APKSIGNER" verify "$APK"

mkdir -p "$OUT"
cp "$APK" "$OUT/mensa.apk"
php -r '
    [$code, $name, $notes, $file] = array_slice($argv, 1);
    file_put_contents($file, json_encode(["versionCode" => (int) $code, "versionName" => $name, "apk" => "mensa.apk", "notes" => $notes === "" ? null : $notes], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
' "$CODE" "$NAME" "$NOTES" "$OUT/version.json"

echo "✓ public/downloads/mensa.apk and version.json ($NAME). Commit version.json, then run scripts/deploy.sh."
