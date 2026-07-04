<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('part_cards', function (Blueprint $table) {
            $table->text('yt_caption')->nullable()->after('yt_error');
        });
    }

    public function down(): void
    {
        Schema::table('part_cards', function (Blueprint $table) {
            $table->dropColumn('yt_caption');
        });
    }
};
