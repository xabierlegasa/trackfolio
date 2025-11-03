<?php

namespace App\DegiroTransaction\Domain\Services;

use App\DegiroTransaction\Domain\DTO\DegiroTransactionDTO;
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
     * @return array{success: bool, message: string, count: int}
     */
    public function processCsv(UploadedFile $file, int $userId): array
    {
        try {
            // Open the file
            $handle = fopen($file->getRealPath(), 'r');
            
            if ($handle === false) {
                return [
                    'success' => false,
                    'message' => 'Unable to open CSV file',
                    'count' => 0
                ];
            }

            // Read and skip the header row
            $header = fgetcsv($handle);
            if ($header === false) {
                fclose($handle);
                return [
                    'success' => false,
                    'message' => 'CSV file is empty or invalid',
                    'count' => 0,
                    'new_count' => 0,
                    'ignored_count' => 0
                ];
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

                try {
                    $transaction = $this->rowParser->parse($row, $userId);
                    if ($transaction !== null) {
                        $allParsedOrderIds[] = $transaction->orderId;
                        $allParsedTransactions[] = $transaction;
                    }
                } catch (\Exception $e) {
                    Log::warning("Failed to parse CSV line {$lineNumber}: " . $e->getMessage());
                    // Continue processing other rows
                    continue;
                }
            }

            fclose($handle);

            if (empty($allParsedTransactions)) {
                return [
                    'success' => false,
                    'message' => 'No valid transactions found in CSV file',
                    'count' => 0,
                    'new_count' => 0,
                    'ignored_count' => 0
                ];
            }

            // Check which order_ids already exist in the database
            $existingOrderIds = $this->repository->findExistingOrderIds($userId, $allParsedOrderIds);
            $existingOrderIdsSet = array_flip($existingOrderIds);

            // Filter transactions: only add those that don't exist yet
            $newTransactions = [];
            $ignoredCount = 0;

            foreach ($allParsedTransactions as $transaction) {
                if (isset($existingOrderIdsSet[$transaction->orderId])) {
                    $ignoredCount++;
                } else {
                    $newTransactions[] = $transaction;
                }
            }

            // If no new transactions, return success with ignored count
            if (empty($newTransactions)) {
                return [
                    'success' => true,
                    'message' => "All transactions were already in the database",
                    'count' => 0,
                    'new_count' => 0,
                    'ignored_count' => $ignoredCount
                ];
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

                return [
                    'success' => true,
                    'message' => "{$newCount} Transactions uploaded successfully",
                    'count' => $newCount,
                    'new_count' => $newCount,
                    'ignored_count' => $ignoredCount
                ];
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("Failed to store transactions: " . $e->getMessage());
                
                return [
                    'success' => false,
                    'message' => 'Failed to store transactions: ' . $e->getMessage(),
                    'count' => 0,
                    'new_count' => 0,
                    'ignored_count' => 0
                ];
            }
        } catch (\Exception $e) {
            Log::error("Error processing CSV: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error processing CSV file: ' . $e->getMessage(),
                'count' => 0,
                'new_count' => 0,
                'ignored_count' => 0
            ];
        }
    }
}

