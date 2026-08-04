# Code Convention

> Memformalkan gaya penulisan kode yang **sudah dipakai** di codebase existing, supaya modul baru
> (dari 5 PRD) konsisten dengan yang lama — bukan style guide generik PHP/Laravel.

## 1. Penamaan kelas

| Jenis | Pola | Contoh existing |
|---|---|---|
| Service, single-responsibility | `NounPhrase` yang mendeskripsikan satu tanggung jawab | `RecipeMatcher`, `IngredientNormalizer`, `AiRecipeImporter`, `AiResponseParser` |
| Service dengan implementasi bisa diganti (provider eksternal) | Interface `XClient`/`XService` polos, implementasi konkret diprefix nama provider | `AiRecipeClient` (interface) → `GroqRecipeClient` (implementasi) |
| Value object hasil kalkulasi | `Noun` readonly, bukan array asosiatif | `MatchResult` |
| Livewire component | Nama fitur, bukan `XController`/`XComponent` | `RecipeFinder`, `ShoppingListPage`, `FinancePage` |

**Untuk modul baru:** ikuti pola provider-swap kalau memang ada kemungkinan ganti implementasi
(mis. `finance.md` §11 — `AiRecipeClient` tetap satu interface walau model Groq-nya berganti
`qwen3-32b` vs `llama-3.3-70b-versatile`, tidak perlu interface baru cuma karena ganti model).

## 2. Konstanta, bukan native enum

Field string yang punya nilai terbatas (`type`, `source`, `action`) selalu **string column + PHP
class constants**, bukan Postgres native `enum` — supaya nilai baru bisa ditambah tanpa migration
ubah tipe kolom (alasan yang sama sudah dipakai untuk `meal_categories`/`cuisine_type` di
`cooking.md` §6.2).

```php
class Category extends Model
{
    public const TYPE_INCOME = 'income';
    public const TYPE_EXPENSE = 'expense';
}
```

Berlaku untuk field baru yang akan datang: `budget_topups`/`ai_usage_events`/`shopping_list_history`
punya kolom diskriminator (`action`, `module`) — pakai pola yang sama, bukan enum DB.

## 3. Model events: Observer class, bukan closure di `boot()`

Beberapa PRD (`finance.md` §6.9/§6.10, `billing.md` §6.2) butuh model event (`created`, `updating`,
`deleting`) untuk menjaga data snapshot tetap sinkron (lihat `architecture.md` §4). **Konvensi:**
pakai **Eloquent Observer class** (`app/Modules/{Module}/Observers/`), didaftarkan di
`{Module}ServiceProvider::boot()` lewat `Model::observe(Observer::class)` — bukan closure inline di
`static::booted()` model itu sendiri.

Alasan: Observer bisa dites terisolasi (instantiate & panggil method-nya langsung di Pest), dan
tidak menumpuk banyak closure tak bernama di satu method `boot()` kalau modelnya punya beberapa
event yang perlu ditangani.

```php
// app/Modules/Finance/Observers/TransactionObserver.php
class TransactionObserver
{
    public function created(Transaction $transaction): void { /* ... */ }
    public function updating(Transaction $transaction): void { /* ... */ }
    public function deleting(Transaction $transaction): void { /* ... */ }
}
```

## 4. Validasi: inline di Livewire method, bukan FormRequest

Pola existing: `$this->validate([...])` langsung di method Livewire (`FinancePage::save()`,
implisit di `RecipeFinder::addIngredient()`) — **bukan** `FormRequest` class terpisah. Livewire
component sudah jadi satu tempat yang menyatukan state + validasi + aksi; tidak perlu dipecah lagi
ke FormRequest yang menambah indirection tanpa manfaat jelas di konteks Livewire.

## 5. Uang: `decimal:2` cast, tidak pernah float

Semua kolom nominal uang (`amount` di `transactions`, `budget_allocations`, `budget_topups`,
`monthly_finance_summaries`) — migration `decimal(12,2)`, model cast `'amount' => 'decimal:2'`.
Tidak pernah `float`/`double` — presisi floating-point tidak boleh dipakai untuk uang.

## 6. Testing (Pest)

- Semua test Feature: `pest()->extend(TestCase::class)->use(RefreshDatabase::class)` (sudah
  dikonfigurasi global di `tests/Pest.php`) — jangan tambahkan trait ini manual per file.
- Helper fixture household+user: pola `makeHouseholdUser(string $name): User` yang sudah ada di
  `HouseholdIsolationTest.php` — kalau modul baru butuh fixture serupa dan dipakai di banyak file
  test, **pindahkan ke `tests/Pest.php`** sebagai fungsi global (bukan copy-paste per file test).
- Setiap modul baru dari 5 PRD **wajib** py test isolasi tenancy minimal satu (pola
  `HouseholdIsolationTest`), plus test untuk logic non-trivial yang disebutkan eksplisit di
  masing-masing PRD §15 (weighted scoring, quota enforcement, snapshot consistency, dst) — bukan
  cuma "ada test", tapi test yang **gagal kalau logic itu kebobol**.

## 7. Komentar: cuma untuk WHY yang tidak jelas dari kode

Pola yang sudah bagus di codebase (`IngredientNormalizer`, `IngredientPlausibility`,
`GroqRecipeClient`, `AiResponseParser`) — komentar menjelaskan **kenapa** sebuah keputusan diambil
(constraint tersembunyi, workaround, invariant yang bisa mengejutkan), bukan menjelaskan **apa**
yang kode lakukan (nama variabel/method sudah menjelaskan itu). Pertahankan gaya ini untuk kode
baru — jangan tambah komentar yang cuma mengulang nama method dalam bentuk kalimat.

## 8. PHP style

Laravel Pint (`./vendor/bin/pint`) — dijalankan sebelum commit, sudah didokumentasikan di
`CLAUDE.md`. Tidak ada aturan style tambahan di luar itu.

## 9. Penamaan migration & tabel baru

- Nama tabel: snake_case jamak (`budget_allocations`, `budget_topups`,
  `monthly_finance_summaries`, `shopping_list_history`, `ai_usage_events`, `ai_usage_counters`,
  `subscription_payments`, `admins`) — konsisten dengan tabel existing.
- Kolom timestamp periode: `period_start` (tipe `date`), **bukan** string bulan-kalender
  (`"2026-08"`) — keputusan dari `finance.md` §6.11 supaya kompatibel dengan periode custom
  (tanggal reset per household). Pakai pola ini untuk **semua** fitur berbasis periode ke depannya,
  jangan campur string bulan di satu tempat dan `date` di tempat lain.
- Lihat `database-design.md` untuk konvensi kolom & constraint lebih lengkap.

## 10. Riwayat Perubahan

| Tanggal | Perubahan |
|---|---|
| 2026-07-30 | Draf awal — memformalkan gaya kode existing + keputusan dari 5 PRD (cooking, billing, shopping-list, admin-cms, finance). |
