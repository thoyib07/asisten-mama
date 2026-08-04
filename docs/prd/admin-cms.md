# PRD: CMS Super Admin

> **Status dokumen:** Greenfield — belum ada implementasi sama sekali (sama seperti `billing.md`).
> **Mengubah/menggantikan `docs/prd/billing.md` §6.5** (dashboard pemantauan AI usage) — fitur itu
> jadi salah satu bagian di dalam CMS ini, bukan halaman berdiri sendiri (lihat §5).

## 1. Ringkasan

Panel Filament terpisah khusus untuk **super admin** (pemilik produk) — bukan household biasa —
untuk mengelola dan memantau data lintas semua modul (Cooking, ShoppingList, Finance, Billing) dan
lintas semua household. Dipicu oleh temuan penting saat brainstorming: aplikasi ini **belum punya
pemisahan role sama sekali**. Panel Filament yang ada sekarang (`/admin`) dipakai ganda — untuk
registrasi household biasa (`Register::class`) **dan** untuk CRUD resource admin
(`RecipeResource`) — artinya secara teori household mana pun yang login bisa mengakses
`RecipeResource` lewat URL yang sama, karena `authMiddleware` cuma cek login, bukan role.

## 2. Latar Belakang & Masalah

**Rumusan masalah:** Pemilik produk butuh satu tempat untuk mengelola & memantau seluruh data
lintas household (kesehatan bisnis, subscription, pemakaian AI, isi data tiap modul) tanpa
tercampur dengan panel yang dipakai household biasa untuk registrasi — dan tanpa risiko kebocoran
akses (household biasa tidak sengaja bisa akses resource admin).

**Temuan teknis (dicek Juli 2026):** `AdminPanelProvider` (`app/Providers/Filament/`) cuma
mendefinisikan **satu panel** (`id: admin`, `path: admin`), dipakai untuk registrasi household
(`->registration(Register::class)`) **dan** `CookingPanelPlugin` yang mendaftarkan
`RecipeResource`. Tidak ada model/guard/panel terpisah untuk admin — dan tidak ada sistem
role/permission apa pun di codebase saat ini.

## 3. Goals & Non-Goals

**Goals:**
- Panel Filament **baru dan terpisah total** (`/super-admin`, guard & tabel user sendiri) — tidak
  ada household user yang bisa mengakses panel ini, dan sebaliknya.
- Super admin bisa **CRUD penuh** (bukan cuma lihat) data lintas household untuk semua modul:
  Household/User, Cooking (Recipe), ShoppingList, Finance, Billing/subscription.
- **`RecipeResource` dipindah** dari panel `/admin` (household) ke panel `/super-admin` — menutup
  celah akses yang ditemukan di §2.
- Dashboard pemakaian AI (`billing.md` §6.5) jadi bagian dari panel ini, bukan halaman terpisah.
- Akun super admin **cuma satu** (pemilik produk sendiri) — tidak perlu sistem role/permission
  granular untuk MVP ini.

**Non-Goals (sengaja tidak dibangun dulu):**
- Sistem role/permission multi-staff (mis. Spatie Permission) — cuma satu akun admin, jadi tidak
  perlu granularitas permission per fitur. Kalau nanti ada staff tambahan, ini jadi prasyarat
  duluan sebelum staff kedua dibuat (lihat §13).
- Self-registration untuk akun super admin — akun dibuat manual (seeder/Artisan command), **tidak**
  ada halaman registrasi publik untuk panel ini (beda dari panel household yang py registrasi).
- Audit log granular (siapa mengubah apa, kapan) — dicatat sebagai gap (§12), bukan dibangun di
  MVP ini karena cuma satu admin (risiko akuntabilitas rendah untuk sekarang).
- Notifikasi/alert otomatis (mis. email kalau ada household mencurigakan) — panel ini pull-based
  (admin buka & lihat), bukan push-based.

## 4. Target Users / Personas

| Persona | Deskripsi | Kebutuhan utama |
|---|---|---|
| **Super Admin (pemilik produk)** | Satu-satunya pengguna panel ini | Pantau kesehatan bisnis, kelola data household kalau perlu support/troubleshooting, kelola katalog resep |

## 5. User Flows

**Flow A — Login super admin:**
1. Buka `/super-admin` → form login terpisah dari `/admin` (household), pakai guard & tabel
   `admins` sendiri (§7).
2. Tidak ada opsi "daftar" di sini — akun cuma dibuat lewat seeder/Artisan command (§6.1).

**Flow B — Kelola household & user:**
1. Lihat daftar semua household (lintas tenant — panel ini **satu-satunya tempat** yang boleh
   query lintas household, karena `BelongsToHousehold` normalnya scoping otomatis per-household
   untuk user biasa).
2. Bisa lihat detail: anggota, tier subscription (`billing.md`), pemakaian AI bulan ini.
3. Bisa edit data household (mis. koreksi nama, ubah tier manual untuk kasus support) atau hapus
   household bermasalah.

**Flow C — Kelola katalog resep (pindah dari `/admin`):**
1. `RecipeResource` yang sebelumnya ada di panel `/admin` (household) sekarang cuma muncul di
   `/super-admin`.
2. Perilaku form/table-nya sama persis seperti sebelumnya (lihat `docs/prd/cooking.md` §6.8) —
   cuma pindah panel, bukan didesain ulang.

**Flow D — Monitoring ShoppingList & Finance (lintas household):**
1. Lihat daftar shopping list per household (untuk keperluan support, mis. user komplain data
   hilang) — akses read+edit, bukan cuma read.
2. Lihat transaksi & kategori Finance per household — data sensitif, ditandai di §8.

**Flow E — Dashboard Billing & AI usage (generalisasi `billing.md` §6.5):**
1. Tabel `ai_usage_events`/`ai_usage_counters` (`billing.md` §7) ditampilkan di sini, per household
   & per user — **menggantikan** rencana halaman terpisah di `billing.md` §6.5.
2. Bisa lihat status subscription tiap household, riwayat `subscription_payments`.

## 6. Functional Requirements

### 6.1 Autentikasi & panel terpisah
- Model baru `App\Models\Admin` + tabel `admins` (id, name, email, password, timestamps) —
  **terpisah total** dari tabel `users` (household).
- Guard Laravel baru (mis. `admin`) di `config/auth.php`, provider mengarah ke model `Admin`.
- Panel Filament baru: id `super-admin`, path `/super-admin`, `->authGuard('admin')`, **tanpa**
  `->registration()` — akun dibuat lewat seeder/Artisan command (mis.
  `php artisan make:filament-user` yang diarahkan ke guard baru, atau seeder custom).
- Panel `/admin` (household) **tidak berubah** kecuali kehilangan `CookingPanelPlugin` (dipindah
  ke panel baru, lihat §6.3).

### 6.2 Manajemen Household & User (CRUD penuh)
- Resource untuk `Household`: list semua household, detail (anggota, tier, pemakaian AI), edit,
  hapus.
- Resource untuk `User`: list semua user lintas household, lihat household mana yang diikuti.
- Ini **satu-satunya tempat** di aplikasi yang boleh query lintas household tanpa terkena scoping
  `BelongsToHousehold` — karena dijalankan dari guard/model `Admin`, bukan `User` biasa, jadi tidak
  otomatis kena global scope household (perlu dipastikan saat implementasi, lihat §13).

### 6.3 Manajemen Cooking — pindah `RecipeResource`
- `CookingPanelPlugin` (lihat `docs/prd/cooking.md` §6.8) di-registrasi ke panel `super-admin`,
  **bukan** lagi ke panel `admin`.
- Tidak ada perubahan behavior form/table `RecipeResource` itu sendiri — cuma pindah rumah.

### 6.4 Monitoring & manajemen ShoppingList
- Resource baru untuk lihat/edit `ShoppingList` & `ShoppingListItem` lintas household — untuk
  keperluan support (mis. bantu user yang komplain).
- Termasuk lihat `shopping_list_history` (`docs/prd/shopping-list.md` §6.8) kalau sudah
  diimplementasikan.

### 6.5 Monitoring & manajemen Finance
- Resource baru untuk lihat/edit `Transaction` & `Category` lintas household.
- **Data paling sensitif** di antara semua modul (uang riil keluarga) — lihat catatan privasi §8.

### 6.6 Dashboard Billing & AI usage
- **Menggantikan** `billing.md` §6.5 — tabel `ai_usage_events` & `ai_usage_counters` ditampilkan
  di sini (per household, per user, sortable by pemakaian tertinggi), plus status
  `subscription_tier`/`subscription_expires_at` dan riwayat `subscription_payments`.

## 7. Data Model

| Tabel | Kolom kunci | Catatan |
|---|---|---|
| `admins` (baru) | name, email (unique), password | **Terpisah total** dari `users` — guard sendiri |

Tidak ada tabel baru untuk data yang dimonitor (Household, ShoppingList, Finance, Billing) — CMS
ini murni menyediakan **jendela admin** ke tabel-tabel yang sudah/akan ada di PRD lain, tidak
menduplikasi datanya.

## 8. Non-Functional Requirements

- **Isolasi keamanan:** Panel `/super-admin` **wajib** pakai guard & model terpisah dari `/admin`
  household — ini alasan utama dokumen ini dibuat. Kesalahan di sini bisa berarti household biasa
  bisa akses data household lain.
- **Privasi data Finance:** Transaksi keuangan adalah data paling sensitif secara personal —
  meskipun cuma satu admin (pemilik produk sendiri) yang akses, tetap dicatat sebagai area yang
  butuh kehati-hatian ekstra kalau nanti ada staff tambahan (lihat Non-Goals §3, Gap §12).
- **Tidak ada akses publik:** Panel ini tidak py halaman registrasi/lupa-password yang bisa
  ditemukan publik dengan mudah — akun dibuat manual, bukan self-service.
- **Konsistensi dengan PRD lain:** Fitur di §6.3 dan §6.6 **bukan fitur baru**, cuma
  reorganisasi/pemindahan dari yang sudah direncanakan di `cooking.md` dan `billing.md` — dokumen
  ini adalah "rumah" barunya, bukan spec ulang dari nol.

## 9. Metrics / KPI

- **Waktu resolusi support:** kalau CMS ini dipakai untuk bantu household yang komplain, seberapa
  cepat masalah bisa diverifikasi/diperbaiki dibanding sebelum ada CMS (baseline: harus query
  manual ke DB).
- Metrik lain sudah tercakup di `billing.md` §9 (conversion rate, quota exhaustion, dst) — cuma
  cara **melihatnya** yang berubah (lewat CMS ini), bukan metriknya sendiri.

## 10. Edge Cases & Error Handling

- Admin coba akses `/admin` (panel household) — tidak py efek khusus, guard `admin` cuma valid di
  panel `/super-admin`, bukan otomatis py akses ke panel household juga (dua guard terpisah).
- Household user coba akses `/super-admin` — ditolak di level guard (bukan user model `Admin`),
  tidak perlu extra check manual di tiap resource.
- Household dihapus dari CMS — perlu keputusan cascade (favorit, resep AI-imported yang terhubung,
  dst) — lihat Open Question §13.

## 11. Dependencies & Integrations

- **`docs/prd/cooking.md` §6.8** — `RecipeResource` dipindah ke sini, bukan diduplikasi.
- **`docs/prd/billing.md` §6.5, §7** — dashboard AI usage & data subscription ditampilkan di sini;
  `billing.md` §6.5 perlu ditandai supersession (lihat §14 & edit di `billing.md`).
- **`docs/prd/shopping-list.md` §6.8** — riwayat aktivitas ditampilkan di sini kalau sudah ada.
- Tidak ada dependency eksternal baru (tidak ada payment/AI baru) — murni internal Filament +
  Laravel auth guard.

## 12. Gap & Next Steps

- Audit log (siapa admin mengubah apa) belum dibangun — dicatat sebagai Non-Goal MVP (§3), tapi
  jadi prasyarat serius begitu ada admin kedua.
- Kebijakan cascade saat household dihapus dari CMS belum diputuskan (lihat §13).
- Sistem role/permission (Spatie Permission atau serupa) belum dibangun — cuma relevan begitu ada
  staff kedua, bukan sekarang.

## 13. Open Questions

1. Kebijakan cascade saat super admin hapus household dari CMS — hapus semua data terkait
   (favorit, shopping list, transaksi), atau soft-delete/arsip dulu supaya bisa dipulihkan kalau
   salah hapus?
2. Query lintas household di §6.2 perlu dipastikan **tidak** kena `BelongsToHousehold` global
   scope — apakah cukup karena beda model auth (`Admin` bukan `User`), atau perlu penanganan
   eksplisit (mis. `withoutGlobalScope`) di level resource?
3. Kalau nanti benar-benar butuh staff kedua — kapan titik itu dianggap tercapai, dan apakah sistem
   role/permission dibangun preventif sebelum staff kedua direkrut, atau baru saat itu juga?

## 14. Riwayat Perubahan

| Tanggal | Perubahan |
|---|---|
| 2026-07-29 | Draf awal dari sesi brainstorm — dipicu temuan bahwa aplikasi belum punya pemisahan role sama sekali (panel `/admin` dipakai ganda untuk registrasi household & CRUD `RecipeResource`). Keputusan: panel Filament terpisah (`/super-admin`), satu akun admin saja, CRUD penuh lintas semua modul. Menggantikan `billing.md` §6.5. |
| 2026-07-29 | Ditambahkan §15 Rencana Implementasi (fase kerja + todo list). |

## 15. Rencana Implementasi

> Fase 5 (dashboard Billing/AI usage) **bergantung** ke tabel-tabel dari `billing.md` Fase 1
> (`ai_usage_counters`, `subscription_payments`, dst) — kalau tabel itu belum ada saat fase ini
> dikerjakan, halamannya bisa dibangun duluan dengan empty-state, diisi begitu `billing.md` Fase 1
> selesai.

**Fase 1 — Autentikasi & panel terpisah** *(estimasi: 3–4 jam)*
Fondasi paling kritis — ini yang menutup celah keamanan yang ditemukan di §2.
- Migration: `admins` (name, email unique, password, timestamps) — tabel baru, terpisah total dari
  `users`.
- Model `Admin` (implements `Illuminate\Contracts\Auth\Authenticatable`, terpisah dari model
  `User`).
- Guard baru `admin` di `config/auth.php` (provider mengarah ke model `Admin`).
- `SuperAdminPanelProvider` baru: id `super-admin`, path `super-admin`, `->authGuard('admin')`,
  **tanpa** `->registration()`.
- Seeder/Artisan command untuk buat satu akun admin awal (mis. `php artisan make:filament-user`
  yang diarahkan ke guard `admin`, atau seeder custom kalau command bawaan tidak cocok).

**Fase 2 — Pindah `RecipeResource`** *(estimasi: 1 jam)*
Bergantung ke Fase 1 (panel baru harus sudah ada). Menutup celah akses yang ditemukan di §2.
- Hapus registrasi `CookingPanelPlugin` dari `AdminPanelProvider` (panel household).
- Daftarkan `CookingPanelPlugin` ke `SuperAdminPanelProvider` sebagai gantinya.
- Verifikasi manual: household user login ke `/admin` **tidak lagi** lihat resource resep.

**Fase 3 — Resource Household & User** *(estimasi: 3–4 jam)*
- Filament Resource `Household`: list, detail (anggota, tier, pemakaian AI), edit, hapus.
- Filament Resource `User`: list lintas household, lihat keanggotaan household.
- Pastikan query di resource ini **tidak** kena global scope `BelongsToHousehold` (dijalankan dari
  guard `Admin`, perlu diverifikasi eksplisit — lihat Open Question §13 #2).

**Fase 4 — Resource ShoppingList & Finance** *(estimasi: 3–4 jam)*
Independen dari Fase 3 — bisa paralel.
- Filament Resource untuk `ShoppingList`/`ShoppingListItem` lintas household (termasuk
  `shopping_list_history` kalau `shopping-list.md` Fase 1–3 sudah selesai).
- Filament Resource untuk `Transaction`/`Category` lintas household — tandai sebagai data sensitif
  di UI (mis. label/warning), sesuai §8.

**Fase 5 — Dashboard Billing & AI usage** *(estimasi: 2–3 jam)*
Menggantikan `billing.md` §6.5 — lihat catatan dependency di atas.
- Halaman/widget Filament: `ai_usage_events`/`ai_usage_counters` per household & per user, sortable
  by pemakaian tertinggi.
- Tampilkan `subscription_tier`, `subscription_expires_at`, riwayat `subscription_payments`.

**Fase 6 — Tes minimal** *(estimasi: 2 jam)*
- Pest: household user (guard `web`/`User`) tidak bisa akses `/super-admin`.
- Pest: admin (guard `admin`) bisa lihat data lintas household (verifikasi bypass global scope).
- Pest: household user login ke `/admin` tidak lagi melihat resource resep (regresi Fase 2).

## ✅ Todo List

- [ ] [db] Migration: `admins` (name, email unique, password)
- [ ] [backend] Model `Admin` terpisah dari `User`
- [ ] [config] Guard baru `admin` di `config/auth.php`
- [ ] [backend] `SuperAdminPanelProvider` baru (id `super-admin`, path `super-admin`, guard `admin`, tanpa registration)
- [ ] [infra] Seeder/Artisan command untuk buat akun admin awal
- [ ] [backend] Hapus `CookingPanelPlugin` dari `AdminPanelProvider`, daftarkan ke `SuperAdminPanelProvider`
- [ ] [backend] Filament Resource `Household` (list/detail/edit/hapus, lintas household)
- [ ] [backend] Filament Resource `User` (list lintas household)
- [ ] [backend] Filament Resource `ShoppingList`/`ShoppingListItem` lintas household
- [ ] [backend] Filament Resource `Transaction`/`Category` lintas household (tandai sensitif)
- [ ] [backend] Halaman/widget dashboard AI usage & subscription (dari tabel `billing.md`)
- [ ] [test] Pest: household user tidak bisa akses `/super-admin`
- [ ] [test] Pest: admin bisa query lintas household (bypass global scope terverifikasi)
- [ ] [test] Pest: household user di `/admin` tidak lagi lihat resource resep
