<?php

namespace App\DegiroTransaction\Infrastructure\Controllers;

use App\DegiroTransaction\Infrastructure\Repository\DegiroTransactionRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class DeleteAllDegiroTransactionsController
{
    public function __construct(
        private DegiroTransactionRepository $repository
    ) {}

    /**
     * Delete all Degiro transactions for the authenticated user.
     */
    public function destroy(): JsonResponse
    {
        $user = Auth::user();
        $deleted = $this->repository->deleteAllForUser($user->id);

        return response()->json([
            'message' => 'All Degiro transactions have been deleted.',
            'deleted_count' => $deleted,
        ]);
    }
}
