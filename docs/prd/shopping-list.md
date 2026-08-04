# PRD: Modul Shopping List

> **Status dokumen:** Baseline — ditulis dengan cara reverse-engineer dari kode yang sudah ada
> (sama seperti `cooking.md`), bukan spec yang mendahului implementasi. Merekam kontrak perilaku
> saat ini sebagai titik referensi sebelum modul ini diubah lebih lanjut.

## 1. Ringkasan

Modul Shopping List adalah daftar belanja bersama **per household** (bukan per user) — satu daftar
yang bisa dilihat dan diedit oleh semua anggota keluarga yang login ke household yang sama. Item
bisa ditambah manual, atau otomatis dari bahan yang "missing" saat pencarian resep di modul Cooking.
Rencana pengembangan (**🔜 Planned**, lihat §6.5–§6.7): item bisa **ditugaskan ke anggota tertentu**
untuk dibeli, ada opsi **beli online lewat marketplace partner** (belum ditentukan partnernya), dan
hasil belanja bisa **langsung tercatat sebagai transaksi di modul Finance**.

## 2. Latar Belakang & Masalah

**Rumusan masalah:** Anggota keluarga perlu satu daftar belanja yang sama-sama bisa diakses/diubah
oleh siapa pun di rumah (bukan daftar terpisah per orang yang harus disatukan manual), dan idealnya
otomatis terisi saat mereka baru saja cari resep dan sadar ada bahan yang belum dipunya.

Modul ini jauh lebih sederhana dibanding Cooking — tidak ada AI, tidak ada admin panel, cuma CRUD
item + satu titik integrasi masuk dari Cooking.

## 3. Goals & Non-Goals

**Goals:**
- Household punya **satu** daftar belanja bersama, otomatis dibuat saat pertama kali diakses
  (`firstOrCreate`) — tidak perlu setup manual.
- Anggota manapun di household bisa tambah, centang (selesai dibeli), dan hapus item.
- Bahan yang "missing" dari hasil pencarian resep di Cooking bisa didorong ke daftar ini dengan
  satu klik, tanpa duplikat kalau nama bahannya sama.
- **🔜 Planned:** Item bisa **ditugaskan ke anggota household tertentu** untuk dibeli (§6.5).
- **🔜 Planned:** Ada opsi **beli online** lewat marketplace partner, langsung dari item di daftar
  (§6.6).
- **🔜 Planned:** Hasil belanja bisa dicatat langsung jadi **transaksi di modul Finance** tanpa
  perlu input ulang manual di Finance (§6.7).
- **🔜 Planned:** Barang dengan nama yang sama **dikelompokkan jadi satu baris** dengan kuantitas
  bertambah, bukan baris duplikat — berlaku untuk tambah manual maupun otomatis (§6.2, §6.3).
- **🔜 Planned:** Ada **riwayat aktivitas per user** (siapa nambah/centang/hapus apa, kapan) — lihat
  §6.8.

**Non-Goals (sengaja tidak dibangun di baseline ini):**
- Multiple lists per household (mis. "belanja mingguan" vs "belanja bulanan" terpisah) — cuma satu
  list per household, hardcoded lewat `firstOrCreate(['household_id' => ...])`. **Diputuskan tetap
  begini untuk sekarang**, dievaluasi lagi nanti (bukan open question lagi, lihat §14).
- Kategori/pengelompokan item (mis. per lorong supermarket: sayuran, daging, dst) — item flat,
  tidak ada grouping **berdasarkan jenis barang**. (Catatan: ada bentuk "pengelompokan" lain yang
  memang direncanakan — barang dengan nama sama jadi satu baris dengan qty bertambah, lihat Goals
  di atas & §6.2/§6.3 — itu beda konsep dari grouping per kategori/lorong toko yang tetap Non-Goal.)
- Kuantitas dengan **satuan terstruktur** (mis. "2 kg", "1 lusin") — `quantity` **🔜 jadi dipakai**
  sebagai angka polos/jumlah (lihat Goals di atas & §7), tapi belum ada satuan terpisah.
- Admin/Filament resource — berbeda dari Cooking, modul ini tidak py CRUD admin karena datanya
  murni milik household (bukan katalog bersama), jadi tidak relevan dikelola dari panel admin.
- **Modul manajemen kulkas/inventory rumah tangga** — direncanakan sebagai **modul terpisah di masa
  depan** (belum ada PRD-nya sama sekali), dicatat di sini cuma sebagai titik integrasi masa depan
  (lihat §12) — **bukan** dibangun sebagai bagian dari PRD ini.
- Notifikasi ke anggota yang ditugaskan (§6.5) — aplikasi ini belum py sistem notifikasi sama
  sekali; assignment bersifat informasional (terlihat di UI), bukan push/email/WhatsApp.
- Perbandingan harga real-time antar marketplace (§6.6) — di luar cakupan, terlalu kompleks untuk
  tahap belum-ada-partner ini.

## 4. Target Users / Personas

| Persona | Deskripsi | Kebutuhan utama |
|---|---|---|
| **Ibu rumah tangga (primary)** | Yang paling sering belanja & update daftar | Tambah/centang item cepat, terisi otomatis dari resep |
| **Anggota keluarga lain** | Ikut menambahkan kalau sadar kehabisan sesuatu | Akses list yang sama, tidak perlu japri/japri manual |

## 5. User Flows

**Flow A — Kelola daftar belanja (ShoppingListPage):**
1. User buka `/shopping-list` → sistem `firstOrCreate` daftar milik household (kalau belum ada,
   dibuat otomatis dengan nama default "Belanja").
2. User ketik nama barang → submit → **🔜 Planned:** nama dinormalisasi (`IngredientNormalizer`,
   disamakan dengan Flow B — resolusi inkonsistensi lama, lihat §14) lalu dicek ke item yang sudah
   ada di list. Kalau sudah ada (nama ternormalisasi sama) → **quantity item itu bertambah 1**,
   bukan bikin baris baru. Kalau belum ada → baris baru dibuat dengan quantity = 1.
3. User centang checkbox → item ditandai `is_checked`, tampilan jadi strikethrough & pindah ke
   bawah (urutan render: belum-dicentang dulu, lalu terbaru duluan dalam masing-masing grup).
4. User klik "×" → item dihapus permanen dari list (riwayat aktivitasnya tetap tersimpan terpisah,
   lihat §6.8 — bukan berarti "tanpa jejak" lagi seperti sebelumnya).

**Flow B — Dorong bahan "missing" dari Cooking (`AddMissingIngredients`):**
1. Setelah pencarian resep di Cooking menghasilkan daftar bahan yang belum dipunya, user klik
   "+ Tambahkan bahan yang kurang ke daftar belanja".
2. `MissingIngredientsToShoppingList::add()` menormalisasi tiap nama bahan
   (`IngredientNormalizer`) — perilaku pengelompokan-nya sekarang **sama seperti Flow A**: kalau
   bahan sudah ada di list, quantity bertambah 1; kalau belum, baris baru dibuat quantity = 1.
3. Tombol berubah jadi centang hijau "Bahan yang kurang sudah ditambahkan" (state `$added`,
   tidak bisa diklik berulang dalam satu render siklus komponen).

**Flow C — Tugaskan item ke anggota lain (🔜 Planned, lihat §6.5):**
1. User pilih anggota household dari dropdown per item (saat tambah item baru, atau edit item yang
   sudah ada).
2. Item menampilkan badge "ditugaskan ke: [nama]" di UI.
3. Assignment bersifat informasional — anggota manapun tetap bisa centang/hapus item siapa pun,
   sama seperti perilaku sekarang (§6.4), assignment tidak mengubah permission.

**Flow D — Beli online via marketplace partner (🔜 Planned, konseptual — lihat §6.6):**
1. Item punya opsi tambahan "beli online" (mekanisme pastinya menunggu partner ditentukan — bisa
   deep-link sederhana atau integrasi API, lihat §13).
2. User diarahkan ke marketplace partner untuk item tsb.
3. Belum ditentukan apakah status "sudah dibeli" ter-update otomatis dari marketplace (butuh
   webhook/callback dari partner) atau user tetap manual centang sendiri di aplikasi.

**Flow E — Catat pengeluaran belanja ke Finance (🔜 Planned, lihat §6.7):**
1. Setelah selesai belanja (baik online maupun offline), user memicu aksi "Catat pengeluaran
   belanja" (aksi terpisah dari centang item — centang cuma menandai "sudah dibeli", bukan
   otomatis mencatat keuangan).
2. User input nominal total (dan pilih kategori Finance, default ke kategori "Belanja"/groceries
   household kalau ada) secara manual.
3. Sistem membuat satu `Transaction` baru di modul Finance dengan nominal & kategori tsb — tidak
   ada input harga per item satu-satu (lihat asumsi & alternatif di §13).

## 6. Functional Requirements

### 6.1 Daftar belanja per household
- Satu `ShoppingList` per household, dibuat otomatis (`firstOrCreate(['household_id' => ...])`),
  bukan dibuat eksplisit oleh user.
- Household-scoped lewat trait `BelongsToHousehold` — invisible ke user di luar household tsb,
  termasuk lewat relasi (diverifikasi test, lihat §9).

### 6.2 Tambah item manual
- Input nama bebas (`trim()`, tolak kalau kosong setelah trim).
- **🔜 Planned (mengubah perilaku lama):** nama **dinormalisasi** (`IngredientNormalizer`,
  disamakan dengan §6.3 — resolusi inkonsistensi lama). Dicek dulu ke item existing di list yang
  sama by nama ternormalisasi: kalau ketemu → **increment `quantity` item tsb sebesar 1**; kalau
  tidak → buat baris baru dengan `quantity = 1`. Status `is_checked` item yang ketemu **tidak
  diubah** oleh penambahan ini (menambah qty tidak otomatis meng-uncheck item yang sudah dicentang
  — asumsi, lihat §13).
- Item baru/increment tercatat `added_by` = user yang melakukan aksi — berguna untuk tahu siapa
  yang minta beli apa, dan jadi sumber untuk riwayat per user (§6.8).
- `ingredient_id` nullable — item manual tidak terhubung ke katalog `ingredients` milik Cooking.

### 6.3 Tambah item otomatis dari Cooking (missing ingredients)
- Input dinormalisasi (`IngredientNormalizer::normalize()`) sebelum disimpan — konsisten dengan
  representasi nama bahan di Cooking.
- **🔜 Planned (mengubah perilaku lama):** Perilaku "ketemu nama sama" **berubah dari dedup diam
  (`firstOrCreate`, tidak menambah apa pun) jadi increment quantity** — sama seperti §6.2. Kalau
  bahan yang sama sudah ada di daftar, `quantity`-nya bertambah 1, bukan cuma "dilewati".
- `added_by` dicatat sebagai user yang klik tombol "tambahkan", bukan otomatis sistem.

### 6.4 Centang & hapus item
- Toggle `is_checked` per item (tidak ada validasi tambahan, siapa pun anggota household bisa
  centang item siapa pun).
- Hapus permanen tanpa konfirmasi/undo di UI.
- Urutan tampilan: item belum-dicentang di atas, item tercentang di bawah; dalam tiap grup, item
  terbaru (`id` terbesar) di atas.

### 6.5 Assign tugas beli per item (🔜 Planned — belum ada di kode)
- Kolom baru `assigned_to` (nullable, FK ke `users`) di `shopping_list_items` (§7).
- Assignee dipilih dari **anggota household yang sama** (bukan sembarang user) — daftar pilihan
  diambil dari relasi household-user yang sudah ada (`household_user` pivot).
- Assignment **informasional saja** — tidak membatasi siapa yang boleh toggle/hapus item (§6.4
  tetap berlaku apa adanya untuk semua anggota, assigned atau tidak).
- Tidak ada notifikasi ke assignee (lihat Non-Goals §3) — assignee cuma tahu lewat badge di UI saat
  buka halaman shopping list.
- Item boleh tidak di-assign sama sekali (`assigned_to` null, default) — assignment opsional, bukan
  wajib per item.

### 6.6 Beli online via marketplace partner (🔜 Planned — konseptual, partner belum ditentukan)
- Belum ada partner API spesifik yang dituju — bagian ini sengaja masih konseptual, bukan spec
  teknis integrasi.
- Begitu partner ditentukan, dua pendekatan yang mungkin (perlu dipilih saat itu, lihat §13):
  deep-link sederhana (buka halaman pencarian produk di marketplace dengan nama item) vs integrasi
  API afiliasi/cart-injection yang lebih terintegrasi tapi butuh kerja sama teknis dengan partner.
- Tidak menjanjikan sinkronisasi status "sudah dibeli" otomatis dari marketplace kecuali partner
  API-nya mendukung webhook — kalau tidak, tetap manual centang di §6.4.

### 6.7 Catat pengeluaran belanja ke Finance (🔜 Planned — belum ada di kode)
- Aksi eksplisit terpisah dari centang item ("Catat pengeluaran belanja") — bukan otomatis tiap
  kali item di-centang, supaya tidak memaksa user input harga untuk tiap barang kecil satu-satu.
- User input **nominal total** (bukan harga per item — lihat asumsi §13) + pilih kategori Finance
  (default ke kategori groceries/belanja household kalau sudah ada dari seed default categories).
- Membuat satu row `Transaction` baru di modul Finance (`type = expense`) — ShoppingList
  **memanggil** service di Finance (mirip pola `MissingIngredientsToShoppingList` yang sudah ada:
  Cooking → ShoppingList searah), bukan sebaliknya. Ini jadi dependency cross-module **kedua** yang
  disengaja di codebase (setelah Cooking → ShoppingList), lihat §8 & §11.
- **Penamaan & link ke detail belanja:** `Transaction::description` (kolom yang **sudah ada** di
  Finance, tidak perlu migration baru di sana) diisi otomatis dengan ringkasan mis. "Belanja rumah
  tangga (5 barang) — 29 Jul 2026". Baris-baris `shopping_list_history` (§6.8) yang termasuk sesi
  belanja ini ditandai dengan `transaction_id` yang baru dibuat — supaya dari riwayat shopping list
  bisa ditelusuri balik "ini masuk transaksi Finance yang mana", tanpa perlu ubah skema Finance
  sama sekali (link-nya cuma satu arah, dari ShoppingList ke Finance).

### 6.8 Riwayat aktivitas per user (🔜 Planned — belum ada di kode)
- Setiap aksi berarti (tambah item baru, quantity bertambah, centang, uncheck, hapus) dicatat
  sebagai satu baris log **insert-only** di `shopping_list_history` (§7) — nama tabel & pola sama
  dengan `ai_usage_events` di `billing.md` supaya konsisten gaya di seluruh codebase.
- Tiap baris riwayat mencatat **siapa** (`user_id`) melakukan **apa** (`action`) terhadap barang
  **apa** (`item_name`, snapshot nama saat itu — tidak berubah walau item aslinya nanti dihapus/
  di-rename) dan **kapan** (`created_at`).
- Riwayat ini tetap ada meskipun item aslinya sudah dihapus dari `shopping_list_items` — jadi
  "riwayat/arsip" yang sebelumnya jadi Non-Goal (§3) sekarang justru jadi Goal, disimpan terpisah
  dari data kerja (list) yang sifatnya masih volatile.
- Data ini juga jadi sumber untuk rencana **analisis kebiasaan lintas modul** (`billing.md` §2.1) —
  belanja apa yang sering dibeli, oleh siapa, seberapa sering.

## 7. Data Model

| Tabel | Kolom kunci | Catatan scoping |
|---|---|---|
| `shopping_lists` | household_id, name (default "Belanja") | Household-scoped via `BelongsToHousehold` |
| `shopping_list_items` | shopping_list_id, ingredient_id (nullable), name, 🔜 `quantity` (diubah jadi integer, nullable), is_checked, added_by (nullable), 🔜 `assigned_to` (nullable FK users) | Scoped transitif lewat `shopping_list_id` |
| `shopping_list_history` (🔜 baru) | household_id, user_id, item_name (snapshot string, bukan FK), action (string: `added`\|`quantity_increased`\|`checked`\|`unchecked`\|`deleted`), quantity (nullable, snapshot), 🔜 `transaction_id` (nullable FK `transactions` — diisi saat §6.7), created_at | Insert-only log, household-scoped. Pola sama dengan `ai_usage_events` di `billing.md`. |

`quantity` **saat ini** (string, nullable) tidak pernah diisi jalur kode manapun. **🔜 Planned:**
diubah jadi kolom **integer** (nullable, default `1` untuk baris baru) supaya bisa dipakai sebagai
counter jumlah barang yang di-increment (§6.2, §6.3) — alter-column aman karena belum ada data
riil yang bergantung ke tipe string-nya.

> 🔜 **Planned:** `assigned_to` (nullable, FK `users`) & alter `quantity` ke integer — perlu
> migration baru (§6.5, §6.2/§6.3). `shopping_list_history` juga tabel baru (§6.8). Tidak ada tabel
> baru untuk marketplace (§6.6, masih konseptual) atau pencatatan Finance itu sendiri (§6.7 menulis
> ke tabel `transactions` milik modul Finance — kolom `description`-nya sudah ada, tidak perlu
> migration baru di Finance).

## 8. Non-Functional Requirements

- **Tenancy/keamanan:** Scoping household diverifikasi lewat test otomatis
  (`HouseholdIsolationTest::'never leaks shopping lists across households'`) — **berbeda dari
  Cooking yang belum py test sama sekali**, modul ini py jaminan regresi minimal untuk isolasi data.
- **Konsistensi bahan dengan Cooking:** Hanya jalur otomatis (§6.3) yang pakai
  `IngredientNormalizer` yang sama dengan Cooking — jalur manual (§6.2) tidak, jadi nama item bisa
  tidak konsisten (mis. "Beras" vs "beras" bisa jadi dua baris berbeda kalau diketik manual, tapi
  tidak akan pernah dobel kalau lewat "tambahkan bahan yang kurang").
- **Sederhana secara sengaja:** Tidak ada AI, tidak ada background job, tidak ada dependency
  eksternal — modul paling ringan di antara ketiga modul MVP (berlaku untuk baseline; §6.5–§6.7
  menambah kompleksitas terukur di atasnya).
- **🔜 Planned — kopling antar modul bertambah:** Begitu §6.7 dibangun, `CLAUDE.md` yang saat ini
  bilang Cooking→ShoppingList "satu-satunya dependency cross-module yang disengaja" perlu di-update
  — ShoppingList→Finance jadi dependency kedua. Pola yang sama (satu arah, lewat service kecil)
  tetap dipertahankan, bukan coupling dua arah.

## 9. Metrics / KPI (usulan — belum diinstrumentasi di kode)

> Belum ada tracking apa pun untuk ini di kode saat ini.

- **Manual vs auto-add ratio:** % item yang berasal dari input manual (§6.2) vs dari Cooking
  (§6.3) — indikasi seberapa besar nilai integrasi cross-module ini secara riil.
- **Completion rate:** % item yang akhirnya dicentang (dibeli) vs dihapus tanpa dicentang atau
  dibiarkan menumpuk.
- **Duplicate manual entries:** perkiraan berapa sering user menambahkan barang yang sebenarnya
  sudah ada di daftar (nama mirip/sama) lewat jalur manual yang tidak dedup — sinyal apakah
  normalisasi+dedup perlu diperluas ke Flow A juga.

## 10. Edge Cases & Error Handling (sudah ditangani di kode)

- Nama item kosong/hanya spasi (manual add) → diabaikan, tidak membuat row.
- Bahan "missing" kosong (`empty($this->missing)`) atau user belum py household
  (`auth()->user()?->currentHousehold` null) → `MissingIngredientsToShoppingList::add()` tidak
  dipanggil, tombol tidak berefek.
- Nama bahan hasil normalisasi jadi string kosong (mis. input aneh) → dilewati (`continue`), tidak
  bikin row kosong.
- User coba `toggleItem`/`removeItem` dengan `itemId` yang bukan milik household saat ini (mis.
  tebak-tebak ID) → `findOrFail`/`where('id', ...)` discoped lewat `currentList()->items()`, jadi
  otomatis gagal/no-op untuk item di luar household — tidak perlu extra guard manual.

## 11. Dependencies & Integrations

- **Cooking module** — satu-satunya sumber integrasi masuk: `MissingIngredientsToShoppingList`
  dipanggil dari `AddMissingIngredients` yang di-embed di hasil pencarian resep Cooking (lihat
  `docs/prd/cooking.md` §6.7). Dependency-nya searah (Cooking → ShoppingList), bukan sebaliknya.
- **`IngredientNormalizer`** (`App\Modules\Cooking\Support`) — dipakai lintas modul dari Cooking,
  bukan duplikat logic sendiri di ShoppingList.
- Tidak ada dependency eksternal (AI, payment, dst) — modul paling mandiri di antara ketiganya
  (berlaku untuk baseline; lihat penambahan di bawah).
- **🔜 Planned — Finance module:** §6.7 bikin ShoppingList jadi caller ke service Finance (mis.
  `RecordShoppingExpense` atau nama serupa) yang membuat `Transaction`. Arahnya searah
  (ShoppingList → Finance), sama seperti pola Cooking → ShoppingList yang sudah ada.
- **🔜 Planned — Marketplace partner (belum ditentukan):** §6.6 baru konseptual, dependency
  eksternal riilnya (API key, SDK, dst) baru ada begitu partner dipilih.
- **🔜 Planned — Modul Inventory/kulkas (masa depan, belum ada PRD):** dicatat sebagai titik
  integrasi masa depan, lihat §12 — bukan dependency yang ada sekarang.

## 12. Gap & Next Steps (belum tercatat resmi di `docs/ROADMAP.md` — modul ini belum py section sendiri di sana)

- Belum ada test untuk Flow A (`ShoppingListPage`) — test yang ada (`HouseholdIsolationTest`) cuma
  memverifikasi isolasi tenancy, bukan behavior tambah/centang/hapus item. Makin penting begitu
  logic increment-quantity (§6.2/§6.3) masuk — perlu test baru.
- **🔜 Planned — Modul Inventory/kulkas (masa depan):** belum ada PRD-nya sama sekali. Titik
  integrasi yang perlu diperhatikan nanti: (a) barang yang dibeli (§6.4/§6.7) idealnya menambah
  stok di Inventory begitu modul itu ada, (b) sebaliknya, barang yang "menipis" di Inventory bisa
  otomatis diusulkan masuk ke Shopping List — arah integrasi kedua ini **belum diputuskan**,
  cuma dicatat sebagai kemungkinan.
- **🔜 Baru disepakati:** `assigned_to` (§6.5) belum ada di data model — perlu migration baru +
  UI pemilihan assignee dari anggota household. Saat anggota keluar/dikeluarkan dari household,
  `assigned_to` yang menunjuk ke dia **di-null-kan** (bukan dibiarkan) supaya item bisa di-assign
  ulang ke anggota lain — butuh listener/observer di event household membership berubah, bukan
  cuma `nullOnDelete()` di FK (itu cuma trigger kalau *user row*-nya dihapus, bukan kalau dia
  sekadar keluar dari household tertentu).
- **🔜 Baru disepakati:** integrasi Finance (§6.7) belum ada — perlu service baru yang membuat
  `Transaction` dari ShoppingList, plus UI aksi "Catat pengeluaran belanja" terpisah dari checklist.
- **🔜 Baru disepakati:** riwayat per user (`shopping_list_history`, §6.8) belum ada — tabel baru +
  logging di setiap aksi (tambah/increment/centang/uncheck/hapus).
- **🔜 Baru disepakati:** logic increment-quantity (§6.2/§6.3) mengganti perilaku lama (dedup diam
  di Flow B, tidak ada dedup di Flow A) — perlu diimplementasikan bareng, bukan terpisah, karena
  keduanya sekarang harus berperilaku identik.

## 13. Open Questions

1. **Marketplace partner (§6.6) — masih terbuka, belum dijawab:** siapa partner yang dituju, dan
   apakah integrasinya deep-link sederhana atau API afiliasi/cart-injection penuh? Ini menentukan
   besar-kecilnya effort teknis secara drastis, belum bisa di-scope lebih lanjut sampai partner
   ditentukan.
2. Menambah item yang sudah ter-checked (increment quantity-nya, §6.2/§6.3) — asumsi saat ini
   **tidak** meng-uncheck item itu otomatis. Apakah ini perilaku yang benar, atau justru harusnya
   di-uncheck lagi (indikasi "perlu dibeli lagi")?
3. `shopping_list_history` (§6.8) mencatat snapshot nama barang, bukan FK ke item — kalau item yang
   sama di-increment berkali-kali, apakah tiap increment jadi baris riwayat sendiri (granular tapi
   riwayat bisa jadi panjang), atau cukup satu baris yang di-update quantity-nya?

## 14. Riwayat Perubahan

| Tanggal | Perubahan |
|---|---|
| 2026-07-29 | Draf baseline awal, reverse-engineer dari kode existing (`ShoppingListPage`, `AddMissingIngredients`, `MissingIngredientsToShoppingList`, migrations, `HouseholdIsolationTest`). |
| 2026-07-29 | Ditambahkan 3 rencana fitur: (1) **assign tugas beli per item** ke anggota household, informasional tidak membatasi permission (§6.5, §7); (2) **beli online via marketplace partner**, masih konseptual karena partner belum ditentukan (§6.6); (3) **catat pengeluaran belanja langsung ke Finance**, nominal total manual per sesi (bukan per item), jadi dependency cross-module kedua setelah Cooking→ShoppingList (§6.7, §8, §11). Dicatat juga rencana **modul Inventory/kulkas** di masa depan sebagai titik integrasi, belum ada PRD-nya (§3, §12). |
| 2026-07-29 | Resolusi 5 dari 6 Open Question (marketplace partner #5 lama masih terbuka, dipindah jadi #1 di §13): (1) tetap **satu list per household**, dievaluasi lagi nanti (§3); (2) dedup Flow A & B **disatukan** — nama sama sekarang **increment quantity**, bukan dedup-diam atau duplikat baris; `quantity` diubah dari string tak terpakai jadi integer counter aktif (§6.2, §6.3, §7); (3) ditambahkan **riwayat aktivitas per user** (`shopping_list_history`, insert-only, pola sama seperti `ai_usage_events` di `billing.md`) — §6.8, §7; (4) tetap **nominal total** (bukan per item) untuk pencatatan Finance, ditambah **penamaan otomatis + link balik ke riwayat** lewat `transaction_id` di `shopping_list_history` (tidak perlu ubah skema Finance, `description` sudah ada di sana) — §6.7; (6) `assigned_to` **di-null-kan** saat anggota keluar dari household, supaya item bisa di-assign ulang (§12). |
| 2026-07-29 | Ditambahkan §15 Rencana Implementasi (fase kerja + todo list). |

## 15. Rencana Implementasi

> Marketplace partner (§6.6) **sengaja tidak masuk fase manapun** di bawah — masih gated di Open
> Question §13 #1, belum bisa di-scope sampai partner ditentukan.

**Fase 1 — Data model** *(estimasi: 1–2 jam)*
Fondasi untuk semua fase lain.
- Migration: alter `shopping_list_items.quantity` dari string jadi integer (nullable, default `1`
  untuk baris baru).
- Migration: `shopping_list_items` +`assigned_to` (nullable, FK `users`).
- Migration: `shopping_list_history` (household_id, user_id, item_name, action, quantity,
  transaction_id nullable, created_at).

**Fase 2 — Unifikasi tambah item (Flow A & B) jadi increment-quantity** *(estimasi: 2–3 jam)*
Inti dari resolusi inkonsistensi lama — dua jalur ini sekarang harus identik perilakunya.
- `ShoppingListPage::addItem()`: normalisasi nama (`IngredientNormalizer`), cari item existing by
  nama ternormalisasi di list yang sama → increment `quantity` kalau ketemu, buat baris baru
  (`quantity = 1`) kalau tidak.
- `MissingIngredientsToShoppingList::add()`: ganti `firstOrCreate` diam jadi logic increment yang
  sama seperti di atas (idealnya satu service/trait dipakai bareng oleh keduanya, bukan dua
  implementasi terpisah yang harus disinkronkan manual).
- Blade `shopping-list-page.blade.php`: tampilkan `quantity` di sebelah nama item (mis. "Susu ×3").
- Log ke `shopping_list_history` (action `added` atau `quantity_increased`) di titik yang sama.

**Fase 3 — Riwayat untuk aksi lain (centang/uncheck/hapus)** *(estimasi: 2 jam)*
Melengkapi §6.8 supaya semua aksi berarti tercatat, bukan cuma nambah.
- `toggleItem()`: log `checked`/`unchecked` ke `shopping_list_history`.
- `removeItem()`: log `deleted` (snapshot nama & quantity sebelum baris asli dihapus).
- Halaman/view sederhana untuk household lihat riwayat mereka sendiri (list flat, tidak perlu
  filter/pencarian canggih) — **asumsi minimal**, koreksi kalau ternyata riwayat ini maksudnya
  cuma untuk keperluan internal/CMS, bukan ditampilkan ke household.

**Fase 4 — Assign tugas per item** *(estimasi: 2–3 jam)*
Bergantung ke Fase 1 (`assigned_to` sudah ada).
- UI: dropdown pilih assignee (dari anggota household) saat tambah/edit item.
- Badge "ditugaskan ke: [nama]" di tampilan item.
- Listener/observer di event household membership berubah (anggota keluar/dikeluarkan) → null-kan
  `assigned_to` yang menunjuk ke anggota tsb di semua item household itu.

**Fase 5 — Integrasi Finance ("Catat pengeluaran belanja")** *(estimasi: 3–4 jam)*
Bergantung ke Fase 1 & 3 (butuh kolom & riwayat checked items sudah jalan).
- Service baru (mis. `RecordShoppingExpense`) di ShoppingList yang memanggil Finance untuk membuat
  `Transaction` (`type = expense`, `description` auto-generate, kategori dipilih user).
- UI aksi terpisah "Catat pengeluaran belanja" (bukan bagian dari checklist item biasa) — input
  nominal total + pilih kategori.
- Set `transaction_id` di baris `shopping_list_history` yang termasuk sesi belanja ini.

**Fase 6 — Marketplace partner** *(belum dijadwalkan — gated, lihat §13)*
Tidak dieksekusi sampai partner ditentukan dan pendekatan integrasi (deep-link vs API) dipilih.

**Fase 7 — Tes minimal** *(estimasi: 2 jam)*
- Pest: tambah item dengan nama yang sudah ada → increment quantity, bukan baris baru (berlaku
  untuk Flow A manual maupun Flow B dari Cooking).
- Pest: tiap aksi (tambah/increment/centang/uncheck/hapus) menghasilkan baris `shopping_list_history`
  yang benar.
- Pest: `assigned_to` ter-null-kan otomatis saat anggota keluar dari household.
- Pest: "Catat pengeluaran belanja" membuat `Transaction` dengan `description` & `transaction_id`
  yang benar di baris riwayat terkait.

## ✅ Todo List

- [ ] [db] Migration: alter `shopping_list_items.quantity` jadi integer, default 1
- [ ] [db] Migration: `shopping_list_items` +`assigned_to` (nullable FK users)
- [ ] [db] Migration: `shopping_list_history` (household_id, user_id, item_name, action, quantity, transaction_id, created_at)
- [ ] [backend] Unifikasi logic increment-quantity di `ShoppingListPage::addItem()` & `MissingIngredientsToShoppingList::add()`
- [ ] [frontend] Tampilkan quantity di blade shopping-list-page
- [ ] [backend] Logging `shopping_list_history` untuk aksi added/quantity_increased
- [ ] [backend] Logging `shopping_list_history` untuk checked/unchecked/deleted
- [ ] [frontend] Halaman/view riwayat sederhana untuk household
- [ ] [backend] UI + kolom `assigned_to`: dropdown assignee dari anggota household
- [ ] [backend] Listener/observer null-kan `assigned_to` saat anggota keluar household
- [ ] [backend] Service `RecordShoppingExpense` (ShoppingList → Finance `Transaction`)
- [ ] [frontend] UI aksi "Catat pengeluaran belanja" (nominal + kategori)
- [ ] [backend] Link `transaction_id` ke baris riwayat terkait
- [ ] [test] Pest: increment quantity untuk item nama sama (Flow A & B)
- [ ] [test] Pest: logging riwayat benar untuk tiap jenis aksi
- [ ] [test] Pest: assigned_to null saat anggota keluar household
- [ ] [test] Pest: catat pengeluaran membuat Transaction + link riwayat yang benar
