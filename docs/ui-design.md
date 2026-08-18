# UI Design

> Arah visual dari file Figma **"Asisten Mama"**
> (`figma.com/design/JP7zSthjOXwdQuaCdP1BIk/Asisten-Mama`, Page 1, 6 frame).
> **Menggantikan total** arah "Buku Catatan Rumah Tangga" yang berlaku 2026-07-30 – 2026-08-18
> (palet kraft, tinta teal, badge stempel miring, radius 4–6px, nav 3-slot, dark mode) — lihat §8.
> Nilai token di bawah **dipanen dari panel Design Figma**, bukan diambil dari piksel screenshot.

## 1. Konsep

Satu ruang koordinasi keluarga: putih bersih di atas latar mint sangat pucat, kartu membulat
besar dengan bayangan halus, satu warna aksi hijau, dan warna per-modul yang cuma muncul sebagai
ubin ikon di Beranda. Tidak ada elemen dekoratif yang "berani" — keterbacaan dan kepadatan
informasi yang dikejar, karena tiap layar menampilkan daftar (tugas, belanja, agenda, resep).

Frame acuan lebar **402px**. Padding horizontal halaman **24**, gap antar-blok **16**.

## 2. Token warna

Nama variabel & kelas utility sengaja dipertahankan dari sistem lama; yang berubah cuma nilainya.
Alasannya operasional: blade yang belum dimigrasikan (mis. Finance) dibangun di atas `bg-app`,
`text-ink-soft`, `border-rule` — kalau kelasnya dihapus, utility-nya jadi **tidak terdefinisi**
(hilang total, bukan berganti gaya) dan halaman itu rusak sampai gilirannya diredesain.

| Token | Nilai | Peran |
|---|---|---|
| `--bg` | `#F4FAF6` | Latar halaman |
| `--surface` | `#FFFFFF` | Kartu |
| `--surface-alt` | `#FFFFFF` | Nav bar |
| `--ink` | `#1F1D2B` | Teks utama |
| `--ink-soft` | `#6C6880` | Teks sekunder |
| `--muted-2` | `#A5A4BF` | Teks tersier — **hanya non-esensial** |
| `--rule` | `#E8EAE6` | Divider & border |
| `--accent` | `#00B14F` | Aksi utama, state aktif nav, FAB |
| `--accent-tint` | `#E5F7EE` | Latar ikon/chip hijau solid |
| `--danger` | `#FF5E5B` | Prioritas Tinggi, aksi destruktif |
| `--warning` | `#FFC107` | Prioritas Sedang |
| `--shadow-card` | `0 2px 8px rgb(0 0 0 / 6%)` | Elevasi kartu (ekstrapolasi) |

Tiga token turunan untuk **teks & ikon**, karena warna Figma di atas terlalu terang sebagai teks:

| Token | Nilai | Kontras di atas putih | Menggantikan |
|---|---|---|---|
| `--accent-text` | `#087A3B` | 5,44:1 | `--accent` (2,84:1) |
| `--danger-text` | `#C62828` | 5,62:1 | `--danger` (3,00:1) |
| `--warning-text` | `#8A5D00` | 5,76:1 | `--warning` (1,66:1) |

Aturannya: **isian (fill) pakai warna Figma apa adanya, teks & ikon pakai turunan gelapnya.**
Jadi `.bg-accent` tetap `#00B14F` (identitas brand tidak berubah), sedangkan `.text-accent`,
`.text-danger`, dan teks di `.badge-*` memakai turunan. Latar badge tetap warna Figma pada 7,84%.

**Light-only.** Tidak ada satupun frame gelap di Figma, dan dark mode dibuang 2026-08-18 — tidak
ada `@media (prefers-color-scheme: dark)`, `[data-theme]`, maupun toggle di shell.

⚠️ `--muted-2` di atas `#FFFFFF` kontrasnya ≈ **2,4:1**, di bawah ambang WCAG AA 4,5:1. Pakai
hanya untuk hal yang boleh tidak terbaca — **tanggal luar-bulan di kalender dan placeholder input,
titik**. Jangan untuk teks sekunder (pakai `--ink-soft`, 5,34:1) dan jangan untuk kontrol
interaktif (tombol hapus item / keluarkan anggota sempat memakainya, sudah dipindah).

⚠️ **Belum beres:** label putih di atas isian `--accent` `#00B14F` kontrasnya **2,84:1** — di bawah
AA untuk teks 14px bold (ambang 3:1 hanya berlaku untuk teks ≥18,66px bold). Ini menyangkut warna
brand di file Figma, jadi tidak diubah sepihak. Kalau mau dibereskan tanpa mengubah tampilan
banyak: gelapkan isian tombol ke `#00873D` (putih di atasnya = 4,63:1).

### 2.1 Warna ubin modul (Beranda)

Pasangan ikon/latar bergaya Material 800/50. Muncul **hanya** di ubin grid Beranda.

| Ubin | Ikon | Latar |
|---|---|---|
| Kalender | `#1976D2` | `#E3F2FD` |
| Tugas | `#5E35B1` | `#EDE7F6` |
| Belanja | `#2E7D32` | `#E8F5E9` |
| Tagihan | `#E65100` | `#FFF3E0` |
| Resep | `#00838F` | `#E0F7FA` (ekstrapolasi — §7) |

### 2.2 Warna avatar anggota

Palet **terpisah** dari §2.1 — beda dari sistem lama yang sengaja menyatukan keduanya.
Dipakai lewat `User::avatarColorClass()` (`.avatar-1` … `.avatar-6`).

`#00B14F` · `#E91E63` · `#5C68C0` · `#FFA000` · `#00897B`¹ · `#C2185B`¹
<sup>¹ ekstrapolasi — Figma cuma memberi 4, dua terakhir ada supaya keluarga 5–6 orang tidak
bertabrakan warna.</sup>

## 3. Tipografi

Font tunggal **Figtree**, self-host lewat `bunny()` di `vite.config.js` (bukan CDN runtime —
konsisten sikap zero-budget `docs/DEPLOY.md`). Bobot: Regular 400, Bold 700, ExtraBold 800.

| Peran | Spek |
|---|---|
| H1 halaman | ExtraBold 24 / `--ink` |
| Sub-judul halaman | Regular 14 / `--ink-soft` |
| Judul section | Bold 16 / `--ink` |
| Judul kartu | Bold 14–16 / `--ink` |
| Body & meta | Regular 12–14 / `--ink-soft` |
| Label tombol | Bold 14 / putih |

## 4. Signature element: badge pill

Pill kecil ber-radius penuh. Latar = warna semantik pada **opacity 7,84%**, teks = warna semantik
penuh (terkonfirmasi di panel Figma: tiap warna badge muncul berpasangan 100% + 7,84%).
Kelas: `.badge` + `.badge-accent` / `.badge-danger` / `.badge-warning` / `.badge-muted`.

Dipakai untuk: prioritas tugas (Tinggi/Sedang/Rendah), jadwal tugas (Hari Ini/Besok/tanggal),
durasi & porsi resep, skor kecocokan hasil pencarian bahan.

Badge stempel miring dari sistem lama **dihapus** — beserta token `--stamp`.

## 5. Pola layout

### 5.1 Beranda = grid ubin modul
Brand bar (ikon rumah hijau + wordmark "Keluarga" + ikon lonceng) → sapaan `Halo, {nama depan}!`
→ baris avatar anggota + tombol `+` bergaris putus → grid ubin 3 kolom → section "Acara Terdekat"
→ section "Tugas Hari Ini".

Ubin = kartu putih radius 20 dengan ikon rounded-square ber-tint. Badge angka kecil di pojok ubin
untuk hal yang perlu perhatian (belanja pending, resep baru). **Modul baru = nambah satu ubin.**

Isi grid (revisi dari Figma, keputusan 2026-08-18): Kalender, Tugas, Belanja, Resep, Tagihan.
Ubin **Obrolan** & **Galeri** yang ada di frame sengaja dibuang. Ubin **Tagihan** dirender
non-aktif sampai hubungannya dengan modul Finance diputuskan.

### 5.2 Nav bar: 4 slot flat
| Slot | Isi |
|---|---|
| 1 | Beranda |
| 2 | Kalender |
| 3 | Belanja |
| 4 | Profil |

Tombol "Cari" timbul di tengah dari sistem lama **dihapus**.

**Aturan state aktif — satu aturan, ditetapkan sekali di `layout.blade.php`:** slot menyala hanya
untuk route miliknya sendiri. Pengecualian tunggal `/tugas` → menyalakan **Kalender** (mengikuti
frame `task-list`; Kalender & Tugas satu pasangan agenda keluarga). Route lain yang tidak punya
slot — `/resep`, `/resep/bahan`, `/finance`, `/favorites`, `/resep/{id}` — tidak menyalakan apa pun.

### 5.3 Kartu & tombol
| Elemen | Spek |
|---|---|
| Kartu besar (`.card`) | radius 24, padding 20, gap 16, putih, `--shadow-card` |
| Kartu kecil (`.card-sm`) | radius 20, putih, `--shadow-card` |
| Tombol primary | radius penuh (pill), tinggi 42, padding 18×12, gap 8, `--accent` |
| FAB (`<x-fab>`) | lingkaran 56, `--accent`, mengambang kanan bawah di dalam kolom konten |
| Segmented control | kartu pill putih, segmen aktif = pill `--accent` teks putih |
| Grid resep | 2 kolom, gap 16 |

### 5.4 Buku Resep = dua route, bukan satu komponen bertab
"Tab" Jelajah / Cari dari bahan adalah **dua route** dengan satu partial tautan
(`<x-resep-tabs>`), bukan komponen Livewire pembungkus:

| Route | Komponen |
|---|---|
| `/resep` | `RecipeList` — jelajah: pencarian nama, chip kategori, grid 2 kolom |
| `/resep/bahan` | `RecipeFinder` — pencarian by-bahan + tombol AI |

Alasannya bukan estetika: `RecipeList` & `RecipeFinder` tetap berdiri sendiri sehingga tetap bisa
dites langsung lewat `Livewire::test()`. Melipat keduanya ke komponen induk akan memecahkan
`tests/Feature/Cooking/RecipeFinderTest.php`.

`/cari` → redirect ke `/resep/bahan`, `/recipes` → redirect ke `/resep` (bookmark & entri PWA
cache yang sudah beredar tidak boleh mati; dijaga `tests/Feature/ExampleTest.php`).

## 6. Status implementasi

**Selesai (2026-08-18):** token & font, shell (nav 4-slot, dark mode dibuang), Beranda,
Kalender, Tugas, Belanja, Buku Resep + Cari-dari-bahan, detail resep, Favorit, rating,
Profil Keluarga. Kalender & Tugas sudah jadi modul penuh (`app/Modules/{Calendar,Tasks}`,
tabel `events` & `tasks`, household-scoped) — **tidak ada lagi data statis di aplikasi.**

**Belum:**
- `FinancePage` — sengaja tidak diredesain (keputusan Tagihan-vs-Finance masih terbuka). Ikut
  palet baru karena nama kelas dipertahankan, tapi masih pakai pola kartu Tailwind lama.
- Peserta acara masih satu penanggung jawab (`events.user_id`), belum tumpukan avatar seperti
  di frame — butuh pivot `event_user`.

## 7. Yang didesain vs yang diekstrapolasi

**Dari Figma:** seluruh token warna §2, Figtree + bobot/ukuran §3, radius 20/24/pill, padding 24/20,
gap 16, pola badge 7,84%, 4 warna avatar, 4 warna ubin, isi & copy Indonesia di 6 frame.

**Ekstrapolasi (tidak ada di Figma):** warna ubin Resep, warna avatar ke-5 & ke-6, nilai
`--shadow-card`, tiga token teks `--accent-text`/`--danger-text`/`--warning-text` (diturunkan demi
kontras, bukan diambil dari file), tinggi/padding nav & ukuran ikon, tampilan detail resep /
Favorit / rating / semua form, penempatan logout & invite-code di Profil, penempatan badge angka
di ubin, aturan state aktif nav, pemecahan `/resep` + `/resep/bahan`.

**Fungsi yang dipindah, bukan dihapus:** checkbox inline "Hanya favorit" di halaman daftar resep
sudah tidak ada (tidak ada di frame) — favorit sekarang dicapai lewat ikon hati di brand bar
menuju `/favorites`. Konsekuensinya `RecipeList::$onlyFavorites` & `updatedOnlyFavorites()` kini
hanya terpakai lewat subclass `FavoritesList`.

**Ada di Figma tapi tidak dipakai:** ubin Obrolan & Galeri; banner "Terhubung dengan Cooking Mama"
(Cooking modul internal, bukan integrasi eksternal); mock status bar `09:41`; header grup
Dapur/Kamar Mandi di Belanja (`shopping_list_items` belum punya kolom kategori); baris toggle
"Pengaturan Notifikasi" di Profil (belum ada penyimpanannya — toggle yang tidak menyimpan apa pun
lebih menyesatkan daripada absen); "Estimasi Total Budget Rp 150.000" di Belanja (item belum punya
kolom harga — metriknya diganti jadi jumlah item belum dibeli, angka yang benar-benar ada).

**Form tambah/edit sepenuhnya ekstrapolasi:** Figma tidak punya satu pun frame form — frame
Kalender bahkan tidak punya tombol tambah. Form tambah Acara & Tugas dirakit dari komponen sistem
yang sudah ada (kartu, pill, chip, `<input type="date">` bawaan browser), dipicu dari FAB.

## 8. Riwayat Perubahan

| Tanggal | Perubahan |
|---|---|
| 2026-07-30 | Draf arah "Buku Catatan Rumah Tangga" disetujui setelah 3 iterasi mockup: kraft/tinta-teal/stempel; Beranda jadi grid ala ShopeePay + tombol Cari timbul; nav 3 slot (Beranda/Cari/Keluarga). Redesain per-modul ditunda ke fase implementasi masing-masing. |
| 2026-08-05 | Bug kontras teks di form Cooking pada dark mode. Klarifikasi: sistem terang/gelap sudah dirancang sejak 2026-07-30; bug-nya karena blade Cooking belum dimigrasikan ke token. Cooking ditandai prioritas redesain berikutnya. |
| 2026-08-18 | **Arah "Buku Catatan Rumah Tangga" diganti total** oleh file Figma "Asisten Mama". Lima keputusan user: (1) Kalender & Tugas dibangun UI dulu, data menyusul; (2) hubungan ubin Tagihan ↔ modul Finance **belum diputuskan** — ubin dirender non-aktif, Finance tidak disentuh; (3) grid Beranda direvisi dari frame — Obrolan & Galeri dibuang, Resep ditambahkan; (4) pencarian by-bahan tetap hidup sebagai mode kedua Buku Resep, bukan slot nav; (5) **dark mode dibuang** — tidak ada frame gelap di Figma. Konsekuensi: §2 palet, §2.1 penyatuan warna modul-avatar, §3 tipografi tiga-font (Zilla Slab/Inter/JetBrains Mono), §4 badge stempel, §5.2 nav 3-slot, §5.3 kartu kantong amplop, dan §5.4 radius 4–6px dari versi lama **semuanya tidak berlaku lagi**. |
