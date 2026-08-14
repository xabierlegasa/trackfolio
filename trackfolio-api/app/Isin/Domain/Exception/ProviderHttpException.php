<?php

namespace App\Isin\Domain\Exception;

class ProviderHttpException extends \Exception
{
    public function __construct(
        string $message,
        public readonly ?int $httpStatus = null,
        public readonly mixed $rawResponse = null,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
