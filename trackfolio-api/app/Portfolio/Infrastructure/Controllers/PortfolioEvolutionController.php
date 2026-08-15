<?php

namespace App\Portfolio\Infrastructure\Controllers;

use App\Http\Controllers\Controller;
use App\Portfolio\Domain\Service\PortfolioDailySnapshotService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortfolioEvolutionController extends Controller
{
    public function __construct(
        private PortfolioDailySnapshotService $portfolioDailySnapshotService,
    ) {}

    /**
     * Daily portfolio snapshots for the last N months (default 3). Chart reads this table only.
     */
    public function index(Request $request): JsonResponse
    {
        $months = max(1, min(24, (int) $request->get('months', 3)));
        $userId = (int) $request->user()->id;
        $from = Carbon::today(config('app.timezone', 'UTC'))->subMonthsNoOverflow($months)->startOfDay();

        $rows = $this->portfolioDailySnapshotService->listForUserSince($userId, $from);

        return response()->json([
            'from' => $from->toDateString(),
            'to' => Carbon::today(config('app.timezone', 'UTC'))->toDateString(),
            'months' => $months,
            'data' => $rows->map(static fn ($row) => [
                'snapshot_date' => $row->snapshot_date?->format('Y-m-d'),
                'balance_eur_min_unit' => (int) $row->balance_eur_min_unit,
                'portfolio_eur_min_unit' => (int) $row->portfolio_eur_min_unit,
                'leverage_eur_min_unit' => (int) $row->leverage_eur_min_unit,
                'day_change_eur_min_unit' => $row->day_change_eur_min_unit !== null
                    ? (int) $row->day_change_eur_min_unit
                    : null,
                'total_gain_loss_eur_min_unit' => $row->total_gain_loss_eur_min_unit !== null
                    ? (int) $row->total_gain_loss_eur_min_unit
                    : null,
            ])->values(),
        ]);
    }
}
