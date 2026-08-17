<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Move admin flags from admin_users into users.is_admin, then drop admin_users.
     */
    public function up(): void
    {
        if (! Schema::hasTable('admin_users') || ! Schema::hasColumn('users', 'is_admin')) {
            return;
        }

        $adminUserIds = DB::table('admin_users')->pluck('user_id');
        if ($adminUserIds->isNotEmpty()) {
            DB::table('users')
                ->whereIn('id', $adminUserIds)
                ->update(['is_admin' => true]);
        }

        // Ensure seeded primary user remains admin after the column introduction.
        DB::table('users')
            ->where('email', 'xabierlegasa@gmail.com')
            ->update(['is_admin' => true]);

        Schema::dropIfExists('admin_users');
    }

    public function down(): void
    {
        if (! Schema::hasTable('admin_users')) {
            Schema::create('admin_users', function ($table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('users', 'is_admin')) {
            return;
        }

        $now = now();
        $adminIds = DB::table('users')->where('is_admin', true)->pluck('id');
        foreach ($adminIds as $userId) {
            DB::table('admin_users')->insertOrIgnore([
                'user_id' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
