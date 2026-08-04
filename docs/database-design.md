# Database Design

> Konvensi skema database — memformalkan pola yang sudah dipakai + pola baru dari 5 PRD, supaya
> migration baru konsisten tanpa perlu didiskusikan ulang tiap kali.

## 1. Household scoping

- Tabel milik household: `household_id` FK + trait `BelongsToHousehold` (global scope Eloquent) —
  **bukan** Filament native tenancy (`->tenant()`), lihat alasan di `CLAUDE.md`.
- `foreignId('household_id')->constrained()->cascadeOnDelete()` — kalau household dihapus, semua
  data turunannya ikut hilang (tidak ada orphan row).
- Data **tidak** household-scoped (sengaja): katalog bersama (`recipes`, `ingredients` — lihat
  `cooking.md`), tabel yang justru mendefinisikan boundary-nya sendiri (`households`,
  `household_user`, `admins`).

## 2. Foreign key: cascade vs null-on-delete

| Pola | Kapan dipakai | Contoh |
|---|---|---|
| `->cascadeOnDelete()` | Child tidak bermakna tanpa parent-nya | `recipe_ingredient.recipe_id`, `shopping_list_items.shopping_list_id` |
| `->nullOnDelete()` | Child tetap bermakna walau parent hilang (riwayat harus tetap ada) | `shopping_list_items.ingredient_id`, `transactions.category_id` |

Untuk kolom baru: tanya "kalau parent-nya dihapus, apakah baris ini masih berguna sebagai catatan
historis?" — kalau ya, `nullOnDelete()`; kalau tidak, `cascadeOnDelete()`.

## 3. Uang: `decimal(12,2)`, tidak pernah `float`/`double`

Semua kolom nominal uang pakai `$table->decimal('amount', 12, 2)` + model cast `decimal:2`. Lihat
`code-conventions.md` §5.

## 4. Field enum-like: string + PHP constants, bukan native DB enum

Lihat `code-conventions.md` §2 untuk alasannya. Migration: `$table->string('type')`, bukan
`$table->enum('type', [...])`.

## 5. Soft-hide (archive) vs hard-delete

Diperkenalkan di `finance.md` §6.8 (kategori/kantong custom) — **pola baru** di codebase ini
(sebelumnya tidak ada satu pun `SoftDeletes` yang dipakai).

- **`archived_at` (nullable timestamp)** — dipakai kalau data itu **bisa dipakai lagi** dan
  histori/relasinya **harus tetap utuh** meski disembunyikan dari tampilan aktif (kategori custom).
  Sengaja **bukan** `deleted_at`/Eloquent `SoftDeletes` bawaan — supaya tidak tertukar makna dengan
  semantik hard-delete/global-scope Eloquent yang biasa diasosiasikan dengan `SoftDeletes`.
  - Semua query "list data aktif" **wajib** `whereNull('archived_at')` — tidak ada default global
    scope otomatis seperti `SoftDeletes`, jadi ini tanggung jawab eksplisit tiap query/scope.
- **Hard-delete (baris benar-benar hilang)** — dipakai untuk data yang genuinely disposable/
  transient dan tidak dibutuhkan untuk audit/analisis (item shopping list, favorit, dst — perilaku
  existing yang **tidak** berubah).
- **7 kategori default di Finance dikunci** dari kedua opsi archive/delete (`is_default = true`,
  lihat `finance.md` §6.8) — baseline yang harus selalu ada untuk tiap household.

## 6. Periode: kolom `period_start` (date), bukan string bulan-kalender

Keputusan dari `finance.md` §6.11: karena household bisa custom tanggal reset periode
(`budget_period_reset_day`), representasi periode **tidak boleh** berupa string `"2026-08"` (ambigu
kalau periode tidak align ke tanggal 1). Semua tabel berbasis periode pakai `period_start` (tanggal
eksak mulai periode), dihitung dari service kalkulasi periode (`finance.md` §15 Fase 2).

Berlaku untuk: `budget_allocations`, `monthly_finance_summaries`, dan fitur berbasis periode
lainnya di masa depan.

## 7. Kolom snapshot (data turunan yang dipelihara, bukan agregat live)

Lihat `architecture.md` §4 untuk kapan pola ini dipakai. Konvensi kolom:
- `spent_amount`, `topup_total_amount` (di `budget_allocations`) — `decimal(12,2) default 0`.
- `count` (di `ai_usage_counters`) — `integer default 0`.
- `total_income`, `total_expense` (di `monthly_finance_summaries`) — `decimal(12,2) default 0`.

**Increment harus atomik** di level DB (`UPDATE ... SET count = count + 1` atau
`increment()`/`decrement()` Eloquent), **bukan** read-modify-write di level aplikasi — beberapa
request bisa datang nyaris bersamaan, race condition di sini berarti data drift tanpa terdeteksi.

## 8. Tabel event/riwayat insert-only

Lihat `architecture.md` §5. Kolom minimum: `household_id`, `user_id` (aktor), diskriminator
(`action`/`module`), `created_at` — **tidak perlu `updated_at`** (baris tidak pernah diubah).

## 9. Unique constraint: satu baris per (owner, periode)

Pola dipakai di `budget_allocations` (unique per `category_id`+`period_start`),
`ai_usage_counters` (unique per `household_id`+`period`), `monthly_finance_summaries` (unique per
`household_id`+`period_start`). Pertahankan pola ini untuk tabel period-based baru — mencegah baris
dobel di level DB, bukan cuma diandalkan dari validasi aplikasi.

## 10. Katalog tabel baru dari 5 PRD (referensi cepat)

| Tabel | PRD | Catatan |
|---|---|---|
| `admins` | admin-cms.md | Guard/model terpisah dari `users` |
| `ai_usage_counters` | billing.md | Snapshot kuota, DB-backed (bukan Cache) |
| `ai_usage_events` | billing.md | Insert-only, drill-down per user |
| `subscription_payments` | billing.md | Riwayat transaksi billing |
| `shopping_list_history` | shopping-list.md | Insert-only |
| `budget_allocations` | finance.md | Snapshot `spent_amount`/`topup_total_amount` |
| `budget_topups` | finance.md | Insert-only |
| `monthly_finance_summaries` | finance.md | Snapshot ringkasan bulanan |

Cek tabel ini dulu sebelum bikin migration baru yang mirip — kemungkinan besar polanya sudah ada,
tinggal diikuti.

## 11. Riwayat Perubahan

| Tanggal | Perubahan |
|---|---|
| 2026-07-30 | Draf awal — memformalkan konvensi skema dari codebase existing + 5 PRD (cooking, billing, shopping-list, admin-cms, finance). |
