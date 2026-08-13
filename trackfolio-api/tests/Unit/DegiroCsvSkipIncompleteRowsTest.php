<?php

namespace Tests\Unit;

use App\DegiroTransaction\Domain\Service\SkipIncompleteDegiroCsvRowService;
use App\DegiroTransaction\Domain\Service\UploadDegiroTransactionsService;
use App\DegiroTransaction\Domain\Service\ValidateDegiroTransactionsCsvService;
use App\DegiroTransaction\Infrastructure\Repository\DegiroTransactionRepository;
use Illuminate\Http\UploadedFile;
use Mockery;
use Tests\TestCase;

class DegiroCsvSkipIncompleteRowsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_validate_skips_bitcoin_like_row_and_accepts_valid_row(): void
    {
        $csv = $this->csv([
            $this->validCompactRow('US0378331005', '1', 'order-valid'),
            $this->bitcoinLikeRow(),
        ]);

        $result = $this->app->make(ValidateDegiroTransactionsCsvService::class)
            ->validate($this->uploadedCsv($csv));

        $this->assertTrue($result['valid']);
        $this->assertSame([], $result['errors']);
        $this->assertCount(1, $result['skipped_rows']);
        $this->assertSame(3, $result['skipped_rows'][0]['line']);
        $this->assertSame(
            SkipIncompleteDegiroCsvRowService::REASON_MISSING_ISIN_AND_QUANTITY,
            $result['skipped_rows'][0]['reason']
        );
        $this->assertSame('04-05-2026', $result['skipped_rows'][0]['date']);
        $this->assertSame('16:39', $result['skipped_rows'][0]['time']);
        $this->assertSame('BITCOIN', $result['skipped_rows'][0]['product']);
        $this->assertSame('635,80 EUR', $result['skipped_rows'][0]['local_value']);
    }

    public function test_validate_only_skippable_rows_is_still_valid(): void
    {
        $csv = $this->csv([
            $this->bitcoinLikeRow(),
        ]);

        $result = $this->app->make(ValidateDegiroTransactionsCsvService::class)
            ->validate($this->uploadedCsv($csv));

        $this->assertTrue($result['valid']);
        $this->assertCount(1, $result['skipped_rows']);
        $this->assertSame(2, $result['skipped_rows'][0]['line']);
    }

    public function test_validate_empty_isin_with_quantity_fails(): void
    {
        $csv = $this->csv([
            $this->validCompactRow('', '1', 'order-bad'),
        ]);

        $result = $this->app->make(ValidateDegiroTransactionsCsvService::class)
            ->validate($this->uploadedCsv($csv));

        $this->assertFalse($result['valid']);
        $this->assertSame([], $result['skipped_rows']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('ISIN', implode(' ', $result['errors']));
    }

    public function test_upload_skips_bitcoin_like_row_and_imports_valid_row(): void
    {
        $csv = $this->csv([
            $this->validCompactRow('US0378331005', '1', 'order-valid'),
            $this->bitcoinLikeRow(),
        ]);

        $repository = Mockery::mock(DegiroTransactionRepository::class);
        $repository->shouldReceive('findExistingContentHashes')
            ->once()
            ->andReturn([]);
        $repository->shouldReceive('createMany')
            ->once()
            ->with(Mockery::on(fn (array $rows) => count($rows) === 1))
            ->andReturn(1);

        $this->app->instance(DegiroTransactionRepository::class, $repository);

        $result = $this->app->make(UploadDegiroTransactionsService::class)
            ->processCsv($this->uploadedCsv($csv), 1);

        $this->assertTrue($result->success);
        $this->assertSame(1, $result->newCount);
        $this->assertSame(1, $result->skippedCount);
        $this->assertSame(3, $result->skippedRows[0]['line']);
        $this->assertSame(
            SkipIncompleteDegiroCsvRowService::REASON_MISSING_ISIN_AND_QUANTITY,
            $result->skippedRows[0]['reason']
        );
    }

    public function test_upload_only_skippable_rows_returns_success_with_summary(): void
    {
        $csv = $this->csv([
            $this->bitcoinLikeRow(),
        ]);

        $repository = Mockery::mock(DegiroTransactionRepository::class);
        $repository->shouldNotReceive('findExistingContentHashes');
        $repository->shouldNotReceive('createMany');

        $this->app->instance(DegiroTransactionRepository::class, $repository);

        $result = $this->app->make(UploadDegiroTransactionsService::class)
            ->processCsv($this->uploadedCsv($csv), 1);

        $this->assertTrue($result->success);
        $this->assertSame(0, $result->newCount);
        $this->assertSame(1, $result->skippedCount);
        $this->assertSame(2, $result->skippedRows[0]['line']);
    }

    private function uploadedCsv(string $csvContent): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('transactions.csv', $csvContent);
    }

    /**
     * @param array<int, string> $rows
     */
    private function csv(array $rows): string
    {
        $header = implode(',', [
            'Date',
            'Time',
            'Product',
            'ISIN',
            'Reference',
            'Venue',
            'Quantity',
            'Price',
            'Price currency',
            'Local value',
            'Local value currency',
            'Value EUR',
            'Exchange rate',
            'AutoFX Fee',
            'Transaction and/or third party fees EUR',
            'Total EUR',
            'Order ID',
        ]);

        return $header . "\n" . implode("\n", $rows) . "\n";
    }

    private function validCompactRow(string $isin, string $quantity, string $orderId): string
    {
        return implode(',', [
            '01-01-2024',
            '10:00',
            'APPLE',
            $isin,
            'XNAS',
            'XNAS',
            $quantity,
            '"150,00"',
            'EUR',
            '"-150,00"',
            'EUR',
            '"-150,00"',
            '',
            '"0,00"',
            '"-0,50"',
            '"-150,50"',
            $orderId,
        ]);
    }

    private function bitcoinLikeRow(): string
    {
        return implode(',', [
            '04-05-2026',
            '16:39',
            'BITCOIN',
            '',
            'TRD',
            'TRDS',
            '',
            '"678,186,000"',
            'EUR',
            '"635,80"',
            'EUR',
            '"635,80"',
            '',
            '"0,00"',
            '"-3,18"',
            '"632,62"',
            '36c2d134-6793-46b3-869e-a16c81656a16',
        ]);
    }
}
