<?php

namespace App\Isin\Domain\DTO;

class StockInfoDTO
{
    public function __construct(
        public readonly string $symbol,
        public readonly ?string $description,
        public readonly ?string $displaySymbol,
        public readonly ?string $type,
        public readonly ?StockQuoteDTO $quote,
    ) {}

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'symbol' => $this->symbol,
            'description' => $this->description,
            'displaySymbol' => $this->displaySymbol,
            'type' => $this->type,
            'quote' => $this->quote?->toArray(),
        ];
    }
}

