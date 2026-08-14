<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('isin_closing_prices', function (Blueprint $table) {
            $table->dropUnique(['isin', 'closing_date']);
            $table->unique(['isin', 'closing_date', 'provider']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('isin_closing_prices', function (Blueprint $table) {
            $table->dropUnique(['isin', 'closing_date', 'provider']);
            $table->unique(['isin', 'closing_date']);
        });
    }
};
