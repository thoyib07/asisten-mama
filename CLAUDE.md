# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A household-assistant SaaS for families ("asisten mama"): each family (household) gets its own
scoped data across modules. Modules: Cooking (recipe finder, ported from the standalone
cooking-mama-git prototype), Shopping List, Financial tracking, Calendar, Tasks, Household. Laravel 13 + Livewire 4
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

### Multi-tenancy: Household, not Filament tenancy

The security boundary is `users.current_household_id` + the `App\Support\Concerns\BelongsToHousehold`
Eloquent global-scope trait — deliberately NOT Filament's native panel tenancy (`->tenant()`),
because that only scopes Filament Resources, not Livewire components or plain service classes,
both of which this app has plenty of. Every household-scoped model uses the trait; every
household-scoped query is invisible to any user not authenticated as a member of that household —
including via relation traversal (see `HouseholdIsolationTest`).

Scoping per table:
- `households`, `household_user` — not scoped (they define the boundary).
- `shopping_lists`, `categories`, `transactions`, `events`, `tasks` — household-scoped via the trait.
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
app/Modules/{Cooking,ShoppingList,Finance,Calendar,Tasks,Household}/
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
