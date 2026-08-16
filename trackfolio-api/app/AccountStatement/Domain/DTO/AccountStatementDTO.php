<?php

namespace App\AccountStatement\Domain\DTO;

class AccountStatementDTO
{
    public function __construct(
        public readonly int $userId,
        public readonly string $date,
        public readonly ?string $time,
        public readonly ?string $valueDate,
        public readonly ?string $product,
        public readonly ?string $isin,
        public readonly ?string $description,
        public readonly ?string $fx,
        public readonly ?string $changeCurrency,
        public readonly ?int $changeMinUnit,
        public readonly ?string $balanceCurrency,
        public readonly ?int $balanceMinUnit,
        public readonly ?string $orderId,
        public readonly string $customContentHash,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'date' => $this->date,
            'time' => $this->time,
            'value_date' => $this->valueDate,
            'product' => $this->product,
            'isin' => $this->isin,
            'description' => $this->description,
            'fx' => $this->fx,
            'change_currency' => $this->changeCurrency,
            'change_min_unit' => $this->changeMinUnit,
            'balance_currency' => $this->balanceCurrency,
            'balance_min_unit' => $this->balanceMinUnit,
            'order_id' => $this->orderId,
            'custom_content_hash' => $this->customContentHash,
        ];
    }
}
