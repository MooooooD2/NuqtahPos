<?php

namespace App\Policies;

use App\Models\CustomerGroup;
use App\Models\User;

class CustomerGroupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_customers');
    }

    public function view(User $user, CustomerGroup $group): bool
    {
        return $user->can('view_customers');
    }

    public function create(User $user): bool
    {
        return $user->can('add_customer');
    }

    public function update(User $user, CustomerGroup $group): bool
    {
        return $user->can('edit_customer');
    }

    public function delete(User $user, CustomerGroup $group): bool
    {
        return $user->can('delete_customer');
    }
}
