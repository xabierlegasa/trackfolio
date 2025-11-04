<?php

namespace App\DegiroTransaction\Domain\DTO;

class DegiroTransactionDTO
{
    public function __construct(
        public readonly int $userId,
        public readonly string $date,
        public readonly string $time,
        public readonly string $product,
        public readonly string $isin,
        public readonly string $reference,
        public readonly ?string $venue,
        public readonly int $quantity,
        public readonly int $priceMinUnit,
        public readonly string $priceCurrency,
        public readonly int $localValueMinUnit,
        public readonly string $localValueCurrency,
        public readonly int $valueMinUnit,
        public readonly string $valueCurrency,
        public readonly string $exchangeRate,
        public readonly ?string $transactionAndOrThird,
        public readonly ?string $transactionCurrency,
        public readonly int $totalMinUnit,
        public readonly string $totalCurrency,
        public readonly string $orderId,
        public readonly ?\Carbon\Carbon $createdAt = null,
        public readonly ?\Carbon\Carbon $updatedAt = null,
    ) {}

    /**
     * Convert the DTO to an array for database insertion.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'user_id' => $this->userId,
            'date' => $this->date,
            'time' => $this->time,
            'product' => $this->product,
            'isin' => $this->isin,
            'reference' => $this->reference,
            'venue' => $this->venue,
            'quantity' => $this->quantity,
            'price_min_unit' => $this->priceMinUnit,
            'price_currency' => $this->priceCurrency,
            'local_value_min_unit' => $this->localValueMinUnit,
            'local_value_currency' => $this->localValueCurrency,
            'value_min_unit' => $this->valueMinUnit,
            'value_currency' => $this->valueCurrency,
            'exchange_rate' => $this->exchangeRate,
            'transaction_and_or_third' => $this->transactionAndOrThird,
            'transaction_currency' => $this->transactionCurrency,
            'total_min_unit' => $this->totalMinUnit,
            'total_currency' => $this->totalCurrency,
            'order_id' => $this->orderId,
        ];

        if ($this->createdAt !== null) {
            $data['created_at'] = $this->createdAt;
        }

        if ($this->updatedAt !== null) {
            $data['updated_at'] = $this->updatedAt;
        }

        return $data;
    }
}

