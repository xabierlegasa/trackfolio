<?php

namespace App\DegiroTransaction\Domain\Service;

use Illuminate\Http\UploadedFile;

class ValidateDegiroTransactionsCsvService
{
    /**
     * Expected number of columns in the CSV file.
     */
    private const EXPECTED_COLUMN_COUNT = 19;

    /**
     * Expected CSV header structure.
     */
    private const EXPECTED_HEADER = [
        'Date', 'Time', 'Product', 'ISIN', 'Reference', 'Venue', 'Quantity', 'Price', '', 
        'Local value', '', 'Value', '', 'Exchange rate', 'Transaction and/or third', 
        '', 'Total', '', 'Order ID'
    ];

    /**
     * Valid currency codes (ISO 4217).
     */
    private const VALID_CURRENCIES = ['USD', 'EUR', 'GBP', 'JPY', 'CHF', 'CAD', 'AUD', 'NZD', 'HKD'];

    /**
     * Validate the CSV file structure and data format.
     *
     * @param UploadedFile $file
     * @return array{valid: bool, errors: array<int, string>}
     */
    public function validate(UploadedFile $file): array
    {
        $errors = [];

        $handle = fopen($file->getRealPath(), 'r');
        
        if ($handle === false) {
            return [
                'valid' => false,
                'errors' => ['Unable to open CSV file']
            ];
        }

        // Read and validate header
        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            return [
                'valid' => false,
                'errors' => ['CSV file is empty or invalid']
            ];
        }

        $headerErrors = $this->validateHeader($header);
        if (!empty($headerErrors)) {
            $errors = array_merge($errors, $headerErrors);
        }

        // Validate each data row
        $lineNumber = 1; // Start at 1 because we already read the header
        while (($row = fgetcsv($handle)) !== false) {
            $lineNumber++;
            
            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }

            $rowErrors = $this->validateRow($row, $lineNumber);
            $errors = array_merge($errors, $rowErrors);
        }

        fclose($handle);

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate the CSV header row.
     *
     * @param array $header
     * @return array<string>
     */
    private function validateHeader(array $header): array
    {
        $errors = [];

        $columnCount = count($header);
        if ($columnCount !== self::EXPECTED_COLUMN_COUNT) {
            $errors[] = "Header row has {$columnCount} columns, expected " . self::EXPECTED_COLUMN_COUNT . " columns";
        }

        return $errors;
    }

    /**
     * Validate a single data row.
     *
     * @param array $row
     * @param int $lineNumber
     * @return array<string>
     */
    private function validateRow(array $row, int $lineNumber): array
    {
        $errors = [];

        // Check column count
        $columnCount = count($row);
        if ($columnCount !== self::EXPECTED_COLUMN_COUNT) {
            $errors[] = "Line {$lineNumber}: Expected " . self::EXPECTED_COLUMN_COUNT . " columns, found {$columnCount}";
            // If column count is wrong, skip further validation for this row
            return $errors;
        }

        // Clean values (remove quotes and trim)
        $cleanValue = function($value) {
            if ($value === null || $value === '') {
                return null;
            }
            return trim(trim($value, '"'));
        };

        // Validate Date (column 0): DD-MM-YYYY format
        $date = $cleanValue($row[0] ?? null);
        if ($date === null || $date === '') {
            $errors[] = "Line {$lineNumber}, column 1 (Date): Date is required.";
        } elseif (!$this->isValidDate($date)) {
            $dateDisplay = $date !== null ? "'{$date}'" : 'empty';
            $errors[] = "Line {$lineNumber}, column 1 (Date): Invalid date format. Expected DD-MM-YYYY format. Value: {$dateDisplay}.";
        }

        // Validate Time (column 1): HH:MM format
        $time = $cleanValue($row[1] ?? null);
        if ($time === null || $time === '') {
            $errors[] = "Line {$lineNumber}, column 2 (Time): Time is required.";
        } elseif (!$this->isValidTime($time)) {
            $timeDisplay = $time !== null ? "'{$time}'" : 'empty';
            $errors[] = "Line {$lineNumber}, column 2 (Time): Invalid time format. Expected HH:MM format. Value: {$timeDisplay}.";
        }

        // Validate Product (column 2): required string
        $product = $cleanValue($row[2] ?? null);
        if ($product === null || $product === '') {
            $errors[] = "Line {$lineNumber}, column 3 (Product): Product name is required.";
        }

        // Validate ISIN (column 3): required, minimum 6 characters
        // Note: Not validating ISIN format as Degiro now includes Bitcoin and other non-standard identifiers
        $isin = $cleanValue($row[3] ?? null);
        if ($isin === null || $isin === '') {
            $errors[] = "Line {$lineNumber}, column 4 (ISIN): ISIN is required.";
        } elseif (strlen($isin) < 6) {
            $errors[] = "Line {$lineNumber}, column 4 (ISIN): ISIN must be at least 6 characters long. Value: '{$isin}' (length: " . strlen($isin) . ").";
        }

        // Validate Reference (column 4): required string
        $reference = $cleanValue($row[4] ?? null);
        if ($reference === null || $reference === '') {
            $errors[] = "Line {$lineNumber}, column 5 (Reference): Reference is required.";
        }

        // Venue (column 5): optional, no validation needed

        // Validate Quantity (column 6): required decimal number (can be negative, zero, or positive; supports decimals like 0,004727)
        $quantity = $cleanValue($row[6] ?? null);
        if ($quantity === null || $quantity === '') {
            $errors[] = "Line {$lineNumber}, column 7 (Quantity): Quantity is required.";
        } elseif (!preg_match('/^-?\d+([,.]\d+)?$/', $quantity)) {
            $errors[] = "Line {$lineNumber}, column 7 (Quantity): Quantity '{$quantity}' must be a valid number.";
        }
        // Note: Quantity can be 0, so no zero check needed

        // Validate Price (column 7): required, valid currency format
        $price = $cleanValue($row[7] ?? null);
        if ($price === null || $price === '') {
            $errors[] = "Line {$lineNumber}, column 8 (Price): Price is required.";
        } elseif (!$this->isValidCurrencyValue($price)) {
            $errors[] = "Line {$lineNumber}, column 8 (Price): Invalid price format. Expected numeric value with comma as decimal separator. Value: '{$price}'.";
        }

        // Validate Price Currency (column 8): required, valid currency code
        $priceCurrency = $cleanValue($row[8] ?? null);
        if ($priceCurrency === null || $priceCurrency === '') {
            $errors[] = "Line {$lineNumber}, column 9 (Price Currency): Price currency is required.";
        } elseif (!$this->isValidCurrencyCode($priceCurrency)) {
            $errors[] = "Line {$lineNumber}, column 9 (Price Currency): Invalid currency code. Value: '{$priceCurrency}'.";
        }

        // Validate Local value (column 9): required, valid currency format
        $localValue = $cleanValue($row[9] ?? null);
        if ($localValue === null || $localValue === '') {
            $errors[] = "Line {$lineNumber}, column 10 (Local value): Local value is required.";
        } elseif (!$this->isValidCurrencyValue($localValue)) {
            $errors[] = "Line {$lineNumber}, column 10 (Local value): Invalid format. Expected numeric value with comma as decimal separator. Value: '{$localValue}'.";
        }

        // Validate Local value Currency (column 10): required, valid currency code
        $localValueCurrency = $cleanValue($row[10] ?? null);
        if ($localValueCurrency === null || $localValueCurrency === '') {
            $errors[] = "Line {$lineNumber}, column 11 (Local value Currency): Local value currency is required.";
        } elseif (!$this->isValidCurrencyCode($localValueCurrency)) {
            $errors[] = "Line {$lineNumber}, column 11 (Local value Currency): Invalid currency code. Value: '{$localValueCurrency}'.";
        }

        // Validate Value (column 11): required, valid currency format
        $value = $cleanValue($row[11] ?? null);
        if ($value === null || $value === '') {
            $errors[] = "Line {$lineNumber}, column 12 (Value): Value is required.";
        } elseif (!$this->isValidCurrencyValue($value)) {
            $errors[] = "Line {$lineNumber}, column 12 (Value): Invalid format. Expected numeric value with comma as decimal separator. Value: '{$value}'.";
        }

        // Validate Value Currency (column 12): required, valid currency code
        $valueCurrency = $cleanValue($row[12] ?? null);
        if ($valueCurrency === null || $valueCurrency === '') {
            $errors[] = "Line {$lineNumber}, column 13 (Value Currency): Value currency is required.";
        } elseif (!$this->isValidCurrencyCode($valueCurrency)) {
            $errors[] = "Line {$lineNumber}, column 13 (Value Currency): Invalid currency code. Value: '{$valueCurrency}'.";
        }

        // Validate Exchange rate (column 13): optional, valid decimal format if provided
        $exchangeRate = $cleanValue($row[13] ?? null);
        if ($exchangeRate !== null && $exchangeRate !== '' && !$this->isValidCurrencyValue($exchangeRate)) {
            $errors[] = "Line {$lineNumber}, column 14 (Exchange rate): Invalid format. Expected numeric value with comma as decimal separator. Value: '{$exchangeRate}'.";
        }

        // Validate Transaction and/or third (column 14): optional, valid currency format if provided
        $transactionAndOrThird = $cleanValue($row[14] ?? null);
        if ($transactionAndOrThird !== null && $transactionAndOrThird !== '' && !$this->isValidCurrencyValue($transactionAndOrThird)) {
            $errors[] = "Line {$lineNumber}, column 15 (Transaction and/or third): Invalid format. Expected numeric value with comma as decimal separator. Value: '{$transactionAndOrThird}'.";
        }

        // Validate Transaction Currency (column 15): optional, valid currency code if transaction_and_or_third is provided
        $transactionCurrency = $cleanValue($row[15] ?? null);
        if ($transactionAndOrThird !== null && $transactionAndOrThird !== '') {
            // If transaction_and_or_third is provided, currency must be valid
            if ($transactionCurrency === null || $transactionCurrency === '') {
                $errors[] = "Line {$lineNumber}, column 16 (Transaction Currency): Transaction currency is required when transaction and/or third is provided.";
            } elseif (!$this->isValidCurrencyCode($transactionCurrency)) {
                $errors[] = "Line {$lineNumber}, column 16 (Transaction Currency): Invalid currency code. Value: '{$transactionCurrency}'.";
            }
        }
        // If transaction_and_or_third is null/empty, currency can also be null/empty (no validation needed)

        // Validate Total (column 16): required, valid currency format
        $total = $cleanValue($row[16] ?? null);
        if ($total === null || $total === '') {
            $errors[] = "Line {$lineNumber}, column 17 (Total): Total is required.";
        } elseif (!$this->isValidCurrencyValue($total)) {
            $errors[] = "Line {$lineNumber}, column 17 (Total): Invalid format. Expected numeric value with comma as decimal separator. Value: '{$total}'.";
        }

        // Validate Total Currency (column 17): required, valid currency code
        $totalCurrency = $cleanValue($row[17] ?? null);
        if ($totalCurrency === null || $totalCurrency === '') {
            $errors[] = "Line {$lineNumber}, column 18 (Total Currency): Total currency is required.";
        } elseif (!$this->isValidCurrencyCode($totalCurrency)) {
            $errors[] = "Line {$lineNumber}, column 18 (Total Currency): Invalid currency code. Value: '{$totalCurrency}'.";
        }

        // Validate Order ID (column 18): optional (can be null or empty)
        // No validation needed as order_id is optional

        return $errors;
    }

    /**
     * Validate date format (DD-MM-YYYY).
     *
     * @param string $date
     * @return bool
     */
    private function isValidDate(string $date): bool
    {
        // Check format DD-MM-YYYY
        if (!preg_match('/^\d{2}-\d{2}-\d{4}$/', $date)) {
            return false;
        }

        // Validate that it's a real date
        $parts = explode('-', $date);
        if (count($parts) !== 3) {
            return false;
        }

        $day = (int)$parts[0];
        $month = (int)$parts[1];
        $year = (int)$parts[2];

        return checkdate($month, $day, $year);
    }

    /**
     * Validate time format (HH:MM).
     *
     * @param string $time
     * @return bool
     */
    private function isValidTime(string $time): bool
    {
        if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
            return false;
        }

        $parts = explode(':', $time);
        $hour = (int)$parts[0];
        $minute = (int)$parts[1];

        return $hour >= 0 && $hour <= 23 && $minute >= 0 && $minute <= 59;
    }


    /**
     * Validate currency value format (numeric with comma as decimal separator).
     *
     * @param string $value
     * @return bool
     */
    private function isValidCurrencyValue(string $value): bool
    {
        // Allow negative values, comma as decimal separator
        // Examples: "147,6800", "-1072,82", "0,50"
        return preg_match('/^-?\d+,\d+$/', $value) === 1 || preg_match('/^-?\d+$/', $value) === 1;
    }

    /**
     * Validate currency code (ISO 4217).
     *
     * @param string $currency
     * @return bool
     */
    private function isValidCurrencyCode(string $currency): bool
    {
        return in_array(strtoupper($currency), self::VALID_CURRENCIES, true);
    }

    /**
     * Validate UUID format.
     *
     * @param string $uuid
     * @return bool
     */
    private function isValidUuid(string $uuid): bool
    {
        // UUID format: 8-4-4-4-12 hexadecimal digits
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid) === 1;
    }
}

