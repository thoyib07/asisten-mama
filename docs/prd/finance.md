# PRD: Modul Finance (Keuangan Keluarga)

> **Status dokumen:** Baseline — ditulis dengan cara reverse-engineer dari kode yang sudah ada
> (sama seperti `cooking.md`/`shopping-list.md`), bukan spec yang mendahului implementasi.
> Merekam kontrak perilaku saat ini sebagai titik referensi sebelum modul ini diubah lebih lanjut.

## 1. Ringkasan

Modul Finance adalah pencatat pemasukan/pengeluaran sederhana **per household** — satu buku kas
bersama yang bisa diisi oleh semua anggota keluarga, dengan kategori transaksi yang di-seed
otomatis saat household dibuat. Baseline-nya paling minimal di antara ketiga modul MVP: cuma catat
transaksi + ringkasan bulan berjalan, tanpa budgeting sama sekali.

Rencana pengembangan (**🔜 Planned**, lihat §6.5–§6.11): fitur **Kantong** ala Bank Jago — kategori
expense bisa diberi **alokasi nominal per periode** ("kantong"), pengeluaran wajib terpetakan ke
kantong mana budgetnya diambil, dan kantong bisa **di-top-up di tengah periode** dengan setiap
top-up tercatat sebagai event historis untuk analisis pola pemakaian lintas semester/tahun nanti.
Ditambah: household bisa **bikin kategori/kantong sendiri** (7 default tetap terkunci), **edit/hapus
transaksi**, ringkasan bulanan **disimpan permanen** (bukan dihitung ulang tiap render), riwayat
transaksi **difilter per periode** (bukan lintas-waktu tanpa filter), dan household bisa **atur
tanggal reset periode sendiri** (default tanggal 1, bisa disesuaikan mis. ikut tanggal gajian).

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
- **🔜 Planned:** Household bisa **set nominal budget per kantong (kategori expense) di awal
  periode** — lihat §6.5.
- **🔜 Planned:** Pengeluaran **wajib terpetakan ke kantong** mana budgetnya diambil — kategori
  jadi wajib untuk transaksi expense (lihat §6.2, §6.6).
- **🔜 Planned:** Kantong bisa **di-top-up di tengah periode**, setiap top-up tercatat sebagai
  event historis (siapa, kapan, berapa) — fondasi data untuk analisis pola top-up lintas
  semester/tahun nanti (lihat §6.7).
- **🔜 Planned:** Household bisa **bikin kategori/kantong custom sendiri** — 7 kategori default
  tetap ada & terkunci (tidak bisa diarsipkan/dihapus, cuma bisa diedit nama/icon), kategori
  tambahan buatan user bebas diarsipkan kapan saja (lihat §6.8).
- **🔜 Planned:** Membuat kantong custom baru **mewajibkan** langsung set nominal budget awal
  dalam satu alur yang sama — tidak ada kantong yang "menggantung" tanpa budget begitu selesai
  dibuat (lihat §6.8).
- **🔜 Planned:** Household bisa **edit/hapus transaksi** yang sudah dicatat, untuk koreksi
  kesalahan input (lihat §6.9).
- **🔜 Planned:** Ringkasan bulanan (income/expense/saldo per kategori/kantong) **disimpan
  permanen** per periode, bukan dihitung ulang tiap kali halaman dibuka — lihat §6.10.
- **🔜 Planned:** Riwayat transaksi **difilter per satu periode** (bukan menampilkan lintas-waktu
  tanpa filter) — user ganti filter periode untuk lihat bulan lain, lihat §6.11.
- **🔜 Planned:** Household bisa **atur tanggal reset periode sendiri** (default tanggal 1) — mis.
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
   (opsional), tanggal (wajib, default hari ini). **🔜 Planned:** untuk tipe Pengeluaran, kategori
   jadi wajib dipilih — lihat Flow D yang menggantikan langkah ini.
4. Submit → `Transaction::create()` langsung tersimpan, form ter-reset (nominal/catatan/kategori
   kosong lagi, tanggal balik ke hari ini) — **tidak ada halaman konfirmasi/redirect**, tetap di
   halaman yang sama.

**Flow B — Lihat ringkasan & riwayat (🔜 diubah — filter periode, lihat §6.10, §6.11):**
1. Tiga kartu ringkasan di atas form: Pemasukan, Pengeluaran, Saldo — **scope periode yang
   sedang difilter** (default: periode berjalan berdasarkan tanggal reset household, §6.11),
   dibaca langsung dari `monthly_finance_summaries` yang sudah tersimpan (§6.10), **bukan**
   dihitung ulang tiap render seperti sebelumnya.
2. User bisa **ganti filter periode** (dropdown/navigasi periode lain, mis. "Juli 2026") untuk
   lihat ringkasan & riwayat periode yang berbeda.
3. Daftar transaksi **difilter ke periode yang sama** yang sedang dipilih (bukan lagi 20 transaksi
   lintas-waktu tanpa filter) — kalau mau lihat bulan lain, ganti filter periode, bukan scroll.

**Flow C — Kelola & top-up kantong existing (🔜 Planned, lihat §6.5, §6.7):**
1. User buka halaman "Kelola Kantong" → daftar kategori expense household (default + custom yang
   belum diarsipkan) ditampilkan, tiap kategori punya input nominal untuk periode berjalan.
2. User isi/ubah nominal per kategori → tersimpan sebagai `budget_allocations` untuk periode
   berjalan (`household_id`, `category_id`, `period_start`).
3. User pilih kantong tertentu → "Top up" → isi nominal tambahan → tersimpan sebagai baris baru
   di `budget_topups` (**insert-only**, bukan update nominal awal) — supaya riwayat kapan &
   berapa kali top-up terjadi tetap utuh untuk analisis pola nanti (§6.7).
4. Total budget kantong periode ini = alokasi awal + jumlah semua top-up periode ini.

**Flow D — Catat pengeluaran terpetakan ke kantong (🔜 Planned, mengubah Flow A, lihat §6.6):**
1. User pilih tipe "Pengeluaran" → kategori **sekarang wajib dipilih** (beda dari sebelumnya yang
   opsional) — kategori inilah kantong yang budgetnya dipakai.
2. Submit → transaksi tersimpan seperti biasa (§6.2), dan otomatis ikut mengurangi sisa
   tampilan budget kantong tsb untuk periode berjalan — dihitung dari kolom snapshot
   (`spent_amount` di `budget_allocations`, di-update di titik yang sama transaksi disimpan,
   §6.10), bukan query agregat setiap kali ditampilkan.

**Flow E — Bikin kantong/kategori custom baru (🔜 Planned, lihat §6.8):**
1. User pilih "Tambah Kantong" → isi nama & icon kategori baru (tipe otomatis `expense`, karena
   kantong custom cuma berlaku untuk expense, §3) **dan** nominal budget awal untuk periode
   berjalan **dalam satu form yang sama** — tidak bisa submit tanpa mengisi nominal budget.
2. Submit → membuat baris `Category` (`is_default = false`) **dan** `budget_allocations` untuk
   periode berjalan sekaligus, dalam satu transaksi DB.
3. Kantong baru langsung muncul di Flow C & dropdown pencatatan transaksi (Flow D).

**Flow F — Arsipkan kategori/kantong custom (🔜 Planned, lihat §6.8):**
1. User pilih kategori custom (bukan salah satu dari 7 default) → "Arsipkan".
2. Kategori itu hilang dari dropdown pencatatan transaksi & Flow C ke depannya, tapi transaksi &
   riwayat kantong lama yang sudah menempel di kategori itu **tetap utuh** dan tetap muncul di
   laporan/riwayat historis (§6.8).
3. 7 kategori default **tidak punya opsi ini** — cuma "Edit nama/icon" yang tersedia untuk mereka.

**Flow G — Edit/hapus transaksi (🔜 Planned, lihat §6.9):**
1. User buka transaksi dari riwayat (Flow B) → "Edit" atau "Hapus".
2. Edit: ubah nominal/kategori/tanggal/catatan → submit → `monthly_finance_summaries` dan
   `budget_allocations.spent_amount` terkait **disesuaikan** (nilai lama dikurangi, nilai baru
   ditambahkan) — bukan cuma transaksinya yang berubah (§6.9, §6.10).
3. Hapus: transaksi dihapus permanen → nilai yang sama disesuaikan (dikurangi saja, tidak ada
   nilai baru yang ditambahkan).

### 6.1 Kategori transaksi (seed otomatis per household)
- 7 kategori default di-seed sekali saat household dibuat (`DefaultCategories::DEFAULTS`,
  dipanggil dari `Household::createWithOwner()`): 2 income (Gaji, Lainnya), 5 expense (Belanja
  Harian, Tagihan, Pendidikan, Kesehatan, Lainnya).
- Household-scoped via `BelongsToHousehold` — kategori satu household tidak terlihat household
  lain (diverifikasi test, lihat §9).
- Kategori punya `type` (income/expense — menentukan di form mana dia muncul), `name`, `icon`
  (emoji string, nullable).
- **🔜 Planned:** kategori hasil seed ini ditandai `is_default = true` — dikunci dari
  arsip/hapus, cuma bisa diedit `name`/`icon` (lihat §6.8).

### 6.2 Catat transaksi
- Validasi: `type` wajib in income/expense, `categoryId` nullable tapi harus exists kalau diisi,
  `amount` wajib numeric min 0.01, `description` opsional max 255, `occurredOn` wajib tanggal valid.
- Transaksi **tidak wajib** punya kategori (`category_id` nullable) — muncul sebagai "Tanpa
  kategori" di tampilan riwayat.
  - **🔜 Planned (mengubah perilaku lama):** Untuk `type = expense`, `categoryId` **jadi wajib**
    (bukan nullable lagi) — supaya pengeluaran selalu terpetakan ke kantong mana budgetnya
    dipakai (§6.6). Untuk `type = income`, kategori **tetap opsional** seperti sekarang (kantong
    tidak berlaku untuk income, lihat §3).
- `user_id` selalu tercatat = user yang input (bukan household secara umum) — siapa yang mencatat
  transaksi ini, berguna untuk akuntabilitas dalam keluarga.
- `household_id` diambil dari `auth()->user()->current_household_id` saat submit, bukan dari
  household context Livewire manapun.

### 6.3 Ringkasan periode berjalan (🔜 akan diubah — lihat §6.10, §6.11)
- **Perilaku saat ini:** Pemasukan/pengeluaran/saldo dihitung ulang **setiap render** (bukan
  cached/precomputed) — query langsung ke `Transaction::income()`/`expense()` scope dengan filter
  tanggal bulan kalender berjalan. Tidak ada pemilihan bulan lain.
- **🔜 Planned:** diganti total oleh §6.10 (baca dari tabel tersimpan) & §6.11 (periode custom +
  filter) — subsection ini akan digantikan, bukan ditambah di atasnya.

### 6.4 Riwayat transaksi (🔜 akan diubah — lihat §6.11)
- **Perilaku saat ini:** 20 transaksi terakhir lintas semua waktu (bukan cuma bulan ini) — berbeda
  scope dari §6.3, bisa membingungkan user kalau tidak disadari. Tidak ada pagination/load-more —
  hard-capped di 20.
- **🔜 Planned:** diganti oleh §6.11 (filter per periode, bukan hard-cap lintas-waktu).

### 6.5 Alokasi budget kantong per bulan (🔜 Planned — belum ada di kode)
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

### 6.6 Pengeluaran wajib terpetakan ke kantong (🔜 Planned — mengubah §6.2)
- Transaksi `type = expense` **wajib** punya `category_id` (perubahan dari nullable jadi
  required) — supaya setiap pengeluaran jelas mengurangi budget kantong yang mana.
- Sisa budget kantong untuk periode berjalan dihitung **on-the-fly** saat ditampilkan (bukan
  kolom saldo yang di-update tiap transaksi):
  `(budget_allocations.amount + Σ budget_topups periode ini) − Σ transactions.amount (expense,
  kategori itu, periode ini)`.
- Kalau hasilnya negatif (pengeluaran melebihi total budget+top-up) → **tetap tersimpan**, sisa
  budget ditampilkan minus/merah di UI (§3 — keputusan sadar, bukan blocking).

### 6.7 Top-up kantong di tengah periode (🔜 Planned — belum ada di kode)
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

### 6.8 Kategori/kantong custom & arsip (🔜 Planned — belum ada di kode)
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

### 6.9 Edit & hapus transaksi (🔜 Planned — belum ada di kode)
- User bisa mengedit field transaksi (`category_id`, `amount`, `description`, `occurred_on`,
  `type`) atau menghapusnya secara permanen — validasi yang sama seperti input baru (§6.2) tetap
  berlaku untuk edit.
- **Efek samping wajib:** karena ringkasan & sisa budget kantong sekarang berupa nilai tersimpan
  (§6.10), bukan dihitung ulang tiap saat, edit/hapus transaksi **harus** menyesuaikan nilai
  tersimpan yang terkait:
  - `monthly_finance_summaries` periode transaksi itu (total income/expense) — nilai lama
    dikurangi, nilai baru (kalau edit) ditambahkan.
  - `budget_allocations.spent_amount` kategori & periode itu (kalau `type = expense`) — sama:
    kurangi nilai lama, tambah nilai baru.
  - Kalau kategori/tanggal transaksi **berubah** saat edit (pindah kantong atau pindah periode),
    penyesuaian dilakukan ke **dua** baris berbeda: kurangi dari kantong/periode lama, tambah ke
    kantong/periode baru.
- Rekomendasi implementasi: dipusatkan lewat Eloquent model events (`updating`/`deleting` di
  `Transaction`) supaya logic penyesuaian ini tidak tercecer di banyak tempat pemanggil.

### 6.10 Ringkasan & sisa budget tersimpan, bukan dihitung ulang (🔜 Planned — mengubah §6.3, §6.6)
- Tabel baru `monthly_finance_summaries` (household, periode) menyimpan total income & expense
  periode itu — **dipelihara lewat model event** setiap transaksi dibuat/diedit/dihapus (§6.9),
  bukan dihitung dari query agregat setiap halaman dibuka.
- Kolom `budget_allocations.spent_amount` & `budget_allocations.topup_total_amount` (§7) juga
  dipelihara dengan cara yang sama — increment saat transaksi/top-up baru, disesuaikan saat
  transaksi diedit/dihapus (§6.9).
- Sisa budget kantong = `amount + topup_total_amount − spent_amount`, semuanya kolom yang sudah
  ada di baris `budget_allocations` — tampilan cukup baca satu baris, tidak perlu agregasi ulang.
- Manfaat langsung: laporan/grafik nanti (di luar cakupan PRD ini, §12) tinggal baca angka yang
  sudah jadi, tidak perlu hitung ulang dari jutaan baris transaksi historis.

### 6.11 Filter periode & tanggal reset custom per household (🔜 Planned — mengubah §6.3/§6.4)
- `households` dapat kolom baru `budget_period_reset_day` (integer 1–28, default `1`) — menentukan
  tanggal berapa tiap bulan periode kantong/ringkasan "mulai ulang". Default tanggal 1 (ikut
  kalender bulan biasa); household bisa ubah, mis. tanggal 25 kalau gajian di situ.
  - Dibatasi 1–28 (bukan sampai 31) supaya konsisten di semua bulan termasuk Februari — hindari
    ambiguitas "tanggal 30 di bulan yang cuma py 28/29 hari".
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
  sudah tercatat (`budget_allocations`/`monthly_finance_summaries` lama tidak disentuh). Konsisten
  dengan pola "tidak backdate" yang sudah dipakai di `billing.md` (siklus tagihan restart dari
  tanggal bayar aktual, bukan di-backdate).

## 7. Data Model

| Tabel | Kolom kunci | Catatan scoping |
|---|---|---|
| `households` | (existing) + 🔜 `budget_period_reset_day` (integer 1–28, default 1) | — |
| `categories` | household_id, type (income\|expense), name, icon (nullable), 🔜 `is_default` (bool, default false), 🔜 `archived_at` (nullable timestamp) | Household-scoped via `BelongsToHousehold` |
| `transactions` | household_id, category_id (🔜 wajib untuk expense, tetap nullable untuk income), user_id, type, amount (decimal 12,2), description (nullable), occurred_on | Household-scoped via `BelongsToHousehold`; index (household_id, occurred_on) |
| `budget_allocations` (🔜 baru) | household_id, category_id (FK, harus type=expense), period_start (date), amount (decimal 12,2 — nominal awal), 🔜 `spent_amount` (decimal 12,2, default 0 — snapshot terpelihara), 🔜 `topup_total_amount` (decimal 12,2, default 0 — snapshot terpelihara) | Household-scoped. Unique per (category_id, period_start). |
| `budget_topups` (🔜 baru) | household_id, budget_allocation_id (FK), amount (decimal 12,2), user_id, occurred_at, created_at | Insert-only log, household-scoped. Pola sama dengan `ai_usage_events`/`shopping_list_history`. |
| `monthly_finance_summaries` (🔜 baru) | household_id, period_start (date), total_income (decimal 12,2, default 0), total_expense (decimal 12,2, default 0) | Household-scoped. Unique per (household_id, period_start). Dipelihara via model event, bukan query agregat (§6.10). |

> 🔜 **Planned:** `budget_allocations`, `budget_topups`, `monthly_finance_summaries` belum ada di
> migration saat ini; `categories` & `households` butuh kolom tambahan. Kolom `category_id` di
> `transactions` **berubah dari nullable jadi conditionally required** (wajib untuk `type=expense`)
> — perubahan validasi di level aplikasi (`FinancePage::save()`), bukan constraint `NOT NULL` di
> DB (karena income tetap boleh nullable). `period_start` (date) dipakai konsisten di semua tabel
> yang sebelumnya dibayangkan pakai string bulan-kalender (`"2026-08"`), supaya kompatibel dengan
> periode custom (§6.11) yang tidak selalu align ke tanggal 1.
> `archived_at` (kategori) sengaja bukan `deleted_at`/`SoftDeletes` bawaan Eloquent — supaya tidak
> tertukar makna dengan hard-delete (lihat §6.8).

## 8. Non-Functional Requirements

- **Tenancy/keamanan:** Scoping household diverifikasi lewat test otomatis
  (`HouseholdIsolationTest::'never leaks transactions or categories across households'`) — sama
  seperti ShoppingList, modul ini py jaminan regresi minimal untuk isolasi data (beda dari Cooking
  yang belum py test sama sekali).
- **Presisi nominal:** `amount` disimpan `decimal:2` (bukan float) — tepat untuk uang, tidak ada
  masalah floating-point rounding.
- **Sederhana secara sengaja:** Tidak ada AI, tidak ada background job, tidak ada dependency
  eksternal — level kesederhanaan sama dengan ShoppingList baseline.
- **Konsistensi periode ringkasan vs riwayat — 🔜 diselesaikan:** §6.3 (ringkasan) dan §6.4
  (riwayat) tadinya punya scope waktu berbeda. §6.10/§6.11 menyatukan keduanya ke **satu periode
  filter yang sama** — bukan lagi dua scope berbeda yang membingungkan.
- **🔜 Planned — integritas data tersimpan:** Karena §6.10 memindahkan ringkasan/sisa-budget dari
  "dihitung saat tampil" jadi "nilai tersimpan yang di-maintain di write-path", **setiap** titik
  penulisan (`Transaction` create/update/delete, `budget_topups` create) **wajib** menjaga nilai
  turunan (`monthly_finance_summaries`, `budget_allocations.spent_amount`/`topup_total_amount`)
  tetap sinkron — kalau ada titik tulis yang lupa di-handle, data ringkasan bisa drift dari data
  transaksi asli tanpa ada yang menyadari (tidak ada validasi silang otomatis). Direkomendasikan
  dipusatkan lewat Eloquent model events (§6.9), bukan logic tersebar di berbagai caller.
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
- **🔜 Planned:** Kategori expense dipilih tapi belum punya `budget_allocations` untuk periode
  berjalan → transaksi tetap tersimpan normal (§6.5), tampilan kantong untuk kategori itu
  menunjukkan "belum dianggarkan" alih-alih sisa budget — bukan error. **Kecuali** untuk kategori
  custom yang baru dibuat (§6.8), yang memang dipaksa selalu punya alokasi sejak awal.
- **🔜 Planned:** Total pengeluaran kantong melebihi alokasi+top-up periode ini → transaksi tetap
  tersimpan (§6.6), sisa budget ditampilkan negatif — keputusan sadar (§3), bukan validasi yang
  gagal.
- **🔜 Planned:** User set alokasi budget dua kali untuk kategori & periode yang sama → constraint
  unique (`category_id`, `period_start`) di §7 mencegah baris dobel; UI-nya perlu **update** baris
  yang sudah ada, bukan `create` baru (idealnya `updateOrCreate`).
- **🔜 Planned:** User coba arsipkan salah satu dari 7 kategori default → ditolak di level
  aplikasi (tombol arsip tidak muncul/disabled untuk `is_default = true`), cuma opsi edit
  nama/icon yang tersedia (§6.8).
- **🔜 Planned:** Transaksi lama menunjuk ke kategori yang sudah diarsipkan → tetap tampil normal
  di riwayat/laporan historis (data tidak hilang, §6.8) — kategori itu cuma tidak lagi muncul di
  dropdown untuk transaksi **baru**.
- **🔜 Planned:** Edit transaksi mengubah kategori dan/atau tanggal ke periode lain → nilai
  tersimpan (`monthly_finance_summaries`, `budget_allocations.spent_amount`) disesuaikan di
  **kedua** baris (lama dikurangi, baru ditambah) — lihat §6.9.

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

- Belum ada test untuk `FinancePage` (form/validasi/render) — test yang ada
  (`HouseholdIsolationTest`) cuma verifikasi isolasi tenancy, bukan behavior pencatatan transaksi.
  Makin krusial begitu §6.9/§6.10 (edit/delete + snapshot terpelihara) masuk — regresi di sini
  bisa bikin angka ringkasan diam-diam salah tanpa ketahuan.
- **🔜 Baru disepakati:** fitur Kantong (§6.5–§6.11) belum ada di data model sama sekali — perlu
  migration `budget_allocations`, `budget_topups`, `monthly_finance_summaries`, kolom tambahan di
  `categories` & `households`, perubahan validasi `category_id` jadi conditionally required, dan
  UI baru untuk kelola kantong, top-up, kategori custom, edit/hapus transaksi, filter periode.
- **🔜 Baru disepakati — di luar cakupan PRD ini:** **UI/laporan analisis pola top-up lintas
  semester/tahun** (mis. grafik tren, insight otomatis) — §6.7/§6.10 cuma menyiapkan data
  mentah/snapshot-nya, analisis & visualisasinya sendiri **belum di-scope**, kemungkinan jadi
  PRD/fase terpisah nanti (juga berpotensi terkait rencana **analisis kebiasaan lintas modul** di
  `billing.md` §2.1).
- **🔜 Baru disepakati — di luar cakupan PRD ini:** **Auto-budgeting terjadwal** (top-up otomatis
  berkala ala Jago) — fitur ini tidak diminta, top-up tetap manual per kejadian untuk MVP kantong.

## 13. Open Questions

Tidak ada open question tersisa saat ini — seluruh pertanyaan dari draf-draf sebelumnya (arah
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
| 2026-08-19 | **Tagihan dipisah jadi modul sendiri** (`docs/prd/tagihan.md`), menutup keputusan terbuka di `ROADMAP.md` soal ubin Tagihan vs Finance. Tidak ada perubahan pada desain kantong (§6.5–§6.11) — modul Tagihan cuma **menulis** `Transaction` expense saat tagihan ditandai lunas (dependency searah Bills → Finance, bentuk yang sama dengan rencana ShoppingList → Finance di §11), memakai kategori `bills.category_id` atau jatuh ke kategori default "Tagihan". Begitu §6.10 (snapshot via model event `Transaction::created`) dibangun, transaksi dari modul Tagihan otomatis ikut tertangkap — tidak perlu penanganan khusus. |

## 15. Rencana Implementasi

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

- [ ] [db] Migration: `households` +budget_period_reset_day
- [ ] [db] Migration: `categories` +is_default +archived_at, backfill is_default=true untuk seed existing
- [ ] [db] Migration: `budget_allocations` (household_id, category_id, period_start, amount, spent_amount, topup_total_amount)
- [ ] [db] Migration: `budget_topups` (household_id, budget_allocation_id, amount, user_id, occurred_at)
- [ ] [db] Migration: `monthly_finance_summaries` (household_id, period_start, total_income, total_expense)
- [ ] [backend] Service `FinancePeriod`: currentPeriodStart/periodStartFor dari budget_period_reset_day
- [ ] [backend] Service `FinancePeriod`: label hybrid (nama bulan mayoritas + rentang tanggal)
- [ ] [test] Pest unit: `FinancePeriod` edge case (reset day, bulan pendek)
- [ ] [backend] Update `DefaultCategories::seedFor()` set is_default=true
- [ ] [frontend] UI edit nama/icon kategori (default & custom)
- [ ] [frontend] [backend] Flow E: form "Tambah Kantong" (kategori + budget awal dalam satu transaksi DB)
- [ ] [frontend] [backend] Flow F: "Arsipkan" kategori custom (archived_at), filter whereNull di semua query list aktif
- [ ] [frontend] [backend] Halaman "Kelola Kantong": set/ubah alokasi per kategori per periode (updateOrCreate)
- [ ] [frontend] [backend] UI top-up kantong (insert budget_topups, insert-only)
- [ ] [backend] Validasi `categoryId` required untuk type=expense di `FinancePage::save()`
- [ ] [backend] Model event Transaction::created — increment budget_allocations.spent_amount (expense)
- [ ] [backend] Model event Transaction::created — increment monthly_finance_summaries (income/expense)
- [ ] [frontend] Flow B: baca ringkasan dari monthly_finance_summaries, bukan query agregat
- [ ] [frontend] [backend] UI edit/hapus transaksi
- [ ] [backend] Model event Transaction::updating — delta adjustment (dua baris kalau kategori/periode berubah)
- [ ] [backend] Model event Transaction::deleting — pengurangan nilai tersimpan
- [ ] [frontend] UI selector/navigasi filter periode di Flow B, riwayat difilter period_start yang sama
- [ ] [frontend] [backend] UI pengaturan household: ubah budget_period_reset_day
- [ ] [test] Pest: kategori default tidak bisa diarsipkan
- [ ] [test] Pest: Flow E ditolak tanpa nominal budget
- [ ] [test] Pest: validasi kategori required untuk expense, opsional untuk income
- [ ] [test] Pest: konsistensi spent_amount/monthly_finance_summaries setelah create/edit/delete
