<?php

namespace App\User\Controllers;

use App\DegiroTransaction\Infrastructure\Repository\DegiroTransactionRepository;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private DegiroTransactionRepository $degiroTransactionRepository
    ) {}

    /**
     * Get the authenticated user's account information.
     */
    public function account(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'email' => $user->email,
            'name' => $user->name,
        ]);
    }

    /**
     * Get the count of Degiro transactions for the authenticated user.
     */
    public function degiroTransactionsCount(Request $request): JsonResponse
    {
        $user = $request->user();
        $count = $this->degiroTransactionRepository->countByUserId($user->id);

        return response()->json([
            'count' => $count,
        ]);
    }
}

