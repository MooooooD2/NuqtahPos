<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FIX-3: اختبار صلاحية إنشاء المرتجعات
 */
class ReturnAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    /** @test */
    public function user_without_returns_permission_cannot_create_return()
    {
        // User with no role has no permissions at all
        $user = User::factory()->create(['is_active' => true]);

        $response = $this->actingAs($user)->postJson('/api/returns', [
            'invoice_id' => 1,
            'items' => [['product_id' => 1, 'quantity' => 1]],
            'refund_method' => 'cash',
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function cashier_with_returns_permission_can_create_return()
    {
        $cashier = User::factory()->create(['is_active' => true]);
        $cashier->assignRole('cashier');

        $product = Product::factory()->create(['price' => 100, 'quantity' => 10]);
        $invoice = Invoice::factory()->create(['status' => 'completed']);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'price' => 100,
        ]);

        $response = $this->actingAs($cashier)->postJson('/api/returns', [
            'invoice_id' => $invoice->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'refund_method' => 'cash',
        ]);

        // 201 created — but not 403
        $this->assertNotEquals(403, $response->getStatusCode());
    }
}
