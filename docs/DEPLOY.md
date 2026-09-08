# Deploy Guide — Asisten Mama

Zero-budget stack, same as the cooking-mama-git prototype: Render + Neon + Groq.

## Prerequisites
- Neon PostgreSQL account (free tier): https://neon.tech
- Render account: https://render.com
- Groq API key: https://console.groq.com

## Steps

### 1. Create Neon Database
1. Sign up at neon.tech, create a new project.
2. Copy the `DATABASE_URL` connection string (postgres://...).
3. Neon free tier sleeps after 5 min inactivity — first request after sleep takes ~2-3 seconds.

### 2. Deploy to Render
1. Create a new **Web Service**, connect this repo's `deploy-main` branch.
2. **Runtime**: Docker (uses the `Dockerfile` at the repo root — the `production` stage).
3. **Instance type**: Free.
4. Add all environment variables from the table below.
5. Deploy. Container boot runs `migrate --force` → seeds the shared recipe catalog
   (`db:seed --class=RecipeSeeder --force`) → `config:cache` → serves on `$PORT`.
   Idempotent, safe on every redeploy.

### 3. Environment Variables

| Variable | Value |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | Your Render service URL |
| `APP_KEY` | `php artisan key:generate --show` locally |
| `DB_CONNECTION` | `pgsql` |
| `DATABASE_URL` | From Neon dashboard |
| `GROQ_API_KEY` | From console.groq.com |
| `SESSION_DRIVER` | `database` |
| `CACHE_STORE` | `database` |

Customers register themselves via `/register`, which creates their household automatically.

The SaaS admin lives in its own table and has no registration page. Render's free plan has **no
shell**, so `php artisan make:saas-admin` cannot be run after deploy — set these three env vars
instead and `AdminSeeder` (already in the Dockerfile `CMD`) creates the first owner on boot:

| Key | Value |
|---|---|
| `ADMIN_NAME` | Display name (defaults to `Owner`) |
| `ADMIN_EMAIL` | Login email |
| `ADMIN_PASSWORD` | Initial password |

The seeder only runs while the `admins` table is **empty**, so it is a no-op on every later boot —
and it doubles as the recovery path if every admin is ever lost. **Delete `ADMIN_PASSWORD` from the
Render dashboard once the account exists.**

### 4. Verify Deploy
- `/` — a guest sees the Beranda shell itself (tile grid, zero counts, "Belum ada apa-apa di
  sini." + Masuk/Daftar), not a redirect and not a separate marketing page.
- `/resep` and `/resep/{id}` — open to a guest; no favourite button, no rating, no FAB, no tabs.
- `/resep/bahan` — must still 302 to `/login` for a guest. This is the AI-quota boundary
  (`docs/prd/cooking.md` §7.4); if it ever returns 200 to a guest, anonymous visitors can drain
  the Groq quota.
- `/register` — sign up, confirm redirect into Beranda with a working household-scoped session.
- `/shopping-list`, `/finance`, `/akun` — accessible once logged in, empty-state renders correctly.
- `/manifest.json` — valid JSON; try "Add to Home Screen" on mobile.
- `/admin/login` and `/admin` — must 301 to `/login` and `/` (old customer bookmarks).
- `/backoffice/login` — only an `admins` row can log in here; a customer's credentials must be rejected.
- `/backoffice` — Filament backoffice loads with the overview widget plus Keluarga, Customer, and Recipes.
- `/backoffice/admins` — visible to an `owner`, forbidden (403) for a plain `admin`.

## Free-Tier Limits to Watch
- **Neon**: 512MB storage, compute sleeps after 5 min idle.
- **Render free**: spins down after 15 min inactivity (cold start on next request); local
  filesystem is ephemeral — recipe images uploaded via `/backoffice` are lost on redeploy/restart
  unless moved to external storage (S3, Cloudinary, etc.) — not solved yet, see `docs/ROADMAP.md`.
- **Groq API**: free-tier rate limit; AI recipe suggestions are cached 6 hours per unique
  ingredient set to stay well under it.

## Local development
`docker compose up` — runs a local Postgres (host port `5433`, remapped because port `5432` may
already be in use by another local Postgres container) plus an app container that installs
dependencies and serves on `localhost:8000`. See `docker-compose.yml`.
