<?php

namespace App\DegiroTransaction\Infrastructure\Repository;

use App\DegiroTransaction\Domain\Entity\DegiroTransaction;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DegiroTransactionRepository
{
    /**
     * Create a new Degiro transaction.
     */
    public function create(array $data): DegiroTransaction
    {
        return DegiroTransaction::create($data);
    }

    /**
     * Create multiple Degiro transactions.
     * All transactions should have been pre-validated for duplicates.
     *
     * @param  array  $transactions  Array of transaction data arrays
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
     */
    public function findByUserId(int $userId): Collection
    {
        return DegiroTransaction::where('user_id', $userId)->get();
    }

    /**
     * Count transactions for a user.
     */
    public function countByUserId(int $userId): int
    {
        return DegiroTransaction::where('user_id', $userId)->count();
    }

    /**
     * Delete all Degiro transactions for a user.
     *
     * @return int Number of rows deleted
     */
    public function deleteAllForUser(int $userId): int
    {
        return DegiroTransaction::where('user_id', $userId)->delete();
    }

    /**
     * Get paginated transactions for a user, ordered by `date` (stored as DD-MM-YYYY; parsed for real chronology).
     *
     * @param  string  $sortOrder  "desc" = newest date first, "asc" = oldest date first
     * @param  string|null  $productLike  when non-empty, filter with SQL LIKE %...% (wildcards in $productLike are escaped)
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function findPaginatedByUserId(int $userId, int $perPage = 10, string $sortOrder = 'desc', ?string $productLike = null)
    {
        $direction = strtolower($sortOrder) === 'asc' ? 'ASC' : 'DESC';
        $idDirection = strtolower($sortOrder) === 'asc' ? 'asc' : 'desc';

        $query = DegiroTransaction::where('user_id', $userId);

        if ($productLike !== null && $productLike !== '') {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $productLike);
            $query->where('product', 'LIKE', '%'.$escaped.'%');
        }

        return $query
            ->orderByRaw(
                "STR_TO_DATE(`degiro_transactions`.`date`, '%d-%m-%Y') {$direction}"
            )
            ->orderBy('degiro_transactions.id', $idDirection)
            ->paginate($perPage);
    }

    /**
     * Get existing content hashes for a user.
     *
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
        if (! empty($isins)) {
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
     * All open holdings (non-paginated) as isin + quantity + product.
     *
     * @return \Illuminate\Support\Collection<int, object{isin: string, quantity: float, product: string}>
     */
    public function getAllPortfolioHoldings(int $userId, ?string $asOfDate = null)
    {
        $holdingsQuery = DegiroTransaction::where('user_id', $userId);
        $this->constrainToAsOfDate($holdingsQuery, $asOfDate);
        $holdings = $holdingsQuery
            ->selectRaw('isin, SUM(quantity) as quantity')
            ->groupBy('isin')
            ->havingRaw('SUM(quantity) != 0')
            ->get();

        $isins = $holdings->pluck('isin')->all();
        $latestProducts = collect();
        if ($isins !== []) {
            $productsQuery = DegiroTransaction::where('user_id', $userId)
                ->whereIn('isin', $isins);
            $this->constrainToAsOfDate($productsQuery, $asOfDate);
            $latestProducts = $productsQuery
                ->select('isin', 'product', 'id')
                ->orderByRaw("STR_TO_DATE(`date`, '%d-%m-%Y') desc")
                ->orderByDesc('id')
                ->get()
                ->groupBy('isin')
                ->map(fn ($transactions) => (string) ($transactions->first()->product ?? ''));
        }

        return $holdings->map(function ($row) use ($latestProducts) {
            return (object) [
                'isin' => (string) $row->isin,
                'quantity' => (float) $row->quantity,
                'product' => (string) ($latestProducts->get($row->isin) ?? ''),
            ];
        })->values();
    }

    /**
     * @deprecated Use getAllPortfolioHoldings()
     * @return \Illuminate\Support\Collection<int, object{isin: string, quantity: float}>
     */
    public function getAllPortfolioHoldingQuantities(int $userId)
    {
        return $this->getAllPortfolioHoldings($userId)->map(function ($row) {
            return (object) [
                'isin' => $row->isin,
                'quantity' => $row->quantity,
            ];
        });
    }

    /**
     * Remaining cost basis (cents) for an open ISIN position using average-cost on sells.
     * Price comes from price_ten_thousandths (converted to cents).
     */
    public function getOpenPositionCostMinUnit(int $userId, string $isin, ?string $asOfDate = null): ?int
    {
        $rows = DegiroTransaction::query()
            ->where('user_id', $userId)
            ->where('isin', $isin)
            ->get(['id', 'date', 'time', 'quantity', 'price_ten_thousandths']);

        if ($asOfDate !== null) {
            $rows = $rows->filter(fn (DegiroTransaction $t) => $this->transactionDateYmd($t) <= $asOfDate);
        }

        if ($rows->isEmpty()) {
            return null;
        }

        $ordered = $rows
            ->sortBy(fn (DegiroTransaction $t) => $this->chronologicalSortKey($t))
            ->values();

        $qty = 0.0;
        $costCents = 0.0;

        foreach ($ordered as $tx) {
            $q = (float) $tx->quantity;
            $priceCents = ((int) $tx->price_ten_thousandths) / 100.0;

            if ($q > 0) {
                $costCents += $priceCents * $q;
                $qty += $q;
                continue;
            }

            if ($q < 0 && $qty > 0.0000001) {
                $avg = $costCents / $qty;
                $sellQty = min(abs($q), $qty);
                $costCents -= $avg * $sellQty;
                $qty -= $sellQty;
                if ($qty < 0.0000001) {
                    $qty = 0.0;
                    $costCents = 0.0;
                }
            }
        }

        if ($qty <= 0.0000001) {
            return null;
        }

        return (int) round($costCents);
    }

    /**
     * Get closed trades for a user, grouped by ISIN.
     * Returns products that have been completely closed (total quantity = 0),
     * with profit/loss, first purchase date, and last sale date.
     *
     * @param  string|null  $productLike  when non-empty, only ISINs with at least one matching transaction (LIKE %...%)
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getClosedTrades(int $userId, int $perPage = 10, string $sortBy = 'last_sale_date', string $sortOrder = 'desc', ?string $productLike = null)
    {
        // Validate sort order
        $sortOrder = strtolower($sortOrder) === 'asc' ? 'ASC' : 'DESC';

        // Build order by clause
        $orderBy = match ($sortBy) {
            'profit_loss' => 'SUM(value_min_unit)',
            'last_sale_date' => 'STR_TO_DATE(MAX(CASE WHEN quantity < 0 THEN date END), "%d-%m-%Y")',
            'first_purchase_date' => 'STR_TO_DATE(MIN(CASE WHEN quantity > 0 THEN date END), "%d-%m-%Y")',
            default => 'STR_TO_DATE(MAX(CASE WHEN quantity < 0 THEN date END), "%d-%m-%Y")'
        };

        // Get aggregated trades grouped by ISIN where total quantity = 0 (completely closed)
        $trades = DegiroTransaction::where('user_id', $userId)
            ->when($productLike !== null && $productLike !== '', function ($query) use ($userId, $productLike) {
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $productLike);
                $query->whereIn('isin', function ($sub) use ($userId, $escaped) {
                    $sub->select('isin')
                        ->from('degiro_transactions')
                        ->where('user_id', $userId)
                        ->where('product', 'LIKE', '%'.$escaped.'%');
                });
            })
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
        if (! empty($isins)) {
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
        if (! empty($isins)) {
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
     */
    public function getTradesSummary(int $userId, ?string $asOfDate = null): array
    {
        $tradesQuery = DegiroTransaction::where('user_id', $userId);
        $this->constrainToAsOfDate($tradesQuery, $asOfDate);
        $trades = $tradesQuery
            ->selectRaw('
                isin,
                SUM(quantity) as total_quantity,
                SUM(value_min_unit) as total_profit_loss
            ')
            ->groupBy('isin')
            ->havingRaw('SUM(quantity) = 0')
            ->get();

        $isins = $trades->pluck('isin')->toArray();
        $currencies = collect();
        if (! empty($isins)) {
            $currencyQuery = DegiroTransaction::where('user_id', $userId)
                ->whereIn('isin', $isins);
            $this->constrainToAsOfDate($currencyQuery, $asOfDate);
            $currencies = $currencyQuery
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
            } elseif ($profitLoss < 0) {
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

    /**
     * All transactions for the user in chronological order (FIFO processing).
     * Sorting is done in PHP so it works on SQLite (tests) and MySQL (production).
     *
     * @return Collection<int, DegiroTransaction>
     */
    public function findChronologicalForUser(int $userId): Collection
    {
        $rows = DegiroTransaction::query()
            ->where('user_id', $userId)
            ->get([
                'id',
                'date',
                'time',
                'isin',
                'product',
                'quantity',
                'price_ten_thousandths',
                'price_currency',
                'value_min_unit',
                'transaction_and_or_third',
                'autofx_fee',
            ]);

        return $rows
            ->sortBy(fn (DegiroTransaction $t) => $this->chronologicalSortKey($t))
            ->values();
    }

    /**
     * Calendar year of the oldest transaction for the user, or null if none.
     */
    public function minTransactionYear(int $userId): ?int
    {
        $dates = DegiroTransaction::query()
            ->where('user_id', $userId)
            ->pluck('date');

        if ($dates->isEmpty()) {
            return null;
        }

        $minYear = null;
        foreach ($dates as $date) {
            try {
                $y = (int) Carbon::createFromFormat('d-m-Y', (string) $date)->year;
            } catch (\Throwable) {
                continue;
            }
            $minYear = $minYear === null ? $y : min($minYear, $y);
        }

        return $minYear;
    }

    /**
     * @return array{0: int, 1: int} Unix timestamp (best effort), then id
     */
    /**
     * @param \Illuminate\Database\Eloquent\Builder<DegiroTransaction> $query
     */
    private function constrainToAsOfDate($query, ?string $asOfDate): void
    {
        if ($asOfDate === null || $asOfDate === '') {
            return;
        }

        $query->whereRaw("STR_TO_DATE(`date`, '%d-%m-%Y') <= ?", [$asOfDate]);
    }

    private function transactionDateYmd(DegiroTransaction $t): string
    {
        try {
            return Carbon::createFromFormat('d-m-Y', (string) $t->date)->toDateString();
        } catch (\Throwable) {
            return '9999-12-31';
        }
    }

    /**
     * Calendar date (Y-m-d) of the user's earliest Degiro transaction, or null if none.
     */
    public function earliestTransactionDate(int $userId): ?string
    {
        $raw = DegiroTransaction::query()
            ->where('user_id', $userId)
            ->whereNotNull('date')
            ->where('date', '!=', '')
            ->selectRaw("MIN(STR_TO_DATE(`date`, '%d-%m-%Y')) as earliest")
            ->value('earliest');

        if ($raw === null || $raw === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $raw)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Calendar year of the user's earliest Degiro transaction, or null if none.
     */
    public function earliestTransactionYear(int $userId): ?int
    {
        $date = $this->earliestTransactionDate($userId);
        if ($date === null) {
            return null;
        }

        try {
            return Carbon::parse($date)->year;
        } catch (\Throwable) {
            return null;
        }
    }

    private function chronologicalSortKey(DegiroTransaction $t): array
    {
        $ts = 0;
        foreach (['d-m-Y H:i:s', 'd-m-Y H:i'] as $fmt) {
            try {
                $ts = Carbon::createFromFormat($fmt, $t->date.' '.$t->time)->getTimestamp();

                break;
            } catch (\Throwable) {
            }
        }

        return [$ts, (int) $t->id];
    }
}
