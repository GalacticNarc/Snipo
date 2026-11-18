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

## Accounts

People sign up with email and password. Each person sees and manages only their own videos. Share links (`/v/...`) work for anyone, without an account.

## Putting it online for free (Render + Supabase)

- **Render** runs the Laravel app (Docker, free plan). It sleeps after 15 idle minutes, so the first visit can take ~1 minute.
- **Supabase** holds the database (Postgres) and the video files (Storage, S3-compatible, bucket `videos`).

Steps: create a private `videos` bucket and S3 access keys in Supabase, then in Render choose **New → Blueprint**, pick this repo, and fill in `APP_KEY`, `DB_URL`, `AWS_ACCESS_KEY_ID` and `AWS_SECRET_ACCESS_KEY`. Everything else is set in `render.yaml`.

Free-plan limits: 5-minute / 50 MB videos (set with `MAX_RECORDING_SECONDS` and `MAX_VIDEO_MB`), 1 GB of storage in total.

## Notes

- Screen recording only works on `localhost` or HTTPS.
- Tests: `php artisan test`
