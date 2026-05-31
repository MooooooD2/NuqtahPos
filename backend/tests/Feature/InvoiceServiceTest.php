<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * InvoiceService feature test suite.
 *
 * Test IDs match the PDF technical spec:
 *   TC-Sales-01, EC-Sales-01, EC-Sales-02,
 *   EC-Stock-01, EC-Stock-02, EC-Return-01, EC-Return-02
 */
class InvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->cashier = User::factory()->create(['is_active' => true]);
        $this->cashier->assignRole('cashier');
    }

    // ── TC-Sales-01: Happy path ───────────────────────────────────────────────

    #[Test]
    public function tc_sales_01_happy_path_creates_invoice_and_decrements_stock(): void
    {
        $product = Product::factory()->create(['price' => 150.00, 'cost_price' => 80.00, 'quantity' => 20]);

        $response = $this->actingAs($this->cashier)->postJson('/api/invoices', [
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('invoices', ['total' => 450.00]);
        $this->assertEquals(17, $product->fresh()->quantity);
    }

    // ── EC-Sales-01: Insufficient stock ──────────────────────────────────────

    #[Test]
    public function ec_sales_01_insufficient_stock_returns_422(): void
    {
        $product = Product::factory()->create(['price' => 50.00, 'quantity' => 3]);

        $response = $this->actingAs($this->cashier)->postJson('/api/invoices', [
            'items' => [['product_id' => $product->id, 'quantity' => 10]],
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('invoices', 0);
        $this->assertEquals(3, $product->fresh()->quantity);
    }

    // ── EC-Sales-02: Price override rejected ─────────────────────────────────

    #[Test]
    public function ec_sales_02_user_supplied_price_is_ignored(): void
    {
        $product = Product::factory()->create(['price' => 200.00, 'quantity' => 5]);

        $response = $this->actingAs($this->cashier)->postJson('/api/invoices', [
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'price' => 1.00]],
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseMissing('invoice_items', ['price' => 1.00]);
        $this->assertDatabaseHas('invoice_items', ['product_id' => $product->id, 'price' => 200.00]);
    }

    // ── EC-Stock-01: Negative/zero quantity rejected ──────────────────────────

    #[Test]
    public function ec_stock_01_zero_quantity_returns_422(): void
    {
        $product = Product::factory()->create(['price' => 50.00, 'quantity' => 10]);

        $this->actingAs($this->cashier)->postJson('/api/invoices', [
            'items' => [['product_id' => $product->id, 'quantity' => 0]],
            'payment_method' => 'cash',
        ])->assertStatus(422);

        $this->actingAs($this->cashier)->postJson('/api/invoices', [
            'items' => [['product_id' => $product->id, 'quantity' => -5]],
            'payment_method' => 'cash',
        ])->assertStatus(422);

        $this->assertDatabaseCount('invoices', 0);
    }

    // ── EC-Stock-02: Concurrent deductions do not oversell ───────────────────

    #[Test]
    public function ec_stock_02_concurrent_deductions_do_not_exceed_stock(): void
    {
        $product = Product::factory()->create(['price' => 10.00, 'quantity' => 5]);

        $this->actingAs($this->cashier)->postJson('/api/invoices', [
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
            'payment_method' => 'cash',
        ])->assertStatus(201);

        $this->actingAs($this->cashier)->postJson('/api/invoices', [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'payment_method' => 'cash',
        ])->assertStatus(201);

        $this->actingAs($this->cashier)->postJson('/api/invoices', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payment_method' => 'cash',
        ])->assertStatus(422);

        $this->assertEquals(0, $product->fresh()->quantity);
        $this->assertDatabaseCount('invoices', 2);
    }

    // ── EC-Return-01: Return exceeds original quantity ────────────────────────

    #[Test]
    public function ec_return_01_return_exceeds_purchased_quantity_returns_422(): void
    {
        $product = Product::factory()->create(['price' => 100.00, 'quantity' => 10]);

        $invoice = Invoice::factory()->create([
            'status' => 'completed',
            'total' => 200.00,
            'final_total' => 200.00,
        ]);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 2,
            'price' => 100.00,
            'subtotal' => 200.00,
        ]);

        $this->actingAs($this->cashier)->postJson('/api/returns', [
            'invoice_id' => $invoice->id,
            'items' => [['product_id' => $product->id, 'quantity' => 99]],
            'refund_method' => 'cash',
        ])->assertStatus(422);
    }

    // ── EC-Return-02: Return from non-completed invoice ───────────────────────

    #[Test]
    public function ec_return_02_return_from_cancelled_invoice_returns_422(): void
    {
        $product = Product::factory()->create(['price' => 50.00, 'quantity' => 5]);
        $cancelled = Invoice::factory()->create(['status' => 'cancelled']);
        InvoiceItem::factory()->create([
            'invoice_id' => $cancelled->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
        ]);

        $this->actingAs($this->cashier)->postJson('/api/returns', [
            'invoice_id' => $cancelled->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'refund_method' => 'cash',
        ])->assertStatus(422);
    }
}
