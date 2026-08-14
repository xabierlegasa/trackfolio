<?php

namespace App\Dummy\Controllers;

use App\Dummy\Application\Job\PingQueueOneJob;
use App\Http\Controllers\Controller;
use App\Isin\Domain\Service\ResolveIsinClosingPriceService;
use App\User\Domain\Entity\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DummyController extends Controller
{
    private const TEST_SYMBOL = 'TSLA';
    private const TEST_ISIN = 'US88160R1014';
    private const TEST_DATE = '2026-08-13';

    public function __construct(
        private ResolveIsinClosingPriceService $resolveIsinClosingPriceService,
    ) {}

    /**
     * Return dummy JSON data for a given ISIN.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // $isin = $request->get('isin', '');

        // try {
        //     // $stockInfo = $this->stockApiService->getStockInfo($isin);

        //     // if (!$stockInfo) {
        //     //     return response()->json([
        //     //         'error' => 'No se encontró información para el ISIN proporcionado'
        //     //     ], 404);
        //     // }

        //     return response()->json($stockInfo->toArray());
        // } catch (\Exception $e) {
        //     return response()->json([
        //         'error' => $e->getMessage()
        //     ], 500);
        // 
        return response()->json([
            'message' => 'Hello World',
        ]);
    }

    /**
     * Dispatch PingQueueOneJob to RabbitMQ queue_one (development only).
     */
    public function pingQueue(Request $request): JsonResponse
    {
        if (! app()->environment('local')) {
            return response()->json(['error' => 'Not available outside local environment'], 403);
        }

        $message = (string) $request->input('message', 'ping');

        PingQueueOneJob::dispatch($message);

        return response()->json([
            'message' => 'Job dispatched to queue_one',
            'payload' => $message,
        ]);
    }

    /**
     * Reset user id 1 password to "xabi" (development only).
     */
    public function resetPassword(): JsonResponse
    {
        if (! app()->environment('local')) {
            return response()->json(['error' => 'Not available outside local environment'], 403);
        }

        $user = User::find(1);

        if (! $user) {
            return response()->json(['error' => 'User id 1 not found'], 404);
        }

        $user->password = 'xabi';
        $user->save();

        return response()->json([
            'message' => 'Password reset successfully',
            'user_id' => $user->id,
            'email' => $user->email,
        ]);
    }

    /**
     * Test closing price for TSLA on 2026-08-13 against all providers via ResolveIsinClosingPriceService.
     * Each provider key includes a request summary plus raw "response" JSON.
     */
    public function testApi(): JsonResponse
    {
        $results = [];
        foreach (ResolveIsinClosingPriceService::providerOrder() as $providerName) {
            $results[$providerName] = $this->testProviderClosingPrice($providerName);
        }

        return response()->json([
            'isin' => self::TEST_ISIN,
            'symbol' => self::TEST_SYMBOL,
            'date' => self::TEST_DATE,
            ...$results,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function testProviderClosingPrice(string $providerName): array
    {
        $date = Carbon::parse(self::TEST_DATE, 'UTC');

        $closingPrice = $this->resolveIsinClosingPriceService->resolveForDateRange(
            isin: self::TEST_ISIN,
            fromDate: $date,
            toDate: $date,
            provider: $providerName,
            bypassCache: true,
            forcedSymbol: self::TEST_SYMBOL,
        );

        $tickerRequest = $this->resolveIsinClosingPriceService->latestTickerRequest(
            self::TEST_ISIN,
            $providerName,
        );

        if ($closingPrice === null) {
            return [
                'success' => false,
                'error_message' => $tickerRequest?->error_message ?? 'No closing price returned',
                'provider_response_http_status' => $tickerRequest?->provider_response_http_status,
                'ticker_request_id' => $tickerRequest?->id,
                'response' => $tickerRequest?->response,
            ];
        }

        return [
            'success' => true,
            'error_message' => $tickerRequest?->error_message,
            'provider_response_http_status' => $tickerRequest?->provider_response_http_status,
            'ticker_request_id' => $tickerRequest?->id ?? $closingPrice->ticker_request_id,
            'isin_quote_id' => $closingPrice->id,
            'closing_date' => $closingPrice->closing_date?->format('Y-m-d'),
            'close_price' => $closingPrice->close_price_min_unit !== null
                ? $closingPrice->close_price_min_unit / 100
                : null,
            'close_price_min_unit' => $closingPrice->close_price_min_unit,
            'open_price_min_unit' => $closingPrice->open_price_min_unit,
            'high_price_min_unit' => $closingPrice->high_price_min_unit,
            'low_price_min_unit' => $closingPrice->low_price_min_unit,
            'volume' => $closingPrice->volume,
            'response' => $tickerRequest?->response,
        ];
    }
}
