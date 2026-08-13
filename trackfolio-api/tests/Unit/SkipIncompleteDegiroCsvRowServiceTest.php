<?php

namespace Tests\Unit;

use App\DegiroTransaction\Domain\Service\SkipIncompleteDegiroCsvRowService;
use PHPUnit\Framework\TestCase;

class SkipIncompleteDegiroCsvRowServiceTest extends TestCase
{
    private SkipIncompleteDegiroCsvRowService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SkipIncompleteDegiroCsvRowService();
    }

    public function test_skips_when_isin_and_quantity_are_empty(): void
    {
        $row = $this->row(isin: '', quantity: '');

        $this->assertTrue($this->service->shouldSkip($row));
        $this->assertSame(
            [
                'line' => 2,
                'reason' => SkipIncompleteDegiroCsvRowService::REASON_MISSING_ISIN_AND_QUANTITY,
                'date' => '01-01-2024',
                'time' => '10:00',
                'product' => 'BITCOIN',
                'local_value' => '100,00 EUR',
            ],
            $this->service->skippedEntry($row, 2)
        );
    }

    public function test_does_not_skip_when_isin_empty_but_quantity_present(): void
    {
        $row = $this->row(isin: '', quantity: '1');

        $this->assertFalse($this->service->shouldSkip($row));
        $this->assertNull($this->service->skippedEntry($row, 3));
    }

    public function test_does_not_skip_when_both_present(): void
    {
        $row = $this->row(isin: 'US0378331005', quantity: '1');

        $this->assertFalse($this->service->shouldSkip($row));
    }

    /**
     * @return array<int, string>
     */
    private function row(string $isin, string $quantity): array
    {
        return [
            '01-01-2024',
            '10:00',
            'BITCOIN',
            $isin,
            'TRD',
            'TRDS',
            $quantity,
            '100,00',
            'EUR',
            '100,00',
            'EUR',
            '100,00',
            '',
            '0,00',
            '-3,18',
            '96,82',
            'order-1',
        ];
    }
}
