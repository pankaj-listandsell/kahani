<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('part_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('part_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(1); // Card number (1, 2, 3...)
            $table->string('image_path');                       // Card PNG ka path
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('part_cards');
    }
};
