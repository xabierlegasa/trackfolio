<?php

namespace App\Admin\Infrastructure\Controllers;

use App\Admin\Infrastructure\Repository\AdminSnapshotCalculationProcessRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListSnapshotCalculationProcessesController
{
    public function __construct(
        private AdminSnapshotCalculationProcessRepository $repository,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, (int) $request->query('per_page', 20)));
        $paginator = $this->repository->paginateProcesses($perPage);

        $data = collect($paginator->items())->map(static function ($process): array {
            return [
                'id' => $process->id,
                'user_id' => $process->user_id,
                'status' => $process->status,
                'started_from' => $process->started_from?->format('Y-m-d'),
                'deleted_snapshots' => $process->deleted_snapshots,
                'finished_at' => $process->finished_at?->toIso8601String(),
                'created_at' => $process->created_at?->toIso8601String(),
            ];
        })->values()->all();

        return response()->json([
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
