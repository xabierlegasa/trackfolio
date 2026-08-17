<?php

namespace App\Isin\Domain\Service\Provider;

use App\Isin\Domain\DTO\ProviderCandleCallResult;
use App\Isin\Domain\DTO\StockCandleDTO;
use App\Isin\Domain\DTO\StockQuoteDTO;
use App\Isin\Domain\DTO\StockSearchResponseDTO;
use App\Isin\Domain\DTO\StockSearchResultDTO;
use App\Isin\Domain\Exception\ProviderHttpException;
use App\Isin\Domain\Service\RecordProviderRequestService;
use App\Isin\Domain\Service\StockApiService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class EodhdProvider implements StockApiProviderInterface
{
    public const CALL_TYPE_SEARCH = 'search';
    public const CALL_TYPE_EOD = 'eod';
    public const CALL_TYPE_REAL_TIME = 'real_time';

    private string $apiToken;
    private string $baseUrl;
    private RecordProviderRequestService $recorder;
    private ?int $lastRequestId = null;

    public function __construct(?RecordProviderRequestService $recorder = null)
    {
        $this->apiToken = (string) Config::get('stock_api.eodhd.api_token', '');
        $this->baseUrl = rtrim((string) Config::get('stock_api.eodhd.base_url', 'https://eodhd.com/api'), '/');
        $this->recorder = $recorder ?? new RecordProviderRequestService();

        if ($this->apiToken === '') {
            throw new \RuntimeException('EODHD_API_TOKEN is not set in environment variables');
        }
    }

    public function lastRequestId(): ?int
    {
        return $this->lastRequestId;
    }

    public function searchByIsin(string $isin): ?StockSearchResponseDTO
    {
        $pick = $this->searchByIsinWithExchange($isin);
        if ($pick === null) {
            return null;
        }

        return new StockSearchResponseDTO([
            new StockSearchResultDTO(
                description: $pick['name'] ?? '',
                displaySymbol: $pick['code'] ?? $pick['symbol'],
                symbol: $pick['symbol'],
                type: $pick['type'] ?? 'Common Stock',
            ),
        ]);
    }

    /**
     * @return array{
     *   symbol: string,
     *   stock_exchange: ?string,
     *   code: ?string,
     *   name: ?string,
     *   type: ?string
     * }|null
     */
    public function searchByIsinWithExchange(string $isin): ?array
    {
        $data = $this->apiRequest(
            callType: self::CALL_TYPE_SEARCH,
            path: '/search/' . rawurlencode($isin),
            query: ['fmt' => 'json', 'limit' => 15],
        );

        if (!$data || !is_array($data)) {
            return null;
        }

        $items = array_values(array_filter($data, 'is_array'));
        $picked = $this->pickBestSearchResult($items, $isin);
        if ($picked === null || empty($picked['Code'])) {
            return null;
        }

        $code = (string) $picked['Code'];
        $exchange = isset($picked['Exchange']) ? (string) $picked['Exchange'] : 'US';

        return [
            'symbol' => $this->composeTicker($code, $exchange),
            'stock_exchange' => $exchange,
            'code' => $code,
            'name' => isset($picked['Name']) ? (string) $picked['Name'] : null,
            'type' => isset($picked['Type']) ? (string) $picked['Type'] : null,
        ];
    }

    public function getQuote(string $symbol): ?StockQuoteDTO
    {
        $ticker = $this->normalizeTicker($symbol);
        $data = $this->apiRequest(
            callType: self::CALL_TYPE_REAL_TIME,
            path: '/real-time/' . rawurlencode($ticker),
            query: ['fmt' => 'json'],
        );

        if (!$data || !is_array($data)) {
            return null;
        }

        $scale = $this->isLseTicker($ticker) ? 0.01 : 1.0;

        return new StockQuoteDTO(
            currentPrice: isset($data['close']) ? (float) $data['close'] * $scale : null,
            change: isset($data['change']) ? (float) $data['change'] * $scale : null,
            percentChange: isset($data['change_p']) ? (float) $data['change_p'] : null,
            highPrice: isset($data['high']) ? (float) $data['high'] * $scale : null,
            lowPrice: isset($data['low']) ? (float) $data['low'] * $scale : null,
            openPrice: isset($data['open']) ? (float) $data['open'] * $scale : null,
            previousClose: isset($data['previousClose']) ? (float) $data['previousClose'] * $scale : null,
        );
    }

    public function getCandleData(string $symbol, int $fromTimestamp, int $toTimestamp, string $resolution = 'D'): ?StockCandleDTO
    {
        return $this->fetchCandleData($symbol, $fromTimestamp, $toTimestamp, $resolution)->candle;
    }

    public function fetchCandleData(string $symbol, int $fromTimestamp, int $toTimestamp, string $resolution = 'D'): ProviderCandleCallResult
    {
        $ticker = $this->normalizeTicker($symbol);
        $fromDate = Carbon::createFromTimestamp($fromTimestamp)->format('Y-m-d');
        $toDate = Carbon::createFromTimestamp($toTimestamp)->format('Y-m-d');
        $period = match ($resolution) {
            'W' => 'w',
            'M' => 'm',
            default => 'd',
        };

        try {
            $data = $this->apiRequest(
                callType: self::CALL_TYPE_EOD,
                path: '/eod/' . rawurlencode($ticker),
                query: [
                    'from' => $fromDate,
                    'to' => $toDate,
                    'period' => $period,
                    'fmt' => 'json',
                    'order' => 'a',
                ],
            );

            if (!$data || !is_array($data)) {
                return new ProviderCandleCallResult(
                    success: false,
                    candle: null,
                    response: $data,
                    httpStatus: 200,
                    errorMessage: 'Empty response from EODHD',
                    providerRequestId: $this->lastRequestId,
                );
            }

            $candle = $this->convertEodToDto($data, $ticker);
            $hasClose = $candle !== null
                && $candle->status === 'ok'
                && !empty($candle->closePrices);

            return new ProviderCandleCallResult(
                success: $hasClose,
                candle: $candle,
                response: $data,
                httpStatus: 200,
                errorMessage: $hasClose ? null : 'No closing price in EODHD response',
                providerRequestId: $this->lastRequestId,
            );
        } catch (ProviderHttpException $e) {
            return new ProviderCandleCallResult(
                success: false,
                candle: null,
                response: $e->rawResponse,
                httpStatus: $e->httpStatus,
                errorMessage: $e->getMessage(),
                rateLimited: $e->httpStatus === 429,
                providerRequestId: $this->lastRequestId,
            );
        } catch (\Throwable $e) {
            return new ProviderCandleCallResult(
                success: false,
                candle: null,
                response: null,
                httpStatus: null,
                errorMessage: $e->getMessage(),
                providerRequestId: $this->lastRequestId,
            );
        }
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return array<string, mixed>|null
     */
    private function pickBestSearchResult(array $items, string $isin): ?array
    {
        if ($items === []) {
            return null;
        }

        $isinUpper = strtoupper($isin);
        $exact = [];
        foreach ($items as $item) {
            $itemIsin = isset($item['ISIN']) ? strtoupper((string) $item['ISIN']) : '';
            if ($itemIsin === $isinUpper) {
                $exact[] = $item;
            }
        }

        $pool = $exact !== [] ? $exact : $items;

        usort($pool, function (array $a, array $b): int {
            $aPrimary = !empty($a['isPrimary']) ? 1 : 0;
            $bPrimary = !empty($b['isPrimary']) ? 1 : 0;

            return $bPrimary <=> $aPrimary;
        });

        return $pool[0] ?? null;
    }

    public function normalizeTicker(string $symbol, ?string $exchange = null): string
    {
        $symbol = strtoupper(trim($symbol));
        if ($symbol === '') {
            return $symbol;
        }

        if (str_contains($symbol, '.')) {
            return $symbol;
        }

        return $this->composeTicker($symbol, $exchange ?? 'US');
    }

    private function composeTicker(string $code, string $exchange): string
    {
        return strtoupper(trim($code)) . '.' . strtoupper(trim($exchange));
    }

    /**
     * @param array<int|string, mixed> $data
     */
    private function convertEodToDto(array $data, string $ticker): ?StockCandleDTO
    {
        $rows = array_is_list($data)
            ? array_values(array_filter($data, 'is_array'))
            : [];

        if ($rows === []) {
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

        $scale = $this->isLseTicker($ticker) ? 0.01 : 1.0;
        $closePrices = [];
        $highPrices = [];
        $lowPrices = [];
        $openPrices = [];
        $timestamps = [];
        $volumes = [];

        foreach ($rows as $row) {
            if (!isset($row['date'])) {
                continue;
            }

            $closePrices[] = (float) ($row['close'] ?? 0) * $scale;
            $highPrices[] = (float) ($row['high'] ?? 0) * $scale;
            $lowPrices[] = (float) ($row['low'] ?? 0) * $scale;
            $openPrices[] = (float) ($row['open'] ?? 0) * $scale;
            $volumes[] = (int) ($row['volume'] ?? 0);
            $timestamps[] = Carbon::parse((string) $row['date'], 'UTC')->startOfDay()->getTimestamp();
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
     * LSE equities/ETCs are quoted in GBX (pence). EODHD returns GBX; we store GBP.
     */
    private function isLseTicker(string $ticker): bool
    {
        return str_ends_with(strtoupper(trim($ticker)), '.LSE');
    }

    /**
     * @param array<string, scalar|null> $query
     * @return array<int|string, mixed>|null
     */
    private function apiRequest(string $callType, string $path, array $query = []): ?array
    {
        $query['api_token'] = $this->apiToken;
        $url = $this->baseUrl . $path . '?' . http_build_query($query);

        $startedAt = hrtime(true);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $durationMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);
        $responseBody = is_string($response) ? $response : null;
        $decoded = is_string($response) ? json_decode($response, true) : null;

        Log::info('EODHD request', [
            'call_type' => $callType,
            'http_status' => $httpCode,
            'duration_ms' => $durationMs,
        ]);

        if ($response === false) {
            $message = 'EODHD curl error: ' . ($curlError !== '' ? $curlError : 'no response');
            $recorded = $this->recorder->record(
                provider: StockApiService::PROVIDER_EODHD,
                callType: $callType,
                method: 'GET',
                url: $url,
                httpStatus: $httpCode ?: null,
                responseBody: null,
                durationMs: $durationMs,
                success: false,
                errorMessage: $message,
            );
            $this->lastRequestId = $recorded->id;

            throw new ProviderHttpException($message, $httpCode ?: null, null);
        }

        if ($httpCode !== 200) {
            $errorMessage = "EODHD HTTP {$httpCode}";
            if (is_array($decoded)) {
                $errorMessage .= ' - ' . ($decoded['message'] ?? $decoded['error'] ?? $decoded['Error'] ?? json_encode($decoded));
            } else {
                $errorMessage .= ' - ' . substr((string) $responseBody, 0, 500);
            }

            $recorded = $this->recorder->record(
                provider: StockApiService::PROVIDER_EODHD,
                callType: $callType,
                method: 'GET',
                url: $url,
                httpStatus: $httpCode,
                responseBody: $responseBody,
                durationMs: $durationMs,
                success: false,
                errorMessage: $errorMessage,
            );
            $this->lastRequestId = $recorded->id;

            throw new ProviderHttpException($errorMessage, $httpCode, is_array($decoded) ? $decoded : $responseBody);
        }

        $recorded = $this->recorder->record(
            provider: StockApiService::PROVIDER_EODHD,
            callType: $callType,
            method: 'GET',
            url: $url,
            httpStatus: $httpCode,
            responseBody: $responseBody,
            durationMs: $durationMs,
            success: true,
            errorMessage: null,
        );
        $this->lastRequestId = $recorded->id;

        return is_array($decoded) ? $decoded : null;
    }
}
