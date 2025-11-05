<?php

namespace App\DegiroTransaction\Infrastructure\Controllers;

use App\DegiroTransaction\Infrastructure\Repository\DegiroTransactionRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PortfolioStatsController
{
    public function __construct(
        private DegiroTransactionRepository $repository
    ) {}

    /**
     * Get paginated portfolio holdings for the authenticated user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $perPage = (int) $request->get('per_page', 20);

        // Ensure per_page is within reasonable bounds
        $perPage = max(1, min($perPage, 100));

        $holdings = $this->repository->getPortfolioHoldings($user->id, $perPage);

        return response()->json([
            'data' => $holdings->items(),
            'current_page' => $holdings->currentPage(),
            'per_page' => $holdings->perPage(),
            'total' => $holdings->total(),
            'last_page' => $holdings->lastPage(),
        ]);
    }
}

