<?php

namespace App\ExchangeRate\Domain\Service;

use App\ExchangeRate\Domain\Entity\ExchangeRate;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ResolveUsdToEurRateService
{
    private const PROVIDER = 'open_er_api';
    private const API_URL = 'https://open.er-api.com/v6/latest/USD';

    /**
     * USD → EUR rate for today. Uses DB cache; fetches open.er-api when missing.
     *
     * @return array{rate: float, rate_date: string}|null
     */
    public function resolveToday(): ?array
    {
        $today = Carbon::today()->toDateString();

        $cached = ExchangeRate::query()
            ->whereDate('rate_date', $today)
            ->where('base_currency', 'USD')
            ->where('quote_currency', 'EUR')
            ->first();

        if ($cached !== null && (float) $cached->rate > 0) {
            return [
                'rate' => (float) $cached->rate,
                'rate_date' => $this->asOfDateFromRow($cached),
            ];
        }

        return $this->fetchAndPersist($today);
    }

    /**
     * USD → EUR stored on or before $rateDate. Does not call the live FX API for historical dates.
     *
     * @return array{rate: float, rate_date: string}|null
     */
    public function resolveOnOrBefore(string $rateDate): ?array
    {
        $today = Carbon::today()->toDateString();
        if ($rateDate === $today) {
            return $this->resolveToday();
        }

        $exact = ExchangeRate::query()
            ->whereDate('rate_date', $rateDate)
            ->where('base_currency', 'USD')
            ->where('quote_currency', 'EUR')
            ->first();

        if ($exact !== null && (float) $exact->rate > 0) {
            return [
                'rate' => (float) $exact->rate,
                'rate_date' => $this->asOfDateFromRow($exact),
            ];
        }

        $latestBefore = ExchangeRate::query()
            ->where('base_currency', 'USD')
            ->where('quote_currency', 'EUR')
            ->whereDate('rate_date', '<=', $rateDate)
            ->orderByDesc('rate_date')
            ->first();

        if ($latestBefore !== null && (float) $latestBefore->rate > 0) {
            return [
                'rate' => (float) $latestBefore->rate,
                'rate_date' => $this->asOfDateFromRow($latestBefore),
            ];
        }

        $any = ExchangeRate::query()
            ->where('base_currency', 'USD')
            ->where('quote_currency', 'EUR')
            ->orderByDesc('rate_date')
            ->first();

        if ($any !== null && (float) $any->rate > 0) {
            return [
                'rate' => (float) $any->rate,
                'rate_date' => $this->asOfDateFromRow($any),
            ];
        }

        return null;
    }

    /**
     * USD → EUR rate for today. Uses DB cache; fetches open.er-api when missing.
     */
    public function forToday(): ?float
    {
        $resolved = $this->resolveToday();

        return $resolved['rate'] ?? null;
    }

    /**
     * @return array{rate: float, rate_date: string}|null
     */
    private function fetchAndPersist(string $rateDate): ?array
    {
        $payload = $this->fetchLatestUsdRates();
        if ($payload === null) {
            return null;
        }

        $rates = $payload['rates'] ?? null;
        if (!is_array($rates) || !isset($rates['EUR'])) {
            Log::warning('open.er-api response missing EUR rate', [
                'result' => $payload['result'] ?? null,
            ]);

            return null;
        }

        $rate = (float) $rates['EUR'];
        if ($rate <= 0) {
            return null;
        }

        ExchangeRate::query()->updateOrCreate(
            [
                'rate_date' => $rateDate,
                'base_currency' => 'USD',
                'quote_currency' => 'EUR',
            ],
            [
                'rate' => $rate,
                'provider' => self::PROVIDER,
                'response' => $payload,
            ],
        );

        return [
            'rate' => $rate,
            'rate_date' => $this->asOfDateFromPayload($payload, $rateDate),
        ];
    }

    private function asOfDateFromRow(ExchangeRate $row): string
    {
        $fallback = $row->rate_date?->format('Y-m-d') ?? Carbon::today()->toDateString();
        $payload = is_array($row->response) ? $row->response : [];

        return $this->asOfDateFromPayload($payload, $fallback);
    }

    /**
     * Prefer the provider's last-update date (when the FX actually comes from).
     *
     * @param array<string, mixed> $payload
     */
    private function asOfDateFromPayload(array $payload, string $fallback): string
    {
        $unix = $payload['time_last_update_unix'] ?? null;
        if (is_numeric($unix) && (int) $unix > 0) {
            return Carbon::createFromTimestampUTC((int) $unix)->toDateString();
        }

        $utc = $payload['time_last_update_utc'] ?? null;
        if (is_string($utc) && $utc !== '') {
            try {
                return Carbon::parse($utc)->utc()->toDateString();
            } catch (\Throwable) {
                // Fall through to the stored rate_date.
            }
        }

        return $fallback;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchLatestUsdRates(): ?array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => self::API_URL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);

        $body = curl_exec($ch);
        $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($body === false || $curlError !== '' || $httpStatus < 200 || $httpStatus >= 300) {
            Log::warning('Failed to fetch open.er-api USD rates', [
                'http_status' => $httpStatus,
                'curl_error' => $curlError !== '' ? $curlError : null,
            ]);

            return null;
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded) || ($decoded['result'] ?? null) !== 'success') {
            Log::warning('open.er-api returned unsuccessful payload', [
                'result' => is_array($decoded) ? ($decoded['result'] ?? null) : null,
            ]);

            return null;
        }

        return $decoded;
    }
}
