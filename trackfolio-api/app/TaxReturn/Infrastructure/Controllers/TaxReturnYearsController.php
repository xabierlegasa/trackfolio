<?php

namespace App\TaxReturn\Infrastructure\Controllers;

use App\DegiroTransaction\Infrastructure\Repository\DegiroTransactionRepository;
use App\TaxReturn\Domain\Exception\InsufficientFifoInventoryException;
use App\TaxReturn\Domain\Service\FifoTaxYearReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class TaxReturnYearsController
{
    public function __construct(
        private DegiroTransactionRepository $transactions,
        private FifoTaxYearReportService $fifoTaxYearReport,
    ) {}

    public function index(): JsonResponse
    {
        $user = Auth::user();
        $minYear = $this->transactions->minTransactionYear($user->id);

        if ($minYear === null) {
            return response()->json([
                'years' => [],
                'evolution' => [],
            ]);
        }

        $currentYear = (int) date('Y');
        $years = [];
        for ($y = $currentYear; $y >= $minYear; $y--) {
            $years[] = $y;
        }

        $evolution = [];
        foreach ($years as $y) {
            try {
                $report = $this->fifoTaxYearReport->buildReport($user->id, $y);
                $evolution[] = [
                    'year' => $y,
                    'total_net_gain_cents' => $report['total_net_gain_cents'],
                ];
            } catch (InsufficientFifoInventoryException) {
                $evolution[] = [
                    'year' => $y,
                    'total_net_gain_cents' => null,
                    'fifo_incomplete' => true,
                ];
            }
        }

        return response()->json([
            'years' => $years,
            'evolution' => $evolution,
        ]);
    }
}
