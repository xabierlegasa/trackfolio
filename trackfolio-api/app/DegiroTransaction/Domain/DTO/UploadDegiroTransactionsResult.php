<?php

namespace App\DegiroTransaction\Domain\DTO;

class UploadDegiroTransactionsResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly int $count = 0,
        public readonly int $newCount = 0,
        public readonly int $ignoredCount = 0,
        public readonly ?array $errors = null,
    ) {}

    /**
     * Convert the result to an array for JSON responses.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [
            'success' => $this->success,
            'message' => $this->message,
            'count' => $this->count,
            'new_count' => $this->newCount,
            'ignored_count' => $this->ignoredCount,
        ];

        if ($this->errors !== null && !empty($this->errors)) {
            $result['errors'] = $this->errors;
        }

        return $result;
    }

    /**
     * Create a success result.
     *
     * @param string $message
     * @param int $newCount
     * @param int $ignoredCount
     * @return self
     */
    public static function success(string $message, int $newCount, int $ignoredCount = 0): self
    {
        return new self(
            success: true,
            message: $message,
            count: $newCount,
            newCount: $newCount,
            ignoredCount: $ignoredCount,
        );
    }

    /**
     * Create a failure result.
     *
     * @param string $message
     * @param array<string>|null $errors
     * @return self
     */
    public static function failure(string $message, ?array $errors = null): self
    {
        return new self(
            success: false,
            message: $message,
            count: 0,
            newCount: 0,
            ignoredCount: 0,
            errors: $errors,
        );
    }
}

