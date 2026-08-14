<?php

namespace App\DegiroTransaction\Infrastructure\Controllers;

use App\DegiroTransaction\Domain\Entity\DegiroTransaction;
use App\DegiroTransaction\Infrastructure\Repository\DegiroTransactionRepository;
use App\ExchangeRate\Domain\Service\ResolveUsdToEurRateService;
use App\Isin\Domain\Entity\Isin;
use App\Isin\Domain\Entity\IsinQuote;
use App\Isin\Domain\Service\ResolveIsinClosingPriceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PortfolioStatsController
{
    private const CONCENTRATION_TOP_N = 7;

    private const SORTABLE = [
        'symbols' => 'ticker_symbol',
        'price' => 'closing_price_min_unit',
        'quantity' => 'quantity',
        'total' => 'market_value_min_unit',
        'total_eur' => 'market_value_eur_min_unit',
        'change' => 'day_change_min_unit',
        'total_gain_loss' => 'total_gain_loss_min_unit',
        'total_gain_loss_eur' => 'total_gain_loss_eur_min_unit',
        'weight' => 'weight_percent',
    ];

    public function __construct(
        private DegiroTransactionRepository $repository,
        private ResolveIsinClosingPriceService $resolveIsinClosingPriceService,
        private ResolveUsdToEurRateService $resolveUsdToEurRateService,
    ) {}

    /**
     * Get paginated portfolio holdings for the authenticated user,
     * enriched with D-1 quote, day change and unrealized gain/loss.
     * Sorting applies to the full portfolio, then the page is sliced.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $perPage = max(1, min(100, (int) $request->get('per_page', 10)));
        $page = max(1, (int) $request->get('page', 1));

        $sortByParam = (string) $request->get('sort_by', 'weight');
        $sortBy = self::SORTABLE[$sortByParam] ?? 'weight_percent';
        $sortOrder = strtolower((string) $request->get('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';

        $allHoldings = $this->repository->getAllPortfolioHoldings($user->id);

        $usdToEur = $this->resolveUsdToEurRateService->forToday();

        $enriched = [];
        foreach ($allHoldings as $holding) {
            $enriched[] = $this->enrichHolding(
                userId: $user->id,
                isin: (string) $holding->isin,
                quantity: (float) $holding->quantity,
                product: (string) $holding->product,
                usdToEur: $usdToEur,
            );
        }

        $totalMarketValue = 0;
        foreach ($enriched as $row) {
            $totalMarketValue += (int) ($row['market_value_min_unit'] ?? 0);
        }

        foreach ($enriched as $i => $row) {
            $marketValue = (int) ($row['market_value_min_unit'] ?? 0);
            $enriched[$i]['weight_percent'] = $totalMarketValue > 0 && $marketValue > 0
                ? round(($marketValue / $totalMarketValue) * 100, 1)
                : null;
        }

        $enriched = $this->sortHoldings($enriched, $sortBy, $sortOrder);

        $total = count($enriched);
        $lastPage = max(1, (int) ceil($total / $perPage));
        if ($page > $lastPage) {
            $page = $lastPage;
        }

        $pageItems = array_slice($enriched, ($page - 1) * $perPage, $perPage);

        return response()->json([
            'data' => array_values($pageItems),
            'concentration' => $this->buildConcentrationFromEnriched($enriched),
            'performance_temperature' => $this->buildPerformanceTemperatureFromEnriched($enriched),
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => $lastPage,
            'sort_by' => $sortByParam,
            'sort_order' => $sortOrder,
            'usd_to_eur_rate' => $usdToEur,
        ]);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function sortHoldings(array $rows, string $sortBy, string $sortOrder): array
    {
        $dir = $sortOrder === 'asc' ? 1 : -1;

        usort($rows, function (array $a, array $b) use ($sortBy, $dir) {
            if ($sortBy === 'ticker_symbol') {
                $va = strtoupper((string) ($a['ticker_symbol'] ?? $a['product'] ?? ''));
                $vb = strtoupper((string) ($b['ticker_symbol'] ?? $b['product'] ?? ''));
                $cmp = $va <=> $vb;
                if ($cmp === 0) {
                    $cmp = strcasecmp((string) ($a['product'] ?? ''), (string) ($b['product'] ?? ''));
                }

                return $dir * $cmp;
            }

            $va = $a[$sortBy] ?? null;
            $vb = $b[$sortBy] ?? null;

            if ($va === null && $vb === null) {
                return 0;
            }
            if ($va === null) {
                return 1;
            }
            if ($vb === null) {
                return -1;
            }

            return $dir * ($va <=> $vb);
        });

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function enrichHolding(
        int $userId,
        string $isin,
        float $quantity,
        string $product,
        ?float $usdToEur,
    ): array {
        $closing = $isin !== ''
            ? $this->resolveIsinClosingPriceService->resolveForD1($isin)
            : null;

        $tickerSymbol = $this->resolveTickerSymbol($isin, $closing);

        $currency = $closing?->currency;
        if ($currency === null && $isin !== '') {
            $currency = DegiroTransaction::query()
                ->where('user_id', $userId)
                ->where('isin', $isin)
                ->orderByDesc('id')
                ->value('price_currency');
        }

        $closeCents = $closing?->close_price_min_unit;
        $closingDate = $closing?->closing_date?->format('Y-m-d');

        $dayChangeMinUnit = null;
        $dayChangePercent = null;
        if ($closing !== null && $closeCents !== null && $closingDate !== null) {
            $previous = $this->resolveIsinClosingPriceService->findPreviousQuote(
                $isin,
                $closingDate,
                $closing->provider,
            );
            if ($previous?->close_price_min_unit !== null && $previous->close_price_min_unit > 0) {
                $perShareChange = $closeCents - (int) $previous->close_price_min_unit;
                $dayChangeMinUnit = (int) round($perShareChange * $quantity);
                $dayChangePercent = ($perShareChange / (int) $previous->close_price_min_unit) * 100;
            }
        }

        $totalGainLossMinUnit = null;
        $totalGainLossEurMinUnit = null;
        $totalGainLossPercent = null;
        $marketValueMinUnit = null;
        $marketValueEurMinUnit = null;
        if ($closeCents !== null) {
            $marketValueMinUnit = (int) round($closeCents * $quantity);
            if ($usdToEur !== null && $usdToEur > 0) {
                $marketValueEurMinUnit = (int) round($marketValueMinUnit * $usdToEur);
            }
        }

        if ($closeCents !== null && $isin !== '') {
            $costMinUnit = $this->repository->getOpenPositionCostMinUnit($userId, $isin);
            if ($costMinUnit !== null && $costMinUnit > 0 && $marketValueMinUnit !== null) {
                $totalGainLossMinUnit = $marketValueMinUnit - $costMinUnit;
                $totalGainLossPercent = ($totalGainLossMinUnit / $costMinUnit) * 100;
                if ($usdToEur !== null && $usdToEur > 0) {
                    $totalGainLossEurMinUnit = (int) round($totalGainLossMinUnit * $usdToEur);
                }
            }
        }

        return [
            'isin' => $isin,
            'product' => $product,
            'quantity' => $quantity,
            'ticker_symbol' => $tickerSymbol,
            'closing_price_min_unit' => $closeCents,
            'closing_price_currency' => $currency,
            'closing_date' => $closingDate,
            'day_change_min_unit' => $dayChangeMinUnit,
            'day_change_percent' => $dayChangePercent,
            'total_gain_loss_min_unit' => $totalGainLossMinUnit,
            'total_gain_loss_eur_min_unit' => $totalGainLossEurMinUnit,
            'total_gain_loss_percent' => $totalGainLossPercent,
            'market_value_min_unit' => $marketValueMinUnit,
            'market_value_eur_min_unit' => $marketValueEurMinUnit,
            'weight_percent' => null,
        ];
    }

    /**
     * @param list<array<string, mixed>> $enriched
     * @return list<array{isin: string, ticker_symbol: string, weight_percent: float}>
     */
    private function buildConcentrationFromEnriched(array $enriched): array
    {
        $rows = [];
        foreach ($enriched as $row) {
            $marketValue = (int) ($row['market_value_min_unit'] ?? 0);
            $weight = $row['weight_percent'] ?? null;
            if ($marketValue <= 0 || $weight === null) {
                continue;
            }

            $rows[] = [
                'isin' => (string) $row['isin'],
                'ticker_symbol' => (string) ($row['ticker_symbol'] ?? $row['isin']),
                'market_value_min_unit' => $marketValue,
                'weight_percent' => (float) $weight,
            ];
        }

        usort($rows, fn (array $a, array $b) => $b['market_value_min_unit'] <=> $a['market_value_min_unit']);
        $top = array_slice($rows, 0, self::CONCENTRATION_TOP_N);

        return array_map(static fn (array $row) => [
            'isin' => $row['isin'],
            'ticker_symbol' => $row['ticker_symbol'],
            'weight_percent' => $row['weight_percent'],
        ], $top);
    }

    /**
     * Full-portfolio tiles for the performance temperature treemap.
     *
     * @param list<array<string, mixed>> $enriched
     * @return list<array{isin: string, ticker_symbol: string, product: string, weight_percent: float, day_change_percent: float|null}>
     */
    private function buildPerformanceTemperatureFromEnriched(array $enriched): array
    {
        $rows = [];
        foreach ($enriched as $row) {
            $weight = $row['weight_percent'] ?? null;
            if ($weight === null || (float) $weight <= 0) {
                continue;
            }

            $dayChangePercent = $row['day_change_percent'] ?? null;

            $rows[] = [
                'isin' => (string) $row['isin'],
                'ticker_symbol' => (string) ($row['ticker_symbol'] ?? $row['isin']),
                'product' => (string) ($row['product'] ?? ''),
                'weight_percent' => (float) $weight,
                'day_change_percent' => $dayChangePercent !== null ? (float) $dayChangePercent : null,
            ];
        }

        usort($rows, fn (array $a, array $b) => $b['weight_percent'] <=> $a['weight_percent']);

        return $rows;
    }

    private function resolveTickerSymbol(string $isin, ?IsinQuote $closing): ?string
    {
        $tickerSymbol = $closing?->ticker_symbol;
        if (($tickerSymbol === null || $tickerSymbol === '') && $isin !== '') {
            $tickerSymbol = Isin::query()->where('isin', $isin)->value('symbol');
        }

        return $tickerSymbol !== null && $tickerSymbol !== '' ? (string) $tickerSymbol : null;
    }
}
