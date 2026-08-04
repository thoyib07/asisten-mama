# PRD: Modul Cooking

> **Status dokumen:** Baseline — ditulis dengan cara reverse-engineer dari kode yang sudah ada
> (bukan spec yang mendahului implementasi). Tujuannya merekam kontrak perilaku saat ini sebagai
> titik referensi sebelum modul ini diubah lebih lanjut. Bagian "Gap & Next Steps" membedakan mana
> yang sudah jadi vs. yang sengaja ditunda.

## 1. Ringkasan

Modul Cooking membantu anggota keluarga menemukan resep berdasarkan bahan yang mereka punya di
rumah, dengan dua sumber resep: katalog resep yang sudah ada (seed/admin-input) dan resep baru
yang di-generate AI (Groq) saat katalog lokal tidak punya kecocokan bagus. Pencarian bisa difilter
lebih spesifik berdasarkan kategori makan (sarapan/makan siang/makan malam/cemilan/dst) dan jenis
masakan (Indonesia/Jepang/Barat/Korea/dst) supaya hasil AI tidak "berhalusinasi" di luar konteks
yang diminta (lihat §6.2). Resep yang ditemukan bisa di-favoritkan, diberi rating,
dan bahan yang belum ada bisa langsung didorong ke Shopping List household (§6.7).

## 2. Latar Belakang & Masalah

**Rumusan masalah:** Anggota keluarga sering punya bahan masakan di rumah tapi tidak tahu resep
apa yang bisa dibuat dari kombinasi bahan itu, dan mencari resep di internet secara manual makan
waktu serta hasilnya belum tentu cocok dengan bahan yang benar-benar tersedia.

Prototipe awal (`cooking-mama-git`) sudah membuktikan pendekatan match-by-ingredient + AI fallback
ini bekerja secara zero-budget (Groq free tier). Modul ini adalah port dari prototipe tersebut ke
dalam arsitektur household-scoped SaaS (lihat `CLAUDE.md`), dengan akun asli menggantikan token
anonim berbasis cookie/session.

## 3. Goals & Non-Goals

**Goals:**
- Pengguna bisa memasukkan daftar bahan dan langsung melihat resep yang cocok, diurutkan dari
  yang paling banyak match.
- Kalau tidak ada/sedikit resep cocok, pengguna bisa memicu pencarian AI untuk dapat ide resep
  baru — hasilnya otomatis masuk ke katalog bersama (bukan cuma ditampilkan sekali pakai).
- Pengguna bisa memfilter pencarian (lokal maupun AI) berdasarkan kategori makan (multi-select)
  dan jenis masakan, supaya hasil AI tidak ngarang resep di luar konteks yang diminta (lihat §6.2).
- Bahan yang belum dipunyai user bisa langsung ditambahkan ke shopping list household dengan satu
  aksi (§6.7).
- Pengguna bisa menandai resep favorit dan memberi rating (1x per resep) untuk dirinya sendiri.
- Admin (Filament) bisa CRUD resep + bahan secara manual.

**Non-Goals (sengaja tidak dibangun di baseline ini):**
- Rekomendasi personalisasi berbasis histori/preferensi rasa.
- Fuzzy/synonym matching antar bahan (mis. "cabe" vs "cabai") — saat ini exact-name match saja
  setelah normalisasi.
- Resep per-household (katalog resep bersifat global/shared, lihat §12 Gap & Next Steps).
- Meal planning / penjadwalan menu mingguan.
- Validasi otomatis kepatuhan AI terhadap kategori/jenis masakan yang diminta — diputuskan cukup
  andalkan instruksi prompt saja untuk saat ini (lihat §6.4, §10, §14).

## 4. Target Users / Personas

| Persona | Deskripsi | Kebutuhan utama |
|---|---|---|
| **Ibu rumah tangga (primary)** | Mengatur masakan harian keluarga, sering cek kulkas dulu sebelum belanja | Cari resep cepat dari bahan yang ada, hindari beli bahan yang mubazir |
| **Anggota keluarga lain** | Ikut lihat/favoritkan resep, kadang masak juga | Lihat resep favorit keluarga, kasih rating |
| **Admin/Owner household** | Mengelola data resep (Filament panel) | Tambah/edit resep manual, kurasi kualitas katalog |

## 5. User Flows

**Flow A — Cari resep dari bahan (RecipeFinder):**
1. User ketik nama bahan satu-satu → sistem normalisasi (`IngredientNormalizer`) dan validasi
   plausibilitas lokal (`IngredientPlausibility` + `IngredientCatalog`) sebelum diterima ke daftar.
2. User (opsional) pilih satu atau lebih kategori makan (sarapan/makan siang/makan
   malam/cemilan/dst — multi-select) dan/atau jenis masakan (Indonesia/Jepang/Barat/Korea/dst)
   sebagai filter tambahan (lihat §6.2).
3. User klik cari → `RecipeMatcher` skor semua resep di katalog berdasarkan
   `matched/total ingredients`, disaring dulu oleh filter kategori/jenis masakan jika dipilih, urut
   skor tertinggi dulu (tie-break: missing paling sedikit).
4. Jika hasil kurang memuaskan, user klik "cari dengan AI" → bahan yang lolos validasi (whitelist
   lokal atau `IngredientValidator` via Groq) dikirim ke `GroqRecipeClient` bersama filter
   kategori/jenis masakan yang dipilih (anti-hallucination guard, lihat §6.4), hasil di-parse
   (`AiResponseParser`) dan diimpor ke katalog (`AiRecipeImporter`, dedup by
   `LOWER(TRIM(name))`), lalu pencarian dijalankan ulang otomatis dengan katalog yang sudah
   bertambah.
5. Untuk tiap resep hasil pencarian, bahan yang "missing" bisa didorong ke shopping list household
   lewat komponen `shopping-list::add-missing-ingredients` (§6.7).

**Flow B — Jelajah katalog (RecipeList / FavoritesList):**
- Browse semua resep dengan search by nama + infinite "load more" (step 12), atau filter khusus
  favorit sendiri (`FavoritesList` mengunci filter favorit).

**Flow C — Detail resep:**
- Halaman `/recipes/{recipe}` menampilkan resep + bahan. Tombol favorit (toggle) dan rating
  (1–5 bintang, sekali per user per resep, tidak bisa diubah setelah submit) tersedia di sini.

**Flow D — Admin kelola resep (Filament):**
- CRUD resep: nama, langkah (textarea 1 baris = 1 langkah), gambar upload, jumlah porsi, sumber
  (seed/ai), bahan (multi-select relasi, bisa create-on-the-fly).

## 6. Functional Requirements

### 6.1 Ingredient input & validation
- Normalisasi: lowercase, trim tanda baca, hapus qualifier trailing seperti "30%" atau angka
  takaran (`IngredientNormalizer`).
- Diterima jika: sudah ada di tabel `ingredients`, ATAU cocok (dengan toleransi typo Levenshtein
  ≤1 untuk kata ≥4 huruf) dengan daftar statis `resources/data/common_ingredients.php`, ATAU lolos
  heuristik "terlihat seperti kata" (`IngredientPlausibility`: 2–40 karakter, tidak ada digit, ada
  vokal, tidak ada runtun konsonan >4).
- Ditolak dengan pesan error inline jika tidak lolos salah satu di atas.
- Duplikat (case-insensitive, sudah dinormalisasi) di dalam satu sesi pencarian tidak ditambahkan dua kali.

### 6.2 Filter kategori makan & jenis masakan
- User bisa (opsional) memilih **satu atau lebih kategori makan** (multi-select) — mis. sarapan,
  makan siang, makan malam, cemilan, dan kategori lain yang bisa ditambah kemudian — dan/atau
  **satu atau lebih jenis masakan** (multi-select juga) — mis. Indonesia, Jepang, Barat, Korea, dst
  — sebagai filter tambahan di samping daftar bahan.
- Filter multi-select ini bersifat OR di dalam masing-masing dimensi (mis. pilih "sarapan" +
  "cemilan" → tampilkan resep yang cocok salah satu).
- **Satu resep boleh ditag lebih dari satu kategori makan sekaligus** (mis. resep yang cocok untuk
  "makan siang" DAN "makan malam") — `meal_categories` disimpan sebagai **array/jsonb**, bukan
  string tunggal. **Jenis masakan (`cuisine_type`) tetap satu nilai per resep** (string tunggal) —
  asumsi: satu resep jarang punya lebih dari satu jenis masakan sekaligus; ralat kalau ternyata
  cuisine juga perlu multi-tag per resep.
- Filter ini berlaku untuk **pencarian lokal** (§6.3) maupun **pencarian AI** (§6.4) — bukan cuma
  jadi instruksi tambahan ke prompt AI, supaya user juga bisa menyaring katalog yang sudah ada.
- Kedua filter opsional, default "semua kategori" / "semua jenis masakan" — tidak wajib diisi
  supaya tidak menghalangi pencarian by-bahan biasa yang sudah ada.
- Nilai kategori (`meal_categories`, array/jsonb) & jenis masakan (`cuisine_type`, string) disimpan
  di tabel `recipes` — bukan Postgres native enum, supaya daftar pilihan gampang ditambah tanpa
  migration ubah tipe kolom. Daftar nilai valid divalidasi di level aplikasi (konstanta PHP), bukan
  DB constraint.
- Resep lama (seed) yang belum ditag kategori/jenis masakan tetap tampil kalau user tidak memakai
  filter ini (kolom nullable). **Data lama sengaja tidak di-backfill** — dibiarkan kosong/`null`
  dan diperbaiki manual satu-satu lewat Filament kalau/kapan diperlukan, bukan prasyarat rilis
  fitur ini (lihat §14).
- **Framing eksplorasi:** saat kategori dipilih, pertanyaan ke katalog/AI dibingkai sebagai
  "makanan yang bisa dibuat untuk kategori [X] dengan bahan-bahan berikut: [daftar bahan]" — bukan
  "resep apa saja yang memakai bahan ini" secara generik. Kategori jadi konteks utama, bahan tetap
  jadi batasan wajib (lihat §6.4).

### 6.3 Pencarian lokal (RecipeMatcher)
- Skor = `jumlah bahan cocok / total bahan resep`, dibulatkan 4 desimal.
- Resep tanpa bahan sama sekali dikecualikan dari pencarian (`has('ingredients')`).
- Urutan hasil: skor desc, lalu jumlah missing asc.
- Jika user mengisi filter kategori makan dan/atau jenis masakan (§6.2), resep disaring dulu
  sebelum discoring — kategori pakai overlap array (`orWhereJsonContains` per nilai terpilih di
  dalam satu grup `where`, karena `meal_categories` array per-resep — `whereJsonContains` sendiri
  cuma containment satu nilai, bukan overlap multi-value), jenis masakan pakai `whereIn('cuisine_type',
  $cuisineTypes)` — filternya multi-select (bisa pilih Indonesia **dan** Jepang sekaligus, cocok
  kalau resep bertag salah satunya), meskipun kolom `cuisine_type` sendiri tetap satu nilai per
  resep (§6.2, §7). **Koreksi 2026-08-05:** implementasi awal Fase 2 (eksekusi §15) sempat keliru
  membuat filter ini single-select (`<select>` tunggal) alih-alih multi-select seperti yang sudah
  didesain di §6.2 — dilaporkan user, sudah diperbaiki jadi checkbox multi-select (lihat §14).
  Resep yang belum ditag (`null`/array kosong) tidak ikut muncul saat filter yang bersangkutan aktif.
- **Bobot bahan utama:** skor tidak lagi menghitung semua bahan setara. Bahan yang ditandai
  `is_primary` (lihat §7) dihitung dengan bobot lebih tinggi daripada bahan sekunder/bumbu. Formula
  jadi `Σ bobot bahan cocok / Σ bobot total bahan resep`, dengan bobot default: bahan utama = 2,
  bahan lain = 1 (**di-hardcode**, bukan konfigurasi — lihat §14). Bahan/pivot lama yang belum
  ditandai (`is_primary = null`) diperlakukan sebagai bobot 1 (non-primary) supaya scoring tidak
  error untuk data lama yang belum di-backfill.

### 6.4 Pencarian AI (Groq)
- Hanya bahan yang lolos whitelist lokal ATAU `IngredientValidator` (tanya Groq "apakah ini nama
  bahan yang masuk akal?", cache permanen per nama) yang dikirim ke prompt resep.
- Bahan yang diabaikan ditampilkan ke user sebagai notice, bukan silent drop.
- Response Groq di-cache 6 jam per kombinasi bahan + kategori makan + jenis masakan terpilih (key:
  md5 dari bahan ternormalisasi & terurut, digabung kategori & jenis masakan terurut) supaya query
  identik tidak memanggil API berulang.
- Hasil AI diparse dari JSON (toleran terhadap code-fence ```json``` dan prosa di sekitar array
  JSON), lalu diimpor ke tabel `recipes`/`ingredients` permanen — bukan tampilan sekali pakai.
  Resep dedup by nama (case/whitespace-insensitive); resep yang sudah ada dilewati.
- Rate limit Groq (HTTP 429) dan kegagalan lain ditangkap dan ditampilkan sebagai pesan error
  ramah pengguna, bukan stack trace.
- **Anti-hallucination guard:** `RecipePrompt::build()` dibingkai sebagai "berikan ide
  resep untuk kategori makan [kategori terpilih] yang bisa dibuat dengan bahan-bahan berikut:
  [daftar bahan]" ditambah jenis masakan kalau dipilih — bukan prompt generik "resep pakai bahan
  ini". Konteks pencarian (kategori + jenis masakan + bahan) **tidak boleh keluar dari inputan
  yang diberikan user**; ini ditegakkan murni lewat kekuatan instruksi prompt (di-enhance di
  `RecipePrompt`), **tanpa lapisan validasi otomatis tambahan** — keputusan sadar untuk MVP, lihat
  §10 & §14. Resep hasil AI ikut disimpan dengan `meal_categories` (bisa lebih dari satu — diisi
  dari seluruh kategori yang dipilih user saat generate, bukan AI yang memutuskan sendiri cocok
  kategori mana) dan `cuisine_type` (satu nilai) sesuai filter yang dipakai saat generate — kalau
  user memilih **lebih dari satu** jenis masakan sekaligus (filter multi-select, §6.2/§6.3), resep
  hasil AI-nya **tidak** ditag `cuisine_type` (dibiarkan `null`) karena ambigu jenis masakan mana
  yang berlaku untuk satu resep tunggal; hanya distamp otomatis kalau tepat satu jenis masakan yang
  dipilih. Prompt tanpa filter (kategori/jenis "semua") berperilaku sama seperti sebelumnya. Prompt
  juga menyertakan guardrail keamanan pangan: melarang teknik pengalengan/fermentasi/curing/
  simpan-bahan-mentah-dalam-minyak, dan meminta metode paling konvensional kalau ragu — plus badge
  "Resep dari AI — cek kematangan & kebersihan sendiri" di kartu resep `source: ai`
  (`recipe-finder.blade.php`, `recipes/show.blade.php`).
- **Tagging bahan utama, gramasi, durasi per-langkah & nilai gizi dari AI (2026-08-05):** format
  ingredients yang diminta ke Groq adalah array objek `{name, is_primary, quantity}` (bukan array
  string polos) — `quantity` adalah perkiraan gramasi/takaran bahan (mis. "200 gram", "2 butir"),
  disimpan ke kolom `quantity` di pivot `recipe_ingredient` (kolom ini sudah ada sejak awal tapi
  baru diisi mulai sekarang). Format steps berubah dari array string jadi array objek
  `{text, duration_minutes}` — durasi per langkah dalam menit, boleh `null`. Groq juga diminta
  `duration_minutes` (total resep) dan `nutrition` (objek `{calories, protein, carbs, fat}`,
  perkiraan kasar untuk keseluruhan resep) sebagai field baru di level resep. Semua field baru ini
  **opsional/boleh `null`** — AI boleh tidak memberikan perkiraan kalau tidak yakin, ditangani
  sebagai "tidak ada data" (§7), bukan error. Sama seperti guard kategori/jenis masakan, ini murni
  mengandalkan kepatuhan AI terhadap instruksi prompt — kalau AI salah tandai atau meleset
  perkiraannya, dampaknya cuma ke akurasi data tampilan (§6.3, §7), bukan fatal; admin bisa koreksi
  manual lewat Filament (§6.8). Perubahan kontrak ini disertai bump cache key Groq
  `groq:v2:` → `groq:v3:` (`GroqRecipeClient`) supaya response lama (shape lama) yang masih
  ke-cache tidak gagal di-parse.
- **Admin mengedit langkah resep AI lewat Filament menghapus durasi per-langkahnya:** form admin
  (§6.8) untuk `steps` masih `Textarea` polos satu-baris-satu-langkah tanpa input durasi — begitu
  admin menyimpan lewat form ini, semua `duration_minutes` per langkah yang tadinya diisi AI
  ke-reset jadi `null` (bukan dipertahankan), karena Textarea cuma tahu teksnya. Keterbatasan yang
  disengaja untuk sekarang (bukan bug tersembunyi — ada peringatan eksplisit di helper text form,
  lihat §6.8), diselesaikan kalau/kapan editor `steps` di-upgrade jadi `Repeater` (§12).

### 6.5 Rate limiting AI generation (**akan disupersede**)

> **Catatan supersession:** desain di bawah ini (cap app-wide + per-household harian via
> `Cache::increment`) khusus melindungi kuota gratis Groq yang shared. Begitu `docs/prd/billing.md`
> diimplementasikan (upgrade ke API AI berbayar + tier Premium/Free), mekanisme ini **diganti**
> oleh kuota bulanan berbasis tier & household yang persisten di DB (lihat `billing.md` §5–§7),
> bukan dijalankan paralel. Bagian di bawah tetap berlaku sampai billing PRD itu diimplementasikan.
- **Cap app-wide:** diimplementasikan di `App\Modules\Cooking\Services\AiQuotaGuard`. Satu counter
  harian untuk seluruh aplikasi (key `ai-quota:app:{tanggal}` via `Cache::increment`, TTL
  `now()->endOfDay()`) membatasi total panggilan `suggest()` ke Groq per hari — angka **di-hardcode
  25/hari** (titik tengah dari rentang ~20–30 yang direncanakan, lihat §14), disesuaikan dengan
  realita limit free tier Groq: `llama-3.3-70b-versatile` = 30 RPM / 1.000 RPD / 12K TPM /
  **100K TPD** — dengan `max_tokens: 4000` per panggilan, TPD adalah constraint yang kena duluan,
  jauh di bawah 1.000 RPD. Angka ini boleh di-tuning belakangan (tanpa perlu keputusan produk baru)
  berdasarkan metrik "AI quota rejection rate" (§9). Catatan implementasi: cache store default app
  ini adalah `database`, dan `Cache::increment()`-nya tidak auto-create key yang belum ada (beda
  dari cache store `array`/`redis`) — `AiQuotaGuard` memanggil `Cache::add($key, 0, $ttl)` sebelum
  `increment()` untuk menghindari itu.
- **Cap per-household:** counter harian tambahan per household (key
  `ai-quota:household:{household_id}:{tanggal}`) supaya satu household tidak menghabiskan kuota
  app-wide sendirian — angka **di-hardcode 7/hari** per household (titik tengah dari rentang
  ~5–10), sama-sama boleh di-tuning belakangan.
- Kedua cap dicek **sebelum** memanggil Groq (fail fast, hemat kuota) — kalau salah satu kena,
  tampilkan pesan ramah yang membedakan penyebabnya (mis. "Kuota AI household kamu hari ini sudah
  habis" vs "Kuota AI aplikasi hari ini sudah habis, coba lagi besok"). Wired di
  `RecipeFinder::exploreWithAi()`, dicek pakai `current_household_id` user yang login — **setelah**
  filter plausibilitas bahan (§6.4), supaya percobaan yang gagal di filter itu (mis. semua bahan
  tidak dikenali AI) tidak ikut memakan kuota harian.
- `IngredientValidator` (§6.1) **tidak** ikut cap ini — token-nya kecil (`max_tokens: 5`) dan
  hasilnya sudah di-cache permanen per nama bahan, jadi tidak berkontribusi signifikan ke TPD.

### 6.6 Favorit & Rating
- Favorit: toggle per (`user_id`, `recipe_id`), unique constraint di DB. Butuh login (guard
  `auth()->check()` di Livewire method).
- Rating: 1–5 (di-clamp), satu kali per (`user_id`, `recipe_id`) — unique constraint dipertahankan.
  User bisa **mengubah** rating yang sudah pernah dia beri (`Rating::updateOrCreate`, update value
  di baris yang sama, bukan insert baris baru) — mengatasi kasus salah pencet bintang. Rata-rata &
  jumlah rating (`RecipeRating::refreshStats()`) dihitung ulang otomatis setiap update.

### 6.7 Integrasi Shopping List
- Bahan "missing" dari hasil match bisa didorong ke shopping list household (bukan per-user) via
  `MissingIngredientsToShoppingList` — dedup by nama ternormalisasi per list, mencatat `added_by`.
- Modul Shopping List sudah tersedia dan bisa dipakai, jadi integrasi ini **aktif** di flow utama
  (bukan fitur yang ditunda).

### 6.8 Admin (Filament RecipeResource)
- Form: nama, langkah (textarea multi-baris → dinormalisasi jadi array lewat `RecipeSteps`),
  gambar (upload lokal), servings, source (seed/ai), bahan (relasi many-to-many, searchable,
  bisa create-on-the-fly).
- Tabel: nama, source (badge), servings, created_at — searchable/sortable by nama.
- Form punya `Select::make('meal_categories')->multiple()` dan `Select::make('cuisine_type')`
  (single) supaya admin bisa tag resep secara manual (resep baru maupun edit resep lama kalau
  perlu — bukan kewajiban backfill masal).
- Form bisa menandai bahan mana yang "bahan utama" per resep (`is_primary` di pivot) lewat satu
  `Select::make('primary_ingredient_ids')` multiple, reaktif terhadap bahan yang sudah dipilih di
  field `ingredients` (`dehydrated(false)`, disinkron ke pivot lewat `afterCreate`/`afterSave` di
  `CreateRecipe`/`EditRecipe` — sengaja **setelah** Filament menyinkron relasi `ingredients`, karena
  `Select::relationship()` bawaan Filament me-replace pivot row lewat `sync()` tanpa mempertahankan
  kolom pivot custom seperti `is_primary`).
- **(2026-08-05)** Form punya `TextInput::make('duration_minutes')` (total menit) dan
  `Fieldset` "Perkiraan Nilai Gizi" berisi 4 `TextInput` (`nutrition.calories`, `.protein`,
  `.carbs`, `.fat`) lewat dot-notation ke kolom `nutrition` (json). Kalau admin membiarkan
  keempatnya kosong, `RecipeResource::normalizeNutrition()` (dipanggil dari
  `mutateFormDataBeforeCreate`/`mutateFormDataBeforeSave`) mengubahnya jadi `null` — bukan array
  4 nilai `null` — supaya section "Perkiraan Nilai Gizi" di halaman resep tidak ikut nongol kosong.
- **(2026-08-05)** Gramasi per bahan (`quantity` di pivot) diisi lewat `Section` "Gramasi bahan"
  berisi satu `TextInput` per bahan terpilih, dibuat dinamis dari `Get $get('ingredients')` (pola
  sama dengan `primary_ingredient_ids`), state path `ingredient_quantities.{ingredient_id}`,
  disinkron ke pivot lewat `RecipeResource::syncIngredientQuantities()` di
  `afterCreate`/`afterSave` — sama seperti `is_primary`, dijalankan setelah sync relasi bawaan
  Filament.

## 7. Data Model

| Tabel | Kolom kunci | Catatan scoping |
|---|---|---|
| `recipes` | name, steps (json — array `{text, duration_minutes}`), image_url, source (seed\|ai), servings, `duration_minutes` (int, nullable, total menit), `nutrition` (json, nullable — `{calories, protein, carbs, fat}` atau `null` kalau tidak ada datanya), `meal_categories` (jsonb, nullable), `cuisine_type` (string, nullable) | Global/shared — **tidak** household-scoped |
| `ingredients` | name (unique) | Global/shared |
| `recipe_ingredient` | recipe_id, ingredient_id, `quantity` (string, nullable — gramasi/takaran mis. "200 gram"), `is_primary` (bool, nullable) | Pivot, composite PK |
| `favorites` | recipe_id, user_id (unique pair) | Personal, per-user |
| `ratings` | recipe_id, user_id (unique pair), value (1–5) | Personal, per-user |

> `meal_categories` (array/jsonb, nullable, di `recipes` — satu resep boleh multi-kategori),
> `cuisine_type` (string, nullable, di `recipes` — satu nilai per resep), dan `is_primary` (bool,
> nullable, di pivot `recipe_ingredient`) ditambahkan lewat migration
> `2026_08_04_000001_add_meal_categories_and_cuisine_type_to_recipes_table` dan
> `2026_08_04_000002_add_is_primary_to_recipe_ingredient_table`. **Tidak ada backfill masal** untuk
> resep seed lama; kolom dibiarkan `null`/array kosong dan diperbaiki manual per-resep lewat
> Filament kalau/kapan diperlukan (§14).
>
> `duration_minutes` & `nutrition` di `recipes` ditambahkan lewat migration
> `2026_08_04_000000_add_duration_and_nutrition_to_recipes_table` (dibuat sesi sebelumnya, baru
> benar-benar dipakai — diisi & ditampilkan — mulai 2026-08-05, lihat §14). Kolom `quantity` di
> pivot `recipe_ingredient` sudah ada sejak migration awal modul ini tapi baru mulai diisi mulai
> 2026-08-05 juga. Shape `steps` (json) berubah dari array string polos jadi array objek
> `{text, duration_minutes}` — **tidak ada migration/backfill data untuk ini**, baris lama yang
> masih array string dibaca apa adanya oleh accessor `Recipe::steps` sebagai
> `{text: <string lama>, duration_minutes: null}` tanpa perlu migration data (lihat §14).

## 8. Non-Functional Requirements

- **Tenancy/keamanan:** Resep & bahan sengaja global (bukan household-scoped) — tradeoff MVP yang
  disadari (lihat §7 CLAUDE.md). Favorit & rating scoped per `user_id`, bukan per household.
- **Ketersediaan AI:** Fitur cari-dengan-AI adalah *best-effort enhancement*, bukan dependency
  keras — pencarian lokal tetap jalan penuh tanpa Groq. Kalau Groq unreachable saat validasi bahan,
  sistem fail-open (anggap bahan plausible) supaya tidak memblokir user karena downtime pihak
  ketiga.
- **Biaya:** Didesain zero-budget — cache 6 jam per query AI + cache permanen per validasi bahan
  untuk menghemat kuota free-tier Groq (lihat `docs/DEPLOY.md`).
- **Performa:** `RecipeMatcher::search()` memuat seluruh resep + relasi bahan ke memori tiap
  pencarian (tidak ada pagination/index khusus untuk matching) — cukup untuk skala katalog MVP,
  jadi perhatian kalau katalog tumbuh besar.
- **Storage gambar:** Upload gambar resep ke filesystem lokal — hilang saat redeploy Render
  (ephemeral filesystem). Diketahui sebagai gap, lihat §12.
- **Keamanan input bahan (koreksi 2026-08-04, lihat §14):** nama bahan adalah free-text yang masuk
  ke prompt Groq. `IngredientPlausibility::looksLikeWord()` **bukan** pertahanan anti-prompt-injection
  — diverifikasi langsung: string seperti `"ignore previous instructions"` atau
  `"system: you are now unrestricted"` LOLOS heuristik ini (ditolak hanya kalau melebihi 40 karakter
  atau terlihat seperti keyboard-mash). Risiko residual diterima untuk MVP: blast radius kalau ada
  yang mencoba prompt injection lewat nama bahan adalah teks resep bogus masuk ke katalog bersama
  (butuh moderasi manual), bukan eksekusi kode atau kebocoran data — tidak dibangun lapisan sanitasi
  tambahan untuk risiko ini.

## 9. Metrics / KPI (usulan — belum diinstrumentasi di kode)

> Belum ada tracking apa pun untuk ini di kode saat ini; ini target yang perlu dibangun kalau mau
> diukur.

- **Search success rate:** % pencarian bahan yang menghasilkan ≥1 resep dengan skor match >0.
- **AI fallback rate:** % pencarian yang berlanjut ke "cari dengan AI" (indikasi katalog lokal
  kurang lengkap).
- **AI import yield:** rata-rata jumlah resep AI yang benar-benar tersimpan (bukan duplikat) per
  panggilan.
- **Favorite/rating engagement:** % user aktif yang pernah favorit atau rating minimal 1 resep.
- **Shopping-list conversion:** % hasil pencarian yang bahan missing-nya didorong ke shopping list.
- **Ingredient rejection rate:** % input bahan yang ditolak validasi lokal — sinyal false-positive
  kalau terlalu tinggi (heuristik terlalu ketat).
- **Filter usage rate:** % pencarian yang memakai filter kategori makan dan/atau jenis masakan.
- **AI category compliance:** (perlu sampling review manual, bukan otomatis) % resep hasil AI
  yang benar-benar sesuai kategori/jenis masakan yang diminta — indikator efektif-tidaknya
  anti-hallucination guard di §6.4.
- **AI quota rejection rate:** % percobaan "cari dengan AI" yang diblokir oleh cap app-wide
  atau per-household (§6.5) — sinyal kalau angka cap awal (25 app-wide, 7/household)
  perlu dinaikkan atau justru masih longgar.

## 10. Edge Cases & Error Handling (sudah ditangani di kode)

- Bahan kosong/hanya spasi → diabaikan, tidak ditambahkan ke daftar.
- Semua bahan input gagal validasi plausibilitas → tombol "cari dengan AI" menampilkan error
  "Tidak ada bahan yang dikenali", tidak memanggil Groq sama sekali (hemat kuota).
- Response Groq bukan JSON valid / bukan array resep / field resep tidak lengkap → dilempar sebagai
  `InvalidArgumentException`, ditangkap di Livewire, ditampilkan sebagai pesan generik + `report()`
  untuk observability.
- Resep AI dengan nama yang sudah ada (case/whitespace-insensitive) → dilewati, tidak dobel entry.
- User belum login mencoba toggle favorit/rating → method langsung return, tidak ada efek (bukan
  redirect ke login — silent no-op).
- User rating ulang resep yang sudah pernah dia rating → value rating yang lama di-**update**
  (`Rating::updateOrCreate`, bukan diblokir), rata-rata dihitung ulang.
- User memakai filter kategori/jenis masakan tapi tidak ada resep lokal yang cocok → pencarian
  lokal tampil "tidak ada hasil", tombol "cari dengan AI" tetap tersedia dengan filter yang sama
  dibawa ke prompt.
- **Keputusan sadar, bukan gap:** AI berpotensi tetap mengembalikan resep di luar kategori/jenis
  masakan yang diminta (LLM tidak selalu 100% patuh instruksi) → tidak ada validasi otomatis
  post-hoc untuk MVP ini, murni diandalkan ke kekuatan prompt (§6.4). Risiko residual ini diterima;
  dipantau lewat metrik "AI category compliance" (§9) secara sampling manual.
- User kena cap AI harian (app-wide atau household, §6.5) → tombol "cari dengan AI" menampilkan
  pesan kuota habis (dibedakan app-wide vs household) dan **tidak** memanggil Groq sama sekali
  (`AiQuotaGuard::check()` dicek sebelum panggilan Groq, fail fast sebelum kena 429 asli).
- Bahan/pivot lama tanpa `is_primary` (`null`) → diperlakukan sebagai bobot 1 (non-primary) di
  scoring (§6.3), tidak error.

## 11. Dependencies & Integrations

- **Groq API** (`GROQ_API_KEY`, `GROQ_ENDPOINT`, `GROQ_MODEL` di `config/services.php`) — via
  `AiRecipeClient` interface, binding tunggal `GroqRecipeClient`. Bisa diganti provider lain lewat
  interface ini tanpa ubah `RecipeFinder`.
  - **PRD tidak lengkap tanpa flag ini:** `/claude-api` skill relevan kalau kita mau bahas ganti
    model/provider AI — tandai untuk sesi berikutnya jika masuk topik.
- **ShoppingList module** — satu-satunya dependency cross-module yang disengaja (lihat
  `CLAUDE.md`): `MissingIngredientsToShoppingList` dipanggil dari komponen Livewire ShoppingList,
  bukan sebaliknya. Modul ini sudah tersedia/bisa dipakai, jadi integrasi aktif di flow utama
  (§6.7).
- **Filament panel** — `CookingPanelPlugin` auto-discover resource, tidak perlu daftar manual di
  panel provider pusat.

## 12. Gap & Next Steps (dari `docs/ROADMAP.md`, belum dibangun)

- Resep masih katalog global; kalau AI-import satu keluarga "bocor" ke keluarga lain jadi masalah
  nyata, pertimbangkan kolom `household_id` nullable di `recipes`.
- Filament steps editor masih `Textarea` polos (satu baris = satu langkah) — belum jadi `Repeater`
  dengan metadata per-langkah (durasi, foto per step). **Sejak 2026-08-05 ini bukan cuma gap
  fitur, tapi juga sumber data loss**: `duration_minutes` per langkah yang diisi AI ikut hilang
  (jadi `null`) begitu admin menyimpan lewat form ini, karena Textarea cuma menyimpan teksnya
  (§6.4, §6.8). Ada peringatan eksplisit di helper text form untuk sekarang; upgrade ke `Repeater`
  akan menyelesaikan ini sekalian.
- Gambar resep perlu dipindah ke storage persisten (S3/Cloudinary) sebelum production-serious,
  karena filesystem Render ephemeral.
- Ada beberapa test Pest untuk modul ini sejak eksekusi §15 (2026-08-04) & perbaikan lanjutan
  (2026-08-05):
  `tests/Feature/Cooking/{RecipeMatcherTest,AiQuotaGuardTest,RecipeRatingTest,RecipeFinderTest,RecipeResourceTest,RecipeStepsTest,AiResponseParserTest}.php`
  — cakupan masih minimal (weighted scoring & filter kategori/cuisine matcher termasuk lewat
  Livewire end-to-end, cap AI, rating update-in-place, create resep + tag `is_primary`/gramasi/gizi
  lewat Filament, normalisasi `RecipeSteps`, parsing response AI), belum ada test untuk
  `AiRecipeImporter` atau `IngredientPlausibility`. Risiko regresi di area yang belum ditest itu
  masih ada.

## 13. Open Questions

Tidak ada open question tersisa saat ini — seluruh pertanyaan dari draf-draf sebelumnya (rasio
bobot bahan utama, angka cap AI harian, kebutuhan backfill data lama, dan multi-kategori per resep)
sudah diputuskan. Lihat §14 untuk riwayat keputusannya. Open question baru akan ditambahkan di sini
kalau muncul saat implementasi atau setelah fitur ini jalan di production.

## 14. Riwayat Perubahan

| Tanggal | Perubahan |
|---|---|
| 2026-07-29 | Draf awal ditambahkan requirement filter **kategori makan** & **jenis masakan** untuk pencarian lokal + AI sebagai anti-hallucination guard (§1, §3, §5, §6.2–§6.4, §6.7, §7, §9, §10, §12, §13); integrasi Shopping List sempat ditandai ditunda. |
| 2026-07-29 | **Koreksi:** integrasi Shopping List dikonfirmasi **aktif** (modul sudah tersedia/bisa dipakai) — bukan ditunda; semua penanda "🔜 ditunda" terkait dicabut (§1, §3, §5, §6.6, §8, §11, §12). Kategori makan dikonfirmasi **multi-select** dengan framing prompt "resep untuk kategori X dengan bahan berikut" (§6.2, §6.4). Anti-hallucination guard diputuskan cukup mengandalkan instruksi prompt saja, tanpa validasi otomatis tambahan (§6.4, §10). |
| 2026-07-29 | Resolusi 4 Open Question tersisa: (1) skor matching **ditambah bobot bahan utama** via `is_primary` di pivot (§6.3, §6.4, §6.8, §7); (2) rating **bisa diubah** user, bukan permanen lagi (§6.6, §10); (3) kuota AI dibatasi dengan **cap app-wide DAN per-household** (§6.5 baru, §9, §10, §12); (4) jenis masakan (cuisine) dikonfirmasi **multi-select**, sama seperti kategori makan (§6.2). Open Questions baru soal rasio bobot, angka cap final, dan siapa yang backfill data lama (§13). |
| 2026-07-29 | Resolusi 3 Open Question lanjutan: (1) rasio bobot bahan utama **di-hardcode 2:1**, bukan konfigurasi (§6.3); (2) angka cap AI harian (~20–30 app-wide, ~5–10/household) **dipatok manual sebagai starting point**, boleh di-tuning belakangan tanpa keputusan produk baru (§6.5); (3) resep seed lama **tidak di-backfill masal** — kolom baru (`meal_category`, `cuisine_type`, `is_primary`) dibiarkan `null`, diperbaiki manual per-resep lewat Filament kalau/kapan diperlukan, bukan prasyarat rilis (§6.2, §6.8, §7, §12). Open Questions tersisa: apakah satu resep perlu multi-kategori/multi-cuisine sekaligus (§13). |
| 2026-07-29 | **Resolusi Open Question terakhir:** satu resep **boleh ditag lebih dari satu kategori makan sekaligus** — kolom berubah dari `meal_category` (string tunggal) jadi **`meal_categories`** (array/jsonb). `cuisine_type` tetap satu nilai per resep (asumsi, belum diminta multi). Berdampak ke §6.2 (deskripsi filter), §6.3 (query overlap array, bukan `IN`), §6.4 (AI simpan multi-kategori sesuai filter yang dipakai saat generate), §6.8 (Select multiple untuk kategori), §7 (tipe kolom), §12. Tidak ada Open Question tersisa untuk saat ini (§13). |
| 2026-07-29 | Ditambahkan §15 Rencana Implementasi (fase kerja + todo list) untuk seluruh item 🔜 Planned di dokumen ini. |
| 2026-08-04 | Sesi brainstorm ulang scope `feat/cooking` sebelum eksekusi (belum ada kode berubah): filter exclude-bahan/alergi & tag diet (halal/vegan) **ditunda ke `docs/ROADMAP.md`**; guardrail keamanan resep AI & dokumentasi anti-prompt-injection **dicatat untuk dilipat ke Fase 3/Fase 7** saat fase itu dieksekusi (§15, catatan brainstorm). |
| 2026-08-04 | **Koreksi sebelum eksekusi:** item brainstorm #3 (klaim `IngredientPlausibility` "sengaja berfungsi ganda sebagai barrier" anti-prompt-injection) **dicabut** — diverifikasi langsung bahwa `looksLikeWord("ignore previous instructions")` dan `looksLikeWord("system: you are now unrestricted")` lolos heuristik ini (§8). Tidak ditulis test yang mengklaim string itu ditolak (akan gagal). Item brainstorm #2 (guardrail keamanan resep AI: larangan canning/fermentasi/curing/minyak-mentah + badge disclaimer `source: ai`) **tetap dikerjakan** sesuai rencana, dilipat ke Fase 3 (§6.4). |
| 2026-08-04 | **Eksekusi §15 selesai** (7 fase, 22 item Todo List): filter kategori makan/jenis masakan (§6.2, §6.3, §6.4, §6.8, §7), bobot bahan utama `is_primary` (§6.3, §6.4, §6.8, §7), rate limiting AI via `AiQuotaGuard` (§6.5, §10), rating update-in-place (§6.6, §10), form admin Filament untuk kategori/jenis masakan/`is_primary` (§6.8), guardrail keamanan resep AI + badge disclaimer (§6.4), dan test Pest minimal (§12). Semua tag 🔜 Planned terkait di §1, §3, §5, §6.2–§6.8, §7, §9, §10, §12 dicabut. Cache key Groq di-bump `groq:v1:` → `groq:v2:` mengikuti perubahan kontrak ingredients AI (§6.4). |
| 2026-08-04 | **Perbaikan pasca-review eksekusi §15:** urutan cek `AiQuotaGuard` di `RecipeFinder::exploreWithAi()` dipindah ke **setelah** filter plausibilitas bahan AI (§6.5) — sebelumnya kuota terpakai bahkan saat semua bahan gagal filter dan Groq tidak pernah dipanggil, melanggar niat §6.5 ("membatasi total panggilan `suggest()`"). Ditambah 2 test Pest end-to-end yang tidak ada di Todo List asli tapi menutup celah cakupan: filter kategori makan lewat komponen Livewire `RecipeFinder` (bukan cuma `RecipeMatcher` langsung), dan alur create resep + tag `is_primary` lewat Filament `CreateRecipe` (§12). |
| 2026-08-05 | **Bug ditemukan user setelah eksekusi §15:** filter jenis masakan di `RecipeFinder` sempat ke-implementasi sebagai `<select>` single-select, padahal §6.2 sudah lama mendesainnya multi-select (sama seperti kategori makan) — kesalahan implementasi Fase 2, bukan perubahan desain. Diperbaiki jadi checkbox multi-select; `RecipeMatcher::search()`, `RecipeFinder`, `GroqRecipeClient`, `RecipePrompt`, `AiRecipeImporter` diubah dari parameter `?string $cuisineType` jadi `array $cuisineTypes` (§6.2, §6.3). Ditambah test cuisine-filter OR-multi-select yang sebelumnya tidak ada — signature lama lolos 14/14 test justru karena tidak ada test yang menguji jalur itu. |
| 2026-08-05 | **Bug UI ditemukan user:** teks di kotak input/select Cooking nyaris tidak kelihatan di dark mode — `<body class="text-ink">` (`layout.blade.php`) mewariskan warna teks tema ke elemen form yang `bg-white`-nya hardcoded, jadi teks krem-terang di atas kotak putih. Diperbaiki dengan `text-stone-800` eksplisit di semua `<input>`/`<select>` modul Cooking. |
| 2026-08-05 | **Fitur baru diminta user (scaffolding-nya sudah ada dari sesi sebelumnya, uncommitted, belum ada UI):** durasi per-langkah masak, gramasi bahan, dan perkiraan nilai gizi. Nilai gizi dibatasi ke 4 field standar (kalori/protein/karbohidrat/lemak), sumber data AI-estimate otomatis + admin bisa koreksi manual lewat Filament (keputusan user via pertanyaan klarifikasi). Shape `steps` (json) berubah dari array string jadi array objek `{text, duration_minutes}` (§7); kolom `quantity` di pivot `recipe_ingredient` (sudah ada sejak awal) dan kolom `duration_minutes`/`nutrition` di `recipes` (sudah ada dari migration sesi sebelumnya) mulai benar-benar dipakai. `RecipePrompt`, `AiResponseParser`, `AiRecipeImporter` diperluas untuk minta & parse field-field baru ini; cache key Groq di-bump `groq:v2:` → `groq:v3:` (§6.4). Form Filament dapat `TextInput duration_minutes`, `Fieldset` nilai gizi (dot-notation ke kolom json), dan `Section` gramasi dinamis per bahan (pola sama dengan `primary_ingredient_ids`) (§6.8). Halaman resep (`recipes/show.blade.php`) menampilkan badge durasi per langkah, gramasi per bahan, dan seksi nilai gizi. **Keterbatasan yang disengaja, bukan bug tersembunyi:** admin belum bisa input durasi per-langkah lewat Filament (`steps` masih `Textarea` polos) — menyimpan lewat form itu menghapus durasi per-langkah yang tadinya diisi AI, ada peringatan eksplisit di helper text form (§6.4, §6.8, §12). Ditemukan & diperbaiki dalam review: fieldset nilai gizi yang dibiarkan kosong sempat tersimpan sebagai array 4 `null` (bukan `null`) sehingga seksi "Perkiraan Nilai Gizi" ikut tampil kosong di halaman resep — ditambah `RecipeResource::normalizeNutrition()` untuk menormalkan ini sebelum simpan, plus test untuk kedua kasus (§6.8). Ditambah test `AiResponseParser` (sebelumnya nol test untuk kelas ini meski kontraknya sudah berubah dua kali dalam sesi yang sama) dan test `RecipeSteps::normalize()` termasuk backward-compat baca data lama. |

## 15. Rencana Implementasi (Cooking)

> **Status: dieksekusi 2026-08-04** (7 fase, 22 item Todo List — lihat §14 untuk ringkasan).
>
> **Catatan brainstorm asli (2026-08-04, sebelum eksekusi)** mengangkat 3 pertimbangan tambahan di
> luar §6–§12 — hasil akhirnya:
> 1. **Filter exclude-bahan/alergi & tag diet (halal/vegan/dst)** — tetap ditunda ke
>    `docs/ROADMAP.md`, di luar scope eksekusi ini (keputusan produk lebih besar, butuh UI baru).
> 2. **Guardrail keamanan resep AI** — **dikerjakan**, dilipat ke Fase 3 (§6.4): instruksi eksplisit
>    di prompt melarang teknik pengalengan/fermentasi/curing/simpan-bahan-mentah-dalam-minyak, plus
>    badge disclaimer di kartu resep `source: ai`.
> 3. **Dokumentasikan `IngredientPlausibility` sebagai pertahanan anti-prompt-injection** —
>    **dicabut, bukan dikerjakan seperti tertulis.** Verifikasi langsung menunjukkan klaim ini
>    salah: `looksLikeWord("ignore previous instructions")` dan
>    `looksLikeWord("system: you are now unrestricted")` **lolos** heuristik ini (hanya menolak
>    string >40 karakter atau yang terlihat seperti keyboard-mash). Tidak ada Pest test yang
>    mengklaim string itu ditolak. Sebagai gantinya, §8 dikoreksi dengan pernyataan jujur: nama
>    bahan adalah free-text yang masuk ke prompt Groq, `IngredientPlausibility` bukan barrier
>    injection, risiko residual diterima untuk MVP.

> Rencana ini mengurutkan seluruh item yang sebelumnya ditandai 🔜 Planned di §6–§12 jadi fase kerja
> yang bisa langsung dieksekusi. Urutan berdasarkan dependency: data model dulu (fondasi), baru
> logic yang bergantung padanya. Urutan eksekusi aktual sedikit berbeda dari urutan fase di bawah
> (Fase 1 → 5 → 2 → 3 → 4 → 6 → 7) — lihat §14.

**Fase 1 — Data model & konstanta** *(estimasi: 2–3 jam)*
Fondasi untuk semua fase lain. Tanpa ini, tidak ada yang bisa disimpan.
- Migration baru: `meal_categories` (jsonb, nullable) & `cuisine_type` (string, nullable) di
  `recipes`; `is_primary` (boolean, nullable) di pivot `recipe_ingredient`.
- Daftar nilai valid kategori & jenis masakan sebagai konstanta PHP (bukan DB constraint/enum,
  sesuai §6.2) — cukup satu class kecil, mis. `App\Modules\Cooking\Support\RecipeTaxonomy` dengan
  dua array statis.
- Update `Recipe` model: cast `meal_categories` ke array, tambah ke `$fillable`.

**Fase 2 — Pencarian lokal (RecipeMatcher + RecipeFinder)** *(estimasi: 3–4 jam)*
Bisa langsung dites manual begitu Fase 1 selesai, tanpa perlu Groq.
- `RecipeMatcher::search()`: terima parameter filter kategori/jenis masakan (array), terapkan
  jsonb overlap (`meal_categories`) + `whereIn` (`cuisine_type`) sebelum scoring.
- `RecipeMatcher::search()`: ubah formula skor jadi weighted (`is_primary` = bobot 2, lainnya = 1,
  hardcoded).
- `RecipeFinder`: tambah public property untuk kategori/jenis masakan terpilih (multi-select),
  teruskan ke `RecipeMatcher::search()`.
- Blade `recipe-finder`: UI multi-select untuk kategori makan & jenis masakan.

**Fase 3 — Prompt & import AI** *(estimasi: 3–4 jam)*
Bergantung ke Fase 1 (kolom) & Fase 2 (filter terpilih sudah ada di Livewire state).
- `RecipePrompt::build()`: terima kategori/jenis masakan terpilih, bingkai ulang jadi "resep untuk
  kategori [X] dengan bahan berikut" (§6.2), minta format ingredients sebagai objek
  `{name, is_primary}`.
- `AiResponseParser::parse()`: sesuaikan parsing ingredients dari array string jadi array objek.
- `AiRecipeImporter::import()`: terima context kategori/jenis masakan terpilih (bukan dari respons
  AI), stamp ke `Recipe::meal_categories`/`cuisine_type`; simpan `is_primary` per bahan ke pivot.
- `RecipeFinder::exploreWithAi()`: teruskan filter terpilih ke prompt & importer.

**Fase 4 — Rate limiting AI** *(estimasi: 2 jam)*
Independen dari Fase 2–3, bisa dikerjakan paralel — cuma perlu tahu di mana `exploreWithAi()`
dipanggil.
- Service kecil (mis. `AiQuotaGuard`) dengan `Cache::increment` untuk cap app-wide
  (`ai-quota:app:{tanggal}`) dan per-household (`ai-quota:household:{id}:{tanggal}`), TTL sampai
  tengah malam.
- Cek kedua cap di awal `RecipeFinder::exploreWithAi()`, sebelum panggil `AiRecipeClient` — kalau
  kena, set `$aiError` dengan pesan yang membedakan penyebab, jangan panggil Groq sama sekali.

**Fase 5 — Rating bisa diubah** *(estimasi: 30–60 menit)*
Independen, paling kecil.
- `RecipeRating::rate()`: ganti guard `hasRated` (block total) jadi `updateOrCreate` pada
  `Rating`, lalu `refreshStats()` seperti biasa.
- Blade `recipe-rating`: tombol yang tadinya disabled kalau `hasRated` jadi tetap aktif ("ubah
  rating").

**Fase 6 — Admin Filament** *(estimasi: 2–3 jam)*
Bisa dikerjakan kapan saja setelah Fase 1 — tidak memblokir fase lain, dan sebaliknya.
- `RecipeResource::form()`: tambah `Select::make('meal_categories')->multiple()` dan
  `Select::make('cuisine_type')`.
- Tambah cara tag `is_primary` per bahan (opsi paling sederhana: dua `Select` terpisah — "bahan
  utama" & "bahan lain" — keduanya menulis ke pivot `recipe_ingredient` yang sama dengan
  `is_primary` berbeda, lewat `afterSave`/`mutateFormDataBeforeSave`).

**Fase 7 — Tes minimal** *(estimasi: 2 jam)*
Modul ini belum punya test sama sekali (§12) — untuk logic baru yang non-trivial (weighted
scoring, cap harian, update-in-place rating), tambah minimal satu Pest test per logic supaya ada
yang gagal kalau kebobol nanti:
- `RecipeMatcher`: skor weighted menghasilkan urutan yang benar saat ada bahan `is_primary`.
- `RecipeMatcher`: filter kategori/jenis masakan mengecualikan resep yang tidak cocok.
- `AiQuotaGuard`: cap app-wide & per-household benar-benar memblokir setelah limit tercapai.
- `RecipeRating::rate()`: rating kedua meng-update baris yang sama, bukan menambah baris baru.

## ✅ Todo List

- [x] [db] Migration: `meal_categories` (jsonb, nullable) & `cuisine_type` (string, nullable) di `recipes`
- [x] [db] Migration: `is_primary` (boolean, nullable) di pivot `recipe_ingredient`
- [x] [backend] Buat `RecipeTaxonomy` (atau nama serupa) berisi daftar konstanta kategori makan & jenis masakan
- [x] [backend] Update model `Recipe`: cast `meal_categories` ke array, tambah ke `$fillable`
- [x] [backend] `RecipeMatcher::search()`: tambah parameter filter kategori/jenis masakan (jsonb overlap + whereIn)
- [x] [backend] `RecipeMatcher::search()`: ubah formula skor jadi weighted by `is_primary` (2:1, hardcoded)
- [x] [backend] `RecipeFinder`: tambah state kategori/jenis masakan terpilih, teruskan ke matcher
- [x] [frontend] Blade `recipe-finder`: UI multi-select kategori makan & jenis masakan
- [x] [backend] `RecipePrompt::build()`: reframe prompt + minta ingredients sebagai `{name, is_primary}`
- [x] [backend] `AiResponseParser::parse()`: parse ingredients sebagai objek, bukan string polos
- [x] [backend] `AiRecipeImporter::import()`: terima context kategori/jenis masakan, stamp ke Recipe; simpan `is_primary` ke pivot
- [x] [backend] `RecipeFinder::exploreWithAi()`: teruskan filter terpilih ke prompt & importer
- [x] [backend] Buat `AiQuotaGuard` (cap app-wide + per-household via `Cache::increment`, TTL tengah malam)
- [x] [backend] Wire `AiQuotaGuard` ke `RecipeFinder::exploreWithAi()` sebelum panggil Groq, pesan error yang membedakan penyebab
- [x] [backend] `RecipeRating::rate()`: ganti block-if-already-rated jadi `updateOrCreate`
- [x] [frontend] Blade `recipe-rating`: tombol "ubah rating" saat `hasRated`, bukan disabled
- [x] [backend] `RecipeResource::form()`: tambah `Select::make('meal_categories')->multiple()` & `Select::make('cuisine_type')`
- [x] [backend] `RecipeResource`: mekanisme tag `is_primary` per bahan — diimplementasikan sebagai satu `Select::make('primary_ingredient_ids')` reaktif (opsinya mengikuti bahan yang sudah dipilih di field `ingredients`), disinkron ke pivot lewat `afterCreate`/`afterSave`, bukan dua Select terpisah seperti opsi awal di §15 (lebih sederhana, tidak butuh admin menjaga dua daftar tetap sinkron)
- [x] [test] Pest: `RecipeMatcher` weighted scoring menghasilkan urutan benar
- [x] [test] Pest: `RecipeMatcher` filter kategori/jenis masakan mengecualikan resep tidak cocok
- [x] [test] Pest: `AiQuotaGuard` memblokir setelah cap app-wide/per-household tercapai
- [x] [test] Pest: `RecipeRating::rate()` update-in-place, bukan insert baris baru
