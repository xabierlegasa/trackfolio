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
        Schema::create('ticker_requests', function (Blueprint $table) {
            $table->id();
            $table->string('isin');
            $table->string('ticker_symbol')->nullable();
            $table->date('closing_date')->nullable();
            $table->string('provider');
            $table->string('stock_exchange')->nullable();
            $table->json('response')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedSmallInteger('provider_response_http_status')->nullable();
            $table->boolean('success')->default(false);

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index('isin');
            $table->index('provider');
            $table->index('closing_date');
            $table->index('success');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticker_requests');
    }
};
