# Roadmap

Fitur yang ditunda secara sengaja. Tambahkan saat ada kebutuhan nyata.

## Modul lanjutan (belum dibangun)
- Edukasi anak (child education helper)
- Perawatan anak sakit (sick-child care tracker)
- Pengingat bayar tagihan (bill payment reminders)

Fondasi `Household` + `BelongsToHousehold` sudah dirancang menampung modul-modul ini tanpa rombak —
tinggal tambah tabel household-scoped baru + folder modul baru mengikuti pola Cooking/ShoppingList/Finance.

## Auth & tenancy
- Verifikasi email nyata (perlu provider email zero-budget yang teruji — kandidat: Resend free tier).
  Saat ini `emailVerification()` tidak diaktifkan di panel Filament.
- Undangan anggota household (invite flow) — saat ini hanya owner yang terbentuk otomatis saat
  registrasi, belum ada cara menambah anggota lain ke household yang sama.
- Tenancy Filament native (`->tenant()`) — hanya relevan jika suatu saat butuh UX ganti-ganti
  household yang lebih kaya daripada satu household per user.

## Cooking
- Resep tetap katalog global/shared, bukan per-household. Jika ini jadi masalah (mis. AI import
  satu keluarga muncul di keluarga lain), pertimbangkan kolom `household_id` nullable di `recipes`.
- Filament steps editor masih `Textarea` polos (satu baris = satu langkah), belum jadi `Repeater`
  dengan metadata per-langkah (durasi, foto).
- Gambar resep di-upload ke filesystem lokal — hilang saat redeploy di Render (filesystem
  ephemeral). Belum dipindah ke S3/Cloudinary.

## Billing
- Belum ada infrastruktur billing/subscription (Stripe/Cashier) — di luar cakupan MVP zero-budget.
