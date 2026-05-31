<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Warehouse CRUD and stock operations.
 */
class WarehouseManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('admin');

        $this->cashier = User::factory()->create(['is_active' => true]);
        $this->cashier->assignRole('cashier');
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }
        parent::tearDown();
    }

    private function makeWarehouse(string $code = 'WH01', array $extra = []): Warehouse
    {
        return Warehouse::create(array_merge([
            'name' => "Warehouse {$code}",
            'code' => $code,
            'is_active' => true,
        ], $extra));
    }

    // ── List ─────────────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_list_warehouses(): void
    {
        $this->makeWarehouse('LW01');
        $this->makeWarehouse('LW02');

        // index() returns a raw JSON array (collection) — seeded data may be present too
        $this->actingAs($this->admin)
            ->getJson('/api/warehouses')
            ->assertOk()
            ->assertJsonIsArray();
    }

    #[Test]
    public function cashier_cannot_list_warehouses(): void
    {
        $this->actingAs($this->cashier)
            ->getJson('/api/warehouses')
            ->assertForbidden();
    }

    // ── Create ───────────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_create_warehouse(): void
    {
        $res = $this->actingAs($this->admin)
            ->postJson('/api/warehouses', [
                'name' => 'Main Warehouse',
                'code' => 'MW01',
                'address' => '5th Industrial Zone',
            ]);

        $res->assertStatus(201)
            ->assertJsonStructure(['success', 'warehouse']);

        $this->assertDatabaseHas('warehouses', ['code' => 'MW01']);
    }

    #[Test]
    public function warehouse_code_must_be_unique(): void
    {
        $this->makeWarehouse('DUPE');

        $this->actingAs($this->admin)
            ->postJson('/api/warehouses', [
                'name' => 'Another',
                'code' => 'DUPE',
            ])->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    #[Test]
    public function warehouse_requires_name_and_code(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/warehouses', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'code']);
    }

    // ── Update ───────────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_update_warehouse(): void
    {
        $wh = $this->makeWarehouse('UPD1', ['name' => 'Old Name']);

        $this->actingAs($this->admin)
            ->putJson("/api/warehouses/{$wh->id}", ['name' => 'Updated'])
            ->assertOk()
            ->assertJsonPath('warehouse.name', 'Updated');
    }

    // ── Delete ───────────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_delete_warehouse(): void
    {
        $wh = $this->makeWarehouse('DEL1');

        $this->actingAs($this->admin)
            ->deleteJson("/api/warehouses/{$wh->id}")
            ->assertOk();

        $this->assertDatabaseMissing('warehouses', ['id' => $wh->id]);
    }

    // ── Stock ─────────────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_view_warehouse_stock(): void
    {
        $wh = $this->makeWarehouse('STCK1');

        $this->actingAs($this->admin)
            ->getJson("/api/warehouses/{$wh->id}/stock")
            ->assertOk()
            ->assertJsonStructure(['success', 'stock']);
    }

    #[Test]
    public function admin_can_adjust_warehouse_stock(): void
    {
        $wh = $this->makeWarehouse('ADJ1');
        $product = Product::factory()->create(['quantity' => 10]);

        // Controller expects 'new_quantity' (the absolute new value), not delta 'quantity'
        $this->actingAs($this->admin)
            ->postJson("/api/warehouses/{$wh->id}/adjust-stock", [
                'product_id' => $product->id,
                'new_quantity' => 15,
                'reason' => 'manual adjustment',
            ])->assertOk();
    }

    // ── Products List ─────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_list_all_products_for_warehouse(): void
    {
        Product::factory()->count(3)->create();

        $this->actingAs($this->admin)
            ->getJson('/api/warehouses/products-list')
            ->assertOk()
            ->assertJsonStructure(['success', 'products']);
    }

    // ── Toggle Lock ───────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_toggle_warehouse_lock(): void
    {
        $wh = $this->makeWarehouse('LCK1', ['is_locked' => false]);

        $res = $this->actingAs($this->admin)
            ->postJson("/api/warehouses/{$wh->id}/toggle-lock");

        $res->assertOk();
        $this->assertTrue((bool) $wh->fresh()->is_locked);
    }
}
