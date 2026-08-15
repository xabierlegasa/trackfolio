<?php

namespace App\Isin\Domain\Service;

use App\Isin\Domain\Service\Provider\FinnhubProvider;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ResolveLastUsMarketOpenDateService
{
    private const EXCHANGE = 'US';
    private const TIMEZONE = 'America/New_York';
    private const LOOKBACK_DAYS = 14;

    /**
     * Most recent US session that is not today (NY) and was open.
     */
    public function resolve(): ?string
    {
        $todayNy = Carbon::now(self::TIMEZONE)->toDateString();
        $cacheKey = 'last_us_market_open_date:' . $todayNy;

        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '' && $cached < $todayNy) {
            return $cached;
        }

        $resolved = $this->resolveUncached();
        if ($resolved !== null) {
            Cache::put($cacheKey, $resolved, Carbon::now(self::TIMEZONE)->endOfDay());
        }

        return $resolved;
    }

    private function resolveUncached(): ?string
    {
        $provider = $this->tryProvider();
        $timezone = self::TIMEZONE;
        $closedDates = [];

        if ($provider !== null) {
            $status = $provider->getMarketStatus(self::EXCHANGE);
            if (is_array($status) && is_string($status['timezone'] ?? null) && $status['timezone'] !== '') {
                $timezone = $status['timezone'];
            }

            $holiday = $provider->getMarketHoliday(self::EXCHANGE);
            $closedDates = $this->fullCloseDates($holiday);
            if (is_array($holiday) && is_string($holiday['timezone'] ?? null) && $holiday['timezone'] !== '') {
                $timezone = $holiday['timezone'];
            }
        }

        $todayInTz = Carbon::now($timezone)->toDateString();

        return $this->walkBackToOpenDay($timezone, $todayInTz, $closedDates);
    }

    /**
     * @param array<string, mixed>|null $holidayPayload
     * @return array<string, true>
     */
    private function fullCloseDates(?array $holidayPayload): array
    {
        $closed = [];
        $rows = $holidayPayload['data'] ?? [];
        if (!is_array($rows)) {
            return $closed;
        }

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $date = $row['atDate'] ?? null;
            $hours = trim((string) ($row['tradingHour'] ?? ''));
            if (is_string($date) && $date !== '' && $hours === '') {
                $closed[$date] = true;
            }
        }

        return $closed;
    }

    /**
     * @param array<string, true> $closedDates
     */
    private function walkBackToOpenDay(string $timezone, string $today, array $closedDates): ?string
    {
        $cursor = Carbon::parse($today, $timezone)->subDay()->startOfDay();
        for ($i = 0; $i < self::LOOKBACK_DAYS; $i++) {
            $date = $cursor->toDateString();
            if ($cursor->isWeekday() && !isset($closedDates[$date])) {
                return $date;
            }
            $cursor->subDay();
        }

        Log::warning('Could not resolve a US market open date in lookback window');

        return null;
    }

    private function tryProvider(): ?FinnhubProvider
    {
        try {
            return new FinnhubProvider();
        } catch (\Throwable $e) {
            Log::warning('Finnhub provider unavailable: ' . $e->getMessage());

            return null;
        }
    }
}
