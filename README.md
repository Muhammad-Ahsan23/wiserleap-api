# WiserLeap API

Laravel 13 backend for the WiserLeap Next.js website and its blog admin panel.

- **Admin panel:** blogs, categories, authors, dashboard, image uploads
- **Auth:** Laravel Sanctum cookie login (sign up, login, logout, forgot/reset password)
- **Public website:** published posts, single post with related posts, categories, view counter

Full endpoint reference: [API.md](API.md)

## Requirements

PHP 8.3+, Composer, MySQL 8+ or MariaDB 10.6+ (tested on MariaDB 10.11)

Create the database first (phpMyAdmin, or):

```sql
CREATE DATABASE wiserleap CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

## Setup

```bash
composer install
cp .env.example .env          # Windows: copy .env.example .env
php artisan key:generate
```

Edit `.env`:

```env
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=wiserleap
DB_USERNAME=root
DB_PASSWORD=

FRONTEND_URLS=http://localhost:3000
FRONTEND_URL=http://localhost:3000
SANCTUM_STATEFUL_DOMAINS=localhost:3000,127.0.0.1:3000
```

Then:

```bash
php artisan migrate --seed    # tables + starting categories and authors
php artisan storage:link      # public URLs for uploaded images
php artisan test              # 28 feature tests
php artisan serve             # http://localhost:8000
```

Create the first admin from the Next.js sign-up page (only the first account can sign up),
or run `php artisan admin:create`.

## Production (example: api.wiserleap.com)

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.wiserleap.com

FRONTEND_URLS=https://wiserleap.com,https://www.wiserleap.com
FRONTEND_URL=https://wiserleap.com
SANCTUM_STATEFUL_DOMAINS=wiserleap.com,www.wiserleap.com
SESSION_DOMAIN=.wiserleap.com
SESSION_SECURE_COOKIE=true

# Password reset emails
MAIL_MAILER=smtp
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_USERNAME=info@wiserleap.com
MAIL_PASSWORD=
MAIL_SCHEME=smtps
MAIL_FROM_ADDRESS=info@wiserleap.com
```

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan storage:link
php artisan config:cache && php artisan route:cache
```

The web server's document root must point to the `public` folder.

## Project structure

| Path | What it holds |
|---|---|
| `routes/api.php` | All routes (`/api/v1/...`) |
| `app/Http/Controllers/Api/V1/{Auth,Admin,Public}` | Controllers |
| `app/Http/Requests` | Validation (same limits as the admin panel) |
| `app/Http/Resources` | JSON output (camelCase) |
| `app/Services` | `BlogService` (all blog writes), `HtmlSanitizer`, `ActivityLogger` |
| `app/Support` | Image storage, public cache, slugs, publish checklist |
| `config/admin.php`, `config/media.php`, `config/cors.php` | App settings |
| `tests/Feature` | Feature tests for every endpoint |
