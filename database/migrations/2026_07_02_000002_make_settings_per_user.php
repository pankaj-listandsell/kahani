<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Purani (global) settings ko pehle admin ke naam kar denge.
        $adminId = DB::table('users')->where('role', 'admin')->min('id')
            ?? DB::table('users')->min('id');

        // 'key' primary key hata do (ab per-user hone se key unique nahi rahega).
        Schema::table('settings', function (Blueprint $table) {
            $table->dropPrimary();
        });

        // Auto-increment id + user_id add karo.
        Schema::table('settings', function (Blueprint $table) {
            $table->id()->first();
            $table->foreignId('user_id')->nullable()->after('id')
                ->constrained()->cascadeOnDelete();
        });

        // Existing rows admin ko de do.
        if ($adminId) {
            DB::table('settings')->whereNull('user_id')->update(['user_id' => $adminId]);
        }

        // Ek user ke andar key unique honi chahiye.
        Schema::table('settings', function (Blueprint $table) {
            $table->unique(['user_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'key']);
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn('id');
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->primary('key');
        });
    }
};
