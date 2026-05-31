<?php

namespace Tests\Feature\Sales;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * TC-INV: Invoice lifecycle — creation, tax, payment methods, stock deduction, edge cases.
 */
class InvoiceFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private User $admin;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('admin');

        $this->cashier = User::factory()->create(['is_active' => true]);
        $this->cashier->assignRole('cashier');

        $this->product = Product::factory()->create([
            'price' => 100.00,
            'cost_price' => 60.00,
            'quantity' => 50,
            'min_stock' => 5,
        ]);
    }

    // ── Happy paths ────────────────────────────────────────────────────────────

    #[Test]
    public function creates_invoice_and_deducts_stock(): void
    {
        $response = $this->actingAs($this->cashier)->postJson('/api/invoices', [
            'items' => [['product_id' => $this->product->id, 'quantity' => 3]],
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(201);
        $this->assertEquals(47, $this->product->fresh()->quantity);
        $this->assertDatabaseHas('invoices', ['total' => 300.00, 'status' => 'completed']);
    }

    #[Test]
    public function cash_received_change_calculated_correctly(): void
    {
        $response = $this->actingAs($this->cashier)->postJson('/api/invoices', [
            'items' => [['product_id' => $this->product->id, 'quantity' => 2]],
            'payment_method' => 'cash',
            'cash_received' => 250.00,
        ]);

        $response->assertStatus(201);
        $invoice = Invoice::latest()->first();
        $this->assertEquals(200.00, $invoice->final_total);
        $this->assertEquals(50.00, $invoice->change_amount);
    }

    #[Test]
    public function creates_invoice_with_card_payment(): void
    {
        $response = $this->actingAs($this->cashier)->postJson('/api/invoices', [
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            'payment_method' => 'card',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('invoice.payment_method', 'card');
    }

    #[Test]
    public function creates_invoice_with_multiple_products(): void
    {
        $p2 = Product::factory()->create(['price' => 50.00, 'quantity' => 20]);

        $response = $this->actingAs($this->cashier)->postJson('/api/invoices', [
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 2],
                ['product_id' => $p2->id,            'quantity' => 4],
            ],
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('invoices', ['total' => 400.00]); // 2×100 + 4×50
    }

    #[Test]
    public function invoice_discount_reduces_final_total(): void
    {
        $response = $this->actingAs($this->cashier)->postJson('/api/invoices', [
            'items' => [['product_id' => $this->product->id, 'quantity' => 2]],
            'payment_method' => 'cash',
            'discount' => 20.00,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('invoices', ['total' => 200.00, 'final_total' => 180.00]);
    }

    #[Test]
    public function invoice_linked_to_customer(): void
    {
        $customer = Customer::create([
            'code' => 'CUST-001',
            'name' => 'أحمد محمد',
            'phone' => '01000000001',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->cashier)->postJson('/api/invoices', [
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            'payment_method' => 'cash',
            'customer_id' => $customer->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('invoices', ['customer_id' => $customer->id]);
    }

    #[Test]
    public function invoice_number_is_unique_and_sequential(): void
    {
        $r1 = $this->actingAs($this->cashier)->postJson('/api/invoices', [
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            'payment_method' => 'cash',
        ]);
        $r2 = $this->actingAs($this->cashier)->postJson('/api/invoices', [
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            'payment_method' => 'cash',
        ]);

        $n1 = Invoice::orderBy('id')->first()->invoice_number;
        $n2 = Invoice::orderBy('id')->skip(1)->first()->invoice_number;
        $this->assertNotEquals($n1, $n2);
    }

    // ── Edge cases: stock ─────────────────────────────────────────────────────

    #[Test]
    public function rejects_when_quantity_exceeds_stock(): void
    {
        $this->actingAs($this->cashier)->postJson('/api/invoices', [
            'items' => [['product_id' => $this->product->id, 'quantity' => 999]],
            'payment_method' => 'cash',
        ])->assertStatus(422);

        $this->assertEquals(50, $this->product->fresh()->quantity); // unchanged
    }

    #[Test]
    public function rejects_zero_quantity(): void
    {
        $this->actingAs($this->cashier)->postJson('/api/invoices', [
            'items' => [['product_id' => $this->product->id, 'quantity' => 0]],
            'payment_method' => 'cash',
        ])->assertStatus(422);
    }

    #[Test]
    public function rejects_negative_quantity(): void
    {
        $this->actingAs($this->cashier)->postJson('/api/invoices', [
            'items' => [['product_id' => $this->product->id, 'quantity' => -1]],
            'payment_method' => 'cash',
        ])->assertStatus(422);
    }

    #[Test]
    public function stock_not_deducted_on_failed_invoice(): void
    {
        $this->actingAs($this->cashier)->postJson('/api/invoices', [
            'items' => [['product_id' => $this->product->id, 'quantity' => 999]],
            'payment_method' => 'cash',
        ])->assertStatus(422);

        $this->assertEquals(50, $this->product->fresh()->quantity);
    }

    // ── Edge cases: discount ──────────────────────────────────────────────────

    #[Test]
    public function discount_cannot_exceed_total(): void
    {
        $this->actingAs($this->cashier)->postJson('/api/invoices', [
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            'payment_method' => 'cash',
            'discount' => 500.00, // more than total (100)
        ])->assertStatus(422);
    }

    #[Test]
    public function zero_discount_accepted(): void
    {
        $this->actingAs($this->cashier)->postJson('/api/invoices', [
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            'payment_method' => 'cash',
            'discount' => 0,
        ])->assertStatus(201);
    }

    // ── Edge cases: validation ────────────────────────────────────────────────

    #[Test]
    public function rejects_empty_items_array(): void
    {
        $this->actingAs($this->cashier)->postJson('/api/invoices', [
            'items' => [],
            'payment_method' => 'cash',
        ])->assertStatus(422);
    }

    #[Test]
    public function rejects_nonexistent_product(): void
    {
        $this->actingAs($this->cashier)->postJson('/api/invoices', [
            'items' => [['product_id' => 99999, 'quantity' => 1]],
            'payment_method' => 'cash',
        ])->assertStatus(422);
    }

    #[Test]
    public function rejects_invalid_payment_method(): void
    {
        $this->actingAs($this->cashier)->postJson('/api/invoices', [
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            'payment_method' => 'bitcoin',
        ])->assertStatus(422);
    }

    // ── Authorization ─────────────────────────────────────────────────────────

    #[Test]
    public function unauthenticated_user_cannot_create_invoice(): void
    {
        $this->postJson('/api/invoices', [
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            'payment_method' => 'cash',
        ])->assertStatus(401);
    }

    // ── Cancel invoice ────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_cancel_invoice_and_restore_stock(): void
    {
        $invoice = Invoice::factory()->create([
            'status' => 'completed',
            'cashier_id' => $this->cashier->id,
        ]);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
            'price' => 100.00,
            'subtotal' => 500.00,
        ]);
        $initialQty = $this->product->quantity;

        $this->actingAs($this->admin)->postJson("/api/invoices/{$invoice->id}/cancel")
            ->assertStatus(200);

        $this->assertEquals('cancelled', $invoice->fresh()->status);
        $this->assertEquals($initialQty + 5, $this->product->fresh()->quantity);
    }

    #[Test]
    public function cashier_cannot_cancel_invoice(): void
    {
        $invoice = Invoice::factory()->create(['status' => 'completed', 'cashier_id' => $this->cashier->id]);

        $this->actingAs($this->cashier)->postJson("/api/invoices/{$invoice->id}/cancel")
            ->assertStatus(403);
    }

    #[Test]
    public function already_cancelled_invoice_cannot_be_cancelled_again(): void
    {
        $invoice = Invoice::factory()->create(['status' => 'cancelled', 'cashier_id' => $this->cashier->id]);

        $this->actingAs($this->admin)->postJson("/api/invoices/{$invoice->id}/cancel")
            ->assertStatus(422);
    }
}
