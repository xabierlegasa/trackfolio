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
     * @return array{process_id: int, started_from: string|null, until: string|null, deleted: int}
     */
    public function execute(int $userId, ?string $fromDate = null, ?string $untilDate = null): array
    {
        $process = $this->snapshotCalculationProcessLogService->start($userId);
        $processId = $process->id;

        $yesterday = Carbon::now(self::TIMEZONE)->subDay()->toDateString();
        $rangeUntil = $untilDate !== null && $untilDate < $yesterday ? $untilDate : $yesterday;

        $earliestTransactionDate = $this->degiroTransactionRepository->earliestTransactionDate($userId);

        if ($fromDate !== null) {
            $startDate = $earliestTransactionDate !== null && $earliestTransactionDate > $fromDate
                ? $earliestTransactionDate
                : $fromDate;
            $clearFrom = $fromDate;
            $clearUntil = $rangeUntil;
            $scoped = true;
        } else {
            $startDate = $earliestTransactionDate;
            $clearFrom = null;
            $clearUntil = null;
            $scoped = false;
        }

        Log::info(
            'evolution.recalculate start user_id=' . $userId
            . ' process_id=' . $processId
            . ' started_from=' . ($startDate ?? 'null')
            . ' until=' . $rangeUntil
            . ' scoped=' . ($scoped ? '1' : '0')
        );

        if ($scoped) {
            $this->snapshotCalculationProcessLogService->log(
                $processId,
                "Recálculo acotado: borrar cache y daily snapshots desde {$clearFrom} hasta {$clearUntil}",
            );
            $cacheKeysForgotten = $this->forgetAsOfViewCacheBetween($userId, $clearFrom, $clearUntil);
            $deleted = $this->portfolioDailySnapshotService->deleteForUserBetween($userId, $clearFrom, $clearUntil);
        } else {
            $dates = $this->portfolioDailySnapshotService->listDatesForUser($userId);
            $cacheKeysForgotten = 0;
            foreach ($dates as $date) {
                Cache::forget("portfolio_as_of_view:{$userId}:{$date}");
                $cacheKeysForgotten++;
            }
            $deleted = $this->portfolioDailySnapshotService->deleteAllForUser($userId);
        }

        $this->snapshotCalculationProcessLogService->log(
            $processId,
            "Snapshots borrados: {$deleted}. Cache keys olvidadas: {$cacheKeysForgotten}",
        );

        Log::info("evolution.recalculate snapshots cleared user_id={$userId} deleted={$deleted} cache_keys_forgotten={$cacheKeysForgotten}");

        if ($earliestTransactionDate === null) {
            $this->snapshotCalculationProcessLogService->log(
                $processId,
                'Proceso detenido: el usuario no tiene transacciones Degiro',
            );
            $this->snapshotCalculationProcessLogService->markStopped($processId, null, $deleted);
            Log::info("evolution.recalculate stopped user_id={$userId} reason=no_degiro_transactions");

            return [
                'process_id' => $processId,
                'started_from' => null,
                'until' => $rangeUntil,
                'deleted' => $deleted,
            ];
        }

        $this->snapshotCalculationProcessLogService->log(
            $processId,
            "Fecha inicial de recálculo: {$startDate} (hasta {$rangeUntil})",
            dateProcessed: $startDate,
        );
        $this->snapshotCalculationProcessLogService->setStartedFromAndDeleted($processId, $startDate, $deleted);

        if ($startDate > $rangeUntil) {
            $this->snapshotCalculationProcessLogService->log(
                $processId,
                "Proceso detenido: fecha inicial {$startDate} es posterior al fin del rango {$rangeUntil}",
                dateProcessed: $startDate,
            );
            $this->snapshotCalculationProcessLogService->markStopped($processId, $startDate, $deleted);
            Log::info("evolution.recalculate stopped user_id={$userId} reason=start_after_until started_from={$startDate} until={$rangeUntil}");

            return [
                'process_id' => $processId,
                'started_from' => $startDate,
                'until' => $rangeUntil,
                'deleted' => $deleted,
            ];
        }

        $this->snapshotCalculationProcessLogService->log(
            $processId,
            "Llamar Job que calcula snapshot de {$startDate}",
            dateProcessed: $startDate,
        );

        RecalculateEvolutionDayJob::dispatch($userId, $startDate, $processId, $rangeUntil);

        $this->snapshotCalculationProcessLogService->log(
            $processId,
            "Job despachado para fecha {$startDate}",
            dateProcessed: $startDate,
        );

        Log::info("evolution.recalculate first job dispatched user_id={$userId} date={$startDate} until={$rangeUntil} process_id={$processId}");

        return [
            'process_id' => $processId,
            'started_from' => $startDate,
            'until' => $rangeUntil,
            'deleted' => $deleted,
        ];
    }

    /**
     * Forget portfolio_as_of_view cache for every calendar day in [fromDate, toDate].
     * Iterates the full range (not only dates that already have a DB snapshot).
     */
    private function forgetAsOfViewCacheBetween(int $userId, string $fromDate, string $toDate): int
    {
        $forgotten = 0;
        $cursor = Carbon::parse($fromDate, self::TIMEZONE)->startOfDay();
        $end = Carbon::parse($toDate, self::TIMEZONE)->startOfDay();

        while ($cursor->lte($end)) {
            Cache::forget("portfolio_as_of_view:{$userId}:{$cursor->toDateString()}");
            $forgotten++;
            $cursor->addDay();
        }

        return $forgotten;
    }
}
