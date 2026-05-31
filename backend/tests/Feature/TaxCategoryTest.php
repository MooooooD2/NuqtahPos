<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\TaxCategory;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tax Category CRUD + compliance reports.
 */
class TaxCategoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();
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

    private function makeTaxCategory(string $code, float $rate = 5.0, bool $isDefault = false): TaxCategory
    {
        return TaxCategory::create([
            'name_ar' => "ضريبة {$code}",
            'name_en' => "Tax {$code}",
            'code' => $code,
            'rate' => $rate,
            'is_default' => $isDefault,
            'is_active' => true,
        ]);
    }

    // ── List ─────────────────────────────────────────────────────────────────

    #[Test]
    public function authenticated_user_can_list_tax_categories(): void
    {
        $this->makeTaxCategory('TC1');
        $this->makeTaxCategory('TC2');
        $this->makeTaxCategory('TC3');

        $this->actingAs($this->cashier)
            ->getJson('/api/tax-categories')
            ->assertOk()
            ->assertJsonCount(3);
    }

    #[Test]
    public function guest_cannot_list_tax_categories(): void
    {
        $this->getJson('/api/tax-categories')->assertUnauthorized();
    }

    // ── Create ───────────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_create_tax_category(): void
    {
        $res = $this->actingAs($this->admin)
            ->postJson('/api/tax-categories', [
                'name_ar' => 'ضريبة القيمة المضافة',
                'name_en' => 'VAT',
                'code' => 'VAT14',
                'rate' => 14,
                'is_default' => false,
            ]);

        $res->assertStatus(201);
        $this->assertDatabaseHas('tax_categories', ['code' => 'VAT14']);
    }

    #[Test]
    public function tax_code_must_be_unique(): void
    {
        $this->makeTaxCategory('DUPE');

        $this->actingAs($this->admin)
            ->postJson('/api/tax-categories', [
                'name_ar' => 'ضريبة',
                'name_en' => 'Tax',
                'code' => 'DUPE',
                'rate' => 5,
            ])->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    #[Test]
    public function rate_must_be_0_to_100(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/tax-categories', [
                'name_ar' => 'فئة',
                'name_en' => 'Cat',
                'code' => 'CAT1',
                'rate' => 150,
            ])->assertStatus(422)
            ->assertJsonValidationErrors(['rate']);
    }

    #[Test]
    public function creating_default_tax_unsets_previous_default(): void
    {
        $this->makeTaxCategory('OLD_DEF', 5.0, true);

        $this->actingAs($this->admin)
            ->postJson('/api/tax-categories', [
                'name_ar' => 'جديد',
                'name_en' => 'New Default',
                'code' => 'NEW_DEF',
                'rate' => 10,
                'is_default' => true,
            ])->assertStatus(201);

        $this->assertDatabaseHas('tax_categories', ['code' => 'OLD_DEF', 'is_default' => 0]);
    }

    // ── Update ───────────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_update_tax_category(): void
    {
        $tax = $this->makeTaxCategory('UPD1', 5.0);

        $res = $this->actingAs($this->admin)
            ->putJson("/api/tax-categories/{$tax->id}", ['rate' => 10])
            ->assertOk();

        // MySQL returns DECIMAL as string; cast to float for comparison
        $this->assertEquals(10.0, (float) $res->json('rate'));
    }

    // ── Delete ───────────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_delete_non_default_tax(): void
    {
        $tax = $this->makeTaxCategory('DEL1', 5.0, false);

        $this->actingAs($this->admin)
            ->deleteJson("/api/tax-categories/{$tax->id}")
            ->assertNoContent();
    }

    #[Test]
    public function cannot_delete_default_tax_category(): void
    {
        $tax = $this->makeTaxCategory('DEF1', 5.0, true);

        $this->actingAs($this->admin)
            ->deleteJson("/api/tax-categories/{$tax->id}")
            ->assertStatus(422);
    }

    #[Test]
    public function cashier_cannot_create_tax_category(): void
    {
        $this->actingAs($this->cashier)
            ->postJson('/api/tax-categories', [
                'name_ar' => 'ضريبة',
                'name_en' => 'Tax',
                'code' => 'T99',
                'rate' => 5,
            ])->assertForbidden();
    }

    // ── Reports ──────────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_get_tax_report(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/reports/tax', [
                'from' => now()->startOfMonth()->toDateString(),
                'to' => now()->endOfMonth()->toDateString(),
            ])->assertOk()
            ->assertJsonStructure(['from', 'to', 'by_rate', 'totals']);
    }

    #[Test]
    public function tax_report_requires_date_range(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/reports/tax', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['from', 'to']);
    }

    #[Test]
    public function admin_can_get_monthly_tax_report(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/reports/tax/monthly?year=' . now()->year)
            ->assertOk()
            ->assertJsonStructure(['year', 'months', 'totals']);
    }

    #[Test]
    public function monthly_tax_report_requires_valid_year(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/reports/tax/monthly?year=1999')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['year']);
    }
}
