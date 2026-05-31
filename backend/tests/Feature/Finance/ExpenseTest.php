<?php

namespace Tests\Feature\Finance;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * TC-EXP: Expense management — CRUD, categories, payment methods, validation.
 */
class ExpenseTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $cashier;

    private int $categoryId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('admin');

        $this->cashier = User::factory()->create(['is_active' => true]);
        $this->cashier->assignRole('cashier');

        // Create an expense category
        $this->categoryId = DB::table('expense_categories')->insertGetId([
            'name' => 'مصروفات إدارية',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ── Create expense ────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_create_expense(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/expenses', [
            'category_id' => $this->categoryId,
            'title' => 'إيجار المكتب',
            'amount' => 2000.00,
            'payment_method' => 'transfer',
            'expense_date' => now()->toDateString(),
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('expenses', ['title' => 'إيجار المكتب', 'amount' => 2000.00]);
    }

    #[Test]
    public function expense_requires_valid_category(): void
    {
        $this->actingAs($this->admin)->postJson('/api/expenses', [
            'category_id' => 99999,
            'title' => 'مصروف خطأ',
            'amount' => 100.00,
            'payment_method' => 'cash',
            'expense_date' => now()->toDateString(),
        ])->assertStatus(422);
    }

    #[Test]
    public function expense_amount_must_be_positive(): void
    {
        $this->actingAs($this->admin)->postJson('/api/expenses', [
            'category_id' => $this->categoryId,
            'title' => 'مصروف صفر',
            'amount' => 0,
            'payment_method' => 'cash',
            'expense_date' => now()->toDateString(),
        ])->assertStatus(422);
    }

    #[Test]
    public function expense_amount_cannot_be_negative(): void
    {
        $this->actingAs($this->admin)->postJson('/api/expenses', [
            'category_id' => $this->categoryId,
            'title' => 'مصروف سالب',
            'amount' => -500.00,
            'payment_method' => 'cash',
            'expense_date' => now()->toDateString(),
        ])->assertStatus(422);
    }

    #[Test]
    public function expense_requires_valid_payment_method(): void
    {
        $this->actingAs($this->admin)->postJson('/api/expenses', [
            'category_id' => $this->categoryId,
            'title' => 'مصروف',
            'amount' => 100.00,
            'payment_method' => 'crypto',
            'expense_date' => now()->toDateString(),
        ])->assertStatus(422);
    }

    #[Test]
    public function all_payment_methods_accepted(): void
    {
        foreach (['cash', 'card', 'transfer', 'wallet'] as $method) {
            $response = $this->actingAs($this->admin)->postJson('/api/expenses', [
                'category_id' => $this->categoryId,
                'title' => "مصروف {$method}",
                'amount' => 100.00,
                'payment_method' => $method,
                'expense_date' => now()->toDateString(),
            ]);
            $response->assertStatus(201);
        }
    }

    #[Test]
    public function cashier_cannot_create_expense(): void
    {
        $this->actingAs($this->cashier)->postJson('/api/expenses', [
            'category_id' => $this->categoryId,
            'title' => 'مصروف غير مسموح',
            'amount' => 100.00,
            'payment_method' => 'cash',
            'expense_date' => now()->toDateString(),
        ])->assertStatus(403);
    }

    // ── Update & Delete ───────────────────────────────────────────────────────

    #[Test]
    public function admin_can_update_expense(): void
    {
        $id = DB::table('expenses')->insertGetId([
            'expense_number' => 'EXP-UPD-' . uniqid(),
            'category_id' => $this->categoryId,
            'title' => 'مصروف قديم',
            'amount' => 300.00,
            'payment_method' => 'cash',
            'expense_date' => now()->toDateString(),
            'created_by' => $this->admin->id,
            'created_by_name' => $this->admin->full_name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->admin)->putJson("/api/expenses/{$id}", [
            'category_id' => $this->categoryId,
            'title' => 'مصروف محدّث',
            'amount' => 350.00,
            'payment_method' => 'transfer',
            'expense_date' => now()->toDateString(),
        ])->assertStatus(200);

        $this->assertDatabaseHas('expenses', ['id' => $id, 'title' => 'مصروف محدّث', 'amount' => 350.00]);
    }

    #[Test]
    public function admin_can_delete_expense(): void
    {
        $id = DB::table('expenses')->insertGetId([
            'expense_number' => 'EXP-DEL-' . uniqid(),
            'category_id' => $this->categoryId,
            'title' => 'مصروف للحذف',
            'amount' => 100.00,
            'payment_method' => 'cash',
            'expense_date' => now()->toDateString(),
            'created_by' => $this->admin->id,
            'created_by_name' => $this->admin->full_name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->admin)->deleteJson("/api/expenses/{$id}")
            ->assertStatus(200);

        // Expense uses SoftDeletes — row remains with deleted_at set
        $this->assertSoftDeleted('expenses', ['id' => $id]);
    }

    // ── Summary ───────────────────────────────────────────────────────────────

    #[Test]
    public function expense_summary_returns_correct_total_for_period(): void
    {
        DB::table('expenses')->insert([
            ['expense_number' => 'EXP-S1-' . uniqid(), 'category_id' => $this->categoryId, 'title' => 'مصروف 1', 'amount' => 200.00, 'payment_method' => 'cash', 'expense_date' => now()->toDateString(), 'created_by' => $this->admin->id, 'created_by_name' => $this->admin->full_name, 'created_at' => now(), 'updated_at' => now()],
            ['expense_number' => 'EXP-S2-' . uniqid(), 'category_id' => $this->categoryId, 'title' => 'مصروف 2', 'amount' => 300.00, 'payment_method' => 'card', 'expense_date' => now()->toDateString(), 'created_by' => $this->admin->id, 'created_by_name' => $this->admin->full_name, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->actingAs($this->admin)->postJson('/api/expenses/summary', [
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_to' => now()->endOfMonth()->toDateString(),
        ]);

        $response->assertStatus(200);
        $total = $response->json('grand_total') ?? $response->json('total') ?? 0;
        $this->assertGreaterThanOrEqual(500.00, $total);
    }
}
