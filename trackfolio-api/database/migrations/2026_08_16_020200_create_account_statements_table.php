<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('time', 16)->nullable();
            $table->date('value_date')->nullable();
            $table->string('product')->nullable();
            $table->string('isin', 32)->nullable();
            $table->text('description')->nullable();
            $table->string('fx', 32)->nullable();
            $table->string('change_currency', 8)->nullable();
            $table->bigInteger('change_min_unit')->nullable();
            $table->string('balance_currency', 8)->nullable();
            $table->bigInteger('balance_min_unit')->nullable();
            $table->string('order_id')->nullable();
            $table->string('custom_content_hash', 64);
            $table->timestamps();

            $table->unique(['user_id', 'custom_content_hash']);
            $table->index(['user_id', 'date', 'balance_currency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_statements');
    }
};
