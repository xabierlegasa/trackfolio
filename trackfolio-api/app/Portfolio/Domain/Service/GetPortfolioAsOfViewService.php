<?php

namespace App\Portfolio\Domain\Service;

use App\Isin\Domain\Entity\IsinQuote;
use App\Isin\Domain\Service\ResolveIsinClosingPriceService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Loads the portfolio as-of view from cache/DB, rebuilding when exact closes became available.
 */
class GetPortfolioAsOfViewService
{
    public function __construct(
        private BuildPortfolioStatsAsOfService $buildPortfolioStatsAsOfService,
        private PortfolioDailySnapshotService $portfolioDailySnapshotService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function get(int $userId, string $asOfDate): array
    {
        $cacheKey = $this->cacheKey($userId, $asOfDate);

        $cached = Cache::get($cacheKey);
        if (is_array($cached) && isset($cached['holdings'])) {
            if (!$this->shouldRebuild($cached, $asOfDate)) {
                return $cached;
            }
            Cache::forget($cacheKey);
            Log::info("portfolio.as_of_view rebuild_from_cache user_id={$userId} as_of={$asOfDate}");
        }

        $fromDb = $this->portfolioDailySnapshotService->findViewPayload($userId, $asOfDate);
        if (is_array($fromDb) && isset($fromDb['holdings']) && !$this->shouldRebuild($fromDb, $asOfDate)) {
            Cache::forever($cacheKey, $fromDb);

            return $fromDb;
        }

        if (is_array($fromDb) && isset($fromDb['holdings'])) {
            Log::info("portfolio.as_of_view rebuild_from_db user_id={$userId} as_of={$asOfDate}");
        }

        $payload = $this->buildPortfolioStatsAsOfService->build($userId, $asOfDate);
        $metrics = is_array($payload['metrics'] ?? null) ? $payload['metrics'] : [];
        unset($payload['metrics']);

        $this->portfolioDailySnapshotService->replaceView($userId, $asOfDate, $metrics, $payload);
        Cache::forever($cacheKey, $payload);

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function shouldRebuild(array $payload, string $asOfDate): bool
    {
        if (($payload['closes_complete'] ?? false) === true) {
            return false;
        }

        $holdings = is_array($payload['holdings'] ?? null) ? $payload['holdings'] : [];
        foreach ($holdings as $holding) {
            if (!is_array($holding)) {
                continue;
            }

            $isin = strtoupper(trim((string) ($holding['isin'] ?? '')));
            if ($isin === '') {
                continue;
            }

            $closingDate = $holding['closing_date'] ?? null;
            if ($closingDate === $asOfDate) {
                continue;
            }

            if ($this->exactCloseExists($isin, $asOfDate)) {
                return true;
            }
        }

        return false;
    }

    private function exactCloseExists(string $isin, string $asOfDate): bool
    {
        return IsinQuote::query()
            ->where('isin', $isin)
            ->whereDate('closing_date', $asOfDate)
            ->whereIn('provider', ResolveIsinClosingPriceService::providerOrder())
            ->whereNotNull('close_price_min_unit')
            ->exists();
    }

    private function cacheKey(int $userId, string $asOfDate): string
    {
        return "portfolio_as_of_view:{$userId}:{$asOfDate}";
    }
}
