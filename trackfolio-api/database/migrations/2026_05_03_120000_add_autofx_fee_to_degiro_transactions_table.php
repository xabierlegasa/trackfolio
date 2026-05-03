<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * AutoFX fee in hundredths of the currency unit (céntimos): e.g. -5,88 EUR -> -588.
     */
    public function up(): void
    {
        Schema::table('degiro_transactions', function (Blueprint $table) {
            $table->bigInteger('autofx_fee')->nullable()->after('exchange_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('degiro_transactions', function (Blueprint $table) {
            $table->dropColumn('autofx_fee');
        });
    }
};
