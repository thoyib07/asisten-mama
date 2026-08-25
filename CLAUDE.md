# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A household-assistant SaaS for families ("asisten mama"): each family (household) gets its own
scoped data across modules. Modules: Cooking (recipe finder, ported from the standalone
cooking-mama-git prototype), Shopping List, Financial tracking, Bills, Calendar, Tasks, Household. Laravel 13 + Livewire 4
(customer-facing UI) + Filament 5 (admin/CRUD). PHP 8.3, PostgreSQL, Pest 4.

## Commands

composer install && npm install   # first-time setup
docker compose up                 # local dev: Postgres (5433) + app container
php artisan migrate --seed        # migrate + seed shared recipe catalog
composer dev                      # server + queue + pail logs + vite concurrently
composer test                     # clears config, then runs the full suite
php artisan test --filter=HouseholdIsolationTest
./vendor/bin/pint                 # lint/format (Laravel Pint)

Tests require a real PostgreSQL database (not sqlite) — `ilike` and other Postgres-specific
queries are used. phpunit.xml points at `asisten_mama_test` on 127.0.0.1:5433 (docker-compose's
db service, mapped off the standard 5432 to avoid clashing with other local Postgres instances).
Create the test DB once: `docker exec asisten-mama-db-1 psql -U postgres -c "CREATE DATABASE asisten_mama_test;"`.

## Architecture

### Two Filament panels, two guards, two tables

SaaS admins live in their own `admins` table (`App\Models\Admin`, guard `admin`), never in
`users`. That split is what makes the panel separation work at all:
`vendor/filament/filament/src/Auth/Pages/Login.php` calls `attemptWhen(..., canAccessPanel())`, so a
single panel can NOT be both "everyone's login page" and "admin-only backoffice" — gating
`canAccessPanel()` would block customers from logging in entirely. Separate guards sidestep it:
`User::canAccessPanel()` is never consulted for the admin panel.

- **`app` panel** (`AppPanelProvider`, path `''`, guard `web`) — the panel is `->default()` and
  deliberately has **no resources and no pages**. Its only job is customer auth: `/login`,
  `/register` (with the invite-code field), `/password-reset/*`. `bootstrap/app.php` routes guests
  through `filament()->getLoginUrl()`, so the default panel is what `/` redirects to.
  The empty path does **not** collide with Beranda: on Laravel 13+ Filament registers its panel
  `home` route only when no GET route exists at the panel root yet
  (`vendor/filament/filament/routes/web.php`), and `routes/web.php` claims `/` first. That means
  route-registration order matters — if `filament.app.home` ever shows up in `route:list` and `/`
  stops resolving to Beranda, that ordering is what broke.
- **`admin` panel** (`AdminPanelProvider`, path `/backoffice`, guard `admin`) — backoffice only.
  **Not** `/admin`: that URL was the *customer* login/registration page before the panels were
  split, so bookmarks for it are in circulation. `routes/web.php` permanently redirects
  `/admin/login` → `/login`, `/admin/register` → `/register`, `/admin` → `/`. One URL cannot serve
  two guards — a customer landing on an admin-guard login gets "credentials do not match" while
  holding the correct password.

  There is deliberately no public registration page. The *first* admin comes from `AdminSeeder`
  (env `ADMIN_NAME`/`ADMIN_EMAIL`/`ADMIN_PASSWORD`, wired into the Dockerfile `CMD`) or
  `php artisan make:saas-admin` locally; after that `AdminResource` at `/backoffice/admins` is the
  CRUD. `Admin::roleOptions()` is the single source of roles — command, form, and seeder all use it.

  **`AdminResource` is owner-only, in full.** Gating only `create` would be theatre: an admin who
  can still *edit* another admin can change the owner's password and log in as them. Non-owner
  admins change their own name/password through the panel's `->profile()` page instead. Deleting
  *yourself* is blocked, which is what guarantees at least one admin always survives without
  counting rows, and the resource registers **no** bulk delete (that path authorizes through
  `getDeleteAnyAuthorizationResponse()` with no per-record check, so one action could wipe every
  admin).

**Authorize Filament resources by overriding `get*AuthorizationResponse()`, never `can*()`.**
`can*()` is a derived helper (`Resource/Concerns/HasAuthorization.php:154`) consumed only by page
`mount()` guards and navigation. Actions route through
`Resources/Pages/Page::getDefaultActionAuthorizationResponse()`, which calls the `Response` methods
directly — so a `canDelete()` override looks correct, tests green against it, and the UI deletes the
row anyway. This shipped once in `AdminResource` and was caught only by driving the real table
action in a test. Test authorization by calling the action, not the helper.

`App\Http\Responses\LoginResponse` + `RegistrationResponse` override Filament's default
`redirect()->intended(Filament::getUrl())` so app-panel users land on `route('beranda')`. They
branch on panel id and defer to the parent for anything that isn't `app` — keep it that way, so a
bug there can't break admin login.

**Household-scoped models are invisible from the admin panel.** `BelongsToHousehold` filters on
`Auth::user()?->current_household_id`, and `Auth::user()` reads the *default* guard (`web`), which
is null when an admin is authenticated on the `admin` guard. It fails closed, so `households`,
`users` and `recipes` (none of them scoped) are fine — but any future admin resource over a scoped
table MUST use `withoutGlobalScope('household')` plus an explicit filter. Same trap as the ICS feed
route (see Bills module).

Filament page classes resolve against the *current* panel, so tests driving an admin resource need
both the guard and the panel: use the `actingAsSaasAdmin()` helper in `tests/Pest.php`.

### Multi-tenancy: Household, not Filament tenancy

The security boundary is `users.current_household_id` + the `App\Support\Concerns\BelongsToHousehold`
Eloquent global-scope trait — deliberately NOT Filament's native panel tenancy (`->tenant()`),
because that only scopes Filament Resources, not Livewire components or plain service classes,
both of which this app has plenty of. Every household-scoped model uses the trait; every
household-scoped query is invisible to any user not authenticated as a member of that household —
including via relation traversal (see `HouseholdIsolationTest`).

Scoping per table:
- `households`, `household_user` — not scoped (they define the boundary).
- `shopping_lists`, `categories`, `transactions`, `events`, `tasks`, `bills`, `bill_payments` —
  household-scoped via the trait.
- `recipes`, `ingredients`, `recipe_ingredient` — shared/global catalog, NOT household-scoped.
  AI-imported recipes become visible to every household. Accepted MVP tradeoff.
- `favorites`, `ratings` — scoped per `user_id` (personal preference, not household-shared).

`App\Models\Household::createWithOwner()` is the one entry point for household creation: attaches
the owner pivot row, sets `current_household_id` (via `forceFill` — it's not in User's fillable
list on purpose, since it's system-managed, not user-editable), and seeds default finance
categories, all in one transaction. Called from the custom Filament registration page
(`app/Filament/Pages/Auth/Register.php`).

### Module layout (custom, no package)

```
app/Modules/{Cooking,ShoppingList,Finance,Bills,Calendar,Tasks,Household}/
  Models/ Services/ Livewire/ Filament/ Providers/ routes/web.php
```

Each module has its own `{Module}ServiceProvider` (registered in `bootstrap/providers.php`) that:
1. Registers routes: `Route::middleware('web')->group(__DIR__.'/../routes/web.php')`. **Do not**
   use `$this->loadRoutesFrom()` alone — that does NOT inherit the `web` middleware group (that
   group is only auto-applied to the file passed to `withRouting()` in `bootstrap/app.php`), so
   routes loaded that way silently run without session/CSRF/auth. This bit us once; don't repeat it.
2. Registers Livewire components: `Livewire::addNamespace('cooking', classNamespace: 'App\Modules\Cooking\Livewire')`.
   This makes every class in that folder resolvable as `cooking::kebab-case-name` (e.g.
   `RecipeFinder` → `cooking::recipe-finder`) with zero per-component registration. Note:
   `Livewire::component('ns::name', Class::class)` does NOT work for namespaced names — Livewire's
   Factory checks `classNamespaces` first when a name contains `::` and never falls through to
   individually-registered names. Use `addNamespace`, not `component()`, for anything with a `::`.
3. Filament resources register via a `{Module}PanelPlugin implements Filament\Contracts\Plugin`,
   whose `register()` calls `$panel->discoverResources(...)` — this is native Filament, appends
   rather than overwrites, and keeps per-module Filament wiring out of the central panel provider.

`Household`, `User`, `BelongsToHousehold` stay in plain `app/Models` / `app/Support/Concerns` —
cross-cutting infra, not a feature module.

### Cooking module

`RecipeMatcher::search()` (`Services/Matching`) scores every recipe by matched/total ingredients
against `IngredientNormalizer::normalize()`'d input — exact-name matching, no fuzzy/synonym logic.
`GroqRecipeClient` (primary `AiRecipeClient` binding, zero-budget-proven) suggests 3 recipes via
Groq, parsed by `AiResponseParser`, imported by `AiRecipeImporter` (dedups on `LOWER(TRIM(name))`).
`Favorite`/`Rating` are `user_id`-scoped (real accounts now — the original prototype used anonymous
cookie/session tokens, since replaced).

### Finance module

A **period is not a calendar month.** `households.budget_period_reset_day` (1–28) sets when each
period starts, so a household on payday billing runs 25 Jul–24 Aug. Every date calculation goes
through `Finance\Services\FinancePeriod` — don't inline `startOfMonth()` anywhere. The 1–28 cap
is what makes `addMonth()` safe (no 31st-of-February ambiguity); widening it breaks that
invariant, not just the UI copy.

A **"kantong" (pocket) is not an entity** — it's a `budget_allocations` row (household, category,
`period_start`, amount) layered over an existing expense `Category`. Top-ups are insert-only rows
in `budget_topups`; they never mutate `budget_allocations.amount`, so "planned" and "topped up
mid-period" stay distinguishable. Effective budget = allocation + Σ topups.

**Summaries and remaining budget are computed on read, not stored.** `docs/prd/finance.md` §6.10
records the reversal: the original plan had `monthly_finance_summaries` plus
`spent_amount`/`topup_total_amount` snapshot columns maintained by `Transaction` model events.
That was dropped because `BelongsToHousehold` filters on `Auth::user()` and fails *closed*
silently — maintaining derived totals across tables inside model events puts that exact failure
mode (already seen in `BillSchedule::paidPeriods()`) on the write path, where drift is invisible.
`Finance\Services\Pockets::forPeriod()` is the single read path; if this ever measurably drags,
add caching there without touching any UI. Don't reintroduce write-path snapshots on a hunch.

**`category_id` is required for expenses at the validation layer only** (`FinancePage::save()`) —
the DB column stays nullable because income doesn't use pockets. Any *other* writer bypasses that
check: `Bills\Services\RecordBillPayment` is the existing one. Its fallback prefers a category
named "Tagihan" and falls back to the household's oldest expense category — and it looks that up
with `withoutGlobalScope('household')` + an explicit `where('household_id', $bill->household_id)`,
because the scope reads `Auth::user()` while the transaction is written as `$bill->household_id`.
A caller outside a web session would otherwise get an empty lookup, a null category, and a
pocket-less expense. A new cross-module writer of `Transaction` must do the same.

Categories carry `is_default` (the 7 seeds — renameable, never archivable) and `archived_at`
(a plain column, deliberately **not** Eloquent `SoftDeletes`, so archive can't be confused with
delete). The default lock is enforced in `KantongPage::archive()`, not only by hiding the button.

### Bills module

Bill due dates are never stored as rows. `bills.rrule` holds a raw RFC 5545 string, expanded on
demand by `BillSchedule` (`rlanvin/php-rrule`) — so recurring reminders need **no scheduler/cron**,
which matters because this app has none (`routes/console.php` is empty, no cron in the Dockerfile).

Reminders reach the family through an **ICS subscription feed**, not the Google Calendar API:
`calendar.events` is a Google "sensitive" scope requiring app verification, capped at 100 test users
until approved. See `docs/prd/tagihan.md` §6.3.

`IcsFeed` emits one all-day VEVENT **per occurrence** rather than one recurring VEVENT + RRULE.
Reason: events are placed on the *reminder* date, and shifting `DTSTART` does NOT shift a rule's
occurrences (`FREQ=MONTHLY;BYMONTHDAY=20` stays on the 20th regardless of `DTSTART`). Expanding in
PHP also means a user-typed RRULE never reaches Google verbatim. Don't "simplify" this back into an
RRULE + EXDATE.

**The feed route runs without a session** — the token is the auth. `BelongsToHousehold` filters on
`Auth::user()?->current_household_id`, which is `null` there, so every read on that path must use
`withoutGlobalScope('household')` plus an explicit household filter. This already bit once:
`BillSchedule::paidPeriods()` silently returned nothing when unauthenticated, resurrecting paid
bills in the calendar. There is a dedicated unauthenticated test for it.

`RecordBillPayment` writes a `Transaction` into Finance when a bill is marked paid — one-way
Bills → Finance, the same shape as `MissingIngredientsToShoppingList`.

### Calendar module

The family calendar reaches Google the same way Bills does — an **ICS subscription feed**, not the
Calendar API. The broad write scopes (`calendar`, `calendar.events`) are *sensitive*: verification
plus a hard, permanent 100-user cap per Cloud project while unverified. `calendar.app.created` is
narrower ("secondary calendars this app made") and **its classification is unverified** — check the
sensitivity column in the Cloud Console scope picker before planning anything on top of it.
Google Tasks doesn't fit the "assign a task to a family member" half either: task lists have no
account-to-account sharing and no assignee field (assignment exists only via Chat spaces / Docs,
neither of which this app has).

**The token is per `User`, not per `Household`** (`users.calendar_token`, distinct from
`households.calendar_token` which belongs to Bills). Deliberate: one feed carries the whole
household's agenda *plus only the token holder's own tasks*, so members' task lists don't pile up in
each other's calendars. `FamilyIcsFeed::forUser()` is the single entry point; `App\Support\Ics`
holds the RFC 5545 escaping/folding shared with `Bills\Services\IcsFeed`.

`/kalender/{token}.ics` **runs without a session**, exactly like the Bills feed — same trap, same
rule: every read there uses `withoutGlobalScope('household')` plus an explicit `household_id`
filter, and it fails silently (empty feed), not loudly.

Timed events are emitted as **floating local time** (`DTSTART:20260825T090000` — no `Z`, no
`TZID`). `app.timezone` is UTC while users type local wall-clock time, so stamping `Z` would shift
every event 7 hours in Google. Switch to `TZID` only alongside a per-household timezone column.

Google refreshes subscribed feeds roughly every 12–24 hours, with no way to force it. The
subscription panel on `/kalender` says so out loud — a user expecting today's task to appear today
will file it as a bug otherwise.

### Shopping List ↔ Cooking integration

`MissingIngredientsToShoppingList` takes `MatchResult::$missing` and adds each to the household's
shopping list, reusing `IngredientNormalizer`. Wired into `RecipeFinder`'s results via the
`shopping-list::add-missing-ingredients` Livewire component — the one deliberate cross-module
service dependency in the codebase.

### PWA

`public/manifest.json` + `public/sw.js`: network-first fetch with cache fallback, falling back
further to `public/offline.html`. Cache-first was tried during the original prototype and rejected
— it served stale HTML referencing deleted hashed Vite assets after deploys. Don't reintroduce it.

## Notes

- Zero-budget deploy target: Render (Dockerfile-based) + Neon Postgres + Groq AI — see `docs/DEPLOY.md`.
- Local dev: `docker-compose.yml` runs Postgres on host port 5433 (5432 was already taken by
  another local container on the dev machine — check before assuming 5432 is free elsewhere).
- Known deferred work: see `docs/ROADMAP.md`.
- Branching: `main` is the reference branch. `deploy-main` is what Render auto-deploys and must be
  kept in sync with `main` (fast-forward after merging, same discipline as cooking-mama-git).
