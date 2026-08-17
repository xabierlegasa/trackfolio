<?php

namespace App\Isin\Domain\Service;

use App\Isin\Domain\Entity\Isin;
use App\Isin\Domain\Entity\IsinQuote;
use App\Isin\Domain\Entity\TickerRequest;
use App\Isin\Domain\DTO\StockCandleDTO;
use App\Isin\Domain\Service\Provider\AlphaVantageProvider;
use App\Isin\Domain\Service\Provider\EodhdProvider;
use App\Isin\Domain\Service\Provider\FinnhubProvider;
use App\Isin\Domain\Service\Provider\FmpProvider;
use App\Isin\Domain\Service\Provider\StockApiProviderInterface;
use App\Portfolio\Domain\Service\InvalidatePortfolioAsOfViewsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ResolveIsinClosingPriceService
{
    private const LOOKBACK_DAYS = 6;

    private ?int $lastProviderRequestId = null;

    /** @var list<array{isin: string, closing_date: string, provider: string, close_price_min_unit: int|null, ticker_symbol: string}> */
    private array $lastNewlyPersistedQuotes = [];

    /** 'isin_quotes' | 'provider_api' | null */
    private ?string $lastResolutionSource = null;

    public function __construct(
        private ThrottleStockApiRequestService $throttle,
        private InvalidatePortfolioAsOfViewsService $invalidatePortfolioAsOfViewsService,
    ) {}

    public function lastProviderRequestId(): ?int
    {
        return $this->lastProviderRequestId;
    }

    /**
     * Where the last resolveForDateRange / resolveForD1 got the quote from.
     *
     * @return 'isin_quotes'|'provider_api'|null
     */
    public function lastResolutionSource(): ?string
    {
        return $this->lastResolutionSource;
    }

    /**
     * Quotes inserted into isin_quotes during the last resolveForDateRange / resolveForD1 call.
     *
     * @return list<array{isin: string, closing_date: string, provider: string, close_price_min_unit: int|null, ticker_symbol: string}>
     */
    public function lastNewlyPersistedQuotes(): array
    {
        return $this->lastNewlyPersistedQuotes;
    }
    /**
     * Provider fallback order for closing prices.
     *
     * @return list<string>
     */
    public static function providerOrder(): array
    {
        // Paid EODHD plan is the only active source. Other providers remain in codebase
        // but are not called from the default chain.
        return [
            StockApiService::PROVIDER_EODHD,
        ];
    }

    /**
     * Providers that may be read from isin_quotes cache for a closing date.
     *
     * @return list<string>
     */
    public static function cacheProviderOrder(string $closingDate): array
    {
        return self::providerOrder();
    }

    /**
     * Resolve D-1 closing price for an ISIN (cache first, then providers with fallback).
     * $asOfDate is the last completed US session (YYYY-MM-DD). Defaults to yesterday UTC.
     */
    public function resolveForD1(string $isin, ?string $asOfDate = null): ?IsinQuote
    {
        $toDate = $asOfDate !== null && $asOfDate !== ''
            ? Carbon::parse($asOfDate, 'UTC')->startOfDay()
            : Carbon::yesterday('UTC')->startOfDay();
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
        $this->lastProviderRequestId = null;
        $this->lastNewlyPersistedQuotes = [];
        $this->lastResolutionSource = null;

        if (!$bypassCache) {
            $exact = $this->cachedQuoteOnDate($isin, $to->toDateString(), $provider);
            if ($exact !== null) {
                $this->ensureIsinPersistedFromQuote($isin, $exact);
                $this->lastResolutionSource = 'isin_quotes';

                return $exact;
            }

            $cachedInRange = $this->cachedLatestInRange(
                $isin,
                $from->toDateString(),
                $to->toDateString(),
                $provider,
            );
            if ($cachedInRange !== null) {
                $this->ensureIsinPersistedFromQuote($isin, $cachedInRange);
                $this->lastResolutionSource = 'isin_quotes';

                return $cachedInRange;
            }
        }

        $stockExchange = null;
        if ($forcedSymbol !== null && $forcedSymbol !== '') {
            $symbol = strtoupper($forcedSymbol);
            $this->persistResolvedIsin(
                isin: $isin,
                symbol: $symbol,
                description: null,
                type: null,
                displaySymbol: null,
            );
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
            if ($providerName === StockApiService::PROVIDER_FINNHUB
                && $to->toDateString() < Carbon::yesterday('UTC')->toDateString()
            ) {
                $this->logTickerRequest(
                    isin: $isin,
                    tickerSymbol: $symbol,
                    closingDate: $to->toDateString(),
                    provider: $providerName,
                    stockExchange: $stockExchange,
                    response: null,
                    errorMessage: 'Finnhub skipped for historical closing date (live quote only)',
                    httpStatus: null,
                    success: false,
                );
                continue;
            }

            if ($this->throttle->isInCooldown($providerName)) {
                $this->logTickerRequest(
                    isin: $isin,
                    tickerSymbol: $symbol,
                    closingDate: $to->toDateString(),
                    provider: $providerName,
                    stockExchange: $stockExchange,
                    response: null,
                    errorMessage: "Provider {$providerName} is in rate-limit cooldown",
                    httpStatus: null,
                    success: false,
                );
                continue;
            }

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

            $result = $this->throttle->execute(
                $providerName,
                $symbol,
                fn () => $providerInstance->fetchCandleData($symbol, $fromTimestamp, $toTimestamp, 'D'),
            );

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

            $this->lastProviderRequestId = $result->providerRequestId;
            $this->lastResolutionSource = 'provider_api';

            $this->persistNewClosingPricesFromCandle(
                isin: $isin,
                symbol: $symbol,
                stockExchange: $stockExchange,
                providerName: $providerName,
                candle: $result->candle,
                tickerRequestId: $tickerRequest->id,
            );

            $exact = IsinQuote::query()
                ->where('isin', $isin)
                ->where('provider', $providerName)
                ->whereDate('closing_date', $to->toDateString())
                ->whereNotNull('close_price_min_unit')
                ->first();

            if ($exact !== null) {
                return $exact;
            }

            $fromCandle = $this->cachedLatestInRange(
                $isin,
                $from->toDateString(),
                $to->toDateString(),
                $providerName,
            );
            if ($fromCandle !== null) {
                return $fromCandle;
            }
        }

        if (!$bypassCache) {
            $fallback = $this->cachedLatestInRange(
                $isin,
                $from->toDateString(),
                $to->toDateString(),
                $provider,
            );
            if ($fallback !== null) {
                $this->lastResolutionSource = 'isin_quotes';
            }

            return $fallback;
        }

        return null;
    }

    private function cachedQuoteOnDate(string $isin, string $closingDate, ?string $provider = null): ?IsinQuote
    {
        $providers = $provider !== null
            ? [strtolower($provider)]
            : self::cacheProviderOrder($closingDate);

        foreach ($providers as $preferredProvider) {
            if ($preferredProvider === StockApiService::PROVIDER_FINNHUB
                && $closingDate < Carbon::yesterday('UTC')->toDateString()
            ) {
                continue;
            }

            $cached = IsinQuote::query()
                ->where('isin', $isin)
                ->where('provider', $preferredProvider)
                ->whereDate('closing_date', $closingDate)
                ->whereNotNull('close_price_min_unit')
                ->first();

            if ($cached !== null) {
                return $cached;
            }
        }

        return null;
    }

    private function cachedLatestInRange(
        string $isin,
        string $fromDate,
        string $toDate,
        ?string $provider = null,
    ): ?IsinQuote {
        $providers = $provider !== null
            ? [strtolower($provider)]
            : self::cacheProviderOrder($toDate);

        foreach ($providers as $preferredProvider) {
            if ($preferredProvider === StockApiService::PROVIDER_FINNHUB
                && $toDate < Carbon::yesterday('UTC')->toDateString()
            ) {
                continue;
            }

            $query = IsinQuote::query()
                ->where('isin', $isin)
                ->where('provider', $preferredProvider)
                ->whereBetween('closing_date', [$fromDate, $toDate])
                ->whereNotNull('close_price_min_unit')
                ->orderByDesc('closing_date');

            if ($preferredProvider === StockApiService::PROVIDER_FINNHUB) {
                $query->whereDate('closing_date', '>=', Carbon::yesterday('UTC')->toDateString());
            }

            $cached = $query->first();

            if ($cached !== null) {
                return $cached;
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
        $newlyPersistedDates = [];

        foreach ($candle->timestamps as $index => $timestamp) {
            $closingDate = Carbon::createFromTimestamp((int) $timestamp, 'UTC')->toDateString();

            $close = $candle->closePrices[$index] ?? null;
            $open = $candle->openPrices[$index] ?? null;
            $high = $candle->highPrices[$index] ?? null;
            $low = $candle->lowPrices[$index] ?? null;
            $volume = $candle->volumes[$index] ?? null;
            $currency = $this->quoteCurrencyForSymbol($symbol, $stockExchange);

            $quote = IsinQuote::query()->firstOrCreate(
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
                    'currency' => $currency,
                    'stock_exchange' => $stockExchange,
                    'ticker_request_id' => $tickerRequestId,
                ]
            );

            if ($quote->wasRecentlyCreated) {
                $newlyPersistedDates[] = $closingDate;
                $this->lastNewlyPersistedQuotes[] = [
                    'isin' => $isin,
                    'closing_date' => $closingDate,
                    'provider' => $providerName,
                    'close_price_min_unit' => $quote->close_price_min_unit !== null
                        ? (int) $quote->close_price_min_unit
                        : null,
                    'ticker_symbol' => $symbol,
                ];
            }
        }

        if ($newlyPersistedDates !== []) {
            $this->invalidatePortfolioAsOfViewsService->forClosingDates($newlyPersistedDates);
        }
    }

    private function priceToMinUnit(mixed $price): ?int
    {
        if ($price === null || $price === '') {
            return null;
        }

        return (int) round((float) $price * 100);
    }

    private function quoteCurrencyForSymbol(string $symbol, ?string $stockExchange): ?string
    {
        $symbolUpper = strtoupper(trim($symbol));
        $exchangeUpper = strtoupper(trim((string) $stockExchange));

        if ($exchangeUpper === 'LSE' || str_ends_with($symbolUpper, '.LSE')) {
            return 'GBP';
        }

        return null;
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
        $finnhubMinDate = Carbon::yesterday('UTC')->toDateString();

        foreach ($providers as $providerName) {
            $query = IsinQuote::query()
                ->where('isin', $isin)
                ->where('provider', $providerName)
                ->where('closing_date', '<', $beforeClosingDate)
                ->whereNotNull('close_price_min_unit')
                ->orderByDesc('closing_date');

            if ($providerName === StockApiService::PROVIDER_FINNHUB) {
                $query->whereDate('closing_date', '>=', $finnhubMinDate);
            }

            $quote = $query->first();

            if ($quote !== null) {
                return $quote;
            }
        }

        return IsinQuote::query()
            ->where('isin', $isin)
            ->where('closing_date', '<', $beforeClosingDate)
            ->whereNotNull('close_price_min_unit')
            ->where(function ($query) use ($finnhubMinDate) {
                $query->where('provider', '!=', StockApiService::PROVIDER_FINNHUB)
                    ->orWhereDate('closing_date', '>=', $finnhubMinDate);
            })
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
        // EODHD needs SYMBOL.EXCHANGE; bare tickers from older rows are not enough.
        if ($local !== null && $local->symbol !== '' && str_contains($local->symbol, '.')) {
            $parts = explode('.', $local->symbol, 2);

            return [
                'symbol' => strtoupper($local->symbol),
                'stock_exchange' => $parts[1] ?? null,
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
                if ($provider instanceof EodhdProvider || $provider instanceof FmpProvider) {
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
                        $this->persistResolvedIsin(
                            isin: $isin,
                            symbol: $withExchange['symbol'],
                            description: $withExchange['name'] ?? null,
                            type: $withExchange['type'] ?? null,
                            displaySymbol: $withExchange['code'] ?? null,
                        );

                        return [
                            'symbol' => $withExchange['symbol'],
                            'stock_exchange' => $withExchange['stock_exchange'] ?? null,
                        ];
                    }
                    continue;
                }

                $search = $provider->searchByIsin($isin);
                $first = $search?->results[0] ?? null;
                $symbol = $first?->symbol ?? null;
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
                    $this->persistResolvedIsin(
                        isin: $isin,
                        symbol: $symbol,
                        description: $first?->description,
                        type: $first?->type,
                        displaySymbol: $first?->displaySymbol,
                    );

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

    private function persistResolvedIsin(
        string $isin,
        string $symbol,
        ?string $description,
        ?string $type,
        ?string $displaySymbol,
    ): void {
        $symbol = strtoupper(trim($symbol));
        if ($symbol === '') {
            return;
        }

        $display = $displaySymbol !== null && $displaySymbol !== ''
            ? strtoupper(trim($displaySymbol))
            : (str_contains($symbol, '.') ? explode('.', $symbol, 2)[0] : $symbol);

        $existing = Isin::query()->where('isin', strtoupper(trim($isin)))->first();
        $hasUsableLocal = $existing !== null
            && $existing->symbol !== ''
            && str_contains((string) $existing->symbol, '.');

        // Keep a richer description/type already stored; only fill blanks / upgrade bare tickers.
        Isin::query()->updateOrCreate(
            ['isin' => strtoupper(trim($isin))],
            [
                'symbol' => $symbol,
                'description' => ($description !== null && $description !== '')
                    ? $description
                    : ($existing?->description ?: $symbol),
                'type' => ($type !== null && $type !== '')
                    ? $type
                    : ($existing?->type ?: 'Common Stock'),
                'display_symbol' => $display !== ''
                    ? $display
                    : ($existing?->display_symbol ?: $display),
            ],
        );

        if (!$hasUsableLocal) {
            Log::info("ResolveIsinClosingPriceService: persisted ISIN {$isin} as {$symbol}");
        }
    }

    private function ensureIsinPersistedFromQuote(string $isin, IsinQuote $quote): void
    {
        $existing = Isin::query()->where('isin', $isin)->first();
        if ($existing !== null && $existing->symbol !== '' && str_contains((string) $existing->symbol, '.')) {
            return;
        }

        // Prefer provider metadata (name/type) when the isins row is missing or incomplete.
        if ($this->resolveSymbol($isin) !== null) {
            return;
        }

        $symbol = $quote->ticker_symbol;
        if ($symbol === null || $symbol === '') {
            return;
        }

        $this->persistResolvedIsin(
            isin: $isin,
            symbol: (string) $symbol,
            description: null,
            type: null,
            displaySymbol: null,
        );
    }

    private function tryCreateProvider(string $providerName): ?StockApiProviderInterface
    {
        try {
            return match (strtolower($providerName)) {
                StockApiService::PROVIDER_EODHD => new EodhdProvider(),
                // Kept for manual/debug; not in providerOrder().
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
