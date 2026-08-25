<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            // Tanggal eksak mulai periode, bukan string bulan "2026-08": periode household bisa
            // mulai tanggal berapa saja (budget_period_reset_day).
            $table->date('period_start');
            $table->decimal('amount', 12, 2);
            $table->timestamps();

            $table->unique(['category_id', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_allocations');
    }
};
