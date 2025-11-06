<?php

namespace App\DegiroTransaction\Infrastructure\Controllers;

use App\DegiroTransaction\Infrastructure\Repository\DegiroTransactionRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class TradesSummaryController
{
    public function __construct(
        private DegiroTransactionRepository $repository
    ) {}

    /**
     * Get trades summary for the authenticated user.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();
        $summary = $this->repository->getTradesSummary($user->id);

        return response()->json($summary);
    }
}

