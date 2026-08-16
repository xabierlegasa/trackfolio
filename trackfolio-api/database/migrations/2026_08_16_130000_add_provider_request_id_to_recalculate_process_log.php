<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recalculate_portfolio_daily_snapshots_process_log', function (Blueprint $table) {
            $table->unsignedBigInteger('provider_request_id')->nullable()->after('symbol');
            $table->foreign('provider_request_id', 'rpds_process_log_provider_request_fk')
                ->references('id')
                ->on('provider_requests')
                ->nullOnDelete();
            $table->index('provider_request_id', 'rpds_process_log_provider_request_idx');
        });
    }

    public function down(): void
    {
        Schema::table('recalculate_portfolio_daily_snapshots_process_log', function (Blueprint $table) {
            $table->dropForeign('rpds_process_log_provider_request_fk');
            $table->dropIndex('rpds_process_log_provider_request_idx');
            $table->dropColumn('provider_request_id');
        });
    }
};
