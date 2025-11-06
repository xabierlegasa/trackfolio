<?php

namespace App\Isin\Domain\DTO;

class StockSearchResultDTO
{
    public function __construct(
        public readonly string $description,
        public readonly string $displaySymbol,
        public readonly string $symbol,
        public readonly string $type,
    ) {}

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'description' => $this->description,
            'displaySymbol' => $this->displaySymbol,
            'symbol' => $this->symbol,
            'type' => $this->type,
        ];
    }
}


