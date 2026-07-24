# asisten-mama
Ibu adalah CEO rumah tangga yang bekerja 24/7. Proyek ini dibuat sebagai asisten pintar yang membantu mengatur, mengingatkan, dan mendukung berbagai aktivitas keluarga agar urusan rumah tetap terkendali tanpa perlu jurus seribu bayangan.

## Stack
Laravel 13 + Filament 5 + Livewire 4 + Tailwind 4, PHP 8.3, PostgreSQL. Arsitektur modular (`app/Modules/{Cooking,ShoppingList,Finance}`) di atas fondasi multi-tenant per-keluarga (Household). PWA, mobile-friendly, dibangun & di-deploy zero-budget (Docker, Render, Neon, Groq AI).

## Modul MVP
- **Cooking** — pencarian resep berdasarkan bahan yang tersedia, dibantu AI (Groq) untuk resep tambahan.
- **Shopping List** — daftar belanja keluarga, termasuk auto-tambah bahan yang kurang dari hasil pencarian resep.
- **Finance** — pencatatan keuangan keluarga (pemasukan/pengeluaran) per household.

Modul lanjutan (edukasi anak, perawatan anak sakit, pengingat tagihan) direncanakan menyusul di atas fondasi yang sama.
