# FluxConvert Laravel Port

This folder is a Laravel-ready port of the Flask `cloudconvert` project.

## Requirements

- PHP 8.2+
- Composer
- FFmpeg on PATH for audio/video conversions
- PHP extensions commonly used by Laravel plus `gd`, `zip`, and `fileinfo`

## Install

```bash
cd laravel-cloudconvert
composer install
cp .env.example .env
php artisan key:generate
php artisan storage:link
php artisan serve
```

Open:

```text
http://127.0.0.1:8000
```

## Ported Routes

- `GET /`
- `GET /signin`
- `GET /signup`
- `GET /signout`
- `GET /auth/google`
- `GET /auth/microsoft`
- `GET /api/highlights`
- `POST /api/convert`
- `GET /api/download/{fileId}`
- `POST /api/download-all`

## Notes

- The frontend Vue app and CSS were copied from the Flask version.
- The Laravel conversion service mirrors the Flask API shape, but should be tested in a real PHP/Laravel environment after Composer dependencies are installed.
- Social login currently uses demo session behavior, matching the local Flask development behavior.
