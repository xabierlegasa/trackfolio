<?php

namespace App\Dummy\Controllers;

use App\Http\Controllers\Controller;
use App\Isin\Domain\Service\StockApiService;
use App\User\Domain\Entity\User;
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
        // $isin = $request->get('isin', '');

        try {
            // $stockInfo = $this->stockApiService->getStockInfo($isin);

            // if (!$stockInfo) {
            //     return response()->json([
            //         'error' => 'No se encontró información para el ISIN proporcionado'
            //     ], 404);
            // }

            return response()->json($stockInfo->toArray());
            return response()->json($stockInfo->toArray());
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
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
}
