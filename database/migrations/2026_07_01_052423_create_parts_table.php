<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(1);   // Part number (Bhaag 1, 2, 3...)
            $table->string('title')->nullable();                 // Part ka title (Hindi)
            $table->longText('body');                            // Part ka poora text (Hindi)
            $table->string('image_path')->nullable();            // AI se bani image ka path
            $table->text('image_prompt')->nullable();            // Image banane wala prompt (English)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parts');
    }
};
