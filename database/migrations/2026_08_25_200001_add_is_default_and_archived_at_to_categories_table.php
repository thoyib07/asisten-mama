<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('is_default')->default(false);
            // archived_at, bukan deleted_at/SoftDeletes: arsip di sini berarti "sembunyikan dari
            // dropdown", transaksi & alokasi lamanya tetap utuh. Jangan tertukar dengan hapus.
            $table->timestamp('archived_at')->nullable();
        });

        // DefaultCategories::seedFor() satu-satunya penulis categories sampai migration ini,
        // jadi semua baris yang sudah ada memang kategori default — tidak perlu cocokkan nama.
        DB::table('categories')->update(['is_default' => true]);
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['is_default', 'archived_at']);
        });
    }
};
