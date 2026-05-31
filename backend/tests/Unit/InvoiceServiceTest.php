<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\User;
use App\Services\InvoiceService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function discount_cannot_exceed_total()
    {
        $product = Product::factory()->create(['price' => 100.00, 'quantity' => 5]);

        $service = app(InvoiceService::class);
        $this->actingAs(User::factory()->create());

        // خصم أكبر من الإجمالي — يجب أن يرفضه النظام
        $this->expectException(Exception::class);

        $service->createInvoice([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'discount' => 999, // أكبر من الحد المسموح
            'payment_method' => 'cash',
        ]);
    }
}
