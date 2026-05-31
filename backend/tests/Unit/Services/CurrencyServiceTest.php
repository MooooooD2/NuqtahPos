<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Currency;
use App\Services\CurrencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 10 — Currency Service Unit Tests
 */
class CurrencyServiceTest extends TestCase
{
    use RefreshDatabase;

    private CurrencyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CurrencyService::class);
    }

    #[Test]
    public function it_converts_same_currency_to_itself(): void
    {
        Currency::create([
            'code' => 'EGP',
            'name' => 'Egyptian Pound',
            'symbol' => 'ج.م',
            'exchange_rate' => 1.0,
            'is_base' => true,
            'is_active' => true,
        ]);

        $result = $this->service->convert(100.0, 'EGP', 'EGP');

        $this->assertEquals(100.0, $result);
    }

    #[Test]
    public function it_converts_between_currencies(): void
    {
        Currency::create(['code' => 'EGP', 'name' => 'EGP', 'symbol' => 'E', 'exchange_rate' => 1.0, 'is_base' => true, 'is_active' => true]);
        Currency::create(['code' => 'USD', 'name' => 'USD', 'symbol' => '$', 'exchange_rate' => 50.0, 'is_base' => false, 'is_active' => true]);

        // 100 USD → EGP: 100 / 50 * 1 = 2 EGP
        $result = $this->service->convert(100.0, 'USD', 'EGP');

        $this->assertEquals(2.0, $result);
    }

    #[Test]
    public function it_formats_amounts_correctly(): void
    {
        Currency::create(['code' => 'USD', 'name' => 'USD', 'symbol' => '$', 'exchange_rate' => 50.0, 'is_base' => false, 'is_active' => true]);

        $formatted = $this->service->format(1234.5, 'USD');

        $this->assertStringContainsString('$', $formatted);
        $this->assertStringContainsString('1,234.50', $formatted);
    }
}
