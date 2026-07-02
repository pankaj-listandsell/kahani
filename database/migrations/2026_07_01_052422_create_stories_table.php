<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stories', function (Blueprint $table) {
            $table->id();
            $table->string('title');                        // Kahani ka naam (Hindi)
            $table->string('slug')->unique();               // URL ke liye
            $table->text('description')->nullable();         // Chhota parichay
            $table->string('cover_image')->nullable();       // Cover image path
            $table->string('status')->default('published');  // draft / published
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stories');
    }
};
