# PRD: Akun Premium & Free (Billing)

> **Status dokumen:** Greenfield — belum ada implementasi sama sekali di kode (tidak seperti
> `cooking.md` yang reverse-engineer dari kode existing). Ini hasil brainstorm strategi, bukan
> kontrak perilaku yang sudah jalan. `docs/ROADMAP.md` sebelumnya mencatat billing "di luar cakupan
> MVP zero-budget" — dokumen ini mulai membongkar cakupan itu.

## 1. Ringkasan

asisten-mama akan dijadikan produk SaaS berbayar untuk keluarga lain (bukan cuma dipakai sendiri),
dengan dua tier per **household** (bukan per user): **Free** dan **Premium**. Pembeda utamanya
adalah **kuota pemakaian AI** — fitur AI (saat ini cuma di Cooking, direncanakan meluas ke semua
modul) makan biaya API riil per panggilan begitu di-upgrade dari Groq free-tier ke API berbayar.
Harga langganan dipatok **murah** (target Rp10.000–20.000/bulan) untuk menggaet banyak user,
dengan filosofi: pemasukan langganan harus menutup biaya AI (cost-recovery), bukan strategi
harga-tinggi-margin-tinggi.

## 2. Latar Belakang & Masalah

**Rumusan masalah:** Pemilik produk ingin menjadikan asisten-mama SaaS berbayar untuk keluarga
selain miliknya sendiri. Setelah AI (Groq) di-upgrade dari free-tier ke API berbayar, setiap
panggilan AI di semua modul punya biaya riil yang harus ditutup dari pemasukan langganan — tapi
harga langganannya sendiri harus tetap murah supaya banyak orang mau coba/pakai.

**Fakta unit economics (dicek Juli 2026):** Model yang direkomendasikan untuk API berbayar adalah
**Qwen3 32B via Groq** ($0,29/juta token input, $0,59/juta token output) — **bukan**
`llama-3.3-70b-versatile` yang dipakai di free-tier saat ini. Alasan pemilihan (lihat §2.1):
harganya ~26-29% lebih murah dari Llama 3.3 70B, dan secara riset lebih cocok untuk dua karakter
kerja AI di aplikasi ini (pencarian resep di Cooking + rencana analisis kebiasaan lintas modul).
Panggilan `suggest()` (prompt pendek ~200-300 token, output sampai `max_tokens: 4000`) berbiaya
**~Rp16–40 per panggilan** dengan Qwen3 32B. Artinya biaya AI itu jauh lebih kecil dari intuisi
awal — pada volume wajar (puluhan hingga ratusan panggilan/bulan/household), total biaya AI tetap
di kisaran ribuan rupiah/bulan, bukan puluhan ribu.

### 2.1 Kenapa Qwen3 32B (bukan model lain)

Dibandingkan dengan kandidat lain yang lebih murah (Llama 3.1 8B Instant, GPT-OSS 20B, Llama 4
Scout, Mistral Small, Gemini Flash-Lite) maupun DeepSeek-chat native, riset menunjukkan:
- **Model kecil (7-20B) tidak reliable untuk output JSON terstruktur** yang kita butuhkan (resep +
  `is_primary` per bahan + kategori/cuisine) — riset benchmark structured-output 2026 menunjukkan
  model seukuran itu sering gagal menghasilkan JSON yang benar secara konsisten, terlepas dari
  harganya yang murah.
- **Qwen3 secara eksplisit disebut kuat untuk Bahasa Indonesia** di benchmark SEA-HELM (AI
  Singapore + Stanford CRFM) — penting karena seluruh output (nama resep, langkah, analisis) harus
  dalam Bahasa Indonesia yang wajar, bukan cuma benar secara struktur.
- **Qwen3 (keluarga besarnya) unggul di benchmark reasoning (GPQA Diamond 77,2%)** — relevan untuk
  rencana fitur **analisis kebiasaan lintas modul** (pola belanja/pengeluaran/masakan), yang butuh
  reasoning lebih dari sekadar generate resep.
- Dengan Qwen3 32B, **satu model bisa dipakai untuk kedua use case** (Cooking + analisis kebiasaan)
  — tidak perlu maintain dua binding model berbeda untuk kebutuhan yang berbeda karakter.
- **Belum divalidasi dengan prompt asli** — angka & rekomendasi ini dari benchmark generik, bukan
  tes langsung ke `RecipePrompt` kita. Perlu dicoba langsung sebelum production (lihat §13).

## 3. Goals & Non-Goals

**Goals:**
- Household bisa upgrade ke Premium untuk dapat kuota AI yang jauh lebih besar dari Free, di semua
  modul yang punya fitur AI (saat ini Cooking; ke depannya ShoppingList & Finance juga).
- Free tier tetap dapat **kuota AI kecil** (bukan nol) — cukup untuk merasakan value AI sebelum
  upgrade, tapi jelas ada batas natural yang mendorong upgrade.
- Harga Premium murah & simpel: **satu tier, satu harga, per household**, bukan multi-tier
  good-better-best yang rumit dijelaskan.
- Pemasukan langganan menutup biaya AI riil dengan margin, bukan sekadar impas.
- Billing terintegrasi ke payment gateway lokal (Indonesia) yang mendukung recurring/subscription.

**Non-Goals (sengaja tidak dibangun dulu):**
- Hard paywall / trial-wajib-bayar — risiko churn terlalu tinggi untuk produk yang belum
  tervalidasi (lihat riset di sesi brainstorm; ditolak, bukan cuma belum diprioritaskan).
- Module-locking (mengunci seluruh modul Finance/ShoppingList di balik Premium) — AI usage tetap
  jadi satu-satunya pembeda untuk MVP; modul itu sendiri tetap bisa diakses semua tier.
- Multi-tier pricing (Free/Plus/Pro/dst) — cuma dua tier (Free, Premium) untuk MVP.
- Billing per-user dalam satu household — semua anggota household ikut tier household-nya.
- Metered overage / beli-kuota-tambahan mid-cycle — kalau kuota habis, ya habis sampai
  periode berikutnya (atau upgrade tier); tidak ada micro-topup untuk MVP (kompleksitas payment
  tidak sepadan di tahap ini).

## 4. Model Bisnis & Tier

| | **Free** | **Premium** |
|---|---|---|
| Harga | Rp 0 | **Rp 15.000/bulan** (starting point, lihat §14) |
| Unit billing | — | Per household |
| Kuota AI/bulan | **10x** (starting point) | **100x** (starting point) |
| Akses modul (Cooking/ShoppingList/Finance) | Penuh | Penuh (sama — bukan pembeda) |
| Jumlah anggota household | Tidak dibatasi di MVP ini (beda topik dari kuota AI) | Sama |

Angka-angka di atas **sengaja dipatok sebagai starting point**, bukan hasil kalkulasi final —
belum ada data pemakaian nyata untuk memvalidasinya. Rencananya dievaluasi ulang secara berkala
begitu ada data riil (lihat metrik di §9), bukan sekali putus lalu dikunci selamanya.

Kuota AI dihitung **satu pool gabungan per household per bulan**, lintas semua modul yang punya
fitur AI — bukan kuota terpisah per modul. Alasan: AI direncanakan meluas ke ShoppingList & Finance
ke depannya, dan pool tunggal lebih simpel untuk dijelaskan ke user ("kuota AI kamu") dan lebih
gampang di-maintain daripada kuota per-modul yang harus disinkronkan satu-satu.

## 5. Dampak ke Desain yang Sudah Ada

**Mengubah §6.5 di `docs/prd/cooking.md`.** Cap "app-wide + per-household harian via
`Cache::increment`" yang didesain di sana **khusus melindungi kuota gratis Groq yang shared** (satu
API key, rate limit kecil dari free tier). Begitu upgrade ke API berbayar, constraint itu hilang —
constraint baru adalah "apakah pemasukan household ini menutup biaya AI-nya sendiri", bukan
melindungi pool bersama seluruh aplikasi. Implikasinya:
- Cap **app-wide** (pool bersama seluruh app) jadi tidak relevan lagi setelah API berbayar aktif —
  dihapus atau dijadikan safety-valve darurat saja (mis. kalau tagihan API tiba-tiba melonjak),
  bukan mekanisme utama.
- Cap **per-household** berevolusi dari "harian" jadi **"bulanan, sesuai tier"** (Free vs Premium),
  dan jadi **shared pool lintas modul**, bukan spesifik Cooking saja.
- Mekanisme cache (`Cache::increment`) yang tadinya cukup untuk rate-limiting sederhana **tidak
  cukup untuk kuota yang terikat uang** — butuh counter yang **persisten di DB** (lihat §7), supaya
  tidak hilang kalau cache di-clear/di-restart, dan bisa diaudit kalau ada dispute pelanggan.

Kesimpulan: `AiQuotaGuard` yang direncanakan di Cooking §6.5 **diganti** oleh service billing-aware
yang dijelaskan di §6 dokumen ini, bukan dua mekanisme paralel yang jalan bareng.

## 6. Functional Requirements

### 6.1 Household subscription state
- Setiap `Household` punya `subscription_tier` (`free` default | `premium`) dan
  `subscription_expires_at` (nullable — null berarti tier `free` permanen, terisi untuk `premium`
  yang aktif sampai tanggal tsb).
- Household baru selalu mulai di tier `free` (konsisten dengan `Household::createWithOwner()` yang
  sudah ada).

### 6.2 Kuota AI gabungan (per household, per siklus, per tier)
- Satu counter per household per periode (bukan per hari, bukan per modul) — lihat §7 untuk tabel.
- Setiap panggilan AI (di modul manapun) increment counter yang sama, dicek dulu **sebelum**
  memanggil API AI (fail fast, konsisten dengan pola yang sudah dipakai di Cooking) — kalau counter
  sudah kena limit tier household saat ini, tampilkan pesan upsell ("kuota AI bulan ini habis,
  upgrade ke Premium untuk kuota lebih besar") dan **tidak** memanggil API.
- Limit ditentukan dari `subscription_tier` household saat itu: `free` → **10x/bulan**, `premium` →
  **100x/bulan** (starting point, lihat §4 & §14).
- **Definisi "periode" beda antara Free dan Premium:**
  - **Free** tidak punya siklus tagihan (tidak pernah bayar), jadi cukup pakai kalender bulan
    biasa (reset tanggal 1).
  - **Premium** periode-nya **mengikuti siklus tagihan household itu sendiri** (anchored ke
    tanggal bayar terakhir), **bukan** tanggal 1 kalender — konsisten dengan mekanisme perpanjangan
    di §6.3. Kalau household bayar tanggal 15, kuotanya reset tiap tanggal 15, bukan tanggal 1.

### 6.3 Upgrade / downgrade tier
- Household upgrade ke Premium via payment gateway (§6.4) → `subscription_tier` jadi `premium`,
  `subscription_expires_at` di-set sesuai periode yang dibayar.
- **Grace period 2 hari** setelah `subscription_expires_at` lewat sebelum household benar-benar
  diturunkan ke `free` — kasih waktu toleransi kalau pembayaran perpanjangan telat diproses.
- Kalau household membayar ulang (baik masih dalam grace period maupun sudah lewat dan sempat turun
  ke `free`) → **siklus bulanan baru dimulai dari tanggal pembayaran itu terjadi**, bukan
  di-backdate ke tanggal seharusnya dia bayar. Kuota AI (§6.2) ikut reset mengikuti tanggal siklus
  baru ini.
- Kalau lewat grace period tanpa perpanjangan sama sekali → household otomatis turun ke tier `free`
  (kuota AI ikut turun ke kuota Free, efektif sejak household benar-benar berstatus `free`).
- Downgrade tidak menghapus data apa pun (resep favorit, riwayat Finance, dst) — cuma kuota AI yang
  berubah.

### 6.4 Integrasi Payment Gateway
- Pakai **Xendit** untuk recurring/subscription billing (bukan Midtrans) — Xendit py fitur
  recurring payment bawaan + SDK Laravel resmi, lebih cocok untuk model langganan dibanding Midtrans
  yang lebih ke arah checkout sekali-bayar.
- Webhook Xendit untuk event pembayaran berhasil/gagal/berulang, diverifikasi signature-nya sebelum
  diproses (keamanan — jangan percaya payload webhook tanpa verifikasi).
- Alur: household pilih upgrade → redirect ke halaman pembayaran Xendit → webhook konfirmasi →
  update `subscription_tier`/`subscription_expires_at`.

### 6.5 Dashboard pemantauan pemakaian AI (admin) — **digantikan oleh `docs/prd/admin-cms.md`**

> **Catatan supersession:** bagian ini awalnya membayangkan halaman Filament berdiri sendiri untuk
> dashboard AI usage. Setelah brainstorm terpisah soal CMS super admin, ternyata aplikasi ini
> belum punya pemisahan role sama sekali (panel `/admin` dipakai ganda untuk registrasi household
> & CRUD admin) — jadi dashboard ini **dipindah jadi bagian dari `admin-cms.md`** (panel
> `/super-admin` terpisah), bukan halaman sendiri di panel `/admin` yang juga dipakai household.
> Kebutuhan fungsionalnya (di bawah) tetap valid, cuma "rumah"-nya berubah.

- Kuota AI **tetap dibatasi ketat** untuk semua household (Free maupun Premium) — tidak ada
  pengecualian otomatis untuk kasus ekstrem/abuse (lihat §14, ex-Open Question #2).
- Kalau kasus ekstrem (household terus-menerus menghabiskan kuota besar mereka) terjadi berulang,
  admin butuh **dashboard pemantauan** untuk evaluasi manual — bukan mekanisme otomatis (soft
  throttle/kontak otomatis) yang menambah kompleksitas di MVP ini.
- Dashboard menampilkan pemakaian AI **per household** dan **per user di dalam household itu**
  (bukan cuma total household) — supaya admin bisa lihat pola siapa yang paling banyak pakai kalau
  perlu ditelusuri.
- Diimplementasikan di panel `/super-admin` (`admin-cms.md` §6.6), **bukan** halaman Filament di
  panel `/admin` seperti rencana awal.

## 7. Data Model

| Tabel | Kolom kunci | Catatan |
|---|---|---|
| `households` (existing, ditambah kolom) | + `subscription_tier` (string, default `free`), + `subscription_expires_at` (nullable timestamp) | Bukan tabel baru, cuma tambahan kolom |
| `ai_usage_counters` (baru) | `household_id`, `period` (anchor sesuai §6.2 — kalender bulan untuk Free, siklus tagihan untuk Premium), `count` (integer, default 0) | Unique per (`household_id`, `period`). **DB-backed, bukan Cache** — supaya tidak hilang & bisa diaudit untuk dispute pelanggan. Dipakai untuk **penegakan kuota** (fail-fast check), bukan untuk dashboard. |
| `ai_usage_events` (baru) | `household_id`, `user_id`, `module` (mis. `cooking`), `created_at` | Log satu baris per panggilan AI — sumber data untuk **dashboard pemantauan per user per household** (§6.5). Insert-only, tidak perlu logic atomik khusus (bukan untuk penegakan kuota). |
| `subscription_payments` (baru, opsional tapi disarankan) | `household_id`, `xendit_reference_id`, `amount`, `status`, `paid_at` | Riwayat transaksi untuk audit/reconciliation, bukan cuma state terakhir |

> Increment `ai_usage_counters.count` harus **atomik** (mis. `UPDATE ... SET count = count + 1`
> langsung di DB, bukan read-modify-write di aplikasi) — beberapa modul AI bisa dipanggil hampir
> bersamaan, race condition di sini langsung berarti household bisa melebihi kuota tanpa kena cap.
> `ai_usage_events` ditulis **bersamaan** dengan increment counter (bukan pengganti) — satu untuk
> penegakan cepat, satu untuk drill-down per user yang tidak butuh agregat real-time.

## 8. Non-Functional Requirements

- **Biaya:** Tetap zero-budget-friendly di infra dasar — Xendit tidak ada biaya bulanan tetap
  (fee per transaksi saja), tidak perlu platform billing pihak ketiga (Stripe Billing/Chargebee)
  yang biasanya berbayar bulanan sendiri.
- **Keamanan:** Webhook payment **wajib** diverifikasi signature-nya — endpoint yang menerima
  "pembayaran berhasil" tanpa verifikasi adalah lubang keamanan langsung (orang bisa fake-call
  webhook untuk dapat Premium gratis).
- **Auditability:** Counter kuota & riwayat pembayaran disimpan di DB (bukan cuma cache) supaya
  ada jejak kalau ada komplain "kuota saya kepotong padahal belum kepake".
- **Konsistensi dengan household tenancy:** Tier & kuota melekat ke `Household`, bukan ke `User` —
  konsisten dengan keputusan billing per-household dan dengan model tenancy yang sudah ada
  (`BelongsToHousehold`, lihat `CLAUDE.md`).

## 9. Metrics / KPI

- **Conversion rate:** % household Free yang upgrade ke Premium (dan dalam berapa lama sejak
  registrasi/sejak pertama kali kena limit kuota).
- **Quota exhaustion rate (Free):** % household Free yang menghabiskan kuota bulanan mereka —
  sinyal langsung apakah kuota Free terlalu longgar (tidak ada dorongan upgrade) atau terlalu ketat
  (churn sebelum kenal value).
- **AI cost vs revenue margin riil:** biaya API AI aktual per household Premium per bulan
  dibandingkan Rp yang dibayar — validasi apakah asumsi unit economics di §2 benar-benar akurat
  begitu ada data pemakaian nyata (bukan cuma estimasi).
- **Churn/downgrade rate:** % household Premium yang tidak memperpanjang setelah periode berakhir.

## 10. Edge Cases & Error Handling

- Household exceeds kuota di tengah bulan → AI diblokir (fail fast, tidak panggil API), pesan
  upsell ditampilkan, fitur non-AI (pencarian lokal, dst) tetap jalan normal.
- Pembayaran Xendit gagal/expired → household tidak naik tier, tetap `free`. Tidak ada retry
  otomatis untuk MVP — user perlu coba bayar ulang manual.
- Household Premium tidak perpanjang → turun ke `free` otomatis begitu `subscription_expires_at`
  lewat, kuota ikut turun **mulai periode berikutnya** (bukan langsung dipotong di periode yang
  sudah dibayar penuh).
- Webhook Xendit diterima duluan sebelum redirect user selesai (race antara webhook & UI) →
  webhook adalah **sumber kebenaran** untuk status pembayaran, bukan state di sisi client.
- Dua modul AI dipanggil nyaris bersamaan oleh household yang sama → counter increment atomik di
  DB (§7) mencegah keduanya lolos padahal gabungannya sudah melebihi kuota.

## 11. Dependencies & Integrations

- **Xendit** — payment gateway untuk recurring billing (§6.4). Perlu akun bisnis Xendit + API key,
  di luar cakupan zero-budget murni (ada fee per transaksi, tapi tidak ada biaya bulanan tetap).
- **Groq, model `qwen3-32b`** (ganti dari `llama-3.3-70b-versatile` yang dipakai di free-tier saat
  ini) — begitu upgrade dari free-tier ke berbayar, biaya per panggilan model ini jadi biaya
  variabel riil yang langsung terhubung ke §6.2. Perubahan model cukup lewat `GROQ_MODEL` di
  `config/services.php`/env, tidak perlu ubah `AiRecipeClient` interface. Kalau provider/model AI
  berubah lagi nanti (lihat `/claude-api` skill kalau diskusi provider Anthropic secara spesifik
  masuk topik), unit economics di §2 perlu dihitung ulang.
- **Cooking module (`docs/prd/cooking.md` §6.5)** — supersession, lihat §5.

## 12. Gap & Next Steps

- Modul ShoppingList & Finance belum punya fitur AI sama sekali — dokumen ini menyiapkan
  infrastruktur kuota gabungan duluan, tapi fitur AI di modul-modul itu sendiri belum di-PRD-kan.
- Belum ada UI/halaman untuk household melihat sisa kuota AI bulan ini atau riwayat
  tagihan/pembayaran — perlu di-desain terpisah (Livewire component sederhana kemungkinan cukup).
- Belum ada kebijakan refund/pro-rata kalau household downgrade di tengah periode yang sudah
  dibayar.
- **Sequencing upgrade ke API berbayar:** disepakati **ditunda** sampai beberapa modul lain (AI di
  ShoppingList/Finance) sudah jadi — bukan langsung begitu sistem kuota di dokumen ini selesai
  dibangun. Infrastruktur kuota & billing tetap dibangun sekarang (bisa "dilatih" di atas Groq
  free-tier dulu sebagai rehearsal), tapi tombol upgrade Groq ke tier berbayar ditunggu sampai lebih
  banyak fitur AI lintas modul siap — supaya biaya berbayar sepadan dengan value yang sudah ada.
- **Uji coba Qwen3 32B** (§2.1) dengan prompt asli (`RecipePrompt` + contoh prompt analisis
  kebiasaan) sudah disepakati untuk dilakukan — belum dieksekusi. Fallback kalau hasilnya kurang
  bagus: tetap di Llama 3.3 70B Versatile (lebih mahal tapi sudah terbukti jalan di free-tier).

## 13. Open Questions

Tidak ada open question tersisa saat ini — kelima pertanyaan dari draf sebelumnya (angka
kuota/harga starting point, kebijakan household boros, sequencing upgrade API, grace period, uji
coba model) sudah diputuskan/diagendakan. Lihat §14 untuk riwayat keputusannya.

## 14. Riwayat Perubahan

| Tanggal | Perubahan |
|---|---|
| 2026-07-29 | Draf awal dari sesi brainstorm: strategi Premium/Free per household, AI usage sebagai pembeda utama lintas semua modul, harga murah untuk volume, cost-recovery dari biaya AI. Payment gateway: Xendit. Ditandai dampak supersession ke `cooking.md` §6.5. |
| 2026-07-29 | Riset perbandingan harga API AI (Groq vs DeepSeek vs alternatif lain) dan kecocokan model untuk dua karakter kerja AI (pencarian resep di Cooking + rencana analisis kebiasaan lintas modul). **Model direkomendasikan untuk MVP: `qwen3-32b` di Groq** (ganti dari asumsi `llama-3.3-70b-versatile`) — lebih murah (~26-29%), disebut eksplisit kuat untuk Bahasa Indonesia (SEA-HELM), dan reasoning-nya cocok untuk analisis kebiasaan. Berdampak ke §2 (unit economics), §2.1 (baru), §11. Belum divalidasi dengan prompt asli — dicatat sebagai Open Question #5 (§13). |
| 2026-07-29 | Resolusi 5 Open Question: (1) angka kuota/harga **dikunci sebagai starting point** — Free 10x/bulan, Premium 100x/bulan, harga Rp15.000/bulan, dievaluasi ulang berkala (§4); (2) kuota **tetap dibatasi ketat** untuk semua household termasuk kasus ekstrem, sebagai gantinya dibangun **dashboard pemantauan admin** per household & per user (§6.5 baru, §7 tabel `ai_usage_events` baru); (3) upgrade ke API berbayar **ditunda** sampai beberapa modul lain (ShoppingList/Finance) sudah py fitur AI (§12); (4) **grace period 2 hari**, dan siklus bulanan (baik untuk kuota maupun tagihan) **restart dari tanggal pembayaran ulang terjadi**, bukan di-backdate — mengubah §6.2 (kuota Premium anchored ke siklus tagihan, bukan tanggal 1 kalender) dan §6.3; (5) uji coba Qwen3 32B **disepakati untuk dilakukan**, dipindah jadi action item di §12 (bukan open question lagi karena arahnya sudah jelas, tinggal eksekusi). |
| 2026-07-29 | Ditambahkan §15 Rencana Implementasi (fase kerja + todo list). |
| 2026-07-29 | §6.5 (dashboard AI usage) **digantikan** oleh `docs/prd/admin-cms.md` — dipicu temuan bahwa aplikasi belum punya pemisahan role sama sekali, jadi dashboard ini pindah ke panel Filament `/super-admin` yang terpisah, bukan halaman di panel `/admin` yang juga dipakai household. Fase 5 di §15 (masih menyebut halaman Filament sederhana) perlu disesuaikan saat eksekusi — dianggap tergantikan oleh `admin-cms.md`, bukan dikerjakan dua kali. |

## 15. Rencana Implementasi

> Urutan berdasarkan dependency: data model dulu, baru service yang bergantung padanya, baru
> integrasi eksternal (Xendit). **Upgrade Groq ke tier berbayar sengaja TIDAK termasuk** di fase
> manapun di bawah — itu ditunda sesuai §12, jadi seluruh infrastruktur ini dibangun & bisa dites
> penuh di atas Groq **free-tier** dulu (config model tetap `GROQ_MODEL` yang sudah ada, tidak ada
> biaya riil sampai keputusan cutover diambil terpisah).

**Fase 1 — Data model & household subscription state** *(estimasi: 2–3 jam)*
Fondasi untuk semua fase lain.
- Migration: `households` +`subscription_tier` (string, default `free`), +`subscription_expires_at`
  (nullable timestamp).
- Migration: `ai_usage_counters` (`household_id`, `period`, `count`, unique per household+period).
- Migration: `ai_usage_events` (`household_id`, `user_id`, `module`, `created_at`).
- Migration: `subscription_payments` (`household_id`, `xendit_reference_id`, `amount`, `status`,
  `paid_at`).
- Update model `Household`: cast/fillable kolom baru, helper method (mis. `isPremium()`,
  `currentAiUsagePeriod()`).

**Fase 2 — Shared AI quota service** *(estimasi: 3–4 jam)*
Menggantikan `AiQuotaGuard` yang direncanakan di `cooking.md` §6.5 (§5 dokumen ini) — bukan
paralel. Bisa langsung dites manual begitu Fase 1 selesai.
- Service baru (mis. `AiUsageQuota`) dengan method `remaining(Household $household): int` dan
  `record(Household $household, User $user, string $module): void`.
- Logic periode: kalender bulan untuk `free`, anchored ke `subscription_expires_at`/siklus tagihan
  untuk `premium` (§6.2).
- Increment `ai_usage_counters.count` atomik (raw `UPDATE ... SET count = count + 1`) **dan**
  insert baris ke `ai_usage_events` di operasi yang sama (§7).
- Wire ke `RecipeFinder::exploreWithAi()` — cek quota **sebelum** panggil `AiRecipeClient`, pesan
  upsell kalau habis, tidak memanggil API sama sekali kalau kena limit.

**Fase 3 — Integrasi Xendit** *(estimasi: 4–6 jam)*
Independen dari Fase 2 — bisa paralel, cuma perlu tahu di mana `subscription_tier` di-update.
- Setup akun bisnis Xendit + API key (bukan tugas kode, tapi prasyarat sebelum lanjut).
- Alur upgrade: tombol "upgrade ke Premium" → buat invoice/subscription Xendit → redirect user ke
  halaman pembayaran.
- Endpoint webhook Xendit + **verifikasi signature** (§8) sebelum memproses payload apa pun.
- Handler webhook: pembayaran sukses → update `subscription_tier`/`subscription_expires_at`,
  insert baris `subscription_payments`; pembayaran gagal → tidak ada perubahan tier.

**Fase 4 — Grace period & siklus perpanjangan** *(estimasi: 2 jam)*
Bergantung ke Fase 1 & 3 (butuh kolom & event webhook sudah ada).
- Scheduled job (Laravel scheduler, jalan harian) yang cek household dengan
  `subscription_expires_at` + grace period (2 hari) sudah lewat tanpa perpanjangan → turunkan ke
  `subscription_tier = free` (§6.3).
- Logic pembayaran ulang (baik dalam grace period maupun setelah sempat turun ke `free`): siklus
  baru **anchored ke tanggal pembayaran aktual**, bukan di-backdate ke jadwal semula.

**Fase 5 — Dashboard pemantauan (admin)** *(estimasi: 2 jam)*
Independen, bisa dikerjakan kapan saja setelah Fase 2 (butuh `ai_usage_events` mulai terisi).
- Halaman Filament sederhana: tabel `ai_usage_events` diagregasi per household & per user,
  sortable by pemakaian tertinggi (§6.5) — bukan dashboard analitik terpisah.

**Fase 6 — Uji coba model Qwen3 32B** *(estimasi: 1–2 jam)*
Independen dari fase lain — murni evaluasi kualitas, tidak mengubah kode produksi dulu.
- Jalankan `RecipePrompt` asli + contoh prompt analisis kebiasaan (manual) ke Qwen3 32B via Groq,
  bandingkan kualitas/keandalan JSON dengan Llama 3.3 70B yang dipakai sekarang.
- Hasilnya menentukan `GROQ_MODEL` mana yang dipakai **begitu** cutover ke tier berbayar terjadi
  (Fase 7) — tidak mengubah config sekarang karena masih di free-tier.

**Fase 7 — Cutover ke Groq berbayar** *(belum dijadwalkan — gated, lihat §12)*
**Sengaja tidak dieksekusi sekarang.** Trigger-nya: fitur AI sudah ada di ShoppingList dan/atau
Finance (bukan cuma Cooking). Begitu triggernya terpenuhi, tugasnya cuma: aktifkan billing Groq
berbayar, set `GROQ_MODEL` ke hasil Fase 6, dan mulai pantau metrik "AI cost vs revenue margin
riil" (§9) dengan data biaya sungguhan.

**Fase 8 — Tes minimal** *(estimasi: 2–3 jam)*
Sama seperti `cooking.md` — logic baru yang non-trivial (quota, race condition, webhook, grace
period) butuh minimal satu test yang gagal kalau kebobol nanti.
- Pest: quota `free` vs `premium` menegakkan limit yang benar sesuai tier.
- Pest: dua panggilan AI nyaris bersamaan tidak membuat household melebihi kuota (race condition
  di increment atomik).
- Pest: webhook Xendit ditolak kalau signature tidak valid.
- Pest: scheduled job menurunkan household ke `free` tepat setelah grace period lewat, tidak lebih
  cepat/lambat.
- Pest: pembayaran ulang me-restart siklus dari tanggal bayar aktual, bukan tanggal jadwal semula.

## ✅ Todo List

- [ ] [db] Migration: `households` +`subscription_tier` +`subscription_expires_at`
- [ ] [db] Migration: `ai_usage_counters` (household_id, period, count, unique)
- [ ] [db] Migration: `ai_usage_events` (household_id, user_id, module, created_at)
- [ ] [db] Migration: `subscription_payments` (household_id, xendit_reference_id, amount, status, paid_at)
- [ ] [backend] Update model `Household`: cast/fillable kolom baru + helper `isPremium()`/`currentAiUsagePeriod()`
- [ ] [backend] Buat service `AiUsageQuota` (cek + increment atomik + insert event, logic periode free vs premium)
- [ ] [backend] Wire `AiUsageQuota` ke `RecipeFinder::exploreWithAi()`, ganti mekanisme `AiQuotaGuard` lama dari `cooking.md` §6.5
- [ ] [infra] Setup akun bisnis Xendit + API key
- [ ] [backend] Alur upgrade: buat invoice/subscription Xendit, redirect ke halaman pembayaran
- [ ] [backend] Endpoint webhook Xendit + verifikasi signature
- [ ] [backend] Handler webhook: update `subscription_tier`/`subscription_expires_at` + insert `subscription_payments`
- [ ] [backend] Scheduled job harian: downgrade household ke `free` setelah grace period (2 hari) lewat
- [ ] [backend] Logic restart siklus dari tanggal pembayaran aktual saat perpanjangan (bukan backdate)
- [ ] [backend] Halaman Filament: dashboard pemakaian AI per household & per user (dari `ai_usage_events`)
- [ ] [ops] Jalankan uji coba manual Qwen3 32B vs Llama 3.3 70B pakai `RecipePrompt` + contoh prompt analisis kebiasaan
- [ ] [test] Pest: quota free vs premium menegakkan limit sesuai tier
- [ ] [test] Pest: race condition dua panggilan AI bersamaan tidak melebihi kuota
- [ ] [test] Pest: webhook Xendit ditolak kalau signature invalid
- [ ] [test] Pest: scheduled job downgrade tepat waktu sesuai grace period
- [ ] [test] Pest: pembayaran ulang me-restart siklus dari tanggal bayar aktual
