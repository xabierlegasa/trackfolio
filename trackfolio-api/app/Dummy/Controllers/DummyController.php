<?php

namespace App\Dummy\Controllers;

use App\Http\Controllers\Controller;
use App\Isin\Domain\Service\StockApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DummyController extends Controller
{
    public function __construct(
        private StockApiService $stockApiService
    ) {}

    /**
     * Return dummy JSON data for a given ISIN.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $isin = $request->get('isin', '');

        try {
            $stockInfo = $this->stockApiService->getStockInfo($isin);

            if (!$stockInfo) {
                return response()->json([
                    'error' => 'No se encontró información para el ISIN proporcionado'
                ], 404);
            }

            return response()->json($stockInfo->toArray());
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
