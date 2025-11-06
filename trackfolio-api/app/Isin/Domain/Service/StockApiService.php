<?php

namespace App\Isin\Domain\Service;

use App\Isin\Domain\DTO\StockCandleDTO;
use App\Isin\Domain\DTO\StockInfoDTO;
use App\Isin\Domain\DTO\StockQuoteDTO;
use App\Isin\Domain\DTO\StockSearchResponseDTO;
use App\Isin\Domain\Service\Provider\AlphaVantageProvider;
use App\Isin\Domain\Service\Provider\FinnhubProvider;
use App\Isin\Domain\Service\Provider\FmpProvider;
use App\Isin\Domain\Service\Provider\StockApiProviderInterface;
use Carbon\Carbon;

class StockApiService
{
    public const PROVIDER_FINNHUB = 'finnhub';
    public const PROVIDER_FMP = 'fmp';
    public const PROVIDER_ALPHAVANTAGE = 'alphavantage';

    /**
     * Create provider instance.
     *
     * @param string $providerName
     * @return StockApiProviderInterface
     * @throws \InvalidArgumentException
     */
    private function createProvider(string $providerName): StockApiProviderInterface
    {
        return match (strtolower($providerName)) {
            self::PROVIDER_FINNHUB => new FinnhubProvider(),
            self::PROVIDER_FMP => new FmpProvider(),
            self::PROVIDER_ALPHAVANTAGE => new AlphaVantageProvider(),
            default => throw new \InvalidArgumentException("Unknown stock API provider: {$providerName}. Available: " . self::PROVIDER_FINNHUB . ", " . self::PROVIDER_FMP . ", " . self::PROVIDER_ALPHAVANTAGE),
        };
    }

    /**
     * Get provider instance.
     *
     * @param string|null $provider
     * @return StockApiProviderInterface
     */
    private function getProvider(?string $provider = null): StockApiProviderInterface
    {
        $providerName = $provider ?? self::PROVIDER_FINNHUB;
        return $this->createProvider($providerName);
    }

    /**
     * Search for a stock by ISIN.
     *
     * @param string $isin
     * @param string|null $provider Provider to use (PROVIDER_FINNHUB, PROVIDER_FMP, or PROVIDER_ALPHAVANTAGE). Defaults to PROVIDER_FINNHUB.
     * @return StockSearchResponseDTO|null
     * @throws \Exception
     */
    public function searchByIsin(string $isin, ?string $provider = null): ?StockSearchResponseDTO
    {
        return $this->getProvider($provider)->searchByIsin($isin);
    }

    /**
     * Get quote information for a symbol.
     *
     * @param string $symbol
     * @param string|null $provider Provider to use (PROVIDER_FINNHUB, PROVIDER_FMP, or PROVIDER_ALPHAVANTAGE). Defaults to PROVIDER_FINNHUB.
     * @return StockQuoteDTO|null
     * @throws \Exception
     */
    public function getQuote(string $symbol, ?string $provider = null): ?StockQuoteDTO
    {
        return $this->getProvider($provider)->getQuote($symbol);
    }

    /**
     * Get stock information from external API by ISIN.
     *
     * @param string $isin
     * @param string|null $provider Provider to use (PROVIDER_FINNHUB, PROVIDER_FMP, or PROVIDER_ALPHAVANTAGE). Defaults to PROVIDER_FINNHUB.
     * @return StockInfoDTO|null
     * @throws \Exception
     */
    public function getStockInfo(string $isin, ?string $provider = null): ?StockInfoDTO
    {
        // Search by ISIN
        $searchResponse = $this->searchByIsin($isin, $provider);

        if (!$searchResponse || empty($searchResponse->results)) {
            return null;
        }

        $firstResult = $searchResponse->results[0];
        $symbol = $firstResult->symbol;

        if (!$symbol) {
            return null;
        }

        // Get quote for the symbol
        $quote = $this->getQuote($symbol, $provider);

        return new StockInfoDTO(
            symbol: $symbol,
            description: $firstResult->description ?? null,
            displaySymbol: $firstResult->displaySymbol ?? null,
            type: $firstResult->type ?? null,
            quote: $quote,
        );
    }

    /**
     * Get candle data (OHLCV) for a stock symbol with custom parameters.
     * 
     * @param string $symbol The stock symbol (ticker), e.g., 'AAPL'.
     * @param int $fromTimestamp Unix timestamp for start time.
     * @param int $toTimestamp Unix timestamp for end time.
     * @param string $resolution Resolution: '1', '5', '15', '30', '60', 'D', 'W', 'M'.
     * @param string|null $provider Provider to use (PROVIDER_FINNHUB, PROVIDER_FMP, or PROVIDER_ALPHAVANTAGE). Defaults to PROVIDER_FINNHUB.
     * @return StockCandleDTO|null DTO containing all candle data, or null on error.
     */
    public function getCandleData(string $symbol, int $fromTimestamp, int $toTimestamp, string $resolution = 'D', ?string $provider = null): ?StockCandleDTO
    {
        return $this->getProvider($provider)->getCandleData($symbol, $fromTimestamp, $toTimestamp, $resolution);
    }

    /**
     * Get candle data (OHLCV) for a stock symbol on a specific date.
     * 
     * @param string $symbol The stock symbol (ticker), e.g., 'AAPL'.
     * @param Carbon $date The desired date (will use closing price of that day).
     * @param string|null $provider Provider to use (PROVIDER_FINNHUB, PROVIDER_FMP, or PROVIDER_ALPHAVANTAGE). Defaults to PROVIDER_FINNHUB.
     * @return StockCandleDTO|null DTO containing all candle data, or null on error.
     */
    public function getClosingPriceByDate(string $symbol, Carbon $date, ?string $provider = null): ?StockCandleDTO
    {
        // Inicio del día (00:00:00 UTC)
        $fromTimestamp = $date->copy()->setTimezone('UTC')->startOfDay()->getTimestamp();
        
        // Fin del día (23:59:59 UTC)
        $toTimestamp = $date->copy()->setTimezone('UTC')->endOfDay()->getTimestamp();

        return $this->getCandleData($symbol, $fromTimestamp, $toTimestamp, 'D', $provider);
    }
}

