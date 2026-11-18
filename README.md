# Snipo

Record your screen, face, and voice in the browser, then share it with a link.

Built with Laravel 12 and plain JavaScript (no build step).

## Features

- Screen + camera bubble + microphone recording (optional computer sound)
- Floating camera bubble with Pause / Stop controls (Picture-in-Picture)
- 3-2-1 countdown, pause / resume, cancel
- Chunked upload (4 MB pieces) with retry, or save the recording locally if the upload fails
- Share page with player, copy link, download, rename, delete
- View count and auto-generated thumbnails
- Video library

## Requirements

- PHP 8.2+ with `pdo_sqlite`
- Composer
- Chrome or Edge on desktop (needed for screen recording)

## Setup

```bash
git clone <this-repo-url> snipo
cd snipo
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan serve --port=8010
```

Open http://127.0.0.1:8010/record in Chrome or Edge. Allow camera and microphone, click **Choose screen to share** and pick **Entire screen**, then click **Start recording**.

Set the app name with `APP_NAME=Snipo` in `.env`.

## How it works

| Part | Where |
|---|---|
| Recorder (screen, camera bubble, audio mix, upload) | `resources/views/videos/record.blade.php` |
| Upload, streaming, share links | `app/Http/Controllers/VideoController.php` |
| Routes | `routes/web.php` |
| Video files | `storage/app/private/videos/` (not public; streamed through the app) |

## Notes

- Screen recording only works on `localhost` or HTTPS.
- There are no user accounts yet: anyone who can open the app can record and delete videos. Add authentication before putting it online.
