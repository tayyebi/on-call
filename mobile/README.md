stack:

android
java
based on https://github.com/tayyebi/android-webclient (clone the repo to /tmp/ and learn)
no maven
no marline
no dart

## Layout

Plain Android project, no build framework at all (no Gradle, no Maven, no
third-party build/UI libraries) under `app/`:

- `MainActivity` — the three states from `MainActivity.txt`: disconnected
  (server address + pair code form), paired-but-unreachable (same form plus a
  retry button), and healthy (connected-since + disconnect).
- `PollService` — foreground service holding the long-poll connection to
  `/poll.php`, executing `sms` / `notification` / `ring` commands, and
  reporting outcomes back via `/report.php`. Raises a persistent notification
  if the server has been unreachable for more than 5 minutes.
- `ApiClient` — plain `HttpURLConnection` + `org.json` client for
  `/pair.php`, `/poll.php`, `/report.php`.
- `Prefs` — SharedPreferences for server address, device uid/token, and
  connection timestamps.
- `BootReceiver` — restarts `PollService` after a reboot if already paired.

## Building

Requires an Android SDK with `platforms;android-34` and a `build-tools`
version installed, and `ANDROID_SDK_ROOT` (or `ANDROID_HOME`) pointing at it.
`build.sh` drives `aapt2`, `javac`, `d8`, `zipalign`, and `apksigner`
directly — no Gradle, no wrapper jar to vendor or trust.

```
./build.sh
```

Produces `build/outputs/apk/release/app-release.apk`, signed with an
auto-generated debug keystore (`build/debug.keystore`) unless
`ONCALL_KEYSTORE` / `ONCALL_KEYSTORE_PASS` / `ONCALL_KEY_PASS` point at a
real one. CI runs the same script on release (see
`.github/workflows/ci.yml`).
