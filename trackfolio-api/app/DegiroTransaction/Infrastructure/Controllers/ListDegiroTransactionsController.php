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
        $perPage = (int) $request->get('per_page', 10);

        // Ensure per_page is within reasonable bounds
        $perPage = max(1, min($perPage, 100));

        $sortOrder = strtolower((string) $request->get('sort_order', 'desc'));
        if (! in_array($sortOrder, ['asc', 'desc'], true)) {
            $sortOrder = 'desc';
        }

        $product = trim((string) $request->get('product', ''));
        if (strlen($product) > 200) {
            $product = substr($product, 0, 200);
        }
        $productFilter = $product !== '' ? $product : null;

        $transactions = $this->repository->findPaginatedByUserId($user->id, $perPage, $sortOrder, $productFilter);

        return response()->json([
            'data' => $transactions->items(),
            'current_page' => $transactions->currentPage(),
            'per_page' => $transactions->perPage(),
            'total' => $transactions->total(),
            'last_page' => $transactions->lastPage(),
        ]);
    }
}

