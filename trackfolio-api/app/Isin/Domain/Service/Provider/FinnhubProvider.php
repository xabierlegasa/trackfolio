<?php

namespace App\Isin\Domain\Service\Provider;

use App\Isin\Domain\DTO\ProviderCandleCallResult;
use App\Isin\Domain\DTO\StockCandleDTO;
use App\Isin\Domain\DTO\StockQuoteDTO;
use App\Isin\Domain\DTO\StockSearchResponseDTO;
use App\Isin\Domain\Exception\ProviderHttpException;
use App\Isin\Domain\Service\ThrottleStockApiRequestService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class FinnhubProvider implements StockApiProviderInterface
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = Config::get('stock_api.finnhub.api_key', '');
        $this->baseUrl = 'https://finnhub.io/api/v1/';

        if (empty($this->apiKey)) {
            throw new \RuntimeException('FINNHUB_API_KEY is not set in environment variables');
        }
    }

    public function searchByIsin(string $isin): ?StockSearchResponseDTO
    {
        $data = $this->apiRequest('search', ['q' => $isin]);

        if (!$data) {
            return null;
        }

        return StockSearchResponseDTO::fromArray($data);
    }

    public function getQuote(string $symbol): ?StockQuoteDTO
    {
        $data = $this->apiRequest('quote', ['symbol' => $symbol]);

        if (!$data) {
            return null;
        }

        return StockQuoteDTO::fromArray($data);
    }

    public function getCandleData(string $symbol, int $fromTimestamp, int $toTimestamp, string $resolution = 'D'): ?StockCandleDTO
    {
        return $this->fetchCandleData($symbol, $fromTimestamp, $toTimestamp, $resolution)->candle;
    }

    /**
     * Free Finnhub plans do not include historical candles (/stock/candle → 403).
     * /quote is live only and must never be used for past as-of dates.
     *
     * Allowed only when the requested session date is today or yesterday UTC
     * (live last close via previousClose / current).
     */
    public function fetchCandleData(string $symbol, int $fromTimestamp, int $toTimestamp, string $resolution = 'D'): ProviderCandleCallResult
    {
        $requestedDate = Carbon::createFromTimestamp($fromTimestamp, 'UTC')->toDateString();
        $today = Carbon::now('UTC')->toDateString();
        $yesterday = Carbon::yesterday('UTC')->toDateString();

        if ($requestedDate < $yesterday) {
            return new ProviderCandleCallResult(
                success: false,
                candle: null,
                response: null,
                httpStatus: null,
                errorMessage: "Finnhub /quote cannot provide historical close for {$requestedDate}; use FMP or Alpha Vantage",
            );
        }

        try {
            $data = $this->apiRequest('quote', ['symbol' => strtoupper($symbol)]);

            if (!$data) {
                return new ProviderCandleCallResult(
                    success: false,
                    candle: null,
                    response: $data,
                    httpStatus: 200,
                    errorMessage: 'Empty response from Finnhub quote',
                );
            }

            $usePreviousClose = $requestedDate < $today;

            $close = $usePreviousClose
                ? (isset($data['pc']) ? (float) $data['pc'] : null)
                : (isset($data['c']) ? (float) $data['c'] : null);

            if ($close === null || $close <= 0) {
                return new ProviderCandleCallResult(
                    success: false,
                    candle: null,
                    response: $data,
                    httpStatus: 200,
                    errorMessage: $usePreviousClose
                        ? 'No previousClose (pc) in Finnhub quote response'
                        : 'No current price (c) in Finnhub quote response',
                );
            }

            if ($usePreviousClose) {
                $open = $close;
                $high = $close;
                $low = $close;
                $timestamp = Carbon::parse($requestedDate, 'UTC')->startOfDay()->getTimestamp();
            } else {
                $open = isset($data['o']) ? (float) $data['o'] : $close;
                $high = isset($data['h']) ? (float) $data['h'] : $close;
                $low = isset($data['l']) ? (float) $data['l'] : $close;
                $timestamp = isset($data['t']) ? (int) $data['t'] : $toTimestamp;
            }

            $candle = new StockCandleDTO(
                status: 'ok',
                closePrices: [$close],
                highPrices: [$high],
                lowPrices: [$low],
                openPrices: [$open],
                timestamps: [$timestamp],
                volumes: [0],
            );

            return new ProviderCandleCallResult(
                success: true,
                candle: $candle,
                response: $data,
                httpStatus: 200,
                errorMessage: null,
            );
        } catch (ProviderHttpException $e) {
            return new ProviderCandleCallResult(
                success: false,
                candle: null,
                response: $e->rawResponse,
                httpStatus: $e->httpStatus,
                errorMessage: $e->getMessage(),
                rateLimited: $e->httpStatus === 429 || str_contains(strtolower($e->getMessage()), 'too many requests'),
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
     * @return array<string, mixed>|null
     */
    public function getMarketStatus(string $exchange = 'US'): ?array
    {
        try {
            return $this->apiRequest('stock/market-status', ['exchange' => $exchange]);
        } catch (\Throwable $e) {
            Log::warning('Finnhub market-status failed: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getMarketHoliday(string $exchange = 'US'): ?array
    {
        try {
            return $this->apiRequest('stock/market-holiday', ['exchange' => $exchange]);
        } catch (\Throwable $e) {
            Log::warning('Finnhub market-holiday failed: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    private function apiRequest(string $endpoint, array $params = []): ?array
    {
        $queryParams = http_build_query(array_merge($params, ['token' => $this->apiKey]));
        $url = $this->baseUrl . $endpoint . '?' . $queryParams;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        Log::info('curl request to Finnhub: ' . ThrottleStockApiRequestService::redactSecretsInUrl($url));
        Log::info('response: ' . $response);
        Log::info('http code: ' . $httpCode);

        $decoded = is_string($response) ? json_decode($response, true) : null;

        if ($response === false || $httpCode !== 200) {
            throw new ProviderHttpException(
                "Error en la petición a Finnhub. Código HTTP: {$httpCode}",
                $httpCode ?: null,
                is_array($decoded) ? $decoded : (is_string($response) ? $response : null),
            );
        }

        return is_array($decoded) ? $decoded : null;
    }
}
