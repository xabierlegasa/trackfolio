<?php

namespace App\DegiroTransaction\Infrastructure\Controllers;

use App\DegiroTransaction\Infrastructure\Repository\DegiroTransactionRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TradesController
{
    public function __construct(
        private DegiroTransactionRepository $repository
    ) {}

    /**
     * Get paginated closed trades for the authenticated user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $perPage = (int) $request->get('per_page', 10);
        $sortBy = $request->get('sort_by', 'last_sale_date');
        $sortOrder = $request->get('sort_order', 'desc');

        // Ensure per_page is within reasonable bounds
        $perPage = max(1, min($perPage, 100));

        $trades = $this->repository->getClosedTrades($user->id, $perPage, $sortBy, $sortOrder);

        return response()->json([
            'data' => $trades->items(),
            'current_page' => $trades->currentPage(),
            'per_page' => $trades->perPage(),
            'total' => $trades->total(),
            'last_page' => $trades->lastPage(),
        ]);
    }
}

