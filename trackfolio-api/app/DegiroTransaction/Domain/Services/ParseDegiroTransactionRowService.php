<?php

namespace App\DegiroTransaction\Domain\Services;

use App\DegiroTransaction\Domain\DTO\DegiroTransactionDTO;

class ParseDegiroTransactionRowService
{
    public function __construct(
        private ConvertCurrencyToMinUnitService $currencyConverter
    ) {}

    /**
     * Parse a single CSV row into transaction data.
     *
     * CSV structure (19 columns):
     * Date, Time, Product, ISIN, Reference, Venue, Quantity, Price, Currency1, 
     * Local value, Currency2, Value, Currency3, Exchange rate, Transaction and/or third, 
     * Currency4, Total, Currency5, Order ID
     *
     * @param array $row The CSV row data
     * @param int $userId The user ID to associate with the transaction
     * @return DegiroTransactionDTO|null The parsed transaction DTO, or null if invalid
     */
    public function parse(array $row, int $userId): ?DegiroTransactionDTO
    {
        if (count($row) < 8) { // Minimum required columns
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
        $quantity = is_numeric($row[6] ?? null) ? (int)$row[6] : null;
        $priceMinUnit = $this->currencyConverter->convertToCents($row[7] ?? null);
        $priceCurrency = $cleanValue($row[8] ?? null);
        $localValueMinUnit = $this->currencyConverter->convertToCents($row[9] ?? null);
        $localValueCurrency = $cleanValue($row[10] ?? null);
        $valueMinUnit = $this->currencyConverter->convertToCents($row[11] ?? null);
        $valueCurrency = $cleanValue($row[12] ?? null);
        $exchangeRate = $cleanValue($row[13] ?? null);
        $transactionAndOrThird = $cleanValue($row[14] ?? null);
        $transactionCurrency = $cleanValue($row[15] ?? null);
        $totalMinUnit = $this->currencyConverter->convertToCents($row[16] ?? null);
        $totalCurrency = $cleanValue($row[17] ?? null);
        $orderId = $cleanValue($row[18] ?? null);

        // Validate required fields are not null
        if ($date === null || $time === null || $product === null || $isin === null || 
            $reference === null || $quantity === null || $priceMinUnit === null || 
            $priceCurrency === null || $localValueMinUnit === null || 
            $localValueCurrency === null || $valueMinUnit === null || 
            $valueCurrency === null || $exchangeRate === null || 
            $transactionAndOrThird === null || $transactionCurrency === null || 
            $totalMinUnit === null || $totalCurrency === null || $orderId === null) {
            return null;
        }

        return new DegiroTransactionDTO(
            userId: $userId,
            date: $date,
            time: $time,
            product: $product,
            isin: $isin,
            reference: $reference,
            venue: $venue,
            quantity: $quantity,
            priceMinUnit: $priceMinUnit,
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
        );
    }
}

