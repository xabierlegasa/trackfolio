<?php

namespace App\Isin\Domain\Service\Provider;

use App\Isin\Domain\DTO\StockCandleDTO;
use App\Isin\Domain\DTO\StockQuoteDTO;
use App\Isin\Domain\DTO\StockSearchResponseDTO;
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

    /**
     * Search for a stock by ISIN.
     *
     * @param string $isin
     * @return StockSearchResponseDTO|null
     * @throws \Exception
     */
    public function searchByIsin(string $isin): ?StockSearchResponseDTO
    {
        $data = $this->apiRequest('search', ['q' => $isin]);
        
        if (!$data) {
            return null;
        }

        return StockSearchResponseDTO::fromArray($data);
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
        $data = $this->apiRequest('quote', ['symbol' => $symbol]);
        
        if (!$data) {
            return null;
        }

        return StockQuoteDTO::fromArray($data);
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
        $endpoint = 'stock/candle';
        $params = [
            'symbol' => strtoupper($symbol),
            'resolution' => $resolution,
            'from' => $fromTimestamp,
            'to' => $toTimestamp,
        ];

        try {
            $data = $this->apiRequest($endpoint, $params);

            if (!$data) {
                return null;
            }

            return StockCandleDTO::fromArray($data);
        } catch (\Exception $e) {
            error_log("Error en la petición Finnhub para {$symbol}. Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Make a request to Finnhub API.
     *
     * @param string $endpoint
     * @param array $params
     * @return array|null
     * @throws \Exception
     */
    private function apiRequest(string $endpoint, array $params = []): ?array
    {
        $queryParams = http_build_query(array_merge($params, ['token' => $this->apiKey]));
        $url = $this->baseUrl . $endpoint . '?' . $queryParams;

        // Inicializar cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Ejecutar la petición
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        Log::info('curl request to Finnhub: ' . $url);
        Log::info('response: ' . $response);
        Log::info('http code: ' . $httpCode);

        // Manejo de errores básicos
        if ($response === false || $httpCode !== 200) {
            throw new \Exception("Error en la petición a Finnhub. Código HTTP: {$httpCode}");
        }

        return json_decode($response, true);
    }
}

