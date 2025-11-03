<?php

namespace App\DegiroTransaction\Infrastructure\Controllers;

use App\DegiroTransaction\Infrastructure\Repository\DegiroTransactionRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ListDegiroTransactionsController
{
    public function __construct(
        private DegiroTransactionRepository $repository
    ) {}

    /**
     * Get paginated list of Degiro transactions for the authenticated user.
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

        $transactions = $this->repository->findPaginatedByUserId($user->id, $perPage);

        return response()->json([
            'data' => $transactions->items(),
            'current_page' => $transactions->currentPage(),
            'per_page' => $transactions->perPage(),
            'total' => $transactions->total(),
            'last_page' => $transactions->lastPage(),
        ]);
    }
}

