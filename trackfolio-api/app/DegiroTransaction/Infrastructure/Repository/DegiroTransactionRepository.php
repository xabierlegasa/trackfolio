<?php

namespace App\DegiroTransaction\Infrastructure\Repository;

use App\DegiroTransaction\Domain\Entity\DegiroTransaction;
use Illuminate\Support\Collection;

class DegiroTransactionRepository
{
    /**
     * Create a new Degiro transaction.
     *
     * @param array $data
     * @return DegiroTransaction
     */
    public function create(array $data): DegiroTransaction
    {
        return DegiroTransaction::create($data);
    }

    /**
     * Create multiple Degiro transactions.
     * All transactions should have been pre-validated for duplicates.
     *
     * @param array $transactions Array of transaction data arrays
     * @return int Number of transactions created
     */
    public function createMany(array $transactions): int
    {
        if (empty($transactions)) {
            return 0;
        }

        // Use bulk insert for better performance
        // All duplicates have been filtered out beforehand
        DegiroTransaction::insert($transactions);
        
        return count($transactions);
    }

    /**
     * Get all transactions for a user.
     *
     * @param int $userId
     * @return Collection
     */
    public function findByUserId(int $userId): Collection
    {
        return DegiroTransaction::where('user_id', $userId)->get();
    }

    /**
     * Count transactions for a user.
     *
     * @param int $userId
     * @return int
     */
    public function countByUserId(int $userId): int
    {
        return DegiroTransaction::where('user_id', $userId)->count();
    }

    /**
     * Get paginated transactions for a user, ordered by ID (most recent first).
     *
     * @param int $userId
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function findPaginatedByUserId(int $userId, int $perPage = 20)
    {
        return DegiroTransaction::where('user_id', $userId)
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get existing content hashes for a user.
     *
     * @param int $userId
     * @param array $hashes
     * @return array Array of existing content hashes
     */
    public function findExistingContentHashes(int $userId, array $hashes): array
    {
        return DegiroTransaction::where('user_id', $userId)
            ->whereIn('custom_content_hash', $hashes)
            ->pluck('custom_content_hash')
            ->toArray();
    }
}

