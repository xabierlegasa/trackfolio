<?php

namespace App\Isin\Domain\Service\Provider;

use App\Isin\Domain\DTO\ProviderCandleCallResult;
use App\Isin\Domain\DTO\StockCandleDTO;
use App\Isin\Domain\DTO\StockQuoteDTO;
use App\Isin\Domain\DTO\StockSearchResponseDTO;
use App\Isin\Domain\Exception\ProviderHttpException;
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

    public function searchByIsin(string $isin): ?StockSearchResponseDTO
    {
        Log::warning("AlphaVantageProvider: ISIN search not supported. ISIN: {$isin}");
        return null;
    }

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

    public function getCandleData(string $symbol, int $fromTimestamp, int $toTimestamp, string $resolution = 'D'): ?StockCandleDTO
    {
        return $this->fetchCandleData($symbol, $fromTimestamp, $toTimestamp, $resolution)->candle;
    }

    public function fetchCandleData(string $symbol, int $fromTimestamp, int $toTimestamp, string $resolution = 'D'): ProviderCandleCallResult
    {
        if ($resolution !== 'D') {
            return new ProviderCandleCallResult(
                success: false,
                candle: null,
                response: null,
                httpStatus: null,
                errorMessage: "AlphaVantageProvider: Only daily resolution (D) is supported. Requested: {$resolution}",
            );
        }

        $params = [
            // TIME_SERIES_DAILY_ADJUSTED is premium; free tier uses TIME_SERIES_DAILY
            'function' => 'TIME_SERIES_DAILY',
            'symbol' => strtoupper($symbol),
            'outputsize' => 'compact', // last ~100 trading days (enough for D-1 / short ranges)
            'apikey' => $this->apiKey,
        ];

        try {
            $data = $this->apiRequest($params);

            if (!$data) {
                return new ProviderCandleCallResult(
                    success: false,
                    candle: null,
                    response: $data,
                    httpStatus: 200,
                    errorMessage: 'Empty response from Alpha Vantage',
                );
            }

            $candle = $this->convertAlphaVantageCandleToDto($data, $fromTimestamp, $toTimestamp);
            $hasClose = $candle !== null
                && $candle->status === 'ok'
                && !empty($candle->closePrices);

            return new ProviderCandleCallResult(
                success: $hasClose,
                candle: $candle,
                response: $data,
                httpStatus: 200,
                errorMessage: $hasClose ? null : 'No closing price in Alpha Vantage candle response',
            );
        } catch (ProviderHttpException $e) {
            return new ProviderCandleCallResult(
                success: false,
                candle: null,
                response: $e->rawResponse,
                httpStatus: $e->httpStatus,
                errorMessage: $e->getMessage(),
            );
        } catch (\Throwable $e) {
            return new ProviderCandleCallResult(
                success: false,
                candle: null,
                response: null,
                httpStatus: null,
                errorMessage: $e->getMessage(),
            );
        }
    }

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

        $closePrices = [];
        $highPrices = [];
        $lowPrices = [];
        $openPrices = [];
        $timestamps = [];
        $volumes = [];

        // Persist the full compact series (all days returned); callers filter by requested range.
        foreach ($timeSeries as $date => $candle) {
            $dateCarbon = Carbon::parse($date);
            $timestamp = $dateCarbon->getTimestamp();

            $closePrices[] = (float) ($candle['4. close'] ?? 0);
            $highPrices[] = (float) ($candle['2. high'] ?? 0);
            $lowPrices[] = (float) ($candle['3. low'] ?? 0);
            $openPrices[] = (float) ($candle['1. open'] ?? 0);
            // Daily free series uses "5. volume"; adjusted (premium) uses "6. volume"
            $volumes[] = (int) ($candle['5. volume'] ?? $candle['6. volume'] ?? 0);
            $timestamps[] = $timestamp;
        }

        if (!empty($timestamps)) {
            array_multisort($timestamps, SORT_ASC, $closePrices, $highPrices, $lowPrices, $openPrices, $volumes);
        }

        return new StockCandleDTO(
            status: empty($closePrices) ? 'no_data' : 'ok',
            closePrices: $closePrices,
            highPrices: $highPrices,
            lowPrices: $lowPrices,
            openPrices: $openPrices,
            timestamps: $timestamps,
            volumes: $volumes,
        );
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    private function apiRequest(array $params): ?array
    {
        $url = $this->baseUrl . '?' . http_build_query($params);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        Log::info('curl request to Alpha Vantage: ' . $url);
        Log::info('response: ' . $response);
        Log::info('http code: ' . $httpCode);

        $decoded = is_string($response) ? json_decode($response, true) : null;

        if ($response === false || $httpCode !== 200) {
            throw new ProviderHttpException(
                "Error en la petición a Alpha Vantage. Código HTTP: {$httpCode}",
                $httpCode ?: null,
                is_array($decoded) ? $decoded : (is_string($response) ? $response : null),
            );
        }

        if (isset($decoded['Error Message'])) {
            throw new ProviderHttpException(
                'Error devuelto por Alpha Vantage: ' . $decoded['Error Message'],
                $httpCode,
                $decoded,
            );
        }

        if (isset($decoded['Information'])) {
            throw new ProviderHttpException(
                'Información de Alpha Vantage: ' . $decoded['Information'],
                $httpCode,
                $decoded,
            );
        }

        if (isset($decoded['Note'])) {
            throw new ProviderHttpException(
                'Nota de Alpha Vantage (posible límite excedido): ' . $decoded['Note'],
                $httpCode,
                $decoded,
            );
        }

        return is_array($decoded) ? $decoded : null;
    }
}
