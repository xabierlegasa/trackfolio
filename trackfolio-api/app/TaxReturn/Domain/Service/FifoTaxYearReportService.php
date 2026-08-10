<?php

namespace App\TaxReturn\Domain\Service;

use App\DegiroTransaction\Domain\Entity\DegiroTransaction;
use App\DegiroTransaction\Domain\Service\ConvertCurrencyToMinUnitService;
use App\DegiroTransaction\Infrastructure\Repository\DegiroTransactionRepository;
use App\TaxReturn\Domain\Exception\InsufficientFifoInventoryException;
use Carbon\Carbon;

final class FifoTaxYearReportService
{
    public function __construct(
        private DegiroTransactionRepository $transactions,
        private ConvertCurrencyToMinUnitService $currency,
    ) {}

    /**
     * @return array{
     *     year: int,
     *     lines: list<array{
     *         isin: string,
     *         product: string,
     *         acquisition_value_cents: int,
     *         acquisition_commissions_cents: int,
     *         transmission_value_cents: int,
     *         transmission_commissions_cents: int,
     *         net_gain_cents: int
     *     }>,
     *     total_net_gain_cents: int
     * }
     */
    public function buildReport(int $userId, int $year): array
    {
        bcscale(14);

        $transactions = $this->transactions->findChronologicalForUser($userId);

        /** @var array<string, list<FifoLot>> $queues */
        $queues = [];

        /** @var array<string, array{acq_v: int, acq_f: int, sale_v: int, sale_f: int}> $agg */
        $agg = [];

        /** @var array<string, string> $latestProduct */
        $latestProduct = [];

        foreach ($transactions as $tx) {
            $isinKey = $this->isinKey($tx->isin);
            if ($isinKey === '') {
                continue;
            }

            $latestProduct[$isinKey] = (string) $tx->product;

            $qtyStr = $this->normalizeQuantity((string) $tx->quantity);

            if (bccomp($qtyStr, '0', 12) === 0) {
                continue;
            }

            if (bccomp($qtyStr, '0', 12) > 0) {
                $this->pushBuyLot($queues, $isinKey, $qtyStr, $tx);

                continue;
            }

            $sellQtyStr = bcmul($qtyStr, '-1', 12);
            $sellYear = $this->transactionYear($tx);

            $lineSaleValue = abs((int) $tx->value_min_unit);
            $lineSaleFee = $this->rowFeeCents($tx);

            $result = $this->fifoConsumeForSell($queues, $isinKey, $sellQtyStr, (string) $tx->date);
            $allocatedAcqV = $result['acq_v'];
            $allocatedAcqF = $result['acq_f'];

            if ($sellYear === $year) {
                if (! isset($agg[$isinKey])) {
                    $agg[$isinKey] = ['acq_v' => 0, 'acq_f' => 0, 'sale_v' => 0, 'sale_f' => 0];
                }
                $agg[$isinKey]['acq_v'] += $allocatedAcqV;
                $agg[$isinKey]['acq_f'] += $allocatedAcqF;
                $agg[$isinKey]['sale_v'] += $lineSaleValue;
                $agg[$isinKey]['sale_f'] += $lineSaleFee;
            }
        }

        $lines = [];
        $totalNet = 0;

        foreach ($agg as $isinKey => $row) {
            $net = ($row['sale_v'] - $row['sale_f']) - ($row['acq_v'] + $row['acq_f']);
            $totalNet += $net;
            $lines[] = [
                'isin' => $isinKey,
                'product' => $latestProduct[$isinKey] ?? '',
                'acquisition_value_cents' => $row['acq_v'],
                'acquisition_commissions_cents' => $row['acq_f'],
                'transmission_value_cents' => $row['sale_v'],
                'transmission_commissions_cents' => $row['sale_f'],
                'net_gain_cents' => $net,
            ];
        }

        usort($lines, fn (array $a, array $b) => strcmp($a['isin'], $b['isin']));

        return [
            'year' => $year,
            'lines' => $lines,
            'total_net_gain_cents' => $totalNet,
        ];
    }

    /**
     * FIFO audit trail for one ISIN and calendar year of disposals.
     *
     * @return array{
     *     year: int,
     *     isin: string,
     *     product: string,
     *     steps: list<array<string, mixed>>,
     *     summary: array{
     *         acquisition_value_cents: int,
     *         acquisition_commissions_cents: int,
     *         transmission_value_cents: int,
     *         transmission_commissions_cents: int,
     *         net_gain_cents: int
     *     }
     * }
     */
    public function buildIsinAudit(int $userId, int $year, string $targetIsin): array
    {
        bcscale(14);

        $targetIsin = $this->isinKey($targetIsin);
        if ($targetIsin === '') {
            throw new \InvalidArgumentException('Invalid ISIN.');
        }

        $transactions = $this->transactions->findChronologicalForUser($userId);

        /** @var array<string, list<FifoLot>> $queues */
        $queues = [];

        /** @var array<string, string> $latestProduct */
        $latestProduct = [];

        /** @var list<array<string, mixed>> $steps */
        $steps = [];

        foreach ($transactions as $tx) {
            $isinKey = $this->isinKey($tx->isin);
            if ($isinKey === '') {
                continue;
            }

            $latestProduct[$isinKey] = (string) $tx->product;

            $qtyStr = $this->normalizeQuantity((string) $tx->quantity);

            if (bccomp($qtyStr, '0', 12) === 0) {
                continue;
            }

            if (bccomp($qtyStr, '0', 12) > 0) {
                $this->pushBuyLot($queues, $isinKey, $qtyStr, $tx);

                continue;
            }

            $sellQtyStr = bcmul($qtyStr, '-1', 12);
            $sellYear = $this->transactionYear($tx);

            $lineSaleValue = abs((int) $tx->value_min_unit);
            $lineSaleFee = $this->rowFeeCents($tx);

            $result = $this->fifoConsumeForSell($queues, $isinKey, $sellQtyStr, (string) $tx->date);

            if ($sellYear === $year && $isinKey === $targetIsin) {
                $steps[] = [
                    'kind' => 'sell',
                    'transaction_id' => (int) $tx->id,
                    'date' => (string) $tx->date,
                    'time' => (string) $tx->time,
                    'isin' => $isinKey,
                    'product' => (string) $tx->product,
                    'quantity' => (float) $tx->quantity,
                    'price_ten_thousandths' => (int) $tx->price_ten_thousandths,
                    'price_currency' => (string) $tx->price_currency,
                    'price_label' => $this->formatPriceLabel((int) $tx->price_ten_thousandths, (string) $tx->price_currency),
                    'value_cents' => $lineSaleValue,
                    'fees_cents' => $lineSaleFee,
                ];

                foreach ($result['allocations'] as $a) {
                    $steps[] = [
                        'kind' => 'buy_fifo',
                        'source_transaction_id' => $a['source_transaction_id'],
                        'date' => $a['source_date'],
                        'time' => $a['source_time'],
                        'isin' => $isinKey,
                        'product' => $a['source_product'],
                        'price_ten_thousandths' => $a['source_price_ten_thousandths'],
                        'price_currency' => $a['source_price_currency'],
                        'price_label' => $this->formatPriceLabel($a['source_price_ten_thousandths'], $a['source_price_currency']),
                        'allocated_quantity' => $a['allocated_quantity'],
                        'allocated_value_cents' => $a['allocated_value_cents'],
                        'allocated_fee_cents' => $a['allocated_fee_cents'],
                    ];
                }
            }
        }

        $saleV = 0;
        $saleF = 0;
        $acqV = 0;
        $acqF = 0;

        foreach ($steps as $step) {
            if (($step['kind'] ?? '') === 'sell') {
                $saleV += (int) $step['value_cents'];
                $saleF += (int) $step['fees_cents'];
            }
            if (($step['kind'] ?? '') === 'buy_fifo') {
                $acqV += (int) $step['allocated_value_cents'];
                $acqF += (int) $step['allocated_fee_cents'];
            }
        }

        $net = ($saleV - $saleF) - ($acqV + $acqF);

        return [
            'year' => $year,
            'isin' => $targetIsin,
            'product' => $latestProduct[$targetIsin] ?? '',
            'steps' => $steps,
            'summary' => [
                'acquisition_value_cents' => $acqV,
                'acquisition_commissions_cents' => $acqF,
                'transmission_value_cents' => $saleV,
                'transmission_commissions_cents' => $saleF,
                'net_gain_cents' => $net,
            ],
        ];
    }

    private function isinKey(mixed $isin): string
    {
        return strtoupper(trim((string) $isin));
    }

    private function formatPriceLabel(int $tenThousandths, string $currency): string
    {
        $v = $tenThousandths / 10000.0;

        return number_format($v, 4, '.', ',').' '.$currency;
    }

    /**
     * @param  array<string, list<FifoLot>>  $queues
     * @return array{
     *     acq_v: int,
     *     acq_f: int,
     *     allocations: list<array{
     *         source_transaction_id: int,
     *         source_date: string,
     *         source_time: string,
     *         source_product: string,
     *         source_price_ten_thousandths: int,
     *         source_price_currency: string,
     *         allocated_quantity: string,
     *         allocated_value_cents: int,
     *         allocated_fee_cents: int
     *     }>
     * }
     */
    private function fifoConsumeForSell(array &$queues, string $isinKey, string $sellQtyStr, string $sellDateForError): array
    {
        $allocatedAcqV = 0;
        $allocatedAcqF = 0;
        $allocations = [];
        $remainingSell = $sellQtyStr;

        while (bccomp($remainingSell, '0', 12) > 0) {
            if (! isset($queues[$isinKey]) || $queues[$isinKey] === []) {
                throw new InsufficientFifoInventoryException(
                    $isinKey,
                    $sellDateForError,
                );
            }

            /** @var FifoLot $lot */
            $lot = $queues[$isinKey][0];
            if (bccomp($lot->remainingQty, '0', 12) <= 0) {
                array_shift($queues[$isinKey]);

                continue;
            }

            $take = bccomp($remainingSell, $lot->remainingQty, 12) <= 0
                ? $remainingSell
                : $lot->remainingQty;

            $sourceTransactionId = $lot->sourceTransactionId;
            $sourceDate = $lot->sourceDate;
            $sourceTime = $lot->sourceTime;
            $sourceProduct = $lot->sourceProduct;
            $sourcePriceTenThousandths = $lot->sourcePriceTenThousandths;
            $sourcePriceCurrency = $lot->sourcePriceCurrency;

            $part = $this->allocateFromLot($lot, $take);
            $allocatedAcqV += $part['value'];
            $allocatedAcqF += $part['fee'];

            $allocations[] = [
                'source_transaction_id' => $sourceTransactionId,
                'source_date' => $sourceDate,
                'source_time' => $sourceTime,
                'source_product' => $sourceProduct,
                'source_price_ten_thousandths' => $sourcePriceTenThousandths,
                'source_price_currency' => $sourcePriceCurrency,
                'allocated_quantity' => $take,
                'allocated_value_cents' => $part['value'],
                'allocated_fee_cents' => $part['fee'],
            ];

            $remainingSell = bcsub($remainingSell, $take, 12);

            if (bccomp($lot->remainingQty, '0', 12) <= 0) {
                array_shift($queues[$isinKey]);
            }
        }

        return [
            'acq_v' => $allocatedAcqV,
            'acq_f' => $allocatedAcqF,
            'allocations' => $allocations,
        ];
    }

    /**
     * @param  array<string, list<FifoLot>>  $queues
     */
    private function pushBuyLot(array &$queues, string $isinKey, string $qtyStr, DegiroTransaction $tx): void
    {
        $valueGross = abs((int) $tx->value_min_unit);
        $fee = $this->rowFeeCents($tx);

        if (! isset($queues[$isinKey])) {
            $queues[$isinKey] = [];
        }

        $queues[$isinKey][] = new FifoLot(
            remainingQty: $qtyStr,
            valueCentsRemaining: $valueGross,
            feeCentsRemaining: $fee,
            sourceTransactionId: (int) $tx->id,
            sourceDate: (string) $tx->date,
            sourceTime: (string) $tx->time,
            sourceProduct: (string) $tx->product,
            sourcePriceTenThousandths: (int) $tx->price_ten_thousandths,
            sourcePriceCurrency: (string) $tx->price_currency,
        );
    }

    /**
     * @return array{value: int, fee: int}
     */
    private function allocateFromLot(FifoLot $lot, string $takeStr): array
    {
        if (bccomp($takeStr, '0', 12) <= 0) {
            return ['value' => 0, 'fee' => 0];
        }

        if (bccomp($takeStr, $lot->remainingQty, 12) > 0) {
            throw new \LogicException('FIFO internal: take exceeds lot remaining quantity.');
        }

        $willDeplete = bccomp($takeStr, $lot->remainingQty, 12) === 0;

        if ($willDeplete) {
            $v = $lot->valueCentsRemaining;
            $f = $lot->feeCentsRemaining;
            $lot->valueCentsRemaining = 0;
            $lot->feeCentsRemaining = 0;
            $lot->remainingQty = '0';

            return ['value' => $v, 'fee' => $f];
        }

        $rq = $lot->remainingQty;
        $perV = bcdiv((string) $lot->valueCentsRemaining, $rq, 14);
        $perF = bcdiv((string) $lot->feeCentsRemaining, $rq, 14);
        $v = (int) round((float) bcmul($perV, $takeStr, 10));
        $f = (int) round((float) bcmul($perF, $takeStr, 10));
        $v = min($v, $lot->valueCentsRemaining);
        $f = min($f, $lot->feeCentsRemaining);

        $lot->valueCentsRemaining -= $v;
        $lot->feeCentsRemaining -= $f;
        $lot->remainingQty = bcsub($rq, $takeStr, 12);

        return ['value' => $v, 'fee' => $f];
    }

    private function rowFeeCents(DegiroTransaction $tx): int
    {
        $parsed = $this->currency->convertToCents($tx->transaction_and_or_third);
        $fromColumn = $parsed !== null ? abs($parsed) : 0;
        $autofx = $tx->autofx_fee !== null ? abs((int) $tx->autofx_fee) : 0;

        return $fromColumn + $autofx;
    }

    private function transactionYear(DegiroTransaction $tx): int
    {
        try {
            return (int) Carbon::createFromFormat('d-m-Y', (string) $tx->date)->year;
        } catch (\Throwable) {
            throw new \InvalidArgumentException('Invalid transaction date format: '.(string) $tx->date);
        }
    }

    private function normalizeQuantity(string $raw): string
    {
        $raw = trim($raw);

        return $raw === '' ? '0' : $raw;
    }
}
