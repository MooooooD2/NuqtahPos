<?php

namespace Tests\Feature\Inventory;

use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * TC-WHTRANSFER: Warehouse transfers — create, receive, lock/unlock, isolation.
 */
class WarehouseTransferTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $warehouse;

    private User $cashier;

    private Warehouse $warehouseA;

    private Warehouse $warehouseB;

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

        $this->warehouseA = Warehouse::create([
            'name' => 'المخزن الرئيسي',
            'code' => 'WH-A',
            'is_active' => true,
            'is_locked' => false,
            'is_default' => true,
        ]);

        $this->warehouseB = Warehouse::create([
            'name' => 'مخزن الفرع',
            'code' => 'WH-B',
            'is_active' => true,
            'is_locked' => false,
        ]);

        $this->product = Product::factory()->create(['quantity' => 50]);

        // Seed warehouse stock for source warehouse
        WarehouseStock::create([
            'warehouse_id' => $this->warehouseA->id,
            'product_id' => $this->product->id,
            'quantity' => 50,
            'reserved_qty' => 0,
            'min_stock' => 0,
        ]);

        WarehouseStock::create([
            'warehouse_id' => $this->warehouseB->id,
            'product_id' => $this->product->id,
            'quantity' => 0,
            'reserved_qty' => 0,
            'min_stock' => 0,
        ]);
    }

    // ── Create transfer ───────────────────────────────────────────────────────

    #[Test]
    public function warehouse_user_can_create_transfer(): void
    {
        $response = $this->actingAs($this->warehouse)->postJson('/api/warehouse-transfers', [
            'from_warehouse_id' => $this->warehouseA->id,
            'to_warehouse_id' => $this->warehouseB->id,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 10],
            ],
            'notes' => 'نقل منتظم',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('warehouse_transfers', [
            'from_warehouse_id' => $this->warehouseA->id,
            'to_warehouse_id' => $this->warehouseB->id,
        ]);
    }

    #[Test]
    public function admin_can_create_transfer(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/warehouse-transfers', [
            'from_warehouse_id' => $this->warehouseA->id,
            'to_warehouse_id' => $this->warehouseB->id,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 5],
            ],
        ]);

        $response->assertStatus(201);
    }

    #[Test]
    public function cashier_cannot_create_transfer(): void
    {
        $this->actingAs($this->cashier)->postJson('/api/warehouse-transfers', [
            'from_warehouse_id' => $this->warehouseA->id,
            'to_warehouse_id' => $this->warehouseB->id,
            'items' => [['product_id' => $this->product->id, 'quantity' => 5]],
        ])->assertStatus(403);
    }

    #[Test]
    public function transfer_to_same_warehouse_is_rejected(): void
    {
        $this->actingAs($this->warehouse)->postJson('/api/warehouse-transfers', [
            'from_warehouse_id' => $this->warehouseA->id,
            'to_warehouse_id' => $this->warehouseA->id, // same
            'items' => [['product_id' => $this->product->id, 'quantity' => 5]],
        ])->assertStatus(422);
    }

    #[Test]
    public function transfer_with_zero_quantity_is_rejected(): void
    {
        $this->actingAs($this->warehouse)->postJson('/api/warehouse-transfers', [
            'from_warehouse_id' => $this->warehouseA->id,
            'to_warehouse_id' => $this->warehouseB->id,
            'items' => [['product_id' => $this->product->id, 'quantity' => 0]],
        ])->assertStatus(422);
    }

    #[Test]
    public function transfer_requires_at_least_one_item(): void
    {
        $this->actingAs($this->warehouse)->postJson('/api/warehouse-transfers', [
            'from_warehouse_id' => $this->warehouseA->id,
            'to_warehouse_id' => $this->warehouseB->id,
            'items' => [],
        ])->assertStatus(422);
    }

    #[Test]
    public function transfer_with_nonexistent_product_is_rejected(): void
    {
        $this->actingAs($this->warehouse)->postJson('/api/warehouse-transfers', [
            'from_warehouse_id' => $this->warehouseA->id,
            'to_warehouse_id' => $this->warehouseB->id,
            'items' => [['product_id' => 99999, 'quantity' => 5]],
        ])->assertStatus(422);
    }

    // ── Receive transfer ──────────────────────────────────────────────────────

    #[Test]
    public function receiving_transfer_updates_stock(): void
    {
        // Create transfer
        $createResponse = $this->actingAs($this->warehouse)->postJson('/api/warehouse-transfers', [
            'from_warehouse_id' => $this->warehouseA->id,
            'to_warehouse_id' => $this->warehouseB->id,
            'items' => [['product_id' => $this->product->id, 'quantity' => 10]],
        ]);
        $createResponse->assertStatus(201);

        $transferId = $createResponse->json('transfer.id');

        // Receive transfer
        $receiveResponse = $this->actingAs($this->warehouse)->postJson("/api/warehouse-transfers/{$transferId}/receive");
        $receiveResponse->assertStatus(200);

        // Destination stock should increase
        $destStock = WarehouseStock::where('warehouse_id', $this->warehouseB->id)
            ->where('product_id', $this->product->id)
            ->first();

        $this->assertGreaterThan(0, $destStock->quantity);
    }

    #[Test]
    public function transfer_status_changes_to_received_after_receive(): void
    {
        $createResponse = $this->actingAs($this->warehouse)->postJson('/api/warehouse-transfers', [
            'from_warehouse_id' => $this->warehouseA->id,
            'to_warehouse_id' => $this->warehouseB->id,
            'items' => [['product_id' => $this->product->id, 'quantity' => 5]],
        ]);

        $transferId = $createResponse->json('transfer.id');

        $this->actingAs($this->warehouse)->postJson("/api/warehouse-transfers/{$transferId}/receive");

        $this->assertDatabaseHas('warehouse_transfers', [
            'id' => $transferId,
            'status' => 'received',
        ]);
    }

    // ── Lock / Unlock ─────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_lock_warehouse(): void
    {
        $response = $this->actingAs($this->admin)->postJson("/api/warehouses/{$this->warehouseA->id}/toggle-lock");

        $response->assertStatus(200);
        $this->assertDatabaseHas('warehouses', ['id' => $this->warehouseA->id, 'is_locked' => true]);
    }

    #[Test]
    public function admin_can_unlock_warehouse(): void
    {
        // Lock first
        $this->warehouseA->update(['is_locked' => true, 'locked_by' => $this->admin->id, 'locked_at' => now()]);

        $response = $this->actingAs($this->admin)->postJson("/api/warehouses/{$this->warehouseA->id}/toggle-lock");

        $response->assertStatus(200);
        $this->assertDatabaseHas('warehouses', ['id' => $this->warehouseA->id, 'is_locked' => false]);
    }

    #[Test]
    public function cashier_cannot_lock_warehouse(): void
    {
        $this->actingAs($this->cashier)->postJson("/api/warehouses/{$this->warehouseA->id}/toggle-lock")
            ->assertStatus(403);
    }

    // ── List transfers ────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_list_all_transfers(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/warehouse-transfers');
        $response->assertStatus(200);
    }

    #[Test]
    public function transfers_can_be_filtered_by_status(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/warehouse-transfers?status=pending');
        $response->assertStatus(200);
    }

    #[Test]
    public function transfers_can_be_filtered_by_warehouse(): void
    {
        $response = $this->actingAs($this->admin)->getJson(
            "/api/warehouse-transfers?warehouse_id={$this->warehouseA->id}",
        );
        $response->assertStatus(200);
    }
}
