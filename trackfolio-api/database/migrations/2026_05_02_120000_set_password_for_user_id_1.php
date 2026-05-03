<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * Set password for user id 1 (xabierlegasa@gmail.com) to "xabi" (stored hashed).
     */
    public function up(): void
    {
        $affected = DB::table('users')
            ->where('email', 'xabierlegasa@gmail.com')
            ->update([
                'password' => Hash::make('xabi'),
                'updated_at' => now(),
            ]);

        if ($affected === 0) {
            throw new \RuntimeException(
                'No row updated: expected user id 1 with email xabierlegasa@gmail.com.'
            );
        }
    }

    /**
     * Previous password cannot be restored.
     */
    public function down(): void {}
};
