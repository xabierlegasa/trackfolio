<?php

namespace App\Isin\Domain\Service\Provider;

use App\Isin\Domain\DTO\ProviderCandleCallResult;
use App\Isin\Domain\DTO\StockCandleDTO;
use App\Isin\Domain\DTO\StockQuoteDTO;
use App\Isin\Domain\DTO\StockSearchResponseDTO;
use App\Isin\Domain\DTO\StockSearchResultDTO;
use App\Isin\Domain\Exception\ProviderHttpException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class FmpProvider implements StockApiProviderInterface
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = Config::get('stock_api.fmp.api_key', '');
        // Stable API (legacy /api/v3 and /api/v4 endpoints are deprecated)
        $this->baseUrl = 'https://financialmodelingprep.com/stable/';

        if (empty($this->apiKey)) {
            throw new \RuntimeException('FMP_API_KEY is not set in environment variables');
        }
    }

    public function searchByIsin(string $isin): ?StockSearchResponseDTO
    {
        $data = $this->apiRequest('search-isin', ['isin' => $isin]);

        if (!$data || empty($data)) {
            return null;
        }

        $items = $this->normalizeListResponse($data);
        $results = [];
        foreach ($items as $item) {
            if (!isset($item['symbol'])) {
                continue;
            }
            $results[] = new StockSearchResultDTO(
                description: $item['name'] ?? $item['description'] ?? '',
                displaySymbol: $item['symbol'] ?? '',
                symbol: $item['symbol'] ?? '',
                type: $item['type'] ?? 'stock',
            );
        }

        return new StockSearchResponseDTO($results);
    }

    /**
     * Search by ISIN and return exchange when FMP provides it.
     *
     * @return array{symbol: string, stock_exchange: ?string}|null
     */
    public function searchByIsinWithExchange(string $isin): ?array
    {
        $data = $this->apiRequest('search-isin', ['isin' => $isin]);

        if (!$data || empty($data)) {
            return null;
        }

        $items = $this->normalizeListResponse($data);
        if ($items === [] || !isset($items[0]['symbol'])) {
            return null;
        }

        $item = $items[0];

        return [
            'symbol' => (string) $item['symbol'],
            'stock_exchange' => isset($item['exchange'])
                ? (string) $item['exchange']
                : (isset($item['stockExchange']) ? (string) $item['stockExchange'] : null),
        ];
    }

    public function getQuote(string $symbol): ?StockQuoteDTO
    {
        $data = $this->apiRequest('quote', ['symbol' => strtoupper($symbol)]);

        if (!$data || empty($data)) {
            return null;
        }

        $items = $this->normalizeListResponse($data);
        $quote = $items[0] ?? (is_array($data) ? $data : null);

        if (!is_array($quote)) {
            return null;
        }

        $percentChange = $quote['changePercentage']
            ?? $quote['changesPercentage']
            ?? null;

        return new StockQuoteDTO(
            currentPrice: isset($quote['price']) ? (float) $quote['price'] : null,
            change: isset($quote['change']) ? (float) $quote['change'] : null,
            percentChange: $percentChange !== null ? (float) $percentChange : null,
            highPrice: isset($quote['dayHigh']) ? (float) $quote['dayHigh'] : null,
            lowPrice: isset($quote['dayLow']) ? (float) $quote['dayLow'] : null,
            openPrice: isset($quote['open']) ? (float) $quote['open'] : null,
            previousClose: isset($quote['previousClose']) ? (float) $quote['previousClose'] : null,
        );
    }

    public function getCandleData(string $symbol, int $fromTimestamp, int $toTimestamp, string $resolution = 'D'): ?StockCandleDTO
    {
        return $this->fetchCandleData($symbol, $fromTimestamp, $toTimestamp, $resolution)->candle;
    }

    public function fetchCandleData(string $symbol, int $fromTimestamp, int $toTimestamp, string $resolution = 'D'): ProviderCandleCallResult
    {
        $fromDate = Carbon::createFromTimestamp($fromTimestamp)->format('Y-m-d');
        $toDate = Carbon::createFromTimestamp($toTimestamp)->format('Y-m-d');

        if ($resolution === 'D') {
            $endpoint = 'historical-price-eod/full';
            $params = [
                'symbol' => strtoupper($symbol),
                'from' => $fromDate,
                'to' => $toDate,
            ];
        } else {
            $timeframeMap = [
                '1' => '1min',
                '5' => '5min',
                '15' => '15min',
                '30' => '30min',
                '60' => '1hour',
            ];

            $timeframe = $timeframeMap[$resolution] ?? '1min';
            $endpoint = 'historical-chart/' . $timeframe;
            $params = [
                'symbol' => strtoupper($symbol),
                'from' => $fromDate,
                'to' => $toDate,
            ];
        }

        try {
            $data = $this->apiRequest($endpoint, $params);

            if (!$data) {
                return new ProviderCandleCallResult(
                    success: false,
                    candle: null,
                    response: $data,
                    httpStatus: 200,
                    errorMessage: 'Empty response from FMP',
                );
            }

            $candle = $this->convertFmpCandleToDto($data);
            $hasClose = $candle !== null
                && $candle->status === 'ok'
                && !empty($candle->closePrices);

            return new ProviderCandleCallResult(
                success: $hasClose,
                candle: $candle,
                response: $data,
                httpStatus: 200,
                errorMessage: $hasClose ? null : 'No closing price in FMP candle response',
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

    /**
     * Stable EOD/intraday endpoints return a flat list of candles.
     *
     * @param array<int|string, mixed> $data
     */
    private function convertFmpCandleToDto(array $data): ?StockCandleDTO
    {
        $historical = $this->normalizeListResponse($data);

        // Legacy shape fallback: { "historical": [...] }
        if ($historical === [] && isset($data['historical']) && is_array($data['historical'])) {
            $historical = $data['historical'];
        }

        if ($historical === []) {
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

        $closePrices = [];
        $highPrices = [];
        $lowPrices = [];
        $openPrices = [];
        $timestamps = [];
        $volumes = [];

        foreach ($historical as $candle) {
            if (!is_array($candle)) {
                continue;
            }

            $closePrices[] = (float) ($candle['close'] ?? $candle['price'] ?? 0);
            $highPrices[] = (float) ($candle['high'] ?? 0);
            $lowPrices[] = (float) ($candle['low'] ?? 0);
            $openPrices[] = (float) ($candle['open'] ?? 0);
            $volumes[] = (int) ($candle['volume'] ?? 0);

            if (isset($candle['date'])) {
                $timestamps[] = Carbon::parse($candle['date'])->getTimestamp();
            } elseif (isset($candle['timestamp'])) {
                $timestamps[] = (int) $candle['timestamp'];
            }
        }

        if ($closePrices === []) {
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
     * @param array<int|string, mixed> $data
     * @return list<array<string, mixed>>
     */
    private function normalizeListResponse(array $data): array
    {
        if ($data === []) {
            return [];
        }

        // Associative object (single record) vs list of records
        if (array_is_list($data)) {
            /** @var list<array<string, mixed>> $data */
            return array_values(array_filter($data, 'is_array'));
        }

        if (isset($data[0]) && is_array($data[0])) {
            /** @var list<array<string, mixed>> */
            return array_values(array_filter($data, 'is_array'));
        }

        return [];
    }

    /**
     * @param array<string, mixed> $params
     * @return array<int|string, mixed>|null
     */
    private function apiRequest(string $endpoint, array $params = []): ?array
    {
        $params['apikey'] = $this->apiKey;

        $url = $this->baseUrl . ltrim($endpoint, '/') . '?' . http_build_query($params);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        Log::info('curl request to FMP: ' . $url);
        Log::info('response: ' . $response);
        Log::info('http code: ' . $httpCode);

        $decoded = is_string($response) ? json_decode($response, true) : null;

        if ($response === false) {
            throw new ProviderHttpException(
                'Error en la petición a FMP: No se recibió respuesta del servidor',
                $httpCode ?: null,
                null,
            );
        }

        if ($httpCode !== 200) {
            $errorMessage = "Código HTTP: {$httpCode}";

            if (is_array($decoded)) {
                if (isset($decoded['Error Message'])) {
                    $errorMessage .= ' - Error: ' . $decoded['Error Message'];
                } elseif (isset($decoded['Error'])) {
                    $errorMessage .= ' - Error: ' . $decoded['Error'];
                } elseif (isset($decoded['error'])) {
                    $errorMessage .= ' - Error: ' . $decoded['error'];
                } elseif (isset($decoded['message'])) {
                    $errorMessage .= ' - Mensaje: ' . $decoded['message'];
                }
            } else {
                $errorMessage .= ' - Respuesta: ' . substr((string) $response, 0, 500);
            }

            throw new ProviderHttpException(
                "Error en la petición a FMP. {$errorMessage}",
                $httpCode,
                is_array($decoded) ? $decoded : (string) $response,
            );
        }

        if (is_array($decoded) && (
            isset($decoded['Error'])
            || isset($decoded['error'])
            || isset($decoded['Error Message'])
        )) {
            throw new ProviderHttpException(
                'Error devuelto por FMP: ' . ($decoded['Error Message'] ?? $decoded['Error'] ?? $decoded['error']),
                $httpCode,
                $decoded,
            );
        }

        return is_array($decoded) ? $decoded : null;
    }
}
