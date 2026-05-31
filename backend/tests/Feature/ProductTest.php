<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    /** @test */
    public function user_without_permission_cannot_delete_product()
    {
        $cashier = User::factory()->create(['is_active' => true]);
        $cashier->assignRole('cashier');
        $product = Product::factory()->create();

        $response = $this->actingAs($cashier)->deleteJson("/api/products/{$product->id}");
        $response->assertStatus(403);
    }

    /** @test */
    public function product_quantity_cannot_go_negative()
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');
        $product = Product::factory()->create(['quantity' => 2]);

        $response = $this->actingAs($admin)->postJson('/api/invoices', [
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(422);
        $this->assertEquals(2, $product->fresh()->quantity); // لم تتغير
    }

    /** @test */
    public function stock_add_is_always_logged()
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');
        $product = Product::factory()->create(['quantity' => 0]);

        $this->actingAs($admin)->postJson("/api/products/{$product->id}/add-stock", [
            'quantity' => 20,
            'reason' => 'جرد دوري',
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'quantity' => 20,
        ]);
    }
}
