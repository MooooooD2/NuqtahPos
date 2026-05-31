<?php

namespace App\Policies;

use App\Models\Budget;
use App\Models\User;

class BudgetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_financial_reports');
    }

    public function view(User $user, Budget $budget): bool
    {
        return $user->can('view_financial_reports');
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'manager']);
    }

    public function update(User $user, Budget $budget): bool
    {
        return $user->hasAnyRole(['admin', 'manager']);
    }

    public function delete(User $user, Budget $budget): bool
    {
        return $user->hasRole('admin');
    }
}
