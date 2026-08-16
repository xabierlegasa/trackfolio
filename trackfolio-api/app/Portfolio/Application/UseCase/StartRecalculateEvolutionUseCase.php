<?php

namespace App\Portfolio\Application\UseCase;

use App\DegiroTransaction\Infrastructure\Repository\DegiroTransactionRepository;
use App\Portfolio\Application\Job\RecalculateEvolutionDayJob;
use App\Portfolio\Domain\Service\PortfolioDailySnapshotService;
use App\Portfolio\Domain\Service\SnapshotCalculationProcessLogService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class StartRecalculateEvolutionUseCase
{
    private const TIMEZONE = 'America/New_York';

    public function __construct(
        private PortfolioDailySnapshotService $portfolioDailySnapshotService,
        private DegiroTransactionRepository $degiroTransactionRepository,
        private SnapshotCalculationProcessLogService $snapshotCalculationProcessLogService,
    ) {}

    /**
     * @return array{process_id: int, started_from: string|null, deleted: int}
     */
    public function execute(int $userId): array
    {
        $process = $this->snapshotCalculationProcessLogService->start($userId);
        $processId = $process->id;

        $startDate = $this->degiroTransactionRepository->earliestTransactionDate($userId);
        $yesterday = Carbon::now(self::TIMEZONE)->subDay()->toDateString();

        Log::info('evolution.recalculate start user_id=' . $userId . ' process_id=' . $processId . ' started_from=' . ($startDate ?? 'null') . ' until=' . $yesterday);

        $dates = $this->portfolioDailySnapshotService->listDatesForUser($userId);
        foreach ($dates as $date) {
            Cache::forget("portfolio_as_of_view:{$userId}:{$date}");
        }

        $deleted = $this->portfolioDailySnapshotService->deleteAllForUser($userId);

        $this->snapshotCalculationProcessLogService->log(
            $processId,
            "Snapshots borrados: {$deleted}. Cache keys olvidadas: " . count($dates),
        );

        Log::info("evolution.recalculate snapshots cleared user_id={$userId} deleted={$deleted} cache_keys_forgotten=" . count($dates));

        if ($startDate === null) {
            $this->snapshotCalculationProcessLogService->log(
                $processId,
                'Proceso detenido: el usuario no tiene transacciones Degiro',
            );
            $this->snapshotCalculationProcessLogService->markStopped($processId, null, $deleted);
            Log::info("evolution.recalculate stopped user_id={$userId} reason=no_degiro_transactions");

            return [
                'process_id' => $processId,
                'started_from' => null,
                'deleted' => $deleted,
            ];
        }

        $this->snapshotCalculationProcessLogService->log(
            $processId,
            "Fecha inicial de la actividad del usuario es {$startDate}",
            dateProcessed: $startDate,
        );
        $this->snapshotCalculationProcessLogService->setStartedFromAndDeleted($processId, $startDate, $deleted);

        if ($startDate > $yesterday) {
            $this->snapshotCalculationProcessLogService->log(
                $processId,
                "Proceso detenido: fecha inicial {$startDate} es posterior a ayer {$yesterday}",
                dateProcessed: $startDate,
            );
            $this->snapshotCalculationProcessLogService->markStopped($processId, $startDate, $deleted);
            Log::info("evolution.recalculate stopped user_id={$userId} reason=start_after_yesterday started_from={$startDate}");

            return [
                'process_id' => $processId,
                'started_from' => $startDate,
                'deleted' => $deleted,
            ];
        }

        $this->snapshotCalculationProcessLogService->log(
            $processId,
            "Llamar Job que calcula snapshot de {$startDate}",
            dateProcessed: $startDate,
        );

        RecalculateEvolutionDayJob::dispatch($userId, $startDate, $processId);

        $this->snapshotCalculationProcessLogService->log(
            $processId,
            "Job despachado para fecha {$startDate}",
            dateProcessed: $startDate,
        );

        Log::info("evolution.recalculate first job dispatched user_id={$userId} date={$startDate} process_id={$processId}");

        return [
            'process_id' => $processId,
            'started_from' => $startDate,
            'deleted' => $deleted,
        ];
    }
}
