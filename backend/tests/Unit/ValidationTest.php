<?php

namespace Tests\Unit;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    #[Test]
    public function invoice_requires_at_least_one_item()
    {
        $cashier = User::factory()->create(['is_active' => true]);
        $cashier->assignRole('cashier');

        $this->actingAs($cashier)
            ->postJson('/api/invoices', ['items' => [], 'payment_method' => 'cash'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }

    #[Test]
    public function invalid_payment_method_is_rejected()
    {
        $cashier = User::factory()->create(['is_active' => true]);
        $cashier->assignRole('cashier');

        $this->actingAs($cashier)
            ->postJson('/api/invoices', [
                'items' => [['product_id' => 1, 'quantity' => 1]],
                'payment_method' => 'bitcoin',  // غير مسموح
            ])->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method']);
    }

    #[Test]
    public function product_search_query_has_max_length()
    {
        $cashier = User::factory()->create(['is_active' => true]);
        $cashier->assignRole('cashier');

        $longQuery = str_repeat('a', 101); // 101 حرف > الحد 100
        $this->actingAs($cashier)
            ->getJson("/api/search-product?query={$longQuery}")
            ->assertStatus(422);
    }

    #[Test]
    public function future_invoice_date_is_rejected()
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->postJson('/api/journal-entries', [
                'entry_date' => now()->addDays(10)->toDateString(),
                'lines' => [
                    ['account_id' => 1, 'debit' => 100, 'credit' => 0],
                    ['account_id' => 2, 'debit' => 0, 'credit' => 100],
                ],
            ])->assertStatus(422)
            ->assertJsonValidationErrors(['entry_date']);
    }

    #[Test]
    public function journal_entry_must_be_balanced()
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->postJson('/api/journal-entries', [
                'entry_date' => now()->toDateString(),
                'lines' => [
                    ['account_id' => 1, 'debit' => 100, 'credit' => 0],
                    ['account_id' => 2, 'debit' => 0,   'credit' => 50],  // غير متوازن
                ],
            ])->assertStatus(422);
    }
}
