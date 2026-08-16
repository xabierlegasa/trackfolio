<?php

namespace App\Portfolio\Domain\Service;

use App\DegiroTransaction\Infrastructure\Repository\DegiroTransactionRepository;
use App\Portfolio\Domain\Entity\PortfolioDailySnapshot;
use Carbon\Carbon;

class BuildPortfolioEvolutionViewService
{
    public function __construct(
        private PortfolioDailySnapshotService $portfolioDailySnapshotService,
        private DegiroTransactionRepository $degiroTransactionRepository,
    ) {}

    /**
     * @return array{
     *   granularity: string,
     *   year: int,
     *   years: list<int>,
     *   from: string,
     *   to: string,
     *   data: list<array<string, mixed>>
     * }
     */
    public function build(int $userId, string $granularity, ?int $requestedYear): array
    {
        $granularity = in_array($granularity, ['day', 'month', 'year'], true)
            ? $granularity
            : 'day';

        $today = Carbon::today(config('app.timezone', 'UTC'));
        $currentYear = (int) $today->year;
        $firstYear = $this->degiroTransactionRepository->earliestTransactionYear($userId) ?? $currentYear;

        $earliestSnapshot = $this->portfolioDailySnapshotService->earliestDateForUser($userId);
        if ($earliestSnapshot !== null) {
            $firstYear = min($firstYear, (int) substr($earliestSnapshot, 0, 4));
        }

        $latestSnapshot = $this->portfolioDailySnapshotService->latestDateForUser($userId);
        $rangeTo = $today->toDateString();
        if ($latestSnapshot !== null && $latestSnapshot > $rangeTo) {
            $rangeTo = $latestSnapshot;
        }
        $lastYear = max($currentYear, (int) substr($rangeTo, 0, 4));

        if ($firstYear > $lastYear) {
            $firstYear = $lastYear;
        }
        $years = range($firstYear, $lastYear);

        if ($granularity === 'month') {
            return $this->buildAggregated($userId, 'month', $firstYear, $lastYear, $years, $rangeTo);
        }
        if ($granularity === 'year') {
            return $this->buildAggregated($userId, 'year', $firstYear, $lastYear, $years, $rangeTo);
        }

        return $this->buildDaily($userId, $requestedYear, $firstYear, $lastYear, $years, $rangeTo);
    }

    /**
     * @param  list<int>  $years
     * @return array{
     *   granularity: string,
     *   year: int,
     *   years: list<int>,
     *   from: string,
     *   to: string,
     *   data: list<array<string, mixed>>
     * }
     */
    private function buildDaily(
        int $userId,
        ?int $requestedYear,
        int $firstYear,
        int $lastYear,
        array $years,
        string $rangeTo,
    ): array {
        $year = $requestedYear ?? $lastYear;
        if ($year < $firstYear || $year > $lastYear) {
            $year = $lastYear;
        }

        $from = Carbon::create($year, 1, 1)->toDateString();
        $to = $year === (int) substr($rangeTo, 0, 4)
            ? $rangeTo
            : Carbon::create($year, 12, 31)->toDateString();

        $rows = $this->portfolioDailySnapshotService->listForUserBetween($userId, $from, $to);

        return [
            'granularity' => 'day',
            'year' => $year,
            'years' => $years,
            'from' => $from,
            'to' => $to,
            'data' => $rows->map(fn ($row) => $this->mapRow($row))->values()->all(),
        ];
    }

    /**
     * @param  list<int>  $years
     * @return array{
     *   granularity: string,
     *   year: int,
     *   years: list<int>,
     *   from: string,
     *   to: string,
     *   data: list<array<string, mixed>>
     * }
     */
    private function buildAggregated(
        int $userId,
        string $granularity,
        int $firstYear,
        int $lastYear,
        array $years,
        string $rangeTo,
    ): array {
        $from = Carbon::create($firstYear, 1, 1)->toDateString();
        $to = $rangeTo;

        $rows = $granularity === 'month'
            ? $this->portfolioDailySnapshotService->listLastPerMonth($userId, $from, $to)
            : $this->portfolioDailySnapshotService->listLastPerYear($userId, $from, $to);

        $data = [];
        foreach ($rows as $row) {
            $dateString = $row->snapshot_date instanceof Carbon
                ? $row->snapshot_date->toDateString()
                : substr((string) $row->snapshot_date, 0, 10);
            $period = $granularity === 'month'
                ? substr($dateString, 0, 7)
                : substr($dateString, 0, 4);

            $mapped = $this->mapRow($row);
            $mapped['period'] = $period;
            $data[] = $mapped;
        }

        return [
            'granularity' => $granularity,
            'year' => $lastYear,
            'years' => $years,
            'from' => $from,
            'to' => $to,
            'data' => $data,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapRow(PortfolioDailySnapshot $row): array
    {
        return [
            'snapshot_date' => $row->snapshot_date instanceof Carbon
                ? $row->snapshot_date->toDateString()
                : substr((string) $row->snapshot_date, 0, 10),
            'balance_eur_min_unit' => (int) $row->balance_eur_min_unit,
            'portfolio_eur_min_unit' => (int) $row->portfolio_eur_min_unit,
            'cash_eur_min_unit' => (int) $row->cash_eur_min_unit,
            'day_change_eur_min_unit' => $row->day_change_eur_min_unit !== null
                ? (int) $row->day_change_eur_min_unit
                : null,
            'total_gain_loss_eur_min_unit' => $row->total_gain_loss_eur_min_unit !== null
                ? (int) $row->total_gain_loss_eur_min_unit
                : null,
        ];
    }
}
