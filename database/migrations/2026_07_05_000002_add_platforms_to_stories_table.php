<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            // Kaunse platforms par auto-post ho — JSON array ["instagram","facebook"].
            // null/empty = sab platforms (default, backward-compatible).
            $table->text('platforms')->nullable()->after('tts_mode');
        });
    }

    public function down(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->dropColumn('platforms');
        });
    }
};
