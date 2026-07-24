<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            // Kisi dusri website se import ki gayi kahani ka original URL.
            // Duplicate import rokne ke liye (same URL dobara na aaye).
            $table->string('source_url')->nullable()->unique()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->dropUnique(['source_url']);
            $table->dropColumn('source_url');
        });
    }
};
