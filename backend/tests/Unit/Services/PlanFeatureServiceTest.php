<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\PlanFeatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use stdClass;
use Tests\TestCase;

/**
 * Unit tests for PlanFeatureService — plan-based feature gating.
 */
class PlanFeatureServiceTest extends TestCase
{
    use RefreshDatabase;

    // ── Basic tier defaults ────────────────────────────────────────────────

    #[Test]
    public function basic_plan_has_core_pos_features(): void
    {
        $this->mockTenantPlan('basic');

        $this->assertTrue(PlanFeatureService::has('pos'));
        $this->assertTrue(PlanFeatureService::has('inventory'));
        $this->assertTrue(PlanFeatureService::has('customers'));
    }

    #[Test]
    public function basic_plan_lacks_advanced_features(): void
    {
        $this->mockTenantPlan('basic');

        $this->assertFalse(PlanFeatureService::has('hr_module'));
        $this->assertFalse(PlanFeatureService::has('white_label'));
        $this->assertFalse(PlanFeatureService::has('crm'));
        $this->assertFalse(PlanFeatureService::has('franchise'));
    }

    #[Test]
    public function pro_plan_has_all_basic_plus_advanced_ops_features(): void
    {
        $this->mockTenantPlan('pro');

        $this->assertTrue(PlanFeatureService::has('pos'));
        $this->assertTrue(PlanFeatureService::has('accounting'));
        $this->assertTrue(PlanFeatureService::has('crm'));
        $this->assertTrue(PlanFeatureService::has('kitchen_display'));
        $this->assertTrue(PlanFeatureService::has('qr_ordering'));
        $this->assertTrue(PlanFeatureService::has('cashback'));
        $this->assertTrue(PlanFeatureService::has('promotions'));
    }

    #[Test]
    public function pro_plan_lacks_enterprise_only_features(): void
    {
        $this->mockTenantPlan('pro');

        $this->assertFalse(PlanFeatureService::has('hr_module'));
        $this->assertFalse(PlanFeatureService::has('payroll'));
        $this->assertFalse(PlanFeatureService::has('white_label'));
        $this->assertFalse(PlanFeatureService::has('franchise'));
        $this->assertFalse(PlanFeatureService::has('ai_forecasting'));
    }

    #[Test]
    public function enterprise_plan_has_all_features(): void
    {
        $this->mockTenantPlan('enterprise');

        $enterpriseOnly = [
            'hr_module', 'payroll', 'shift_management', 'white_label',
            'currencies', 'franchise', 'ai_forecasting', 'budget_vs_actual',
        ];

        foreach ($enterpriseOnly as $feature) {
            $this->assertTrue(
                PlanFeatureService::has($feature),
                "Enterprise plan should include [{$feature}]",
            );
        }
    }

    #[Test]
    public function unknown_feature_always_returns_false(): void
    {
        $this->mockTenantPlan('enterprise');

        $this->assertFalse(PlanFeatureService::has('does_not_exist_xyz'));
    }

    #[Test]
    public function features_returns_array(): void
    {
        $this->mockTenantPlan('pro');

        $features = PlanFeatureService::features();

        $this->assertIsArray($features);
        $this->assertNotEmpty($features);
    }

    #[Test]
    public function check_throws_403_for_missing_feature(): void
    {
        $this->mockTenantPlan('basic');

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        PlanFeatureService::check('hr_module');
    }

    #[Test]
    public function check_passes_silently_for_present_feature(): void
    {
        $this->mockTenantPlan('basic');

        // Should not throw
        PlanFeatureService::check('pos');

        $this->assertTrue(true); // reached here without exception
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * Override the tenant plan in the tenancy context for the duration of the test.
     *
     * stancl/tenancy exposes a public `$tenant` property on its singleton.
     * We assign a plain stdClass with `plan = $planId` directly so that
     * PlanFeatureService::features() resolves the correct tier without any
     * real database query.
     */
    private function mockTenantPlan(string $planId): void
    {
        // Clear any cached plan features from previous test runs.
        foreach (['basic', 'pro', 'enterprise', $planId] as $id) {
            \Illuminate\Support\Facades\Cache::forget("plan_features:{$id}");
        }

        // Build a minimal fake tenant and inject it into the Tenancy singleton.
        $fakeTenant = new stdClass;
        $fakeTenant->id = 'test-tenant';
        $fakeTenant->plan = $planId;

        // tenancy() resolves \Stancl\Tenancy\Tenancy::class from the container.
        // Its $tenant property is public, so we can assign directly.
        tenancy()->tenant = $fakeTenant;
    }
}
