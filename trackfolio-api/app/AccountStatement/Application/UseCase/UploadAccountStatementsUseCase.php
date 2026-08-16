<?php

namespace App\AccountStatement\Application\UseCase;

use App\AccountStatement\Domain\DTO\UploadAccountStatementsResult;
use App\AccountStatement\Domain\Service\UploadAccountStatementsService;
use App\AccountStatement\Domain\Service\ValidateAccountStatementsCsvService;
use Illuminate\Http\UploadedFile;

class UploadAccountStatementsUseCase
{
    public function __construct(
        private ValidateAccountStatementsCsvService $validator,
        private UploadAccountStatementsService $uploadService,
    ) {}

    public function execute(UploadedFile $file, int $userId): UploadAccountStatementsResult
    {
        $validation = $this->validator->validate($file);
        if (!$validation['valid']) {
            return UploadAccountStatementsResult::failure(
                'CSV validation failed',
                $validation['errors'],
            );
        }

        return $this->uploadService->processCsv($file, $userId);
    }
}
