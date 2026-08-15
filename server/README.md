stack:

docker compose,
pure php,
no js,
sqlite (persistent volume ./data - .gitignored .dockerignored)
no css,
semantic html (dont use class or id attributes)

## Layout

- `public/` — front-facing pages, one file per route (`login.php`, `devices.php`,
  `on-board.php`, `command-center.php`, `api.php` (call log), `call.php`,
  `results.php`, plus the JSON endpoints `webhook.php`, `pair.php`, `poll.php`,
  `report.php`).
- `src/` — the flat, namespace-free class library (`OC_*`), loaded by
  `src/Autoloader.php`.
- `build.php` — merges every `src/` class and `public/` page into a single
  `dist/app.php` for the release artifact (see `.github/workflows/ci.yml`).
- `data/` — sqlite database file, gitignored/dockerignored, persisted via the
  compose volume.

## Running

```
cp .env.example .env
# fill in USERNAME, PASSWORD, TOTP secret, API_TOKEN
docker compose up --build
```

Generate a TOTP secret and bearer token for `.env`:

```
php -r "require 'src/Autoloader.php'; OC_Autoloader::register('src'); echo OC_Totp::generateSecret(), PHP_EOL;"
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Add the printed TOTP secret to an authenticator app (as a base32 secret) and
put both values in `.env`.

## Webhook API

See `public/opendocs.json`. All requests need `Authorization: Bearer <API_TOKEN>`.

- `POST /webhook.php?action=sms` `{ "number", "text", "devices" }`
- `POST /webhook.php?action=notification` `{ "text", "devices" }`
- `POST /webhook.php?action=ring` `{ "devices" }`

`devices` is either an array of device ids or the string `"all"`.

## Device protocol (used by the mobile app)

- `POST /pair.php` `{ "code", "model" }` → `{ "uid", "token" }` — claims a
  6-digit on-boarding code shown on `on-board.php`.
- `GET /poll.php?uid=...&token=...` — long-polls (up to 25s) for pending
  commands, returns `{ "commands": [...] }`.
- `POST /report.php` `{ "uid", "token", "target_id", "status", "result" }` —
  reports the outcome of an executed command.
