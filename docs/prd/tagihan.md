# PRD: Modul Tagihan (Pengingat Bayar)

> **Status dokumen:** Ditulis bersamaan dengan implementasinya (2026-08-19) — bukan spec yang
> mendahului kode, bukan pula reverse-engineer murni. Seluruh keputusan di sini diambil di sesi
> brainstorm yang sama, lalu langsung dibangun.

## 1. Ringkasan

Modul Tagihan mencatat **kapan** keluarga harus membayar tagihan berulang (listrik, air, internet,
SPP, PBB) dan mengirim pengingatnya ke **Google Calendar** — supaya ayah dan ibu sama-sama kena
notifikasi tanpa aplikasi ini perlu membangun push notification sendiri.

Menandai satu tagihan lunas otomatis mencatat pengeluarannya ke modul Finance, sehingga kejadian
yang sama tidak perlu diinput dua kali.

Pengirimannya lewat **feed ICS** yang di-subscribe keluarga di Google Calendar, bukan lewat Google
Calendar API — lihat §6.3 untuk alasannya.

## 2. Latar Belakang & Masalah

`docs/ROADMAP.md` mencatat "Pengingat bayar tagihan" sebagai modul lanjutan yang ditunda, sekaligus
satu **keputusan terbuka**: ubin "Tagihan" di grid Beranda sengaja dirender non-aktif karena belum
jelas apakah ia modul Finance yang berganti nama, atau fitur terpisah. Dokumen ini menutup keputusan
itu: **keduanya fitur terpisah.**

- **Tagihan** menjawab "kapan harus bayar".
- **Finance** (`finance.md`) menjawab "uangnya ke mana" — pencatatan cash flow model kantong.

Keduanya bertemu di satu titik saja: tandai lunas → `Transaction` expense (§6.5).

## 3. Goals & Non-Goals

**Goals:**
- Household bisa mendaftarkan tagihan sekali, dengan pengulangan bebas, lalu dilupakan — Google yang
  mengingatkan tiap periode.
- Pengingat sampai ke **semua anggota keluarga**, bukan cuma yang membuka aplikasi.
- Menandai lunas **sekaligus** mencatat pengeluarannya ke Finance, dengan nominal asli (bukan
  perkiraan).
- Tidak menambah infrastruktur operasional apa pun: **tidak ada cron/scheduler, tidak ada queue,
  tidak ada push notification, tidak ada OAuth.**

**Non-Goals (sengaja tidak dibangun):**
- **Menulis ke kalender keluarga yang sudah ada** — feed ICS membuat kalender *baru* di akun tiap
  anggota. Butuh Google Calendar API untuk itu, yang berkonsekuensi verifikasi app (§6.3).
- **Sinkronisasi dua arah** — mengubah/menghapus event di Google tidak berpengaruh ke aplikasi.
  Feed ini read-only.
- **Pembayaran otomatis / integrasi biller** — modul ini murni pengingat + pencatatan, tidak
  menyentuh uang sungguhan.
- **Riwayat pembayaran sebagai halaman sendiri** — `bill_payments` sudah menyimpan datanya, tapi
  UI-nya belum ada (lihat §12).
- **Notifikasi in-app** — Google Calendar yang jadi kanal pengingat, itu inti dari pilihan desainnya.

## 4. Target Users / Personas

| Persona | Deskripsi | Kebutuhan utama |
|---|---|---|
| **Ibu rumah tangga (primary)** | Paling sering memegang jadwal pembayaran rumah tangga | Tidak kelewat jatuh tempo; catat lunas tanpa buka aplikasi keuangan terpisah |
| **Ayah** | Ikut membayar sebagian tagihan | Tahu apa yang jatuh tempo tanpa harus bertanya |

## 5. User Flows

**Flow A — Daftarkan tagihan berulang**
1. `/tagihan` → tombol + → isi nama, perkiraan nominal, kantong tujuan, tanggal jatuh tempo
   pertama, jumlah hari pengingat, dan pengulangan.
2. Pengulangan dipilih dari preset (Sekali jalan / Tiap bulan / Tiap 3 bulan / Tiap tahun / Tiap
   minggu) yang **menghasilkan** string RRULE dari tanggal jatuh tempo pertama, atau lewat opsi
   **Lanjutan** untuk mengetik RRULE sendiri (§6.2).
3. Simpan → tagihan langsung muncul di daftar dan di feed kalender.

**Flow B — Hubungkan ke Google Calendar (sekali per anggota)**
1. Panel "Hubungkan ke Google Calendar" di `/tagihan` menampilkan alamat feed ICS household.
2. Tiap anggota menempelkan alamat itu di Google Calendar → Kalender lain → Dari URL.
3. Panel menyebut apa adanya bahwa hasilnya kalender baru (bukan kalender keluarga yang sudah ada)
   dan bahwa perubahan menyusul 8–24 jam — ekspektasi yang keliru di sini akan dilaporkan sebagai
   bug padahal perilaku Google.
4. Tombol "Ganti alamat" me-regenerate token; alamat lama langsung mati (§6.4).

**Flow C — Tandai lunas**
1. Kartu tagihan menampilkan occurrence berikutnya yang belum lunas; yang sudah lewat diberi
   lencana "Telat".
2. "Tandai lunas" → isi **nominal asli** (perkiraan cuma jadi nilai awal) → catat.
3. `BillPayment` tersimpan, `Transaction` expense terbuat di Finance, dan kartu bergeser ke
   occurrence berikutnya. Occurrence yang lunas hilang dari feed kalender pada refresh berikutnya.

**Flow D — Arsipkan tagihan**
1. Tagihan yang tidak berlaku lagi diarsipkan (`archived_at`), bukan dihapus.
2. Hilang dari daftar dan dari feed kalender; riwayat pembayarannya tetap utuh.

## 6. Functional Requirements

### 6.1 Tagihan
- Field: `name`, `amount_estimate` (nullable), `category_id` (nullable), `rrule` (nullable),
  `starts_on`, `reminder_days_before` (default 2), `notes`, `archived_at`.
- Household-scoped via `BelongsToHousehold`.
- **Nominal adalah perkiraan, bukan nominal final** — listrik/air berubah tiap periode. Nominal asli
  diisi saat tandai lunas dan itulah yang masuk Finance (§6.5).

### 6.2 Pengulangan lewat RRULE
- Pengulangan disimpan sebagai **string RRULE (RFC 5545) mentah** di satu kolom; `null` berarti
  tagihan sekali jalan.
- Tidak ada tabel occurrence dan **tidak ada scheduler** — tanggal jatuh tempo diekspansi saat
  dibutuhkan oleh `BillSchedule`, memakai `rlanvin/php-rrule`.
- UI tidak punya widget builder pengulangan: preset menghasilkan string RRULE, dan opsi "Lanjutan"
  menerima RRULE ketikan user. `BillList::presetFor()` mengenali kembali string yang dihasilkan
  presetnya sendiri saat tagihan dibuka untuk diedit, jadi tagihan preset tidak jatuh ke "Lanjutan".
- **RRULE ketikan user adalah trust boundary**: divalidasi dengan mencoba membangun `RRule`, dan
  ditolak juga kalau aturannya valid secara sintaks tapi tidak pernah menghasilkan tanggal
  (mis. `FREQ=YEARLY;BYMONTH=2;BYMONTHDAY=30`).

### 6.3 Pengiriman lewat feed ICS (bukan Calendar API)
- Route publik `GET /tagihan/kalender/{token}.ics`, di luar middleware `auth`.
- **Kenapa bukan Google Calendar API:** scope `calendar.events` masuk tier **sensitive** Google →
  wajib verifikasi app (video demo, domain ownership, privacy policy); sebelum lolos, app dibatasi
  100 test user dan menampilkan layar peringatan. Ada laporan developer 2026 yang tersangkut review
  >5 minggu. Feed ICS melepas ketergantungan itu sepenuhnya.
- **Konsekuensi yang diterima sadar:** (a) subscribe URL membuat kalender *baru* di akun tiap
  anggota — tidak bisa menyuntik ke kalender keluarga yang sudah ada; (b) Google me-refresh feed
  eksternal tiap 8–24 jam, tidak bisa dipercepat, tanpa force-refresh. Untuk tagihan yang diketahui
  berminggu-minggu sebelumnya, keterlambatan itu tidak berdampak.
- Kalau kelak butuh penulisan langsung ke kalender keluarga, Calendar API bisa ditambahkan sebagai
  transport kedua **tanpa mengubah data model** (§12).

### 6.4 Bentuk kalendernya
- Satu `VEVENT` all-day (`VALUE=DATE`) per occurrence, **bukan** satu VEVENT berulang dengan RRULE.
  Alasannya: event ditaruh pada **tanggal pengingat** (`jatuh tempo − reminder_days_before`), dan
  menggeser `DTSTART` **tidak** menggeser occurrence sebuah RRULE — `FREQ=MONTHLY;BYMONTHDAY=20`
  tetap jatuh tanggal 20 berapa pun `DTSTART`-nya. Mengekspansi sendiri juga berarti RRULE bebas
  ketikan user tidak pernah diteruskan mentah-mentah ke Google.
- Tanggal jatuh tempo ditulis di judul: `🧾 Bayar Listrik PLN — jatuh tempo 20 Ags, ±Rp 450.000`.
  Kehadiran event di day view + email agenda harian Google itulah pengingatnya.
- **`VALARM` tetap diemit tapi fiturnya tidak bergantung padanya** — belum terkonfirmasi apakah
  Google mengeksekusi alarm di kalender langganan atau hanya menyimpannya (lihat §13).
- Occurrence yang sudah lunas dan tagihan yang diarsip tidak diemit sama sekali; jendela feed
  mundur 3 bulan (supaya yang telat tetap terlihat) dan maju 12 bulan, bergulir tiap kali diambil.
- `UID` stabil per occurrence (`bill-{id}-{Ymd}@asisten-mama`) supaya Google tidak menduplikasi.
- Nilai TEXT di-escape (`\` `;` `,` newline) dan baris dilipat di 75 oktet per karakter UTF-8 —
  nama tagihan diketik user, dan satu koma yang lolos merusak **seluruh** feed secara senyap.
- Token: kolom `households.calendar_token`, dibuat saat pertama dibutuhkan, bisa di-regenerate
  (= mencabut akses alamat lama).

### 6.5 Tandai lunas → Finance
- `RecordBillPayment` membuat `BillPayment` + `Transaction` expense dalam satu transaksi DB.
- Kategori: `bills.category_id` kalau diisi, kalau tidak jatuh ke kategori **"Tagihan"** — salah satu
  dari 7 kategori yang di-seed tiap household dibuat (`DefaultCategories::DEFAULTS`).
- Deskripsi transaksi menyebut nama tagihan & periodenya: `Tagihan: Listrik PLN (Apr 2026)`.
- Dependency **searah** Bills → Finance, pola yang sama dengan `MissingIngredientsToShoppingList`.
  Finance tidak tahu apa pun soal modul Tagihan. Saat kantong (`finance.md` §6.10) dibangun, model
  event `Transaction::created` otomatis menangkap transaksi ini juga — tidak ada penyesuaian yang
  perlu dilakukan di sisi Tagihan.
- Satu periode tidak bisa dibayar dua kali (unique `bill_id` + `period_on`, plus guard aplikasi).

### 6.6 Lencana di Beranda
- Ubin Tagihan menampilkan jumlah tagihan yang **telat + jatuh tempo dalam sepekan**, dihitung
  dengan mengekspansi rrule tiap tagihan (tanggal jatuh tempo tidak tersimpan sebagai baris).
- Daftar Tagihan, lencana Beranda, dan feed ICS **wajib memakai jendela pencarian yang sama**
  (`BillSchedule::LOOKBACK_MONTHS`). Kalau berbeda, tagihan sekali-jalan yang telat berbulan-bulan
  bisa terhitung di lencana sementara kartunya tidak menampilkan tombol "Tandai lunas" — user
  melihat angka yang tidak bisa dihilangkan. Ada testnya.

## 7. Data Model

| Tabel | Kolom kunci | Catatan scoping |
|---|---|---|
| `bills` | household_id, category_id (nullable), name, amount_estimate (decimal 12,2 nullable), rrule (nullable), starts_on (date), reminder_days_before (default 2), notes, archived_at (nullable) | Household-scoped; index (household_id, starts_on) |
| `bill_payments` | household_id, bill_id, period_on (date), amount (decimal 12,2), user_id, transaction_id (nullable), paid_at | Household-scoped, **insert-only**. Unique (bill_id, period_on) |
| `households` | + `calendar_token` (string 64, unique, nullable) | — |

`archived_at` sengaja bukan `deleted_at`/`SoftDeletes` bawaan — konsisten dengan keputusan yang sama
di `finance.md` §6.8, supaya tidak tertukar makna dengan penghapusan permanen.

`bill_payments.transaction_id` nullable & `nullOnDelete`: transaksi boleh dihapus dari Finance tanpa
ikut menghapus riwayat pembayaran tagihan.

## 8. Non-Functional Requirements

- **Tenancy/keamanan:** diverifikasi `HouseholdIsolationTest` (termasuk lewat penelusuran relasi).
- **Titik paling rawan — feed tanpa sesi:** `BelongsToHousehold` memfilter dengan
  `Auth::user()?->current_household_id`, yang bernilai `null` pada request feed. Fail-safe (hasilnya
  kosong, bukan bocor semua), tapi artinya **setiap** pembacaan di jalur feed harus
  `withoutGlobalScope('household')` + filter household eksplisit. Ini sudah menggigit sekali saat
  implementasi: `BillSchedule::paidPeriods()` semula mengembalikan kosong tanpa sesi, yang membuat
  tagihan lunas muncul lagi di kalender. Ada test khusus untuk skenario tanpa autentikasi.
- **Nol infrastruktur baru:** tanpa cron, queue, push notification, atau OAuth. Satu dependency baru
  (`rlanvin/php-rrule`, tanpa dependency eksternal).
- **Presisi nominal:** `decimal:2`, konsisten dengan `transactions`.

## 9. Metrics / KPI (usulan — belum diinstrumentasi)

- **Adopsi feed:** % household yang pernah membuka panel koneksi / token-nya pernah diakses.
- **On-time rate:** % pembayaran yang dicatat sebelum tanggal jatuh tempo — inti nilai fitur ini.
- **Akurasi perkiraan:** selisih `amount_estimate` vs `bill_payments.amount`.
- **Adopsi RRULE lanjutan:** % tagihan yang tidak memakai preset — sinyal apakah preset sudah cukup.

## 10. Edge Cases & Error Handling

- RRULE ketikan user malformed → ditolak validasi, tidak pernah sampai ke feed (§6.2).
- RRULE valid tapi tidak pernah menghasilkan tanggal → ditolak validasi.
- `BYMONTHDAY=31` di bulan pendek → RFC 5545 melewatinya, tidak meluber ke tanggal 1 (ada testnya).
- Periode dibayar dua kali → ditolak guard aplikasi + unique constraint.
- **Tanggal pembayaran yang bukan jatuh tempo** → ditolak. `payingPeriod` adalah properti Livewire
  publik: nilainya datang dari klien, tombol di blade cuma mengusulkan. Tanpa cek
  `BillSchedule::isOccurrence()`, pembayaran bisa dicatat pada tanggal sembarang — dan kalau
  tanggalnya kebetulan cocok dengan occurrence di masa depan, occurrence itu diam-diam hilang dari
  kalender. Bukan lubang lintas-household (`Bill::findOrFail` tetap ter-scope).
- Tanggal periode tidak valid dari input → ditolak `RecordBillPayment` **sebelum** menyentuh query;
  string sembarang yang lolos ke `where period_on = ...` bukan cuma error, tapi membatalkan seluruh
  transaksi DB yang sedang berjalan di Postgres.
- Token feed tidak dikenal → 404. Token diganti → alamat lama langsung 404.
- Nama tagihan mengandung koma/titik-koma/emoji → di-escape & dilipat tanpa merusak feed.
- Tagihan diarsip → hilang dari daftar & feed, riwayat pembayaran tetap ada.

## 11. Dependencies & Integrations

- **`rlanvin/php-rrule` ^3.0** — ekspansi RFC 5545. Satu-satunya dependency baru; nol dependency
  eksternal.
- **Finance (`finance.md`)** — searah Bills → Finance (§6.5).
- **Google Calendar** — konsumen feed ICS. Tidak ada API key, akun, atau OAuth yang terlibat.

## 12. Gap & Next Steps

- **UI riwayat pembayaran** belum ada — `bill_payments` sudah terisi, tampilannya belum dibangun.
- **Google Calendar API sebagai transport kedua** (menulis ke kalender keluarga yang sudah ada,
  update instan) — gated pada verifikasi app Google. Data modelnya sudah siap; yang berubah cuma
  cara pengirimannya.
- **Login Google (Socialite)** disepakati di sesi yang sama tapi merupakan fitur auth, bukan
  tagihan — rencana terpisah. Scope-nya (`openid`/`email`/`profile`) non-sensitive, jadi tidak kena
  gerbang verifikasi.
- **Verifikasi empiris `VALARM`** di Google Calendar sungguhan (§13).
- **Peserta/penanggung jawab tagihan** — `bills` belum punya `user_id`; semua tagihan milik keluarga.

## 13. Open Questions

1. **Apakah Google mengeksekusi `VALARM` di kalender ICS langganan, atau hanya menyimpannya?**
   Belum terkonfirmasi; sumber yang tersedia membahas Google saat meng-*ekspor* ICS, bukan
   mengonsumsi feed. Desainnya sudah dibuat **tidak bergantung** pada jawabannya (event jatuh di
   tanggal pengingat, §6.4), jadi ini menentukan apakah ada lapisan pengingat tambahan — bukan
   apakah fiturnya jalan. Perlu dicek dengan akun Google asli.

## 14. Riwayat Perubahan

| Tanggal | Perubahan |
|---|---|
| 2026-08-19 | Draf awal + implementasi. Menutup keputusan terbuka `ROADMAP.md` (Tagihan vs Finance = fitur terpisah). Keputusan desain: feed ICS dulu (Calendar API ditunda karena verifikasi scope sensitive), RRULE mentah tanpa tabel occurrence & tanpa cron, nominal perkiraan + nominal asli saat bayar, tandai lunas → `Transaction` searah ke Finance. Saat implementasi ditemukan bahwa menggeser `DTSTART` tidak menggeser occurrence RRULE, sehingga feed diubah dari satu VEVENT berulang + `EXDATE` menjadi satu VEVENT per occurrence hasil ekspansi PHP (§6.4). |

## 15. Rencana Implementasi

Seluruh fase di bawah **sudah dikerjakan** pada 2026-08-19.

## ✅ Todo List

- [x] [db] Migration: `bills`, `bill_payments`, `households.calendar_token`
- [x] [backend] Model `Bill` & `BillPayment` (household-scoped, scope `active()`)
- [x] [backend] `BillsServiceProvider` (route via `middleware('web')`, `Livewire::addNamespace`)
- [x] [backend] Service `BillSchedule` — ekspansi RRULE, occurrence belum lunas, `nextUnpaid`
- [x] [backend] Service `IcsFeed` — VEVENT per occurrence, escaping, folding UTF-8
- [x] [backend] Route feed ICS ber-token di luar middleware `auth`
- [x] [backend] Service `RecordBillPayment` — BillPayment + Transaction dalam satu transaksi DB
- [x] [frontend] Komponen `bills::bill-list` — daftar, form tambah/edit, tandai lunas, arsip
- [x] [frontend] Panel koneksi Google Calendar + regenerate token
- [x] [frontend] Aktifkan ubin Tagihan di Beranda + lencana `dueBillCount`
- [x] [test] `BillSchedule` (8), `IcsFeed` (12), `RecordBillPayment` (6), route feed (4), `BillList` (15)
- [x] [test] Isolasi household untuk `bills`/`bill_payments`, `/tagihan` di `PageRendersTest`
- [x] [backend] Guard `payingPeriod` harus benar-benar occurrence tagihan tsb (§10)
- [x] [backend] Jendela pencarian tunggal `BillSchedule::LOOKBACK_MONTHS` dipakai ketiga konsumen
- [ ] [ops] Verifikasi `VALARM` dengan akun Google asli (§13)
- [ ] [frontend] UI riwayat pembayaran (§12)
