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
* * * * * cd /var/www/litstack/current && php artisan schedule:run >> /dev/null 2>&1
```

On Laravel Cloud / Forge / Vapor, use the built-in Scheduler panel instead.

---

## Deployment

Demo and production deploy automatically via `.github/workflows/deploy.yml` on every push to `master`: a single build compiles `vendor/` and `public/build` once, and both servers extract that identical artifact, so demo and live always run the exact same build rather than two independently-built copies of the same source.

Each server uses a releases/shared/current layout rather than a single persistent checkout:

```
/var/www/litstack/
├── current -> releases/<commit-sha>/   # symlink; web server, queue worker, and cron all point here
├── releases/
│   └── <commit-sha>/                   # fresh checkout + built vendor/, public/build per deploy
└── shared/
    ├── .env                            # persists across every release
    └── storage/                        # persists across every release (covers, logs, framework cache)
```

Each deploy clones `master` fresh into `releases/<sha>`, symlinks `.env` and `storage/` in from `shared/`, runs migrations, and only then atomically re-points `current` at the new release — if a migration fails, `current` is left untouched and the site keeps serving the last good release. Old releases are pruned automatically, keeping the last 5.

**One-time setup required on a server before its first deploy under this layout** (`/var/www/litstack` for live, `/var/www/litstack-demo` for demo — run as the deploy user):

```bash
cd /var/www/litstack   # or litstack-demo

mkdir -p releases/initial shared
mv .env shared/.env
mv storage shared/storage

for item in *; do
  case "$item" in
    releases|shared) ;;
    *) mv "$item" releases/initial/ ;;
  esac
done

ln -s "$(pwd)/shared/.env" releases/initial/.env
ln -s "$(pwd)/shared/storage" releases/initial/storage
mkdir -p releases/initial/public
ln -sfn "$(pwd)/shared/storage/app/public" releases/initial/public/storage

ln -sfn "$(pwd)/releases/initial" current
```

Then point these at `current` instead of the fixed app root, and restart/reload each:
- **Web server / PHP-FPM document root** → `/var/www/litstack/current/public`
- **Queue worker** (Supervisor/systemd) → command should invoke `current/artisan`, e.g. `php /var/www/litstack/current/artisan queue:work`
- **Cron entry** (above) → already updated to `cd /var/www/litstack/current`

Verify the site still loads correctly against `current` before the next deploy runs. Until this migration is done on a given server, its deploy job fails safely at a guard check (`shared/.env missing`) rather than running against the old layout.

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
