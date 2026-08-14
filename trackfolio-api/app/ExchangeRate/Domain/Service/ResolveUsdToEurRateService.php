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
     */
    public function forToday(): ?float
    {
        $today = Carbon::today()->toDateString();

        $cached = ExchangeRate::query()
            ->whereDate('rate_date', $today)
            ->where('base_currency', 'USD')
            ->where('quote_currency', 'EUR')
            ->first();

        if ($cached !== null && (float) $cached->rate > 0) {
            return (float) $cached->rate;
        }

        return $this->fetchAndPersist($today);
    }

    private function fetchAndPersist(string $rateDate): ?float
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

        return $rate;
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
