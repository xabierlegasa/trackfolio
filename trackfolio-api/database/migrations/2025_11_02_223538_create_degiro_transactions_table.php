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
        Schema::create('degiro_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('date'); // Date column (e.g., 06-12-2017) - NOT NULL
            $table->string('time'); // Time column (e.g., 21:23) - NOT NULL
            $table->string('product'); // Product name - NOT NULL
            $table->string('isin'); // ISIN code - NOT NULL
            $table->string('reference'); // Reference (e.g., NSY, NDQ) - NOT NULL
            $table->string('venue')->nullable(); // Venue - nullable (empty in sample)
            $table->integer('quantity'); // Quantity - NOT NULL
            $table->bigInteger('price_min_unit'); // Price in smallest currency unit (cents), e.g., "147,6800" USD -> 14768 - NOT NULL
            $table->string('price_currency'); // Currency after Price column - NOT NULL
            $table->bigInteger('local_value_min_unit'); // Local value in smallest currency unit (cents), e.g., "-147,68" USD -> -14768 - NOT NULL
            $table->string('local_value_currency'); // Currency after Local value - NOT NULL
            $table->bigInteger('value_min_unit'); // Value in smallest currency unit (cents), e.g., "-125,23" EUR -> -12523 - NOT NULL
            $table->string('value_currency'); // Currency after Value - NOT NULL
            $table->string('exchange_rate'); // Exchange rate (e.g., "1,18") - NOT NULL
            $table->string('transaction_and_or_third')->nullable(); // Transaction and/or third (e.g., "-0,50") - nullable
            $table->string('transaction_currency')->nullable(); // Currency after Transaction and/or third - nullable (null if transaction_and_or_third is null)
            $table->bigInteger('total_min_unit'); // Total in smallest currency unit (cents), e.g., "-125,73" EUR -> -12573 - NOT NULL
            $table->string('total_currency'); // Currency after Total - NOT NULL
            $table->string('order_id'); // Order ID (UUID) - NOT NULL (not unique, can be repeated)
            $table->string('custom_content_hash'); // Hash of all column values for duplicate detection - NOT NULL
            
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            
            // Index for faster queries
            $table->index('user_id');
            $table->index('date');
            $table->index('custom_content_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('degiro_transactions');
    }
};
