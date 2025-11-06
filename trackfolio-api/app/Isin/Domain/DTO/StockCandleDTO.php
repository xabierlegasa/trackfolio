<?php

namespace App\Isin\Domain\DTO;

class StockCandleDTO
{
    /**
     * @param string $status Status ('ok', 'no_data', etc.)
     * @param float[] $closePrices Array of close prices
     * @param float[] $highPrices Array of high prices
     * @param float[] $lowPrices Array of low prices
     * @param float[] $openPrices Array of open prices
     * @param int[] $timestamps Array of timestamps
     * @param int[] $volumes Array of volumes
     */
    public function __construct(
        public readonly string $status,
        public readonly array $closePrices,
        public readonly array $highPrices,
        public readonly array $lowPrices,
        public readonly array $openPrices,
        public readonly array $timestamps,
        public readonly array $volumes,
    ) {}

    /**
     * Create from API response array.
     *
     * @param array $data
     * @return self|null
     */
    public static function fromArray(array $data): ?self
    {
        if (!isset($data['s'])) {
            return null;
        }

        return new self(
            status: $data['s'] ?? 'no_data',
            closePrices: isset($data['c']) && is_array($data['c']) 
                ? array_map(fn($value) => (float) $value, $data['c']) 
                : [],
            highPrices: isset($data['h']) && is_array($data['h']) 
                ? array_map(fn($value) => (float) $value, $data['h']) 
                : [],
            lowPrices: isset($data['l']) && is_array($data['l']) 
                ? array_map(fn($value) => (float) $value, $data['l']) 
                : [],
            openPrices: isset($data['o']) && is_array($data['o']) 
                ? array_map(fn($value) => (float) $value, $data['o']) 
                : [],
            timestamps: isset($data['t']) && is_array($data['t']) 
                ? array_map(fn($value) => (int) $value, $data['t']) 
                : [],
            volumes: isset($data['v']) && is_array($data['v']) 
                ? array_map(fn($value) => (int) $value, $data['v']) 
                : [],
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
            's' => $this->status,
            'c' => $this->closePrices,
            'h' => $this->highPrices,
            'l' => $this->lowPrices,
            'o' => $this->openPrices,
            't' => $this->timestamps,
            'v' => $this->volumes,
        ];
    }
}

