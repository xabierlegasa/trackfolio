<?php

namespace App\DegiroTransaction\Domain\Services;

use App\DegiroTransaction\Domain\DTO\DegiroTransactionDTO;
use App\DegiroTransaction\Domain\DTO\UploadDegiroTransactionsResult;
use App\DegiroTransaction\Infrastructure\Repository\DegiroTransactionRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class UploadDegiroTransactionsService
{
    public function __construct(
        private DegiroTransactionRepository $repository,
        private ParseDegiroTransactionRowService $rowParser
    ) {}

    /**
     * Process and store Degiro transactions from CSV file.
     *
     * @param UploadedFile $file
     * @param int $userId
     * @return UploadDegiroTransactionsResult
     */
    public function processCsv(UploadedFile $file, int $userId): UploadDegiroTransactionsResult
    {
        try {
            // Open the file
            $handle = fopen($file->getRealPath(), 'r');
            
            if ($handle === false) {
                return UploadDegiroTransactionsResult::failure('Unable to open CSV file');
            }

            // Read and skip the header row
            $header = fgetcsv($handle);
            if ($header === false) {
                fclose($handle);
                return UploadDegiroTransactionsResult::failure('CSV file is empty or invalid');
            }

            // Get all existing order_ids for this user to check for duplicates
            $allParsedOrderIds = [];
            $allParsedTransactions = [];
            $lineNumber = 1; // Start at 1 because we already read the header

            // First pass: parse all transactions to collect order_ids
            while (($row = fgetcsv($handle)) !== false) {
                $lineNumber++;
                
                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }

                $transaction = $this->rowParser->parse($row, $userId);
                if ($transaction === null) {
                    throw new \RuntimeException("Failed to parse CSV line {$lineNumber}: Invalid or incomplete row data");
                }

                $allParsedOrderIds[] = $transaction->orderId;
                $allParsedTransactions[] = $transaction;
            }

            fclose($handle);

            if (empty($allParsedTransactions)) {
                return UploadDegiroTransactionsResult::failure('No valid transactions found in CSV file');
            }

            Log::info('debu 2');
            // Check which order_ids already exist in the database
            $existingOrderIds = $this->repository->findExistingOrderIds($userId, $allParsedOrderIds);
            
            // Filter transactions: only add those that don't exist yet
            $newTransactions = [];
            $ignoredCount = 0;

            foreach ($allParsedTransactions as $transaction) {
                if (in_array($transaction->orderId, $existingOrderIds)) {
                    $ignoredCount++;
                } else {
                    $newTransactions[] = $transaction;
                }
            }

            // If no new transactions, return success with ignored count
            if (empty($newTransactions)) {
                return UploadDegiroTransactionsResult::success(
                    "All transactions were already in the database",
                    0,
                    $ignoredCount
                );
            }

            // Store only new transactions
            DB::beginTransaction();
            try {
                // Convert DTOs to arrays and add timestamps
                $now = now();
                $transactionArrays = array_map(function (DegiroTransactionDTO $dto) use ($now) {
                    $array = $dto->toArray();
                    $array['created_at'] = $now;
                    $array['updated_at'] = $now;
                    return $array;
                }, $newTransactions);
                
                $newCount = $this->repository->createMany($transactionArrays);
                DB::commit();

                return UploadDegiroTransactionsResult::success(
                    "{$newCount} Transactions uploaded successfully",
                    $newCount,
                    $ignoredCount
                );
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("Failed to store transactions: " . $e->getMessage());
                
                return UploadDegiroTransactionsResult::failure(
                    'Failed to store transactions: ' . $e->getMessage()
                );
            }
        } catch (\Exception $e) {
            Log::error("Error processing CSV: " . $e->getMessage());
            return UploadDegiroTransactionsResult::failure(
                'Error processing CSV file: ' . $e->getMessage()
            );
        }
    }
}

