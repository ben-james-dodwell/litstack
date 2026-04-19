# Litstack

A personal book tracking app. Search the Open Library catalogue, add books to your shelf, track reading progress, and leave reviews — all in one place.

Built with Laravel 13, Livewire 4, Flux UI, and Tailwind CSS 4.

---

## Features

- **Shelf** — view your entire collection with filter by ownership (owned / wishlist), reading status (in progress / completed / abandoned), author, and genre
- **Book search** — find any book by title, author, or ISBN via the Open Library API; covers are fetched and cached locally
- **Reading tracking** — log start/end dates and track progress through each book
- **Reviews** — write a review and star rating (1–5) once you've finished a book
- **Detail panel** — slide-in panel on the shelf showing full book metadata, status controls, and review
- **Authentication** — registration, login, email verification, password reset, and two-factor authentication (TOTP)

---

## Tech stack

| Layer | Package | Version |
|---|---|---|
| Framework | Laravel | 13 |
| Reactive UI | Livewire | 4 |
| UI components | Flux UI | 2 |
| CSS | Tailwind CSS | 4 |
| Auth backend | Laravel Fortify | 1 |
| Testing | Pest | 4 |
| Runtime | PHP | 8.3 |

---

## Local setup

**1. Install dependencies and initialise the environment**

```bash
composer run setup
```

This installs Composer and npm packages, copies `.env.example` → `.env`, generates an app key, and runs migrations.

**2. Start the development server**

```bash
composer run dev
```

Runs the Laravel server, queue listener, log viewer (Pail), and Vite dev server concurrently.

The app is available at **http://localhost:8000**.

**3. (Optional) Seed demo data**

```bash
php artisan db:seed
```

Creates a demo user (`demo@litstack.app` / `Passw0rd`) with a pre-populated shelf of classic books.

---

## Testing

```bash
php artisan test --compact
```

Tests use an in-memory SQLite database and run in isolation via `RefreshDatabase`. Feature tests cover auth flows, profile/security settings, and book shelf behaviour (cleanup, cover caching).

To also check code style before running tests:

```bash
composer run test
```

---

## Demo account

The app supports a publicly accessible demo account that visitors can log into without a password. This is intended for portfolio or staging deployments.

**Enable it** by setting these values in `.env`:

```dotenv
DEMO_ENABLED=true
DEMO_EMAIL=demo@litstack.app
```

When enabled, a "Continue as demo user" button appears on the login page. Visiting `/demo-login` logs the visitor in immediately (rate-limited to 10 requests/minute). Password changes and 2FA settings are disabled for the demo account.

**Seed the demo shelf** on a fresh deployment:

```bash
php artisan migrate --force
php artisan db:seed --force
```

`ShelfSeeder` uses `firstOrCreate` so re-running it is safe.

**Reset the demo data** at any time (clears all non-demo users and re-seeds the demo shelf, including deleting cached cover files):

```bash
php artisan demo:reset
```

This is also scheduled to run automatically every day at 04:00. To activate the scheduler on your server, add one cron entry:

```cron
* * * * * cd /var/www/litstack && php artisan schedule:run >> /dev/null 2>&1
```

On Laravel Cloud / Forge / Vapor, use the built-in Scheduler panel instead.

---

## Environment

Key `.env` values to review before deploying:

```dotenv
APP_ENV=production
APP_URL=https://your-domain.com

DB_CONNECTION=mysql   # sqlite by default; switch for production

MAIL_MAILER=smtp      # log by default

DEMO_ENABLED=false
DEMO_EMAIL=demo@litstack.app
```

Cover images are cached to `storage/app/public/covers/` — run `php artisan storage:link` once after initial setup if the symlink is not already present.
