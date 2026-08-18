<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->date('due_on')->nullable();
            // ponytail: string biasa + daftar konstanta di model, bukan enum DB — menambah
            // prioritas baru nanti tidak perlu migrasi ALTER TYPE di Postgres.
            $table->string('priority')->default('sedang');
            $table->boolean('is_done')->default(false);
            $table->timestamps();

            $table->index(['household_id', 'is_done', 'due_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
