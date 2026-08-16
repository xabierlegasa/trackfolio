<?php

namespace App\Admin\Infrastructure\Repository;

use App\Portfolio\Domain\Entity\SnapshotCalculationProcess;
use App\Portfolio\Domain\Entity\SnapshotCalculationProcessLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AdminSnapshotCalculationProcessRepository
{
    /**
     * @return LengthAwarePaginator<int, SnapshotCalculationProcess>
     */
    public function paginateProcesses(int $perPage = 20): LengthAwarePaginator
    {
        return SnapshotCalculationProcess::query()
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findProcess(int $processId): ?SnapshotCalculationProcess
    {
        return SnapshotCalculationProcess::query()->find($processId);
    }

    /**
     * @return LengthAwarePaginator<int, SnapshotCalculationProcessLog>
     */
    public function paginateLogs(int $processId, int $perPage = 20): LengthAwarePaginator
    {
        return SnapshotCalculationProcessLog::query()
            ->where('snapshot_calculation_process_id', $processId)
            ->orderBy('id')
            ->paginate($perPage);
    }
}
