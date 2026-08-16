<?php

namespace App\Portfolio\Infrastructure\Repository;

use App\Portfolio\Domain\Entity\SnapshotCalculationProcess;
use App\Portfolio\Domain\Entity\SnapshotCalculationProcessLog;
use Carbon\Carbon;

class SnapshotCalculationProcessRepository
{
    public function createRunning(int $userId): SnapshotCalculationProcess
    {
        return SnapshotCalculationProcess::query()->create([
            'user_id' => $userId,
            'status' => SnapshotCalculationProcess::STATUS_RUNNING,
        ]);
    }

    public function updateProcess(
        int $processId,
        ?string $status = null,
        ?string $startedFrom = null,
        ?int $deletedSnapshots = null,
        bool $markFinished = false,
    ): void {
        $attributes = [];

        if ($status !== null) {
            $attributes['status'] = $status;
        }
        if ($startedFrom !== null) {
            $attributes['started_from'] = $startedFrom;
        }
        if ($deletedSnapshots !== null) {
            $attributes['deleted_snapshots'] = $deletedSnapshots;
        }
        if ($markFinished) {
            $attributes['finished_at'] = Carbon::now();
        }

        if ($attributes === []) {
            return;
        }

        SnapshotCalculationProcess::query()
            ->where('id', $processId)
            ->update($attributes);
    }

    public function addLog(
        int $processId,
        string $description,
        ?string $dateProcessed = null,
        ?string $isin = null,
        ?string $symbol = null,
        ?int $providerRequestId = null,
    ): SnapshotCalculationProcessLog {
        return SnapshotCalculationProcessLog::query()->create([
            'snapshot_calculation_process_id' => $processId,
            'description' => $description,
            'date_processed' => $dateProcessed,
            'isin' => $isin !== null && $isin !== '' ? strtoupper($isin) : null,
            'symbol' => $symbol !== null && $symbol !== '' ? strtoupper($symbol) : null,
            'provider_request_id' => $providerRequestId,
        ]);
    }
}
