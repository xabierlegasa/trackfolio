<?php

namespace App\Portfolio\Domain\Service;

use App\Portfolio\Domain\Entity\PortfolioDailySnapshot;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Clears cached / stored as-of portfolio views when fresher closing prices arrive.
 */
class InvalidatePortfolioAsOfViewsService
{
    public function forClosingDate(string $closingDate): void
    {
        $closingDate = substr($closingDate, 0, 10);
        if ($closingDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $closingDate)) {
            return;
        }

        $userIds = PortfolioDailySnapshot::query()
            ->whereDate('snapshot_date', $closingDate)
            ->distinct()
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            Cache::forget($this->cacheKey((int) $userId, $closingDate));
        }

        $cleared = PortfolioDailySnapshot::query()
            ->whereDate('snapshot_date', $closingDate)
            ->whereNotNull('view_payload')
            ->update(['view_payload' => null]);

        if ($cleared > 0 || $userIds->isNotEmpty()) {
            Log::info("portfolio.as_of_view invalidated closing_date={$closingDate} payloads_cleared={$cleared} users=" . $userIds->count());
        }
    }

    /**
     * @param list<string> $closingDates
     */
    public function forClosingDates(array $closingDates): void
    {
        $unique = [];
        foreach ($closingDates as $date) {
            $normalized = substr((string) $date, 0, 10);
            if ($normalized !== '') {
                $unique[$normalized] = true;
            }
        }

        foreach (array_keys($unique) as $date) {
            $this->forClosingDate($date);
        }
    }

    private function cacheKey(int $userId, string $asOfDate): string
    {
        return "portfolio_as_of_view:{$userId}:{$asOfDate}";
    }
}
