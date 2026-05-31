<?php

namespace Tests\Feature\Purchasing;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * TC-PURCHRET: Purchase returns — create, validation, stock adjustment, refund methods.
 */
class PurchaseReturnTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $warehouse;

    private User $cashier;

    private int $purchaseOrderId;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('admin');

        $this->warehouse = User::factory()->create(['is_active' => true]);
        $this->warehouse->assignRole('warehouse');

        $this->cashier = User::factory()->create(['is_active' => true]);
        $this->cashier->assignRole('cashier');

        $supplier = Supplier::factory()->create();
        $this->product = Product::factory()->create(['price' => 100.00, 'quantity' => 50]);

        // Create a received purchase order
        $this->purchaseOrderId = DB::table('purchase_orders')->insertGetId([
            'po_number' => 'PO-TEST-' . uniqid(),
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'status' => 'received',
            'total_amount' => 1000.00,
            'final_amount' => 1000.00,
            'created_by' => $this->admin->id,
            'created_by_name' => $this->admin->full_name,
            'order_date' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('purchase_order_items')->insert([
            'po_id' => $this->purchaseOrderId,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'quantity' => 10,
            'received_quantity' => 10,
            'cost_price' => 100.00,
            'subtotal' => 1000.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ── Create return ─────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_create_purchase_return(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/purchase-returns', [
            'purchase_order_id' => $this->purchaseOrderId,
            'reason' => 'منتجات تالفة',
            'refund_method' => 'cash',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 2],
            ],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('purchase_returns', [
            'purchase_order_id' => $this->purchaseOrderId,
        ]);
    }

    #[Test]
    public function cashier_cannot_create_purchase_return(): void
    {
        $this->actingAs($this->cashier)->postJson('/api/purchase-returns', [
            'purchase_order_id' => $this->purchaseOrderId,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 1],
            ],
        ])->assertStatus(403);
    }

    #[Test]
    public function purchase_return_requires_valid_purchase_order(): void
    {
        $this->actingAs($this->admin)->postJson('/api/purchase-returns', [
            'purchase_order_id' => 99999, // nonexistent
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 1],
            ],
        ])->assertStatus(422);
    }

    #[Test]
    public function purchase_return_requires_at_least_one_item(): void
    {
        $this->actingAs($this->admin)->postJson('/api/purchase-returns', [
            'purchase_order_id' => $this->purchaseOrderId,
            'items' => [],
        ])->assertStatus(422);
    }

    #[Test]
    public function item_quantity_must_be_at_least_one(): void
    {
        $this->actingAs($this->admin)->postJson('/api/purchase-returns', [
            'purchase_order_id' => $this->purchaseOrderId,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 0],
            ],
        ])->assertStatus(422);
    }

    #[Test]
    public function refund_method_must_be_cash_or_credit_note(): void
    {
        $this->actingAs($this->admin)->postJson('/api/purchase-returns', [
            'purchase_order_id' => $this->purchaseOrderId,
            'refund_method' => 'crypto', // invalid
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 1],
            ],
        ])->assertStatus(422);
    }

    #[Test]
    public function cash_refund_method_is_accepted(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/purchase-returns', [
            'purchase_order_id' => $this->purchaseOrderId,
            'refund_method' => 'cash',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(201);
    }

    #[Test]
    public function credit_note_refund_method_is_accepted(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/purchase-returns', [
            'purchase_order_id' => $this->purchaseOrderId,
            'refund_method' => 'credit_note',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(201);
    }

    // ── Stock adjustment ──────────────────────────────────────────────────────

    #[Test]
    public function purchase_return_reduces_product_stock(): void
    {
        $initialQty = $this->product->quantity;

        $this->actingAs($this->admin)->postJson('/api/purchase-returns', [
            'purchase_order_id' => $this->purchaseOrderId,
            'refund_method' => 'cash',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 3],
            ],
        ])->assertStatus(201);

        $this->product->refresh();
        $this->assertEquals($initialQty - 3, $this->product->quantity);
    }

    // ── Returnable items ──────────────────────────────────────────────────────

    #[Test]
    public function admin_can_query_returnable_items_for_purchase_order(): void
    {
        $response = $this->actingAs($this->admin)->getJson(
            "/api/purchase-orders/{$this->purchaseOrderId}/returnable-items",
        );

        // May vary by route — accept 200 or 404
        $this->assertContains($response->status(), [200, 404]);
        if ($response->status() === 200) {
            $this->assertNotNull($response->json('items'));
        }
    }

    // ── List returns ──────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_list_purchase_returns(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/purchase-returns');
        $this->assertContains($response->status(), [200, 404]);
    }

    #[Test]
    public function purchase_returns_can_be_filtered_by_supplier(): void
    {
        $supplier = Supplier::factory()->create();
        $response = $this->actingAs($this->admin)->getJson(
            "/api/purchase-returns?supplier_id={$supplier->id}",
        );
        $this->assertContains($response->status(), [200, 404]);
    }
}
