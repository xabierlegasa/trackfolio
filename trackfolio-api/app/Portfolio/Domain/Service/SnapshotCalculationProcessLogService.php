<?php

namespace App\Portfolio\Domain\Service;

use App\Portfolio\Domain\Entity\SnapshotCalculationProcess;
use App\Portfolio\Infrastructure\Repository\SnapshotCalculationProcessRepository;

class SnapshotCalculationProcessLogService
{
    public function __construct(
        private SnapshotCalculationProcessRepository $repository,
    ) {}

    public function start(int $userId): SnapshotCalculationProcess
    {
        $process = $this->repository->createRunning($userId);
        $this->log($process->id, 'Inicio del proceso');

        return $process;
    }

    public function log(
        int $processId,
        string $description,
        ?string $dateProcessed = null,
        ?string $isin = null,
        ?string $symbol = null,
        ?int $providerRequestId = null,
    ): void {
        $this->repository->addLog(
            processId: $processId,
            description: $description,
            dateProcessed: $dateProcessed,
            isin: $isin,
            symbol: $symbol,
            providerRequestId: $providerRequestId,
        );
    }

    public function markStopped(int $processId, ?string $startedFrom = null, ?int $deletedSnapshots = null): void
    {
        $this->repository->updateProcess(
            processId: $processId,
            status: SnapshotCalculationProcess::STATUS_STOPPED,
            startedFrom: $startedFrom,
            deletedSnapshots: $deletedSnapshots,
            markFinished: true,
        );
    }

    public function markCompleted(int $processId): void
    {
        $this->repository->updateProcess(
            processId: $processId,
            status: SnapshotCalculationProcess::STATUS_COMPLETED,
            markFinished: true,
        );
    }

    public function markFailed(int $processId): void
    {
        $this->repository->updateProcess(
            processId: $processId,
            status: SnapshotCalculationProcess::STATUS_FAILED,
            markFinished: true,
        );
    }

    public function setStartedFromAndDeleted(int $processId, string $startedFrom, int $deletedSnapshots): void
    {
        $this->repository->updateProcess(
            processId: $processId,
            startedFrom: $startedFrom,
            deletedSnapshots: $deletedSnapshots,
        );
    }
}
