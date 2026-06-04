# Copilot Usage Dashboard

A Laravel application that shows per-developer GitHub Copilot usage with
charts powered by [noeka/svgraph](https://packagist.org/packages/noeka/svgraph).

- Developers log in with their GitHub account and see their own usage.
- Organisation admins see usage for every member and a leaderboard.
- Overviews per day / week / month / year / all-time.
- Zero JavaScript — charts rendered server-side as SVG.

## Requirements

- PHP 8.3+
- Composer
- SQLite (dev) or MySQL/PostgreSQL (prod)
- GitHub organisation with Copilot Business or Enterprise
- GitHub App with the **Copilot usage metrics** permission
- GitHub OAuth App for user login

> **Data availability:** Per-user metrics data starts from **2025-10-10** and
> requires the "user-level metrics policy" to be enabled in your org's Copilot
> settings (`Settings → Copilot → Policies → User engagement data`).

---

## Setup

### 1. Clone & install

```bash
git clone <repo-url> copilot-usage && cd copilot-usage
composer install
cp .env.example .env
php artisan key:generate
```

### 2. Create a GitHub App

1. Go to `https://github.com/organizations/<org>/settings/apps/new`.
2. Name it (e.g. *Copilot Usage Dashboard*).
3. Under **Permissions → Organisation permissions**, add:
   - **Copilot usage metrics** → Read-only
   - **Members** → Read-only (needed for org role detection)
4. Install the app on your org and note the **App ID** and **Installation ID**.
5. Generate a private key and save it as a `.pem` file on the server.

### 3. Create a GitHub OAuth App

1. Go to `https://github.com/organizations/<org>/settings/applications` → *New OAuth App*.
2. Set the **Authorization callback URL** to `https://your-domain/auth/github/callback`.
3. Note the **Client ID** and **Client Secret**.

### 4. Configure `.env`

Fill in the keys added at the bottom of `.env.example`:

```
GITHUB_ORG=your-org-login

GITHUB_APP_ID=123456
GITHUB_APP_INSTALLATION_ID=789012
GITHUB_APP_PRIVATE_KEY_PATH=/path/to/private-key.pem

GITHUB_CLIENT_ID=Ov23ct...
GITHUB_CLIENT_SECRET=...
GITHUB_REDIRECT_URL=https://your-domain/auth/github/callback

# Optional: extra admins beyond org owners
COPILOT_ADMIN_LOGINS=alice,bob
```

### 5. Migrate & seed

```bash
php artisan migrate

# Seed historical data (up to 1 year; data only from 2025-10-10)
php artisan copilot:sync-usage --backfill=365
```

### 6. Scheduler

Add a cron entry to run the Laravel scheduler (it syncs yesterday's data
daily at the time set by `COPILOT_SYNC_TIME`):

```cron
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

### 7. Serve

```bash
php artisan serve
```

---

## Artisan commands

| Command | Description |
|---|---|
| `copilot:sync-usage` | Sync yesterday's data |
| `copilot:sync-usage --day=2025-11-01` | Sync a specific day |
| `copilot:sync-usage --backfill=90` | Sync the last 90 days |

---

## Architecture notes

- **Data model**: `copilot_users` + `daily_usages` (one row per user per day).
  The full raw NDJSON record is stored in `daily_usages.raw` so no data is lost
  if field names change in a future API version.
- **Auth**: GitHub OAuth via Laravel Socialite. The backend checks org membership
  at login time; only active org members can log in.
- **Org credential**: A GitHub App installation token is obtained on demand and
  cached for ~50 minutes. The token is used only for the scheduled sync.
- **Charts**: `noeka/svgraph` renders line, bar, donut, and sparkline charts as
  plain SVG strings inline in Blade — no canvas, no JavaScript.
