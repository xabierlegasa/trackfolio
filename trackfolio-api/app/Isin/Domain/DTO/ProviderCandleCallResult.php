<?php

namespace App\Isin\Domain\DTO;

class ProviderCandleCallResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?StockCandleDTO $candle = null,
        public readonly mixed $response = null,
        public readonly ?int $httpStatus = null,
        public readonly ?string $errorMessage = null,
    ) {}
}
