<?php

namespace App\Console\Commands;

use App\Isin\Domain\Entity\IsinQuote;
use App\Isin\Domain\Service\StockApiService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Removes Finnhub isin_quotes stamped onto past dates (live /quote misused as historical).
 */
class PurgeInvalidFinnhubQuotesCommand extends Command
{
    protected $signature = 'isin-quotes:purge-invalid-finnhub
                            {--dry-run : Show how many rows would be deleted without deleting}';

    protected $description = 'Delete Finnhub closing quotes for dates older than yesterday UTC (they are not real historical prices)';

    public function handle(): int
    {
        $cutoff = Carbon::yesterday('UTC')->toDateString();

        $query = IsinQuote::query()
            ->where('provider', StockApiService::PROVIDER_FINNHUB)
            ->whereDate('closing_date', '<', $cutoff);

        $count = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->info("Would delete {$count} Finnhub isin_quotes with closing_date < {$cutoff}");

            return self::SUCCESS;
        }

        $deleted = $query->delete();
        $this->info("Deleted {$deleted} Finnhub isin_quotes with closing_date < {$cutoff}");
        $this->warn('Re-run Portfolio Evolution recalculation so daily snapshots are rebuilt with FMP/Alpha Vantage historical prices.');

        return self::SUCCESS;
    }
}
