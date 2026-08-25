<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('households', function (Blueprint $table) {
            // 1–28 saja (bukan 31): tanggal 29–31 tidak ada di tiap bulan, dan periode kantong
            // harus punya batas yang sama persis tiap bulan termasuk Februari.
            $table->unsignedTinyInteger('budget_period_reset_day')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->dropColumn('budget_period_reset_day');
        });
    }
};
