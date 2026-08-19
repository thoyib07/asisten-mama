<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('households', function (Blueprint $table) {
            // Token URL feed ICS. Kolom permanen yang bisa di-regenerate (= tombol "cabut
            // akses"), bukan signed URL Laravel yang punya masa berlaku.
            $table->string('calendar_token', 64)->nullable()->unique()->after('invite_code');
        });
    }

    public function down(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->dropColumn('calendar_token');
        });
    }
};
