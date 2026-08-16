<?php

namespace App\AccountStatement\Domain\DTO;

class UploadAccountStatementsResult
{
    /**
     * @param array<int, string>|null $errors
     */
    public function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly int $count = 0,
        public readonly int $newCount = 0,
        public readonly int $ignoredCount = 0,
        public readonly ?array $errors = null,
    ) {}

    /**
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

        if ($this->errors !== null && $this->errors !== []) {
            $result['errors'] = $this->errors;
        }

        return $result;
    }

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
     * @param array<string>|null $errors
     */
    public static function failure(string $message, ?array $errors = null): self
    {
        return new self(
            success: false,
            message: $message,
            errors: $errors,
        );
    }
}
