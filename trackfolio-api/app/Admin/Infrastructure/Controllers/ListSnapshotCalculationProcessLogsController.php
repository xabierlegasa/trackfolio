<?php

namespace App\Admin\Infrastructure\Controllers;

use App\Admin\Infrastructure\Repository\AdminSnapshotCalculationProcessRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListSnapshotCalculationProcessLogsController
{
    public function __construct(
        private AdminSnapshotCalculationProcessRepository $repository,
    ) {}

    public function index(Request $request, int $processId): JsonResponse
    {
        $process = $this->repository->findProcess($processId);
        if ($process === null) {
            return response()->json(['message' => 'Process not found'], 404);
        }

        $perPage = max(1, min(100, (int) $request->query('per_page', 20)));
        $isin = trim((string) $request->query('isin', ''));
        $symbol = trim((string) $request->query('symbol', ''));
        $paginator = $this->repository->paginateLogs(
            $processId,
            $perPage,
            $isin !== '' ? $isin : null,
            $symbol !== '' ? $symbol : null,
        );

        $data = collect($paginator->items())->map(static function ($log): array {
            return [
                'id' => $log->id,
                'snapshot_calculation_process_id' => $log->snapshot_calculation_process_id,
                'description' => $log->description,
                'date_processed' => $log->date_processed?->format('Y-m-d'),
                'isin' => $log->isin,
                'symbol' => $log->symbol,
                'provider_request_id' => $log->provider_request_id,
                'created_at' => $log->created_at?->toIso8601String(),
            ];
        })->values()->all();

        return response()->json([
            'process' => [
                'id' => $process->id,
                'user_id' => $process->user_id,
                'status' => $process->status,
                'started_from' => $process->started_from?->format('Y-m-d'),
                'deleted_snapshots' => $process->deleted_snapshots,
                'finished_at' => $process->finished_at?->toIso8601String(),
                'created_at' => $process->created_at?->toIso8601String(),
            ],
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
