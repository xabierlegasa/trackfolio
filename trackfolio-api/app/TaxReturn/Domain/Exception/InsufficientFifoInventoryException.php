<?php

namespace App\TaxReturn\Domain\Exception;

use RuntimeException;

class InsufficientFifoInventoryException extends RuntimeException
{
    public function __construct(
        public readonly string $isin,
        public readonly string $date,
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : "FIFO: not enough purchased quantity to cover a sale (ISIN {$isin}, date {$date}).");
    }
}
