<?php

namespace App\TaxReturn\Infrastructure\Controllers;

use App\TaxReturn\Domain\Exception\InsufficientFifoInventoryException;
use App\TaxReturn\Domain\Service\FifoTaxYearReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class TaxReturnYearDetailController
{
    private const MIN_YEAR = 1990;

    public function __construct(
        private FifoTaxYearReportService $fifoTaxYearReport,
    ) {}

    public function show(int $year): JsonResponse
    {
        $maxYear = (int) date('Y') + 1;
        if ($year < self::MIN_YEAR || $year > $maxYear) {
            return response()->json([
                'message' => 'Invalid tax year.',
            ], 400);
        }

        $user = Auth::user();

        try {
            $report = $this->fifoTaxYearReport->buildReport($user->id, $year);
        } catch (InsufficientFifoInventoryException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'isin' => $e->isin,
                'date' => $e->date,
            ], 422);
        }

        return response()->json($report);
    }
}
