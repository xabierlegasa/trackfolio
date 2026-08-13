<?php

namespace App\DegiroTransaction\Domain\DTO;

class UploadDegiroTransactionsResult
{
    /**
     * @param array<int, string>|null $errors
     * @param array<int, array{line: int, reason: string, date?: string|null, time?: string|null, product?: string|null, local_value?: string|null}> $skippedRows
     */
    public function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly int $count = 0,
        public readonly int $newCount = 0,
        public readonly int $ignoredCount = 0,
        public readonly int $skippedCount = 0,
        public readonly array $skippedRows = [],
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
            'skipped_count' => $this->skippedCount,
            'skipped_rows' => $this->skippedRows,
        ];

        if ($this->errors !== null && !empty($this->errors)) {
            $result['errors'] = $this->errors;
        }

        return $result;
    }

    /**
     * Create a success result.
     *
     * @param array<int, array{line: int, reason: string, date?: string|null, time?: string|null, product?: string|null, local_value?: string|null}> $skippedRows
     */
    public static function success(
        string $message,
        int $newCount,
        int $ignoredCount = 0,
        array $skippedRows = [],
    ): self {
        return new self(
            success: true,
            message: $message,
            count: $newCount,
            newCount: $newCount,
            ignoredCount: $ignoredCount,
            skippedCount: count($skippedRows),
            skippedRows: $skippedRows,
        );
    }

    /**
     * Create a failure result.
     *
     * @param array<string>|null $errors
     */
    public static function failure(string $message, ?array $errors = null): self
    {
        return new self(
            success: false,
            message: $message,
            count: 0,
            newCount: 0,
            ignoredCount: 0,
            skippedCount: 0,
            skippedRows: [],
            errors: $errors,
        );
    }
}
