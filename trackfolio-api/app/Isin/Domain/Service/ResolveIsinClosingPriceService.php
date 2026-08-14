<?php

namespace App\Isin\Domain\Service;

use App\Isin\Domain\Entity\Isin;
use App\Isin\Domain\Entity\IsinQuote;
use App\Isin\Domain\Entity\TickerRequest;
use App\Isin\Domain\DTO\StockCandleDTO;
use App\Isin\Domain\Service\Provider\AlphaVantageProvider;
use App\Isin\Domain\Service\Provider\FinnhubProvider;
use App\Isin\Domain\Service\Provider\FmpProvider;
use App\Isin\Domain\Service\Provider\StockApiProviderInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ResolveIsinClosingPriceService
{
    private const LOOKBACK_DAYS = 6;

    /**
     * Provider fallback order for closing prices.
     *
     * @return list<string>
     */
    public static function providerOrder(): array
    {
        return [
            StockApiService::PROVIDER_ALPHAVANTAGE,
            StockApiService::PROVIDER_FMP,
            StockApiService::PROVIDER_FINNHUB,
        ];
    }

    /**
     * Resolve D-1 closing price for an ISIN (cache first, then providers with fallback).
     * Uses yesterday UTC and walks back up to LOOKBACK_DAYS if needed.
     */
    public function resolveForD1(string $isin): ?IsinQuote
    {
        $toDate = Carbon::yesterday('UTC')->startOfDay();
        $fromDate = $toDate->copy()->subDays(self::LOOKBACK_DAYS);

        return $this->resolveForDateRange($isin, $fromDate, $toDate);
    }

    /**
     * Resolve closing price for an ISIN within [fromDate, toDate] (inclusive).
     * Prefers the latest available closing date in that window.
     *
     * @param string|null $provider If set, only this provider is tried (no fallback chain).
     * @param bool $bypassCache If true, skips isin_quotes lookup and always hits providers.
     * @param string|null $forcedSymbol If set, skips symbol resolution and uses this ticker.
     */
    public function resolveForDateRange(
        string $isin,
        Carbon $fromDate,
        Carbon $toDate,
        ?string $provider = null,
        bool $bypassCache = false,
        ?string $forcedSymbol = null,
    ): ?IsinQuote {
        $isin = strtoupper(trim($isin));
        $from = $fromDate->copy()->setTimezone('UTC')->startOfDay();
        $to = $toDate->copy()->setTimezone('UTC')->startOfDay();

        if (!$bypassCache) {
            foreach (self::providerOrder() as $preferredProvider) {
                $cached = IsinQuote::query()
                    ->where('isin', $isin)
                    ->where('provider', $preferredProvider)
                    ->whereBetween('closing_date', [$from->toDateString(), $to->toDateString()])
                    ->orderByDesc('closing_date')
                    ->first();

                if ($cached !== null) {
                    return $cached;
                }
            }
        }

        $stockExchange = null;
        if ($forcedSymbol !== null && $forcedSymbol !== '') {
            $symbol = strtoupper($forcedSymbol);
        } else {
            $resolvedSymbol = $this->resolveSymbol($isin);
            if ($resolvedSymbol === null) {
                return null;
            }
            $symbol = $resolvedSymbol['symbol'];
            $stockExchange = $resolvedSymbol['stock_exchange'];
        }

        $fromTimestamp = $from->getTimestamp();
        $toTimestamp = $to->copy()->endOfDay()->getTimestamp();

        $providers = $provider !== null
            ? [strtolower($provider)]
            : self::providerOrder();

        foreach ($providers as $providerName) {
            $providerInstance = $this->tryCreateProvider($providerName);
            if ($providerInstance === null) {
                $this->logTickerRequest(
                    isin: $isin,
                    tickerSymbol: $symbol,
                    closingDate: $to->toDateString(),
                    provider: $providerName,
                    stockExchange: $stockExchange,
                    response: null,
                    errorMessage: "Provider {$providerName} is not configured (missing API key)",
                    httpStatus: null,
                    success: false,
                );
                continue;
            }

            $result = $providerInstance->fetchCandleData($symbol, $fromTimestamp, $toTimestamp, 'D');

            $tickerRequest = $this->logTickerRequest(
                isin: $isin,
                tickerSymbol: $symbol,
                closingDate: $to->toDateString(),
                provider: $providerName,
                stockExchange: $stockExchange,
                response: $result->response,
                errorMessage: $result->errorMessage,
                httpStatus: $result->httpStatus,
                success: $result->success,
            );

            if (!$result->success || $result->candle === null || empty($result->candle->closePrices)) {
                continue;
            }

            $this->persistNewClosingPricesFromCandle(
                isin: $isin,
                symbol: $symbol,
                stockExchange: $stockExchange,
                providerName: $providerName,
                candle: $result->candle,
                tickerRequestId: $tickerRequest->id,
            );

            $inRange = IsinQuote::query()
                ->where('isin', $isin)
                ->where('provider', $providerName)
                ->whereBetween('closing_date', [$from->toDateString(), $to->toDateString()])
                ->orderByDesc('closing_date')
                ->first();

            if ($inRange !== null) {
                return $inRange;
            }
        }

        return null;
    }

    /**
     * Persist every candle day that does not already exist for this ISIN + provider.
     */
    private function persistNewClosingPricesFromCandle(
        string $isin,
        string $symbol,
        ?string $stockExchange,
        string $providerName,
        StockCandleDTO $candle,
        int $tickerRequestId,
    ): void {
        foreach ($candle->timestamps as $index => $timestamp) {
            $closingDate = Carbon::createFromTimestamp((int) $timestamp, 'UTC')->toDateString();

            $close = $candle->closePrices[$index] ?? null;
            $open = $candle->openPrices[$index] ?? null;
            $high = $candle->highPrices[$index] ?? null;
            $low = $candle->lowPrices[$index] ?? null;
            $volume = $candle->volumes[$index] ?? null;

            IsinQuote::query()->firstOrCreate(
                [
                    'isin' => $isin,
                    'closing_date' => $closingDate,
                    'provider' => $providerName,
                ],
                [
                    'ticker_symbol' => $symbol,
                    'close_price_min_unit' => $this->priceToMinUnit($close),
                    'open_price_min_unit' => $this->priceToMinUnit($open),
                    'high_price_min_unit' => $this->priceToMinUnit($high),
                    'low_price_min_unit' => $this->priceToMinUnit($low),
                    'volume' => $volume !== null ? (int) $volume : null,
                    'currency' => null,
                    'stock_exchange' => $stockExchange,
                    'ticker_request_id' => $tickerRequestId,
                ]
            );
        }
    }

    private function priceToMinUnit(mixed $price): ?int
    {
        if ($price === null || $price === '') {
            return null;
        }

        return (int) round((float) $price * 100);
    }

    /**
     * Latest quote strictly before the given closing date (provider preference order).
     */
    public function findPreviousQuote(string $isin, string $beforeClosingDate, ?string $preferredProvider = null): ?IsinQuote
    {
        $isin = strtoupper(trim($isin));
        $providers = $preferredProvider !== null
            ? array_values(array_unique([$preferredProvider, ...self::providerOrder()]))
            : self::providerOrder();

        foreach ($providers as $providerName) {
            $quote = IsinQuote::query()
                ->where('isin', $isin)
                ->where('provider', $providerName)
                ->where('closing_date', '<', $beforeClosingDate)
                ->whereNotNull('close_price_min_unit')
                ->orderByDesc('closing_date')
                ->first();

            if ($quote !== null) {
                return $quote;
            }
        }

        return IsinQuote::query()
            ->where('isin', $isin)
            ->where('closing_date', '<', $beforeClosingDate)
            ->whereNotNull('close_price_min_unit')
            ->orderByDesc('closing_date')
            ->first();
    }

    /**
     * Latest ticker_requests row for an ISIN + provider (for debugging / dummy endpoints).
     */
    public function latestTickerRequest(string $isin, string $provider): ?TickerRequest
    {
        return TickerRequest::query()
            ->where('isin', strtoupper(trim($isin)))
            ->where('provider', strtolower($provider))
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array{symbol: string, stock_exchange: ?string}|null
     */
    private function resolveSymbol(string $isin): ?array
    {
        $local = Isin::query()->where('isin', $isin)->first();
        if ($local !== null && $local->symbol !== '') {
            return [
                'symbol' => $local->symbol,
                'stock_exchange' => null,
            ];
        }

        foreach (self::providerOrder() as $providerName) {
            if ($providerName === StockApiService::PROVIDER_ALPHAVANTAGE) {
                continue;
            }

            $provider = $this->tryCreateProvider($providerName);
            if ($provider === null) {
                $this->logTickerRequest(
                    isin: $isin,
                    tickerSymbol: null,
                    closingDate: null,
                    provider: $providerName,
                    stockExchange: null,
                    response: null,
                    errorMessage: "Provider {$providerName} is not configured (missing API key) during symbol resolve",
                    httpStatus: null,
                    success: false,
                );
                continue;
            }

            try {
                if ($provider instanceof FmpProvider) {
                    $withExchange = $provider->searchByIsinWithExchange($isin);
                    $this->logTickerRequest(
                        isin: $isin,
                        tickerSymbol: $withExchange['symbol'] ?? null,
                        closingDate: null,
                        provider: $providerName,
                        stockExchange: $withExchange['stock_exchange'] ?? null,
                        response: $withExchange,
                        errorMessage: $withExchange === null ? 'No symbol found for ISIN' : null,
                        httpStatus: 200,
                        success: $withExchange !== null,
                    );
                    if ($withExchange !== null) {
                        return $withExchange;
                    }
                    continue;
                }

                $search = $provider->searchByIsin($isin);
                $symbol = $search?->results[0]->symbol ?? null;
                $this->logTickerRequest(
                    isin: $isin,
                    tickerSymbol: $symbol,
                    closingDate: null,
                    provider: $providerName,
                    stockExchange: null,
                    response: $search?->toArray(),
                    errorMessage: $symbol ? null : 'No symbol found for ISIN',
                    httpStatus: 200,
                    success: $symbol !== null && $symbol !== '',
                );
                if ($symbol) {
                    return [
                        'symbol' => $symbol,
                        'stock_exchange' => null,
                    ];
                }
            } catch (\App\Isin\Domain\Exception\ProviderHttpException $e) {
                $this->logTickerRequest(
                    isin: $isin,
                    tickerSymbol: null,
                    closingDate: null,
                    provider: $providerName,
                    stockExchange: null,
                    response: $e->rawResponse,
                    errorMessage: $e->getMessage(),
                    httpStatus: $e->httpStatus,
                    success: false,
                );
            } catch (\Throwable $e) {
                $this->logTickerRequest(
                    isin: $isin,
                    tickerSymbol: null,
                    closingDate: null,
                    provider: $providerName,
                    stockExchange: null,
                    response: null,
                    errorMessage: $e->getMessage(),
                    httpStatus: null,
                    success: false,
                );
            }
        }

        Log::warning("ResolveIsinClosingPriceService: could not resolve symbol for ISIN {$isin}");

        return null;
    }

    private function tryCreateProvider(string $providerName): ?StockApiProviderInterface
    {
        try {
            return match (strtolower($providerName)) {
                StockApiService::PROVIDER_FINNHUB => new FinnhubProvider(),
                StockApiService::PROVIDER_FMP => new FmpProvider(),
                StockApiService::PROVIDER_ALPHAVANTAGE => new AlphaVantageProvider(),
                default => null,
            };
        } catch (\Throwable $e) {
            Log::warning("ResolveIsinClosingPriceService: cannot create provider {$providerName}: " . $e->getMessage());

            return null;
        }
    }

    /**
     * @param list<int> $timestamps
     * @param list<float> $closePrices
     */
    private function latestCandleIndex(array $timestamps, array $closePrices): ?int
    {
        if ($closePrices === []) {
            return null;
        }

        if ($timestamps === []) {
            return count($closePrices) - 1;
        }

        $maxTs = null;
        $maxIndex = null;
        foreach ($timestamps as $index => $ts) {
            if ($maxTs === null || $ts >= $maxTs) {
                $maxTs = $ts;
                $maxIndex = $index;
            }
        }

        return $maxIndex;
    }

    private function logTickerRequest(
        string $isin,
        ?string $tickerSymbol,
        ?string $closingDate,
        string $provider,
        ?string $stockExchange,
        mixed $response,
        ?string $errorMessage,
        ?int $httpStatus,
        bool $success,
    ): TickerRequest {
        return TickerRequest::query()->create([
            'isin' => $isin,
            'ticker_symbol' => $tickerSymbol,
            'closing_date' => $closingDate,
            'provider' => $provider,
            'stock_exchange' => $stockExchange,
            'response' => $this->normalizeResponse($response),
            'error_message' => $errorMessage,
            'provider_response_http_status' => $httpStatus,
            'success' => $success,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeResponse(mixed $response): ?array
    {
        if ($response === null) {
            return null;
        }

        if (is_array($response)) {
            return $response;
        }

        if (is_string($response)) {
            $decoded = json_decode($response, true);
            if (is_array($decoded)) {
                return $decoded;
            }

            return ['raw' => $response];
        }

        if (is_object($response) && method_exists($response, 'toArray')) {
            /** @var array<string, mixed> $array */
            $array = $response->toArray();

            return $array;
        }

        return ['value' => $response];
    }
}
