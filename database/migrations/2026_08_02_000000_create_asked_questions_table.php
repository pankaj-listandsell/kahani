<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jo quiz sawaal ek baar post ho chuke hain unka permanent record.
 *
 * Ye table stories/cards se JAAN-BOOJH KAR alag hai (koi foreign key nahi) —
 * collection delete karne par bhi record bacha rehta hai, taaki wahi sawaal
 * dobara kabhi generate na ho.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asked_questions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('topic')->default('')->index();   // lowercase, taaki match aasan ho
            $table->string('language', 20)->default('hindi');
            $table->text('question');
            // Normalized sawaal ka sha1 — chhote-mote farak (emoji, spacing,
            // punctuation) ke baawajood ek hi sawaal ek hi hash deta hai
            $table->string('hash', 40);
            $table->timestamps();

            // Ek user ke liye ek sawaal ek hi baar
            $table->unique(['user_id', 'hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asked_questions');
    }
};
