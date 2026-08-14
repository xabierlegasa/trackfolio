<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('isin_closing_prices', 'close_price_ten_thousandths')) {
            return;
        }

        Schema::table('isin_closing_prices', function (Blueprint $table) {
            $table->renameColumn('close_price_ten_thousandths', 'close_price_min_unit');
        });

        // Convert ten-thousandths → cents (divide by 100)
        DB::table('isin_closing_prices')->update([
            'close_price_min_unit' => DB::raw('ROUND(close_price_min_unit / 100)'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('isin_closing_prices', 'close_price_min_unit')) {
            return;
        }

        // Convert cents → ten-thousandths (multiply by 100)
        DB::table('isin_closing_prices')->update([
            'close_price_min_unit' => DB::raw('close_price_min_unit * 100'),
        ]);

        Schema::table('isin_closing_prices', function (Blueprint $table) {
            $table->renameColumn('close_price_min_unit', 'close_price_ten_thousandths');
        });
    }
};
