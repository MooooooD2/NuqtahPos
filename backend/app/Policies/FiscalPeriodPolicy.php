<?php

namespace App\Policies;

use App\Models\FiscalPeriod;
use App\Models\User;

class FiscalPeriodPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_financial_reports');
    }

    public function view(User $user, FiscalPeriod $period): bool
    {
        return $user->can('view_financial_reports');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, FiscalPeriod $period): bool
    {
        return $user->hasRole('admin');
    }

    public function close(User $user, FiscalPeriod $period): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, FiscalPeriod $period): bool
    {
        return false;
    }
}
