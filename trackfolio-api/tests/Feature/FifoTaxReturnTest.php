<?php

namespace Tests\Feature;

use App\DegiroTransaction\Domain\Entity\DegiroTransaction;
use App\User\Domain\Entity\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * @requires extension pdo_sqlite
 */
class FifoTaxReturnTest extends TestCase
{
    use RefreshDatabase;

    private function makeDegiroRow(User $user, array $overrides = []): DegiroTransaction
    {
        $isin = $overrides['isin'] ?? 'NL0000000000';
        $hash = $overrides['custom_content_hash'] ?? hash('sha256', uniqid('tx', true));

        $defaults = [
            'user_id' => $user->id,
            'date' => '01-01-2024',
            'time' => '10:00',
            'product' => 'TestCo',
            'isin' => $isin,
            'reference' => 'X',
            'venue' => null,
            'quantity' => 1,
            'price_ten_thousandths' => 1_000_000,
            'price_currency' => 'EUR',
            'local_value_min_unit' => -100_00,
            'local_value_currency' => 'EUR',
            'value_min_unit' => -100_00,
            'value_currency' => 'EUR',
            'exchange_rate' => null,
            'autofx_fee' => null,
            'transaction_and_or_third' => '-0,50',
            'transaction_currency' => 'EUR',
            'total_min_unit' => -100_50,
            'total_currency' => 'EUR',
            'order_id' => '1',
            'custom_content_hash' => $hash,
        ];

        return DegiroTransaction::create(array_merge($defaults, $overrides));
    }

    public function test_tax_return_years_lists_from_current_year_down_to_oldest(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->makeDegiroRow($user, [
            'date' => '15-03-2022',
            'time' => '09:00',
            'custom_content_hash' => hash('sha256', 'a'),
        ]);

        $response = $this->getJson('/api/tax-return/years');

        $response->assertOk();
        $years = $response->json('years');
        $evolution = $response->json('evolution');
        $this->assertNotEmpty($years);
        $this->assertIsArray($evolution);
        $this->assertCount(count($years), $evolution);
        $this->assertSame((int) date('Y'), $years[0]);
        $this->assertContains(2022, $years);
        $this->assertTrue($years[0] >= $years[count($years) - 1]);
    }

    public function test_tax_return_year_detail_fifo_net_gain(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $y = (int) date('Y');
        $prev = $y - 1;

        $this->makeDegiroRow($user, [
            'date' => '01-01-'.$prev,
            'time' => '09:00',
            'quantity' => 10,
            'value_min_unit' => -100_000,
            'local_value_min_unit' => -100_000,
            'transaction_and_or_third' => '-1,00',
            'total_min_unit' => -100_100,
            'custom_content_hash' => hash('sha256', 'buy1'),
        ]);

        $this->makeDegiroRow($user, [
            'date' => '02-01-'.$prev,
            'time' => '09:00',
            'quantity' => 10,
            'value_min_unit' => -200_000,
            'local_value_min_unit' => -200_000,
            'transaction_and_or_third' => '-1,00',
            'total_min_unit' => -200_100,
            'custom_content_hash' => hash('sha256', 'buy2'),
        ]);

        $this->makeDegiroRow($user, [
            'date' => '15-06-'.$y,
            'time' => '12:00',
            'quantity' => -5,
            'value_min_unit' => 90_000,
            'local_value_min_unit' => 90_000,
            'transaction_and_or_third' => '-0,50',
            'total_min_unit' => 89_950,
            'custom_content_hash' => hash('sha256', 'sell1'),
        ]);

        $response = $this->getJson('/api/tax-return/'.$y);

        $response->assertOk();
        $response->assertJsonPath('year', $y);

        $lines = $response->json('lines');
        $this->assertCount(1, $lines);
        $line = $lines[0];

        $this->assertSame('NL0000000000', $line['isin']);
        $this->assertSame(50_000, $line['acquisition_value_cents']);
        $this->assertSame(50, $line['acquisition_commissions_cents']);
        $this->assertSame(90_000, $line['transmission_value_cents']);
        $this->assertSame(50, $line['transmission_commissions_cents']);

        $expectedNet = (90_000 - 50) - (50_000 + 50);
        $this->assertSame($expectedNet, $line['net_gain_cents']);
        $this->assertSame($expectedNet, $response->json('total_net_gain_cents'));
    }

    public function test_tax_return_year_detail_returns_422_when_fifo_insufficient(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $y = (int) date('Y');

        $this->makeDegiroRow($user, [
            'date' => '01-06-'.$y,
            'time' => '12:00',
            'quantity' => -1,
            'value_min_unit' => 1000,
            'local_value_min_unit' => 1000,
            'transaction_and_or_third' => null,
            'transaction_currency' => null,
            'total_min_unit' => 1000,
            'custom_content_hash' => hash('sha256', 'orphan-sell'),
        ]);

        $response = $this->getJson('/api/tax-return/'.$y);

        $response->assertStatus(422);
        $response->assertJsonPath('isin', 'NL0000000000');
    }
}
