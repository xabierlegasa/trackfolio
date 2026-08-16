<?php

namespace App\AccountStatement\Domain\Service;

use App\AccountStatement\Domain\DTO\AccountStatementDTO;
use App\DegiroTransaction\Domain\Service\ConvertCurrencyToMinUnitService;
use Carbon\Carbon;

class ParseAccountStatementRowService
{
    public function __construct(
        private ConvertCurrencyToMinUnitService $currencyConverter,
    ) {}

    /**
     * Degiro Account Statement: 12 columns
     * Date,Time,Value date,Product,ISIN,Description,FX,Change,,Balance,,Order Id
     *
     * @param  list<string|null>  $row
     */
    public function parse(array $row, int $userId): ?AccountStatementDTO
    {
        if (count($row) < 11) {
            return null;
        }

        $clean = static function ($value): ?string {
            if ($value === null || $value === '') {
                return null;
            }

            $trimmed = trim(trim((string) $value, '"'));

            return $trimmed === '' ? null : $trimmed;
        };

        $dateRaw = $clean($row[0] ?? null);
        $time = $clean($row[1] ?? null);
        $valueDateRaw = $clean($row[2] ?? null);
        $product = $clean($row[3] ?? null);
        $isin = $clean($row[4] ?? null);
        $description = $clean($row[5] ?? null);
        $fx = $clean($row[6] ?? null);
        $changeCurrency = $clean($row[7] ?? null);
        $changeMinUnit = $this->currencyConverter->convertToCents($row[8] ?? null);
        $balanceCurrency = $clean($row[9] ?? null);
        $balanceMinUnit = $this->currencyConverter->convertToCents($row[10] ?? null);
        $orderId = $clean($row[11] ?? null);

        $date = $this->toIsoDate($dateRaw);
        if ($date === null) {
            return null;
        }

        $valueDate = $this->toIsoDate($valueDateRaw);

        $hashSource = implode('|', [
            (string) $userId,
            $date,
            $time ?? '',
            $valueDate ?? '',
            $product ?? '',
            $isin ?? '',
            $description ?? '',
            $fx ?? '',
            $changeCurrency ?? '',
            (string) ($changeMinUnit ?? ''),
            $balanceCurrency ?? '',
            (string) ($balanceMinUnit ?? ''),
            $orderId ?? '',
        ]);

        return new AccountStatementDTO(
            userId: $userId,
            date: $date,
            time: $time,
            valueDate: $valueDate,
            product: $product,
            isin: $isin,
            description: $description,
            fx: $fx,
            changeCurrency: $changeCurrency !== null ? strtoupper($changeCurrency) : null,
            changeMinUnit: $changeMinUnit,
            balanceCurrency: $balanceCurrency !== null ? strtoupper($balanceCurrency) : null,
            balanceMinUnit: $balanceMinUnit,
            orderId: $orderId,
            customContentHash: hash('sha256', $hashSource),
        );
    }

    private function toIsoDate(?string $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('d-m-Y', $raw)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
