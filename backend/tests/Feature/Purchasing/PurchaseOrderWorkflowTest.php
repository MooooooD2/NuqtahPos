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
 * TC-PO: Purchase order full workflow — draft → submit → approve/reject → receive.
 */
class PurchaseOrderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $warehouse;

    private User $cashier;

    private Supplier $supplier;

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

        $this->supplier = Supplier::factory()->create();
        $this->product = Product::factory()->create(['quantity' => 10, 'cost_price' => 40.00]);
    }

    private function createDraftPO(array $overrides = []): array
    {
        $response = $this->actingAs($this->warehouse)->postJson('/api/purchase-orders', array_merge([
            'supplier_id' => $this->supplier->id,
            'order_date' => now()->toDateString(),
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'product_name' => $this->product->name,
                    'quantity' => 20,
                    'cost_price' => 40.00,
                ],
            ],
            'notes' => 'Test PO',
        ], $overrides));

        $id = $response->json('purchase_order.id') ?? $response->json('order.id') ?? $response->json('id');

        return [$response, $id];
    }

    // ── Creation ──────────────────────────────────────────────────────────────

    #[Test]
    public function warehouse_can_create_purchase_order(): void
    {
        [$response, $id] = $this->createDraftPO();
        $response->assertStatus(201);
        $this->assertDatabaseHas('purchase_orders', ['supplier_id' => $this->supplier->id, 'status' => 'draft']);
    }

    #[Test]
    public function cashier_cannot_create_purchase_order(): void
    {
        $this->actingAs($this->cashier)->postJson('/api/purchase-orders', [
            'supplier_id' => $this->supplier->id,
            'order_date' => now()->toDateString(),
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'product_name' => $this->product->name,
                    'quantity' => 5,
                    'cost_price' => 40.00,
                ],
            ],
        ])->assertStatus(403);
    }

    #[Test]
    public function purchase_order_total_calculated_correctly(): void
    {
        $p2 = Product::factory()->create(['quantity' => 5, 'cost_price' => 20.00]);

        $response = $this->actingAs($this->warehouse)->postJson('/api/purchase-orders', [
            'supplier_id' => $this->supplier->id,
            'order_date' => now()->toDateString(),
            'items' => [
                ['product_id' => $this->product->id, 'product_name' => $this->product->name, 'quantity' => 10, 'cost_price' => 40.00],
                ['product_id' => $p2->id,            'product_name' => $p2->name,            'quantity' => 5,  'cost_price' => 20.00],
            ],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('purchase_orders', ['total_amount' => 500.00]);
    }

    #[Test]
    public function po_requires_at_least_one_item(): void
    {
        $this->actingAs($this->warehouse)->postJson('/api/purchase-orders', [
            'supplier_id' => $this->supplier->id,
            'order_date' => now()->toDateString(),
            'items' => [],
        ])->assertStatus(422);
    }

    #[Test]
    public function po_requires_valid_supplier(): void
    {
        $this->actingAs($this->warehouse)->postJson('/api/purchase-orders', [
            'supplier_id' => 99999,
            'order_date' => now()->toDateString(),
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'product_name' => $this->product->name,
                    'quantity' => 1,
                    'cost_price' => 40.00,
                ],
            ],
        ])->assertStatus(422);
    }

    #[Test]
    public function po_requires_order_date(): void
    {
        $this->actingAs($this->warehouse)->postJson('/api/purchase-orders', [
            'supplier_id' => $this->supplier->id,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'product_name' => $this->product->name,
                    'quantity' => 1,
                    'cost_price' => 40.00,
                ],
            ],
        ])->assertStatus(422);
    }

    // ── Submit for approval ───────────────────────────────────────────────────

    #[Test]
    public function draft_po_can_be_submitted_for_approval(): void
    {
        [$response, $id] = $this->createDraftPO();
        $response->assertStatus(201);

        $this->actingAs($this->warehouse)->postJson("/api/purchase-orders/{$id}/submit")
            ->assertStatus(200);

        $this->assertDatabaseHas('purchase_orders', ['id' => $id, 'status' => 'pending']);
    }

    #[Test]
    public function already_pending_po_cannot_be_submitted_again(): void
    {
        [$response, $id] = $this->createDraftPO();
        $response->assertStatus(201);
        $this->actingAs($this->warehouse)->postJson("/api/purchase-orders/{$id}/submit");

        $this->actingAs($this->warehouse)->postJson("/api/purchase-orders/{$id}/submit")
            ->assertStatus(422);
    }

    // ── Approve ───────────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_approve_pending_po(): void
    {
        [$response, $id] = $this->createDraftPO();
        $response->assertStatus(201);
        $this->actingAs($this->warehouse)->postJson("/api/purchase-orders/{$id}/submit");

        $this->actingAs($this->admin)->postJson("/api/purchase-orders/{$id}/approve")
            ->assertStatus(200);

        $this->assertDatabaseHas('purchase_orders', ['id' => $id, 'status' => 'approved']);
    }

    #[Test]
    public function warehouse_cannot_approve_po(): void
    {
        [$response, $id] = $this->createDraftPO();
        $response->assertStatus(201);
        $this->actingAs($this->warehouse)->postJson("/api/purchase-orders/{$id}/submit");

        $this->actingAs($this->warehouse)->postJson("/api/purchase-orders/{$id}/approve")
            ->assertStatus(403);
    }

    #[Test]
    public function draft_po_cannot_be_approved_directly(): void
    {
        [$response, $id] = $this->createDraftPO();
        $response->assertStatus(201);

        $this->actingAs($this->admin)->postJson("/api/purchase-orders/{$id}/approve")
            ->assertStatus(422);
    }

    // ── Reject ────────────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_reject_pending_po_with_reason(): void
    {
        [$response, $id] = $this->createDraftPO();
        $response->assertStatus(201);
        $this->actingAs($this->warehouse)->postJson("/api/purchase-orders/{$id}/submit");

        $this->actingAs($this->admin)->postJson("/api/purchase-orders/{$id}/reject", [
            'reason' => 'Budget exceeded',
        ])->assertStatus(200);

        $this->assertDatabaseHas('purchase_orders', ['id' => $id, 'status' => 'rejected']);
    }

    #[Test]
    public function reject_requires_reason(): void
    {
        [$response, $id] = $this->createDraftPO();
        $response->assertStatus(201);
        $this->actingAs($this->warehouse)->postJson("/api/purchase-orders/{$id}/submit");

        $this->actingAs($this->admin)->postJson("/api/purchase-orders/{$id}/reject", [])
            ->assertStatus(422);
    }

    // ── Receive ───────────────────────────────────────────────────────────────

    private function getItemId(int $poId): int
    {
        return DB::table('purchase_order_items')
            ->where('po_id', $poId)
            ->value('id');
    }

    #[Test]
    public function receiving_approved_po_adds_stock(): void
    {
        $initialQty = $this->product->quantity;
        [$response, $id] = $this->createDraftPO();
        $response->assertStatus(201);
        $this->actingAs($this->warehouse)->postJson("/api/purchase-orders/{$id}/submit");
        $this->actingAs($this->admin)->postJson("/api/purchase-orders/{$id}/approve");

        $itemId = $this->getItemId($id);
        $this->actingAs($this->warehouse)->postJson("/api/purchase-orders/{$id}/receive", [
            'items' => [['item_id' => $itemId, 'received_quantity' => 20]],
        ])->assertStatus(200);

        $this->assertGreaterThan($initialQty, $this->product->fresh()->quantity);
    }

    #[Test]
    public function cannot_receive_unapproved_po(): void
    {
        [$response, $id] = $this->createDraftPO();
        $response->assertStatus(201);

        $itemId = $this->getItemId($id);
        $this->actingAs($this->warehouse)->postJson("/api/purchase-orders/{$id}/receive", [
            'items' => [['item_id' => $itemId, 'received_quantity' => 5]],
        ])->assertStatus(422);
    }

    #[Test]
    public function partial_receipt_sets_status_to_partial(): void
    {
        [$response, $id] = $this->createDraftPO();
        $response->assertStatus(201);
        $this->actingAs($this->warehouse)->postJson("/api/purchase-orders/{$id}/submit");
        $this->actingAs($this->admin)->postJson("/api/purchase-orders/{$id}/approve");

        $itemId = $this->getItemId($id);
        $this->actingAs($this->warehouse)->postJson("/api/purchase-orders/{$id}/receive", [
            'items' => [['item_id' => $itemId, 'received_quantity' => 10]], // ordered 20, receive 10
        ])->assertStatus(200);

        $this->assertDatabaseHas('purchase_orders', ['id' => $id, 'status' => 'partial']);
    }

    #[Test]
    public function full_receipt_marks_po_as_received(): void
    {
        [$response, $id] = $this->createDraftPO();
        $response->assertStatus(201);
        $this->actingAs($this->warehouse)->postJson("/api/purchase-orders/{$id}/submit");
        $this->actingAs($this->admin)->postJson("/api/purchase-orders/{$id}/approve");

        $itemId = $this->getItemId($id);
        $this->actingAs($this->warehouse)->postJson("/api/purchase-orders/{$id}/receive", [
            'items' => [['item_id' => $itemId, 'received_quantity' => 20]], // receive all 20
        ])->assertStatus(200);

        $this->assertDatabaseHas('purchase_orders', ['id' => $id, 'status' => 'received']);
    }
}
