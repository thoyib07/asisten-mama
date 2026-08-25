# Arsitektur

> Dokumen ini memformalkan pola arsitektur yang **sudah dipakai** di codebase existing dan pola
> **baru** yang diperkenalkan lewat 5 PRD (`docs/prd/cooking.md`, `billing.md`, `shopping-list.md`,
> `admin-cms.md`, `finance.md`) — supaya dikerjakan konsisten, bukan diciptakan ulang per modul.
> Ini pelengkap `CLAUDE.md` (yang isinya overview codebase), bukan pengganti.

## 1. Modularisasi

### 1.1 Struktur modul (sudah established)
```
app/Modules/{Module}/
  Models/ Services/ Livewire/ Filament/ Providers/ routes/web.php
```
Setiap modul baru **wajib** ikuti struktur ini. Tiap modul punya `{Module}ServiceProvider`
(didaftarkan di `bootstrap/providers.php`) yang:
1. `Route::middleware('web')->group(__DIR__.'/../routes/web.php')` — **bukan** `loadRoutesFrom()`
   (tidak inherit middleware `web`, insiden pernah terjadi, lihat `CLAUDE.md`).
2. `Livewire::addNamespace('{prefix}', classNamespace: 'App\\Modules\\{Module}\\Livewire')`.
3. Filament resource register lewat `{Module}PanelPlugin implements Filament\Contracts\Plugin`.

### 1.2 Kapan bikin modul baru
Household-scoped data yang punya feature area sendiri yang jelas (bukan cuma sub-fitur dari modul
lain). Kandidat yang sudah teridentifikasi tapi belum dibangun: **Inventory/kulkas** (disebut di
`shopping-list.md` §12), modul-modul di `docs/ROADMAP.md` (edukasi anak, perawatan anak sakit,
pengingat tagihan).

### 1.3 Infra lintas-modul (bukan modul)
`Household`, `User`, `Admin` (baru, lihat §3), `BelongsToHousehold` tetap di `app/Models` /
`app/Support/Concerns` — dipakai semua modul, bukan milik satu modul.

## 2. Aturan dependency antar-modul

**Satu arah, tidak boleh siklik.** Dependency lintas modul selalu lewat **satu service class
kecil** yang dipanggil dari sisi pemanggil (caller), modul yang dipanggil (callee) **tidak pernah**
tahu soal pemanggilnya.

Rantai yang sudah/akan ada (garis lurus, tidak ada siklus):
```
Cooking → ShoppingList → Finance
```
- `Cooking → ShoppingList`: `MissingIngredientsToShoppingList` (existing).
- `ShoppingList → Finance`: service pencatat pengeluaran belanja (`shopping-list.md` §6.7,
  `finance.md` §11) — nama service disarankan `RecordShoppingExpense` atau serupa.

**Sebelum menambah dependency baru antar modul**, cek: apakah ini menciptakan siklus (A→B dan
B→A)? Kalau iya, itu tanda desainnya perlu dipikir ulang — biasanya berarti ada konsep yang
harusnya diekstrak jadi infra lintas-modul (§1.3), bukan tetap di salah satu modul.

## 3. Pemisahan panel admin (terpasang 2026-08-25)

Sebelumnya cuma ada **satu panel Filament** (`/admin`) yang dipakai ganda: registrasi household
**dan** CRUD `RecipeResource` — tidak ada pemisahan role sama sekali, jadi household mana pun yang
login bisa mengedit katalog resep global. Sekarang **dua panel terpisah total**, guard dan tabel
berbeda:

| Panel | Path | Guard | Model | Isi |
|---|---|---|---|---|
| App (auth customer) | `/` | `web` | `User` | Hanya `/login`, `/register`, `/password-reset/*`. **Tanpa resource dan tanpa page.** |
| Backoffice | `/backoffice` | `admin` | `Admin` (tabel `admins`) | Filament Resource lintas household + halaman profil admin. |

Path-nya berbeda dari rencana di `admin-cms.md` (`/super-admin`): `/admin` sudah beredar sebagai
URL login **customer**, jadi dipakai sebagai redirect permanen ke `/login` dan tidak boleh
ditempati panel mana pun. Lihat header `admin-cms.md` untuk daftar lengkap penyimpangannya.

**Aturan 1 — tempat resource.** Resource Filament CRUD untuk data lintas-household **selalu** ke
panel `backoffice`. Panel `app` tidak boleh punya resource sama sekali. Kalau household perlu
melihat datanya sendiri, itu Livewire component biasa di sisi customer, bukan Filament Resource.

**Aturan 2 — global scope tidak berlaku di backoffice.** `BelongsToHousehold` memfilter lewat
`Auth::user()`, yang membaca guard *default* (`web`) dan bernilai null saat admin login di guard
`admin`. Gagalnya ke arah aman (query balik kosong), tapi resource backoffice atas tabel
household-scoped **wajib** `withoutGlobalScope('household')` plus filter eksplisit.

**Aturan 3 — otorisasi lewat `get*AuthorizationResponse()`, bukan `can*()`.** Aksi Filament
merutekan otorisasi lewat method `Response`; `can*()` cuma turunannya dan tidak pernah dilihat
DeleteAction. Ini pernah lolos ke kode sekali. Uji dengan menjalankan aksi tabel sungguhan.

## 4. Pola kuota & data turunan (snapshot), bukan hitung ulang

Diperkenalkan di `billing.md` (kuota AI) dan `finance.md` (ringkasan Finance, sisa budget kantong).
**Pola:** kalau sebuah angka (a) perlu dicek **sebelum** operasi mahal/berbayar (fail-fast), atau
(b) ditampilkan berkali-kali tanpa perlu presisi real-time absolut — simpan sebagai **kolom/tabel
snapshot yang dipelihara di write-path**, bukan query agregat yang dihitung ulang tiap render.

- Contoh: `ai_usage_counters.count` (billing.md), `budget_allocations.spent_amount` &
  `monthly_finance_summaries` (finance.md).
- **Konsekuensi wajib:** setiap titik tulis yang mempengaruhi angka ini (create/update/delete pada
  model sumber) harus menjaga snapshot tetap sinkron. Direkomendasikan lewat **Eloquent Observer
  class** (lihat `code-conventions.md` §3), bukan logic tersebar di banyak Livewire method.
- **Test wajib:** ada minimal satu test yang membandingkan nilai snapshot dengan hitung-ulang
  manual dari data sumber, supaya drift ketahuan (lihat pola di `finance.md` §15 Fase 9).

Kapan **tidak** perlu pola ini: kalau angkanya jarang dibaca, tidak butuh fail-fast, dan
querynya murah (mis. hitung ulang tetap oke). Jangan pakai snapshot cuma karena "kelihatannya lebih
cepat" — ini nambah kompleksitas (harus dijaga sinkron) yang cuma sepadan kalau ada alasan konkret.

## 5. Pola tabel event/riwayat insert-only

Diperkenalkan di `billing.md` (`ai_usage_events`), `shopping-list.md` (`shopping_list_history`),
`finance.md` (`budget_topups`). **Pola:** satu baris per kejadian, **tidak pernah** di-update atau
dihapus, dipakai untuk (a) audit/riwayat siapa-melakukan-apa-kapan, dan/atau (b) fondasi data untuk
analisis pola di masa depan (lihat `billing.md` §2.1 — rencana analisis kebiasaan lintas modul).

- Kolom minimum: `household_id`, aktor (`user_id`), diskriminator kejadian (`action`/`module`),
  `created_at`. Tidak butuh `updated_at` (baris tidak pernah berubah).
- Sering **dipasangkan** dengan tabel snapshot (§4) — satu untuk penegakan cepat (snapshot), satu
  untuk drill-down granular (event table) — bukan satu tabel merangkap dua fungsi.

## 6. Fitur AI lintas modul

Saat ini AI cuma ada di Cooking (`GroqRecipeClient`), tapi `billing.md` merencanakan AI meluas ke
semua modul dengan **kuota gabungan satu pool per household** (bukan kuota per-modul). Kalau modul
baru menambah fitur AI:
- Panggil lewat `AiUsageQuota` (nama service dari `billing.md` §6.5/§15), cek kuota **sebelum**
  memanggil provider AI (fail-fast, konsisten dengan §4).
- Jangan bikin mekanisme kuota sendiri per modul — pool-nya sengaja gabungan.

## 7. Riwayat Perubahan

| Tanggal | Perubahan |
|---|---|
| 2026-07-30 | Draf awal — memformalkan pola dari codebase existing + 5 PRD (cooking, billing, shopping-list, admin-cms, finance) menjadi acuan arsitektur lintas modul. |
