<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\StockService;
use Database\Seeders\RolePermissionSeeder;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    private StockService $service;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->service = app(StockService::class);
        $this->user = User::factory()->create(['is_active' => true]);
        $this->user->assignRole('admin');
        $this->actingAs($this->user);
    }

    #[Test]
    public function add_stock_increases_quantity()
    {
        $product = Product::factory()->create(['quantity' => 5]);
        $this->service->addStock($product, 10, 'test');
        $this->assertEquals(15, $product->fresh()->quantity);
    }

    #[Test]
    public function deduct_stock_decreases_quantity()
    {
        $product = Product::factory()->create(['quantity' => 10]);
        $this->service->deductStock($product, 3, 'sale', 'test sale');
        $this->assertEquals(7, $product->fresh()->quantity);
    }

    #[Test]
    public function deduct_stock_throws_when_insufficient()
    {
        $product = Product::factory()->create(['quantity' => 2]);
        $this->expectException(Exception::class);
        $this->service->deductStock($product, 5, 'sale', 'test');
    }

    #[Test]
    public function every_stock_change_creates_movement()
    {
        $product = Product::factory()->create(['quantity' => 0]);
        $this->service->addStock($product, 20, 'initial stock');
        $this->assertDatabaseCount('stock_movements', 1);
        $this->service->deductStock($product, 5, 'sale', 'sold item');
        $this->assertDatabaseCount('stock_movements', 2);
    }

    #[Test]
    public function balance_after_is_accurate()
    {
        $product = Product::factory()->create(['quantity' => 10]);
        $this->service->addStock($product, 5, 'purchase');

        $movement = StockMovement::latest()->first();
        $this->assertEquals(15, $movement->balance_after);
    }

    #[Test]
    public function adjust_stock_logs_difference()
    {
        $product = Product::factory()->create(['quantity' => 10]);
        $this->service->adjustStock($product, 7, 'جرد دوري');

        $this->assertEquals(7, $product->fresh()->quantity);
        $movement = StockMovement::where('product_id', $product->id)->latest()->first();
        $this->assertStringContainsString('adjustment', $movement->movement_type);
        $this->assertEquals(3, $movement->quantity); // الفرق
    }
}
