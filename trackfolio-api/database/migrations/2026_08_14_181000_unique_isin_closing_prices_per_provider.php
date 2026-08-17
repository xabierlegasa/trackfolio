<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Fresh installs already get unique(isin, closing_date, provider) from the create
     * table migration; this only upgrades DBs that still have the old 2-column unique.
     */
    public function up(): void
    {
        Schema::table('isin_closing_prices', function (Blueprint $table) {
            if (Schema::hasIndex('isin_closing_prices', 'isin_closing_prices_isin_closing_date_unique')) {
                $table->dropUnique(['isin', 'closing_date']);
            }

            if (! Schema::hasIndex('isin_closing_prices', 'isin_closing_prices_isin_closing_date_provider_unique')) {
                $table->unique(['isin', 'closing_date', 'provider']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('isin_closing_prices', function (Blueprint $table) {
            if (Schema::hasIndex('isin_closing_prices', 'isin_closing_prices_isin_closing_date_provider_unique')) {
                $table->dropUnique(['isin', 'closing_date', 'provider']);
            }

            if (! Schema::hasIndex('isin_closing_prices', 'isin_closing_prices_isin_closing_date_unique')) {
                $table->unique(['isin', 'closing_date']);
            }
        });
    }
};
