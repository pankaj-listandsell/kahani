<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('part_cards', function (Blueprint $table) {
            $table->string('fb_status')->nullable();     // null | posted | failed
            $table->string('fb_post_id')->nullable();    // Facebook post/video id
            $table->timestamp('fb_posted_at')->nullable();
            $table->text('fb_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('part_cards', function (Blueprint $table) {
            $table->dropColumn(['fb_status', 'fb_post_id', 'fb_posted_at', 'fb_error']);
        });
    }
};
