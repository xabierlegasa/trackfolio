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
    public function findPaginatedByUserId(int $userId, int $perPage = 10)
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
    public function getPortfolioHoldings(int $userId, int $perPage = 10)
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

    /**
     * Get closed trades for a user, grouped by ISIN.
     * Returns products that have been completely closed (total quantity = 0),
     * with profit/loss, first purchase date, and last sale date.
     *
     * @param int $userId
     * @param int $perPage
     * @param string $sortBy
     * @param string $sortOrder
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getClosedTrades(int $userId, int $perPage = 10, string $sortBy = 'last_sale_date', string $sortOrder = 'desc')
    {
        // Validate sort order
        $sortOrder = strtolower($sortOrder) === 'asc' ? 'ASC' : 'DESC';
        
        // Build order by clause
        $orderBy = match($sortBy) {
            'profit_loss' => 'SUM(value_min_unit)',
            'last_sale_date' => 'STR_TO_DATE(MAX(CASE WHEN quantity < 0 THEN date END), "%d-%m-%Y")',
            'first_purchase_date' => 'STR_TO_DATE(MIN(CASE WHEN quantity > 0 THEN date END), "%d-%m-%Y")',
            default => 'STR_TO_DATE(MAX(CASE WHEN quantity < 0 THEN date END), "%d-%m-%Y")'
        };
        
        // Get aggregated trades grouped by ISIN where total quantity = 0 (completely closed)
        $trades = DegiroTransaction::where('user_id', $userId)
            ->selectRaw('
                isin,
                SUM(quantity) as total_quantity,
                SUM(value_min_unit) as total_profit_loss,
                MAX(CASE WHEN quantity < 0 THEN date END) as last_sale_date,
                MIN(CASE WHEN quantity > 0 THEN date END) as first_purchase_date
            ')
            ->groupBy('isin')
            ->havingRaw('SUM(quantity) = 0')
            ->orderByRaw("{$orderBy} {$sortOrder}")
            ->paginate($perPage);

        // Get all ISINs from the trades
        $isins = $trades->getCollection()->pluck('isin')->toArray();

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

        // Get currency for each ISIN (use the most recent transaction's value_currency)
        $currencies = collect();
        if (!empty($isins)) {
            $currencies = DegiroTransaction::where('user_id', $userId)
                ->whereIn('isin', $isins)
                ->select('isin', 'value_currency', 'id')
                ->orderBy('id', 'desc')
                ->get()
                ->groupBy('isin')
                ->map(function ($transactions) {
                    // Get the first (latest) transaction's currency for each ISIN
                    return $transactions->first()->value_currency;
                });
        }

        // Transform the trades to add product name, ensure profit_loss is an integer, and add currency
        $trades->getCollection()->transform(function ($trade) use ($latestProducts, $currencies) {
            $trade->product = $latestProducts->get($trade->isin) ?? '';
            $trade->profit_loss = (int) $trade->total_profit_loss;
            $trade->currency = $currencies->get($trade->isin) ?? 'EUR';
            unset($trade->total_quantity, $trade->total_profit_loss);
            return $trade;
        });

        return $trades;
    }

    /**
     * Get trades summary for a user.
     * Returns sum of positive trades, sum of negative trades, and the difference.
     *
     * @param int $userId
     * @return array
     */
    public function getTradesSummary(int $userId): array
    {
        // Get aggregated trades grouped by ISIN where total quantity = 0 (completely closed)
        $trades = DegiroTransaction::where('user_id', $userId)
            ->selectRaw('
                isin,
                SUM(quantity) as total_quantity,
                SUM(value_min_unit) as total_profit_loss
            ')
            ->groupBy('isin')
            ->havingRaw('SUM(quantity) = 0')
            ->get();

        // Get currency for each ISIN (use the most recent transaction's value_currency)
        $isins = $trades->pluck('isin')->toArray();
        $currencies = collect();
        if (!empty($isins)) {
            $currencies = DegiroTransaction::where('user_id', $userId)
                ->whereIn('isin', $isins)
                ->select('isin', 'value_currency', 'id')
                ->orderBy('id', 'desc')
                ->get()
                ->groupBy('isin')
                ->map(function ($transactions) {
                    return $transactions->first()->value_currency;
                });
        }

        // Calculate sums
        $positiveSum = 0;
        $negativeSum = 0;
        $currency = 'EUR'; // Default currency

        foreach ($trades as $trade) {
            $profitLoss = (int) $trade->total_profit_loss;
            $tradeCurrency = $currencies->get($trade->isin) ?? 'EUR';
            
            // Use the first currency found as the main currency
            if ($currency === 'EUR' && $tradeCurrency !== 'EUR') {
                $currency = $tradeCurrency;
            }
            
            if ($profitLoss > 0) {
                $positiveSum += $profitLoss;
            } else if ($profitLoss < 0) {
                $negativeSum += abs($profitLoss);
            }
        }

        $difference = $positiveSum - $negativeSum;

        return [
            'positive_sum' => $positiveSum,
            'negative_sum' => $negativeSum,
            'difference' => $difference,
            'currency' => $currency,
        ];
    }
}

