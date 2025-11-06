<?php

namespace App\Isin\Domain\DTO;

class StockSearchResponseDTO
{
    /**
     * @param StockSearchResultDTO[] $results
     */
    public function __construct(
        public readonly array $results,
    ) {}

    /**
     * Create from API response array.
     *
     * @param array $data
     * @return self|null
     */
    public static function fromArray(array $data): ?self
    {
        if (!isset($data['result']) || !is_array($data['result'])) {
            return null;
        }

        $results = [];
        foreach ($data['result'] as $item) {
            if (!isset($item['symbol']) || !isset($item['description'])) {
                continue;
            }
            $results[] = new StockSearchResultDTO(
                description: $item['description'] ?? '',
                displaySymbol: $item['displaySymbol'] ?? '',
                symbol: $item['symbol'] ?? '',
                type: $item['type'] ?? '',
            );
        }

        return new self($results);
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'result' => array_map(fn($result) => $result->toArray(), $this->results),
        ];
    }
}


