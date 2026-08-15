stack:

android
java
based on https://github.com/tayyebi/android-webclient (clone the repo to /tmp/ and learn)
no maven
no marline
no dart

## Layout

Plain Gradle Android project (no Maven build, no third-party build/UI
frameworks) under `app/`:

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

```
./gradlew assembleDebug
./gradlew assembleRelease   # only run by CI, on release (see .github/workflows/ci.yml)
```
