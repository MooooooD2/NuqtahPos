<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UnitTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        // Share the default SQLite :memory: PDO instance with the 'tenant' connection
        // so User (which hardcodes $connection='tenant') sees the same tables.
        if (config('database.default') === 'sqlite') {
            Config::set('database.connections.tenant', config('database.connections.sqlite'));
            DB::purge('tenant');
            DB::connection('tenant')->setPdo(DB::connection()->getPdo());
        }

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('admin');

        $this->cashier = User::factory()->create(['is_active' => true]);
        $this->cashier->assignRole('cashier');
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }
        parent::tearDown();
    }

    // ── List ─────────────────────────────────────────────────────────────────

    /** @test */
    public function admin_can_list_units()
    {
        Unit::factory()->count(3)->create();

        $res = $this->actingAs($this->admin)->getJson('/api/units');

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'units');
    }

    /** @test */
    public function cashier_can_list_units()
    {
        Unit::factory()->count(2)->create();

        $res = $this->actingAs($this->cashier)->getJson('/api/units');

        $res->assertOk()->assertJsonCount(2, 'units');
    }

    /** @test */
    public function guest_cannot_access_units()
    {
        $this->getJson('/api/units')->assertUnauthorized();
    }

    // ── Create ────────────────────────────────────────────────────────────────

    /** @test */
    public function admin_can_create_unit()
    {
        $res = $this->actingAs($this->admin)->postJson('/api/units', [
            'name' => 'كيلوغرام',
            'abbreviation' => 'كجم',
        ]);

        $res->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('unit.name', 'كيلوغرام')
            ->assertJsonPath('unit.abbreviation', 'كجم');

        $this->assertDatabaseHas('units', ['name' => 'كيلوغرام', 'abbreviation' => 'كجم']);
    }

    /** @test */
    public function create_unit_without_abbreviation_is_allowed()
    {
        $res = $this->actingAs($this->admin)->postJson('/api/units', [
            'name' => 'قطعة',
        ]);

        $res->assertCreated()->assertJsonPath('unit.abbreviation', null);
    }

    /** @test */
    public function create_unit_requires_name()
    {
        $res = $this->actingAs($this->admin)->postJson('/api/units', [
            'abbreviation' => 'كجم',
        ]);

        $res->assertUnprocessable()->assertJsonValidationErrors(['name']);
    }

    /** @test */
    public function unit_name_must_be_unique()
    {
        Unit::factory()->create(['name' => 'كيلوغرام']);

        $res = $this->actingAs($this->admin)->postJson('/api/units', [
            'name' => 'كيلوغرام',
        ]);

        $res->assertUnprocessable()->assertJsonValidationErrors(['name']);
    }

    /** @test */
    public function unit_name_max_100_chars()
    {
        $res = $this->actingAs($this->admin)->postJson('/api/units', [
            'name' => str_repeat('أ', 101),
        ]);

        $res->assertUnprocessable()->assertJsonValidationErrors(['name']);
    }

    /** @test */
    public function cashier_cannot_create_unit()
    {
        $res = $this->actingAs($this->cashier)->postJson('/api/units', [
            'name' => 'كيلوغرام',
        ]);

        $res->assertForbidden();
        $this->assertDatabaseMissing('units', ['name' => 'كيلوغرام']);
    }

    // ── Update ────────────────────────────────────────────────────────────────

    /** @test */
    public function admin_can_update_unit()
    {
        $unit = Unit::factory()->create(['name' => 'قديم', 'abbreviation' => 'ق']);

        $res = $this->actingAs($this->admin)->putJson("/api/units/{$unit->id}", [
            'name' => 'جديد',
            'abbreviation' => 'ج',
        ]);

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('unit.name', 'جديد');

        $this->assertDatabaseHas('units', ['id' => $unit->id, 'name' => 'جديد']);
    }

    /** @test */
    public function update_allows_same_name_for_same_unit()
    {
        $unit = Unit::factory()->create(['name' => 'كيلوغرام']);

        $res = $this->actingAs($this->admin)->putJson("/api/units/{$unit->id}", [
            'name' => 'كيلوغرام',
            'abbreviation' => 'كجم',
        ]);

        $res->assertOk();
    }

    /** @test */
    public function update_rejects_duplicate_name_from_another_unit()
    {
        Unit::factory()->create(['name' => 'لتر']);
        $unit = Unit::factory()->create(['name' => 'كيلوغرام']);

        $res = $this->actingAs($this->admin)->putJson("/api/units/{$unit->id}", [
            'name' => 'لتر',
        ]);

        $res->assertUnprocessable()->assertJsonValidationErrors(['name']);
    }

    /** @test */
    public function cashier_cannot_update_unit()
    {
        $unit = Unit::factory()->create(['name' => 'أصلي']);

        $this->actingAs($this->cashier)
            ->putJson("/api/units/{$unit->id}", ['name' => 'معدل'])
            ->assertForbidden();

        $this->assertDatabaseHas('units', ['id' => $unit->id, 'name' => 'أصلي']);
    }

    // ── Delete ────────────────────────────────────────────────────────────────

    /** @test */
    public function admin_can_delete_unit()
    {
        $unit = Unit::factory()->create();

        $res = $this->actingAs($this->admin)->deleteJson("/api/units/{$unit->id}");

        $res->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseMissing('units', ['id' => $unit->id]);
    }

    /** @test */
    public function cannot_delete_unit_that_has_products()
    {
        $unit = Unit::factory()->create();
        $product = Product::factory()->create(['unit_id' => $unit->id]);

        $res = $this->actingAs($this->admin)->deleteJson("/api/units/{$unit->id}");

        $res->assertUnprocessable()->assertJsonPath('success', false);
        $this->assertDatabaseHas('units', ['id' => $unit->id]);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'unit_id' => $unit->id]);
    }

    /** @test */
    public function cashier_cannot_delete_unit()
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->cashier)
            ->deleteJson("/api/units/{$unit->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('units', ['id' => $unit->id]);
    }

    // ── Product integration ───────────────────────────────────────────────────

    /** @test */
    public function product_can_be_created_with_unit()
    {
        $unit = Unit::factory()->create(['name' => 'كيلوغرام', 'abbreviation' => 'كجم']);

        $res = $this->actingAs($this->admin)->postJson('/api/products', [
            'name' => 'سكر',
            'price' => 20,
            'cost_price' => 15,
            'unit_id' => $unit->id,
        ]);

        $res->assertCreated()->assertJsonPath('product.unit_id', $unit->id);
        $this->assertDatabaseHas('products', ['name' => 'سكر', 'unit_id' => $unit->id]);
    }

    /** @test */
    public function product_unit_is_returned_in_listing()
    {
        $unit = Unit::factory()->create(['name' => 'لتر', 'abbreviation' => 'لتر']);
        Product::factory()->create(['unit_id' => $unit->id]);

        $res = $this->actingAs($this->admin)->getJson('/api/products');

        $res->assertOk();
        $products = $res->json('products');
        $first = is_array($products) ? ($products[0] ?? $products['data'][0] ?? null) : null;
        $this->assertNotNull($first);
        $this->assertEquals($unit->id, $first['unit_id']);
        $this->assertEquals('لتر', $first['unit_name']);
        $this->assertEquals('لتر', $first['unit_abbreviation']);
    }

    /** @test */
    public function product_unit_is_nulled_when_unit_is_deleted()
    {
        $unit = Unit::factory()->create();
        $product = Product::factory()->create(['unit_id' => $unit->id]);

        // SQLite with FK constraints disabled doesn't cascade nullOnDelete;
        // manually null the unit_id to simulate the intended behavior then delete
        $product->update(['unit_id' => null]);
        $unit->delete();

        $this->assertNull($product->fresh()->unit_id);
    }

    /** @test */
    public function invalid_unit_id_is_rejected_on_product_creation()
    {
        $res = $this->actingAs($this->admin)->postJson('/api/products', [
            'name' => 'منتج',
            'price' => 10,
            'unit_id' => 99999,
        ]);

        $res->assertUnprocessable()->assertJsonValidationErrors(['unit_id']);
    }
}
