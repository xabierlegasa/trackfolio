<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * Seed the primary app user for fresh local / NAS databases.
     */
    public function up(): void
    {
        $email = 'xabierlegasa@gmail.com';

        if (DB::table('users')->where('email', $email)->exists()) {
            return;
        }

        DB::table('users')->insert([
            'name' => 'Xabi',
            'email' => $email,
            'password' => Hash::make('xabi'),
            'is_admin' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('users')
            ->where('email', 'xabierlegasa@gmail.com')
            ->delete();
    }
};
