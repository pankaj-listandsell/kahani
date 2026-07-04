<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('part_cards', function (Blueprint $table) {
            $table->string('yt_status')->nullable();       // null | posted | failed
            $table->string('yt_video_id')->nullable();     // YouTube video id
            $table->timestamp('yt_posted_at')->nullable();
            $table->text('yt_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('part_cards', function (Blueprint $table) {
            $table->dropColumn(['yt_status', 'yt_video_id', 'yt_posted_at', 'yt_error']);
        });
    }
};
