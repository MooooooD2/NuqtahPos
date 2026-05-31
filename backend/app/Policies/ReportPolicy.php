<?php

namespace App\Policies;

use App\Models\User;

class ReportPolicy
{
    // Any user with view_reports can access standard sales/stock/returns reports
    public function viewSales(User $user): bool
    {
        return $user->can('view_reports');
    }

    public function viewStock(User $user): bool
    {
        return $user->can('view_reports');
    }

    public function viewReturns(User $user): bool
    {
        return $user->can('view_reports');
    }

    // Financial reports require accounting permission as well
    public function viewFinancial(User $user): bool
    {
        return $user->can('view_reports') && $user->can('view_accounting');
    }

    // Cashier performance and permissions audit require admin/manager level
    public function viewCashierPerformance(User $user): bool
    {
        return $user->can('view_reports') && $user->can('manage_roles');
    }

    public function viewPermissionsAudit(User $user): bool
    {
        return $user->hasRole('admin');
    }

    // Aged receivables/payables require warehouse + reports access
    public function viewAged(User $user): bool
    {
        return $user->can('view_reports') && $user->can('view_warehouse');
    }

    // Export operations: same as viewing but rate-limited separately by route middleware
    public function export(User $user): bool
    {
        return $user->can('view_reports');
    }
}
