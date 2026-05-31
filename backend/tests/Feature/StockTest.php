<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('admin');
    }

    /** @test */
    public function add_stock_creates_movement_record()
    {
        $product = Product::factory()->create(['quantity' => 5]);

        $this->actingAs($this->admin)
            ->postJson("/api/products/{$product->id}/add-stock", [
                'quantity' => 10,
                'reason' => 'شراء جديد',
            ])->assertStatus(200);

        $this->assertEquals(15, $product->fresh()->quantity);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'quantity' => 10,
            'movement_type' => 'add',
        ]);
    }

    /** @test */
    public function cannot_add_negative_stock()
    {
        $product = Product::factory()->create(['quantity' => 5]);

        $this->actingAs($this->admin)
            ->postJson("/api/products/{$product->id}/add-stock", ['quantity' => -5, 'reason' => 'test'])
            ->assertStatus(422);

        $this->assertEquals(5, $product->fresh()->quantity);
    }

    /** @test */
    public function stock_movement_has_employee_info()
    {
        $product = Product::factory()->create(['quantity' => 0]);

        $this->actingAs($this->admin)
            ->postJson("/api/products/{$product->id}/add-stock", ['quantity' => 20, 'reason' => 'تسليم']);

        $movement = StockMovement::where('product_id', $product->id)->first();
        $this->assertNotNull($movement->employee_id);
        $this->assertNotNull($movement->employee_name);
        $this->assertNotNull($movement->balance_after);
    }

    /** @test */
    public function stock_balance_after_is_correct()
    {
        $product = Product::factory()->create(['quantity' => 10]);

        $this->actingAs($this->admin)
            ->postJson("/api/products/{$product->id}/add-stock", ['quantity' => 5, 'reason' => 'test']);

        $movement = StockMovement::where('product_id', $product->id)->latest()->first();
        $this->assertEquals(15, $movement->balance_after);
    }

    /** @test */
    public function user_without_permission_cannot_add_stock()
    {
        $cashier = User::factory()->create(['is_active' => true]);
        $cashier->assignRole('cashier');
        $product = Product::factory()->create(['quantity' => 5]);

        $this->actingAs($cashier)
            ->postJson("/api/products/{$product->id}/add-stock", ['quantity' => 10, 'reason' => 'test'])
            ->assertStatus(403);

        $this->assertEquals(5, $product->fresh()->quantity);
    }
}
