<?php

namespace App\Portfolio\Infrastructure\Controllers;

use App\Http\Controllers\Controller;
use App\Portfolio\Domain\Service\BuildPortfolioEvolutionViewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortfolioEvolutionController extends Controller
{
    public function __construct(
        private BuildPortfolioEvolutionViewService $buildPortfolioEvolutionViewService,
    ) {}

    /**
     * Portfolio evolution series for charts (day / month / year).
     */
    public function index(Request $request): JsonResponse
    {
        $granularity = strtolower((string) $request->query('granularity', 'day'));
        $requestedYear = $request->query('year');
        $year = is_numeric($requestedYear) ? (int) $requestedYear : null;

        return response()->json(
            $this->buildPortfolioEvolutionViewService->build(
                (int) $request->user()->id,
                $granularity,
                $year,
            ),
        );
    }
}
