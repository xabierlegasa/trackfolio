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
        Schema::create('portfolio_daily_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('snapshot_date');
            $table->bigInteger('balance_eur_min_unit');
            $table->bigInteger('portfolio_eur_min_unit');
            $table->unsignedBigInteger('leverage_eur_min_unit')->default(0);
            $table->bigInteger('day_change_eur_min_unit')->nullable();
            $table->bigInteger('total_gain_loss_eur_min_unit')->nullable();

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['user_id', 'snapshot_date']);
            $table->index(['user_id', 'snapshot_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('portfolio_daily_snapshots');
    }
};
