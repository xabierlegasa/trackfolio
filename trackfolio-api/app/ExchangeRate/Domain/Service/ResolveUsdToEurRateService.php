<?php

namespace App\ExchangeRate\Domain\Service;

use App\ExchangeRate\Domain\Entity\ExchangeRate;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ResolveUsdToEurRateService
{
    private const PROVIDER = 'frankfurter';
    private const API_BASE = 'https://api.frankfurter.app';

    /**
     * USD → EUR rate for today. Uses DB cache; fetches Frankfurter when missing.
     *
     * @return array{rate: float, rate_date: string}|null
     */
    public function resolveToday(): ?array
    {
        return $this->resolvePairToday('USD', 'EUR');
    }

    /**
     * USD → EUR for $rateDate. Uses DB (exact, then on-or-before); fetches Frankfurter when missing.
     *
     * @return array{rate: float, rate_date: string}|null
     */
    public function resolveOnOrBefore(string $rateDate): ?array
    {
        return $this->resolvePairOnOrBefore('USD', 'EUR', $rateDate);
    }

    /**
     * GBP → EUR for $rateDate (DB first, then Frankfurter).
     *
     * @return array{rate: float, rate_date: string}|null
     */
    public function resolveGbpToEurOnOrBefore(string $rateDate): ?array
    {
        return $this->resolvePairOnOrBefore('GBP', 'EUR', $rateDate);
    }

    /**
     * USD → EUR rate for today. Uses DB cache; fetches Frankfurter when missing.
     */
    public function forToday(): ?float
    {
        $resolved = $this->resolveToday();

        return $resolved['rate'] ?? null;
    }

    /**
     * @return array{rate: float, rate_date: string}|null
     */
    public function resolvePairOnOrBefore(string $baseCurrency, string $quoteCurrency, string $rateDate): ?array
    {
        $base = strtoupper(trim($baseCurrency));
        $quote = strtoupper(trim($quoteCurrency));
        $today = Carbon::today()->toDateString();

        if ($rateDate === $today) {
            return $this->resolvePairToday($base, $quote);
        }

        $exact = ExchangeRate::query()
            ->whereDate('rate_date', $rateDate)
            ->where('base_currency', $base)
            ->where('quote_currency', $quote)
            ->first();

        if ($exact !== null && (float) $exact->rate > 0) {
            return [
                'rate' => (float) $exact->rate,
                'rate_date' => $this->asOfDateFromRow($exact),
            ];
        }

        $latestBefore = ExchangeRate::query()
            ->where('base_currency', $base)
            ->where('quote_currency', $quote)
            ->whereDate('rate_date', '<=', $rateDate)
            ->orderByDesc('rate_date')
            ->first();

        if ($latestBefore !== null && (float) $latestBefore->rate > 0) {
            return [
                'rate' => (float) $latestBefore->rate,
                'rate_date' => $this->asOfDateFromRow($latestBefore),
            ];
        }

        return $this->fetchAndPersistPair($base, $quote, $rateDate);
    }

    /**
     * @return array{rate: float, rate_date: string}|null
     */
    private function resolvePairToday(string $baseCurrency, string $quoteCurrency): ?array
    {
        $base = strtoupper(trim($baseCurrency));
        $quote = strtoupper(trim($quoteCurrency));
        $today = Carbon::today()->toDateString();

        $cached = ExchangeRate::query()
            ->whereDate('rate_date', $today)
            ->where('base_currency', $base)
            ->where('quote_currency', $quote)
            ->first();

        if ($cached !== null && (float) $cached->rate > 0) {
            return [
                'rate' => (float) $cached->rate,
                'rate_date' => $this->asOfDateFromRow($cached),
            ];
        }

        return $this->fetchAndPersistPair($base, $quote, $today);
    }

    /**
     * @return array{rate: float, rate_date: string}|null
     */
    private function fetchAndPersistPair(string $baseCurrency, string $quoteCurrency, string $rateDate): ?array
    {
        $payload = $this->fetchPairForDate($baseCurrency, $quoteCurrency, $rateDate);
        if ($payload === null) {
            return null;
        }

        $rates = $payload['rates'] ?? null;
        if (!is_array($rates) || !isset($rates[$quoteCurrency])) {
            Log::warning('frankfurter response missing quote rate', [
                'base' => $baseCurrency,
                'quote' => $quoteCurrency,
                'requested_date' => $rateDate,
                'payload_date' => $payload['date'] ?? null,
            ]);

            return null;
        }

        $rate = (float) $rates[$quoteCurrency];
        if ($rate <= 0) {
            return null;
        }

        $providerDate = $this->asOfDateFromPayload($payload, $rateDate);

        ExchangeRate::query()->updateOrCreate(
            [
                'rate_date' => $rateDate,
                'base_currency' => $baseCurrency,
                'quote_currency' => $quoteCurrency,
            ],
            [
                'rate' => $rate,
                'provider' => self::PROVIDER,
                'response' => $payload,
            ],
        );

        Log::info('exchange_rate.frankfurter persisted', [
            'base' => $baseCurrency,
            'quote' => $quoteCurrency,
            'requested_date' => $rateDate,
            'provider_date' => $providerDate,
            'rate' => $rate,
        ]);

        return [
            'rate' => $rate,
            'rate_date' => $providerDate,
        ];
    }

    private function asOfDateFromRow(ExchangeRate $row): string
    {
        $fallback = $row->rate_date?->format('Y-m-d') ?? Carbon::today()->toDateString();
        $payload = is_array($row->response) ? $row->response : [];

        return $this->asOfDateFromPayload($payload, $fallback);
    }

    /**
     * Prefer the provider's rate date (ECB business day actually returned).
     *
     * @param array<string, mixed> $payload
     */
    private function asOfDateFromPayload(array $payload, string $fallback): string
    {
        $date = $payload['date'] ?? null;
        if (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
            return $date;
        }

        return $fallback;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchPairForDate(string $baseCurrency, string $quoteCurrency, string $rateDate): ?array
    {
        $today = Carbon::today()->toDateString();
        $query = 'from=' . rawurlencode($baseCurrency) . '&to=' . rawurlencode($quoteCurrency);
        $url = $rateDate === $today
            ? self::API_BASE . '/latest?' . $query
            : self::API_BASE . '/' . rawurlencode($rateDate) . '?' . $query;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);

        $body = curl_exec($ch);
        $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($body === false || $curlError !== '' || $httpStatus < 200 || $httpStatus >= 300) {
            Log::warning('Failed to fetch frankfurter FX pair', [
                'url' => $url,
                'base' => $baseCurrency,
                'quote' => $quoteCurrency,
                'requested_date' => $rateDate,
                'http_status' => $httpStatus,
                'curl_error' => $curlError !== '' ? $curlError : null,
            ]);

            return null;
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded) || !isset($decoded['rates']) || !is_array($decoded['rates'])) {
            Log::warning('frankfurter returned unexpected payload', [
                'url' => $url,
                'requested_date' => $rateDate,
            ]);

            return null;
        }

        return $decoded;
    }
}
