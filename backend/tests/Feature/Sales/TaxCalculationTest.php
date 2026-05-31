<?php

namespace Tests\Feature\Sales;

use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * TC-TAX: Tax calculation scenarios — inclusive, exclusive, zero-rate.
 */
class TaxCalculationTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->cashier = User::factory()->create(['is_active' => true]);
        $this->cashier->assignRole('cashier');

        // Enable global tax in settings
        DB::table('settings')
            ->upsert(
                [['key' => 'tax_enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'tax', 'label_ar' => 'تفعيل الضريبة', 'label_en' => 'Enable Tax'],
                    ['key' => 'tax_rate',    'value' => '14', 'type' => 'number',  'group' => 'tax', 'label_ar' => 'نسبة الضريبة', 'label_en' => 'Tax Rate']],
                ['key'],
                ['value'],
            );
    }

    #[Test]
    public function tax_exclusive_adds_to_total(): void
    {
        // tax_inclusive = false → tax is ADDED on top of price
        DB::table('settings')
            ->upsert([['key' => 'tax_inclusive', 'value' => '0', 'type' => 'boolean', 'group' => 'tax', 'label_ar' => 'شامل الضريبة', 'label_en' => 'Tax Inclusive']], ['key'], ['value']);

        $product = Product::factory()->create(['price' => 100.00, 'quantity' => 10]);

        $response = $this->actingAs($this->cashier)->postJson('/api/invoices', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(201);
        $invoice = Invoice::latest()->first();
        // 100 × 14% = 14 tax → final = 114
        $this->assertEquals(14.00, round((float) $invoice->tax_amount, 2));
        $this->assertEquals(114.00, round((float) $invoice->final_total, 2));
    }

    #[Test]
    public function tax_inclusive_extracts_from_price(): void
    {
        // tax_inclusive = true → price already includes tax
        DB::table('settings')
            ->upsert([['key' => 'tax_inclusive', 'value' => '1', 'type' => 'boolean', 'group' => 'tax', 'label_ar' => 'شامل الضريبة', 'label_en' => 'Tax Inclusive']], ['key'], ['value']);

        $product = Product::factory()->create(['price' => 114.00, 'quantity' => 10]);

        $response = $this->actingAs($this->cashier)->postJson('/api/invoices', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(201);
        $invoice = Invoice::latest()->first();
        // Price 114 includes 14% → tax = 14, net = 100
        $this->assertEquals(114.00, round((float) $invoice->final_total, 2));
    }

    #[Test]
    public function zero_rate_tax_produces_no_tax_amount(): void
    {
        DB::table('settings')
            ->upsert([['key' => 'tax_rate', 'value' => '0', 'type' => 'number', 'group' => 'tax', 'label_ar' => 'نسبة الضريبة', 'label_en' => 'Tax Rate']], ['key'], ['value']);

        $product = Product::factory()->create(['price' => 100.00, 'quantity' => 10]);

        $response = $this->actingAs($this->cashier)->postJson('/api/invoices', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(201);
        $invoice = Invoice::latest()->first();
        $this->assertEquals(0.00, (float) $invoice->tax_amount);
        $this->assertEquals(100.00, (float) $invoice->final_total);
    }

    #[Test]
    public function tax_disabled_produces_no_tax(): void
    {
        DB::table('settings')
            ->upsert([['key' => 'tax_enabled', 'value' => '0', 'type' => 'boolean', 'group' => 'tax', 'label_ar' => 'تفعيل الضريبة', 'label_en' => 'Enable Tax']], ['key'], ['value']);

        $product = Product::factory()->create(['price' => 100.00, 'quantity' => 10]);

        $response = $this->actingAs($this->cashier)->postJson('/api/invoices', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(201);
        $invoice = Invoice::latest()->first();
        $this->assertEquals(0.00, (float) $invoice->tax_amount);
    }

    #[Test]
    public function tax_calculated_on_after_discount_amount(): void
    {
        // Tax should apply on discounted total, not gross total
        DB::table('settings')
            ->upsert([['key' => 'tax_inclusive', 'value' => '0', 'type' => 'boolean', 'group' => 'tax', 'label_ar' => 'شامل الضريبة', 'label_en' => 'Tax Inclusive']], ['key'], ['value']);

        $product = Product::factory()->create(['price' => 100.00, 'quantity' => 10]);

        $response = $this->actingAs($this->cashier)->postJson('/api/invoices', [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'payment_method' => 'cash',
            'discount' => 50.00, // gross 200, after discount 150
        ]);

        $response->assertStatus(201);
        $invoice = Invoice::latest()->first();
        // Tax on 150 at 14% = 21, final = 171
        $this->assertEquals(21.00, round((float) $invoice->tax_amount, 2));
        $this->assertEquals(171.00, round((float) $invoice->final_total, 2));
    }

    #[Test]
    public function multiple_items_tax_summed_correctly(): void
    {
        DB::table('settings')
            ->upsert([['key' => 'tax_inclusive', 'value' => '0', 'type' => 'boolean', 'group' => 'tax', 'label_ar' => 'شامل الضريبة', 'label_en' => 'Tax Inclusive']], ['key'], ['value']);

        $p1 = Product::factory()->create(['price' => 100.00, 'quantity' => 10]);
        $p2 = Product::factory()->create(['price' => 200.00, 'quantity' => 10]);

        $response = $this->actingAs($this->cashier)->postJson('/api/invoices', [
            'items' => [
                ['product_id' => $p1->id, 'quantity' => 1], // 100
                ['product_id' => $p2->id, 'quantity' => 1], // 200
            ],
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(201);
        $invoice = Invoice::latest()->first();
        // Total 300, 14% = 42, final = 342
        $this->assertEquals(42.00, round((float) $invoice->tax_amount, 2));
        $this->assertEquals(342.00, round((float) $invoice->final_total, 2));
    }
}
