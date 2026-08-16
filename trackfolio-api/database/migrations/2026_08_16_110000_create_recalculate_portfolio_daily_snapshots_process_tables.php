<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recalculate_portfolio_daily_snapshots_processes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 32)->default('running');
            $table->date('started_from')->nullable();
            $table->unsignedInteger('deleted_snapshots')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at'], 'rpds_processes_user_created_idx');
            $table->index('status', 'rpds_processes_status_idx');
        });

        Schema::create('recalculate_portfolio_daily_snapshots_process_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('recalculate_portfolio_daily_snapshots_processes_id');
            $table->text('description');
            $table->date('date_processed')->nullable();
            $table->string('isin', 32)->nullable();
            $table->string('symbol', 64)->nullable();
            $table->timestamps();

            $table->foreign(
                'recalculate_portfolio_daily_snapshots_processes_id',
                'rpds_process_log_process_fk'
            )
                ->references('id')
                ->on('recalculate_portfolio_daily_snapshots_processes')
                ->cascadeOnDelete();

            $table->index(
                'recalculate_portfolio_daily_snapshots_processes_id',
                'rpds_process_log_process_idx'
            );
            $table->index('date_processed', 'rpds_process_log_date_idx');
            $table->index('isin', 'rpds_process_log_isin_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recalculate_portfolio_daily_snapshots_process_log');
        Schema::dropIfExists('recalculate_portfolio_daily_snapshots_processes');
    }
};
