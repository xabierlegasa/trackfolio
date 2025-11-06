<?php

namespace App\Isin\Domain\Service\Provider;

use App\Isin\Domain\DTO\StockCandleDTO;
use App\Isin\Domain\DTO\StockQuoteDTO;
use App\Isin\Domain\DTO\StockSearchResponseDTO;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class AlphaVantageProvider implements StockApiProviderInterface
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = Config::get('stock_api.alphavantage.api_key', '');
        $this->baseUrl = 'https://www.alphavantage.co/query';

        if (empty($this->apiKey)) {
            throw new \RuntimeException('ALPHAVANTAGE_API_KEY is not set in environment variables');
        }
    }

    /**
     * Search for a stock by ISIN.
     * Note: Alpha Vantage does not support direct ISIN search.
     *
     * @param string $isin
     * @return StockSearchResponseDTO|null
     * @throws \Exception
     */
    public function searchByIsin(string $isin): ?StockSearchResponseDTO
    {
        // Alpha Vantage doesn't support ISIN search directly
        // We could try SYMBOL_SEARCH but it's unlikely to work with ISIN
        // For now, return null
        Log::warning("AlphaVantageProvider: ISIN search not supported. ISIN: {$isin}");
        return null;
    }

    /**
     * Get quote information for a symbol.
     *
     * @param string $symbol
     * @return StockQuoteDTO|null
     * @throws \Exception
     */
    public function getQuote(string $symbol): ?StockQuoteDTO
    {
        $params = [
            'function' => 'GLOBAL_QUOTE',
            'symbol' => strtoupper($symbol),
            'apikey' => $this->apiKey,
        ];

        $data = $this->apiRequest($params);

        if (!$data || !isset($data['Global Quote'])) {
            return null;
        }

        $quote = $data['Global Quote'];

        // Alpha Vantage uses different field names
        return new StockQuoteDTO(
            currentPrice: isset($quote['05. price']) ? (float) $quote['05. price'] : null,
            change: isset($quote['09. change']) ? (float) $quote['09. change'] : null,
            percentChange: isset($quote['10. change percent']) ? (float) str_replace('%', '', $quote['10. change percent']) : null,
            highPrice: isset($quote['03. high']) ? (float) $quote['03. high'] : null,
            lowPrice: isset($quote['04. low']) ? (float) $quote['04. low'] : null,
            openPrice: isset($quote['02. open']) ? (float) $quote['02. open'] : null,
            previousClose: isset($quote['08. previous close']) ? (float) $quote['08. previous close'] : null,
        );
    }

    /**
     * Get candle data (OHLCV) for a stock symbol with custom parameters.
     * 
     * @param string $symbol The stock symbol (ticker), e.g., 'AAPL'.
     * @param int $fromTimestamp Unix timestamp for start time.
     * @param int $toTimestamp Unix timestamp for end time.
     * @param string $resolution Resolution: '1', '5', '15', '30', '60', 'D', 'W', 'M'.
     * @return StockCandleDTO|null DTO containing all candle data, or null on error.
     */
    public function getCandleData(string $symbol, int $fromTimestamp, int $toTimestamp, string $resolution = 'D'): ?StockCandleDTO
    {
        // Alpha Vantage only supports daily resolution for free tier
        if ($resolution !== 'D') {
            Log::warning("AlphaVantageProvider: Only daily resolution (D) is supported. Requested: {$resolution}");
            return null;
        }

        $params = [
            'function' => 'TIME_SERIES_DAILY_ADJUSTED',
            'symbol' => strtoupper($symbol),
            'outputsize' => 'full', // Get full historical data
            'apikey' => $this->apiKey,
        ];

        try {
            $data = $this->apiRequest($params);

            if (!$data) {
                return null;
            }

            // Convert Alpha Vantage format to DTO
            return $this->convertAlphaVantageCandleToDto($data, $fromTimestamp, $toTimestamp);
        } catch (\Exception $e) {
            error_log("Error en la petición Alpha Vantage para {$symbol}. Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Convert Alpha Vantage candle data format to StockCandleDTO.
     *
     * @param array $data
     * @param int $fromTimestamp
     * @param int $toTimestamp
     * @return StockCandleDTO|null
     */
    private function convertAlphaVantageCandleToDto(array $data, int $fromTimestamp, int $toTimestamp): ?StockCandleDTO
    {
        $timeSeriesKey = 'Time Series (Daily)';

        if (!isset($data[$timeSeriesKey])) {
            return new StockCandleDTO(
                status: 'no_data',
                closePrices: [],
                highPrices: [],
                lowPrices: [],
                openPrices: [],
                timestamps: [],
                volumes: [],
            );
        }

        $timeSeries = $data[$timeSeriesKey];
        $fromDate = Carbon::createFromTimestamp($fromTimestamp)->format('Y-m-d');
        $toDate = Carbon::createFromTimestamp($toTimestamp)->format('Y-m-d');

        $closePrices = [];
        $highPrices = [];
        $lowPrices = [];
        $openPrices = [];
        $timestamps = [];
        $volumes = [];

        // Alpha Vantage returns data with dates as keys (YYYY-MM-DD format)
        foreach ($timeSeries as $date => $candle) {
            // Filter by date range
            if ($date < $fromDate || $date > $toDate) {
                continue;
            }

            $dateCarbon = Carbon::parse($date);
            $timestamp = $dateCarbon->getTimestamp();

            $closePrices[] = (float) ($candle['4. close'] ?? 0);
            $highPrices[] = (float) ($candle['2. high'] ?? 0);
            $lowPrices[] = (float) ($candle['3. low'] ?? 0);
            $openPrices[] = (float) ($candle['1. open'] ?? 0);
            $volumes[] = (int) ($candle['6. volume'] ?? 0);
            $timestamps[] = $timestamp;
        }

        // Sort by timestamp ascending (oldest first)
        if (!empty($timestamps)) {
            array_multisort($timestamps, SORT_ASC, $closePrices, $highPrices, $lowPrices, $openPrices, $volumes);
        }

        return new StockCandleDTO(
            status: 'ok',
            closePrices: $closePrices,
            highPrices: $highPrices,
            lowPrices: $lowPrices,
            openPrices: $openPrices,
            timestamps: $timestamps,
            volumes: $volumes,
        );
    }

    /**
     * Make a request to Alpha Vantage API.
     *
     * @param array $params
     * @return array|null
     * @throws \Exception
     */
    private function apiRequest(array $params): ?array
    {
        $url = $this->baseUrl . '?' . http_build_query($params);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        Log::info('curl request to Alpha Vantage: ' . $url);
        Log::info('response: ' . $response);
        Log::info('http code: ' . $httpCode);

        if ($response === false || $httpCode !== 200) {
            throw new \Exception("Error en la petición a Alpha Vantage. Código HTTP: {$httpCode}");
        }

        $data = json_decode($response, true);
        
        // Alpha Vantage returns errors in the response body even with HTTP 200
        if (isset($data['Error Message'])) {
            throw new \Exception("Error devuelto por Alpha Vantage: " . $data['Error Message']);
        }

        if (isset($data['Note'])) {
            throw new \Exception("Nota de Alpha Vantage (posible límite excedido): " . $data['Note']);
        }
        
        return $data;
    }
}

