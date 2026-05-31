<?php

namespace Tests\Feature\Finance;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * TC-BUDGET: Budget management — upsert, conflict handling, budget-vs-actual report.
 */
class BudgetTest extends TestCase
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

    #[Test]
    public function admin_can_create_revenue_budget(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/budgets', [
            'entries' => [
                ['year' => 2026, 'month' => 1, 'type' => 'revenue', 'category' => null, 'amount' => 50000.00],
            ],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('budgets', ['year' => 2026, 'month' => 1, 'type' => 'revenue', 'amount' => 50000.00]);
    }

    #[Test]
    public function admin_can_create_expense_budget_by_category(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/budgets', [
            'entries' => [
                ['year' => 2026, 'month' => 1, 'type' => 'expense', 'category' => 'رواتب', 'amount' => 10000.00],
                ['year' => 2026, 'month' => 1, 'type' => 'expense', 'category' => 'إيجار',  'amount' => 3000.00],
            ],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('budgets', ['category' => 'رواتب', 'amount' => 10000.00]);
        $this->assertDatabaseHas('budgets', ['category' => 'إيجار',  'amount' => 3000.00]);
    }

    #[Test]
    public function upserting_budget_updates_existing_entry(): void
    {
        // Create initial budget
        $this->actingAs($this->admin)->postJson('/api/budgets', [
            'entries' => [['year' => 2026, 'month' => 3, 'type' => 'revenue', 'category' => null, 'amount' => 40000.00]],
        ]);

        // Update with new amount
        $this->actingAs($this->admin)->postJson('/api/budgets', [
            'entries' => [['year' => 2026, 'month' => 3, 'type' => 'revenue', 'category' => null, 'amount' => 55000.00]],
        ]);

        $this->assertDatabaseHas('budgets', ['year' => 2026, 'month' => 3, 'type' => 'revenue', 'amount' => 55000.00]);
        $this->assertDatabaseMissing('budgets', ['year' => 2026, 'month' => 3, 'type' => 'revenue', 'amount' => 40000.00]);
    }

    #[Test]
    public function budget_amount_cannot_be_negative(): void
    {
        $this->actingAs($this->admin)->postJson('/api/budgets', [
            'entries' => [['year' => 2026, 'month' => 1, 'type' => 'revenue', 'category' => null, 'amount' => -1000.00]],
        ])->assertStatus(422);
    }

    #[Test]
    public function budget_type_must_be_revenue_or_expense(): void
    {
        $this->actingAs($this->admin)->postJson('/api/budgets', [
            'entries' => [['year' => 2026, 'month' => 1, 'type' => 'profit', 'category' => null, 'amount' => 1000.00]],
        ])->assertStatus(422);
    }

    #[Test]
    public function cashier_cannot_create_budget(): void
    {
        $this->actingAs($this->cashier)->postJson('/api/budgets', [
            'entries' => [['year' => 2026, 'month' => 1, 'type' => 'revenue', 'category' => null, 'amount' => 1000.00]],
        ])->assertStatus(403);
    }

    #[Test]
    public function budget_vs_actual_report_returns_data_for_year(): void
    {
        $this->actingAs($this->admin)->postJson('/api/budgets', [
            'entries' => [['year' => 2026, 'month' => null, 'type' => 'revenue', 'category' => null, 'amount' => 100000.00]],
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/reports/budget-vs-actual?year=2026');

        $response->assertStatus(200);
    }

    #[Test]
    public function budget_vs_actual_filtered_by_month(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/reports/budget-vs-actual?year=2026&month=1');
        $response->assertStatus(200);
    }

    #[Test]
    public function budget_month_must_be_1_to_12(): void
    {
        $this->actingAs($this->admin)->postJson('/api/budgets', [
            'entries' => [['year' => 2026, 'month' => 13, 'type' => 'revenue', 'category' => null, 'amount' => 1000.00]],
        ])->assertStatus(422);
    }
}
