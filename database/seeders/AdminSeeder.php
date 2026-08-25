<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;

/**
 * Membuat admin SaaS pertama dari environment variable.
 *
 * Alasannya konkret: Render paket gratis tidak punya shell, jadi `php artisan make:saas-admin`
 * tidak bisa dijalankan setelah deploy — tanpa seeder ini tabel `admins` akan kosong selamanya
 * dan backoffice-nya terpasang tapi tidak bisa dimasuki siapa pun, termasuk pemiliknya.
 *
 * Hanya jalan kalau tabel `admins` benar-benar kosong. Itu membuatnya aman dijalankan tiap boot
 * (Dockerfile CMD memanggilnya setiap kali container hidup), dan sekaligus jadi jalur pemulihan:
 * kalau suatu saat semua admin hilang, set lagi env var-nya lalu redeploy.
 *
 * Hapus ADMIN_PASSWORD dari dashboard setelah akunnya terbentuk.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        if (Admin::count() > 0) {
            return;
        }

        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (blank($email) || blank($password)) {
            $this->command?->warn('AdminSeeder dilewati: ADMIN_EMAIL / ADMIN_PASSWORD belum diset.');

            return;
        }

        Admin::create([
            'name' => env('ADMIN_NAME', 'Owner'),
            'email' => $email,
            'password' => $password,
            'role' => Admin::ROLE_OWNER,
        ]);

        $this->command?->info("Admin owner pertama dibuat: {$email}");
    }
}
