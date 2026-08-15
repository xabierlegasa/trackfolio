<?php

namespace App\User\Controllers;

use App\Http\Controllers\Controller;
use App\User\Domain\Entity\UserLeverage;
use App\User\Domain\Service\ResolveUserLeverageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserLeverageController extends Controller
{
    public function __construct(
        private ResolveUserLeverageService $resolveUserLeverageService,
    ) {}

    /**
     * Current leverage + recent history for the authenticated user.
     */
    public function show(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;
        $amount = $this->resolveUserLeverageService->currentAmountEurMinUnit($userId);

        $history = UserLeverage::query()
            ->where('user_id', $userId)
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get(['id', 'amount_eur_min_unit', 'recorded_at']);

        return response()->json([
            'amount_eur_min_unit' => $amount,
            'history' => $history,
        ]);
    }

    /**
     * Append a new leverage snapshot (keeps full history).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount_eur_min_unit' => ['required', 'integer', 'min:0'],
        ]);

        $userId = (int) $request->user()->id;
        $row = $this->resolveUserLeverageService->record(
            $userId,
            (int) $validated['amount_eur_min_unit'],
        );

        return response()->json([
            'id' => $row->id,
            'amount_eur_min_unit' => (int) $row->amount_eur_min_unit,
            'recorded_at' => $row->recorded_at?->toIso8601String(),
        ], 201);
    }
}
