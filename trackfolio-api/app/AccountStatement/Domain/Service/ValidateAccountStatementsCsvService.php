<?php

namespace App\AccountStatement\Domain\Service;

use Illuminate\Http\UploadedFile;

class ValidateAccountStatementsCsvService
{
    private const VALID_CURRENCIES = ['USD', 'EUR', 'GBP', 'JPY', 'CHF', 'CAD', 'AUD', 'NZD', 'HKD'];

    public function __construct(
        private ReadAccountStatementsCsvService $csvReader,
    ) {}

    /**
     * @return array{valid: bool, errors: list<string>}
     */
    public function validate(UploadedFile $file): array
    {
        $read = $this->csvReader->read($file);
        if ($read['errors'] !== []) {
            return [
                'valid' => false,
                'errors' => $read['errors'],
            ];
        }

        $errors = [];
        foreach ($read['rows'] as $entry) {
            $errors = array_merge(
                $errors,
                $this->validateRow($entry['values'], $entry['line']),
            );
        }

        if ($read['rows'] === [] && $errors === []) {
            $errors[] = 'CSV has no data rows';
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
        ];
    }

    /**
     * @param  list<string|null>  $row
     * @return list<string>
     */
    private function validateRow(array $row, int $lineNumber): array
    {
        $errors = [];
        $clean = $this->cleaner();

        if (count($row) < 11) {
            $errors[] = "Line {$lineNumber}: expected at least 11 columns, got " . count($row);

            return $errors;
        }

        $date = $clean($row[0] ?? null);
        if ($date === null) {
            $errors[] = "Line {$lineNumber}, column 1 (Date): Date is required (DD-MM-YYYY).";
        } elseif (!$this->isValidDate($date)) {
            $errors[] = "Line {$lineNumber}, column 1 (Date): Invalid date format. Expected DD-MM-YYYY. Value: '{$date}'.";
        }

        $time = $clean($row[1] ?? null);
        if ($time === null) {
            $errors[] = "Line {$lineNumber}, column 2 (Time): Time is required (HH:MM).";
        } elseif (!$this->isValidTime($time)) {
            $errors[] = "Line {$lineNumber}, column 2 (Time): Invalid time format. Expected HH:MM. Value: '{$time}'.";
        }

        $valueDate = $clean($row[2] ?? null);
        if ($valueDate !== null && !$this->isValidDate($valueDate)) {
            $errors[] = "Line {$lineNumber}, column 3 (Value date): Invalid date format. Expected DD-MM-YYYY. Value: '{$valueDate}'.";
        }

        $changeCurrency = $clean($row[7] ?? null);
        $changeAmount = $clean($row[8] ?? null);
        if ($changeCurrency !== null && !$this->isValidCurrencyCode($changeCurrency)) {
            $errors[] = "Line {$lineNumber}, column 8 (Change currency): Invalid currency code. Value: '{$changeCurrency}'.";
        }
        if ($changeAmount !== null && !$this->isValidCurrencyValue($changeAmount)) {
            $errors[] = "Line {$lineNumber}, column 9 (Change amount): Invalid amount. Expected European number (e.g. -2,50). Value: '{$changeAmount}'.";
        }
        if ($changeCurrency !== null && $changeAmount === null) {
            $errors[] = "Line {$lineNumber}, column 9 (Change amount): Amount is required when Change currency is set.";
        }
        if ($changeAmount !== null && $changeCurrency === null) {
            $errors[] = "Line {$lineNumber}, column 8 (Change currency): Currency is required when Change amount is set.";
        }

        $balanceCurrency = $clean($row[9] ?? null);
        $balanceAmount = $clean($row[10] ?? null);
        if ($balanceCurrency === null) {
            $errors[] = "Line {$lineNumber}, column 10 (Balance currency): Balance currency is required.";
        } elseif (!$this->isValidCurrencyCode($balanceCurrency)) {
            $errors[] = "Line {$lineNumber}, column 10 (Balance currency): Invalid currency code. Value: '{$balanceCurrency}'.";
        }
        if ($balanceAmount === null) {
            $errors[] = "Line {$lineNumber}, column 11 (Balance amount): Balance amount is required.";
        } elseif (!$this->isValidCurrencyValue($balanceAmount)) {
            $errors[] = "Line {$lineNumber}, column 11 (Balance amount): Invalid amount. Expected European number (e.g. -3427,33). Value: '{$balanceAmount}'.";
        }

        return $errors;
    }

    /**
     * @return callable(string|null): ?string
     */
    private function cleaner(): callable
    {
        return static function ($value): ?string {
            if ($value === null || $value === '') {
                return null;
            }
            $trimmed = trim(trim((string) $value, '"'));

            return $trimmed === '' ? null : $trimmed;
        };
    }

    private function isValidDate(string $date): bool
    {
        if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $date) !== 1) {
            return false;
        }
        [$day, $month, $year] = array_map('intval', explode('-', $date));

        return checkdate($month, $day, $year);
    }

    private function isValidTime(string $time): bool
    {
        if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time) !== 1) {
            return false;
        }
        $parts = explode(':', $time);
        $hour = (int) $parts[0];
        $minute = (int) $parts[1];
        $second = isset($parts[2]) ? (int) $parts[2] : 0;

        return $hour >= 0 && $hour <= 23
            && $minute >= 0 && $minute <= 59
            && $second >= 0 && $second <= 59;
    }

    private function isValidCurrencyValue(string $value): bool
    {
        return preg_match('/^-?\d+(,\d+)?$/', $value) === 1;
    }

    private function isValidCurrencyCode(string $currency): bool
    {
        return in_array(strtoupper($currency), self::VALID_CURRENCIES, true);
    }
}
