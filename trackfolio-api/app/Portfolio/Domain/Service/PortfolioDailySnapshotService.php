<?php

namespace App\Portfolio\Domain\Service;

use App\Portfolio\Domain\Entity\PortfolioDailySnapshot;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;

class PortfolioDailySnapshotService
{
    /**
     * @param array{
     *   balance_eur_min_unit: int,
     *   portfolio_eur_min_unit: int,
     *   cash_eur_min_unit: int,
     *   day_change_eur_min_unit: int|null,
     *   total_gain_loss_eur_min_unit: int|null
     * } $metrics
     */
    public function ensureForDate(int $userId, string $snapshotDate, array $metrics): PortfolioDailySnapshot
    {
        try {
            return PortfolioDailySnapshot::query()->firstOrCreate(
                [
                    'user_id' => $userId,
                    'snapshot_date' => $snapshotDate,
                ],
                $this->metricValues($metrics),
            );
        } catch (UniqueConstraintViolationException) {
            $existing = $this->findByUserAndDate($userId, $snapshotDate);
            if ($existing === null) {
                throw new \RuntimeException("Snapshot missing after unique conflict for user {$userId} on {$snapshotDate}");
            }

            return $existing;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findViewPayload(int $userId, string $snapshotDate): ?array
    {
        $row = $this->findByUserAndDate($userId, $snapshotDate);

        if ($row === null || !is_array($row->view_payload) || $row->view_payload === []) {
            return null;
        }

        return $row->view_payload;
    }

    /**
     * @param array{
     *   balance_eur_min_unit: int,
     *   portfolio_eur_min_unit: int,
     *   cash_eur_min_unit: int,
     *   day_change_eur_min_unit: int|null,
     *   total_gain_loss_eur_min_unit: int|null
     * } $metrics
     * @param array<string, mixed> $payload
     */
    public function storeView(int $userId, string $snapshotDate, array $metrics, array $payload): PortfolioDailySnapshot
    {
        $values = [
            ...$this->metricValues($metrics),
            'view_payload' => $payload,
        ];

        try {
            $row = PortfolioDailySnapshot::query()->firstOrCreate(
                [
                    'user_id' => $userId,
                    'snapshot_date' => $snapshotDate,
                ],
                $values,
            );
        } catch (UniqueConstraintViolationException) {
            $row = $this->findByUserAndDate($userId, $snapshotDate);
            if ($row === null) {
                throw new \RuntimeException("Snapshot missing after unique conflict for user {$userId} on {$snapshotDate}");
            }
        }

        if ($row->wasRecentlyCreated) {
            return $row;
        }

        if (is_array($row->view_payload) && $row->view_payload !== []) {
            return $row;
        }

        $row->fill($values);
        $row->save();

        return $row;
    }

    /**
     * @param array{
     *   balance_eur_min_unit: int,
     *   portfolio_eur_min_unit: int,
     *   cash_eur_min_unit: int,
     *   day_change_eur_min_unit: int|null,
     *   total_gain_loss_eur_min_unit: int|null
     * } $metrics
     * @param array<string, mixed> $payload
     */
    public function replaceView(int $userId, string $snapshotDate, array $metrics, array $payload): PortfolioDailySnapshot
    {
        $values = [
            ...$this->metricValues($metrics),
            'view_payload' => $payload,
        ];

        try {
            return PortfolioDailySnapshot::query()->updateOrCreate(
                [
                    'user_id' => $userId,
                    'snapshot_date' => $snapshotDate,
                ],
                $values,
            );
        } catch (UniqueConstraintViolationException) {
            $existing = $this->findByUserAndDate($userId, $snapshotDate);
            if ($existing === null) {
                throw new \RuntimeException("Snapshot missing after unique conflict for user {$userId} on {$snapshotDate}");
            }
            $existing->fill($values);
            $existing->save();

            return $existing;
        }
    }

    /**
     * @return list<string>
     */
    public function listDatesForUser(int $userId): array
    {
        return PortfolioDailySnapshot::query()
            ->where('user_id', $userId)
            ->orderBy('snapshot_date')
            ->pluck('snapshot_date')
            ->map(static function ($date): string {
                if ($date instanceof Carbon) {
                    return $date->toDateString();
                }

                return (string) $date;
            })
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function listDatesForUserBetween(int $userId, string $fromDate, string $toDate): array
    {
        return PortfolioDailySnapshot::query()
            ->where('user_id', $userId)
            ->whereDate('snapshot_date', '>=', $fromDate)
            ->whereDate('snapshot_date', '<=', $toDate)
            ->orderBy('snapshot_date')
            ->pluck('snapshot_date')
            ->map(static function ($date): string {
                if ($date instanceof Carbon) {
                    return $date->toDateString();
                }

                return (string) $date;
            })
            ->values()
            ->all();
    }

    public function deleteAllForUser(int $userId): int
    {
        return PortfolioDailySnapshot::query()
            ->where('user_id', $userId)
            ->delete();
    }

    public function deleteForUserBetween(int $userId, string $fromDate, string $toDate): int
    {
        return PortfolioDailySnapshot::query()
            ->where('user_id', $userId)
            ->whereDate('snapshot_date', '>=', $fromDate)
            ->whereDate('snapshot_date', '<=', $toDate)
            ->delete();
    }

    /**
     * @return Collection<int, PortfolioDailySnapshot>
     */
    public function listForUserSince(int $userId, Carbon $fromDate): Collection
    {
        return PortfolioDailySnapshot::query()
            ->where('user_id', $userId)
            ->whereDate('snapshot_date', '>=', $fromDate->toDateString())
            ->orderBy('snapshot_date')
            ->get();
    }

    /**
     * @return Collection<int, PortfolioDailySnapshot>
     */
    public function listForUserBetween(int $userId, string $fromDate, string $toDate): Collection
    {
        return PortfolioDailySnapshot::query()
            ->where('user_id', $userId)
            ->whereDate('snapshot_date', '>=', $fromDate)
            ->whereDate('snapshot_date', '<=', $toDate)
            ->orderBy('snapshot_date')
            ->get();
    }

    public function earliestDateForUser(int $userId): ?string
    {
        $date = PortfolioDailySnapshot::query()
            ->where('user_id', $userId)
            ->orderBy('snapshot_date')
            ->value('snapshot_date');

        return $this->dateToString($date);
    }

    public function latestDateForUser(int $userId): ?string
    {
        $date = PortfolioDailySnapshot::query()
            ->where('user_id', $userId)
            ->orderByDesc('snapshot_date')
            ->value('snapshot_date');

        return $this->dateToString($date);
    }

    /**
     * Last snapshot of each calendar year in [fromDate, toDate].
     *
     * @return Collection<int, PortfolioDailySnapshot>
     */
    public function listLastPerYear(int $userId, string $fromDate, string $toDate): Collection
    {
        $dates = PortfolioDailySnapshot::query()
            ->where('user_id', $userId)
            ->whereDate('snapshot_date', '>=', $fromDate)
            ->whereDate('snapshot_date', '<=', $toDate)
            ->selectRaw('MAX(snapshot_date) as snapshot_date')
            ->groupByRaw('YEAR(snapshot_date)')
            ->pluck('snapshot_date')
            ->map(fn ($date) => $this->dateToString($date))
            ->filter()
            ->values()
            ->all();

        if ($dates === []) {
            return new Collection();
        }

        return PortfolioDailySnapshot::query()
            ->where('user_id', $userId)
            ->whereIn('snapshot_date', $dates)
            ->orderBy('snapshot_date')
            ->get();
    }

    /**
     * Last snapshot of each calendar month in [fromDate, toDate].
     *
     * @return Collection<int, PortfolioDailySnapshot>
     */
    public function listLastPerMonth(int $userId, string $fromDate, string $toDate): Collection
    {
        $dates = PortfolioDailySnapshot::query()
            ->where('user_id', $userId)
            ->whereDate('snapshot_date', '>=', $fromDate)
            ->whereDate('snapshot_date', '<=', $toDate)
            ->selectRaw('MAX(snapshot_date) as snapshot_date')
            ->groupByRaw('YEAR(snapshot_date), MONTH(snapshot_date)')
            ->pluck('snapshot_date')
            ->map(fn ($date) => $this->dateToString($date))
            ->filter()
            ->values()
            ->all();

        if ($dates === []) {
            return new Collection();
        }

        return PortfolioDailySnapshot::query()
            ->where('user_id', $userId)
            ->whereIn('snapshot_date', $dates)
            ->orderBy('snapshot_date')
            ->get();
    }

    private function dateToString(mixed $date): ?string
    {
        if ($date === null) {
            return null;
        }
        if ($date instanceof Carbon) {
            return $date->toDateString();
        }

        return substr((string) $date, 0, 10) ?: null;
    }

    private function findByUserAndDate(int $userId, string $snapshotDate): ?PortfolioDailySnapshot
    {
        return PortfolioDailySnapshot::query()
            ->where('user_id', $userId)
            ->where('snapshot_date', $snapshotDate)
            ->first();
    }

    /**
     * @param array{
     *   balance_eur_min_unit: int,
     *   portfolio_eur_min_unit: int,
     *   cash_eur_min_unit: int,
     *   day_change_eur_min_unit: int|null,
     *   total_gain_loss_eur_min_unit: int|null
     * } $metrics
     * @return array{
     *   balance_eur_min_unit: int,
     *   portfolio_eur_min_unit: int,
     *   cash_eur_min_unit: int,
     *   day_change_eur_min_unit: int|null,
     *   total_gain_loss_eur_min_unit: int|null
     * }
     */
    private function metricValues(array $metrics): array
    {
        return [
            'balance_eur_min_unit' => (int) $metrics['balance_eur_min_unit'],
            'portfolio_eur_min_unit' => (int) $metrics['portfolio_eur_min_unit'],
            'cash_eur_min_unit' => (int) $metrics['cash_eur_min_unit'],
            'day_change_eur_min_unit' => $metrics['day_change_eur_min_unit'],
            'total_gain_loss_eur_min_unit' => $metrics['total_gain_loss_eur_min_unit'],
        ];
    }
}
