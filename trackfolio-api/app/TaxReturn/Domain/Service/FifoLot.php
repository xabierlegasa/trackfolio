<?php

namespace App\TaxReturn\Domain\Service;

/**
 * @internal Mutable FIFO lot (remaining quantity and cost components in céntimos).
 */
final class FifoLot
{
    public function __construct(
        public string $remainingQty,
        public int $valueCentsRemaining,
        public int $feeCentsRemaining,
        public int $sourceTransactionId,
        public string $sourceDate,
        public string $sourceTime,
        public string $sourceProduct,
        public int $sourcePriceTenThousandths,
        public string $sourcePriceCurrency,
    ) {}
}
