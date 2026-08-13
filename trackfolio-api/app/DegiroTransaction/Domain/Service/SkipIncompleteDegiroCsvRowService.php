<?php

namespace App\DegiroTransaction\Domain\Service;

/**
 * Soft-skip Degiro CSV rows that cannot be imported (e.g. Bitcoin without ISIN/quantity).
 */
class SkipIncompleteDegiroCsvRowService
{
    public const REASON_MISSING_ISIN_AND_QUANTITY = 'Missing ISIN and Quantity';

    /**
     * Whether the row should be skipped (empty ISIN and empty Quantity).
     *
     * @param array<int, string|null> $row
     */
    public function shouldSkip(array $row): bool
    {
        $isin = $this->cleanValue($row[3] ?? null);
        $quantity = $this->cleanValue($row[6] ?? null);

        return ($isin === null || $isin === '')
            && ($quantity === null || $quantity === '');
    }

    /**
     * @param array<int, string|null> $row
     * @return array{line: int, reason: string, date: string|null, time: string|null, product: string|null, local_value: string|null}|null
     */
    public function skippedEntry(array $row, int $lineNumber): ?array
    {
        if (! $this->shouldSkip($row)) {
            return null;
        }

        $localValue = $this->cleanValue($row[9] ?? null);
        $localValueCurrency = $this->cleanValue($row[10] ?? null);
        $localValueDisplay = null;
        if ($localValue !== null) {
            $localValueDisplay = $localValueCurrency !== null
                ? "{$localValue} {$localValueCurrency}"
                : $localValue;
        }

        return [
            'line' => $lineNumber,
            'reason' => self::REASON_MISSING_ISIN_AND_QUANTITY,
            'date' => $this->cleanValue($row[0] ?? null),
            'time' => $this->cleanValue($row[1] ?? null),
            'product' => $this->cleanValue($row[2] ?? null),
            'local_value' => $localValueDisplay,
        ];
    }

    private function cleanValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $cleaned = trim(trim((string) $value, '"'));

        return $cleaned === '' ? null : $cleaned;
    }
}
