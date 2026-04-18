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

## Environment

Key `.env` values to review before deploying:

```dotenv
APP_ENV=production
APP_URL=https://your-domain.com

DB_CONNECTION=mysql   # sqlite by default; switch for production

MAIL_MAILER=smtp      # log by default
```

Cover images are cached to `storage/app/public/covers/` — run `php artisan storage:link` once after initial setup if the symlink is not already present.
