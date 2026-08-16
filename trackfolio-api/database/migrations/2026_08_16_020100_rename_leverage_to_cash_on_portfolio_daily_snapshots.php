<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portfolio_daily_snapshots', function (Blueprint $table) {
            $table->bigInteger('cash_eur_min_unit')->default(0)->after('portfolio_eur_min_unit');
        });

        if (Schema::hasColumn('portfolio_daily_snapshots', 'leverage_eur_min_unit')) {
            DB::statement('UPDATE portfolio_daily_snapshots SET cash_eur_min_unit = -CAST(leverage_eur_min_unit AS SIGNED)');
            Schema::table('portfolio_daily_snapshots', function (Blueprint $table) {
                $table->dropColumn('leverage_eur_min_unit');
            });
        }
    }

    public function down(): void
    {
        Schema::table('portfolio_daily_snapshots', function (Blueprint $table) {
            $table->unsignedBigInteger('leverage_eur_min_unit')->default(0)->after('portfolio_eur_min_unit');
        });

        if (Schema::hasColumn('portfolio_daily_snapshots', 'cash_eur_min_unit')) {
            DB::statement('UPDATE portfolio_daily_snapshots SET leverage_eur_min_unit = CASE WHEN cash_eur_min_unit < 0 THEN CAST(-cash_eur_min_unit AS UNSIGNED) ELSE 0 END');
            Schema::table('portfolio_daily_snapshots', function (Blueprint $table) {
                $table->dropColumn('cash_eur_min_unit');
            });
        }
    }
};
