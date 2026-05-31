<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a structured `feature_flags` JSON column to the plans table so each
 * plan can declare exactly which features it unlocks.  Falls back to
 * PlanFeatureService::PLAN_DEFAULTS when the column is null.
 */
return new class extends Migration
{
    // plans table lives on the central (mysql) connection
    protected $connection = 'mysql';

    public function up(): void
    {
        // Guard against re-running (e.g. when tenant bootstrappers run central migrations).
        if (Schema::connection($this->connection)->hasColumn('plans', 'feature_flags')) {
            return;
        }

        Schema::connection($this->connection)->table('plans', function (Blueprint $table) {
            $table->json('feature_flags')->nullable()->after('features')
                ->comment('Structured list of feature keys enabled for this plan tier');
        });

        // Back-fill defaults from PlanFeatureService constants
        $defaults = [
            'basic' => [
                'pos', 'inventory', 'returns', 'expenses',
                'reports_basic', 'customers',
            ],
            'pro' => [
                'pos', 'inventory', 'returns', 'expenses',
                'reports_basic', 'reports_advanced', 'customers',
                'customer_groups', 'promotions', 'cashback', 'accounting',
                'purchase_orders', 'multi_warehouse', 'whatsapp',
                'kitchen_display', 'qr_ordering', 'kiosk', 'crm',
                'dynamic_pricing', 'pricing_rules', 'waste_tracking',
            ],
            'enterprise' => [
                'pos', 'inventory', 'returns', 'expenses',
                'reports_basic', 'reports_advanced', 'reports_financial',
                'customers', 'customer_groups', 'promotions', 'cashback',
                'accounting', 'purchase_orders', 'multi_warehouse', 'multi_branch',
                'whatsapp', 'kitchen_display', 'qr_ordering', 'kiosk', 'crm',
                'dynamic_pricing', 'pricing_rules', 'waste_tracking',
                'hr_module', 'payroll', 'shift_management', 'white_label',
                'currencies', 'franchise', 'ai_forecasting', 'budget_vs_actual',
                'device_sessions', 'backup_monitor',
            ],
        ];

        foreach ($defaults as $planId => $flags) {
            \DB::connection($this->connection)
                ->table('plans')
                ->where('id', $planId)
                ->update(['feature_flags' => json_encode($flags)]);
        }
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('plans', function (Blueprint $table) {
            $table->dropColumn('feature_flags');
        });
    }
};
