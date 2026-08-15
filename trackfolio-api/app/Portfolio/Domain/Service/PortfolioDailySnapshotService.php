<?php

namespace App\Portfolio\Domain\Service;

use App\Portfolio\Domain\Entity\PortfolioDailySnapshot;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PortfolioDailySnapshotService
{
    /**
     * Persist a daily snapshot for the given market/as-of date if it does not exist yet.
     * Prefer the portfolio closing date (same as "Data as of"), not the calendar clock.
     *
     * @param array{
     *   balance_eur_min_unit: int,
     *   portfolio_eur_min_unit: int,
     *   leverage_eur_min_unit: int,
     *   day_change_eur_min_unit: int|null,
     *   total_gain_loss_eur_min_unit: int|null
     * } $metrics
     */
    public function ensureForDate(int $userId, string $snapshotDate, array $metrics): PortfolioDailySnapshot
    {
        $existing = PortfolioDailySnapshot::query()
            ->where('user_id', $userId)
            ->whereDate('snapshot_date', $snapshotDate)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return PortfolioDailySnapshot::query()->create([
            'user_id' => $userId,
            'snapshot_date' => $snapshotDate,
            'balance_eur_min_unit' => (int) $metrics['balance_eur_min_unit'],
            'portfolio_eur_min_unit' => (int) $metrics['portfolio_eur_min_unit'],
            'leverage_eur_min_unit' => max(0, (int) $metrics['leverage_eur_min_unit']),
            'day_change_eur_min_unit' => $metrics['day_change_eur_min_unit'],
            'total_gain_loss_eur_min_unit' => $metrics['total_gain_loss_eur_min_unit'],
        ]);
    }

    /**
     * @deprecated Use ensureForDate with the market closing date.
     *
     * @param array{
     *   balance_eur_min_unit: int,
     *   portfolio_eur_min_unit: int,
     *   leverage_eur_min_unit: int,
     *   day_change_eur_min_unit: int|null,
     *   total_gain_loss_eur_min_unit: int|null
     * } $metrics
     */
    public function ensureToday(int $userId, array $metrics): PortfolioDailySnapshot
    {
        $today = Carbon::today(config('app.timezone', 'UTC'))->toDateString();

        return $this->ensureForDate($userId, $today, $metrics);
    }

    /**
     * @return Collection<int, PortfolioDailySnapshot>
     */
    public function listForUserSince(int $userId, Carbon $fromDate): Collection
    {
        return PortfolioDailySnapshot::query()
            ->where('user_id', $userId)
            ->whereDate('snapshot_date', '>=', $fromDate->toDateString())
            ->orderBy('snapshot_date')
            ->get();
    }
}
