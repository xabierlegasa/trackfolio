<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recalculate_portfolio_daily_snapshots_process_log', function (Blueprint $table) {
            $table->dropForeign('rpds_process_log_process_fk');
            $table->dropForeign('rpds_process_log_provider_request_fk');
        });

        Schema::rename('recalculate_portfolio_daily_snapshots_processes', 'snapshot_calculation_processes');
        Schema::rename('recalculate_portfolio_daily_snapshots_process_log', 'snapshot_calculation_process_logs');

        DB::statement(
            'ALTER TABLE snapshot_calculation_process_logs
             CHANGE recalculate_portfolio_daily_snapshots_processes_id snapshot_calculation_process_id BIGINT UNSIGNED NOT NULL'
        );

        Schema::table('snapshot_calculation_process_logs', function (Blueprint $table) {
            $table->foreign('snapshot_calculation_process_id', 'scp_logs_process_fk')
                ->references('id')
                ->on('snapshot_calculation_processes')
                ->cascadeOnDelete();
            $table->foreign('provider_request_id', 'scp_logs_provider_request_fk')
                ->references('id')
                ->on('provider_requests')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('snapshot_calculation_process_logs', function (Blueprint $table) {
            $table->dropForeign('scp_logs_process_fk');
            $table->dropForeign('scp_logs_provider_request_fk');
        });

        DB::statement(
            'ALTER TABLE snapshot_calculation_process_logs
             CHANGE snapshot_calculation_process_id recalculate_portfolio_daily_snapshots_processes_id BIGINT UNSIGNED NOT NULL'
        );

        Schema::rename('snapshot_calculation_process_logs', 'recalculate_portfolio_daily_snapshots_process_log');
        Schema::rename('snapshot_calculation_processes', 'recalculate_portfolio_daily_snapshots_processes');

        Schema::table('recalculate_portfolio_daily_snapshots_process_log', function (Blueprint $table) {
            $table->foreign(
                'recalculate_portfolio_daily_snapshots_processes_id',
                'rpds_process_log_process_fk'
            )
                ->references('id')
                ->on('recalculate_portfolio_daily_snapshots_processes')
                ->cascadeOnDelete();
            $table->foreign('provider_request_id', 'rpds_process_log_provider_request_fk')
                ->references('id')
                ->on('provider_requests')
                ->nullOnDelete();
        });
    }
};
