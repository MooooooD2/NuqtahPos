<?php

namespace Tests\Unit\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\SalesReturn;
use App\Models\User;
use App\Services\ReturnService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReturnServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReturnService $service;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('sequences')->whereIn('name', ['return', 'invoice'])->update(['value' => 0]);

        $user = User::factory()->create(['role' => 'cashier', 'language' => 'ar']);
        $this->actingAs($user);

        $this->service = app(ReturnService::class);
    }

    private function createInvoiceWithItem(int $qty = 5, float $price = 100.0): array
    {
        $product = Product::factory()->create(['quantity' => 0, 'price' => $price]);
        $invoice = Invoice::factory()->create();
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => $qty,
            'price' => $price,
            'subtotal' => $qty * $price,
        ]);

        return [$invoice, $product];
    }

    public function test_processes_valid_return(): void
    {
        [$invoice, $product] = $this->createInvoiceWithItem(5, 100.0);

        $return = $this->service->processReturn([
            'invoice_id' => $invoice->id,
            'reason' => 'Defective item',
            'items' => [[
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => 2,
                'price' => 100.0,
            ]],
        ]);

        $this->assertInstanceOf(SalesReturn::class, $return);
        $this->assertEquals(200.0, $return->total_amount);
        $this->assertStringStartsWith('RET-', $return->return_number);
    }

    public function test_restores_stock_on_return(): void
    {
        [$invoice, $product] = $this->createInvoiceWithItem(5);
        $initialQty = $product->quantity;

        $this->service->processReturn([
            'invoice_id' => $invoice->id,
            'items' => [[
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => 3,
                'price' => 100.0,
            ]],
        ]);

        $this->assertEquals($initialQty + 3, $product->fresh()->quantity);
    }

    public function test_throws_exception_when_return_exceeds_invoice_quantity(): void
    {
        [$invoice, $product] = $this->createInvoiceWithItem(2);

        $this->expectException(Exception::class);

        $this->service->processReturn([
            'invoice_id' => $invoice->id,
            'items' => [[
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => 99, // more than the 2 in invoice
                'price' => 100.0,
            ]],
        ]);
    }

    public function test_throws_exception_when_already_fully_returned(): void
    {
        [$invoice, $product] = $this->createInvoiceWithItem(2);

        // First return — full qty
        $this->service->processReturn([
            'invoice_id' => $invoice->id,
            'items' => [[
                'product_id' => $product->id, 'product_name' => $product->name,
                'quantity' => 2, 'price' => 100.0,
            ]],
        ]);

        // Second return should fail
        $this->expectException(Exception::class);

        $this->service->processReturn([
            'invoice_id' => $invoice->id,
            'items' => [[
                'product_id' => $product->id, 'product_name' => $product->name,
                'quantity' => 1, 'price' => 100.0,
            ]],
        ]);
    }
}
