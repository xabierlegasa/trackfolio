<?php

namespace App\Isin\Domain\Service\Provider;

use App\Isin\Domain\DTO\StockCandleDTO;
use App\Isin\Domain\DTO\StockQuoteDTO;
use App\Isin\Domain\DTO\StockSearchResponseDTO;
use Carbon\Carbon;

interface StockApiProviderInterface
{
    /**
     * Search for a stock by ISIN.
     *
     * @param string $isin
     * @return StockSearchResponseDTO|null
     * @throws \Exception
     */
    public function searchByIsin(string $isin): ?StockSearchResponseDTO;

    /**
     * Get quote information for a symbol.
     *
     * @param string $symbol
     * @return StockQuoteDTO|null
     * @throws \Exception
     */
    public function getQuote(string $symbol): ?StockQuoteDTO;

    /**
     * Get candle data (OHLCV) for a stock symbol with custom parameters.
     * 
     * @param string $symbol The stock symbol (ticker), e.g., 'AAPL'.
     * @param int $fromTimestamp Unix timestamp for start time.
     * @param int $toTimestamp Unix timestamp for end time.
     * @param string $resolution Resolution: '1', '5', '15', '30', '60', 'D', 'W', 'M'.
     * @return StockCandleDTO|null DTO containing all candle data, or null on error.
     */
    public function getCandleData(string $symbol, int $fromTimestamp, int $toTimestamp, string $resolution = 'D'): ?StockCandleDTO;
}

