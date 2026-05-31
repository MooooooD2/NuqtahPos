<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Sales & Financial Reports — access control and structure.
 */
class SalesReportTest extends TestCase
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

    // ── Sales Report ─────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_get_sales_report(): void
    {
        // Controller validates start_date / end_date (not from / to)
        $this->actingAs($this->admin)
            ->postJson('/api/reports/sales', [
                'start_date' => now()->startOfMonth()->toDateString(),
                'end_date' => now()->endOfMonth()->toDateString(),
            ])->assertOk();
    }

    #[Test]
    public function cashier_cannot_access_sales_report(): void
    {
        // Blocked by permission:view_reports middleware before validation
        $this->actingAs($this->cashier)
            ->postJson('/api/reports/sales', [
                'start_date' => now()->startOfMonth()->toDateString(),
                'end_date' => now()->endOfMonth()->toDateString(),
            ])->assertForbidden();
    }

    #[Test]
    public function sales_report_requires_date_range(): void
    {
        // Validation errors use start_date / end_date (not from / to)
        $this->actingAs($this->admin)
            ->postJson('/api/reports/sales', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['start_date', 'end_date']);
    }

    // ── Stock Report ──────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_get_stock_report(): void
    {
        // stockReport() returns a plain array (no ApiResponse wrapper)
        $this->actingAs($this->admin)
            ->getJson('/api/reports/stock')
            ->assertOk();
    }

    // ── Balance Sheet ─────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_get_balance_sheet(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/reports/balance-sheet')
            ->assertOk();
    }

    #[Test]
    public function cashier_cannot_access_balance_sheet(): void
    {
        $this->actingAs($this->cashier)
            ->getJson('/api/reports/balance-sheet')
            ->assertForbidden();
    }

    // ── Inventory Valuation ───────────────────────────────────────────────────

    #[Test]
    public function admin_can_get_inventory_valuation(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/reports/inventory-valuation')
            ->assertOk();
    }

    // ── Aged Receivables/Payables ─────────────────────────────────────────────

    #[Test]
    public function admin_can_get_aged_receivables(): void
    {
        // Gate::authorize('report.aged') — admin bypasses via Gate::before()
        $this->actingAs($this->admin)
            ->getJson('/api/reports/aged-receivables')
            ->assertOk();
    }

    #[Test]
    public function admin_can_get_aged_payables(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/reports/aged-payables')
            ->assertOk();
    }

    // ── KPI Dashboard ─────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_get_kpi_dashboard(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/reports/kpi-dashboard')
            ->assertOk();
    }

    // ── Returns Report ────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_get_returns_report(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/reports/returns', [
                'start_date' => now()->startOfMonth()->toDateString(),
                'end_date' => now()->endOfMonth()->toDateString(),
            ])->assertOk();
    }

    // ── Net Profit Report ─────────────────────────────────────────────────────

    #[Test]
    public function admin_can_get_net_profit_report(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/reports/net-profit', [
                'start_date' => now()->startOfMonth()->toDateString(),
                'end_date' => now()->endOfMonth()->toDateString(),
            ])->assertOk();
    }

    // ── Best Selling ──────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_get_best_selling_products(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/reports/best-selling', [
                'start_date' => now()->startOfMonth()->toDateString(),
                'end_date' => now()->endOfMonth()->toDateString(),
            ])->assertOk();
    }

    // ── Inventory Turnover ────────────────────────────────────────────────────

    #[Test]
    public function admin_can_get_inventory_turnover(): void
    {
        // Controller validates required start_date/end_date — pass as query params on GET
        $start = now()->startOfMonth()->toDateString();
        $end = now()->endOfMonth()->toDateString();

        $this->actingAs($this->admin)
            ->getJson("/api/reports/inventory-turnover?start_date={$start}&end_date={$end}")
            ->assertOk();
    }

    // ── Waste Ratio ───────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_get_waste_ratio_report(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/reports/waste-ratio')
            ->assertOk();
    }

    // ── Permissions Audit ─────────────────────────────────────────────────────

    #[Test]
    public function admin_can_get_permissions_audit(): void
    {
        // Gate::authorize('report.permissions-audit') — admin bypasses via Gate::before()
        $this->actingAs($this->admin)
            ->getJson('/api/reports/permissions-audit')
            ->assertOk();
    }
}
