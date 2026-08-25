<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Token feed ICS pribadi — feednya berisi agenda keluarga + tugas milik user ini
            // saja, jadi tokennya per-user, bukan memakai ulang households.calendar_token
            // (itu punya modul Tagihan dan isinya sama untuk semua anggota).
            $table->string('calendar_token', 64)->nullable()->unique()->after('current_household_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('calendar_token');
        });
    }
};
