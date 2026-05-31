<?php

namespace Tests\Unit\Services;

use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use App\Services\InvoiceService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    private InvoiceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('sequences')->where('name', 'invoice')->update(['value' => 0]);

        $user = User::factory()->create(['role' => 'cashier', 'language' => 'ar']);
        $this->actingAs($user);

        $this->service = app(InvoiceService::class);
    }

    public function test_creates_invoice_successfully(): void
    {
        $product = Product::factory()->create(['quantity' => 10, 'price' => 100.00]);

        $invoice = $this->service->createInvoice([
            'payment_method' => 'cash',
            'discount' => 0,
            'items' => [[
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => 2,
                'price' => 100.00,
            ]],
        ]);

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertEquals(200.00, $invoice->total);
        $this->assertEquals(200.00, $invoice->final_total);
        $this->assertStringStartsWith('INV-', $invoice->invoice_number);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);
    }

    public function test_deducts_stock_on_invoice_creation(): void
    {
        $product = Product::factory()->create(['quantity' => 10, 'price' => 50.00]);

        $this->service->createInvoice([
            'payment_method' => 'cash',
            'discount' => 0,
            'items' => [[
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => 3,
                'price' => 50.00,
            ]],
        ]);

        $this->assertEquals(7, $product->fresh()->quantity);
    }

    public function test_applies_discount_correctly(): void
    {
        $product = Product::factory()->create(['quantity' => 10, 'price' => 200.00]);

        $invoice = $this->service->createInvoice([
            'payment_method' => 'cash',
            'discount' => 50,
            'items' => [[
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => 1,
                'price' => 200.00,
            ]],
        ]);

        $this->assertEquals(200.00, $invoice->total);
        $this->assertEquals(50.00, $invoice->discount);
        $this->assertEquals(150.00, $invoice->final_total);
    }

    public function test_throws_exception_on_insufficient_stock(): void
    {
        $product = Product::factory()->create(['quantity' => 2, 'price' => 100.00]);

        $this->expectException(Exception::class);

        $this->service->createInvoice([
            'payment_method' => 'cash',
            'discount' => 0,
            'items' => [[
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => 99,
                'price' => 100.00,
            ]],
        ]);
    }

    public function test_invoice_number_is_unique_for_each_invoice(): void
    {
        $product = Product::factory()->create(['quantity' => 100, 'price' => 10.00]);

        $itemData = [
            'payment_method' => 'cash',
            'discount' => 0,
            'items' => [[
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => 1,
                'price' => 10.00,
            ]],
        ];

        $inv1 = $this->service->createInvoice($itemData);
        $inv2 = $this->service->createInvoice($itemData);
        $inv3 = $this->service->createInvoice($itemData);

        $this->assertNotEquals($inv1->invoice_number, $inv2->invoice_number);
        $this->assertNotEquals($inv2->invoice_number, $inv3->invoice_number);
    }

    public function test_creates_invoice_items_correctly(): void
    {
        $p1 = Product::factory()->create(['quantity' => 10, 'price' => 100.00]);
        $p2 = Product::factory()->create(['quantity' => 10, 'price' => 50.00]);

        $invoice = $this->service->createInvoice([
            'payment_method' => 'cash',
            'discount' => 0,
            'items' => [
                ['product_id' => $p1->id, 'product_name' => $p1->name, 'quantity' => 2, 'price' => 100.00],
                ['product_id' => $p2->id, 'product_name' => $p2->name, 'quantity' => 3, 'price' => 50.00],
            ],
        ]);

        $this->assertCount(2, $invoice->items);
        $this->assertEquals(350.00, $invoice->total); // (2×100) + (3×50)
    }
}
