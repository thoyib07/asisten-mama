# UI Design

> Arah visual "Buku Catatan Rumah Tangga" — disetujui lewat sesi eksplorasi desain (lihat §7
> Riwayat Perubahan). **Bukan** dokumentasi tampilan yang sudah ada di kode (beda dari
> `architecture.md`/`code-conventions.md`/`database-design.md`) — ini arah baru yang mengganti
> tampilan Tailwind-default existing (`amber-50`/`green-700`/`stone`/`rounded-xl shadow-sm`).
> Redesain **per-modul menyusul saat masing-masing modul masuk fase implementasi** — lihat §6.

## 1. Konsep

Tiga (lalu makin banyak) modul asisten-mama diperlakukan seperti halaman-halaman dalam satu **buku
catatan rumah tangga** fisik — bukan sekadar app dengan tema warna hangat generik. Elemen material
budaya rumah tangga Indonesia yang dipakai sebagai sumber keputusan visual: kertas kraft/amplop,
tinta pena, dan stempel tinta merah pada kuitansi/dokumen resmi.

Dua alasan kenapa arah ini dipilih (bukan sekadar "app yang ramah"): (1) fitur **Kantong**
(`finance.md`) secara harfiah adalah amplop uang — motif amplop jadi masuk akal secara fungsional,
bukan dekoratif; (2) menghindari 3 klise desain AI generik (cream+serif+terracotta;
hitam+neon; broadsheet hairline) dengan cara aksen merah cuma dipakai sebagai "stempel" sesekali,
bukan warna brand utama.

## 2. Token warna

| Token | Terang | Gelap | Peran |
|---|---|---|---|
| `--bg` | `#EAE3CE` | `#1B1712` | Latar utama — kertas kraft / sampul ledger malam |
| `--surface` | `#F8F2E6` | `#262019` | Permukaan kartu "indeks" |
| `--surface-alt` | `#F1E9D6` | `#2E271D` | Header, nav bar, variasi permukaan |
| `--ink` | `#2B211A` | `#EDE4D0` | Teks utama |
| `--ink-soft` | `#6B5F4E` | `#B3A68C` | Teks sekunder/muted |
| `--accent` (tinta teal) | `#1F5D52` | `#4FA090` | Aksi utama, status "lagi di sini" (nav) |
| `--stamp` (stempel merah) | `#A8432C` | `#D97452` | **Cuma untuk badge status** (LUNAS/OVER/BARU), bukan warna brand utama |
| `--rule` | `#C9BC9C` | `#4A4030` | Garis ledger/border kartu |

Kedua tema (terang & gelap) didesain setara — dark mode bukan cuma invert, tetap warm/berkarakter
("sampul ledger malam", bukan abu-abu generik). Implementasi: token di `:root`, override di
`@media (prefers-color-scheme: dark)` dan `:root[data-theme="dark"]`/`[data-theme="light"]` untuk
toggle manual pengguna.

### 2.1 Warna per-modul (tab & avatar anggota)

Selain token global di atas, tiap modul dapat warna kecil sendiri — dipakai konsisten untuk ubin
grid Beranda **dan** avatar anggota keluarga (satu sistem warna, bukan dua palet terpisah):

| Modul | Terang | Gelap |
|---|---|---|
| Resep | `#A9812F` | `#CDA54C` |
| Belanja | `#1F5D52` | `#4FA090` |
| Keuangan | `#A8432C` | `#D97452` |
| Langganan | `#6B5F4E` | `#B3A68C` |
| Rumah Tangga | `#3F6B8A` | `#6EA0C2` |
| Inventaris (segera) | `#8A8072` | `#A69A86` |

## 3. Tipografi

| Peran | Font (final, self-hosted) | Font sistem (preview/fallback) |
|---|---|---|
| Display — judul, nominal besar | Zilla Slab (bold) | Georgia, Cambria, "Times New Roman", serif |
| Body/UI — teks & label | Inter | "Segoe UI", -apple-system, Helvetica, Arial, sans-serif |
| Data — nominal uang & tanggal | JetBrains Mono | "Cascadia Mono", Consolas, "Courier New", monospace |

Font data (mono) dipakai **konsisten** untuk semua nominal uang & tanggal — memberi kesan
"buku kas", langsung menyambung ke fitur Kantong. `font-variant-numeric: tabular-nums` di semua
tempat digit berbaris (nominal, tanggal) supaya sejajar rapi.

**Catatan implementasi:** font final (Zilla Slab/Inter/JetBrains Mono) harus **di-self-host**
(taruh di `public/fonts` + `@font-face`, load lewat Vite) — bukan CDN Google Fonts, konsisten
dengan sikap zero-budget & kontrol performa aplikasi ini (lihat `docs/DEPLOY.md`).

## 4. Signature element: badge stempel

Lingkaran/oval kecil, tinta stempel-merah, sedikit miring (rotate ±6–9°), border 1.5px warna
stempel, teks uppercase kecil letter-spaced. Dipakai konsisten lintas modul untuk status:
- **LUNAS** — item shopping list yang dibayar/checked (`shopping-list.md`)
- **OVER** — kantong yang melebihi budget (`finance.md` §6.6)
- **BARU** — resep hasil import AI (`cooking.md`)
- **PREMIUM** — badge tier langganan (`billing.md`)

Ini satu-satunya elemen "berani" di sistem — dipakai hemat & konsisten, bukan disebar ke semua
tempat sebagai dekorasi (prinsip "spend boldness in one place").

## 5. Pola layout

### 5.1 Beranda = grid modul (bukan daftar isi vertikal — direvisi, lihat §7)
Terinspirasi grid shortcut ala e-wallet Indonesia (GoPay/DANA/ShopeePay): ubin ikon bulat/rounded
per modul, 3 kolom, dengan badge notifikasi kecil (angka/tanda seru) di pojok ubin untuk item yang
perlu perhatian. **Modul baru = nambah satu ubin**, tidak mengubah struktur nav bar.

Header Beranda: sapaan + tanggal + **avatar anggota keluarga berkode-warna** (terinspirasi Cozi,
sampai beberapa anggota, warna reuse dari §2.1) + strip ringkasan "hari ini" (jumlah barang
belanja, kantong over, resep baru — inspirasi "Today view" Cozi).

### 5.2 Nav bar: 3 slot, tidak duplikasi dengan grid
| Slot | Isi | Alasan |
|---|---|---|
| Kiri | Beranda | Anchor utama |
| Tengah (timbul) | **Cari** (pencarian resep by bahan, fitur AI andalan) | Pola tombol pop-up ala tombol "Scan" e-wallet — dipakaikan ke aksi paling khas & bernilai di app ini |
| Kanan | Keluarga/User | Kelola anggota rumah tangga & pengaturan (mis. tanggal reset kantong, `finance.md` §6.11) |

Modul fitur (Resep, Belanja, Keuangan, Langganan, dst) **cuma** ada di grid Beranda — nav bar tidak
menduplikasinya. Status "lagi di halaman mana" untuk item nav flat pakai cincin tipis warna
`--accent` di sekitar ikon (reuse bentuk lingkaran dari signature stempel, bukan metafora baru).

### 5.3 Kartu "kantong" (amplop budget)
Sudut atas terpotong (clip-path, siluet lipatan amplop), nominal pakai font mono besar, garis putus
horizontal memisahkan info sisa budget, badge stempel "OVER" untuk kantong minus.

### 5.4 Kartu "indeks" umum
Card dasar (`--surface` di atas `--bg`), border 1px `--rule`, radius kecil (4–6px, bukan
`rounded-xl` besar khas Tailwind default) — kesan kartu indeks fisik, bukan card app generik.

## 6. Status implementasi & rencana lanjutan

**Disetujui (fondasi/sistem):** token warna, tipografi, signature stempel, pola Beranda-grid,
struktur nav 3-slot, kartu kantong, kartu indeks.

**🔜 Menyusul saat implementasi — per modul, urutan mengikuti kapan modulnya digarap:**
- **Cooking — prioritas berikutnya (2026-08-05):** implementasi fitur modul Cooking (`RecipeFinder`,
  `RecipeList`, `recipes/show`, dll) sudah selesai, jadi ini modul pertama yang siap masuk fase
  redesain sesuai §6 (aturan "redesain menyusul saat implementasi"). Motivasi konkret yang baru
  muncul: blade Cooking masih pakai palet Tailwind lama (`bg-white`/`stone-*`/`green-700`) yang
  **tidak ikut skema dark-mode** `docs/ui-design.md` §2 — sementara `<body>` (`layout.blade.php`)
  sudah pakai token `--ink`/`--bg` yang otomatis berganti gelap/terang. Akibatnya teks di kotak
  input/select Cooking nyaris tak terbaca di dark mode (ditemukan user 2026-08-05, tambal-sulam
  darurat: nambah `text-stone-800` eksplisit — **bukan** perbaikan permanen, cuma supaya kebaca
  sampai redesain penuh ke token `--surface`/`--ink`/`--rule` dst dikerjakan). Redesain penuh perlu
  mencakup: form `RecipeFinder` (input bahan, filter kategori/cuisine, tombol AI), kartu hasil
  pencarian, `RecipeList`/`FavoritesList`, halaman detail `recipes/show` (termasuk badge stempel
  "BARU" utk resep AI sesuai §4, badge durasi/gizi/gramasi yang baru ditambahkan), dan
  `recipe-rating`.
- Redesain `ShoppingListPage` ke sistem ini (termasuk kartu item, badge assign, dll dari
  `shopping-list.md`).
- Redesain `FinancePage` + halaman baru "Kelola Kantong" (dari `finance.md` §15) ke sistem ini —
  ini yang paling banyak elemen barunya (kartu kantong, top-up, filter periode).
- Halaman Beranda (grid) & Keluarga/User itu sendiri — belum ada route/Livewire component-nya sama
  sekali, perlu dibangun dari nol mengikuti mockup ini.
- Migrasi warna/tipografi di `resources/views/components/layout.blade.php` (shell PWA) dari
  palet lama (`amber-50`/`green-700`/`stone`) ke token baru.
- Font self-hosting (Zilla Slab/Inter/JetBrains Mono) — belum di-setup di build Vite.

**Preview referensi:** mockup interaktif (HTML, palet terang/gelap, grid + nav + kartu kantong)
dibuat selama sesi desain — kalau butuh dilihat lagi, minta dibuatkan ulang dari deskripsi di
dokumen ini (file mockup asli ada di scratchpad sesi, sifatnya sementara).

## 7. Riwayat Perubahan

| Tanggal | Perubahan |
|---|---|
| 2026-07-30 | Draf arah desain "Buku Catatan Rumah Tangga" disetujui setelah 3 iterasi mockup: (v1) konsep dasar kraft/tinta-teal/stempel; (v2) Beranda diubah dari daftar-isi vertikal jadi grid ala ShopeePay + tombol Cari timbul di tengah nav + avatar anggota ala Cozi; (v3) nav bar disederhanakan jadi 3 slot non-duplikatif (Beranda/Cari/Keluarga), modul "Rumah Tangga" dipindah dari grid ke nav sebagai "Keluarga". Redesain per-modul (Cooking/ShoppingList/Finance/Beranda/Keluarga) sengaja **ditunda ke fase implementasi masing-masing**, dicatat sebagai reminder (§6). |
| 2026-08-05 | User menemukan bug kontras teks di form Cooking pada dark mode, lalu mengaitkannya dengan pertanyaan apakah sistem terang/gelap sudah ada. **Klarifikasi:** sistem terang/gelap **sudah** dirancang & disetujui sejak 2026-07-30 (§2) — bug-nya bukan karena konsepnya belum ada, tapi karena blade Cooking belum dimigrasikan ke token itu (masih Tailwind default `bg-white`/`stone`). §6 diperkuat: Cooking ditandai sebagai modul **prioritas berikutnya** untuk redesain, karena implementasi fiturnya sudah selesai (syarat "menyusul saat implementasi" di §6 sudah terpenuhi). Perbaikan `text-stone-800` yang ditambahkan saat itu ditandai eksplisit sebagai tambal-sulam sementara, bukan solusi akhir. |
