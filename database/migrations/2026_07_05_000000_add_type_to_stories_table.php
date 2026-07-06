<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            // Content type: story | shayari | joke | quote
            $table->string('type')->default('story')->after('slug');
            // Topic/mood (love, sad, funny, motivational...)
            $table->string('category')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->dropColumn(['type', 'category']);
        });
    }
};
