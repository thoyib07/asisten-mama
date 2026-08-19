<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            // Kantong tujuan saat tagihan ditandai lunas. Nullable: kalau kosong, jatuh ke
            // kategori "Tagihan" bawaan household saat pencatatan.
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            // Perkiraan, bukan nominal final — listrik/air berubah tiap periode. Nominal asli
            // diisi saat tandai lunas (bill_payments.amount).
            $table->decimal('amount_estimate', 12, 2)->nullable();
            // String RRULE (RFC 5545) apa adanya; null = tagihan sekali jalan.
            $table->string('rrule')->nullable();
            $table->date('starts_on');
            $table->unsignedSmallInteger('reminder_days_before')->default(2);
            $table->text('notes')->nullable();
            // Soft-hide, bukan SoftDeletes bawaan — pola yang sama dengan rencana categories
            // di docs/prd/finance.md §6.8, supaya tidak tertukar makna dengan hapus permanen.
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['household_id', 'starts_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};
