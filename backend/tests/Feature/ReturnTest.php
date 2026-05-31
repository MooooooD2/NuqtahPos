<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReturnTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private Product $product;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->cashier = User::factory()->create(['is_active' => true]);
        $this->cashier->assignRole('cashier');

        $this->product = Product::factory()->create(['price' => 100.00, 'quantity' => 10]);

        // إنشاء فاتورة مكتملة مع عنصر
        $this->invoice = Invoice::factory()->create([
            'status' => 'completed',
            'total' => 200.00,
            'final_total' => 200.00,
            'cashier_id' => $this->cashier->id,
        ]);
        InvoiceItem::factory()->create([
            'invoice_id' => $this->invoice->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'quantity' => 2,
            'price' => 100.00,
            'subtotal' => 200.00,
        ]);
    }

    /** @test */
    public function can_return_items_from_completed_invoice()
    {
        $this->actingAs($this->cashier)
            ->postJson('/api/returns', [
                'invoice_id' => $this->invoice->id,
                'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
                'reason' => 'منتج تالف',
                'refund_method' => 'cash',
            ])->assertStatus(201);
    }

    /** @test */
    public function return_restores_stock()
    {
        $initialQty = $this->product->quantity;

        $this->actingAs($this->cashier)->postJson('/api/returns', [
            'invoice_id' => $this->invoice->id,
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            'refund_method' => 'cash',
        ]);

        $this->assertEquals($initialQty + 1, $this->product->fresh()->quantity);
    }

    /** @test */
    public function cannot_return_more_than_purchased()
    {
        $this->actingAs($this->cashier)
            ->postJson('/api/returns', [
                'invoice_id' => $this->invoice->id,
                'items' => [['product_id' => $this->product->id, 'quantity' => 99]],
            ])->assertStatus(422);
    }

    /** @test */
    public function return_price_comes_from_original_invoice_not_user()
    {
        $this->actingAs($this->cashier)->postJson('/api/returns', [
            'invoice_id' => $this->invoice->id,
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            'refund_method' => 'cash',
        ]);

        // التحقق أن السعر في المرتجع = السعر في الفاتورة الأصلية (100)
        $this->assertDatabaseHas('return_items', [
            'product_id' => $this->product->id,
            'quantity' => 1,
            'price' => 100.00,
        ]);
    }

    /** @test */
    public function cannot_return_from_cancelled_invoice()
    {
        $cancelled = Invoice::factory()->create(['status' => 'cancelled']);
        InvoiceItem::factory()->create(['invoice_id' => $cancelled->id, 'product_id' => $this->product->id]);

        $this->actingAs($this->cashier)
            ->postJson('/api/returns', [
                'invoice_id' => $cancelled->id,
                'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            ])->assertStatus(422);
    }
}
