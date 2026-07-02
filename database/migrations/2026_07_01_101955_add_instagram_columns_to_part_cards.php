<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('part_cards', function (Blueprint $table) {
            $table->string('ig_status')->nullable();      // null | posted | failed
            $table->string('ig_media_id')->nullable();    // Instagram media id
            $table->timestamp('ig_posted_at')->nullable();
            $table->text('ig_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('part_cards', function (Blueprint $table) {
            $table->dropColumn(['ig_status', 'ig_media_id', 'ig_posted_at', 'ig_error']);
        });
    }
};
