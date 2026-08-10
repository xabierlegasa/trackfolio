<?php

namespace Tests\Unit;

use App\DegiroTransaction\Domain\Entity\DegiroTransaction;
use App\DegiroTransaction\Domain\Service\ConvertCurrencyToMinUnitService;
use App\DegiroTransaction\Infrastructure\Repository\DegiroTransactionRepository;
use App\TaxReturn\Domain\Exception\InsufficientFifoInventoryException;
use App\TaxReturn\Domain\Service\FifoTaxYearReportService;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FifoTaxYearReportServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_matches_oldest_buy_first_and_aggregates_by_year(): void
    {
        $repo = Mockery::mock(DegiroTransactionRepository::class);
        $rows = collect([
            $this->tx(['date' => '01-01-2024', 'time' => '09:00', 'id' => 1, 'quantity' => 10, 'value_min_unit' => -100_000, 'transaction_and_or_third' => '-1,00']),
            $this->tx(['date' => '02-01-2024', 'time' => '09:00', 'id' => 2, 'quantity' => 10, 'value_min_unit' => -200_000, 'transaction_and_or_third' => '-1,00']),
            $this->tx(['date' => '15-06-2025', 'time' => '12:00', 'id' => 3, 'quantity' => -5, 'value_min_unit' => 90_000, 'transaction_and_or_third' => '-0,50']),
        ]);
        $repo->shouldReceive('findChronologicalForUser')->once()->with(1)->andReturn($rows);

        $service = new FifoTaxYearReportService($repo, new ConvertCurrencyToMinUnitService);
        $report = $service->buildReport(1, 2025);

        $this->assertSame(2025, $report['year']);
        $this->assertCount(1, $report['lines']);
        $line = $report['lines'][0];
        $this->assertSame(50_000, $line['acquisition_value_cents']);
        $this->assertSame(50, $line['acquisition_commissions_cents']);
        $this->assertSame(90_000, $line['transmission_value_cents']);
        $this->assertSame(50, $line['transmission_commissions_cents']);
        $expectedNet = (90_000 - 50) - (50_000 + 50);
        $this->assertSame($expectedNet, $line['net_gain_cents']);
        $this->assertSame($expectedNet, $report['total_net_gain_cents']);
    }

    #[Test]
    public function it_throws_when_selling_without_inventory(): void
    {
        $repo = Mockery::mock(DegiroTransactionRepository::class);
        $rows = collect([
            $this->tx(['date' => '01-06-2025', 'time' => '12:00', 'id' => 1, 'quantity' => -1, 'value_min_unit' => 1000, 'transaction_and_or_third' => null]),
        ]);
        $repo->shouldReceive('findChronologicalForUser')->once()->with(1)->andReturn($rows);

        $service = new FifoTaxYearReportService($repo, new ConvertCurrencyToMinUnitService);

        $this->expectException(InsufficientFifoInventoryException::class);
        $service->buildReport(1, 2025);
    }

    #[Test]
    public function it_builds_isin_audit_with_sell_and_fifo_buy_steps(): void
    {
        $repo = Mockery::mock(DegiroTransactionRepository::class);
        $rows = collect([
            $this->tx(['date' => '01-01-2024', 'time' => '09:00', 'id' => 1, 'quantity' => 10, 'value_min_unit' => -100_000, 'transaction_and_or_third' => '-1,00']),
            $this->tx(['date' => '02-01-2024', 'time' => '09:00', 'id' => 2, 'quantity' => 10, 'value_min_unit' => -200_000, 'transaction_and_or_third' => '-1,00']),
            $this->tx(['date' => '15-06-2025', 'time' => '12:00', 'id' => 3, 'quantity' => -5, 'value_min_unit' => 90_000, 'transaction_and_or_third' => '-0,50']),
        ]);
        $repo->shouldReceive('findChronologicalForUser')->once()->with(1)->andReturn($rows);

        $service = new FifoTaxYearReportService($repo, new ConvertCurrencyToMinUnitService);
        $audit = $service->buildIsinAudit(1, 2025, 'NL0000000000');

        $this->assertSame('NL0000000000', $audit['isin']);
        $this->assertCount(2, $audit['steps']);
        $this->assertSame('sell', $audit['steps'][0]['kind']);
        $this->assertSame('buy_fifo', $audit['steps'][1]['kind']);
        $this->assertSame(1, $audit['steps'][1]['source_transaction_id']);
        $expectedNet = (90_000 - 50) - (50_000 + 50);
        $this->assertSame($expectedNet, $audit['summary']['net_gain_cents']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function tx(array $overrides): DegiroTransaction
    {
        $defaults = [
            'id' => 0,
            'date' => '01-01-2024',
            'time' => '10:00',
            'isin' => 'NL0000000000',
            'product' => 'TestCo',
            'quantity' => 1,
            'price_ten_thousandths' => 100_0000,
            'price_currency' => 'EUR',
            'value_min_unit' => -100,
            'transaction_and_or_third' => null,
            'autofx_fee' => null,
        ];

        $m = new DegiroTransaction;
        $m->forceFill(array_merge($defaults, $overrides));

        return $m;
    }
}
