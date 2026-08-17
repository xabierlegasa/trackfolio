<?php

namespace App\Portfolio\Application\UseCase;

use App\GlobalConfig\Domain\Service\GetGlobalConfigService;
use App\Isin\Domain\Service\ResolveLastUsMarketOpenDateService;
use App\Portfolio\Application\Job\RecalculateEvolutionDayJob;
use App\Portfolio\Domain\Service\BuildPortfolioStatsAsOfService;
use App\Portfolio\Domain\Service\PortfolioDailySnapshotService;
use App\Portfolio\Domain\Service\SnapshotCalculationProcessLogService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class RecalculateEvolutionDayUseCase
{
    private const TIMEZONE = 'America/New_York';

    public function __construct(
        private GetGlobalConfigService $getGlobalConfigService,
        private ResolveLastUsMarketOpenDateService $resolveLastUsMarketOpenDateService,
        private BuildPortfolioStatsAsOfService $buildPortfolioStatsAsOfService,
        private PortfolioDailySnapshotService $portfolioDailySnapshotService,
        private SnapshotCalculationProcessLogService $snapshotCalculationProcessLogService,
    ) {}

    public function execute(int $userId, string $date, int $processId, ?string $untilDate = null): void
    {
        $this->snapshotCalculationProcessLogService->log(
            $processId,
            "Start Job para fecha {$date}",
            dateProcessed: $date,
        );
        Log::info("evolution.recalculate day started user_id={$userId} date={$date} process_id={$processId} until=" . ($untilDate ?? 'null'));

        try {
            if (!$this->getGlobalConfigService->isRecalculateEvolutionFeatureEnabled()) {
                $this->snapshotCalculationProcessLogService->log(
                    $processId,
                    "Proceso detenido: feature flag de recalculate evolution desactivada (fecha {$date})",
                    dateProcessed: $date,
                );
                $this->snapshotCalculationProcessLogService->markStopped($processId);
                Log::info("evolution.recalculate stopped user_id={$userId} date={$date} reason=feature_flag_off");

                return;
            }

            $yesterday = Carbon::now(self::TIMEZONE)->subDay()->toDateString();
            $rangeUntil = $untilDate !== null && $untilDate < $yesterday ? $untilDate : $yesterday;

            if ($date > $rangeUntil) {
                $this->snapshotCalculationProcessLogService->log(
                    $processId,
                    "Proceso detenido: fecha {$date} es posterior al fin del rango {$rangeUntil}",
                    dateProcessed: $date,
                );
                $this->snapshotCalculationProcessLogService->markCompleted($processId);
                Log::info("evolution.recalculate stopped user_id={$userId} date={$date} until={$rangeUntil} reason=after_until");

                return;
            }

            $status = $this->resolveLastUsMarketOpenDateService->marketStatusOn($date);
            $isOpen = (bool) ($status['open'] ?? false);
            $reason = (string) ($status['reason'] ?? '');
            $holiday = (string) ($status['holiday'] ?? '');
            $openLabel = $isOpen ? 'open' : 'closed';

            $this->snapshotCalculationProcessLogService->log(
                $processId,
                "Estado del mercado US en {$date}: {$openLabel}"
                    . ($reason !== '' ? " (reason={$reason})" : '')
                    . ($holiday !== '' ? " (holiday={$holiday})" : ''),
                dateProcessed: $date,
            );
            Log::info("evolution.recalculate market status user_id={$userId} date={$date} market={$openLabel} reason={$reason} holiday={$holiday}");

            if ($isOpen) {
                $this->persistOpenDay($userId, $date, $processId);
            } else {
                $this->snapshotCalculationProcessLogService->log(
                    $processId,
                    "Mercado cerrado en {$date}; no se calcula snapshot",
                    dateProcessed: $date,
                );
                Log::info("evolution.recalculate skipped market closed user_id={$userId} date={$date} reason={$reason} holiday={$holiday}");
            }

            $this->dispatchNextDay($userId, $date, $rangeUntil, $processId, $untilDate);
        } catch (Throwable $exception) {
            $message = $exception->getMessage();
            $exceptionClass = $exception::class;
            $this->snapshotCalculationProcessLogService->log(
                $processId,
                "Error en fecha {$date}: {$exceptionClass} — {$message}",
                dateProcessed: $date,
            );
            $this->snapshotCalculationProcessLogService->markFailed($processId);
            Log::error("evolution.recalculate failed user_id={$userId} date={$date} exception={$exceptionClass} message={$message}");

            throw $exception;
        }
    }

    private function persistOpenDay(int $userId, string $date, int $processId): void
    {
        $payload = $this->buildPortfolioStatsAsOfService->build($userId, $date, $processId);
        $metrics = is_array($payload['metrics'] ?? null) ? $payload['metrics'] : [];
        unset($payload['metrics']);

        $this->portfolioDailySnapshotService->replaceView($userId, $date, $metrics, $payload);
        Cache::forever("portfolio_as_of_view:{$userId}:{$date}", $payload);

        $balance = $this->formatMinUnitAsEuro($metrics['balance_eur_min_unit'] ?? null);
        $portfolio = $this->formatMinUnitAsEuro($metrics['portfolio_eur_min_unit'] ?? null);
        $cash = $this->formatMinUnitAsEuro($metrics['cash_eur_min_unit'] ?? null);
        $dayChange = $metrics['day_change_eur_min_unit'] ?? 'null';
        $totalPl = $metrics['total_gain_loss_eur_min_unit'] ?? 'null';

        $this->snapshotCalculationProcessLogService->log(
            $processId,
            "Snapshot guardado para {$date}: balance={$balance}, portfolio={$portfolio}, cash={$cash}, day_change={$dayChange}, total_pl={$totalPl}",
            dateProcessed: $date,
        );
        Log::info("evolution.recalculate snapshot saved user_id={$userId} date={$date} balance_eur={$balance} portfolio_eur={$portfolio} cash_eur={$cash} day_change_eur_min_unit={$dayChange} total_gain_loss_eur_min_unit={$totalPl}");
    }

    private function formatMinUnitAsEuro(mixed $minUnit): string
    {
        if ($minUnit === null || $minUnit === '') {
            return 'null';
        }

        return number_format(((int) $minUnit) / 100, 2, '.', '');
    }

    private function dispatchNextDay(
        int $userId,
        string $date,
        string $rangeUntil,
        int $processId,
        ?string $untilDate,
    ): void {
        if (!$this->getGlobalConfigService->isRecalculateEvolutionFeatureEnabled()) {
            $this->snapshotCalculationProcessLogService->log(
                $processId,
                "Proceso detenido antes de re-dispatch: feature flag desactivada (fecha {$date})",
                dateProcessed: $date,
            );
            $this->snapshotCalculationProcessLogService->markStopped($processId);
            Log::info("evolution.recalculate stopped user_id={$userId} date={$date} reason=feature_flag_off_before_redispatch");

            return;
        }

        $next = Carbon::parse($date, self::TIMEZONE)->addDay()->toDateString();
        if ($next > $rangeUntil) {
            $this->snapshotCalculationProcessLogService->log(
                $processId,
                "Proceso completado: alcanzado el final del rango (último día procesado {$date}, until={$rangeUntil})",
                dateProcessed: $date,
            );
            $this->snapshotCalculationProcessLogService->markCompleted($processId);
            Log::info("evolution.recalculate stopped user_id={$userId} date={$date} next={$next} until={$rangeUntil} reason=reached_until");

            return;
        }

        $this->snapshotCalculationProcessLogService->log(
            $processId,
            "Llamar Job que calcula snapshot de {$next}",
            dateProcessed: $next,
        );
        Log::info("evolution.recalculate re-dispatch user_id={$userId} from_date={$date} next_date={$next} until={$rangeUntil}");

        RecalculateEvolutionDayJob::dispatch($userId, $next, $processId, $untilDate);
    }
}
