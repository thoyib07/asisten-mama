<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bill_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bill_id')->constrained()->cascadeOnDelete();
            // Occurrence mana yang dibayar — ini yang jadi EXDATE di feed ICS.
            $table->date('period_on');
            $table->decimal('amount', 12, 2);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Jejak ke Transaction yang dibuat di Finance. Nullable supaya transaksi boleh
            // dihapus dari Finance tanpa ikut menghapus riwayat pembayaran tagihan.
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('paid_at');
            $table->timestamps();

            // Satu periode tidak bisa dibayar dua kali.
            $table->unique(['bill_id', 'period_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_payments');
    }
};
