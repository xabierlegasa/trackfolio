<?php

namespace App\Isin\Domain\Service\Provider;

use App\Isin\Domain\DTO\StockCandleDTO;
use App\Isin\Domain\DTO\StockQuoteDTO;
use App\Isin\Domain\DTO\StockSearchResponseDTO;
use App\Isin\Domain\DTO\StockSearchResultDTO;
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
        $this->baseUrl = 'https://financialmodelingprep.com/api/';

        if (empty($this->apiKey)) {
            throw new \RuntimeException('FMP_API_KEY is not set in environment variables');
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
        $data = $this->apiRequest('v4/search/isin', ['isin' => $isin]);
        
        if (!$data || empty($data)) {
            return null;
        }

        // FMP devuelve un array directamente, no un objeto con 'result'
        // Convertir el formato de FMP al formato esperado por el DTO
        $results = [];
        foreach ($data as $item) {
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
     * Get quote information for a symbol.
     *
     * @param string $symbol
     * @return StockQuoteDTO|null
     * @throws \Exception
     */
    public function getQuote(string $symbol): ?StockQuoteDTO
    {
        // FMP usa el endpoint 'quote' para obtener cotizaciones
        $data = $this->apiRequest('quote/' . strtoupper($symbol));
        
        if (!$data || empty($data)) {
            return null;
        }

        // FMP devuelve un array, tomamos el primer elemento
        $quote = is_array($data) && isset($data[0]) ? $data[0] : $data;

        // Mapear los campos de FMP al formato del DTO
        // FMP usa: price, change, changesPercentage, dayLow, dayHigh, open, previousClose
        return new StockQuoteDTO(
            currentPrice: isset($quote['price']) ? (float) $quote['price'] : null,
            change: isset($quote['change']) ? (float) $quote['change'] : null,
            percentChange: isset($quote['changesPercentage']) ? (float) $quote['changesPercentage'] : null,
            highPrice: isset($quote['dayHigh']) ? (float) $quote['dayHigh'] : null,
            lowPrice: isset($quote['dayLow']) ? (float) $quote['dayLow'] : null,
            openPrice: isset($quote['open']) ? (float) $quote['open'] : null,
            previousClose: isset($quote['previousClose']) ? (float) $quote['previousClose'] : null,
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
        // Convertir timestamps a fechas
        $fromDate = Carbon::createFromTimestamp($fromTimestamp)->format('Y-m-d');
        $toDate = Carbon::createFromTimestamp($toTimestamp)->format('Y-m-d');

        // FMP usa el endpoint 'historical-price-full' para datos históricos diarios
        // Para otros timeframes, usaría 'historical-chart/1min', 'historical-chart/5min', etc.
        if ($resolution === 'D') {
            $endpoint = 'historical-price-full/' . strtoupper($symbol);
            $params = [
                'from' => $fromDate,
                'to' => $toDate,
            ];
        } else {
            // Para otros timeframes, FMP usa diferentes endpoints
            $timeframeMap = [
                '1' => '1min',
                '5' => '5min',
                '15' => '15min',
                '30' => '30min',
                '60' => '1hour',
            ];
            
            $timeframe = $timeframeMap[$resolution] ?? '1day';
            $endpoint = 'historical-chart/' . $timeframe . '/' . strtoupper($symbol);
            $params = [];
        }

        try {
            $data = $this->apiRequest($endpoint, $params);

            if (!$data) {
                return null;
            }

            // Convertir formato de FMP al formato del DTO
            return $this->convertFmpCandleToDto($data, $resolution === 'D');
        } catch (\Exception $e) {
            error_log("Error en la petición FMP para {$symbol}. Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Convert FMP candle data format to StockCandleDTO.
     *
     * @param array $data
     * @param bool $isDaily
     * @return StockCandleDTO|null
     */
    private function convertFmpCandleToDto(array $data, bool $isDaily): ?StockCandleDTO
    {
        if ($isDaily) {
            // Para daily, FMP devuelve: { "historical": [...] }
            $historical = $data['historical'] ?? [];
        } else {
            // Para intraday, FMP devuelve directamente un array
            $historical = is_array($data) && isset($data[0]) ? $data : [];
        }

        if (empty($historical)) {
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
            $closePrices[] = (float) ($candle['close'] ?? 0);
            $highPrices[] = (float) ($candle['high'] ?? 0);
            $lowPrices[] = (float) ($candle['low'] ?? 0);
            $openPrices[] = (float) ($candle['open'] ?? 0);
            $volumes[] = (int) ($candle['volume'] ?? 0);
            
            // FMP usa formato de fecha, convertir a timestamp
            if (isset($candle['date'])) {
                $date = Carbon::parse($candle['date']);
                $timestamps[] = $date->getTimestamp();
            } elseif (isset($candle['timestamp'])) {
                $timestamps[] = (int) $candle['timestamp'];
            }
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
     * Make a request to FMP API.
     *
     * @param string $endpoint
     * @param array $params
     * @return array|null
     * @throws \Exception
     */
    private function apiRequest(string $endpoint, array $params = []): ?array
    {
        // Añadir la clave de API automáticamente
        $params['apikey'] = $this->apiKey;
        
        $url = $this->baseUrl . $endpoint . '?' . http_build_query($params);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        Log::info('curl request to FMP: ' . $url);
        Log::info('response: ' . $response);
        Log::info('http code: ' . $httpCode);

        if ($response === false) {
            throw new \Exception("Error en la petición a FMP: No se recibió respuesta del servidor");
        }

        if ($httpCode !== 200) {
            // Intentar decodificar el mensaje de error de la respuesta
            $errorMessage = "Código HTTP: {$httpCode}";
            $decodedResponse = json_decode($response, true);
            
            if (is_array($decodedResponse)) {
                if (isset($decodedResponse['Error'])) {
                    $errorMessage .= " - Error: " . $decodedResponse['Error'];
                } elseif (isset($decodedResponse['error'])) {
                    $errorMessage .= " - Error: " . $decodedResponse['error'];
                } elseif (isset($decodedResponse['message'])) {
                    $errorMessage .= " - Mensaje: " . $decodedResponse['message'];
                }
            } else {
                // Si no es JSON, usar el texto de la respuesta directamente
                $errorMessage .= " - Respuesta: " . substr($response, 0, 500);
            }
            
            throw new \Exception("Error en la petición a FMP. {$errorMessage}");
        }

        $data = json_decode($response, true);
        
        // FMP a veces devuelve un objeto de error incluso con HTTP 200
        if (isset($data['Error']) || isset($data['error'])) {
            throw new \Exception("Error devuelto por FMP: " . ($data['Error'] ?? $data['error']));
        }
        
        return $data;
    }
}

