<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->unsignedSmallInteger('duration_minutes')->nullable()->after('servings');
            $table->json('nutrition')->nullable()->after('duration_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropColumn(['duration_minutes', 'nutrition']);
        });
    }
};
