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
        Schema::table('degiro_transactions', function (Blueprint $table) {
            $table->string('transaction_and_or_third')->nullable()->change();
            $table->string('transaction_currency')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('degiro_transactions', function (Blueprint $table) {
            $table->string('transaction_and_or_third')->nullable(false)->change();
            $table->string('transaction_currency')->nullable(false)->change();
        });
    }
};
