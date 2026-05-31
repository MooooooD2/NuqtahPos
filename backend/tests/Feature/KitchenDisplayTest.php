<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\KitchenOrder;
use App\Models\KitchenOrderItem;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Kitchen Display System — order lifecycle & authorization.
 */
class KitchenDisplayTest extends TestCase
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

    // ── Helper ────────────────────────────────────────────────────────────────

    private function createKitchenOrder(string $status = 'pending'): KitchenOrder
    {
        $order = KitchenOrder::create([
            'order_number' => 'KDS-' . uniqid(),
            'order_type' => 'dine_in',
            'status' => $status,
            'table_number' => 'T1',
            'branch_id' => null,
        ]);

        KitchenOrderItem::create([
            'kitchen_order_id' => $order->id,
            'product_name' => 'Burger',
            'quantity' => 2,
            'status' => 'pending',
        ]);

        return $order;
    }

    // ── List / Stats ──────────────────────────────────────────────────────────

    #[Test]
    public function cashier_can_get_active_kitchen_orders(): void
    {
        $this->createKitchenOrder('pending');

        $this->actingAs($this->cashier)
            ->getJson('/api/kitchen')
            ->assertOk()
            ->assertJsonStructure(['orders', 'stats']);
    }

    #[Test]
    public function cashier_can_get_kitchen_stats(): void
    {
        $this->actingAs($this->cashier)
            ->getJson('/api/kitchen/stats')
            ->assertOk()
            ->assertJsonStructure([
                'pending', 'preparing', 'ready', 'avg_prep_min',
            ]);
    }

    #[Test]
    public function guest_cannot_access_kitchen(): void
    {
        $this->getJson('/api/kitchen')->assertUnauthorized();
    }

    // ── Create ───────────────────────────────────────────────────────────────

    #[Test]
    public function cashier_can_create_kitchen_order(): void
    {
        $res = $this->actingAs($this->cashier)
            ->postJson('/api/kitchen', [
                'order_type' => 'dine_in',
                'table_number' => 'T5',
                'items' => [
                    ['product_name' => 'Pizza', 'quantity' => 1],
                    ['product_name' => 'Salad', 'quantity' => 2],
                ],
            ]);

        $res->assertStatus(201)
            ->assertJsonStructure(['order', 'message']);

        $this->assertDatabaseHas('kitchen_orders', ['table_number' => 'T5']);
    }

    #[Test]
    public function kitchen_order_requires_items(): void
    {
        $this->actingAs($this->cashier)
            ->postJson('/api/kitchen', [
                'order_type' => 'takeaway',
                'items' => [],
            ])->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }

    #[Test]
    public function kitchen_order_requires_valid_type(): void
    {
        $this->actingAs($this->cashier)
            ->postJson('/api/kitchen', [
                'order_type' => 'invalid_type',
                'items' => [['product_name' => 'Burger', 'quantity' => 1]],
            ])->assertStatus(422)
            ->assertJsonValidationErrors(['order_type']);
    }

    // ── State Transitions ─────────────────────────────────────────────────────

    #[Test]
    public function cashier_can_accept_kitchen_order(): void
    {
        $order = $this->createKitchenOrder('pending');

        $this->actingAs($this->cashier)
            ->postJson("/api/kitchen/{$order->id}/accept")
            ->assertOk()
            ->assertJsonPath('order.status', 'preparing');
    }

    #[Test]
    public function cashier_can_mark_order_ready(): void
    {
        $order = $this->createKitchenOrder('preparing');

        $this->actingAs($this->cashier)
            ->postJson("/api/kitchen/{$order->id}/ready")
            ->assertOk()
            ->assertJsonPath('order.status', 'ready');
    }

    #[Test]
    public function cashier_can_mark_order_served(): void
    {
        $order = $this->createKitchenOrder('ready');

        $this->actingAs($this->cashier)
            ->postJson("/api/kitchen/{$order->id}/served")
            ->assertOk()
            ->assertJsonPath('order.status', 'served');
    }

    #[Test]
    public function cashier_can_cancel_kitchen_order(): void
    {
        $order = $this->createKitchenOrder('pending');

        $this->actingAs($this->cashier)
            ->postJson("/api/kitchen/{$order->id}/cancel")
            ->assertOk()
            ->assertJsonPath('order.status', 'cancelled');
    }

    // ── Item Status ──────────────────────────────────────────────────────────

    #[Test]
    public function cashier_can_update_item_status(): void
    {
        $order = $this->createKitchenOrder('preparing');
        $item = $order->items()->first();

        $this->actingAs($this->cashier)
            ->patchJson("/api/kitchen/items/{$item->id}/status", ['status' => 'done'])
            ->assertOk()
            ->assertJsonPath('item.status', 'done');
    }

    #[Test]
    public function item_status_must_be_valid(): void
    {
        $order = $this->createKitchenOrder('preparing');
        $item = $order->items()->first();

        $this->actingAs($this->cashier)
            ->patchJson("/api/kitchen/items/{$item->id}/status", ['status' => 'flying'])
            ->assertStatus(422);
    }
}
