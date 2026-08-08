<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->string('invite_code', 8)->nullable()->unique()->after('name');
        });

        foreach (DB::table('households')->whereNull('invite_code')->pluck('id') as $id) {
            DB::table('households')->where('id', $id)->update([
                'invite_code' => $this->generateUniqueCode(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->dropColumn('invite_code');
        });
    }

    private function generateUniqueCode(): string
    {
        do {
            $code = Str::upper(Str::random(8));
        } while (DB::table('households')->where('invite_code', $code)->exists());

        return $code;
    }
};
