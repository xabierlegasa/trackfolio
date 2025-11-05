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

    /**
     * Get portfolio holdings for a user, grouped by ISIN.
     * Returns products with non-zero quantities, ordered by quantity descending.
     *
     * @param int $userId
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getPortfolioHoldings(int $userId, int $perPage = 20)
    {
        // Get aggregated holdings grouped by ISIN (without product name to avoid GROUP BY issues)
        $holdings = DegiroTransaction::where('user_id', $userId)
            ->selectRaw('isin, SUM(quantity) as total_quantity')
            ->groupBy('isin')
            ->havingRaw('SUM(quantity) != 0')
            ->orderByRaw('SUM(quantity) DESC')
            ->paginate($perPage);

        // Get all ISINs from the holdings
        $isins = $holdings->getCollection()->pluck('isin')->toArray();

        // Get the latest product name for each ISIN (only if we have ISINs)
        $latestProducts = collect();
        if (!empty($isins)) {
            $latestProducts = DegiroTransaction::where('user_id', $userId)
                ->whereIn('isin', $isins)
                ->select('isin', 'product', 'id')
                ->orderBy('id', 'desc')
                ->get()
                ->groupBy('isin')
                ->map(function ($transactions) {
                    // Get the first (latest) transaction for each ISIN
                    return $transactions->first()->product;
                });
        }

        // Transform the holdings to add product name and ensure quantity is a float
        $holdings->getCollection()->transform(function ($holding) use ($latestProducts) {
            $holding->product = $latestProducts->get($holding->isin) ?? '';
            $holding->quantity = (float) $holding->total_quantity;
            unset($holding->total_quantity);
            return $holding;
        });

        return $holdings;
    }
}

