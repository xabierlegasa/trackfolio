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
        Schema::create('isin_closing_prices', function (Blueprint $table) {
            $table->id();
            $table->string('isin');
            $table->string('ticker_symbol');
            $table->date('closing_date');
            $table->bigInteger('close_price_min_unit')->nullable();
            $table->bigInteger('open_price_min_unit')->nullable();
            $table->bigInteger('high_price_min_unit')->nullable();
            $table->bigInteger('low_price_min_unit')->nullable();
            $table->unsignedBigInteger('volume')->nullable();
            $table->string('currency')->nullable();
            $table->string('stock_exchange')->nullable();
            $table->string('provider');
            $table->foreignId('ticker_request_id')->nullable()->constrained('ticker_requests')->nullOnDelete();

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['isin', 'closing_date', 'provider']);
            $table->index('isin');
            $table->index('closing_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('isin_closing_prices');
    }
};
