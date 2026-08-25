# Roadmap

Fitur yang ditunda secara sengaja. Tambahkan saat ada kebutuhan nyata.

## Modul lanjutan (belum dibangun)
- Edukasi anak (child education helper)
- Perawatan anak sakit (sick-child care tracker)

Fondasi `Household` + `BelongsToHousehold` sudah dirancang menampung modul-modul ini tanpa rombak —
tinggal tambah tabel household-scoped baru + folder modul baru mengikuti pola Cooking/ShoppingList/Finance.

## Auth & tenancy
- Verifikasi email nyata (perlu provider email zero-budget yang teruji — kandidat: Resend free tier).
  Saat ini `emailVerification()` tidak diaktifkan di panel Filament.
- Undangan anggota household (invite flow) — saat ini hanya owner yang terbentuk otomatis saat
  registrasi, belum ada cara menambah anggota lain ke household yang sama.
- Tenancy Filament native (`->tenant()`) — hanya relevan jika suatu saat butuh UX ganti-ganti
  household yang lebih kaya daripada satu household per user.

## Role & permission admin SaaS (naikkan dari kolom string ke library)

**Kondisi sekarang (2026-08-25).** Otorisasi admin dipegang satu kolom string `admins.role` dengan
dua nilai, `owner` dan `admin`, sumbernya `Admin::roleOptions()`. Yang membedakannya persis satu
hal: owner boleh mengelola akun admin, admin biasa tidak. Penegakannya ada di dua tempat saja —
`Admin::isOwner()` dan `AdminResource::ownerOnly()` (dipakai oleh `getViewAny`/`getCreate`/
`getEdit`/`getDelete` + `shouldRegisterNavigation`). Resource lain di panel backoffice
(`HouseholdResource`, `UserResource`, `RecipeResource`) belum melihat role sama sekali.

Ini cukup untuk dua peran. Begitu muncul peran ketiga, atau begitu butuh beda hak **per resource**
(mis. staf yang boleh lihat Customer tapi tidak boleh sentuh katalog resep), pola `if ($this->role
=== ...)` akan menyebar ke tiap resource dan jadi mahal dirawat. Titik itulah waktunya pindah ke
library.

**Kandidat: `spatie/laravel-permission`.** Sudah disebut sebagai kandidat di
`docs/prd/admin-cms.md` §3 (waktu itu sebagai Non-Goal, karena rencananya cuma satu akun admin —
asumsi itu sudah tidak berlaku). Diverifikasi terhadap dokumentasi v7: package ini mendukung
**multiple guards**, dan model Authenticatable non-`User` bisa memakai trait `HasRoles` dengan
`$guard_name` sendiri — persis bentuk yang kita butuhkan.

**Hal-hal spesifik codebase ini yang akan menggigit kalau tidak disiapkan:**

- **Dua guard, dan role hanya boleh untuk satu.** Aplikasi punya guard `web` (model `User`,
  customer) dan `admin` (model `Admin`). Spatie mengunci setiap role/permission ke satu guard,
  jadi `Admin` wajib mendeklarasikan `protected string $guard_name = 'admin';`. Tanpa itu role
  admin bocor ke namespace guard `web` dan pengecekannya diam-diam selalu false.
- **Jangan campur dengan role household.** `household_user.pivot.role` (`owner`/`member`) sudah ada
  dan memakai istilah yang sama, tapi sumbunya beda total: itu peran seseorang di dalam
  keluarganya, bukan peran internal SaaS. Keduanya harus tetap terpisah — jangan tergoda
  menyatukan karena namanya mirip.
- **Otorisasi Filament lewat `get*AuthorizationResponse()`, bukan `can*()`.** Lihat catatan di
  CLAUDE.md. Ini pernah menggigit sekali: override `canDelete()` lulus test tapi UI tetap
  menghapus barisnya. Plugin yang menghasilkan Policy otomatis berinteraksi dengan jalur ini —
  verifikasi dengan menjalankan aksi tabel sungguhan di test, bukan memanggil helper-nya.
- **Migrasi datanya kecil.** Cukup `admins.role` → assign role Spatie, lalu kolomnya di-drop.
  Jumlah barisnya sedikit, jadi ini bukan bagian yang sulit.

**Plugin Filament untuk Spatie** (`bezhanSalleh/filament-shield` dan alternatif yang lebih baru)
mengotomatiskan pembuatan Policy per Resource/Page/Widget. **Cek dulu dukungan Filament 5-nya saat
mengerjakan** — project ini di Filament 5, dan status kompatibilitas plugin-plugin itu belum
terverifikasi per catatan ini ditulis. Kalau belum ada yang matang, `spatie/laravel-permission`
polos tanpa plugin sudah cukup: titik penegakannya cuma segelintir method di resource.

## Cooking
- Resep tetap katalog global/shared, bukan per-household. Jika ini jadi masalah (mis. AI import
  satu keluarga muncul di keluarga lain), pertimbangkan kolom `household_id` nullable di `recipes`.
- Filament steps editor masih `Textarea` polos (satu baris = satu langkah), belum jadi `Repeater`
  dengan metadata per-langkah (durasi, foto).
- Gambar resep di-upload ke filesystem lokal — hilang saat redeploy di Render (filesystem
  ephemeral). Belum dipindah ke S3/Cloudinary.
- **Filter exclude-bahan/alergi & tag diet (vegan/vegetarian/halal/gluten-free)** — belum ada sama
  sekali di data model maupun `docs/prd/cooking.md` §15 (yang ada baru filter kategori-makan &
  jenis-masakan, soal occasion bukan restriksi diet). Untuk market Indonesia, halal khususnya
  bukan nice-to-have. Sengaja ditunda dari scope `feat/cooking` (2026-08-04) — pattern
  implementasinya bisa niru `meal_categories` (array/jsonb + filter overlap) begitu ada kepastian
  target rilis publik; digarap sekali jadi dengan feedback user nyata, bukan ditebak sekarang.

## UI / modul baru (dari redesign Figma 2026-08-18)
- **Peserta acara** — `events` baru punya satu `user_id` (penanggung jawab). Frame Beranda
  menumpuk beberapa avatar per acara; butuh pivot `event_user` kalau memang mau multi-peserta.
- **Acara berulang** (mingguan/bulanan) belum ada — `events` menyimpan satu baris per kejadian.
- **Kalender & Tugas belum punya Filament Resource**, beda dari Cooking/Finance.
- ~~**Keputusan terbuka: ubin "Tagihan" vs modul Finance.**~~ **Diputuskan 2026-08-19: fitur
  terpisah.** Tagihan = pengingat jatuh tempo lewat Google Calendar (`docs/prd/tagihan.md`),
  Finance = cash flow model kantong (`docs/prd/finance.md`). Ubin Tagihan sudah aktif.
  `FinancePage` masih belum diredesain (pola kartu Tailwind lama, meski sudah ikut palet baru) —
  sekarang murni karena antre di belakang fitur kantong, bukan lagi karena keputusan menggantung.
- **Kolom kategori item belanja** (Dapur / Kamar Mandi dst). Frame Figma mengelompokkan daftar
  belanja per ruangan, tapi `shopping_list_items` belum punya kolomnya.
- **Harga item belanja** — frame menampilkan "Estimasi Total Budget"; sementara diganti jumlah item
  belum dibeli sampai ada kolom harga.
- **Preferensi notifikasi** — baris toggle ada di frame Profil, belum ada penyimpanannya.

## Kalender ↔ Google (ditunda dari `feat/kalender-keluarga`, 2026-08-25)

Yang dikirim sekarang: feed ICS per-anggota (`/kalender/{token}.ics`), satu arah, tanpa OAuth.
Sengaja tidak dikerjakan, beserta pemicunya:

- **Sync dua arah lewat Google Calendar API.** Semua scope tulis Calendar tergolong *sensitive* →
  verifikasi app + cap 100 user permanen per Cloud project selama belum lolos. Scope yang lebih
  sempit `calendar.app.created` ("bikin kalender sekunder + kelola event di dalamnya") **belum
  diverifikasi klasifikasinya** — cek kolom sensitivitas di scope picker Cloud Console sebelum
  merencanakan apa pun di atasnya. Selain itu tidak ada cron di app ini, jadi pull dari Google
  harus dipicu on-request.
- **Notifikasi real-time.** Google menyegarkan feed langganan 12–24 jam sekali dan tidak bisa
  dipercepat, jadi tugas untuk hari yang sama tidak akan keburu sampai. Pemicunya: keluhan nyata
  soal tugas mendadak. Kandidat termurah = Web Push lewat service worker PWA yang sudah ada
  (butuh VAPID key + tabel subscription), bukan OAuth Google.
- **Tugas tanpa `due_on` dan tugas yang sudah selesai** tidak masuk feed — ICS wajib punya tanggal,
  dan tugas selesai hanya jadi sampah visual (perlu diingat: karena refresh lambat, tugas yang baru
  dicentang masih nangkring di Google sampai fetch berikutnya).
- **Tugas tanpa penanggung jawab** tidak muncul di feed siapa pun. Kalau ternyata dipakai sebagai
  "pengingat kolektif", masukkan ke feed semua anggota. Awas: `->orWhereNull('user_id')` polos di rantai query
  itu jadi `... AND ... OR user_id IS NULL` (OR mengikat lebih longgar dari AND) dan
  membocorkan tugas tanpa penanggung jawab dari **semua** household lewat endpoint tanpa
  auth. Wajib dibungkus: `->where(fn ($q) => $q->where('user_id', $user->id)->orWhereNull('user_id'))`.
- **Anggota sebagai attendee Google** mustahil tanpa OAuth (service account butuh Domain-Wide
  Delegation = Workspace berbayar). Karena itu nama penanggung jawab ditempel di judul event.

## Billing
- Belum ada infrastruktur billing/subscription (Stripe/Cashier) — di luar cakupan MVP zero-budget.
