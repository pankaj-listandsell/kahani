<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Har post (card × platform) ke asli numbers — `analytics:fetch` command
 * Instagram / YouTube se laakar yahan latest snapshot rakhta hai.
 * null = platform ne ye number nahi diya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('part_card_id')->constrained('part_cards')->cascadeOnDelete();
            $table->string('platform', 4); // ig | yt
            $table->unsignedBigInteger('views')->nullable();
            $table->unsignedBigInteger('reach')->nullable();
            $table->unsignedBigInteger('likes')->nullable();
            $table->unsignedBigInteger('comments')->nullable();
            $table->unsignedBigInteger('saves')->nullable();
            $table->unsignedBigInteger('shares')->nullable();
            $table->string('permalink', 500)->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();

            $table->unique(['part_card_id', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_metrics');
    }
};
