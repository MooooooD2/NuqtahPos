<?php

namespace Tests\Unit\Services;

use App\Models\Product;
use App\Models\User;
use App\Services\StockService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    private StockService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create(['role' => 'admin', 'language' => 'ar']);
        $this->actingAs($user);
        $this->service = app(StockService::class);
    }

    public function test_adds_stock_and_logs_movement(): void
    {
        $product = Product::factory()->create(['quantity' => 10]);

        $this->service->addStock($product, 5, 'Manual addition');

        $this->assertEquals(15, $product->fresh()->quantity);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'movement_type' => 'add',
            'quantity' => 5,
        ]);
    }

    public function test_deducts_stock_and_logs_movement(): void
    {
        $product = Product::factory()->create(['quantity' => 10]);

        $this->service->deductStock($product, 4, 'sale', 'Sale deduction');

        $this->assertEquals(6, $product->fresh()->quantity);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'movement_type' => 'sale',
            'quantity' => 4,
        ]);
    }

    public function test_throws_exception_on_insufficient_stock(): void
    {
        $product = Product::factory()->create(['quantity' => 3]);

        $this->expectException(Exception::class);

        $this->service->deductStock($product, 10, 'sale', 'Should fail');
    }

    public function test_stock_cannot_go_negative(): void
    {
        $product = Product::factory()->create(['quantity' => 5]);

        try {
            $this->service->deductStock($product, 6, 'sale', 'Exceeds stock');
        } catch (Exception $e) {
            // expected
        }

        $this->assertGreaterThanOrEqual(0, $product->fresh()->quantity);
    }
}
