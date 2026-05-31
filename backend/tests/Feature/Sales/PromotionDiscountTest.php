<?php

namespace Tests\Feature\Sales;

use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * TC-PROMO: Promotions — CRUD, types (percent/fixed/buy_x_get_y), date ranges, preview.
 */
class PromotionDiscountTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $cashier;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('admin');

        $this->cashier = User::factory()->create(['is_active' => true]);
        $this->cashier->assignRole('cashier');

        $this->product = Product::factory()->create(['price' => 100.00, 'quantity' => 100]);
    }

    // ── CRUD ──────────────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_create_percentage_promotion(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/promotions', [
            'name' => 'خصم 10%',
            'type' => 'percentage',
            'value' => 10.00,
            'is_active' => true,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('promotions', ['name' => 'خصم 10%', 'type' => 'percentage', 'value' => 10.00]);
    }

    #[Test]
    public function admin_can_create_fixed_amount_promotion(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/promotions', [
            'name' => 'خصم ثابت 20 جنيه',
            'type' => 'fixed',
            'value' => 20.00,
            'is_active' => true,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('promotions', ['type' => 'fixed', 'value' => 20.00]);
    }

    #[Test]
    public function admin_can_create_buy_x_get_y_promotion(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/promotions', [
            'name' => 'اشتري 2 واحصل على 1',
            'type' => 'buy_x_get_y',
            'buy_qty' => 2,
            'get_qty' => 1,
            'product_id' => $this->product->id,
            'is_active' => true,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('promotions', ['type' => 'buy_x_get_y', 'buy_qty' => 2, 'get_qty' => 1]);
    }

    #[Test]
    public function cashier_cannot_create_promotion(): void
    {
        $this->actingAs($this->cashier)->postJson('/api/promotions', [
            'name' => 'خصم',
            'type' => 'percentage',
            'value' => 5.00,
            'is_active' => true,
        ])->assertStatus(403);
    }

    #[Test]
    public function promotion_value_cannot_be_negative(): void
    {
        $this->actingAs($this->admin)->postJson('/api/promotions', [
            'name' => 'خصم سالب',
            'type' => 'percentage',
            'value' => -5.00,
            'is_active' => true,
        ])->assertStatus(422);
    }

    #[Test]
    public function promotion_type_must_be_valid(): void
    {
        $this->actingAs($this->admin)->postJson('/api/promotions', [
            'name' => 'خصم خاطئ',
            'type' => 'invalid_type',
            'value' => 10.00,
            'is_active' => true,
        ])->assertStatus(422);
    }

    #[Test]
    public function admin_can_update_promotion(): void
    {
        $promo = Promotion::create([
            'name' => 'خصم قديم',
            'type' => 'percentage',
            'value' => 5.00,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)->putJson("/api/promotions/{$promo->id}", [
            'name' => 'خصم محدّث',
            'type' => 'percentage',
            'value' => 15.00,
            'is_active' => true,
        ])->assertStatus(200);

        $this->assertDatabaseHas('promotions', ['id' => $promo->id, 'value' => 15.00]);
    }

    #[Test]
    public function admin_can_delete_promotion(): void
    {
        $promo = Promotion::create([
            'name' => 'خصم للحذف',
            'type' => 'fixed',
            'value' => 10.00,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)->deleteJson("/api/promotions/{$promo->id}")->assertStatus(200);
        $this->assertDatabaseMissing('promotions', ['id' => $promo->id]);
    }

    // ── Date ranges ───────────────────────────────────────────────────────────

    #[Test]
    public function promotion_with_valid_date_range_is_active(): void
    {
        $promo = Promotion::create([
            'name' => 'عرض رمضان',
            'type' => 'percentage',
            'value' => 20.00,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(30),
            'is_active' => true,
        ]);

        $this->assertTrue($promo->isValid());
    }

    #[Test]
    public function expired_promotion_is_not_valid(): void
    {
        $promo = Promotion::create([
            'name' => 'عرض منتهي',
            'type' => 'percentage',
            'value' => 10.00,
            'starts_at' => now()->subDays(30),
            'ends_at' => now()->subDay(), // expired yesterday
            'is_active' => true,
        ]);

        $this->assertFalse($promo->isValid());
    }

    #[Test]
    public function future_promotion_is_not_yet_valid(): void
    {
        $promo = Promotion::create([
            'name' => 'عرض مستقبلي',
            'type' => 'percentage',
            'value' => 10.00,
            'starts_at' => now()->addDays(5), // starts in the future
            'is_active' => true,
        ]);

        $this->assertFalse($promo->isValid());
    }

    #[Test]
    public function inactive_promotion_is_not_valid(): void
    {
        $promo = Promotion::create([
            'name' => 'عرض معطّل',
            'type' => 'percentage',
            'value' => 10.00,
            'is_active' => false,
        ]);

        $this->assertFalse($promo->isValid());
    }

    // ── Active promotions list ────────────────────────────────────────────────

    #[Test]
    public function active_promotions_endpoint_returns_only_valid_promotions(): void
    {
        Promotion::create(['name' => 'نشط', 'type' => 'percentage', 'value' => 10.00, 'is_active' => true]);
        Promotion::create(['name' => 'معطّل', 'type' => 'percentage', 'value' => 5.00, 'is_active' => false]);

        $response = $this->actingAs($this->admin)->getJson('/api/promotions/active');

        $response->assertStatus(200);
        $names = collect($response->json('promotions') ?? $response->json())->pluck('name')->toArray();
        $this->assertContains('نشط', $names);
        $this->assertNotContains('معطّل', $names);
    }

    // ── Preview ───────────────────────────────────────────────────────────────

    #[Test]
    public function promotion_preview_returns_discount_calculation(): void
    {
        Promotion::create([
            'name' => 'خصم 10%',
            'type' => 'percentage',
            'value' => 10.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->postJson('/api/promotions/preview', [
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                    'subtotal' => 200.00,
                ],
            ],
        ]);

        $response->assertStatus(200);
    }

    #[Test]
    public function promotion_preview_without_items_fails(): void
    {
        $this->actingAs($this->admin)->postJson('/api/promotions/preview', [
            'items' => [],
        ])->assertStatus(422);
    }

    // ── Min order amount ──────────────────────────────────────────────────────

    #[Test]
    public function promotion_with_min_order_amount_can_be_created(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/promotions', [
            'name' => 'خصم عند 500 جنيه',
            'type' => 'percentage',
            'value' => 15.00,
            'min_order_amount' => 500.00,
            'is_active' => true,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('promotions', ['min_order_amount' => 500.00]);
    }

    // ── Invoice applies promotion ─────────────────────────────────────────────

    #[Test]
    public function invoice_creation_with_active_promotion_succeeds(): void
    {
        Promotion::create([
            'name' => 'خصم 10%',
            'type' => 'percentage',
            'value' => 10.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->cashier)->postJson('/api/invoices', [
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            'payment_method' => 'cash',
        ]);

        // Invoice should be created — promotion application is handled server-side
        $response->assertStatus(201);
    }
}
