<?php

namespace App\Console\Commands;

use App\Portfolio\Application\UseCase\StartRecalculateEvolutionUseCase;
use App\User\Domain\Entity\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RecalculatePortfolioEvolutionCommand extends Command
{
    private const TIMEZONE = 'America/New_York';

    protected $signature = 'portfolio:recalculate-evolution
                            {userId : User id to recalculate}
                            {year : Calendar year (e.g. 2024)}
                            {month : Calendar month 1-12}';

    protected $description = 'Clear and recalculate portfolio daily snapshots for one user for a specific year/month (creates a snapshot_calculation_process)';

    public function handle(StartRecalculateEvolutionUseCase $startRecalculateEvolutionUseCase): int
    {
        $userId = (int) $this->argument('userId');
        $year = (int) $this->argument('year');
        $month = (int) $this->argument('month');

        if ($userId < 1) {
            $this->error('userId must be a positive integer.');

            return Command::FAILURE;
        }

        if ($month < 1 || $month > 12) {
            $this->error('month must be between 1 and 12.');

            return Command::FAILURE;
        }

        if ($year < 2000 || $year > 2100) {
            $this->error('year looks invalid.');

            return Command::FAILURE;
        }

        if (! User::query()->whereKey($userId)->exists()) {
            $this->error("User {$userId} does not exist.");

            return Command::FAILURE;
        }

        $fromDate = Carbon::create($year, $month, 1, 0, 0, 0, self::TIMEZONE)->toDateString();
        $untilDate = Carbon::create($year, $month, 1, 0, 0, 0, self::TIMEZONE)->endOfMonth()->toDateString();
        $yesterday = Carbon::now(self::TIMEZONE)->subDay()->toDateString();
        if ($untilDate > $yesterday) {
            $untilDate = $yesterday;
        }

        $this->info("Clearing cache + daily snapshots for user {$userId} from {$fromDate} to {$untilDate}, then recalculating…");

        $result = $startRecalculateEvolutionUseCase->execute($userId, $fromDate, $untilDate);

        $this->info("Process #{$result['process_id']} started.");
        $this->line('  range: ' . $fromDate . ' → ' . $untilDate);
        $this->line('  started_from: ' . ($result['started_from'] ?? 'null'));
        $this->line('  until: ' . ($result['until'] ?? 'null'));
        $this->line("  deleted_snapshots: {$result['deleted']}");
        $this->comment('Cache keys portfolio_as_of_view:{userId}:{date} cleared for every day in that range.');
        $this->comment('Jobs run on queue_one — keep the queue worker running.');

        return Command::SUCCESS;
    }
}
