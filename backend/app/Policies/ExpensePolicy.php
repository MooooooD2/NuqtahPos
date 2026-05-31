<?php

namespace App\Policies;

use App\Models\User;

class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_expenses');
    }

    public function create(User $user): bool
    {
        return $user->can('add_expense');
    }

    public function update(User $user): bool
    {
        return $user->can('edit_expense');
    }

    public function delete(User $user): bool
    {
        return $user->can('delete_expense');
    }
}
