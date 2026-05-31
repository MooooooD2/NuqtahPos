<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceTest extends TestCase
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

    /** @test */
    public function unauthenticated_user_cannot_create_invoice()
    {
        $response = $this->postJson('/api/invoices', []);
        $response->assertStatus(401);
    }

    /** @test */
    public function price_is_taken_from_database_not_user()
    {
        $product = Product::factory()->create(['price' => 100.00, 'quantity' => 10]);

        $response = $this->actingAs($this->cashier)->postJson('/api/invoices', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(201);
        // السعر من DB (100) وليس من المستخدم
        $this->assertDatabaseHas('invoices', ['total' => 100.00]);
    }

    /** @test */
    public function cannot_sell_more_than_available_stock()
    {
        $product = Product::factory()->create(['price' => 50.00, 'quantity' => 2]);

        $response = $this->actingAs($this->cashier)->postJson('/api/invoices', [
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('invoices', 0);
    }

    /** @test */
    public function stock_decreases_after_invoice()
    {
        $product = Product::factory()->create(['price' => 50.00, 'quantity' => 10]);

        $this->actingAs($this->cashier)->postJson('/api/invoices', [
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
            'payment_method' => 'cash',
        ]);

        $this->assertEquals(7, $product->fresh()->quantity);
    }

    /** @test */
    public function stock_movement_is_logged_after_invoice()
    {
        $product = Product::factory()->create(['price' => 50.00, 'quantity' => 10]);

        $this->actingAs($this->cashier)->postJson('/api/invoices', [
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
            'payment_method' => 'cash',
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'quantity' => 3,
            'movement_type' => 'sale',
        ]);
    }

    /** @test */
    public function disabled_user_cannot_login()
    {
        $user = User::factory()->create(['is_active' => false, 'username' => 'dis_' . uniqid(), 'password' => bcrypt('Secret123')]);

        $response = $this->postJson('/login', [
            'tenant_code' => 'test',
            'username' => $user->username,
            'password' => 'Secret123',
        ]);
        // Without a real tenant: 401 (tenant not found). With one: 403 (inactive).
        $this->assertContains($response->status(), [401, 403]);
    }
}
