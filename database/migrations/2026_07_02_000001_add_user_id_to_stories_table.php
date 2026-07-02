<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')
                ->constrained()->nullOnDelete();
        });

        // Purani saari stories ko pehle admin ko de do (owner).
        $adminId = DB::table('users')->where('role', 'admin')->min('id')
            ?? DB::table('users')->min('id');

        if ($adminId) {
            DB::table('stories')->whereNull('user_id')->update(['user_id' => $adminId]);
        }
    }

    public function down(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
