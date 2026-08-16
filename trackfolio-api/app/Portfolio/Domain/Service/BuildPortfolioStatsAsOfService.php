<?php

namespace App\Portfolio\Domain\Service;

use App\AccountStatement\Infrastructure\Repository\AccountStatementRepository;
use App\DegiroTransaction\Domain\Entity\DegiroTransaction;
use App\DegiroTransaction\Infrastructure\Repository\DegiroTransactionRepository;
use App\ExchangeRate\Domain\Service\ResolveUsdToEurRateService;
use App\Isin\Domain\Entity\Isin;
use App\Isin\Domain\Entity\IsinQuote;
use App\Isin\Domain\Service\ResolveIsinClosingPriceService;

class BuildPortfolioStatsAsOfService
{
    private const CONCENTRATION_TOP_N = 10;

    public function __construct(
        private DegiroTransactionRepository $repository,
        private ResolveIsinClosingPriceService $resolveIsinClosingPriceService,
        private ResolveUsdToEurRateService $resolveUsdToEurRateService,
        private AccountStatementRepository $accountStatementRepository,
        private SnapshotCalculationProcessLogService $snapshotCalculationProcessLogService,
    ) {}

    /**
     * Full as-of portfolio view (all holdings). Caller paginates/sorts.
     *
     * @return array<string, mixed>
     */
    public function build(int $userId, string $asOfDate, ?int $processId = null): array
    {
        $usdToEurResolved = $this->resolveUsdToEurRateService->resolveOnOrBefore($asOfDate);
        $usdToEur = $usdToEurResolved !== null ? $usdToEurResolved['rate'] : null;
        $usdToEurRateDate = $usdToEurResolved !== null ? $usdToEurResolved['rate_date'] : null;

        $allHoldings = $this->repository->getAllPortfolioHoldings($userId, $asOfDate);

        $enriched = [];
        foreach ($allHoldings as $holding) {
            $enriched[] = $this->enrichHolding(
                userId: $userId,
                isin: (string) $holding->isin,
                quantity: (float) $holding->quantity,
                product: (string) $holding->product,
                usdToEur: $usdToEur,
                asOfDate: $asOfDate,
                processId: $processId,
            );
        }

        if ($processId !== null) {
            $this->logHoldingsSnapshot($processId, $asOfDate, $enriched);
        }

        $totalMarketValue = 0;
        $totalMarketValueEur = 0;
        $totalDayChangeEur = 0;
        $hasDayChange = false;
        $totalGainLossEur = 0;
        $hasTotalGainLoss = false;
        foreach ($enriched as $row) {
            $totalMarketValue += (int) ($row['market_value_min_unit'] ?? 0);
            $totalMarketValueEur += (int) ($row['market_value_eur_min_unit'] ?? 0);
            if (($row['day_change_eur_min_unit'] ?? null) !== null) {
                $totalDayChangeEur += (int) $row['day_change_eur_min_unit'];
                $hasDayChange = true;
            }
            if (($row['total_gain_loss_eur_min_unit'] ?? null) !== null) {
                $totalGainLossEur += (int) $row['total_gain_loss_eur_min_unit'];
                $hasTotalGainLoss = true;
            }
        }

        foreach ($enriched as $i => $row) {
            $marketValue = (int) ($row['market_value_min_unit'] ?? 0);
            $enriched[$i]['weight_percent'] = $totalMarketValue > 0 && $marketValue > 0
                ? round(($marketValue / $totalMarketValue) * 100, 1)
                : null;
        }

        $cashEurMinUnit = $this->accountStatementRepository->cashEurMinUnitOnOrBefore($userId, $asOfDate);
        $portfolioEurForSnapshot = $totalMarketValueEur;
        $balanceEurForSnapshot = $portfolioEurForSnapshot + $cashEurMinUnit;
        $netMarketValueEur = $totalMarketValueEur > 0 || $cashEurMinUnit !== 0
            ? $balanceEurForSnapshot
            : null;

        $realizedEurMinUnit = $this->realizedClosedTradesEurMinUnit($userId, $usdToEur, $asOfDate);
        $totalGainLossEur += $realizedEurMinUnit['amount'];
        $hasTotalGainLoss = $hasTotalGainLoss || $realizedEurMinUnit['has_activity'];

        $dayChangeForResponse = $hasDayChange ? $totalDayChangeEur : null;
        $totalPlForResponse = $hasTotalGainLoss ? $totalGainLossEur : null;

        return [
            'as_of_date' => $asOfDate,
            'closes_complete' => $this->areClosesComplete($enriched, $asOfDate),
            'holdings' => $enriched,
            'concentration' => $this->buildConcentrationFromEnriched($enriched),
            'performance_temperature' => $this->buildPerformanceTemperatureFromEnriched($enriched, $asOfDate),
            'usd_to_eur_rate' => $usdToEur,
            'usd_to_eur_rate_date' => $usdToEurRateDate,
            'total_market_value_min_unit' => $totalMarketValue > 0 ? $totalMarketValue : null,
            'total_market_value_eur_min_unit' => $totalMarketValueEur > 0 ? $totalMarketValueEur : null,
            'cash_eur_min_unit' => $cashEurMinUnit,
            'net_market_value_eur_min_unit' => $netMarketValueEur,
            'day_change_eur_min_unit' => $dayChangeForResponse,
            'total_gain_loss_eur_min_unit' => $totalPlForResponse,
            'metrics' => [
                'balance_eur_min_unit' => (int) $balanceEurForSnapshot,
                'portfolio_eur_min_unit' => (int) $portfolioEurForSnapshot,
                'cash_eur_min_unit' => $cashEurMinUnit,
                'day_change_eur_min_unit' => $dayChangeForResponse,
                'total_gain_loss_eur_min_unit' => $totalPlForResponse,
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $enriched
     */
    private function areClosesComplete(array $enriched, string $asOfDate): bool
    {
        foreach ($enriched as $row) {
            $isin = trim((string) ($row['isin'] ?? ''));
            if ($isin === '') {
                continue;
            }
            if ((float) ($row['quantity'] ?? 0) == 0.0) {
                continue;
            }
            if (($row['closing_date'] ?? null) !== $asOfDate) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<array<string, mixed>> $enrichedHoldings
     */
    private function logHoldingsSnapshot(int $processId, string $asOfDate, array $enrichedHoldings): void
    {
        $items = [];
        foreach ($enrichedHoldings as $holding) {
            $isin = strtoupper(trim((string) ($holding['isin'] ?? '')));
            $symbol = $holding['ticker_symbol'] ?? null;

            if (($symbol === null || $symbol === '') && $isin !== '') {
                $local = Isin::query()->where('isin', $isin)->first(['display_symbol', 'symbol']);
                $symbol = ($local?->display_symbol !== null && $local->display_symbol !== '')
                    ? $local->display_symbol
                    : $local?->symbol;
            }

            $items[] = [
                'symbol' => $symbol !== null && $symbol !== '' ? (string) $symbol : null,
                'isin' => $isin,
                'shares' => (string) ($holding['quantity'] ?? '0'),
            ];
        }

        $json = json_encode(['items' => $items], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->snapshotCalculationProcessLogService->log(
            $processId,
            "Cartera en fecha {$asOfDate} en formato JSON: {$json}",
            dateProcessed: $asOfDate,
        );
    }

    /**
     * @return array{amount: int, has_activity: bool}
     */
    private function realizedClosedTradesEurMinUnit(int $userId, ?float $usdToEur, string $asOfDate): array
    {
        $summary = $this->repository->getTradesSummary($userId, $asOfDate);
        $amount = (int) ($summary['difference'] ?? 0);
        $currency = strtoupper((string) ($summary['currency'] ?? 'EUR'));
        $hasActivity = $amount !== 0
            || (int) ($summary['positive_sum'] ?? 0) !== 0
            || (int) ($summary['negative_sum'] ?? 0) !== 0;

        if ($currency === 'USD' && $usdToEur !== null && $usdToEur > 0) {
            $amount = (int) round($amount * $usdToEur);
        }

        return [
            'amount' => $amount,
            'has_activity' => $hasActivity,
        ];
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
        string $asOfDate,
        ?int $processId = null,
    ): array {
        $hadUsableIsinRow = $isin !== '' && $this->hasUsableIsinRow($isin);

        if ($processId !== null && $isin !== '') {
            $this->logIsinResolution($processId, $isin, $asOfDate, $hadUsableIsinRow);
        }

        $closing = $isin !== ''
            ? $this->resolveIsinClosingPriceService->resolveForD1($isin, $asOfDate)
            : null;
        $providerRequestId = $this->resolveIsinClosingPriceService->lastProviderRequestId();

        if ($processId !== null && $isin !== '' && !$hadUsableIsinRow) {
            $this->logIsinIntroduced($processId, $isin, $asOfDate);
        }

        $tickerSymbol = $this->resolveTickerSymbol($isin, $closing);

        if ($processId !== null && $isin !== '' && $tickerSymbol !== null && $closing !== null) {
            $closeDate = $closing->closing_date?->format('Y-m-d') ?? 'null';
            $closePrice = $closing->close_price_min_unit !== null
                ? number_format(((int) $closing->close_price_min_unit) / 100, 2, '.', '')
                : 'null';
            $this->snapshotCalculationProcessLogService->log(
                $processId,
                "ISIN {$isin} resolved closing price via provider={$closing->provider} symbol={$tickerSymbol} close_date={$closeDate} close_price={$closePrice}",
                dateProcessed: $asOfDate,
                isin: $isin,
                symbol: $tickerSymbol,
                providerRequestId: $providerRequestId,
            );
        } elseif ($processId !== null && $isin !== '' && $closing === null) {
            $this->snapshotCalculationProcessLogService->log(
                $processId,
                "ISIN {$isin}: no se pudo resolver precio de cierre",
                dateProcessed: $asOfDate,
                isin: $isin,
                providerRequestId: $providerRequestId,
            );
        }

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
        $dayChangeEurMinUnit = null;
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
                if ($usdToEur !== null && $usdToEur > 0) {
                    $dayChangeEurMinUnit = (int) round($dayChangeMinUnit * $usdToEur);
                }
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
            $costMinUnit = $this->repository->getOpenPositionCostMinUnit($userId, $isin, $asOfDate);
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
            'day_change_eur_min_unit' => $dayChangeEurMinUnit,
            'day_change_percent' => $dayChangePercent,
            'total_gain_loss_min_unit' => $totalGainLossMinUnit,
            'total_gain_loss_eur_min_unit' => $totalGainLossEurMinUnit,
            'total_gain_loss_percent' => $totalGainLossPercent,
            'market_value_min_unit' => $marketValueMinUnit,
            'market_value_eur_min_unit' => $marketValueEurMinUnit,
            'weight_percent' => null,
        ];
    }

    private function hasUsableIsinRow(string $isin): bool
    {
        $local = Isin::query()->where('isin', $isin)->first();

        return $local !== null
            && $local->symbol !== ''
            && str_contains((string) $local->symbol, '.');
    }

    private function logIsinResolution(int $processId, string $isin, string $asOfDate, bool $hadUsableIsinRow): void
    {
        if (!$hadUsableIsinRow) {
            $local = Isin::query()->where('isin', $isin)->first();
            $this->snapshotCalculationProcessLogService->log(
                $processId,
                "ISIN {$isin} does not exist in isins table (or lacks SYMBOL.EXCHANGE), get from provider",
                dateProcessed: $asOfDate,
                isin: $isin,
                symbol: $local?->symbol,
            );

            return;
        }

        $local = Isin::query()->where('isin', $isin)->first();
        $this->snapshotCalculationProcessLogService->log(
            $processId,
            "ISIN {$isin} is {$local?->symbol}",
            dateProcessed: $asOfDate,
            isin: $isin,
            symbol: $local?->symbol,
        );
    }

    private function logIsinIntroduced(int $processId, string $isin, string $asOfDate): void
    {
        $row = Isin::query()->where('isin', $isin)->first();
        if ($row === null || $row->symbol === '') {
            return;
        }

        $this->snapshotCalculationProcessLogService->log(
            $processId,
            'ISIN ' . $isin . ' introducido. Symbol: "' . $row->symbol
                . '", Description: "' . $row->description
                . '", type: "' . $row->type
                . '", display_symbol: "' . $row->display_symbol . '"',
            dateProcessed: $asOfDate,
            isin: $isin,
            symbol: $row->symbol,
        );
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
     * @param list<array<string, mixed>> $enriched
     * @return list<array{isin: string, ticker_symbol: string, product: string, weight_percent: float, day_change_percent: float|null}>
     */
    private function buildPerformanceTemperatureFromEnriched(array $enriched, string $asOfDate): array
    {
        $rows = [];
        foreach ($enriched as $row) {
            $weight = $row['weight_percent'] ?? null;
            if ($weight === null || (float) $weight <= 0) {
                continue;
            }

            $dayChangePercent = $row['day_change_percent'] ?? null;
            $closingDate = $row['closing_date'] ?? null;
            if ($closingDate !== $asOfDate) {
                $dayChangePercent = null;
            }

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
        if ($isin !== '') {
            $local = Isin::query()->where('isin', $isin)->first(['display_symbol', 'symbol']);
            $display = $local?->display_symbol;
            if ($display !== null && $display !== '') {
                return (string) $display;
            }
            $symbol = $local?->symbol;
            if ($symbol !== null && $symbol !== '') {
                return (string) $symbol;
            }
        }

        $tickerSymbol = $closing?->ticker_symbol;

        return $tickerSymbol !== null && $tickerSymbol !== '' ? (string) $tickerSymbol : null;
    }
}
