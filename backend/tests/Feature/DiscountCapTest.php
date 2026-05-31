<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * FIX-2: اختبار حد الخصم الأقصى
 */
class DiscountCapTest extends TestCase
{
    use RefreshDatabase;

    protected User $cashier;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->cashier = User::factory()->create(['is_active' => true]);
        $this->cashier->assignRole('cashier');

        $this->product = Product::factory()->create([
            'price' => 100,
            'quantity' => 50,
        ]);

        // إعداد حد الخصم بـ 20%
        Setting::updateOrCreate(['key' => 'max_discount_percent'], [
            'value' => '20',
            'type' => 'number',
            'group' => 'pos',
        ]);
    }

    /** @test */
    public function discount_within_limit_is_accepted()
    {
        $response = $this->actingAs($this->cashier)->postJson('/api/invoices', [
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            'discount' => 15, // 15% من 100 = مسموح (الحد 20%)
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('invoices', ['discount' => 15.0]);
    }

    /** @test */
    public function discount_exceeding_limit_is_rejected()
    {
        $response = $this->actingAs($this->cashier)->postJson('/api/invoices', [
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            'discount' => 50, // 50% من 100 — يتجاوز الحد 20%
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('invoices', ['discount' => 50.0]);
    }

    /** @test */
    public function zero_discount_is_always_accepted()
    {
        $response = $this->actingAs($this->cashier)->postJson('/api/invoices', [
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            'discount' => 0,
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(201);
    }

    /** @test */
    public function discount_cap_respects_settings_change()
    {
        // تغيير الحد إلى 10%
        Setting::where('key', 'max_discount_percent')->update(['value' => '10']);
        Cache::flush();

        $response = $this->actingAs($this->cashier)->postJson('/api/invoices', [
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            'discount' => 15, // 15 > 10% من 100
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(422);
    }
}
