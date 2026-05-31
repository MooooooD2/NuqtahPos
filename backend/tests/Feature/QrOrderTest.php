<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\QrOrder;
use App\Models\QrTable;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * QR Self-Ordering — public menu browsing, order placement, status polling.
 */
class QrOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $cashier;
    private QrTable $table;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('admin');

        $this->cashier = User::factory()->create(['is_active' => true]);
        $this->cashier->assignRole('cashier');

        // QrTable uses 'table_name', not 'table_number'
        $this->table = QrTable::create([
            'table_name' => 'T10',
            'token' => 'test-token-abc123',
            'is_active' => true,
            'capacity' => 4,
        ]);
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }
        parent::tearDown();
    }

    // ── QR Table Management ───────────────────────────────────────────────────

    #[Test]
    public function cashier_can_generate_qr_table(): void
    {
        // Web route POST /qr-tables; controller validates 'table_name' (not 'table_number')
        $this->actingAs($this->cashier)
            ->postJson('/qr-tables', [
                'table_name' => 'T20',
                'capacity' => 4,
            ])->assertStatus(201);

        $this->assertDatabaseHas('qr_tables', ['table_name' => 'T20']);
    }

    // ── Public Menu (no auth) ─────────────────────────────────────────────────

    #[Test]
    public function guest_can_browse_qr_menu_with_valid_token(): void
    {
        Product::factory()->count(3)->create(['is_active' => true]);

        $this->getJson("/api/qr/{$this->table->token}/products")
            ->assertOk()
            ->assertJsonStructure(['products']);
    }

    #[Test]
    public function invalid_token_returns_404_on_menu(): void
    {
        $this->getJson('/api/qr/non-existent-token/products')
            ->assertNotFound();
    }

    // ── Place Order (no auth) ─────────────────────────────────────────────────

    #[Test]
    public function guest_can_place_qr_order(): void
    {
        $product = Product::factory()->create(['price' => 30.00, 'is_active' => true]);

        $res = $this->postJson("/api/qr/{$this->table->token}/order", [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ]);

        $res->assertStatus(201)
            ->assertJsonStructure(['order_id', 'status']);
    }

    #[Test]
    public function qr_order_requires_items(): void
    {
        $this->postJson("/api/qr/{$this->table->token}/order", ['items' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }

    #[Test]
    public function qr_order_with_invalid_token_fails(): void
    {
        $product = Product::factory()->create(['price' => 30.00, 'is_active' => true]);

        $this->postJson('/api/qr/bad-token/order', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertNotFound();
    }

    // ── Order Status (no auth) ────────────────────────────────────────────────

    #[Test]
    public function guest_can_poll_order_status(): void
    {
        // orderStatus() returns {status, order} — create a minimal QrOrder row
        $order = QrOrder::create([
            'qr_table_id' => $this->table->id,
            'status' => 'pending',
            'total' => 60.00,
        ]);

        $this->getJson("/api/qr/order/{$order->id}/status")
            ->assertOk()
            ->assertJsonStructure(['status', 'order']);
    }

    #[Test]
    public function non_existent_order_returns_404(): void
    {
        $this->getJson('/api/qr/order/99999/status')
            ->assertNotFound();
    }
}
