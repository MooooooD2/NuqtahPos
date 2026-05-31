<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\InvoiceService;
use Database\Seeders\RolePermissionSeeder;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PriceCalculationTest extends TestCase
{
    use RefreshDatabase;

    private InvoiceService $service;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->cashier = User::factory()->create(['is_active' => true]);
        $this->cashier->assignRole('cashier');
        $this->actingAs($this->cashier);
        $this->service = app(InvoiceService::class);
    }

    #[Test]
    public function price_comes_from_database_not_user_input()
    {
        $product = Product::factory()->create(['price' => 100.00, 'quantity' => 5]);

        $invoice = $this->service->createInvoice([
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'payment_method' => 'cash',
        ]);

        // السعر 100 × 2 = 200 من DB
        $this->assertEquals(200.00, $invoice->total);
    }

    #[Test]
    public function discount_cannot_exceed_total()
    {
        $product = Product::factory()->create(['price' => 100.00, 'quantity' => 5]);

        // Discount of 999 exceeds the total (100) — service throws, not silently caps
        $this->expectException(Exception::class);

        $this->service->createInvoice([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'discount' => 999,
            'payment_method' => 'cash',
        ]);
    }

    #[Test]
    public function tax_is_calculated_from_settings_not_user()
    {
        Setting::set('tax_enabled', true);
        Setting::set('tax_rate', 15);
        Setting::set('tax_inclusive', false);

        $product = Product::factory()->create(['price' => 100.00, 'quantity' => 5]);

        $invoice = $this->service->createInvoice([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payment_method' => 'cash',
        ]);

        // 100 × 15% = 15 ضريبة
        $this->assertEquals(15.00, round($invoice->tax_amount, 2));
        $this->assertEquals(115.00, round($invoice->final_total, 2));
    }

    #[Test]
    public function invoice_total_matches_sum_of_items()
    {
        $p1 = Product::factory()->create(['price' => 50.00, 'quantity' => 5]);
        $p2 = Product::factory()->create(['price' => 75.00, 'quantity' => 5]);

        $invoice = $this->service->createInvoice([
            'items' => [
                ['product_id' => $p1->id, 'quantity' => 2],  // 100
                ['product_id' => $p2->id, 'quantity' => 1],  // 75
            ],
            'payment_method' => 'cash',
        ]);

        $this->assertEquals(175.00, $invoice->total);
    }
}
