<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\User;

class AccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_accounting');
    }

    public function create(User $user): bool
    {
        return $user->can('manage_accounts');
    }

    public function update(User $user, Account $account): bool
    {
        return $user->can('manage_accounts');
    }

    public function delete(User $user, Account $account): bool
    {
        return $user->can('manage_accounts');
    }
}
