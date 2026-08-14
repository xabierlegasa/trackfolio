<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('isin_closing_prices') && ! Schema::hasTable('isin_quotes')) {
            Schema::rename('isin_closing_prices', 'isin_quotes');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('isin_quotes') && ! Schema::hasTable('isin_closing_prices')) {
            Schema::rename('isin_quotes', 'isin_closing_prices');
        }
    }
};
