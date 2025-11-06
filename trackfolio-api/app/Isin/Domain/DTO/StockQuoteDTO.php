<?php

namespace App\Isin\Domain\DTO;

class StockQuoteDTO
{
    public function __construct(
        public readonly ?float $currentPrice,      // c
        public readonly ?float $change,            // d
        public readonly ?float $percentChange,     // dp
        public readonly ?float $highPrice,         // h
        public readonly ?float $lowPrice,          // l
        public readonly ?float $openPrice,         // o
        public readonly ?float $previousClose,     // pc
    ) {}

    /**
     * Create from API response array.
     *
     * @param array $data
     * @return self|null
     */
    public static function fromArray(array $data): ?self
    {
        return new self(
            currentPrice: isset($data['c']) ? (float) $data['c'] : null,
            change: isset($data['d']) ? (float) $data['d'] : null,
            percentChange: isset($data['dp']) ? (float) $data['dp'] : null,
            highPrice: isset($data['h']) ? (float) $data['h'] : null,
            lowPrice: isset($data['l']) ? (float) $data['l'] : null,
            openPrice: isset($data['o']) ? (float) $data['o'] : null,
            previousClose: isset($data['pc']) ? (float) $data['pc'] : null,
        );
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'c' => $this->currentPrice,
            'd' => $this->change,
            'dp' => $this->percentChange,
            'h' => $this->highPrice,
            'l' => $this->lowPrice,
            'o' => $this->openPrice,
            'pc' => $this->previousClose,
        ];
    }
}

