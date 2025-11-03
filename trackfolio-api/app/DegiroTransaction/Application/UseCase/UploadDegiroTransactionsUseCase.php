<?php

namespace App\DegiroTransaction\Application\UseCase;

use App\DegiroTransaction\Domain\Services\UploadDegiroTransactionsService;
use App\DegiroTransaction\Domain\Services\ValidateDegiroTransactionsCsvService;
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
     * @return array{success: bool, message: string, count: int, errors?: array<string>}
     */
    public function execute(UploadedFile $file, int $userId): array
    {
        // Validate CSV before processing
        $validationResult = $this->validator->validate($file);
        
        if (!$validationResult['valid']) {
            return [
                'success' => false,
                'message' => 'CSV validation failed',
                'count' => 0,
                'errors' => $validationResult['errors']
            ];
        }

        // If validation passes, process the CSV
        return $this->uploadService->processCsv($file, $userId);
    }
}

