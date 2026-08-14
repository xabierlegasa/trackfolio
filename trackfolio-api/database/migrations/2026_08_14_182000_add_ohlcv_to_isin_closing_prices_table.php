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
        Schema::table('isin_closing_prices', function (Blueprint $table) {
            if (! Schema::hasColumn('isin_closing_prices', 'open_price_min_unit')) {
                $table->bigInteger('open_price_min_unit')->nullable()->after('close_price_min_unit');
            }
            if (! Schema::hasColumn('isin_closing_prices', 'high_price_min_unit')) {
                $table->bigInteger('high_price_min_unit')->nullable()->after('open_price_min_unit');
            }
            if (! Schema::hasColumn('isin_closing_prices', 'low_price_min_unit')) {
                $table->bigInteger('low_price_min_unit')->nullable()->after('high_price_min_unit');
            }
            if (! Schema::hasColumn('isin_closing_prices', 'volume')) {
                $table->unsignedBigInteger('volume')->nullable()->after('low_price_min_unit');
            }
        });

        // Make close nullable without doctrine/dbal change()
        DB::statement('ALTER TABLE isin_closing_prices MODIFY close_price_min_unit BIGINT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('isin_closing_prices', function (Blueprint $table) {
            $table->dropColumn(['open_price_min_unit', 'high_price_min_unit', 'low_price_min_unit', 'volume']);
        });

        DB::statement('ALTER TABLE isin_closing_prices MODIFY close_price_min_unit BIGINT NOT NULL');
    }
};
