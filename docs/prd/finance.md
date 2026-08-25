# PRD: Modul Finance (Keuangan Keluarga)

> **Status dokumen:** Baseline — ditulis dengan cara reverse-engineer dari kode yang sudah ada
> (sama seperti `cooking.md`/`shopping-list.md`), bukan spec yang mendahului implementasi.
> Merekam kontrak perilaku saat ini sebagai titik referensi sebelum modul ini diubah lebih lanjut.

## 1. Ringkasan

Modul Finance adalah pencatat pemasukan/pengeluaran sederhana **per household** — satu buku kas
bersama yang bisa diisi oleh semua anggota keluarga, dengan kategori transaksi yang di-seed
otomatis saat household dibuat. Baseline-nya paling minimal di antara ketiga modul MVP: cuma catat
transaksi + ringkasan bulan berjalan, tanpa budgeting sama sekali.

Fitur **Kantong** ala Bank Jago sudah dibangun (2026-08-26, §6.5–§6.11): kategori expense bisa
diberi **alokasi nominal per periode** ("kantong"), pengeluaran wajib terpetakan ke kantong mana
budgetnya diambil, dan kantong bisa **di-top-up di tengah periode** dengan setiap top-up tercatat
sebagai event historis untuk analisis pola pemakaian lintas semester/tahun nanti. Ditambah:
household bisa **bikin kategori/kantong sendiri** (7 default tetap terkunci), **edit/hapus
transaksi**, riwayat & ringkasan **difilter per periode** (bukan lintas-waktu tanpa filter), dan
household bisa **atur tanggal reset periode sendiri** (default tanggal 1, bisa disesuaikan mis.
ikut tanggal gajian).

**Satu penyimpangan dari rencana 2026-07-30:** ringkasan periode & sisa kantong **dihitung saat
ditampilkan**, bukan disimpan sebagai kolom/tabel snapshot yang dipelihara model event — lihat
§6.10 untuk alasannya.

## 2. Latar Belakang & Masalah

**Rumusan masalah:** Anggota keluarga perlu mencatat uang masuk/keluar bersama (bukan tercecer di
aplikasi masing-masing atau di kepala), supaya bisa tahu kondisi keuangan bulan berjalan tanpa
harus rekap manual.

Modul ini juga jadi **penerima** dua integrasi cross-module yang direncanakan di PRD lain:
`ShoppingList → Finance` (catat pengeluaran belanja langsung dari daftar belanja, lihat
`shopping-list.md` §6.7) — belum diimplementasikan di kode saat ini.

## 3. Goals & Non-Goals

**Goals:**
- Household punya kategori pemasukan/pengeluaran default yang langsung tersedia saat
  household dibuat (`DefaultCategories::seedFor()`, dipanggil dari `Household::createWithOwner()`).
- Anggota manapun bisa mencatat transaksi (income/expense) dengan kategori, nominal, catatan
  bebas, dan tanggal kejadian.
- Ringkasan pemasukan/pengeluaran/saldo **bulan berjalan** langsung terlihat di halaman yang sama
  dengan form input — tidak perlu buka laporan terpisah.
- Household bisa **set nominal budget per kantong (kategori expense) di awal
  periode** — lihat §6.5.
- Pengeluaran **wajib terpetakan ke kantong** mana budgetnya diambil — kategori
  jadi wajib untuk transaksi expense (lihat §6.2, §6.6).
- Kantong bisa **di-top-up di tengah periode**, setiap top-up tercatat sebagai
  event historis (siapa, kapan, berapa) — fondasi data untuk analisis pola top-up lintas
  semester/tahun nanti (lihat §6.7).
- Household bisa **bikin kategori/kantong custom sendiri** — 7 kategori default
  tetap ada & terkunci (tidak bisa diarsipkan/dihapus, cuma bisa diedit nama/icon), kategori
  tambahan buatan user bebas diarsipkan kapan saja (lihat §6.8).
- Membuat kantong custom baru **mewajibkan** langsung set nominal budget awal
  dalam satu alur yang sama — tidak ada kantong yang "menggantung" tanpa budget begitu selesai
  dibuat (lihat §6.8).
- Household bisa **edit/hapus transaksi** yang sudah dicatat, untuk koreksi
  kesalahan input (lihat §6.9).
- Ringkasan periode & sisa kantong **dihitung dari transaksi saat ditampilkan** — rencana awal
  (menyimpannya permanen per periode) dibatalkan, lihat §6.10.
- Riwayat transaksi **difilter per satu periode** (bukan menampilkan lintas-waktu
  tanpa filter) — user ganti filter periode untuk lihat bulan lain, lihat §6.11.
- Household bisa **atur tanggal reset periode sendiri** (default tanggal 1) — mis.
  ikut tanggal gajian, bukan cuma awal bulan kalender, lihat §6.11.

**Non-Goals (sengaja tidak dibangun di baseline ini):**
- Multi-currency / uang asing — nominal diasumsikan satu mata uang (implisit Rupiah dari UI,
  `number_format(..., ',', '.')`), tidak ada kolom currency.
- Admin/Filament resource — sama seperti ShoppingList, tidak ada CRUD admin karena data murni
  milik household.
- **Kantong untuk kategori income** — budgeting cuma berlaku untuk expense (membatasi
  pengeluaran); kategori income (Gaji, dll) tetap seperti sekarang, tanpa konsep alokasi/target.
- **Blocking pengeluaran over-budget** — kantong yang sudah habis (termasuk top-up) tetap
  menerima transaksi baru, cuma ditampilkan minus/merah — tidak ada penolakan transaksi.
- **Carry-over saldo antar periode** — tiap periode, kantong mulai dari alokasi baru yang di-set
  user; sisa/kurang periode lalu **tidak** otomatis menambah/mengurangi alokasi periode
  berikutnya, murni jadi catatan historis (lihat §6.7).
- **Hard-delete kategori default** — 7 kategori seed selamanya ada untuk tiap household (cuma
  bisa diedit nama/icon), untuk menjaga baseline yang stabil (lihat §6.8).
- **UI analisis pola historis** (semester/tahun, grafik tren) — data fondasinya dibangun sekarang
  (§6.7, §6.10), tapi tampilan/laporan analisisnya sendiri **belum** di-scope di PRD ini (lihat
  §12).
- **Auto-budgeting terjadwal** (top-up otomatis berkala ala Jago) — top-up tetap manual per
  kejadian, tidak ada penjadwalan recurring.
- **Kolom/tabel snapshot ringkasan** (`monthly_finance_summaries`, `spent_amount`,
  `topup_total_amount`) — dibatalkan sebelum dibangun, lihat §6.10.

## 4. Target Users / Personas

| Persona | Deskripsi | Kebutuhan utama |
|---|---|---|
| **Ibu rumah tangga (primary)** | Paling sering pegang & catat uang belanja harian | Catat cepat, lihat sisa saldo bulan ini |
| **Anggota keluarga lain** | Ikut catat kalau mereka yang belanja/dapat pemasukan | Akses buku kas yang sama, tidak perlu lapor manual |

## 5. User Flows

**Flow A — Catat transaksi (FinancePage, perilaku baseline saat ini):**
1. User buka `/finance` → form default: tipe "Pengeluaran" (`expense`), tanggal hari ini
   (`now()->toDateString()`).
2. User pilih tipe (Pengeluaran/Pemasukan) → kategori yang muncul di dropdown otomatis
   **difilter ulang** sesuai tipe (`updatedType()` reset `categoryId`, `render()` query
   `Category::where('type', $this->type)`).
3. User pilih kategori (opsional — nullable), isi nominal (wajib, numeric, min 0.01), catatan
   (opsional), tanggal (wajib, default hari ini). Untuk tipe Pengeluaran kategori **wajib**
   dipilih — lihat Flow D yang menggantikan langkah ini.
4. Submit → `Transaction::create()` langsung tersimpan, form ter-reset (nominal/catatan/kategori
   kosong lagi, tanggal balik ke hari ini) — **tidak ada halaman konfirmasi/redirect**, tetap di
   halaman yang sama.

**Flow B — Lihat ringkasan & riwayat (lihat §6.10, §6.11):**
1. Tiga kartu ringkasan di atas form: Pemasukan, Pengeluaran, Saldo — **scope periode yang
   sedang difilter** (default: periode berjalan berdasarkan tanggal reset household, §6.11),
   dijumlahkan dari `transactions` periode itu (§6.10).
2. User bisa **ganti filter periode** (dropdown/navigasi periode lain, mis. "Juli 2026") untuk
   lihat ringkasan & riwayat periode yang berbeda.
3. Daftar transaksi **difilter ke periode yang sama** yang sedang dipilih (bukan lagi 20 transaksi
   lintas-waktu tanpa filter) — kalau mau lihat bulan lain, ganti filter periode, bukan scroll.

**Flow C — Kelola & top-up kantong existing (lihat §6.5, §6.7):**
1. User buka halaman "Kelola Kantong" → daftar kategori expense household (default + custom yang
   belum diarsipkan) ditampilkan, tiap kategori punya input nominal untuk periode berjalan.
2. User isi/ubah nominal per kategori → tersimpan sebagai `budget_allocations` untuk periode
   berjalan (`household_id`, `category_id`, `period_start`).
3. User pilih kantong tertentu → "Top up" → isi nominal tambahan → tersimpan sebagai baris baru
   di `budget_topups` (**insert-only**, bukan update nominal awal) — supaya riwayat kapan &
   berapa kali top-up terjadi tetap utuh untuk analisis pola nanti (§6.7).
4. Total budget kantong periode ini = alokasi awal + jumlah semua top-up periode ini.

**Flow D — Catat pengeluaran terpetakan ke kantong (mengubah Flow A, lihat §6.6):**
1. User pilih tipe "Pengeluaran" → kategori **wajib dipilih** (beda dari baseline yang opsional)
   — kategori inilah kantong yang budgetnya dipakai.
2. Submit → transaksi tersimpan seperti biasa (§6.2); sisa kantong periode itu otomatis ikut
   berkurang karena dijumlahkan ulang dari `transactions` saat ditampilkan (§6.6, §6.10).

**Flow E — Bikin kantong/kategori custom baru (lihat §6.8):**
1. User pilih "Tambah Kantong" → isi nama & icon kategori baru (tipe otomatis `expense`, karena
   kantong custom cuma berlaku untuk expense, §3) **dan** nominal budget awal untuk periode
   berjalan **dalam satu form yang sama** — tidak bisa submit tanpa mengisi nominal budget.
2. Submit → membuat baris `Category` (`is_default = false`) **dan** `budget_allocations` untuk
   periode berjalan sekaligus, dalam satu transaksi DB.
3. Kantong baru langsung muncul di Flow C & dropdown pencatatan transaksi (Flow D).

**Flow F — Arsipkan kategori/kantong custom (lihat §6.8):**
1. User pilih kategori custom (bukan salah satu dari 7 default) → "Arsipkan".
2. Kategori itu hilang dari dropdown pencatatan transaksi & Flow C ke depannya, tapi transaksi &
   riwayat kantong lama yang sudah menempel di kategori itu **tetap utuh** dan tetap muncul di
   laporan/riwayat historis (§6.8).
3. 7 kategori default **tidak punya opsi ini** — cuma "Edit nama/icon" yang tersedia untuk mereka.

**Flow G — Edit/hapus transaksi (lihat §6.9):**
1. User buka transaksi dari riwayat (Flow B) → "Ubah" atau "Hapus" (form input yang sama, terisi
   nilai lama).
2. Edit: ubah nominal/kategori/tanggal/catatan → submit → cukup baris transaksinya yang berubah;
   ringkasan & sisa kantong ikut benar dengan sendirinya karena keduanya dijumlahkan ulang saat
   ditampilkan (§6.10).
3. Hapus: transaksi dihapus permanen — juga tanpa penyesuaian nilai turunan.

### 6.1 Kategori transaksi (seed otomatis per household)
- 7 kategori default di-seed sekali saat household dibuat (`DefaultCategories::DEFAULTS`,
  dipanggil dari `Household::createWithOwner()`): 2 income (Gaji, Lainnya), 5 expense (Belanja
  Harian, Tagihan, Pendidikan, Kesehatan, Lainnya).
- Household-scoped via `BelongsToHousehold` — kategori satu household tidak terlihat household
  lain (diverifikasi test, lihat §9).
- Kategori punya `type` (income/expense — menentukan di form mana dia muncul), `name`, `icon`
  (emoji string, nullable).
- Kategori hasil seed ditandai `is_default = true` — dikunci dari arsip/hapus, cuma bisa diedit
  `name`/`icon` (lihat §6.8).

### 6.2 Catat transaksi
- Validasi: `type` wajib in income/expense, `categoryId` nullable tapi harus exists kalau diisi,
  `amount` wajib numeric min 0.01, `description` opsional max 255, `occurredOn` wajib tanggal valid.
- Transaksi **tidak wajib** punya kategori (`category_id` nullable) — muncul sebagai "Tanpa
  kategori" di tampilan riwayat.
  - **Sejak 2026-08-26 (mengubah perilaku baseline):** Untuk `type = expense`, `categoryId`
    **wajib** (bukan nullable lagi) — supaya pengeluaran selalu terpetakan ke kantong mana budgetnya
    dipakai (§6.6). Untuk `type = income`, kategori **tetap opsional** seperti sekarang (kantong
    tidak berlaku untuk income, lihat §3).
- `user_id` selalu tercatat = user yang input (bukan household secara umum) — siapa yang mencatat
  transaksi ini, berguna untuk akuntabilitas dalam keluarga.
- `household_id` diambil dari `auth()->user()->current_household_id` saat submit, bukan dari
  household context Livewire manapun.

### 6.3 Ringkasan periode terpilih
- Pemasukan/pengeluaran/saldo dijumlahkan dari `transactions` dengan filter tanggal **periode yang
  sedang dipilih** (§6.11), bukan bulan kalender berjalan yang terkunci seperti di baseline.
- Dihitung saat render (`sum('amount')` lewat scope `Transaction::income()`/`expense()`) — lihat
  §6.10 untuk kenapa ini tidak jadi diganti tabel snapshot.

### 6.4 Riwayat transaksi
- Seluruh transaksi **periode yang sedang dipilih**, terbaru duluan. Hard-cap 20-lintas-waktu di
  baseline dihapus: scope-nya sekarang sama persis dengan ringkasan §6.3, jadi tidak ada lagi dua
  angka dengan rentang waktu berbeda di satu halaman.
- Tidak ada pagination — satu periode satu keluarga tidak sampai ke jumlah yang membutuhkannya.

### 6.5 Alokasi budget kantong per bulan
- Kantong = kategori expense yang diberi nominal alokasi untuk periode (bulan) tertentu — bukan
  entity baru yang terpisah dari `Category`, cuma lapisan budget di atasnya.
- Satu baris `budget_allocations` per (household, kategori, periode) — periode diwakili kolom
  `period_start` (tanggal eksak mulai periode, bukan string bulan-kalender) supaya kompatibel
  dengan tanggal reset custom per household (lihat §6.11, §7).
- Nominal alokasi **di-set manual oleh user di awal periode** — tidak ada auto-budgeting/recurring
  terjadwal ala Jago (di luar cakupan, lihat §12).
- Kategori expense yang **belum** punya `budget_allocations` untuk periode berjalan dianggap
  "belum dianggarkan" — bukan error, transaksi tetap bisa dicatat ke kategori itu (§6.6), cuma
  tidak ada angka pembanding budget yang ditampilkan.
- **Tidak ada carry-over** — alokasi periode baru selalu mulai dari nominal yang di-set user untuk
  periode itu, tidak otomatis mewarisi sisa/kurang dari periode sebelumnya (§3, konfirmasi di
  §14).

### 6.6 Pengeluaran wajib terpetakan ke kantong (mengubah §6.2)
- Transaksi `type = expense` **wajib** punya `category_id` (perubahan dari nullable jadi
  required) — supaya setiap pengeluaran jelas mengurangi budget kantong yang mana.
- Sisa budget kantong untuk periode berjalan dihitung **on-the-fly** saat ditampilkan (bukan
  kolom saldo yang di-update tiap transaksi):
  `(budget_allocations.amount + Σ budget_topups periode ini) − Σ transactions.amount (expense,
  kategori itu, periode ini)`.
- Kalau hasilnya negatif (pengeluaran melebihi total budget+top-up) → **tetap tersimpan**, sisa
  budget ditampilkan minus/merah di UI (§3 — keputusan sadar, bukan blocking).

### 6.7 Top-up kantong di tengah periode
- Top-up dicatat sebagai baris **insert-only** di `budget_topups` — pola yang sama seperti
  `ai_usage_events` (`billing.md`) dan `shopping_list_history` (`shopping-list.md`): tidak pernah
  di-update/dihapus, cuma ditambah.
- Tiap baris mencatat: kantong mana (`budget_allocation_id`), nominal top-up, siapa (`user_id`),
  dan kapan (`occurred_at`/`created_at`).
- **Ini fondasi data untuk analisis pola top-up lintas semester/tahun** (mis. "kantong Belanja
  Harian rata-rata di-top-up 2x/bulan, biasanya sekitar tanggal 20") — tapi **UI/laporan
  analisisnya sendiri belum di-scope di PRD ini** (lihat §12, ini murni menyiapkan data mentahnya).
- Top-up tidak mengubah nilai `budget_allocations.amount` yang asli (nominal awal tetap sebagai
  catatan "rencana awal") — total budget efektif dihitung dengan menjumlahkan alokasi awal + semua
  top-up (§6.6), bukan meng-overwrite satu kolom nominal.

### 6.8 Kategori/kantong custom & arsip
- Kategori dibedakan `is_default` (7 seed, `true`) vs custom (buatan household, `false`).
- **7 kategori default tidak bisa diarsipkan/dihapus** — cuma field `name`/`icon` yang bisa diedit.
  Ini menjaga baseline kategori tetap ada untuk semua household, tidak mungkin "kosong total".
- **Kategori custom bebas diarsipkan** (`archived_at` diisi timestamp, bukan hard-delete) — begitu
  diarsip, hilang dari dropdown pencatatan transaksi & Kelola Kantong (Flow C), tapi baris
  `Category`-nya tetap ada di DB beserta seluruh transaksi & `budget_allocations`/`budget_topups`
  historisnya. Bisa di-"unarchive" kapan saja.
- **Pola baru di codebase:** ini pertama kalinya ada konsep archive/soft-hide di aplikasi ini
  (tidak ada `SoftDeletes` di mana pun sebelumnya) — pakai kolom `archived_at` sendiri, bukan
  Eloquent `SoftDeletes`/`deleted_at` bawaan, supaya tidak tertukar makna dengan penghapusan
  permanen.
- **Membuat kategori custom expense (kantong) mewajibkan set budget awal di form yang sama**
  (Flow F) — tidak ada state "kantong ada tapi belum pernah dianggarkan sama sekali" untuk kantong
  custom. (Kategori default yang di-seed otomatis saat household dibuat **tidak** melalui alur
  ini, jadi tetap bisa dalam status "belum dianggarkan" untuk periode tertentu sampai user
  mengisinya lewat Flow C — beda dari kantong custom yang dipaksa punya budget sejak lahir.)
- Membuat kategori custom **income** tidak mewajibkan apa pun tambahan (kantong tidak berlaku
  untuk income, §3) — cukup nama & icon seperti kategori biasa.

### 6.9 Edit & hapus transaksi
- User bisa mengedit field transaksi (`category_id`, `amount`, `description`, `occurred_on`,
  `type`) atau menghapusnya secara permanen — validasi yang sama seperti input baru (§6.2) tetap
  berlaku untuk edit.
- **Tidak ada efek samping yang perlu dijaga.** Rencana 2026-07-30 mewajibkan edit/hapus
  menyesuaikan nilai tersimpan (`monthly_finance_summaries`, `spent_amount`) lewat model event;
  karena nilai-nilai itu tidak jadi disimpan (§6.10), edit/hapus di sini cuma `update()`/`delete()`
  biasa. Pindah kantong atau pindah periode saat edit pun tidak butuh penanganan khusus.
- Otorisasi: `Transaction::findOrFail()` lewat global scope `BelongsToHousehold` — id milik
  household lain berakhir 404, bukan baris orang lain yang ikut terubah (ada testnya).

### 6.10 Ringkasan & sisa budget dihitung saat ditampilkan (dibatalkan: snapshot tersimpan)

**Rencana 2026-07-30 dibatalkan sebelum dibangun (keputusan 2026-08-26).** Yang dibatalkan: tabel
`monthly_finance_summaries`, kolom `budget_allocations.spent_amount` &
`budget_allocations.topup_total_amount`, dan seluruh model event `Transaction` yang memeliharanya.

Alasannya:
- Beban query yang mau dihindari tidak ada. Satu keluarga menghasilkan puluhan transaksi per
  periode, dan `transactions` sudah punya index `(household_id, occurred_on)` — dua `SUM()` per
  render, bukan "jutaan baris historis".
- Risikonya nyata dan spesifik ke codebase ini. `BelongsToHousehold` memfilter dari
  `Auth::user()?->current_household_id` dan **gagal diam-diam** (mengembalikan kosong) di jalur
  tanpa sesi — persis yang sudah menggigit `BillSchedule::paidPeriods()` di modul Tagihan.
  Memelihara nilai turunan lintas tabel di dalam model event menaruh pola kegagalan yang sama di
  jalur tulis: begitu satu titik tulis meleset, angka ringkasan drift dari transaksi aslinya tanpa
  ada yang menyadari. §8 versi sebelumnya sudah menandai risiko ini sebagai kewajiban yang harus
  dijaga manual — menghapus sumbernya lebih murah daripada menjaganya.
- Konsekuensi ke fase lain: edit/hapus transaksi (§6.9) jadi CRUD biasa, dan modul Tagihan yang
  menulis `Transaction` sendiri (§14, 2026-08-19) tidak butuh penanganan khusus apa pun.

Yang dipakai sekarang:
- Ringkasan periode (§6.3): dua `SUM()` ke `transactions` dengan filter rentang periode.
- Sisa kantong (§6.6): `App\Modules\Finance\Services\Pockets::forPeriod()` — satu query alokasi
  (`withSum('topups')`) + satu query agregat pengeluaran per kategori, digabung di PHP. Satu tempat
  ini dipakai bersama oleh halaman Keuangan (sisa kantong di dropdown) dan halaman Kantong.
- Kalau suatu saat ini benar-benar terasa lambat, snapshot bisa ditambahkan belakangan tanpa
  mengubah UI — pemanggilnya sudah terpusat di `Pockets`.

### 6.11 Filter periode & tanggal reset custom per household (mengubah §6.3/§6.4)
- `households` dapat kolom baru `budget_period_reset_day` (integer 1–28, default `1`) — menentukan
  tanggal berapa tiap bulan periode kantong/ringkasan "mulai ulang". Default tanggal 1 (ikut
  kalender bulan biasa); household bisa ubah, mis. tanggal 25 kalau gajian di situ.
  - Dibatasi 1–28 (bukan sampai 31) supaya konsisten di semua bulan termasuk Februari — hindari
    ambiguitas "tanggal 30 di bulan yang cuma py 28/29 hari".
- Perhitungannya terpusat di `App\Modules\Finance\Services\FinancePeriod`
  (`startFor`/`endOf`/`label`/`recent`), dipakai oleh kedua halaman — logic tanggal tidak tercecer.
- Konsep "periode" **tidak lagi diwakili string bulan-kalender** (mis. `"2026-08"`), tapi
  `period_start` (tanggal eksak mulai periode itu, dihitung dari `budget_period_reset_day`) — baik
  di `budget_allocations`, `budget_topups`, maupun `monthly_finance_summaries` (§7).
- Ringkasan (§6.3/§6.10) & riwayat (§6.4) selalu mengacu ke **satu periode aktif** yang sedang
  difilter user — default periode yang sedang berjalan (dihitung dari tanggal hari ini &
  `budget_period_reset_day`), bisa diganti user untuk lihat periode lain (Flow B).
- **Label periode di UI — hybrid:** tampilan utama pakai **nama bulan yang scannable** (mis.
  "Agustus 2026" untuk periode yang mayoritas harinya jatuh di Agustus), dengan **rentang tanggal
  eksak sebagai keterangan kecil di bawahnya** ("25 Jul – 24 Ags"). Pola ini konsisten dengan
  siklus tagihan aplikasi billing pada umumnya — label besar untuk sekilas-lihat, detail presisi
  untuk yang butuh cek eksak. Bulan yang dipakai untuk nama besar = bulan tempat **mayoritas hari**
  periode itu jatuh (bukan selalu bulan mulai) — mis. periode 25 Jul–24 Ags punya 7 hari di Juli
  dan 24 hari di Agustus, jadi dilabeli "Agustus 2026".
- **Efek ubah tanggal reset di tengah jalan — non-retroaktif:** periode yang **sedang aktif**
  tetap berjalan sampai tanggal akhir yang sudah ditentukan berdasarkan `budget_period_reset_day`
  **lama** — tidak dipotong paksa atau ditutup instan begitu setting diubah. Reset day yang baru
  cuma dipakai untuk menghitung batas periode **berikutnya**, yang mulai secara alami setelah
  periode aktif ini selesai. Tidak ada migrasi data atau penyesuaian retroaktif ke periode yang
  sudah tercatat (`budget_allocations` lama tidak disentuh). Konsisten dengan pola "tidak
  backdate" yang sudah dipakai di `billing.md` (siklus tagihan restart dari tanggal bayar aktual,
  bukan di-backdate).
- **Supaya "tidak disentuh" benar-benar berarti "masih bisa dibuka":** dropdown periode bukan cuma
  12 periode terakhir menurut tanggal reset yang berlaku sekarang, tapi digabung dengan setiap
  `period_start` yang sudah punya alokasi (`Pockets::selectablePeriods()`). Tanpa itu, alokasi
  dengan irama lama tetap ada di DB tapi tidak ada opsi UI yang bisa menampilkannya lagi.
- Nilai `periodStart` di halaman Kantong datang dari `wire:model.live`, jadi selalu dinormalkan
  ulang lewat `FinancePeriod::startFor()` sebelum dipakai membaca **atau menulis** — supaya tidak
  ada baris `budget_allocations` yang lahir di tanggal batas periode yang tidak sah.

## 7. Data Model

| Tabel | Kolom kunci | Catatan scoping |
|---|---|---|
| `households` | (existing) + `budget_period_reset_day` (unsignedTinyInteger 1–28, default 1) | — |
| `categories` | household_id, type (income\|expense), name, icon (nullable), `is_default` (bool, default false), `archived_at` (nullable timestamp) | Household-scoped via `BelongsToHousehold` |
| `transactions` | household_id, category_id (wajib untuk expense di level validasi, tetap nullable di DB untuk income), user_id, type, amount (decimal 12,2), description (nullable), occurred_on | Household-scoped via `BelongsToHousehold`; index (household_id, occurred_on) |
| `budget_allocations` | household_id, category_id (FK), period_start (date), amount (decimal 12,2 — nominal awal) | Household-scoped. Unique per (category_id, period_start). |
| `budget_topups` | household_id, budget_allocation_id (FK), user_id, amount (decimal 12,2), occurred_at | Insert-only log, household-scoped. Pola sama dengan `bill_payments`. |

> Kolom `category_id` di `transactions` **berubah dari nullable jadi conditionally required**
> (wajib untuk `type=expense`) — penegakannya di level aplikasi (`FinancePage::save()`), bukan
> constraint `NOT NULL` di DB, karena income tetap boleh nullable. Jalur tulis lain
> (`Bills\Services\RecordBillPayment`) tidak lewat validasi itu, jadi fallback kategorinya
> diperkuat supaya tidak pernah menghasilkan pengeluaran tanpa kantong.
>
> `period_start` (date) dipakai konsisten di semua tabel yang sebelumnya dibayangkan pakai string
> bulan-kalender (`"2026-08"`), supaya kompatibel dengan periode custom (§6.11) yang tidak selalu
> align ke tanggal 1.
>
> `archived_at` (kategori) sengaja bukan `deleted_at`/`SoftDeletes` bawaan Eloquent — supaya tidak
> tertukar makna dengan hard-delete (lihat §6.8).
>
> **Dibatalkan (§6.10):** tabel `monthly_finance_summaries` dan kolom snapshot
> `budget_allocations.spent_amount` / `topup_total_amount` tidak jadi dibuat.

## 8. Non-Functional Requirements

- **Tenancy/keamanan:** Scoping household diverifikasi lewat test otomatis
  (`HouseholdIsolationTest::'never leaks transactions or categories across households'`) — sama
  seperti ShoppingList, modul ini py jaminan regresi minimal untuk isolasi data (beda dari Cooking
  yang belum py test sama sekali).
- **Presisi nominal:** `amount` disimpan `decimal:2` (bukan float) — tepat untuk uang, tidak ada
  masalah floating-point rounding.
- **Sederhana secara sengaja:** Tidak ada AI, tidak ada background job, tidak ada dependency
  eksternal — level kesederhanaan sama dengan ShoppingList baseline.
- **Konsistensi periode ringkasan vs riwayat — selesai:** §6.3 (ringkasan) dan §6.4 (riwayat)
  tadinya punya scope waktu berbeda. §6.11 menyatukan keduanya ke **satu periode filter yang
  sama** — bukan lagi dua scope berbeda yang membingungkan.
- **Integritas angka turunan:** tidak ada nilai turunan yang disimpan, jadi tidak ada yang bisa
  drift (§6.10). Ringkasan & sisa kantong selalu konsekuensi langsung dari baris `transactions`
  yang ada saat itu — termasuk untuk transaksi yang ditulis modul lain (Tagihan).
- **Pola baru — archiving:** `archived_at` di kategori (§6.8) adalah **konsep soft-hide pertama**
  di codebase ini — perlu diterapkan konsisten (semua query list kategori aktif harus filter
  `whereNull('archived_at')`) supaya tidak ada tempat yang "lupa" filter dan menampilkan kategori
  yang seharusnya sudah diarsipkan.

## 9. Metrics / KPI (usulan — belum diinstrumentasi di kode)

> Belum ada tracking apa pun untuk ini di kode saat ini.

- **Recording frequency:** rata-rata jumlah transaksi dicatat per household per minggu — sinyal
  seberapa aktif modul ini dipakai relatif ke Cooking/ShoppingList.
- **Uncategorized rate:** % transaksi yang dicatat tanpa kategori (`category_id` null) — sinyal
  apakah kategori default sudah cukup relevan atau user sering skip karena tidak ada yang cocok.
- **Income vs expense ratio tercatat:** perbandingan jumlah transaksi income vs expense — indikasi
  household lebih sering pakai modul ini untuk apa (kemungkinan besar dominan expense/belanja).
- **🔜 Kantong yang belum dianggarkan:** % kategori expense yang belum punya `budget_allocations`
  untuk periode berjalan — sinyal seberapa besar adopsi fitur kantong secara riil.
- **🔜 Over-budget rate:** % kantong yang berakhir minus di akhir periode — sinyal apakah nominal
  alokasi yang di-set user realistis, atau kantong sistemik selalu kurang.
- **🔜 Top-up frequency:** rata-rata jumlah & nominal top-up per kantong per periode — data mentah
  yang nanti jadi bahan analisis pola semester/tahun (§6.7, §12).
- **🔜 Custom category adoption:** % household yang bikin minimal satu kategori/kantong custom
  (§6.8) — sinyal apakah 7 kategori default dirasa cukup atau tidak.
- **🔜 Edit/delete rate:** % transaksi yang pernah diedit/dihapus setelah dicatat (§6.9) — sinyal
  seberapa sering kesalahan input terjadi (kalau tinggi, mungkin form input perlu diperbaiki).
- **🔜 Custom reset day adoption:** % household yang mengubah `budget_period_reset_day` dari
  default tanggal 1 (§6.11) — sinyal seberapa relevan fitur ini secara riil.

## 10. Edge Cases & Error Handling (sudah ditangani/belum ditangani di kode)

- Nominal negatif/nol/bukan angka → ditolak validasi (`numeric|min:0.01`), pesan error inline di
  bawah field amount.
- Kategori dipilih dari tipe yang salah (mis. pilih kategori expense lalu ganti tipe ke income) →
  `updatedType()` langsung reset `categoryId` ke null, jadi tidak mungkin submit dengan kombinasi
  tipe-kategori yang salah lewat UI normal.
- Kategori expense dipilih tapi belum punya `budget_allocations` untuk periode
  berjalan → transaksi tetap tersimpan normal (§6.5), tampilan kantong untuk kategori itu
  menunjukkan "belum dianggarkan" alih-alih sisa budget — bukan error. **Kecuali** untuk kategori
  custom yang baru dibuat (§6.8), yang memang dipaksa selalu punya alokasi sejak awal.
- Total pengeluaran kantong melebihi alokasi+top-up periode ini → transaksi tetap
  tersimpan (§6.6), sisa budget ditampilkan negatif — keputusan sadar (§3), bukan validasi yang
  gagal.
- User set alokasi budget dua kali untuk kategori & periode yang sama → constraint unique
  (`category_id`, `period_start`) di §7 mencegah baris dobel, dan UI-nya memang `updateOrCreate`.
- User coba arsipkan salah satu dari 7 kategori default → tombol arsipnya memang tidak dirender,
  tapi penolakannya ada di `KantongPage::archive()` (`abort_unless($category->canBeArchived())`),
  bukan cuma di blade — ada testnya (§6.8).
- Transaksi lama menunjuk ke kategori yang sudah diarsipkan → tetap tampil normal
  di riwayat/laporan historis (data tidak hilang, §6.8) — kategori itu cuma tidak lagi muncul di
  dropdown untuk transaksi **baru**.
- Edit transaksi mengubah kategori dan/atau tanggal ke periode lain → tidak butuh penanganan
  apa pun; kantong/periode lama & baru sama-sama dijumlahkan ulang saat ditampilkan (§6.9, §6.10).
- Menyimpan transaksi bertanggal di luar periode yang sedang difilter → filter periode ikut pindah
  ke periode transaksi itu, supaya yang baru disimpan tidak terlihat "hilang" dari riwayat.
- Alokasi dikirim untuk `category_id` milik household lain (array `amounts` datang dari klien) →
  diabaikan diam-diam; hanya id yang ada di daftar kategori expense aktif household ini yang
  diproses.

## 11. Dependencies & Integrations

- Tidak ada dependency eksternal (AI, payment, dst) saat ini — modul paling mandiri bersama
  ShoppingList baseline.
- **🔜 Planned — ShoppingList module (`shopping-list.md` §6.7):** ShoppingList akan **memanggil**
  Finance untuk membuat `Transaction` baru ("Catat pengeluaran belanja") — dependency searah
  (ShoppingList → Finance), Finance sendiri tidak perlu tahu soal ShoppingList. Kolom
  `Transaction::description` (sudah ada) dipakai untuk ringkasan otomatis dari sesi belanja, tidak
  perlu migration baru di Finance untuk integrasi ini.
- **🔜 Terkait — Billing (`billing.md` §2.1):** rencana **analisis kebiasaan lintas modul** butuh
  data transaksi historis sebagai salah satu sumber (pola pengeluaran/pemasukan keluarga) — data
  ini sudah ada sekarang (`transactions` table), tinggal fitur analisisnya yang belum dibangun.
- **🔜 Terkait — Admin CMS (`admin-cms.md` §6.5):** super admin akan py akses CRUD penuh ke
  `Transaction`/`Category` lintas household lewat panel `/super-admin` — ditandai sebagai data
  paling sensitif di PRD itu.

## 12. Gap & Next Steps

- ~~Belum ada test untuk `FinancePage`.~~ Sudah ada: `tests/Feature/Finance/FinancePageTest.php`
  (validasi kantong wajib, filter periode, edit/hapus, isolasi household),
  `tests/Feature/Finance/KantongTest.php` (alokasi, top-up, kategori custom/arsip, sisa kantong
  setelah create→edit→delete), `tests/Unit/FinancePeriodTest.php` (edge case tanggal periode).
- **Belum ada di UI:** riwayat top-up per kantong tidak ditampilkan di mana pun — barisnya tercatat
  (`budget_topups`) tapi user cuma melihat totalnya menyatu di angka budget. Ini disengaja: yang
  dibutuhkan sekarang cuma datanya, tampilannya menyusul bersama analisis pola (di bawah).
- **Belum ada di UI:** ubin Finance/Kantong di Beranda — `/finance` masih diakses lewat URL
  langsung, sama seperti sebelum fitur ini.
- **Di luar cakupan PRD ini:** **UI/laporan analisis pola top-up lintas semester/tahun** (mis.
  grafik tren, insight otomatis) — §6.7 cuma menyiapkan data mentahnya, analisis & visualisasinya
  sendiri **belum di-scope**, kemungkinan jadi PRD/fase terpisah nanti (juga berpotensi terkait
  rencana **analisis kebiasaan lintas modul** di `billing.md` §2.1).
- **Di luar cakupan PRD ini:** **Auto-budgeting terjadwal** (top-up otomatis berkala ala Jago) —
  fitur ini tidak diminta, top-up tetap manual per kejadian untuk MVP kantong.

## 13. Open Questions

Tidak ada open question tersisa. Implementasi 2026-08-26 juga tidak memunculkan yang baru; satu
keputusan rencana dibatalkan (§6.10) dan alasannya sudah dicatat, bukan digantung.

Seluruh pertanyaan dari draf-draf sebelumnya (arah
budgeting, kategori custom, edit/hapus transaksi, laporan periode, inkonsistensi scope waktu,
enforcement alokasi kantong, definisi periode, label periode custom, efek ubah reset day) sudah
diputuskan. Lihat §14 untuk riwayat keputusannya. Open question baru akan ditambahkan di sini kalau
muncul saat implementasi atau setelah fitur ini jalan di production.

## 14. Riwayat Perubahan

| Tanggal | Perubahan |
|---|---|
| 2026-07-30 | Draf baseline awal, reverse-engineer dari kode existing (`FinancePage`, `Category`, `Transaction`, `DefaultCategories`, migrations, `HouseholdIsolationTest`). |
| 2026-07-30 | Ditambahkan rencana fitur **Kantong** ala Bank Jago (§1, §3, §5 Flow C/D/E, §6.5–§6.7, §7, §9, §10, §12): kategori expense bisa diberi alokasi budget per bulan, pengeluaran wajib terpetakan ke kantong (kategori jadi wajib untuk expense), top-up tengah bulan dicatat insert-only sebagai fondasi analisis pola lintas semester/tahun (analisis/UI-nya sendiri di luar cakupan PRD ini). Keputusan desain: **reset tiap bulan** (bukan saldo persisten ala rekening Jago beneran), **boleh minus** kalau over-budget (bukan blocking), **cuma untuk expense** (bukan income). Open Question lama #1 (arah budgeting) sudah terjawab lewat keputusan ini; 2 Open Question baru ditambahkan soal enforcement alokasi & definisi periode (§13). |
| 2026-07-30 | Resolusi 6 Open Question tersisa, sekaligus eksplorasi opsi kategori custom (3 opsi dipresentasikan, **Opsi 3 dipilih**): (1) kategori custom **boleh dibuat**, tapi 7 default **terkunci** (tidak bisa diarsipkan/dihapus, cuma edit nama/icon) — kategori custom bebas diarsipkan (`archived_at`, bukan hard-delete atau `SoftDeletes` bawaan) — §6.8, §7; (2) **edit/hapus transaksi ditambahkan**, dengan efek samping wajib menyesuaikan nilai tersimpan (§6.9); (3) laporan/analisis periode disimpan sebagai **data tersimpan** (`monthly_finance_summaries`, kolom snapshot di `budget_allocations`), dipelihara di write-path bukan dihitung ulang saat tampil (§6.10); (4) ringkasan & riwayat **disatukan** ke satu scope periode filter yang sama, bukan dua scope berbeda (§6.3, §6.4, §6.10, §6.11); (5) membuat kantong custom **memaksa** set budget awal di form yang sama (§6.8) — beda dari kategori default yang tetap boleh "belum dianggarkan" sampai diisi manual; (6) tanggal reset periode **bisa di-custom per household** (`budget_period_reset_day`, default tanggal 1) — §6.11. Ditambahkan 2 Open Question baru soal detail teknis: label periode custom di UI, dan efek ubah tanggal reset di tengah jalan (§13). |
| 2026-07-30 | Resolusi 2 Open Question terakhir soal detail teknis periode custom (§6.11): (1) label periode di UI pakai **hybrid** — nama bulan besar/scannable (mayoritas hari periode itu) + rentang tanggal eksak sebagai keterangan kecil; (2) ubah `budget_period_reset_day` di tengah jalan **non-retroaktif** — periode aktif tetap jalan sampai batas lama, reset day baru cuma berlaku mulai periode berikutnya, konsisten dengan pola "tidak backdate" di `billing.md`. Tidak ada Open Question tersisa untuk `finance.md` saat ini (§13). |
| 2026-07-30 | Ditambahkan §15 Rencana Implementasi (fase kerja + todo list). |
| 2026-08-26 | **Fitur Kantong (§6.5–§6.11) dibangun** — Fase 1–9 di §15 selesai: migration `budget_allocations` & `budget_topups` + kolom `households.budget_period_reset_day`, `categories.is_default`/`archived_at`; service `FinancePeriod` (periode custom + label hybrid) & `Pockets` (kondisi kantong per periode); halaman `/finance/kantong` (alokasi, top-up, kategori custom, arsip, setting tanggal reset); `FinancePage` dapat filter periode, kategori wajib untuk expense, dan edit/hapus transaksi. **Satu keputusan diubah dari rencana 2026-07-30:** ringkasan & sisa kantong **dihitung saat ditampilkan**, bukan disimpan di `monthly_finance_summaries` + kolom snapshot yang dipelihara model event (§6.10) — beban query-nya tidak nyata untuk skala satu keluarga, sementara risiko drift-nya nyata dan sudah pernah menggigit di jalur `Auth::user()`-dependent (`BillSchedule::paidPeriods()`). Efek: Fase 6 batal, Fase 5 & 7 menyusut jadi CRUD biasa tanpa model event. Ditambahkan juga: `RecordBillPayment` tidak lagi bisa menghasilkan pengeluaran tanpa kategori (§7), dan `FinancePage` menolak kategori yang tipenya tidak cocok dengan tipe transaksinya (§10). |
| 2026-08-19 | **Tagihan dipisah jadi modul sendiri** (`docs/prd/tagihan.md`), menutup keputusan terbuka di `ROADMAP.md` soal ubin Tagihan vs Finance. Tidak ada perubahan pada desain kantong (§6.5–§6.11) — modul Tagihan cuma **menulis** `Transaction` expense saat tagihan ditandai lunas (dependency searah Bills → Finance, bentuk yang sama dengan rencana ShoppingList → Finance di §11), memakai kategori `bills.category_id` atau jatuh ke kategori default "Tagihan". Begitu §6.10 (snapshot via model event `Transaction::created`) dibangun, transaksi dari modul Tagihan otomatis ikut tertangkap — tidak perlu penanganan khusus. |

## 15. Rencana Implementasi

> **Status 2026-08-26: seluruh fase di bawah sudah dikerjakan**, kecuali Fase 6 yang dibatalkan
> (§6.10) — Fase 5 & 7 ikut menyusut karena tidak ada lagi nilai tersimpan yang perlu dipelihara.
> Rencana asli dibiarkan apa adanya sebagai catatan; yang berlaku sekarang adalah §6.

> Urutan berdasarkan dependency: data model dulu, lalu service kalkulasi periode (dipakai oleh
> hampir semua fase berikutnya), lalu fitur yang bergantung padanya. Fase 5–7 saling terkait erat
> (semuanya menyentuh siklus hidup `Transaction`) — direkomendasikan dikerjakan berurutan tanpa
> jeda oleh orang/PR yang sama, supaya model event tidak terpecah jadi beberapa implementasi
> yang tidak sinkron.

**Fase 1 — Data model** *(estimasi: 2–3 jam)*
Fondasi untuk semua fase lain.
- Migration: `households` +`budget_period_reset_day` (integer 1–28, default 1).
- Migration: `categories` +`is_default` (bool, default false) +`archived_at` (nullable timestamp).
- Migration: backfill `is_default = true` untuk 7 kategori seed yang sudah ada di household
  existing (data migration, bukan cuma schema).
- Migration: `budget_allocations` (household_id, category_id, period_start, amount, spent_amount
  default 0, topup_total_amount default 0; unique category_id+period_start).
- Migration: `budget_topups` (household_id, budget_allocation_id, amount, user_id, occurred_at).
- Migration: `monthly_finance_summaries` (household_id, period_start, total_income default 0,
  total_expense default 0; unique household_id+period_start).

**Fase 2 — Service kalkulasi periode** *(estimasi: 2–3 jam)*
Dipakai oleh Fase 3 dst — dibangun sekali, dipakai di banyak tempat supaya logic tanggal tidak
tercecer.
- Service (mis. `FinancePeriod`) dengan method `currentPeriodStart(Household $household): Carbon`
  dan `periodStartFor(Household $household, Carbon $date): Carbon`, dihitung dari
  `budget_period_reset_day` (§6.11).
- Method label hybrid: nama bulan mayoritas + rentang tanggal eksak (§6.11) — satu tempat, dipakai
  konsisten di semua UI yang menampilkan label periode.
- Pest test unit untuk service ini duluan (banyak edge case: reset day > tanggal hari ini bulan
  ini vs bulan lalu, bulan pendek Februari, dst) — lebih murah dites di level unit sebelum dipakai
  di banyak fitur.

**Fase 3 — Kategori: default lock, edit, custom, arsip** *(estimasi: 3–4 jam)*
Bergantung ke Fase 1.
- Update `DefaultCategories::seedFor()`: set `is_default = true` untuk 7 kategori seed.
- UI: edit nama/icon kategori (berlaku untuk default maupun custom).
- UI: "Tambah Kantong" (Flow E) — form gabungan nama+icon+nominal budget awal, `is_default =
  false`, submit membuat `Category` + `budget_allocations` periode berjalan dalam satu transaksi
  DB. Kategori income custom tidak perlu field nominal.
- UI: "Arsipkan" (Flow F) — set `archived_at`, cuma muncul untuk `is_default = false`. Semua query
  list kategori aktif (dropdown transaksi, Kelola Kantong) difilter `whereNull('archived_at')`.

**Fase 4 — Kelola Kantong: alokasi & top-up** *(estimasi: 3–4 jam)*
Bergantung ke Fase 1–3.
- Halaman "Kelola Kantong" (Flow C): list kategori expense aktif (default + custom belum
  diarsipkan), input nominal per kategori untuk periode berjalan (`updateOrCreate` by
  category_id+period_start, §10).
- UI top-up per kantong → insert baris `budget_topups` (insert-only, tidak update nominal awal).
- Tampilan sisa budget kantong: baca langsung dari `budget_allocations` (amount +
  topup_total_amount − spent_amount) — kolom-kolom ini baru benar-benar terisi setelah Fase 5/6.

**Fase 5 — Pengeluaran wajib kantong + snapshot spent_amount** *(estimasi: 3–4 jam)*
Bergantung ke Fase 4.
- `FinancePage::save()`: validasi `categoryId` **required** kalau `type = expense` (tetap nullable
  untuk income).
- Model event `Transaction::created`: kalau expense, increment `budget_allocations.spent_amount`
  kategori+periode terkait (buat baris `budget_allocations` kosong/`amount=0` dulu kalau belum ada
  — supaya tetap tercatat "belum dianggarkan" tapi spent_amount tetap akurat, §6.5/§10).

**Fase 6 — Ringkasan bulanan tersimpan (monthly_finance_summaries)** *(estimasi: 2–3 jam)*
Bisa paralel dengan Fase 5 — sama-sama nempel di model event `Transaction`, tapi target tabel beda.
- Model event `Transaction::created`: increment `monthly_finance_summaries.total_income` atau
  `total_expense` (household+periode transaksi itu), `updateOrCreate` kalau baris periode belum
  ada.
- Flow B (ringkasan): baca langsung dari `monthly_finance_summaries`, bukan query agregat
  `Transaction` seperti sekarang.

**Fase 7 — Edit & hapus transaksi** *(estimasi: 3–4 jam)*
Bergantung ke Fase 5 & 6 (butuh hook model event sudah ada, tinggal diperluas ke update/delete).
- UI edit/hapus di Flow B (riwayat transaksi).
- Model event `Transaction::updating`: hitung delta (nilai lama vs baru) untuk
  `monthly_finance_summaries` **dan** `budget_allocations.spent_amount` — kalau kategori atau
  periode berubah, sesuaikan **dua** baris (lama dikurangi, baru ditambah), bukan cuma satu.
- Model event `Transaction::deleting`: kurangi nilai yang sama tanpa ada penambahan baru.

**Fase 8 — Filter periode & pengaturan reset day** *(estimasi: 2–3 jam)*
Bergantung ke Fase 2 (service kalkulasi periode) & Fase 6 (data untuk ditampilkan sudah ada).
- UI Flow B: selector/navigasi ganti periode (default periode berjalan), pakai label hybrid dari
  Fase 2.
- Riwayat transaksi difilter ke `period_start` yang sama dengan yang dipilih (bukan hard-cap 20
  lintas-waktu seperti sekarang).
- UI pengaturan household: ubah `budget_period_reset_day` — efeknya non-retroaktif (§6.11), tidak
  perlu logic migrasi data apa pun saat disimpan.

**Fase 9 — Tes minimal** *(estimasi: 2–3 jam)*
- Pest: kategori default tidak bisa diarsipkan (guard di level aplikasi).
- Pest: bikin kantong custom tanpa nominal budget ditolak validasi (Flow E).
- Pest: transaksi expense tanpa kategori ditolak; income tanpa kategori tetap diterima.
- Pest: `spent_amount`/`monthly_finance_summaries` konsisten setelah create → edit (pindah
  kategori/periode) → delete, dibandingkan dengan hitung ulang manual dari tabel `transactions`.
- Pest: `FinancePeriod` service — reset day custom menghasilkan `period_start` yang benar untuk
  berbagai tanggal hari ini (termasuk edge case akhir bulan/Februari).

## ✅ Todo List

- [x] [db] Migration: `households` +budget_period_reset_day
- [x] [db] Migration: `categories` +is_default +archived_at, backfill is_default=true untuk seed existing
- [x] [db] Migration: `budget_allocations` (household_id, category_id, period_start, amount, spent_amount, topup_total_amount)
- [x] [db] Migration: `budget_topups` (household_id, budget_allocation_id, amount, user_id, occurred_at)
- [~] [db] Migration: `monthly_finance_summaries` — **dibatalkan** (§6.10)
- [x] [backend] Service `FinancePeriod`: currentPeriodStart/periodStartFor dari budget_period_reset_day
- [x] [backend] Service `FinancePeriod`: label hybrid (nama bulan mayoritas + rentang tanggal)
- [x] [test] Pest unit: `FinancePeriod` edge case (reset day, bulan pendek)
- [x] [backend] Update `DefaultCategories::seedFor()` set is_default=true
- [x] [frontend] UI edit nama/icon kategori (default & custom)
- [x] [frontend] [backend] Flow E: form "Tambah Kantong" (kategori + budget awal dalam satu transaksi DB)
- [x] [frontend] [backend] Flow F: "Arsipkan" kategori custom (archived_at), filter whereNull di semua query list aktif
- [x] [frontend] [backend] Halaman "Kelola Kantong": set/ubah alokasi per kategori per periode (updateOrCreate)
- [x] [frontend] [backend] UI top-up kantong (insert budget_topups, insert-only)
- [x] [backend] Validasi `categoryId` required untuk type=expense di `FinancePage::save()`
- [~] [backend] Model event Transaction::created (spent_amount) — **dibatalkan** (§6.10)
- [~] [backend] Model event Transaction::created (monthly_finance_summaries) — **dibatalkan** (§6.10)
- [~] [frontend] Flow B: baca ringkasan dari monthly_finance_summaries — **dibatalkan**, tetap query agregat (§6.10)
- [x] [frontend] [backend] UI edit/hapus transaksi
- [~] [backend] Model event Transaction::updating — **dibatalkan** (§6.10)
- [~] [backend] Model event Transaction::deleting — **dibatalkan** (§6.10)
- [x] [frontend] UI selector/navigasi filter periode di Flow B, riwayat difilter period_start yang sama
- [x] [frontend] [backend] UI pengaturan household: ubah budget_period_reset_day
- [x] [test] Pest: kategori default tidak bisa diarsipkan
- [x] [test] Pest: Flow E ditolak tanpa nominal budget
- [x] [test] Pest: validasi kategori required untuk expense, opsional untuk income
- [x] [test] Pest: sisa kantong konsisten setelah create → edit (pindah kantong/periode) → delete
