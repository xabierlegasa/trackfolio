<?php

namespace App\DegiroTransaction\Domain\Service;

use App\DegiroTransaction\Domain\DTO\DegiroTransactionDTO;

class ParseDegiroTransactionRowService
{
    public function __construct(
        private ConvertCurrencyToMinUnitService $currencyConverter
    ) {}

    /**
     * Parse a single CSV row into transaction data.
     *
     * Legacy (19 columns): separate Value / Total currency columns.
     * Current DEGIRO EU export (17–18 columns): Value EUR, AutoFX Fee, fees and total in EUR; optional blank before Order ID.
     *
     * @param array $row The CSV row data
     * @param int $userId The user ID to associate with the transaction
     * @return DegiroTransactionDTO|null The parsed transaction DTO, or null if invalid
     */
    public function parse(array $row, int $userId): ?DegiroTransactionDTO
    {
        $columnCount = count($row);
        if ($columnCount !== 19 && $columnCount !== 17 && $columnCount !== 18) {
            return null;
        }

        // Clean values (remove quotes and trim)
        $cleanValue = function($value) {
            if ($value === null || $value === '') {
                return null;
            }
            return trim(trim($value, '"'));
        };

        $date = $cleanValue($row[0] ?? null);
        $time = $cleanValue($row[1] ?? null);
        $product = $cleanValue($row[2] ?? null);
        $isin = $cleanValue($row[3] ?? null);
        $reference = $cleanValue($row[4] ?? null);
        $venue = $cleanValue($row[5] ?? null);
        // Parse quantity as float (supports decimal values like 0,004727)
        $quantityRaw = $cleanValue($row[6] ?? null);
        $quantity = null;
        if ($quantityRaw !== null && $quantityRaw !== '') {
            // Convert comma to dot for parsing
            $standard = str_replace(',', '.', $quantityRaw);
            $quantity = is_numeric($standard) ? (float)$standard : null;
        }
        $priceTenThousandths = $this->currencyConverter->convertToTenThousandths($row[7] ?? null);
        $priceCurrency = $cleanValue($row[8] ?? null);
        $localValueMinUnit = $this->currencyConverter->convertToCents($row[9] ?? null);
        $localValueCurrency = $cleanValue($row[10] ?? null);
        $valueMinUnit = $this->currencyConverter->convertToCents($row[11] ?? null);

        $autoFxForHash = '';

        if ($columnCount === 19) {
            $valueCurrency = $cleanValue($row[12] ?? null);
            $exchangeRate = $cleanValue($row[13] ?? null);
            $transactionAndOrThird = $cleanValue($row[14] ?? null);
            $transactionCurrency = $cleanValue($row[15] ?? null);
            $totalMinUnit = $this->currencyConverter->convertToCents($row[16] ?? null);
            $totalCurrency = $cleanValue($row[17] ?? null);
            $orderId = $cleanValue($row[18] ?? null);
        } else {
            // 17 or 18 columns: EUR product totals in export; local/price currencies still per column
            $valueCurrency = 'EUR';
            $exchangeRate = $cleanValue($row[12] ?? null);
            $autoFxForHash = $cleanValue($row[13] ?? null) ?? '';
            $transactionAndOrThird = $cleanValue($row[14] ?? null);
            $transactionCurrency = ($transactionAndOrThird !== null && $transactionAndOrThird !== '') ? 'EUR' : null;
            $totalMinUnit = $this->currencyConverter->convertToCents($row[15] ?? null);
            $totalCurrency = 'EUR';
            if ($columnCount === 18) {
                $cell16 = $cleanValue($row[16] ?? null);
                $cell17 = $cleanValue($row[17] ?? null);
                $orderId = ($cell17 !== null && $cell17 !== '') ? $cell17 : $cell16;
            } else {
                $orderId = $cleanValue($row[16] ?? null);
            }
        }

        // Validate required fields are not null
        // Note: exchangeRate, transactionAndOrThird, transactionCurrency, and orderId are optional (nullable)
        if ($date === null || $time === null || $product === null || $isin === null ||
            $reference === null || $quantity === null || $priceTenThousandths === null ||
            $priceCurrency === null || $localValueMinUnit === null ||
            $localValueCurrency === null || $valueMinUnit === null ||
            $valueCurrency === null ||
            $totalMinUnit === null || $totalCurrency === null) {
            return null;
        }

        // Calculate content hash from all column values (legacy hash unchanged for 19-column rows)
        $hashParts = [
            $userId,
            $date,
            $time,
            $product,
            $isin,
            $reference,
            $venue ?? '',
            number_format($quantity, 10, '.', ''),
            $priceTenThousandths,
            $priceCurrency,
            $localValueMinUnit,
            $localValueCurrency,
            $valueMinUnit,
            $valueCurrency,
            $exchangeRate ?? '',
        ];
        if ($columnCount !== 19) {
            $hashParts[] = $autoFxForHash;
        }
        $hashParts[] = $transactionAndOrThird ?? '';
        $hashParts[] = $transactionCurrency ?? '';
        $hashParts[] = $totalMinUnit;
        $hashParts[] = $totalCurrency;
        $hashParts[] = $orderId ?? '';
        $contentForHash = implode('|', $hashParts);
        
        $customContentHash = hash('sha256', $contentForHash);

        return new DegiroTransactionDTO(
            userId: $userId,
            date: $date,
            time: $time,
            product: $product,
            isin: $isin,
            reference: $reference,
            venue: $venue,
            quantity: $quantity,
            priceTenThousandths: $priceTenThousandths,
            priceCurrency: $priceCurrency,
            localValueMinUnit: $localValueMinUnit,
            localValueCurrency: $localValueCurrency,
            valueMinUnit: $valueMinUnit,
            valueCurrency: $valueCurrency,
            exchangeRate: $exchangeRate,
            transactionAndOrThird: $transactionAndOrThird,
            transactionCurrency: $transactionCurrency,
            totalMinUnit: $totalMinUnit,
            totalCurrency: $totalCurrency,
            orderId: $orderId,
            customContentHash: $customContentHash,
        );
    }
}

