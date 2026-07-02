<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('part_cards', function (Blueprint $table) {
            // AI se bani (ya haath se likhi) caption — post karte waqt yahi use hogi
            $table->text('ig_caption')->nullable()->after('image_path');
        });
    }

    public function down(): void
    {
        Schema::table('part_cards', function (Blueprint $table) {
            $table->dropColumn('ig_caption');
        });
    }
};
