<?php

namespace App\DegiroTransaction\Application\UseCase;

use App\DegiroTransaction\Domain\DTO\UploadDegiroTransactionsResult;
use App\DegiroTransaction\Domain\Service\UploadDegiroTransactionsService;
use App\DegiroTransaction\Domain\Service\ValidateDegiroTransactionsCsvService;
use Illuminate\Http\UploadedFile;

class UploadDegiroTransactionsUseCase
{
    public function __construct(
        private ValidateDegiroTransactionsCsvService $validator,
        private UploadDegiroTransactionsService $uploadService
    ) {}

    /**
     * Execute the upload Degiro transactions use case.
     *
     * @param UploadedFile $file
     * @param int $userId
     * @return UploadDegiroTransactionsResult
     */
    public function execute(UploadedFile $file, int $userId): UploadDegiroTransactionsResult
    {
        // Validate CSV before processing
        $validationResult = $this->validator->validate($file);
        
        if (!$validationResult['valid']) {
            return UploadDegiroTransactionsResult::failure(
                'CSV validation failed',
                $validationResult['errors']
            );
        }

        // If validation passes, process the CSV
        return $this->uploadService->processCsv($file, $userId);
    }
}

