<?php

namespace App\Isin\Infrastructure\Controllers;

use App\Http\Controllers\Controller;
use App\Isin\Domain\Service\StockApiService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StockCandleController extends Controller
{
    public function __construct(
        private StockApiService $stockApiService
    ) {}

    /**
     * Get candle data (OHLCV) for a stock by ISIN.
     * Only daily resolution ('D') is supported.
     * 
     * Query parameters:
     * - isin (required): The ISIN code
     * - date (optional): Date in YYYY-MM-DD format. Defaults to today.
     * - from (optional): Unix timestamp for start time. If provided, overrides date.
     * - to (optional): Unix timestamp for end time. If provided, overrides date.
     * - provider (optional): Provider to use ('finnhub' or 'fmp'). Defaults to 'finnhub'.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $isin = $request->get('isin', '');

        if (empty($isin)) {
            return response()->json([
                'error' => 'ISIN parameter is required'
            ], 400);
        }

        // Get provider from request (optional, defaults to finnhub)
        $provider = $request->get('provider');
        if ($provider !== null && !in_array($provider, [StockApiService::PROVIDER_FINNHUB, StockApiService::PROVIDER_FMP, StockApiService::PROVIDER_ALPHAVANTAGE])) {
            return response()->json([
                'error' => 'Invalid provider. Available: ' . StockApiService::PROVIDER_FINNHUB . ', ' . StockApiService::PROVIDER_FMP . ', ' . StockApiService::PROVIDER_ALPHAVANTAGE
            ], 400);
        }

        try {
            // Search for the stock by ISIN to get the symbol
            $searchResponse = $this->stockApiService->searchByIsin($isin, $provider);

            if (!$searchResponse || empty($searchResponse->results)) {
                return response()->json([
                    'error' => 'No stock found for the provided ISIN'
                ], 404);
            }

            $symbol = $searchResponse->results[0]->symbol;

            if (!$symbol) {
                return response()->json([
                    'error' => 'Could not determine symbol for the provided ISIN'
                ], 404);
            }

            // Get parameters from request
            $fromParam = $request->get('from');
            $toParam = $request->get('to');
            $dateParam = $request->get('date');

            // Calculate timestamps
            if ($fromParam !== null && $toParam !== null) {
                // Use explicit from/to timestamps
                $fromTimestamp = (int) $fromParam;
                $toTimestamp = (int) $toParam;
            } elseif ($dateParam !== null) {
                // Use date parameter
                try {
                    $date = Carbon::parse($dateParam)->setTimezone('UTC');
                    $fromTimestamp = $date->copy()->startOfDay()->getTimestamp();
                    $toTimestamp = $date->copy()->endOfDay()->getTimestamp();
                } catch (\Exception $e) {
                    return response()->json([
                        'error' => 'Invalid date format. Use YYYY-MM-DD format.'
                    ], 400);
                }
            } else {
                // Default to today
                $date = Carbon::now()->setTimezone('UTC');
                $fromTimestamp = $date->copy()->startOfDay()->getTimestamp();
                $toTimestamp = $date->copy()->endOfDay()->getTimestamp();
            }

            Log::info('Getting candle data for symbol: ' . $symbol . ' from ' . $fromTimestamp . ' to ' . $toTimestamp . ' with resolution: D');
            
            // Get candle data with daily resolution (fixed to 'D')
            $candleData = $this->stockApiService->getCandleData($symbol, $fromTimestamp, $toTimestamp, 'D', $provider);

            if (!$candleData) {
                return response()->json([
                    'error' => 'No candle data available for the provided ISIN and parameters'
                ], 404);
            }

            return response()->json($candleData->toArray());
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

