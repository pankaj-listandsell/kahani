<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quiz card par answer-reveal ka data.
 *
 * Ek quiz card = ek question. Ab uske saath answer wali image bhi rehti hai,
 * taaki ek hi reel me: question + countdown timer → answer reveal (jaisa
 * viral quiz reels me hota hai). Purane cards me ye null rehte hain — unka
 * behaviour bilkul pehle jaisa hi chalta hai.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('part_cards', function (Blueprint $table) {
            $table->string('answer_image_path')->nullable()->after('image_path');
            $table->text('answer_text')->nullable()->after('text'); // answer-reveal ki voice
        });
    }

    public function down(): void
    {
        Schema::table('part_cards', function (Blueprint $table) {
            $table->dropColumn(['answer_image_path', 'answer_text']);
        });
    }
};
