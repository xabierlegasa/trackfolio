<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portfolio_daily_snapshots', function (Blueprint $table) {
            $table->json('view_payload')->nullable()->after('total_gain_loss_eur_min_unit');
        });
    }

    public function down(): void
    {
        Schema::table('portfolio_daily_snapshots', function (Blueprint $table) {
            $table->dropColumn('view_payload');
        });
    }
};
